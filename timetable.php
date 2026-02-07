<?php
// timetable.php - Student Timetable View (FIXED for NULL sessions)

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

// ==================== Get student ID and section ====================
$student_id = $_SESSION['student_id'] ?? null;
$student_section = '';
$student_name = '';

if (!$student_id) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Get student details ====================
if ($conn) {
    $student_query = "SELECT student_name, section FROM students WHERE student_id = ?";
    $student_stmt = mysqli_prepare($conn, $student_query);
    if ($student_stmt) {
        mysqli_stmt_bind_param($student_stmt, "s", $student_id);
        mysqli_stmt_execute($student_stmt);
        mysqli_stmt_bind_result($student_stmt, $student_name, $student_section);
        mysqli_stmt_fetch($student_stmt);
        mysqli_stmt_close($student_stmt);
    } else {
        echo "<!-- DEBUG: Failed to prepare student query -->\n";
    }
} else {
    echo "<!-- DEBUG: No database connection -->\n";
}

// ==================== Clean buffer before output ====================
if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

$page_title = "Class Timetable";

// Include header after processing
include 'header.php';
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
    <style>
        :root {
            --mqt-color: #4361ee;
            --wt-color: #4cc9f0;
            --or-color: #f72585;
            --dsp-color: #7209b7;
            --coa-color: #3a0ca3;
            --cd-color: #ff9e00;
            --lab-color: #06d6a0;
            --project-color: #ffd166;
            --mentor-color: #118ab2;
            --free-color: #e9ecef;
        }
        
        body {
            background: #f8f9fa;
            padding-bottom: 50px;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            color: white;
        }
        
        .section-badge {
            font-size: 0.9rem;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .time-slot {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #4361ee;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .time-slot:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .time-slot.lab {
            border-left-color: var(--lab-color);
        }
        
        .time-slot.mqt {
            border-left-color: var(--mqt-color);
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.1) 0%, rgba(67, 97, 238, 0.05) 100%);
        }
        
        .time-slot.wt {
            border-left-color: var(--wt-color);
            background: linear-gradient(135deg, rgba(76, 201, 240, 0.1) 0%, rgba(76, 201, 240, 0.05) 100%);
        }
        
        .time-slot.or {
            border-left-color: var(--or-color);
            background: linear-gradient(135deg, rgba(247, 37, 133, 0.1) 0%, rgba(247, 37, 133, 0.05) 100%);
        }
        
        .time-slot.dsp {
            border-left-color: var(--dsp-color);
            background: linear-gradient(135deg, rgba(114, 9, 183, 0.1) 0%, rgba(114, 9, 183, 0.05) 100%);
        }
        
        .time-slot.coa {
            border-left-color: var(--coa-color);
            background: linear-gradient(135deg, rgba(58, 12, 163, 0.1) 0%, rgba(58, 12, 163, 0.05) 100%);
        }
        
        .time-slot.cd {
            border-left-color: var(--cd-color);
            background: linear-gradient(135deg, rgba(255, 158, 0, 0.1) 0%, rgba(255, 158, 0, 0.05) 100%);
        }
        
        .subject-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            margin-bottom: 8px;
        }
        
        .mqt-badge { background: var(--mqt-color); }
        .wt-badge { background: var(--wt-color); }
        .or-badge { background: var(--or-color); }
        .dsp-badge { background: var(--dsp-color); }
        .coa-badge { background: var(--coa-color); }
        .cd-badge { background: var(--cd-color); }
        .lab-badge { background: var(--lab-color); }
        .project-badge { background: var(--project-color); color: #333; }
        .mentor-badge { background: var(--mentor-color); }
        
        .day-header {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 600;
        }
        
        .period-time {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .subject-name {
            font-weight: 600;
            margin: 5px 0;
            color: #495057;
        }
        
        .subject-fullname {
            font-size: 0.8rem;
            color: #6c757d;
            margin: 0;
        }
        
        .timetable-legend {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            margin-right: 10px;
        }
        
        .timetable-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            color: white;
            border-color: #4361ee;
        }
        
        /* Timetable Grid */
        .timetable-grid {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .timetable-row {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .timetable-row:last-child {
            border-bottom: none;
        }
        
        .time-cell {
            flex: 0 0 80px;
            background: #f8f9fa;
            padding: 12px;
            text-align: center;
            font-weight: 600;
            color: #495057;
            border-right: 1px solid #e0e0e0;
        }
        
        .day-cell {
            flex: 1;
            padding: 10px;
            min-height: 80px;
        }
        
        .period-item {
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .period-item.mqt { background: rgba(67, 97, 238, 0.1); border-left: 3px solid var(--mqt-color); }
        .period-item.wt { background: rgba(76, 201, 240, 0.1); border-left: 3px solid var(--wt-color); }
        .period-item.or { background: rgba(247, 37, 133, 0.1); border-left: 3px solid var(--or-color); }
        .period-item.dsp { background: rgba(114, 9, 183, 0.1); border-left: 3px solid var(--dsp-color); }
        .period-item.coa { background: rgba(58, 12, 163, 0.1); border-left: 3px solid var(--coa-color); }
        .period-item.cd { background: rgba(255, 158, 0, 0.1); border-left: 3px solid var(--cd-color); }
        .period-item.lab { background: rgba(6, 214, 160, 0.1); border-left: 3px solid var(--lab-color); }
        
        .current-period {
            box-shadow: 0 0 0 2px #4361ee;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(67, 97, 238, 0.4); }
            70% { box-shadow: 0 0 0 5px rgba(67, 97, 238, 0); }
            100% { box-shadow: 0 0 0 0 rgba(67, 97, 238, 0); }
        }
        
        /* Dark mode support */
        body.dark-mode .time-slot {
            background: #2d2d2d;
            color: #e0e0e0;
        }
        
        body.dark-mode .subject-name {
            color: #e0e0e0;
        }
        
        body.dark-mode .subject-fullname {
            color: #aaa;
        }
        
        body.dark-mode .timetable-legend {
            background: #2d2d2d;
            border-color: #444;
        }
        
        body.dark-mode .timetable-container {
            background: #2d2d2d;
        }
    </style>
</head>
<body>
    <!-- Header is included from header.php -->
    
    <div class="container-fluid py-4">
        <div class="container">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <h1 class="display-6">
                        <i class="fas fa-calendar-alt text-primary me-2"></i>Class Timetable
                    </h1>
                    <p class="text-muted">
                        Welcome, <?php echo htmlspecialchars($student_name); ?> | 
                        Section: <span class="badge bg-info"><?php echo htmlspecialchars($student_section); ?></span>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-outline-primary" onclick="printTimetable()">
                        <i class="fas fa-print me-1"></i>Print Timetable
                    </button>
                    <a href="student_dashboard.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Legend -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="timetable-legend">
                        <h6 class="mb-3"><i class="fas fa-palette me-2"></i>Subject Color Legend</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="legend-item">
                                    <div class="legend-color" style="background: var(--mqt-color);"></div>
                                    <span>MQT - Modern Quantum Theory</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: var(--wt-color);"></div>
                                    <span>WT - Web Technologies</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="legend-item">
                                    <div class="legend-color" style="background: var(--or-color);"></div>
                                    <span>OR - Operations Research</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: var(--dsp-color);"></div>
                                    <span>DSP - DataScience With Python</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="legend-item">
                                    <div class="legend-color" style="background: var(--coa-color);"></div>
                                    <span>COA - Computer Organization</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: var(--cd-color);"></div>
                                    <span>CD - Compiler Design</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="legend-item">
                                    <div class="legend-color" style="background: var(--lab-color);"></div>
                                    <span>LAB - Laboratory Session</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: var(--project-color);"></div>
                                    <span>Project/Community Work</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Period Timings -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Period Timings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3 mb-3">
                                    <div class="alert alert-primary py-3">
                                        <h6 class="mb-1">P1</h6>
                                        <p class="mb-0"><strong>08:30 – 09:30</strong><br>MQT (ALL Sections)</p>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="alert alert-secondary py-3">
                                        <h6 class="mb-1">P2</h6>
                                        <p class="mb-0"><strong>09:30 – 10:30</strong></p>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="alert alert-info py-3">
                                        <h6 class="mb-1">P3 - P4</h6>
                                        <p class="mb-0"><strong>10:40 – 12:40</strong></p>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="alert alert-warning py-3">
                                        <h6 class="mb-1">LUNCH</h6>
                                        <p class="mb-0"><strong>12:40 – 01:40</strong></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <div class="alert alert-success py-3">
                                        <h6 class="mb-1">P5</h6>
                                        <p class="mb-0"><strong>01:40 – 02:40</strong></p>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="alert alert-danger py-3">
                                        <h6 class="mb-1">P6</h6>
                                        <p class="mb-0"><strong>02:40 – 03:40</strong></p>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="alert alert-dark py-3">
                                        <h6 class="mb-1">P7</h6>
                                        <p class="mb-0"><strong>03:40 – 04:40</strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weekly Timetable Grid -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-week me-2"></i>Weekly Timetable - Section <?php echo htmlspecialchars($student_section); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="timetable-grid">
                                <!-- Header Row -->
                                <div class="timetable-row" style="background: #f1f3f4;">
                                    <div class="time-cell">Period</div>
                                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                                        <div class="day-cell text-center fw-bold"><?php echo $day; ?></div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php 
                                $periods = [
                                    'P1' => ['time' => '08:30 - 09:30'],
                                    'P2' => ['time' => '09:30 - 10:30'],
                                    'P3' => ['time' => '10:40 - 11:40'],
                                    'P4' => ['time' => '11:40 - 12:40'],
                                    'P5' => ['time' => '01:40 - 02:40'],
                                    'P6' => ['time' => '02:40 - 03:40'],
                                    'P7' => ['time' => '03:40 - 04:40'],
                                ];
                                
                                foreach ($periods as $period => $periodData): 
                                    $currentTime = date('H:i');
                                    $periodStart = substr($periodData['time'], 0, 5);
                                    $periodEnd = substr($periodData['time'], 8, 5);
                                    $isCurrent = ($currentTime >= $periodStart && $currentTime <= $periodEnd);
                                ?>
                                <div class="timetable-row <?php echo $isCurrent ? 'bg-light' : ''; ?>">
                                    <div class="time-cell">
                                        <?php echo $period; ?><br>
                                        <small><?php echo $periodData['time']; ?></small>
                                        <?php if ($isCurrent): ?>
                                            <br><span class="badge bg-danger mt-1">Now</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): 
                                        $classInfo = getClassInfo($student_section, $day, $period);
                                    ?>
                                        <div class="day-cell">
                                            <?php if (!empty($classInfo)): ?>
                                                <div class="period-item <?php echo strtolower($classInfo['code']); ?> <?php echo ($isCurrent && date('l') == $day) ? 'current-period' : ''; ?>">
                                                    <div class="d-flex justify-content-between">
                                                        <strong><?php echo $classInfo['code']; ?></strong>
                                                        <?php if ($classInfo['type'] == 'LAB'): ?>
                                                            <span class="badge bg-success">LAB</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small><?php echo $classInfo['name']; ?></small>
                                                    <?php if (!empty($classInfo['combined'])): ?>
                                                        <br><small class="text-muted"><?php echo $classInfo['combined']; ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Day-wise Detailed View -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Day-wise Schedule</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="day-header">
                                            <i class="fas fa-calendar-day me-2"></i><?php echo $day; ?>
                                        </div>
                                        
                                        <?php 
                                        $dayClasses = getDayClasses($student_section, $day);
                                        if (!empty($dayClasses)): 
                                            foreach ($dayClasses as $class): 
                                        ?>
                                            <div class="time-slot <?php echo strtolower($class['type']); ?> <?php echo strtolower($class['code']); ?>">
                                                <div class="period-time">
                                                    <i class="far fa-clock me-1"></i><?php echo $class['time']; ?>
                                                </div>
                                                <span class="subject-badge <?php echo strtolower($class['code']); ?>-badge">
                                                    <?php echo $class['code']; ?>
                                                    <?php if ($class['type'] === 'LAB'): ?>
                                                        <i class="fas fa-flask ms-1"></i>
                                                    <?php endif; ?>
                                                </span>
                                                <h6 class="subject-name"><?php echo $class['name']; ?></h6>
                                                <p class="subject-fullname"><?php echo $class['fullname']; ?></p>
                                                <?php if (!empty($class['combined'])): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-users me-1"></i><?php echo $class['combined']; ?>
                                                    </small>
                                                <?php endif; ?>
                                                <?php if ($class['duration'] > 1): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-hourglass-half me-1"></i><?php echo $class['duration']; ?> periods
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php 
                                            endforeach;
                                        else: 
                                        ?>
                                            <div class="alert alert-light text-center">
                                                <i class="far fa-calendar-times fa-2x mb-2"></i>
                                                <p class="mb-0">No classes scheduled</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Labs Schedule -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-flask me-2"></i>Laboratory Sessions</h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $labs = getLabsForSection($student_section);
                            if (!empty($labs)): 
                            ?>
                                <div class="row">
                                    <?php foreach ($labs as $lab): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-success h-100">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div class="bg-success text-white rounded-circle p-3 me-3">
                                                            <i class="fas fa-flask fa-2x"></i>
                                                        </div>
                                                        <div>
                                                            <h5 class="mb-0"><?php echo $lab['subject']; ?> LAB</h5>
                                                            <p class="text-muted mb-0"><?php echo $lab['day']; ?></p>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <p class="mb-1"><strong>Time:</strong></p>
                                                            <p class="mb-1"><strong>Duration:</strong></p>
                                                            <p class="mb-0"><strong>Frequency:</strong></p>
                                                        </div>
                                                        <div class="col-6 text-end">
                                                            <p class="mb-1"><?php echo $lab['time']; ?></p>
                                                            <p class="mb-1"><?php echo $lab['duration']; ?> periods</p>
                                                            <p class="mb-0">
                                                                <span class="badge bg-primary"><?php echo $lab['count']; ?> per week</span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mt-3">
                                                        <div class="progress" style="height: 8px;">
                                                            <div class="progress-bar bg-success" style="width: <?php echo min($lab['count'] * 33, 100); ?>%"></div>
                                                        </div>
                                                        <small class="text-muted">Weekly lab frequency</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Total Labs: <?php echo array_sum(array_column($labs, 'count')); ?> per week</strong> | 
                                    All lab sessions are 3 periods long (P5-P7)
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-flask fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Lab Sessions Found</h5>
                                    <p class="text-muted">Lab schedule not available for your section.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer is included from footer.php -->
    <?php include 'footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function printTimetable() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Class Timetable - <?php echo htmlspecialchars($student_name); ?></title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #333; border-bottom: 2px solid #4361ee; padding-bottom: 10px; }
                    h2 { color: #555; margin-top: 20px; }
                    .timetable-grid { border-collapse: collapse; width: 100%; margin: 20px 0; }
                    .timetable-grid th, .timetable-grid td { border: 1px solid #ddd; padding: 10px; text-align: center; }
                    .timetable-grid th { background: #f1f3f4; font-weight: bold; }
                    .day-header { background: #6c757d; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
                    .time-slot { border-left: 4px solid #4361ee; padding: 10px; margin: 5px 0; background: #f8f9fa; }
                    .subject-badge { background: #4361ee; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; display: inline-block; margin-bottom: 5px; }
                    .lab { border-left-color: #06d6a0; }
                    .lab .subject-badge { background: #06d6a0; }
                    .legend-item { display: flex; align-items: center; margin-bottom: 5px; }
                    .legend-color { width: 20px; height: 20px; border-radius: 4px; margin-right: 10px; }
                    @media print {
                        @page { margin: 0.5cm; }
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <h1>Class Timetable - <?php echo htmlspecialchars($student_name); ?></h1>
                <p><strong>Section:</strong> <?php echo htmlspecialchars($student_section); ?> | <strong>Generated:</strong> ${new Date().toLocaleDateString()}</p>
                
                <h2>Period Timings</h2>
                <table style="width: 100%; margin: 15px 0;">
                    <tr>
                        <td><strong>P1:</strong> 08:30 – 09:30</td>
                        <td><strong>P2:</strong> 09:30 – 10:30</td>
                        <td><strong>P3-P4:</strong> 10:40 – 12:40</td>
                    </tr>
                    <tr>
                        <td><strong>Lunch:</strong> 12:40 – 01:40</td>
                        <td><strong>P5:</strong> 01:40 – 02:40</td>
                        <td><strong>P6-P7:</strong> 02:40 – 04:40</td>
                    </tr>
                </table>
                
                <h2>Weekly Timetable Grid</h2>
                <table class="timetable-grid">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Monday</th>
                            <th>Tuesday</th>
                            <th>Wednesday</th>
                            <th>Thursday</th>
                            <th>Friday</th>
                            <th>Saturday</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${document.querySelector('.timetable-grid').innerHTML}
                    </tbody>
                </table>
                
                <h2>Subject Legend</h2>
                <div style="margin: 15px 0;">
                    <div class="legend-item"><div class="legend-color" style="background: #4361ee;"></div>MQT - Modern Quantum Theory</div>
                    <div class="legend-item"><div class="legend-color" style="background: #4cc9f0;"></div>WT - Web Technologies</div>
                    <div class="legend-item"><div class="legend-color" style="background: #f72585;"></div>OR - Operations Research</div>
                    <div class="legend-item"><div class="legend-color" style="background: #7209b7;"></div>DSP - DataScience With Python</div>
                    <div class="legend-item"><div class="legend-color" style="background: #3a0ca3;"></div>COA - Computer Organization</div>
                    <div class="legend-item"><div class="legend-color" style="background: #ff9e00;"></div>CD - Compiler Design</div>
                    <div class="legend-item"><div class="legend-color" style="background: #06d6a0;"></div>LAB - Laboratory Session</div>
                </div>
                
                <div class="no-print" style="margin-top: 30px; text-align: center; padding-top: 20px; border-top: 1px solid #ddd;">
                    <button onclick="window.print()" style="padding: 10px 20px; background: #4361ee; color: white; border: none; border-radius: 5px; cursor: pointer;">Print Timetable</button>
                    <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">Close</button>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
    
    // Auto-refresh current period highlight every minute
    setInterval(function() {
        const now = new Date();
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();
        const currentTime = `${currentHour.toString().padStart(2, '0')}:${currentMinute.toString().padStart(2, '0')}`;
        
        // Update "Now" badge
        document.querySelectorAll('.time-cell').forEach(cell => {
            const timeRange = cell.querySelector('small')?.textContent;
            if (timeRange) {
                const [start, end] = timeRange.split(' - ');
                const isCurrent = (currentTime >= start && currentTime <= end);
                const nowBadge = cell.querySelector('.badge');
                if (isCurrent) {
                    if (!nowBadge) {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-danger mt-1';
                        badge.textContent = 'Now';
                        cell.appendChild(document.createElement('br'));
                        cell.appendChild(badge);
                    }
                    cell.parentElement.classList.add('bg-light');
                } else {
                    if (nowBadge) nowBadge.remove();
                    cell.parentElement.classList.remove('bg-light');
                }
            }
        });
    }, 60000); // Check every minute
    </script>
</body>
</html>

<?php
// ==================== Helper Functions ====================

function getClassInfo($section, $day, $period) {
    // Get timetable based on section and day
    $timetable = getTimetableForSection($section);
    
    if (!isset($timetable[$day])) {
        return null;
    }
    
    foreach ($timetable[$day] as $class) {
        $classPeriods = getPeriodFromTime($class['time']);
        if (in_array($period, $classPeriods)) {
            return [
                'code' => $class['code'],
                'name' => $class['name'],
                'fullname' => $class['fullname'],
                'type' => $class['type'],
                'combined' => $class['combined'] ?? '',
                'duration' => $class['duration']
            ];
        }
    }
    
    return null;
}

function getPeriodFromTime($time) {
    $periodMap = [
        '08:30 - 09:30' => ['P1'],
        '09:30 - 10:30' => ['P2'],
        '10:40 - 11:40' => ['P3'],
        '11:40 - 12:40' => ['P4'],
        '01:40 - 02:40' => ['P5'],
        '02:40 - 03:40' => ['P6'],
        '03:40 - 04:40' => ['P7'],
        '01:40 - 04:40' => ['P5', 'P6', 'P7'], // 3-period labs
        '02:40 - 04:40' => ['P6', 'P7'], // 2-period labs
    ];
    
    return $periodMap[$time] ?? [];
}

function getDayClasses($section, $day) {
    $timetable = getTimetableForSection($section);
    return $timetable[$day] ?? [];
}

function getTimetableForSection($section) {
    // Your updated timetable data
    $timetables = [
        'A' => [
            'Monday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'DataScience With Python', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'WT', 'name' => 'WT LAB', 'fullname' => 'Web Technologies Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Tuesday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 02:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'DataScience With Python', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '02:40 - 04:40', 'code' => 'COA', 'name' => 'COA LAB', 'fullname' => 'Computer Organization
                +Lab', 'type' => 'LAB', 'duration' => 2],
            ],
            'Wednesday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1, 'combined' => '(A+D combined)'],
                ['time' => '01:40 - 04:40', 'code' => 'COA', 'name' => 'COA LAB', 'fullname' => 'Computer Organization Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Thursday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'COA', 'name' => 'COA LAB', 'fullname' => 'Computer Organization Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Friday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1, 'combined' => '(A+B combined)'],
                ['time' => '10:40 - 11:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'DSP', 'name' => 'wLAB', 'fullname' => 'DataScience With Python Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Saturday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
            ]
        ],
        'B' => [
            'Monday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'DataScience With Python', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'COA', 'name' => 'COA LAB', 'fullname' => 'Computer Organization Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Tuesday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 02:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'DataScience With Python', 'type' => 'LECTURE', 'duration' => 1],
            ],
            'Wednesday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
            ],
            'Thursday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 02:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'DataScience With Python', 'type' => 'LECTURE', 'duration' => 1],
            ],
            'Friday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1, 'combined' => '(A+B combined)'],
                ['time' => '10:40 - 11:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'DataScience With Python', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'DSP', 'name' => 'DSP LAB', 'fullname' => 'DataScience With Python Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Saturday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'MENTOR', 'name' => 'Mentor-Mentee', 'fullname' => 'Mentor-Mentee Session', 'type' => 'ACTIVITY', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'PROJECT', 'name' => 'Mini Project', 'fullname' => 'Mini Project/Community Work', 'type' => 'ACTIVITY', 'duration' => 3],
            ]
        ],
        'C' => [
            'Monday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 02:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
            ],
            'Tuesday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'DataScience With Python', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'DSP', 'name' => 'DSP LAB', 'fullname' => 'DataScience With Python Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Wednesday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 02:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
            ],
            'Thursday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'WT', 'name' => 'WT LAB', 'fullname' => 'Web Technologies Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Friday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'COA', 'name' => 'COA LAB', 'fullname' => 'Computer Organization Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Saturday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
            ]
        ],
        'D' => [
            'Monday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'Digital Signal Processing', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
            ],
            'Tuesday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'WT', 'name' => 'WT LAB', 'fullname' => 'Web Technologies Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Wednesday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1, 'combined' => '(A+D combined)'],
                ['time' => '01:40 - 02:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
            ],
            'Thursday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'COA', 'name' => 'COA LAB', 'fullname' => 'Computer Organization Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Friday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1, 'combined' => '(C+D combined)'],
                ['time' => '11:40 - 12:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'Digital Signal Processing', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'DSP', 'name' => 'DSP LAB', 'fullname' => 'Digital Signal Processing Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Saturday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
            ]
        ],
       // Update the Section E timetable in getTimetableForSection() function:

'E' => [
    'Monday' => [
        ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '10:40 - 11:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'DataScience With Python', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '11:40 - 12:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '01:40 - 04:40', 'code' => 'DSP', 'name' => 'DSP LAB', 'fullname' => 'DataScience With Python Lab', 'type' => 'LAB', 'duration' => 3],
    ],
    'Tuesday' => [
        ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '10:40 - 11:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '11:40 - 12:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '01:40 - 04:40', 'code' => 'WT', 'name' => 'WT LAB', 'fullname' => 'Web Technologies Lab', 'type' => 'LAB', 'duration' => 3],
    ],
    'Wednesday' => [
        ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '10:40 - 11:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '11:40 - 12:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '02:40 - 04:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'DataScience With Python', 'type' => 'LECTURE', 'duration' => 2],
    ],
    'Thursday' => [
        ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '09:30 - 10:30', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '10:40 - 11:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '11:40 - 12:40', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '01:40 - 04:40', 'code' => 'COA', 'name' => 'COA LAB', 'fullname' => 'Computer Organization Lab', 'type' => 'LAB', 'duration' => 3],
    ],
    'Friday' => [
        ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '09:30 - 10:30', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '10:40 - 11:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '11:40 - 12:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
        ['time' => '02:40 - 04:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 2],
    ],
    'Saturday' => [
        ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
    ]
]
    ];

    // Return timetable for the specific section or default to A
    return isset($timetables[$section]) ? $timetables[$section] : $timetables['A'];
}

