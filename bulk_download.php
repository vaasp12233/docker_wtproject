<?php
// bulk_download.php
ob_start(); 
require_once 'config.php';
session_start();

// Security check - faculty only
if (!isset($_SESSION['faculty_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];
$page_title = "Bulk Download Students Attendance";
include 'header.php';

// Get faculty details
$faculty_query = "SELECT * FROM faculty WHERE faculty_id = ?";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "s", $faculty_id);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty = mysqli_fetch_assoc($faculty_result);
mysqli_stmt_close($faculty_stmt);

// Get all subjects taught by this faculty
$subjects_query = "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name 
                   FROM sessions se 
                   JOIN subjects s ON se.subject_id = s.subject_id 
                   WHERE se.faculty_id = ? 
                   ORDER BY s.subject_name";
$subjects_stmt = mysqli_prepare($conn, $subjects_query);
mysqli_stmt_bind_param($subjects_stmt, "s", $faculty_id);
mysqli_stmt_execute($subjects_stmt);
$subjects_result = mysqli_stmt_get_result($subjects_stmt);

$subjects_for_display = [];
while ($subject = mysqli_fetch_assoc($subjects_result)) {
    $subjects_for_display[] = $subject;
}
mysqli_stmt_close($subjects_stmt);

// Get all sections
$sections_query = "SELECT DISTINCT section FROM students WHERE section != '' ORDER BY section";
$sections_result = mysqli_query($conn, $sections_query);
$sections_data = [];
while($section = mysqli_fetch_assoc($sections_result)) {
    $sections_data[] = $section;
}

// Initialize filter variables
$subject_filter = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$section_filter = isset($_GET['section']) ? mysqli_real_escape_string($conn, trim($_GET['section'])) : '';
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, trim($_GET['date_from'])) : date('Y-m-d', strtotime('-90 days'));
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, trim($_GET['date_to'])) : date('Y-m-d');
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$min_attendance = isset($_GET['min_attendance']) ? intval($_GET['min_attendance']) : 0;
$max_attendance = isset($_GET['max_attendance']) ? intval($_GET['max_attendance']) : 100;

