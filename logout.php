<?php
/**
 * Logout Handler
 * File: logout.php
 */

// Start session
session_start();

// Include database and logger
require_once 'config/database.php';

// Log the logout action
if (isset($logger) && isset($_SESSION['username'])) {
    $logger->info('AUTH', 'User logged out', 
        "Username: {$_SESSION['username']}, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

// Unset all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page with a message
header('Location: index.php?logout=success');
exit;