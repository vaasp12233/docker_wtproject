<?php
// 360_students_attendance.php
require_once 'config.php';
session_start(); // Add session start

// Security check - faculty only
if (!isset($_SESSION['faculty_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];
$page_title = "360 Students Attendance Overview";
include 'header.php';

// Get faculty details using prepared statement
$faculty_query = "SELECT * FROM faculty WHERE faculty_id = ?";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "s", $faculty_id);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty = mysqli_fetch_assoc($faculty_result);
mysqli_stmt_close($faculty_stmt);

// Get all subjects taught by this faculty using prepared statement
$subjects_query = "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name 
                   FROM sessions se 
                   JOIN subjects s ON se.subject_id = s.subject_id 
                   WHERE se.faculty_id = ? 
                   ORDER BY s.subject_name";
$subjects_stmt = mysqli_prepare($conn, $subjects_query);
mysqli_stmt_bind_param($subjects_stmt, "s", $faculty_id);
mysqli_stmt_execute($subjects_stmt);
$subjects_result = mysqli_stmt_get_result($subjects_stmt);

// Store subjects in array for reuse
$subjects_data = [];
while($subject = mysqli_fetch_assoc($subjects_result)) {
    $subjects_data[] = $subject;
}
mysqli_stmt_close($subjects_stmt);

// Get all sections
$sections_query = "SELECT DISTINCT section FROM students WHERE section != '' ORDER BY section";
$sections_result = mysqli_query($conn, $sections_query);
$sections_data = [];
while($section = mysqli_fetch_assoc($sections_result)) {
    $sections_data[] = $section;
}

// Initialize filter variables with sanitization
$subject_filter = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$section_filter = isset($_GET['section']) ? mysqli_real_escape_string($conn, trim($_GET['section'])) : '';
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, trim($_GET['date_from'])) : date('Y-m-d', strtotime('-90 days'));
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, trim($_GET['date_to'])) : date('Y-m-d');
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$min_attendance = isset($_GET['min_attendance']) ? intval($_GET['min_attendance']) : 0;
$max_attendance = isset($_GET['max_attendance']) ? intval($_GET['max_attendance']) : 100;

