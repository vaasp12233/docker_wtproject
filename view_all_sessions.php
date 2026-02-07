<?php
// view_all_sessions.php - Display all sessions by faculty

session_start();
require_once 'config.php';

// ==================== Security check - Redirect if not logged in as faculty ====================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

// ==================== Get faculty details ====================
$faculty_query = "SELECT * FROM faculty WHERE faculty_id = ?";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "s", $faculty_id);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty = mysqli_fetch_assoc($faculty_stmt);
mysqli_stmt_close($faculty_stmt);

// ==================== Get faculty's allowed subjects ====================
$allowed_subjects_str = $faculty['allowed_subjects'] ?? '';
$allowed_subjects_array = [];

if (!empty($allowed_subjects_str)) {
    $allowed_subjects_array = array_map('trim', explode(',', $allowed_subjects_str));
    $allowed_subjects_array = array_filter($allowed_subjects_array);
}

$has_allowed_subjects = !empty($allowed_subjects_array);

// ==================== Check columns in sessions table ====================
$check_columns = mysqli_query($conn, "SHOW COLUMNS FROM sessions");
$has_created_at = false;
$has_start_time = false;
$has_lab_type = false;

while ($column = mysqli_fetch_assoc($check_columns)) {
    if ($column['Field'] == 'created_at') $has_created_at = true;
    if ($column['Field'] == 'start_time') $has_start_time = true;
    if ($column['Field'] == 'lab_type') $has_lab_type = true;
}

// Determine time column to use
if ($has_created_at) {
    $time_column = "created_at";
} elseif ($has_start_time) {
    $time_column = "start_time";
} else {
    $time_column = "session_id";
}

// ==================== Get all sessions for this faculty ====================
$sessions_data = [];

