<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

// Log page access
if (isset($logger)) {
    $logger->debug('PAGE', 'Dashboard accessed', 'User: ' . ($_SESSION['username'] ?? 'unknown'));
}

/**
 * Fetch count from database
 */
function getCount($conn, $table, $whereClause = '') {
    $sql = "SELECT COUNT(*) as total FROM {$table}";
    if ($whereClause) {
        $sql .= " WHERE {$whereClause}";
    }
    $result = fetchOne($conn, $sql);
    return ($result && !isset($result['error'])) ? (int)$result['total'] : 0;
}

/**
 * Fetch monthly data for charts
 */
function getMonthlyData($conn, $table, $dateField) {
    $sql = "SELECT DATE_FORMAT({$dateField}, '%b') as month, COUNT(*) as count 
            FROM {$table} 
            WHERE YEAR({$dateField}) = YEAR(CURDATE())
            GROUP BY MONTH({$dateField})
            ORDER BY MONTH({$dateField})";
    
    $result = fetchAll($conn, $sql);
    return ($result && !isset($result['error'])) ? $result : [];
}

/**
 * Process monthly data into array indexed by month
 */
function processMonthlyData($data, $months) {
    $counts = array_fill(0, 12, 0);
    foreach ($data as $row) {
        $monthIndex = array_search($row['month'], $months);
        if ($monthIndex !== false) {
            $counts[$monthIndex] = (int)$row['count'];
        }
    }
    return $counts;
}

// Get dashboard statistics
$stats = [
    'employees' => getCount($conn, 'employee', "employment_status = 'Active'"),
    'applicants' => getCount($conn, 'applicant', "application_status = 'Pending'"),
    'events' => getCount($conn, 'recruitment', "MONTH(date_posted) = MONTH(CURDATE()) AND YEAR(date_posted) = YEAR(CURDATE())")
];

// Get monthly chart data
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

$chartData = [
    'employees' => processMonthlyData(getMonthlyData($conn, 'employee', 'hire_date'), $months),
    'applicants' => processMonthlyData(getMonthlyData($conn, 'applicant', 'date_applied'), $months),
    'events' => processMonthlyData(getMonthlyData($conn, 'recruitment', 'date_posted'), $months)
];
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
    <style>
        .card-active {
            transform: scale(1.02);
            box-shadow: 0 10px 25px rgba(13, 148, 136, 0.3);
        }
        
        .chart-container {
            opacity: 0;
            animation: fadeIn 0.5s ease-in forwards;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen lg:ml-64">
        <header class="gradient-bg text-white p-4 lg:p-6 shadow-lg">
            <div class="flex items-center justify-between pl-14 lg:pl-0">
                <?php include 'includes/sidebar.php'; ?>
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold">Dashboard</h1>
                <a href="logout.php" 
                   onclick="return confirm('Are you sure you want to logout?')"
                   class="bg-white px-3 py-2 rounded-lg font-medium text-red-600 hover:text-red-700 hover:bg-gray-100 text-xs sm:text-sm">
                    Logout
                </a>
            </div>
        </header>

        <main class="p-4 lg:p-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6 mb-6 lg:mb-8">
                <div class="stat-card bg-teal-700 text-white rounded-lg p-4 lg:p-6 shadow-lg" 
                     data-chart="employees"
                     onclick="switchChart('employees')">
                    <h3 class="text-base lg:text-lg font-semibold mb-2">Employees</h3>
                    <p class="text-2xl lg:text-3xl font-bold"><?php echo $stats['employees']; ?></p>
                    <p class="text-xs lg:text-sm opacity-80 mt-2">Total Active Employees</p>
                </div>

                <div class="stat-card bg-teal-700 text-white rounded-lg p-4 lg:p-6 shadow-lg" 
                     data-chart="applicants"
                     onclick="switchChart('applicants')">
                    <h3 class="text-base lg:text-lg font-semibold mb-2">Applicants</h3>
                    <p class="text-2xl lg:text-3xl font-bold"><?php echo $stats['applicants']; ?></p>
                    <p class="text-xs lg:text-sm opacity-80 mt-2">Pending Applications</p>
                </div>

                <div class="stat-card bg-teal-700 text-white rounded-lg p-4 lg:p-6 shadow-lg sm:col-span-2 lg:col-span-1" 
                     data-chart="events"
                     onclick="switchChart('events')">
                    <h3 class="text-base lg:text-lg font-semibold mb-2">Events</h3>
                    <p class="text-2xl lg:text-3xl font-bold"><?php echo $stats['events']; ?></p>
                    <p class="text-xs lg:text-sm opacity-80 mt-2">Upcoming This Month</p>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6">
                <h3 class="text-lg lg:text-xl font-bold text-gray-800 mb-4 lg:mb-6" id="chartTitle">NUMBER OF EMPLOYEES</h3>
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[500px] chart-container">
                        <canvas id="mainChart" class="w-full"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Chart configuration
        const chartConfig = {
            months: <?php echo json_encode($months); ?>,
            data: {
                employees: {
                    data: <?php echo json_encode($chartData['employees']); ?>,
                    label: 'Employees Hired',
                    title: 'NUMBER OF EMPLOYEES',
                    color: '#0d9488'
                },
                applicants: {
                    data: <?php echo json_encode($chartData['applicants']); ?>,
                    label: 'Applications Received',
                    title: 'NUMBER OF APPLICANTS',
                    color: '#0891b2'
                },
                events: {
                    data: <?php echo json_encode($chartData['events']); ?>,
                    label: 'Recruitment Events',
                    title: 'NUMBER OF EVENTS',
                    color: '#059669'
                }
            }
        };

        let currentChart = null;
        let currentChartType = 'employees';

        // Chart options
        const getChartOptions = () => ({
            responsive: true,
            maintainAspectRatio: true,
            animation: {
                duration: 800,
                easing: 'easeInOutQuart'
            },
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
        });

        // Create chart
        function createChart(type) {
            const ctx = document.getElementById('mainChart').getContext('2d');
            const config = chartConfig.data[type];
            
            if (currentChart) {
                currentChart.destroy();
            }

            // Remove and re-add chart container for animation
            const chartContainer = document.querySelector('.chart-container');
            chartContainer.style.opacity = '0';
            
            setTimeout(() => {
                currentChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartConfig.months,
                        datasets: [{
                            label: config.label,
                            data: config.data,
                            backgroundColor: config.color + '33',
                            borderColor: config.color,
                            borderWidth: 1
                        }]
                    },
                    options: getChartOptions()
                });
                
                chartContainer.style.opacity = '1';
            }, 100);

            // Update title
            document.getElementById('chartTitle').textContent = config.title;
        }

        // Switch chart
        function switchChart(type) {
            if (type === currentChartType) return;
            
            currentChartType = type;
            
            // Update active state
            document.querySelectorAll('.stat-card').forEach(card => {
                card.classList.remove('card-active');
            });
            document.querySelector(`[data-chart="${type}"]`).classList.add('card-active');
            
            // Create new chart
            createChart(type);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            createChart('employees');
            document.querySelector('[data-chart="employees"]').classList.add('card-active');
        });
    </script>
</body>
</html>