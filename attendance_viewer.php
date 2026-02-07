<?php
// attendance_viewer.php - Fixed for accurate attendance calculation

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

// ==================== Check if user is logged in and is a student ====================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

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

$page_title = "Attendance Viewer";

// ==================== Clean buffer before output ====================
if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

// ==================== Initialize variables to avoid undefined errors ====================
$student = null;
$student_section = '';
$total_classes_for_student = 0;
$attended_count = 0;
$attendance_percentage = 0;
$subject_details_result = null;
$records_result = null;
$monthly_data = [];
$month_labels = [];
$month_total_counts = [];
$month_attended_counts = [];
$subject_data = [];
$subject_labels = [];
$subject_attendance_percentages = [];
$filtered_result = null;
$total_filtered = 0;
$months_result = null;
$subjects_result = null;
$missed_result = null;
$missed_count = 0;

// ==================== Fetch student details including section ====================
if ($conn) {
    $student_query = "SELECT * FROM students WHERE student_id = ?";
    $student_stmt = mysqli_prepare($conn, $student_query);
    if ($student_stmt) {
        mysqli_stmt_bind_param($student_stmt, "s", $student_id);
        mysqli_stmt_execute($student_stmt);
        $student_result = mysqli_stmt_get_result($student_stmt);
        $student = mysqli_fetch_assoc($student_result);
        mysqli_stmt_close($student_stmt);
        
        if ($student) {
            $student_section = $student['section'] ?? '';
        } else {
            // Student not found
            if (ob_get_length() > 0) {
                ob_end_clean();
            }
            header('Location: login.php');
            exit;
        }
    } else {
        // Database error
        error_log("Database error in student query");
        if (ob_get_length() > 0) {
            ob_end_clean();
        }
        die("Database connection error. Please try again later.");
    }
} else {
    error_log("No database connection");
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    die("Database connection error. Please try again later.");
}

// ==================== FIXED: Get TOTAL classes for student ====================
if ($student_section && $conn) {
    // Get all sessions for the student's section that have already occurred
    $total_sessions_query = "SELECT COUNT(DISTINCT session_id) as total_sessions 
                             FROM sessions 
                             WHERE section_targeted = ? 
                             AND start_time <= NOW()";
    $total_sessions_stmt = mysqli_prepare($conn, $total_sessions_query);
    if ($total_sessions_stmt) {
        mysqli_stmt_bind_param($total_sessions_stmt, "s", $student_section);
        mysqli_stmt_execute($total_sessions_stmt);
        $total_sessions_result = mysqli_stmt_get_result($total_sessions_stmt);
        if ($total_sessions_result) {
            $total_sessions_data = mysqli_fetch_assoc($total_sessions_result);
            $total_classes_for_student = $total_sessions_data['total_sessions'] ? (int)$total_sessions_data['total_sessions'] : 0;
        }
        mysqli_stmt_close($total_sessions_stmt);
    }
    
    // Debug log
    error_log("Total sessions for section $student_section: $total_classes_for_student");
}

// ==================== FIXED: Get attended classes ====================
if ($conn) {
    // Count distinct attended sessions (avoid duplicates)
    $attended_query = "SELECT COUNT(DISTINCT ar.session_id) as attended_count 
                       FROM attendance_records ar
                       JOIN sessions s ON ar.session_id = s.session_id
                       WHERE ar.student_id = ?
                       AND s.start_time <= NOW()";
    $attended_stmt = mysqli_prepare($conn, $attended_query);
    if ($attended_stmt) {
        mysqli_stmt_bind_param($attended_stmt, "s", $student_id);
        mysqli_stmt_execute($attended_stmt);
        $attended_result = mysqli_stmt_get_result($attended_stmt);
        if ($attended_result) {
            $attended_data = mysqli_fetch_assoc($attended_result);
            $attended_count = $attended_data['attended_count'] ? (int)$attended_data['attended_count'] : 0;
        }
        mysqli_stmt_close($attended_stmt);
    }
    
    // Debug log
    error_log("Attended sessions for student $student_id: $attended_count");
}

