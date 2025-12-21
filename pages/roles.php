<?php
/**
 * Role Management Page
 * Admin-only page for managing employee roles and department assignments
 */

require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth_included = true;
require_once '../includes/auth.php';

// Only admins can access this page
if (!canManageRoles()) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$messageType = '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_role':
                $employee_id = $_POST['employee_id'] ?? null;
                $new_role = $_POST['new_role'] ?? null;
                $managed_department_id = !empty($_POST['managed_department_id']) ? $_POST['managed_department_id'] : null;
                
                // Clear department for non-manager roles
                if ($new_role !== 'Supervisor') {
                    $managed_department_id = null;
                }
                
                if ($employee_id && $new_role) {
                    $result = updateUserRole($conn, $employee_id, $new_role, $managed_department_id, $_SESSION['user_id']);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                    
                    if (isset($logger) && $result['success']) {
                        $logger->info('ROLES', 'Role updated', "Employee ID: $employee_id, New Role: $new_role");
                    }
                }
                break;
        }
    }
}

// Fetch all employees with their roles
$employees = getAllEmployeesWithRoles($conn);

// Fetch departments for dropdown
$departments = fetchAll($conn, "SELECT MIN(department_id) as department_id, department_name FROM department GROUP BY department_name ORDER BY department_name");

// Role options
$roles = ['Employee', 'Supervisor', 'HR Manager', 'Admin'];

// Filter handling
$filter_role = $_GET['role'] ?? '';
$filter_department = $_GET['department'] ?? '';
$search = $_GET['search'] ?? '';

// Apply filters
$filtered_employees = array_filter($employees, function($emp) use ($filter_role, $filter_department, $search) {
    if ($filter_role && ($emp['role'] ?? 'Employee') !== $filter_role) {
        return false;
    }
    if ($filter_department && $emp['department_id'] != $filter_department) {
        return false;
    }
    if ($search) {
        $searchLower = strtolower($search);
        $nameMatch = stripos($emp['full_name'], $search) !== false;
        $emailMatch = stripos($emp['email'] ?? '', $search) !== false;
        $usernameMatch = stripos($emp['username'] ?? '', $search) !== false;
        if (!$nameMatch && !$emailMatch && !$usernameMatch) {
            return false;
        }
    }
    return true;
});

