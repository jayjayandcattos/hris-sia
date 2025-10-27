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
                    // Insert applicant
                    $sql = "INSERT INTO applicant (recruitment_id, full_name, email, contact_number, 
                            resume_file, application_status) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $status = !empty($_POST['interview_date']) ? 'To Interview' : 'Pending';
                    
                    $params = [
                        $_POST['recruitment_id'] ?: null,
                        $_POST['full_name'],
                        $_POST['email'],
                        $_POST['contact_number'],
                        $_POST['resume_file'] ?? null,
                        $status
                    ];
                    
                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute($params);
                    
                    if ($success) {
                        $applicant_id = $conn->lastInsertId();
                        
                        // If interview date is provided, create interview record
                        if (!empty($_POST['interview_date'])) {
                            $interviewSql = "INSERT INTO interview (applicant_id, interviewer_id, interview_date, interview_result) 
                                           VALUES (?, ?, ?, 'Scheduled')";
                            $interviewStmt = $conn->prepare($interviewSql);
                            $interviewStmt->execute([
                                $applicant_id,
                                $_SESSION['employee_id'],
                                $_POST['interview_date']
                            ]);
                        }
                        
                        if (isset($logger)) {
                            $logger->info('RECRUITMENT', 'Applicant added', 
                                "Name: {$_POST['full_name']}");
                        }
                        $message = "Applicant added successfully!";
                        $messageType = "success";
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('RECRUITMENT', 'Failed to add applicant', $e->getMessage());
                    }
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'update_status':
                try {
                    $sql = "UPDATE applicant SET application_status = ? WHERE applicant_id = ?";
                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute([$_POST['status'], $_POST['applicant_id']]);
                    
                    if ($success) {
                        if (isset($logger)) {
                            $logger->info('RECRUITMENT', 'Applicant status updated', 
                                "ID: {$_POST['applicant_id']}, Status: {$_POST['status']}");
                        }
                        $message = "Status updated successfully!";
                        $messageType = "success";
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('RECRUITMENT', 'Failed to update status', $e->getMessage());
                    }
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'schedule_interview':
                try {
                    $sql = "INSERT INTO interview (applicant_id, interviewer_id, interview_date, interview_result) 
                            VALUES (?, ?, ?, 'Scheduled')";
                    
                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute([
                        $_POST['applicant_id'],
                        $_SESSION['employee_id'],
                        $_POST['interview_date']
                    ]);
                    
                    if ($success) {
                        // Update applicant status
                        $updateSql = "UPDATE applicant SET application_status = 'To Interview' 
                                     WHERE applicant_id = ?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->execute([$_POST['applicant_id']]);
                        
                        $message = "Interview scheduled successfully!";
                        $messageType = "success";
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'archive':
                try {
                    $sql = "UPDATE applicant SET application_status = 'Archived', archived_at = NOW() 
                            WHERE applicant_id = ?";
                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute([$_POST['applicant_id']]);
                    
                    if ($success) {
                        if (isset($logger)) {
                            $logger->info('RECRUITMENT', 'Applicant archived', 
                                "Applicant ID: {$_POST['applicant_id']}");
                        }
                        $message = "Applicant archived successfully!";
                        $messageType = "success";
                    } else {
                        throw new Exception("Failed to archive applicant");
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('RECRUITMENT', 'Failed to archive applicant', $e->getMessage());
                    }
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'unarchive':
                try {
                    $sql = "UPDATE applicant SET application_status = 'Pending', archived_at = NULL 
                            WHERE applicant_id = ?";
                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute([$_POST['applicant_id']]);
                    
                    if ($success) {
                        if (isset($logger)) {
                            $logger->info('RECRUITMENT', 'Applicant unarchived', 
                                "Applicant ID: {$_POST['applicant_id']}");
                        }
                        $message = "Applicant restored successfully!";
                        $messageType = "success";
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('RECRUITMENT', 'Failed to unarchive applicant', $e->getMessage());
                    }
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'delete':
                try {
                    // First delete related interviews
                    $deleteSql = "DELETE FROM interview WHERE applicant_id = ?";
                    $deleteStmt = $conn->prepare($deleteSql);
                    $deleteStmt->execute([$_POST['applicant_id']]);
                    
                    // Then delete the applicant
                    $sql = "DELETE FROM applicant WHERE applicant_id = ?";
                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute([$_POST['applicant_id']]);
                    
                    if ($success) {
                        if (isset($logger)) {
                            $logger->info('RECRUITMENT', 'Applicant deleted', 
                                "Applicant ID: {$_POST['applicant_id']}");
                        }
                        $message = "Applicant deleted successfully!";
                        $messageType = "success";
                    } else {
                        throw new Exception("Failed to delete applicant");
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('RECRUITMENT', 'Failed to delete applicant', $e->getMessage());
                    }
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch applicants with filters
$position_filter = $_GET['position'] ?? '';
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';
$show_archived = isset($_GET['archived']) && $_GET['archived'] == '1';

$sql = "SELECT a.*, 
        r.job_title, 
        d.department_name,
        i.interview_date,
        i.interview_result
        FROM applicant a
        LEFT JOIN recruitment r ON a.recruitment_id = r.recruitment_id
        LEFT JOIN department d ON r.department_id = d.department_id
        LEFT JOIN interview i ON a.applicant_id = i.applicant_id
        WHERE 1=1";

$params = [];

// Filter by archived status
if ($show_archived) {
    $sql .= " AND a.application_status = 'Archived'";
} else {
    $sql .= " AND a.application_status != 'Archived'";
}

if ($position_filter) {
    $sql .= " AND r.job_title LIKE ?";
    $params[] = "%$position_filter%";
}

if ($department_filter) {
    $sql .= " AND d.department_name LIKE ?";
    $params[] = "%$department_filter%";
}

if ($status_filter) {
    $sql .= " AND a.application_status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY a.applicant_id DESC";

$applicants = fetchAll($conn, $sql, $params);

// Count statistics (excluding archived)
$total_applicants = 0;
$to_interview = 0;
$to_evaluate = 0;

$statsSql = "SELECT 
             COUNT(*) as total,
             SUM(CASE WHEN application_status = 'To Interview' THEN 1 ELSE 0 END) as interview,
             SUM(CASE WHEN application_status = 'To Evaluate' THEN 1 ELSE 0 END) as evaluate
             FROM applicant
             WHERE application_status != 'Archived'";
$stats = fetchOne($conn, $statsSql);

if ($stats) {
    $total_applicants = $stats['total'];
    $to_interview = $stats['interview'];
    $to_evaluate = $stats['evaluate'];
}

// Count archived
$archivedSql = "SELECT COUNT(*) as archived FROM applicant WHERE application_status = 'Archived'";
$archivedStats = fetchOne($conn, $archivedSql);
$archived_count = $archivedStats['archived'] ?? 0;

// Fetch recruitment positions for dropdown
$recruitments = fetchAll($conn, "SELECT r.*, d.department_name 
                                 FROM recruitment r 
                                 LEFT JOIN department d ON r.department_id = d.department_id 
                                 WHERE r.status = 'Open' 
                                 ORDER BY r.date_posted DESC");

// If no recruitment positions exist, show message
$noRecruitments = empty($recruitments);
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
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold">Recruitment <?php echo $show_archived ? '- Archived' : ''; ?></h1>
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

            <?php if ($noRecruitments && !$show_archived): ?>
                <div class="mb-4 p-4 rounded-lg bg-yellow-100 text-yellow-800">
                    <strong>No open recruitment positions found!</strong><br>
                    <small>Please create recruitment positions in the Calendar page or contact administrator to add job openings.</small>
                </div>
            <?php endif; ?>

            <!-- Toggle Archive View Button -->
            <div class="mb-4">
                <a href="?archived=<?php echo $show_archived ? '0' : '1'; ?>" 
                   class="inline-block bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg font-medium text-sm">
                    <?php echo $show_archived ? '← Back to Active Applicants' : '📦 View Archived (' . $archived_count . ')'; ?>
                </a>
            </div>

            <?php if (!$show_archived): ?>
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 lg:gap-6 mb-4 lg:mb-6">
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6 cursor-pointer hover:bg-teal-800 transition-colors" onclick="filterByStatus('all')">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">Total Applicant</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $total_applicants; ?></p>
                </div>
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6 cursor-pointer hover:bg-teal-800 transition-colors" onclick="filterByStatus('To Interview')">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">To Interview</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $to_interview; ?></p>
                </div>
                <div class="bg-teal-700 text-white rounded-lg shadow-lg p-4 lg:p-6 cursor-pointer hover:bg-teal-800 transition-colors" onclick="filterByStatus('To Evaluate')">
                    <h3 class="text-xs sm:text-sm font-medium opacity-90 mb-2">To Evaluate</h3>
                    <p class="text-2xl sm:text-3xl lg:text-4xl font-bold"><?php echo $to_evaluate; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filter and Search Section -->
            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-4 lg:mb-6">
                <?php if (!$show_archived): ?>
                <form method="GET" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <input type="text" name="position" value="<?php echo htmlspecialchars($position_filter); ?>" 
                        placeholder="Position"
                        onchange="this.form.submit()"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <input type="text" name="department" value="<?php echo htmlspecialchars($department_filter); ?>" 
                        placeholder="Department"
                        onchange="this.form.submit()"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    <select name="status" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="To Interview" <?php echo $status_filter === 'To Interview' ? 'selected' : ''; ?>>To Interview</option>
                        <option value="To Evaluate" <?php echo $status_filter === 'To Evaluate' ? 'selected' : ''; ?>>To Evaluate</option>
                        <option value="Hired" <?php echo $status_filter === 'Hired' ? 'selected' : ''; ?>>Hired</option>
                        <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                            <span class="hidden sm:inline">Search</span>
                            <span class="sm:hidden">🔍</span>
                        </button>
                        <button type="button" onclick="clearFilters()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                            Clear
                        </button>
                        <button type="button" onclick="openAddModal()" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg font-medium text-sm whitespace-nowrap">
                            + Add
                        </button>
                    </div>
                </form>
                <?php endif; ?>

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
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Interview Date</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applicants as $applicant): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm"><?php echo $applicant['applicant_id']; ?></td>
                                    <td class="px-3 py-2 text-sm"><?php echo htmlspecialchars($applicant['full_name']); ?></td>
                                    <td class="px-3 py-2 text-sm"><?php echo htmlspecialchars($applicant['job_title'] ?? 'N/A'); ?></td>
                                    <td class="px-3 py-2 text-sm"><?php echo htmlspecialchars($applicant['department_name'] ?? 'N/A'); ?></td>
                                    <td class="px-3 py-2 text-sm"><?php echo htmlspecialchars($applicant['contact_number'] ?? 'N/A'); ?></td>
                                    <td class="px-3 py-2 text-sm"><?php echo $applicant['interview_date'] ? date('M d, Y', strtotime($applicant['interview_date'])) : '-'; ?></td>
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            <?php 
                                            if ($applicant['application_status'] === 'Pending') echo 'bg-blue-100 text-blue-800';
                                            elseif ($applicant['application_status'] === 'To Interview') echo 'bg-yellow-100 text-yellow-800';
                                            elseif ($applicant['application_status'] === 'To Evaluate') echo 'bg-purple-100 text-purple-800';
                                            elseif ($applicant['application_status'] === 'Hired') echo 'bg-green-100 text-green-800';
                                            elseif ($applicant['application_status'] === 'Archived') echo 'bg-gray-100 text-gray-800';
                                            else echo 'bg-red-100 text-red-800';
                                            ?>">
                                            <?php echo $applicant['application_status']; ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex gap-2">
                                            <button onclick='viewApplicant(<?php echo json_encode($applicant); ?>)'
                                                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs">
                                                View
                                            </button>
                                            <?php if ($show_archived): ?>
                                                <button onclick='unarchiveApplicant(<?php echo $applicant['applicant_id']; ?>)'
                                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs">
                                                    Restore
                                                </button>
                                                <button onclick='deleteApplicant(<?php echo $applicant['applicant_id']; ?>)'
                                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs">
                                                    Delete
                                                </button>
                                            <?php else: ?>
                                                <button onclick='updateStatus(<?php echo json_encode($applicant); ?>)'
                                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs">
                                                    Update
                                                </button>
                                                <button onclick='archiveApplicant(<?php echo $applicant['applicant_id']; ?>)'
                                                    class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-xs">
                                                    Archive
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="mobile-card space-y-3">
                    <?php foreach ($applicants as $applicant): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($applicant['full_name']); ?></h3>
                                    <p class="text-xs text-gray-500">ID: <?php echo $applicant['applicant_id']; ?></p>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    <?php 
                                    if ($applicant['application_status'] === 'Pending') echo 'bg-blue-100 text-blue-800';
                                    elseif ($applicant['application_status'] === 'To Interview') echo 'bg-yellow-100 text-yellow-800';
                                    elseif ($applicant['application_status'] === 'To Evaluate') echo 'bg-purple-100 text-purple-800';
                                    elseif ($applicant['application_status'] === 'Hired') echo 'bg-green-100 text-green-800';
                                    elseif ($applicant['application_status'] === 'Archived') echo 'bg-gray-100 text-gray-800';
                                    else echo 'bg-red-100 text-red-800';
                                    ?>">
                                    <?php echo $applicant['application_status']; ?>
                                </span>
                            </div>
                            <div class="space-y-2 mb-3 text-sm">
                                <div><span class="text-gray-500">Position:</span> <?php echo htmlspecialchars($applicant['job_title'] ?? 'N/A'); ?></div>
                                <div><span class="text-gray-500">Department:</span> <?php echo htmlspecialchars($applicant['department_name'] ?? 'N/A'); ?></div>
                                <div><span class="text-gray-500">Contact:</span> <?php echo htmlspecialchars($applicant['contact_number'] ?? 'N/A'); ?></div>
                                <div><span class="text-gray-500">Interview:</span> <?php echo $applicant['interview_date'] ? date('M d, Y', strtotime($applicant['interview_date'])) : '-'; ?></div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick='viewApplicant(<?php echo json_encode($applicant); ?>)'
                                    class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm">
                                    View
                                </button>
                                <?php if ($show_archived): ?>
                                    <button onclick='unarchiveApplicant(<?php echo $applicant['applicant_id']; ?>)'
                                        class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm">
                                        Restore
                                    </button>
                                <?php else: ?>
                                    <button onclick='updateStatus(<?php echo json_encode($applicant); ?>)'
                                        class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm">
                                        Update
                                    </button>
                                    <button onclick='archiveApplicant(<?php echo $applicant['applicant_id']; ?>)'
                                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm">
                                        📦
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Applicant Modal -->
    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Add Applicant</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Full Name *</label>
                            <input type="text" name="full_name" required
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Email *</label>
                            <input type="email" name="email" required
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Contact Number *</label>
                            <input type="text" name="contact_number" required
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Position *</label>
                            <select name="recruitment_id" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                                <option value="">Select Position</option>
                                <?php foreach ($recruitments as $rec): ?>
                                    <option value="<?php echo $rec['recruitment_id']; ?>">
                                        <?php echo htmlspecialchars($rec['job_title'] . ' - ' . ($rec['department_name'] ?? 'No Dept')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Interview Date (Optional)</label>
                            <input type="date" name="interview_date"
                                min="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                            <p class="text-xs text-gray-500 mt-1">If set, status will be "To Interview"</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Resume File (URL)</label>
                            <input type="text" name="resume_file"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-teal-700 hover:bg-teal-800 text-white px-4 py-3 rounded-lg font-medium">
                            Add Applicant
                        </button>
                        <button type="button" onclick="closeAddModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-lg font-medium">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View/Update Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Applicant Details</h3>
                <div id="applicantDetails" class="space-y-3 mb-6"></div>
                <button onclick="closeViewModal()" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-lg font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Update Status</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="applicant_id" id="updateApplicantId">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">New Status</label>
                        <select name="status" id="updateStatus" required
                            class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                            <option value="Pending">Pending</option>
                            <option value="To Interview">To Interview</option>
                            <option value="To Evaluate">To Evaluate</option>
                            <option value="Hired">Hired</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-3 rounded-lg font-medium">
                            Update
                        </button>
                        <button type="button" onclick="closeUpdateModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-lg font-medium">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Archive Confirmation Modal -->
    <div id="archiveModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Archive Applicant</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to archive this applicant? They can be restored later from the archived section.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="applicant_id" id="archiveApplicantId">
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-lg font-medium">
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

    <!-- Unarchive Confirmation Modal -->
    <div id="unarchiveModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Restore Applicant</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to restore this applicant? Their status will be set to "Pending".</p>
                <form method="POST">
                    <input type="hidden" name="action" value="unarchive">
                    <input type="hidden" name="applicant_id" id="unarchiveApplicantId">
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-xl font-bold text-red-600 mb-4">⚠️ Delete Applicant</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to <strong>permanently delete</strong> this applicant? This action cannot be undone!</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="applicant_id" id="deleteApplicantId">
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg font-medium">
                            Yes, Delete Permanently
                        </button>
                        <button type="button" onclick="closeDeleteModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-lg font-medium">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
        function filterByStatus(status) {
            const url = new URL(window.location.href);
            url.searchParams.delete('position');
            url.searchParams.delete('department');
            if (status === 'all') {
                url.searchParams.delete('status');
            } else {
                url.searchParams.set('status', status);
            }
            window.location.href = url.toString();
        }

        function clearFilters() {
            window.location.href = 'recruitment.php';
        }

        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        function viewApplicant(applicant) {
            const details = `
                <div class="text-sm">
                    <p><strong>Name:</strong> ${applicant.full_name}</p>
                    <p><strong>Email:</strong> ${applicant.email || 'N/A'}</p>
                    <p><strong>Contact:</strong> ${applicant.contact_number || 'N/A'}</p>
                    <p><strong>Position:</strong> ${applicant.job_title || 'N/A'}</p>
                    <p><strong>Department:</strong> ${applicant.department_name || 'N/A'}</p>
                    <p><strong>Status:</strong> ${applicant.application_status}</p>
                    <p><strong>Interview:</strong> ${applicant.interview_date ? new Date(applicant.interview_date).toLocaleDateString() : 'Not scheduled'}</p>
                </div>
            `;
            document.getElementById('applicantDetails').innerHTML = details;
            document.getElementById('viewModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        function updateStatus(applicant) {
            document.getElementById('updateApplicantId').value = applicant.applicant_id;
            document.getElementById('updateStatus').value = applicant.application_status;
            document.getElementById('updateModal').classList.remove('hidden');
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').classList.add('hidden');
        }

        function archiveApplicant(applicantId) {
            document.getElementById('archiveApplicantId').value = applicantId;
            document.getElementById('archiveModal').classList.remove('hidden');
        }

        function closeArchiveModal() {
            document.getElementById('archiveModal').classList.add('hidden');
        }

        function unarchiveApplicant(applicantId) {
            document.getElementById('unarchiveApplicantId').value = applicantId;
            document.getElementById('unarchiveModal').classList.remove('hidden');
        }

        function closeUnarchiveModal() {
            document.getElementById('unarchiveModal').classList.add('hidden');
        }

        function deleteApplicant(applicantId) {
            document.getElementById('deleteApplicantId').value = applicantId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
    </script>
</body>
</html>