<?php
// attendance_viewer_faculty.php
require_once 'config.php';
session_start(); // Add session start

// Security check - faculty only
if (!isset($_SESSION['faculty_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];
$page_title = "Attendance Viewer - Faculty";
include 'header.php';

// Get faculty details using prepared statement
$faculty_query = "SELECT * FROM faculty WHERE faculty_id = ?";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "s", $faculty_id);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty = mysqli_fetch_assoc($faculty_result);
mysqli_stmt_close($faculty_stmt);

// Get subjects taught by this faculty from sessions table using prepared statement
$subjects_query = "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name 
                   FROM sessions se 
                   JOIN subjects s ON se.subject_id = s.subject_id 
                   WHERE se.faculty_id = ? 
                   ORDER BY s.subject_name";
$subjects_stmt = mysqli_prepare($conn, $subjects_query);
mysqli_stmt_bind_param($subjects_stmt, "s", $faculty_id);
mysqli_stmt_execute($subjects_stmt);
$subjects_result = mysqli_stmt_get_result($subjects_stmt);

// Get all sections from students
$sections_query = "SELECT DISTINCT section FROM students WHERE section != '' ORDER BY section";
$sections_result = mysqli_query($conn, $sections_query);

// Initialize filter variables with sanitization
$subject_filter = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$section_filter = isset($_GET['section']) ? mysqli_real_escape_string($conn, trim($_GET['section'])) : '';
$student_id_filter = isset($_GET['student_id']) ? mysqli_real_escape_string($conn, trim($_GET['student_id'])) : '';
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, trim($_GET['date_from'])) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, trim($_GET['date_to'])) : date('Y-m-d');
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// Validate date format
if (!empty($date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = date('Y-m-d', strtotime('-30 days'));
}
if (!empty($date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = date('Y-m-d');
}

// Build base query WITHOUT status column using prepared statements
$query = "SELECT 
            ar.record_id,
            ar.student_id,
            s.student_name,
            s.section,
            s.student_department,
            s.id_number,
            sub.subject_code,
            sub.subject_name,
            ses.start_time as session_time,
            ar.marked_at
          FROM attendance_records ar
          JOIN students s ON ar.student_id = s.student_id
          JOIN sessions ses ON ar.session_id = ses.session_id
          JOIN subjects sub ON ses.subject_id = sub.subject_id
          WHERE ses.faculty_id = ?";

// Prepare parameters for main query
$params = [$faculty_id];
$param_types = "s";

// Apply filters with prepared statements
if ($subject_filter > 0) {
    $query .= " AND sub.subject_id = ?";
    $params[] = $subject_filter;
    $param_types .= "i";
}

if (!empty($section_filter)) {
    $query .= " AND s.section = ?";
    $params[] = $section_filter;
    $param_types .= "s";
}

if (!empty($student_id_filter)) {
    $query .= " AND ar.student_id LIKE ?";
    $params[] = "%$student_id_filter%";
    $param_types .= "s";
}

if (!empty($search_query)) {
    $query .= " AND (s.student_name LIKE ? OR s.student_id LIKE ? OR s.id_number LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $param_types .= "sss";
}

// Date range filter
if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(ar.marked_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $param_types .= "ss";
}

// Order by
$query .= " ORDER BY ar.marked_at DESC, s.section, s.student_name";

// For summary statistics - simplified without status using prepared statement
$summary_query = "SELECT 
        COUNT(*) as total_records,
        COUNT(DISTINCT ar.student_id) as total_students,
        COUNT(DISTINCT DATE(ar.marked_at)) as total_days
      FROM attendance_records ar
      JOIN sessions ses ON ar.session_id = ses.session_id
      JOIN subjects sub ON ses.subject_id = sub.subject_id
      WHERE ses.faculty_id = ?";

// Prepare parameters for summary query
$summary_params = [$faculty_id];
$summary_param_types = "s";

// Add same filters to summary query
if ($subject_filter > 0) {
    $summary_query .= " AND sub.subject_id = ?";
    $summary_params[] = $subject_filter;
    $summary_param_types .= "i";
}

if (!empty($section_filter)) {
    $summary_query .= " AND EXISTS (SELECT 1 FROM students s WHERE s.student_id = ar.student_id AND s.section = ?)";
    $summary_params[] = $section_filter;
    $summary_param_types .= "s";
}

if (!empty($student_id_filter)) {
    $summary_query .= " AND ar.student_id LIKE ?";
    $summary_params[] = "%$student_id_filter%";
    $summary_param_types .= "s";
}

if (!empty($search_query)) {
    $summary_query .= " AND EXISTS (SELECT 1 FROM students s WHERE s.student_id = ar.student_id AND (s.student_name LIKE ? OR s.student_id LIKE ? OR s.id_number LIKE ?))";
    $summary_params[] = "%$search_query%";
    $summary_params[] = "%$search_query%";
    $summary_params[] = "%$search_query%";
    $summary_param_types .= "sss";
}

if (!empty($date_from) && !empty($date_to)) {
    $summary_query .= " AND DATE(ar.marked_at) BETWEEN ? AND ?";
    $summary_params[] = $date_from;
    $summary_params[] = $date_to;
    $summary_param_types .= "ss";
}

// Execute summary query with prepared statement
$summary_stmt = mysqli_prepare($conn, $summary_query);
if ($summary_stmt) {
    mysqli_stmt_bind_param($summary_stmt, $summary_param_types, ...$summary_params);
    mysqli_stmt_execute($summary_stmt);
    $summary_result = mysqli_stmt_get_result($summary_stmt);
    $summary = mysqli_fetch_assoc($summary_result);
    mysqli_stmt_close($summary_stmt);
} else {
    $summary = ['total_records' => 0, 'total_students' => 0, 'total_days' => 0];
}

// For pagination
$records_per_page = 50;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Count total records with same filters using prepared statement
$count_query = "SELECT COUNT(*) as total
                FROM attendance_records ar
                JOIN sessions ses ON ar.session_id = ses.session_id
                JOIN subjects sub ON ses.subject_id = sub.subject_id
                WHERE ses.faculty_id = ?";

$count_params = [$faculty_id];
$count_param_types = "s";

if ($subject_filter > 0) {
    $count_query .= " AND sub.subject_id = ?";
    $count_params[] = $subject_filter;
    $count_param_types .= "i";
}

if (!empty($section_filter)) {
    $count_query .= " AND EXISTS (SELECT 1 FROM students s WHERE s.student_id = ar.student_id AND s.section = ?)";
    $count_params[] = $section_filter;
    $count_param_types .= "s";
}

if (!empty($student_id_filter)) {
    $count_query .= " AND ar.student_id LIKE ?";
    $count_params[] = "%$student_id_filter%";
    $count_param_types .= "s";
}

if (!empty($search_query)) {
    $count_query .= " AND EXISTS (SELECT 1 FROM students s WHERE s.student_id = ar.student_id AND (s.student_name LIKE ? OR s.student_id LIKE ? OR s.id_number LIKE ?))";
    $count_params[] = "%$search_query%";
    $count_params[] = "%$search_query%";
    $count_params[] = "%$search_query%";
    $count_param_types .= "sss";
}

if (!empty($date_from) && !empty($date_to)) {
    $count_query .= " AND DATE(ar.marked_at) BETWEEN ? AND ?";
    $count_params[] = $date_from;
    $count_params[] = $date_to;
    $count_param_types .= "ss";
}

$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $count_param_types, ...$count_params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$count_row = mysqli_fetch_assoc($count_result);
$total_records = $count_row['total'] ?? 0;
$total_pages = ceil($total_records / $records_per_page);
mysqli_stmt_close($count_stmt);

// Add pagination to main query
$query .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $records_per_page;
$param_types .= "ii";

// Execute main query with prepared statement
$main_stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($main_stmt, $param_types, ...$params);
mysqli_stmt_execute($main_stmt);
$attendance_result = mysqli_stmt_get_result($main_stmt);

// Get chart data for daily attendance trend using prepared statement
$chart_query = "SELECT 
                DATE(ar.marked_at) as date,
                COUNT(*) as total
                FROM attendance_records ar
                JOIN sessions ses ON ar.session_id = ses.session_id
                WHERE ses.faculty_id = ? 
                AND DATE(ar.marked_at) BETWEEN ? AND ?";
                
$chart_params = [$faculty_id, $date_from, $date_to];
$chart_param_types = "sss";

if ($subject_filter > 0) {
    $chart_query .= " AND ses.subject_id = ?";
    $chart_params[] = $subject_filter;
    $chart_param_types .= "i";
}

if (!empty($section_filter)) {
    $chart_query .= " AND EXISTS (SELECT 1 FROM students s WHERE s.student_id = ar.student_id AND s.section = ?)";
    $chart_params[] = $section_filter;
    $chart_param_types .= "s";
}

$chart_query .= " GROUP BY DATE(ar.marked_at) ORDER BY DATE(ar.marked_at)";

$chart_stmt = mysqli_prepare($conn, $chart_query);
mysqli_stmt_bind_param($chart_stmt, $chart_param_types, ...$chart_params);
mysqli_stmt_execute($chart_stmt);
$chart_result = mysqli_stmt_get_result($chart_stmt);
$chart_labels = [];
$chart_counts = [];

while($chart_row = mysqli_fetch_assoc($chart_result)) {
    $chart_labels[] = date('M d', strtotime($chart_row['date']));
    $chart_counts[] = intval($chart_row['total']);
}
mysqli_stmt_close($chart_stmt);
?>

<!-- The rest of your HTML/PHP code remains exactly the same -->
<!-- Only the PHP data fetching part has been secured -->

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-chart-bar text-primary"></i> Attendance Analytics
            <small class="text-muted ms-2">Faculty: <?php echo htmlspecialchars($faculty['faculty_name']); ?></small>
        </h1>
        <div class="btn-group">
            <button onclick="printReport()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button onclick="exportToCSV()" class="btn btn-success">
                <i class="fas fa-file-export"></i> Export CSV
            </button>
            <button onclick="exportToExcel()" class="btn btn-info">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Records
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($summary['total_records']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Unique Students
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($summary['total_students']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Days
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($summary['total_days']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Avg Records/Day
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $avg_per_day = ($summary['total_days'] > 0) ? round($summary['total_records'] / $summary['total_days'], 1) : 0;
                                echo $avg_per_day;
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
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
                <i class="fas fa-filter"></i> Filter Attendance Records
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Subject</label>
                    <select name="subject_id" class="form-control">
                        <option value="">All Subjects</option>
                        <?php 
                        mysqli_data_seek($subjects_result, 0); // Reset result pointer
                        while($subject = mysqli_fetch_assoc($subjects_result)): ?>
                            <option value="<?php echo intval($subject['subject_id']); ?>" 
                                <?php echo ($subject_filter == $subject['subject_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endwhile; ?>
                        <?php mysqli_stmt_close($subjects_stmt); ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Section</label>
                    <select name="section" class="form-control">
                        <option value="">All Sections</option>
                        <?php 
                        mysqli_data_seek($sections_result, 0);
                        while($section = mysqli_fetch_assoc($sections_result)): ?>
                            <option value="<?php echo htmlspecialchars($section['section']); ?>" 
                                <?php echo ($section_filter == $section['section']) ? 'selected' : ''; ?>>
                                Section <?php echo htmlspecialchars($section['section']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Student ID</label>
                    <input type="text" name="student_id" class="form-control" 
                           value="<?php echo htmlspecialchars($student_id_filter); ?>" 
                           placeholder="Student ID">
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
                
                <div class="col-md-6">
                    <label class="form-label">Search (Name/ID Number)</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?php echo htmlspecialchars($search_query); ?>" 
                           placeholder="Search by student name, ID, or ID number">
                </div>
                
                <div class="col-md-12">
                    <div class="btn-group mt-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="attendance_viewer_faculty.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Attendance Records Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table"></i> Attendance Records 
                <span class="badge bg-secondary ms-2"><?php echo number_format($total_records); ?> records</span>
                <span class="badge bg-info ms-1"><?php echo $summary['total_students']; ?> unique students</span>
            </h6>
            <div>
                <button class="btn btn-sm btn-outline-primary me-2" onclick="toggleColumn('student_id')">
                    <i class="fas fa-eye-slash"></i> Toggle Columns
                </button>
                <button class="btn btn-sm btn-success" onclick="quickExport()">
                    <i class="fas fa-file-download"></i> Quick Export
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($attendance_result) > 0): ?>
                <div class="table-responsive" id="attendanceTable">
                    <table class="table table-bordered table-hover table-striped" width="100%" cellspacing="0">
                        <thead class="bg-light">
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>ID Number</th>
                                <th>Section</th>
                                <th>Department</th>
                                <th>Subject</th>
                                <th>Session Time</th>
                                <th>Marked At</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = $offset + 1;
                            while ($record = mysqli_fetch_assoc($attendance_result)): 
                                // Calculate if late (if marked_at is more than 15 minutes after session start)
                                $session_time = strtotime($record['session_time']);
                                $marked_time = strtotime($record['marked_at']);
                                $is_late = ($marked_time - $session_time) > (15 * 60); // 15 minutes in seconds
                                
                                // Determine status based on timing
                                if ($is_late) {
                                    $status = 'Late';
                                    $badge_class = 'warning';
                                } else {
                                    $status = 'Present';
                                    $badge_class = 'success';
                                }
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td class="student-id">
                                    <span class="font-monospace fw-bold">
                                        <?php echo htmlspecialchars($record['student_id']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                <td class="id-number">
                                    <span class="text-muted">
                                        <?php echo htmlspecialchars($record['id_number']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info">Section <?php echo htmlspecialchars($record['section']); ?></span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($record['student_department']); ?></small>
                                </td>
                                <td>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($record['subject_code']); ?></small>
                                    <strong><?php echo htmlspecialchars($record['subject_name']); ?></strong>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y h:i A', strtotime($record['session_time'])); ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y h:i A', strtotime($record['marked_at'])); ?>
                                    <?php if ($is_late): ?>
                                        <br><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Late Entry</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <i class="fas fa-<?php echo ($status == 'Present') ? 'check' : 'clock'; ?>"></i>
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
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
                        // Show limited page numbers
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
                <?php endif; ?>

                <div class="text-center mt-3">
                    <small class="text-muted">
                        Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $records_per_page, $total_records)); ?> 
                        of <?php echo number_format($total_records); ?> records
                    </small>
                </div>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No attendance records found</h4>
                    <p class="text-muted">Try adjusting your filters or select a different date range</p>
                    <a href="attendance_viewer_faculty.php" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Reset Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Charts Card -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Daily Attendance Trend
                    </h6>
                    <small class="text-muted">Total attendance records per day</small>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="attendanceLineChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary by Section -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> Attendance by Section
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Section</th>
                                    <th>Total Students</th>
                                    <th>Total Records</th>
                                    <th>Avg Records/Student</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get section-wise statistics using prepared statement
                                $section_stats_query = "SELECT 
                                        s.section,
                                        COUNT(DISTINCT s.student_id) as total_students,
                                        COUNT(ar.record_id) as total_records,
                                        ROUND(COUNT(ar.record_id) / COUNT(DISTINCT s.student_id), 1) as avg_per_student
                                    FROM students s
                                    LEFT JOIN attendance_records ar ON s.student_id = ar.student_id
                                    LEFT JOIN sessions ses ON ar.session_id = ses.session_id
                                    WHERE 1=1";
                                    
                                $section_params = [];
                                $section_param_types = "";
                                    
                                if ($subject_filter > 0) {
                                    $section_stats_query .= " AND ses.subject_id = ?";
                                    $section_params[] = $subject_filter;
                                    $section_param_types .= "i";
                                }
                                
                                if (!empty($date_from) && !empty($date_to)) {
                                    $section_stats_query .= " AND DATE(ar.marked_at) BETWEEN ? AND ?";
                                    $section_params[] = $date_from;
                                    $section_params[] = $date_to;
                                    $section_param_types .= "ss";
                                }
                                
                                if (!empty($student_id_filter)) {
                                    $section_stats_query .= " AND ar.student_id LIKE ?";
                                    $section_params[] = "%$student_id_filter%";
                                    $section_param_types .= "s";
                                }
                                
                                $section_stats_query .= " GROUP BY s.section ORDER BY s.section";
                                
                                $section_stats_stmt = mysqli_prepare($conn, $section_stats_query);
                                if ($section_params) {
                                    mysqli_stmt_bind_param($section_stats_stmt, $section_param_types, ...$section_params);
                                }
                                mysqli_stmt_execute($section_stats_stmt);
                                $section_stats_result = mysqli_stmt_get_result($section_stats_stmt);
                                
                                while($section_stat = mysqli_fetch_assoc($section_stats_result)):
                                ?>
                                <tr>
                                    <td><strong>Section <?php echo htmlspecialchars($section_stat['section']); ?></strong></td>
                                    <td><?php echo intval($section_stat['total_students']); ?></td>
                                    <td><?php echo intval($section_stat['total_records']); ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <?php 
                                            $percentage = ($section_stat['avg_per_student'] > 10) ? 100 : ($section_stat['avg_per_student'] * 10);
                                            ?>
                                            <div class="progress-bar bg-info" role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%">
                                                <?php echo $section_stat['avg_per_student']; ?> records
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="?section=<?php echo urlencode($section_stat['section']); ?>&<?php echo http_build_query($_GET); ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-filter"></i> Filter
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; 
                                mysqli_stmt_close($section_stats_stmt);
                                mysqli_stmt_close($main_stmt);
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
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
                                <option value="csv">CSV (Comma Separated Values)</option>
                                <option value="excel">Excel (XLSX)</option>
                                <option value="pdf">PDF Document</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Range</label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="date" id="exportDateFrom" class="form-control" 
                                           value="<?php echo htmlspecialchars($date_from); ?>">
                                </div>
                                <div class="col-6">
                                    <input type="date" id="exportDateTo" class="form-control" 
                                           value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Include Columns</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="colAll" checked onchange="toggleAllColumns(this)">
                                <label class="form-check-label" for="colAll">All Columns</label>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input column-check" type="checkbox" name="columns[]" value="student_id" checked>
                                        <label class="form-check-label">Student ID</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input column-check" type="checkbox" name="columns[]" value="student_name" checked>
                                        <label class="form-check-label">Student Name</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input column-check" type="checkbox" name="columns[]" value="id_number" checked>
                                        <label class="form-check-label">ID Number</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input column-check" type="checkbox" name="columns[]" value="section" checked>
                                        <label class="form-check-label">Section</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input column-check" type="checkbox" name="columns[]" value="subject" checked>
                                        <label class="form-check-label">Subject</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input column-check" type="checkbox" name="columns[]" value="session_time" checked>
                                        <label class="form-check-label">Session Time</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input column-check" type="checkbox" name="columns[]" value="marked_at" checked>
                                        <label class="form-check-label">Marked At</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="processExport()">
                    <i class="fas fa-download"></i> Export Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

<script>
// Initialize chart
document.addEventListener('DOMContentLoaded', function() {
    // Line Chart - Daily Trend
    var lineCtx = document.getElementById('attendanceLineChart').getContext('2d');
    var lineChart = new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Attendance Records',
                data: <?php echo json_encode($chart_counts); ?>,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                pointBackgroundColor: '#4e73df',
                pointBorderColor: '#4e73df',
                pointRadius: 3,
                pointHoverRadius: 5,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value;
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Records: ' + context.parsed.y;
                        }
                    }
                }
            }
        }
    });
});

