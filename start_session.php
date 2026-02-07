<?php
session_start();
require_once 'config.php';

// ============= AUTO-ACTIVATION FUNCTION =============
function updateSessionStatus($conn) {
    $current_time = date('Y-m-d H:i:s');
    
    // Activate ALL lab sessions whose Start_time has arrived
    $activate_query = "UPDATE sessions 
                      SET is_active = 1 
                      WHERE Start_time <= ? 
                      AND is_active = 0
                      AND class_type = 'lab'";
    $stmt = mysqli_prepare($conn, $activate_query);
    mysqli_stmt_bind_param($stmt, "s", $current_time);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Call the function to update status before doing anything
updateSessionStatus($conn);

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = $_POST['subject_id'];
    $section_targeted = $_POST['section_targeted'];
    $class_type = $_POST['class_type'];

    $current_time = date('Y-m-d H:i:s');

    if ($class_type === 'lab') {
        // ============= CREATE 3 LAB SESSIONS =============
        // ALL sessions start at the SAME TIME
        $lab_types = [
            'pre-lab',      // Pre-lab session
            'during-lab',   // During-lab session  
            'post-lab'      // Post-lab session
        ];

        $created_sessions = 0;
        $first_session_id = null;
        
        // All sessions start NOW (same time)
        $scheduled_start = $current_time;

        foreach ($lab_types as $lab_type) {
            // Check if lab_type column exists
            $check_column = mysqli_query($conn, "SHOW COLUMNS FROM sessions LIKE 'lab_type'");
            $has_lab_type = mysqli_num_rows($check_column) > 0;

            if ($has_lab_type) {
                // Insert with lab_type column - session_id will auto-increment
                $query = "INSERT INTO sessions 
                         (faculty_id, subject_id, section_targeted, class_type, lab_type, created_at, Start_time, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, 1)"; // ALL are active immediately

                $stmt = mysqli_prepare($conn, $query);

                mysqli_stmt_bind_param($stmt, "issssss", 
                    $faculty_id, 
                    $subject_id, 
                    $section_targeted, 
                    $class_type, 
                    $lab_type, 
                    $current_time,
                    $scheduled_start
                );
            } else {
                // Insert without lab_type column - session_id will auto-increment
                $query = "INSERT INTO sessions 
                         (faculty_id, subject_id, section_targeted, class_type, created_at, Start_time, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, 1)"; // ALL are active immediately

                $stmt = mysqli_prepare($conn, $query);

                mysqli_stmt_bind_param($stmt, "isssss", 
                    $faculty_id, 
                    $subject_id, 
                    $section_targeted, 
                    $class_type, 
                    $current_time,
                    $scheduled_start
                );
            }

            if (mysqli_stmt_execute($stmt)) {
                $created_sessions++;
                $last_insert_id = mysqli_insert_id($conn);

                // Store first session ID for redirection (pre-lab)
                if ($lab_type == 'pre-lab') {
                    $first_session_id = $last_insert_id;
                    $_SESSION['current_session'] = $last_insert_id;
                }
            }
            mysqli_stmt_close($stmt);
        }

        if ($created_sessions == 3) {
            $_SESSION['success_message'] = "✅ 3 Lab Sessions Created Successfully!<br>
                                           • Pre-Lab: Active Now<br>
                                           • During-Lab: Active Now<br>
                                           • Post-Lab: Active Now<br>
                                           <small><i>All sessions activated simultaneously</i></small>";

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
        // For normal class (lecture), it starts immediately
        $scheduled_start = $current_time;
        
        // Check if lab_type column exists
        $check_column = mysqli_query($conn, "SHOW COLUMNS FROM sessions LIKE 'lab_type'");
        $has_lab_type = mysqli_num_rows($check_column) > 0;

        if ($has_lab_type) {
            // session_id is auto-increment, so don't include it in the insert
            $query = "INSERT INTO sessions 
                     (faculty_id, subject_id, section_targeted, class_type, lab_type, created_at, Start_time, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

            $stmt = mysqli_prepare($conn, $query);
            
            $lab_type_value = 'lecture';
            
            mysqli_stmt_bind_param($stmt, "issssss", 
                $faculty_id, 
                $subject_id, 
                $section_targeted, 
                $class_type, 
                $lab_type_value,
                $current_time,
                $scheduled_start
            );
        } else {
            // session_id is auto-increment, so don't include it in the insert
            $query = "INSERT INTO sessions 
                     (faculty_id, subject_id, section_targeted, class_type, created_at, Start_time, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, 1)";

            $stmt = mysqli_prepare($conn, $query);

            mysqli_stmt_bind_param($stmt, "isssss", 
                $faculty_id, 
                $subject_id, 
                $section_targeted, 
                $class_type, 
                $current_time,
                $scheduled_start
            );
        }

        if (mysqli_stmt_execute($stmt)) {
            $last_insert_id = mysqli_insert_id($conn);
            $_SESSION['current_session'] = $last_insert_id;
            $_SESSION['success_message'] = "Attendance session started successfully!";
            mysqli_stmt_close($stmt);

            // Redirect with session ID
            header('Location: faculty_scan.php?session_id=' . urlencode($last_insert_id));
            exit;
        } else {
            $error = mysqli_error($conn);
            $_SESSION['error_message'] = "Error starting session: " . $error;
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
