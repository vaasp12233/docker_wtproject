<?php
// faculty_dashboard.php - Fixed for Render + Aiven

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

// ==================== Clean buffer before output ====================
if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

// ==================== Display messages from stop_session ====================
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// ==================== Get faculty details ====================
$faculty = null;
$faculty_query = "SELECT * FROM faculty WHERE faculty_id = ?";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);

if ($faculty_stmt) {
    mysqli_stmt_bind_param($faculty_stmt, "s", $faculty_id);
    mysqli_stmt_execute($faculty_stmt);
    $result = mysqli_stmt_get_result($faculty_stmt);
    
    if (mysqli_num_rows($result) == 0) {
        session_destroy();
        if (ob_get_length() > 0) {
            ob_end_clean();
        }
        header('Location: login.php?error=faculty_not_found');
        exit;
    }
    
    $faculty = mysqli_fetch_assoc($result);
    mysqli_stmt_close($faculty_stmt);
} else {
    die("Database error: " . mysqli_error($conn));
}

// ==================== Check if faculty has custom password ====================
$has_custom_password = !empty($faculty['password']);

// ==================== Get subjects for dropdown ====================
$subjects_result = null;
$subjects_query = "SELECT * FROM subjects ORDER BY subject_name";
$subjects_result = mysqli_query($conn, $subjects_query);
if (!$subjects_result) {
    $subjects_result = null;
}

// ==================== Check what time column exists in sessions table ====================
$check_columns = mysqli_query($conn, "SHOW COLUMNS FROM sessions");
$has_created_at = false;
$has_start_time = false;

while ($column = mysqli_fetch_assoc($check_columns)) {
    if ($column['Field'] == 'created_at') $has_created_at = true;
    if ($column['Field'] == 'start_time') $has_start_time = true;
}

// Use the correct time column
if ($has_created_at) {
    $time_column = "created_at";
} elseif ($has_start_time) {
    $time_column = "start_time";
} else {
    $time_column = "session_id"; // Fallback to sort by ID
}

// ==================== Get recent sessions for stats ====================
$recent_sessions_result = null;
$recent_sessions_query = "SELECT s.*, sub.subject_code, sub.subject_name 
                         FROM sessions s 
                         JOIN subjects sub ON s.subject_id = sub.subject_id 
                         WHERE s.faculty_id = ? 
                         ORDER BY s.$time_column DESC LIMIT 5";
$recent_sessions_stmt = mysqli_prepare($conn, $recent_sessions_query);

if ($recent_sessions_stmt) {
    mysqli_stmt_bind_param($recent_sessions_stmt, "s", $faculty_id);
    mysqli_stmt_execute($recent_sessions_stmt);
    $recent_sessions_result = mysqli_stmt_get_result($recent_sessions_stmt);
    mysqli_stmt_close($recent_sessions_stmt);
}

// ==================== Get stats ====================
$total_sessions = 0;
$sessions_count_query = "SELECT COUNT(*) as count FROM sessions WHERE faculty_id = ?";
$sessions_count_stmt = mysqli_prepare($conn, $sessions_count_query);
if ($sessions_count_stmt) {
    mysqli_stmt_bind_param($sessions_count_stmt, "s", $faculty_id);
    mysqli_stmt_execute($sessions_count_stmt);
    $sessions_count_result = mysqli_stmt_get_result($sessions_count_stmt);
    $sessions_count_data = mysqli_fetch_assoc($sessions_count_result);
    $total_sessions = $sessions_count_data['count'] ?? 0;
    mysqli_stmt_close($sessions_count_stmt);
}

$total_attendance = 0;
$attendance_count_query = "SELECT COUNT(*) as total FROM attendance_records ar 
                          JOIN sessions s ON ar.session_id = s.session_id 
                          WHERE s.faculty_id = ?";
$attendance_count_stmt = mysqli_prepare($conn, $attendance_count_query);
if ($attendance_count_stmt) {
    mysqli_stmt_bind_param($attendance_count_stmt, "s", $faculty_id);
    mysqli_stmt_execute($attendance_count_stmt);
    $attendance_count_result = mysqli_stmt_get_result($attendance_count_stmt);
    $attendance_count_data = mysqli_fetch_assoc($attendance_count_result);
    $total_attendance = $attendance_count_data['total'] ?? 0;
    mysqli_stmt_close($attendance_count_stmt);
}

$active_count = 0;
$active_sessions_query = "SELECT COUNT(*) as count FROM sessions WHERE faculty_id = ? AND is_active = 1";
$active_sessions_stmt = mysqli_prepare($conn, $active_sessions_query);
if ($active_sessions_stmt) {
    mysqli_stmt_bind_param($active_sessions_stmt, "s", $faculty_id);
    mysqli_stmt_execute($active_sessions_stmt);
    $active_sessions_result = mysqli_stmt_get_result($active_sessions_stmt);
    $active_sessions_data = mysqli_fetch_assoc($active_sessions_result);
    $active_count = $active_sessions_data['count'] ?? 0;
    mysqli_stmt_close($active_sessions_stmt);
}

// ==================== Set page title ====================
$page_title = "Faculty Dashboard";

