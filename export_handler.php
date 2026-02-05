<?php
// export_handler.php
require_once 'config.php';

// Security check
if (!isset($_SESSION['faculty_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

// Copy all your export handling code here from bulk_download.php
// (The code inside if(isset($_GET['export'])) { ... })
// Make sure to NOT include header.php in this file

// Your export code here...
if (isset($_GET['export'])) {
    if ($_GET['export'] == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="360_students_attendance_' . date('Y-m-d') . '.xls"');
        // ... rest of export code
    }
}
?>