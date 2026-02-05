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
    // Verify faculty owns this session using prepared statement
    $check_query = "SELECT * FROM sessions WHERE session_id = ? AND faculty_id = ? AND is_active = 1";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "ii", $session_id, $faculty_id);
    mysqli_stmt_execute($stmt);
    $check_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Stop the session using prepared statement
        $update_query = "UPDATE sessions SET is_active = 0 WHERE session_id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "i", $session_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Session stopped successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to stop session. Please try again.";
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Session not found or already stopped.";
    }
    
    // Close the first prepared statement if it exists
    if (isset($stmt) && is_object($stmt)) {
        mysqli_stmt_close($stmt);
    }
} else {
    $_SESSION['error_message'] = "Invalid session ID.";
}

// Redirect back to dashboard
header("Location: faculty_dashboard.php");
exit;
?>
