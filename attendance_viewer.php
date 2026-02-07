<?php
// attendance_viewer.php - Simplified and Fixed

// ==================== Start output buffering ====================
if (!ob_get_level()) {
    ob_start();
}

// ==================== Configure session for Render ====================
ini_set('session.save_path', '/tmp');
ini_set('session.cookie_lifetime', 86400);

// ==================== Start session ====================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== Include database config ====================
require_once 'config.php';

// ==================== Security check ====================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// ==================== Role check ====================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
    header('Location: login.php');
    exit;
}

$page_title = "Attendance Viewer";

// ==================== Clean buffer before output ====================
if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

// ==================== Initialize variables ====================
$student = null;
$student_section = '';
$attended_count = 0;
$subject_details = [];

// ==================== Get student details ====================
if ($conn) {
    $student_query = "SELECT * FROM students WHERE student_id = ?";
    $stmt = mysqli_prepare($conn, $student_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $student = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($student) {
            $student_section = $student['section'] ?? '';
        }
    }
}

// ==================== SIMPLIFIED: Get attendance records ====================
$attendance_records = [];
$total_attendance = 0;

if ($conn && $student_id) {
    // Simple query to get all attendance records for this student
    $query = "SELECT ar.*, s.subject_name, s.subject_code, ses.start_time, f.faculty_name, ses.class_type
              FROM attendance_records ar
              JOIN sessions ses ON ar.session_id = ses.session_id
              JOIN subjects s ON ses.subject_id = s.subject_id
              LEFT JOIN faculty f ON ses.faculty_id = f.faculty_id
              WHERE ar.student_id = ?
              ORDER BY ar.marked_at DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $attendance_records[] = $row;
        }
        $total_attendance = count($attendance_records);
        mysqli_stmt_close($stmt);
    }
}

// ==================== Get missed sessions ====================
$missed_sessions = [];
$missed_count = 0;

if ($conn && $student_id && $student_section) {
    $query = "SELECT ses.*, s.subject_name, s.subject_code, f.faculty_name
              FROM sessions ses
              JOIN subjects s ON ses.subject_id = s.subject_id
              LEFT JOIN faculty f ON ses.faculty_id = f.faculty_id
              WHERE ses.section_targeted = ?
              AND ses.session_id NOT IN (
                  SELECT session_id FROM attendance_records WHERE student_id = ?
              )
              AND ses.start_time <= NOW()
              ORDER BY ses.start_time DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $student_section, $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $missed_sessions[] = $row;
        }
        $missed_count = count($missed_sessions);
        mysqli_stmt_close($stmt);
    }
}

// ==================== Get total sessions for section ====================
$total_sessions = 0;
if ($conn && $student_section) {
    $query = "SELECT COUNT(*) as total FROM sessions 
              WHERE section_targeted = ? AND start_time <= NOW()";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $student_section);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $total_sessions = $row['total'] ?? 0;
        mysqli_stmt_close($stmt);
    }
}

// ==================== Calculate attendance percentage ====================
$attendance_percentage = 0;
if ($total_sessions > 0) {
    $attendance_percentage = round(($total_attendance / $total_sessions) * 100, 2);
}