// ==================== Calculate attendance percentage ====================
if ($total_classes_for_student > 0) {
    $attendance_percentage = round(($attended_count / $total_classes_for_student) * 100, 2);
    $missed_count = $total_classes_for_student - $attended_count;
} else {
    $attendance_percentage = 0;
    $missed_count = 0;
}

// ==================== FIXED: Query for detailed subject-wise statistics ====================
if ($student_section && $conn) {
    $subject_details_query = "SELECT 
        s.subject_id,
        s.subject_code,
        s.subject_name,
        COUNT(DISTINCT CASE WHEN ar.record_id IS NOT NULL THEN ses.session_id END) as classes_attended,
        COUNT(DISTINCT ses.session_id) as total_sessions_for_subject,
        GROUP_CONCAT(DISTINCT f.faculty_name ORDER BY f.faculty_name SEPARATOR ', ') as faculties,
        GROUP_CONCAT(DISTINCT ses.class_type ORDER BY ses.class_type SEPARATOR ', ') as class_types
    FROM subjects s
    LEFT JOIN sessions ses ON s.subject_id = ses.subject_id 
        AND ses.section_targeted = ? 
        AND ses.start_time <= NOW()
    LEFT JOIN attendance_records ar ON ses.session_id = ar.session_id 
        AND ar.student_id = ?
    LEFT JOIN faculty f ON ses.faculty_id = f.faculty_id
    WHERE ses.session_id IS NOT NULL
    GROUP BY s.subject_id, s.subject_code, s.subject_name
    HAVING total_sessions_for_subject > 0
    ORDER BY s.subject_name";
    
    $subject_details_stmt = mysqli_prepare($conn, $subject_details_query);
    if ($subject_details_stmt) {
        mysqli_stmt_bind_param($subject_details_stmt, "ss", $student_section, $student_id);
        mysqli_stmt_execute($subject_details_stmt);
        $subject_details_result = mysqli_stmt_get_result($subject_details_stmt);
        mysqli_stmt_close($subject_details_stmt);
    }
}

// ==================== FIXED: Get attendance records with session details ====================
if ($conn) {
    $records_query = "SELECT 
        ar.record_id,
        ar.session_id,
        ar.student_id,
        ar.marked_at,
        s.subject_code,
        s.subject_name,
        ses.start_time,
        f.faculty_name,
        ses.section_targeted,
        ses.class_type,
        'Present' as status
    FROM attendance_records ar
    JOIN sessions ses ON ar.session_id = ses.session_id
    JOIN subjects s ON ses.subject_id = s.subject_id
    LEFT JOIN faculty f ON ses.faculty_id = f.faculty_id
    WHERE ar.student_id = ?
    AND ses.start_time <= NOW()
    ORDER BY ar.marked_at DESC";

    $records_stmt = mysqli_prepare($conn, $records_query);
    if ($records_stmt) {
        mysqli_stmt_bind_param($records_stmt, "s", $student_id);
        mysqli_stmt_execute($records_stmt);
        $records_result = mysqli_stmt_get_result($records_stmt);
        mysqli_stmt_close($records_stmt);
    }
}

