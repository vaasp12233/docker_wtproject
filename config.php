

<?php
// These will be pulled from Render's settings
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$pass = getenv('DB_PASS'); 
$name = getenv('DB_NAME'); 
$port = getenv('DB_PORT'); 
date_default_timezone_set('Asia/Kolkata');
// Connect to Aiven (Port 26600 is required!)
$conn = mysqli_connect($host, $user, $pass, $name, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>

