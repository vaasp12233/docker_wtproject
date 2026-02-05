<?php
// timetable.php - Student Timetable View

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
$student_query = "SELECT student_name, section FROM students WHERE student_id = ?";
$student_stmt = mysqli_prepare($conn, $student_query);
if ($student_stmt) {
    mysqli_stmt_bind_param($student_stmt, "s", $student_id);
    mysqli_stmt_execute($student_stmt);
    mysqli_stmt_bind_result($student_stmt, $student_name, $student_section);
    mysqli_stmt_fetch($student_stmt);
    mysqli_stmt_close($student_stmt);
} else {
    die("Database error");
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
    <!-- FullCalendar -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
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
        
        .fc-day-today {
            background-color: rgba(67, 97, 238, 0.1) !important;
        }
        
        .fc-event {
            border-radius: 6px !important;
            border: none !important;
            padding: 5px !important;
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
                                    <span>DSP - Digital Signal Processing</span>
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
                                        <h6 class="mb-1">P1 - P2</h6>
                                        <p class="mb-0"><strong>08:30 – 10:30</strong></p>
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
                                <div class="col-md-3 mb-3">
                                    <div class="alert alert-success py-3">
                                        <h6 class="mb-1">P5 - P7</h6>
                                        <p class="mb-0"><strong>01:40 – 04:40</strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <ul class="nav nav-tabs mb-4" id="timetableTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="weekly-tab" data-bs-toggle="tab" data-bs-target="#weekly" type="button">
                        <i class="fas fa-calendar-week me-2"></i>Weekly View
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar" type="button">
                        <i class="fas fa-calendar me-2"></i>Calendar View
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="lab-tab" data-bs-toggle="tab" data-bs-target="#lab" type="button">
                        <i class="fas fa-flask me-2"></i>Labs Schedule
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="timetableTabContent">
                <!-- Weekly View Tab -->
                <div class="tab-pane fade show active" id="weekly" role="tabpanel">
                    <?php 
                    // Define timetable data based on section
                    $timetable = getTimetableForSection($student_section);
                    ?>
                    <div class="row">
                        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                            <div class="col-md-4 mb-4">
                                <div class="day-header">
                                    <i class="fas fa-calendar-day me-2"></i><?php echo $day; ?>
                                </div>
                                
                                <?php if (isset($timetable[$day])): ?>
                                    <?php foreach ($timetable[$day] as $period): ?>
                                        <div class="time-slot <?php echo strtolower($period['type']); ?> <?php echo strtolower($period['code']); ?>">
                                            <div class="period-time">
                                                <i class="far fa-clock me-1"></i><?php echo $period['time']; ?>
                                            </div>
                                            <span class="subject-badge <?php echo strtolower($period['code']); ?>-badge">
                                                <?php echo $period['code']; ?>
                                                <?php if ($period['type'] === 'LAB'): ?>
                                                    <i class="fas fa-flask ms-1"></i>
                                                <?php endif; ?>
                                            </span>
                                            <h6 class="subject-name"><?php echo $period['name']; ?></h6>
                                            <p class="subject-fullname"><?php echo $period['fullname']; ?></p>
                                            <?php if ($period['duration'] > 1): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-hourglass-half me-1"></i><?php echo $period['duration']; ?> periods
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-light text-center">
                                        <i class="far fa-calendar-times fa-2x mb-2"></i>
                                        <p class="mb-0">No classes scheduled</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Calendar View Tab -->
                <div class="tab-pane fade" id="calendar" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Interactive Calendar View</h5>
                        </div>
                        <div class="card-body">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>

                <!-- Labs Schedule Tab -->
                <div class="tab-pane fade" id="lab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-flask me-2"></i>Laboratory Sessions - <?php echo htmlspecialchars($student_section); ?></h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $labs = getLabsForSection($student_section);
                            if (!empty($labs)): 
                            ?>
                                <div class="row">
                                    <?php foreach ($labs as $lab): ?>
                                        <div class="col-md-6 mb-4">
                                            <div class="card border-success h-100">
                                                <div class="card-header bg-success text-white">
                                                    <h6 class="mb-0">
                                                        <i class="fas fa-flask me-2"></i><?php echo $lab['subject']; ?> LAB
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <p class="mb-1"><strong>Day:</strong></p>
                                                            <p class="mb-1"><strong>Time:</strong></p>
                                                            <p class="mb-1"><strong>Duration:</strong></p>
                                                            <p class="mb-0"><strong>Sessions/Week:</strong></p>
                                                        </div>
                                                        <div class="col-6 text-end">
                                                            <p class="mb-1"><?php echo $lab['day']; ?></p>
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
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    
    <script>
    // Initialize FullCalendar
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        
        if (calendarEl) {
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [
                    <?php 
                    // Generate calendar events from timetable
                    $events = generateCalendarEvents($student_section);
                    foreach ($events as $event): ?>
                    {
                        title: '<?php echo $event['title']; ?>',
                        start: '<?php echo $event['start']; ?>',
                        end: '<?php echo $event['end']; ?>',
                        backgroundColor: '<?php echo $event['color']; ?>',
                        borderColor: '<?php echo $event['color']; ?>',
                        extendedProps: {
                            type: '<?php echo $event['type']; ?>'
                        }
                    },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    alert('Class: ' + info.event.title + '\nTime: ' + 
                          info.event.start.toLocaleTimeString() + ' - ' + 
                          info.event.end.toLocaleTimeString());
                },
                slotMinTime: '08:00:00',
                slotMaxTime: '17:00:00',
                allDaySlot: false,
                height: 'auto',
                weekends: true,
                businessHours: {
                    daysOfWeek: [1, 2, 3, 4, 5, 6], // Monday - Saturday
                    startTime: '08:30',
                    endTime: '16:40',
                }
            });
            calendar.render();
        }
        
        // Tab persistence
        const triggerTabList = document.querySelectorAll('#timetableTabs button');
        triggerTabList.forEach(triggerEl => {
            triggerEl.addEventListener('click', event => {
                const tabId = triggerEl.getAttribute('data-bs-target');
                localStorage.setItem('activeTimetableTab', tabId);
            });
        });
        
        // Restore active tab
        const activeTab = localStorage.getItem('activeTimetableTab');
        if (activeTab) {
            const triggerEl = document.querySelector(`[data-bs-target="${activeTab}"]`);
            if (triggerEl) {
                bootstrap.Tab.getOrCreateInstance(triggerEl).show();
            }
        }
    });
    
    function printTimetable() {
        const activeTab = localStorage.getItem('activeTimetableTab') || '#weekly';
        let content = '';
        
        if (activeTab === '#weekly') {
            content = document.getElementById('weekly').innerHTML;
        } else if (activeTab === '#lab') {
            content = document.getElementById('lab').innerHTML;
        } else {
            content = document.getElementById('weekly').innerHTML;
        }
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Class Timetable - <?php echo htmlspecialchars($student_name); ?></title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #333; border-bottom: 2px solid #4361ee; padding-bottom: 10px; }
                    .day-header { background: #6c757d; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
                    .time-slot { border-left: 4px solid #4361ee; padding: 10px; margin: 5px 0; background: #f8f9fa; }
                    .subject-badge { background: #4361ee; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; }
                    .lab { border-left-color: #06d6a0; }
                    .lab .subject-badge { background: #06d6a0; }
                    @media print {
                        @page { size: landscape; margin: 0.5cm; }
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <h1>Class Timetable - <?php echo htmlspecialchars($student_name); ?></h1>
                <p>Section: <?php echo htmlspecialchars($student_section); ?> | Generated: ${new Date().toLocaleDateString()}</p>
                ${content}
                <div class="no-print" style="margin-top: 20px; text-align: center;">
                    <button onclick="window.print()" style="padding: 10px 20px; background: #4361ee; color: white; border: none; border-radius: 5px; cursor: pointer;">Print</button>
                    <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">Close</button>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        setTimeout(() => printWindow.print(), 500);
    }
    </script>
</body>
</html>

<?php
// ==================== Helper Functions ====================

function getTimetableForSection($section) {
    $timetables = [
        'A' => [
            'Monday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'Digital Signal Processing', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'COA', 'name' => 'COA LAB', 'fullname' => 'Computer Organization Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Tuesday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'OR', 'name' => 'OR', 'fullname' => 'Operations Research', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 02:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'Digital Signal Processing', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '02:40 - 04:40', 'code' => 'DSP', 'name' => 'DSP LAB', 'fullname' => 'Digital Signal Processing Lab', 'type' => 'LAB', 'duration' => 2],
            ],
            'Wednesday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 04:40', 'code' => 'COA', 'name' => 'COA LAB', 'fullname' => 'Computer Organization Lab', 'type' => 'LAB', 'duration' => 3],
            ],
            'Thursday' => [
                ['time' => '08:30 - 09:30', 'code' => 'MQT', 'name' => 'MQT', 'fullname' => 'Modern Quantum Theory', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '09:30 - 10:30', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 02:40', 'code' => 'DSP', 'name' => 'DSP', 'fullname' => 'Digital Signal Processing', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '02:40 - 04:40', 'code' => 'WT', 'name' => 'WT LAB', 'fullname' => 'Web Technologies Lab', 'type' => 'LAB', 'duration' => 2],
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
                ['time' => '09:30 - 10:30', 'code' => 'COA', 'name' => 'COA', 'fullname' => 'Computer Organization', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '10:40 - 11:40', 'code' => 'CD', 'name' => 'CD', 'fullname' => 'Compiler Design', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '11:40 - 12:40', 'code' => 'WT', 'name' => 'WT', 'fullname' => 'Web Technologies', 'type' => 'LECTURE', 'duration' => 1],
                ['time' => '01:40 - 02:40', 'code' => 'MENTOR', 'name' => 'Mentor-Mentee', 'fullname' => 'Mentor-Mentee Session', 'type' => 'ACTIVITY', 'duration' => 1],
                ['time' => '02:40 - 03:40', 'code' => 'PROJECT', 'name' => 'Mini Project', 'fullname' => 'Mini Project Work', 'type' => 'ACTIVITY', 'duration' => 1],
                ['time' => '03:40 - 04:40', 'code' => 'COMMUNITY', 'name' => 'Community Work', 'fullname' => 'Community Service', 'type' => 'ACTIVITY', 'duration' => 1],
            ]
        ],
        // Add other sections B, C, D, E similarly...
    ];

    // Return timetable for the specific section
    return isset($timetables[$section]) ? $timetables[$section] : $timetables['A'];
}

function getLabsForSection($section) {
    $labs = [
        'A' => [
            ['subject' => 'COA', 'day' => 'Monday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
            ['subject' => 'DSP', 'day' => 'Tuesday', 'time' => '02:40 - 04:40', 'duration' => 2, 'count' => 1],
            ['subject' => 'WT', 'day' => 'Thursday', 'time' => '02:40 - 04:40', 'duration' => 2, 'count' => 1],
            ['subject' => 'COA', 'day' => 'Wednesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
        ],
        'B' => [
            ['subject' => 'COA', 'day' => 'Monday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
            ['subject' => 'DSP', 'day' => 'Tuesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
            ['subject' => 'DSP', 'day' => 'Wednesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
            ['subject' => 'COA', 'day' => 'Thursday', 'time' => '02:40 - 04:40', 'duration' => 2, 'count' => 2],
        ],
        'C' => [
            ['subject' => 'WT', 'day' => 'Monday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
            ['subject' => 'DSP', 'day' => 'Tuesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
            ['subject' => 'WT', 'day' => 'Thursday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
            ['subject' => 'COA', 'day' => 'Friday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
        ],
        'D' => [
            ['subject' => 'WT', 'day' => 'Monday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
            ['subject' => 'DSP', 'day' => 'Tuesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
            ['subject' => 'COA', 'day' => 'Wednesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
            ['subject' => 'WT', 'day' => 'Thursday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
        ],
        'E' => [
            ['subject' => 'DSP', 'day' => 'Monday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
            ['subject' => 'WT', 'day' => 'Tuesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 1],
            ['subject' => 'COA', 'day' => 'Wednesday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
            ['subject' => 'COA', 'day' => 'Thursday', 'time' => '01:40 - 04:40', 'duration' => 3, 'count' => 2],
        ]
    ];

    return isset($labs[$section]) ? $labs[$section] : [];
}

function generateCalendarEvents($section) {
    $timetable = getTimetableForSection($section);
    $events = [];
    $dayMap = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];
    
    $colors = [
        'MQT' => '#4361ee',
        'WT' => '#4cc9f0',
        'OR' => '#f72585',
        'DSP' => '#7209b7',
        'COA' => '#3a0ca3',
        'CD' => '#ff9e00',
        'LAB' => '#06d6a0',
        'MENTOR' => '#118ab2',
        'PROJECT' => '#ffd166',
        'COMMUNITY' => '#ef476f'
    ];
    
    foreach ($timetable as $day => $periods) {
        $dayIndex = $dayMap[$day];
        $date = date('Y-m-d', strtotime("next $day"));
        
        foreach ($periods as $period) {
            $timeParts = explode(' - ', $period['time']);
            $startTime = $timeParts[0];
            $endTime = isset($timeParts[1]) ? $timeParts[1] : date('H:i', strtotime($startTime . ' +1 hour'));
            
            $events[] = [
                'title' => $period['name'] . ($period['type'] === 'LAB' ? ' LAB' : ''),
                'start' => $date . 'T' . str_replace(':', ':', $startTime) . ':00',
                'end' => $date . 'T' . str_replace(':', ':', $endTime) . ':00',
                'color' => $colors[$period['code']] ?? '#4361ee',
                'type' => $period['type']
            ];
        }
    }
    
    return $events;
}

// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
