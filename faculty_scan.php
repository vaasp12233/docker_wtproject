<?php
// faculty_scan.php - Fixed for Render + Aiven (QR auto-extract & auto-mark)

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

// ==================== Security check ====================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    if (ob_get_length() > 0) ob_end_clean();
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'] ?? null;
if (!$faculty_id) {
    if (ob_get_length() > 0) ob_end_clean();
    header('Location: login.php');
    exit;
}

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

// ==================== Clean buffer before output ====================
if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

// ==================== Get active sessions for dropdown ====================
$active_sessions_result = null;
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

// Auto-select if only one active session
if ($session_id === 0 && mysqli_num_rows($active_sessions_result) == 1) {
    $session = mysqli_fetch_assoc($active_sessions_result);
    $session_id = $session['session_id'];
    mysqli_data_seek($active_sessions_result, 0); // reset for later use
}

// ==================== Get session details ====================
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
        $session_id = 0;
    }
}

// ==================== Handle manual / AJAX attendance marking ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    // Clean student ID: trim, remove newlines, and extract only alphanumeric + allowed chars
    $raw_student_id = trim($_POST['student_id'] ?? '');
    // Extract first alphanumeric sequence (supports IDs like S001, 2024001, STD-123)
    preg_match('/[A-Za-z0-9\-_]+/', $raw_student_id, $matches);
    $student_id = $matches[0] ?? '';
    
    $current_session_id = intval($_POST['session_id'] ?? 0);
    $response = ['success' => false, 'message' => ''];
    
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
                "SELECT marked_at FROM attendance_records 
                 WHERE session_id = ? AND student_id = ? LIMIT 1");
            mysqli_stmt_bind_param($check_attendance, "is", $current_session_id, $student_id);
            mysqli_stmt_execute($check_attendance);
            $attendance_result = mysqli_stmt_get_result($check_attendance);
            
            if (mysqli_num_rows($attendance_result) == 0) {
                $current_time = date('Y-m-d H:i:s');
                $insert_attendance = mysqli_prepare($conn,
                    "INSERT INTO attendance_records (session_id, student_id, marked_at) 
                     VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($insert_attendance, "iss", $current_session_id, $student_id, $current_time);
                
                if (mysqli_stmt_execute($insert_attendance)) {
                    $response['success'] = true;
                    $response['message'] = "Attendance marked for " . htmlspecialchars($student_data['student_name']);
                } else {
                    $response['message'] = "Database error. Please try again.";
                }
            } else {
                $existing = mysqli_fetch_assoc($attendance_result);
                $existing_time = date('h:i A', strtotime($existing['marked_at']));
                $response['message'] = "Already marked at $existing_time";
            }
        } else {
            $response['message'] = "Student ID not found in this section.";
        }
    } else {
        $response['message'] = "Invalid student ID or session.";
    }
    
    // If this is an AJAX request (QR scan), return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Otherwise (manual form submission) use session flash and redirect
    if ($response['success']) {
        $_SESSION['scan_success'] = $response['message'];
    } else {
        $_SESSION['scan_error'] = $response['message'];
    }
    
    if (ob_get_length() > 0) ob_end_clean();
    header("Location: faculty_scan.php?session_id=$current_session_id");
    exit;
}

