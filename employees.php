<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

$message = '';
$messageType = '';

// Handle form submissions
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
        }
    }
}

// Fetch employees with filters
$search = $_GET['search'] ?? '';
$position_filter = $_GET['position'] ?? '';
$department_filter = $_GET['department'] ?? '';

$sql = "SELECT e.*, 
        d.department_name, 
        p.position_title 
        FROM employee e
        LEFT JOIN department d ON e.department_id = d.department_id
        LEFT JOIN position p ON e.position_id = p.position_id
        WHERE e.employment_status = 'Active'";

$params = [];

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

// Fetch departments for dropdown
$departments = fetchAll($conn, "SELECT * FROM department ORDER BY department_name");

// Fetch positions for dropdown
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
    </style>
</head>

<body class="bg-gray-100">
    <div class="min-h-screen lg:ml-64">
        <header class="gradient-bg text-white p-4 lg:p-6 shadow-lg">
            <div class="flex items-center justify-between pl-14 lg:pl-0">
                <?php include 'includes/sidebar.php'; ?>
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold">Employee Management</h1>
                <a href="logout.php" class="bg-white text-teal-600 px-3 py-2 rounded-lg font-medium hover:bg-gray-100 text-xs sm:text-sm">
                    Logout
                </a>
            </div>
        </header>

        <main class="p-3 sm:p-4 lg:p-8">
            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Search and Filter -->
            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-4 lg:mb-6">
                <form method="GET" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search Name or Email"
                        onkeyup="debounceSearch(this)"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <input type="text" name="position" value="<?php echo htmlspecialchars($position_filter); ?>"
                        placeholder="Filter by Position"
                        onchange="this.form.submit()"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <input type="text" name="department" value="<?php echo htmlspecialchars($department_filter); ?>"
                        placeholder="Filter by Department"
                        onchange="this.form.submit()"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                            Search
                        </button>
                        <button type="button" onclick="clearFilters()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                            Clear
                        </button>
                        <button type="button" onclick="openAddModal()" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg font-medium text-sm whitespace-nowrap">
                            + Add Employee
                        </button>
                    </div>
                </form>

                <!-- Desktop Table -->
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
                                            <button onclick='editEmployee(<?php echo json_encode($emp); ?>)'
                                                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs">
                                                Edit
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Archive this employee?')">
                                                <input type="hidden" name="action" value="archive">
                                                <input type="hidden" name="employee_id" value="<?php echo $emp['employee_id']; ?>">
                                                <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-xs">
                                                    Archive
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="mobile-card space-y-3">
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
                                <button onclick='editEmployee(<?php echo json_encode($emp); ?>)'
                                    class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm">
                                    Edit
                                </button>
                                <form method="POST" class="flex-1" onsubmit="return confirm('Archive?')">
                                    <input type="hidden" name="action" value="archive">
                                    <input type="hidden" name="employee_id" value="<?php echo $emp['employee_id']; ?>">
                                    <button type="submit" class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm">
                                        Archive
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit Modal -->
    <div id="employeeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
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

    <script>
        let searchTimeout;

        function debounceSearch(input) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (input.value.length >= 3 || input.value.length === 0) {
                    input.form.submit();
                }
            }, 500); // Wait 500ms after user stops typing
        }

        function clearFilters() {
            window.location.href = 'employees.php';
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
    </script>
</body>

</html>