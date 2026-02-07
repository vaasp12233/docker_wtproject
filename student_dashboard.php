<?php
require_once 'config.php';

// NEW: Check if gender is set, if not redirect to set_gender.php
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// NEW: Check if gender is set
$check_gender = mysqli_query($conn, "SELECT gender FROM students WHERE student_id = '$student_id'");
$student_data = mysqli_fetch_assoc($check_gender);

if (empty($student_data['gender'])) {
    header('Location: set_gender.php');
    exit;
}

// Get student details
$student_query = "SELECT * FROM students WHERE student_id = '$student_id'";
$result = mysqli_query($conn, $student_query);
$student = mysqli_fetch_assoc($result);

// Get student's attendance
$attendance_query = "SELECT ar.*, s.subject_code, s.subject_name, ses.session_id, ses.start_time
                     FROM attendance_records ar 
                     JOIN sessions ses ON ar.session_id = ses.session_id
                     JOIN subjects s ON ses.subject_id = s.subject_id
                     WHERE ar.student_id = '$student_id' 
                     ORDER BY ar.marked_at DESC LIMIT 10";
$attendance_result = mysqli_query($conn, $attendance_query);

// NEW: Get attendance statistics
$stats_query = "SELECT 
    COUNT(DISTINCT ar.session_id) as total_attended,
    (SELECT COUNT(DISTINCT session_id) FROM sessions WHERE active = 1) as total_sessions,
    (SELECT COUNT(*) FROM attendance_records WHERE student_id = '$student_id') as total_records
    FROM attendance_records ar 
    WHERE ar.student_id = '$student_id'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Calculate attendance percentage
$attendance_percentage = 0;
if ($stats['total_sessions'] > 0) {
    $attendance_percentage = round(($stats['total_attended'] / $stats['total_sessions']) * 100, 1);
}

// NEW: Get recent sessions for timetable
$timetable_query = "SELECT s.subject_code, s.subject_name, ses.day_of_week, ses.start_time, ses.end_time
                    FROM sessions ses
                    JOIN subjects s ON ses.subject_id = s.subject_id
                    JOIN student_subjects ss ON s.subject_id = ss.subject_id
                    WHERE ss.student_id = '$student_id' AND ses.active = 1
                    ORDER BY FIELD(ses.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), 
                    ses.start_time";
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
    
    /* NEW: Attendance percentage circle */
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
</style>

