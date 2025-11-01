<?php
session_start();


if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// include 'config/database.php';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  
    $employee_id = $_POST['employee_id'] ?? '';
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
   
    $success_message = "Leave request submitted successfully!";
}

//TEMPORARY DATA LANGS
$leave_requests = [
    [
        'id' => 1,
        'employee_name' => 'Juan Dela Cruz',
        'employee_number' => 'EMP001',
        'leave_type' => 'Sick Leave',
        'start_date' => '2024-11-01',
        'end_date' => '2024-11-03',
        'days' => 3,
        'reason' => 'Flu and fever',
        'status' => 'Pending',
        'date_filed' => '2024-10-25'
    ],
    [
        'id' => 2,
        'employee_name' => 'Maria Santos',
        'employee_number' => 'EMP002',
        'leave_type' => 'Vacation Leave',
        'start_date' => '2024-11-15',
        'end_date' => '2024-11-20',
        'days' => 6,
        'reason' => 'Family vacation',
        'status' => 'Approved',
        'date_filed' => '2024-10-20'
    ],
    [
        'id' => 3,
        'employee_name' => 'Pedro Reyes',
        'employee_number' => 'EMP003',
        'leave_type' => 'Emergency Leave',
        'start_date' => '2024-10-28',
        'end_date' => '2024-10-28',
        'days' => 1,
        'reason' => 'Family emergency',
        'status' => 'Rejected',
        'date_filed' => '2024-10-27'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS - Leave Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body class="bg-gray-100">
    <div class="min-h-screen lg:ml-64">
        <header class="gradient-bg text-white p-4 lg:p-6 shadow-lg">
            <div class="flex items-center justify-between pl-14 lg:pl-0">
                <?php include 'includes/sidebar.php'; ?>
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold">Leave Management</h1>
                <a href="index.php" class="bg-white px-3 py-2 rounded-lg font-medium text-red-600 hover:text-red-700 hover:bg-gray-100 text-xs sm:text-sm">
                    Logout
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="p-4 lg:p-8">
            <?php if (isset($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-6 lg:mb-8">
                <div class="bg-white rounded-lg p-4 lg:p-6 shadow-lg">
                    <h3 class="text-sm text-gray-600 mb-2">Pending Requests</h3>
                    <p class="text-2xl lg:text-3xl font-bold text-yellow-600">12</p>
                </div>

                <div class="bg-white rounded-lg p-4 lg:p-6 shadow-lg">
                    <h3 class="text-sm text-gray-600 mb-2">Approved This Month</h3>
                    <p class="text-2xl lg:text-3xl font-bold text-green-600">25</p>
                </div>

                <div class="bg-white rounded-lg p-4 lg:p-6 shadow-lg">
                    <h3 class="text-sm text-gray-600 mb-2">Rejected This Month</h3>
                    <p class="text-2xl lg:text-3xl font-bold text-red-600">3</p>
                </div>

                <div class="bg-white rounded-lg p-4 lg:p-6 shadow-lg">
                    <h3 class="text-sm text-gray-600 mb-2">Total This Month</h3>
                    <p class="text-2xl lg:text-3xl font-bold text-teal-600">40</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mb-6">
                <button 
                    onclick="openModal()"
                    class="bg-teal-600 hover:bg-teal-700 text-white font-semibold px-6 py-3 rounded-lg transition duration-200"
                >
                    New Leave Request
                </button>
            </div>

            <!-- Leave Requests Table -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="p-4 lg:p-6 border-b border-gray-200">
                    <h3 class="text-lg lg:text-xl font-bold text-gray-800">Leave Requests</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($leave_requests as $request): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($request['employee_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['employee_number']); ?></div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($request['leave_type']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M d', strtotime($request['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $request['days']; ?> day<?php echo $request['days'] > 1 ? 's' : ''; ?>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-900 max-w-xs truncate">
                                    <?php echo htmlspecialchars($request['reason']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <?php 
                                    $status_color = '';
                                    switch($request['status']) {
                                        case 'Pending':
                                            $status_color = 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'Approved':
                                            $status_color = 'bg-green-100 text-green-800';
                                            break;
                                        case 'Rejected':
                                            $status_color = 'bg-red-100 text-red-800';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_color; ?>">
                                        <?php echo htmlspecialchars($request['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm">
                                    <?php if ($request['status'] === 'Pending'): ?>
                                    <button onclick="approveLeave(<?php echo $request['id']; ?>)" class="text-green-600 hover:text-green-900 mr-3">Approve</button>
                                    <button onclick="rejectLeave(<?php echo $request['id']; ?>)" class="text-red-600 hover:text-red-900">Reject</button>
                                    <?php else: ?>
                                    <button onclick="viewLeave(<?php echo $request['id']; ?>)" class="text-teal-600 hover:text-teal-900">View</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- New Leave Request Modal -->
    <div id="leaveModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-900">New Leave Request</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>

            <form method="POST" action="" id="leaveForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee Number</label>
                        <input 
                            type="text" 
                            name="employee_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                            required
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Leave Type</label>
                        <select 
                            name="leave_type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                            required
                        >
                            <option value="">Select Leave Type</option>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Vacation Leave">Vacation Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                            <option value="Maternity Leave">Maternity Leave</option>
                            <option value="Paternity Leave">Paternity Leave</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                        <input 
                            type="date" 
                            name="start_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                            required
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                        <input 
                            type="date" 
                            name="end_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                            required
                        >
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                    <textarea 
                        name="reason"
                        rows="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                        required
                    ></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button 
                        type="button"
                        onclick="closeModal()"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700"
                    >
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/leave.js"></script>
</body>

</html>