// Validate inputs
if (!empty($date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = date('Y-m-d', strtotime('-90 days'));
}
if (!empty($date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = date('Y-m-d');
}

if ($min_attendance < 0) $min_attendance = 0;
if ($max_attendance > 100) $max_attendance = 100;
if ($min_attendance > $max_attendance) {
    $temp = $min_attendance;
    $min_attendance = $max_attendance;
    $max_attendance = $temp;
}

// OPTIMIZED QUERY: Get all students with attendance data in ONE query
$student_query = "SELECT 
                    s.student_id,
                    s.student_name,
                    s.section,
                    s.student_department,
                    s.id_number,
                    sub.subject_id,
                    sub.subject_code,
                    sub.subject_name,
                    COUNT(DISTINCT ses.session_id) as total_sessions,
                    COUNT(DISTINCT ar.record_id) as attended_sessions
                   FROM students s
                   CROSS JOIN subjects sub
                   LEFT JOIN sessions ses ON sub.subject_id = ses.subject_id 
                        AND ses.faculty_id = ?
                        AND (? = '' OR DATE(ses.start_time) >= ?)
                        AND (? = '' OR DATE(ses.start_time) <= ?)
                        AND (? = 0 OR sub.subject_id = ?)
                   LEFT JOIN attendance_records ar ON ar.student_id = s.student_id 
                        AND ar.session_id = ses.session_id
                        AND (? = '' OR DATE(ar.marked_at) >= ?)
                        AND (? = '' OR DATE(ar.marked_at) <= ?)
                   WHERE 1=1";

$student_params = [
    $faculty_id,                    // ses.faculty_id
    $date_from, $date_from,         // start_time >=
    $date_to, $date_to,             // start_time <=
    $subject_filter, $subject_filter, // subject filter
    $date_from, $date_from,         // marked_at >=
    $date_to, $date_to              // marked_at <=
];
$student_param_types = "sssssiiissss";

if (!empty($section_filter)) {
    $student_query .= " AND s.section = ?";
    $student_params[] = $section_filter;
    $student_param_types .= "s";
}

if (!empty($search_query)) {
    $student_query .= " AND (s.student_name LIKE ? OR s.student_id LIKE ? OR s.id_number LIKE ?)";
    $student_params[] = "%$search_query%";
    $student_params[] = "%$search_query%";
    $student_params[] = "%$search_query%";
    $student_param_types .= "sss";
}

$student_query .= " GROUP BY s.student_id, sub.subject_id
                   ORDER BY s.section, s.student_name, sub.subject_name";

$student_stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($student_stmt, $student_param_types, ...$student_params);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);

// Process student data
$student_attendance_data = [];
$subject_sessions = []; // Store total sessions per subject

while ($row = mysqli_fetch_assoc($student_result)) {
    $student_id = $row['student_id'];
    $subject_id = $row['subject_id'];
    
    // Initialize student data if not exists
    if (!isset($student_attendance_data[$student_id])) {
        $student_attendance_data[$student_id] = [
            'info' => [
                'student_id' => $row['student_id'],
                'student_name' => $row['student_name'],
                'section' => $row['section'],
                'student_department' => $row['student_department'],
                'id_number' => $row['id_number']
            ],
            'subjects' => [],
            'total_attended' => 0,
            'total_sessions' => 0,
            'overall_percentage' => 0
        ];
    }
    
    // Store subject session count
    if (!isset($subject_sessions[$subject_id])) {
        $subject_sessions[$subject_id] = [
            'subject_id' => $subject_id,
            'subject_code' => $row['subject_code'],
            'subject_name' => $row['subject_name'],
            'total_sessions' => $row['total_sessions']
        ];
    }
    
    // Calculate subject percentage
    $total_sessions = $row['total_sessions'] ?: 0;
    $attended = $row['attended_sessions'] ?: 0;
    $percentage = ($total_sessions > 0) ? round(($attended / $total_sessions) * 100, 1) : 0;
    
    $student_attendance_data[$student_id]['subjects'][$subject_id] = [
        'attended' => $attended,
        'total' => $total_sessions,
        'percentage' => $percentage
    ];
    
    // Update totals
    $student_attendance_data[$student_id]['total_attended'] += $attended;
    $student_attendance_data[$student_id]['total_sessions'] += $total_sessions;
}

mysqli_stmt_close($student_stmt);

// Calculate overall percentage for each student
foreach ($student_attendance_data as $student_id => $data) {
    if ($data['total_sessions'] > 0) {
        $student_attendance_data[$student_id]['overall_percentage'] = 
            round(($data['total_attended'] / $data['total_sessions']) * 100, 1);
    }
}

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

// Calculate statistics
$total_students = count($filtered_students);
$avg_percentage = 0;
$min_percentage = 100;
$max_percentage = 0;
$attendance_distribution = ['excellent' => 0, 'good' => 0, 'average' => 0, 'poor' => 0];

foreach ($filtered_students as $data) {
    $percentage = $data['overall_percentage'];
    $avg_percentage += $percentage;
    $min_percentage = min($min_percentage, $percentage);
    $max_percentage = max($max_percentage, $percentage);
    
    if ($percentage >= 90) $attendance_distribution['excellent']++;
    elseif ($percentage >= 75) $attendance_distribution['good']++;
    elseif ($percentage >= 50) $attendance_distribution['average']++;
    else $attendance_distribution['poor']++;
}

$avg_percentage = $total_students > 0 ? round($avg_percentage / $total_students, 1) : 0;

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $export_students = $filtered_students;
    
    if (!in_array($export_type, ['csv', 'excel', 'print'])) {
        http_response_code(400);
        die("Invalid export type");
    }
    
    // Generate CSV
    if ($export_type == 'csv') {
        // Clear any previous output
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=students_attendance_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        
        // Headers - ALL SUBJECTS + OVERALL
        $headers = ['#', 'Student ID', 'Student Name', 'Section', 'Department', 'ID Number'];
        
        foreach ($subject_sessions as $subject) {
            $headers[] = $subject['subject_code'] . ' %';
            $headers[] = $subject['subject_code'] . ' (Attended/Total)';
        }
        
        $headers[] = 'Overall %';
        $headers[] = 'Overall (Attended/Total)';
        $headers[] = 'Attendance Status';
        
        fputcsv($output, $headers);
        
        // Data rows
        $counter = 1;
        foreach ($export_students as $student_id => $data) {
            $student_info = $data['info'];
            
            // Determine status
            if ($data['overall_percentage'] >= 90) $status = 'Excellent';
            elseif ($data['overall_percentage'] >= 75) $status = 'Good';
            elseif ($data['overall_percentage'] >= 50) $status = 'Average';
            else $status = 'Poor';
            
            $row = [
                $counter++,
                $student_info['student_id'],
                $student_info['student_name'],
                $student_info['section'],
                $student_info['student_department'],
                $student_info['id_number']
            ];
            
            // Add ALL subject data
            foreach ($subject_sessions as $subject_id => $subject) {
                $subject_data = isset($data['subjects'][$subject_id]) ? $data['subjects'][$subject_id] : ['percentage' => 0, 'attended' => 0, 'total' => $subject['total_sessions']];
                $row[] = $subject_data['percentage'] . '%';
                $row[] = $subject_data['attended'] . '/' . $subject_data['total'];
            }
            
            $row[] = $data['overall_percentage'] . '%';
            $row[] = $data['total_attended'] . '/' . $data['total_sessions'];
            $row[] = $status;
            
            fputcsv($output, $row);
        }
        
        // Add summary
        fputcsv($output, []);
        fputcsv($output, ['Summary Statistics']);
        fputcsv($output, ['Total Students', $total_students]);
        fputcsv($output, ['Average Attendance', $avg_percentage . '%']);
        fputcsv($output, ['Highest Percentage', $max_percentage . '%']);
        fputcsv($output, ['Lowest Percentage', $min_percentage . '%']);
        fputcsv($output, ['Date Range', $date_from . ' to ' . $date_to]);
        fputcsv($output, ['Excellent (90-100%)', $attendance_distribution['excellent']]);
        fputcsv($output, ['Good (75-89%)', $attendance_distribution['good']]);
        fputcsv($output, ['Average (50-74%)', $attendance_distribution['average']]);
        fputcsv($output, ['Poor (0-49%)', $attendance_distribution['poor']]);
        
        fclose($output);
        exit;
    }
    
    // Generate Excel
    elseif ($export_type == 'excel') {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="students_attendance_' . date('Y-m-d') . '.xls"');
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Students Attendance Report</title>
            <style>
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: center; font-size: 11px; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .summary { margin-top: 20px; }
            </style>
        </head>
        <body>';
        
        echo '<h2>Students Attendance Report - ALL Subjects</h2>';
        echo '<h3>Faculty: ' . htmlspecialchars($faculty['faculty_name']) . '</h3>';
        echo '<p>Generated on: ' . date('F j, Y h:i A') . ' | Date Range: ' . $date_from . ' to ' . $date_to . '</p>';
        
        echo '<table>';
        echo '<thead><tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Section</th>
                <th>Department</th>
                <th>ID Number</th>';
        
        foreach ($subject_sessions as $subject) {
            echo '<th>' . $subject['subject_code'] . ' %</th>';
            echo '<th>' . $subject['subject_code'] . ' Count</th>';
        }
        
        echo '<th>Overall %</th>
              <th>Overall Count</th>
              <th>Status</th>
            </tr></thead><tbody>';
        
        $counter = 1;
        foreach ($export_students as $student_id => $data) {
            $student_info = $data['info'];
            
            if ($data['overall_percentage'] >= 90) $status = 'Excellent';
            elseif ($data['overall_percentage'] >= 75) $status = 'Good';
            elseif ($data['overall_percentage'] >= 50) $status = 'Average';
            else $status = 'Poor';
            
            echo '<tr>
                    <td>' . $counter++ . '</td>
                    <td>' . $student_info['student_id'] . '</td>
                    <td>' . $student_info['student_name'] . '</td>
                    <td>' . $student_info['section'] . '</td>
                    <td>' . $student_info['student_department'] . '</td>
                    <td>' . $student_info['id_number'] . '</td>';
            
            foreach ($subject_sessions as $subject_id => $subject) {
                $subject_data = isset($data['subjects'][$subject_id]) ? $data['subjects'][$subject_id] : ['percentage' => 0, 'attended' => 0, 'total' => $subject['total_sessions']];
                echo '<td>' . $subject_data['percentage'] . '%</td>';
                echo '<td>' . $subject_data['attended'] . '/' . $subject_data['total'] . '</td>';
            }
            
            echo '<td>' . $data['overall_percentage'] . '%</td>
                  <td>' . $data['total_attended'] . '/' . $data['total_sessions'] . '</td>
                  <td>' . $status . '</td>
                </tr>';
        }
        
        echo '</tbody></table>';
        
        // Summary
        echo '<div class="summary">
                <h3>Summary Statistics</h3>
                <table>
                    <tr><td>Total Students</td><td>' . $total_students . '</td></tr>
                    <tr><td>Average Attendance</td><td>' . $avg_percentage . '%</td></tr>
                    <tr><td>Highest Percentage</td><td>' . $max_percentage . '%</td></tr>
                    <tr><td>Lowest Percentage</td><td>' . $min_percentage . '%</td></tr>
                </table>
              </div>';
        
        echo '</body></html>';
        exit;
    }
    
    // Generate Print
    elseif ($export_type == 'print') {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Students Attendance Report - Print</title>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial; margin: 15px; }
                @page { size: landscape; margin: 0.5cm; }
                table { width: 100%; border-collapse: collapse; font-size: 9px; }
                th, td { border: 1px solid #ddd; padding: 3px; text-align: left; }
                th { background-color: #f2f2f2; }
                .header { text-align: center; margin-bottom: 15px; }
                @media print {
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Students Attendance Report - ALL Subjects</h2>
                <h4>Faculty: ' . htmlspecialchars($faculty['faculty_name']) . '</h4>
                <p>Generated: ' . date('F j, Y') . ' | Students: ' . $total_students . '</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Section</th>';
        
        foreach ($subject_sessions as $subject) {
            echo '<th>' . $subject['subject_code'] . ' %</th>';
        }
        
        echo '<th>Overall %</th>
              <th>Status</th>
            </tr>
        </thead>
        <tbody>';
        
        $counter = 1;
        foreach ($export_students as $student_id => $data) {
            $student_info = $data['info'];
            
            if ($data['overall_percentage'] >= 90) $status = 'Excellent';
            elseif ($data['overall_percentage'] >= 75) $status = 'Good';
            elseif ($data['overall_percentage'] >= 50) $status = 'Average';
            else $status = 'Poor';
            
            echo '<tr>
                    <td>' . $counter++ . '</td>
                    <td>' . $student_info['student_id'] . '</td>
                    <td>' . $student_info['student_name'] . '</td>
                    <td>' . $student_info['section'] . '</td>';
            
            foreach ($subject_sessions as $subject_id => $subject) {
                $subject_data = isset($data['subjects'][$subject_id]) ? $data['subjects'][$subject_id] : ['percentage' => 0];
                echo '<td>' . $subject_data['percentage'] . '%</td>';
            }
            
            echo '<td>' . $data['overall_percentage'] . '%</td>
                  <td>' . $status . '</td>
                </tr>';
        }
        
        echo '</tbody></table>
            
            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()">Print Report</button>
                <button onclick="window.close()">Close</button>
            </div>
            
            <script>window.onload = function() { window.print(); }</script>
        </body>
        </html>';
        exit;
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-download text-primary"></i> Bulk Download - Complete Attendance Data
        </h1>
        <a href="360_students_attendance.php?<?php echo http_build_query($_GET); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to View
        </a>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
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
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Avg Attendance
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $avg_percentage; ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Subjects
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($subject_sessions); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Date Range
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Download Options -->
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-success text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-file-excel"></i> Excel Download</h6>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-file-excel fa-5x text-success mb-4"></i>
                    <h5>Download Excel (.xls)</h5>
                    <p>Includes ALL <?php echo count($subject_sessions); ?> subjects + overall percentage</p>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" 
                       class="btn btn-success btn-lg btn-block mt-3">
                        <i class="fas fa-download"></i> Download Excel
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-info text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-file-csv"></i> CSV Download</h6>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-file-csv fa-5x text-info mb-4"></i>
                    <h5>Download CSV (.csv)</h5>
                    <p>Complete data with <?php echo $total_students; ?> students</p>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                       class="btn btn-info btn-lg btn-block mt-3">
                        <i class="fas fa-download"></i> Download CSV
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-warning text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-print"></i> Print Report</h6>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-print fa-5x text-warning mb-4"></i>
                    <h5>Print Report</h5>
                    <p>Printable format with all data</p>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'print'])); ?>" 
                       class="btn btn-warning btn-lg btn-block mt-3" target="_blank">
                        <i class="fas fa-print"></i> Print Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Preview -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-eye"></i> Data Preview (First 3 Students)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="bg-light">
                        <tr>
                            <th>Student</th>
                            <th>Section</th>
                            <?php 
                            // Show first 3 subjects + overall
                            $subject_count = 0;
                            foreach ($subject_sessions as $subject): 
                                if ($subject_count++ < 3):
                            ?>
                            <th><?php echo $subject['subject_code']; ?> %</th>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                            <th>Overall %</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $preview_count = 0;
                        foreach ($filtered_students as $student_id => $data): 
                            if ($preview_count++ >= 3) break;
                            $student_info = $data['info'];
                            
                            if ($data['overall_percentage'] >= 90) {
                                $status = 'Excellent'; $status_class = 'success';
                            } elseif ($data['overall_percentage'] >= 75) {
                                $status = 'Good'; $status_class = 'info';
                            } elseif ($data['overall_percentage'] >= 50) {
                                $status = 'Average'; $status_class = 'warning';
                            } else {
                                $status = 'Poor'; $status_class = 'danger';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student_info['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($student_info['section']); ?></td>
                            <?php 
                            $subject_count = 0;
                            foreach ($subject_sessions as $subject_id => $subject): 
                                if ($subject_count++ < 3):
                                    $subject_data = isset($data['subjects'][$subject_id]) ? $data['subjects'][$subject_id] : ['percentage' => 0];
                            ?>
                            <td><?php echo $subject_data['percentage']; ?>%</td>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                            <td>
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo $data['overall_percentage']; ?>%
                                </span>
                            </td>
                            <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="mt-2">
                <small class="text-muted">
                    Showing 3 of <?php echo $total_students; ?> students. 
                    Full download includes ALL <?php echo count($subject_sessions); ?> subjects.
                </small>
            </p>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<?php
if (ob_get_length()) {
    ob_end_flush();
}
?>
