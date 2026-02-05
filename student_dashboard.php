<?php
// student_dashboard.php - Fixed for Render + Aiven

// ==================== CRITICAL: Start output buffering ====================
if (!ob_get_level()) {
    ob_start();
}

// ==================== Configure session for Render ====================
ini_set('session.save_path', '/tmp');
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);

// ==================== Start session ====================
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ==================== Include database config ====================
require_once 'config.php';

// ==================== Security check ====================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Role check ====================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Get student ID ====================
$student_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null;
if (!$student_id) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Clean buffer before output ====================
if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

// ==================== Database operations ====================
$student = null;
$attendance_result = null;
$error = '';
$success = '';

// Get student details
$student_query = "SELECT * FROM students WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $student_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} else {
    $error = "Database query error: " . mysqli_error($conn);
}

// Get student's attendance (only if student exists)
if ($student) {
    $attendance_query = "SELECT ar.*, s.subject_code, s.subject_name, ses.session_id, ses.start_time
                         FROM attendance_records ar 
                         JOIN sessions ses ON ar.session_id = ses.session_id
                         JOIN subjects s ON ses.subject_id = s.subject_id
                         WHERE ar.student_id = ? 
                         ORDER BY ar.marked_at DESC LIMIT 10";
    $stmt2 = mysqli_prepare($conn, $attendance_query);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, "s", $student_id);
        mysqli_stmt_execute($stmt2);
        $attendance_result = mysqli_stmt_get_result($stmt2);
        mysqli_stmt_close($stmt2);
    } else {
        $error = "Attendance query error: " . mysqli_error($conn);
    }
}

