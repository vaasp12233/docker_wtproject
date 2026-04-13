<?php
// faculty_scan.php - Both Manual Entry & QR Scanner with Confirmation
// Compatible with students table: student_id (PK), id_number (roll number)

if (!ob_get_level()) ob_start();

ini_set('session.save_path', '/tmp');
ini_set('session.cookie_lifetime', 86400);
date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) @session_start();
require_once 'config.php';

// Security checks
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    if (ob_get_length() > 0) ob_end_clean();
    header('Location: login.php');
    exit;
}
$faculty_id = $_SESSION['faculty_id'] ?? null;
if (!$faculty_id) {
    header('Location: login.php');
    exit;
}

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

// Get active sessions
$active_sessions_result = null;
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
    if (mysqli_num_rows($active_sessions_result) == 1) {
        $session = mysqli_fetch_assoc($active_sessions_result);
        $session_id = $session['session_id'];
    }
}

// Get session details
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

// ==================== ATTENDANCE MARKING HANDLER (Supports both manual & QR) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    // Determine if this is from QR scanner (has scanned_qr) or manual form (has student_id)
    $scanned_qr = trim($_POST['scanned_qr'] ?? '');
    $entered_value = trim($_POST['entered_student_id'] ?? '');
    $current_session_id = intval($_POST['session_id'] ?? 0);

    // If manual form (no QR), get value from 'student_id'
    if (empty($scanned_qr)) {
        $entered_value = trim($_POST['student_id'] ?? '');
    }

    if (empty($entered_value) || $current_session_id <= 0) {
        $_SESSION['scan_error'] = "Student ID/Roll Number and session are required.";
        header("Location: faculty_scan.php?session_id=$current_session_id");
        exit;
    }

    // QR validation only if QR was scanned
    if (!empty($scanned_qr)) {
        if (!str_starts_with($scanned_qr, "QR_")) {
            $_SESSION['scan_error'] = "Invalid QR format (must start with QR_).";
            header("Location: faculty_scan.php?session_id=$current_session_id");
            exit;
        }
        $qr_roll = strtoupper(trim(str_replace("QR_", "", $scanned_qr)));
    }

    // Find student by entered value (matches student_id PK OR id_number)
    $find_sql = "SELECT student_id, id_number, student_name, section 
                 FROM students 
                 WHERE student_id = ? OR id_number = ?";
    $find_stmt = mysqli_prepare($conn, $find_sql);
    mysqli_stmt_bind_param($find_stmt, "ss", $entered_value, $entered_value);
    mysqli_stmt_execute($find_stmt);
    $student_result = mysqli_stmt_get_result($find_stmt);

    if (mysqli_num_rows($student_result) == 0) {
        $_SESSION['scan_error'] = "Student not found with given ID or Roll Number.";
        header("Location: faculty_scan.php?session_id=$current_session_id");
        exit;
    }
    $student = mysqli_fetch_assoc($student_result);
    $student_pk = $student['student_id'];
    $student_roll = strtoupper($student['id_number']);

    // If QR was scanned, verify match
    if (!empty($scanned_qr)) {
        if ($qr_roll !== $student_roll) {
            $_SESSION['scan_error'] = "QR code does NOT belong to this student. (QR roll: $qr_roll, Student roll: $student_roll)";
            header("Location: faculty_scan.php?session_id=$current_session_id");
            exit;
        }
    }

    // Check section match
    $session_section = $session_info['section_targeted'] ?? '';
    if ($student['section'] !== $session_section) {
        $_SESSION['scan_error'] = "Student section ({$student['section']}) does not match session section ($session_section).";
        header("Location: faculty_scan.php?session_id=$current_session_id");
        exit;
    }

    // Prevent duplicate
    $check_att = mysqli_prepare($conn,
        "SELECT 1 FROM attendance_records WHERE session_id = ? AND student_id = ?");
    mysqli_stmt_bind_param($check_att, "is", $current_session_id, $student_pk);
    mysqli_stmt_execute($check_att);
    $att_exists = mysqli_stmt_get_result($check_att);
    if (mysqli_num_rows($att_exists) > 0) {
        $_SESSION['scan_error'] = "Attendance already marked for this student in this session.";
        header("Location: faculty_scan.php?session_id=$current_session_id");
        exit;
    }

    // Mark attendance
    $current_time = date('Y-m-d H:i:s');
    $insert = mysqli_prepare($conn,
        "INSERT INTO attendance_records (session_id, student_id, marked_at) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($insert, "iss", $current_session_id, $student_pk, $current_time);
    if (mysqli_stmt_execute($insert)) {
        $_SESSION['scan_success'] = "Attendance marked for " . htmlspecialchars($student['student_name']) . " (Roll: $student_roll)";
    } else {
        $_SESSION['scan_error'] = "Database error. Could not mark attendance.";
    }

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
        .card { margin-bottom: 20px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 10px; transition: transform 0.2s; }
        .card:hover { transform: translateY(-2px); }
        .card-header { font-weight: 600; border-radius: 10px 10px 0 0 !important; }
        #qr-reader { border: 3px solid #dee2e6 !important; border-radius: 10px; overflow: hidden; background: white; }
        .scanner-active { animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); } }
        .current-time-display { font-size: 0.9rem; background: linear-gradient(45deg, #6c757d, #495057); color: white; padding: 5px 10px; border-radius: 20px; }
        .session-info-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-left: 4px solid #28a745 !important; }
        .recent-attendance-item { transition: background-color 0.2s; border-radius: 5px; margin-bottom: 3px; }
        .recent-attendance-item:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <!-- Top Buttons: Back and View Attendance -->
            <div class="mb-4 d-flex gap-2">
                <a href="faculty_dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
                <?php if ($session_id > 0): ?>
                <a href="check_attendance.php?session_id=<?php echo $session_id; ?>" class="btn btn-info text-white">
                    <i class="fas fa-list-check me-2"></i> View Attendance Records
                </a>
                <?php endif; ?>
            </div>

            <!-- Page Header -->
            <div class="card shadow-lg border-0 mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0"><i class="fas fa-qrcode me-2"></i> Attendance Scanner</h2>
                            <p class="mb-0 opacity-75">Scan student QR or enter manually</p>
                        </div>
                        <div class="text-end d-flex align-items-center gap-3">
                            <span class="current-time-display"><i class="fas fa-clock me-1"></i><span id="current-time"><?php echo date('h:i A'); ?></span></span>
                            <span class="badge bg-success fs-6 scanner-active"><i class="fas fa-circle fa-xs"></i> Scanner Active</span>
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
                                if ($active_sessions_result && mysqli_num_rows($active_sessions_result) > 0) {
                                    mysqli_data_seek($active_sessions_result, 0);
                                    while ($session = mysqli_fetch_assoc($active_sessions_result)): ?>
                                        <option value="<?php echo $session['session_id']; ?>" <?php echo $session_id == $session['session_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($session['subject_code'] . ' - ' . $session['subject_name']); ?>
                                            (Section <?php echo $session['section_targeted']; ?> - <?php echo $session['class_type']; ?>)
                                        </option>
                                    <?php endwhile;
                                } ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <?php if ($session_id > 0): ?>
                                <a href="stop_session.php?session_id=<?php echo $session_id; ?>" class="btn btn-danger w-100" onclick="return confirm('Stop this session? Students cannot mark attendance afterwards.')">
                                    <i class="fas fa-stop-circle me-2"></i> Stop Session
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($session_id > 0 && $session_info): ?>
                <!-- Session Info & Attendance Count -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-success session-info-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3"><h6><i class="fas fa-book me-2 text-primary"></i> Subject</h6><p class="fw-bold"><?php echo htmlspecialchars($session_info['subject_code'] . ' - ' . $session_info['subject_name']); ?></p></div>
                                    <div class="col-md-2"><h6><i class="fas fa-users me-2 text-success"></i> Section</h6><p class="fw-bold">Section <?php echo htmlspecialchars($session_info['section_targeted']); ?></p></div>
                                    <div class="col-md-2"><h6><i class="fas fa-chalkboard me-2 text-warning"></i> Type</h6><p class="fw-bold"><?php echo htmlspecialchars($session_info['class_type']); ?></p></div>
                                    <div class="col-md-2"><h6><i class="fas fa-clock me-2 text-info"></i> Started</h6><p class="fw-bold"><?php echo !empty($session_info['start_time']) ? date('h:i A', strtotime($session_info['start_time'])) : (isset($session_info['created_at']) ? date('h:i A', strtotime($session_info['created_at'])) : 'N/A'); ?></p></div>
                                    <div class="col-md-3"><h6><i class="fas fa-hourglass-half me-2 text-danger"></i> Duration</h6><p class="fw-bold" id="session-duration">--:--</p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Scanning Area -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow-lg border-0 h-100">
                            <div class="card-header bg-dark text-white"><h4 class="mb-0"><i class="fas fa-camera me-2"></i> QR Code Scanner</h4></div>
                            <div class="card-body text-center">
                                <!-- QR Scanner Container -->
                                <div id="qr-reader" style="width: 100%; max-width: 600px; margin: 0 auto;"></div>
                                <div id="qr-reader-results" class="mt-3"></div>

                                <!-- ORIGINAL MANUAL ENTRY FORM (RESTORED) -->
                                <div class="mt-4 pt-4 border-top">
                                    <h5 class="mb-3"><i class="fas fa-keyboard me-2"></i> Manual Entry (Direct Mark)</h5>
                                    <form method="POST" class="row g-3 justify-content-center">
                                        <div class="col-md-6">
                                            <div class="input-group input-group-lg">
                                                <span class="input-group-text bg-primary text-white"><i class="fas fa-id-card"></i></span>
                                                <input type="text" name="student_id" class="form-control" placeholder="Enter Student ID or Roll Number" required>
                                                <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" name="mark_attendance" class="btn btn-primary btn-lg w-100">
                                                <i class="fas fa-check-circle me-2"></i> Mark Attendance
                                            </button>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-outline-secondary btn-lg w-100" onclick="document.querySelector('[name=student_id]').value = ''; document.querySelector('[name=student_id]').focus();">
                                                <i class="fas fa-times"></i> Clear
                                            </button>
                                        </div>
                                    </form>
                                    <p class="text-muted small mt-2">Enter either <strong>numeric ID</strong> (e.g., 305) or <strong>Roll Number</strong> (e.g., R220849).</p>
                                </div>

                                <!-- QR Confirmation Section (separate) -->
                                <div class="mt-4 pt-4 border-top">
                                    <h5 class="mb-3"><i class="fas fa-qrcode me-2"></i> QR + Confirmation</h5>
                                    <p class="text-muted small">After scanning QR, enter the student's ID or Roll Number below to confirm.</p>
                                    <div class="row g-3 justify-content-center">
                                        <div class="col-md-6">
                                            <input type="text" id="qrConfirmInput" class="form-control form-control-lg" placeholder="Student ID or Roll Number">
                                        </div>
                                        <div class="col-md-3">
                                            <button id="qrConfirmBtn" class="btn btn-success btn-lg w-100" disabled>Confirm & Mark</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Attendance Panel -->
                    <div class="col-lg-4">
                        <div class="card shadow-lg border-0 mb-4">
                            <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Instructions</h5></div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item">1️⃣ <strong>Manual Entry</strong>: Enter ID/Roll → click "Mark Attendance"</div>
                                    <div class="list-group-item">2️⃣ <strong>QR Scanner</strong>: Scan QR → enter ID/Roll → Confirm</div>
                                    <div class="list-group-item">3️⃣ Both methods verify student, section, and duplicate</div>
                                </div>
                            </div>
                        </div>
                        <div class="card shadow-lg border-0">
                            <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Attendance</h5></div>
                            <div class="card-body" style="max-height: 320px; overflow-y: auto;">
                                <?php
                                $recent_query = mysqli_prepare($conn,
                                    "SELECT ar.student_id, ar.marked_at, s.student_name, s.id_number
                                     FROM attendance_records ar
                                     LEFT JOIN students s ON ar.student_id = s.student_id
                                     WHERE ar.session_id = ?
                                     ORDER BY ar.marked_at DESC LIMIT 8");
                                mysqli_stmt_bind_param($recent_query, "i", $session_id);
                                mysqli_stmt_execute($recent_query);
                                $recent_result = mysqli_stmt_get_result($recent_query);
                                if ($recent_result && mysqli_num_rows($recent_result) > 0):
                                    while ($record = mysqli_fetch_assoc($recent_result)): ?>
                                        <div class="recent-attendance-item d-flex justify-content-between align-items-center p-3">
                                            <div><i class="fas fa-user-circle fa-lg text-primary me-2"></i> <strong><?php echo htmlspecialchars($record['id_number']); ?></strong><br><small><?php echo htmlspecialchars($record['student_name']); ?></small></div>
                                            <div><small class="text-success"><?php echo date('h:i A', strtotime($record['marked_at'])); ?></small></div>
                                        </div>
                                    <?php endwhile;
                                else: ?>
                                    <div class="text-center py-5"><i class="fas fa-user-clock fa-3x text-muted mb-3"></i><h6 class="text-muted">No attendance marked yet</h6></div>
                                <?php endif; ?>
                                <?php
                                $count_query = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM attendance_records WHERE session_id = ?");
                                mysqli_stmt_bind_param($count_query, "i", $session_id);
                                mysqli_stmt_execute($count_query);
                                $att_count = mysqli_fetch_assoc(mysqli_stmt_get_result($count_query))['count'] ?? 0;
                                if ($att_count > 8): ?>
                                    <div class="text-center mt-3"><a href="check_attendance.php?session_id=<?php echo $session_id; ?>" class="btn btn-outline-success btn-sm">View All</a></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($session_id == 0): ?>
                <div class="card shadow-lg"><div class="card-body text-center py-5"><i class="fas fa-qrcode fa-4x text-muted mb-4"></i><h3 class="text-muted">No Active Session Selected</h3><a href="faculty_dashboard.php" class="btn btn-primary btn-lg mt-3">Start New Session</a></div></div>
            <?php else: ?>
                <div class="card shadow-lg"><div class="card-body text-center py-5"><i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i><h3 class="text-warning">Session Not Available</h3><a href="faculty_dashboard.php" class="btn btn-primary">Back to Dashboard</a></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let lastScannedQR = '';
    let html5QrcodeScanner = null;

    function updateCurrentTime() {
        document.getElementById('current-time').textContent = new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
    function updateSessionDuration() {
        <?php if ($session_id > 0 && !empty($session_info['start_time'])): ?>
        const start = new Date('<?php echo $session_info['start_time']; ?>');
        const diff = Math.floor((new Date() - start) / 60000);
        const hours = Math.floor(diff / 60), mins = diff % 60;
        document.getElementById('session-duration').textContent = hours ? `${hours}h ${mins}m` : `${mins}m`;
        <?php endif; ?>
    }
    setInterval(updateCurrentTime, 1000);
    setInterval(updateSessionDuration, 60000);
    updateSessionDuration();

    <?php if (isset($_SESSION['scan_success'])): ?>
        showAlert('success', '<?php echo addslashes($_SESSION['scan_success']); unset($_SESSION['scan_success']); ?>');
    <?php endif; ?>
    <?php if (isset($_SESSION['scan_error'])): ?>
        showAlert('danger', '<?php echo addslashes($_SESSION['scan_error']); unset($_SESSION['scan_error']); ?>');
    <?php endif; ?>

    function showAlert(type, message) {
        const div = document.createElement('div');
        div.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-4`;
        div.style.zIndex = '1050';
        div.style.maxWidth = '400px';
        div.innerHTML = `<div><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i> ${message}</div><button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 5000);
    }

    function playBeepSound() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.frequency.value = 800;
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.5);
        } catch(e) { console.log("Beep not supported"); }
    }

    function markAttendanceFromQR(scannedQR, enteredValue) {
        document.getElementById('qr-reader-results').innerHTML = `<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i> Processing...</div>`;
        const formData = new FormData();
        formData.append('scanned_qr', scannedQR);
        formData.append('entered_student_id', enteredValue);
        formData.append('session_id', <?php echo $session_id; ?>);
        formData.append('mark_attendance', '1');
        fetch('faculty_scan.php', { method: 'POST', body: formData })
            .then(() => window.location.reload())
            .catch(() => { showAlert('danger', 'Failed to mark attendance.'); resumeScanner(); });
    }

    function resumeScanner() {
        document.getElementById('qr-reader-results').innerHTML = '';
        lastScannedQR = '';
        const qrConfirmBtn = document.getElementById('qrConfirmBtn');
        if (qrConfirmBtn) qrConfirmBtn.disabled = true;
        const qrInput = document.getElementById('qrConfirmInput');
        if (qrInput) qrInput.value = '';
        if (html5QrcodeScanner) html5QrcodeScanner.resume();
    }

    function onScanSuccess(decodedText) {
        if (html5QrcodeScanner) html5QrcodeScanner.pause();
        playBeepSound();
        lastScannedQR = decodedText;
        const scanTime = new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
        document.getElementById('qr-reader-results').innerHTML = `
            <div class="alert alert-success">
                <h5>QR Scanned</h5>
                <p><strong>${decodedText}</strong><br><small>${scanTime}</small></p>
                <p>Now enter Student ID or Roll Number below and click Confirm.</p>
            </div>`;
        document.getElementById('qrConfirmBtn').disabled = false;
        document.getElementById('qrConfirmInput').focus();
    }

    function onScanFailure(err) { console.log("Scan error", err); }

    document.addEventListener('DOMContentLoaded', function() {
        const qrConfirmBtn = document.getElementById('qrConfirmBtn');
        const qrInput = document.getElementById('qrConfirmInput');
        if (qrConfirmBtn) {
            qrConfirmBtn.addEventListener('click', () => {
                const val = qrInput.value.trim();
                if (!val) { showAlert('danger', 'Please enter ID or Roll Number.'); return; }
                if (!lastScannedQR) { showAlert('danger', 'No QR scanned.'); return; }
                markAttendanceFromQR(lastScannedQR, val);
            });
        }
        <?php if ($session_id > 0): ?>
        html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0, showTorchButtonIfSupported: true });
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        <?php endif; ?>
    });
</script>
</body>
</html>
<?php
if (ob_get_level() > 0) ob_end_flush();
?> 