if ($has_allowed_subjects) {
    // Create placeholders for IN clause
    $placeholders = str_repeat('?,', count($allowed_subjects_array) - 1) . '?';
    
    $sessions_query = "SELECT s.*, sub.subject_code, sub.subject_name 
                      FROM sessions s 
                      JOIN subjects sub ON s.subject_id = sub.subject_id 
                      WHERE s.faculty_id = ? 
                      AND sub.subject_code IN ($placeholders)
                      ORDER BY s.$time_column DESC";
    
    $sessions_stmt = mysqli_prepare($conn, $sessions_query);
    
    if ($sessions_stmt) {
        $params = array_merge([$faculty_id], $allowed_subjects_array);
        $types = "s" . str_repeat('s', count($allowed_subjects_array));
        mysqli_stmt_bind_param($sessions_stmt, $types, ...$params);
        mysqli_stmt_execute($sessions_stmt);
        $sessions_result = mysqli_stmt_get_result($sessions_stmt);
        
        while ($session = mysqli_fetch_assoc($sessions_result)) {
            // Get attendance count for this session
            $att_count_query = mysqli_query($conn, 
                "SELECT COUNT(*) as count FROM attendance_records 
                 WHERE session_id = '{$session['session_id']}'");
            $att_count = $att_count_query ? mysqli_fetch_assoc($att_count_query)['count'] : 0;
            
            $session['attendance_count'] = $att_count;
            $sessions_data[] = $session;
        }
        mysqli_stmt_close($sessions_stmt);
    }
}

// ==================== Set page title ====================
$page_title = "All Sessions";

// Include header
include 'header.php';
?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white shadow">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="mb-1"><i class="fas fa-history me-2"></i> All Sessions</h3>
                            <p class="mb-0 opacity-75">Complete history of your attendance sessions</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <span class="badge bg-light text-primary fs-6">
                                Total: <?php echo count($sessions_data); ?> Sessions
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sessions Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow border-0">
                <div class="card-body">
                    <?php if (!empty($sessions_data)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Subject</th>
                                        <th>Section</th>
                                        <th>Type</th>
                                        <?php if ($has_lab_type): ?>
                                        <th>Lab Type</th>
                                        <?php endif; ?>
                                        <th>Date & Time</th>
                                        <th>Attendance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1;
                                    foreach ($sessions_data as $session): 
                                        $status_class = $session['is_active'] ? 'text-success' : 'text-secondary';
                                        $status_icon = $session['is_active'] ? 'fa-circle-play' : 'fa-circle-stop';
                                        $status_text = $session['is_active'] ? 'Active' : 'Ended';
                                        
                                        // Format time
                                        $display_time = 'N/A';
                                        if ($has_created_at && !empty($session['created_at'])) {
                                            $display_time = date('M d, Y h:i A', strtotime($session['created_at']));
                                        } elseif ($has_start_time && !empty($session['start_time'])) {
                                            $display_time = date('M d, Y h:i A', strtotime($session['start_time']));
                                        }
                                        
                                        // Lab type badge
                                        $lab_type_badge = '';
                                        if ($has_lab_type && !empty($session['lab_type'])) {
                                            $lab_type = $session['lab_type'];
                                            $badge_color = 'secondary';
                                            
                                            if ($lab_type == 'pre-lab') $badge_color = 'info';
                                            elseif ($lab_type == 'during-lab') $badge_color = 'success';
                                            elseif ($lab_type == 'post-lab') $badge_color = 'warning';
                                            elseif ($lab_type == 'lecture') $badge_color = 'primary';
                                            elseif ($lab_type == 'tutorial') $badge_color = 'info';
                                            
                                            $lab_type_badge = '<span class="badge bg-' . $badge_color . '">' . ucfirst($lab_type) . '</span>';
                                        }
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo $counter++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($session['subject_code'] ?? 'N/A'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($session['subject_name'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">Section <?php echo htmlspecialchars($session['section_targeted'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $class_type = $session['class_type'] ?? 'normal';
                                            $type_badge_color = ($class_type == 'lab') ? 'danger' : 'info';
                                            ?>
                                            <span class="badge bg-<?php echo $type_badge_color; ?>">
                                                <i class="fas fa-<?php echo ($class_type == 'lab') ? 'flask' : 'chalkboard'; ?> me-1"></i>
                                                <?php echo ucfirst($class_type); ?>
                                            </span>
                                        </td>
                                        <?php if ($has_lab_type): ?>
                                        <td><?php echo $lab_type_badge; ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <small><?php echo $display_time; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="fas fa-user-check me-1"></i>
                                                <?php echo $session['attendance_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="<?php echo $status_class; ?>">
                                                <i class="fas <?php echo $status_icon; ?> me-1"></i>
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Summary Stats -->
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="card bg-light border-0">
                                    <div class="card-body text-center">
                                        <h4 class="text-primary mb-1">
                                            <?php 
                                            $total_sessions = count($sessions_data);
                                            echo $total_sessions;
                                            ?>
                                        </h4>
                                        <p class="text-muted mb-0">Total Sessions</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light border-0">
                                    <div class="card-body text-center">
                                        <h4 class="text-success mb-1">
                                            <?php 
                                            $active_count = 0;
                                            foreach ($sessions_data as $session) {
                                                if ($session['is_active']) $active_count++;
                                            }
                                            echo $active_count;
                                            ?>
                                        </h4>
                                        <p class="text-muted mb-0">Active Sessions</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light border-0">
                                    <div class="card-body text-center">
                                        <h4 class="text-warning mb-1">
                                            <?php 
                                            $lab_count = 0;
                                            foreach ($sessions_data as $session) {
                                                if (isset($session['class_type']) && $session['class_type'] == 'lab') {
                                                    $lab_count++;
                                                }
                                            }
                                            echo $lab_count;
                                            ?>
                                        </h4>
                                        <p class="text-muted mb-0">Lab Sessions</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="text-center py-5">
                            <i class="fas fa-history fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted mb-3">No Sessions Found</h4>
                            <p class="text-muted mb-4">You haven't created any attendance sessions yet.</p>
                            <a href="faculty_dashboard.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
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
