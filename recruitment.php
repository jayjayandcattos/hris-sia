<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Sample Data lang ulit
$applicants = [
    ['id' => 'APP001', 'name' => 'John Mark Garcia', 'position' => 'Software Developer', 'department' => 'IT', 'contact' => '09171234567', 'interview_date' => '2024-11-15', 'status' => 'To Interview'],
    ['id' => 'APP002', 'name' => 'Ruzzel Valle', 'position' => 'HR Assistant', 'department' => 'HR', 'contact' => '09181234567', 'interview_date' => '2024-11-20', 'status' => 'To Evaluate'],
    ['id' => 'APP003', 'name' => 'Jan Anthony Alejo', 'position' => 'Accountant', 'department' => 'Finance', 'contact' => '09191234567', 'interview_date' => '2024-11-10', 'status' => 'To Evaluate'],
    ['id' => 'APP004', 'name' => 'Solfia Trinidad', 'position' => 'Marketing Manager', 'department' => 'Marketing', 'contact' => '09201234567', 'interview_date' => '2024-11-25', 'status' => 'To Interview'],
    ['id' => 'APP005', 'name' => 'Brian Carpio', 'position' => 'Sales Executive', 'department' => 'Sales', 'contact' => '09211234567', 'interview_date' => '2024-11-18', 'status' => 'To Evaluate'],
    ['id' => 'APP006', 'name' => 'Ehroll Alcantara', 'position' => 'Business Analyst', 'department' => 'Operations', 'contact' => '09221234567', 'interview_date' => '2024-11-22', 'status' => 'To Interview'],
    ['id' => 'APP007', 'name' => 'Pierre Angelo Conejos', 'position' => 'Project Coordinator', 'department' => 'Project Management', 'contact' => '09231234567', 'interview_date' => '2024-11-27', 'status' => 'To Evaluate']
    ,
];