// Set page title
$page_title = "Student Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - CSE Attendance System</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Header Styles -->
    <style>
        /* Header Styles */
        .header-container {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            color: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .dark-mode .header-container {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }
        
        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            text-decoration: none;
        }
        
        .logo:hover {
            color: rgba(255,255,255,0.9) !important;
        }
        
        .mode-toggle {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .mode-toggle:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.1);
        }
        
        .dark-mode .mode-toggle {
            background: rgba(255,255,255,0.15);
        }
        
        .dark-mode .mode-toggle:hover {
            background: rgba(255,255,255,0.25);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #f72585;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
        }
        
        /* Body and main styles */
        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            min-height: 100vh;
            transition: background-color 0.3s ease;
        }
        
        .dark-mode body {
            background: #121212;
            color: #e0e0e0;
        }
        
        .dark-mode .card {
            background: #1e1e1e;
            border-color: #333;
        }
        
        .dark-mode .card-header {
            background: #2d2d2d !important;
            border-color: #333;
            color: #e0e0e0;
        }
        
        .dark-mode .table {
            background: #1e1e1e;
            color: #e0e0e0;
        }
        
        .dark-mode .table th {
            background: #2d2d2d;
            border-color: #333;
            color: #e0e0e0;
        }
        
        .dark-mode .table td {
            border-color: #333;
            color: #e0e0e0;
        }
        
        .dark-mode .table tbody tr:hover {
            background: #2d2d2d;
        }
        
        .dark-mode .text-muted {
            color: #aaa !important;
        }
        
        .dark-mode .alert {
            background: #2d2d2d;
            color: #e0e0e0;
            border-color: #444;
        }
        
        .dark-mode .btn-outline-primary {
            border-color: #4361ee;
            color: #4361ee;
        }
        
        .dark-mode .btn-outline-primary:hover {
            background: #4361ee;
            color: white;
        }
        
        /* Card Styling */
        .card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease, background-color 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .dark-mode .card {
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
        }
        
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .dark-mode .profile-img {
            border-color: #333;
        }
        
        .badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .btn {
            border-radius: 10px;
            font-weight: 500;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            border: none;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #4cc9f0 0%, #4361ee 100%);
            border: none;
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border: none;
            padding: 15px;
        }
        
        .table td {
            padding: 15px;
            vertical-align: middle;
            border-color: #f1f3f4;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Alert styling */
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .welcome-card h4 {
            font-weight: 700;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .dark-mode .stat-card {
            background: #1e1e1e;
            color: #e0e0e0;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        
        .dark-mode .stat-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            font-weight: 700;
            margin: 0;
        }
        
        .stat-card p {
            color: #6c757d;
            margin: 0;
        }
        
        .dark-mode .stat-card p {
            color: #aaa;
        }
        
        /* Footer Styling */
        footer {
            background: #343a40 !important;
            color: white;
            margin-top: auto;
        }
        
        .dark-mode footer {
            background: #1a1a1a !important;
        }
        
        /* Translation Button */
        .translation-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .translation-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.05);
        }
        
        /* Additional Sections */
        .quick-actions {
            margin-top: 20px;
        }
        
        .action-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .dark-mode .action-card {
            background: #1e1e1e;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        
        .dark-mode .action-card:hover {
            box-shadow: 0 6px 15px rgba(0,0,0,0.3);
        }
        
        .action-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #4361ee;
        }
        
        .dark-mode .action-card i {
            color: #4cc9f0;
        }
        
        .action-card h5 {
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .action-card p {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .dark-mode .action-card p {
            color: #aaa;
        }
    </style>
</head>
<body>
    <!-- Custom Header -->
    <div class="header-container sticky-top">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center py-3">
                <!-- Logo and Brand -->
                <div class="d-flex align-items-center">
                    <a href="student_dashboard.php" class="logo d-flex align-items-center">
                        <i class="fas fa-graduation-cap me-2"></i>
                        <span>CSE Attendance</span>
                    </a>
                    <span class="badge bg-light text-primary ms-2">Student Portal</span>
                </div>
                
                <!-- Right Side: User Info + Mode Toggle -->
                <div class="d-flex align-items-center gap-3">
                    <!-- User Profile -->
                    <div class="dropdown">
                        <button class="btn btn-link text-white text-decoration-none dropdown-toggle d-flex align-items-center" 
                                type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2 fs-5"></i>
                            <span class="d-none d-md-inline">
                                <?php 
                                if ($student) {
                                    $name_parts = explode(' ', $student['student_name']);
                                    echo htmlspecialchars($name_parts[0]);
                                } else {
                                    echo 'Student';
                                }
                                ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <h6 class="dropdown-header">
                                    <i class="fas fa-user me-2"></i>
                                    <?php echo htmlspecialchars($student['student_name'] ?? 'Student'); ?>
                                </h6>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="student_profile.php">
                                    <i class="fas fa-user me-2"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="attendance_viewer.php">
                                    <i class="fas fa-chart-line me-2"></i> Attendance Report
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="edit_profile.php">
                                    <i class="fas fa-cog me-2"></i> Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Notification Bell -->
                    <div class="position-relative">
                        <button class="btn btn-link text-white position-relative" onclick="showNotifications()">
                            <i class="fas fa-bell fs-5"></i>
                            <span class="notification-badge">3</span>
                        </button>
                    </div>
                    
                    <!-- Dark/Light Mode Toggle -->
                    <button class="mode-toggle" id="modeToggle" title="Toggle Dark Mode">
                        <i class="fas fa-moon" id="modeIcon"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="container">
            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Left Column: Profile Only (QR Code Section Removed) -->
                <div class="col-lg-4 col-md-12 mb-4">
                    <!-- Profile Card -->
                    <div class="card shadow-lg">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>My Profile</h5>
                        </div>
                        <div class="card-body text-center">
                            <!-- Profile Picture -->
                            <div class="mb-4">
                                <?php
                                $profile_pic = 'uploads/profiles/default.png';
                                if (isset($student['profile_pic']) && !empty($student['profile_pic']) && file_exists($student['profile_pic'])) {
                                    $profile_pic = $student['profile_pic'];
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                                     class="profile-img rounded-circle" 
                                     alt="Profile Picture">
                            </div>
                            
                            <?php if ($student): ?>
                                <h4 class="card-title"><?php echo htmlspecialchars($student['student_name']); ?></h4>
                                <div class="student-info text-start mt-3">
                                    <p><i class="fas fa-fingerprint text-primary me-2"></i> 
                                       <strong>ID:</strong> <?php echo htmlspecialchars($student_id); ?></p>
                                    <p><i class="fas fa-envelope text-primary me-2"></i> 
                                       <strong>Email:</strong> <?php echo htmlspecialchars($student['student_email']); ?></p>
                                    <p><i class="fas fa-id-card text-primary me-2"></i> 
                                       <strong>Student ID:</strong> <?php echo htmlspecialchars($student['id_number'] ?? 'N/A'); ?></p>
                                    <p><i class="fas fa-users text-primary me-2"></i> 
                                       <strong>Section:</strong> <?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></p>
                                    <p><i class="fas fa-building text-primary me-2"></i> 
                                       <strong>Department:</strong> <?php echo htmlspecialchars($student['student_department'] ?? 'N/A'); ?></p>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="student_profile.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-edit me-2"></i> Edit Profile
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Student profile not found
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Attendance Statistics Card -->
                    <div class="card shadow-lg mt-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Attendance Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="mb-2">
                                        <i class="fas fa-calendar-check fa-2x text-primary"></i>
                                    </div>
                                    <h5 id="todayAttendance">0</h5>
                                    <small>Today</small>
                                </div>
                                <div class="col-4">
                                    <div class="mb-2">
                                        <i class="fas fa-percentage fa-2x text-success"></i>
                                    </div>
                                    <h5 id="attendancePercent">0%</h5>
                                    <small>Overall</small>
                                </div>
                                <div class="col-4">
                                    <div class="mb-2">
                                        <i class="fas fa-clock fa-2x text-warning"></i>
                                    </div>
                                    <h5 id="lateCount">0</h5>
                                    <small>Late</small>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="attendance_viewer.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-chart-line me-2"></i> View Detailed Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Dashboard Content -->
                <div class="col-lg-8 col-md-12">
                    <!-- Welcome Card -->
                    <div class="card shadow-lg welcome-card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="mb-2">
                                        Welcome back, 
                                        <?php 
                                            if ($student) {
                                                $name_parts = explode(' ', $student['student_name']);
                                                echo htmlspecialchars($name_parts[0]); 
                                            } else {
                                                echo 'Student';
                                            }
                                        ?>!
                                    </h4>
                                    <p class="mb-0">Track your attendance and view your academic schedule.</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="fas fa-user-graduate fa-4x text-white opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Section -->
                    <div class="row mb-4 quick-actions">
                        <div class="col-12 mb-3">
                            <h5 class="mb-3"><i class="fas fa-bolt text-warning me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="action-card">
                                <i class="fas fa-user-check"></i>
                                <h5>Mark Attendance</h5>
                                <p>Attend your classes</p>
                                <button class="btn btn-sm btn-primary" onclick="openAttendance()">
                                    Mark Now
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="action-card">
                                <i class="fas fa-chart-bar"></i>
                                <h5>Reports</h5>
                                <p>View attendance analytics</p>
                                <a href="attendance_viewer.php" class="btn btn-sm btn-success">
                                    View Report
                                </a>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="action-card">
                                <i class="fas fa-calendar-alt"></i>
                                <h5>Timetable</h5>
                                <p>View class schedule</p>
                                <!-- CHANGED: Link to timetable.php -->
                                <a href="timetable.php" class="btn btn-sm btn-info">
                                    View Schedule
                                </a>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="action-card">
                                <i class="fas fa-book"></i>
                                <h5>Study Materials</h5>
                                <p>Access course materials</p>
                                <a href="materials.php" class="btn btn-sm btn-warning">
                                    Access
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance History -->
                    <div class="card shadow-lg">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Attendance Records</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($attendance_result && mysqli_num_rows($attendance_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Subject</th>
                                                <th>Session Time</th>
                                                <th>Marked Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $attendance_result->data_seek(0); // Reset pointer
                                            while ($record = mysqli_fetch_assoc($attendance_result)): 
                                            ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($record['marked_at'])); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($record['subject_code']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($record['subject_name']); ?></small>
                                                </td>
                                                <td><?php echo date('h:i A', strtotime($record['start_time'])); ?></td>
                                                <td><?php echo date('h:i A', strtotime($record['marked_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-success">Present</span>
                                                    <?php
                                                    $session_time = strtotime($record['start_time']);
                                                    $marked_time = strtotime($record['marked_at']);
                                                    if (($marked_time - $session_time) > 900) { // 15 minutes late
                                                        echo '<span class="badge bg-warning ms-1">Late</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <a href="attendance_viewer.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-chart-line me-2"></i> View Complete Attendance Report
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No attendance records found</h5>
                                    <p class="text-muted mb-4">Your attendance will appear here after marking attendance in class</p>
                                    <a href="how_to_use.php" class="btn btn-outline-primary">
                                        <i class="fas fa-question-circle me-2"></i> How to mark attendance
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-5 py-4 bg-dark text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h6>CSE Attendance System</h6>
                    <p class="mb-0 small">Department of Computer Science</p>
                    <p class="mb-0 small">Attendance Management System</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 small">&copy; <?php echo date('Y'); ?> CSE Department</p>
                    <p class="mb-0 small">Student ID: <?php echo htmlspecialchars($student_id); ?></p>
                    <p class="mb-0 small">
                        <span id="currentTime"></span> | 
                        <span id="currentDate"></span>
                    </p>
                    <!-- ADDED: Translation Button -->
                    <button class="translation-btn mt-2" id="translateBtn">
                        <i class="fas fa-language me-1"></i> Translate
                    </button>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Dark/Light Mode Toggle
    document.addEventListener('DOMContentLoaded', function() {
        const modeToggle = document.getElementById('modeToggle');
        const modeIcon = document.getElementById('modeIcon');
        const body = document.body;
        const translateBtn = document.getElementById('translateBtn');
        
        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            modeIcon.classList.remove('fa-moon');
            modeIcon.classList.add('fa-sun');
        }
        
        // Toggle theme
        modeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                modeIcon.classList.remove('fa-moon');
                modeIcon.classList.add('fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                modeIcon.classList.remove('fa-sun');
                modeIcon.classList.add('fa-moon');
                localStorage.setItem('theme', 'light');
            }
        });
        
        // Translation Button Handler
        if (translateBtn) {
            translateBtn.addEventListener('click', function() {
                // Toggle between English and another language
                const isEnglish = !document.documentElement.hasAttribute('data-translated');
                
                if (isEnglish) {
                    // Translate to another language (example: Arabic/Spanish)
                    translatePage('ar'); // Change to your desired language code
                    translateBtn.innerHTML = '<i class="fas fa-language me-1"></i> English';
                    document.documentElement.setAttribute('data-translated', 'true');
                    document.documentElement.setAttribute('lang', 'ar');
                } else {
                    // Back to English
                    translatePage('en');
                    translateBtn.innerHTML = '<i class="fas fa-language me-1"></i> Translate';
                    document.documentElement.removeAttribute('data-translated');
                    document.documentElement.setAttribute('lang', 'en');
                }
            });
        }
        
        // Update current time and date
        function updateDateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString();
            document.getElementById('currentDate').textContent = now.toLocaleDateString();
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Load attendance statistics via AJAX
        loadAttendanceStats();
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    
    // Load attendance statistics
    function loadAttendanceStats() {
        // This would typically be an AJAX call to get real data
        // For now, we'll simulate with placeholder data
        setTimeout(() => {
            document.getElementById('todayAttendance').textContent = '2';
            document.getElementById('attendancePercent').textContent = '85%';
            document.getElementById('lateCount').textContent = '1';
        }, 500);
    }
    
    // Translation function
    function translatePage(targetLang) {
        const translations = {
            'en': {
                'CSE Attendance System': 'CSE Attendance System',
                'Department of Computer Science': 'Department of Computer Science',
                'Attendance Management System': 'Attendance Management System',
                'Student ID': 'Student ID',
                'Welcome back': 'Welcome back',
                'Track your attendance': 'Track your attendance and view your academic schedule',
                'Quick Actions': 'Quick Actions',
                'Mark Attendance': 'Mark Attendance',
                'Attend your classes': 'Attend your classes',
                'Reports': 'Reports',
                'View attendance analytics': 'View attendance analytics',
                'Timetable': 'Timetable',
                'View class schedule': 'View class schedule',
                'Study Materials': 'Study Materials',
                'Access course materials': 'Access course materials',
                'Recent Attendance Records': 'Recent Attendance Records',
                'Date': 'Date',
                'Subject': 'Subject',
                'Session Time': 'Session Time',
                'Marked Time': 'Marked Time',
                'Status': 'Status',
                'No attendance records found': 'No attendance records found',
                'Your attendance will appear here': 'Your attendance will appear here after marking attendance in class'
            },
            'ar': {
                'CSE Attendance System': 'نظام الحضور لقسم علوم الحاسوب',
                'Department of Computer Science': 'قسم علوم الحاسوب',
                'Attendance Management System': 'نظام إدارة الحضور',
                'Student ID': 'رقم الطالب',
                'Welcome back': 'مرحبًا بعودتك',
                'Track your attendance': 'تتبع حضورك وراجع جدولك الأكاديمي',
                'Quick Actions': 'إجراءات سريعة',
                'Mark Attendance': 'تسجيل الحضور',
                'Attend your classes': 'حضر فصولك الدراسية',
                'Reports': 'تقارير',
                'View attendance analytics': 'عرض تحليلات الحضور',
                'Timetable': 'الجدول الزمني',
                'View class schedule': 'عرض جدول الحصص',
                'Study Materials': 'المواد الدراسية',
                'Access course materials': 'الوصول إلى المواد الدراسية',
                'Recent Attendance Records': 'سجلات الحضور الحديثة',
                'Date': 'التاريخ',
                'Subject': 'المادة',
                'Session Time': 'وقت الحصة',
                'Marked Time': 'وقت التسجيل',
                'Status': 'الحالة',
                'No attendance records found': 'لم يتم العثور على سجلات حضور',
                'Your attendance will appear here': 'سيظهر حضورك هنا بعد التسجيل في الفصل'
            }
        };
        
        // Get current language
        const currentLang = document.documentElement.getAttribute('lang') || 'en';
        const trans = translations[targetLang] || translations['en'];
        
        // Translate all elements with data-translate attribute
        document.querySelectorAll('[data-translate]').forEach(element => {
            const key = element.getAttribute('data-translate');
            if (trans[key]) {
                element.textContent = trans[key];
            }
        });
        
        // Translate common text
        const elementsToTranslate = [
            { selector: 'footer h6', key: 'CSE Attendance System' },
            { selector: 'footer p:nth-child(2)', key: 'Department of Computer Science' },
            { selector: 'footer p:nth-child(3)', key: 'Attendance Management System' },
            { selector: '.welcome-card h4', key: 'Welcome back' },
            { selector: '.welcome-card p', key: 'Track your attendance' },
            { selector: '.quick-actions h5', key: 'Quick Actions' },
            // Add more selectors as needed
        ];
        
        elementsToTranslate.forEach(item => {
            const element = document.querySelector(item.selector);
            if (element && trans[item.key]) {
                element.textContent = trans[item.key];
            }
        });
    }
    
    // Quick Action Functions
    function openAttendance() {
        alert('Attendance marking feature would open here.');
        // In a real app: window.location.href = 'mark_attendance.php';
    }
    
    function showNotifications() {
        alert('Notifications panel would open here. You have 3 new notifications.');
        // In a real app: window.location.href = 'notifications.php';
    }
    </script>
</body>
</html>
<?php
// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