// ==================== FIXED: Query for monthly attendance trend ====================
if ($student_section && $conn) {
    $monthly_query = "SELECT 
        DATE_FORMAT(ses.start_time, '%Y-%m') as month,
        COUNT(DISTINCT ses.session_id) as total_sessions,
        COUNT(DISTINCT ar.record_id) as attended_sessions
    FROM sessions ses
    LEFT JOIN attendance_records ar ON ses.session_id = ar.session_id 
        AND ar.student_id = ?
    WHERE ses.section_targeted = ?
    AND ses.start_time <= NOW()
    GROUP BY DATE_FORMAT(ses.start_time, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6";
    
    $monthly_stmt = mysqli_prepare($conn, $monthly_query);
    if ($monthly_stmt) {
        mysqli_stmt_bind_param($monthly_stmt, "ss", $student_id, $student_section);
        mysqli_stmt_execute($monthly_stmt);
        $monthly_result = mysqli_stmt_get_result($monthly_stmt);
        
        if ($monthly_result) {
            while ($row = mysqli_fetch_assoc($monthly_result)) {
                $monthly_data[] = $row;
                $month_labels[] = date('M', strtotime($row['month'] . '-01'));
                $month_total_counts[] = (int)$row['total_sessions'];
                $month_attended_counts[] = (int)$row['attended_sessions'];
            }
            
            // Reverse for chronological order
            $month_labels = array_reverse($month_labels);
            $month_total_counts = array_reverse($month_total_counts);
            $month_attended_counts = array_reverse($month_attended_counts);
        }
        mysqli_stmt_close($monthly_stmt);
    }
}

// ==================== FIXED: Query for subject-wise attendance for pie chart ====================
if ($student_section && $conn) {
    $subject_query = "SELECT 
        s.subject_name,
        COUNT(DISTINCT CASE WHEN ar.record_id IS NOT NULL THEN ses.session_id END) as attended_count,
        COUNT(DISTINCT ses.session_id) as total_count,
        ROUND((COUNT(DISTINCT CASE WHEN ar.record_id IS NOT NULL THEN ses.session_id END) * 100.0 / 
               NULLIF(COUNT(DISTINCT ses.session_id), 0)), 2) as attendance_percentage
    FROM subjects s
    LEFT JOIN sessions ses ON s.subject_id = ses.subject_id 
        AND ses.section_targeted = ? 
        AND ses.start_time <= NOW()
    LEFT JOIN attendance_records ar ON ses.session_id = ar.session_id 
        AND ar.student_id = ?
    WHERE ses.session_id IS NOT NULL
    GROUP BY s.subject_id, s.subject_name
    HAVING total_count > 0
    ORDER BY s.subject_name";
    
    $subject_stmt = mysqli_prepare($conn, $subject_query);
    if ($subject_stmt) {
        mysqli_stmt_bind_param($subject_stmt, "ss", $student_section, $student_id);
        mysqli_stmt_execute($subject_stmt);
        $subject_result = mysqli_stmt_get_result($subject_stmt);
        
        if ($subject_result) {
            while ($row = mysqli_fetch_assoc($subject_result)) {
                $subject_data[] = $row;
                $subject_labels[] = $row['subject_name'];
                $subject_attendance_percentages[] = (float)$row['attendance_percentage'];
            }
        }
        mysqli_stmt_close($subject_stmt);
    }
}

// ==================== FIXED: Handle filters ====================
if ($conn) {
    $where_clause = "WHERE ar.student_id = ?";
    $filter_params = [$student_id];
    $filter_types = "s";

    if (isset($_GET['subject']) && !empty($_GET['subject'])) {
        $subject_filter = trim($_GET['subject']);
        $where_clause .= " AND s.subject_name LIKE ?";
        $filter_params[] = "%$subject_filter%";
        $filter_types .= "s";
    }

    if (isset($_GET['month']) && !empty($_GET['month'])) {
        $month_filter = trim($_GET['month']);
        $where_clause .= " AND DATE_FORMAT(ar.marked_at, '%Y-%m') = ?";
        $filter_params[] = $month_filter;
        $filter_types .= "s";
    }

    $filtered_query = "SELECT 
        ar.record_id,
        ar.session_id,
        ar.student_id,
        ar.marked_at,
        s.subject_code,
        s.subject_name,
        ses.start_time,
        f.faculty_name,
        ses.section_targeted,
        ses.class_type,
        'Present' as status
    FROM attendance_records ar
    JOIN sessions ses ON ar.session_id = ses.session_id
    JOIN subjects s ON ses.subject_id = s.subject_id
    LEFT JOIN faculty f ON ses.faculty_id = f.faculty_id
    $where_clause
    AND ses.start_time <= NOW()
    ORDER BY ar.marked_at DESC";

    $filtered_stmt = mysqli_prepare($conn, $filtered_query);
    if ($filtered_stmt) {
        mysqli_stmt_bind_param($filtered_stmt, $filter_types, ...$filter_params);
        mysqli_stmt_execute($filtered_stmt);
        $filtered_result = mysqli_stmt_get_result($filtered_stmt);
        if ($filtered_result) {
            $total_filtered = mysqli_num_rows($filtered_result);
        }
        mysqli_stmt_close($filtered_stmt);
    }
}

// ==================== For dropdowns - Get unique months and subjects ====================
if ($student_section && $conn) {
    $months_query = "SELECT DISTINCT DATE_FORMAT(ses.start_time, '%Y-%m') as month_value, 
                    DATE_FORMAT(ses.start_time, '%M %Y') as month_display
                    FROM sessions ses
                    WHERE ses.section_targeted = ?
                    AND ses.start_time <= NOW()
                    ORDER BY month_value DESC";
    $months_stmt = mysqli_prepare($conn, $months_query);
    if ($months_stmt) {
        mysqli_stmt_bind_param($months_stmt, "s", $student_section);
        mysqli_stmt_execute($months_stmt);
        $months_result = mysqli_stmt_get_result($months_stmt);
        mysqli_stmt_close($months_stmt);
    }
}

if ($student_section && $conn) {
    $subjects_query = "SELECT DISTINCT s.subject_id, s.subject_name
                      FROM subjects s
                      WHERE s.subject_id IN (
                          SELECT DISTINCT subject_id 
                          FROM sessions 
                          WHERE section_targeted = ? 
                          AND start_time <= NOW()
                      )
                      ORDER BY s.subject_name";
    $subjects_stmt = mysqli_prepare($conn, $subjects_query);
    if ($subjects_stmt) {
        mysqli_stmt_bind_param($subjects_stmt, "s", $student_section);
        mysqli_stmt_execute($subjects_stmt);
        $subjects_result = mysqli_stmt_get_result($subjects_stmt);
        mysqli_stmt_close($subjects_stmt);
    }
}

// ==================== FIXED: Get missed sessions ====================
if ($student_section && $conn) {
    $missed_sessions_query = "SELECT 
        ses.session_id,
        ses.start_time,
        s.subject_code,
        s.subject_name,
        f.faculty_name,
        ses.section_targeted,
        ses.class_type
    FROM sessions ses
    JOIN subjects s ON ses.subject_id = s.subject_id
    LEFT JOIN faculty f ON ses.faculty_id = f.faculty_id
    WHERE ses.section_targeted = ? 
    AND ses.start_time <= NOW()
    AND ses.session_id NOT IN (
        SELECT session_id FROM attendance_records WHERE student_id = ?
    )
    ORDER BY ses.start_time DESC";
    
    $missed_stmt = mysqli_prepare($conn, $missed_sessions_query);
    if ($missed_stmt) {
        mysqli_stmt_bind_param($missed_stmt, "ss", $student_section, $student_id);
        mysqli_stmt_execute($missed_stmt);
        $missed_result = mysqli_stmt_get_result($missed_stmt);
        if ($missed_result) {
            $missed_count = mysqli_num_rows($missed_result);
        }
        mysqli_stmt_close($missed_stmt);
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }
        .badge {
            font-size: 0.9em;
        }
        .table th {
            background-color: #f1f3f4;
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
        }
        .subject-card:hover {
            transform: translateY(-5px);
        }
        .attendance-progress {
            height: 15px;
        }
        .subject-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .navbar-brand {
            font-weight: 600;
        }
        footer {
            margin-top: 50px;
            padding: 20px 0;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        .attendance-high {
            background-color: #d4edda;
        }
        .attendance-medium {
            background-color: #fff3cd;
        }
        .attendance-low {
            background-color: #f8d7da;
        }
    </style>
</head>
<body>
    <!-- Header is now included from header.php -->
    
    <div class="container">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="display-6">
                    <i class="fas fa-chart-line text-primary me-2"></i>Attendance Viewer
                </h1>
                <p class="text-muted">Welcome, <?php echo htmlspecialchars($student['student_name'] ?? 'Student'); ?> | 
                   Student ID: <?php echo htmlspecialchars($student_id); ?> | 
                   Section: <?php echo htmlspecialchars($student_section); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print Report
                </button>
                <a href="student_dashboard.php" class="btn btn-secondary ms-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Real Statistics from Database -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card border-primary h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Total Sessions</h6>
                                <h2 class="card-title text-primary"><?php echo $total_classes_for_student; ?></h2>
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
                                <h2 class="card-title text-success"><?php echo $attended_count; ?></h2>
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

        <!-- Debug Info (can be removed after confirming it works) -->
        <div class="alert alert-info mb-4">
            <h5><i class="fas fa-bug me-2"></i>Debug Information</h5>
            <p class="mb-1"><strong>Student Section:</strong> <?php echo htmlspecialchars($student_section); ?></p>
            <p class="mb-1"><strong>Total Sessions in Database:</strong> <?php echo $total_classes_for_student; ?></p>
            <p class="mb-1"><strong>Sessions Attended:</strong> <?php echo $attended_count; ?></p>
            <p class="mb-0"><strong>Attendance Rate:</strong> <?php echo number_format($attendance_percentage, 2); ?>%</p>
        </div>

        <!-- Charts Section - Only show if we have data -->
        <?php if ($total_classes_for_student > 0): ?>
        <div class="row mb-4">
            <!-- Monthly Trend Chart -->
            <?php if (count($monthly_data) > 0): ?>
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Monthly Attendance Trend
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Subject-wise Chart -->
            <?php if (count($subject_data) > 0): ?>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>Subject-wise Attendance %
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="subjectChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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
                            <?php 
                            if ($missed_result) {
                                mysqli_data_seek($missed_result, 0);
                                while ($missed = mysqli_fetch_assoc($missed_result)): 
                            ?>
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
                            <?php 
                                endwhile;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters and Search -->
        <?php if ($total_classes_for_student > 0): ?>
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Subject</label>
                            <select class="form-select" name="subject" id="subjectFilter">
                                <option value="">All Subjects</option>
                                <?php 
                                if ($subjects_result) {
                                    mysqli_data_seek($subjects_result, 0);
                                    while ($subject = mysqli_fetch_assoc($subjects_result)): ?>
                                        <option value="<?php echo htmlspecialchars($subject['subject_name']); ?>"
                                            <?php echo (isset($_GET['subject']) && $_GET['subject'] == $subject['subject_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        </option>
                                    <?php 
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Month</label>
                            <select class="form-select" name="month" id="monthFilter">
                                <option value="">All Months</option>
                                <?php 
                                if ($months_result) {
                                    mysqli_data_seek($months_result, 0);
                                    while ($month = mysqli_fetch_assoc($months_result)): ?>
                                        <option value="<?php echo $month['month_value']; ?>"
                                            <?php echo (isset($_GET['month']) && $_GET['month'] == $month['month_value']) ? 'selected' : ''; ?>>
                                            <?php echo $month['month_display']; ?>
                                        </option>
                                    <?php 
                                    endwhile;
                                }
                                ?>
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
                        <small class="text-muted">(Filtered: <?php echo $total_filtered; ?> records)</small>
                    <?php endif; ?>
                </h5>
                <div>
                    <span class="badge bg-primary">Attended: <?php echo $attended_count; ?> of <?php echo $total_classes_for_student; ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if ($attended_count == 0): ?>
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
                        <table class="table table-hover" id="attendanceTable">
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
                                <?php 
                                if ($filtered_result) {
                                    mysqli_data_seek($filtered_result, 0);
                                    while ($record = mysqli_fetch_assoc($filtered_result)): 
                                        $class_type = $record['class_type'] ?? 'Normal';
                                        $type_badge_color = 'bg-primary';
                                        if ($class_type == 'Lab') $type_badge_color = 'bg-danger';
                                        if ($class_type == 'Tutorial') $type_badge_color = 'bg-warning';
                                        if ($class_type == 'Project') $type_badge_color = 'bg-success';
                                        
                                        // Calculate if late (more than 15 minutes after start)
                                        $is_late = false;
                                        if (!empty($record['start_time']) && !empty($record['marked_at'])) {
                                            $start_time = strtotime($record['start_time']);
                                            $marked_time = strtotime($record['marked_at']);
                                            if (($marked_time - $start_time) > 900) { // 15 minutes = 900 seconds
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
                                        <?php if (!empty($record['section_targeted'])): ?>
                                            <span class="badge bg-secondary class-type-badge">Sec: <?php echo $record['section_targeted']; ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($class_type)): ?>
                                            <span class="badge <?php echo $type_badge_color; ?> class-type-badge"><?php echo $class_type; ?></span>
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
                                <?php 
                                    endwhile;
                                } elseif (!$filtered_result && $attended_count > 0) {
                                    // If no filtered result but we have attendance, show all records
                                    if ($records_result) {
                                        mysqli_data_seek($records_result, 0);
                                        while ($record = mysqli_fetch_assoc($records_result)): 
                                            $class_type = $record['class_type'] ?? 'Normal';
                                            $type_badge_color = 'bg-primary';
                                            if ($class_type == 'Lab') $type_badge_color = 'bg-danger';
                                            if ($class_type == 'Tutorial') $type_badge_color = 'bg-warning';
                                            if ($class_type == 'Project') $type_badge_color = 'bg-success';
                                            
                                            $is_late = false;
                                            if (!empty($record['start_time']) && !empty($record['marked_at'])) {
                                                $start_time = strtotime($record['start_time']);
                                                $marked_time = strtotime($record['marked_at']);
                                                if (($marked_time - $start_time) > 900) {
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
                                        <?php if (!empty($record['section_targeted'])): ?>
                                            <span class="badge bg-secondary class-type-badge">Sec: <?php echo $record['section_targeted']; ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($class_type)): ?>
                                            <span class="badge <?php echo $type_badge_color; ?> class-type-badge"><?php echo $class_type; ?></span>
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
                                <?php 
                                        endwhile;
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (isset($_GET['subject']) || isset($_GET['month'])): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Showing <?php echo $total_filtered; ?> filtered records. 
                            <a href="attendance_viewer.php" class="alert-link">View all <?php echo $attended_count; ?> records</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subject-wise Attendance Details -->
        <?php if ($subject_details_result && mysqli_num_rows($subject_details_result) > 0): ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-book-open me-2"></i>Subject-wise Attendance Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">Detailed attendance statistics for each subject in your section.</p>
                        
                        <div class="row">
                            <?php 
                            mysqli_data_seek($subject_details_result, 0);
                            $subject_count = 0;
                            while ($subject_detail = mysqli_fetch_assoc($subject_details_result)): 
                                $subject_count++;
                                $subject_attended = $subject_detail['classes_attended'] ? (int)$subject_detail['classes_attended'] : 0;
                                $subject_total = $subject_detail['total_sessions_for_subject'] ? (int)$subject_detail['total_sessions_for_subject'] : 0;
                                $subject_percentage = $subject_total > 0 ? round(($subject_attended / $subject_total) * 100, 2) : 0;
                                
                                $card_class = '';
                                if ($subject_percentage >= 75) $card_class = 'attendance-high';
                                elseif ($subject_percentage >= 50) $card_class = 'attendance-medium';
                                else $card_class = 'attendance-low';
                                
                                $subject_icon = 'fa-book';
                                $subject_name_lower = strtolower($subject_detail['subject_name']);
                                if (strpos($subject_name_lower, 'programming') !== false || strpos($subject_name_lower, 'code') !== false) {
                                    $subject_icon = 'fa-code';
                                } elseif (strpos($subject_name_lower, 'database') !== false) {
                                    $subject_icon = 'fa-database';
                                } elseif (strpos($subject_name_lower, 'network') !== false) {
                                    $subject_icon = 'fa-network-wired';
                                } elseif (strpos($subject_name_lower, 'web') !== false) {
                                    $subject_icon = 'fa-globe';
                                } elseif (strpos($subject_name_lower, 'math') !== false) {
                                    $subject_icon = 'fa-calculator';
                                }
                            ?>
                            <div class="col-md-6 mb-4">
                                <div class="card subject-card h-100 <?php echo $card_class; ?>">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">
                                                <i class="fas <?php echo $subject_icon; ?> me-2 text-primary"></i>
                                                <?php echo htmlspecialchars($subject_detail['subject_name']); ?>
                                            </h6>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($subject_detail['subject_code']); ?></span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6">
                                                <p class="mb-1"><strong>Total Sessions:</strong></p>
                                                <p class="mb-1"><strong>Attended:</strong></p>
                                                <p class="mb-1"><strong>Attendance %:</strong></p>
                                                <p class="mb-1"><strong>Faculty:</strong></p>
                                                <p class="mb-0"><strong>Class Types:</strong></p>
                                            </div>
                                            <div class="col-6 text-end">
                                                <p class="mb-1"><?php echo $subject_total; ?></p>
                                                <p class="mb-1"><?php echo $subject_attended; ?></p>
                                                <p class="mb-1">
                                                    <span class="badge <?php 
                                                        echo $subject_percentage >= 75 ? 'bg-success' : 
                                                             ($subject_percentage >= 50 ? 'bg-warning' : 'bg-danger'); 
                                                    ?>">
                                                        <?php echo number_format($subject_percentage, 2); ?>%
                                                    </span>
                                                </p>
                                                <p class="mb-1">
                                                    <small><?php echo htmlspecialchars($subject_detail['faculties'] ?? 'N/A'); ?></small>
                                                </p>
                                                <p class="mb-0">
                                                    <?php 
                                                    $class_types = !empty($subject_detail['class_types']) ? 
                                                        explode(', ', $subject_detail['class_types']) : [];
                                                    foreach ($class_types as $type) {
                                                        $type_badge = 'bg-primary';
                                                        if ($type == 'Lab') $type_badge = 'bg-danger';
                                                        if ($type == 'Tutorial') $type_badge = 'bg-warning';
                                                        if ($type == 'Project') $type_badge = 'bg-success';
                                                        echo '<span class="badge ' . $type_badge . ' me-1">' . $type . '</span>';
                                                    }
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <!-- Attendance progress bar -->
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Attendance Progress</small>
                                                <small><strong><?php echo number_format($subject_percentage, 2); ?>%</strong></small>
                                            </div>
                                            <div class="progress attendance-progress">
                                                <div class="progress-bar <?php 
                                                    echo $subject_percentage >= 75 ? 'bg-success' : 
                                                         ($subject_percentage >= 50 ? 'bg-warning' : 'bg-danger'); 
                                                ?>" 
                                                style="width: <?php echo min($subject_percentage, 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($subject_count % 2 == 0): ?>
                                </div><div class="row">
                            <?php endif; ?>
                            
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include 'footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Charts JavaScript -->
    <?php if ($total_classes_for_student > 0): ?>
    <script>
    // Monthly Trend Chart - Show total vs attended
    <?php if (count($monthly_data) > 0): ?>
    const ctx1 = document.getElementById('attendanceChart');
    if (ctx1) {
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($month_labels); ?>,
                datasets: [{
                    label: 'Total Sessions',
                    data: <?php echo json_encode($month_total_counts); ?>,
                    backgroundColor: '#dc3545',
                    borderColor: '#dc3545',
                    borderWidth: 1
                }, {
                    label: 'Attended',
                    data: <?php echo json_encode($month_attended_counts); ?>,
                    backgroundColor: '#28a745',
                    borderColor: '#28a745',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Classes'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>

    // Subject-wise Chart - Show attendance percentage
    <?php if (count($subject_data) > 0): ?>
    const ctx2 = document.getElementById('subjectChart');
    if (ctx2) {
        // Generate colors based on percentage
        const backgroundColors = <?php echo json_encode($subject_attendance_percentages); ?>.map(p => {
            if (p >= 75) return '#28a745';
            if (p >= 50) return '#ffc107';
            return '#dc3545';
        });
        
        new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($subject_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($subject_attendance_percentages); ?>,
                    backgroundColor: backgroundColors,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>
<?php
// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
