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
        
        // ==================== GET SESSIONS FROM SUBJECTS TABLE ====================
        // Query to get all subjects and their target_sessions
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
        } else {
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
// Count how many sessions have actually happened (past sessions)
$sessions_happened = 0;
if ($conn) {
    // Try multiple queries to get sessions happened
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
    
    // If still 0, use total_possible_sessions as fallback for percentage calculation
    if ($sessions_happened == 0) {
        $sessions_happened = $total_possible_sessions;
    }
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

<style>
    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        .card {
            margin-bottom: 15px;
        }
        
        .waving-avatar {
            width: 100px !important;
            height: 100px !important;
        }
        
        .profile-img {
            width: 120px !important;
            height: 120px !important;
        }
        
        .timetable-card {
            padding: 15px !important;
        }
        
        .timetable-btn {
            padding: 8px 15px !important;
            font-size: 0.9rem !important;
        }
        
        .qr-container {
            width: 180px !important;
            height: 180px !important;
        }
        
        .table-responsive {
            font-size: 0.85rem;
        }
        
        .table th, .table td {
            padding: 0.5rem !important;
        }
        
        .stat-number {
            font-size: 2rem !important;
        }
        
        .predictor-card {
            padding: 15px !important;
        }
    }
    
    @media (max-width: 576px) {
        .col-md-4, .col-md-8 {
            padding-left: 10px !important;
            padding-right: 10px !important;
        }
        
        .waving-avatar {
            width: 80px !important;
            height: 80px !important;
        }
        
        .profile-img {
            width: 100px !important;
            height: 100px !important;
        }
        
        .btn-lg {
            padding: 0.5rem 1rem !important;
            font-size: 0.9rem !important;
        }
        
        .card-header h5 {
            font-size: 1.1rem !important;
        }
        
        .card-body h5 {
            font-size: 1.2rem !important;
        }
        
        .qr-container {
            width: 150px !important;
            height: 150px !important;
        }
    }

    /* QR Code Container - Always white background */
    .qr-container {
        background-color: white !important;
        padding: 10px;
        border-radius: 8px;
        border: 2px solid #dee2e6;
        display: inline-block;
        margin: 0 auto 15px auto;
    }
    
    /* Force QR code to be visible in dark mode */
    [data-bs-theme="dark"] .qr-container,
    .dark-mode .qr-container {
        background-color: white !important;
        border-color: #495057 !important;
    }
    
    /* Avatar Animation - NO SHAKING */
    .waving-avatar {
        width: 120px;
        height: 120px;
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center;
        margin: 0 auto;
        /* No animation to prevent shaking */
    }
    
    .female-avatar {
        background-image: url('assets/images/female_avatar.gif');
    }
    
    .male-avatar {
        background-image: url('assets/images/male_avatar.gif');
    }
    
    /* Profile Picture Styling */
    .profile-img {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    
    .profile-img:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }
    
    /* Gender Badge */
    .gender-badge {
        font-size: 0.8rem;
        padding: 3px 10px;
        border-radius: 15px;
        margin-left: 5px;
    }
    
    .gender-male {
        background-color: #007bff;
        color: white;
    }
    
    .gender-female {
        background-color: #e83e8c;
        color: white;
    }
    
    /* Timetable Section */
    .timetable-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
    }
    
    .timetable-btn {
        background: white;
        color: #764ba2;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    
    .timetable-btn:hover {
        background: #f8f9fa;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        color: #764ba2;
        text-decoration: none;
    }
    
    /* Stats Cards */
    .stat-card {
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
        color: white;
        text-align: center;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .stat-card-1 {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }
    
    .stat-card-2 {
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    }
    
    .stat-card-3 {
        background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
    }
    
    .stat-number {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .attendance-status-badge {
        font-size: 0.9rem;
        padding: 5px 15px;
        border-radius: 20px;
        margin-top: 10px;
        display: inline-block;
    }
    
    /* QR Code Download Button */
    .qr-download-btn {
        background: linear-gradient(45deg, #28a745, #20c997);
        border: none;
        transition: all 0.3s ease;
    }
    
    .qr-download-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    }
    
    /* Attendance Table */
    .attendance-table tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
    }
    
    /* Custom Scrollbar */
    .recent-attendance-scroll {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .recent-attendance-scroll::-webkit-scrollbar {
        width: 6px;
    }
    
    .recent-attendance-scroll::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .recent-attendance-scroll::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }
    
    .recent-attendance-scroll::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    /* Progress Bar */
    .attendance-progress {
        height: 20px;
        border-radius: 10px;
        margin: 10px 0;
    }
    
    .progress-percentage {
        font-size: 0.9rem;
        font-weight: bold;
        margin-top: 5px;
    }
    
    /* 75% Predictor Card */
    .predictor-card {
        background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
        color: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(23, 162, 184, 0.2);
    }
    
    .predictor-number {
        font-size: 2.8rem;
        font-weight: bold;
        margin-bottom: 10px;
        text-align: center;
    }
    
    .predictor-label {
        font-size: 1.1rem;
        margin-bottom: 5px;
    }
    
    .predictor-detail {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    /* Calculation Box */
    .calculation-box {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 15px;
        margin-top: 15px;
        border-left: 4px solid #ffc107;
    }
    
    /* Subjects Breakdown */
    .subjects-breakdown {
        margin-top: 15px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
        font-size: 0.9rem;
    }
    
    .subject-item {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px solid #dee2e6;
    }
    
    .subject-item:last-child {
        border-bottom: none;
    }
    
    .lab-badge {
        background-color: #17a2b8;
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.8rem;
        margin-left: 5px;
    }
    
    .subject-badge {
        background-color: #28a745;
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.8rem;
        margin-left: 5px;
    }
    
    .session-info {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 3px;
    }
</style>

<div class="row">
    <!-- Left Column: Profile & QR Code -->
    <div class="col-md-4 mb-4">
        <!-- Welcome Avatar with Animation -->
        <div class="card shadow-lg border-0 mb-4">
            <div class="card-body text-center">
                <div class="waving-avatar <?php echo $avatar_class; ?> mb-3"></div>
                <h4 class="text-primary">
                    Hi, <?php 
                        $name_parts = explode(' ', $student['student_name'] ?? 'Student');
                        echo htmlspecialchars($name_parts[0]); 
                    ?>!
                    <span class="gender-badge gender-<?php echo $gender; ?>">
                        <i class="fas fa-<?php echo ($gender === 'female') ? 'venus' : 'mars'; ?>"></i>
                        <?php echo ucfirst($gender); ?>
                    </span>
                </h4>
                <p class="text-muted mb-0">Welcome to your smart attendance dashboard</p>
            </div>
        </div>

        <!-- Profile Card -->
        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>My Profile</h5>
            </div>
            <div class="card-body text-center">
                <!-- Profile Picture - ALWAYS USE default.png -->
                <div class="mb-3">
                    <?php
                    // ALWAYS USE default.png FOR ALL STUDENTS
                    $profile_pic = 'uploads/profiles/' . $default_avatar;
                    ?>
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                         class="rounded-circle profile-img border" 
                         alt="Profile Picture"
                         onerror="this.onerror=null; this.src='uploads/profiles/default.png';">
                </div>
                
                <h5 class="card-title"><?php echo htmlspecialchars($student['student_name'] ?? 'Student'); ?></h5>
                <p class="card-text text-muted">
                    <!-- Display student_id and id_number correctly -->
                    <i class="fas fa-id-card me-1"></i> Student ID: <?php echo htmlspecialchars($student_id); ?><br>
                    <i class="fas fa-hashtag me-1"></i> ID Number: <?php echo htmlspecialchars($id_number); ?><br>
                    
                    <!-- Display year as E2 format -->
                    <?php if (!empty($year_display)): ?>
                    <i class="fas fa-calendar-alt me-1"></i> Year: <?php echo htmlspecialchars($year_display); ?><br>
                    <?php endif; ?>
                    
                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($student['student_email'] ?? 'N/A'); ?><br>
                    <i class="fas fa-users me-1"></i> Section <?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?><br>
                    <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($student['student_department'] ?? 'N/A'); ?><br>
                    <i class="fas fa-<?php echo ($gender === 'female') ? 'venus' : 'mars'; ?> me-1"></i>
                    <?php echo ucfirst(htmlspecialchars($gender)); ?>
                </p>
            </div>
        </div>

        <!-- QR Code Card -->
        <div class="card shadow-lg border-0 mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>My QR Code</h5>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($student['qr_content'])): ?>
                    <!-- Normal QR Code Container -->
                    <div class="qr-container">
                        <div id="qrcode"></div>
                    </div>
                    <p class="mt-2 small text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Show this QR code during class to mark attendance
                    </p>
                    <div class="d-grid gap-2">
                        <button onclick="downloadQR()" class="btn qr-download-btn">
                            <i class="fas fa-download me-1"></i> Download QR Code
                        </button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        QR Code not generated yet. Contact administrator.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Dashboard Content -->
    <div class="col-md-8">
        <!-- Quick Stats Row -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-card-1">
                    <div class="stat-number">
                        <i class="fas fa-calendar-check"></i> <?php echo $total_attendance; ?>
                    </div>
                    <h6 class="mb-0">Classes Attended</h6>
                    <small>Out of <?php echo $total_possible_sessions; ?> total</small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-card-2">
                    <div class="stat-number">
                        <i class="fas fa-percentage"></i> <?php echo $attendance_percentage; ?>%
                    </div>
                    <h6 class="mb-0">Attendance %</h6>
                    <div class="small mb-2">
                        <?php if ($sessions_happened > 0): ?>
                            (<?php echo $total_attendance; ?>/<?php echo $sessions_happened; ?> sessions)
                        <?php else: ?>
                            (No sessions yet)
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-<?php echo $attendance_class; ?> attendance-status-badge">
                        <?php echo $attendance_status; ?>
                    </span>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-card-3">
                    <div class="stat-number">
                        <i class="fas fa-clock"></i> <span id="current-time"><?php echo date('h:i A'); ?></span>
                    </div>
                    <h6 class="mb-0">Current Time</h6>
                    <small><?php echo date('l, F j, Y'); ?></small>
                </div>
            </div>
        </div>

        <!-- 75% ATTENDANCE PREDICTOR CARD -->
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
                    <div class="predictor-label">Sessions Needed</div>
                </div>
            </div>
            
            <div class="calculation-box mt-3">
                <div class="row">
                    <div class="col-md-6">
                        <div class="predictor-detail">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Current:</strong> <?php echo $total_attendance; ?> sessions attended
                        </div>
                        <div class="predictor-detail mt-2">
                            <i class="fas fa-flag me-2"></i>
                            <strong>Target:</strong> <?php echo $sessions_for_75_percent; ?> sessions for 75%
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="predictor-detail">
                            <i class="fas fa-calculator me-2"></i>
                            <strong>Calculation:</strong><br>
                            75% of <?php echo $total_possible_sessions; ?> = <?php echo $sessions_for_75_percent; ?> sessions
                        </div>
                    </div>
                </div>
                
                <!-- Progress towards 75% -->
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small>Progress to 75%:</small>
                        <?php if ($sessions_for_75_percent > 0): ?>
                        <small><?php echo min(100, round(($total_attendance / $sessions_for_75_percent) * 100, 1)); ?>%</small>
                        <?php else: ?>
                        <small>0%</small>
                        <?php endif; ?>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-warning" 
                             role="progressbar" 
                             style="width: <?php echo $sessions_for_75_percent > 0 ? min(100, ($total_attendance / $sessions_for_75_percent) * 100) : 0; ?>%"
                             aria-valuenow="<?php echo $sessions_for_75_percent > 0 ? min(100, ($total_attendance / $sessions_for_75_percent) * 100) : 0; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                        </div>
                    </div>
                </div>
                
                <!-- Encouragement Message -->
                <?php if ($remaining_for_75_percent > 0): ?>
                <div class="alert alert-light mt-3 mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Goal:</strong> Attend <?php echo $remaining_for_75_percent; ?> more sessions 
                    (<?php echo ceil($remaining_for_75_percent / 8); ?> weeks at 100% attendance)
                </div>
                <?php else: ?>
                <div class="alert alert-success mt-3 mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    <strong>Congratulations!</strong> You've already achieved 75% attendance requirement!
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Attendance Progress Section -->
        <div class="card shadow-lg border-0 mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Attendance Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Current Status: <span class="badge bg-<?php echo $attendance_class; ?>"><?php echo $attendance_status; ?></span></h6>
                        <?php if ($sessions_happened > 0): ?>
                        <div class="progress attendance-progress">
                            <div class="progress-bar bg-<?php echo $attendance_class; ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo min($attendance_percentage, 100); ?>%"
                                 aria-valuenow="<?php echo $attendance_percentage; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                        <div class="progress-percentage text-center">
                            <?php echo $attendance_percentage; ?>% (<?php echo $total_attendance; ?>/<?php echo $sessions_happened; ?> sessions)
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            No class sessions have been scheduled yet.
                        </div>
                        <?php endif; ?>
                        
                        <!-- Subjects Breakdown -->
                        <div class="subjects-breakdown mt-3">
                            <h6><i class="fas fa-book me-2"></i>Subject Breakdown:</h6>
                            <div class="subject-item">
                                <span>Theory Sessions <span class="subject-badge">Subject</span></span>
                                <span><strong><?php echo $theory_sessions; ?></strong> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Lab Sessions <span class="lab-badge">Lab</span></span>
                                <span><strong><?php echo $lab_sessions; ?></strong> sessions</span>
                                <small class="text-muted">(<?php echo ceil($lab_sessions / 3); ?> lab classes)</small>
                            </div>
                            <div class="subject-item">
                                <span>Total Sessions</span>
                                <span><strong><?php echo $total_possible_sessions; ?></strong> sessions</span>
                            </div>
                            
                            <!-- Display individual subjects from database -->
                            <?php if (!empty($subjects_data)): ?>
                            <div class="session-info mt-2">
                                <strong>Individual Subjects:</strong>
                                <?php foreach ($subjects_data as $subject): ?>
                                <div style="font-size: 0.8rem; margin-top: 3px;">
                                    <?php echo htmlspecialchars($subject['code']) . ' - ' . htmlspecialchars($subject['name']); ?>
                                    (<?php echo $subject['target']; ?> sessions)
                                    <?php if ($subject['is_lab']): ?>
                                        <span class="lab-badge" style="font-size: 0.7rem;">Lab</span>
                                    <?php else: ?>
                                        <span class="subject-badge" style="font-size: 0.7rem;">Subject</span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="session-info mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Note: Each lab session counts as 1. <?php echo ceil($lab_sessions / 3); ?> actual lab classes needed.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-chart-pie me-2"></i>Attendance Distribution</h6>
                        <div class="subjects-breakdown">
                            <div class="subject-item">
                                <span>75% Requirement</span>
                                <span><strong><?php echo $sessions_for_75_percent; ?></strong> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Your Attendance</span>
                                <span><strong><?php echo $total_attendance; ?></strong> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Remaining to 75%</span>
                                <span><strong><?php echo $remaining_for_75_percent; ?></strong> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Weekly Goal</span>
                                <span><strong><?php echo ceil($remaining_for_75_percent / 8); ?></strong> weeks at 100%</span>
                            </div>
                        </div>
                        
                        <!-- Detailed Attendance Info -->
                        <div class="subjects-breakdown mt-3">
                            <h6><i class="fas fa-calculator me-2"></i>Attendance Calculation:</h6>
                            <div class="subject-item">
                                <span>To Attend (Theory)</span>
                                <span><?php echo $theory_sessions; ?> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>To Attend (Labs)</span>
                                <span><?php echo $lab_sessions; ?> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Already Attended</span>
                                <span><?php echo $total_attendance; ?> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Remaining All</span>
                                <span><?php echo max(0, $total_possible_sessions - $total_attendance); ?> sessions</span>
                            </div>
                        </div>
                        
                        <!-- Real-time Update Notice -->
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-sync-alt me-2"></i>
                            <strong>Auto-updating:</strong> Your 75% goal decreases automatically as you attend classes.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TIMETABLE SECTION -->
        <div class="timetable-section">
            <div class="timetable-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-2">
                            <i class="fas fa-calendar-alt me-2"></i>Class Timetable
                        </h4>
                        <p class="mb-0">View your weekly class schedule and upcoming sessions.</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <a href="timetable.php" class="timetable-btn">
                            <i class="fas fa-calendar me-2"></i> View Timetable
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance History -->
        <div class="card shadow-lg border-0">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Attendance</h5>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-user-check me-1"></i>
                    <?php echo $total_attendance; ?> Records
                </span>
            </div>
            <div class="card-body">
                <?php if ($attendance_result && mysqli_num_rows($attendance_result) > 0): ?>
                    <div class="recent-attendance-scroll">
                        <div class="table-responsive">
                            <table class="table table-hover attendance-table">
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
                                        $subject_name = $record['subject_name'] ?? '';
                                        $subject_code = $record['subject_code'] ?? '';
                                        $class_type = $record['class_type'] ?? '';
                                        $lab_type = $record['lab_type'] ?? '';
                                        
                                        // Check if it's a lab based on class_type and lab_type
                                        $is_lab = false;
                                        if ($class_type === 'lab') {
                                            $is_lab = true;
                                        } elseif ($class_type === 'normal' && $lab_type === 'lecture') {
                                            $is_lab = false;
                                        }
                                        
                                        // Determine session type display
                                        $session_type_display = "Lecture";
                                        if ($is_lab) {
                                            if ($lab_type === 'pre-lab') {
                                                $session_type_display = "Pre-Lab";
                                            } elseif ($lab_type === 'during-lab') {
                                                $session_type_display = "During-Lab";
                                            } elseif ($lab_type === 'post-lab') {
                                                $session_type_display = "Post-Lab";
                                            } else {
                                                $session_type_display = "Lab";
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-calendar-day text-primary me-1"></i>
                                            <?php echo date('d/m/Y', strtotime($record['marked_at'])); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($subject_code); ?></strong>
                                            <?php if ($is_lab): ?>
                                                <span class="lab-badge">Lab</span>
                                            <?php else: ?>
                                                <span class="subject-badge">Subject</span>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($subject_name); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($is_lab): ?>
                                                <span class="badge bg-info"><?php echo $session_type_display; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-primary"><?php echo $session_type_display; ?></span>
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
                                            <br>
                                            <small class="text-muted session-info">
                                                <?php if ($is_lab): ?>
                                                    Lab session (counts as 1)
                                                <?php else: ?>
                                                    Theory session
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="attendance_viewer.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-chart-line me-2"></i> View Complete Attendance Report
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-clipboard-list fa-4x text-muted"></i>
                        </div>
                        <h5 class="text-muted">No attendance records yet</h5>
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

<!-- QR Code Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// Generate QR code from database content
<?php if (!empty($student['qr_content'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Clear existing QR code
    const qrElement = document.getElementById('qrcode');
    if (qrElement) {
        qrElement.innerHTML = '';
    }
    
    // Create normal QR code with black on white
    var qrcode = new QRCode(document.getElementById("qrcode"), {
        text: "<?php echo addslashes($student['qr_content']); ?>",
        width: 180,
        height: 180,
        colorDark: "#000000",  // Always black
        colorLight: "#ffffff", // Always white
        correctLevel: QRCode.CorrectLevel.H
    });
});

function downloadQR() {
    var canvas = document.querySelector("#qrcode canvas");
    if (!canvas) {
        alert('QR code not found!');
        return;
    }
    
    // Create a temporary white background
    var tempCanvas = document.createElement('canvas');
    var ctx = tempCanvas.getContext('2d');
    tempCanvas.width = canvas.width;
    tempCanvas.height = canvas.height;
    
    // Fill with white background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
    
    // Draw the QR code on top
    ctx.drawImage(canvas, 0, 0);
    
    // Create download link
    var link = document.createElement('a');
    link.download = 'Attendance_QR_<?php echo $student_id; ?>.png';
    link.href = tempCanvas.toDataURL("image/png");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show success message
    showAlert('success', 'QR code downloaded successfully!');
}
<?php endif; ?>

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alertDiv.style.zIndex = '1050';
    alertDiv.style.maxWidth = '350px';
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} fa-lg me-3"></i>
            <div class="flex-grow-1">
                ${message}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.body.appendChild(alertDiv);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            const bsAlert = new bootstrap.Alert(alertDiv);
            bsAlert.close();
        }
    }, 3000);
}

// Update current time every minute
function updateCurrentTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-IN', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
    document.getElementById('current-time').textContent = timeString;
}

// Update time every minute
setInterval(updateCurrentTime, 60000);

// Auto-refresh attendance data every 60 seconds
setTimeout(function() {
    // Show refresh notification
    showAlert('info', 'Refreshing attendance data...');
    setTimeout(function() {
        location.reload();
    }, 1000);
}, 60000); // 60 seconds
</script>

<?php 
include 'footer.php';

// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
1)beside clasess attdenced the total session calculated munch be filter by section 
2) donot chnage the logic or ui?ux nad funtionality
3) tell what you undestand and give me the correctcode whole code atnonec
