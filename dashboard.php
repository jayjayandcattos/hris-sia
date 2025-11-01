<?php
session_start();

// Check if user is logged in - PROPER CHECK
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

// Log page access
if (isset($logger)) {
    $logger->debug('PAGE', 'Dashboard accessed', 'User: ' . ($_SESSION['username'] ?? 'unknown'));
}

// Get total employees
$totalEmployees = 0;
$sql = "SELECT COUNT(*) as total FROM employee WHERE employment_status = 'Active'";
$result = fetchOne($conn, $sql);
if ($result && !isset($result['error'])) {
    $totalEmployees = $result['total'];
}

// Get total applicants
$totalApplicants = 0;
$sql = "SELECT COUNT(*) as total FROM applicant WHERE application_status = 'Pending'";
$result = fetchOne($conn, $sql);
if ($result && !isset($result['error'])) {
    $totalApplicants = $result['total'];
}

// Get upcoming events this month
$upcomingEvents = 0;
$sql = "SELECT COUNT(*) as total FROM recruitment 
        WHERE MONTH(date_posted) = MONTH(CURDATE()) 
        AND YEAR(date_posted) = YEAR(CURDATE())";
$result = fetchOne($conn, $sql);
if ($result && !isset($result['error'])) {
    $upcomingEvents = $result['total'];
}

// Get monthly employee data for chart
$monthlyData = [];
$sql = "SELECT DATE_FORMAT(hire_date, '%b') as month, COUNT(*) as count 
        FROM employee 
        WHERE YEAR(hire_date) = YEAR(CURDATE())
        GROUP BY MONTH(hire_date)
        ORDER BY MONTH(hire_date)";
$chartData = fetchAll($conn, $sql);
if ($chartData && !isset($chartData['error'])) {
    $monthlyData = $chartData;
}

// Prepare data for JavaScript
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$employeeCounts = array_fill(0, 12, 0);

foreach ($monthlyData as $data) {
    $monthIndex = array_search($data['month'], $months);
    if ($monthIndex !== false) {
        $employeeCounts[$monthIndex] = (int)$data['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen lg:ml-64">
        <header class="gradient-bg text-white p-4 lg:p-6 shadow-lg">
            <div class="flex items-center justify-between pl-14 lg:pl-0">
                <?php include 'includes/sidebar.php'; ?>
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold">Dashboard</h1>
                <a href="logout.php" 
                   onclick="return confirm('Are you sure you want to logout?')"
                   classb="bg-white px-3 py-2 rounded-lg font-medium text-red-600 hover:text-red-700 hover:bg-gray-100 text-xs sm:text-sm">
                    Logout
                </a>
            </div>
        </header>

        <main class="p-4 lg:p-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6 mb-6 lg:mb-8">
                <div class="bg-teal-700 text-white rounded-lg p-4 lg:p-6 shadow-lg">
                    <h3 class="text-base lg:text-lg font-semibold mb-2">Employees</h3>
                    <p class="text-2xl lg:text-3xl font-bold"><?php echo $totalEmployees; ?></p>
                    <p class="text-xs lg:text-sm opacity-80 mt-2">Total Active Employees</p>
                </div>

                <div class="bg-teal-700 text-white rounded-lg p-4 lg:p-6 shadow-lg">
                    <h3 class="text-base lg:text-lg font-semibold mb-2">Applicants</h3>
                    <p class="text-2xl lg:text-3xl font-bold"><?php echo $totalApplicants; ?></p>
                    <p class="text-xs lg:text-sm opacity-80 mt-2">Pending Applications</p>
                </div>

                <div class="bg-teal-700 text-white rounded-lg p-4 lg:p-6 shadow-lg sm:col-span-2 lg:col-span-1">
                    <h3 class="text-base lg:text-lg font-semibold mb-2">Events</h3>
                    <p class="text-2xl lg:text-3xl font-bold"><?php echo $upcomingEvents; ?></p>
                    <p class="text-xs lg:text-sm opacity-80 mt-2">Upcoming This Month</p>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
                <h3 class="text-lg lg:text-xl font-bold text-gray-800 mb-4 lg:mb-6">NUMBER OF EMPLOYEES</h3>
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[500px]">
                        <canvas id="employeeChart" class="w-full"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const ctx = document.getElementById('employeeChart').getContext('2d');
        const employeeChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Number of Employees',
                    data: <?php echo json_encode($employeeCounts); ?>,
                    backgroundColor: '#bbf7d0',
                    borderColor: '#10b981',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 10
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>