<?php
// student_dashboard.php - Render + Aiven + GitHub Compatible

// ==================== Start output buffering ====================
if (!ob_get_level()) {
    ob_start();
}

// ==================== Configure session for Render ====================
ini_set('session.save_path', '/tmp');
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);

// ==================== Start session ====================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== Include database config ====================
require_once 'config.php';

// ==================== Security check ====================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Role check ====================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Clean buffer before output ====================
if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

// ==================== Database operations with prepared statements ====================
$student = null;
$attendance_result = null;
$total_possible_sessions = 0;
$theory_sessions = 0;
$lab_sessions = 0;
$subjects_data = [];
$total_attendance = 0; // Initialize total attendance variable

// Get student details - USING PREPARED STATEMENTS
if ($conn) {
    $student_query = "SELECT * FROM students WHERE student_id = ?";
    $stmt = mysqli_prepare($conn, $student_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $student = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }

    // Get student's attendance - USING PREPARED STATEMENTS
    if ($student) {
        // Get student's section
        $student_section = $student['section'] ?? '';
        
        // FIRST: Get total attendance count (ALL records, not limited)
        $total_attendance_query = "SELECT COUNT(*) as total_count 
                                  FROM attendance_records ar 
                                  JOIN sessions ses ON ar.session_id = ses.session_id
                                  JOIN subjects s ON ses.subject_id = s.subject_id
                                  WHERE ar.student_id = ?";
        $stmt_total = mysqli_prepare($conn, $total_attendance_query);
        if ($stmt_total) {
            mysqli_stmt_bind_param($stmt_total, "s", $student_id);
            mysqli_stmt_execute($stmt_total);
            $total_result = mysqli_stmt_get_result($stmt_total);
            if ($total_row = mysqli_fetch_assoc($total_result)) {
                $total_attendance = intval($total_row['total_count']);
            }
            mysqli_stmt_close($stmt_total);
        }
        
        // SECOND: Get recent attendance for display (limited to 10)
        $recent_attendance_query = "SELECT ar.*, s.subject_code, s.subject_name, ses.session_id, ses.start_time, ses.class_type, ses.lab_type
                            FROM attendance_records ar 
                            JOIN sessions ses ON ar.session_id = ses.session_id
                            JOIN subjects s ON ses.subject_id = s.subject_id
                            WHERE ar.student_id = ? 
                            ORDER BY ar.marked_at DESC LIMIT 10";
        $stmt2 = mysqli_prepare($conn, $recent_attendance_query);
        if ($stmt2) {
            mysqli_stmt_bind_param($stmt2, "s", $student_id);
            mysqli_stmt_execute($stmt2);
            $attendance_result = mysqli_stmt_get_result($stmt2);
            mysqli_stmt_close($stmt2);
        }
        
        // ==================== GET SUBJECTS AND SESSIONS FILTERED BY STUDENT'S SECTION ====================
        // Get subjects that have sessions targeted to the student's section
        if (!empty($student_section)) {
            // Get subjects that have sessions for this section
            $subjects_query = "SELECT DISTINCT s.subject_id, s.subject_name, s.subject_code, s.target_sessions 
                              FROM subjects s 
                              JOIN sessions ses ON s.subject_id = ses.subject_id 
                              WHERE ses.section_targeted = ?";
            $stmt_subjects = mysqli_prepare($conn, $subjects_query);
            if ($stmt_subjects) {
                mysqli_stmt_bind_param($stmt_subjects, "s", $student_section);
                mysqli_stmt_execute($stmt_subjects);
                $result = mysqli_stmt_get_result($stmt_subjects);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    while ($subject = mysqli_fetch_assoc($result)) {
                        $subject_name = strtolower($subject['subject_name']);
                        $subject_code = strtolower($subject['subject_code']);
                        $target_sessions = intval($subject['target_sessions']);
                        
                        // Check if it's a lab (based on common patterns)
                        $is_lab = false;
                        if (strpos($subject_name, 'lab') !== false || 
                            strpos($subject_name, 'practical') !== false ||
                            strpos($subject_code, '_lab') !== false ||
                            strpos($subject_code, 'lab_') !== false) {
                            $is_lab = true;
                            $lab_sessions += $target_sessions;
                        } else {
                            $theory_sessions += $target_sessions;
                        }
                        
                        // Store subject data for display
                        $subjects_data[] = [
                            'name' => $subject['subject_name'],
                            'code' => $subject['subject_code'],
                            'target' => $target_sessions,
                            'is_lab' => $is_lab
                        ];
                        
                        $total_possible_sessions += $target_sessions;
                    }
                    
                    // Free result
                    mysqli_free_result($result);
                }
                mysqli_stmt_close($stmt_subjects);
            }
            
            // Also get count of sessions specifically targeted to this section
            $section_sessions_query = "SELECT COUNT(*) as section_sessions FROM sessions WHERE section_targeted = ?";
            $stmt_section_sessions = mysqli_prepare($conn, $section_sessions_query);
            if ($stmt_section_sessions) {
                mysqli_stmt_bind_param($stmt_section_sessions, "s", $student_section);
                mysqli_stmt_execute($stmt_section_sessions);
                $section_result = mysqli_stmt_get_result($stmt_section_sessions);
                if ($section_row = mysqli_fetch_assoc($section_result)) {
                    $section_sessions_count = intval($section_row['section_sessions']);
                    // If we have section-specific sessions, use that as total possible
                    if ($section_sessions_count > 0 && $total_possible_sessions > $section_sessions_count) {
                        $total_possible_sessions = $section_sessions_count;
                    }
                }
                mysqli_stmt_close($stmt_section_sessions);
            }
        } else {
            // If section is empty, use all subjects (fallback)
            $subjects_query = "SELECT subject_name, subject_code, target_sessions FROM subjects";
            $result = mysqli_query($conn, $subjects_query);
            
            if ($result && mysqli_num_rows($result) > 0) {
                while ($subject = mysqli_fetch_assoc($result)) {
                    $subject_name = strtolower($subject['subject_name']);
                    $subject_code = strtolower($subject['subject_code']);
                    $target_sessions = intval($subject['target_sessions']);
                    
                    // Check if it's a lab (based on common patterns)
                    $is_lab = false;
                    if (strpos($subject_name, 'lab') !== false || 
                        strpos($subject_name, 'practical') !== false ||
                        strpos($subject_code, '_lab') !== false ||
                        strpos($subject_code, 'lab_') !== false) {
                        $is_lab = true;
                        $lab_sessions += $target_sessions;
                    } else {
                        $theory_sessions += $target_sessions;
                    }
                    
                    // Store subject data for display
                    $subjects_data[] = [
                        'name' => $subject['subject_name'],
                        'code' => $subject['subject_code'],
                        'target' => $target_sessions,
                        'is_lab' => $is_lab
                    ];
                    
                    $total_possible_sessions += $target_sessions;
                }
                
                // Free result
                mysqli_free_result($result);
            }
        }
        
        // If no subjects found or section filtering didn't return results, use fallback
        if ($total_possible_sessions == 0) {
            // Fallback to default values if no subjects found
            // Based on your information: 5 subjects + 3 labs
            $theory_sessions = 5 * 15;  // 5 subjects × 15 weeks
            $lab_sessions = 3 * 15;     // 3 labs × 15 weeks
            $total_possible_sessions = $theory_sessions + $lab_sessions;
        }
    }
}

