<?php
require_once 'config.php';

// 1. FIRST check if user is logged in as faculty
if (!isset($_SESSION['faculty_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

// 2. THEN check if faculty has set a custom password
$faculty_id = $_SESSION['faculty_id'];
$password_check = mysqli_query($conn, "SELECT password FROM faculty WHERE faculty_id = '$faculty_id'");
$faculty = mysqli_fetch_assoc($password_check);

if (empty($faculty['password'])) {
    header('Location: faculty_dashboard.php?error=set_password_first');
    exit;
}

// 3. Then handle the session creation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = $conn->real_escape_string($_POST['subject_id']);
    $section_targeted = $conn->real_escape_string($_POST['section_targeted']);
    $class_type = $conn->real_escape_string($_POST['class_type']);
    
    // Generate unique session code
    $session_code = strtoupper(substr(md5(uniqid()), 0, 8));
    
    // Insert session
    $sql = "INSERT INTO sessions (faculty_id, subject_id, section_targeted, class_type, is_active, start_time) 
            VALUES ('$faculty_id', '$subject_id', '$section_targeted', '$class_type', 1, NOW())";
    
    if (mysqli_query($conn, $sql)) {
        $session_id = mysqli_insert_id($conn);
        $_SESSION['current_session'] = $session_id;
        header('Location: faculty_scan.php?session=' . $session_id);
        exit;
    } else {
        header('Location: faculty_dashboard.php?error=session_failed');
        exit;
    }
} else {
    header('Location: faculty_dashboard.php');
    exit;
}
?>