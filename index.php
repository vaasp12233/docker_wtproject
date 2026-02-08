<?php
$page_title = "CSE Smart Attendance System";
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - RGUKT RK Valley</title>
    <style>
        :root {
            --primary-blue: #1a56db;
            --secondary-blue: #3b82f6;
            --accent-blue: #60a5fa;
            --light-blue: #dbeafe;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --danger-red: #ef4444;
            --gray-100: #f3f4f6;
            --gray-800: #1f2937;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            min-height: 85vh;
            position: relative;
            overflow: hidden;
        }
        
        .hero-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 20%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 20%);
        }
        
        /* QR Scanner Animation */
        .qr-scanner-animation {
            position: relative;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .scanner-beam {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.8),
                rgba(59, 130, 246, 0.8),
                rgba(255, 255, 255, 0.8),
                transparent);
            animation: scan 2s linear infinite;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
        }
        
        @keyframes scan {
            0% { top: 0; }
            100% { top: 100%; }
        }
        
        .qr-corner {
            position: absolute;
            width: 40px;
            height: 40px;
            border: 3px solid white;
        }
        
        .qr-corner.tl {
            top: 20px;
            left: 20px;
            border-right: none;
            border-bottom: none;
        }
        
        .qr-corner.tr {
            top: 20px;
            right: 20px;
            border-left: none;
            border-bottom: none;
        }
        
        .qr-corner.bl {
            bottom: 20px;
            left: 20px;
            border-right: none;
            border-top: none;
        }
        
        /* Feature Cards */
        .feature-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1) !important;
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }
        
        /* Process Timeline */
        .process-timeline {
            position: relative;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .process-timeline::before {
            content: '';
            position: absolute;
            width: 4px;
            background: linear-gradient(180deg, var(--primary-blue), var(--accent-blue));
            top: 0;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }
        
        .process-step {
            position: relative;
            margin-bottom: 50px;
            width: 45%;
        }
        
        .process-step:nth-child(odd) {
            left: 0;
        }
        
        .process-step:nth-child(even) {
            left: 55%;
        }
        
        .step-number {
            position: absolute;
            width: 50px;
            height: 50px;
            background: var(--primary-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            top: 0;
        }
        
        .process-step:nth-child(odd) .step-number {
            right: -75px;
        }
        
        .process-step:nth-child(even) .step-number {
            left: -75px;
        }
        
        /* Dashboard Preview */
        .dashboard-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .dashboard-item {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        
        /* Attendance Meter */
        .attendance-meter {
            width: 200px;
            height: 200px;
            position: relative;
            margin: 0 auto;
        }
        
        .meter-circle {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }
        
        .meter-bg {
            fill: none;
            stroke: var(--gray-100);
            stroke-width: 10;
        }
        
        .meter-progress {
            fill: none;
            stroke: var(--success-green);
            stroke-width: 10;
            stroke-linecap: round;
            stroke-dasharray: 565.48;
            stroke-dashoffset: 141.37; /* 75% */
            transition: stroke-dashoffset 1s ease;
        }
        
        .meter-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        
        /* Login Cards */
        .login-card {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .login-card:hover {
            transform: translateY(-10px);
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            padding: 40px 20px;
            text-align: center;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .process-timeline::before {
                left: 30px;
            }
            
            .process-step {
                width: calc(100% - 80px);
                left: 80px !important;
            }
            
            .step-number {
                left: -65px !important;
                right: auto !important;
            }
            
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Stats Counter */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Button Styles */
        .btn-gradient {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(26, 86, 219, 0.3);
            color: white;
        }
        
        /* Student Dashboard Preview */
        .student-dashboard {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-nav {
            background: var(--gray-100);
            padding: 20px;
            border-bottom: 2px solid var(--light-blue);
        }
        
        .nav-btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            background: white;
            color: var(--primary-blue);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .nav-btn.active {
            background: var(--primary-blue);
            color: white;
        }
        
        .nav-btn:hover:not(.active) {
            background: var(--light-blue);
        }
        
        .qr-display {
            width: 200px;
            height: 200px;
            background: white;
            border: 2px dashed var(--primary-blue);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
        }
        
        .qr-placeholder {
            text-align: center;
            color: var(--primary-blue);
        }
        
        .qr-placeholder i {
            font-size: 50px;
            margin-bottom: 10px;
        }
        
        /* Faculty Panel Preview */
        .faculty-panel {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }
        
        .scan-controls {
            background: var(--gray-100);
            padding: 20px;
            border-bottom: 2px solid var(--light-blue);
        }
        
        .student-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            padding: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .student-card {
            background: white;
            border: 2px solid var(--light-blue);
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .student-card.present {
            border-color: var(--success-green);
            background: rgba(16, 185, 129, 0.1);
        }
        
        .student-card.absent {
            border-color: var(--danger-red);
            background: rgba(239, 68, 68, 0.1);
        }
        
        .student-id {
            font-size: 12px;
            font-weight: 600;
        }
        
        /* Analysis Preview */
        .analysis-panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .export-btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .export-btn.csv {
            background: var(--success-green);
            color: white;
        }
        
        .export-btn.excel {
            background: #217346;
            color: white;
        }
        
        .export-btn.print {
            background: var(--gray-800);
            color: white;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section text-white d-flex align-items-center">
        <div class="hero-pattern"></div>
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="mb-4">
                        <div class="d-inline-flex align-items-center bg-white text-primary px-3 py-1 rounded-pill mb-3">
                            <i class="fas fa-laptop-code me-2"></i>
                            <span class="fw-bold">Computer Science Department</span>
                        </div>
                        <h1 class="display-3 fw-bold mb-4">
                            Smart QR Attendance
                            <span class="d-block">Management System</span>
                        </h1>
                        <p class="lead mb-4">
                            Faculty scans student QR codes for instant attendance marking. 
                            Real-time tracking, comprehensive analytics, and automated reporting 
                            for RGUKT RK Valley CSE Department.
                        </p>
                        
                        <div class="d-flex flex-wrap gap-3 mb-5">
                            <a href="#features" class="btn-gradient">
                                <i class="fas fa-play-circle me-2"></i> How It Works
                            </a>
                            <a href="#login" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i> Login Now
                            </a>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success fa-lg me-3"></i>
                                    <div>
                                        <h5 class="mb-0">Faculty Scans QR</h5>
                                        <small class="opacity-75">Activate session & scan students</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-chart-bar text-warning fa-lg me-3"></i>
                                    <div>
                                        <h5 class="mb-0">360° Analytics</h5>
                                        <small class="opacity-75">Complete batch analysis</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="qr-scanner-animation mx-auto">
                        <div class="scanner-beam"></div>
                        <div class="qr-corner tl"></div>
                        <div class="qr-corner tr"></div>
                        <div class="qr-corner bl"></div>
                        
                        <div class="position-absolute top-50 start-50 translate-middle text-center">
                            <i class="fas fa-qrcode fa-4x text-white mb-3"></i>
                            <h5 class="text-white mb-2">Faculty Scanning Mode</h5>
                            <p class="text-white opacity-75">Real-time student QR scanning</p>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <div class="d-inline-flex align-items-center bg-white text-dark px-3 py-2 rounded-pill">
                            <div class="spinner-grow spinner-grow-sm text-success me-2" role="status"></div>
                            <span>Live Scanning Session Active</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <i class="fas fa-qrcode fa-3x text-primary mb-3"></i>
                        <div class="stat-number">1500+</div>
                        <p class="text-muted mb-0">Active QR Codes</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <i class="fas fa-user-graduate fa-3x text-success mb-3"></i>
                        <div class="stat-number">72</div>
                        <p class="text-muted mb-0">Students per Batch</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <i class="fas fa-chalkboard-teacher fa-3x text-warning mb-3"></i>
                        <div class="stat-number">24</div>
                        <p class="text-muted mb-0">Faculty Members</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <i class="fas fa-percentage fa-3x text-danger mb-3"></i>
                        <div class="stat-number">75%</div>
                        <p class="text-muted mb-0">Minimum Required</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">System Features</h2>
                <p class="lead text-muted">Designed specifically for CSE Department workflow</p>
            </div>
            
            <div class="row g-4">
                <!-- Feature 1: Faculty Scanning -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card shadow-sm p-4">
                        <div class="feature-icon bg-primary bg-gradient text-white mb-4">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Faculty QR Scanning</h4>
                        <p class="text-muted">
                            Faculty activates session and scans student QR codes. Real-time view of 
                            <strong>72 students</strong> showing present/absent status immediately.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-primary me-2">Live Updates</span>
                            <span class="badge bg-success">Instant Marking</span>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 2: Batch Analytics -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card shadow-sm p-4">
                        <div class="feature-icon bg-success bg-gradient text-white mb-4">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h4 class="fw-bold mb-3">360° Batch Analytics</h4>
                        <p class="text-muted">
                            Comprehensive analysis of all 360 students across 5 sections. 
                            Track attendance trends, identify patterns, and monitor compliance.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-success me-2">Trend Analysis</span>
                            <span class="badge bg-info">Pattern Recognition</span>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 3: Bulk Export -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card shadow-sm p-4">
                        <div class="feature-icon bg-warning bg-gradient text-white mb-4">
                            <i class="fas fa-file-export"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Bulk Data Export</h4>
                        <p class="text-muted">
                            Export complete 360 student data in one click. Multiple formats:
                            <strong>CSV, Excel, PDF</strong> with printing capability.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-warning me-2">CSV Export</span>
                            <span class="badge bg-success">Excel Download</span>
                            <span class="badge bg-dark">Print</span>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 4: Student Dashboard -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card shadow-sm p-4">
                        <div class="feature-icon bg-info bg-gradient text-white mb-4">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Student Dashboard</h4>
                        <p class="text-muted">
                            Personalized dashboard with: QR code, attendance analysis, timetable, 
                            and <strong>75% requirement tracking</strong> with session reminders.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-info me-2">Personal QR</span>
                            <span class="badge bg-primary">Attendance Meter</span>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 5: Session Management -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card shadow-sm p-4">
                        <div class="feature-icon bg-danger bg-gradient text-white mb-4">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Session Control</h4>
                        <p class="text-muted">
                            Faculty controls: Start/Stop scanning sessions, view live attendance, 
                            and manage class sessions efficiently.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-danger me-2">Start/Stop</span>
                            <span class="badge bg-success">Live View</span>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 6: Minimum Session Alert -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card shadow-sm p-4">
                        <div class="feature-icon bg-purple bg-gradient text-white mb-4">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h4 class="fw-bold mb-3">75% Session Alert</h4>
                        <p class="text-muted">
                            Smart alerts for students: Shows minimum sessions needed to achieve 
                            <strong>75% attendance</strong> requirement.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-purple me-2">Smart Alerts</span>
                            <span class="badge bg-warning">Requirement Tracking</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">System Workflow</h2>
                <p class="lead text-muted">Simple 3-step process for efficient attendance management</p>
            </div>
            
            <div class="process-timeline">
                <!-- Step 1 -->
                <div class="process-step">
                    <div class="step-number">1</div>
                    <div class="card shadow-sm border-0 p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-gradient rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-play text-white"></i>
                            </div>
                            <h4 class="mb-0">Activate Session</h4>
                        </div>
                        <p class="text-muted mb-0">
                            Faculty logs in and activates scanning session for a specific class. 
                            System generates unique session ID and prepares for QR scanning.
                        </p>
                    </div>
                </div>
                
                <!-- Step 2 -->
                <div class="process-step">
                    <div class="step-number">2</div>
                    <div class="card shadow-sm border-0 p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-gradient rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-camera text-white"></i>
                            </div>
                            <h4 class="mb-0">Scan Student QR Codes</h4>
                        </div>
                        <p class="text-muted mb-0">
                            Faculty scans QR codes from 72 students using mobile or webcam. 
                            Real-time view shows present/absent status for entire batch.
                        </p>
                    </div>
                </div>
                
                <!-- Step 3 -->
                <div class="process-step">
                    <div class="step-number">3</div>
                    <div class="card shadow-sm border-0 p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning bg-gradient rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-chart-bar text-white"></i>
                            </div>
                            <h4 class="mb-0">Analyze & Export</h4>
                        </div>
                        <p class="text-muted mb-0">
                            View 360-student analytics, generate reports, export data to 
                            CSV/Excel, or print. Track 75% requirement compliance.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Preview Sections -->
    <section class="py-5">
        <div class="container">
            <div class="row g-5">
                <!-- Student Dashboard Preview -->
                <div class="col-lg-6">
                    <div class="student-dashboard">
                        <div class="card-header-custom text-white">
                            <h4 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Student Dashboard</h4>
                            <p class="mb-0 opacity-75">Personalized attendance management</p>
                        </div>
                        
                        <div class="dashboard-nav">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="nav-btn active">My QR Code</button>
                                <button class="nav-btn">Attendance Analysis</button>
                                <button class="nav-btn">Timetable</button>
                                <button class="nav-btn">75% Requirement</button>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <!-- QR Display -->
                            <div class="qr-display mb-4">
                                <div class="qr-placeholder">
                                    <i class="fas fa-qrcode"></i>
                                    <div>Personal QR Code</div>
                                    <small class="text-muted">Tap to refresh</small>
                                </div>
                            </div>
                            
                            <!-- Attendance Meter -->
                            <div class="attendance-meter mb-4">
                                <svg class="meter-circle" viewBox="0 0 200 200">
                                    <circle class="meter-bg" cx="100" cy="100" r="90"></circle>
                                    <circle class="meter-progress" cx="100" cy="100" r="90"></circle>
                                </svg>
                                <div class="meter-text">
                                    <div class="h2 fw-bold text-success">75%</div>
                                    <div class="text-muted">Current Attendance</div>
                                </div>
                            </div>
                            
                            <!-- Minimum Sessions -->
                            <div class="alert alert-warning">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                                    <div>
                                        <strong>75% Requirement Alert</strong>
                                        <div class="small">You need to attend <strong>15 more sessions</strong> to reach 75% attendance</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Faculty Panel Preview -->
                <div class="col-lg-6">
                    <div class="faculty-panel">
                        <div class="card-header-custom text-white">
                            <h4 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Faculty Control Panel</h4>
                            <p class="mb-0 opacity-75">Live QR scanning session</p>
                        </div>
                        
                        <div class="scan-controls">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1">E2 Section (72 Students)</h5>
                                    <small class="text-muted">Session ID: CSE2024-11-01-001</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm">
                                        <i class="fas fa-play me-1"></i> Start
                                    </button>
                                    <button class="btn btn-danger btn-sm">
                                        <i class="fas fa-stop me-1"></i> Stop
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-3">
                            <h6 class="mb-3">Student Status (Live View)</h6>
                            <div class="student-grid">
                                <?php for($i=1; $i<=72; $i++): ?>
                                    <div class="student-card <?php echo rand(0,1) ? 'present' : 'absent'; ?>">
                                        <div class="student-id"><?php echo sprintf('S%03d', $i); ?></div>
                                        <small><?php echo rand(0,1) ? '✓' : '✗'; ?></small>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <div class="mt-3 text-center">
                                <div class="d-inline-flex align-items-center bg-light px-3 py-1 rounded-pill">
                                    <span class="me-3">Present: <strong>48</strong></span>
                                    <span>Absent: <strong>24</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Analysis Panel -->
            <div class="analysis-panel mt-5">
                <div class="text-center mb-4">
                    <h3 class="fw-bold mb-2">360 Student Analysis Panel</h3>
                    <p class="text-muted">Complete batch data export and reporting</p>
                </div>
                
                <div class="export-buttons mb-4">
                    <button class="export-btn csv">
                        <i class="fas fa-file-csv"></i> Export as CSV
                    </button>
                    <button class="export-btn excel">
                        <i class="fas fa-file-excel"></i> Download Excel
                    </button>
                    <button class="export-btn print">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
                
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <div class="h2 fw-bold text-primary">360</div>
                        <div class="text-muted">Total Students</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="h2 fw-bold text-success">288</div>
                        <div class="text-muted">Above 75%</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="h2 fw-bold text-warning">45</div>
                        <div class="text-muted">Below 75%</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="h2 fw-bold text-danger">27</div>
                        <div class="text-muted">Critical (< 50%)</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Section -->
    <section id="login" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">Access Your Portal</h2>
                <p class="lead text-muted">Choose your role to login</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="row g-4">
                        <!-- Student Login -->
                        <div class="col-md-6">
                            <div class="login-card shadow-lg">
                                <div class="card-header-custom text-white">
                                    <i class="fas fa-user-graduate fa-3x mb-3"></i>
                                    <h3 class="mb-2">Student Portal</h3>
                                    <p class="mb-0 opacity-75">Access your attendance dashboard</p>
                                </div>
                                <div class="card-body p-4">
                                    <ul class="list-unstyled mb-4">
                                        <li class="mb-3">
                                            <i class="fas fa-qrcode text-primary me-2"></i>
                                            <strong>Personal QR Code</strong> - Show for attendance
                                        </li>
                                        <li class="mb-3">
                                            <i class="fas fa-chart-line text-success me-2"></i>
                                            <strong>Attendance Analysis</strong> - View your progress
                                        </li>
                                        <li class="mb-3">
                                            <i class="fas fa-calendar-alt text-warning me-2"></i>
                                            <strong>Timetable Access</strong> - View class schedule
                                        </li>
                                        <li class="mb-0">
                                            <i class="fas fa-bell text-danger me-2"></i>
                                            <strong>75% Requirement Alert</strong> - Minimum sessions needed
                                        </li>
                                    </ul>
                                    <div class="d-grid">
                                        <a href="login.php?role=student" class="btn-gradient">
                                            <i class="fas fa-sign-in-alt me-2"></i> Student Login
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Faculty Login -->
                        <div class="col-md-6">
                            <div class="login-card shadow-lg">
                                <div class="card-header-custom text-white">
                                    <i class="fas fa-chalkboard-teacher fa-3x mb-3"></i>
                                    <h3 class="mb-2">Faculty Portal</h3>
                                    <p class="mb-0 opacity-75">Manage attendance & analytics</p>
                                </div>
                                <div class="card-body p-4">
                                    <ul class="list-unstyled mb-4">
                                        <li class="mb-3">
                                            <i class="fas fa-camera text-primary me-2"></i>
                                            <strong>QR Code Scanner</strong> - Scan student QR codes
                                        </li>
                                        <li class="mb-3">
                                            <i class="fas fa-users text-success me-2"></i>
                                            <strong>Batch Analytics</strong> - 360 student analysis
                                        </li>
                                        <li class="mb-3">
                                            <i class="fas fa-file-export text-warning me-2"></i>
                                            <strong>Bulk Export</strong> - CSV, Excel, Print
                                        </li>
                                        <li class="mb-0">
                                            <i class="fas fa-chart-bar text-info me-2"></i>
                                            <strong>Live Reports</strong> - Real-time attendance view
                                        </li>
                                    </ul>
                                    <div class="d-grid">
                                        <a href="login.php?role=faculty" class="btn-gradient">
                                            <i class="fas fa-sign-in-alt me-2"></i> Faculty Login
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <section class="cta-section py-5 text-white">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h2 class="display-5 fw-bold mb-4">Transform Your Attendance Management</h2>
                    <p class="lead mb-5" style="opacity: 0.9;">
                        Join the CSE Department in embracing smart, efficient, and accurate 
                        attendance tracking with our QR-based system.
                    </p>
                    
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="login.php" class="btn btn-light btn-lg px-5">
                            <i class="fas fa-rocket me-2"></i> Get Started Now
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-5">
                            <i class="fas fa-info-circle me-2"></i> Learn More
                        </a>
                    </div>
                    
                    <div class="mt-5">
                        <p class="mb-2">
                            <i class="fas fa-university me-2"></i>
                            <strong>RGUKT RK Valley - Computer Science & Engineering</strong>
                        </p>
                        <p class="mb-0" style="opacity: 0.8;">
                            Idupulapaya, Andhra Pradesh • 5 Sections • 360 Students • 24 Faculty
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Faculty panel demo
        const studentCards = document.querySelectorAll('.student-card');
        setInterval(() => {
            studentCards.forEach(card => {
                if (Math.random() > 0.3) {
                    card.classList.remove('absent');
                    card.classList.add('present');
                    card.querySelector('small').textContent = '✓';
                }
            });
        }, 3000);

        // Attendance meter animation
        const meterProgress = document.querySelector('.meter-progress');
        if (meterProgress) {
            setTimeout(() => {
                meterProgress.style.strokeDashoffset = '141.37'; // 75%
            }, 500);
        }

        // Export button animations
        const exportBtns = document.querySelectorAll('.export-btn');
        exportBtns.forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.05)';
            });
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Demo student status update
        function updateStudentStatus() {
            const presentCount = Math.floor(Math.random() * 20) + 50;
            const absentCount = 72 - presentCount;
            
            const presentSpan = document.querySelector('.present-count');
            const absentSpan = document.querySelector('.absent-count');
            
            if (presentSpan && absentSpan) {
                presentSpan.textContent = presentCount;
                absentSpan.textContent = absentCount;
            }
        }

        // Update every 5 seconds
        setInterval(updateStudentStatus, 5000);

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateStudentStatus();
            
            // Add current year to footer
            const yearSpan = document.getElementById('currentYear');
            if (yearSpan) {
                yearSpan.textContent = new Date().getFullYear();
            }
        });
    </script>
</body>
</html>

<?php include 'footer.php'; ?>
