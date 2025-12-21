<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/auth.php';

// AJAX handler for employee attendance history
if (isset($_GET['ajax']) && $_GET['ajax'] === 'history') {
    header('Content-Type: application/json');
    
    $employee_id = $_GET['employee_id'] ?? null;
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    if (!$employee_id) {
        echo json_encode(['error' => 'Employee ID required']);
        exit;
    }
    
    try {
        $records = [];
        $stats = ['present' => 0, 'absent' => 0, 'leave' => 0, 'restDays' => 0];
        
        // Get all dates in range
        $current = strtotime($start_date);
        $endTs = strtotime($end_date);
        
        while ($current <= $endTs) {
            $dateStr = date('Y-m-d', $current);
            $dayNum = date('N', $current); // 1=Mon, 7=Sun
            $isWeekend = ($dayNum >= 6);
            $dayName = date('l', $current);
            
            $record = [
                'date' => date('M d, Y', $current),
                'dayName' => $dayName,
                'status' => 'Absent',
                'timeIn' => null,
                'timeOut' => null,
                'hours' => null
            ];
            
            if ($isWeekend) {
                $record['status'] = 'Rest Day';
                $stats['restDays']++;
            } else {
                // Check attendance
                $attSql = "SELECT time_in, time_out, total_hours, status FROM attendance WHERE employee_id = ? AND DATE(date) = ?";
                $attStmt = $conn->prepare($attSql);
                $attStmt->execute([$employee_id, $dateStr]);
                $att = $attStmt->fetch();
                
                if ($att) {
                    $record['status'] = 'Present';
                    $record['timeIn'] = $att['time_in'] ? date('h:i A', strtotime($att['time_in'])) : null;
                    $record['timeOut'] = $att['time_out'] ? date('h:i A', strtotime($att['time_out'])) : null;
                    $record['hours'] = $att['total_hours'] ?? null;
                    $stats['present']++;
                } else {
                    // Check leave
                    $leaveSql = "SELECT lt.leave_name FROM leave_request lr 
                                 LEFT JOIN leave_type lt ON lr.leave_type_id = lt.leave_type_id
                                 WHERE lr.employee_id = ? AND UPPER(TRIM(lr.status)) = 'APPROVED'
                                 AND ? >= lr.start_date AND ? <= lr.end_date";
                    $leaveStmt = $conn->prepare($leaveSql);
                    $leaveStmt->execute([$employee_id, $dateStr, $dateStr]);
                    $leave = $leaveStmt->fetch();
                    
                    if ($leave) {
                        $record['status'] = 'Leave';
                        $record['hours'] = $leave['leave_name'];
                        $stats['leave']++;
                    } else {
                        // Only count absent for past dates
                        if (strtotime($dateStr) <= strtotime(date('Y-m-d'))) {
                            $stats['absent']++;
                        } else {
                            $record['status'] = 'Upcoming';
                        }
                    }
                }
            }
            
            $records[] = $record;
            $current = strtotime('+1 day', $current);
        }
        
        echo json_encode(['records' => $records, 'stats' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to fetch data: ' . $e->getMessage()]);
    }
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'time_out':
                if (!isAdmin()) {
                    $message = "NO PERMISSIONS: You do not have permission to perform this action.";
                    $messageType = "error";
                    break;
                }
                try {
                    $sql = "UPDATE attendance 
                            SET time_out = NOW()
                            WHERE attendance_id = ?";

                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute([$_POST['attendance_id']]);

                    if ($success) {
                        if (isset($logger)) {
                            $logger->info(
                                'ATTENDANCE',
                                'Time-out recorded',
                                "Attendance ID: {$_POST['attendance_id']}"
                            );
                        }
                        $message = "Time-out recorded successfully!";
                        $messageType = "success";
                    } else {
                        throw new Exception("Failed to record time-out");
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('ATTENDANCE', 'Failed to record time-out', $e->getMessage());
                    }
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

$dedupe_sql = "DELETE a1 FROM attendance a1
               INNER JOIN attendance a2 
               WHERE a1.employee_id = a2.employee_id 
               AND DATE(a1.date) = DATE(a2.date)
               AND a1.attendance_id > a2.attendance_id";
try {
    $conn->exec($dedupe_sql);
} catch (Exception $e) {

}

$date_filter = $_GET['date'] ?? date('Y-m-d');
$position_filter = $_GET['position'] ?? '';
$department_filter = $_GET['department'] ?? '';

// Weekend detection function (renamed to avoid conflict with auth.php)
function isDateWeekend($date) {
    $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 7=Sunday
    return $dayOfWeek >= 6; // 6=Saturday, 7=Sunday
}

$isRestDay = isDateWeekend($date_filter);
$dayName = date('l', strtotime($date_filter)); // Get day name (Saturday, Sunday, etc.)

// Department-scoped filtering for Supervisors
$managerDeptFilter = "";
$managerDeptId = null;
$debugInfo = "";
if (isSupervisor() && !isAdmin() && !isHRManager()) {
    $managerDeptId = getUserDepartmentId($conn);
    // Get department name for debug
    if ($managerDeptId) {
        $deptResult = fetchOne($conn, "SELECT department_name FROM department WHERE department_id = ?", [$managerDeptId]);
        $deptName = $deptResult['department_name'] ?? 'Unknown';
        $debugInfo = "Filtering: {$deptName} (ID: {$managerDeptId})";
    } else {
        $debugInfo = "WARNING: No department assigned! Please run migration.";
    }
}

// Query to get attendance records
$sql = "SELECT a.attendance_id, a.employee_id as emp_id, a.date, a.time_in, a.time_out, a.total_hours, a.status, a.remarks,
        e.first_name, e.last_name,
        d.department_name, 
        p.position_title,
        NULL as leave_request_id,
        NULL as leave_status,
        NULL as leave_name,
        'attendance' as record_type
        FROM attendance a
        INNER JOIN employee e ON a.employee_id = e.employee_id
        LEFT JOIN department d ON e.department_id = d.department_id
        LEFT JOIN position p ON e.position_id = p.position_id
        WHERE DATE(a.date) = ?";

$params = [$date_filter];

// Manager department scope filter
if ($managerDeptId) {
    $sql .= " AND e.department_id = ?";
    $params[] = $managerDeptId;
}

if ($position_filter) {
    $sql .= " AND p.position_title LIKE ?";
    $params[] = "%$position_filter%";
}

if ($department_filter) {
    $sql .= " AND d.department_name LIKE ?";
    $params[] = "%$department_filter%";
}

// UNION with employees on approved leave who don't have attendance records
$sql .= " UNION
        SELECT NULL as attendance_id, e.employee_id as emp_id, ? as date, NULL as time_in, NULL as time_out, NULL as total_hours, 'Leave' as status, NULL as remarks,
        e.first_name, e.last_name,
        d.department_name,
        p.position_title,
        lr.leave_request_id,
        lr.status as leave_status,
        lt.leave_name,
        'leave' as record_type
        FROM employee e
        INNER JOIN leave_request lr ON e.employee_id = lr.employee_id
        LEFT JOIN department d ON e.department_id = d.department_id
        LEFT JOIN position p ON e.position_id = p.position_id
        LEFT JOIN leave_type lt ON lr.leave_type_id = lt.leave_type_id
        WHERE e.employment_status = 'Active'
        AND UPPER(TRIM(lr.status)) = 'APPROVED'
        AND CAST(? AS DATE) >= CAST(lr.start_date AS DATE)
        AND CAST(? AS DATE) <= CAST(lr.end_date AS DATE)
        AND e.employee_id NOT IN (
            SELECT DISTINCT employee_id FROM attendance WHERE DATE(date) = CAST(? AS DATE)
        )";

$params[] = $date_filter;
$params[] = $date_filter;
$params[] = $date_filter;
$params[] = $date_filter;

// Manager department scope filter for leave union
if ($managerDeptId) {
    $sql .= " AND e.department_id = ?";
    $params[] = $managerDeptId;
}

if ($position_filter) {
    $sql .= " AND p.position_title LIKE ?";
    $params[] = "%$position_filter%";
}

if ($department_filter) {
    $sql .= " AND d.department_name LIKE ?";
    $params[] = "%$department_filter%";
}

$sql .= " ORDER BY record_type DESC, time_in DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();
} catch (Exception $e) {
    $attendance_records = [];
    $message = "Database Error: " . $e->getMessage();
    $messageType = "error";
    // Log error for debugging
    error_log("Attendance query error: " . $e->getMessage());
    if (isset($logger)) {
        $logger->error('ATTENDANCE', 'Failed to fetch attendance records', $e->getMessage());
    }
}

function calculateWorkHours($time_in, $time_out)
{
    if (!$time_in) return '-';

    $start = new DateTime($time_in);
    $end = $time_out ? new DateTime($time_out) : new DateTime();

    $interval = $start->diff($end);

    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;

    if ($hours == 0 && $minutes == 0) {
        $seconds = $interval->s;
        return $seconds . 's';
    } elseif ($hours == 0) {
        return $minutes . 'm';
    } else {
        return $hours . 'h ' . $minutes . 'm';
    }
}

$present = 0;
$absent = 0;
$leave = 0;

// Get all active employees
$totalEmployees = 0;
try {
    $empSql = "SELECT COUNT(*) as total FROM employee WHERE employment_status = 'Active'";
    $empStmt = $conn->query($empSql);
    $empResult = $empStmt->fetch();
    $totalEmployees = $empResult['total'];
} catch (Exception $e) {
    $totalEmployees = 0;
}

// Get employees with attendance records for the date
$employeesWithAttendance = [];
foreach ($attendance_records as $record) {
    if ($record['record_type'] === 'attendance') {
        $employeesWithAttendance[] = $record['emp_id'];
        if ($record['status'] === 'Present') $present++;
        elseif ($record['status'] === 'Absent') $absent++;
    }
}

// Get employees on approved leave for the date (detailed information for modal)
$employeesOnLeave = [];
$employeesOnLeaveDetails = [];
try {
    $leaveSql = "SELECT DISTINCT e.employee_id 
                 FROM employee e
                 INNER JOIN leave_request lr ON e.employee_id = lr.employee_id
                 WHERE e.employment_status = 'Active'
                 AND UPPER(TRIM(lr.status)) = 'APPROVED'
                 AND CAST(? AS DATE) >= CAST(lr.start_date AS DATE)
                 AND CAST(? AS DATE) <= CAST(lr.end_date AS DATE)";
    $leaveStmt = $conn->prepare($leaveSql);
    $leaveStmt->execute([$date_filter, $date_filter]);
    $leaveEmployees = $leaveStmt->fetchAll(PDO::FETCH_COLUMN);
    $employeesOnLeave = $leaveEmployees;
    $leave = count($employeesOnLeave);
    
    // Get detailed leave information for modal
    if (count($employeesOnLeave) > 0) {
        $leaveDetailsSql = "SELECT e.employee_id, e.first_name, e.last_name,
                           d.department_name, p.position_title,
                           lr.leave_request_id, lr.start_date, lr.end_date, lr.total_days, lr.reason,
                           lt.leave_name
                           FROM employee e
                           INNER JOIN leave_request lr ON e.employee_id = lr.employee_id
                           LEFT JOIN department d ON e.department_id = d.department_id
                           LEFT JOIN position p ON e.position_id = p.position_id
                           LEFT JOIN leave_type lt ON lr.leave_type_id = lt.leave_type_id
                           WHERE e.employment_status = 'Active'
                           AND UPPER(TRIM(lr.status)) = 'APPROVED'
                           AND CAST(? AS DATE) >= CAST(lr.start_date AS DATE)
                           AND CAST(? AS DATE) <= CAST(lr.end_date AS DATE)
                           ORDER BY e.first_name, e.last_name";
        $leaveDetailsStmt = $conn->prepare($leaveDetailsSql);
        $leaveDetailsStmt->execute([$date_filter, $date_filter]);
        $employeesOnLeaveDetails = $leaveDetailsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $employeesOnLeave = [];
    $employeesOnLeaveDetails = [];
    // Log error for debugging
    error_log("Error fetching leave employees: " . $e->getMessage());
    if (isset($logger)) {
        $logger->error('ATTENDANCE', 'Failed to fetch leave employees', $e->getMessage());
    }
}

// Calculate absent: total employees - present - on leave
// Absent = employees without attendance record and not on leave
// On weekends (rest days), absent count is 0
$employeesPresentIds = array_unique($employeesWithAttendance);
if ($isRestDay) {
    $absent = 0; // No absences on rest days
} else {
    $absent = $totalEmployees - count($employeesPresentIds) - count($employeesOnLeave);
}

// Ensure absent is not negative
if ($absent < 0) $absent = 0;

// Get list of absent employees (not present and not on leave)
$absentEmployees = [];
try {
    $absentSql = "SELECT e.employee_id, e.first_name, e.last_name, 
                         d.department_name, p.position_title
                  FROM employee e
                  LEFT JOIN department d ON e.department_id = d.department_id
                  LEFT JOIN position p ON e.position_id = p.position_id
                  WHERE e.employment_status = 'Active'
                  AND e.employee_id NOT IN (
                      SELECT DISTINCT employee_id FROM attendance WHERE DATE(date) = ?
                  )
                  AND e.employee_id NOT IN (
                      SELECT DISTINCT lr.employee_id 
                      FROM leave_request lr
                      WHERE UPPER(TRIM(lr.status)) = 'APPROVED'
                      AND CAST(? AS DATE) >= CAST(lr.start_date AS DATE)
                      AND CAST(? AS DATE) <= CAST(lr.end_date AS DATE)
                  )";
    $absentStmt = $conn->prepare($absentSql);
    $absentStmt->execute([$date_filter, $date_filter, $date_filter]);
    $absentEmployees = $absentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $absentEmployees = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/evergreen.svg">
    <title>HRIS - Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background: linear-gradient(135deg, #f0fdfa 0%, #e0f2f1 50%, #f8fafc 100%);
            background-attachment: fixed;
        }

        .header-gradient {
            background: linear-gradient(135deg, #003631 0%, #004d45 50%, #002b27 100%);
            position: relative;
            overflow: hidden;
        }

        .header-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(236, 72, 153, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(14, 165, 233, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .header-gradient > * {
            position: relative;
            z-index: 1;
        }

        .stat-card {
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.1);
        }

        .stat-card.present-card {
            background: linear-gradient(135deg, #10b981 0%, #34d399 50%, #10b981 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }

        .stat-card.absent-card {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 50%, #ef4444 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }

        .stat-card.leave-card {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 50%, #f59e0b 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
            background: rgba(255, 255, 255, 0.3);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin: 8px 0;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            letter-spacing: -1px;
        }

        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .stat-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            animation: fadeInModal 0.3s ease;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 0;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-30px) scale(0.95);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .card-enhanced {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .card-enhanced:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12), 0 1px 3px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="min-h-screen lg:ml-64">
        <header class="header-gradient text-white p-4 lg:p-6 shadow-xl">
            <div class="flex items-center justify-between pl-14 lg:pl-0">
                <?php include '../includes/sidebar.php'; ?>
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold tracking-tight">
                    <i class="fas fa-clock mr-2"></i>Attendance
                </h1>
                <button onclick="openLogoutModal()"
                    class="bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg font-semibold text-red-600 hover:text-red-700 hover:bg-white transition-all duration-200 text-xs sm:text-sm shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </button>
            </div>
        </header>

        <main class="p-3 sm:p-4 lg:p-8">
            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                <div class="mb-4 p-4 rounded-lg bg-blue-100 text-blue-800 text-sm">
                    <strong>🔍 DEBUG INFO:</strong><br>
                    Date Filter: <strong><?php echo htmlspecialchars($date_filter); ?></strong><br>
                    Total Records: <?php echo count($attendance_records); ?><br>
                    Leave Count: <strong><?php echo $leave; ?></strong><br>
                    Employees on Leave IDs: <?php echo empty($employeesOnLeave) ? 'NONE' : implode(', ', $employeesOnLeave); ?><br>
                    <hr class="my-2 border-blue-300">
                    <strong>👤 SUPERVISOR INFO:</strong><br>
                    User Role: <strong><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'unknown'); ?></strong><br>
                    isSupervisor(): <strong><?php echo isSupervisor() ? 'TRUE' : 'FALSE'; ?></strong><br>
                    isAdmin(): <strong><?php echo isAdmin() ? 'TRUE' : 'FALSE'; ?></strong><br>
                    isHRManager(): <strong><?php echo isHRManager() ? 'TRUE' : 'FALSE'; ?></strong><br>
                    Manager Dept ID: <strong><?php echo $managerDeptId ?? 'NULL'; ?></strong><br>
                    Debug Info: <strong><?php echo $debugInfo ?: 'N/A'; ?></strong>
                </div>
            <?php endif; ?>
            
            <?php // Always show department filter banner for supervisors ?>
            <?php if ($debugInfo): ?>
                <div class="mb-4 p-3 rounded-lg <?php echo $managerDeptId ? 'bg-teal-100 text-teal-800' : 'bg-red-100 text-red-800'; ?>">
                    <i class="fas <?php echo $managerDeptId ? 'fa-building' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                    <strong><?php echo htmlspecialchars($debugInfo); ?></strong>
                </div>
            <?php endif; ?>
            
            <?php 
            $hasAttendanceRecords = false;
            $hasLeaveRecords = false;
            foreach ($attendance_records as $record) {
                if ($record['record_type'] === 'attendance') {
                    $hasAttendanceRecords = true;
                }
                if ($record['record_type'] === 'leave') {
                    $hasLeaveRecords = true;
                }
            }
            ?>
            <?php if (!$hasAttendanceRecords && !$hasLeaveRecords): ?>
                <div class="mb-4 p-4 rounded-lg bg-yellow-100 text-yellow-800">
                    <strong>No attendance records found for <?php echo htmlspecialchars($date_filter); ?></strong>
                    <br>
                    <small>Employees need to time-in from the main login page first.</small>
                </div>
            <?php elseif (!$hasAttendanceRecords && $hasLeaveRecords): ?>
                <div class="mb-4 p-4 rounded-lg bg-blue-100 text-blue-800">
                    <strong>No attendance records for <?php echo htmlspecialchars($date_filter); ?></strong>
                    <br>
                    <small>However, <?php echo $leave; ?> employee(s) are on approved leave for this date.</small>
                </div>
            <?php endif; ?>

            <?php if ($isRestDay): ?>
                <div class="mb-4 p-4 rounded-lg bg-purple-100 text-purple-800 flex items-center gap-3">
                    <i class="fas fa-moon text-2xl"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($dayName); ?> - Rest Day</strong>
                        <br>
                        <small>This is a scheduled rest day. Attendance is not required and absences will not be recorded.</small>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-<?php echo $isRestDay ? '4' : '3'; ?> gap-3 sm:gap-4 lg:gap-6 mb-4 lg:mb-6">
                <?php if ($isRestDay): ?>
                <div class="stat-card text-white rounded-xl p-5 lg:p-6 shadow-xl" style="background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 50%, #8b5cf6 100%); background-size: 200% 200%; animation: gradientShift 3s ease infinite;">
                    <div class="stat-icon">
                        <i class="fas fa-moon text-2xl"></i>
                    </div>
                    <h3 class="stat-title">Rest Day</h3>
                    <p class="stat-number"><?php echo $dayName; ?></p>
                    <p class="stat-label">No attendance required</p>
                </div>
                <?php endif; ?>
                <div class="stat-card present-card text-white rounded-xl p-5 lg:p-6 shadow-xl">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle text-2xl"></i>
                    </div>
                    <h3 class="stat-title">Present</h3>
                    <p class="stat-number"><?php echo $present; ?></p>
                    <p class="stat-label"><?php echo $isRestDay ? 'Worked despite rest day' : 'Employees on site'; ?></p>
                </div>
                <div class="stat-card absent-card text-white rounded-xl p-5 lg:p-6 shadow-xl <?php echo $isRestDay ? 'opacity-50' : ''; ?>" <?php echo !$isRestDay ? 'onclick="showAbsentEmployees()" style="cursor: pointer;"' : ''; ?>>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle text-2xl"></i>
                    </div>
                    <h3 class="stat-title">Absent</h3>
                    <p class="stat-number"><?php echo $absent; ?></p>
                    <p class="stat-label"><?php echo $isRestDay ? 'N/A (Rest day)' : 'Without attendance'; ?></p>
                </div>
                <div class="stat-card leave-card text-white rounded-xl p-5 lg:p-6 shadow-xl" onclick="showLeaveEmployees()" style="cursor: pointer;">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-times text-2xl"></i>
                    </div>
                    <h3 class="stat-title">On Leave</h3>
                    <p class="stat-number"><?php echo $leave; ?></p>
                    <p class="stat-label">Approved leave</p>
                </div>
            </div>

            <div class="card-enhanced p-4 lg:p-6 mb-4 lg:mb-6">
                <!-- Employee History Search -->
                <div class="mb-4 p-4 bg-gradient-to-r from-teal-50 to-cyan-50 rounded-lg border border-teal-200">
                    <h3 class="text-sm font-semibold text-teal-800 mb-3">
                        <i class="fas fa-user-clock mr-2"></i>Employee Attendance History
                    </h3>
                    <div class="flex flex-wrap gap-3 items-end">
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Select Employee</label>
                            <select id="employeeSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 text-sm">
                                <option value="">Choose an employee...</option>
                                <?php
                                // Filter employees by supervisor's department
                                $empQuery = "SELECT e.employee_id, CONCAT(e.first_name, ' ', e.last_name) as full_name, d.department_name 
                                             FROM employee e 
                                             LEFT JOIN department d ON e.department_id = d.department_id 
                                             WHERE e.employment_status = 'Active'";
                                $empParams = [];
                                if ($managerDeptId) {
                                    $empQuery .= " AND e.department_id = ?";
                                    $empParams[] = $managerDeptId;
                                }
                                $empQuery .= " ORDER BY e.first_name, e.last_name";
                                $empList = fetchAll($conn, $empQuery, $empParams);
                                foreach ($empList as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>">
                                        EMP-<?php echo str_pad($emp['employee_id'], 4, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo htmlspecialchars($emp['department_name'] ?? 'No Dept'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-36">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Start Date</label>
                            <input type="date" id="historyStartDate" value="<?php echo date('Y-m-01'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 text-sm">
                        </div>
                        <div class="w-36">
                            <label class="block text-xs font-medium text-gray-600 mb-1">End Date</label>
                            <input type="date" id="historyEndDate" value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 text-sm">
                        </div>
                        <button type="button" onclick="viewEmployeeHistory()" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg font-medium text-sm shadow-md hover:shadow-lg transition-all">
                            <i class="fas fa-history mr-2"></i>View History
                        </button>
                    </div>
                </div>

                <!-- Date Filters -->
                <form method="GET" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>"
                        onchange="this.form.submit()"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <input type="text" name="position" value="<?php echo htmlspecialchars($position_filter); ?>"
                        placeholder="Position"
                        onchange="this.form.submit()"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <input type="text" name="department" value="<?php echo htmlspecialchars($department_filter); ?>"
                        placeholder="Department"
                        onchange="this.form.submit()"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium flex items-center justify-center gap-2 text-sm shadow-md hover:shadow-lg transition-all duration-200">
                            <i class="fas fa-search"></i>
                            <span class="hidden sm:inline">Search</span>
                        </button>
                        <button type="button" onclick="clearFilters()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium text-sm shadow-md hover:shadow-lg transition-all duration-200 flex items-center justify-center">
                            <i class="fas fa-times mr-2"></i>Clear
                        </button>
                    </div>
                </form>
                
                <!-- Centered Export Button -->
                <div class="flex justify-center mt-3">
                    <button type="button" onclick="exportAttendance()" class="bg-teal-700 hover:bg-teal-800 text-white px-6 py-2 rounded-lg font-medium text-sm shadow-md hover:shadow-lg transition-all duration-200">
                        <i class="fas fa-download mr-2"></i>Export Attendance
                    </button>
                </div>

                <?php if (!empty($attendance_records) || $hasLeaveRecords): ?>
                    <div class="overflow-x-auto">
                        <table class="desktop-table w-full">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Employee ID</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Employee Name</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Position</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Department</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Time-In</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Time-Out</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Work Duration</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceTableBody">
                                <?php foreach ($attendance_records as $record): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors"
                                        data-status="<?php echo htmlspecialchars($record['status']); ?>"
                                        data-time-in="<?php echo htmlspecialchars($record['time_in'] ?? ''); ?>"
                                        data-time-out="<?php echo htmlspecialchars($record['time_out'] ?? ''); ?>"
                                        data-attendance-id="<?php echo htmlspecialchars($record['attendance_id'] ?? ''); ?>">
                                        <td class="px-3 py-2 text-sm text-gray-800 font-mono text-teal-700"><?php echo 'EMP-' . str_pad($record['emp_id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td class="px-3 py-2 text-sm text-gray-800"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                        <td class="px-3 py-2 text-sm text-gray-800"><?php echo htmlspecialchars($record['position_title'] ?? 'N/A'); ?></td>
                                        <td class="px-3 py-2 text-sm text-gray-800"><?php echo htmlspecialchars($record['department_name'] ?? 'N/A'); ?></td>
                                        <td class="px-3 py-2 text-sm text-gray-800">
                                            <?php if ($record['record_type'] === 'leave'): ?>
                                                <span class="text-blue-600 font-medium">On Leave</span>
                                                <?php if (!empty($record['leave_name'])): ?>
                                                    <br><span class="text-xs text-gray-500"><?php echo htmlspecialchars($record['leave_name']); ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php echo $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-'; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-800">
                                            <?php if ($record['record_type'] === 'leave'): ?>
                                                <span class="text-gray-500">-</span>
                                            <?php else: ?>
                                                <?php echo $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-'; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-800 work-duration" data-live="<?php echo ($record['record_type'] === 'attendance' && !$record['time_out']) ? '1' : '0'; ?>">
                                            <?php if ($record['record_type'] === 'leave'): ?>
                                                <span class="text-blue-600">-</span>
                                            <?php else: ?>
                                                <?php echo calculateWorkHours($record['time_in'], $record['time_out']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2">
                                            <?php if ($record['record_type'] === 'leave'): ?>
                                                <span class="text-blue-600 text-xs font-medium">On Leave</span>
                                            <?php elseif (!$record['time_out']): ?>
                                                <button type="button" onclick="showConfirmTimeOut(<?php echo htmlspecialchars($record['attendance_id']); ?>)" 
                                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium shadow-sm hover:shadow-md transition-all duration-200">
                                                    <i class="fas fa-sign-out-alt mr-1"></i>Time Out
                                                </button>
                                            <?php else: ?>
                                                <span class="text-green-600 text-xs font-medium">✓ Complete</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mobile-card space-y-3" id="mobileCardContainer">
                        <?php foreach ($attendance_records as $record): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow attendance-card"
                                data-position="<?php echo strtolower($record['position_title'] ?? ''); ?>"
                                data-department="<?php echo strtolower($record['department_name'] ?? ''); ?>"
                                data-status="<?php echo htmlspecialchars($record['status']); ?>"
                                data-time-in="<?php echo htmlspecialchars($record['time_in'] ?? ''); ?>"
                                data-time-out="<?php echo htmlspecialchars($record['time_out'] ?? ''); ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-900 text-base"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></h3>
                                        <p class="text-xs text-gray-500 mt-1 font-mono"><?php echo 'EMP-' . str_pad($record['emp_id'], 4, '0', STR_PAD_LEFT); ?></p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    <?php
                                    if ($record['status'] === 'Present') echo 'bg-green-100 text-green-800';
                                    elseif ($record['status'] === 'Absent') echo 'bg-red-100 text-red-800';
                                    elseif ($record['status'] === 'Leave') echo 'bg-blue-100 text-blue-800';
                                    else echo 'bg-yellow-100 text-yellow-800';
                                    ?>">
                                        <?php echo htmlspecialchars($record['status']); ?>
                                    </span>
                                </div>

                                <div class="space-y-2 mb-3">
                                    <div class="flex items-center text-sm">
                                        <span class="text-gray-500 w-28 text-xs">Position:</span>
                                        <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($record['position_title'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="flex items-center text-sm">
                                        <span class="text-gray-500 w-28 text-xs">Department:</span>
                                        <span class="text-gray-900"><?php echo htmlspecialchars($record['department_name'] ?? 'N/A'); ?></span>
                                    </div>
                                    <?php if ($record['record_type'] === 'leave'): ?>
                                        <div class="flex items-center text-sm">
                                            <span class="text-gray-500 w-28 text-xs">Leave Type:</span>
                                            <span class="text-blue-600 font-medium"><?php echo htmlspecialchars($record['leave_name'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <span class="text-gray-500 w-28 text-xs">Status:</span>
                                            <span class="text-blue-600 font-medium">On Leave</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center text-sm">
                                            <span class="text-gray-500 w-28 text-xs">Time-In:</span>
                                            <span class="text-gray-900"><?php echo $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-'; ?></span>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <span class="text-gray-500 w-28 text-xs">Time-Out:</span>
                                            <span class="text-gray-900"><?php echo $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-'; ?></span>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <span class="text-gray-500 w-28 text-xs">Work Duration:</span>
                                            <span class="text-gray-900 work-duration" data-live="<?php echo !$record['time_out'] ? '1' : '0'; ?>">
                                                <?php echo calculateWorkHours($record['time_in'], $record['time_out']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($record['record_type'] === 'leave'): ?>
                                    <div class="text-center text-blue-600 text-sm font-medium py-2">On Leave</div>
                                <?php elseif (!$record['time_out']): ?>
                                    <button type="button" onclick="showConfirmTimeOut(<?php echo htmlspecialchars($record['attendance_id']); ?>)" 
                                            class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm transition-colors">
                                        Time Out
                                    </button>
                                <?php else: ?>
                                    <div class="text-center text-green-600 text-sm font-medium py-2">✓ Complete</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <h3 class="text-lg font-medium mb-2">No Attendance Records</h3>
                        <p class="text-sm">No employees have timed in for this date yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="logoutModal" class="modal">
        <div class="modal-content max-w-md w-full mx-4">
            <div class="bg-red-600 text-white p-4 rounded-t-lg">
                <h2 class="text-xl font-bold">Confirm Logout</h2>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-6">Are you sure you want to logout?</p>
                <div class="flex gap-3 justify-end">
                    <button onclick="closeLogoutModal()"
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">
                        Cancel
                    </button>
                    <a href="../logout.php"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media (max-width: 768px) {
            .mobile-card {
                display: block;
            }

            .desktop-table {
                display: none;
            }
        }

        @media (min-width: 769px) {
            .mobile-card {
                display: none;
            }

            .desktop-table {
                display: table;
            }
        }
    </style>

    <script>
        function calculateDuration(timeIn, timeOut) {
            const start = new Date(timeIn);
            const end = timeOut ? new Date(timeOut) : new Date();

            const diff = end - start;
            const seconds = Math.floor(diff / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);

            if (hours === 0 && minutes === 0) {
                return seconds + 's';
            } else if (hours === 0) {
                return minutes + 'm';
            } else {
                return hours + 'h ' + (minutes % 60) + 'm';
            }
        }

        function updateLiveDurations() {
            document.querySelectorAll('.work-duration[data-live="1"]').forEach(element => {
                const row = element.closest('tr, .attendance-card');
                const timeIn = row.dataset.timeIn;
                const timeOut = row.dataset.timeOut;

                if (timeIn && !timeOut) {
                    element.textContent = calculateDuration(timeIn, null);
                }
            });
        }

        setInterval(updateLiveDurations, 1000);

        function clearFilters() {
            window.location.href = 'attendance.php';
        }

        function exportAttendance() {
            const today = new Date('<?php echo $date_filter; ?>');
            const dateStr = today.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            const rows = document.querySelectorAll('#attendanceTableBody tr');
            let visibleRecords = [];

            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = row.querySelectorAll('td');
                    const record = {
                        id: cells[0].textContent.trim(),
                        name: cells[1].textContent.trim(),
                        position: cells[2].textContent.trim(),
                        department: cells[3].textContent.trim(),
                        timeIn: cells[4].textContent.trim(),
                        timeOut: cells[5].textContent.trim(),
                        duration: cells[6].textContent.trim()
                    };
                    visibleRecords.push(record);
                }
            });

            if (visibleRecords.length === 0) {
                showAlertModal('No records to export', 'warning');
                return;
            }

            const printWindow = window.open('', '', 'width=800,height=600');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Attendance Report - ${dateStr}</title>
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        body { font-family: Arial, sans-serif; padding: 30px; color: #333; }
                        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #0d9488; padding-bottom: 20px; }
                        .header h1 { color: #0d9488; font-size: 28px; margin-bottom: 5px; }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                        thead { background: #0d9488; color: white; }
                        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 12px; }
                        tbody tr:nth-child(even) { background: #f9fafb; }
                        .print-button { position: fixed; top: 20px; right: 20px; background: #0d9488; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; }
                        @media print { .print-button { display: none; } }
                    </style>
                </head>
                <body>
                    <button class="print-button" onclick="window.print()">🖨️ Print</button>
                    <div class="header">
                        <h1>ATTENDANCE REPORT</h1>
                        <p>${dateStr}</p>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th><th>Name</th><th>Position</th><th>Department</th>
                                <th>Time In</th><th>Time Out</th><th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${visibleRecords.map(r => `
                                <tr>
                                    <td>${r.id}</td><td>${r.name}</td><td>${r.position}</td>
                                    <td>${r.department}</td><td>${r.timeIn}</td><td>${r.timeOut}</td>
                                    <td>${r.duration}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>

                    
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        function openLogoutModal() {
            document.getElementById('logoutModal').classList.add('active');
        }

        function closeLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }

        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogoutModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLogoutModal();
            }
        });

        function showConfirmTimeOut(attendanceId) {
            <?php if (!isAdmin()): ?>
            // HR Manager - Show permission denied message
            showAlertModal('NO PERMISSIONS: You do not have permission to perform this action.', 'error');
            return;
            <?php endif; ?>
            
            showConfirmModal(
                'Record time-out for this employee?',
                function() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="time_out">
                        <input type="hidden" name="attendance_id" value="${attendanceId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            );
        }

        function showAbsentEmployees() {
            const absentEmployees = <?php echo json_encode($absentEmployees); ?>;
            const dateFilter = '<?php echo htmlspecialchars($date_filter); ?>';
            
            if (absentEmployees.length === 0) {
                showAlertModal('No absent employees for ' + dateFilter, 'info');
                return;
            }
            
            let listHtml = '<div class="max-h-96 overflow-y-auto"><table class="w-full text-sm"><thead><tr class="bg-gray-100"><th class="px-3 py-2 text-left">ID</th><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Position</th><th class="px-3 py-2 text-left">Department</th></tr></thead><tbody>';
            absentEmployees.forEach(emp => {
                const formattedId = 'EMP-' + String(emp.employee_id).padStart(4, '0');
                listHtml += `<tr class="border-b"><td class="px-3 py-2 font-mono text-teal-700">${formattedId}</td><td class="px-3 py-2">${emp.first_name} ${emp.last_name}</td><td class="px-3 py-2">${emp.position_title || 'N/A'}</td><td class="px-3 py-2">${emp.department_name || 'N/A'}</td></tr>`;
            });
            listHtml += '</tbody></table></div>';
            
            const modal = document.getElementById('absentEmployeesModal');
            const modalContent = document.getElementById('absentEmployeesList');
            if (modal && modalContent) {
                modalContent.innerHTML = listHtml;
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            } else {
                showAlertModal('Absent Employees for ' + dateFilter + ':\n\n' + absentEmployees.map(e => `${e.first_name} ${e.last_name} (EMP-${String(e.employee_id).padStart(4, '0')})`).join('\n'), 'info');
            }
        }

        function showLeaveEmployees() {
            const leaveEmployees = <?php echo json_encode($employeesOnLeaveDetails); ?>;
            const dateFilter = '<?php echo htmlspecialchars($date_filter); ?>';
            
            if (leaveEmployees.length === 0) {
                showAlertModal('No employees on leave for ' + dateFilter, 'info');
                return;
            }
            
            let listHtml = '<div class="max-h-96 overflow-y-auto"><table class="w-full text-sm"><thead><tr class="bg-gray-100"><th class="px-3 py-2 text-left">ID</th><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Position</th><th class="px-3 py-2 text-left">Department</th><th class="px-3 py-2 text-left">Leave Type</th><th class="px-3 py-2 text-left">Dates</th><th class="px-3 py-2 text-left">Days</th></tr></thead><tbody>';
            leaveEmployees.forEach(emp => {
                const formattedId = 'EMP-' + String(emp.employee_id).padStart(4, '0');
                const startDate = new Date(emp.start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                const endDate = new Date(emp.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                listHtml += `<tr class="border-b">
                    <td class="px-3 py-2 font-mono text-teal-700">${formattedId}</td>
                    <td class="px-3 py-2">${emp.first_name} ${emp.last_name}</td>
                    <td class="px-3 py-2">${emp.position_title || 'N/A'}</td>
                    <td class="px-3 py-2">${emp.department_name || 'N/A'}</td>
                    <td class="px-3 py-2">${emp.leave_name || 'N/A'}</td>
                    <td class="px-3 py-2">${startDate} - ${endDate}</td>
                    <td class="px-3 py-2">${emp.total_days || 'N/A'}</td>
                </tr>`;
            });
            listHtml += '</tbody></table></div>';
            
            const modal = document.getElementById('leaveEmployeesModal');
            const modalContent = document.getElementById('leaveEmployeesList');
            if (modal && modalContent) {
                modalContent.innerHTML = listHtml;
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            } else {
                showAlertModal('Employees on Leave for ' + dateFilter + ':\n\n' + leaveEmployees.map(e => `${e.first_name} ${e.last_name} (EMP-${String(e.employee_id).padStart(4, '0')}) - ${e.leave_name || 'N/A'}`).join('\n'), 'info');
            }
        }

        function closeLeaveEmployeesModal() {
            const modal = document.getElementById('leaveEmployeesModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }

        function closeAbsentEmployeesModal() {
            const modal = document.getElementById('absentEmployeesModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }
    </script>

    <!-- Alert Modal -->
    <div id="alertModal" class="modal">
        <div class="modal-content max-w-md w-full mx-4">
            <div class="modal-header bg-teal-700 text-white p-4 rounded-t-lg">
                <h2 class="text-xl font-bold" id="alertModalTitle">Information</h2>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-6" id="alertModalMessage"></p>
                <div class="flex justify-end">
                    <button onclick="closeAlertModal()" 
                            class="px-4 py-2 bg-teal-700 text-white rounded-lg hover:bg-teal-800 transition">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content max-w-md w-full mx-4">
            <div class="bg-yellow-600 text-white p-4 rounded-t-lg">
                <h2 class="text-xl font-bold">Confirm Action</h2>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-6" id="confirmModalMessage"></p>
                <div class="flex gap-3 justify-end">
                    <button onclick="handleCancel()" 
                            class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">
                        Cancel
                    </button>
                    <button onclick="handleConfirm()" 
                            class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Absent Employees Modal -->
    <div id="absentEmployeesModal" class="modal">
        <div class="modal-content max-w-3xl w-full mx-4">
            <div class="bg-red-600 text-white p-4 rounded-t-lg">
                <h2 class="text-xl font-bold">Absent Employees - <?php echo htmlspecialchars($date_filter); ?></h2>
            </div>
            <div class="p-6" id="absentEmployeesList">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="p-4 bg-gray-50 rounded-b-lg">
                <button onclick="closeAbsentEmployeesModal()" 
                        class="w-full px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Leave Employees Modal -->
    <div id="leaveEmployeesModal" class="modal">
        <div class="modal-content max-w-4xl w-full mx-4">
            <div class="bg-blue-600 text-white p-4 rounded-t-lg">
                <h2 class="text-xl font-bold">Employees on Leave - <?php echo htmlspecialchars($date_filter); ?></h2>
            </div>
            <div class="p-6" id="leaveEmployeesList">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="p-4 bg-gray-50 rounded-b-lg">
                <button onclick="closeLeaveEmployeesModal()" 
                        class="w-full px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Employee History Modal -->
    <div id="employeeHistoryModal" class="modal">
        <div class="modal-content max-w-4xl w-full mx-4">
            <div class="bg-teal-600 text-white p-4 rounded-t-lg flex justify-between items-center">
                <h2 class="text-xl font-bold">
                    <i class="fas fa-user-clock mr-2"></i>Attendance History - <span id="employeeHistoryTitle">Employee</span>
                </h2>
                <button onclick="closeEmployeeHistoryModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6" id="employeeHistoryContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="p-4 bg-gray-50 rounded-b-lg flex gap-2">
                <button onclick="closeEmployeeHistoryModal()" 
                        class="flex-1 px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Add event listeners for custom modals
        document.addEventListener('DOMContentLoaded', function() {
            // Absent Employees Modal
            const absentModal = document.getElementById('absentEmployeesModal');
            if (absentModal) {
                absentModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeAbsentEmployeesModal();
                    }
                });
            }

            // Leave Employees Modal
            const leaveModal = document.getElementById('leaveEmployeesModal');
            if (leaveModal) {
                leaveModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeLeaveEmployeesModal();
                    }
                });
            }

            // Close modals on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAbsentEmployeesModal();
                    closeLeaveEmployeesModal();
                    closeEmployeeHistoryModal();
                }
            });
        });

        // Employee History Modal Functions
        function viewEmployeeHistory() {
            const employeeId = document.getElementById('employeeSelect').value;
            const startDate = document.getElementById('historyStartDate').value;
            const endDate = document.getElementById('historyEndDate').value;

            if (!employeeId) {
                showAlertModal('Please select an employee first.', 'warning');
                return;
            }

            // Show loading
            const modal = document.getElementById('employeeHistoryModal');
            const content = document.getElementById('employeeHistoryContent');
            const title = document.getElementById('employeeHistoryTitle');
            
            if (!modal || !content) {
                showAlertModal('Modal not found. Please refresh the page.', 'error');
                return;
            }

            const selectedOption = document.getElementById('employeeSelect').selectedOptions[0];
            title.textContent = selectedOption.text.split(' - ')[1]?.split(' (')[0] || 'Employee';
            content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-3xl text-teal-600"></i><p class="mt-2 text-gray-600">Loading attendance history...</p></div>';
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Fetch attendance history via AJAX
            fetch(`attendance.php?ajax=history&employee_id=${employeeId}&start_date=${startDate}&end_date=${endDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        content.innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-3xl"></i><p class="mt-2">${data.error}</p></div>`;
                        return;
                    }

                    let html = `<div class="mb-4 flex gap-4 text-sm">
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full"><i class="fas fa-check-circle mr-1"></i>Present: ${data.stats.present}</span>
                        <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full"><i class="fas fa-times-circle mr-1"></i>Absent: ${data.stats.absent}</span>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full"><i class="fas fa-calendar-times mr-1"></i>Leave: ${data.stats.leave}</span>
                        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full"><i class="fas fa-moon mr-1"></i>Rest Days: ${data.stats.restDays}</span>
                    </div>`;
                    
                    html += '<div class="max-h-96 overflow-y-auto"><table class="w-full text-sm"><thead class="sticky top-0 bg-gray-100"><tr><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Day</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Time In</th><th class="px-3 py-2 text-left">Time Out</th><th class="px-3 py-2 text-left">Hours</th></tr></thead><tbody>';
                    
                    data.records.forEach(record => {
                        const statusClass = {
                            'Present': 'bg-green-100 text-green-800',
                            'Absent': 'bg-red-100 text-red-800',
                            'Leave': 'bg-yellow-100 text-yellow-800',
                            'Rest Day': 'bg-purple-100 text-purple-800'
                        }[record.status] || 'bg-gray-100 text-gray-800';
                        
                        html += `<tr class="border-b">
                            <td class="px-3 py-2 font-mono">${record.date}</td>
                            <td class="px-3 py-2">${record.dayName}</td>
                            <td class="px-3 py-2"><span class="px-2 py-1 rounded text-xs font-medium ${statusClass}">${record.status}</span></td>
                            <td class="px-3 py-2">${record.timeIn || '-'}</td>
                            <td class="px-3 py-2">${record.timeOut || '-'}</td>
                            <td class="px-3 py-2">${record.hours || '-'}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    
                    // Add print button
                    html += `<div class="mt-4 flex justify-center">
                        <button onclick="printAttendanceHistory()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium text-sm shadow-md hover:shadow-lg transition-all">
                            <i class="fas fa-print mr-2"></i>Print History
                        </button>
                    </div>`;
                    
                    content.innerHTML = html;
                })
                .catch(err => {
                    content.innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-3xl"></i><p class="mt-2">Error loading data: ${err.message}</p></div>`;
                });
        }

        function closeEmployeeHistoryModal() {
            const modal = document.getElementById('employeeHistoryModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
        
        function printAttendanceHistory() {
            const content = document.getElementById('employeeHistoryContent');
            const title = document.getElementById('employeeHistoryTitle');
            if (!content) return;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Attendance History - ${title?.textContent || 'Employee'}</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h1 { color: #0d9488; font-size: 24px; margin-bottom: 10px; }
                        .date-range { color: #666; margin-bottom: 20px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f3f4f6; font-weight: bold; }
                        .stats { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
                        .stat-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; }
                        .stat-present { background: #dcfce7; color: #166534; }
                        .stat-absent { background: #fee2e2; color: #991b1b; }
                        .stat-leave { background: #fef3c7; color: #92400e; }
                        .stat-rest { background: #f3e8ff; color: #6b21a8; }
                        @media print { body { padding: 0; } }
                    </style>
                </head>
                <body>
                    <h1>Attendance History - ${title?.textContent || 'Employee'}</h1>
                    <div class="date-range">Period: ${document.getElementById('historyStartDate')?.value || ''} to ${document.getElementById('historyEndDate')?.value || ''}</div>
                    ${content.innerHTML.replace(/<button.*?<\/button>/gi, '')}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>

    <script src="../js/modal.js"></script>
</body>

</html>