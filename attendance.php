<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Sample attendance data
$attendance_records = [
    ['id' => 'EMP001', 'name' => 'Justin Rivera', 'position' => 'Software Developer', 'department' => 'IT', 'time_in' => '08:00 AM', 'time_out' => '05:00 PM', 'status' => 'Present'],
    ['id' => 'EMP002', 'name' => 'Augoste Flores', 'position' => 'Top', 'department' => 'IT', 'time_in' => '08:15 AM', 'time_out' => '05:15 PM', 'status' => 'Present'],
    ['id' => 'EMP003', 'name' => 'Krish Detalla', 'position' => 'Helicopter', 'department' => 'IT', 'time_in' => '-', 'time_out' => '-', 'status' => 'Absent'],
    ['id' => 'EMP004', 'name' => 'Hans Briones', 'position' => 'Motorista', 'department' => 'IT', 'time_in' => '08:30 AM', 'time_out' => '05:30 PM', 'status' => 'Present'],
    ['id' => 'EMP005', 'name' => 'Karylle Galupo', 'position' => 'Queen', 'department' => 'TI', 'time_in' => '-', 'time_out' => '-', 'status' => 'Leave'],
    ['id' => 'EMP006', 'name' => 'Charles Cabos', 'position' => 'Roam', 'department' => 'TI', 'time_in' => '08:00 AM', 'time_out' => '05:00 PM', 'status' => 'Present'],
    ['id' => 'EMP007', 'name' => 'Johsua Nambio', 'position' => 'Gold Lane', 'department' => 'TI', 'time_in' => '08:45 AM', 'time_out' => '05:45 PM', 'status' => 'Present'],
    ['id' => 'EMP008', 'name' => 'Arwin Decena', 'position' => 'Halimaw', 'department' => 'TI', 'time_in' => '-', 'time_out' => '-', 'status' => 'Leave'],
];

// Count statistics
$present = count(array_filter($attendance_records, fn($a) => $a['status'] === 'Present'));
$absent = count(array_filter($attendance_records, fn($a) => $a['status'] === 'Absent'));
$leave = count(array_filter($attendance_records, fn($a) => $a['status'] === 'Leave'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS - Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body class="bg-gray-100">

    <div class="min-h-screen lg:ml-64">
        <header class="gradient-bg text-white p-4 lg:p-6 shadow-lg">
            <div class="flex items-center justify-between pl-14 lg:pl-0">
                <?php include 'includes/sidebar.php'; ?>
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold">Attendance</h1>
                <a href="index.php" class="bg-white text-teal-600 px-3 py-2 rounded-lg font-medium hover:bg-gray-100 text-xs sm:text-sm">
                    Logout
                </a>
            </div>
        </header>

        <main class="p-3 sm:p-4 lg:p-8">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 lg:gap-6 mb-4 lg:mb-6">
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6 cursor-pointer hover:bg-teal-700 transition-colors" onclick="filterByStatus('Present')">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">Present</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $present; ?></p>
                </div>
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6 cursor-pointer hover:bg-teal-700 transition-colors" onclick="filterByStatus('Absent')">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">Absent</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $absent; ?></p>
                </div>
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6 cursor-pointer hover:bg-teal-700 transition-colors" onclick="filterByStatus('Leave')">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">Leave</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $leave; ?></p>
                </div>
            </div>


            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-4 lg:mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <input type="text" id="positionFilter" placeholder="Position"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <input type="text" id="departmentFilter" placeholder="Department"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <button onclick="searchAttendance()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium flex items-center justify-center gap-2 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Search
                    </button>
                    <button onclick="exportAttendance()" class="bg-white text-black px-4 py-2 rounded-lg font-medium text-sm">
                        Export
                    </button>
                </div>


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
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <?php foreach ($attendance_records as $record): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50" data-status="<?php echo $record['status']; ?>">
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $record['id']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $record['name']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $record['position']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $record['department']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $record['time_in']; ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-800"><?php echo $record['time_out']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>


                <div class="mobile-card space-y-3" id="mobileCardContainer">
                    <?php foreach ($attendance_records as $record): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow attendance-card"
                            data-position="<?php echo strtolower($record['position']); ?>"
                            data-department="<?php echo strtolower($record['department']); ?>"
                            data-status="<?php echo $record['status']; ?>">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-base"><?php echo $record['name']; ?></h3>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo $record['id']; ?></p>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    <?php
                                    if ($record['status'] === 'Present') echo 'bg-green-100 text-green-800';
                                    elseif ($record['status'] === 'Absent') echo 'bg-red-100 text-red-800';
                                    else echo 'bg-yellow-100 text-yellow-800';
                                    ?>">
                                    <?php echo $record['status']; ?>
                                </span>
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-24 text-xs">Position:</span>
                                    <span class="text-gray-900 font-medium"><?php echo $record['position']; ?></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-24 text-xs">Department:</span>
                                    <span class="text-gray-900"><?php echo $record['department']; ?></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-24 text-xs">Time-In:</span>
                                    <span class="text-gray-900"><?php echo $record['time_in']; ?></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="text-gray-500 w-24 text-xs">Time-Out:</span>
                                    <span class="text-gray-900"><?php echo $record['time_out']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
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

        @media print {
            body * {
                visibility: hidden;
            }

            #printableArea,
            #printableArea * {
                visibility: visible;
            }

            #printableArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>


    <script src="js/attendance.js"></script>
</body>

</html>