<?php
session_start();



if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
   
    if (!empty($email) && !empty($password)) {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Kinueh Valer';
        $_SESSION['user_role'] = 'Human Resource Personnel';
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-2xl p-8 w-full max-w-md">
 
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 bg-gray-300 rounded-full"></div>
        </div>
        
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">HRIS Login</h2>
        
        <form method="POST" action="">
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
            
            <!-- Login Button Uncomment na lang -->
            <!-- <button 
                type="submit"
                class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-3 rounded-lg transition duration-200"
            >
                Login
            </button> -->

            <button 
                type="button"
                onclick="window.location.href='dashboard.php'"
                class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-3 rounded-lg transition duration-200"
            >
                Login
            </button>
        </form>
        
        <p class="text-center text-gray-600 text-sm mt-6">
            Forgot password? <a href="#" class="text-teal-600 hover:underline">Reset here</a>
        </p>
    </div>
</body>
</html>