// Print Function
function printReport() {
    var printContent = document.getElementById('attendanceTable').innerHTML;
    var originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Attendance Report - <?php echo date('Y-m-d'); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 12px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .summary { margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; }
                .summary-item { display: inline-block; margin-right: 30px; }
                .badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; }
                .bg-success { background-color: #28a745; color: white; }
                .bg-warning { background-color: #ffc107; color: black; }
                .bg-info { background-color: #17a2b8; color: white; }
                @media print {
                    @page { size: landscape; margin: 0.5cm; }
                    .no-print { display: none; }
                    body { margin: 0; }
                    table { font-size: 10px; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Attendance Report</h1>
                <h3>Faculty: <?php echo htmlspecialchars($faculty['faculty_name']); ?></h3>
                <p>Generated on: <?php echo date('F j, Y h:i A'); ?></p>
                
                <div class="summary">
                    <h4>Summary</h4>
                    <div class="summary-item"><strong>Total Records:</strong> <?php echo number_format($summary['total_records']); ?></div>
                    <div class="summary-item"><strong>Unique Students:</strong> <?php echo $summary['total_students']; ?></div>
                    <div class="summary-item"><strong>Total Days:</strong> <?php echo $summary['total_days']; ?></div>
                    <div class="summary-item"><strong>Avg/Day:</strong> <?php echo ($summary['total_days'] > 0) ? round($summary['total_records'] / $summary['total_days'], 1) : 0; ?></div>
                    <br><br>
                    <div><strong>Filters Applied:</strong> 
                        Subject: <?php echo !empty($subject_filter) ? 'Selected' : 'All'; ?> | 
                        Section: <?php echo !empty($section_filter) ? htmlspecialchars($section_filter) : 'All'; ?> | 
                        Date Range: <?php echo date('d/m/Y', strtotime($date_from)); ?> to <?php echo date('d/m/Y', strtotime($date_to)); ?>
                    </div>
                </div>
            </div>
            ${printContent}
            <div style="margin-top: 50px; font-size: 11px; text-align: center;">
                <p>Report generated by: <?php echo htmlspecialchars($faculty['faculty_name']); ?> | Total Records: <?php echo number_format($total_records); ?></p>
                <p>Page 1 of 1</p>
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

// Export to CSV
function exportToCSV() {
    let csv = [];
    
    // Get headers
    let headers = [];
    document.querySelectorAll('#attendanceTable thead th').forEach((th) => {
        headers.push(th.textContent.trim().replace(/\n/g, ' '));
    });
    csv.push(headers.join(','));
    
    // Get rows data
    document.querySelectorAll('#attendanceTable tbody tr').forEach(row => {
        let rowData = [];
        row.querySelectorAll('td').forEach((td) => {
            let text = td.textContent.trim().replace(/\n/g, ' ').replace(/,/g, ';');
            // Handle commas and quotes in text
            if (text.includes(',') || text.includes('"') || text.includes("'")) {
                text = `"${text}"`;
            }
            rowData.push(text);
        });
        csv.push(rowData.join(','));
    });
    
    // Download CSV
    let csvContent = csv.join('\n');
    let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    let filename = `attendance_report_${new Date().toISOString().split('T')[0]}_${Date.now()}.csv`;
    saveAs(blob, filename);
}

// Export to Excel
function exportToExcel() {
    const table = document.getElementById('attendanceTable').querySelector('table');
    
    const workbook = XLSX.utils.table_to_book(table, { sheet: "Attendance" });
    
    // Add metadata
    workbook.Props = {
        Title: "Attendance Report",
        Author: "<?php echo htmlspecialchars($faculty['faculty_name']); ?>",
        CreatedDate: new Date()
    };
    
    // Generate Excel file
    XLSX.writeFile(workbook, `attendance_report_${new Date().toISOString().split('T')[0]}.xlsx`);
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

// Show export modal
function showExportModal() {
    const exportModal = new bootstrap.Modal(document.getElementById('exportModal'));
    exportModal.show();
}

// Toggle all columns
function toggleAllColumns(checkbox) {
    const isChecked = checkbox.checked;
    document.querySelectorAll('.column-check').forEach(col => {
        col.checked = isChecked;
    });
}

// Process export from modal
function processExport() {
    const format = document.getElementById('exportFormat').value;
    
    if (format === 'csv') {
        exportToCSV();
    } else if (format === 'excel') {
        exportToExcel();
    } else if (format === 'pdf') {
        printReport();
    }
    
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
}

// Toggle column visibility
function toggleColumn(columnClass) {
    const columns = document.querySelectorAll('.' + columnClass);
    columns.forEach(col => {
        col.style.display = col.style.display === 'none' ? '' : 'none';
    });
}
</script>

<?php include 'footer.php'; ?>
