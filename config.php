<?php
// config.php - Render + Aiven MySQL Configuration
// NO OUTPUT VERSION - Prevents header errors

// Turn off error display but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Get environment variables from Render
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$pass = getenv('DB_PASS'); 
$name = getenv('DB_NAME'); 
$port = getenv('DB_PORT') ?: 26600; // Default to 26600 for Aiven

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Silent database connection (no output on error)
$conn = @mysqli_connect($host, $user, $pass, $name, $port);

if (!$conn) {
    // SILENT error handling - log but don't output
    $error_msg = mysqli_connect_error();
    error_log("Aiven MySQL Connection Failed: $error_msg | Host: $host:$port | User: $user");
    
    // Set connection to null - will be checked in application code
    $conn = null;
    
    // DO NOT start session here - it might cause header errors
    // DO NOT set $_SESSION here - session may not be started
} else {
    // Connection successful
    mysqli_set_charset($conn, 'utf8mb4');
    
    // Optional: Set timezone in MySQL too
    @mysqli_query($conn, "SET time_zone = '+05:30'");
    
    // Optional: Set SQL mode if needed
    @mysqli_query($conn, "SET sql_mode = ''");
}

// NO CLOSING ?> TAG - This prevents whitespace issues
