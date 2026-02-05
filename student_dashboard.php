<?php
// student_dashboard.php - Render + Aiven + GitHub Compatible

// ==================== CRITICAL: Start output buffering ====================
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

// ==================== Get student ID ====================
$student_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null;
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

// ==================== Database operations ====================
$student = null;
$attendance_result = null;
$error = '';
$success = '';

// Get student details - USING PREPARED STATEMENTS FOR SECURITY
$student_query = "SELECT * FROM students WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $student_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} else {
    $error = "Database query error: " . mysqli_error($conn);
}

// Get student's attendance - USING PREPARED STATEMENTS FOR SECURITY
if ($student) {
    $attendance_query = "SELECT ar.*, s.subject_code, s.subject_name, ses.session_id, ses.start_time
                         FROM attendance_records ar 
                         JOIN sessions ses ON ar.session_id = ses.session_id
                         JOIN subjects s ON ses.subject_id = s.subject_id
                         WHERE ar.student_id = ? 
                         ORDER BY ar.marked_at DESC LIMIT 10";
    $stmt2 = mysqli_prepare($conn, $attendance_query);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, "s", $student_id);
        mysqli_stmt_execute($stmt2);
        $attendance_result = mysqli_stmt_get_result($stmt2);
        mysqli_stmt_close($stmt2);
    } else {
        $error = "Attendance query error: " . mysqli_error($conn);
    }
}

// Get QR code path
$qr_path = "qrcodes/student_" . $student_id . ".png";

// Set page title
$page_title = "Student Dashboard";

// Include header
include 'header.php';
?>

