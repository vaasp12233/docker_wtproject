<?php
// student_dashboard.php - UI Only Version (No Database Queries)

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

// ==================== Security check (Keep minimal) ====================
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

// ==================== Get student ID from session only ====================
$student_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null;
$student_name = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : 'Student';

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
                            ?>
                            <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                                 class="profile-img rounded-circle" 
                                 alt="Profile Picture">
                        </div>
                        
                        <h4 class="card-title"><?php echo htmlspecialchars($student_name); ?></h4>
                        <div class="student-info text-start mt-3">
                            <!-- Static Data - Can be populated via JavaScript if needed -->
                            <p><i class="fas fa-id-card text-primary me-2"></i> 
                               <strong>Student ID:</strong> <span id="displayStudentId">STU001</span></p>
                            <p><i class="fas fa-fingerprint text-primary me-2"></i> 
                               <strong>ID:</strong> <?php echo htmlspecialchars($student_id); ?></p>
                            <p><i class="fas fa-envelope text-primary me-2"></i> 
                               <strong>Email:</strong> student@example.com</p>
                            <p><i class="fas fa-users text-primary me-2"></i> 
                               <strong>Section:</strong> A</p>
                            <p><i class="fas fa-building text-primary me-2"></i> 
                               <strong>Department:</strong> Computer Science</p>
                        </div>
                        
                        <div class="mt-4">
                            <a href="student_profile.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-edit me-2"></i> Edit Profile
                            </a>
                        </div>
                    </div>
                </div>

                <!-- QR Code Card (KEPT AS REQUESTED) -->
                <div class="card shadow-lg mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>My QR Code</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="qrcode-wrapper">
                            <!-- QR Code will be generated dynamically with session data -->
                            <div id="qrcode" class="mb-3"></div>
                            <p class="text-muted mb-3">
                                <small>Scan this QR code during class to mark attendance</small>
                            </p>
                            <div class="d-grid gap-2">
                                <button onclick="downloadQR()" class="btn btn-success">
                                    <i class="fas fa-download me-2"></i> Download QR Code
                                </button>
                            </div>
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
                                        $name_parts = explode(' ', $student_name);
                                        echo htmlspecialchars($name_parts[0]); 
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

                <!-- Quick Stats (Placeholder Data) -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-calendar-check text-primary"></i>
                            <h3 id="todayAttendance">0</h3>
                            <p>Today's Classes</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-percentage text-success"></i>
                            <h3 id="attendancePercent">0%</h3>
                            <p>Overall Attendance</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-clock text-warning"></i>
                            <h3 id="lateCount">0</h3>
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

                <!-- Attendance History (Placeholder) -->
                <div class="card shadow-lg">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Attendance Records</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">Attendance data will appear here</h5>
                            <p class="text-muted mb-4">Your attendance records will be displayed after marking attendance</p>
                            <a href="attendance_viewer.php" class="btn btn-primary">
                                <i class="fas fa-chart-line me-2"></i> View Attendance Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Script -->
<script>
// Generate QR code using session data
document.addEventListener('DOMContentLoaded', function() {
    // Clear any existing QR code
    const qrElement = document.getElementById('qrcode');
    if (qrElement) {
        qrElement.innerHTML = '';
        
        // Generate QR code with student ID or session data
        const qrContent = "student:<?php echo $student_id; ?>|time:" + Date.now();
        
        // Generate new QR code
        var qrcode = new QRCode(qrElement, {
            text: qrContent,
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    }
    
    // Simulate loading statistics (can be replaced with AJAX later)
    setTimeout(() => {
        document.getElementById('todayAttendance').textContent = '0';
        document.getElementById('attendancePercent').textContent = '0%';
        document.getElementById('lateCount').textContent = '0';
    }, 500);
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

// Dark/Light Mode Toggle (if not in header)
document.addEventListener('DOMContentLoaded', function() {
    const modeToggle = document.getElementById('modeToggle');
    const modeIcon = document.getElementById('modeIcon');
    const body = document.body;
    
    if (modeToggle && modeIcon) {
        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            modeIcon.classList.remove('fa-moon');
            modeIcon.classList.add('fa-sun');
        }
        
        // Toggle theme
        modeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                modeIcon.classList.remove('fa-moon');
                modeIcon.classList.add('fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                modeIcon.classList.remove('fa-sun');
                modeIcon.classList.add('fa-moon');
                localStorage.setItem('theme', 'light');
            }
        });
    }
});
</script>

<?php 
// Include footer
include 'footer.php';

// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
