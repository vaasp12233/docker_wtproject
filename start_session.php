<?php
session_start();
require_once 'config.php';

// ============= AUTO-ACTIVATION FUNCTION =============
function updateSessionStatus($conn) {
    $current_time = date('Y-m-d H:i:s');
    
    // 1. Activate sessions whose Start_time has arrived
    $activate_query = "UPDATE sessions 
                      SET is_active = 1 
                      WHERE Start_time <= ? 
                      AND is_active = 0";
    $stmt = mysqli_prepare($conn, $activate_query);
    mysqli_stmt_bind_param($stmt, "s", $current_time);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // 2. Deactivate pre-lab sessions after 1 hour of their Start_time
    $deactivate_prelab = "UPDATE sessions 
                         SET is_active = 0 
                         WHERE lab_type = 'pre-lab' 
                         AND is_active = 1
                         AND Start_time <= DATE_SUB(?, INTERVAL 1 HOUR)";
    $stmt = mysqli_prepare($conn, $deactivate_prelab);
    mysqli_stmt_bind_param($stmt, "s", $current_time);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // 3. Deactivate during-lab sessions after 1 hour of their Start_time
    $deactivate_duringlab = "UPDATE sessions 
                            SET is_active = 0 
                            WHERE lab_type = 'during-lab' 
                            AND is_active = 1
                            AND Start_time <= DATE_SUB(?, INTERVAL 1 HOUR)";
    $stmt = mysqli_prepare($conn, $deactivate_duringlab);
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
        $lab_types = [
            'pre-lab' => 0,    // Starts immediately
            'during-lab' => 1, // Starts after 1 hour  
            'post-lab' => 2    // Starts after 2 hours
        ];

        $created_sessions = 0;
        $first_session_id = null;

        foreach ($lab_types as $lab_type => $hour_delay) {
            $scheduled_start = date('Y-m-d H:i:s', strtotime("+{$hour_delay} hours"));

            // Check if lab_type column exists
            $check_column = mysqli_query($conn, "SHOW COLUMNS FROM sessions LIKE 'lab_type'");
            $has_lab_type = mysqli_num_rows($check_column) > 0;

            if ($has_lab_type) {
                // Insert with lab_type column - session_id will auto-increment
                $query = "INSERT INTO sessions 
                         (faculty_id, subject_id, section_targeted, class_type, lab_type, created_at, Start_time, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $query);

                // Only pre-lab is active immediately (others will auto-activate later)
                $is_active = ($lab_type == 'pre-lab') ? 1 : 0;
                mysqli_stmt_bind_param($stmt, "issssssi", 
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
                // Insert without lab_type column - session_id will auto-increment
                $query = "INSERT INTO sessions 
                         (faculty_id, subject_id, section_targeted, class_type, created_at, Start_time, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $query);

                // Only pre-lab is active immediately (others will auto-activate later)
                $is_active = ($lab_type == 'pre-lab') ? 1 : 0;
                mysqli_stmt_bind_param($stmt, "isssssi", 
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
                $last_insert_id = mysqli_insert_id($conn);

                // Store first session ID for redirection
                if ($lab_type == 'pre-lab') {
                    $first_session_id = $last_insert_id;
                    $_SESSION['current_session'] = $last_insert_id;
                }
            } else {
                // DEBUG: Log error for debugging
                error_log("Error inserting session: " . mysqli_error($conn) . " | Query: " . $query);
            }
            mysqli_stmt_close($stmt);
        }

        if ($created_sessions == 3) {
            $_SESSION['success_message'] = "✅ 3 Lab Sessions Created Successfully!<br>
                                           • Pre-Lab: Active now (1 hour)<br>
                                           • During-Lab: Auto-activates in 1 hour<br>
                                           • Post-Lab: Auto-activates in 2 hours";

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
        $scheduled_start = $current_time; // Normal class starts immediately
        
        // Check if lab_type column exists
        $check_column = mysqli_query($conn, "SHOW COLUMNS FROM sessions LIKE 'lab_type'");
        $has_lab_type = mysqli_num_rows($check_column) > 0;

        if ($has_lab_type) {
            // session_id is auto-increment, so don't include it in the insert
            $query = "INSERT INTO sessions 
                     (faculty_id, subject_id, section_targeted, class_type, lab_type, created_at, Start_time, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

            $stmt = mysqli_prepare($conn, $query);
            
            $lab_type_value = 'lecture'; // or NULL or empty string
            
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
            error_log("Error creating normal session: " . $error);
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
