<?php

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

/**
 * Format employee ID to standard EMP-XXXX format
 * @param int|string $employee_id The numeric employee ID
 * @param int $padding Number of digits to pad (default 4 for EMP-0001 format)
 * @return string Formatted employee ID (e.g., "EMP-0001")
 */
function formatEmployeeId($employee_id, $padding = 4) {
    if (empty($employee_id)) {
        return 'N/A';
    }
    // Extract numeric part if already has EMP- prefix
    if (is_string($employee_id) && stripos($employee_id, 'EMP-') === 0) {
        return strtoupper($employee_id);
    }
    // Convert to integer and format
    $numericId = (int) preg_replace('/[^0-9]/', '', $employee_id);
    return 'EMP-' . str_pad($numericId, $padding, '0', STR_PAD_LEFT);
}

/**
 * Parse employee ID from EMP-XXXX format to numeric
 * @param string $formatted_id The formatted employee ID (e.g., "EMP-0001" or "1")
 * @return int|null Numeric employee ID or null if invalid
 */
function parseEmployeeId($formatted_id) {
    if (empty($formatted_id)) {
        return null;
    }
    // Remove EMP- prefix if present and get numeric value
    $numericId = preg_replace('/^EMP-?/i', '', $formatted_id);
    $numericId = (int) preg_replace('/[^0-9]/', '', $numericId);
    return $numericId > 0 ? $numericId : null;
}

function loginUser($conn, $username, $password) {
    try {
       
        $sql = "SELECT ua.user_id, ua.username, ua.password_hash, ua.role, ua.employee_id,
                       e.first_name, e.last_name
                FROM user_account ua
                LEFT JOIN employee e ON ua.employee_id = e.employee_id
                WHERE ua.username = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        
        if (!$user) {
            logLoginAttempt($conn, $username, false, 'User not found');
            return [
                "success" => false, 
                "message" => "Invalid username or password"
            ];
        }
        
        
        if (!password_verify($password, $user['password_hash'])) {
            logLoginAttempt($conn, $username, false, 'Invalid password');
            return [
                "success" => false, 
                "message" => "Invalid username or password"
            ];
        }
        
        
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = trim(($user['first_name'] ?? 'Justin') . ' ' . ($user['last_name'] ?? 'Rivera'));
        $_SESSION['is_admin'] = ($user['role'] === 'Admin');
        $_SESSION['logged_in'] = true;
        
        session_regenerate_id(true);
        
        
        $updateSql = "UPDATE user_account SET last_login = NOW() WHERE user_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([$user['user_id']]);
        
        logLoginAttempt($conn, $username, true);
        
        return [
            "success" => true, 
            "user" => $user
        ];
        
    } catch (Exception $e) {
        error_log("Login Error: " . $e->getMessage());
        return [
            "success" => false, 
            "message" => "System error occurred"
        ];
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['logged_in']) && 
           $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Check if current day is a weekend (Saturday or Sunday)
 * Uses Asia/Manila timezone for accurate checking
 * @return bool True if Saturday (6) or Sunday (0)
 */
function isWeekend() {
    $currentDate = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $dayOfWeek = (int)$currentDate->format('w'); // 0=Sunday, 6=Saturday
    return ($dayOfWeek === 0 || $dayOfWeek === 6);
}

/**
 * Get the name of the current day for display purposes
 * @return string Day name (e.g., "Saturday", "Sunday")
 */
function getCurrentDayName() {
    $currentDate = new DateTime('now', new DateTimeZone('Asia/Manila'));
    return $currentDate->format('l'); // Full day name
}

function recordTimeIn($conn, $employee_id) {
    try {
        // Block time tracking on weekends
        if (isWeekend()) {
            return [
                "success" => false, 
                "message" => "Time tracking is not available on weekends. Today is " . getCurrentDayName() . ".",
                "is_weekend" => true
            ];
        }
        
        // Check if employee already has a time-in today without time-out
        $checkSql = "SELECT attendance_id FROM attendance 
                     WHERE employee_id = ? 
                     AND DATE(time_in) = CURDATE() 
                     AND time_out IS NULL";
        
        $stmt = $conn->prepare($checkSql);
        $stmt->execute([$employee_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            return [
                "success" => false, 
                "message" => "You are already clocked in. Please clock out first."
            ];
        }
        
        // Get current Philippine time
        $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
        
        // Insert new time-in record
        $sql = "INSERT INTO attendance (employee_id, date, time_in, status) 
                VALUES (?, ?, ?, 'Present')";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $employee_id,
            $current_time->format('Y-m-d'),
            $current_time->format('Y-m-d H:i:s')
        ]);
        
        return [
            "success" => true, 
            "message" => "Clocked in successfully at " . $current_time->format('h:i A')
        ];
        
    } catch (Exception $e) {
        error_log("Time-in Error: " . $e->getMessage());
        return [
            "success" => false, 
            "message" => "Failed to record time-in"
        ];
    }
}

