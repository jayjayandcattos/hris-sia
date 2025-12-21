<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/auth.php';

if (isset($logger)) {
    $logger->debug('PAGE', 'Dashboard accessed', 'User: ' . ($_SESSION['username'] ?? 'unknown'));
}

// ========================================
// SUPERVISOR DEPARTMENT FILTERING
// ========================================
// Supervisors only see data from their assigned department
$supervisorDeptId = null;
$supervisorDeptFilter = "";
$supervisorDeptFilterWithAnd = "";
if (isSupervisor() && !isAdmin() && !isHRManager()) {
    $supervisorDeptId = getUserDepartmentId($conn);
    if ($supervisorDeptId) {
        $supervisorDeptFilter = " e.department_id = " . intval($supervisorDeptId);
        $supervisorDeptFilterWithAnd = " AND e.department_id = " . intval($supervisorDeptId);
    }
}

function getCount($conn, $table, $whereClause = '') {
    $sql = "SELECT COUNT(*) as total FROM {$table}";
    if ($whereClause) {
        $sql .= " WHERE {$whereClause}";
    }
    $result = fetchOne($conn, $sql);
    return ($result && !isset($result['error'])) ? (int)$result['total'] : 0;
}

function getMonthlyData($conn, $table, $dateField) {
    if ($table === 'applicant' && $dateField === 'created_at') {
        try {
            $sql = "SELECT DATE_FORMAT(r.date_posted, '%b') as month, COUNT(*) as count 
                    FROM applicant a
                    LEFT JOIN recruitment r ON a.recruitment_id = r.recruitment_id
                    WHERE a.application_status != 'Archived'
                    AND r.date_posted IS NOT NULL
                    AND YEAR(r.date_posted) = YEAR(CURDATE())
                    GROUP BY MONTH(r.date_posted)
                    ORDER BY MONTH(r.date_posted)";
            $result = fetchAll($conn, $sql);
            return ($result && !isset($result['error'])) ? $result : [];
        } catch (Exception $e) {
            // If that fails, return empty array - we'll show 0 for all months
            return [];
        }
    }
    
    $sql = "SELECT DATE_FORMAT({$dateField}, '%b') as month, COUNT(*) as count 
            FROM {$table} 
            WHERE {$dateField} IS NOT NULL
            AND YEAR({$dateField}) = YEAR(CURDATE())
            GROUP BY MONTH({$dateField})
            ORDER BY MONTH({$dateField})";
    
    $result = fetchAll($conn, $sql);
    return ($result && !isset($result['error'])) ? $result : [];
}

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

function getYearlyData($conn, $table, $dateField, $yearsBack = 10, $whereClause = '') {
    $currentYear = (int)date('Y');
    $startYear = $currentYear - $yearsBack;
    
    $sql = "SELECT YEAR({$dateField}) as year, COUNT(*) as count 
            FROM {$table} 
            WHERE {$dateField} IS NOT NULL
            AND YEAR({$dateField}) >= ?";
    
    if ($whereClause) {
        $sql .= " AND {$whereClause}";
    }
    
    $sql .= " GROUP BY YEAR({$dateField})
              ORDER BY YEAR({$dateField})";
    
    try {
        $result = fetchAll($conn, $sql, [$startYear]);
        return ($result && !isset($result['error'])) ? $result : [];
    } catch (Exception $e) {
        return [];
    }
}

function processYearlyData($data, $years) {
    $counts = array_fill(0, count($years), 0);
    foreach ($data as $row) {
        $yearIndex = array_search((int)$row['year'], $years);
        if ($yearIndex !== false) {
            $counts[$yearIndex] = (int)$row['count'];
        }
    }
    return $counts;
}

// Get total employees count (only ACTIVE employees) - filtered by department for supervisors
$employeeWhereClause = "employment_status = 'Active'";
if ($supervisorDeptId) {
    $employeeWhereClause .= " AND department_id = " . intval($supervisorDeptId);
}

// For applicants, we need to filter by recruitment's department
$applicantCount = 0;
if ($supervisorDeptId) {
    $applicantResult = fetchOne($conn, 
        "SELECT COUNT(*) as total FROM applicant a 
         LEFT JOIN recruitment r ON a.recruitment_id = r.recruitment_id 
         WHERE a.application_status != 'Archived' AND r.department_id = ?", 
        [$supervisorDeptId]);
    $applicantCount = $applicantResult['total'] ?? 0;
} else {
    $applicantCount = getCount($conn, 'applicant', "application_status != 'Archived'");
}