// Include header after all processing is done
include 'header.php';
?>

<div class="container-fluid mt-4">
    <!-- Welcome Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white shadow-lg">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="mb-1">Welcome, <?php echo htmlspecialchars($faculty['faculty_name']); ?>!</h3>
                            <p class="mb-0 opacity-75">
                                <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($faculty['faculty_email']); ?>
                                | <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($faculty['faculty_department']); ?>
                                <?php if (!$has_custom_password): ?>
                                    | <span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i> Default Password</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="bg-white text-primary d-inline-block px-4 py-2 rounded-pill">
                                <i class="fas fa-clock me-2"></i>
                                <span id="currentTime"><?php echo date('h:i A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if (!empty($success_message)): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Password Warning -->
    <?php if (!$has_custom_password): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Security Notice:</strong> You're using the default password. 
                <a href="change_password.php" class="alert-link ms-1">Change it now</a> for security.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Feature Cards -->
    <div class="row mb-4">
        <!-- 360° Students View Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <a href="360_students_attendance.php" class="text-decoration-none card-link" aria-label="360° Students Attendance View">
                <div class="card border-left-warning shadow-sm h-100 transition-all hover-lift">
                    <div class="card-body p-3 p-md-4">
                        <div class="row align-items-center g-3">
                            <div class="col-8">
                                <div class="text-xs fw-semibold text-warning text-uppercase mb-1 tracking-wide">
                                    <i class="fas fa-users fa-xs me-1"></i>
                                    360° Students View
                                </div>
                                <h2 class="h5 mb-0 fw-bold text-gray-800">
                                    Complete Attendance
                                </h2>
                                <div class="mt-2">
                                    <span class="badge bg-warning bg-opacity-10 text-warning small">
                                        <i class="fas fa-eye me-1 fa-xs"></i>
                                        All Students
                                    </span>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon-wrapper bg-warning bg-opacity-10 rounded-circle p-3 d-inline-flex">
                                    <i class="fas fa-users fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 pt-0">
                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <span>View all students</span>
                            <i class="fas fa-chevron-right fa-xs"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- Attendance Analytics Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <a href="attendance_viewer_faculty.php" class="text-decoration-none card-link" aria-label="Go to Attendance Analytics Dashboard">
                <div class="card border-left-info shadow-sm h-100 transition-all hover-lift">
                    <div class="card-body p-3 p-md-4">
                        <div class="row align-items-center g-3">
                            <div class="col-8">
                                <div class="text-xs fw-semibold text-info text-uppercase mb-1 tracking-wide">
                                    <i class="fas fa-chart-line fa-xs me-1"></i>
                                    Attendance Analytics
                                </div>
                                <h2 class="h5 mb-0 fw-bold text-gray-800">
                                    View All Records
                                </h2>
                                <div class="mt-2">
                                    <span class="badge bg-info bg-opacity-10 text-info small">
                                        <i class="fas fa-chart-bar me-1 fa-xs"></i>
                                        Interactive Dashboard
                                    </span>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <div class="icon-wrapper bg-info bg-opacity-10 rounded-circle p-3 d-inline-flex">
                                    <i class="fas fa-chart-bar fa-2x text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 pt-0">
                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <span>Click to explore</span>
                            <i class="fas fa-chevron-right fa-xs"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- Stats Cards -->
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-0 shadow h-100">
                <div class="card-body text-center py-4">
                    <div class="stat-icon bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
                    </div>
                    <h2 class="text-primary mb-1"><?php echo $total_sessions; ?></h2>
                    <p class="text-muted mb-0">Total Sessions</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-0 shadow h-100">
                <div class="card-body text-center py-4">
                    <div class="stat-icon bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-user-check fa-2x"></i>
                    </div>
                    <h2 class="text-success mb-1"><?php echo $total_attendance; ?></h2>
                    <p class="text-muted mb-0">Total Records</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-0 shadow h-100">
                <div class="card-body text-center py-4">
                    <div class="stat-icon bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-play-circle fa-2x"></i>
                    </div>
                    <h2 class="text-warning mb-1"><?php echo $active_count; ?></h2>
                    <p class="text-muted mb-0">Active Sessions</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="row">
        <!-- Session Form -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-play-circle me-2"></i> Start New Attendance Session</h5>
                    <p class="mb-0 opacity-75">Begin tracking attendance for your class</p>
                </div>
                <div class="card-body">
                    <form action="start_session.php" method="POST" id="sessionForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                                <select class="form-select" name="subject_id" required>
                                    <option value="" disabled selected>Select a subject</option>
                                    <?php 
                                    // Reset pointer and fetch subjects
                                    if ($subjects_result && mysqli_num_rows($subjects_result) > 0) {
                                        mysqli_data_seek($subjects_result, 0);
                                        while ($subject = mysqli_fetch_assoc($subjects_result)): 
                                    ?>
                                    <option value="<?php echo $subject['subject_id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                                    </option>
                                    <?php 
                                        endwhile;
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">Choose the subject for this session</small>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Section <span class="text-danger">*</span></label>
                                <select class="form-select" name="section_targeted" required>
                                    <option value="A">Section A</option>
                                    <option value="B">Section B</option>
                                    <option value="C">Section C</option>
                                    <option value="D">Section D</option>
                                    <option value="E">Section E</option>
                                </select>
                                <small class="text-muted">Target student section</small>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Class Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="class_type" required>
                                    <option value="Normal">Normal Class</option>
                                    <option value="Lab">Lab Session</option>
                                    <option value="Tutorial">Tutorial</option>
                                    <option value="Project">Project</option>
                                </select>
                                <small class="text-muted">Type of class session</small>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <?php if ($has_custom_password): ?>
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-play me-2"></i> Start Attendance Session
                                </button>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-lock me-2"></i>
                                    <strong>Set a password first!</strong> You need to set a custom password before starting sessions.
                                    <div class="mt-2">
                                        <a href="change_password.php" class="btn btn-warning">
                                            <i class="fas fa-key me-1"></i> Set Password Now
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Recent Sessions -->
        <div class="col-lg-4">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Sessions</h5>
                    <p class="mb-0 opacity-75">Your latest attendance sessions</p>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if ($recent_sessions_result && mysqli_num_rows($recent_sessions_result) > 0): ?>
                        <div class="recent-sessions">
                            <?php 
                            mysqli_data_seek($recent_sessions_result, 0); // Reset pointer
                            while ($session = mysqli_fetch_assoc($recent_sessions_result)): 
                                $status_class = $session['is_active'] ? 'border-start border-success' : 'border-start border-secondary';
                                $status_text = $session['is_active'] ? 'Active' : 'Ended';
                                $status_bg = $session['is_active'] ? 'success' : 'secondary';
                                
                                // Get attendance count for this session
                                $att_count_query = mysqli_query($conn, 
                                    "SELECT COUNT(*) as count FROM attendance_records 
                                     WHERE session_id = '{$session['session_id']}'");
                                $att_count = $att_count_query ? mysqli_fetch_assoc($att_count_query)['count'] : 0;
                                
                                // Safe time display
                                $display_time = 'N/A';
                                if ($has_created_at && !empty($session['created_at'])) {
                                    $display_time = date('h:i A', strtotime($session['created_at']));
                                } elseif ($has_start_time && !empty($session['start_time'])) {
                                    $display_time = date('h:i A', strtotime($session['start_time']));
                                }
                            ?>
                            <div class="session-item p-3 mb-3 <?php echo $status_class; ?> bg-light rounded">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($session['subject_code'] ?? 'N/A'); ?></h6>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-users me-1"></i> Section <?php echo htmlspecialchars($session['section_targeted'] ?? 'N/A'); ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-clock me-1"></i> <?php echo $display_time; ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-chalkboard me-1"></i> <?php echo htmlspecialchars($session['class_type'] ?? 'N/A'); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $status_bg; ?> mb-2">
                                            <i class="fas fa-circle fa-xs"></i> <?php echo $status_text; ?>
                                        </span>
                                        <div class="mb-2">
                                            <small class="text-primary fw-bold">
                                                <i class="fas fa-user-check"></i> <?php echo $att_count; ?>
                                            </small>
                                        </div>
                                        <?php if ($session['is_active']): ?>
                                        <div>
                                            <a href="faculty_scan.php?session_id=<?php echo $session['session_id']; ?>" 
                                               class="btn btn-sm btn-success mb-1" style="min-width: 80px;">
                                                <i class="fas fa-qrcode"></i> Scan
                                            </a>
                                            <a href="stop_session.php?session_id=<?php echo $session['session_id']; ?>" 
                                               class="btn btn-sm btn-danger" style="min-width: 80px;"
                                               onclick="return confirm('Stop this session? Students can no longer mark attendance.')">
                                                <i class="fas fa-stop"></i> Stop
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No sessions yet</h5>
                            <p class="text-muted">Start your first attendance session</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($recent_sessions_result && mysqli_num_rows($recent_sessions_result) > 0): ?>
                        <div class="text-center mt-3">
                            <a href="manage_sessions.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-list me-1"></i> View All Sessions
                            </a>
                            <?php if ($active_count > 0): ?>
                            <a href="faculty_scan.php" class="btn btn-success btn-sm ms-2">
                                <i class="fas fa-qrcode me-1"></i> Go to Scanner
                            </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'footer.php';
?>

<script>
// Update current time
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

// Initialize time and update every minute
updateTime();
setInterval(updateTime, 60000);

// Session form validation
document.addEventListener('DOMContentLoaded', function() {
    const sessionForm = document.getElementById('sessionForm');
    if (sessionForm) {
        sessionForm.addEventListener('submit', function(e) {
            <?php if (!$has_custom_password): ?>
            e.preventDefault();
            alert('Please set a custom password before starting sessions.');
            window.location.href = 'change_password.php';
            return false;
            <?php endif; ?>
        });
    }
    
    // Prevent back button from going back to login if logged in
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            // Page was loaded from cache (like when using back button)
            // Refresh to get fresh data
            window.location.reload();
        }
    });
});

// Add history state to prevent back button issues
history.pushState(null, null, location.href);
window.onpopstate = function () {
    history.go(1);
};
</script>
