<?php

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);

session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config/database.php';
require_once 'includes/auth.php';

$error_message = '';
$success_message = '';
$show_admin_form = false;
$auto_redirect = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['admin_login'])) {
        // ============================================
        // ADMIN LOGIN FORM
        // ============================================
        $show_admin_form = true; 
        
        $username = sanitize($conn, $_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!empty($username) && !empty($password)) {
            $result = loginUser($conn, $username, $password);
            
            if ($result['success']) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error_message = $result['message'];
            }
        } else {
            $error_message = "Please enter both username and password";
        }
        
    } elseif (isset($_POST['employee_action'])) {
        // ============================================
        // EMPLOYEE TIME-IN/OUT FORM
        // ============================================
        $employee_number = sanitize($conn, $_POST['employee_number'] ?? '');
        
        if (!empty($employee_number)) {
            
            $sql = "SELECT employee_id, first_name, last_name FROM employee WHERE employee_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$employee_number]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employee) {
                // Check if employee has an active time-in (no time-out yet)
                $check_sql = "SELECT attendance_id, time_in FROM attendance 
                             WHERE employee_id = ? AND time_out IS NULL 
                             ORDER BY time_in DESC LIMIT 1";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->execute([$employee['employee_id']]);
                $active_attendance = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($active_attendance) {
                    // Employee is clocked in, so clock them out
                    $result = recordTimeOut($conn, $employee['employee_id']);
                    
                    if ($result['success']) {
                        $success_message = "Goodbye, " . $employee['first_name'] . " " . $employee['last_name'] . "! " . $result['message'];
                        $auto_redirect = true;
                    } else {
                        $error_message = $result['message'];
                    }
                } else {
                    // Employee is not clocked in, so clock them in
                    $result = recordTimeIn($conn, $employee['employee_id']);
                    
                    if ($result['success']) {
                        $success_message = "Welcome, " . $employee['first_name'] . " " . $employee['last_name'] . "! " . $result['message'];
                        $auto_redirect = true;
                    } else {
                        $error_message = $result['message'];
                    }
                }
            } else {
                $error_message = "Employee ID not found";
            }
        } else {
            $error_message = "Please enter your employee number";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS - Employee Time-In/Out</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center">
    <?php include 'includes/indexheader.php'; ?>
  
    <div id="employeeForm" class="bg-white rounded-lg mt-24 shadow-2xl p-8 w-full max-w-md <?php echo $show_admin_form ? 'hidden' : ''; ?>">
        <div class="flex justify-center mb-6">
            <img src="assets/LOGO.png" alt="HRIS Logo" class="w-20 h-20 object-cover rounded-full" loading="lazy">
        </div>
        
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Employee Attendance</h2>
        <p class="text-center text-gray-600 text-sm mb-8">Enter your employee number to clock in or out</p>

        <?php if ($success_message && !$show_admin_form): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-center">
                <?php echo htmlspecialchars($success_message); ?>
                <div class="mt-2 text-sm">
                    Returning to form in <span id="countdown" class="font-bold">3</span> seconds...
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message && !$show_admin_form): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-center">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="index.php" id="attendanceForm">
            <input type="hidden" name="employee_action" value="1">
            
            <div class="mb-6">
                <input 
                    type="text" 
                    name="employee_number" 
                    id="employee_number"
                    placeholder="Employee Number"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                    required
                    autofocus
                    <?php echo $auto_redirect ? 'disabled' : ''; ?>
                >
            </div>
            
            <button 
                type="submit"
                class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-3 rounded-lg transition duration-200"
                <?php echo $auto_redirect ? 'disabled' : ''; ?>
            >
                Clock In / Out
            </button>
        </form>
        
        <p class="text-center text-gray-600 text-sm mt-6">
            Current Time: <span id="currentTime" class="font-semibold"></span>
        </p>

        <!-- Secret trigger -->
        <div id="secretTrigger" class="text-center mt-2 cursor-pointer select-none opacity-0 hover:opacity-10 transition-opacity">
            <p class="text-xs text-gray-400">v1.0</p>
        </div>
    </div>

    <!-- Admin Login Form (Hidden) -->
    <div id="adminForm" class="bg-white rounded-lg mt-24 shadow-2xl p-8 w-full max-w-md <?php echo $show_admin_form ? '' : 'hidden'; ?>">
        <div class="flex justify-center mb-6">
            <img src="assets/LOGO.png" alt="HRIS Logo" class="w-20 h-20 object-cover rounded-full" loading="lazy">
        </div>
        
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Admin Login</h2>
        
        <?php if ($error_message && $show_admin_form): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-center">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="index.php">
            <input type="hidden" name="admin_login" value="1">
            
            <div class="mb-6">
                <input 
                    type="text" 
                    name="username" 
                    placeholder="Username"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                    required
                    autofocus
                >
            </div>
            
            <div class="mb-6">
                <input 
                    type="password" 
                    name="password" 
                    placeholder="Password"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                    required
                >
            </div>
            
            <button 
                type="submit"
                class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-3 rounded-lg transition duration-200"
            >
                Login
            </button>
        </form>
        

        <button 
            onclick="toggleForms()"
            class="mt-4 w-full text-gray-600 text-sm hover:text-gray-800 transition-colors"
        >
            ← Back to Employee Time-In
        </button>
    </div>

    <script src="js/login.js"></script>
    <script>
        // Auto-redirect after successful time-in/out
        <?php if ($auto_redirect): ?>
        let timeLeft = 3;
        const countdownEl = document.getElementById('countdown');
        
        const countdown = setInterval(() => {
            timeLeft--;
            if (countdownEl) {
                countdownEl.textContent = timeLeft;
            }
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                window.location.href = 'index.php';
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>