// For events, filter by department for supervisors
$eventWhereClause = "MONTH(date_posted) = MONTH(CURDATE()) AND YEAR(date_posted) = YEAR(CURDATE())";
if ($supervisorDeptId) {
    $eventWhereClause .= " AND department_id = " . intval($supervisorDeptId);
}

$stats = [
    'employees' => getCount($conn, 'employee', $employeeWhereClause),
    'applicants' => $applicantCount,
    'events' => getCount($conn, 'recruitment', $eventWhereClause)
];

$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Get yearly data for employees (last 10 years)
$currentYear = (int)date('Y');
$years = range($currentYear - 9, $currentYear);
$yearsLabels = array_map('strval', $years);

// Get yearly data for employees (only ACTIVE employees) - filtered for supervisors
$employeeWhereBase = "employment_status = 'Active'";
if ($supervisorDeptId) {
    $employeeWhereBase .= " AND department_id = " . intval($supervisorDeptId);
}
$employeeYearlyData = getYearlyData($conn, 'employee', 'hire_date', 10, $employeeWhereBase);
$employeeYearlyCounts = processYearlyData($employeeYearlyData, $years);

// Get monthly data for other charts - filtered for supervisors
if ($supervisorDeptId) {
    // For supervisors, get filtered applicant data
    $applicantMonthlySQL = "SELECT DATE_FORMAT(r.date_posted, '%b') as month, COUNT(*) as count 
                            FROM applicant a
                            LEFT JOIN recruitment r ON a.recruitment_id = r.recruitment_id
                            WHERE a.application_status != 'Archived'
                            AND r.date_posted IS NOT NULL
                            AND YEAR(r.date_posted) = YEAR(CURDATE())
                            AND r.department_id = ?
                            GROUP BY MONTH(r.date_posted)
                            ORDER BY MONTH(r.date_posted)";
    $applicantResult = fetchAll($conn, $applicantMonthlySQL, [$supervisorDeptId]);
    $applicantMonthlyData = $applicantResult ?: [];
    
    // Filtered events data
    $eventsMonthlySQL = "SELECT DATE_FORMAT(date_posted, '%b') as month, COUNT(*) as count 
                         FROM recruitment
                         WHERE date_posted IS NOT NULL
                         AND YEAR(date_posted) = YEAR(CURDATE())
                         AND department_id = ?
                         GROUP BY MONTH(date_posted)
                         ORDER BY MONTH(date_posted)";
    $eventsResult = fetchAll($conn, $eventsMonthlySQL, [$supervisorDeptId]);
    $eventsMonthlyData = $eventsResult ?: [];
} else {
    $applicantMonthlyData = getMonthlyData($conn, 'applicant', 'created_at');
    $eventsMonthlyData = getMonthlyData($conn, 'recruitment', 'date_posted');
}

$chartData = [
    'employees' => $employeeYearlyCounts,
    'applicants' => processMonthlyData($applicantMonthlyData, $months),
    'events' => processMonthlyData($eventsMonthlyData, $months)
];

// ========================================
// DETAILED DATA FOR MODALS
// ========================================

// Build department filter SQL for supervisor
$deptFilterSQL = $supervisorDeptId ? " AND e.department_id = " . intval($supervisorDeptId) : "";
$deptFilterSQLRecruitment = $supervisorDeptId ? " AND r.department_id = " . intval($supervisorDeptId) : "";

// Get detailed employee list for modal - filtered for supervisors
$employeesList = fetchAll($conn, 
    "SELECT e.employee_id, 
            CONCAT('EMP-', LPAD(e.employee_id, 4, '0')) as formatted_id,
            CONCAT(e.first_name, ' ', e.last_name) as full_name,
            d.department_name, p.position_title, e.hire_date, e.email
     FROM employee e
     LEFT JOIN department d ON e.department_id = d.department_id
     LEFT JOIN position p ON e.position_id = p.position_id
     WHERE e.employment_status = 'Active'" . $deptFilterSQL . "
     ORDER BY e.hire_date DESC
     LIMIT 50"
);
$employeesList = $employeesList ?: [];

