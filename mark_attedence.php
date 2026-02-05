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
$data = json_decode(file_get_contents('php://input'), true);
$student_id = $conn->real_escape_string($data['student_id']);
$session_id = $conn->real_escape_string($data['session_id']);

// Verify session is active
$session_check = "SELECT * FROM sessions WHERE session_id = '$session_id' AND is_active = 1";
$session_result = mysqli_query($conn, $session_check);

if (mysqli_num_rows($session_result) == 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid or inactive session']);
    exit;
}

// Get student details
$student_query = "SELECT student_id, student_name, student_email, section FROM students WHERE student_id = '$student_id'";
$student_result = mysqli_query($conn, $student_query);

if (mysqli_num_rows($student_result) == 0) {
    echo json_encode(['success' => false, 'error' => 'Student not found']);
    exit;
}

$student = mysqli_fetch_assoc($student_result);

// Check if already marked
$attendance_check = "SELECT * FROM attendance_records WHERE student_id = '$student_id' AND session_id = '$session_id'";
if (mysqli_num_rows(mysqli_query($conn, $attendance_check)) > 0) {
    echo json_encode(['success' => false, 'error' => 'Already marked present']);
    exit;
}

// Mark attendance
$sql = "INSERT INTO attendance_records (student_id, session_id, marked_at) VALUES ('$student_id', '$session_id', NOW())";
if (mysqli_query($conn, $sql)) {
    echo json_encode([
        'success' => true,
        'student' => [
            'student_name' => $student['student_name'],
            'student_email' => $student['student_email'],
            'section' => $student['section'],
            'student_id' => $student['student_id']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>