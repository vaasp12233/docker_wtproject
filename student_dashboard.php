<?php
// student_dashboard.php - Render + Aiven + GitHub Compatible

// ==================== Start output buffering ====================
if (!ob_get_level()) {
    ob_start();
}

// ==================== Configure session for Render ====================
ini_set('session.save_path', '/tmp');
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);

// ==================== Start session ====================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== Include database config ====================
require_once 'config.php';

// ==================== Security check ====================
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

$student_id = $_SESSION['student_id'] ?? null;
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

// ==================== Database operations with prepared statements ====================
$student = null;
$attendance_result = null;
$total_possible_sessions = 0;
$theory_sessions = 0;
$lab_sessions = 0;
$subjects_data = [];

// DEBUG: Check database connection
if (!$conn) {
    die("<div class='alert alert-danger'>Database connection failed: " . mysqli_connect_error() . "</div>");
}

// Get student details
$student_query = "SELECT * FROM students WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $student_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$student) {
        die("<div class='alert alert-danger'>Student not found in database for ID: $student_id</div>");
    }
} else {
    die("<div class='alert alert-danger'>Failed to prepare student query: " . mysqli_error($conn) . "</div>");
}

// ==================== GET SESSIONS FROM SUBJECTS TABLE ====================
// Query to get all subjects and their target_sessions
$subjects_query = "SELECT subject_id, subject_name, subject_code, target_sessions FROM subjects";
$subjects_result = mysqli_query($conn, $subjects_query);

if ($subjects_result) {
    if (mysqli_num_rows($subjects_result) > 0) {
        while ($subject = mysqli_fetch_assoc($subjects_result)) {
            $subject_name = strtolower($subject['subject_name']);
            $subject_code = strtolower($subject['subject_code']);
            $target_sessions = intval($subject['target_sessions']);

            // Check if it's a lab (based on common patterns)
            $is_lab = false;
            if (strpos($subject_name, 'lab') !== false || 
                strpos($subject_name, 'practical') !== false ||
                strpos($subject_code, '_lab') !== false ||
                strpos($subject_code, 'lab_') !== false ||
                stripos($subject_name, 'laboratory') !== false) {
                $is_lab = true;
                $lab_sessions += $target_sessions;
            } else {
                $theory_sessions += $target_sessions;
            }

            // Store subject data for display
            $subjects_data[] = [
                'id' => $subject['subject_id'],
                'name' => $subject['subject_name'],
                'code' => $subject['subject_code'],
                'target' => $target_sessions,
                'is_lab' => $is_lab
            ];

            $total_possible_sessions += $target_sessions;
        }
    } else {
        // Fallback to default values if no subjects found
        $theory_sessions = 5 * 15;  // 5 subjects × 15 weeks
        $lab_sessions = 3 * 15;     // 3 labs × 15 weeks
        $total_possible_sessions = $theory_sessions + $lab_sessions;
        
        echo "<div class='alert alert-warning'>No subjects found in database. Using default values.</div>";
    }
    mysqli_free_result($subjects_result);
} else {
    die("<div class='alert alert-danger'>Failed to query subjects: " . mysqli_error($conn) . "</div>");
}

// Get student's attendance
$attendance_query = "SELECT ar.*, 
                    COALESCE(s.subject_code, 'Unknown Subject') as subject_code, 
                    COALESCE(s.subject_name, 'Unknown Subject') as subject_name, 
                    ses.session_id, 
                    ses.start_time, 
                    COALESCE(ses.class_type, 'normal') as class_type, 
                    COALESCE(ses.lab_type, 'lecture') as lab_type
                    FROM attendance_records ar 
                    LEFT JOIN sessions ses ON ar.session_id = ses.session_id
                    LEFT JOIN subjects s ON ses.subject_id = s.subject_id
                    WHERE ar.student_id = ? 
                    AND ar.session_id IS NOT NULL
                    ORDER BY ar.marked_at DESC LIMIT 10";
                    
$stmt2 = mysqli_prepare($conn, $attendance_query);
if ($stmt2) {
    mysqli_stmt_bind_param($stmt2, "s", $student_id);
    mysqli_stmt_execute($stmt2);
    $attendance_result = mysqli_stmt_get_result($stmt2);
    mysqli_stmt_close($stmt2);
} else {
    echo "<div class='alert alert-warning'>Failed to prepare attendance query: " . mysqli_error($conn) . "</div>";
}