$total_applicants = count($applicants);
$to_interview = count(array_filter($applicants, fn($a) => $a['status'] === 'To Interview'));
$to_evaluate = count(array_filter($applicants, fn($a) => $a['status'] === 'To Evaluate'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS - Recruitment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body class="bg-gray-100">

    <div class="min-h-screen lg:ml-64">
        <header class="gradient-bg text-white p-4 lg:p-6 shadow-lg">
            <div class="flex items-center justify-between pl-14 lg:pl-0">
                 <?php include 'includes/sidebar.php'; ?>
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold">Recruitment</h1>
                <a href="index.php" class="bg-white text-teal-600 px-3 py-2 rounded-lg font-medium hover:bg-gray-100 text-xs sm:text-sm">
                    Logout
                </a>
            </div>
        </header>

       <main class="p-3 sm:p-4 lg:p-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 lg:gap-6 mb-4 lg:mb-6">
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6 cursor-pointer hover:bg-teal-700 transition-colors" onclick="filterByStatus('all')">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">Total Applicant</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $total_applicants; ?></p>
                </div>
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6 cursor-pointer hover:bg-teal-700 transition-colors" onclick="filterByStatus('To Interview')">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">To Interview</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $to_interview; ?></p>
                </div>
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6 cursor-pointer hover:bg-teal-700 transition-colors" onclick="filterByStatus('To Evaluate')">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">To Evaluate</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $to_evaluate; ?></p>
                </div>
            </div>

            <!-- Filter and Search Section -->
            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-4 lg:mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <input type="text" id="positionFilter" placeholder="Position"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <input type="text" id="departmentFilter" placeholder="Department"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <select id="statusFilter" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                        <option value="">All Status</option>
                        <option value="Total Applicant">Total Applicant</option>
                        <option value="To Interview">To Interview</option>
                        <option value="To Evaluate">To Evaluate</option>
                    </select>
                    <div class="flex gap-2">
                        <button onclick="searchApplicants()" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium flex items-center justify-center gap-2 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Search
                        </button>
                        <button onclick="addApplicant()" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg font-medium text-sm whitespace-nowrap">
                            + Add
                        </button>
                    </div>
                </div>

                <!-- Desktop Table View -->
                <div class="overflow-x-auto">
                    <table class="desktop-table w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Applicant ID</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Applicant Name</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Position</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Department</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Contact No.</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Interview Date</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="applicantTableBody">
                            <?php foreach ($applicants as $applicant): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $applicant['id']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $applicant['name']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $applicant['position']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $applicant['department']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $applicant['contact']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo date('M d, Y', strtotime($applicant['interview_date'])); ?></td>
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            <?php 
                                            if ($applicant['status'] === 'Total Applicant') echo 'bg-blue-100 text-blue-800';
                                            elseif ($applicant['status'] === 'To Interview') echo 'bg-yellow-100 text-yellow-800';
                                            else echo 'bg-purple-100 text-purple-800';
                                            ?>">
                                            <?php echo $applicant['status']; ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex gap-2">
                                            <button onclick="viewApplicant('<?php echo $applicant['id']; ?>')"
                                                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-medium">
                                                View
                                            </button>
                                            <button onclick="updateStatus('<?php echo $applicant['id']; ?>')"
                                                class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs font-medium">
                                                Update
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
                    <?php foreach ($applicants as $applicant): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow applicant-card" 
                             data-position="<?php echo strtolower($applicant['position']); ?>"
                             data-department="<?php echo strtolower($applicant['department']); ?>"
                             data-status="<?php echo $applicant['status']; ?>">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-base"><?php echo $applicant['name']; ?></h3>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo $applicant['id']; ?></p>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    <?php 
                                    if ($applicant['status'] === 'Total Applicant') echo 'bg-blue-100 text-blue-800';
                                    elseif ($applicant['status'] === 'To Interview') echo 'bg-yellow-100 text-yellow-800';
                                    else echo 'bg-purple-100 text-purple-800';
                                    ?>">
                                    <?php echo $applicant['status']; ?>
                                </span>
                            </div>
                            
                            <div class="space-y-2 mb-3">
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-28 text-xs">Position:</span>
                                    <span class="text-gray-900 font-medium"><?php echo $applicant['position']; ?></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-28 text-xs">Department:</span>
                                    <span class="text-gray-900"><?php echo $applicant['department']; ?></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-28 text-xs">Contact:</span>
                                    <span class="text-gray-900"><?php echo $applicant['contact']; ?></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-28 text-xs">Interview Date:</span>
                                    <span class="text-gray-900"><?php echo date('M d, Y', strtotime($applicant['interview_date'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="flex gap-2">
                                <button onclick="viewApplicant('<?php echo $applicant['id']; ?>')"
                                    class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium">
                                    View
                                </button>
                                <button onclick="updateStatus('<?php echo $applicant['id']; ?>')"
                                    class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm font-medium">
                                    Update
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <style>
        @media (max-width: 768px) {
            .mobile-card { display: block; }
            .desktop-table { display: none; }
        }
        @media (min-width: 769px) {
            .mobile-card { display: none; }
            .desktop-table { display: table; }
        }
    </style>

    <script>
        let currentStatusFilter = 'all';

        function searchApplicants() {
            const position = document.getElementById('positionFilter').value.toLowerCase();
            const department = document.getElementById('departmentFilter').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            
            // Update current status filter if status dropdown is used
            if (status) {
                currentStatusFilter = status;
            }
            
            // Desktop table
            const rows = document.querySelectorAll('#applicantTableBody tr');
            rows.forEach(row => {
                const pos = row.cells[2].textContent.toLowerCase();
                const dept = row.cells[3].textContent.toLowerCase();
                const stat = row.cells[6].textContent.trim();

                const matchesPosition = position === '' || pos.includes(position);
                const matchesDept = department === '' || dept.includes(department);
                const matchesStatus = currentStatusFilter === 'all' || currentStatusFilter === '' || stat === currentStatusFilter;

                row.style.display = (matchesPosition && matchesDept && matchesStatus) ? '' : 'none';
            });

            // Mobile cards
            const cards = document.querySelectorAll('.applicant-card');
            cards.forEach(card => {
                const pos = card.dataset.position;
                const dept = card.dataset.department;
                const stat = card.dataset.status;

                const matchesPosition = position === '' || pos.includes(position);
                const matchesDept = department === '' || dept.includes(department);
                const matchesStatus = currentStatusFilter === 'all' || currentStatusFilter === '' || stat === currentStatusFilter;

                card.style.display = (matchesPosition && matchesDept && matchesStatus) ? '' : 'none';
            });
        }

        function filterByStatus(status) {
            currentStatusFilter = status;
            
            // Clear other filters
            document.getElementById('positionFilter').value = '';
            document.getElementById('departmentFilter').value = '';
            document.getElementById('statusFilter').value = status === 'all' ? '' : status;
            
            // Apply filter
            searchApplicants();
        }

        function addApplicant() {
            alert('Add Applicant functionality - Will be implemented with backend');
        }

        function viewApplicant(id) {
            alert('View Applicant: ' + id + ' - Will be implemented with backend');
        }

        function updateStatus(id) {
            alert('Update Status: ' + id + ' - Will be implemented with backend');
        }

        // Real-time search
        document.getElementById('positionFilter').addEventListener('keyup', searchApplicants);
        document.getElementById('departmentFilter').addEventListener('keyup', searchApplicants);
        document.getElementById('statusFilter').addEventListener('change', searchApplicants);
    </script>
</body>

</html>