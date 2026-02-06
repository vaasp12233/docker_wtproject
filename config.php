<?php
// config.php - SECURE VERSION
// DO NOT COMMIT TO GITHUB WITH REAL PASSWORDS

// Turn off error display but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Get environment variables from Render with fallback
// NEVER PUT REAL PASSWORDS HERE
$host = $_ENV['DB_HOST'] ?? 'mysql-3a56a39e-vaseemlaptop-5078.e.aivencloud.com';
$user = $_ENV['DB_USER'] ?? 'avnadmin';
$pass = $_ENV['DB_PASSWORD'] ?? 'AVNS_rMHOMFGZyD5kLjgzXlt'; // USE ENVIRONMENT VARIABLE
$name = $_ENV['DB_NAME'] ?? 'defaultdb';
$port = $_ENV['DB_PORT'] ?? 26600;

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Simple connection function for Aiven
function connectToDatabase() {
    global $host, $user, $pass, $name, $port;

    // Method: Use mysqli with SSL for Aiven
    $conn = mysqli_init();
    
    // Aiven requires SSL
    mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
    mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    
    // Set connection timeout
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    
    // Connect
    $connected = mysqli_real_connect($conn, $host, $user, $pass, $name, $port, NULL, MYSQLI_CLIENT_SSL);
    
    if (!$connected) {
        error_log("Aiven connection failed: " . mysqli_connect_error());
        return false;
    }
    
    return $conn;
}

// Connect to database
$conn = connectToDatabase();

if (!$conn) {
    // Connection failed
    $conn = null;
    // Optionally set a flag for the application
    define('DB_CONNECTED', false);
} else {
    // Connection successful
    mysqli_set_charset($conn, 'utf8mb4');
    define('DB_CONNECTED', true);
}
