<?php
session_start();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['admin_login'])) {
        // Admin login
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (!empty($email) && !empty($password)) {
            // TODO: Validate against database
            $_SESSION['user_id'] = 1;
            $_SESSION['user_name'] = 'Kinueh Valer';
            $_SESSION['user_role'] = 'Human Resource Personnel';
            header('Location: dashboard.php');
            exit;
        }
    } else {
        // Employee time-in
        $employee_number = $_POST['employee_number'] ?? '';
        
        if (!empty($employee_number)) {
            // TODO: Validate employee number and record time-in
            $success_message = "Time-in recorded successfully!";
         
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS - Employee Time-In</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center">
    <?php include 'includes/indexheader.php'; ?>
    
    <!-- Employee Time-In Form (Default/Visible) -->
    <div id="employeeForm" class="bg-white rounded-lg mt-4 shadow-2xl p-8 w-full max-w-md">
        <div class="flex justify-center mb-6">
            <img src="assets/LOGO.png" alt="HRIS Logo" class="w-20 h-20 object-cover rounded-full" loading="lazy">
        </div>
        
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Employee Time-In</h2>
        <p class="text-center text-gray-600 text-sm mb-8">Enter your employee number</p>

        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-center">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-6">
                <input 
                    type="text" 
                    name="employee_number" 
                    id="employee_number"
                    placeholder="Employee Number"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                    required
                >
            </div>
            
            <button 
                type="submit"
                class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-3 rounded-lg transition duration-200"
            >
                Time In
            </button>
        </form>
        
        <p class="text-center text-gray-600 text-sm mt-6">
            Current Time: <span id="currentTime" class="font-semibold"></span>
        </p>

        <!-- Secret ng mga malupit -->
        <div id="secretTrigger" class="text-center mt-2 cursor-pointer select-none opacity-0 hover:opacity-10 transition-opacity">
            <p class="text-xs text-gray-400">v1.0</p>
        </div>
    </div>

    <!-- Admin Login Form (Hidden) -->
    <div id="adminForm" class="bg-white rounded-lg shadow-2xl p-8 w-full max-w-md hidden">
        <div class="flex justify-center mb-6">
            <img src="assets/LOGO.png" alt="HRIS Logo" class="w-20 h-20 object-cover rounded-full" loading="lazy">
        </div>
        
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">HRIS Login</h2>
        
        <form method="POST" action="">
            <input type="hidden" name="admin_login" value="1">
            
            <!-- Email -->
            <div class="mb-6">
                <input 
                    type="email" 
                    name="email" 
                    placeholder="Email Address"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                    required
                >
            </div>
            
            <!-- Password -->
            <div class="mb-6">
                <input 
                    type="password" 
                    name="password" 
                    placeholder="Password"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                    required
                >
            </div>
            
            <!-- Uncomment real login, comment bypass -->
            <button 
                type="submit"
                class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-3 rounded-lg transition duration-200"
            >
                Login
            </button>

            <!-- Development bypass - comment this out pag oki na -->
            <!-- <button 
                type="button"
                onclick="window.location.href='dashboard.php'"
                class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-3 rounded-lg transition duration-200"
            >
                Login
            </button> -->
        </form>
        
        <p class="text-center text-gray-600 text-sm mt-6">
            Forgot password? <a href="#" class="text-teal-600 hover:underline">Reset here</a>
        </p>

        <button 
            onclick="toggleForms()"
            class="mt-4 w-full text-gray-600 text-sm hover:text-gray-800"
        >
            ← Back to Employee Time-In
        </button>
    </div>

    <script src="js/login.js"></script>
</body>
</html>