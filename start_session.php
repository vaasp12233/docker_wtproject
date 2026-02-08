<?php
session_start();
require_once 'config.php';

/* ================== AUTH CHECK ================== */
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

/* ================== ONLY POST ================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: faculty_dashboard.php');
    exit;
}

/* ================== INPUT ================== */
$subject_id        = $_POST['subject_id'];
$section_targeted  = $_POST['section_targeted'];
$class_type        = $_POST['class_type']; // lab | lecture
$current_time      = date('Y-m-d H:i:s');

/* ================== STOP OLD ACTIVE SESSIONS ================== */
$stop_query = "
    UPDATE sessions 
    SET is_active = 0 
    WHERE faculty_id = ? AND is_active = 1
";
$stop_stmt = mysqli_prepare($conn, $stop_query);
mysqli_stmt_bind_param($stop_stmt, "i", $faculty_id);
mysqli_stmt_execute($stop_stmt);
mysqli_stmt_close($stop_stmt);

/* ================== CHECK lab_type COLUMN ================== */
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM sessions LIKE 'lab_type'");
$has_lab_type = mysqli_num_rows($check_column) > 0;

/* ================== LAB SESSION ================== */
if ($class_type === 'lab') {

    $lab_phases = ['pre-lab', 'during-lab', 'post-lab'];
    $first_session_id = null;

    foreach ($lab_phases as $phase) {

        // ONLY pre-lab is active initially
        $is_active = ($phase === 'pre-lab') ? 1 : 0;

        if ($has_lab_type) {
            $query = "
                INSERT INTO sessions
                (faculty_id, subject_id, section_targeted, class_type, lab_type, created_at, Start_time, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = mysqli_prepare($conn, $query);

            mysqli_stmt_bind_param(
                $stmt,
                "issssssi",
                $faculty_id,
                $subject_id,
                $section_targeted,
                $class_type,
                $phase,
                $current_time,
                $current_time,
                $is_active
            );
        } else {
            $query = "
                INSERT INTO sessions
                (faculty_id, subject_id, section_targeted, class_type, created_at, Start_time, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = mysqli_prepare($conn, $query);

            mysqli_stmt_bind_param(
                $stmt,
                "isssssi",
                $faculty_id,
                $subject_id,
                $section_targeted,
                $class_type,
                $current_time,
                $current_time,
                $is_active
            );
        }

        if (!mysqli_stmt_execute($stmt)) {
            $_SESSION['error_message'] = "Failed to create lab session.";
            header('Location: faculty_dashboard.php');
            exit;
        }

        $last_id = mysqli_insert_id($conn);
        if ($phase === 'pre-lab') {
            $first_session_id = $last_id;
            $_SESSION['current_session'] = $last_id;
        }

        mysqli_stmt_close($stmt);
    }

    $_SESSION['success_message'] = "✅ Lab session started (Pre-Lab active)";
    header('Location: faculty_scan.php?session_id=' . urlencode($first_session_id));
    exit;
}

/* ================== LECTURE SESSION ================== */
else {

    if ($has_lab_type) {
        $query = "
            INSERT INTO sessions
            (faculty_id, subject_id, section_targeted, class_type, lab_type, created_at, Start_time, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ";
        $stmt = mysqli_prepare($conn, $query);

        $lab_type_value = 'lecture';

        mysqli_stmt_bind_param(
            $stmt,
            "issssss",
            $faculty_id,
            $subject_id,
            $section_targeted,
            $class_type,
            $lab_type_value,
            $current_time,
            $current_time
        );
    } else {
        $query = "
            INSERT INTO sessions
            (faculty_id, subject_id, section_targeted, class_type, created_at, Start_time, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ";
        $stmt = mysqli_prepare($conn, $query);

        mysqli_stmt_bind_param(
            $stmt,
            "isssss",
            $faculty_id,
            $subject_id,
            $section_targeted,
            $class_type,
            $current_time,
            $current_time
        );
    }

    if (!mysqli_stmt_execute($stmt)) {
        $_SESSION['error_message'] = "Failed to start lecture session.";
        header('Location: faculty_dashboard.php');
        exit;
    }

    $session_id = mysqli_insert_id($conn);
    $_SESSION['current_session'] = $session_id;

    mysqli_stmt_close($stmt);

    $_SESSION['success_message'] = "✅ Lecture session started";
    header('Location: faculty_scan.php?session_id=' . urlencode($session_id));
    exit;
}