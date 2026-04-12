<?php
// faculty_scan.php - QR scanner only, no manual form. Link to mark_attendance.php

if (!ob_get_level()) ob_start();
ini_set('session.save_path', '/tmp');
ini_set('session.cookie_lifetime', 86400);
date_default_timezone_set('Asia/Kolkata');
if (session_status() === PHP_SESSION_NONE) @session_start();

require_once 'config.php';

// Security checks
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty' ||
    !isset($_SESSION['faculty_id'])) {
    if (ob_get_length() > 0) ob_end_clean();
    header('Location: login.php');
    exit;
}
$faculty_id = $_SESSION['faculty_id'];

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
if (ob_get_length() > 0 && !headers_sent()) { ob_end_clean(); ob_start(); }

// Get active sessions
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

// Handle attendance marking (POST from scanner popup)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {

    $scanned_qr = trim($_POST['scanned_qr'] ?? '');
    $manual_input = trim($_POST['entered_student_id'] ?? '');
    $current_session_id = intval($_POST['session_id'] ?? 0);

    if (empty($scanned_qr) || empty($manual_input)) {
        $_SESSION['scan_error'] = "QR scan and manual entry required.";
        header("Location: faculty_scan.php?session_id=$current_session_id");
        exit;
    }

    // Extract roll number from QR
    $qr_upper = strtoupper($scanned_qr);
    if (strpos($qr_upper, "QR_") === 0) {
        $qr_roll = substr($qr_upper, 3);
    } else {
        $qr_roll = $qr_upper;
    }
    $qr_roll = trim($qr_roll);

    // Determine manual roll number
    $manual_roll = null;
    if (is_numeric($manual_input)) {
        $stmt = mysqli_prepare($conn, "SELECT id_number FROM students WHERE student_id = ?");
        mysqli_stmt_bind_param($stmt, "s", $manual_input);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $manual_roll = strtoupper(trim($row['id_number']));
        } else {
            $_SESSION['scan_error'] = "No student found with ID: $manual_input";
            header("Location: faculty_scan.php?session_id=$current_session_id");
            exit;
        }
    } else {
        $manual_roll = strtoupper(trim($manual_input));
    }

    // Compare
    if ($qr_roll !== $manual_roll) {
        $_SESSION['scan_error'] = "QR roll number ($qr_roll) does not match entered student ($manual_roll).";
        header("Location: faculty_scan.php?session_id=$current_session_id");
        exit;
    }

    // Find student by id_number + section
    $section_targeted = $session_info['section_targeted'] ?? '';
    $stmt = mysqli_prepare($conn,
        "SELECT student_id, student_name, id_number 
         FROM students 
         WHERE id_number = ? AND section = ?");
    mysqli_stmt_bind_param($stmt, "ss", $manual_roll, $section_targeted);
    mysqli_stmt_execute($stmt);
    $student_res = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($student_res) === 0) {
        $_SESSION['scan_error'] = "Student not found in section $section_targeted.";
        header("Location: faculty_scan.php?session_id=$current_session_id");
        exit;
    }
    $student = mysqli_fetch_assoc($student_res);
    $student_pk = $student['student_id'];

    // Prevent duplicate
    $dup_stmt = mysqli_prepare($conn,
        "SELECT marked_at FROM attendance_records 
         WHERE session_id = ? AND student_id = ?");
    mysqli_stmt_bind_param($dup_stmt, "is", $current_session_id, $student_pk);
    mysqli_stmt_execute($dup_stmt);
    $dup_res = mysqli_stmt_get_result($dup_stmt);

    if (mysqli_num_rows($dup_res) > 0) {
        $existing = mysqli_fetch_assoc($dup_res);
        $time = date('h:i A', strtotime($existing['marked_at']));
        $_SESSION['scan_error'] = "Attendance already marked for {$student['student_name']} at $time.";
        header("Location: faculty_scan.php?session_id=$current_session_id");
        exit;
    }

    // Insert attendance
    $current_time = date('Y-m-d H:i:s');
    $insert = mysqli_prepare($conn,
        "INSERT INTO attendance_records (session_id, student_id, marked_at) 
         VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($insert, "iss", $current_session_id, $student_pk, $current_time);

    if (mysqli_stmt_execute($insert)) {
        $_SESSION['scan_success'] = "Attendance marked for " . htmlspecialchars($student['student_name']) . " (Roll: {$student['id_number']})";
    } else {
        $_SESSION['scan_error'] = "Database error: " . mysqli_error($conn);
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
        .card { margin-bottom: 20px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 10px; }
        #qr-reader { border: 3px solid #dee2e6 !important; border-radius: 10px; background: white; width: 100%; max-width: 600px; margin: 0 auto; }
        .scanner-active { animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(40,167,69,0.7); } 70% { box-shadow: 0 0 0 10px rgba(40,167,69,0); } 100% { box-shadow: 0 0 0 0 rgba(40,167,69,0); } }
        .current-time-display { background: linear-gradient(45deg,#6c757d,#495057); color: white; padding: 5px 10px; border-radius: 20px; }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <!-- Back Button + Manual Attendance Link (corrected spelling) -->
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <a href="faculty_dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i> Back to Dashboard</a>
                <a href="mark_attendance.php" class="btn btn-outline-primary"><i class="fas fa-pen-alt me-2"></i> Manual Attendance</a>
            </div>

            <!-- Page Header -->
            <div class="card shadow-lg border-0 mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h2 class="mb-0"><i class="fas fa-qrcode me-2"></i> QR Scanner</h2><p class="mb-0 opacity-75">Scan student QR → Confirm with Roll Number or Student ID</p></div>
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
                                <option value="0">-- Select a session --</option>
                                <?php
                                if (isset($active_sessions_result)) {
                                    mysqli_data_seek($active_sessions_result, 0);
                                    while ($session = mysqli_fetch_assoc($active_sessions_result)) {
                                        $selected = ($session_id == $session['session_id']) ? 'selected' : '';
                                        echo "<option value='{$session['session_id']}' $selected>" . htmlspecialchars($session['subject_code'] . ' - ' . $session['subject_name']) . " (Section {$session['section_targeted']} - {$session['class_type']})</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <?php if ($session_id > 0): ?>
                                <a href="stop_session.php?session_id=<?php echo $session_id; ?>" class="btn btn-danger w-100" onclick="return confirm('Stop this session? Students cannot mark after stopping.')"><i class="fas fa-stop-circle me-2"></i> Stop Session</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($session_id > 0 && $session_info): ?>
                <!-- Session Info -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-success">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3"><h6><i class="fas fa-book me-2 text-primary"></i> Subject</h6><p class="fw-bold"><?php echo htmlspecialchars($session_info['subject_code'] . ' - ' . $session_info['subject_name']); ?></p></div>
                                    <div class="col-md-2"><h6><i class="fas fa-users me-2 text-success"></i> Section</h6><p class="fw-bold">Section <?php echo htmlspecialchars($session_info['section_targeted']); ?></p></div>
                                    <div class="col-md-2"><h6><i class="fas fa-chalkboard me-2 text-warning"></i> Type</h6><p class="fw-bold"><?php echo htmlspecialchars($session_info['class_type']); ?></p></div>
                                    <div class="col-md-2"><h6><i class="fas fa-clock me-2 text-info"></i> Started</h6><p class="fw-bold"><?php echo date('h:i A', strtotime($session_info['created_at'] ?? 'now')); ?></p></div>
                                    <div class="col-md-3"><h6><i class="fas fa-hourglass-half me-2 text-danger"></i> Duration</h6><p class="fw-bold" id="session-duration">--:--</p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QR Scanner Only (No manual input form) -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow-lg border-0 h-100">
                            <div class="card-header bg-dark text-white"><h4 class="mb-0"><i class="fas fa-camera me-2"></i> Scan QR Code</h4></div>
                            <div class="card-body text-center">
                                <div id="qr-reader"></div>
                                <div id="qr-reader-results" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card shadow-lg border-0 mb-4">
                            <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> How to use</h5></div>
                            <div class="card-body">
                                <ol class="list-unstyled">
                                    <li>1. Allow camera access</li>
                                    <li>2. Scan student QR code</li>
                                    <li>3. In the popup, enter Roll Number <strong>OR</strong> Student ID</li>
                                    <li>4. Attendance marked automatically if match</li>
                                </ol>
                            </div>
                        </div>
                        <div class="card shadow-lg border-0">
                            <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Attendance</h5></div>
                            <div class="card-body" style="max-height: 320px; overflow-y: auto;">
                                <?php
                                $recent_query = mysqli_prepare($conn,
                                    "SELECT ar.marked_at, s.student_name, s.id_number 
                                     FROM attendance_records ar
                                     JOIN students s ON ar.student_id = s.student_id
                                     WHERE ar.session_id = ?
                                     ORDER BY ar.marked_at DESC LIMIT 8");
                                mysqli_stmt_bind_param($recent_query, "i", $session_id);
                                mysqli_stmt_execute($recent_query);
                                $recent_result = mysqli_stmt_get_result($recent_query);
                                if ($recent_result && mysqli_num_rows($recent_result) > 0):
                                    while ($rec = mysqli_fetch_assoc($recent_result)): ?>
                                        <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                                            <div><strong><?php echo htmlspecialchars($rec['id_number']); ?></strong><br><small><?php echo htmlspecialchars($rec['student_name']); ?></small></div>
                                            <div class="text-end"><small><?php echo date('h:i A', strtotime($rec['marked_at'])); ?></small></div>
                                        </div>
                                    <?php endwhile;
                                else: ?>
                                    <div class="text-center py-4 text-muted">No attendance marked yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($session_id == 0): ?>
                <div class="card shadow-lg border-0 text-center py-5"><i class="fas fa-qrcode fa-4x text-muted mb-4"></i><h3 class="text-muted">No Active Session Selected</h3><p>Please select a session from the dropdown or start a new one from the dashboard.</p><a href="faculty_dashboard.php" class="btn btn-primary">Start New Session</a></div>
            <?php else: ?>
                <div class="card shadow-lg border-0 text-center py-5"><i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i><h3 class="text-warning">Session Not Available</h3><p>This session may have ended or is no longer active.</p><a href="faculty_dashboard.php" class="btn btn-primary">Back to Dashboard</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function updateCurrentTime() { document.getElementById('current-time').textContent = new Date().toLocaleTimeString('en-IN', { hour:'2-digit', minute:'2-digit', hour12:true }); }
    setInterval(updateCurrentTime, 1000);
    <?php if ($session_id > 0): ?>
    let html5QrcodeScanner = null;
    function onScanSuccess(decodedText) {
        if (html5QrcodeScanner) html5QrcodeScanner.pause();
        playBeepSound();
        const manualInput = prompt("Enter Roll Number (id_number) OR Student ID (student_id) to confirm:");
        if (!manualInput) { resumeScanner(); return; }
        const formData = new FormData();
        formData.append('scanned_qr', decodedText);
        formData.append('entered_student_id', manualInput);
        formData.append('session_id', <?php echo $session_id; ?>);
        formData.append('mark_attendance', '1');
        fetch('faculty_scan.php', { method: 'POST', body: formData })
            .then(() => window.location.reload())
            .catch(() => { showAlert('danger','Failed to mark attendance'); resumeScanner(); });
    }
    function onScanFailure(error) { console.log(error); }
    function playBeepSound() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain); gain.connect(audioCtx.destination);
            osc.frequency.value = 800; osc.type = 'sine';
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
            osc.start(); osc.stop(audioCtx.currentTime + 0.5);
        } catch(e) { console.log("Beep not supported"); }
    }
    function resumeScanner() { if(html5QrcodeScanner) html5QrcodeScanner.resume(); }
    document.addEventListener('DOMContentLoaded', function() {
        html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio:1.0, showTorchButtonIfSupported:true });
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    });
    <?php endif; ?>
    <?php if (isset($_SESSION['scan_success'])): ?>
        showAlert('success', '<?php echo addslashes($_SESSION['scan_success']); unset($_SESSION['scan_success']); ?>');
    <?php endif; ?>
    <?php if (isset($_SESSION['scan_error'])): ?>
        showAlert('danger', '<?php echo addslashes($_SESSION['scan_error']); unset($_SESSION['scan_error']); ?>');
    <?php endif; ?>
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-4`;
        alertDiv.style.zIndex = '1050';
        alertDiv.style.maxWidth = '400px';
        alertDiv.innerHTML = `<div class="d-flex align-items-center"><i class="fas fa-${type==='success'?'check-circle':'exclamation-triangle'} fa-lg me-3"></i><div class="flex-grow-1">${message}</div><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
        document.body.appendChild(alertDiv);
        setTimeout(() => { if(alertDiv.parentNode) new bootstrap.Alert(alertDiv).close(); }, 5000);
    }
</script>
</body>
</html>
<?php
if (ob_get_level() > 0) ob_end_flush();
?>
