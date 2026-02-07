<?php
// student_dashboard.php - Fixed Session Count Issue

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
    
    // ==================== FIX 1: Get TOTAL sessions that have happened for student's section ====================
    $total_sessions_query = "SELECT COUNT(*) as total_sessions 
                            FROM sessions 
                            WHERE section_targeted = ? 
                            AND start_time <= NOW()";
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
                        COALESCE(ses.class_type, 'normal') as class_type
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
        $theory_sessions = 75;  // Default theory sessions
        $lab_sessions = 45;     // Default lab sessions
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

if ($total_sessions_happened > 0) {
    // Attendance % = (sessions attended / sessions happened) × 100
    $attendance_percentage = round(($attended_count / $total_sessions_happened) * 100, 1);
    
    // Cap at 100% for display
    $display_percentage = min(100, $attendance_percentage);
    
    // Determine attendance status
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
} elseif ($attended_count > 0 && $total_sessions_happened == 0) {
    // Edge case: Student attended but no sessions recorded in system
    $attendance_status = "System Error";
    $attendance_class = "warning";
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
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
        }
        
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .qr-container {
            background-color: white;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #dee2e6;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .attendance-ratio {
            font-size: 2.2rem;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
            color: #28a745;
            background: linear-gradient(45deg, #28a745, #20c997);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .attendance-progress {
            height: 20px;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .subjects-breakdown {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .subject-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .subject-item:last-child {
            border-bottom: none;
        }
        
        .badge-lab {
            background: linear-gradient(45deg, #17a2b8, #20c997);
        }
        
        .badge-theory {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        
        .predictor-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .predictor-number {
            font-size: 3.5rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .timetable-section {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .waving-avatar {
            width: 120px;
            height: 120px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            margin: 0 auto 15px auto;
        }
        
        .gender-badge {
            font-size: 0.8rem;
            padding: 3px 10px;
            border-radius: 15px;
        }
        
        @media (max-width: 768px) {
            .stat-number {
                font-size: 2.2rem;
            }
            
            .attendance-ratio {
                font-size: 1.8rem;
            }
            
            .predictor-number {
                font-size: 2.5rem;
            }
            
            .card {
                margin-bottom: 15px;
            }
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .btn-success {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }
        
        .highlight-box {
            background: linear-gradient(45deg, rgba(255,193,7,0.1), rgba(253,126,20,0.1));
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <!-- Header included from header.php -->
    
    <div class="container py-4">
        <!-- Welcome Row -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="waving-avatar <?php echo $avatar_class; ?>"></div>
                        <h2 class="text-primary mb-2">
                            Hi, <?php 
                                $name_parts = explode(' ', $student['student_name'] ?? 'Student');
                                echo htmlspecialchars($name_parts[0]); 
                            ?>! <span class="badge gender-badge bg-<?php echo ($gender === 'female') ? 'pink' : 'primary'; ?>">
                                <i class="fas fa-<?php echo ($gender === 'female') ? 'venus' : 'mars'; ?>"></i>
                                <?php echo ucfirst($gender); ?>
                            </span>
                        </h2>
                        <p class="lead text-muted">Welcome to your smart attendance dashboard</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <!-- Left Column: Profile & QR -->
            <div class="col-md-4">
                <!-- Profile Card -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>My Profile</h5>
                    </div>
                    <div class="card-body text-center">
                        <img src="uploads/profiles/<?php echo $default_avatar; ?>" 
                             class="rounded-circle profile-img mb-3" 
                             alt="Profile Picture"
                             onerror="this.onerror=null; this.src='uploads/profiles/default.png';">
                        
                        <h4 class="mb-3"><?php echo htmlspecialchars($student['student_name'] ?? 'Student'); ?></h4>
                        
                        <div class="text-start">
                            <p class="mb-2">
                                <i class="fas fa-id-card text-primary me-2"></i>
                                <strong>Student ID:</strong> <?php echo htmlspecialchars($student_id); ?>
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-hashtag text-primary me-2"></i>
                                <strong>ID Number:</strong> <?php echo htmlspecialchars($id_number); ?>
                            </p>
                            <?php if (!empty($year_display)): ?>
                            <p class="mb-2">
                                <i class="fas fa-calendar-alt text-primary me-2"></i>
                                <strong>Year:</strong> <?php echo htmlspecialchars($year_display); ?>
                            </p>
                            <?php endif; ?>
                            <p class="mb-2">
                                <i class="fas fa-envelope text-primary me-2"></i>
                                <strong>Email:</strong> <?php echo htmlspecialchars($student['student_email'] ?? 'N/A'); ?>
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-users text-primary me-2"></i>
                                <strong>Section:</strong> <?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?>
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-building text-primary me-2"></i>
                                <strong>Department:</strong> <?php echo htmlspecialchars($student['student_department'] ?? 'N/A'); ?>
                            </p>
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
                            <p class="mt-3 small text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Show this QR code during class to mark attendance
                            </p>
                            <button onclick="downloadQR()" class="btn btn-success w-100">
                                <i class="fas fa-download me-1"></i> Download QR Code
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
            <div class="col-md-8">
                <!-- Stats Row -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                            <div class="stat-number">
                                <i class="fas fa-calendar-check"></i> <?php echo $attended_count; ?>
                            </div>
                            <h6 class="mb-0">Classes Attended</h6>
                            <small><?php echo $theory_attended; ?> theory + <?php echo $lab_attended; ?> labs</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                            <div class="stat-number">
                                <i class="fas fa-percentage"></i> <?php echo $display_percentage; ?>%
                            </div>
                            <h6 class="mb-0">Attendance Rate</h6>
                            <div class="small mb-2">Based on <?php echo $total_sessions_happened; ?> sessions</div>
                            <span class="badge bg-<?php echo $attendance_class; ?>">
                                <?php echo $attendance_status; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                            <div class="stat-number">
                                <i class="fas fa-clock"></i> <span id="current-time"><?php echo date('h:i A'); ?></span>
                            </div>
                            <h6 class="mb-0">Current Time</h6>
                            <small><?php echo date('l, F j, Y'); ?></small>
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
                        
                        <div class="text-center mb-3">
                            <span class="badge bg-<?php echo $attendance_class; ?> px-3 py-2 fs-6">
                                <i class="fas fa-user-check me-1"></i> <?php echo $attendance_status; ?>
                            </span>
                        </div>
                        
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
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="highlight-box">
                                    <h6><i class="fas fa-book me-2"></i>Subject Breakdown</h6>
                                    <div class="subjects-breakdown">
                                        <div class="subject-item">
                                            <span>Total Sessions</span>
                                            <span><strong><?php echo $total_sessions_happened; ?></strong> sessions</span>
                                        </div>
                                        <div class="subject-item">
                                            <span>Theory Attended</span>
                                            <span><strong><?php echo $theory_attended; ?></strong> sessions <span class="badge badge-theory">Theory</span></span>
                                        </div>
                                        <div class="subject-item">
                                            <span>Lab Attended</span>
                                            <span><strong><?php echo $lab_attended; ?></strong> sessions <span class="badge badge-lab">Lab</span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="highlight-box">
                                    <h6><i class="fas fa-calculator me-2"></i>Attendance Calculation</h6>
                                    <div class="subjects-breakdown">
                                        <div class="subject-item">
                                            <span>Total Possible</span>
                                            <span><strong><?php echo $total_possible_sessions; ?></strong> sessions</span>
                                        </div>
                                        <div class="subject-item">
                                            <span>75% Requirement</span>
                                            <span><strong><?php echo $sessions_for_75_percent; ?></strong> sessions</span>
                                        </div>
                                        <div class="subject-item">
                                            <span>Remaining to 75%</span>
                                            <span><strong><?php echo $remaining_for_75_percent; ?></strong> sessions</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Individual Subjects -->
                        <?php if (!empty($subjects_data)): ?>
                        <div class="mt-4">
                            <h6><i class="fas fa-list-alt me-2"></i>Individual Subjects</h6>
                            <div class="row">
                                <?php foreach ($subjects_data as $subject): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                                        <span>
                                            <strong><?php echo htmlspecialchars($subject['code']); ?></strong>
                                            <?php if ($subject['is_lab']): ?>
                                                <span class="badge badge-lab ms-1">Lab</span>
                                            <?php else: ?>
                                                <span class="badge badge-theory ms-1">Theory</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="text-muted"><?php echo $subject['target']; ?> sessions</span>
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
                            <h4 class="mb-2">
                                <i class="fas fa-bullseye me-2"></i>75% Attendance Goal
                            </h4>
                            <p class="mb-0">Track your progress towards 75% attendance requirement</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="predictor-number">
                                <?php echo $remaining_for_75_percent; ?>
                            </div>
                            <div class="fs-5">Sessions Needed</div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p><i class="fas fa-check-circle me-2"></i> <strong>Current:</strong> <?php echo $attended_count; ?> sessions attended</p>
                            <p><i class="fas fa-flag me-2"></i> <strong>Target:</strong> <?php echo $sessions_for_75_percent; ?> sessions for 75%</p>
                        </div>
                        <div class="col-md-6">
                            <p><i class="fas fa-calculator me-2"></i> <strong>Calculation:</strong></p>
                            <p>75% of <?php echo $total_possible_sessions; ?> = <?php echo $sessions_for_75_percent; ?> sessions</p>
                        </div>
                    </div>
                    
                    <div class="progress mt-2" style="height: 12px;">
                        <div class="progress-bar bg-warning" 
                             style="width: <?php echo min(100, ($attended_count / $sessions_for_75_percent) * 100); ?>%">
                        </div>
                    </div>
                    <div class="text-center mt-2">
                        <small>Progress: <?php echo min(100, round(($attended_count / $sessions_for_75_percent) * 100, 1)); ?>%</small>
                    </div>
                </div>

                <!-- Timetable Section -->
                <div class="timetable-section">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-2">
                                <i class="fas fa-calendar-alt me-2"></i>Class Timetable
                            </h4>
                            <p class="mb-0">View your weekly class schedule and upcoming sessions.</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <a href="timetable.php" class="btn btn-light">
                                <i class="fas fa-calendar me-2"></i> View Timetable
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance -->
                <div class="card">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Attendance</h5>
                        <span class="badge bg-light text-dark">
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
                                                <i class="fas fa-calendar-day text-primary me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($record['marked_at'])); ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($record['subject_code']); ?></strong>
                                                <?php if ($is_lab): ?>
                                                    <span class="badge badge-lab ms-1">Lab</span>
                                                <?php else: ?>
                                                    <span class="badge badge-theory ms-1">Theory</span>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($record['subject_name']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($is_lab): ?>
                                                    <span class="badge bg-info">Lab Session</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Theory Session</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-clock text-success me-1"></i>
                                                <?php echo date('h:i A', strtotime($record['marked_at'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i> Present
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="attendance_viewer.php" class="btn btn-primary">
                                    <i class="fas fa-chart-line me-2"></i> View Complete Report
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No attendance records yet</h5>
                                <p class="text-muted">Your attendance will appear here after scanning QR codes in class</p>
                            </div>
                        <?php endif; ?>
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
                width: 180,
                height: 180,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    });

    function downloadQR() {
        var canvas = document.querySelector("#qrcode canvas");
        if (!canvas) {
            alert('QR code not found!');
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
        
        // Show success message
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
    updateCurrentTime(); // Initial call

    // Toast notification function
    function showToast(type, message) {
        const toastHTML = `
            <div class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
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
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
        
        // Remove after hide
        toastEl.addEventListener('hidden.bs.toast', function () {
            toastContainer.remove();
        });
    }

    // Auto-refresh every 60 seconds
    setTimeout(function() {
        showToast('info', 'Refreshing attendance data...');
        setTimeout(function() {
            location.reload();
        }, 1000);
    }, 60000);
    </script>
</body>
</html>

<?php 
include 'footer.php';

if (ob_get_level() > 0) {
    ob_end_flush();
}
