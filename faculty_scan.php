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
    "SELECT ar.student_id, s.student_name, ar.marked_at,
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
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card-header {
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem 1.5rem;
        }
        .table th {
            border-top: none;
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .table td {
            vertical-align: middle;
        }
        .attendance-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .percentage-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            margin: 0 auto;
        }
        .present-row {
            background-color: #d4edda !important;
        }
        .sno-column {
            width: 60px;
            text-align: center;
        }
        .action-buttons {
            position: sticky;
            top: 20px;
            z-index: 1000;
        }
        .export-btn {
            transition: all 0.3s;
        }
        .export-btn:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <a href="faculty_scan.php?session_id=<?php echo $session_id; ?>" 
                           class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Scanner
                        </a>
                        <a href="faculty_dashboard.php" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-home me-2"></i> Dashboard
                        </a>
                    </div>
                    <h2 class="mb-0 text-primary">
                        <i class="fas fa-list-check me-2"></i> Attendance Report
                    </h2>
                </div>
            </div>
        </div>

        <!-- Session Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-chalkboard-teacher me-2"></i>
                            Session Information
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <h6><i class="fas fa-book me-2"></i> Subject</h6>
                                <p class="fw-bold fs-5"><?php echo htmlspecialchars($session_info['subject_code'] . ' - ' . $session_info['subject_name']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <h6><i class="fas fa-users me-2"></i> Section</h6>
                                <p class="fw-bold fs-5">Section <?php echo htmlspecialchars($session_info['section_targeted']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <h6><i class="fas fa-chalkboard me-2"></i> Type</h6>
                                <p class="fw-bold fs-5"><?php echo htmlspecialchars($session_info['class_type']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <h6><i class="fas fa-calendar me-2"></i> Date</h6>
                                <p class="fw-bold fs-5">
                                    <?php echo date('d M Y', strtotime($session_info['created_at'] ?? date('Y-m-d'))); ?>
                                </p>
                            </div>
                            <div class="col-md-3">
                                <h6><i class="fas fa-clock me-2"></i> Session ID</h6>
                                <p class="fw-bold fs-5 text-primary">#<?php echo $session_id; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card border-success">
                    <div class="card-body text-center">
                        <div class="percentage-circle bg-success text-white mb-3">
                            <?php echo $attendance_percentage; ?>%
                        </div>
                        <h5 class="card-title">Attendance</h5>
                        <p class="card-text text-muted">Percentage</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-info">
                    <div class="card-body text-center">
                        <h1 class="display-4 text-info mb-3"><?php echo $present_count; ?></h1>
                        <h5 class="card-title">Present</h5>
                        <p class="card-text text-muted">Students marked</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-warning">
                    <div class="card-body text-center">
                        <h1 class="display-4 text-warning mb-3"><?php echo $absent_count; ?></h1>
                        <h5 class="card-title">Absent</h5>
                        <p class="card-text text-muted">Not marked</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-secondary">
                    <div class="card-body text-center">
                        <h1 class="display-4 text-secondary mb-3"><?php echo $total_students; ?></h1>
                        <h5 class="card-title">Total</h5>
                        <p class="card-text text-muted">Students in section</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Button -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-end gap-2 action-buttons">
                    <button onclick="window.print()" class="btn btn-outline-primary export-btn">
                        <i class="fas fa-print me-2"></i> Print Report
                    </button>
                    <a href="export_attendance.php?session_id=<?php echo $session_id; ?>" 
                       class="btn btn-success export-btn">
                        <i class="fas fa-file-excel me-2"></i> Export to Excel
                    </a>
                </div>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            Attendance List (Ordered by Student ID)
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th class="sno-column">S.No</th>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Status</th>
                                        <th>Time Marked</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $serial_number = 1;
                                    
                                    // Get all students in the section
                                    $all_students_query = mysqli_prepare($conn,
                                        "SELECT student_id, student_name FROM students 
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
                                            $is_present = isset($present_students[$student_id]);
                                    ?>
                                    <tr class="<?php echo $is_present ? 'present-row' : ''; ?>">
                                        <td class="text-center fw-bold"><?php echo $serial_number++; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($student_id); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td>
                                            <?php if ($is_present): ?>
                                            <span class="badge bg-success attendance-badge">
                                                <i class="fas fa-check-circle me-1"></i> Present
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-danger attendance-badge">
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
                                            <span class="text-muted">--:--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$is_present): ?>
                                            <form method="POST" action="faculty_scan.php" class="d-inline">
                                                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                                <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                                                <button type="submit" name="mark_attendance" 
                                                        class="btn btn-sm btn-outline-success"
                                                        onclick="return confirm('Mark <?php echo htmlspecialchars($student[\'student_name\']); ?> as present?')">
                                                    <i class="fas fa-user-check me-1"></i> Mark Present
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>
                                                <i class="fas fa-check me-1"></i> Already Marked
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
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
                                    <h6><i class="fas fa-info-circle me-2"></i> Summary</h6>
                                    <p class="mb-1">Total students in Section <?php echo htmlspecialchars($session_info['section_targeted']); ?>: <strong><?php echo $total_students; ?></strong></p>
                                    <p class="mb-1">Present: <span class="text-success fw-bold"><?php echo $present_count; ?></span></p>
                                    <p class="mb-0">Absent: <span class="text-danger fw-bold"><?php echo $absent_count; ?></span></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-clock me-2"></i> Report Generated</h6>
                                    <p class="mb-0"><?php echo date('d F Y h:i A'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-refresh every 30 seconds to update attendance
    setTimeout(function() {
        window.location.reload();
    }, 30000); // 30 seconds

    // Search functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Add search box dynamically
        const searchBox = `
            <div class="input-group mb-3">
                <span class="input-group-text">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="searchBox" class="form-control" placeholder="Search by Student ID or Name...">
                <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        const table = document.querySelector('table');
        if (table) {
            table.parentNode.insertAdjacentHTML('beforebegin', searchBox);
            
            const searchInput = document.getElementById('searchBox');
            const clearButton = document.getElementById('clearSearch');
            
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
            
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('keyup'));
                searchInput.focus();
            });
        }
    });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>
