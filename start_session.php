<?php
require_once 'config.php';
session_start(); // Add session start at the beginning

// 1. FIRST check if user is logged in as faculty
if (!isset($_SESSION['faculty_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

// 2. THEN check if faculty has set a custom password
$faculty_id = $_SESSION['faculty_id'];

// Use prepared statement to prevent SQL injection
$stmt = mysqli_prepare($conn, "SELECT password FROM faculty WHERE faculty_id = ?");
mysqli_stmt_bind_param($stmt, "s", $faculty_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $password);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (empty($password)) {
    header('Location: faculty_dashboard.php?error=set_password_first');
    exit;
}

// 3. Then handle the session creation - ONLY if POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate required fields
    $required_fields = ['subject_id', 'section_targeted', 'class_type'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            header('Location: faculty_dashboard.php?error=missing_fields');
            exit;
        }
    }
    
    // Sanitize and validate inputs
    $subject_id = isset($_POST['subject_id']) ? trim($_POST['subject_id']) : '';
    $section_targeted = isset($_POST['section_targeted']) ? trim($_POST['section_targeted']) : '';
    $class_type = isset($_POST['class_type']) ? trim($_POST['class_type']) : '';
    
    // Validate inputs are not empty
    if (empty($subject_id) || empty($section_targeted) || empty($class_type)) {
        header('Location: faculty_dashboard.php?error=empty_fields');
        exit;
    }
    
    // Generate unique session code
    $session_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    
    // Insert session using prepared statement
    $sql = "INSERT INTO sessions (faculty_id, subject_id, section_targeted, class_type, is_active, start_time) 
            VALUES (?, ?, ?, ?, 1, NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssss", $faculty_id, $subject_id, $section_targeted, $class_type);
    
    if (mysqli_stmt_execute($stmt)) {
        $session_id = mysqli_insert_id($conn);
        $_SESSION['current_session'] = $session_id;
        mysqli_stmt_close($stmt);
        
        // Redirect with session ID
        header('Location: faculty_scan.php?session=' . urlencode($session_id));
        exit;
    } else {
        mysqli_stmt_close($stmt);
        header('Location: faculty_dashboard.php?error=session_failed');
        exit;
    }
} else {
    // If not POST request, redirect to dashboard
    header('Location: faculty_dashboard.php');
    exit;
}
?>