$page_title = "QR Code Scanner";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { margin-bottom: 20px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 10px; }
        .card-header { font-weight: 600; border-radius: 10px 10px 0 0 !important; }
        #qr-reader { border: 3px solid #dee2e6 !important; border-radius: 10px; overflow: hidden; background: white; }
        .session-info-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-left: 4px solid #28a745 !important; }
        .btn-view-attendance { background: linear-gradient(45deg, #17a2b8, #20c997); border: none; color: white; }
        .current-time-display { font-size: 0.9rem; background: linear-gradient(45deg, #6c757d, #495057); color: white; padding: 5px 10px; border-radius: 20px; }
        .attendance-count-badge { font-size: 1.1rem; padding: 8px 15px; border-radius: 25px; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); } }
        .scanner-active { animation: pulse 2s infinite; }
        .toast-notification { position: fixed; top: 20px; right: 20px; z-index: 1100; min-width: 300px; }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="faculty_dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
            
            <div class="card shadow-lg border-0 mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0"><i class="fas fa-qrcode me-2"></i> Attendance Scanner</h2>
                            <p class="mb-0 opacity-75">Scan student QR codes → attendance marked automatically</p>
                        </div>
                        <div class="text-end d-flex align-items-center gap-3">
                            <span class="current-time-display"><i class="fas fa-clock me-1"></i><span id="current-time"><?php echo date('h:i A'); ?></span></span>
                            <span class="badge bg-success fs-6 scanner-active"><i class="fas fa-circle fa-xs"></i> Scanner Active</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Select Active Session:</label>
                            <select name="session_id" class="form-select" onchange="this.form.submit()">
                                <option value="0" <?php echo $session_id == 0 ? 'selected' : ''; ?>>-- Select a session --</option>
                                <?php 
                                if ($active_sessions_result) {
                                    mysqli_data_seek($active_sessions_result, 0);
                                    while ($session = mysqli_fetch_assoc($active_sessions_result)): ?>
                                        <option value="<?php echo $session['session_id']; ?>" <?php echo $session_id == $session['session_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($session['subject_code'] . ' - ' . $session['subject_name']); ?>
                                            (Section <?php echo $session['section_targeted']; ?> - <?php echo $session['class_type']; ?>)
                                        </option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <?php if ($session_id > 0): ?>
                            <a href="stop_session.php?session_id=<?php echo $session_id; ?>" class="btn btn-danger w-100" onclick="return confirm('Stop this session? Students cannot mark attendance after stopping.')">
                                <i class="fas fa-stop-circle me-2"></i> Stop Session
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($session_id > 0 && $session_info): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="check_attendance.php?session_id=<?php echo $session_id; ?>" class="btn btn-view-attendance">
                            <i class="fas fa-list-check me-2"></i> View Complete Attendance
                        </a>
                        <span class="badge bg-primary attendance-count-badge">
                            <i class="fas fa-user-check me-1"></i>
                            <?php
                            $count_query = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM attendance_records WHERE session_id = ?");
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
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card border-success session-info-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3"><h6><i class="fas fa-book me-2 text-primary"></i> Subject</h6><p class="fw-bold"><?php echo htmlspecialchars($session_info['subject_code'] . ' - ' . $session_info['subject_name']); ?></p></div>
                                <div class="col-md-2"><h6><i class="fas fa-users me-2 text-success"></i> Section</h6><p class="fw-bold">Section <?php echo htmlspecialchars($session_info['section_targeted']); ?></p></div>
                                <div class="col-md-2"><h6><i class="fas fa-chalkboard me-2 text-warning"></i> Type</h6><p class="fw-bold"><?php echo htmlspecialchars($session_info['class_type']); ?></p></div>
                                <div class="col-md-2"><h6><i class="fas fa-clock me-2 text-info"></i> Started</h6><p class="fw-bold"><?php echo !empty($session_info['start_time']) ? date('h:i A', strtotime($session_info['start_time'])) : ( !empty($session_info['created_at']) ? date('h:i A', strtotime($session_info['created_at'])) : 'N/A' ); ?></p></div>
                                <div class="col-md-3"><h6><i class="fas fa-hourglass-half me-2 text-danger"></i> Duration</h6><p class="fw-bold" id="session-duration">--:--</p></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card shadow-lg border-0 h-100">
                        <div class="card-header bg-dark text-white"><h4 class="mb-0"><i class="fas fa-camera me-2"></i> QR Code Scanner</h4></div>
                        <div class="card-body text-center">
                            <div id="qr-reader" style="width: 100%; max-width: 600px; margin: 0 auto;"></div>
                            <div id="qr-reader-results" class="mt-3"></div>
                            
                            <div class="mt-4 pt-4 border-top">
                                <h5 class="mb-3"><i class="fas fa-keyboard me-2"></i> Manual Entry</h5>
                                <form method="POST" class="row g-3 justify-content-center">
                                    <div class="col-md-6">
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text bg-primary text-white"><i class="fas fa-id-card"></i></span>
                                            <input type="text" name="student_id" class="form-control" placeholder="Enter Student ID" required>
                                            <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3"><button type="submit" name="mark_attendance" class="btn btn-primary btn-lg w-100"><i class="fas fa-check-circle me-2"></i> Mark Attendance</button></div>
                                    <div class="col-md-3"><button type="button" class="btn btn-outline-secondary btn-lg w-100" onclick="document.querySelector('[name=student_id]').value=''; this.form.querySelector('[name=student_id]').focus();"><i class="fas fa-times"></i> Clear</button></div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card shadow-lg border-0 mb-4">
                        <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Instructions</h5></div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex align-items-center"><div class="me-3"><span class="badge bg-primary rounded-circle p-2">1</span></div><div>Allow camera access</div></div>
                                <div class="list-group-item d-flex align-items-center"><div class="me-3"><span class="badge bg-success rounded-circle p-2">2</span></div><div>Position QR code in frame → auto-marks attendance</div></div>
                                <div class="list-group-item d-flex align-items-center"><div class="me-3"><span class="badge bg-warning rounded-circle p-2">3</span></div><div>Scanner beeps on success</div></div>
                                <div class="list-group-item d-flex align-items-center"><div class="me-3"><span class="badge bg-danger rounded-circle p-2">4</span></div><div>Or enter Student ID manually</div></div>
                                <div class="list-group-item d-flex align-items-center"><div class="me-3"><span class="badge bg-dark rounded-circle p-2">5</span></div><div>Click "Stop Session" when class ends</div></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow-lg border-0">
                        <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Attendance</h5></div>
                        <div class="card-body" style="max-height: 320px; overflow-y: auto;">
                            <?php
                            $recent_query = mysqli_prepare($conn, "SELECT ar.student_id, ar.marked_at, s.student_name FROM attendance_records ar LEFT JOIN students s ON ar.student_id = s.student_id WHERE ar.session_id = ? ORDER BY ar.marked_at DESC LIMIT 8");
                            mysqli_stmt_bind_param($recent_query, "i", $session_id);
                            mysqli_stmt_execute($recent_query);
                            $recent_result = mysqli_stmt_get_result($recent_query);
                            if ($recent_result && mysqli_num_rows($recent_result) > 0):
                                while ($record = mysqli_fetch_assoc($recent_result)): ?>
                                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                                        <div><i class="fas fa-user-circle fa-lg text-primary me-3"></i> <span class="fw-bold"><?php echo htmlspecialchars($record['student_id']); ?></span><br><small class="text-muted"><?php echo htmlspecialchars($record['student_name'] ?? ''); ?></small></div>
                                        <div class="text-end"><small class="text-success fw-bold"><i class="fas fa-check"></i></small><br><small class="text-muted"><?php echo date('h:i A', strtotime($record['marked_at'])); ?></small></div>
                                    </div>
                                <?php endwhile;
                            else: ?>
                                <div class="text-center py-5"><i class="fas fa-user-clock fa-3x text-muted mb-3"></i><h6 class="text-muted">No attendance marked yet</h6><p class="text-muted small">Scan a QR code to begin</p></div>
                            <?php endif; ?>
                            <?php if ($att_count > 8): ?>
                                <div class="text-center mt-3 pt-3 border-top"><a href="check_attendance.php?session_id=<?php echo $session_id; ?>" class="btn btn-outline-success btn-sm"><i class="fas fa-eye me-1"></i> View All</a></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($session_id == 0): ?>
            <div class="card shadow-lg border-0"><div class="card-body text-center py-5"><i class="fas fa-qrcode fa-4x text-muted mb-4"></i><h3 class="text-muted">No Active Session Selected</h3><p class="text-muted">Select a session above or start a new one from the dashboard.</p><a href="faculty_dashboard.php" class="btn btn-primary btn-lg"><i class="fas fa-plus-circle me-2"></i> Start New Session</a></div></div>
            <?php else: ?>
            <div class="card shadow-lg border-0"><div class="card-body text-center py-5"><i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i><h3 class="text-warning">Session Not Available</h3><p class="text-muted">This session may have ended.</p><a href="faculty_dashboard.php" class="btn btn-primary">Back to Dashboard</a></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Helper: show toast notification
function showToast(message, type = 'success') {
    const toastDiv = document.createElement('div');
    toastDiv.className = `toast-notification alert alert-${type} alert-dismissible fade show`;
    toastDiv.innerHTML = `<div class="d-flex align-items-center"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} fa-lg me-3"></i><div>${message}</div><button type="button" class="btn-close ms-3" data-bs-dismiss="alert"></button></div>`;
    document.body.appendChild(toastDiv);
    setTimeout(() => { if (toastDiv.parentNode) toastDiv.remove(); }, 4000);
}

// Update current time
function updateCurrentTime() {
    const now = new Date();
    document.getElementById('current-time').textContent = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
}
setInterval(updateCurrentTime, 1000);

// Session duration
function updateSessionDuration() {
    <?php if ($session_id > 0 && !empty($session_info['start_time'])): ?>
    const start = new Date('<?php echo $session_info['start_time']; ?>');
    const diff = Math.floor((new Date() - start) / 60000);
    const hours = Math.floor(diff / 60), mins = diff % 60;
    document.getElementById('session-duration').textContent = hours ? `${hours}h ${mins}m` : `${mins}m`;
    <?php endif; ?>
}
setInterval(updateSessionDuration, 60000);
updateSessionDuration();

// Show flash messages
<?php if (isset($_SESSION['scan_success'])): ?>showToast('<?php echo addslashes($_SESSION['scan_success']); ?>', 'success');<?php unset($_SESSION['scan_success']); endif; ?>
<?php if (isset($_SESSION['scan_error'])): ?>showToast('<?php echo addslashes($_SESSION['scan_error']); ?>', 'danger');<?php unset($_SESSION['scan_error']); endif; ?>

<?php if ($session_id > 0): ?>
let html5QrcodeScanner = null;
let isProcessing = false;

function playBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.value = 800;
        osc.type = 'sine';
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.5);
    } catch(e) { console.log("Beep not supported"); }
}

function extractStudentId(scannedText) {
    // Remove leading/trailing spaces, newlines, and extract first alphanumeric sequence (supports IDs like S123, 2024001, STD-456)
    let cleaned = scannedText.trim();
    const match = cleaned.match(/[A-Za-z0-9\-_]+/);
    return match ? match[0] : '';
}

async function markAttendance(studentId) {
    if (isProcessing) return;
    isProcessing = true;
    
    // Show a temporary scanning indicator
    const resultsDiv = document.getElementById('qr-reader-results');
    resultsDiv.innerHTML = `<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i> Marking attendance for ${studentId}...</div>`;
    
    const formData = new FormData();
    formData.append('student_id', studentId);
    formData.append('session_id', <?php echo $session_id; ?>);
    formData.append('mark_attendance', '1');
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            playBeep();
            showToast(data.message, 'success');
            // Refresh page after short delay to show updated list
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'danger');
            resultsDiv.innerHTML = `<div class="alert alert-warning">${data.message}</div>`;
            setTimeout(() => { resultsDiv.innerHTML = ''; if(html5QrcodeScanner) html5QrcodeScanner.resume(); }, 2000);
            isProcessing = false;
        }
    } catch (error) {
        console.error('Error marking attendance:', error);
        showToast('Network error. Please try manual entry.', 'danger');
        resultsDiv.innerHTML = `<div class="alert alert-danger">Failed to connect. Use manual entry.</div>`;
        setTimeout(() => { resultsDiv.innerHTML = ''; if(html5QrcodeScanner) html5QrcodeScanner.resume(); }, 2000);
        isProcessing = false;
    }
}

function onScanSuccess(decodedText) {
    if (isProcessing) return;
    
    const studentId = extractStudentId(decodedText);
    if (!studentId) {
        showToast(`Invalid QR format: "${decodedText.substring(0,30)}". Must contain a student ID.`, 'danger');
        return;
    }
    
    // Pause scanner while processing
    if (html5QrcodeScanner) html5QrcodeScanner.pause();
    markAttendance(studentId);
}

function onScanFailure(error) { /* silent */ }

document.addEventListener('DOMContentLoaded', () => {
    html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0, showTorchButtonIfSupported: true });
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    document.querySelector('[name="student_id"]')?.focus();
});
<?php endif; ?>
</script>
</body>
</html>
<?php
if (ob_get_level() > 0) ob_end_flush();
?>