function recordTimeOut($conn, $employee_id) {
    try {
        // Block time tracking on weekends
        if (isWeekend()) {
            return [
                "success" => false, 
                "message" => "Time tracking is not available on weekends. Today is " . getCurrentDayName() . ".",
                "is_weekend" => true
            ];
        }
        
        // Find the active time-in record (no time-out yet)
        $checkSql = "SELECT attendance_id, time_in 
                     FROM attendance 
                     WHERE employee_id = ? 
                     AND time_out IS NULL 
                     ORDER BY time_in DESC 
                     LIMIT 1";
        
        $stmt = $conn->prepare($checkSql);
        $stmt->execute([$employee_id]);
        $active_attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$active_attendance) {
            return [
                "success" => false, 
                "message" => "No active clock-in found. Please clock in first."
            ];
        }
        
        // Get current Philippine time
        $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
        
        // Calculate hours worked BEFORE updating database
        $time_in = new DateTime($active_attendance['time_in'], new DateTimeZone('Asia/Manila'));
        $time_out = clone $current_time;
        
        // Get total seconds difference
        $total_seconds = $time_out->getTimestamp() - $time_in->getTimestamp();
        $total_minutes = floor($total_seconds / 60);
        $hours = floor($total_minutes / 60);
        $minutes = $total_minutes % 60;
        
        // Calculate total_hours as decimal (e.g., 8.5 for 8 hours 30 minutes)
        $total_hours = round($total_minutes / 60, 2);
        
        // Handle edge case for very short duration
        if ($total_seconds < 60) {
            $total_hours = 0;
        }
        
        // CRITICAL FIX: Update time_out AND total_hours in database
        // This ensures accounting system can read accurate hours worked
        $updateSql = "UPDATE attendance 
                     SET time_out = ?, 
                         total_hours = ? 
                     WHERE attendance_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([
            $current_time->format('Y-m-d H:i:s'),
            $total_hours,
            $active_attendance['attendance_id']
        ]);
        
        // Return success message with hours worked
        if ($total_seconds < 60) {
            return [
                "success" => true, 
                "message" => sprintf(
                    "Clocked out at %s. You worked for less than a minute.",
                    $current_time->format('h:i A')
                ),
                "hours_worked" => 0
            ];
        }
        
        return [
            "success" => true, 
            "message" => sprintf(
                "Clocked out at %s. You worked for %d hour%s and %d minute%s.",
                $current_time->format('h:i A'),
                $hours,
                $hours != 1 ? 's' : '',
                $minutes,
                $minutes != 1 ? 's' : ''
            ),
            "hours_worked" => $total_hours
        ];
        
    } catch (Exception $e) {
        error_log("Time-out Error: " . $e->getMessage());
        return [
            "success" => false, 
            "message" => "Failed to record time-out"
        ];
    }
}

function logLoginAttempt($conn, $username, $success, $reason = null) {
    try {
        $sql = "INSERT INTO login_attempts (username, ip_address, success, failure_reason) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $username,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $success ? 1 : 0,
            $reason
        ]);
    } catch (Exception $e) {
        error_log("Log Attempt Error: " . $e->getMessage());
    }
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin';
}

function isHRManager() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'HR Manager';
}

/**
 * Check if current user is a Manager
 * @deprecated Manager role removed - use isSupervisor() instead
 * @return bool Always returns false
 */
function isManager() {
    return false; // Manager role deprecated - all permissions moved to Supervisor
}

/**
 * Check if current user is a Supervisor
 * Supervisors can approve leave requests for employees in their department
 * @return bool
 */