// Count roles
$role_counts = [
    'Admin' => 0,
    'HR Manager' => 0,
    'Supervisor' => 0,
    'Employee' => 0
];
foreach ($employees as $emp) {
    $role = $emp['role'] ?? 'Employee';
    if (isset($role_counts[$role])) {
        $role_counts[$role]++;
    } else {
        $role_counts['Employee']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/evergreen.svg">
    <title>HRIS - Role Management</title>
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
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .role-admin { background: #fee2e2; color: #dc2626; }
        .role-hr { background: #fef3c7; color: #d97706; }
        .role-manager { background: #dbeafe; color: #2563eb; }
        .role-supervisor { background: #e0e7ff; color: #4f46e5; }
        .role-employee { background: #d1fae5; color: #059669; }
        
        /* Dashboard-style stat cards */
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
        .stat-card.card-active {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 25px 50px rgba(13, 148, 136, 0.4), 0 0 0 2px rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.4);
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
        
        /* Role card gradients with animation */
        .admin-card {
            background: linear-gradient(135deg, #dc2626 0%, #f87171 50%, #dc2626 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }
        .hr-card {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 50%, #f59e0b 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }
        .manager-card {
            background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 50%, #3b82f6 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }
        .supervisor-card {
            background: linear-gradient(135deg, #6366f1 0%, #818cf8 50%, #6366f1 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }
        .employees-card {
            background: linear-gradient(135deg, #10b981 0%, #34d399 50%, #10b981 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
    </style>
</head>
<body>
    <div class="min-h-screen lg:ml-64">
        <header class="header-gradient text-white p-4 lg:p-6 shadow-xl">
            <div class="flex items-center justify-between pl-14 lg:pl-0">
                <?php include '../includes/sidebar.php'; ?>
                <div class="flex items-center gap-3">
                    <i class="fas fa-user-shield text-2xl"></i>
                    <h1 class="text-lg sm:text-xl lg:text-2xl font-bold tracking-tight">Role Management</h1>
                </div>
                <button onclick="openLogoutModal()"
                    class="bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg font-semibold text-red-600 hover:text-red-700 hover:bg-white transition-all duration-200 text-xs sm:text-sm shadow-lg">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </button>
            </div>
        </header>

        <main class="p-4 lg:p-8">
            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Role Stats Cards - Dashboard Style -->
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-6 lg:mb-8">
                <!-- Admin Card -->
                <a href="?role=Admin" class="stat-card admin-card text-white rounded-xl p-5 lg:p-6 shadow-xl <?php echo $filter_role === 'Admin' ? 'card-active' : ''; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-crown text-2xl"></i>
                    </div>
                    <h3 class="stat-title">Admins</h3>
                    <p class="stat-number"><?php echo $role_counts['Admin']; ?></p>
                    <p class="stat-label">System Administrators</p>
                </a>
                
                <!-- HR Manager Card -->
                <a href="?role=HR Manager" class="stat-card hr-card text-white rounded-xl p-5 lg:p-6 shadow-xl <?php echo $filter_role === 'HR Manager' ? 'card-active' : ''; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-user-tie text-2xl"></i>
                    </div>
                    <h3 class="stat-title">HR Managers</h3>
                    <p class="stat-number"><?php echo $role_counts['HR Manager']; ?></p>
                    <p class="stat-label">Human Resources</p>
                </a>
                
                <!-- Supervisor Card -->
                <a href="?role=Supervisor" class="stat-card supervisor-card text-white rounded-xl p-5 lg:p-6 shadow-xl <?php echo $filter_role === 'Supervisor' ? 'card-active' : ''; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-user-check text-2xl"></i>
                    </div>
                    <h3 class="stat-title">Supervisors</h3>
                    <p class="stat-number"><?php echo $role_counts['Supervisor']; ?></p>
                    <p class="stat-label">Team Supervisors</p>
                </a>
                
                <!-- Employee Card -->
                <a href="?role=Employee" class="stat-card employees-card text-white rounded-xl p-5 lg:p-6 shadow-xl <?php echo $filter_role === 'Employee' ? 'card-active' : ''; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                    <h3 class="stat-title">Employees</h3>
                    <p class="stat-number"><?php echo $role_counts['Employee']; ?></p>
                    <p class="stat-label">Regular Staff</p>
                </a>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
                <form method="GET" id="filterForm" class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" id="liveSearch" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Name, email or username..."
                               oninput="filterTableLive()"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                    </div>
                    <div class="w-40">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="role" onchange="this.form.submit()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                            <option value="">All Roles</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r; ?>" <?php echo $filter_role === $r ? 'selected' : ''; ?>><?php echo $r; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-48">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select name="department" onchange="this.form.submit()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['department_id']; ?>" <?php echo $filter_department == $d['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <a href="roles.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </form>
            </div>

            <!-- Employee Role Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-users mr-2 text-teal-600"></i>
                        Employees (<?php echo count($filtered_employees); ?>)
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Employee</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Department</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Position</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Current Role</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Managed Dept</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($filtered_employees as $emp): ?>
                                <?php 
                                    $role = $emp['role'] ?? 'Employee';
                                    $roleClass = match($role) {
                                        'Admin' => 'role-admin',
                                        'HR Manager' => 'role-hr',
                                        'Supervisor' => 'role-supervisor',
                                        default => 'role-employee'
                                    };
                                    $roleIcon = match($role) {
                                        'Admin' => 'fa-crown',
                                        'HR Manager' => 'fa-user-tie',
                                        'Supervisor' => 'fa-user-check',
                                        default => 'fa-user'
                                    };
                                ?>
                                <tr class="hover:bg-gray-50 employee-row" 
                                    data-name="<?php echo htmlspecialchars(strtolower($emp['full_name'])); ?>"
                                    data-email="<?php echo htmlspecialchars(strtolower($emp['email'] ?? '')); ?>"
                                    data-username="<?php echo htmlspecialchars(strtolower($emp['username'] ?? '')); ?>"
                                    data-role="<?php echo htmlspecialchars(strtolower($role)); ?>">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                        <div class="text-xs text-gray-500">
                                            <span class="font-mono text-teal-700">EMP-<?php echo str_pad($emp['employee_id'], 4, '0', STR_PAD_LEFT); ?></span>
                                            <?php if ($emp['username']): ?>
                                                • @<?php echo htmlspecialchars($emp['username']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?php echo htmlspecialchars($emp['department_name'] ?? 'Unassigned'); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?php echo htmlspecialchars($emp['position_title'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="role-badge <?php echo $roleClass; ?>">
                                            <i class="fas <?php echo $roleIcon; ?>"></i>
                                            <?php echo $role; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?php if ($role === 'Supervisor' && $emp['managed_department_name']): ?>
                                            <span class="text-blue-600 font-medium">
                                                <i class="fas fa-building mr-1"></i>
                                                <?php echo htmlspecialchars($emp['managed_department_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button onclick="openRoleModal(<?php echo htmlspecialchars(json_encode([
                                            'employee_id' => $emp['employee_id'],
                                            'full_name' => $emp['full_name'],
                                            'current_role' => $role,
                                            'managed_department_id' => $emp['managed_department_id']
                                        ])); ?>)"
                                                class="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white text-xs rounded-lg transition">
                                            <i class="fas fa-edit mr-1"></i>Edit Role
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($filtered_employees)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-search text-4xl mb-2"></i>
                                        <p>No employees found matching your filters.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Role Modal -->
    <div id="roleModal" class="modal">
        <div class="modal-content w-full max-w-md mx-4">
            <div class="bg-gradient-to-r from-teal-600 to-teal-700 text-white p-4 rounded-t-lg">
                <h2 class="text-lg font-bold">
                    <i class="fas fa-user-cog mr-2"></i>Edit Role
                </h2>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="employee_id" id="modal_employee_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                    <div id="modal_employee_name" class="text-lg font-semibold text-gray-900"></div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Role</label>
                    <select name="new_role" id="modal_role" required onchange="toggleDepartmentSelect()"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r; ?>"><?php echo $r; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-6" id="department_select_container" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Managed Department <span class="text-red-500">*</span>
                    </label>
                    <select name="managed_department_id" id="modal_department"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['department_id']; ?>">
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Required for Supervisor role</p>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-teal-600 hover:bg-teal-700 text-white py-2 rounded-lg font-medium transition">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                    <button type="button" onclick="closeRoleModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logout Modal -->
    <div id="logoutModal" class="modal">
        <div class="modal-content max-w-md w-full mx-4">
            <div class="bg-red-600 text-white p-4 rounded-t-lg">
                <h2 class="text-xl font-bold">Confirm Logout</h2>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-6">Are you sure you want to logout?</p>
                <div class="flex gap-3 justify-end">
                    <button onclick="closeLogoutModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">
                        Cancel
                    </button>
                    <a href="../logout.php" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openRoleModal(data) {
            document.getElementById('modal_employee_id').value = data.employee_id;
            document.getElementById('modal_employee_name').textContent = data.full_name;
            document.getElementById('modal_role').value = data.current_role || 'Employee';
            document.getElementById('modal_department').value = data.managed_department_id || '';
            toggleDepartmentSelect();
            document.getElementById('roleModal').classList.add('active');
        }
        
        function closeRoleModal() {
            document.getElementById('roleModal').classList.remove('active');
        }
        
        function toggleDepartmentSelect() {
            const role = document.getElementById('modal_role').value;
            const container = document.getElementById('department_select_container');
            const select = document.getElementById('modal_department');
            
            if (role === 'Supervisor') {
                container.style.display = 'block';
                select.required = true;
            } else {
                container.style.display = 'none';
                select.required = false;
                select.value = '';
            }
        }
        
        function openLogoutModal() {
            document.getElementById('logoutModal').classList.add('active');
        }
        
        function closeLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }
        
        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // Live search filter function
        function filterTableLive() {
            const searchTerm = document.getElementById('liveSearch').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.employee-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.dataset.name || '';
                const email = row.dataset.email || '';
                const username = row.dataset.username || '';
                const role = row.dataset.role || '';

                const matches = name.includes(searchTerm) || 
                               email.includes(searchTerm) || 
                               username.includes(searchTerm) ||
                               role.includes(searchTerm);

                if (matches) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Update count display
            const countEl = document.querySelector('.employee-count');
            if (countEl) countEl.textContent = visibleCount;
        }
    </script>
</body>
</html>
