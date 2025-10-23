<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}


// Sample employee data langs
// Delete paG MAY SAMPLE DATA NA
$employees = [
    ['id' => 'EMP001', 'name' => 'Justin Rivera', 'position' => 'Software Developer', 'department' => 'IT', 'contact' => '09171234567', 'email' => 'justinrivera@gmail.com', 'hired_date' => '2023-01-15', 'status' => 'Active'],
    ['id' => 'EMP002', 'name' => 'Augoste Flores', 'position' => 'Top', 'department' => 'IT', 'contact' => '09181234567', 'email' => 'gust@gmail.com', 'hired_date' => '2022-06-20', 'status' => 'Active'],
    ['id' => 'EMP003', 'name' => 'Krish Detalla', 'position' => 'Helicopter', 'department' => 'IT', 'contact' => '09191234567', 'email' => 'healer@yahoo.com', 'hired_date' => '2023-03-10', 'status' => 'Active'],
    ['id' => 'EMP004', 'name' => 'Hans Briones', 'position' => 'Motorista', 'department' => 'IT', 'contact' => '09201234567', 'email' => 'hans@gamamama.com', 'hired_date' => '2022-11-05', 'status' => 'Active'],
    ['id' => 'EMP005', 'name' => 'Karylle Galupo', 'position' => 'Queen', 'department' => 'TI', 'contact' => '09211234567', 'email' => 'karil.com', 'hired_date' => '2021-08-12', 'status' => 'Active'],
    ['id' => 'EMP006', 'name' => 'Charles Cabos', 'position' => 'Roam', 'department' => 'TI', 'contact' => '09221234567', 'email' => 'wuwaenjoyer@gmail.com', 'hired_date' => '2023-02-28', 'status' => 'Active'],
    ['id' => 'EMP007', 'name' => 'Johsua Nambio', 'position' => 'Gold Lane', 'department' => 'TI', 'contact' => '09231234567', 'email' => 'granger@crossfire.ph', 'hired_date' => '2022-09-15', 'status' => 'Active'],
    ['id' => 'EMP008', 'name' => 'Arwin Decena', 'position' => 'Halimaw', 'department' => 'TI', 'contact' => '09241234567', 'email' => 'arwinimnida', 'hired_date' => '2023-04-01', 'status' => 'Leave'],
];

// Database query pag ok na modify mo na lang
/*
include 'config/database.php';
$search = $_GET['search'] ?? '';
$position_filter = $_GET['position'] ?? '';
$department_filter = $_GET['department'] ?? '';

$sql = "SELECT * FROM employees WHERE 1=1";
if ($search) {
    $sql .= " AND (employee_id LIKE '%$search%' OR name LIKE '%$search%' OR email LIKE '%$search%')";
}
if ($position_filter) {
    $sql .= " AND position = '$position_filter'";
}
if ($department_filter) {
    $sql .= " AND department = '$department_filter'";
}
$result = $conn->query($sql);
$employees = $result->fetch_all(MYSQLI_ASSOC);
*/

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS - Employees</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="index.php" class="bg-white text-teal-600 px-3 py-2 rounded-lg font-medium hover:bg-gray-100 text-xs sm:text-sm">
                    Logout
                </a>
            </div>
        </header>

        <main class="p-3 sm:p-4 lg:p-8">
            <!-- Employee Profile Card -->
            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-4 lg:mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6">
                    <!-- pfp placeholder lang  -->
                    <div class="lg:col-span-2 flex justify-center lg:justify-start">
                        <img src="https://ui-avatars.com/api/?name=Employee&background=a78bfa&color=fff&size=120"
                            alt="Employee"
                            class="w-20 h-20 sm:w-24 sm:h-24 lg:w-32 lg:h-32 rounded-lg shadow-md">
                    </div>

                    <!-- employee form  -->
                    <div class="lg:col-span-10 grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Employee ID</label>
                            <input type="text" placeholder="Employee ID"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Department</label>
                            <input type="text" placeholder="Department"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Employee Name</label>
                            <input type="text" placeholder="Employee Name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Hired Date</label>
                            <input type="date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Position</label>
                            <input type="text" placeholder="Position"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and filter -->
            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-4 lg:mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <input type="text" id="searchInput" placeholder="Search Position"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <input type="text" id="departmentFilter" placeholder="Filter Department"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <button onclick="searchEmployees()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium flex items-center justify-center gap-2 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Search
                    </button>
                    <button onclick="addEmployee()" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg font-medium text-sm">
                        + Add Employee
                    </button>
                </div>

                <!-- Desktop Table View -->
                 <!-- hidden once mobile is active -->
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
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="employeeTableBody">
                            <?php foreach ($employees as $employee): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $employee['id']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $employee['name']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $employee['position']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $employee['department']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $employee['contact']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-600"><?php echo $employee['email']; ?></td>
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $employee['status'] == 'Active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo $employee['status']; ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex gap-2">
                                            <button onclick="editEmployee('<?php echo $employee['id']; ?>')"
                                                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-medium">
                                                Edit
                                            </button>
                                            <button onclick="archiveEmployee('<?php echo $employee['id']; ?>')"
                                                class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-xs font-medium">
                                                Archive
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="mobile-card space-y-3" id="mobileCardContainer">
                    <?php foreach ($employees as $employee): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow employee-card"
                            data-position="<?php echo strtolower($employee['position']); ?>"
                            data-department="<?php echo strtolower($employee['department']); ?>">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-base"><?php echo $employee['name']; ?></h3>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo $employee['id']; ?></p>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $employee['status'] == 'Active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $employee['status']; ?>
                                </span>
                            </div>

                            <div class="space-y-2 mb-3">
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-24 text-xs">Position:</span>
                                    <span class="text-gray-900 font-medium"><?php echo $employee['position']; ?></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-24 text-xs">Department:</span>
                                    <span class="text-gray-900"><?php echo $employee['department']; ?></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-24 text-xs">Contact:</span>
                                    <span class="text-gray-900"><?php echo $employee['contact']; ?></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-24 text-xs">Email:</span>
                                    <span class="text-gray-600 text-xs break-all"><?php echo $employee['email']; ?></span>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button onclick="editEmployee('<?php echo $employee['id']; ?>')"
                                    class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium">
                                    Edit
                                </button>
                                <button onclick="archiveEmployee('<?php echo $employee['id']; ?>')"
                                    class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm font-medium">
                                    Archive
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="js/employee.js"></script>
</body>

</html>