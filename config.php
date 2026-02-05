<?php
// Database configuration using DB_* variables (as set in Render)
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$pass = getenv('DB_PASS'); 
$name = getenv('DB_NAME'); 
$port = getenv('DB_PORT') ?: 26600; // Default to 26600 for Aiven

date_default_timezone_set('Asia/Kolkata');

// Silent error reporting for production
error_reporting(0);

// Connect to Aiven MySQL
$conn = @mysqli_connect($host, $user, $pass, $name, $port);

if (!$conn) {
    // SILENT error handling - NO OUTPUT to prevent header errors
    error_log("Aiven Connection Failed: " . mysqli_connect_error());
    
    // Store error in session for later use (if session starts elsewhere)
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['db_error'] = "Database connection failed";
    
    // Set connection to false but don't die()
    $conn = false;
} else {
    // Connection successful
    mysqli_set_charset($conn, 'utf8mb4');
}

// NO CLOSING TAG - This prevents whitespace issues
