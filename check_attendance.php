<?php
// check_attendance.php - View all attendance for a session

// Start output buffering
ob_start();

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Include database config
require_once 'config.php';

// Security check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'faculty') {
    ob_end_clean();
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'] ?? null;
if (!$faculty_id) {
    ob_end_clean();
    header('Location: login.php');
    exit;
}

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if ($session_id == 0) {
    header('Location: faculty_dashboard.php');
    exit;
}

// Get session details
$stmt = mysqli_prepare($conn, 
    "SELECT s.*, sub.subject_code, sub.subject_name 
     FROM sessions s 
     JOIN subjects sub ON s.subject_id = sub.subject_id 
     WHERE s.session_id = ? AND s.faculty_id = ?");
mysqli_stmt_bind_param($stmt, "is", $session_id, $faculty_id);
mysqli_stmt_execute($stmt);
$session_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($session_result) == 0) {
    header('Location: faculty_dashboard.php');
    exit;
}

$session_info = mysqli_fetch_assoc($session_result);

// Get all attendance records for this session
$attendance_query = mysqli_prepare($conn,
    "SELECT ar.student_id, s.student_name, s.section, ar.marked_at,
            TIME_FORMAT(ar.marked_at, '%h:%i %p') as formatted_time
     FROM attendance_records ar
     LEFT JOIN students s ON ar.student_id = s.student_id
     WHERE ar.session_id = ?
     ORDER BY 
        CAST(SUBSTRING_INDEX(ar.student_id, '-', -1) AS UNSIGNED),
        ar.student_id ASC");
mysqli_stmt_bind_param($attendance_query, "i", $session_id);
mysqli_stmt_execute($attendance_query);
$attendance_result = mysqli_stmt_get_result($attendance_query);

// Count total students in section
$total_students_query = mysqli_prepare($conn,
    "SELECT COUNT(*) as total FROM students WHERE section = ?");
mysqli_stmt_bind_param($total_students_query, "s", $session_info['section_targeted']);
mysqli_stmt_execute($total_students_query);
$total_result = mysqli_stmt_get_result($total_students_query);
$total_data = mysqli_fetch_assoc($total_result);
$total_students = $total_data['total'];

// Get present count
$present_count_query = mysqli_prepare($conn,
    "SELECT COUNT(DISTINCT student_id) as present FROM attendance_records WHERE session_id = ?");
mysqli_stmt_bind_param($present_count_query, "i", $session_id);
mysqli_stmt_execute($present_count_query);
$present_result = mysqli_stmt_get_result($present_count_query);
$present_data = mysqli_fetch_assoc($present_result);
$present_count = $present_data['present'];

$absent_count = max(0, $total_students - $present_count);
$attendance_percentage = $total_students > 0 ? round(($present_count / $total_students) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .badge-present {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .badge-absent {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stat-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-2 { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stat-3 { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
        .stat-4 { background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%); }
        
        .btn-custom {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <a href="faculty_scan.php?session_id=<?php echo $session_id; ?>" 
                           class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Scanner
                        </a>
                    </div>
                    <div class="text-end">
                        <h2 class="text-primary mb-0">
                            <i class="fas fa-list-check me-2"></i> Attendance Report
                        </h2>
                        <p class="text-muted mb-0">Session #<?php echo $session_id; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-chalkboard-teacher me-2"></i>
                            Session Information
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <h6><i class="fas fa-book me-2"></i> Subject</h6>
                                <p class="fw-bold"><?php echo htmlspecialchars($session_info['subject_code'] . ' - ' . $session_info['subject_name']); ?></p>
                            </div>
                            <div class="col-md-2 mb-3">
                                <h6><i class="fas fa-users me-2"></i> Section</h6>
                                <p class="fw-bold">Section <?php echo htmlspecialchars($session_info['section_targeted']); ?></p>
                            </div>
                            <div class="col-md-2 mb-3">
                                <h6><i class="fas fa-chalkboard me-2"></i> Type</h6>
                                <p class="fw-bold"><?php echo htmlspecialchars($session_info['class_type']); ?></p>
                            </div>
                            <div class="col-md-2 mb-3">
                                <h6><i class="fas fa-calendar me-2"></i> Date</h6>
                                <p class="fw-bold">
                                    <?php echo date('d M Y', strtotime($session_info['created_at'] ?? date('Y-m-d'))); ?>
                                </p>
                            </div>
                            <div class="col-md-2 mb-3">
                                <h6><i class="fas fa-clock me-2"></i> Time</h6>
                                <p class="fw-bold">
                                    <?php 
                                    if (!empty($session_info['start_time'])) {
                                        echo date('h:i A', strtotime($session_info['start_time']));
                                    } elseif (!empty($session_info['created_at'])) {
                                        echo date('h:i A', strtotime($session_info['created_at']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card stat-1">
                    <div class="stat-number" style="font-size: 2rem; font-weight: bold;"><?php echo $attendance_percentage; ?>%</div>
                    <h5 class="mb-0">Attendance Rate</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-2">
                    <div class="stat-number" style="font-size: 2rem; font-weight: bold;"><?php echo $present_count; ?></div>
                    <h5 class="mb-0">Present</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-3">
                    <div class="stat-number" style="font-size: 2rem; font-weight: bold;"><?php echo $absent_count; ?></div>
                    <h5 class="mb-0">Absent</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-4">
                    <div class="stat-number" style="font-size: 2rem; font-weight: bold;"><?php echo $total_students; ?></div>
                    <h5 class="mb-0">Total Students</h5>
                </div>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            Student Attendance List
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 80px;">S.No</th>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Section</th>
                                        <th>Status</th>
                                        <th>Time Marked</th>
                                        <th class="no-print">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $serial_number = 1;
                                    
                                    // Get all students in the section
                                    $all_students_query = mysqli_prepare($conn,
                                        "SELECT student_id, student_name, section FROM students 
                                         WHERE section = ? 
                                         ORDER BY 
                                            CAST(SUBSTRING_INDEX(student_id, '-', -1) AS UNSIGNED),
                                            student_id ASC");
                                    mysqli_stmt_bind_param($all_students_query, "s", $session_info['section_targeted']);
                                    mysqli_stmt_execute($all_students_query);
                                    $all_students_result = mysqli_stmt_get_result($all_students_query);
                                    
                                    // Create array of present students
                                    $present_students = [];
                                    if ($attendance_result && mysqli_num_rows($attendance_result) > 0) {
                                        mysqli_data_seek($attendance_result, 0);
                                        while ($present = mysqli_fetch_assoc($attendance_result)) {
                                            $present_students[$present['student_id']] = $present;
                                        }
                                    }
                                    
                                    if (mysqli_num_rows($all_students_result) > 0):
                                        mysqli_data_seek($all_students_result, 0);
                                        while ($student = mysqli_fetch_assoc($all_students_result)):
                                            $student_id = $student['student_id'];
                                            $student_name = htmlspecialchars($student['student_name']);
                                            $is_present = isset($present_students[$student_id]);
                                    ?>
                                    <tr>
                                        <td class="text-center fw-bold"><?php echo $serial_number++; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($student_id); ?></td>
                                        <td><?php echo $student_name; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($student['section']); ?></td>
                                        <td>
                                            <?php if ($is_present): ?>
                                            <span class="badge-present">
                                                <i class="fas fa-check-circle me-1"></i> Present
                                            </span>
                                            <?php else: ?>
                                            <span class="badge-absent">
                                                <i class="fas fa-times-circle me-1"></i> Absent
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_present): ?>
                                            <span class="text-success fw-bold">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $present_students[$student_id]['formatted_time']; ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted fst-italic">--:--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="no-print">
                                            <?php if (!$is_present): ?>
                                            <form method="POST" action="faculty_scan.php" class="d-inline">
                                                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                                <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                                                <button type="submit" name="mark_attendance" 
                                                        class="btn btn-sm btn-outline-success"
                                                        onclick="return confirm('Mark <?php echo $student_name; ?> as present?')">
                                                    <i class="fas fa-user-check me-1"></i> Mark Present
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <span class="text-success">
                                                <i class="fas fa-check-circle me-1"></i> Marked
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No students found in this section</h5>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Summary -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i> Attendance Summary</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-1">Total Students:</p>
                                            <p class="mb-1">Present:</p>
                                            <p class="mb-0">Absent:</p>
                                        </div>
                                        <div class="col-6 text-end">
                                            <p class="mb-1 fw-bold"><?php echo $total_students; ?></p>
                                            <p class="mb-1 fw-bold text-success"><?php echo $present_count; ?></p>
                                            <p class="mb-0 fw-bold text-danger"><?php echo $absent_count; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-clock me-2"></i> Report Information</h6>
                                    <p class="mb-1"><strong>Generated:</strong> <?php echo date('d F Y h:i A'); ?></p>
                                    <p class="mb-0"><strong>Session Status:</strong> 
                                        <span class="badge bg-<?php echo $session_info['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $session_info['is_active'] ? 'Active' : 'Ended'; ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Auto-refresh every 60 seconds to update attendance
    setTimeout(function() {
        window.location.reload();
    }, 60000); // 60 seconds

    // Function to mark attendance via AJAX
    function markAttendance(studentId, studentName) {
        if (confirm(`Mark ${studentName} as present?`)) {
            const formData = new FormData();
            formData.append('student_id', studentId);
            formData.append('session_id', <?php echo $session_id; ?>);
            formData.append('mark_attendance', '1');
            
            fetch('faculty_scan.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                } else {
                    return response.text();
                }
            })
            .then(() => {
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to mark attendance. Please try again.');
            });
        }
    }
    </script>
</body>
</html>
<?php
ob_end_flush();
?>
