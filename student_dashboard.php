<?php
// student_dashboard.php - Fixed with all your requirements

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
    @session_start();
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
$today_attendance = 0;
$attendance_percent = 0;
$late_count = 0;
$error = '';
$success = '';

// Get student details
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

// Get student's attendance (only if student exists)
if ($student) {
    // Get recent attendance records
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
    
    // GET REAL STATISTICS FROM DATABASE
    
    // 1. Get today's attendance count
    $today = date('Y-m-d');
    $today_query = "SELECT COUNT(*) as count FROM attendance_records 
                    WHERE student_id = ? AND DATE(marked_at) = ?";
    $stmt3 = mysqli_prepare($conn, $today_query);
    mysqli_stmt_bind_param($stmt3, "ss", $student_id, $today);
    mysqli_stmt_execute($stmt3);
    $today_result = mysqli_stmt_get_result($stmt3);
    $today_row = mysqli_fetch_assoc($today_result);
    $today_attendance = $today_row['count'] ?? 0;
    mysqli_stmt_close($stmt3);
    
    // 2. Get overall attendance percentage
    // First get total sessions available
    $total_sessions_query = "SELECT COUNT(*) as total FROM sessions WHERE active = 1";
    $total_result = mysqli_query($conn, $total_sessions_query);
    $total_row = mysqli_fetch_assoc($total_result);
    $total_sessions = $total_row['total'] ?? 0;
    
    // Then get attended sessions
    $attended_query = "SELECT COUNT(DISTINCT session_id) as attended 
                       FROM attendance_records WHERE student_id = ?";
    $stmt4 = mysqli_prepare($conn, $attended_query);
    mysqli_stmt_bind_param($stmt4, "s", $student_id);
    mysqli_stmt_execute($stmt4);
    $attended_result = mysqli_stmt_get_result($stmt4);
    $attended_row = mysqli_fetch_assoc($attended_result);
    $attended_sessions = $attended_row['attended'] ?? 0;
    mysqli_stmt_close($stmt4);
    
    // Calculate percentage
    if ($total_sessions > 0) {
        $attendance_percent = round(($attended_sessions / $total_sessions) * 100);
    }
    
    // 3. Get late arrival count (more than 15 minutes late)
    $late_query = "SELECT COUNT(*) as late_count FROM attendance_records ar
                   JOIN sessions s ON ar.session_id = s.session_id
                   WHERE ar.student_id = ? 
                   AND TIMESTAMPDIFF(MINUTE, s.start_time, ar.marked_at) > 15";
    $stmt5 = mysqli_prepare($conn, $late_query);
    mysqli_stmt_bind_param($stmt5, "s", $student_id);
    mysqli_stmt_execute($stmt5);
    $late_result = mysqli_stmt_get_result($stmt5);
    $late_row = mysqli_fetch_assoc($late_result);
    $late_count = $late_row['late_count'] ?? 0;
    mysqli_stmt_close($stmt5);
}

// Set page title
$page_title = "Student Dashboard";

// Include header
include 'header.php';
?>