function isSupervisor() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Supervisor';
}

/**
 * Check if current user has any management role (Admin, HR Manager, or Supervisor)
 * @return bool
 */
function hasManagementRole() {
    return isAdmin() || isHRManager() || isSupervisor();
}

/**
 * Get the department ID of the current user (from user_account.managed_department_id or employee.department_id)
 * @param PDO $conn Database connection
 * @return int|null Department ID or null if not assigned
 */
function getUserDepartmentId($conn) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['employee_id'])) {
        return null;
    }
    
    // For admin users, check if they have a managed department
    if (isset($_SESSION['user_id'])) {
        try {
            $sql = "SELECT ua.managed_department_id, e.department_id 
                    FROM user_account ua
                    LEFT JOIN employee e ON ua.employee_id = e.employee_id
                    WHERE ua.user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Prefer managed_department_id, fall back to employee's department
            return $result['managed_department_id'] ?? $result['department_id'] ?? null;
        } catch (Exception $e) {
            error_log("Error getting user department: " . $e->getMessage());
            return null;
        }
    }
    
    // For employees, get their department from the employee table
    if (isset($_SESSION['employee_id'])) {
        try {
            $sql = "SELECT department_id FROM employee WHERE employee_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_SESSION['employee_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['department_id'] ?? null;
        } catch (Exception $e) {
            error_log("Error getting employee department: " . $e->getMessage());
            return null;
        }
    }
    
    return null;
}

/**
 * Check if the current user can approve leaves for a specific employee
 * - Admins and HR Managers can approve all leaves
 * - Supervisors can approve leaves for employees in their department
 * @param PDO $conn Database connection
 * @param int $employee_id The employee ID whose leave is being approved
 * @return bool
 */
