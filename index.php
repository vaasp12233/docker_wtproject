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
        /* Hero Section Animation */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes glow {
            0%, 100% { box-shadow: 0 0 20px rgba(37, 99, 235, 0.3); }
            50% { box-shadow: 0 0 40px rgba(37, 99, 235, 0.6); }
        }
        
        /* Custom Styles */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 90vh;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            background-position: center;
            opacity: 0.1;
        }
        
        .floating-element {
            animation: float 6s ease-in-out infinite;
        }
        
        .feature-card {
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
            position: relative;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: 0.5s;
        }
        
        .feature-card:hover::before {
            left: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
        }
        
        .stats-counter {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            animation: fadeInUp 0.8s ease-out 0.3s both;
        }
        
        .pulse-btn {
            animation: glow 2s infinite;
            transition: all 0.3s ease;
        }
        
        .pulse-btn:hover {
            transform: scale(1.05);
            animation: none;
        }
        
        .qr-code-animation {
            position: relative;
            width: 300px;
            height: 300px;
            margin: 0 auto;
        }
        
        .qr-grid {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-template-rows: repeat(7, 1fr);
            gap: 4px;
        }
        
        .qr-cell {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 4px;
            animation: pulse 2s infinite;
            animation-delay: calc(var(--cell-index) * 0.1s);
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .testimonial-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .testimonial-card:hover {
            transform: translateX(10px);
        }
        
        .department-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .tech-stack {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .tech-icon {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .tech-icon:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .timeline {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .timeline::after {
            content: '';
            position: absolute;
            width: 6px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -3px;
            border-radius: 10px;
        }
        
        .timeline-item {
            padding: 10px 40px;
            position: relative;
            width: 50%;
            animation: fadeInUp 0.6s ease-out both;
        }
        
        .timeline-item:nth-child(odd) {
            left: 0;
        }
        
        .timeline-item:nth-child(even) {
            left: 50%;
        }
        
        .timeline-content {
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .timeline-item:nth-child(odd) .timeline-content::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            right: -10px;
            top: 50%;
            transform: translateY(-50%) rotate(45deg);
            background: white;
            box-shadow: 2px -2px 5px rgba(0,0,0,0.1);
        }
        
        .timeline-item:nth-child(even) .timeline-content::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            left: -10px;
            top: 50%;
            transform: translateY(-50%) rotate(45deg);
            background: white;
            box-shadow: -2px 2px 5px rgba(0,0,0,0.1);
        }
        
        .timeline-dot {
            width: 20px;
            height: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.5);
        }
        
        .timeline-item:nth-child(odd) .timeline-dot {
            right: -10px;
        }
        
        .timeline-item:nth-child(even) .timeline-dot {
            left: -10px;
        }
        
        /* Responsive Timeline */
        @media screen and (max-width: 768px) {
            .timeline::after {
                left: 31px;
            }
            
            .timeline-item {
                width: 100%;
                padding-left: 70px;
                padding-right: 25px;
            }
            
            .timeline-item:nth-child(even) {
                left: 0;
            }
            
            .timeline-item:nth-child(odd) .timeline-content::after,
            .timeline-item:nth-child(even) .timeline-content::after {
                left: -10px;
                right: auto;
            }
            
            .timeline-dot {
                left: 21px;
            }
        }
        
        .cta-section {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.05)" fill-opacity="1" d="M0,160L48,176C96,192,192,224,288,224C384,224,480,192,576,165.3C672,139,768,117,864,122.7C960,128,1056,160,1152,165.3C1248,171,1344,149,1392,138.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            opacity: 0.1;
        }
        
        .scroll-indicator {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateX(-50%) translateY(0);
            }
            40% {
                transform: translateX(-50%) translateY(-10px);
            }
            60% {
                transform: translateX(-50%) translateY(-5px);
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section text-white d-flex align-items-center">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" style="animation: fadeInUp 0.8s ease-out;">
                    <div class="department-logo">
                        <i class="fas fa-laptop-code fa-2x text-white"></i>
                    </div>
                    <h1 class="display-3 fw-bold mb-4">
                        CSE Smart Attendance
                        <span class="d-block text-warning">System</span>
                    </h1>
                    <p class="lead mb-4" style="opacity: 0.9;">
                        A revolutionary QR-code based attendance management system for 
                        <strong>RGUKT RK Valley's Computer Science Department</strong>. 
                        Streamline attendance tracking with cutting-edge technology.
                    </p>
                    
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <span>Real-time Tracking</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <span>QR Code Technology</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <span>Secure & Reliable</span>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-3">
                        <a href="login.php" class="btn btn-light btn-lg px-4 pulse-btn">
                            <i class="fas fa-sign-in-alt me-2"></i> Login Now
                        </a>
                        <a href="#how-it-works" class="btn btn-outline-light btn-lg px-4">
                            <i class="fas fa-play-circle me-2"></i> How It Works
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="qr-code-animation floating-element">
                        <div class="qr-grid">
                            <?php for($i=0; $i<49; $i++): ?>
                                <div class="qr-cell" style="--cell-index: <?php echo $i; ?>; 
                                    <?php echo rand(0,1) ? 'opacity: 1;' : 'opacity: 0.3;'; ?>"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="text-center mt-4">
                            <h4 class="text-white mb-2"><i class="fas fa-qrcode me-2"></i>Smart QR Scanner</h4>
                            <p class="text-light" style="opacity: 0.8;">Instant attendance with a single scan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="scroll-indicator">
            <a href="#features" class="text-white" style="text-decoration: none;">
                <i class="fas fa-chevron-down fa-2x"></i>
            </a>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 col-6 mb-4">
                    <div class="stats-counter">1500+</div>
                    <p class="text-muted">Students Enrolled</p>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stats-counter">98.7%</div>
                    <p class="text-muted">Accuracy Rate</p>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stats-counter">50+</div>
                    <p class="text-muted">Faculty Users</p>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stats-counter">24/7</div>
                    <p class="text-muted">System Uptime</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">Why Choose Our System?</h2>
                <p class="lead text-muted">Designed specifically for RGUKT CSE Department</p>
            </div>
            
            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card card h-100 shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                 style="width: 70px; height: 70px;">
                                <i class="fas fa-bolt fa-2x text-white"></i>
                            </div>
                            <h4 class="card-title fw-bold">Instant Scanning</h4>
                            <p class="card-text text-muted">
                                Mark attendance in seconds with QR code scanning. No more manual roll calls or paper sheets.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> One-tap attendance</li>
                                <li><i class="fas fa-check text-success me-2"></i> No internet required</li>
                                <li><i class="fas fa-check text-success me-2"></i> Works offline</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 2 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card card h-100 shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-success bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                 style="width: 70px; height: 70px;">
                                <i class="fas fa-chart-line fa-2x text-white"></i>
                            </div>
                            <h4 class="card-title fw-bold">Live Analytics</h4>
                            <p class="card-text text-muted">
                                Real-time attendance analytics with beautiful charts and insights for faculty and administrators.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Real-time reports</li>
                                <li><i class="fas fa-check text-success me-2"></i> Trend analysis</li>
                                <li><i class="fas fa-check text-success me-2"></i> Export to Excel</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 3 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card card h-100 shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-warning bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                 style="width: 70px; height: 70px;">
                                <i class="fas fa-user-shield fa-2x text-white"></i>
                            </div>
                            <h4 class="card-title fw-bold">Advanced Security</h4>
                            <p class="card-text text-muted">
                                Role-based access control, encrypted QR codes, and audit logs ensure complete data security.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Encrypted QR codes</li>
                                <li><i class="fas fa-check text-success me-2"></i> Role-based access</li>
                                <li><i class="fas fa-check text-success me-2"></i> Audit trails</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 4 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card card h-100 shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-info bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                 style="width: 70px; height: 70px;">
                                <i class="fas fa-mobile-alt fa-2x text-white"></i>
                            </div>
                            <h4 class="card-title fw-bold">Mobile Friendly</h4>
                            <p class="card-text text-muted">
                                Fully responsive design that works perfectly on smartphones, tablets, and desktops.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Responsive design</li>
                                <li><i class="fas fa-check text-success me-2"></i> Touch-friendly</li>
                                <li><i class="fas fa-check text-success me-2"></i> Fast loading</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 5 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card card h-100 shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-danger bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                 style="width: 70px; height: 70px;">
                                <i class="fas fa-bell fa-2x text-white"></i>
                            </div>
                            <h4 class="card-title fw-bold">Smart Notifications</h4>
                            <p class="card-text text-muted">
                                Automatic alerts for low attendance, upcoming classes, and important announcements.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Email alerts</li>
                                <li><i class="fas fa-check text-success me-2"></i> In-app notifications</li>
                                <li><i class="fas fa-check text-success me-2"></i> Custom reminders</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 6 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card card h-100 shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-purple bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                 style="width: 70px; height: 70px; background: linear-gradient(135deg, #6f42c1, #e83e8c);">
                                <i class="fas fa-cogs fa-2x text-white"></i>
                            </div>
                            <h4 class="card-title fw-bold">Easy Integration</h4>
                            <p class="card-text text-muted">
                                Seamlessly integrates with existing college systems and supports multiple data formats.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> API support</li>
                                <li><i class="fas fa-check text-success me-2"></i> CSV import/export</li>
                                <li><i class="fas fa-check text-success me-2"></i> Bulk operations</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">How It Works</h2>
                <p class="lead text-muted">Simple three-step process for efficient attendance management</p>
            </div>
            
            <div class="timeline">
                <!-- Step 1 -->
                <div class="timeline-item" style="animation-delay: 0.2s;">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-gradient rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-qrcode fa-lg text-white"></i>
                            </div>
                            <h4 class="mb-0">Step 1: Generate QR</h4>
                        </div>
                        <p class="text-muted mb-0">
                            Faculty generates a unique QR code for each class session. The QR contains encrypted session details 
                            including subject, date, time, and faculty information.
                        </p>
                    </div>
                </div>
                
                <!-- Step 2 -->
                <div class="timeline-item" style="animation-delay: 0.4s;">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-gradient rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-mobile-alt fa-lg text-white"></i>
                            </div>
                            <h4 class="mb-0">Step 2: Scan & Mark</h4>
                        </div>
                        <p class="text-muted mb-0">
                            Students scan the QR code using their smartphone or tablet. The system automatically records their 
                            attendance with timestamp and location verification.
                        </p>
                    </div>
                </div>
                
                <!-- Step 3 -->
                <div class="timeline-item" style="animation-delay: 0.6s;">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning bg-gradient rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-chart-bar fa-lg text-white"></i>
                            </div>
                            <h4 class="mb-0">Step 3: Analyze & Report</h4>
                        </div>
                        <p class="text-muted mb-0">
                            Faculty can view real-time attendance reports, generate analytics, and export data. Students can 
                            track their own attendance percentage and history.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Cards Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">Get Started</h2>
                <p class="lead text-muted">Choose your role to access the system</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="row g-4">
                        <!-- Student Login -->
                        <div class="col-md-6">
                            <div class="login-card shadow-lg border-0">
                                <div class="text-center mb-4">
                                    <div class="bg-primary bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                         style="width: 100px; height: 100px;">
                                        <i class="fas fa-user-graduate fa-3x text-white"></i>
                                    </div>
                                    <h3 class="fw-bold">Student Portal</h3>
                                    <p class="text-muted">Access your attendance dashboard</p>
                                </div>
                                
                                <ul class="list-unstyled mb-4">
                                    <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> View attendance history</li>
                                    <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Check attendance percentage</li>
                                    <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Download QR code</li>
                                    <li class="mb-0"><i class="fas fa-check-circle text-success me-2"></i> View timetable</li>
                                </ul>
                                
                                <div class="d-grid">
                                    <a href="login.php?role=student" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i> Student Login
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Faculty Login -->
                        <div class="col-md-6">
                            <div class="login-card shadow-lg border-0">
                                <div class="text-center mb-4">
                                    <div class="bg-success bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                         style="width: 100px; height: 100px;">
                                        <i class="fas fa-chalkboard-teacher fa-3x text-white"></i>
                                    </div>
                                    <h3 class="fw-bold">Faculty Portal</h3>
                                    <p class="text-muted">Manage attendance & generate reports</p>
                                </div>
                                
                                <ul class="list-unstyled mb-4">
                                    <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Generate QR codes</li>
                                    <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> View attendance reports</li>
                                    <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Export to Excel</li>
                                    <li class="mb-0"><i class="fas fa-check-circle text-success me-2"></i> Manage student data</li>
                                </ul>
                                
                                <div class="d-grid">
                                    <a href="login.php?role=faculty" class="btn btn-success btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i> Faculty Login
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">What Users Say</h2>
                <p class="lead text-muted">Trusted by students and faculty at RGUKT</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card card h-100 border-0 p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-gradient rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-user-graduate text-white"></i>
                            </div>
                            <div>
                                <h5 class="mb-0">Vaseem Khan</h5>
                                <small class="text-muted">CSE Student, E2 Section</small>
                            </div>
                        </div>
                        <p class="mb-0">
                            "The QR attendance system is a game-changer! No more waiting in line to sign attendance sheets. 
                            I can check my attendance percentage anytime."
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="testimonial-card card h-100 border-0 p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-gradient rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-chalkboard-teacher text-white"></i>
                            </div>
                            <div>
                                <h5 class="mb-0">Dr. Smitha Reddy</h5>
                                <small class="text-muted">Assistant Professor, CSE</small>
                            </div>
                        </div>
                        <p class="mb-0">
                            "As a faculty member, I save at least 15 minutes per class. The analytics help me identify 
                            students who need attention immediately."
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="testimonial-card card h-100 border-0 p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning bg-gradient rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-user-tie text-white"></i>
                            </div>
                            <div>
                                <h5 class="mb-0">Prof. Raj Kumar</h5>
                                <small class="text-muted">HOD, Computer Science</small>
                            </div>
                        </div>
                        <p class="mb-0">
                            "This system has revolutionized how we track attendance. The real-time reports help in 
                            making data-driven decisions for student welfare."
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Technology Stack -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">Built With Modern Technology</h2>
                <p class="lead text-muted">Powered by cutting-edge tools and frameworks</p>
            </div>
            
            <div class="tech-stack mb-5">
                <div class="tech-icon">
                    <i class="fab fa-php fa-2x text-primary"></i>
                </div>
                <div class="tech-icon">
                    <i class="fab fa-html5 fa-2x text-danger"></i>
                </div>
                <div class="tech-icon">
                    <i class="fab fa-css3-alt fa-2x text-info"></i>
                </div>
                <div class="tech-icon">
                    <i class="fab fa-js-square fa-2x text-warning"></i>
                </div>
                <div class="tech-icon">
                    <i class="fab fa-bootstrap fa-2x text-purple"></i>
                </div>
                <div class="tech-icon">
                    <i class="fas fa-database fa-2x text-success"></i>
                </div>
            </div>
            
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <h4 class="fw-bold">PHP 8.0+</h4>
                    <p class="text-muted mb-0">Backend Logic</p>
                </div>
                <div class="col-md-3 mb-4">
                    <h4 class="fw-bold">MySQL</h4>
                    <p class="text-muted mb-0">Database</p>
                </div>
                <div class="col-md-3 mb-4">
                    <h4 class="fw-bold">Bootstrap 5</h4>
                    <p class="text-muted mb-0">Responsive UI</p>
                </div>
                <div class="col-md-3 mb-4">
                    <h4 class="fw-bold">Chart.js</h4>
                    <p class="text-muted mb-0">Data Visualization</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta-section py-5 text-white">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h2 class="display-5 fw-bold mb-4">Ready to Transform Attendance Management?</h2>
                    <p class="lead mb-5" style="opacity: 0.9;">
                        Join hundreds of students and faculty who have already streamlined their attendance 
                        process with our smart system.
                    </p>
                    
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="login.php" class="btn btn-light btn-lg px-5 pulse-btn">
                            <i class="fas fa-rocket me-2"></i> Get Started Now
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-5">
                            <i class="fas fa-info-circle me-2"></i> Learn More
                        </a>
                    </div>
                    
                    <div class="mt-5">
                        <p class="mb-2">
                            <i class="fas fa-university me-2"></i>
                            <strong>RGUKT RK Valley - Computer Science Department</strong>
                        </p>
                        <p class="mb-0" style="opacity: 0.8;">
                            Basar, Telangana • Email: cse-attendance@rguktrkv.ac.in • Phone: (08743) 289999
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bootstrap JS -->
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

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, observerOptions);

        // Observe animated elements
        document.querySelectorAll('.feature-card, .timeline-item, .testimonial-card').forEach(el => {
            observer.observe(el);
        });

        // Parallax effect for hero section
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero-section');
            if (hero) {
                hero.style.backgroundPositionY = scrolled * 0.5 + 'px';
            }
        });

        // QR cells animation
        function animateQRCells() {
            const cells = document.querySelectorAll('.qr-cell');
            cells.forEach(cell => {
                cell.style.animationPlayState = 'running';
            });
        }

        // Start QR animation when in view
        const qrObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateQRCells();
                }
            });
        });

        const qrAnimation = document.querySelector('.qr-code-animation');
        if (qrAnimation) {
            qrObserver.observe(qrAnimation);
        }

        // Add current year to footer
        document.addEventListener('DOMContentLoaded', function() {
            const yearSpan = document.getElementById('currentYear');
            if (yearSpan) {
                yearSpan.textContent = new Date().getFullYear();
            }
        });
    </script>
</body>
</html>

<?php include 'footer.php'; ?>
