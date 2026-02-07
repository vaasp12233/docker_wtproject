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
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --success-color: #06d6a0;
            --danger-color: #ef476f;
            --warning-color: #ffd166;
            --info-color: #118ab2;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .main-container {
            flex: 1;
        }
        
        /* Header Styles */
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .nav-link {
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            color: var(--primary-color) !important;
            transform: translateY(-2px);
        }
        
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        /* Footer Styles */
        .footer {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem 0;
            margin-top: auto;
        }
        
        .footer-links a {
            color: #ecf0f1;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: var(--primary-color);
        }
        
        .social-icons a {
            color: white;
            font-size: 1.2rem;
            margin: 0 10px;
            transition: transform 0.3s;
        }
        
        .social-icons a:hover {
            transform: translateY(-3px);
            color: var(--primary-color);
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }
        
        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table th {
            border-top: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            padding: 1rem;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }
        
        .table tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        
        .table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .table tr:nth-child(even):hover {
            background-color: rgba(102, 126, 234, 0.08);
        }
        
        /* Badge Styles */
        .badge-present {
            background: linear-gradient(45deg, #06d6a0, #06b990);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
        }
        
        .badge-absent {
            background: linear-gradient(45deg, #ef476f, #e0345c);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
        }
        
        /* Statistics Cards */
        .stat-card {
            border: none;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            color: white;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .stat-card-1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card-2 {
            background: linear-gradient(135deg, #06d6a0 0%, #06b990 100%);
        }
        
        .stat-card-3 {
            background: linear-gradient(135deg, #ffd166 0%, #ffc043 100%);
        }
        
        .stat-card-4 {
            background: linear-gradient(135deg, #118ab2 0%, #0d7799 100%);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        /* Button Styles */
        .btn-custom {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-outline-custom {
            border: 2px solid #667eea;
            color: #667eea;
            background: transparent;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-outline-custom:hover {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Search Box */
        .search-box {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        
        /* Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .table th {
                background: #f8f9fa !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
            }
            
            .badge-present, .badge-absent {
                background: #f8f9fa !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
            }
        }
        
        /* Scrollbar Styling */
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 10px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(45deg, #5a6fd8, #6a4190);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .stat-number {
                font-size: 2rem;
            }
            
            .table th, .table td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header/Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="faculty_dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i> Smart Attendance
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_dashboard.php">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="faculty_scan.php">
                            <i class="fas fa-qrcode me-1"></i> Scanner
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_sessions.php">
                            <i class="fas fa-history me-1"></i> Sessions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_profile.php">
                            <i class="fas fa-user me-1"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container py-4">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="faculty_scan.php?session_id=<?php echo $session_id; ?>" 
                               class="btn btn-outline-custom">
                                <i class="fas fa-arrow-left me-2"></i> Back to Scanner
                            </a>
                        </div>
                        <div class="text-end">
                            <h1 class="text-primary mb-0">
                                <i class="fas fa-list-check me-2"></i> Attendance Report
                            </h1>
                            <p class="text-muted mb-0">Session #<?php echo $session_id; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Session Info -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <h4 class="mb-0">
                                <i class="fas fa-chalkboard-teacher me-2"></i>
                                Session Information
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <h6><i class="fas fa-book me-2 text-primary"></i> Subject</h6>
                                    <p class="fw-bold fs-5"><?php echo htmlspecialchars($session_info['subject_code'] . ' - ' . $session_info['subject_name']); ?></p>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <h6><i class="fas fa-users me-2 text-success"></i> Section</h6>
                                    <p class="fw-bold fs-5">Section <?php echo htmlspecialchars($session_info['section_targeted']); ?></p>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <h6><i class="fas fa-chalkboard me-2 text-warning"></i> Type</h6>
                                    <p class="fw-bold fs-5"><?php echo htmlspecialchars($session_info['class_type']); ?></p>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <h6><i class="fas fa-calendar me-2 text-info"></i> Date</h6>
                                    <p class="fw-bold fs-5">
                                        <?php echo date('d M Y', strtotime($session_info['created_at'] ?? date('Y-m-d'))); ?>
                                    </p>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <h6><i class="fas fa-clock me-2 text-danger"></i> Time</h6>
                                    <p class="fw-bold fs-5">
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
                <div class="col-md-3 mb-3">
                    <div class="stat-card stat-card-1">
                        <div class="stat-number"><?php echo $attendance_percentage; ?>%</div>
                        <h5 class="mb-0">Attendance Rate</h5>
                        <p class="mb-0 opacity-75">Percentage of present students</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card stat-card-2">
                        <div class="stat-number"><?php echo $present_count; ?></div>
                        <h5 class="mb-0">Present</h5>
                        <p class="mb-0 opacity-75">Students marked present</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card stat-card-3">
                        <div class="stat-number"><?php echo $absent_count; ?></div>
                        <h5 class="mb-0">Absent</h5>
                        <p class="mb-0 opacity-75">Students not marked</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card stat-card-4">
                        <div class="stat-number"><?php echo $total_students; ?></div>
                        <h5 class="mb-0">Total</h5>
                        <p class="mb-0 opacity-75">Students in section</p>
                    </div>
                </div>
            </div>

            <!-- Search Box -->
            <div class="search-box no-print">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-primary text-white">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" id="searchBox" class="form-control" 
                                   placeholder="Search by Student ID or Name...">
                            <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <button onclick="window.print()" class="btn btn-outline-custom me-2">
                            <i class="fas fa-print me-2"></i> Print
                        </button>
                        <a href="export_attendance.php?session_id=<?php echo $session_id; ?>" 
                           class="btn btn-custom">
                            <i class="fas fa-file-excel me-2"></i> Export Excel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="row">
                <div class="col-12">
                    <div class="table-container">
                        <h4 class="mb-3">
                            <i class="fas fa-table me-2 text-primary"></i>
                            Student Attendance List (Ordered by Student ID)
                        </h4>
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 80px;">S.No</th>
                                        <th style="min-width: 120px;">Student ID</th>
                                        <th style="min-width: 200px;">Student Name</th>
                                        <th style="min-width: 80px;">Section</th>
                                        <th style="min-width: 100px;">Status</th>
                                        <th style="min-width: 120px;">Time Marked</th>
                                        <th class="no-print" style="min-width: 150px;">Action</th>
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
                                        <td class="fw-bold text-primary"><?php echo htmlspecialchars($student_id); ?></td>
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
                                                <i class="fas fa-check-circle me-1"></i> Attendance Marked
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
                        
                        <!-- Summary Section -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-chart-bar me-2"></i> Attendance Summary</h6>
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
                                    <h6><i class="fas fa-info-circle me-2"></i> Report Information</h6>
                                    <p class="mb-1"><strong>Generated:</strong> <?php echo date('d F Y h:i A'); ?></p>
                                    <p class="mb-1"><strong>Faculty:</strong> <?php echo htmlspecialchars($_SESSION['faculty_name'] ?? 'N/A'); ?></p>
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

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">
                        <i class="fas fa-graduation-cap me-2"></i> Smart Attendance System
                    </h5>
                    <p class="mb-0">A modern solution for managing student attendance using QR code technology.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <div class="footer-links">
                        <a href="faculty_dashboard.php" class="d-block mb-2">
                            <i class="fas fa-home me-2"></i> Dashboard
                        </a>
                        <a href="faculty_scan.php" class="d-block mb-2">
                            <i class="fas fa-qrcode me-2"></i> Scanner
                        </a>
                        <a href="faculty_sessions.php" class="d-block mb-2">
                            <i class="fas fa-history me-2"></i> Session History
                        </a>
                        <a href="faculty_profile.php" class="d-block">
                            <i class="fas fa-user me-2"></i> Profile
                        </a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">Contact</h5>
                    <p class="mb-2">
                        <i class="fas fa-envelope me-2"></i> support@smartattendance.edu
                    </p>
                    <p class="mb-2">
                        <i class="fas fa-phone me-2"></i> +91 98765 43210
                    </p>
                    <div class="social-icons mt-3">
                        <a href="#" class="me-3"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="me-3"><i class="fab fa-linkedin"></i></a>
                        <a href="#" class="me-3"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr class="bg-light my-4">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Smart Attendance System. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-0">Designed with <i class="fas fa-heart text-danger"></i> for better education</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Auto-refresh every 60 seconds to update attendance
    setTimeout(function() {
        window.location.reload();
    }, 60000); // 60 seconds

    // Search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchBox');
        const clearButton = document.getElementById('clearSearch');
        
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#attendanceTable tbody tr');
                let visibleCount = 0;
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Show message if no results found
                const noResultsRow = document.querySelector('#no-results-row');
                if (visibleCount === 0) {
                    if (!noResultsRow) {
                        const tbody = document.querySelector('#attendanceTable tbody');
                        const newRow = document.createElement('tr');
                        newRow.id = 'no-results-row';
                        newRow.innerHTML = `
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-search fa-2x text-muted mb-3"></i>
                                <h5 class="text-muted">No students found matching "${searchTerm}"</h5>
                                <p class="text-muted">Try a different search term</p>
                            </td>
                        `;
                        tbody.appendChild(newRow);
                    }
                } else if (noResultsRow) {
                    noResultsRow.remove();
                }
            });
            
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('keyup'));
                searchInput.focus();
            });
        }
        
        // Add print functionality
        document.querySelector('[onclick="window.print()"]')?.addEventListener('click', function() {
            // Show print confirmation
            if (confirm('Print attendance report?')) {
                setTimeout(() => {
                    window.print();
                }, 500);
            }
        });
    });
    
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