<div class="row">
    <!-- Left Column: Profile & QR Code -->
    <div class="col-md-4 mb-4">
        <!-- Profile Card - MODIFIED: Made uneditable -->
        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>My Profile</h5>
            </div>
            <div class="card-body text-center">
                <!-- Profile Picture - MODIFIED: Always use default based on gender -->
                <div class="mb-3">
                    <?php
                    // MODIFIED: Always use default profile based on gender
                    $gender = $student['gender'] ?? 'male';
                    $profile_pic = 'uploads/profiles/default_' . $gender . '.png';
                    
                    // Check if file exists, if not use generic default
                    if (!file_exists($profile_pic)) {
                        $profile_pic = 'uploads/profiles/default.png';
                    }
                    ?>
                    <img src="<?php echo $profile_pic; ?>" 
                         class="rounded-circle border" 
                         style="width: 150px; height: 150px; object-fit: cover;"
                         alt="Profile Picture">
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
                    <i class="fas fa-id-card me-1"></i> ID: <?php echo $student['id_number']; ?><br>
                    <i class="fas fa-users me-1"></i> Section <?php echo $student['section']; ?><br>
                    <i class="fas fa-building me-1"></i> <?php echo $student['student_department']; ?><br>
                    <i class="fas fa-venus-mars me-1"></i> <?php echo ucfirst($student['gender'] ?? 'Not set'); ?>
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
        <!-- Welcome Card with Attendance Stats -->
        <div class="card shadow-lg border-0 mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="text-primary">Welcome, <?php 
                            $name_parts = explode(' ', $student['student_name']);
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
                    <div class="col-md-6 text-center">
                        <!-- NEW: Attendance Percentage Display -->
                        <div class="attendance-circle mb-2">
                            <div class="attendance-percentage">
                                <?php echo $attendance_percentage; ?>%
                            </div>
                        </div>
                        <p class="text-muted mb-0">
                            Total Attendance: <?php echo $stats['total_attended']; ?> / <?php echo $stats['total_sessions']; ?> sessions
                        </p>
                        <div class="mt-3">
                            <!-- NEW: Action Buttons -->
                            <a href="attendance_viewer.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-chart-line me-1"></i> View Analysis
                            </a>
                            <a href="time_table.php" class="btn btn-info btn-sm">
                                <i class="fas fa-calendar-alt me-1"></i> Time Table
                            </a>
                            <a href="attendance_report.php" class="btn btn-success btn-sm">
                                <i class="fas fa-file-alt me-1"></i> Full Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW: Timetable Preview -->
        <div class="card shadow-lg border-0 mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Today's Classes</h5>
            </div>
            <div class="card-body">
                <?php
                $today = date('l'); // Get current day name
                $has_classes = false;
                ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Subject</th>
                                <th>Code</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($timetable_result) > 0) {
                                mysqli_data_seek($timetable_result, 0); // Reset pointer
                                while ($class = mysqli_fetch_assoc($timetable_result)) {
                                    if ($class['day_of_week'] == $today) {
                                        $has_classes = true;
                                        echo '<tr>';
                                        echo '<td>' . date('h:i A', strtotime($class['start_time'])) . ' - ' . date('h:i A', strtotime($class['end_time'])) . '</td>';
                                        echo '<td>' . htmlspecialchars($class['subject_name']) . '</td>';
                                        echo '<td><span class="badge bg-warning text-dark">' . htmlspecialchars($class['subject_code']) . '</span></td>';
                                        echo '</tr>';
                                    }
                                }
                                
                                if (!$has_classes) {
                                    echo '<tr><td colspan="3" class="text-center text-muted py-3">No classes scheduled for today</td></tr>';
                                }
                            } else {
                                echo '<tr><td colspan="3" class="text-center text-muted py-3">No timetable available</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end">
                    <a href="time_table.php" class="btn btn-sm btn-outline-warning">
                        <i class="fas fa-calendar me-1"></i> View Full Timetable
                    </a>
                </div>
            </div>
        </div>

        <!-- Attendance History -->
        <div class="card shadow-lg border-0">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Attendance</h5>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($attendance_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Marked Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($record = mysqli_fetch_assoc($attendance_result)): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($record['marked_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['subject_code']); ?></td>
                                    <td><?php echo date('h:i A', strtotime($record['marked_at'])); ?></td>
                                    <td><span class="badge bg-success">Present</span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        
                        <!-- Action Buttons at Bottom -->
                        <div class="text-center mt-4">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <a href="attendance_viewer.php" class="btn btn-primary w-100">
                                        <i class="fas fa-chart-line me-2"></i> View Analysis
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <a href="time_table.php" class="btn btn-info w-100">
                                        <i class="fas fa-calendar-alt me-2"></i> Time Table
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <a href="attendance_report.php" class="btn btn-success w-100">
                                        <i class="fas fa-file-alt me-2"></i> Full Report
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No attendance records yet</h5>
                        <p class="text-muted">Your attendance will appear here after scanning QR codes in class</p>
                        
                        <!-- Action Buttons even when no records -->
                        <div class="mt-4">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <a href="time_table.php" class="btn btn-info w-100">
                                        <i class="fas fa-calendar-alt me-2"></i> View Timetable
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <a href="how_to_scan.php" class="btn btn-warning w-100">
                                        <i class="fas fa-qrcode me-2"></i> How to Scan
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <a href="faq.php" class="btn btn-secondary w-100">
                                        <i class="fas fa-question-circle me-2"></i> FAQ
                                    </a>
                                </div>
                            </div>
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
    // Force QR code to use black on white regardless of theme
    var qrcode = new QRCode(document.getElementById("qrcode"), {
        text: "<?php echo $student['qr_content']; ?>",
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
    link.download = 'my_qr_code_' + Date.now() + '.png';
    link.href = tempCanvas.toDataURL("image/png");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
<?php endif; ?>
</script>

<?php include 'footer.php'; ?>