// ==================== CHECK IF GENDER IS SET ====================
// If gender is not set, redirect to gender question page
if (empty($student['gender'])) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: set_gender.php');
    exit;
}

// ==================== Calculate Attendance Statistics ====================
$total_attendance = 0;
$theory_attendance = 0;
$lab_attendance = 0;

if ($attendance_result && mysqli_num_rows($attendance_result) > 0) {
    mysqli_data_seek($attendance_result, 0);
    while ($record = mysqli_fetch_assoc($attendance_result)) {
        $class_type = $record['class_type'] ?? 'normal';
        $lab_type = $record['lab_type'] ?? 'lecture';
        $subject_name = strtolower($record['subject_name'] ?? '');
        $subject_code = strtolower($record['subject_code'] ?? '');

        // Check if it's a lab session - using multiple criteria
        $is_lab = false;
        if ($class_type === 'lab') {
            $is_lab = true;
        } elseif (strpos($subject_name, 'lab') !== false || 
                  strpos($subject_name, 'practical') !== false ||
                  strpos($subject_code, '_lab') !== false ||
                  strpos($subject_code, 'lab_') !== false ||
                  stripos($subject_name, 'laboratory') !== false) {
            $is_lab = true;
        }

        if ($is_lab) {
            $lab_attendance++;
        } else {
            $theory_attendance++;
        }
    }

    // Total attendance = theory sessions + lab sessions
    $total_attendance = $theory_attendance + $lab_attendance;

    // Reset pointer for later use
    mysqli_data_seek($attendance_result, 0);
}

// ==================== GET SESSIONS HAPPENED ====================
// Count how many sessions have actually happened (past sessions)
$sessions_happened = 0;
$theory_sessions_happened = 0;
$lab_sessions_happened = 0;

$happened_query = "SELECT 
                    ses.session_id,
                    COALESCE(ses.class_type, 'normal') as class_type,
                    COALESCE(ses.lab_type, 'lecture') as lab_type,
                    sub.subject_name,
                    sub.subject_code
                  FROM sessions ses 
                  LEFT JOIN subjects sub ON ses.subject_id = sub.subject_id 
                  WHERE ses.start_time <= NOW() 
                  AND ses.session_id IS NOT NULL";
$happened_result = mysqli_query($conn, $happened_query);

if ($happened_result) {
    if (mysqli_num_rows($happened_result) > 0) {
        while ($session = mysqli_fetch_assoc($happened_result)) {
            // Check if it's a lab session
            $is_lab = false;
            $subject_name = strtolower($session['subject_name'] ?? '');
            $subject_code = strtolower($session['subject_code'] ?? '');

            if ($session['class_type'] === 'lab') {
                $is_lab = true;
            } elseif (strpos($subject_name, 'lab') !== false || 
                      strpos($subject_name, 'practical') !== false ||
                      strpos($subject_code, '_lab') !== false ||
                      strpos($subject_code, 'lab_') !== false ||
                      stripos($subject_name, 'laboratory') !== false) {
                $is_lab = true;
            }

            if ($is_lab) {
                $lab_sessions_happened++;
            } else {
                $theory_sessions_happened++;
            }
        }

        // Total sessions happened = theory sessions + lab sessions
        $sessions_happened = $theory_sessions_happened + $lab_sessions_happened;
    }
    mysqli_free_result($happened_result);
}

// Debug output to see what's happening
echo "<!-- DEBUG INFO:
Total Possible Sessions: $total_possible_sessions
Theory Sessions: $theory_sessions
Lab Sessions: $lab_sessions
Total Attendance: $total_attendance
Sessions Happened: $sessions_happened
Theory Sessions Happened: $theory_sessions_happened
Lab Sessions Happened: $lab_sessions_happened
-->";