<!-- Force QR code to use light theme always -->
<style>
    /* Override dark mode for QR code specifically */
    #qrcode, 
    #qrcode canvas,
    .qrcode-container {
        background-color: white !important;
        padding: 15px;
        border-radius: 8px;
        border: 2px solid #dee2e6;
    }
    
    /* Ensure QR code has proper contrast */
    .dark-mode #qrcode,
    .dark-mode #qrcode canvas,
    [data-bs-theme="dark"] #qrcode,
    [data-bs-theme="dark"] #qrcode canvas {
        background-color: white !important;
        border-color: #495057 !important;
    }
    
    /* Make sure the card background doesn't affect QR code */
    .card .qrcode-wrapper {
        background-color: white !important;
    }
    
    /* Timetable Button Styles */
    .timetable-btn {
        background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .timetable-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        color: white;
        text-decoration: none;
    }
    
    /* Quick Stats Styles */
    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }
    
    .dark-mode .stat-card {
        background: #1e1e1e;
        color: #e0e0e0;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    }
    
    .stat-card i {
        font-size: 2.5rem;
        margin-bottom: 15px;
    }
    
    .stat-card h3 {
        font-weight: 700;
        margin: 0;
    }
    
    .stat-card p {
        color: #6c757d;
        margin: 0;
    }
</style>

<div class="row">
    <!-- Left Column: Profile & QR Code -->
    <div class="col-md-4 mb-4">
        <!-- Profile Card -->
        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>My Profile</h5>
            </div>
            <div class="card-body text-center">
                <!-- Profile Picture -->
                <div class="mb-3">
                    <?php
                    $profile_pic = !empty($student['profile_pic']) ? $student['profile_pic'] : 'uploads/profiles/default.png';
                    if (!file_exists($profile_pic)) {
                        $profile_pic = 'uploads/profiles/default.png';
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                         class="rounded-circle border" 
                         style="width: 150px; height: 150px; object-fit: cover;"
                         alt="Profile Picture">
                    <div class="mt-2">
                        <a href="student_profile.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-camera me-1"></i> Change Photo
                        </a>
                    </div>
                </div>
                
                <h5 class="card-title"><?php echo htmlspecialchars($student['student_name']); ?></h5>
                <p class="card-text text-muted">
                    <i class="fas fa-fingerprint me-1"></i> Student ID: <?php echo htmlspecialchars($student_id); ?><br>
                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($student['student_email']); ?><br>
                    <i class="fas fa-id-card me-1"></i> ID: <?php echo htmlspecialchars($student['id_number'] ?? 'N/A'); ?><br>
                    <i class="fas fa-users me-1"></i> Section <?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?><br>
                    <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($student['student_department'] ?? 'N/A'); ?>
                </p>
            </div>
        </div>

        <!-- QR Code Card -->
        <div class="card shadow-lg border-0 mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>My QR Code</h5>
            </div>
            <div class="card-body text-center qrcode-wrapper">
                <?php if (!empty($student['qr_content'])): ?>
                    <!-- Wrapper div for QR code with forced light theme -->
                    <div class="qrcode-container d-inline-block">
                        <div id="qrcode"></div>
                    </div>
                    <p class="mt-2 small text-muted">
                        Scan this QR code during class to mark attendance
                    </p>
                    <div class="d-grid gap-2">
                        <button onclick="downloadQR()" class="btn btn-sm btn-success">
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

    <!-- Right Column: Attendance & Info -->
    <div class="col-md-8">
        <!-- Welcome Card -->
        <div class="card shadow-lg border-0 mb-4">
            <div class="card-body">
                <h4 class="text-primary">Welcome, <?php 
                    $name_parts = explode(' ', $student['student_name'] ?? 'Student');
                    echo htmlspecialchars($name_parts[0]); 
                ?>!</h4>
                <p class="lead mb-0">
                    Use your QR code to mark attendance during class sessions.
                    Your attendance records are shown below.
                </p>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="fas fa-fingerprint me-1"></i> Student ID: <?php echo htmlspecialchars($student_id); ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Quick Stats Section - ADDED -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <i class="fas fa-calendar-check text-primary"></i>
                    <h3 id="todayAttendance"><?php echo getTodayAttendance($conn, $student_id); ?></h3>
                    <p>Today's Classes</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <i class="fas fa-percentage text-success"></i>
                    <h3 id="attendancePercent"><?php echo getAttendancePercentage($conn, $student_id); ?>%</h3>
                    <p>Overall Attendance</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <i class="fas fa-clock text-warning"></i>
                    <h3 id="lateCount"><?php echo getLateCount($conn, $student_id); ?></h3>
                    <p>Late Arrivals</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section - ADDED -->
        <div class="card shadow-lg border-0 mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-calendar-alt fa-2x text-primary me-3"></i>
                            <div>
                                <h6>View Timetable</h6>
                                <p class="text-muted mb-2">Check your class schedule</p>
                                <!-- TIMETABLE LINK ADDED HERE -->
                                <a href="timetable.php" class="timetable-btn">
                                    <i class="fas fa-calendar me-2"></i> View Timetable
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-chart-line fa-2x text-success me-3"></i>
                            <div>
                                <h6>Attendance Report</h6>
                                <p class="text-muted mb-2">View detailed attendance analytics</p>
                                <a href="attendance_viewer.php" class="btn btn-outline-success">
                                    <i class="fas fa-chart-bar me-1"></i> View Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance History -->
        <div class="card shadow-lg border-0">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Attendance</h5>
            </div>
            <div class="card-body">
                <?php if ($attendance_result && mysqli_num_rows($attendance_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Session Time</th>
                                    <th>Marked Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Reset pointer and fetch data
                                mysqli_data_seek($attendance_result, 0);
                                while ($record = mysqli_fetch_assoc($attendance_result)): 
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($record['marked_at'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($record['subject_code']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($record['subject_name']); ?></small>
                                    </td>
                                    <td><?php echo date('h:i A', strtotime($record['start_time'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($record['marked_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-success">Present</span>
                                        <?php
                                        $session_time = strtotime($record['start_time']);
                                        $marked_time = strtotime($record['marked_at']);
                                        if (($marked_time - $session_time) > 900) { // 15 minutes late
                                            echo '<span class="badge bg-warning ms-1">Late</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        
                        <!-- ONLY BUTTON ADDED AT BOTTOM -->
                        <div class="text-center mt-4">
                            <a href="attendance_viewer.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-chart-line me-2"></i> View Complete Attendance Report
                            </a>
                        </div>
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

<!-- QR Code Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// Generate QR code from database content
<?php if (!empty($student['qr_content'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Clear any existing QR code
    document.getElementById('qrcode').innerHTML = '';
    
    // Force QR code to use black on white regardless of theme
    var qrcode = new QRCode(document.getElementById("qrcode"), {
        text: "<?php echo addslashes($student['qr_content']); ?>",
        width: 200,
        height: 200,
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
    
    // Create a temporary white background if needed
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
    link.download = 'my_qr_code_<?php echo $student_id; ?>.png';
    link.href = tempCanvas.toDataURL("image/png");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
<?php endif; ?>
</script>

<?php 
include 'footer.php';

// ==================== HELPER FUNCTIONS ====================
function getTodayAttendance($conn, $student_id) {
    $today = date('Y-m-d');
    $query = "SELECT COUNT(*) as count FROM attendance_records 
              WHERE student_id = ? AND DATE(marked_at) = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $student_id, $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

function getAttendancePercentage($conn, $student_id) {
    // Get total sessions and attended sessions
    $query = "SELECT 
                (SELECT COUNT(DISTINCT session_id) FROM sessions WHERE active = 1) as total_sessions,
                (SELECT COUNT(DISTINCT session_id) FROM attendance_records WHERE student_id = ?) as attended_sessions";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    if ($row['total_sessions'] > 0) {
        $percentage = ($row['attended_sessions'] / $row['total_sessions']) * 100;
        return round($percentage);
    }
    return 0;
}

function getLateCount($conn, $student_id) {
    $query = "SELECT COUNT(*) as late_count FROM attendance_records ar
              JOIN sessions s ON ar.session_id = s.session_id
              WHERE ar.student_id = ? 
              AND TIMESTAMPDIFF(MINUTE, s.start_time, ar.marked_at) > 15";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['late_count'] ?? 0;
}

// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
