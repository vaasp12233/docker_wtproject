// ... existing error_reporting and getenv() setup ...

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: 'cse_attendance';
$port = getenv('DB_PORT') ?: 3306;

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Connection function to handle reconnection attempts AND SSL
function connectDatabaseWithSSL($host, $user, $pass, $name, $port) {
    $maxAttempts = 3;
    $attempt = 1;
    
    while ($attempt <= $maxAttempts) {
        $conn = mysqli_init();
        
        // --- AIVEN SSL REQUIREMENT ---
        // This sets the SSL mode necessary for Aiven to accept the connection
        mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

        // Use real_connect which supports the SSL handshake
        $success = @mysqli_real_connect($conn, $host, $user, $pass, $name, $port);
        
        if ($success) {
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
$conn = connectDatabaseWithSSL($host, $user, $pass, $name, $port);

// ... rest of your existing error logging logic below this line ...

if (!$conn) {
    // SILENT error handling - log but don't output
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
// NO CLOSING ?> TAG