// ==================== CALCULATE ATTENDANCE PERCENTAGE ====================
if ($sessions_happened == 0) {
    $attendance_percentage = 0;
    $display_percentage = 0;
    $attendance_status = "No Sessions Yet";
    $attendance_class = "secondary";
} else {
    // Attendance % = (sessions attended / sessions happened) × 100
    $attendance_percentage = round(($total_attendance / $sessions_happened) * 100, 1);
    
    // Cap it at 100% for display
    $display_percentage = min(100, $attendance_percentage);

    // Determine attendance status
    if ($attendance_percentage >= 85) {
        $attendance_status = "Excellent";
        $attendance_class = "success";
    } elseif ($attendance_percentage >= 75) {
        $attendance_status = "Good";
        $attendance_class = "primary";
    } elseif ($attendance_percentage >= 60) {
        $attendance_status = "Average";
        $attendance_class = "warning";
    } elseif ($attendance_percentage >= 40) {
        $attendance_status = "Poor";
        $attendance_class = "danger";
    } else {
        $attendance_status = "Very Poor";
        $attendance_class = "dark";
    }
}

// ==================== Calculate 75% Attendance Predictor ====================
$sessions_for_75_percent = 0;
$remaining_for_75_percent = 0;

if ($total_possible_sessions > 0) {
    // Sessions needed for 75% attendance
    $sessions_for_75_percent = ceil($total_possible_sessions * 0.75);
    
    // How many more sessions needed from current
    $remaining_for_75_percent = max(0, $sessions_for_75_percent - $total_attendance);
}

// ==================== Format Student ID Display ====================
$id_number = $student['id_number'] ?? $student_id;
$year_field = $student['year'] ?? '';

// Format year as E2 if year has 2 as input
$year_display = "";
if (!empty($year_field)) {
    if ($year_field == '2') {
        $year_display = "E2";
    } elseif (strlen($year_field) == 4) {
        $last_two = substr($year_field, -2);
        $year_display = "E" . $last_two;
    } else {
        $year_display = "E" . $year_field;
    }
}

// Get QR code path
$qr_path = "qrcodes/student_" . $student_id . ".png";

$page_title = "Student Dashboard";
include 'header.php';

// Determine gender and set avatar
$gender = strtolower($student['gender'] ?? 'male');
$avatar_class = ($gender === 'female') ? 'female-avatar' : 'male-avatar';
$default_avatar = 'default.png';
?>