// ==================== CHECK IF GENDER IS SET ====================
// If gender is not set, redirect to gender question page
if (empty($student['gender'])) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: set_gender.php');
    exit;
}

// Note: $total_attendance is already calculated above using separate query

// ==================== GET SESSIONS HAPPENED - FIXED VERSION ====================
// Count how many sessions have actually happened (past sessions) FOR STUDENT'S SECTION
$sessions_happened = 0;
if ($conn && !empty($student_section)) {
    // Try multiple queries to get sessions happened for student's section
    $happened_query = "SELECT COUNT(*) as total_happened FROM sessions WHERE section_targeted = ? AND start_time <= NOW()";
    $stmt_happened = mysqli_prepare($conn, $happened_query);
    if ($stmt_happened) {
        mysqli_stmt_bind_param($stmt_happened, "s", $student_section);
        mysqli_stmt_execute($stmt_happened);
        $happened_result = mysqli_stmt_get_result($stmt_happened);
        if ($happened_result && $row = mysqli_fetch_assoc($happened_result)) {
            $sessions_happened = intval($row['total_happened']);
        }
        mysqli_stmt_close($stmt_happened);
    }
    
    // If still 0, try alternative query without time filter
    if ($sessions_happened == 0) {
        $alt_query = "SELECT COUNT(*) as total_happened FROM sessions WHERE section_targeted = ? AND DATE(start_time) <= CURDATE()";
        $stmt_alt = mysqli_prepare($conn, $alt_query);
        if ($stmt_alt) {
            mysqli_stmt_bind_param($stmt_alt, "s", $student_section);
            mysqli_stmt_execute($stmt_alt);
            $alt_result = mysqli_stmt_get_result($stmt_alt);
            if ($alt_result && $row = mysqli_fetch_assoc($alt_result)) {
                $sessions_happened = intval($row['total_happened']);
            }
            mysqli_stmt_close($stmt_alt);
        }
    }
} else {
    // If no section or connection, use general query
    $happened_query = "SELECT COUNT(*) as total_happened FROM sessions WHERE start_time <= NOW()";
    $happened_result = mysqli_query($conn, $happened_query);
    
    if ($happened_result && $row = mysqli_fetch_assoc($happened_result)) {
        $sessions_happened = intval($row['total_happened']);
    }
    
    // If still 0, try alternative query
    if ($sessions_happened == 0) {
        $alt_query = "SELECT COUNT(*) as total_happened FROM sessions WHERE DATE(start_time) <= CURDATE()";
        $alt_result = mysqli_query($conn, $alt_query);
        if ($alt_result && $row = mysqli_fetch_assoc($alt_result)) {
            $sessions_happened = intval($row['total_happened']);
        }
    }
}