// ==================== Get subject-wise breakdown ====================
$subject_breakdown = [];
if ($conn && $student_id && $student_section) {
    $query = "SELECT s.subject_id, s.subject_name, s.subject_code,
              COUNT(DISTINCT ses.session_id) as total_classes,
              COUNT(DISTINCT ar.record_id) as attended_classes
              FROM subjects s
              JOIN sessions ses ON s.subject_id = ses.subject_id AND ses.section_targeted = ?
              LEFT JOIN attendance_records ar ON ses.session_id = ar.session_id AND ar.student_id = ?
              WHERE ses.start_time <= NOW()
              GROUP BY s.subject_id, s.subject_name, s.subject_code
              ORDER BY s.subject_name";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $student_section, $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $subject_breakdown[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// ==================== Get available months for filter ====================
$available_months = [];
if ($conn && $student_id) {
    $query = "SELECT DISTINCT DATE_FORMAT(marked_at, '%Y-%m') as month_value,
              DATE_FORMAT(marked_at, '%M %Y') as month_display
              FROM attendance_records
              WHERE student_id = ?
              ORDER BY month_value DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $available_months[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// ==================== Get available subjects for filter ====================
$available_subjects = [];
if ($conn && $student_id) {
    $query = "SELECT DISTINCT s.subject_id, s.subject_name
              FROM subjects s
              JOIN sessions ses ON s.subject_id = ses.subject_id
              JOIN attendance_records ar ON ses.session_id = ar.session_id
              WHERE ar.student_id = ?
              ORDER BY s.subject_name";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $available_subjects[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// ==================== Handle filters ====================
$filtered_records = $attendance_records;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $subject_filter = $_GET['subject'] ?? '';
    $month_filter = $_GET['month'] ?? '';
    
    if (!empty($subject_filter) || !empty($month_filter)) {
        $filtered_records = [];
        
        foreach ($attendance_records as $record) {
            $matches = true;
            
            if (!empty($subject_filter) && $record['subject_id'] != $subject_filter) {
                $matches = false;
            }
            
            if (!empty($month_filter)) {
                $record_month = date('Y-m', strtotime($record['marked_at']));
                if ($record_month != $month_filter) {
                    $matches = false;
                }
            }
            
            if ($matches) {
                $filtered_records[] = $record;
            }
        }
    }
}

// ==================== Include Header ====================
include 'header.php';
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
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .card {
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card-header {
            font-weight: 600;
            background-color: rgba(0,0,0,0.03);
        }
        .badge {
            font-size: 0.9em;
        }
        .progress {
            height: 8px;
        }
        .class-type-badge {
            font-size: 0.8em;
            padding: 0.2em 0.5em;
        }
        .subject-card {
            transition: transform 0.2s;
            border-left: 4px solid #007bff;
        }
        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .attendance-progress {
            height: 15px;
        }
        .subject-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .attendance-high {
            border-left-color: #28a745 !important;
        }
        .attendance-medium {
            border-left-color: #ffc107 !important;
        }
        .attendance-low {
            border-left-color: #dc3545 !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="display-6">
                    <i class="fas fa-chart-line text-primary me-2"></i>Attendance Viewer
                </h1>
                <p class="text-muted">
                    Welcome, <?php echo htmlspecialchars($student['student_name'] ?? 'Student'); ?> | 
                    Student ID: <?php echo htmlspecialchars($student_id); ?> | 
                    Section: <?php echo htmlspecialchars($student_section); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <a href="student_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card border-primary h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Total Sessions</h6>
                                <h2 class="card-title text-primary"><?php echo $total_sessions; ?></h2>
                            </div>
                            <i class="fas fa-calendar-alt fa-2x text-primary opacity-75"></i>
                        </div>
                        <p class="card-text small">Sessions in Section <?php echo htmlspecialchars($student_section); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card border-success h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Attended</h6>
                                <h2 class="card-title text-success"><?php echo $total_attendance; ?></h2>
                            </div>
                            <i class="fas fa-check-circle fa-2x text-success opacity-75"></i>
                        </div>
                        <p class="card-text small">Sessions attended</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card <?php 
                    echo $attendance_percentage >= 75 ? 'border-success' : 
                         ($attendance_percentage >= 50 ? 'border-warning' : 'border-danger'); 
                ?> h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Attendance Rate</h6>
                                <h2 class="card-title"><?php echo number_format($attendance_percentage, 2); ?>%</h2>
                            </div>
                            <i class="fas fa-percentage fa-2x opacity-75"></i>
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar 
                                <?php 
                                    echo $attendance_percentage >= 75 ? 'bg-success' : 
                                         ($attendance_percentage >= 50 ? 'bg-warning' : 'bg-danger'); 
                                ?>" 
                                style="width: <?php echo min($attendance_percentage, 100); ?>%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card border-info h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Missed</h6>
                                <h2 class="card-title text-info"><?php echo $missed_count; ?></h2>
                            </div>
                            <i class="fas fa-times-circle fa-2x text-info opacity-75"></i>
                        </div>
                        <p class="card-text small">Sessions missed</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Missed Sessions (if any) -->
        <?php if ($missed_count > 0): ?>
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Missed Sessions (<?php echo $missed_count; ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Subject</th>
                                <th>Faculty</th>
                                <th>Class Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missed_sessions as $missed): ?>
                            <tr>
                                <td><?php echo date('d/m/Y h:i A', strtotime($missed['start_time'])); ?></td>
                                <td>
                                    <?php if (!empty($missed['subject_code'])): ?>
                                        <strong><?php echo htmlspecialchars($missed['subject_code']); ?></strong><br>
                                    <?php endif; ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($missed['subject_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($missed['faculty_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($missed['class_type'])): ?>
                                        <span class="badge bg-secondary class-type-badge"><?php echo $missed['class_type']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-danger">Absent</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <?php if ($total_attendance > 0): ?>
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Subject</label>
                            <select class="form-select" name="subject" id="subjectFilter">
                                <option value="">All Subjects</option>
                                <?php foreach ($available_subjects as $subject): ?>
                                    <option value="<?php echo $subject['subject_id']; ?>"
                                        <?php echo (isset($_GET['subject']) && $_GET['subject'] == $subject['subject_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Month</label>
                            <select class="form-select" name="month" id="monthFilter">
                                <option value="">All Months</option>
                                <?php foreach ($available_months as $month): ?>
                                    <option value="<?php echo $month['month_value']; ?>"
                                        <?php echo (isset($_GET['month']) && $_GET['month'] == $month['month_value']) ? 'selected' : ''; ?>>
                                        <?php echo $month['month_display']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($_GET['subject']) || isset($_GET['month'])): ?>
                    <div class="mt-3">
                        <a href="attendance_viewer.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attendance Records Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>Attendance Records
                    <?php if (isset($_GET['subject']) || isset($_GET['month'])): ?>
                        <small class="text-muted">(Filtered: <?php echo count($filtered_records); ?> records)</small>
                    <?php endif; ?>
                </h5>
                <div>
                    <span class="badge bg-primary">Attended: <?php echo $total_attendance; ?> of <?php echo $total_sessions; ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if ($total_attendance == 0): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">No Attendance Records Found</h4>
                        <p class="text-muted">You haven't attended any classes yet.</p>
                        <a href="student_dashboard.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Back to Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Faculty</th>
                                    <th>Class Details</th>
                                    <th>Status</th>
                                    <th>Marked Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filtered_records as $record): 
                                    $is_late = false;
                                    if (!empty($record['start_time']) && !empty($record['marked_at'])) {
                                        $start_time = strtotime($record['start_time']);
                                        $marked_time = strtotime($record['marked_at']);
                                        if (($marked_time - $start_time) > 900) { // 15 minutes
                                            $is_late = true;
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($record['marked_at'])); ?></td>
                                    <td>
                                        <?php if (!empty($record['subject_code'])): ?>
                                            <strong><?php echo htmlspecialchars($record['subject_code']); ?></strong><br>
                                        <?php endif; ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($record['subject_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['faculty_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($record['start_time'])): ?>
                                            <strong><?php echo date('h:i A', strtotime($record['start_time'])); ?></strong><br>
                                        <?php endif; ?>
                                        <?php if (!empty($record['class_type'])): ?>
                                            <span class="badge bg-secondary class-type-badge"><?php echo $record['class_type']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Present</span>
                                        <?php if ($is_late): ?>
                                            <span class="badge bg-warning ms-1">Late</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('h:i A', strtotime($record['marked_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (isset($_GET['subject']) || isset($_GET['month'])): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Showing <?php echo count($filtered_records); ?> filtered records. 
                            <a href="attendance_viewer.php" class="alert-link">View all <?php echo $total_attendance; ?> records</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subject-wise Attendance Details -->
        <?php if (!empty($subject_breakdown)): ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-book-open me-2"></i>Subject-wise Attendance Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($subject_breakdown as $subject): 
                                $attended = $subject['attended_classes'] ?? 0;
                                $total = $subject['total_classes'] ?? 0;
                                $percentage = $total > 0 ? round(($attended / $total) * 100, 2) : 0;
                                
                                $card_class = '';
                                if ($percentage >= 75) $card_class = 'attendance-high';
                                elseif ($percentage >= 50) $card_class = 'attendance-medium';
                                else $card_class = 'attendance-low';
                            ?>
                            <div class="col-md-4 mb-3">
                                <div class="card subject-card h-100 <?php echo $card_class; ?>">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($subject['subject_code']); ?></small>
                                        </h6>
                                        
                                        <div class="row mt-3">
                                            <div class="col-6">
                                                <p class="mb-1"><strong>Total:</strong></p>
                                                <p class="mb-1"><strong>Attended:</strong></p>
                                                <p class="mb-0"><strong>Rate:</strong></p>
                                            </div>
                                            <div class="col-6 text-end">
                                                <p class="mb-1"><?php echo $total; ?></p>
                                                <p class="mb-1"><?php echo $attended; ?></p>
                                                <p class="mb-0">
                                                    <span class="badge <?php 
                                                        echo $percentage >= 75 ? 'bg-success' : 
                                                             ($percentage >= 50 ? 'bg-warning' : 'bg-danger'); 
                                                    ?>">
                                                        <?php echo number_format($percentage, 2); ?>%
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Progress</small>
                                                <small><?php echo number_format($percentage, 2); ?>%</small>
                                            </div>
                                            <div class="progress attendance-progress">
                                                <div class="progress-bar <?php 
                                                    echo $percentage >= 75 ? 'bg-success' : 
                                                         ($percentage >= 50 ? 'bg-warning' : 'bg-danger'); 
                                                ?>" 
                                                style="width: <?php echo min($percentage, 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Simple script to handle filter display
    document.addEventListener('DOMContentLoaded', function() {
        const subjectFilter = document.getElementById('subjectFilter');
        const monthFilter = document.getElementById('monthFilter');
        
        if (subjectFilter) {
            subjectFilter.addEventListener('change', function() {
                this.form.submit();
            });
        }
        
        if (monthFilter) {
            monthFilter.addEventListener('change', function() {
                this.form.submit();
            });
        }
    });
    </script>
</body>
</html>
<?php
// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
