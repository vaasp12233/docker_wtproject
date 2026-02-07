<?php
// student_dashboard.php - FIXED for NULL section_targeted

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

// ==================== Initialize variables ====================
$student = null;
$attendance_result = null;
$total_possible_sessions = 0;
$theory_sessions = 0;
$lab_sessions = 0;
$subjects_data = [];

// ==================== Get student details ====================
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
}

// ==================== FIXED: Get correct session counts ====================
$total_sessions_happened = 0;  // Total sessions that have occurred
$attended_count = 0;           // Sessions attended by student
$theory_attended = 0;          // Theory sessions attended
$lab_attended = 0;             // Lab sessions attended

if ($student && $conn) {
    $student_section = $student['section'] ?? '';
    
    // ==================== FIX 1: Get TOTAL sessions that have happened ====================
    // FIXED: Handle NULL section_targeted - Count ALL sessions that have happened
    $total_sessions_query = "SELECT COUNT(*) as total_sessions 
                            FROM sessions 
                            WHERE start_time <= NOW() 
                            AND (section_targeted = ? OR section_targeted IS NULL)";
    $stmt_total = mysqli_prepare($conn, $total_sessions_query);
    if ($stmt_total) {
        mysqli_stmt_bind_param($stmt_total, "s", $student_section);
        mysqli_stmt_execute($stmt_total);
        $result_total = mysqli_stmt_get_result($stmt_total);
        $total_data = mysqli_fetch_assoc($result_total);
        $total_sessions_happened = $total_data['total_sessions'] ?? 0;
        mysqli_stmt_close($stmt_total);
    }
    
    // ==================== FIX 2: Get sessions attended by this student ====================
    $attended_query = "SELECT COUNT(DISTINCT ar.session_id) as attended_count 
                      FROM attendance_records ar
                      JOIN sessions s ON ar.session_id = s.session_id
                      WHERE ar.student_id = ?
                      AND s.start_time <= NOW()";
    $stmt_attended = mysqli_prepare($conn, $attended_query);
    if ($stmt_attended) {
        mysqli_stmt_bind_param($stmt_attended, "s", $student_id);
        mysqli_stmt_execute($stmt_attended);
        $result_attended = mysqli_stmt_get_result($stmt_attended);
        $attended_data = mysqli_fetch_assoc($result_attended);
        $attended_count = $attended_data['attended_count'] ?? 0;
        mysqli_stmt_close($stmt_attended);
    }
    
    // ==================== Get detailed attendance records for display ====================
    $attendance_query = "SELECT ar.*, 
                        COALESCE(s.subject_code, 'Unknown Subject') as subject_code, 
                        COALESCE(s.subject_name, 'Unknown Subject') as subject_name, 
                        ses.session_id, 
                        ses.start_time, 
                        COALESCE(ses.class_type, 'normal') as class_type,
                        ses.section_targeted
                        FROM attendance_records ar 
                        LEFT JOIN sessions ses ON ar.session_id = ses.session_id
                        LEFT JOIN subjects s ON ses.subject_id = s.subject_id
                        WHERE ar.student_id = ? 
                        AND ar.session_id IS NOT NULL
                        ORDER BY ar.marked_at DESC LIMIT 10";
    $stmt2 = mysqli_prepare($conn, $attendance_query);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, "s", $student_id);
        mysqli_stmt_execute($stmt2);
        $attendance_result = mysqli_stmt_get_result($stmt2);
        mysqli_stmt_close($stmt2);
    }
    
    // ==================== Get breakdown of theory vs lab attendance ====================
    if ($attended_count > 0) {
        $breakdown_query = "SELECT 
                            SUM(CASE WHEN ses.class_type = 'normal' THEN 1 ELSE 0 END) as theory_count,
                            SUM(CASE WHEN ses.class_type = 'lab' THEN 1 ELSE 0 END) as lab_count
                           FROM attendance_records ar
                           JOIN sessions ses ON ar.session_id = ses.session_id
                           WHERE ar.student_id = ?";
        $stmt_breakdown = mysqli_prepare($conn, $breakdown_query);
        if ($stmt_breakdown) {
            mysqli_stmt_bind_param($stmt_breakdown, "s", $student_id);
            mysqli_stmt_execute($stmt_breakdown);
            $result_breakdown = mysqli_stmt_get_result($stmt_breakdown);
            $breakdown_data = mysqli_fetch_assoc($result_breakdown);
            $theory_attended = $breakdown_data['theory_count'] ?? 0;
            $lab_attended = $breakdown_data['lab_count'] ?? 0;
            mysqli_stmt_close($stmt_breakdown);
        }
    }
    
    // ==================== Get total possible sessions from subjects ====================
    $subjects_query = "SELECT subject_name, subject_code, target_sessions FROM subjects";
    $result = mysqli_query($conn, $subjects_query);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($subject = mysqli_fetch_assoc($result)) {
            $subject_name = strtolower($subject['subject_name']);
            $subject_code = strtolower($subject['subject_code']);
            $target_sessions = intval($subject['target_sessions']);

            // Check if it's a lab
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

            $subjects_data[] = [
                'name' => $subject['subject_name'],
                'code' => $subject['subject_code'],
                'target' => $target_sessions,
                'is_lab' => $is_lab
            ];

            $total_possible_sessions += $target_sessions;
        }
        mysqli_free_result($result);
    } else {
        // Fallback values
        $theory_sessions = 75;
        $lab_sessions = 45;
        $total_possible_sessions = $theory_sessions + $lab_sessions;
    }
}