// Get detailed applicant list for modal - filtered for supervisors
$applicantsList = fetchAll($conn,
    "SELECT a.applicant_id, a.full_name, a.email, a.contact_number,
            a.application_status, r.job_title, r.date_posted,
            d.department_name
     FROM applicant a
     LEFT JOIN recruitment r ON a.recruitment_id = r.recruitment_id
     LEFT JOIN department d ON r.department_id = d.department_id
     WHERE a.application_status != 'Archived'" . $deptFilterSQLRecruitment . "
     ORDER BY r.date_posted DESC
     LIMIT 50"
);
$applicantsList = $applicantsList ?: [];

// Get detailed events (recruitment) list for modal - fetch entire year for chart filtering
$eventsList = fetchAll($conn,
    "SELECT r.recruitment_id, r.job_title, r.date_posted, r.status,
            d.department_name
     FROM recruitment r
     LEFT JOIN department d ON r.department_id = d.department_id
     WHERE YEAR(r.date_posted) = YEAR(CURDATE())" . $deptFilterSQLRecruitment . "
     ORDER BY r.date_posted DESC
     LIMIT 100"
);
$eventsList = $eventsList ?: [];

// Get today's attendance statistics for additional card - filtered for supervisors
if ($supervisorDeptId) {
    $todayAttendance = fetchOne($conn,
        "SELECT 
            (SELECT COUNT(*) FROM attendance a 
             INNER JOIN employee e ON a.employee_id = e.employee_id 
             WHERE DATE(a.time_in) = CURDATE() AND e.department_id = ?) as present_today,
            (SELECT COUNT(*) FROM employee WHERE employment_status = 'Active' AND department_id = ?) as total_employees",
        [$supervisorDeptId, $supervisorDeptId]
    );
} else {
    $todayAttendance = fetchOne($conn,
        "SELECT 
            (SELECT COUNT(*) FROM attendance WHERE DATE(time_in) = CURDATE()) as present_today,
            (SELECT COUNT(*) FROM employee WHERE employment_status = 'Active') as total_employees"
    );
}
$absentToday = ($todayAttendance['total_employees'] ?? 0) - ($todayAttendance['present_today'] ?? 0);

// Get pending leave requests for additional card - filtered for supervisors
if ($supervisorDeptId) {
    $pendingLeavesResult = fetchOne($conn,
        "SELECT COUNT(*) as total FROM leave_request lr 
         INNER JOIN employee e ON lr.employee_id = e.employee_id 
         WHERE lr.status = 'Pending' AND e.department_id = ?",
        [$supervisorDeptId]
    );
    $pendingLeaves = $pendingLeavesResult['total'] ?? 0;
} else {
    $pendingLeaves = getCount($conn, 'leave_request', "status = 'Pending'");
}

// Get absent employees today for modal - filtered for supervisors
$absentEmployeesList = fetchAll($conn,
    "SELECT e.employee_id,
            CONCAT('EMP-', LPAD(e.employee_id, 4, '0')) as formatted_id,
            CONCAT(e.first_name, ' ', e.last_name) as full_name,
            d.department_name, e.contact_number, e.email
     FROM employee e
     LEFT JOIN department d ON e.department_id = d.department_id
     WHERE e.employment_status = 'Active'" . $deptFilterSQL . "
     AND e.employee_id NOT IN (
         SELECT a.employee_id FROM attendance a WHERE DATE(a.time_in) = CURDATE()
     )
     AND e.employee_id NOT IN (
         SELECT lr.employee_id FROM leave_request lr 
         WHERE lr.status = 'Approved' 
         AND CURDATE() BETWEEN lr.start_date AND lr.end_date
     )
     ORDER BY e.last_name
     LIMIT 50"
);
$absentEmployeesList = $absentEmployeesList ?: [];

// Get pending leave requests for modal - filtered for supervisors
$pendingLeavesList = fetchAll($conn,
    "SELECT lr.leave_request_id, 
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            CONCAT('EMP-', LPAD(e.employee_id, 4, '0')) as formatted_id,
            lt.leave_name, lr.start_date, lr.end_date, lr.total_days,
            lr.reason, lr.date_requested
     FROM leave_request lr
     LEFT JOIN employee e ON lr.employee_id = e.employee_id
     LEFT JOIN leave_type lt ON lr.leave_type_id = lt.leave_type_id
     WHERE lr.status = 'Pending'" . $deptFilterSQL . "
     ORDER BY lr.date_requested DESC
     LIMIT 50"
);
$pendingLeavesList = $pendingLeavesList ?: [];

