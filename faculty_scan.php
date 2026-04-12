<?php
// faculty_scan.php - Fixed for Render + Aiven + AJAX attendance marking

// ==================== CRITICAL: Start output buffering ====================
if (!ob_get_level()) {
    ob_start();
}

// ==================== Configure session for Render ====================
ini_set('session.save_path', '/tmp');
ini_set('session.cookie_lifetime', 86400);

// ==================== Set timezone to Asia/Kolkata ====================
date_default_timezone_set('Asia/Kolkata');

// ==================== Start session ====================
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ==================== Include database config ====================
require_once 'config.php';

// ==================== Security check - Redirect if not logged in as faculty ====================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'] ?? null;
if (!$faculty_id) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

// ==================== Clean buffer before output ====================
if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

// ==================== Get active sessions for dropdown if no session specified ====================
if ($session_id === 0) {
    $active_sessions_query = "SELECT s.session_id, s.section_targeted, s.class_type, 
                             sub.subject_code, sub.subject_name
                             FROM sessions s
                             JOIN subjects sub ON s.subject_id = sub.subject_id
                             WHERE s.faculty_id = ? AND s.is_active = 1
                             ORDER BY s.session_id DESC";
    $stmt = mysqli_prepare($conn, $active_sessions_query);
    mysqli_stmt_bind_param($stmt, "s", $faculty_id);
    mysqli_stmt_execute($stmt);
    $active_sessions_result = mysqli_stmt_get_result($stmt);
    
    // If only one active session, auto-select it
    if (mysqli_num_rows($active_sessions_result) == 1) {
        $session = mysqli_fetch_assoc($active_sessions_result);
        $session_id = $session['session_id'];
    }
}