// ==================== CHECK IF GENDER IS SET ====================
if (empty($student['gender'])) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: set_gender.php');
    exit;
}

// ==================== Calculate Attendance Statistics ====================
$attendance_percentage = 0;
$display_percentage = 0;
$attendance_status = "No Sessions Yet";
$attendance_class = "secondary";

// ==================== FIXED: Handle the edge case properly ====================
if ($total_sessions_happened > 0) {
    // Normal case: Sessions exist in system
    $attendance_percentage = round(($attended_count / $total_sessions_happened) * 100, 1);
    $display_percentage = min(100, $attendance_percentage);
    
    // Determine status
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
} elseif ($attended_count > 0) {
    // Edge case: Student attended but sessions table has NULL section_targeted
    // Show 100% attendance since they attended at least 1 session
    $attendance_percentage = 100;
    $display_percentage = 100;
    $attendance_status = "Attended";
    $attendance_class = "success";
    
    // Force total_sessions_happened to be at least attended_count
    $total_sessions_happened = $attended_count;
}

// ==================== Calculate 75% Attendance Predictor ====================
$sessions_for_75_percent = 0;
$remaining_for_75_percent = 0;

if ($total_possible_sessions > 0) {
    $sessions_for_75_percent = ceil($total_possible_sessions * 0.75);
    $remaining_for_75_percent = max(0, $sessions_for_75_percent - $attended_count);
}

// ==================== Format Student ID Display ====================
$id_number = $student['id_number'] ?? $student_id;
$year_field = $student['year'] ?? '';
$year_display = "";

if (!empty($year_field)) {
    if ($year_field == '2') {
        $year_display = "E2";
    } elseif (strlen($year_field) == 4) {
        $last_two = substr($year_field, -2);
        $year_display = "E" . $last_two;
    } else {
        $year_display = "E" . $year_field;
    }
}

// Get QR code path
$qr_path = "qrcodes/student_" . $student_id . ".png";

$page_title = "Student Dashboard";
include 'header.php';