function getLabsForSection($section) {
    $labs = [
        'A' => [
            ['subject' => 'WT', 'day' => 'Tuesday', 'time' => '02:40 - 04:40', 'duration' => 2, 'count' => 1],
            ['subject' => 'COA', 'day' => 'Thursday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
            ['subject' => 'DSP', 'day' => 'Friday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
        ],
        'B' => [
            ['subject' => 'COA', 'day' => 'Monday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
            ['subject' => 'DSP', 'day' => 'Wednesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
            ['subject' => 'WT', 'day' => 'Friday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
        ],
        'C' => [
            ['subject' => 'DSP', 'day' => 'Tuesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
            ['subject' => 'WT', 'day' => 'Thursday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
            ['subject' => 'COA', 'day' => 'Friday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
        ],
        'D' => [
            ['subject' => 'WT', 'day' => 'Tuesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
            ['subject' => 'COA', 'day' => 'Thursday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
            ['subject' => 'DSP', 'day' => 'Friday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
        ],
       'E' => [
    ['subject' => 'DSP', 'day' => 'Monday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
    ['subject' => 'WT', 'day' => 'Tuesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
    ['subject' => 'COA', 'day' => 'Thursday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
]
    ];

    return isset($labs[$section]) ? $labs[$section] : [];
}

// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