// Update stats to include new metrics
$stats['absent_today'] = $absentToday;
$stats['pending_leaves'] = $pendingLeaves;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/evergreen.svg">
    <title>HRIS - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0fdfa 0%, #e0f2f1 50%, #f8fafc 100%);
            background-attachment: fixed;
        }

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

        .card-active {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 25px 50px rgba(13, 148, 136, 0.4), 0 0 0 2px rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .card-active::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 100%);
            pointer-events: none;
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

        .chart-container {
            opacity: 0;
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .chart-card:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12), 0 1px 3px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 24px;
            letter-spacing: -0.5px;
            position: relative;
            padding-bottom: 12px;
        }

        .chart-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #0d9488, #14b8a6);
            border-radius: 2px;
        }

        .header-gradient {
            background: linear-gradient(135deg, #003631 0%, #004d45 50%, #002b27 100%);
            position: relative;
            overflow: hidden;
        }

        .header-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(236, 72, 153, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(14, 165, 233, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .header-gradient > * {
            position: relative;
            z-index: 1;
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
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 400px;
            width: 90%;
            animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-30px) scale(0.95);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .stat-card.employees-card {
            background: linear-gradient(135deg, #0d9488 0%, #14b8a6 50%, #0d9488 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }

        .stat-card.applicants-card {
            background: linear-gradient(135deg, #0891b2 0%, #06b6d4 50%, #0891b2 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }

        .stat-card.events-card {
            background: linear-gradient(135deg, #059669 0%, #10b981 50%, #059669 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        @media (max-width: 640px) {
            .stat-number {
                font-size: 2rem;
            }
            .stat-icon {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="min-h-screen lg:ml-64">
        <header class="header-gradient text-white p-4 lg:p-6 shadow-xl">
            <div class="flex items-center justify-between pl-14 lg:pl-0">
                <?php include '../includes/sidebar.php'; ?>
                <div class="flex items-center gap-3">
                    <img src="../assets/LOGO.png" alt="Logo" class="h-8 w-8 sm:h-10 sm:w-10 object-contain">
                    <h1 class="text-lg sm:text-xl lg:text-2xl font-bold tracking-tight">Dashboard</h1>
                </div>
                <button onclick="openLogoutModal()" 
                   class="bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg font-semibold text-red-600 hover:text-red-700 hover:bg-white transition-all duration-200 text-xs sm:text-sm shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </button>
            </div>
        </header>

        <main class="p-4 lg:p-8">
            <!-- Stats Grid with View Details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 lg:gap-6 mb-6 lg:mb-8">
                <!-- Employees Card -->
                <div class="stat-card employees-card text-white rounded-xl p-5 lg:p-6 shadow-xl cursor-pointer" 
                     data-chart="employees" onclick="switchChart('employees')">
                    <div class="flex items-start justify-between">
                        <div class="stat-icon">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        <button onclick="event.stopPropagation(); openDetailModal('employees')" class="text-white/80 hover:text-white transition-colors p-1" title="View Details">
                            <i class="fas fa-external-link-alt"></i>
                        </button>
                    </div>
                    <h3 class="stat-title">Employees</h3>
                    <p class="stat-number"><?php echo $stats['employees']; ?></p>
                    <p class="stat-label">Active Employees</p>
                    <div class="mt-3 w-full bg-white/20 text-white text-xs py-1.5 rounded-lg text-center">
                        <i class="fas fa-chart-bar mr-1"></i>Click to View Chart
                    </div>
                </div>

                <!-- Applicants Card -->
                <div class="stat-card applicants-card text-white rounded-xl p-5 lg:p-6 shadow-xl cursor-pointer" 
                     data-chart="applicants" onclick="switchChart('applicants')">
                    <div class="flex items-start justify-between">
                        <div class="stat-icon">
                            <i class="fas fa-user-tie text-2xl"></i>
                        </div>
                        <button onclick="event.stopPropagation(); openDetailModal('applicants')" class="text-white/80 hover:text-white transition-colors p-1" title="View Details">
                            <i class="fas fa-external-link-alt"></i>
                        </button>
                    </div>
                    <h3 class="stat-title">Applicants</h3>
                    <p class="stat-number"><?php echo $stats['applicants']; ?></p>
                    <p class="stat-label">Total Applicants</p>
                    <div class="mt-3 w-full bg-white/20 text-white text-xs py-1.5 rounded-lg text-center">
                        <i class="fas fa-chart-bar mr-1"></i>Click to View Chart
                    </div>
                </div>

                <!-- Events Card -->
                <div class="stat-card events-card text-white rounded-xl p-5 lg:p-6 shadow-xl cursor-pointer" 
                     data-chart="events" onclick="switchChart('events')">
                    <div class="flex items-start justify-between">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt text-2xl"></i>
                        </div>
                        <button onclick="event.stopPropagation(); openDetailModal('events')" class="text-white/80 hover:text-white transition-colors p-1" title="View Details">
                            <i class="fas fa-external-link-alt"></i>
                        </button>
                    </div>
                    <h3 class="stat-title">Events</h3>
                    <p class="stat-number"><?php echo $stats['events']; ?></p>
                    <p class="stat-label">This Month</p>
                    <div class="mt-3 w-full bg-white/20 text-white text-xs py-1.5 rounded-lg text-center">
                        <i class="fas fa-chart-bar mr-1"></i>Click to View Chart
                    </div>
                </div>

                <!-- Absent Today Card -->
                <div class="stat-card text-white rounded-xl p-5 lg:p-6 shadow-xl cursor-pointer" 
                     style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);" onclick="openDetailModal('absent')">
                    <div class="flex items-start justify-between">
                        <div class="stat-icon">
                            <i class="fas fa-user-times text-2xl"></i>
                        </div>
                        <button onclick="event.stopPropagation(); openDetailModal('absent')" class="text-white/80 hover:text-white transition-colors p-1" title="View Details">
                            <i class="fas fa-external-link-alt"></i>
                        </button>
                    </div>
                    <h3 class="stat-title">Absent Today</h3>
                    <p class="stat-number"><?php echo $stats['absent_today']; ?></p>
                    <p class="stat-label">Not Clocked In</p>
                    <a href="attendance.php" onclick="event.stopPropagation()" class="mt-3 w-full block text-center bg-white/20 hover:bg-white/30 text-white text-xs py-1.5 rounded-lg transition-all">
                        <i class="fas fa-clock mr-1"></i>View Attendance
                    </a>
                </div>

                <!-- Pending Leaves Card -->
                <div class="stat-card text-white rounded-xl p-5 lg:p-6 shadow-xl cursor-pointer" 
                     style="background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);" onclick="openDetailModal('leaves')">
                    <div class="flex items-start justify-between">
                        <div class="stat-icon">
                            <i class="fas fa-hourglass-half text-2xl"></i>
                        </div>
                        <button onclick="event.stopPropagation(); openDetailModal('leaves')" class="text-white/80 hover:text-white transition-colors p-1" title="View Details">
                            <i class="fas fa-external-link-alt"></i>
                        </button>
                    </div>
                    <h3 class="stat-title">Pending Leaves</h3>
                    <p class="stat-number"><?php echo $stats['pending_leaves']; ?></p>
                    <p class="stat-label">Awaiting Approval</p>
                    <a href="leave.php" onclick="event.stopPropagation()" class="mt-3 w-full block text-center bg-white/20 hover:bg-white/30 text-white text-xs py-1.5 rounded-lg transition-all">
                        <i class="fas fa-clipboard-list mr-1"></i>Manage Leaves
                    </a>
                </div>
            </div>

            <div class="chart-card p-6 lg:p-8">
                <h3 class="chart-title" id="chartTitle">NUMBER OF EMPLOYEES</h3>
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[500px] chart-container">
                        <canvas id="mainChart" class="w-full"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Logout Modal -->
    <div id="logoutModal" class="modal">
        <div class="modal-content">
            <div class="bg-gradient-to-r from-red-600 to-red-700 text-white p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold">Confirm Logout</h2>
                </div>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-6 text-center">Are you sure you want to logout?</p>
                <div class="flex gap-3 justify-end">
                    <button onclick="closeLogoutModal()" 
                            class="px-5 py-2.5 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-all duration-200 font-medium shadow-sm hover:shadow">
                        Cancel
                    </button>
                    <a href="../logout.php" 
                       class="px-5 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal (Generic) -->
    <div id="detailModal" class="modal">
        <div class="modal-content" style="max-width: 800px; width: 95%;">
            <div id="detailModalHeader" class="bg-gradient-to-r from-teal-600 to-teal-700 text-white p-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                            <i id="detailModalIcon" class="fas fa-users text-xl"></i>
                        </div>
                        <h2 id="detailModalTitle" class="text-xl font-bold">Details</h2>
                    </div>
                    <button onclick="closeDetailModal()" class="text-white/80 hover:text-white text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="p-4 max-h-[60vh] overflow-y-auto">
                <div id="detailModalContent">
                    <!-- Content will be injected via JavaScript -->
                </div>
            </div>
            <div class="p-4 border-t flex justify-end gap-2 print-hide">
                <button onclick="printModal()" class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-all flex items-center gap-2">
                    <i class="fas fa-print"></i>Print
                </button>
                <button onclick="closeDetailModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Print Styles -->
    <style media="print">
        /* Hide everything except the modal */
        body * {
            visibility: hidden;
        }
        
        #detailModal,
        #detailModal * {
            visibility: visible;
        }
        
        #detailModal {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: auto;
            background: white !important;
        }
        
        #detailModal .modal-content {
            max-width: 100% !important;
            width: 100% !important;
            box-shadow: none !important;
            border-radius: 0 !important;
        }
        
        /* Hide the close button and footer when printing */
        .print-hide,
        #detailModal button {
            display: none !important;
        }
        
        /* Make header print nicely */
        #detailModalHeader {
            background: #0d9488 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        /* Ensure table content is visible */
        #detailModalContent table {
            width: 100%;
            border-collapse: collapse;
        }
        
        #detailModalContent th,
        #detailModalContent td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        #detailModalContent th {
            background-color: #f3f4f6 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        /* Add page title */
        @page {
            margin: 1cm;
        }
    </style>

    <!-- Pre-rendered modal data for JavaScript -->
    <script>
        // Modal data from PHP
        const modalData = {
            employees: {
                title: 'Active Employees',
                icon: 'fa-users',
                headerClass: 'from-teal-600 to-teal-700',
                data: <?php echo json_encode($employeesList); ?>
            },
            applicants: {
                title: 'All Applicants',
                icon: 'fa-user-tie',
                headerClass: 'from-cyan-600 to-cyan-700',
                data: <?php echo json_encode($applicantsList); ?>
            },
            events: {
                title: 'Events This Month',
                icon: 'fa-calendar-alt',
                headerClass: 'from-emerald-600 to-emerald-700',
                data: <?php echo json_encode($eventsList); ?>
            },
            absent: {
                title: 'Absent Today',
                icon: 'fa-user-times',
                headerClass: 'from-red-600 to-red-700',
                data: <?php echo json_encode($absentEmployeesList); ?>
            },
            leaves: {
                title: 'Pending Leave Requests',
                icon: 'fa-hourglass-half',
                headerClass: 'from-amber-500 to-amber-600',
                data: <?php echo json_encode($pendingLeavesList); ?>
            }
        };

        function openDetailModal(type, filterLabel = null) {
            const config = modalData[type];
            if (!config) return;

            const modal = document.getElementById('detailModal');
            const header = document.getElementById('detailModalHeader');
            const title = document.getElementById('detailModalTitle');
            const icon = document.getElementById('detailModalIcon');
            const content = document.getElementById('detailModalContent');

            // Update header
            header.className = `bg-gradient-to-r ${config.headerClass} text-white p-5`;
            
            // Update title - add filter label if provided
            if (filterLabel) {
                title.textContent = `${config.title} (${filterLabel})`;
            } else {
                title.textContent = config.title;
            }
            icon.className = `fas ${config.icon} text-xl`;

            // Filter data if filterLabel is provided
            let displayData = config.data;
            if (filterLabel && config.data.length > 0) {
                displayData = config.data.filter(row => {
                    // For employees, filter by hire_date year
                    if (type === 'employees' && row.hire_date) {
                        const year = new Date(row.hire_date).getFullYear().toString();
                        return year === filterLabel;
                    }
                    // For applicants, filter by date_posted (recruitment posting date)
                    if (type === 'applicants' && row.date_posted) {
                        const monthName = new Date(row.date_posted).toLocaleDateString('en-US', { month: 'short' });
                        return monthName === filterLabel;
                    }
                    // For events, filter by date_posted month
                    if (type === 'events' && row.date_posted) {
                        const monthName = new Date(row.date_posted).toLocaleDateString('en-US', { month: 'short' });
                        return monthName === filterLabel;
                    }
                    return true;
                });
            }

            // Generate content based on type
            let html = '';
            if (displayData.length === 0) {
                html = `<p class="text-center text-gray-500 py-8">No data available${filterLabel ? ` for ${filterLabel}` : ''}</p>`;
            } else {
                html = generateTableHTML(type, displayData);
            }

            content.innerHTML = html;
            modal.classList.add('active');
        }

        function generateTableHTML(type, data) {
            let html = '<div class="overflow-x-auto"><table class="w-full text-sm">';
            
            switch(type) {
                case 'employees':
                    html += '<thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">ID</th><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Department</th><th class="px-3 py-2 text-left">Position</th><th class="px-3 py-2 text-left">Hire Date</th></tr></thead><tbody>';
                    data.forEach(row => {
                        html += `<tr class="border-b hover:bg-gray-50"><td class="px-3 py-2 font-mono text-teal-700">${row.formatted_id || ''}</td><td class="px-3 py-2">${row.full_name || ''}</td><td class="px-3 py-2">${row.department_name || 'N/A'}</td><td class="px-3 py-2">${row.position_title || 'N/A'}</td><td class="px-3 py-2">${row.hire_date || ''}</td></tr>`;
                    });
                    break;
                case 'applicants':
                    html += '<thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Position Applied</th><th class="px-3 py-2 text-left">Department</th><th class="px-3 py-2 text-left">Status</th></tr></thead><tbody>';
                    data.forEach(row => {
                        const statusClass = row.application_status === 'Hired' ? 'text-green-600' : (row.application_status === 'Rejected' ? 'text-red-600' : 'text-blue-600');
                        html += `<tr class="border-b hover:bg-gray-50"><td class="px-3 py-2">${row.full_name || ''}</td><td class="px-3 py-2">${row.job_title || 'N/A'}</td><td class="px-3 py-2">${row.department_name || 'N/A'}</td><td class="px-3 py-2 ${statusClass}">${row.application_status || ''}</td></tr>`;
                    });
                    break;
                case 'events':
                    html += '<thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Event/Job Title</th><th class="px-3 py-2 text-left">Department</th><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Status</th></tr></thead><tbody>';
                    data.forEach(row => {
                        html += `<tr class="border-b hover:bg-gray-50"><td class="px-3 py-2">${row.job_title || ''}</td><td class="px-3 py-2">${row.department_name || 'N/A'}</td><td class="px-3 py-2">${row.date_posted || ''}</td><td class="px-3 py-2"><span class="px-2 py-1 rounded-full text-xs ${row.status === 'Open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'}">${row.status || ''}</span></td></tr>`;
                    });
                    break;
                case 'absent':
                    html += '<thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">ID</th><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Department</th><th class="px-3 py-2 text-left">Contact</th></tr></thead><tbody>';
                    data.forEach(row => {
                        html += `<tr class="border-b hover:bg-gray-50"><td class="px-3 py-2 font-mono text-red-600">${row.formatted_id || ''}</td><td class="px-3 py-2">${row.full_name || ''}</td><td class="px-3 py-2">${row.department_name || 'N/A'}</td><td class="px-3 py-2">${row.contact_number || 'N/A'}</td></tr>`;
                    });
                    break;
                case 'leaves':
                    html += '<thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Employee</th><th class="px-3 py-2 text-left">Leave Type</th><th class="px-3 py-2 text-left">Dates</th><th class="px-3 py-2 text-left">Days</th></tr></thead><tbody>';
                    data.forEach(row => {
                        html += `<tr class="border-b hover:bg-gray-50"><td class="px-3 py-2"><div>${row.employee_name || ''}</div><div class="text-xs text-gray-500">${row.formatted_id || ''}</div></td><td class="px-3 py-2">${row.leave_name || 'N/A'}</td><td class="px-3 py-2">${row.start_date || ''} to ${row.end_date || ''}</td><td class="px-3 py-2 text-center">${row.total_days || 0}</td></tr>`;
                    });
                    break;
            }
            
            html += '</tbody></table></div>';
            return html;
        }

        function closeDetailModal() {
            document.getElementById('detailModal').classList.remove('active');
        }

        function printModal() {
            window.print();
        }

        // Close modal on outside click
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailModal();
            }
        });
    </script>

    <script>
        const chartConfig = {
            months: <?php echo json_encode($months); ?>,
            years: <?php echo json_encode($yearsLabels); ?>,
            data: {
                employees: {
                    data: <?php echo json_encode($chartData['employees']); ?>,
                    labels: <?php echo json_encode($yearsLabels); ?>,
                    label: 'Employees Hired',
                    title: 'NUMBER OF EMPLOYEES (YEARLY)',
                    color: '#0d9488',
                    isYearly: true
                },
                applicants: {
                    data: <?php echo json_encode($chartData['applicants']); ?>,
                    labels: <?php echo json_encode($months); ?>,
                    label: 'Applicants',
                    title: 'NUMBER OF APPLICANTS',
                    color: '#0891b2',
                    isYearly: false
                },
                events: {
                    data: <?php echo json_encode($chartData['events']); ?>,
                    labels: <?php echo json_encode($months); ?>,
                    label: 'Recruitment Events',
                    title: 'NUMBER OF EVENTS',
                    color: '#059669',
                    isYearly: false
                }
            }
        };

        let currentChart = null;
        let currentChartType = 'employees';

        const getChartOptions = () => ({
            responsive: true,
            maintainAspectRatio: true,
            animation: {
                duration: 1000,
                easing: 'easeInOutQuart'
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 12,
                            weight: '500'
                        },
                        color: '#64748b'
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 12,
                            weight: '500'
                        },
                        color: '#64748b'
                    },
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: '600'
                    },
                    bodyFont: {
                        size: 13
                    },
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' ' + (context.parsed.y === 1 ? 'item' : 'items');
                        }
                    }
                }
            }
        });

        function createChart(type) {
            const ctx = document.getElementById('mainChart').getContext('2d');
            const config = chartConfig.data[type];
            
            if (currentChart) {
                currentChart.destroy();
            }

            const chartContainer = document.querySelector('.chart-container');
            chartContainer.style.opacity = '0';
            
            setTimeout(() => {
                // Use yearly labels for employees, monthly for others
                const labels = config.isYearly ? config.labels : chartConfig.months;
                
                // Create gradient for chart bars
                const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                gradient.addColorStop(0, config.color + 'CC');
                gradient.addColorStop(1, config.color + '66');
                
                currentChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: config.label,
                            data: config.data,
                            backgroundColor: gradient,
                            borderColor: config.color,
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false,
                            barThickness: 'flex',
                            maxBarThickness: 50
                        }]
                    },
                    options: {
                        ...getChartOptions(),
                        onClick: function(event, elements) {
                            // Only trigger when clicking on an actual bar
                            if (elements.length > 0) {
                                const clickedIndex = elements[0].index;
                                const clickedLabel = currentChart.data.labels[clickedIndex];
                                // Open modal with the clicked year/month as filter
                                openDetailModal(currentChartType, clickedLabel);
                            }
                        },
                        onHover: function(event, elements) {
                            // Change cursor to pointer when hovering over bars
                            const canvas = document.getElementById('mainChart');
                            canvas.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                        }
                    }
                });
                
                chartContainer.style.opacity = '1';
            }, 100);

            document.getElementById('chartTitle').textContent = config.title;
        }

        function switchChart(type) {
            if (type === currentChartType) return;
            
            currentChartType = type;
            
            // Remove active class from all cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.classList.remove('card-active');
            });
            
            // Add active class to the matching card (if exists)
            const targetCard = document.querySelector(`[data-chart="${type}"]`);
            if (targetCard) {
                targetCard.classList.add('card-active');
            }
            
            createChart(type);
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

        document.addEventListener('DOMContentLoaded', () => {
            createChart('employees');
            document.querySelector('[data-chart="employees"]').classList.add('card-active');
        });
    </script>
</body>
</html>