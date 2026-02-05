<?php
// bulk_download.php
ob_start(); 
require_once 'config.php';
session_start(); // Add session start

// Security check - faculty only
if (!isset($_SESSION['faculty_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];
$page_title = "Bulk Download Students Attendance";
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

// Store subjects in array for later use and reset pointer
$subjects_array = [];
$subjects_for_display = []; // Store for display use
while ($subject = mysqli_fetch_assoc($subjects_result)) {
    $subjects_array[$subject['subject_id']] = $subject;
    $subjects_for_display[] = $subject; // Store for display
}

// Get all sections
$sections_query = "SELECT DISTINCT section FROM students WHERE section != '' ORDER BY section";
$sections_result = mysqli_query($conn, $sections_query);

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

// Get all students with their basic info using prepared statement
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

// Get total sessions for each subject using prepared statement
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

// Prepare data structure for student attendance
$student_attendance_data = [];
$subject_ids = [];

// Get all subject IDs for this faculty using prepared statement
$subject_ids_query = "SELECT DISTINCT subject_id FROM sessions WHERE faculty_id = ?";
$subject_ids_params = [$faculty_id];
$subject_ids_param_types = "s";

if ($subject_filter > 0) {
    $subject_ids_query .= " AND subject_id = ?";
    $subject_ids_params[] = $subject_filter;
    $subject_ids_param_types .= "i";
}

$subject_ids_stmt = mysqli_prepare($conn, $subject_ids_query);
mysqli_stmt_bind_param($subject_ids_stmt, $subject_ids_param_types, ...$subject_ids_params);
mysqli_stmt_execute($subject_ids_stmt);
$subject_ids_result = mysqli_stmt_get_result($subject_ids_stmt);
while ($subject_row = mysqli_fetch_assoc($subject_ids_result)) {
    $subject_ids[] = $subject_row['subject_id'];
}
mysqli_stmt_close($subject_ids_stmt);

// Now get attendance data for each student using prepared statements
while ($student = mysqli_fetch_assoc($students_result)) {
    $student_id = $student['student_id'];
    $student_attendance_data[$student_id] = [
        'info' => $student,
        'subjects' => [],
        'total_attended' => 0,
        'total_sessions' => 0,
        'overall_percentage' => 0
    ];
    
    // For each subject, get attendance count using prepared statement
    foreach ($subject_ids as $subject_id) {
        $attendance_count_query = "SELECT COUNT(*) as attended_sessions
                                  FROM attendance_records ar
                                  JOIN sessions ses ON ar.session_id = ses.session_id
                                  WHERE ar.student_id = ?
                                  AND ses.subject_id = ?
                                  AND ses.faculty_id = ?";
        
        $attendance_params = [$student_id, $subject_id, $faculty_id];
        
        if (!empty($date_from) && !empty($date_to)) {
            $attendance_count_query .= " AND DATE(ar.marked_at) BETWEEN ? AND ?";
            $attendance_params[] = $date_from;
            $attendance_params[] = $date_to;
        }
        
        $attendance_stmt = mysqli_prepare($conn, $attendance_count_query);
        $attendance_param_types = "sis";
        if (!empty($date_from) && !empty($date_to)) {
            $attendance_param_types .= "ss";
        }
        mysqli_stmt_bind_param($attendance_stmt, $attendance_param_types, ...$attendance_params);
        mysqli_stmt_execute($attendance_stmt);
        $attendance_count_result = mysqli_stmt_get_result($attendance_stmt);
        $attendance_count = mysqli_fetch_assoc($attendance_count_result);
        mysqli_stmt_close($attendance_stmt);
        
        $total_sessions = isset($subject_sessions[$subject_id]['total_sessions']) ? $subject_sessions[$subject_id]['total_sessions'] : 0;
        $attended = $attendance_count['attended_sessions'];
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

// Calculate summary statistics
$total_students = count($filtered_students);
$avg_percentage = 0;
$min_percentage = 100;
$max_percentage = 0;
$attendance_distribution = [
    'excellent' => 0,
    'good' => 0,
    'average' => 0,
    'poor' => 0,
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

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $export_students = $filtered_students; // Always export all filtered students
    
    // Validate export type
    if (!in_array($export_type, ['csv', 'excel', 'print'])) {
        http_response_code(400);
        die("Invalid export type");
    }
    
    // Generate CSV data
    if ($export_type == 'csv') {
        // Clean any previous output
        if (ob_get_length()) {
            ob_clean();
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=360_students_attendance_' . date('Y-m-d') . '.csv');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Headers
        $headers = ['#', 'Student ID', 'Student Name', 'Section', 'Department', 'ID Number'];
        
        // Add subject headers
        foreach ($subject_sessions as $subject_id => $subject) {
            $headers[] = htmlspecialchars($subject['subject_code']) . ' %';
            $headers[] = htmlspecialchars($subject['subject_code']) . ' (Attended/Total)';
        }
        
        $headers[] = 'Overall %';
        $headers[] = 'Overall (Attended/Total)';
        $headers[] = 'Attendance Status';
        
        fputcsv($output, $headers);
        
        // Data rows
        $counter = 1;
        foreach ($export_students as $student_id => $data) {
            $student_info = $data['info'];
            $overall_percentage = $data['overall_percentage'];
            
            // Determine status
            if ($overall_percentage >= 90) {
                $status = 'Excellent';
            } elseif ($overall_percentage >= 75) {
                $status = 'Good';
            } elseif ($overall_percentage >= 50) {
                $status = 'Average';
            } else {
                $status = 'Poor';
            }
            
            $row = [
                $counter++,
                htmlspecialchars($student_info['student_id']),
                htmlspecialchars($student_info['student_name']),
                htmlspecialchars($student_info['section']),
                htmlspecialchars($student_info['student_department']),
                htmlspecialchars($student_info['id_number'])
            ];
            
            // Add subject data
            foreach ($subject_sessions as $subject_id => $subject) {
                $subject_data = isset($data['subjects'][$subject_id]) ? $data['subjects'][$subject_id] : ['percentage' => 0, 'attended' => 0, 'total' => $subject['total_sessions']];
                $row[] = $subject_data['percentage'] . '%';
                $row[] = $subject_data['attended'] . '/' . $subject_data['total'];
            }
            
            $row[] = $overall_percentage . '%';
            $row[] = $data['total_attended'] . '/' . $data['total_sessions'];
            $row[] = $status;
            
            fputcsv($output, $row);
        }
        
        // Add summary row
        fputcsv($output, []); // Empty row
        fputcsv($output, ['Summary Statistics']);
        fputcsv($output, ['Total Students', $total_students]);
        fputcsv($output, ['Average Attendance', $avg_percentage . '%']);
        fputcsv($output, ['Highest Percentage', $max_percentage . '%']);
        fputcsv($output, ['Lowest Percentage', $min_percentage . '%']);
        fputcsv($output, ['Date Range', $date_from . ' to ' . $date_to]);
        
        fclose($output);
        exit;
    }
    
    // Generate Excel (HTML table format that Excel can open)
    elseif ($export_type == 'excel') {
        // Clean any previous output
        if (ob_get_length()) {
            ob_clean();
        }
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="360_students_attendance_' . date('Y-m-d') . '.xls"');
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>360° Students Attendance Report</title>
            <style>
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .summary { margin-top: 30px; }
                .summary table { width: auto; }
            </style>
        </head>
        <body>';
        
        echo '<h1>360° Students Attendance Report</h1>';
        echo '<h3>Faculty: ' . htmlspecialchars($faculty['faculty_name']) . '</h3>';
        echo '<p>Generated on: ' . date('F j, Y h:i A') . '</p>';
        echo '<p>Date Range: ' . htmlspecialchars($date_from) . ' to ' . htmlspecialchars($date_to) . '</p>';
        echo '<p>Filters Applied: ';
        if ($subject_filter > 0 && isset($subject_sessions[$subject_filter])) {
            echo 'Subject: ' . htmlspecialchars($subject_sessions[$subject_filter]['subject_name']) . ' | ';
        } elseif ($subject_filter > 0) {
            echo 'Subject: Selected Subject | ';
        }
        if (!empty($section_filter)) {
            echo 'Section: ' . htmlspecialchars($section_filter) . ' | ';
        }
        if (!empty($search_query)) {
            echo 'Search: ' . htmlspecialchars($search_query) . ' | ';
        }
        echo 'Attendance Range: ' . $min_attendance . '% to ' . $max_attendance . '%';
        echo '</p>';
        
        echo '<table>';
        echo '<thead><tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Section</th>
                <th>Department</th>
                <th>ID Number</th>';
        
        foreach ($subject_sessions as $subject_id => $subject) {
            echo '<th>' . htmlspecialchars($subject['subject_code']) . ' %</th>';
            echo '<th>' . htmlspecialchars($subject['subject_code']) . ' Count</th>';
        }
        
        echo '<th>Overall %</th>
              <th>Overall Count</th>
              <th>Status</th>
            </tr></thead><tbody>';
        
        $counter = 1;
        foreach ($export_students as $student_id => $data) {
            $student_info = $data['info'];
            $overall_percentage = $data['overall_percentage'];
            
            // Determine status
            if ($overall_percentage >= 90) {
                $status = 'Excellent';
            } elseif ($overall_percentage >= 75) {
                $status = 'Good';
            } elseif ($overall_percentage >= 50) {
                $status = 'Average';
            } else {
                $status = 'Poor';
            }
            
            echo '<tr>
                    <td>' . $counter++ . '</td>
                    <td>' . htmlspecialchars($student_info['student_id']) . '</td>
                    <td>' . htmlspecialchars($student_info['student_name']) . '</td>
                    <td>' . htmlspecialchars($student_info['section']) . '</td>
                    <td>' . htmlspecialchars($student_info['student_department']) . '</td>
                    <td>' . htmlspecialchars($student_info['id_number']) . '</td>';
            
            foreach ($subject_sessions as $subject_id => $subject) {
                $subject_data = isset($data['subjects'][$subject_id]) ? $data['subjects'][$subject_id] : ['percentage' => 0, 'attended' => 0, 'total' => $subject['total_sessions']];
                echo '<td>' . $subject_data['percentage'] . '%</td>';
                echo '<td>' . $subject_data['attended'] . '/' . $subject_data['total'] . '</td>';
            }
            
            echo '<td>' . $overall_percentage . '%</td>
                  <td>' . $data['total_attended'] . '/' . $data['total_sessions'] . '</td>
                  <td>' . $status . '</td>
                </tr>';
        }
        
        echo '</tbody></table>';
        
        // Add summary
        echo '<div class="summary">
                <h3>Summary Statistics</h3>
                <table>
                    <tr><td>Total Students</td><td>' . $total_students . '</td></tr>
                    <tr><td>Average Attendance</td><td>' . $avg_percentage . '%</td></tr>
                    <tr><td>Highest Percentage</td><td>' . $max_percentage . '%</td></tr>
                    <tr><td>Lowest Percentage</td><td>' . $min_percentage . '%</td></tr>
                    <tr><td>Excellent (90-100%)</td><td>' . $attendance_distribution['excellent'] . '</td></tr>
                    <tr><td>Good (75-89%)</td><td>' . $attendance_distribution['good'] . '</td></tr>
                    <tr><td>Average (50-74%)</td><td>' . $attendance_distribution['average'] . '</td></tr>
                    <tr><td>Poor (0-49%)</td><td>' . $attendance_distribution['poor'] . '</td></tr>
                </table>
              </div>';
        
        echo '</body></html>';
        exit;
    }
    
    // Print function
    elseif ($export_type == 'print') {
        // Clean any previous output
        if (ob_get_length()) {
            ob_clean();
        }
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>360° Students Attendance Report - Print</title>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1, h2, h3 { color: #333; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 10px; }
                th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .header { text-align: center; margin-bottom: 20px; }
                .summary { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
                .badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; }
                .excellent { background-color: #28a745; color: white; }
                .good { background-color: #17a2b8; color: white; }
                .average { background-color: #ffc107; color: black; }
                .poor { background-color: #dc3545; color: white; }
                @media print {
                    @page { size: landscape; margin: 0.5cm; }
                    table { font-size: 8px; }
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>360° Students Attendance Report - All Students</h1>
                <h3>Faculty: ' . htmlspecialchars($faculty['faculty_name']) . '</h3>
                <p>Generated on: ' . date('F j, Y h:i A') . '</p>
                <div class="summary">
                    <p><strong>Total Students:</strong> ' . number_format($total_students) . ' | 
                    <strong>Average Attendance:</strong> ' . $avg_percentage . '% | 
                    <strong>Date Range:</strong> ' . htmlspecialchars($date_from) . ' to ' . htmlspecialchars($date_to) . '</p>
                    <p><strong>Filters:</strong> ';
        
        if ($subject_filter > 0 && isset($subject_sessions[$subject_filter])) {
            echo 'Subject: ' . htmlspecialchars($subject_sessions[$subject_filter]['subject_name']) . ' | ';
        } elseif ($subject_filter > 0) {
            echo 'Subject: Selected Subject | ';
        }
        if (!empty($section_filter)) {
            echo 'Section: ' . htmlspecialchars($section_filter) . ' | ';
        }
        if (!empty($search_query)) {
            echo 'Search: ' . htmlspecialchars($search_query) . ' | ';
        }
        echo 'Attendance Range: ' . $min_attendance . '% to ' . $max_attendance . '%';
        
        echo '</p>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Section</th>
                        <th>Department</th>
                        <th>ID Number</th>';
        
        foreach ($subject_sessions as $subject_id => $subject) {
            echo '<th>' . htmlspecialchars($subject['subject_code']) . ' %</th>';
        }
        
        echo '<th>Overall %</th>
              <th>Overall Count</th>
              <th>Status</th>
            </tr>
        </thead>
        <tbody>';
        
        $counter = 1;
        foreach ($export_students as $student_id => $data) {
            $student_info = $data['info'];
            $overall_percentage = $data['overall_percentage'];
            
            // Determine status and class
            if ($overall_percentage >= 90) {
                $status = 'Excellent';
                $status_class = 'excellent';
            } elseif ($overall_percentage >= 75) {
                $status = 'Good';
                $status_class = 'good';
            } elseif ($overall_percentage >= 50) {
                $status = 'Average';
                $status_class = 'average';
            } else {
                $status = 'Poor';
                $status_class = 'poor';
            }
            
            echo '<tr>
                    <td>' . $counter++ . '</td>
                    <td>' . htmlspecialchars($student_info['student_id']) . '</td>
                    <td>' . htmlspecialchars($student_info['student_name']) . '</td>
                    <td>' . htmlspecialchars($student_info['section']) . '</td>
                    <td>' . htmlspecialchars($student_info['student_department']) . '</td>
                    <td>' . htmlspecialchars($student_info['id_number']) . '</td>';
            
            foreach ($subject_sessions as $subject_id => $subject) {
                $subject_data = isset($data['subjects'][$subject_id]) ? $data['subjects'][$subject_id] : ['percentage' => 0];
                echo '<td>' . $subject_data['percentage'] . '%</td>';
            }
            
            echo '<td>' . $overall_percentage . '%</td>
                  <td>' . $data['total_attended'] . '/' . $data['total_sessions'] . '</td>
                  <td><span class="badge ' . $status_class . '">' . $status . '</span></td>
                </tr>';
        }
        
        echo '</tbody>
            </table>
            
            <div style="page-break-before: always; margin-top: 30px;">
                <h3>Summary Statistics</h3>
                <table>
                    <tr><td>Total Students</td><td>' . $total_students . '</td></tr>
                    <tr><td>Average Attendance</td><td>' . $avg_percentage . '%</td></tr>
                    <tr><td>Highest Percentage</td><td>' . $max_percentage . '%</td></tr>
                    <tr><td>Lowest Percentage</td><td>' . $min_percentage . '%</td></tr>
                    <tr><td>Excellent (90-100%)</td><td>' . $attendance_distribution['excellent'] . '</td></tr>
                    <tr><td>Good (75-89%)</td><td>' . $attendance_distribution['good'] . '</td></tr>
                    <tr><td>Average (50-74%)</td><td>' . $attendance_distribution['average'] . '</td></tr>
                    <tr><td>Poor (0-49%)</td><td>' . $attendance_distribution['poor'] . '</td></tr>
                </table>
            </div>
            
            <div style="margin-top: 50px; font-size: 10px; text-align: center;">
                <p>Report generated by: ' . htmlspecialchars($faculty['faculty_name']) . '</p>
            </div>
            
            <div class="no-print" style="margin-top: 30px; text-align: center;">
                <button onclick="window.print()" class="btn btn-primary">Print Report</button>
                <button onclick="window.close()" class="btn btn-secondary">Close Window</button>
            </div>
            
            <script>
                window.onload = function() {
                    // Auto-print only if the user hasn\'t already printed
                    if (!window.matchMedia || !window.matchMedia("print").matches) {
                        window.print();
                    }
                }
            </script>
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
            <i class="fas fa-download text-primary"></i> Bulk Download - Students Attendance
            <small class="text-muted ms-2">Faculty: <?php echo htmlspecialchars($faculty['faculty_name']); ?></small>
        </h1>
        <a href="360_students_attendance.php?<?php echo htmlspecialchars(http_build_query($_GET)); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to 360° View
        </a>
    </div>

    <!-- Statistics Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_students); ?>
                            </div>
                            <div class="text-xs font-weight-bold text-primary text-uppercase">
                                Total Students
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $avg_percentage; ?>%
                            </div>
                            <div class="text-xs font-weight-bold text-success text-uppercase">
                                Average Attendance
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($subject_sessions); ?>
                            </div>
                            <div class="text-xs font-weight-bold text-info text-uppercase">
                                Subjects
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?>
                            </div>
                            <div class="text-xs font-weight-bold text-warning text-uppercase">
                                Date Range
                            </div>
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
                <i class="fas fa-filter"></i> Current Filters
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Subject:</strong><br>
                    <?php 
                    if ($subject_filter > 0 && isset($subject_sessions[$subject_filter])) {
                        echo htmlspecialchars($subject_sessions[$subject_filter]['subject_code'] . ' - ' . $subject_sessions[$subject_filter]['subject_name']);
                    } else {
                        echo 'All Subjects';
                    }
                    ?>
                </div>
                <div class="col-md-2">
                    <strong>Section:</strong><br>
                    <?php echo !empty($section_filter) ? 'Section ' . htmlspecialchars($section_filter) : 'All Sections'; ?>
                </div>
                <div class="col-md-3">
                    <strong>Date Range:</strong><br>
                    <?php echo date('d/m/Y', strtotime($date_from)) . ' to ' . date('d/m/Y', strtotime($date_to)); ?>
                </div>
                <div class="col-md-2">
                    <strong>Attendance Range:</strong><br>
                    <?php echo $min_attendance; ?>% to <?php echo $max_attendance; ?>%
                </div>
                <div class="col-md-2">
                    <strong>Search:</strong><br>
                    <?php echo !empty($search_query) ? htmlspecialchars($search_query) : 'None'; ?>
                </div>
            </div>
            <div class="mt-3">
                <a href="360_students_attendance.php?<?php echo htmlspecialchars(http_build_query($_GET)); ?>" 
                   class="btn btn-sm btn-primary">
                    <i class="fas fa-edit"></i> Modify Filters
                </a>
            </div>
        </div>
    </div>

    <!-- Download Options -->
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-success text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-file-excel"></i> Excel Format
                    </h6>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-file-excel fa-5x text-success mb-4"></i>
                    <h4 class="text-success">Excel Download</h4>
                    <p>Download all <?php echo number_format($total_students); ?> students data in Microsoft Excel format (.xls)</p>
                    <div class="mt-4">
                        <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'excel']))); ?>" 
                           class="btn btn-success btn-lg btn-block">
                            <i class="fas fa-download"></i> Download Excel File
                        </a>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> Compatible with Microsoft Excel, Google Sheets, and LibreOffice Calc
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-file-csv"></i> CSV Format
                    </h6>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-file-csv fa-5x text-info mb-4"></i>
                    <h4 class="text-info">CSV Download</h4>
                    <p>Download all <?php echo number_format($total_students); ?> students data in CSV format (.csv)</p>
                    <div class="mt-4">
                        <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>" 
                           class="btn btn-info btn-lg btn-block">
                            <i class="fas fa-download"></i> Download CSV File
                        </a>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> Best for data analysis, database import, and statistical software
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-warning text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-print"></i> Print Report
                    </h6>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-print fa-5x text-warning mb-4"></i>
                    <h4 class="text-warning">Print Report</h4>
                    <p>Print all <?php echo number_format($total_students); ?> students data as a formatted report</p>
                    <div class="mt-4">
                        <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'print']))); ?>" 
                           class="btn btn-warning btn-lg btn-block" target="_blank">
                            <i class="fas fa-print"></i> Open Print View
                        </a>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> Opens in a new window optimized for printing with landscape layout
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-eye"></i> Data Preview (First 5 Students)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Section</th>
                            <th>Overall %</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $preview_counter = 1;
                        $preview_students = array_slice($filtered_students, 0, 5, true);
                        foreach ($preview_students as $student_id => $data): 
                            $student_info = $data['info'];
                            $overall_percentage = $data['overall_percentage'];
                            
                            // Determine status
                            if ($overall_percentage >= 90) {
                                $status = 'Excellent';
                                $status_class = 'success';
                            } elseif ($overall_percentage >= 75) {
                                $status = 'Good';
                                $status_class = 'info';
                            } elseif ($overall_percentage >= 50) {
                                $status = 'Average';
                                $status_class = 'warning';
                            } else {
                                $status = 'Poor';
                                $status_class = 'danger';
                            }
                        ?>
                        <tr>
                            <td><?php echo $preview_counter++; ?></td>
                            <td><?php echo htmlspecialchars($student_info['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($student_info['student_name']); ?></td>
                            <td>Section <?php echo htmlspecialchars($student_info['section']); ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?php echo $status_class; ?>" 
                                         style="width: <?php echo $overall_percentage; ?>%">
                                        <?php echo $overall_percentage; ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-center">
                <small class="text-muted">
                    Showing 5 of <?php echo number_format($total_students); ?> students. Full data will be included in the download.
                </small>
            </div>
        </div>
    </div>

    <!-- Instructions -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-info-circle"></i> Instructions
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="card border-left-primary h-100">
                        <div class="card-body">
                            <h6 class="font-weight-bold text-primary">
                                <i class="fas fa-step-forward me-2"></i>Step 1
                            </h6>
                            <p>Review the filters applied above. All data will be exported based on these filters.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-left-success h-100">
                        <div class="card-body">
                            <h6 class="font-weight-bold text-success">
                                <i class="fas fa-step-forward me-2"></i>Step 2
                            </h6>
                            <p>Choose your preferred download format (Excel, CSV, or Print).</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-left-info h-100">
                        <div class="card-body">
                            <h6 class="font-weight-bold text-info">
                                <i class="fas fa-step-forward me-2"></i>Step 3
                            </h6>
                            <p>Click the download button. The file will be generated and downloaded automatically.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="alert alert-info mt-4">
                <h6><i class="fas fa-lightbulb me-2"></i>Important Notes:</h6>
                <ul class="mb-0">
                    <li>All <?php echo number_format($total_students); ?> students matching your filters will be included</li>
                    <li>Each download includes summary statistics</li>
                    <li>Excel and CSV files include all subject-wise attendance percentages</li>
                    <li>Print version is optimized for A4 paper with landscape orientation</li>
                    <li>Large files may take a few moments to generate</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<?php
// Clean output buffering properly
if (ob_get_length()) {
    ob_end_flush();
}
// Don't put any text after the closing PHP tag
