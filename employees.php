<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $sql = "INSERT INTO employee (first_name, last_name, middle_name, gender, birth_date, 
                            contact_number, email, address, hire_date, department_id, position_id, employment_status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";

                    $params = [
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['middle_name'] ?? '',
                        $_POST['gender'],
                        $_POST['birth_date'],
                        $_POST['contact_number'],
                        $_POST['email'],
                        $_POST['address'],
                        $_POST['hire_date'],
                        $_POST['department_id'] ?: null,
                        $_POST['position_id'] ?: null
                    ];

                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute($params);

                    if ($success) {
                        if (isset($logger)) {
                            $logger->info(
                                'EMPLOYEE',
                                'Employee added',
                                "Name: {$_POST['first_name']} {$_POST['last_name']}",
                                ['department_id' => $_POST['department_id'], 'position_id' => $_POST['position_id']]
                            );
                        }
                        $message = "Employee added successfully!";
                        $messageType = "success";
                    } else {
                        throw new Exception("Failed to add employee");
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('EMPLOYEE', 'Failed to add employee', $e->getMessage());
                    }
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'edit':
                try {
                    $sql = "UPDATE employee SET 
                            first_name = ?, last_name = ?, middle_name = ?, gender = ?, 
                            birth_date = ?, contact_number = ?, email = ?, address = ?,
                            department_id = ?, position_id = ?
                            WHERE employee_id = ?";

                    $params = [
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['middle_name'] ?? '',
                        $_POST['gender'],
                        $_POST['birth_date'],
                        $_POST['contact_number'],
                        $_POST['email'],
                        $_POST['address'],
                        $_POST['department_id'] ?: null,
                        $_POST['position_id'] ?: null,
                        $_POST['employee_id']
                    ];

                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute($params);

                    if ($success) {
                        if (isset($logger)) {
                            $logger->info(
                                'EMPLOYEE',
                                'Employee updated',
                                "ID: {$_POST['employee_id']}, Name: {$_POST['first_name']} {$_POST['last_name']}"
                            );
                        }
                        $message = "Employee updated successfully!";
                        $messageType = "success";
                    } else {
                        throw new Exception("Failed to update employee");
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('EMPLOYEE', 'Failed to update employee', $e->getMessage());
                    }
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'archive':
                try {
                    $sql = "UPDATE employee SET employment_status = 'Inactive' WHERE employee_id = ?";
                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute([$_POST['employee_id']]);

                    if ($success) {
                        if (isset($logger)) {
                            $logger->info('EMPLOYEE', 'Employee archived', "Employee ID: {$_POST['employee_id']}");
                        }
                        $message = "Employee archived successfully!";
                        $messageType = "success";
                    } else {
                        throw new Exception("Failed to archive employee");
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('EMPLOYEE', 'Failed to archive employee', $e->getMessage());
                    }
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'unarchive':
                try {
                    $sql = "UPDATE employee SET employment_status = 'Active' WHERE employee_id = ?";
                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute([$_POST['employee_id']]);

                    if ($success) {
                        if (isset($logger)) {
                            $logger->info('EMPLOYEE', 'Employee restored', "Employee ID: {$_POST['employee_id']}");
                        }
                        $message = "Employee restored successfully!";
                        $messageType = "success";
                    } else {
                        throw new Exception("Failed to restore employee");
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('EMPLOYEE', 'Failed to restore employee', $e->getMessage());
                    }
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

$view = $_GET['view'] ?? 'active';
$search = $_GET['search'] ?? '';
$position_filter = $_GET['position'] ?? '';
$department_filter = $_GET['department'] ?? '';

$sql = "SELECT e.*, 
        d.department_name, 
        p.position_title 
        FROM employee e
        LEFT JOIN department d ON e.department_id = d.department_id
        LEFT JOIN position p ON e.position_id = p.position_id
        WHERE e.employment_status = ?";

$params = [$view === 'archived' ? 'Inactive' : 'Active'];

if ($search) {
    $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($position_filter) {
    $sql .= " AND p.position_title LIKE ?";
    $params[] = "%$position_filter%";
}

if ($department_filter) {
    $sql .= " AND d.department_name LIKE ?";
    $params[] = "%$department_filter%";
}

$sql .= " ORDER BY e.employee_id DESC";

$employees = fetchAll($conn, $sql, $params);

$departments = fetchAll($conn, "SELECT * FROM department ORDER BY department_name");
$positions = fetchAll($conn, "SELECT * FROM position ORDER BY position_title");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS - Employees</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/styles.css">
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

        .tab-button {
            transition: all 0.3s ease;
        }

        .tab-button.active {
            background-color: #0d9488;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
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
            border-radius: 0.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease;
        }

        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="min-h-screen lg:ml-64">
        <header class="gradient-bg text-white p-4 lg:p-6 shadow-lg">
            <div class="flex items-center justify-between pl-14 lg:pl-0">
                <?php include 'includes/sidebar.php'; ?>
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold">Employee Management</h1>
                <button onclick="openLogoutModal()" class="bg-white px-3 py-2 rounded-lg font-medium text-red-600 hover:text-red-700 hover:bg-gray-100 text-xs sm:text-sm">
                    Logout
                </button>
            </div>
        </header>

        <main class="p-3 sm:p-4 lg:p-8">
            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-4 lg:mb-6">
                <div class="flex flex-wrap gap-2 mb-4">
                    <a href="?view=active" class="tab-button px-4 py-2 rounded-lg font-medium text-sm <?php echo $view === 'active' ? 'active' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                        Active Employees
                    </a>
                    <a href="?view=archived" class="tab-button px-4 py-2 rounded-lg font-medium text-sm <?php echo $view === 'archived' ? 'active' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                        Archived Employees
                    </a>
                </div>

                <form method="GET" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <input type="hidden" name="view" value="<?php echo $view; ?>">
                    <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search Name or Email"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <input type="text" name="position" value="<?php echo htmlspecialchars($position_filter); ?>"
                        placeholder="Filter by Position"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <input type="text" name="department" value="<?php echo htmlspecialchars($department_filter); ?>"
                        placeholder="Filter by Department"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                            Search
                        </button>
                        <button type="button" onclick="clearFilters()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                            Clear
                        </button>
                        <?php if ($view === 'active'): ?>
                        <button type="button" onclick="openAddModal()" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg font-medium text-sm whitespace-nowrap">
                            + Add Employee
                        </button>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="desktop-table w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">ID</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Name</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Position</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Department</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Contact</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Email</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="7" class="px-3 py-8 text-center text-gray-500">
                                        No <?php echo $view === 'archived' ? 'archived' : 'active'; ?> employees found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="px-3 py-2 text-sm"><?php echo $emp['employee_id']; ?></td>
                                        <td class="px-3 py-2 text-sm"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                        <td class="px-3 py-2 text-sm"><?php echo htmlspecialchars($emp['position_title'] ?? 'N/A'); ?></td>
                                        <td class="px-3 py-2 text-sm"><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                                        <td class="px-3 py-2 text-sm"><?php echo htmlspecialchars($emp['contact_number'] ?? 'N/A'); ?></td>
                                        <td class="px-3 py-2 text-sm"><?php echo htmlspecialchars($emp['email'] ?? 'N/A'); ?></td>
                                        <td class="px-3 py-2">
                                            <div class="flex gap-2">
                                                <?php if ($view === 'active'): ?>
                                                    <button onclick='editEmployee(<?php echo json_encode($emp); ?>)'
                                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs">
                                                        Edit
                                                    </button>
                                                    <button onclick="openArchiveModal(<?php echo $emp['employee_id']; ?>, '<?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>')"
                                                        class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-xs">
                                                        Archive
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="openUnarchiveModal(<?php echo $emp['employee_id']; ?>, '<?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>')"
                                                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs">
                                                        Restore
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mobile-card space-y-3">
                    <?php if (empty($employees)): ?>
                        <div class="text-center text-gray-500 py-8">
                            No <?php echo $view === 'archived' ? 'archived' : 'active'; ?> employees found
                        </div>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></h3>
                                        <p class="text-xs text-gray-500">ID: <?php echo $emp['employee_id']; ?></p>
                                    </div>
                                </div>
                                <div class="space-y-2 mb-3 text-sm">
                                    <div><span class="text-gray-500">Position:</span> <?php echo htmlspecialchars($emp['position_title'] ?? 'N/A'); ?></div>
                                    <div><span class="text-gray-500">Department:</span> <?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></div>
                                    <div><span class="text-gray-500">Contact:</span> <?php echo htmlspecialchars($emp['contact_number'] ?? 'N/A'); ?></div>
                                    <div><span class="text-gray-500">Email:</span> <?php echo htmlspecialchars($emp['email'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="flex gap-2">
                                    <?php if ($view === 'active'): ?>
                                        <button onclick='editEmployee(<?php echo json_encode($emp); ?>)'
                                            class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm">
                                            Edit
                                        </button>
                                        <button onclick="openArchiveModal(<?php echo $emp['employee_id']; ?>, '<?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>')"
                                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm">
                                            Archive
                                        </button>
                                    <?php else: ?>
                                        <button onclick="openUnarchiveModal(<?php echo $emp['employee_id']; ?>, '<?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>')"
                                            class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm">
                                            Restore
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="employeeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-800 mb-4">Add Employee</h3>
                <form id="employeeForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="employee_id" id="employeeId">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">First Name *</label>
                            <input type="text" name="first_name" id="firstName" required
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Last Name *</label>
                            <input type="text" name="last_name" id="lastName" required
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Middle Name</label>
                            <input type="text" name="middle_name" id="middleName"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Gender</label>
                            <select name="gender" id="gender" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Birth Date</label>
                            <input type="date" name="birth_date" id="birthDate"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Contact Number</label>
                            <input type="text" name="contact_number" id="contactNumber"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Email</label>
                            <input type="email" name="email" id="email"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Hire Date *</label>
                            <input type="date" name="hire_date" id="hireDate" required
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Department</label>
                            <select name="department_id" id="departmentId" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Position</label>
                            <select name="position_id" id="positionId" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                                <option value="">Select Position</option>
                                <?php foreach ($positions as $pos): ?>
                                    <option value="<?php echo $pos['position_id']; ?>">
                                        <?php echo htmlspecialchars($pos['position_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Address</label>
                            <textarea name="address" id="address" rows="2"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500"></textarea>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-teal-700 hover:bg-teal-800 text-white px-4 py-3 rounded-lg font-medium">
                            Save
                        </button>
                        <button type="button" onclick="closeModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-lg font-medium">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="archiveModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Confirm Archive</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to archive <span id="archiveEmployeeName" class="font-semibold"></span>?</p>
                <form method="POST" id="archiveForm">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="employee_id" id="archiveEmployeeId">
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg font-medium">
                            Yes, Archive
                        </button>
                        <button type="button" onclick="closeArchiveModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-lg font-medium">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="unarchiveModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Confirm Restore</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to restore <span id="unarchiveEmployeeName" class="font-semibold"></span>?</p>
                <form method="POST" id="unarchiveForm">
                    <input type="hidden" name="action" value="unarchive">
                    <input type="hidden" name="employee_id" id="unarchiveEmployeeId">
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg font-medium">
                            Yes, Restore
                        </button>
                        <button type="button" onclick="closeUnarchiveModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-lg font-medium">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
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
                    <button onclick="closeLogoutModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">
                        Cancel
                    </button>
                    <a href="logout.php" 
                       class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let searchTimeout;

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            
            searchInput.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const value = this.value;
                    if (value.length >= 3 || value.length === 0) {
                        document.getElementById('filterForm').submit();
                    }
                }, 500);
            });
        });

        function clearFilters() {
            window.location.href = 'employees.php?view=<?php echo $view; ?>';
        }

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Employee';
            document.getElementById('formAction').value = 'add';
            document.getElementById('employeeForm').reset();
            document.getElementById('employeeModal').classList.remove('hidden');
        }

        function editEmployee(emp) {
            document.getElementById('modalTitle').textContent = 'Edit Employee';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('employeeId').value = emp.employee_id;
            document.getElementById('firstName').value = emp.first_name || '';
            document.getElementById('lastName').value = emp.last_name || '';
            document.getElementById('middleName').value = emp.middle_name || '';
            document.getElementById('gender').value = emp.gender || '';
            document.getElementById('birthDate').value = emp.birth_date || '';
            document.getElementById('contactNumber').value = emp.contact_number || '';
            document.getElementById('email').value = emp.email || '';
            document.getElementById('address').value = emp.address || '';
            document.getElementById('hireDate').value = emp.hire_date || '';
            document.getElementById('departmentId').value = emp.department_id || '';
            document.getElementById('positionId').value = emp.position_id || '';
            document.getElementById('employeeModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('employeeModal').classList.add('hidden');
        }

        function openArchiveModal(employeeId, employeeName) {
            document.getElementById('archiveEmployeeId').value = employeeId;
            document.getElementById('archiveEmployeeName').textContent = employeeName;
            document.getElementById('archiveModal').classList.remove('hidden');
        }

        function closeArchiveModal() {
            document.getElementById('archiveModal').classList.add('hidden');
        }

        function openUnarchiveModal(employeeId, employeeName) {
            document.getElementById('unarchiveEmployeeId').value = employeeId;
            document.getElementById('unarchiveEmployeeName').textContent = employeeName;
            document.getElementById('unarchiveModal').classList.remove('hidden');
        }

        function closeUnarchiveModal() {
            document.getElementById('unarchiveModal').classList.add('hidden');
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
                const logoutModal = document.getElementById('logoutModal');
                if (logoutModal.classList.contains('active')) {
                    closeLogoutModal();
                }
            }
        });

        document.addEventListener('click', function(event) {
            const employeeModal = document.getElementById('employeeModal');
            const archiveModal = document.getElementById('archiveModal');
            const unarchiveModal = document.getElementById('unarchiveModal');
            
            if (event.target === employeeModal) {
                closeModal();
            }
            if (event.target === archiveModal) {
                closeArchiveModal();
            }
            if (event.target === unarchiveModal) {
                closeUnarchiveModal();
            }
        });
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

    <script src="js/modal.js"></script>
    <script src="js/employee.js"></script>
</body>

</html>