<!-- Custom Styles for Dashboard -->
<style>
    /* QR Code Specific Styles */
    .qrcode-wrapper {
        text-align: center;
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin: 0 auto;
    }
    
    .dark-mode .qrcode-wrapper {
        background: white !important;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
    #qrcode {
        padding: 10px;
        background: white;
        border-radius: 8px;
        display: inline-block;
        margin: 0 auto;
    }
    
    #qrcode canvas {
        display: block;
        margin: 0 auto;
    }
    
    /* Dark mode overrides for QR code */
    body.dark-mode #qrcode {
        background: white !important;
    }
    
    /* Card Styling */
    .card {
        border: none;
        border-radius: 15px;
        overflow: hidden;
        transition: transform 0.3s ease, background-color 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    
    .dark-mode .card {
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
    .card:hover {
        transform: translateY(-5px);
    }
    
    .profile-img {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
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
    
    .action-card {
        text-align: center;
        padding: 20px;
        border-radius: 10px;
        background: white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        height: 100%;
    }
    
    .dark-mode .action-card {
        background: #1e1e1e;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }
    
    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    }
    
    .action-card i {
        font-size: 2.5rem;
        margin-bottom: 15px;
        color: #4361ee;
    }
</style>

<div class="container-fluid py-4">
    <div class="container">
        <!-- Error/Success Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Left Column: Profile & QR Code -->
            <div class="col-lg-4 col-md-12 mb-4">
                <!-- Profile Card -->
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>My Profile</h5>
                    </div>
                    <div class="card-body text-center">
                        <!-- Profile Picture -->
                        <div class="mb-4">
                            <?php
                            $profile_pic = 'uploads/profiles/default.png';
                            if (isset($student['profile_pic']) && !empty($student['profile_pic']) && file_exists($student['profile_pic'])) {
                                $profile_pic = $student['profile_pic'];
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                                 class="profile-img rounded-circle" 
                                 alt="Profile Picture">
                        </div>
                        
                        <?php if ($student): ?>
                            <h4 class="card-title"><?php echo htmlspecialchars($student['student_name']); ?></h4>
                            <div class="student-info text-start mt-3">
                                <!-- FIXED: Student ID and ID are now correct -->
                                <p><i class="fas fa-id-card text-primary me-2"></i> 
                                   <strong>Student ID:</strong> <?php echo htmlspecialchars($student['id_number'] ?? 'N/A'); ?></p>
                                <p><i class="fas fa-fingerprint text-primary me-2"></i> 
                                   <strong>ID:</strong> <?php echo htmlspecialchars($student_id); ?></p>
                                <p><i class="fas fa-envelope text-primary me-2"></i> 
                                   <strong>Email:</strong> <?php echo htmlspecialchars($student['student_email']); ?></p>
                                <p><i class="fas fa-users text-primary me-2"></i> 
                                   <strong>Section:</strong> <?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></p>
                                <p><i class="fas fa-building text-primary me-2"></i> 
                                   <strong>Department:</strong> <?php echo htmlspecialchars($student['student_department'] ?? 'N/A'); ?></p>
                            </div>
                            
                            <div class="mt-4">
                                <a href="student_profile.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-edit me-2"></i> Edit Profile
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Student profile not found
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- QR Code Card (KEPT AS REQUESTED) -->
                <div class="card shadow-lg mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>My QR Code</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="qrcode-wrapper">
                            <?php if ($student && !empty($student['qr_content'])): ?>
                                <div id="qrcode" class="mb-3"></div>
                                <p class="text-muted mb-3">
                                    <small>Scan this QR code during class to mark attendance</small>
                                </p>
                                <div class="d-grid gap-2">
                                    <button onclick="downloadQR()" class="btn btn-success">
                                        <i class="fas fa-download me-2"></i> Download QR Code
                                    </button>
                                </div>
                            <?php elseif ($student): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <p class="mb-0">QR Code not generated yet.</p>
                                    <small>Contact your department administrator to generate your QR code.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Dashboard Content -->
            <div class="col-lg-8 col-md-12">
                <!-- Welcome Card -->
                <div class="card shadow-lg welcome-card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-2">
                                    Welcome back, 
                                    <?php 
                                        if ($student) {
                                            $name_parts = explode(' ', $student['student_name']);
                                            echo htmlspecialchars($name_parts[0]); 
                                        } else {
                                            echo 'Student';
                                        }
                                    ?>!
                                </h4>
                                <p class="mb-0">Use your QR code to mark attendance during class sessions.</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-user-graduate fa-4x text-white opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats with REAL DATA -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-calendar-check text-primary"></i>
                            <h3 id="todayAttendance"><?php echo $today_attendance; ?></h3>
                            <p>Today's Classes</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-percentage text-success"></i>
                            <h3 id="attendancePercent"><?php echo $attendance_percent; ?>%</h3>
                            <p>Overall Attendance</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-clock text-warning"></i>
                            <h3 id="lateCount"><?php echo $late_count; ?></h3>
                            <p>Late Arrivals</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Section (REMOVED SCANNER & DOCUMENTS, ADDED TIMETABLE LINK) -->
                <div class="row mb-4 quick-actions">
                    <div class="col-12 mb-3">
                        <h5 class="mb-3"><i class="fas fa-bolt text-warning me-2"></i>Quick Actions</h5>
                    </div>
                    <!-- Attendance Report -->
                    <div class="col-md-4 col-6 mb-3">
                        <div class="action-card">
                            <i class="fas fa-chart-bar"></i>
                            <h5>Reports</h5>
                            <p>View attendance analytics</p>
                            <a href="attendance_viewer.php" class="btn btn-sm btn-success">
                                View Report
                            </a>
                        </div>
                    </div>
                    <!-- Timetable (ADDED PROPER LINK) -->
                    <div class="col-md-4 col-6 mb-3">
                        <div class="action-card">
                            <i class="fas fa-calendar-alt"></i>
                            <h5>Timetable</h5>
                            <p>View class schedule</p>
                            <!-- FIXED: Now links directly to timetable.php -->
                            <a href="timetable.php" class="btn btn-sm btn-info">
                                View Schedule
                            </a>
                        </div>
                    </div>
                    <!-- Materials (REPLACED DOCUMENTS) -->
                    <div class="col-md-4 col-6 mb-3">
                        <div class="action-card">
                            <i class="fas fa-book"></i>
                            <h5>Materials</h5>
                            <p>Access course materials</p>
                            <a href="materials.php" class="btn btn-sm btn-warning">
                                Access
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Attendance History -->
                <div class="card shadow-lg">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Attendance Records</h5>
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
                                        $attendance_result->data_seek(0); // Reset pointer
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
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="attendance_viewer.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-chart-line me-2"></i> View Complete Attendance Report
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No attendance records found</h5>
                                <p class="text-muted mb-4">Your attendance will appear here after scanning QR codes in class</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Script -->
<script>
// Generate QR code
<?php if ($student && !empty($student['qr_content'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Clear any existing QR code
    const qrElement = document.getElementById('qrcode');
    if (qrElement) {
        qrElement.innerHTML = '';
        
        // Generate new QR code
        var qrcode = new QRCode(qrElement, {
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
        alert('QR code not found!');
        return;
    }
    
    // Create temporary canvas with white background
    var tempCanvas = document.createElement('canvas');
    var ctx = tempCanvas.getContext('2d');
    tempCanvas.width = canvas.width + 40;
    tempCanvas.height = canvas.height + 100;
    
    // White background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
    
    // Draw QR code centered
    ctx.drawImage(canvas, 20, 20);
    
    // Add text below QR code
    ctx.fillStyle = '#000000';
    ctx.font = '14px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('Student QR Code', tempCanvas.width/2, canvas.height + 50);
    ctx.font = '12px Arial';
    ctx.fillText('Scan to mark attendance', tempCanvas.width/2, canvas.height + 70);
    
    // Create download link
    var link = document.createElement('a');
    link.download = 'Student_QR_Code_<?php echo $student_id; ?>.png';
    link.href = tempCanvas.toDataURL("image/png");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
<?php endif; ?>
</script>

<?php 
// Include footer
include 'footer.php';

// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
