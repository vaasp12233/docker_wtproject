<?php
require_once 'config.php';

// Security check
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

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

// Get QR code path
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
                    <img src="<?php echo $profile_pic; ?>" 
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
                    <i class="fas fa-id-card me-1"></i> ID: <?php echo $student['id_number']; ?><br>
                    <i class="fas fa-users me-1"></i> Section <?php echo $student['section']; ?><br>
                    <i class="fas fa-building me-1"></i> <?php echo $student['student_department']; ?>
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