function canApproveLeavesForEmployee($conn, $employee_id) {
    // Admins and HR Managers can approve all leaves
    if (isAdmin() || isHRManager()) {
        return true;
    }
    
    // Supervisors can approve leaves for their department
    if (isSupervisor()) {
        $userDeptId = getUserDepartmentId($conn);
        if ($userDeptId) {
            try {
                $sql = "SELECT department_id FROM employee WHERE employee_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$employee_id]);
                $emp = $stmt->fetch(PDO::FETCH_ASSOC);
                
                return $emp && $emp['department_id'] == $userDeptId;
            } catch (Exception $e) {
                error_log("Error checking leave approval permission: " . $e->getMessage());
                return false;
            }
        }
    }
    
    return false;
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Require Supervisor or higher role
 */
function requireManager() {
    if (!isAdmin() && !isHRManager() && !isSupervisor()) {
        header('Location: dashboard.php');
        exit;
    }
}

function canEdit() {
    return isAdmin();
}

function canManageEmployees() {
    return isAdmin() || isHRManager();
}

function canManageLeaves() {
    // Admins, HR Managers, and Supervisors can manage leaves
    // (Supervisors only for their department - enforced separately)
    return isAdmin() || isHRManager() || isSupervisor();
}

function canManageRecruitment() {
    return isAdmin() || isHRManager();
}

function canViewLogs() {
    return isAdmin();
}

/**
 * Check if current user can VIEW employee data (includes supervisors with read-only)
 * Supervisors can view their department's employee data but not edit
 * @return bool
 */
function canViewEmployeeData() {
    return isAdmin() || isHRManager() || isSupervisor();
}

/**
 * Check if current user can EDIT employee data (supervisors CANNOT edit)
 * Only Admins and HR Managers can edit employee records
 * @return bool
 */
function canEditEmployeeData() {
    return isAdmin() || isHRManager();
}

/**
 * Check if current user is supervisor with VIEW-ONLY access
 * Use this to disable edit buttons/forms for supervisors
 * @return bool
 */
function supervisorViewOnly() {
    return isSupervisor() && !isAdmin() && !isHRManager();
}

/**
 * Check if current user can manage user roles
 * Only Admins can assign/change roles
 * @return bool
 */
function canManageRoles() {
    return isAdmin();
}

/**
 * Get all employees with their current roles and departments for role management
 * @param PDO $conn Database connection
 * @return array List of employees with role info
 */
function getAllEmployeesWithRoles($conn) {
    try {
        $sql = "SELECT 
                    e.employee_id,
                    CONCAT(e.first_name, ' ', e.last_name) as full_name,
                    e.email,
                    e.department_id,
                    d.department_name,
                    p.position_title,
                    e.employment_status,
                    ua.user_id,
                    ua.username,
                    ua.role,
                    ua.managed_department_id,
                    md.department_name as managed_department_name
                FROM employee e
                LEFT JOIN department d ON e.department_id = d.department_id
                LEFT JOIN position p ON e.position_id = p.position_id
                LEFT JOIN user_account ua ON e.employee_id = ua.employee_id
                LEFT JOIN department md ON ua.managed_department_id = md.department_id
                WHERE e.employment_status = 'Active'
                ORDER BY e.last_name, e.first_name";
        
        return fetchAll($conn, $sql);
    } catch (Exception $e) {
        error_log("Error fetching employees with roles: " . $e->getMessage());
        return [];
    }
}

/**
 * Update user role and department assignment
 * @param PDO $conn Database connection
 * @param int $employee_id Employee ID
 * @param string $new_role New role to assign
 * @param int|null $managed_department_id Department ID for Managers/Supervisors
 * @param int $changed_by User ID who made the change
 * @return array Result with success status and message
 */
function updateUserRole($conn, $employee_id, $new_role, $managed_department_id, $changed_by) {
    try {
        $valid_roles = ['Admin', 'HR Manager', 'Manager', 'Supervisor', 'Employee'];
        if (!in_array($new_role, $valid_roles)) {
            return ['success' => false, 'message' => 'Invalid role'];
        }
        
        // Check if user_account exists for this employee
        $existing = fetchOne($conn, "SELECT user_id, role, managed_department_id FROM user_account WHERE employee_id = ?", [$employee_id]);
        
        if ($existing) {
            // Update existing user account
            $old_role = $existing['role'];
            $old_dept = $existing['managed_department_id'];
            
            $sql = "UPDATE user_account SET role = ?, managed_department_id = ? WHERE employee_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$new_role, $managed_department_id, $employee_id]);
            
            // Log the role change
            logRoleChange($conn, $existing['user_id'], $changed_by, $old_role, $new_role, $old_dept, $managed_department_id);
            
            return ['success' => true, 'message' => "Role updated to $new_role"];
        } else {
            // Create new user account for this employee
            $employee = fetchOne($conn, "SELECT first_name, last_name FROM employee WHERE employee_id = ?", [$employee_id]);
            if (!$employee) {
                return ['success' => false, 'message' => 'Employee not found'];
            }
            
            // Generate username from employee name
            $username = strtolower(substr($employee['first_name'], 0, 1) . $employee['last_name']);
            $username = preg_replace('/[^a-z0-9]/', '', $username);
            
            // Check for duplicate username and append number if needed
            $baseUsername = $username;
            $counter = 1;
            while (fetchOne($conn, "SELECT user_id FROM user_account WHERE username = ?", [$username])) {
                $username = $baseUsername . $counter;
                $counter++;
            }
            
            // Default password hash for 'password' - should be changed on first login
            $default_password = password_hash('password', PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO user_account (employee_id, username, password_hash, role, managed_department_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$employee_id, $username, $default_password, $new_role, $managed_department_id]);
            
            $new_user_id = $conn->lastInsertId();
            
            // Log the role assignment
            logRoleChange($conn, $new_user_id, $changed_by, null, $new_role, null, $managed_department_id);
            
            return ['success' => true, 'message' => "Account created for $username with role $new_role. Default password: 'password'"];
        }
    } catch (Exception $e) {
        error_log("Error updating user role: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update role: ' . $e->getMessage()];
    }
}

/**
 * Log role change for audit trail
 */
function logRoleChange($conn, $user_id, $changed_by, $old_role, $new_role, $old_dept, $new_dept) {
    try {
        $sql = "INSERT INTO role_change_log (user_id, changed_by, old_role, new_role, old_department_id, new_department_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $changed_by, $old_role, $new_role, $old_dept, $new_dept]);
    } catch (Exception $e) {
        // Table might not exist yet, log but don't fail
        error_log("Role change log failed (table may not exist): " . $e->getMessage());
    }
}

function loginEmployee($conn, $employee_id_input, $employee_name) {
    try {
        // Parse EMP-XXX format or numeric ID
        $employee_id = null;
        if (preg_match('/^EMP-(\d+)$/i', $employee_id_input, $matches)) {
            $employee_id = (int)$matches[1];
        } elseif (is_numeric($employee_id_input)) {
            $employee_id = (int)$employee_id_input;
        } else {
            return [
                "success" => false,
                "message" => "Invalid employee ID format. Use EMP-XXX or numeric ID."
            ];
        }

        // Validate employee exists and name matches
        $sql = "SELECT employee_id, first_name, last_name, employment_status 
                FROM employee 
                WHERE employee_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            logLoginAttempt($conn, 'EMP-' . str_pad($employee_id, 3, '0', STR_PAD_LEFT), false, 'Employee not found');
            return [
                "success" => false,
                "message" => "Employee ID not found"
            ];
        }

        // Check if employee is active
        if ($employee['employment_status'] !== 'Active') {
            logLoginAttempt($conn, 'EMP-' . str_pad($employee_id, 3, '0', STR_PAD_LEFT), false, 'Employee not active');
            return [
                "success" => false,
                "message" => "Employee account is not active"
            ];
        }

        // Validate employee name (case-insensitive, partial match allowed)
        $full_name = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
        $input_name = trim($employee_name);
        
        if (empty($input_name)) {
            return [
                "success" => false,
                "message" => "Employee name is required"
            ];
        }

        // Check if input name matches (case-insensitive, allows partial match)
        $name_match = stripos($full_name, $input_name) !== false || stripos($input_name, $full_name) !== false;
        
        if (!$name_match) {
            logLoginAttempt($conn, 'EMP-' . str_pad($employee_id, 3, '0', STR_PAD_LEFT), false, 'Name mismatch');
            return [
                "success" => false,
                "message" => "Employee name does not match"
            ];
        }

        // Set employee session
        $_SESSION['employee_id'] = $employee['employee_id'];
        $_SESSION['employee_name'] = $full_name;
        $_SESSION['employee_logged_in'] = true;
        $_SESSION['user_type'] = 'employee';
        $_SESSION['logged_in'] = true;
        
        session_regenerate_id(true);
        
        // Automatically record time-in if not already recorded today
        try {
            $checkSql = "SELECT attendance_id FROM attendance 
                         WHERE employee_id = ? 
                         AND DATE(time_in) = CURDATE() 
                         AND time_out IS NULL";
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$employee_id]);
            $existing_attendance = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing_attendance) {
                // Record time-in automatically
                $timeInResult = recordTimeIn($conn, $employee_id);
                if (!$timeInResult['success']) {
                    // Log but don't block login if attendance recording fails
                    error_log("Auto-attendance recording failed for employee $employee_id: " . ($timeInResult['message'] ?? 'Unknown error'));
                }
            }
        } catch (Exception $e) {
            // Log but don't block login if attendance check fails
            error_log("Auto-attendance check failed for employee $employee_id: " . $e->getMessage());
        }
        
        logLoginAttempt($conn, 'EMP-' . str_pad($employee_id, 3, '0', STR_PAD_LEFT), true);
        
        return [
            "success" => true,
            "employee" => $employee
        ];
        
    } catch (Exception $e) {
        error_log("Employee Login Error: " . $e->getMessage());
        return [
            "success" => false,
            "message" => "System error occurred"
        ];
    }
}

function isEmployee() {
    return isset($_SESSION['employee_logged_in']) && 
           $_SESSION['employee_logged_in'] === true &&
           isset($_SESSION['user_type']) &&
           $_SESSION['user_type'] === 'employee';
}

function requireEmployee() {
    if (!isEmployee()) {
        header('Location: ../index.php');
        exit;
    }
}

function logoutUser() {
    // Record time-out if employee is logged in
    if (isset($_SESSION['employee_logged_in']) && $_SESSION['employee_logged_in'] === true && isset($_SESSION['employee_id'])) {
        try {
            require_once __DIR__ . '/../config/database.php';
            global $conn;
            if (isset($conn)) {
                recordTimeOut($conn, $_SESSION['employee_id']);
            }
        } catch (Exception $e) {
            error_log("Error recording time-out on logout: " . $e->getMessage());
        }
    }
    
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
    header('Location: index.php');
    exit;
}
?>