$gender = strtolower($student['gender'] ?? 'male');
$avatar_class = ($gender === 'female') ? 'female-avatar' : 'male-avatar';
$default_avatar = 'default.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Smart Attendance System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 1rem 1.5rem;
        }
        
        .profile-img {
            width: 160px;
            height: 160px;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .profile-img:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
        }
        
        .qr-container {
            background: white;
            padding: 20px;
            border-radius: 15px;
            border: 3px solid #28a745;
            display: inline-block;
            margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.2);
        }
        
        .stat-card {
            border-radius: 15px;
            padding: 25px;
            color: white;
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
        }
        
        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .attendance-ratio {
            font-size: 3rem;
            font-weight: 800;
            text-align: center;
            margin: 20px 0;
            color: #28a745;
            background: linear-gradient(45deg, #28a745, #20c997);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .attendance-progress {
            height: 25px;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .progress-bar {
            border-radius: 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .subjects-breakdown {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #dee2e6;
        }
        
        .subject-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 2px dashed #dee2e6;
            transition: all 0.2s ease;
        }
        
        .subject-item:hover {
            background: rgba(255,255,255,0.5);
            padding-left: 10px;
            padding-right: 10px;
            border-radius: 8px;
        }
        
        .subject-item:last-child {
            border-bottom: none;
        }
        
        .badge-lab {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .badge-theory {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .badge-attendance {
            font-size: 1rem;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .predictor-card {
            background: linear-gradient(135deg, #ff7e5f 0%, #feb47b 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(255, 126, 95, 0.3);
        }
        
        .predictor-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
        }
        
        .predictor-number {
            font-size: 4.5rem;
            font-weight: 900;
            text-align: center;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.2);
        }
        
        .timetable-section {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 15px 35px rgba(106, 17, 203, 0.3);
        }
        
        .waving-avatar {
            width: 140px;
            height: 140px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            margin: 0 auto 20px auto;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .gender-badge {
            font-size: 0.9rem;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .bg-pink {
            background: linear-gradient(135deg, #e83e8c 0%, #ff6b9d 100%);
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .stat-number {
                font-size: 2.8rem;
            }
            
            .attendance-ratio {
                font-size: 2.2rem;
            }
            
            .predictor-number {
                font-size: 3.5rem;
            }
            
            .card {
                margin-bottom: 15px;
            }
            
            .profile-img {
                width: 130px;
                height: 130px;
            }
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.4);
        }
        
        .table-hover tbody tr {
            transition: all 0.2s ease;
        }
        
        .table-hover tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            transform: scale(1.01);
        }
        
        .highlight-box {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.15) 0%, rgba(253, 126, 20, 0.15) 100%);
            border-radius: 15px;
            padding: 20px;
            border-left: 5px solid #ffc107;
            margin-bottom: 20px;
        }
        
        .section-badge {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .data-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Header included from header.php -->
    
    <div class="container-fluid py-3">
        <div class="row justify-content-center">
            <div class="col-xxl-10 col-xl-11 col-lg-12">
                <div class="main-container p-4">
                    <!-- Welcome Row -->
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <div class="card border-0 shadow-lg">
                                <div class="card-body text-center py-4">
                                    <div class="waving-avatar <?php echo $avatar_class; ?>"></div>
                                    <h1 class="display-5 fw-bold text-primary mb-3">
                                        Hi, <?php 
                                            $name_parts = explode(' ', $student['student_name'] ?? 'Student');
                                            echo htmlspecialchars($name_parts[0]); 
                                        ?>!
                                    </h1>
                                    <div class="d-flex justify-content-center align-items-center gap-3 mb-3">
                                        <span class="badge gender-badge bg-<?php echo ($gender === 'female') ? 'pink' : 'primary'; ?>">
                                            <i class="fas fa-<?php echo ($gender === 'female') ? 'venus' : 'mars'; ?> me-2"></i>
                                            <?php echo ucfirst($gender); ?>
                                        </span>
                                        <span class="section-badge">
                                            <i class="fas fa-users me-2"></i>
                                            Section <?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                    <p class="lead text-muted mb-0">Welcome to your smart attendance dashboard</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="row g-4">
                        <!-- Left Column: Profile & QR -->
                        <div class="col-lg-4">
                            <!-- Profile Card -->
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>My Profile</h5>
                                </div>
                                <div class="card-body text-center">
                                    <img src="uploads/profiles/<?php echo $default_avatar; ?>" 
                                         class="rounded-circle profile-img mb-4" 
                                         alt="Profile Picture"
                                         onerror="this.onerror=null; this.src='uploads/profiles/default.png';">
                                    
                                    <h3 class="mb-4"><?php echo htmlspecialchars($student['student_name'] ?? 'Student'); ?></h3>
                                    
                                    <div class="text-start">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                                <i class="fas fa-id-card text-primary fs-4"></i>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block">Student ID</small>
                                                <strong class="fs-5"><?php echo htmlspecialchars($student_id); ?></strong>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                                <i class="fas fa-hashtag text-primary fs-4"></i>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block">ID Number</small>
                                                <strong class="fs-5"><?php echo htmlspecialchars($id_number); ?></strong>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($year_display)): ?>
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                                <i class="fas fa-calendar-alt text-primary fs-4"></i>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block">Year</small>
                                                <strong class="fs-5"><?php echo htmlspecialchars($year_display); ?></strong>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                                <i class="fas fa-envelope text-primary fs-4"></i>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block">Email</small>
                                                <strong class="fs-6"><?php echo htmlspecialchars($student['student_email'] ?? 'N/A'); ?></strong>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                                <i class="fas fa-building text-primary fs-4"></i>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block">Department</small>
                                                <strong class="fs-5"><?php echo htmlspecialchars($student['student_department'] ?? 'N/A'); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- QR Code Card -->
                            <div class="card mt-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>My QR Code</h5>
                                </div>
                                <div class="card-body text-center">
                                    <?php if (!empty($student['qr_content'])): ?>
                                        <div class="qr-container">
                                            <div id="qrcode"></div>
                                        </div>
                                        <p class="mt-3 mb-4 text-muted">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Show this QR code during class to mark attendance
                                        </p>
                                        <button onclick="downloadQR()" class="btn btn-success w-100 py-3">
                                            <i class="fas fa-download me-2"></i> Download QR Code
                                        </button>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            QR Code not generated yet. Contact administrator.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Dashboard -->
                        <div class="col-lg-8">
                            <!-- Stats Row -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                        <div class="stat-number">
                                            <?php echo $attended_count; ?>
                                        </div>
                                        <h6 class="mb-0">Classes Attended</h6>
                                        <small class="opacity-75"><?php echo $theory_attended; ?> theory + <?php echo $lab_attended; ?> labs</small>
                                        <div class="mt-2">
                                            <i class="fas fa-calendar-check fa-2x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                                        <div class="stat-number">
                                            <?php echo $display_percentage; ?>%
                                        </div>
                                        <h6 class="mb-0">Attendance Rate</h6>
                                        <small class="opacity-75">Based on <?php echo $total_sessions_happened; ?> sessions</small>
                                        <div class="mt-3">
                                            <span class="badge badge-attendance bg-<?php echo $attendance_class; ?>">
                                                <i class="fas fa-user-check me-1"></i> <?php echo $attendance_status; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                                        <div class="stat-number">
                                            <span id="current-time"><?php echo date('h:i A'); ?></span>
                                        </div>
                                        <h6 class="mb-0">Current Time</h6>
                                        <small class="opacity-75"><?php echo date('l, F j, Y'); ?></small>
                                        <div class="mt-2">
                                            <i class="fas fa-clock fa-2x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- FIXED: Attendance Progress Section -->
                            <div class="card mb-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Attendance Details</h5>
                                </div>
                                <div class="card-body">
                                    <!-- CORRECT RATIO DISPLAY -->
                                    <div class="attendance-ratio">
                                        <?php echo $attended_count; ?>/<?php echo $total_sessions_happened; ?> sessions
                                    </div>
                                    
                                    <!-- Data Quality Warning -->
                                    <?php if ($total_sessions_happened == 0 && $attended_count > 0): ?>
                                    <div class="data-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Note: Attendance data may be incomplete due to session configuration
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Progress Bar -->
                                    <div class="progress attendance-progress">
                                        <div class="progress-bar bg-<?php echo $attendance_class; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo min($display_percentage, 100); ?>%"
                                             aria-valuenow="<?php echo $display_percentage; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?php echo $display_percentage; ?>%
                                        </div>
                                    </div>
                                    
                                    <!-- Breakdown -->
                                    <div class="row mt-4 g-4">
                                        <div class="col-md-6">
                                            <div class="highlight-box">
                                                <h6 class="fw-bold mb-3"><i class="fas fa-book me-2"></i>Attendance Breakdown</h6>
                                                <div class="subjects-breakdown">
                                                    <div class="subject-item">
                                                        <span>Total Sessions Happened</span>
                                                        <span><strong class="fs-5"><?php echo $total_sessions_happened; ?></strong></span>
                                                    </div>
                                                    <div class="subject-item">
                                                        <span>Theory Attended</span>
                                                        <span>
                                                            <strong class="fs-5"><?php echo $theory_attended; ?></strong>
                                                            <span class="badge badge-theory ms-2">Theory</span>
                                                        </span>
                                                    </div>
                                                    <div class="subject-item">
                                                        <span>Lab Attended</span>
                                                        <span>
                                                            <strong class="fs-5"><?php echo $lab_attended; ?></strong>
                                                            <span class="badge badge-lab ms-2">Lab</span>
                                                        </span>
                                                    </div>
                                                    <div class="subject-item">
                                                        <span>Attendance Percentage</span>
                                                        <span><strong class="fs-5 text-<?php echo $attendance_class; ?>"><?php echo $display_percentage; ?>%</strong></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="highlight-box">
                                                <h6 class="fw-bold mb-3"><i class="fas fa-calculator me-2"></i>Attendance Calculation</h6>
                                                <div class="subjects-breakdown">
                                                    <div class="subject-item">
                                                        <span>Total Possible Sessions</span>
                                                        <span><strong class="fs-5"><?php echo $total_possible_sessions; ?></strong></span>
                                                    </div>
                                                    <div class="subject-item">
                                                        <span>75% Requirement</span>
                                                        <span><strong class="fs-5"><?php echo $sessions_for_75_percent; ?></strong></span>
                                                    </div>
                                                    <div class="subject-item">
                                                        <span>Remaining to 75%</span>
                                                        <span><strong class="fs-5"><?php echo $remaining_for_75_percent; ?></strong></span>
                                                    </div>
                                                    <div class="subject-item">
                                                        <span>Progress to 75%</span>
                                                        <span>
                                                            <strong class="fs-5 text-warning">
                                                                <?php echo min(100, round(($attended_count / $sessions_for_75_percent) * 100, 1)); ?>%
                                                            </strong>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Individual Subjects -->
                                    <?php if (!empty($subjects_data)): ?>
                                    <div class="mt-4">
                                        <h6 class="fw-bold mb-3"><i class="fas fa-list-alt me-2"></i>Individual Subjects</h6>
                                        <div class="row g-2">
                                            <?php foreach ($subjects_data as $subject): ?>
                                            <div class="col-md-6">
                                                <div class="d-flex justify-content-between align-items-center p-3 border rounded-3 shadow-sm hover-shadow">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-<?php echo $subject['is_lab'] ? 'info' : 'primary'; ?> bg-opacity-10 p-2 rounded-circle me-3">
                                                            <i class="fas fa-<?php echo $subject['is_lab'] ? 'flask' : 'book'; ?> text-<?php echo $subject['is_lab'] ? 'info' : 'primary'; ?>"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($subject['code']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($subject['name']); ?></small>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="fw-bold fs-5"><?php echo $subject['target']; ?></div>
                                                        <small class="text-muted">sessions</small>
                                                        <?php if ($subject['is_lab']): ?>
                                                            <span class="badge badge-lab ms-1">Lab</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-theory ms-1">Theory</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 75% Goal Predictor -->
                            <div class="predictor-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h3 class="fw-bold mb-2">
                                            <i class="fas fa-bullseye me-2"></i>75% Attendance Goal
                                        </h3>
                                        <p class="mb-0 opacity-90">Track your progress towards 75% attendance requirement</p>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="predictor-number">
                                            <?php echo $remaining_for_75_percent; ?>
                                        </div>
                                        <div class="fs-4 fw-bold">Sessions Needed</div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-white bg-opacity-20 p-2 rounded-circle me-3">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div>
                                                <small class="opacity-75">Current Attendance</small>
                                                <div class="fs-5 fw-bold"><?php echo $attended_count; ?> sessions</div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white bg-opacity-20 p-2 rounded-circle me-3">
                                                <i class="fas fa-flag"></i>
                                            </div>
                                            <div>
                                                <small class="opacity-75">Target for 75%</small>
                                                <div class="fs-5 fw-bold"><?php echo $sessions_for_75_percent; ?> sessions</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-white bg-opacity-20 p-2 rounded-circle me-3">
                                                <i class="fas fa-calculator"></i>
                                            </div>
                                            <div>
                                                <small class="opacity-75">Calculation</small>
                                                <div class="fs-5 fw-bold">75% of <?php echo $total_possible_sessions; ?> sessions</div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white bg-opacity-20 p-2 rounded-circle me-3">
                                                <i class="fas fa-tachometer-alt"></i>
                                            </div>
                                            <div>
                                                <small class="opacity-75">Weekly Goal</small>
                                                <div class="fs-5 fw-bold"><?php echo ceil($remaining_for_75_percent / 8); ?> sessions/week</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <div class="d-flex justify-content-between mb-2">
                                        <small class="fw-bold">Progress to 75% Goal</small>
                                        <small class="fw-bold"><?php echo min(100, round(($attended_count / $sessions_for_75_percent) * 100, 1)); ?>%</small>
                                    </div>
                                    <div class="progress" style="height: 15px; border-radius: 10px;">
                                        <div class="progress-bar bg-warning" 
                                             style="width: <?php echo min(100, ($attended_count / $sessions_for_75_percent) * 100); ?>%">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Timetable Section -->
                            <div class="timetable-section">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h3 class="fw-bold mb-2">
                                            <i class="fas fa-calendar-alt me-2"></i>Class Timetable
                                        </h3>
                                        <p class="mb-0 opacity-90">View your weekly class schedule and upcoming sessions</p>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <a href="timetable.php" class="btn btn-light btn-lg px-4 py-3">
                                            <i class="fas fa-calendar me-2"></i> View Timetable
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Attendance -->
                            <div class="card">
                                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Attendance</h5>
                                    <span class="badge bg-light text-dark fs-6">
                                        <i class="fas fa-user-check me-1"></i>
                                        <?php echo $attended_count; ?> Records
                                    </span>
                                </div>
                                <div class="card-body">
                                    <?php if ($attendance_result && mysqli_num_rows($attendance_result) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Subject</th>
                                                        <th>Type</th>
                                                        <th>Time</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    mysqli_data_seek($attendance_result, 0);
                                                    while ($record = mysqli_fetch_assoc($attendance_result)): 
                                                        $class_type = $record['class_type'] ?? 'normal';
                                                        $is_lab = ($class_type === 'lab');
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                                                    <i class="fas fa-calendar-day text-primary"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="fw-bold"><?php echo date('d/m/Y', strtotime($record['marked_at'])); ?></div>
                                                                    <small class="text-muted"><?php echo date('D', strtotime($record['marked_at'])); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($record['subject_code']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($record['subject_name']); ?></small>
                                                            <?php if (!empty($record['section_targeted'])): ?>
                                                            <div class="mt-1">
                                                                <small class="badge bg-secondary">Sec: <?php echo $record['section_targeted']; ?></small>
                                                            </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($is_lab): ?>
                                                                <span class="badge bg-info px-3 py-2">
                                                                    <i class="fas fa-flask me-1"></i> Lab
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-primary px-3 py-2">
                                                                    <i class="fas fa-book me-1"></i> Theory
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="bg-success bg-opacity-10 p-2 rounded-circle me-3">
                                                                    <i class="fas fa-clock text-success"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="fw-bold"><?php echo date('h:i A', strtotime($record['marked_at'])); ?></div>
                                                                    <small class="text-muted">Marked</small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success px-3 py-2">
                                                                <i class="fas fa-check-circle me-1"></i> Present
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="text-center mt-4">
                                            <a href="attendance_viewer.php" class="btn btn-primary px-5 py-3">
                                                <i class="fas fa-chart-line me-2"></i> View Complete Attendance Report
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <div class="mb-4">
                                                <i class="fas fa-clipboard-list fa-4x text-muted"></i>
                                            </div>
                                            <h4 class="text-muted mb-3">No attendance records yet</h4>
                                            <p class="text-muted mb-4">Your attendance will appear here after scanning QR codes in class</p>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Show your QR code to faculty during class sessions to mark attendance
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer included from footer.php -->
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <script>
    // Generate QR code
    <?php if (!empty($student['qr_content'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const qrElement = document.getElementById('qrcode');
        if (qrElement) {
            qrElement.innerHTML = '';
            var qrcode = new QRCode(document.getElementById("qrcode"), {
                text: "<?php echo addslashes($student['qr_content']); ?>",
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    });

    function downloadQR() {
        var canvas = document.querySelector("#qrcode canvas");
        if (!canvas) {
            showToast('error', 'QR code not found!');
            return;
        }
        
        var tempCanvas = document.createElement('canvas');
        var ctx = tempCanvas.getContext('2d');
        tempCanvas.width = canvas.width;
        tempCanvas.height = canvas.height;
        
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
        ctx.drawImage(canvas, 0, 0);
        
        var link = document.createElement('a');
        link.download = 'Attendance_QR_<?php echo $student_id; ?>.png';
        link.href = tempCanvas.toDataURL("image/png");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showToast('success', 'QR code downloaded successfully!');
    }
    <?php endif; ?>

    // Update current time
    function updateCurrentTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-IN', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
        const timeElement = document.getElementById('current-time');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
    }

    // Update time every minute
    setInterval(updateCurrentTime, 60000);
    updateCurrentTime();

    // Toast notification function
    function showToast(type, message) {
        const toastHTML = `
            <div class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-3 fs-5"></i>
                        <div>${message}</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '1055';
        toastContainer.innerHTML = toastHTML;
        
        document.body.appendChild(toastContainer);
        
        const toastEl = toastContainer.querySelector('.toast');
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
        
        toastEl.addEventListener('hidden.bs.toast', function () {
            toastContainer.remove();
        });
    }

    // Auto-refresh every 60 seconds
    setTimeout(function() {
        showToast('info', 'Refreshing attendance data...');
        setTimeout(function() {
            location.reload();
        }, 1500);
    }, 60000);

    // Add hover effects to table rows
    document.addEventListener('DOMContentLoaded', function() {
        const tableRows = document.querySelectorAll('.table-hover tbody tr');
        tableRows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
                this.style.zIndex = '1';
            });
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.zIndex = '0';
            });
        });
    });
    </script>
</body>
</html>

<?php 
include 'footer.php';

if (ob_get_level() > 0) {
    ob_end_flush();
}
