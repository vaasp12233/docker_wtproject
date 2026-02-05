<?php
// student_dashboard.php - Fixed for Render + Aiven

// ==================== CRITICAL: Start output buffering ====================
// This prevents "headers already sent" errors
if (!ob_get_level()) {
    ob_start();
}

// ==================== Configure session for Render ====================
ini_set('session.save_path', '/tmp');
ini_set('session.cookie_lifetime', 86400);

// ==================== Start session ====================
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ==================== Include database config ====================
require_once 'config.php';

// ==================== Security check ====================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Clean buffer before redirect
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Role check ====================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    // Clean buffer before redirect
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Get student ID ====================
$student_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null;
if (!$student_id) {
    // Clean buffer before redirect
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Clean buffer before output ====================
// Keep buffer for HTML output, but clean any stray output
if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

// ==================== Database operations ====================
// Initialize variables
$student = null;
$attendance_result = null;
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

// Set page title
$page_title = "Student Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
        }
        
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        /* QR Code Styling */
        #qrcode {
            padding: 15px;
            background: white;
            border-radius: 10px;
            display: inline-block;
            margin: 0 auto;
        }
        
        #qrcode canvas {
            display: block;
            margin: 0 auto;
        }
        
        .qrcode-wrapper {
            text-align: center;
        }
        
        /* Dark mode overrides for QR code */
        body.dark-mode #qrcode,
        body.dark-mode .qrcode-wrapper {
            background: white !important;
        }
        
        .badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .btn {
            border-radius: 10px;
            font-weight: 500;
            padding: 10px 20px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            border: none;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #4cc9f0 0%, #4361ee 100%);
            border: none;
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border: none;
            padding: 15px;
        }
        
        .table td {
            padding: 15px;
            vertical-align: middle;
            border-color: #f1f3f4;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Alert styling */
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .welcome-card h4 {
            font-weight: 700;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
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
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
        <div class="container">
            <a class="navbar-brand" href="student_dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                CSE Attendance - Student Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="student_dashboard.php">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendance_viewer.php">
                            <i class="fas fa-chart-line me-1"></i> Attendance Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_profile.php">
                            <i class="fas fa-user me-1"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
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
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
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
                                    <p><i class="fas fa-fingerprint text-primary me-2"></i> 
                                       <strong>ID:</strong> <?php echo htmlspecialchars($student_id); ?></p>
                                    <p><i class="fas fa-envelope text-primary me-2"></i> 
                                       <strong>Email:</strong> <?php echo htmlspecialchars($student['student_email']); ?></p>
                                    <p><i class="fas fa-id-card text-primary me-2"></i> 
                                       <strong>Student ID:</strong> <?php echo htmlspecialchars($student['id_number'] ?? 'N/A'); ?></p>
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

                    <!-- QR Code Card -->
                    <div class="card shadow-lg mt-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>My QR Code</h5>
                        </div>
                        <div class="card-body text-center qrcode-wrapper">
                            <?php if ($student && !empty($student['qr_content'])): ?>
                                <div id="qrcode" class="mb-3"></div>
                                <p class="text-muted mb-3">
                                    <small>Scan this QR code during class to mark attendance</small>
                                </p>
                                <div class="d-grid gap-2">
                                    <button onclick="downloadQR()" class="btn btn-success">
                                        <i class="fas fa-download me-2"></i> Download QR Code
                                    </button>
                                    <button onclick="shareQR()" class="btn btn-outline-success">
                                        <i class="fas fa-share-alt me-2"></i> Share QR Code
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

                    <!-- Quick Stats -->
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
                                    <a href="how_to_use.php" class="btn btn-outline-primary">
                                        <i class="fas fa-question-circle me-2"></i> How to use QR Code
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-5 py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h6>CSE Attendance System</h6>
                    <p class="mb-0 small">Department of Computer Science</p>
                    <p class="mb-0 small">QR Code Based Attendance Management</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 small">&copy; <?php echo date('Y'); ?> CSE Department</p>
                    <p class="mb-0 small">Student ID: <?php echo htmlspecialchars($student_id); ?></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Generate QR code
    <?php if ($student && !empty($student['qr_content'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Clear any existing QR code
        document.getElementById('qrcode').innerHTML = '';
        
        // Generate new QR code
        var qrcode = new QRCode(document.getElementById("qrcode"), {
            text: "<?php echo addslashes($student['qr_content']); ?>",
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
        
        // Update stats (example - replace with real data)
        updateStats();
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
        tempCanvas.width = canvas.width + 40; // Add padding
        tempCanvas.height = canvas.height + 100; // Add space for text
        
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
    
    function shareQR() {
        if (navigator.share) {
            navigator.share({
                title: 'My Attendance QR Code',
                text: 'Scan this QR code to mark my attendance',
                url: window.location.href
            }).catch(console.error);
        } else {
            alert('Share feature is not supported in your browser. Download the QR code instead.');
        }
    }
    <?php endif; ?>
    
    function updateStats() {
        // Example stats - replace with actual API calls
        document.getElementById('todayAttendance').textContent = '3';
        document.getElementById('attendancePercent').textContent = '92%';
        document.getElementById('lateCount').textContent = '1';
    }
    
    // Auto-refresh attendance every 30 seconds if on dashboard
    setInterval(function() {
        // You can add AJAX call here to refresh attendance data
        console.log('Auto-refresh triggered');
    }, 30000);
    </script>
</body>
</html>
<?php
// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
