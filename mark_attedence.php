<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get JSON data
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

// Validate JSON data
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
if (!isset($data['student_id']) || !isset($data['session_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Sanitize inputs
$student_id = trim($data['student_id']);
$session_id = intval($data['session_id']);

// Validate inputs
if (empty($student_id) || $session_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit;
}

// Verify session is active using prepared statement
$session_check = "SELECT session_id FROM sessions WHERE session_id = ? AND is_active = 1";
$stmt = mysqli_prepare($conn, $session_check);
mysqli_stmt_bind_param($stmt, "i", $session_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) == 0) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'error' => 'Invalid or inactive session']);
    exit;
}
mysqli_stmt_close($stmt);

// Get student details using prepared statement
$student_query = "SELECT student_id, student_name, student_email, section FROM students WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($stmt, "s", $student_id);
mysqli_stmt_execute($stmt);
$student_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($student_result) == 0) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'error' => 'Student not found']);
    exit;
}

$student = mysqli_fetch_assoc($student_result);
mysqli_stmt_close($stmt);

// Check if already marked using prepared statement
$attendance_check = "SELECT id FROM attendance_records WHERE student_id = ? AND session_id = ?";
$stmt = mysqli_prepare($conn, $attendance_check);
mysqli_stmt_bind_param($stmt, "si", $student_id, $session_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'error' => 'Already marked present']);
    exit;
}
mysqli_stmt_close($stmt);

// Mark attendance using prepared statement
$sql = "INSERT INTO attendance_records (student_id, session_id, marked_at) VALUES (?, ?, NOW())";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $student_id, $session_id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode([
        'success' => true,
        'student' => [
            'student_name' => htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'),
            'student_email' => htmlspecialchars($student['student_email'], ENT_QUOTES, 'UTF-8'),
            'section' => htmlspecialchars($student['section'], ENT_QUOTES, 'UTF-8'),
            'student_id' => htmlspecialchars($student['student_id'], ENT_QUOTES, 'UTF-8')
        ]
    ]);
} else {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . mysqli_error($conn)]);
}

// Close database connection if needed
mysqli_close($conn);
?>
