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
    $check_stmt = mysqli_prepare($conn, $check_query);
    
    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "ii", $session_id, $faculty_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Close the check statement first
            mysqli_stmt_close($check_stmt);
            
            // Stop the session using a NEW prepared statement
            $update_query = "UPDATE sessions SET is_active = 0 WHERE session_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, "i", $session_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $_SESSION['success_message'] = "Session stopped successfully! Students can no longer mark attendance.";
                } else {
                    $_SESSION['error_message'] = "Failed to stop session. Please try again.";
                }
                mysqli_stmt_close($update_stmt);
            } else {
                $_SESSION['error_message'] = "Database error: Could not prepare update statement.";
            }
        } else {
            $_SESSION['error_message'] = "Session not found, already stopped, or you don't have permission.";
            mysqli_stmt_close($check_stmt);
        }
    } else {
        $_SESSION['error_message'] = "Database error: Could not prepare check statement.";
    }
} else {
    $_SESSION['error_message'] = "Invalid session ID.";
}

// Redirect back to dashboard
header("Location: faculty_dashboard.php");
exit;
?>
