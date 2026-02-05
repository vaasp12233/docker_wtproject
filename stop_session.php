<?php
session_start();
require_once 'config.php';

// Security check
if (!isset($_SESSION['faculty_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if ($session_id > 0) {
    // Verify faculty owns this session
    $check_query = "SELECT * FROM sessions WHERE session_id = '$session_id' AND faculty_id = '$faculty_id' AND is_active = 1";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Stop the session - REMOVED end_time from query since column doesn't exist
        $update_query = "UPDATE sessions SET is_active = 0 WHERE session_id = '$session_id'";
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success_message'] = "Session stopped successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to stop session. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Session not found or already stopped.";
    }
}

// Redirect back to dashboard
header("Location: faculty_dashboard.php");
exit;
?>