<?php
// config.php - Render + Aiven MySQL Configuration
// NO OUTPUT VERSION - Prevents header errors

// Turn off error display but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Get environment variables from Render with fallback for local development
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: 'cse_attendance';
$port = getenv('DB_PORT') ?: 3306;

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Connection function to handle reconnection attempts
function connectDatabase($host, $user, $pass, $name, $port) {
    $maxAttempts = 3;
    $attempt = 1;
    
    while ($attempt <= $maxAttempts) {
        $conn = @mysqli_connect($host, $user, $pass, $name, $port);
        
        if ($conn) {
            return $conn;
        }
        
        if ($attempt < $maxAttempts) {
            error_log("Connection attempt $attempt failed. Retrying in 2 seconds...");
            sleep(2);
        }
        
        $attempt++;
    }
    
    return false;
}

// Silent database connection with retry logic
$conn = connectDatabase($host, $user, $pass, $name, $port);

if (!$conn) {
    // SILENT error handling - log but don't output
    $error_msg = mysqli_connect_error() ?: 'Unknown connection error';
    error_log("Aiven MySQL Connection Failed: $error_msg | Host: $host:$port | User: $user");
    
    // Set connection to null - will be checked in application code
    $conn = null;
} else {
    // Connection successful
    mysqli_set_charset($conn, 'utf8mb4');
    
    // Set timezone in MySQL
    @mysqli_query($conn, "SET time_zone = '+05:30'");
    
    // Set SQL mode to be compatible
    @mysqli_query($conn, "SET sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    
    // Set connection timeout
    @mysqli_query($conn, "SET SESSION wait_timeout = 300");
    
    // Connection successful - log quietly if needed
    // error_log("Database connected successfully to $host:$port");
}

// NO CLOSING ?> TAG - This prevents whitespace issues
