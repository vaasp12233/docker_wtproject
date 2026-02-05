<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

$session_id = isset($_GET['session_id']) ? $conn->real_escape_string($_GET['session_id']) : 0;

$query = "SELECT a.*, s.full_name, s.email, s.section 
          FROM attendance a 
          JOIN students s ON a.student_id = s.student_id 
          WHERE a.session_id = '$session_id' 
          ORDER BY a.scanned_at DESC";

$result = mysqli_query($conn, $query);
$attendance = [];

while ($row = mysqli_fetch_assoc($result)) {
    $attendance[] = $row;
}

echo json_encode([
    'success' => true,
    'attendance' => $attendance
]);
?>