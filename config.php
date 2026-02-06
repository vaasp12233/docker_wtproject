
<?php
// config.php - FIXED VERSION for Aiven MySQL
// NO OUTPUT VERSION - Prevents header errors

// Turn off error display but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Get environment variables from Render with fallback
$host = 'mysql-3a56a39e-vaseemlaptop-5078.e.aivencloud.com';
$user = 'avnadmin';
$pass = 'AVNS_rMHOMFGZyD5kLjgzXlt';
$name = 'defaultdb';
$port = 26600;

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Simple connection function for Aiven
function connectToDatabase() {
    global $host, $user, $pass, $name, $port;
    
    $maxAttempts = 3;
    $attempt = 1;
    
    while ($attempt <= $maxAttempts) {
        // Method 1: Try with mysqli_connect (simple)
        $conn = @mysqli_connect($host, $user, $pass, $name, $port);
        
        if ($conn) {
            return $conn;
        }
        
        // Method 2: Try with SSL for Aiven
        $conn = mysqli_init();
        
        // Set SSL options
        mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        
        // Try SSL connection
        mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
        
        if (@mysqli_real_connect($conn, $host, $user, $pass, $name, $port, NULL, MYSQLI_CLIENT_SSL)) {
            return $conn;
        }
        
        if ($attempt < $maxAttempts) {
            error_log("Connection attempt $attempt failed. Retrying...");
            sleep(2);
        }
        
        $attempt++;
    }
    
    return false;
}

// Connect to database
$conn = connectToDatabase();

if (!$conn) {
    // SILENT error handling
    $error_msg = mysqli_connect_error() ?: 'Unknown connection error';
    error_log("Aiven MySQL Connection Failed: $error_msg | Host: $host:$port | User: $user");
    $conn = null;
} else {
    // Connection successful
    mysqli_set_charset($conn, 'utf8mb4');
    @mysqli_query($conn, "SET time_zone = '+05:30'");
    @mysqli_query($conn, "SET sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    @mysqli_query($conn, "SET SESSION wait_timeout = 300");
}
// NO CLOSING TAG
