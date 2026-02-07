<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = $_POST['subject_id'];
    $section_targeted = $_POST['section_targeted'];
    $class_type = $_POST['class_type'];
    
    // Generate base session ID
    $base_session_id = uniqid('ses_', true);
    $current_time = date('Y-m-d H:i:s');
    
    if ($class_type === 'lab') {
        // ============= CREATE 3 LAB SESSIONS =============
        $lab_types = [
            'pre-lab' => 0,    // Starts immediately
            'during-lab' => 1, // Starts after 1 hour  
            'post-lab' => 2    // Starts after 2 hours
        ];
        
        $created_sessions = 0;
        $first_session_id = null;
        
        foreach ($lab_types as $lab_type => $hour_delay) {
            $session_id = $base_session_id . '_' . substr($lab_type, 0, 1);
            $scheduled_start = date('Y-m-d H:i:s', strtotime("+{$hour_delay} hours"));
            
            // Check if lab_type column exists
            $check_column = mysqli_query($conn, "SHOW COLUMNS FROM sessions LIKE 'lab_type'");
            $has_lab_type = mysqli_num_rows($check_column) > 0;
            
            if ($has_lab_type) {
                // Insert with lab_type column
                $query = "INSERT INTO sessions 
                         (session_id, faculty_id, subject_id, section_targeted, class_type, lab_type, created_at, scheduled_start, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = mysqli_prepare($conn, $query);
                
                // Only pre-lab is active immediately
                $is_active = ($lab_type == 'pre-lab') ? 1 : 0;
                mysqli_stmt_bind_param($stmt, "ssisssssi", 
                    $session_id, 
                    $faculty_id, 
                    $subject_id, 
                    $section_targeted, 
                    $class_type, 
                    $lab_type, 
                    $current_time,
                    $scheduled_start,
                    $is_active
                );
            } else {
                // Insert without lab_type column
                $query = "INSERT INTO sessions 
                         (session_id, faculty_id, subject_id, section_targeted, class_type, created_at, scheduled_start, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = mysqli_prepare($conn, $query);
                
                // Only pre-lab is active immediately
                $is_active = ($lab_type == 'pre-lab') ? 1 : 0;
                mysqli_stmt_bind_param($stmt, "ssisssi", 
                    $session_id, 
                    $faculty_id, 
                    $subject_id, 
                    $section_targeted, 
                    $class_type, 
                    $current_time,
                    $scheduled_start,
                    $is_active
                );
            }
            
            if (mysqli_stmt_execute($stmt)) {
                $created_sessions++;
                
                // Store first session ID for redirection
                if ($lab_type == 'pre-lab') {
                    $first_session_id = $session_id;
                    $_SESSION['current_session'] = $session_id;
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        if ($created_sessions == 3) {
            $_SESSION['success_message'] = "✅ 3 Lab Sessions Created Successfully!<br>
                                           • Pre-Lab: Active now (1 hour)<br>
                                           • During-Lab: Starts in 1 hour<br>
                                           • Post-Lab: Starts in 2 hours";
            
            // Redirect to pre-lab session scanner
            header('Location: faculty_scan.php?session_id=' . urlencode($first_session_id));
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to create all lab sessions. Created: {$created_sessions}/3";
            header('Location: faculty_dashboard.php');
            exit;
        }
        
    } else {
        // ============= CREATE NORMAL SINGLE SESSION =============
        $query = "INSERT INTO sessions 
                 (session_id, faculty_id, subject_id, section_targeted, class_type, created_at, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssiss", 
            $base_session_id, 
            $faculty_id, 
            $subject_id, 
            $section_targeted, 
            $class_type,
            $current_time
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['current_session'] = $base_session_id;
            $_SESSION['success_message'] = "Attendance session started successfully!";
            mysqli_stmt_close($stmt);

            // Redirect with session ID
            header('Location: faculty_scan.php?session_id=' . urlencode($base_session_id));
            exit;
        } else {
            $_SESSION['error_message'] = "Error starting session: " . mysqli_error($conn);
            mysqli_stmt_close($stmt);
            header('Location: faculty_dashboard.php');
            exit;
        }
    }
} else {
    // If not POST request, redirect to dashboard
    header('Location: faculty_dashboard.php');
    exit;
}
?>
