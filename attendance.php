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
            case 'time_out':
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

$sql = "SELECT a.*, 
        e.first_name, e.last_name, e.employee_id as emp_id,
        d.department_name, 
        p.position_title
        FROM attendance a
        INNER JOIN employee e ON a.employee_id = e.employee_id
        LEFT JOIN department d ON e.department_id = d.department_id
        LEFT JOIN position p ON e.position_id = p.position_id
        WHERE DATE(a.date) = ?";

$params = [$date_filter];

if ($position_filter) {
    $sql .= " AND p.position_title LIKE ?";
    $params[] = "%$position_filter%";
}

if ($department_filter) {
    $sql .= " AND d.department_name LIKE ?";
    $params[] = "%$department_filter%";
}

$sql .= " ORDER BY a.time_in DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();
} catch (Exception $e) {
    $attendance_records = [];
    $message = "Database Error: " . $e->getMessage();
    $messageType = "error";
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

foreach ($attendance_records as $record) {
    if ($record['status'] === 'Present') $present++;
    elseif ($record['status'] === 'Absent') $absent++;
    elseif ($record['status'] === 'Leave') $leave++;
}

$totalEmployees = 0;
try {
    $empSql = "SELECT COUNT(*) as total FROM employee WHERE employment_status = 'Active'";
    $empStmt = $conn->query($empSql);
    $empResult = $empStmt->fetch();
    $totalEmployees = $empResult['total'];
} catch (Exception $e) {
    $totalEmployees = 0;
}

if ($date_filter == date('Y-m-d')) {
    $absent = $totalEmployees - count($attendance_records);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS - Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/styles.css">

    <style>
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
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
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
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold">Attendance</h1>
                <button onclick="openLogoutModal()"
                    class="bg-white px-3 py-2 rounded-lg font-medium text-red-600 hover:text-red-700 hover:bg-gray-100 text-xs sm:text-sm">
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

            <?php if (empty($attendance_records)): ?>
                <div class="mb-4 p-4 rounded-lg bg-yellow-100 text-yellow-800">
                    <strong>No attendance records found for <?php echo htmlspecialchars($date_filter); ?></strong>
                    <br>
                    <small>Employees need to time-in from the main login page first.</small>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 lg:gap-6 mb-4 lg:mb-6">
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">Present</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $present; ?></p>
                </div>
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">Absent</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $absent; ?></p>
                </div>
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">Leave</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $leave; ?></p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-4 lg:mb-6">
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
                        <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium flex items-center justify-center gap-2 text-sm transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <span class="hidden sm:inline">Search</span>
                        </button>
                        <button type="button" onclick="clearFilters()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors">
                            Clear
                        </button>
                        <button type="button" onclick="exportAttendance()" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg font-medium text-sm whitespace-nowrap transition-colors">
                            Export
                        </button>
                    </div>
                </form>

                <?php if (!empty($attendance_records)): ?>
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
                                        data-time-in="<?php echo htmlspecialchars($record['time_in']); ?>"
                                        data-time-out="<?php echo htmlspecialchars($record['time_out'] ?? ''); ?>"
                                        data-attendance-id="<?php echo htmlspecialchars($record['attendance_id']); ?>">
                                        <td class="px-3 py-2 text-sm text-gray-800"><?php echo htmlspecialchars($record['emp_id']); ?></td>
                                        <td class="px-3 py-2 text-sm text-gray-800"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                        <td class="px-3 py-2 text-sm text-gray-800"><?php echo htmlspecialchars($record['position_title'] ?? 'N/A'); ?></td>
                                        <td class="px-3 py-2 text-sm text-gray-800"><?php echo htmlspecialchars($record['department_name'] ?? 'N/A'); ?></td>
                                        <td class="px-3 py-2 text-sm text-gray-800"><?php echo date('h:i A', strtotime($record['time_in'])); ?></td>
                                        <td class="px-3 py-2 text-sm text-gray-800"><?php echo $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-'; ?></td>
                                        <td class="px-3 py-2 text-sm text-gray-800 work-duration" data-live="<?php echo !$record['time_out'] ? '1' : '0'; ?>">
                                            <?php echo calculateWorkHours($record['time_in'], $record['time_out']); ?>
                                        </td>
                                        <td class="px-3 py-2">
                                            <?php if (!$record['time_out']): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Record time-out for this employee?');">
                                                    <input type="hidden" name="action" value="time_out">
                                                    <input type="hidden" name="attendance_id" value="<?php echo htmlspecialchars($record['attendance_id']); ?>">
                                                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs transition-colors">
                                                        Time Out
                                                    </button>
                                                </form>
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
                                data-time-in="<?php echo htmlspecialchars($record['time_in']); ?>"
                                data-time-out="<?php echo htmlspecialchars($record['time_out'] ?? ''); ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-900 text-base"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></h3>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($record['emp_id']); ?></p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    <?php
                                    if ($record['status'] === 'Present') echo 'bg-green-100 text-green-800';
                                    elseif ($record['status'] === 'Absent') echo 'bg-red-100 text-red-800';
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
                                    <div class="flex items-center text-sm">
                                        <span class="text-gray-500 w-28 text-xs">Time-In:</span>
                                        <span class="text-gray-900"><?php echo date('h:i A', strtotime($record['time_in'])); ?></span>
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
                                </div>

                                <?php if (!$record['time_out']): ?>
                                    <form method="POST" onsubmit="return confirm('Record time-out for this employee?');">
                                        <input type="hidden" name="action" value="time_out">
                                        <input type="hidden" name="attendance_id" value="<?php echo htmlspecialchars($record['attendance_id']); ?>">
                                        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm transition-colors">
                                            Time Out
                                        </button>
                                    </form>
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
                    <a href="logout.php"
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
                alert('No records to export');
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
    </script>
</body>

</html>