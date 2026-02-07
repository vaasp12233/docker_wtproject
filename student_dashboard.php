<?php
require_once 'config.php';

// Security check - FIXED for Render
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    // Check if this is an AJAX request to avoid redirect loops
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['error' => 'Session expired']);
        exit;
    }
    
    // Store current URL for redirect back after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Use absolute URL for redirect on Render
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $base_url = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    header('Location: ' . $base_url . '/login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Check if gender is set
$check_gender = mysqli_query($conn, "SELECT gender FROM students WHERE student_id = '$student_id'");
if (!$check_gender) {
    // Log error and handle gracefully
    error_log("Database error in gender check: " . mysqli_error($conn));
    $gender_set = false;
} else {
    $student_data = mysqli_fetch_assoc($check_gender);
    $gender_set = !empty($student_data['gender']);
}

// If gender is not set, redirect to set_gender.php
if (!$gender_set) {
    // Use absolute URL for redirect on Render
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $base_url = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    header('Location: ' . $base_url . '/set_gender.php');
    exit;
}

// Get student details
$student_query = "SELECT * FROM students WHERE student_id = '$student_id'";
$result = mysqli_query($conn, $student_query);
if (!$result) {
    error_log("Database error: " . mysqli_error($conn));
    die("Error fetching student data");
}
$student = mysqli_fetch_assoc($result);

// Get student's recent attendance
$attendance_query = "SELECT ar.*, s.subject_code, s.subject_name, ses.session_id, ses.start_time
                     FROM attendance_records ar 
                     JOIN sessions ses ON ar.session_id = ses.session_id
                     JOIN subjects s ON ses.subject_id = s.subject_id
                     WHERE ar.student_id = '$student_id' 
                     ORDER BY ar.marked_at DESC LIMIT 10";
$attendance_result = mysqli_query($conn, $attendance_query);

// Get attendance statistics with error handling
$stats_query = "SELECT 
    COUNT(DISTINCT ar.session_id) as total_attended,
    (SELECT COUNT(DISTINCT session_id) FROM sessions WHERE active = 1) as total_sessions,
    (SELECT COUNT(*) FROM attendance_records WHERE student_id = '$student_id') as total_records
    FROM attendance_records ar 
    WHERE ar.student_id = '$student_id'";
$stats_result = mysqli_query($conn, $stats_query);

if ($stats_result) {
    $stats = mysqli_fetch_assoc($stats_result);
    // Calculate attendance percentage
    $attendance_percentage = 0;
    if ($stats && $stats['total_sessions'] > 0) {
        $attendance_percentage = round(($stats['total_attended'] / $stats['total_sessions']) * 100, 1);
    }
} else {
    $stats = ['total_attended' => 0, 'total_sessions' => 0, 'total_records' => 0];
    $attendance_percentage = 0;
    error_log("Stats query error: " . mysqli_error($conn));
}

// Get timetable for today
$today = date('l'); // Get current day name
$timetable_query = "SELECT s.subject_code, s.subject_name, ses.day_of_week, ses.start_time, ses.end_time
                    FROM sessions ses
                    JOIN subjects s ON ses.subject_id = s.subject_id
                    JOIN student_subjects ss ON s.subject_id = ss.subject_id
                    WHERE ss.student_id = '$student_id' 
                    AND ses.active = 1
                    AND ses.day_of_week = '$today'
                    ORDER BY ses.start_time";
$timetable_result = mysqli_query($conn, $timetable_query);

$qr_path = "qrcodes/student_" . $student_id . ".png";

$page_title = "Student Dashboard";
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
    
    /* Attendance percentage circle */
    .attendance-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: conic-gradient(var(--bs-success) <?php echo $attendance_percentage * 3.6; ?>deg, #e9ecef 0deg);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        position: relative;
    }
    
    .attendance-circle::before {
        content: '';
        position: absolute;
        width: 90px;
        height: 90px;
        background-color: white;
        border-radius: 50%;
    }
    
    .attendance-percentage {
        position: relative;
        z-index: 1;
        font-size: 2rem;
        font-weight: bold;
        color: #198754;
    }
    
    /* Render compatibility fixes */
    .card {
        min-height: 1px; /* Prevent layout shifts */
    }
    
    /* Responsive adjustments for Render */
    @media (max-width: 768px) {
        .attendance-circle {
            width: 100px;
            height: 100px;
        }
        
        .attendance-circle::before {
            width: 70px;
            height: 70px;
        }
        
        .attendance-percentage {
            font-size: 1.5rem;
        }
    }
</style>

<div class="row">
    <!-- Left Column: Profile & QR Code -->
    <div class="col-md-4 mb-4">
        <!-- Profile Card -->
        <div class="card shadow-lg border-0 h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>My Profile</h5>
            </div>
            <div class="card-body text-center">
                <!-- Profile Picture - Always use default based on gender -->
                <div class="mb-3">
                    <?php
                    // Always use default profile based on gender
                    $gender = $student['gender'] ?? 'male';
                    $profile_pic = 'uploads/profiles/default_' . $gender . '.png';
                    
                    // Check if file exists, if not use generic default
                    if (!file_exists($profile_pic)) {
                        $profile_pic = 'uploads/profiles/default.png';
                    }
                    
                    // Use absolute URL for images on Render
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                    $base_url = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                    $full_profile_pic = $base_url . '/' . $profile_pic;
                    ?>
                    <img src="<?php echo $full_profile_pic; ?>" 
                         class="rounded-circle border" 
                         style="width: 150px; height: 150px; object-fit: cover;"
                         alt="Profile Picture"
                         onerror="this.src='https://via.placeholder.com/150/007bff/ffffff?text=<?php echo urlencode(substr($student['student_name'], 0, 1)); ?>'">
                    <div class="mt-2">
                        <button class="btn btn-sm btn-secondary" disabled>
                            <i class="fas fa-ban me-1"></i> Photo not editable
                        </button>
                    </div>
                </div>
                
                <h5 class="card-title"><?php echo htmlspecialchars($student['student_name']); ?></h5>
                <p class="card-text text-muted">
                    <i class="fas fa-fingerprint me-1"></i> Student ID: <?php echo htmlspecialchars($student_id); ?><br>
                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($student['student_email']); ?><br>
                    <i class="fas fa-id-card me-1"></i> ID: <?php echo htmlspecialchars($student['id_number']); ?><br>
                    <i class="fas fa-users me-1"></i> Section <?php echo htmlspecialchars($student['section']); ?><br>
                    <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($student['student_department']); ?><br>
                    <i class="fas fa-venus-mars me-1"></i> <?php echo ucfirst($student['gender'] ?? 'Not set'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Middle Column: QR Code -->
    <div class="col-md-3 mb-4">
        <div class="card shadow-lg border-0 h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>My QR Code</h5>
            </div>
            <div class="card-body text-center qrcode-wrapper d-flex flex-column">
                <?php if (!empty($student['qr_content'])): ?>
                    <!-- Wrapper div for QR code with forced light theme -->
                    <div class="qrcode-container d-inline-block">
                        <div id="qrcode"></div>
                    </div>
                    <p class="mt-2 small text-muted">
                        Scan this QR code during class to mark attendance
                    </p>
                    <div class="mt-auto">
                        <button onclick="downloadQR()" class="btn btn-success btn-sm w-100">
                            <i class="fas fa-download me-1"></i> Download QR Code
                        </button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning m-auto">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        QR Code not generated yet. Contact administrator.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Main Content -->
    <div class="col-md-5">
        <!-- Welcome Card with Attendance Stats -->
        <div class="card shadow-lg border-0 mb-4">
            <div class="card-body">
                <h4 class="text-primary">Welcome, <?php 
                    $name_parts = explode(' ', $student['student_name']);
                    echo htmlspecialchars($name_parts[0]); 
                ?>!</h4>
                <p class="lead mb-3">
                    Use your QR code to mark attendance during class sessions.
                </p>
                
                <!-- Attendance Stats -->
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="attendance-circle mb-3">
                            <div class="attendance-percentage">
                                <?php echo $attendance_percentage; ?>%
                            </div>
                        </div>
                        <p class="text-center text-muted small">
                            Total: <?php echo $stats['total_attended']; ?>/<?php echo $stats['total_sessions']; ?> sessions
                        </p>
                    </div>
                    <div class="col-md-6">
                        <div class="d-grid gap-2">
                            <a href="attendance_viewer.php" class="btn btn-primary">
                                <i class="fas fa-chart-line me-1"></i> View Analysis
                            </a>
                            <a href="time_table.php" class="btn btn-info">
                                <i class="fas fa-calendar-alt me-1"></i> Time Table
                            </a>
                            <a href="attendance_report.php" class="btn btn-success">
                                <i class="fas fa-file-alt me-1"></i> Full Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Classes -->
        <div class="card shadow-lg border-0 mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Today's Classes (<?php echo $today; ?>)</h5>
            </div>
            <div class="card-body">
                <?php if ($timetable_result && mysqli_num_rows($timetable_result) > 0): ?>
                    <div class="list-group">
                        <?php while ($class = mysqli_fetch_assoc($timetable_result)): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($class['subject_name']); ?></h6>
                                <small><?php echo date('h:i A', strtotime($class['start_time'])); ?> - <?php echo date('h:i A', strtotime($class['end_time'])); ?></small>
                            </div>
                            <p class="mb-1">
                                <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($class['subject_code']); ?></span>
                            </p>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="text-end mt-2">
                        <a href="time_table.php" class="btn btn-sm btn-outline-warning">
                            <i class="fas fa-calendar me-1"></i> Full Timetable
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No classes scheduled for today</p>
                        <a href="time_table.php" class="btn btn-sm btn-outline-warning mt-2">
                            View Full Timetable
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Attendance -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Attendance Records</h5>
            </div>
            <div class="card-body">
                <?php if ($attendance_result && mysqli_num_rows($attendance_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($record = mysqli_fetch_assoc($attendance_result)): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($record['marked_at'])); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($record['subject_code']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($record['subject_name']); ?></small>
                                    </td>
                                    <td><?php echo date('h:i A', strtotime($record['marked_at'])); ?></td>
                                    <td><span class="badge bg-success">Present</span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="attendance_viewer.php" class="btn btn-primary">
                            <i class="fas fa-chart-bar me-2"></i> View Detailed Analysis
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No attendance records yet</h5>
                        <p class="text-muted">Your attendance will appear here after scanning QR codes in class</p>
                        <div class="mt-3">
                            <a href="how_to_scan.php" class="btn btn-outline-info">
                                <i class="fas fa-question-circle me-2"></i> How to Scan QR Code
                            </a>
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
    // Check if QR code container exists
    var qrContainer = document.getElementById("qrcode");
    if (!qrContainer) {
        console.error("QR code container not found");
        return;
    }
    
    // Clear any existing content
    qrContainer.innerHTML = '';
    
    // Force QR code to use black on white regardless of theme
    var qrcode = new QRCode(qrContainer, {
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
        alert('QR code not found or not loaded yet!');
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
    link.download = 'qr_code_<?php echo $student_id; ?>_' + Date.now() + '.png';
    link.href = tempCanvas.toDataURL("image/png");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
<?php endif; ?>

// Render compatibility: Handle session expiration
document.addEventListener('DOMContentLoaded', function() {
    // Check for session issues
    if (window.location.href.indexOf('login.php') > -1 && window.location.href !== window.location.origin + '/login.php') {
        window.location.href = '/login.php';
    }
    
    // Add error handling for broken images
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('error', function() {
            var firstLetter = this.alt ? this.alt.charAt(0) : 'U';
            this.src = 'https://via.placeholder.com/150/6c757d/ffffff?text=' + firstLetter;
        });
    });
});
</script>

<?php include 'footer.php'; ?>