// ==================== Get session details if session_id is set ====================
$session_info = null;
if ($session_id > 0) {
    $stmt = mysqli_prepare($conn, 
        "SELECT s.*, sub.subject_code, sub.subject_name 
         FROM sessions s 
         JOIN subjects sub ON s.subject_id = sub.subject_id 
         WHERE s.session_id = ? AND s.faculty_id = ? AND s.is_active = 1");
    mysqli_stmt_bind_param($stmt, "is", $session_id, $faculty_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $session_info = mysqli_fetch_assoc($result);
    } else {
        // Session doesn't exist or is not active
        $session_id = 0;
    }
}

// ==================== Handle manual attendance marking (AJAX & regular POST) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    // Determine if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    $student_id = trim($_POST['student_id'] ?? '');
    $current_session_id = intval($_POST['session_id'] ?? 0);
    
    $response = ['success' => false, 'message' => '', 'student_name' => '', 'marked_at' => ''];
    
    if (!empty($student_id) && $current_session_id > 0) {
        // Check if student exists and is in correct section
        $check_student = mysqli_prepare($conn, 
            "SELECT student_id, student_name FROM students 
             WHERE student_id = ? AND section = (
                 SELECT section_targeted FROM sessions WHERE session_id = ?
             )");
        mysqli_stmt_bind_param($check_student, "si", $student_id, $current_session_id);
        mysqli_stmt_execute($check_student);
        $student_result = mysqli_stmt_get_result($check_student);
        
        if (mysqli_num_rows($student_result) > 0) {
            $student_data = mysqli_fetch_assoc($student_result);
            
            // Check if attendance already marked
            $check_attendance = mysqli_prepare($conn,
                "SELECT * FROM attendance_records 
                 WHERE session_id = ? AND student_id = ? LIMIT 1");
            mysqli_stmt_bind_param($check_attendance, "is", $current_session_id, $student_id);
            mysqli_stmt_execute($check_attendance);
            $attendance_result = mysqli_stmt_get_result($check_attendance);
            
            if (mysqli_num_rows($attendance_result) == 0) {
                // Mark attendance with current time in Asia/Kolkata
                $current_time = date('Y-m-d H:i:s');
                $insert_attendance = mysqli_prepare($conn,
                    "INSERT INTO attendance_records (session_id, student_id, marked_at) 
                     VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($insert_attendance, "iss", $current_session_id, $student_id, $current_time);
                
                if (mysqli_stmt_execute($insert_attendance)) {
                    $response['success'] = true;
                    $response['message'] = "Attendance marked for " . htmlspecialchars($student_data['student_name']) . " (ID: $student_id)";
                    $response['student_name'] = htmlspecialchars($student_data['student_name']);
                    $response['marked_at'] = date('h:i A', strtotime($current_time));
                    $response['student_id'] = $student_id;
                } else {
                    $response['message'] = "Failed to mark attendance. Please try again.";
                }
            } else {
                $existing_record = mysqli_fetch_assoc($attendance_result);
                $existing_time = date('h:i A', strtotime($existing_record['marked_at']));
                $response['message'] = "Attendance already marked for Student ID: $student_id at $existing_time";
            }
        } else {
            $response['message'] = "Student not found in this section or invalid ID.";
        }
    } else {
        $response['message'] = "Please enter a valid Student ID.";
    }
    
    if ($is_ajax) {
        // Return JSON for AJAX requests
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        // Regular form submission: store message in session and redirect
        if ($response['success']) {
            $_SESSION['scan_success'] = $response['message'];
        } else {
            $_SESSION['scan_error'] = $response['message'];
        }
        
        // Clean buffer before redirect
        if (ob_get_length() > 0) {
            ob_end_clean();
        }
        header("Location: faculty_scan.php?session_id=$current_session_id");
        exit;
    }
}

// ==================== Set page title ====================
$page_title = "QR Code Scanner";
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
    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        /* Same as before, keep existing styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card-header {
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
        }
        #qr-reader {
            border: 3px solid #dee2e6 !important;
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        #qr-reader button {
            margin: 8px;
            border-radius: 5px;
        }
        .position-fixed {
            position: fixed;
        }
        .recent-attendance-item {
            transition: background-color 0.2s;
            border-radius: 5px;
            margin-bottom: 3px;
        }
        .recent-attendance-item:hover {
            background-color: #f8f9fa;
        }
        .session-info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #28a745 !important;
        }
        .btn-view-attendance {
            background: linear-gradient(45deg, #17a2b8, #20c997);
            border: none;
            color: white;
            transition: all 0.3s;
        }
        .btn-view-attendance:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
        }
        .current-time-display {
            font-size: 0.9rem;
            background: linear-gradient(45deg, #6c757d, #495057);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .attendance-count-badge {
            font-size: 1.1rem;
            padding: 8px 15px;
            border-radius: 25px;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
        .scanner-active {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <!-- Back Button -->
                <div class="mb-4">
                    <a href="faculty_dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
                
                <!-- Page Header -->
                <div class="card shadow-lg border-0 mb-4">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-0"><i class="fas fa-qrcode me-2"></i> Attendance Scanner</h2>
                                <p class="mb-0 opacity-75">Scan student QR codes to mark attendance</p>
                            </div>
                            <div class="text-end d-flex align-items-center gap-3">
                                <span class="current-time-display">
                                    <i class="fas fa-clock me-1"></i>
                                    <span id="current-time"><?php echo date('h:i A'); ?></span>
                                </span>
                                <span class="badge bg-success fs-6 scanner-active">
                                    <i class="fas fa-circle fa-xs"></i> Scanner Active
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Session Selector -->
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Select Active Session:</label>
                                <select name="session_id" class="form-select" onchange="this.form.submit()">
                                    <option value="0" <?php echo $session_id == 0 ? 'selected' : ''; ?>>-- Select a session --</option>
                                    <?php
                                    if (isset($active_sessions_result)) {
                                        mysqli_data_seek($active_sessions_result, 0);
                                    } else {
                                        $active_sessions_query = "SELECT s.session_id, s.section_targeted, s.class_type, 
                                                                sub.subject_code, sub.subject_name
                                                                FROM sessions s
                                                                JOIN subjects sub ON s.subject_id = sub.subject_id
                                                                WHERE s.faculty_id = ? AND s.is_active = 1
                                                                ORDER BY s.session_id DESC";
                                        $stmt = mysqli_prepare($conn, $active_sessions_query);
                                        mysqli_stmt_bind_param($stmt, "s", $faculty_id);
                                        mysqli_stmt_execute($stmt);
                                        $active_sessions_result = mysqli_stmt_get_result($stmt);
                                    }
                                    
                                    if ($active_sessions_result) {
                                        while ($session = mysqli_fetch_assoc($active_sessions_result)):
                                    ?>
                                    <option value="<?php echo $session['session_id']; ?>" 
                                        <?php echo $session_id == $session['session_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($session['subject_code'] . ' - ' . $session['subject_name']); ?>
                                        (Section <?php echo $session['section_targeted']; ?> - <?php echo $session['class_type']; ?>)
                                    </option>
                                    <?php 
                                        endwhile;
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <?php if ($session_id > 0): ?>
                                <a href="stop_session.php?session_id=<?php echo $session_id; ?>" 
                                   class="btn btn-danger w-100"
                                   onclick="return confirm('Are you sure you want to stop this session?\n\nStudents will NOT be able to mark attendance after stopping.')">
                                    <i class="fas fa-stop-circle me-2"></i> Stop Session
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($session_id > 0 && $session_info): ?>
                <!-- View Attendance Button -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <a href="check_attendance.php?session_id=<?php echo $session_id; ?>" 
                                   class="btn btn-view-attendance">
                                    <i class="fas fa-list-check me-2"></i> View Complete Attendance
                                </a>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary attendance-count-badge" id="attendance-count-badge">
                                    <i class="fas fa-user-check me-1"></i>
                                    <?php
                                    $count_query = mysqli_prepare($conn,
                                        "SELECT COUNT(*) as count FROM attendance_records WHERE session_id = ?");
                                    mysqli_stmt_bind_param($count_query, "i", $session_id);
                                    mysqli_stmt_execute($count_query);
                                    $count_result = mysqli_stmt_get_result($count_query);
                                    $att_count = $count_result ? mysqli_fetch_assoc($count_result)['count'] ?? 0 : 0;
                                    echo $att_count . " students marked";
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Session Info -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-success session-info-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <h6><i class="fas fa-book me-2 text-primary"></i> Subject</h6>
                                        <p class="fw-bold"><?php echo htmlspecialchars($session_info['subject_code'] . ' - ' . $session_info['subject_name']); ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <h6><i class="fas fa-users me-2 text-success"></i> Section</h6>
                                        <p class="fw-bold">Section <?php echo htmlspecialchars($session_info['section_targeted']); ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <h6><i class="fas fa-chalkboard me-2 text-warning"></i> Type</h6>
                                        <p class="fw-bold"><?php echo htmlspecialchars($session_info['class_type']); ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <h6><i class="fas fa-clock me-2 text-info"></i> Started</h6>
                                        <p class="fw-bold">
                                            <?php 
                                            if (!empty($session_info['start_time'])) {
                                                echo date('h:i A', strtotime($session_info['start_time']));
                                            } elseif (!empty($session_info['created_at'])) {
                                                echo date('h:i A', strtotime($session_info['created_at']));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                    <div class="col-md-3">
                                        <h6><i class="fas fa-hourglass-half me-2 text-danger"></i> Duration</h6>
                                        <p class="fw-bold" id="session-duration">--:--</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Scanning Area -->
                <div class="row">
                    <!-- QR Scanner -->
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow-lg border-0 h-100">
                            <div class="card-header bg-dark text-white">
                                <h4 class="mb-0"><i class="fas fa-camera me-2"></i> QR Code Scanner</h4>
                            </div>
                            <div class="card-body text-center">
                                <div id="qr-reader" style="width: 100%; max-width: 600px; margin: 0 auto;"></div>
                                <div id="qr-reader-results" class="mt-3"></div>
                                
                                <!-- Manual Input Form -->
                                <div class="mt-4 pt-4 border-top">
                                    <h5 class="mb-3"><i class="fas fa-keyboard me-2"></i> Manual Entry</h5>
                                    <form method="POST" class="row g-3 justify-content-center" id="manualAttendanceForm">
                                        <div class="col-md-6">
                                            <div class="input-group input-group-lg">
                                                <span class="input-group-text bg-primary text-white">
                                                    <i class="fas fa-id-card"></i>
                                                </span>
                                                <input type="text" name="student_id" class="form-control" 
                                                       placeholder="Enter Student ID" required>
                                                <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" name="mark_attendance" class="btn btn-primary btn-lg w-100">
                                                <i class="fas fa-check-circle me-2"></i> Mark Attendance
                                            </button>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-outline-secondary btn-lg w-100"
                                                    onclick="document.querySelector('[name=student_id]').value = ''; document.querySelector('[name=student_id]').focus();">
                                                <i class="fas fa-times"></i> Clear
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Scans & Instructions -->
                    <div class="col-lg-4">
                        <!-- Instructions -->
                        <div class="card shadow-lg border-0 mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Instructions</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item d-flex align-items-center">
                                        <div class="me-3"><span class="badge bg-primary rounded-circle p-2">1</span></div>
                                        <div>Allow camera access when prompted</div>
                                    </div>
                                    <div class="list-group-item d-flex align-items-center">
                                        <div class="me-3"><span class="badge bg-success rounded-circle p-2">2</span></div>
                                        <div>Position QR code within scanner frame</div>
                                    </div>
                                    <div class="list-group-item d-flex align-items-center">
                                        <div class="me-3"><span class="badge bg-warning rounded-circle p-2">3</span></div>
                                        <div>Scanner will beep on successful scan</div>
                                    </div>
                                    <div class="list-group-item d-flex align-items-center">
                                        <div class="me-3"><span class="badge bg-danger rounded-circle p-2">4</span></div>
                                        <div>Or manually enter Student ID above</div>
                                    </div>
                                    <div class="list-group-item d-flex align-items-center">
                                        <div class="me-3"><span class="badge bg-dark rounded-circle p-2">5</span></div>
                                        <div>Click "Stop Session" when class ends</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Attendance -->
                        <div class="card shadow-lg border-0">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Attendance</h5>
                            </div>
                            <div class="card-body" style="max-height: 320px; overflow-y: auto;" id="recent-attendance-list">
                                <?php
                                $recent_query = mysqli_prepare($conn,
                                    "SELECT ar.student_id, ar.marked_at, s.student_name
                                     FROM attendance_records ar
                                     LEFT JOIN students s ON ar.student_id = s.student_id
                                     WHERE ar.session_id = ?
                                     ORDER BY ar.marked_at DESC
                                     LIMIT 8");
                                mysqli_stmt_bind_param($recent_query, "i", $session_id);
                                mysqli_stmt_execute($recent_query);
                                $recent_result = mysqli_stmt_get_result($recent_query);
                                
                                if ($recent_result && mysqli_num_rows($recent_result) > 0):
                                    while ($record = mysqli_fetch_assoc($recent_result)): ?>
                                    <div class="recent-attendance-item d-flex justify-content-between align-items-center p-3">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-user-circle fa-lg text-primary"></i>
                                            </div>
                                            <div>
                                                <span class="fw-bold d-block"><?php echo htmlspecialchars($record['student_id']); ?></span>
                                                <?php if (!empty($record['student_name'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($record['student_name']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-success fw-bold"><i class="fas fa-check"></i></small><br>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($record['marked_at'])); ?></small>
                                        </div>
                                    </div>
                                    <?php endwhile;
                                else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-user-clock fa-3x text-muted mb-3"></i>
                                    <h6 class="text-muted mb-2">No attendance marked yet</h6>
                                    <p class="text-muted small">Scan or enter student IDs to mark attendance</p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($att_count > 8): ?>
                                <div class="text-center mt-3 pt-3 border-top">
                                    <a href="check_attendance.php?session_id=<?php echo $session_id; ?>" 
                                       class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-eye me-1"></i> View All Attendance
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($session_id == 0): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-lg border-0">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-qrcode fa-4x text-muted mb-4"></i>
                                <h3 class="text-muted mb-3">No Active Session Selected</h3>
                                <p class="text-muted mb-4">Please select an active session from the dropdown above, or start a new session from the dashboard.</p>
                                <a href="faculty_dashboard.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus-circle me-2"></i> Start New Session
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-lg border-0">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i>
                                <h3 class="text-warning mb-3">Session Not Available</h3>
                                <p class="text-muted mb-4">This session may have ended or is no longer active.</p>
                                <a href="faculty_dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Update current time every second
    function updateCurrentTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        document.getElementById('current-time').textContent = timeString;
    }
    
    // Update session duration
    function updateSessionDuration() {
        <?php if ($session_id > 0 && !empty($session_info['start_time'])): ?>
        const startTime = new Date('<?php echo $session_info['start_time']; ?>');
        const now = new Date();
        const diffMs = now - startTime;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const mins = diffMins % 60;
        let durationString = diffHours > 0 ? diffHours + 'h ' + mins + 'm' : mins + 'm';
        document.getElementById('session-duration').textContent = durationString;
        <?php endif; ?>
    }
    
    setInterval(updateCurrentTime, 1000);
    setInterval(updateSessionDuration, 60000);
    updateSessionDuration();
    
    // Display session messages (for regular POST, not AJAX)
    <?php if (isset($_SESSION['scan_success'])): ?>
        showAlert('success', '<?php echo addslashes($_SESSION['scan_success']); ?>');
        <?php unset($_SESSION['scan_success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['scan_error'])): ?>
        showAlert('danger', '<?php echo addslashes($_SESSION['scan_error']); ?>');
        <?php unset($_SESSION['scan_error']); ?>
    <?php endif; ?>

    // QR Scanner Functionality (only if session active)
    <?php if ($session_id > 0): ?>
    let html5QrcodeScanner = null;
    let isProcessing = false;

    function onScanSuccess(decodedText, decodedResult) {
        if (isProcessing) return;
        
        if (html5QrcodeScanner) html5QrcodeScanner.pause();
        
        // Disable manual form
        const manualForm = document.getElementById('manualAttendanceForm');
        const manualInput = document.querySelector('[name="student_id"]');
        const manualButton = document.querySelector('button[name="mark_attendance"]');
        if (manualForm) {
            manualForm.style.opacity = '0.5';
            if (manualInput) manualInput.disabled = true;
            if (manualButton) manualButton.disabled = true;
        }
        
        playBeepSound();
        
        const now = new Date();
        const scanTime = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        
        document.getElementById('qr-reader-results').innerHTML = `
            <div class="alert alert-info alert-dismissible fade show">
                <div class="d-flex align-items-center">
                    <i class="fas fa-spinner fa-spin me-3"></i>
                    <div>
                        <h6 class="mb-1">Processing scan...</h6>
                        <p class="mb-1">Student ID: <strong>${decodedText}</strong></p>
                        <p class="mb-2">Scan time: <strong>${scanTime}</strong></p>
                        <small>Automatically marking attendance...</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        markAttendance(decodedText);
    }

    function onScanFailure(error) {
        // Silent fail
    }

    function playBeepSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        } catch (e) {}
    }

    function markAttendance(studentId) {
        isProcessing = true;
        
        const formData = new FormData();
        formData.append('student_id', studentId);
        formData.append('session_id', <?php echo $session_id; ?>);
        formData.append('mark_attendance', '1');
        
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }, // Identify AJAX request
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                // Add new attendance record to the top of recent list
                addRecentAttendance(data.student_id, data.student_name, data.marked_at);
                // Update attendance count badge
                updateAttendanceCount();
                // Clear scanner results area and resume scanner
                document.getElementById('qr-reader-results').innerHTML = '';
                resumeScanner();
            } else {
                showAlert('danger', data.message);
                resumeScanner();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Failed to mark attendance. Please try manually.');
            resumeScanner();
        })
        .finally(() => {
            isProcessing = false;
            // Re-enable manual form
            const manualInput = document.querySelector('[name="student_id"]');
            const manualButton = document.querySelector('button[name="mark_attendance"]');
            const manualForm = document.getElementById('manualAttendanceForm');
            if (manualInput) manualInput.disabled = false;
            if (manualButton) manualButton.disabled = false;
            if (manualForm) manualForm.style.opacity = '1';
            // Clear the manual input field for next entry
            if (manualInput) manualInput.value = '';
        });
    }

    function addRecentAttendance(studentId, studentName, markedAt) {
        const recentContainer = document.getElementById('recent-attendance-list');
        // Create new element
        const newItem = document.createElement('div');
        newItem.className = 'recent-attendance-item d-flex justify-content-between align-items-center p-3';
        newItem.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-user-circle fa-lg text-primary"></i>
                </div>
                <div>
                    <span class="fw-bold d-block">${escapeHtml(studentId)}</span>
                    <small class="text-muted">${escapeHtml(studentName)}</small>
                </div>
            </div>
            <div class="text-end">
                <small class="text-success fw-bold"><i class="fas fa-check"></i></small><br>
                <small class="text-muted">${escapeHtml(markedAt)}</small>
            </div>
        `;
        // Insert at the top
        if (recentContainer.firstChild) {
            recentContainer.insertBefore(newItem, recentContainer.firstChild);
        } else {
            recentContainer.appendChild(newItem);
        }
        // If more than 8 items, remove the last one
        const items = recentContainer.querySelectorAll('.recent-attendance-item');
        if (items.length > 8) {
            items[items.length - 1].remove();
        }
        // Remove the "No attendance" placeholder if it exists
        const emptyDiv = recentContainer.querySelector('.text-center.py-5');
        if (emptyDiv && items.length > 0) {
            emptyDiv.remove();
        }
    }

    function updateAttendanceCount() {
        const badge = document.getElementById('attendance-count-badge');
        if (badge) {
            // Extract current number and increment
            let text = badge.innerText;
            let match = text.match(/(\d+)/);
            if (match) {
                let newCount = parseInt(match[1]) + 1;
                badge.innerHTML = `<i class="fas fa-user-check me-1"></i> ${newCount} students marked`;
            } else {
                // If no number, just reload page (fallback)
                window.location.reload();
            }
        }
    }

    function resumeScanner() {
        document.getElementById('qr-reader-results').innerHTML = '';
        if (html5QrcodeScanner && !isProcessing) {
            html5QrcodeScanner.resume();
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Prevent manual form submission while processing
    const manualSubmitBtn = document.querySelector('button[name="mark_attendance"]');
    if (manualSubmitBtn) {
        manualSubmitBtn.addEventListener('click', function(e) {
            if (isProcessing) {
                e.preventDefault();
                showAlert('warning', 'Please wait, a scan is already being processed.');
                return false;
            }
            // For manual submission, also use AJAX to avoid page reload
            e.preventDefault();
            const form = document.getElementById('manualAttendanceForm');
            const formData = new FormData(form);
            formData.append('mark_attendance', '1');
            // Disable form during processing
            const manualInput = document.querySelector('[name="student_id"]');
            const manualButton = document.querySelector('button[name="mark_attendance"]');
            if (manualInput) manualInput.disabled = true;
            if (manualButton) manualButton.disabled = true;
            document.getElementById('manualAttendanceForm').style.opacity = '0.5';
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    addRecentAttendance(data.student_id, data.student_name, data.marked_at);
                    updateAttendanceCount();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                showAlert('danger', 'Failed to mark attendance.');
            })
            .finally(() => {
                if (manualInput) manualInput.disabled = false;
                if (manualButton) manualButton.disabled = false;
                document.getElementById('manualAttendanceForm').style.opacity = '1';
                if (manualInput) manualInput.value = '';
                manualInput.focus();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        html5QrcodeScanner = new Html5QrcodeScanner(
            "qr-reader", 
            { 
                fps: 10, 
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0,
                showTorchButtonIfSupported: true,
                showZoomSliderIfSupported: true
            },
            false
        );
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        document.querySelector('[name="student_id"]')?.focus();
    });
    <?php endif; ?>

    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-4`;
        alertDiv.style.zIndex = '1050';
        alertDiv.style.maxWidth = '400px';
        alertDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'exclamation-triangle'} fa-lg me-3"></i>
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.body.appendChild(alertDiv);
        setTimeout(() => {
            if (alertDiv.parentNode) {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }
        }, 5000);
    }
    </script>
</body>
</html>
<?php
if (ob_get_level() > 0) {
    ob_end_flush();
}