// Validate date format
if (!empty($date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = date('Y-m-d', strtotime('-90 days'));
}
if (!empty($date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = date('Y-m-d');
}

// Validate attendance range
if ($min_attendance < 0) $min_attendance = 0;
if ($max_attendance > 100) $max_attendance = 100;
if ($min_attendance > $max_attendance) {
    $temp = $min_attendance;
    $min_attendance = $max_attendance;
    $max_attendance = $temp;
}

// First, let's get all students with their basic info using prepared statement
$students_query = "SELECT 
                    s.student_id,
                    s.student_name,
                    s.section,
                    s.student_department,
                    s.id_number
                   FROM students s
                   WHERE 1=1";

$students_params = [];
$students_param_types = "";

if (!empty($section_filter)) {
    $students_query .= " AND s.section = ?";
    $students_params[] = $section_filter;
    $students_param_types .= "s";
}

if (!empty($search_query)) {
    $students_query .= " AND (s.student_name LIKE ? OR s.student_id LIKE ? OR s.id_number LIKE ?)";
    $students_params[] = "%$search_query%";
    $students_params[] = "%$search_query%";
    $students_params[] = "%$search_query%";
    $students_param_types .= "sss";
}

$students_query .= " ORDER BY s.section, s.student_name";

$students_stmt = mysqli_prepare($conn, $students_query);
if (!empty($students_params)) {
    mysqli_stmt_bind_param($students_stmt, $students_param_types, ...$students_params);
}
mysqli_stmt_execute($students_stmt);
$students_result = mysqli_stmt_get_result($students_stmt);

// Get total sessions for each subject (for percentage calculation) using prepared statement
$subject_sessions = [];
$total_subject_sessions_query = "SELECT 
                                sub.subject_id,
                                sub.subject_code,
                                sub.subject_name,
                                COUNT(DISTINCT ses.session_id) as total_sessions
                               FROM sessions ses
                               JOIN subjects sub ON ses.subject_id = sub.subject_id
                               WHERE ses.faculty_id = ?";
                               
$subject_session_params = [$faculty_id];
$subject_session_param_types = "s";

if (!empty($date_from) && !empty($date_to)) {
    $total_subject_sessions_query .= " AND DATE(ses.start_time) BETWEEN ? AND ?";
    $subject_session_params[] = $date_from;
    $subject_session_params[] = $date_to;
    $subject_session_param_types .= "ss";
}

if ($subject_filter > 0) {
    $total_subject_sessions_query .= " AND sub.subject_id = ?";
    $subject_session_params[] = $subject_filter;
    $subject_session_param_types .= "i";
}

$total_subject_sessions_query .= " GROUP BY sub.subject_id, sub.subject_code, sub.subject_name";

$subject_sessions_stmt = mysqli_prepare($conn, $total_subject_sessions_query);
mysqli_stmt_bind_param($subject_sessions_stmt, $subject_session_param_types, ...$subject_session_params);
mysqli_stmt_execute($subject_sessions_stmt);
$subject_sessions_result = mysqli_stmt_get_result($subject_sessions_stmt);

while ($subject_session = mysqli_fetch_assoc($subject_sessions_result)) {
    $subject_sessions[$subject_session['subject_id']] = $subject_session;
}
mysqli_stmt_close($subject_sessions_stmt);

// If no subjects found for filter, show empty state
if (empty($subject_sessions)) {
    $no_subjects_message = "No subjects found for the selected filters.";
}

// Prepare data structure for student attendance
$student_attendance_data = [];
$subject_ids = array_keys($subject_sessions);

// Now get attendance data for each student - OPTIMIZED VERSION
if (!empty($subject_ids)) {
    // First, get all student IDs
    $all_student_ids = [];
    while ($student = mysqli_fetch_assoc($students_result)) {
        $all_student_ids[] = $student['student_id'];
        // Store student info
        $student_attendance_data[$student['student_id']] = [
            'info' => $student,
            'subjects' => [],
            'total_attended' => 0,
            'total_sessions' => 0,
            'overall_percentage' => 0
        ];
    }
    
    // Reset pointer to use student result again later
    mysqli_data_seek($students_result, 0);
    
    // Get attendance counts for all students and subjects in batch
    if (!empty($all_student_ids)) {
        // Create placeholders for student IDs
        $student_placeholders = implode(',', array_fill(0, count($all_student_ids), '?'));
        $subject_placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
        
        // Query to get attendance counts for all students and subjects
        $batch_attendance_query = "SELECT 
                                    ar.student_id,
                                    ses.subject_id,
                                    COUNT(*) as attended_sessions
                                   FROM attendance_records ar
                                   JOIN sessions ses ON ar.session_id = ses.session_id
                                   WHERE ar.student_id IN ($student_placeholders)
                                   AND ses.subject_id IN ($subject_placeholders)
                                   AND ses.faculty_id = ?";
        
        $batch_params = array_merge($all_student_ids, $subject_ids, [$faculty_id]);
        $batch_param_types = str_repeat('s', count($all_student_ids)) . str_repeat('i', count($subject_ids)) . 's';
        
        if (!empty($date_from) && !empty($date_to)) {
            $batch_attendance_query .= " AND DATE(ar.marked_at) BETWEEN ? AND ?";
            $batch_params[] = $date_from;
            $batch_params[] = $date_to;
            $batch_param_types .= "ss";
        }
        
        $batch_attendance_query .= " GROUP BY ar.student_id, ses.subject_id";
        
        $batch_stmt = mysqli_prepare($conn, $batch_attendance_query);
        mysqli_stmt_bind_param($batch_stmt, $batch_param_types, ...$batch_params);
        mysqli_stmt_execute($batch_stmt);
        $batch_result = mysqli_stmt_get_result($batch_stmt);
        
        // Store batch attendance data
        $batch_attendance = [];
        while ($row = mysqli_fetch_assoc($batch_result)) {
            if (!isset($batch_attendance[$row['student_id']])) {
                $batch_attendance[$row['student_id']] = [];
            }
            $batch_attendance[$row['student_id']][$row['subject_id']] = $row['attended_sessions'];
        }
        mysqli_stmt_close($batch_stmt);
        
        // Process each student with batch data
        foreach ($all_student_ids as $student_id) {
            foreach ($subject_ids as $subject_id) {
                $attended = isset($batch_attendance[$student_id][$subject_id]) ? $batch_attendance[$student_id][$subject_id] : 0;
                $total_sessions = isset($subject_sessions[$subject_id]['total_sessions']) ? $subject_sessions[$subject_id]['total_sessions'] : 0;
                $percentage = ($total_sessions > 0) ? round(($attended / $total_sessions) * 100, 1) : 0;
                
                $student_attendance_data[$student_id]['subjects'][$subject_id] = [
                    'attended' => $attended,
                    'total' => $total_sessions,
                    'percentage' => $percentage
                ];
                
                $student_attendance_data[$student_id]['total_attended'] += $attended;
                $student_attendance_data[$student_id]['total_sessions'] += $total_sessions;
            }
            
            // Calculate overall percentage
            if ($student_attendance_data[$student_id]['total_sessions'] > 0) {
                $student_attendance_data[$student_id]['overall_percentage'] = 
                    round(($student_attendance_data[$student_id]['total_attended'] / $student_attendance_data[$student_id]['total_sessions']) * 100, 1);
            }
        }
    }
}

mysqli_stmt_close($students_stmt);

// Filter students by attendance percentage range
$filtered_students = [];
foreach ($student_attendance_data as $student_id => $data) {
    if ($data['overall_percentage'] >= $min_attendance && $data['overall_percentage'] <= $max_attendance) {
        $filtered_students[$student_id] = $data;
    }
}

// Sort students by overall percentage (descending)
uasort($filtered_students, function($a, $b) {
    return $b['overall_percentage'] <=> $a['overall_percentage'];
});

// Pagination
$records_per_page = 50;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;
$total_records = count($filtered_students);
$total_pages = ceil($total_records / $records_per_page);

// Get current page students
$current_page_students = array_slice($filtered_students, $offset, $records_per_page, true);

// Calculate summary statistics
$total_students = count($filtered_students);
$avg_percentage = 0;
$min_percentage = 100;
$max_percentage = 0;
$attendance_distribution = [
    'excellent' => 0, // 90-100%
    'good' => 0,      // 75-89%
    'average' => 0,   // 50-74%
    'poor' => 0,      // 0-49%
];

foreach ($filtered_students as $data) {
    $percentage = $data['overall_percentage'];
    $avg_percentage += $percentage;
    $min_percentage = min($min_percentage, $percentage);
    $max_percentage = max($max_percentage, $percentage);
    
    if ($percentage >= 90) {
        $attendance_distribution['excellent']++;
    } elseif ($percentage >= 75) {
        $attendance_distribution['good']++;
    } elseif ($percentage >= 50) {
        $attendance_distribution['average']++;
    } else {
        $attendance_distribution['poor']++;
    }
}

$avg_percentage = $total_students > 0 ? round($avg_percentage / $total_students, 1) : 0;
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-users text-primary"></i> 360° Students Attendance Overview
            <small class="text-muted ms-2">Faculty: <?php echo htmlspecialchars($faculty['faculty_name']); ?></small>
        </h1>
        <div class="btn-group">
            <button onclick="printReport()" class="btn btn-primary btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="exportToExcel()" class="btn btn-success btn-sm">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button onclick="exportToCSV()" class="btn btn-info btn-sm">
                <i class="fas fa-file-csv"></i> CSV
            </button>
            <a href="bulk_download.php?<?php echo http_build_query($_GET); ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-download"></i> Bulk
            </a>
        </div>
    </div>

    <!-- Show error if no subjects -->
    <?php if (isset($no_subjects_message)): ?>
    <div class="alert alert-warning mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo $no_subjects_message; ?>
        <a href="360_students_attendance.php" class="btn btn-sm btn-outline-warning ms-2">Reset Filters</a>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Students
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_students); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Avg Attendance %
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $avg_percentage; ?>%
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-percentage fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Highest %
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_students > 0 ? $max_percentage : 0; ?>%
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-up fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Lowest %
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_students > 0 ? $min_percentage : 0; ?>%
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-down fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-8 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Attendance Distribution
                            </div>
                            <div class="row">
                                <div class="col-3 text-center">
                                    <span class="badge bg-success p-2">90-100%</span><br>
                                    <small><?php echo $attendance_distribution['excellent']; ?></small>
                                </div>
                                <div class="col-3 text-center">
                                    <span class="badge bg-info p-2">75-89%</span><br>
                                    <small><?php echo $attendance_distribution['good']; ?></small>
                                </div>
                                <div class="col-3 text-center">
                                    <span class="badge bg-warning p-2">50-74%</span><br>
                                    <small><?php echo $attendance_distribution['average']; ?></small>
                                </div>
                                <div class="col-3 text-center">
                                    <span class="badge bg-danger p-2">0-49%</span><br>
                                    <small><?php echo $attendance_distribution['poor']; ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter"></i> Filter Students
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Subject</label>
                    <select name="subject_id" class="form-control">
                        <option value="">All Subjects</option>
                        <?php foreach($subjects_data as $subject): ?>
                            <option value="<?php echo intval($subject['subject_id']); ?>" 
                                <?php echo ($subject_filter == $subject['subject_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Section</label>
                    <select name="section" class="form-control">
                        <option value="">All Sections</option>
                        <?php foreach($sections_data as $section): ?>
                            <option value="<?php echo htmlspecialchars($section['section']); ?>" 
                                <?php echo ($section_filter == $section['section']) ? 'selected' : ''; ?>>
                                Section <?php echo htmlspecialchars($section['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Attendance Range</label>
                    <div class="row">
                        <div class="col-6">
                            <input type="number" name="min_attendance" class="form-control" 
                                   value="<?php echo $min_attendance; ?>" min="0" max="100" placeholder="Min %">
                        </div>
                        <div class="col-6">
                            <input type="number" name="max_attendance" class="form-control" 
                                   value="<?php echo $max_attendance; ?>" min="0" max="100" placeholder="Max %">
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Search (Name/ID/ID Number)</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?php echo htmlspecialchars($search_query); ?>" 
                           placeholder="Search student name, ID, or ID number">
                </div>
                
                <div class="col-md-12">
                    <div class="btn-group mt-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="360_students_attendance.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                        <button type="button" class="btn btn-success" onclick="showExportOptions()">
                            <i class="fas fa-download"></i> Export Options
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Students Attendance Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table"></i> Students Attendance Summary
                <span class="badge bg-secondary ms-2"><?php echo number_format($total_records); ?> students</span>
            </h6>
            <div>
                <button class="btn btn-sm btn-outline-primary me-2" onclick="toggleSubjectColumns()">
                    <i class="fas fa-eye-slash"></i> Toggle Subjects
                </button>
                <button class="btn btn-sm btn-success" onclick="quickExport()">
                    <i class="fas fa-download"></i> Quick Download
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if ($total_records > 0 && !empty($subject_sessions)): ?>
                <div class="table-responsive" id="attendanceTable">
                    <table class="table table-bordered table-hover table-striped" width="100%" cellspacing="0">
                        <thead class="bg-light">
                            <tr>
                                <th rowspan="2">#</th>
                                <th rowspan="2">Student ID</th>
                                <th rowspan="2">Student Name</th>
                                <th rowspan="2">Section</th>
                                <th rowspan="2">Department</th>
                                <th rowspan="2">ID Number</th>
                                
                                <!-- Subject Headers -->
                                <?php foreach ($subject_sessions as $subject_id => $subject): ?>
                                    <th class="subject-header text-center" data-subject-id="<?php echo $subject_id; ?>">
                                        <small class="d-block"><?php echo htmlspecialchars($subject['subject_code']); ?></small>
                                        <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">(<?php echo $subject['total_sessions']; ?> sessions)</small>
                                    </th>
                                <?php endforeach; ?>
                                
                                <th rowspan="2" class="bg-light text-center">
                                    <strong>Overall</strong><br>
                                    <small class="text-muted"><?php echo array_sum(array_column($subject_sessions, 'total_sessions')); ?> total sessions</small>
                                </th>
                                <th rowspan="2" class="bg-light text-center">Actions</th>
                            </tr>
                            <tr>
                                <!-- Attendance Percentage Headers -->
                                <?php foreach ($subject_sessions as $subject_id => $subject): ?>
                                    <th class="text-center subject-percentage-header">
                                        <small>Attendance %</small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = $offset + 1;
                            foreach ($current_page_students as $student_id => $data): 
                                $student_info = $data['info'];
                                $overall_percentage = $data['overall_percentage'];
                                
                                // Determine overall badge color
                                if ($overall_percentage >= 90) {
                                    $overall_badge = 'success';
                                } elseif ($overall_percentage >= 75) {
                                    $overall_badge = 'info';
                                } elseif ($overall_percentage >= 50) {
                                    $overall_badge = 'warning';
                                } else {
                                    $overall_badge = 'danger';
                                }
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td class="student-id">
                                    <span class="font-monospace fw-bold">
                                        <?php echo htmlspecialchars($student_info['student_id']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($student_info['student_name']); ?></td>
                                <td>
                                    <span class="badge bg-info">Section <?php echo htmlspecialchars($student_info['section']); ?></span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($student_info['student_department']); ?></small>
                                </td>
                                <td class="id-number">
                                    <span class="text-muted">
                                        <?php echo htmlspecialchars($student_info['id_number']); ?>
                                    </span>
                                </td>
                                
                                <!-- Subject-wise attendance percentages -->
                                <?php foreach ($subject_sessions as $subject_id => $subject): 
                                    $subject_data = isset($data['subjects'][$subject_id]) ? $data['subjects'][$subject_id] : ['percentage' => 0, 'attended' => 0, 'total' => $subject['total_sessions']];
                                    $percentage = $subject_data['percentage'];
                                    
                                    // Determine badge color for subject
                                    if ($percentage >= 90) {
                                        $badge_class = 'success';
                                    } elseif ($percentage >= 75) {
                                        $badge_class = 'info';
                                    } elseif ($percentage >= 50) {
                                        $badge_class = 'warning';
                                    } else {
                                        $badge_class = 'danger';
                                    }
                                ?>
                                <td class="text-center subject-cell" data-subject-id="<?php echo $subject_id; ?>">
                                    <div class="progress mb-1" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $badge_class; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%"
                                             title="<?php echo $subject_data['attended']; ?>/<?php echo $subject_data['total']; ?> sessions">
                                            <?php if ($percentage > 20): ?>
                                                <?php echo $percentage; ?>%
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $subject_data['attended']; ?>/<?php echo $subject_data['total']; ?>
                                    </small>
                                </td>
                                <?php endforeach; ?>
                                
                                <!-- Overall percentage -->
                                <td class="text-center bg-light">
                                    <div class="progress mb-1" style="height: 25px;">
                                        <div class="progress-bar bg-<?php echo $overall_badge; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $overall_percentage; ?>%"
                                             title="<?php echo $data['total_attended']; ?>/<?php echo $data['total_sessions']; ?> total sessions">
                                            <strong><?php echo $overall_percentage; ?>%</strong>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $data['total_attended']; ?>/<?php echo $data['total_sessions']; ?> total
                                    </small>
                                </td>
                                
                                <!-- Actions -->
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" 
                                                onclick="viewStudentDetails('<?php echo htmlspecialchars($student_id, ENT_QUOTES); ?>')"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-info" 
                                                onclick="viewAttendanceHistory('<?php echo htmlspecialchars($student_id, ENT_QUOTES); ?>')"
                                                title="Attendance History">
                                            <i class="fas fa-history"></i>
                                        </button>
                                        <button class="btn btn-outline-success" 
                                                onclick="generateStudentReport('<?php echo htmlspecialchars($student_id, ENT_QUOTES); ?>')"
                                                title="Generate Report">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php 
                                    $query_params = $_GET;
                                    $query_params['page'] = $page - 1;
                                    echo http_build_query($query_params); 
                               ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php 
                                        $query_params = $_GET;
                                        $query_params['page'] = $i;
                                        echo http_build_query($query_params); 
                                   ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php 
                                    $query_params = $_GET;
                                    $query_params['page'] = $page + 1;
                                    echo http_build_query($query_params); 
                               ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $records_per_page, $total_records)); ?> 
                        of <?php echo number_format($total_records); ?> students
                    </small>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users-slash fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No students found</h4>
                    <p class="text-muted">Try adjusting your filters or attendance range</p>
                    <a href="360_students_attendance.php" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Reset Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Statistics -->
    <?php if ($total_records > 0 && !empty($subject_sessions)): ?>
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> Subject-wise Average Attendance
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Total Sessions</th>
                                    <th>Avg Attendance %</th>
                                    <th>Top Student</th>
                                    <th>Lowest Student</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Calculate subject-wise averages
                                $subject_averages = [];
                                foreach ($subject_sessions as $subject_id => $subject) {
                                    $total_percentage = 0;
                                    $count = 0;
                                    $top_student = ['name' => '', 'percentage' => 0];
                                    $lowest_student = ['name' => '', 'percentage' => 100];
                                    
                                    foreach ($filtered_students as $student_id => $data) {
                                        if (isset($data['subjects'][$subject_id])) {
                                            $percentage = $data['subjects'][$subject_id]['percentage'];
                                            $total_percentage += $percentage;
                                            $count++;
                                            
                                            // Track top student
                                            if ($percentage > $top_student['percentage']) {
                                                $top_student = [
                                                    'name' => $data['info']['student_name'],
                                                    'percentage' => $percentage
                                                ];
                                            }
                                            
                                            // Track lowest student
                                            if ($percentage < $lowest_student['percentage']) {
                                                $lowest_student = [
                                                    'name' => $data['info']['student_name'],
                                                    'percentage' => $percentage
                                                ];
                                            }
                                        }
                                    }
                                    
                                    $avg_percentage = $count > 0 ? round($total_percentage / $count, 1) : 0;
                                    $subject_averages[] = [
                                        'subject' => $subject,
                                        'avg_percentage' => $avg_percentage,
                                        'top_student' => $top_student,
                                        'lowest_student' => $lowest_student,
                                        'count' => $count
                                    ];
                                }
                                
                                // Sort by average percentage (descending)
                                usort($subject_averages, function($a, $b) {
                                    return $b['avg_percentage'] <=> $a['avg_percentage'];
                                });
                                
                                foreach ($subject_averages as $subject_avg):
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($subject_avg['subject']['subject_code']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($subject_avg['subject']['subject_name']); ?></small>
                                    </td>
                                    <td><?php echo $subject_avg['subject']['total_sessions']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <?php 
                                            $badge_class = '';
                                            if ($subject_avg['avg_percentage'] >= 90) $badge_class = 'success';
                                            elseif ($subject_avg['avg_percentage'] >= 75) $badge_class = 'info';
                                            elseif ($subject_avg['avg_percentage'] >= 50) $badge_class = 'warning';
                                            else $badge_class = 'danger';
                                            ?>
                                            <div class="progress-bar bg-<?php echo $badge_class; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $subject_avg['avg_percentage']; ?>%">
                                                <?php echo $subject_avg['avg_percentage']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo !empty($subject_avg['top_student']['name']) ? htmlspecialchars($subject_avg['top_student']['name']) : 'N/A'; ?><br>
                                            <span class="badge bg-success"><?php echo $subject_avg['top_student']['percentage']; ?>%</span>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo !empty($subject_avg['lowest_student']['name']) ? htmlspecialchars($subject_avg['lowest_student']['name']) : 'N/A'; ?><br>
                                            <span class="badge bg-danger"><?php echo $subject_avg['lowest_student']['percentage']; ?>%</span>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-trophy"></i> Top 10 Performing Students
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student Name</th>
                                    <th>Section</th>
                                    <th>Overall %</th>
                                    <th>Attendance</th>
                                    <th>Best Subject</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $top_students = array_slice($filtered_students, 0, 10, true);
                                $rank = 1;
                                foreach ($top_students as $student_id => $data):
                                    // Find best subject
                                    $best_subject = ['percentage' => 0, 'name' => ''];
                                    foreach ($data['subjects'] as $subject_id => $subject_data) {
                                        if (isset($subject_sessions[$subject_id]) && $subject_data['percentage'] > $best_subject['percentage']) {
                                            $best_subject = [
                                                'percentage' => $subject_data['percentage'],
                                                'name' => $subject_sessions[$subject_id]['subject_code']
                                            ];
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($rank == 1): ?>
                                            <span class="badge bg-warning text-dark">🥇 1st</span>
                                        <?php elseif ($rank == 2): ?>
                                            <span class="badge bg-secondary">🥈 2nd</span>
                                        <?php elseif ($rank == 3): ?>
                                            <span class="badge bg-danger">🥉 3rd</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">#<?php echo $rank; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($data['info']['student_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($student_id); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">Section <?php echo htmlspecialchars($data['info']['section']); ?></span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $data['overall_percentage']; ?>%">
                                                <?php echo $data['overall_percentage']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo $data['total_attended']; ?>/<?php echo $data['total_sessions']; ?><br>
                                            <span class="text-muted">sessions</span>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($best_subject['name']); ?>: <?php echo $best_subject['percentage']; ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php 
                                $rank++;
                                endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Export Options Modal -->
<div class="modal fade" id="exportOptionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-download me-2"></i>Export Options</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Export Format</label>
                            <select id="exportFormat" class="form-select">
                                <option value="excel">Excel (XLSX) - Recommended</option>
                                <option value="csv">CSV (Comma Separated Values)</option>
                                <option value="pdf">PDF Document</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Include Data</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeAll" checked>
                                <label class="form-check-label" for="includeAll">All Students (<?php echo $total_records; ?>)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeCurrent" checked>
                                <label class="form-check-label" for="includeCurrent">Current Page Only (<?php echo count($current_page_students); ?>)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeSummary">
                                <label class="form-check-label" for="includeSummary">Include Summary Statistics</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Include Columns</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="colAll" checked onchange="toggleAllExportColumns(this)">
                                <label class="form-check-label" for="colAll">All Columns</label>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-column-check" type="checkbox" name="exportColumns[]" value="student_info" checked>
                                        <label class="form-check-label">Student Info</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input export-column-check" type="checkbox" name="exportColumns[]" value="subjects" checked>
                                        <label class="form-check-label">Subject Percentages</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input export-column-check" type="checkbox" name="exportColumns[]" value="overall" checked>
                                        <label class="form-check-label">Overall Percentage</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input export-column-check" type="checkbox" name="exportColumns[]" value="attendance_counts" checked>
                                        <label class="form-check-label">Attendance Counts</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input export-column-check" type="checkbox" name="exportColumns[]" value="rank" checked>
                                        <label class="form-check-label">Rank/Position</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input export-column-check" type="checkbox" name="exportColumns[]" value="section_stats">
                                        <label class="form-check-label">Section Statistics</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File Name</label>
                            <input type="text" id="exportFileName" class="form-control" 
                                   value="students_attendance_<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="processExport()">
                    <i class="fas fa-download"></i> Export Now
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

<script>
// Toggle subject columns visibility
function toggleSubjectColumns() {
    const subjectHeaders = document.querySelectorAll('.subject-header');
    const subjectCells = document.querySelectorAll('.subject-cell');
    const percentageHeaders = document.querySelectorAll('.subject-percentage-header');
    
    const isVisible = subjectHeaders[0].style.display !== 'none';
    
    subjectHeaders.forEach(header => {
        header.style.display = isVisible ? 'none' : '';
    });
    
    subjectCells.forEach(cell => {
        cell.style.display = isVisible ? 'none' : '';
    });
    
    percentageHeaders.forEach(header => {
        header.style.display = isVisible ? 'none' : '';
    });
}

// Show export options modal
function showExportOptions() {
    const exportModal = new bootstrap.Modal(document.getElementById('exportOptionsModal'));
    exportModal.show();
}

// Toggle all export columns
function toggleAllExportColumns(checkbox) {
    const isChecked = checkbox.checked;
    document.querySelectorAll('.export-column-check').forEach(col => {
        col.checked = isChecked;
    });
}

// Quick export function
function quickExport() {
    const format = confirm("Export as Excel (OK) or CSV (Cancel)?") ? 'excel' : 'csv';
    if (format === 'excel') {
        exportToExcel();
    } else {
        exportToCSV();
    }
}

// Print report
function printReport() {
    var printContent = document.getElementById('attendanceTable').innerHTML;
    var originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>360° Students Attendance Report - <?php echo date('Y-m-d'); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 11px; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: middle; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .summary { margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; font-size: 12px; }
                .summary-item { display: inline-block; margin-right: 30px; }
                .badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; }
                .progress { height: 15px; margin-bottom: 5px; }
                .progress-bar { font-size: 10px; line-height: 15px; }
                @media print {
                    @page { size: landscape; margin: 0.5cm; }
                    .no-print { display: none; }
                    body { margin: 0; }
                    table { font-size: 9px; }
                    .progress { height: 12px; }
                    .progress-bar { font-size: 8px; line-height: 12px; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>360° Students Attendance Report</h1>
                <h3>Faculty: <?php echo htmlspecialchars($faculty['faculty_name']); ?></h3>
                <p>Generated on: <?php echo date('F j, Y h:i A'); ?></p>
                
                <div class="summary">
                    <h4>Summary Statistics</h4>
                    <div class="summary-item"><strong>Total Students:</strong> <?php echo number_format($total_students); ?></div>
                    <div class="summary-item"><strong>Average Attendance:</strong> <?php echo $avg_percentage; ?>%</div>
                    <div class="summary-item"><strong>Date Range:</strong> <?php echo date('d/m/Y', strtotime($date_from)); ?> to <?php echo date('d/m/Y', strtotime($date_to)); ?></div>
                    <div class="summary-item"><strong>Sections:</strong> <?php echo !empty($section_filter) ? htmlspecialchars($section_filter) : 'All'; ?></div>
                    <br>
                    <div class="summary-item"><strong>Excellent (90-100%):</strong> <?php echo $attendance_distribution['excellent']; ?></div>
                    <div class="summary-item"><strong>Good (75-89%):</strong> <?php echo $attendance_distribution['good']; ?></div>
                    <div class="summary-item"><strong>Average (50-74%):</strong> <?php echo $attendance_distribution['average']; ?></div>
                    <div class="summary-item"><strong>Poor (0-49%):</strong> <?php echo $attendance_distribution['poor']; ?></div>
                </div>
            </div>
            ${printContent}
            <div style="margin-top: 50px; font-size: 10px; text-align: center;">
                <p>Report generated by: <?php echo htmlspecialchars($faculty['faculty_name']); ?> | Page 1 of 1</p>
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 500);
                }
            <\/script>
        </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// FIXED: Excel Export - Prevents date conversion issue
function exportToExcel() {
    try {
        // Get the table
        const table = document.getElementById('attendanceTable').querySelector('table');
        
        // Clone the table to avoid modifying the original
        const tableClone = table.cloneNode(true);
        
        // Clean up table before conversion
        // 1. Remove action column
        const headerRows = tableClone.querySelectorAll('thead tr');
        headerRows.forEach(headerRow => {
            const lastHeaderCell = headerRow.querySelector('th:last-child');
            if (lastHeaderCell && lastHeaderCell.textContent.includes('Actions')) {
                headerRow.removeChild(lastHeaderCell);
            }
        });
        
        // 2. Remove action cells from each row
        tableClone.querySelectorAll('tbody tr').forEach(row => {
            const lastCell = row.querySelector('td:last-child');
            if (lastCell && lastCell.querySelector('.btn-group')) {
                row.removeChild(lastCell);
            }
        });
        
        // 3. Clean up header text
        tableClone.querySelectorAll('thead th').forEach(th => {
            let headerText = th.textContent.trim();
            // Remove line breaks and extra spaces
            headerText = headerText.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
            // Remove session count from subject headers
            if (headerText.includes('(')) {
                headerText = headerText.replace(/\(.*\)/, '').trim();
            }
            th.textContent = headerText;
        });
        
        // 4. Process data rows to prevent date conversion
        tableClone.querySelectorAll('tbody tr').forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, cellIndex) => {
                let cellText = cell.textContent.trim();
                
                // Clean up text
                cellText = cellText.replace(/\s+/g, ' ').trim();
                
                // For subject and overall percentage cells (skip first 6 columns which are student info)
                if (cellIndex >= 6) {
                    // Extract just the percentage number (remove everything after %)
                    const percentageMatch = cellText.match(/(\d+(?:\.\d+)?%)/);
                    if (percentageMatch) {
                        cellText = percentageMatch[0];
                    }
                    
                    // For cells with attendance counts like "1/2", format to prevent date conversion
                    const countMatch = cellText.match(/(\d+)\/(\d+)/);
                    if (countMatch) {
                        // Format as "1 of 2" instead of "1/2" to prevent date conversion
                        cellText = `${countMatch[1]} of ${countMatch[2]}`;
                    }
                }
                
                cell.textContent = cellText;
            });
        });
        
        // Convert table to workbook
        const workbook = XLSX.utils.table_to_book(tableClone, {sheet: "Attendance"});
        
        // Get the worksheet
        const worksheet = workbook.Sheets["Attendance"];
        
        // Set column widths for better readability
        const maxWidth = 30;
        const wscols = [];
        const headerCells = tableClone.querySelectorAll('thead tr:first-child th');
        headerCells.forEach((th) => {
            const text = th.textContent || '';
            const width = Math.min(maxWidth, Math.max(10, text.length * 1.2));
            wscols.push({wch: width});
        });
        
        worksheet['!cols'] = wscols;
        
        // Add metadata
        if (!workbook.Props) workbook.Props = {};
        workbook.Props.Title = "360° Students Attendance Report";
        workbook.Props.Author = "<?php echo htmlspecialchars($faculty['faculty_name']); ?>";
        workbook.Props.CreatedDate = new Date();
        
        // Generate and download the file
        const fileName = '360_students_attendance_' + new Date().toISOString().split('T')[0] + '.xlsx';
        XLSX.writeFile(workbook, fileName);
        
        // Show success message
        alert('Excel file downloaded successfully! Attendance counts are formatted to prevent date conversion.');
        
    } catch (error) {
        console.error('Excel export error:', error);
        alert('Error downloading Excel file. Please try again.');
    }
}

// FIXED: CSV Export - Prevents date conversion issue
function exportToCSV() {
    try {
        let csv = [];
        
        // Get table headers (clean them up)
        const headers = [];
        document.querySelectorAll('#attendanceTable thead tr:first-child th').forEach((th, index) => {
            // Skip action column
            if (!th.textContent.includes('Actions')) {
                let headerText = th.textContent.trim();
                // Clean up header text
                headerText = headerText.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
                // Remove session count from subject headers
                if (headerText.includes('(')) {
                    headerText = headerText.replace(/\(.*\)/, '').trim();
                }
                headers.push(headerText);
            }
        });
        csv.push(headers.join(','));
        
        // Get table rows data (clean up to prevent date conversion)
        document.querySelectorAll('#attendanceTable tbody tr').forEach(row => {
            const rowData = [];
            row.querySelectorAll('td').forEach((td, index) => {
                // Skip action column (last column)
                if (index < row.querySelectorAll('td').length - 1) {
                    let cellText = td.textContent.trim();
                    
                    // Clean up text
                    cellText = cellText.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
                    
                    // For subject and overall data (skip first 6 columns which are student info)
                    if (index >= 6) {
                        // Extract just the percentage number
                        const percentageMatch = cellText.match(/(\d+(?:\.\d+)?%)/);
                        if (percentageMatch) {
                            cellText = percentageMatch[0];
                        }
                        
                        // For attendance counts, format to prevent date conversion
                        const countMatch = cellText.match(/(\d+)\s*\/\s*(\d+)/);
                        if (countMatch) {
                            // Format as "X of Y" instead of "X/Y" to prevent Excel date conversion
                            cellText = `${countMatch[1]} of ${countMatch[2]}`;
                        }
                    }
                    
                    // Handle commas and quotes in text for CSV
                    if (cellText.includes(',') || cellText.includes('"') || cellText.includes('\n')) {
                        cellText = '"' + cellText.replace(/"/g, '""') + '"';
                    }
                    
                    rowData.push(cellText);
                }
            });
            csv.push(rowData.join(','));
        });
        
        // Convert to CSV string
        const csvString = csv.join('\n');
        
        // Create blob and download
        const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
        const fileName = '360_students_attendance_' + new Date().toISOString().split('T')[0] + '.csv';
        saveAs(blob, fileName);
        
        // Show success message
        alert('CSV file downloaded successfully! Attendance counts are formatted to prevent date conversion.');
        
    } catch (error) {
        console.error('CSV export error:', error);
        alert('Error downloading CSV file. Please try again.');
    }
}

// Process export from modal
function processExport() {
    const format = document.getElementById('exportFormat').value;
    
    if (format === 'excel') {
        exportToExcel();
    } else if (format === 'csv') {
        exportToCSV();
    } else if (format === 'pdf') {
        printReport();
    }
    
    // Close modal
    const exportModal = bootstrap.Modal.getInstance(document.getElementById('exportOptionsModal'));
    if (exportModal) {
        exportModal.hide();
    }
}

// View student details
function viewStudentDetails(studentId) {
    alert('View student details for: ' + studentId);
}

// View attendance history
function viewAttendanceHistory(studentId) {
    alert('View attendance history for: ' + studentId);
}

// Generate student report
function generateStudentReport(studentId) {
    alert('Generate report for: ' + studentId);
}
</script>

<?php include 'footer.php'; ?>