<!-- Rest of your HTML/CSS remains the same -->
<style>
    /* Your existing CSS styles */
    /* ... */
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
                    $profile_pic = 'uploads/profiles/' . $default_avatar;
                    if (file_exists($profile_pic)) {
                        $profile_src = $profile_pic;
                    } else {
                        $profile_src = 'uploads/profiles/default.png';
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($profile_src); ?>" 
                         class="rounded-circle profile-img border" 
                         alt="Profile Picture"
                         onerror="this.onerror=null; this.src='uploads/profiles/default.png';">
                </div>

                <h5 class="card-title"><?php echo htmlspecialchars($student['student_name'] ?? 'Student'); ?></h5>
                <p class="card-text text-muted">
                    <i class="fas fa-id-card me-1"></i> Student ID: <?php echo htmlspecialchars($student_id); ?><br>
                    <i class="fas fa-hashtag me-1"></i> ID Number: <?php echo htmlspecialchars($id_number); ?><br>
                    
                    <?php if (!empty($year_display)): ?>
                    <i class="fas fa-calendar-alt me-1"></i> Year: <?php echo htmlspecialchars($year_display); ?><br>
                    <?php endif; ?>
                    
                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($student['student_email'] ?? 'N/A'); ?><br>
                    <i class="fas fa-users me-1"></i> Section <?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?><br>
                    <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($student['student_department'] ?? 'N/A'); ?><br>
                    <i class="fas fa-<?php echo ($gender === 'female') ? 'venus' : 'mars'; ?> me-1"></i>
                    <?php echo ucfirst(htmlspecialchars($gender)); ?>
                </p>
                
                <!-- Display session info in profile -->
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Total Semester Sessions: <strong><?php echo $total_possible_sessions; ?></strong><br>
                        (<strong><?php echo $theory_sessions; ?></strong> Theory + <strong><?php echo $lab_sessions; ?></strong> Lab)
                    </small>
                </div>
            </div>
        </div>

        <!-- QR Code Card -->
        <div class="card shadow-lg border-0 mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>My QR Code</h5>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($student['qr_content'])): ?>
                    <div class="qr-container">
                        <div id="qrcode"></div>
                    </div>
                    <p class="mt-2 small text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Show this QR code during class to mark attendance
                    </p>
                    <div class="d-grid gap-2">
                        <button onclick="downloadQR()" class="btn qr-download-btn">
                            <i class="fas fa-download me-1"></i> Download QR Code
                        </button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        QR Code not generated yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Dashboard Content -->
    <div class="col-md-8">
        <!-- Quick Stats Row -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-card-1">
                    <div class="stat-number">
                        <i class="fas fa-calendar-check"></i> <?php echo $total_attendance; ?>
                    </div>
                    <h6 class="mb-0">Classes Attended</h6>
                    <small>Total: <?php echo $theory_attendance; ?> theory + <?php echo $lab_attendance; ?> labs</small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-card-2">
                    <div class="stat-number">
                        <i class="fas fa-percentage"></i> <?php echo $display_percentage; ?>%
                    </div>
                    <h6 class="mb-0">Attendance %</h6>
                    <div class="small mb-2">(<?php echo $total_attendance; ?>/<?php echo $sessions_happened; ?> sessions)</div>
                    <span class="badge bg-<?php echo $attendance_class; ?> attendance-status-badge">
                        <?php echo $attendance_status; ?>
                    </span>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-card-3">
                    <div class="stat-number">
                        <i class="fas fa-clock"></i> <span id="current-time"><?php echo date('h:i A'); ?></span>
                    </div>
                    <h6 class="mb-0">Current Time</h6>
                    <small><?php echo date('l, F j, Y'); ?></small>
                </div>
            </div>
        </div>

        <!-- Attendance Progress Section -->
        <div class="card shadow-lg border-0 mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Attendance Details</h5>
            </div>
            <div class="card-body">
                <!-- Attendance Ratio Display -->
                <div class="attendance-ratio">
                    <?php echo $total_attendance; ?>/<?php echo $sessions_happened; ?> sessions
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6>Current Status: <span class="badge bg-<?php echo $attendance_class; ?>"><?php echo $attendance_status; ?></span></h6>
                        <div class="progress attendance-progress">
                            <div class="progress-bar bg-<?php echo $attendance_class; ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo min($display_percentage, 100); ?>%"
                                 aria-valuenow="<?php echo $display_percentage; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                        <div class="progress-percentage text-center">
                            <?php echo $display_percentage; ?>% (<?php echo $total_attendance; ?>/<?php echo $sessions_happened; ?> sessions)
                        </div>

                        <!-- Subjects Breakdown -->
                        <div class="subjects-breakdown mt-3">
                            <h6><i class="fas fa-book me-2"></i>Session Breakdown:</h6>
                            <div class="subject-item">
                                <span>Theory Sessions <span class="subject-badge">Subject</span></span>
                                <span>
                                    <strong><?php echo $theory_attendance; ?></strong> attended /
                                    <strong><?php echo $theory_sessions_happened; ?></strong> happened
                                </span>
                            </div>
                            <div class="subject-item">
                                <span>Lab Sessions <span class="lab-badge">Lab</span></span>
                                <span>
                                    <strong><?php echo $lab_attendance; ?></strong> attended /
                                    <strong><?php echo $lab_sessions_happened; ?></strong> happened
                                </span>
                            </div>
                            <div class="subject-item">
                                <span>Total Sessions</span>
                                <span><strong><?php echo $total_attendance; ?></strong>/<strong><?php echo $sessions_happened; ?></strong> sessions</span>
                            </div>

                            <!-- Display individual subjects from database -->
                            <?php if (!empty($subjects_data)): ?>
                            <div class="session-info mt-2">
                                <strong>Subjects Information:</strong>
                                <?php foreach ($subjects_data as $subject): ?>
                                <div style="font-size: 0.8rem; margin-top: 3px;">
                                    <?php echo htmlspecialchars($subject['code']) . ' - ' . htmlspecialchars($subject['name']); ?>
                                    (<?php echo $subject['target']; ?> sessions)
                                    <?php if ($subject['is_lab']): ?>
                                        <span class="lab-badge" style="font-size: 0.7rem;">Lab</span>
                                    <?php else: ?>
                                        <span class="subject-badge" style="font-size: 0.7rem;">Subject</span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <div class="session-info mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Note: Each lab session counts as 1 attendance mark.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-chart-pie me-2"></i>Semester Progress</h6>
                        <div class="subjects-breakdown">
                            <div class="subject-item">
                                <span>75% Requirement</span>
                                <span><strong><?php echo $sessions_for_75_percent; ?></strong> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Your Attendance</span>
                                <span><strong><?php echo $total_attendance; ?></strong> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Remaining to 75%</span>
                                <span><strong><?php echo $remaining_for_75_percent; ?></strong> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Weekly Goal</span>
                                <span><strong><?php echo ceil($remaining_for_75_percent / 8); ?></strong> weeks at 100%</span>
                            </div>
                        </div>

                        <!-- Detailed Semester Info -->
                        <div class="subjects-breakdown mt-3">
                            <h6><i class="fas fa-calculator me-2"></i>Semester Information:</h6>
                            <div class="subject-item">
                                <span>Total Sessions (Semester)</span>
                                <span><?php echo $total_possible_sessions; ?> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Theory Sessions</span>
                                <span><?php echo $theory_sessions; ?> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Lab Sessions</span>
                                <span><?php echo $lab_sessions; ?> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Already Attended</span>
                                <span><?php echo $total_attendance; ?> sessions</span>
                            </div>
                            <div class="subject-item">
                                <span>Remaining All</span>
                                <span><?php echo max(0, $total_possible_sessions - $total_attendance); ?> sessions</span>
                            </div>
                        </div>

                        <!-- Real-time Update Notice -->
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-sync-alt me-2"></i>
                            <strong>Auto-updating:</strong> Your attendance updates automatically as you attend classes.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 75% ATTENDANCE PREDICTOR CARD -->
        <div class="predictor-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-2">
                        <i class="fas fa-bullseye me-2"></i>75% Attendance Goal
                    </h4>
                    <p class="mb-0">Track your progress towards 75% attendance requirement</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="predictor-number">
                        <?php echo $remaining_for_75_percent; ?>
                    </div>
                    <div class="predictor-label">Sessions Needed</div>
                </div>
            </div>

            <div class="calculation-box mt-3">
                <div class="row">
                    <div class="col-md-6">
                        <div class="predictor-detail">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Current:</strong> <?php echo $total_attendance; ?> sessions attended
                        </div>
                        <div class="predictor-detail mt-2">
                            <i class="fas fa-flag me-2"></i>
                            <strong>Target:</strong> <?php echo $sessions_for_75_percent; ?> sessions for 75%
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="predictor-detail">
                            <i class="fas fa-calculator me-2"></i>
                            <strong>Calculation:</strong><br>
                            75% of <?php echo $total_possible_sessions; ?> = <?php echo $sessions_for_75_percent; ?> sessions
                        </div>
                    </div>
                </div>

                <!-- Progress towards 75% -->
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small>Progress to 75%:</small>
                        <small><?php 
                            $progress_to_75 = ($sessions_for_75_percent > 0) ? round(($total_attendance / $sessions_for_75_percent) * 100, 1) : 0;
                            echo min(100, $progress_to_75); 
                        ?>%</small>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-warning" 
                             role="progressbar" 
                             style="width: <?php 
                                $width = ($sessions_for_75_percent > 0) ? ($total_attendance / $sessions_for_75_percent) * 100 : 0;
                                echo min(100, $width); 
                             ?>%"
                             aria-valuenow="<?php echo min(100, $width); ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                        </div>
                    </div>
                </div>

                <!-- Encouragement Message -->
                <?php if ($remaining_for_75_percent > 0): ?>
                <div class="alert alert-light mt-3 mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Goal:</strong> Attend <?php echo $remaining_for_75_percent; ?> more sessions 
                    (<?php echo ceil($remaining_for_75_percent / 8); ?> weeks at 100% attendance)
                </div>
                <?php else: ?>
                <div class="alert alert-success mt-3 mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    <strong>Congratulations!</strong> You've already achieved 75% attendance requirement!
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rest of your HTML remains the same -->
        <!-- ... -->
    </div>
</div>

<script>
// Your JavaScript code remains the same
// ...
</script>

<?php 
include 'footer.php';

// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>