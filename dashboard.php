<?php
session_start();
//token sa user to pre kailangan lag na sure na logged in yung admin
//bat ko ba ineexplain alam mo naman na to e pota
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// include mo rito pag meron na 'config/database.php';
// fetch actual data from database

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
                <a href="index.php" class="bg-white text-teal-600 px-3 py-2 rounded-lg font-medium hover:bg-gray-100 text-xs sm:text-sm">
                    Logout
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="p-4 lg:p-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6 mb-6 lg:mb-8">
                <!-- Employees Card -->
                <div class="bg-teal-700 text-white rounded-lg p-4 lg:p-6 shadow-lg">
                    <h3 class="text-base lg:text-lg font-semibold mb-2">Employees</h3>
                    <p class="text-2xl lg:text-3xl font-bold">69</p>
                    <p class="text-xs lg:text-sm opacity-80 mt-2">Total Active Employees</p>
                </div>

                <!-- Applicants Card -->
                <div class="bg-teal-700 text-white rounded-lg p-4 lg:p-6 shadow-lg">
                    <h3 class="text-base lg:text-lg font-semibold mb-2">Applicants</h3>
                    <p class="text-2xl lg:text-3xl font-bold">69</p>
                    <p class="text-xs lg:text-sm opacity-80 mt-2">Pending Applications</p>
                </div>

                <!-- Events Card -->
                <div class="bg-teal-700 text-white rounded-lg p-4 lg:p-6 shadow-lg sm:col-span-2 lg:col-span-1">
                    <h3 class="text-base lg:text-lg font-semibold mb-2">Events</h3>
                    <p class="text-2xl lg:text-3xl font-bold">69</p>
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
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
                datasets: [{
                    label: 'Number of Employees',
                    data: [58, 54, 49, 43, 45, 50, 52, 57, 60, 62],
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
                        max: 70,
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