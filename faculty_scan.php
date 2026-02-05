<?php
// faculty_scan.php - Fixed for Render + Aiven

// ==================== CRITICAL: Start output buffering ====================
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

// ==================== Handle manual attendance marking ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $student_id = trim($_POST['student_id'] ?? '');
    $current_session_id = intval($_POST['session_id'] ?? 0);
    
    if (!empty($student_id) && $current_session_id > 0) {
        // Check if student exists and is in correct section
        $check_student = mysqli_prepare($conn, 
            "SELECT student_id FROM students 
             WHERE student_id = ? AND section = (
                 SELECT section_targeted FROM sessions WHERE session_id = ?
             )");
        mysqli_stmt_bind_param($check_student, "si", $student_id, $current_session_id);
        mysqli_stmt_execute($check_student);
        $student_result = mysqli_stmt_get_result($check_student);
        
        if (mysqli_num_rows($student_result) > 0) {
            // Check if attendance already marked
            $check_attendance = mysqli_prepare($conn,
                "SELECT * FROM attendance_records 
                 WHERE session_id = ? AND student_id = ? LIMIT 1");
            mysqli_stmt_bind_param($check_attendance, "is", $current_session_id, $student_id);
            mysqli_stmt_execute($check_attendance);
            $attendance_result = mysqli_stmt_get_result($check_attendance);
            
            if (mysqli_num_rows($attendance_result) == 0) {
                // Mark attendance
                $insert_attendance = mysqli_prepare($conn,
                    "INSERT INTO attendance_records (session_id, student_id, marked_at) 
                     VALUES (?, ?, NOW())");
                mysqli_stmt_bind_param($insert_attendance, "is", $current_session_id, $student_id);
                
                if (mysqli_stmt_execute($insert_attendance)) {
                    $_SESSION['scan_success'] = "Attendance marked for Student ID: $student_id";
                } else {
                    $_SESSION['scan_error'] = "Failed to mark attendance. Please try again.";
                }
            } else {
                $_SESSION['scan_error'] = "Attendance already marked for Student ID: $student_id";
            }
        } else {
            $_SESSION['scan_error'] = "Student not found in this section or invalid ID.";
        }
    } else {
        $_SESSION['scan_error'] = "Please enter a valid Student ID.";
    }
    
    // Clean buffer before redirect
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    
    // Redirect to refresh page
    header("Location: faculty_scan.php?session_id=$current_session_id");
    exit;
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
        body {
            background-color: #f8f9fa;
        }
        .card {
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card-header {
            font-weight: 600;
        }
        .bg-opacity-10 {
            background-color: rgba(var(--bs-primary-rgb), 0.1);
        }
        #qr-reader {
            border: 2px solid #dee2e6 !important;
            border-radius: 8px;
            overflow: hidden;
        }
        #qr-reader button {
            margin: 5px;
        }
        .position-fixed {
            position: fixed;
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
                            <div class="text-end">
                                <span class="badge bg-success fs-6">
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
                <!-- Session Info -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-success">
                            <div class="card-body bg-success bg-opacity-10">
                                <div class="row">
                                    <div class="col-md-3">
                                        <h6><i class="fas fa-book me-2"></i> Subject</h6>
                                        <p class="fw-bold"><?php echo htmlspecialchars($session_info['subject_code'] . ' - ' . $session_info['subject_name']); ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <h6><i class="fas fa-users me-2"></i> Section</h6>
                                        <p class="fw-bold">Section <?php echo htmlspecialchars($session_info['section_targeted']); ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <h6><i class="fas fa-chalkboard me-2"></i> Type</h6>
                                        <p class="fw-bold"><?php echo htmlspecialchars($session_info['class_type']); ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <h6><i class="fas fa-clock me-2"></i> Started</h6>
                                        <p class="fw-bold">
                                            <?php 
                                            // Display start time if available
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
                                        <h6><i class="fas fa-user-check me-2"></i> Attendance Count</h6>
                                        <p class="fw-bold text-primary">
                                            <?php
                                            $count_query = mysqli_prepare($conn,
                                                "SELECT COUNT(*) as count FROM attendance_records WHERE session_id = ?");
                                            mysqli_stmt_bind_param($count_query, "i", $session_id);
                                            mysqli_stmt_execute($count_query);
                                            $count_result = mysqli_stmt_get_result($count_query);
                                            $att_count = $count_result ? mysqli_fetch_assoc($count_result)['count'] ?? 0 : 0;
                                            echo $att_count . " students";
                                            ?>
                                        </p>
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
                                <!-- Scanner Container -->
                                <div id="qr-reader" style="width: 100%; max-width: 600px; margin: 0 auto;"></div>
                                <div id="qr-reader-results" class="mt-3"></div>
                                
                                <!-- Manual Input Form -->
                                <div class="mt-4 pt-4 border-top">
                                    <h5 class="mb-3"><i class="fas fa-keyboard me-2"></i> Manual Entry</h5>
                                    <form method="POST" class="row g-3 justify-content-center">
                                        <div class="col-md-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                <input type="text" name="student_id" class="form-control form-control-lg" 
                                                       placeholder="Enter Student ID" required>
                                                <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" name="mark_attendance" class="btn btn-primary btn-lg w-100">
                                                <i class="fas fa-check-circle me-2"></i> Mark Attendance
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
                                <ol class="mb-0">
                                    <li class="mb-2">Allow camera access when prompted</li>
                                    <li class="mb-2">Position QR code within scanner frame</li>
                                    <li class="mb-2">Scanner will beep on successful scan</li>
                                    <li class="mb-2">Or manually enter Student ID above</li>
                                    <li>Click "Stop Session" when class ends</li>
                                </ol>
                            </div>
                        </div>
                        
                        <!-- Recent Attendance -->
                        <div class="card shadow-lg border-0">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Attendance</h5>
                            </div>
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                <?php
                                $recent_query = mysqli_prepare($conn,
                                    "SELECT ar.student_id, ar.marked_at, s.student_name
                                     FROM attendance_records ar
                                     LEFT JOIN students s ON ar.student_id = s.student_id
                                     WHERE ar.session_id = ?
                                     ORDER BY ar.marked_at DESC
                                     LIMIT 10");
                                mysqli_stmt_bind_param($recent_query, "i", $session_id);
                                mysqli_stmt_execute($recent_query);
                                $recent_result = mysqli_stmt_get_result($recent_query);
                                
                                if ($recent_result && mysqli_num_rows($recent_result) > 0):
                                    while ($record = mysqli_fetch_assoc($recent_result)): ?>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div>
                                            <span class="fw-bold"><?php echo htmlspecialchars($record['student_id']); ?></span>
                                            <?php if (!empty($record['student_name'])): ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['student_name']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-success">
                                                <i class="fas fa-check-circle"></i>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('h:i A', strtotime($record['marked_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php endwhile;
                                else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-user-clock fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No attendance marked yet</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($session_id == 0): ?>
                <!-- No Session Selected -->
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
                <!-- Session Not Found or Inactive -->
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
    // Display messages from session
    <?php if (isset($_SESSION['scan_success'])): ?>
        showAlert('success', '<?php echo addslashes($_SESSION['scan_success']); ?>');
        <?php unset($_SESSION['scan_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['scan_error'])): ?>
        showAlert('danger', '<?php echo addslashes($_SESSION['scan_error']); ?>');
        <?php unset($_SESSION['scan_error']); ?>
    <?php endif; ?>

    // QR Scanner Functionality
    <?php if ($session_id > 0): ?>
    let html5QrcodeScanner = null;

    function onScanSuccess(decodedText, decodedResult) {
        // Stop scanning
        if (html5QrcodeScanner) {
            html5QrcodeScanner.pause();
        }
        
        // Play success sound
        playBeepSound();
        
        // Show scan result
        document.getElementById('qr-reader-results').innerHTML = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <strong>QR Code Scanned!</strong><br>
                Student ID: <strong>${decodedText}</strong>
                <div class="mt-2">
                    <button onclick="markAttendance('${decodedText}')" class="btn btn-success btn-sm">
                        <i class="fas fa-check me-1"></i> Mark Attendance
                    </button>
                    <button onclick="resumeScanner()" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-redo me-1"></i> Scan Again
                    </button>
                </div>
            </div>
        `;
    }

    function onScanFailure(error) {
        // Handle scan failure quietly
        console.warn(`QR scan error: ${error}`);
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
        } catch (e) {
            console.log("Audio not supported");
        }
    }

    function markAttendance(studentId) {
        // Submit form via AJAX
        const formData = new FormData();
        formData.append('student_id', studentId);
        formData.append('session_id', <?php echo $session_id; ?>);
        formData.append('mark_attendance', '1');
        
        fetch('faculty_scan.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            // Refresh page to show updated attendance
            window.location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Failed to mark attendance. Please try manually.');
            resumeScanner();
        });
    }

    function resumeScanner() {
        document.getElementById('qr-reader-results').innerHTML = '';
        if (html5QrcodeScanner) {
            html5QrcodeScanner.resume();
        }
    }

    // Initialize scanner when page loads
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($session_id > 0): ?>
        html5QrcodeScanner = new Html5QrcodeScanner(
            "qr-reader", 
            { 
                fps: 10, 
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0,
                showTorchButtonIfSupported: true,
                showZoomSliderIfSupported: true
            },
            /* verbose= */ false
        );
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        <?php endif; ?>
    });

    // Alert function
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-4`;
        alertDiv.style.zIndex = '1050';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    <?php endif; ?>
    </script>
</body>
</html>
<?php
// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