// If still 0, use total_possible_sessions as fallback for percentage calculation
if ($sessions_happened == 0) {
    $sessions_happened = $total_possible_sessions;
}

// ==================== CALCULATE ATTENDANCE PERCENTAGE - FIXED ====================
// Attendance % = (sessions attended / sessions happened) × 100
$attendance_percentage = 0;
if ($sessions_happened > 0) {
    $attendance_percentage = round(($total_attendance / $sessions_happened) * 100, 1);
} else {
    $attendance_percentage = 0;
}

// Determine attendance status based on attendance percentage
$attendance_status = "No Data";
$attendance_class = "secondary";
if ($sessions_happened > 0) {
    if ($attendance_percentage >= 85) {
        $attendance_status = "Excellent";
        $attendance_class = "success";
    } elseif ($attendance_percentage >= 75) {
        $attendance_status = "Good";
        $attendance_class = "primary";
    } elseif ($attendance_percentage >= 60) {
        $attendance_status = "Average";
        $attendance_class = "warning";
    } elseif ($attendance_percentage >= 40) {
        $attendance_status = "Poor";
        $attendance_class = "danger";
    } else {
        $attendance_status = "Very Poor";
        $attendance_class = "dark";
    }
} else {
    $attendance_status = "No Sessions Yet";
    $attendance_class = "secondary";
}

// ==================== Calculate 75% Attendance Predictor ====================
// This stays the same - based on total possible sessions
$sessions_for_75_percent = 0;
$remaining_for_75_percent = 0;

if ($total_possible_sessions > 0) {
    // Sessions needed for 75% attendance
    $sessions_for_75_percent = ceil($total_possible_sessions * 0.75);
    
    // How many more sessions needed from current
    $remaining_for_75_percent = max(0, $sessions_for_75_percent - $total_attendance);
}

// ==================== Format Student ID Display ====================
// Extract ID number and year from database
$id_number = $student['id_number'] ?? $student_id;
$year_field = $student['year'] ?? '';

// Format year as E2 if year has 2 as input
$year_display = "";
if (!empty($year_field)) {
    // If year is like "2" display as "E2"
    if ($year_field == '2') {
        $year_display = "E2";
    } 
    // If year is 4-digit like 2024, take last 2 digits
    elseif (strlen($year_field) == 4) {
        $last_two = substr($year_field, -2);
        $year_display = "E" . $last_two;
    }
    // For any other format
    else {
        $year_display = "E" . $year_field;
    }
}

// Get QR code path
$qr_path = "qrcodes/student_" . $student_id . ".png";

$page_title = "Student Dashboard";
include 'header.php';

// Determine gender and set avatar
$gender = strtolower($student['gender'] ?? 'male');
$avatar_class = ($gender === 'female') ? 'female-avatar' : 'male-avatar';
// ALWAYS USE default.png FOR ALL STUDENTS
$default_avatar = 'default.png';
?>
