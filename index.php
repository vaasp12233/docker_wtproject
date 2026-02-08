<?php
$page_title = "CSE Smart Attendance System";
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - RGUKT RK Valley</title>
    
    <style>
        :root {
            /* Light Theme */
            --primary-blue: #1a56db;
            --secondary-blue: #3b82f6;
            --accent-blue: #60a5fa;
            --light-blue: #dbeafe;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --danger-red: #ef4444;
            --purple: #8b5cf6;
            
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
        }

        [data-bs-theme="dark"] {
            /* Dark Theme */
            --primary-blue: #3b82f6;
            --secondary-blue: #60a5fa;
            --accent-blue: #93c5fd;
            --light-blue: #1e3a8a;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --danger-red: #ef4444;
            --purple: #8b5cf6;
            
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.3);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.3);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.3);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.3);
        }

        /* Base Styles */
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            transition: all 0.3s ease;
        }

        /* Theme Toggle */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .theme-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .theme-btn:hover {
            transform: rotate(15deg);
            box-shadow: var(--shadow-lg);
        }

        /* Hero Section */
        .hero-section {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 10px 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            animation: fadeInUp 0.6s ease;
        }

        .hero-title {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
            animation: fadeInUp 0.6s ease 0.2s both;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            margin-bottom: 30px;
            max-width: 600px;
            animation: fadeInUp 0.6s ease 0.4s both;
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 40px;
            animation: fadeInUp 0.6s ease 0.6s both;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 20px;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* QR Scanner Animation */
        .scanner-container {
            width: 100%;
            max-width: 400px;
            aspect-ratio: 1;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            position: relative;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.2);
            animation: float 6s ease-in-out infinite;
        }

        .scanner-frame {
            position: absolute;
            width: 70%;
            height: 70%;
            top: 15%;
            left: 15%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
        }

        .scanner-beam {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.8),
                #60a5fa,
                rgba(255, 255, 255, 0.8),
                transparent);
            animation: scan 2s linear infinite;
            box-shadow: 0 0 20px rgba(96, 165, 250, 0.5);
        }

        @keyframes scan {
            0% { top: 0; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
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

        /* Stats Section */
        .stats-section {
            padding: 100px 0;
            background: var(--bg-secondary);
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
        }

        .stat-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
        }

        .stat-value {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Features Section */
        .features-section {
            padding: 100px 0;
        }

        .section-title {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            text-align: center;
            margin-bottom: 60px;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 2px;
        }

        .feature-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 40px 30px;
            height: 100%;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--primary-blue), transparent);
            transition: left 0.5s ease;
        }

        .feature-card:hover::before {
            left: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-blue);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            font-size: 32px;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .feature-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 20px;
        }

        .feature-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Process Timeline */
        .process-section {
            padding: 100px 0;
            background: var(--bg-secondary);
        }

        .timeline {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, 
                var(--primary-blue), 
                var(--secondary-blue),
                var(--accent-blue));
            transform: translateX(-50%);
            border-radius: 2px;
        }

        .timeline-item {
            display: flex;
            margin-bottom: 60px;
            position: relative;
        }

        .timeline-item:nth-child(odd) {
            flex-direction: row;
        }

        .timeline-item:nth-child(even) {
            flex-direction: row-reverse;
        }

        .timeline-content {
            flex: 1;
            padding: 0 40px;
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            box-shadow: var(--shadow-lg);
        }

        .process-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .process-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-blue);
        }

        /* Preview Sections */
        .preview-section {
            padding: 100px 0;
        }

        .preview-card {
            background: var(--bg-card);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .preview-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
        }

        .preview-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            padding: 30px;
            color: white;
        }

        .preview-body {
            padding: 30px;
        }

        .student-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .student-dot {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .student-dot.present {
            background: rgba(16, 185, 129, 0.15);
            border: 2px solid var(--success-green);
            color: var(--success-green);
        }

        .student-dot.absent {
            background: rgba(239, 68, 68, 0.15);
            border: 2px solid var(--danger-red);
            color: var(--danger-red);
        }

        .attendance-chart {
            width: 200px;
            height: 200px;
            margin: 0 auto 20px;
        }

        /* Export Panel */
        .export-panel {
            background: var(--bg-card);
            border-radius: 24px;
            padding: 40px;
            border: 1px solid var(--border-color);
            margin-top: 40px;
        }

        .export-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .export-btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
        }

        .export-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        /* Login Cards */
        .login-section {
            padding: 100px 0;
            background: var(--bg-secondary);
        }

        .login-card {
            background: var(--bg-card);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            height: 100%;
        }

        .login-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .login-body {
            padding: 40px 30px;
        }

        .login-features {
            list-style: none;
            padding: 0;
            margin: 0 0 30px 0;
        }

        .login-features li {
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .login-features li:last-child {
            border-bottom: none;
        }

        .login-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            color: white;
            text-decoration: none;
        }

        /* Footer CTA */
        .footer-cta {
            padding: 100px 0;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .footer-cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 10% 20%, rgba(255,255,255,0.1) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(255,255,255,0.1) 0%, transparent 40%);
        }

        .cta-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .cta-title {
            font-size: clamp(2rem, 4vw, 3.5rem);
            font-weight: 800;
            margin-bottom: 20px;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-stats {
                grid-template-columns: 1fr;
            }

            .timeline::before {
                left: 30px;
            }

            .timeline-item {
                flex-direction: row !important;
            }

            .timeline-content {
                padding-left: 80px;
                padding-right: 0;
            }

            .step-number {
                left: 30px;
                transform: none;
            }

            .student-grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .export-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .export-btn {
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .stat-card {
                padding: 30px 20px;
            }

            .feature-card {
                padding: 30px 20px;
            }

            .preview-body {
                padding: 20px;
            }

            .login-body {
                padding: 30px 20px;
            }

            .cta-buttons {
                flex-direction: column;
            }
        }

        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .btn-outline-light {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: white;
            color: white;
            transform: translateY(-3px);
        }

        /* Scroll Animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <!-- Theme Toggle -->
    <div class="theme-toggle">
        <button class="theme-btn" id="themeToggle">
            <i class="fas fa-moon"></i>
        </button>
    </div>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-bg"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <div class="hero-badge animate-on-scroll">
                        <i class="fas fa-laptop-code"></i>
                        <span>Computer Science Department</span>
                    </div>
                    
                    <h1 class="hero-title">
                        Smart QR Attendance
                        <span class="d-block">Management System</span>
                    </h1>
                    
                    <p class="hero-subtitle">
                        Faculty scans student QR codes for instant attendance marking. 
                        Real-time tracking, comprehensive analytics, and automated reporting 
                        for RGUKT RK Valley CSE Department.
                    </p>
                    
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="#features" class="btn-primary">
                            <i class="fas fa-play-circle me-2"></i> How It Works
                        </a>
                        <a href="login.php" class="btn-outline-light">
                            <i class="fas fa-sign-in-alt me-2"></i> Login Now
                        </a>
                    </div>
                    
                    <div class="hero-stats">
                        <div class="stat-item">
                            <div class="stat-number">72</div>
                            <div class="stat-label">Students per Class</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">360</div>
                            <div class="stat-label">Students per Batch</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mt-5 mt-lg-0">
                    <div class="scanner-container mx-auto">
                        <div class="scanner-frame"></div>
                        <div class="scanner-beam"></div>
                        <div class="position-absolute top-50 start-50 translate-middle text-center">
                            <i class="fas fa-qrcode fa-4x text-white mb-3"></i>
                            <h5 class="text-white mb-2">Faculty Scanning Mode</h5>
                            <p class="text-white opacity-75">Real-time student QR scanning</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-icon" style="background: rgba(26, 86, 219, 0.1); color: var(--primary-blue);">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <div class="stat-value">360+</div>
                        <p class="text-muted mb-0">Active QR Codes</p>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success-green);">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-value">360</div>
                        <p class="text-muted mb-0">Total Students</p>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning-orange);">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-value">16</div>
                        <p class="text-muted mb-0">Faculty Members</p>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger-red);">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-value">75%</div>
                        <p class="text-muted mb-0">Minimum Required</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <h2 class="section-title animate-on-scroll">System Features</h2>
            <p class="text-center text-secondary mb-5 animate-on-scroll">Designed specifically for CSE Department workflow</p>
            
            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon" style="background: rgba(26, 86, 219, 0.1); color: var(--primary-blue);">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h3 class="feature-title">Faculty QR Scanning</h3>
                        <p class="text-secondary">
                            Faculty activates session and scans student QR codes. Real-time view of 
                            <strong>72 students</strong> showing present/absent status immediately.
                        </p>
                        <div class="feature-badges">
                            <span class="feature-badge" style="background: rgba(26, 86, 219, 0.1); color: var(--primary-blue);">
                                Live Updates
                            </span>
                            <span class="feature-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success-green);">
                                Instant Marking
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 2 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success-green);">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h3 class="feature-title">360° Batch Analytics</h3>
                        <p class="text-secondary">
                            Comprehensive analysis of all 360 students across 5 sections. 
                            Track attendance trends, identify patterns, and monitor compliance.
                        </p>
                        <div class="feature-badges">
                            <span class="feature-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success-green);">
                                Trend Analysis
                            </span>
                            <span class="feature-badge" style="background: rgba(59, 130, 246, 0.1); color: var(--secondary-blue);">
                                Pattern Recognition
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 3 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning-orange);">
                            <i class="fas fa-file-export"></i>
                        </div>
                        <h3 class="feature-title">Bulk Data Export</h3>
                        <p class="text-secondary">
                            Export complete 360 student data in one click. Multiple formats:
                            <strong>CSV, Excel, PDF</strong> with printing capability.
                        </p>
                        <div class="feature-badges">
                            <span class="feature-badge" style="background: rgba(245, 158, 11, 0.1); color: var(--warning-orange);">
                                CSV Export
                            </span>
                            <span class="feature-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success-green);">
                                Excel Download
                            </span>
                            <span class="feature-badge" style="background: rgba(30, 41, 59, 0.1); color: var(--text-primary);">
                                Print
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 4 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--secondary-blue);">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <h3 class="feature-title">Student Dashboard</h3>
                        <p class="text-secondary">
                            Personalized dashboard with: QR code, attendance analysis, timetable, 
                            and <strong>75% requirement tracking</strong> with session reminders.
                        </p>
                        <div class="feature-badges">
                            <span class="feature-badge" style="background: rgba(59, 130, 246, 0.1); color: var(--secondary-blue);">
                                Personal QR
                            </span>
                            <span class="feature-badge" style="background: rgba(26, 86, 219, 0.1); color: var(--primary-blue);">
                                Attendance Meter
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 5 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger-red);">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <h3 class="feature-title">Session Control</h3>
                        <p class="text-secondary">
                            Faculty controls: Start/Stop scanning sessions, view live attendance, 
                            and manage class sessions efficiently.
                        </p>
                        <div class="feature-badges">
                            <span class="feature-badge" style="background: rgba(239, 68, 68, 0.1); color: var(--danger-red);">
                                Start/Stop
                            </span>
                            <span class="feature-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success-green);">
                                Live View
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 6 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon" style="background: rgba(139, 92, 246, 0.1); color: var(--purple);">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3 class="feature-title">75% Session Alert</h3>
                        <p class="text-secondary">
                            Smart alerts for students: Shows minimum sessions needed to achieve 
                            <strong>75% attendance</strong> requirement.
                        </p>
                        <div class="feature-badges">
                            <span class="feature-badge" style="background: rgba(139, 92, 246, 0.1); color: var(--purple);">
                                Smart Alerts
                            </span>
                            <span class="feature-badge" style="background: rgba(245, 158, 11, 0.1); color: var(--warning-orange);">
                                Requirement Tracking
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Process Section -->
    <section class="process-section">
        <div class="container">
            <h2 class="section-title animate-on-scroll">System Workflow</h2>
            <p class="text-center text-secondary mb-5 animate-on-scroll">Simple 3-step process for efficient attendance management</p>
            
            <div class="timeline">
                <!-- Step 1 -->
                <div class="timeline-item animate-on-scroll">
                    <div class="step-number">1</div>
                    <div class="timeline-content">
                        <div class="process-card">
                            <div class="d-flex align-items-center mb-3">
                                <div class="feature-icon me-3" style="background: rgba(26, 86, 219, 0.1); color: var(--primary-blue);">
                                    <i class="fas fa-play"></i>
                                </div>
                                <h4 class="mb-0">Activate Session</h4>
                            </div>
                            <p class="text-secondary mb-0">
                                Faculty logs in and activates scanning session for a specific class. 
                                System generates unique session ID and prepares for QR scanning.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2 -->
                <div class="timeline-item animate-on-scroll">
                    <div class="step-number">2</div>
                    <div class="timeline-content">
                        <div class="process-card">
                            <div class="d-flex align-items-center mb-3">
                                <div class="feature-icon me-3" style="background: rgba(16, 185, 129, 0.1); color: var(--success-green);">
                                    <i class="fas fa-camera"></i>
                                </div>
                                <h4 class="mb-0">Scan Student QR Codes</h4>
                            </div>
                            <p class="text-secondary mb-0">
                                Faculty scans QR codes from 72 students using mobile or webcam. 
                                Real-time view shows present/absent status for entire batch.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3 -->
                <div class="timeline-item animate-on-scroll">
                    <div class="step-number">3</div>
                    <div class="timeline-content">
                        <div class="process-card">
                            <div class="d-flex align-items-center mb-3">
                                <div class="feature-icon me-3" style="background: rgba(245, 158, 11, 0.1); color: var(--warning-orange);">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <h4 class="mb-0">Analyze & Export</h4>
                            </div>
                            <p class="text-secondary mb-0">
                                View 360-student analytics, generate reports, export data to 
                                CSV/Excel, or print. Track 75% requirement compliance.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Preview Section -->
    <section class="preview-section">
        <div class="container">
            <div class="row g-5">
                <!-- Student Dashboard Preview -->
                <div class="col-lg-6">
                    <div class="preview-card animate-on-scroll">
                        <div class="preview-header">
                            <h4 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Student Dashboard</h4>
                            <p class="mb-0 opacity-75">Personalized attendance management</p>
                        </div>
                        
                        <div class="preview-body">
                            <!-- QR Display -->
                            <div class="text-center mb-4">
                                <div style="width: 200px; height: 200px; background: rgba(59, 130, 246, 0.1); 
                                            border: 2px dashed var(--secondary-blue); border-radius: 12px;
                                            display: flex; align-items: center; justify-content: center;
                                            margin: 0 auto;">
                                    <div class="text-center">
                                        <i class="fas fa-qrcode fa-3x" style="color: var(--secondary-blue);"></i>
                                        <div class="mt-2" style="color: var(--text-primary);">Personal QR Code</div>
                                        <small class="text-secondary">Tap to refresh</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Attendance Chart -->
                            <div class="attendance-chart">
                                <svg viewBox="0 0 200 200">
                                    <circle cx="100" cy="100" r="90" fill="none" stroke="var(--border-color)" stroke-width="10"/>
                                    <circle cx="100" cy="100" r="90" fill="none" stroke="var(--success-green)" 
                                            stroke-width="10" stroke-linecap="round"
                                            stroke-dasharray="565.48" stroke-dashoffset="141.37"/>
                                    <text x="100" y="100" text-anchor="middle" dy="8" 
                                          style="font-size: 32px; font-weight: bold; fill: var(--success-green);">75%</text>
                                    <text x="100" y="130" text-anchor="middle" 
                                          style="font-size: 14px; fill: var(--text-secondary);">Attendance</text>
                                </svg>
                            </div>
                            
                            <!-- Alert -->
                            <div style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid var(--warning-orange);
                                        padding: 15px; border-radius: 8px; margin-top: 20px;">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle me-3" style="color: var(--warning-orange);"></i>
                                    <div>
                                        <strong style="color: var(--text-primary);">75% Requirement Alert</strong>
                                        <div class="text-secondary small">You need to attend <strong>15 more sessions</strong> to reach 75% attendance</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Faculty Panel Preview -->
                <div class="col-lg-6">
                    <div class="preview-card animate-on-scroll">
                        <div class="preview-header">
                            <h4 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Faculty Control Panel</h4>
                            <p class="mb-0 opacity-75">Live QR scanning session</p>
                        </div>
                        
                        <div class="preview-body">
                            <div class="mb-4">
                                <h5 style="color: var(--text-primary);">E2 Section (72 Students)</h5>
                                <small class="text-secondary">Session ID: CSE2024-11-01-001</small>
                            </div>
                            
                            <!-- Student Grid -->
                            <h6 class="mb-3" style="color: var(--text-primary);">Student Status (Live View)</h6>
                            <div class="student-grid">
                                <?php for($i=1; $i<=72; $i++): ?>
                                    <div class="student-dot <?php echo rand(0,1) ? 'present' : 'absent'; ?>">
                                        S<?php echo sprintf('%02d', $i); ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <div class="text-center mt-3">
                                <div style="display: inline-flex; align-items: center; background: var(--bg-secondary); 
                                            padding: 8px 20px; border-radius: 20px; gap: 20px;">
                                    <span style="color: var(--text-primary);">
                                        <i class="fas fa-check-circle me-1" style="color: var(--success-green);"></i>
                                        Present: <strong id="presentCount">48</strong>
                                    </span>
                                    <span style="color: var(--text-primary);">
                                        <i class="fas fa-times-circle me-1" style="color: var(--danger-red);"></i>
                                        Absent: <strong id="absentCount">24</strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Export Panel -->
            <div class="export-panel animate-on-scroll">
                <div class="text-center mb-4">
                    <h3 style="color: var(--text-primary);">360 Student Analysis Panel</h3>
                    <p class="text-secondary">Complete batch data export and reporting</p>
                </div>
                
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <div class="stat-value">360</div>
                        <div class="text-secondary">Total Students</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-value" style="background: linear-gradient(135deg, var(--success-green), #34d399); 
                                                       -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            288
                        </div>
                        <div class="text-secondary">Above 75%</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-value" style="background: linear-gradient(135deg, var(--warning-orange), #fbbf24); 
                                                       -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            45
                        </div>
                        <div class="text-secondary">Below 75%</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-value" style="background: linear-gradient(135deg, var(--danger-red), #f87171); 
                                                       -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            27
                        </div>
                        <div class="text-secondary">Critical (< 50%)</div>
                    </div>
                </div>
                
                <div class="export-buttons">
                    <button class="export-btn" style="background: var(--success-green); color: white;">
                        <i class="fas fa-file-csv"></i> Export as CSV
                    </button>
                    <button class="export-btn" style="background: #217346; color: white;">
                        <i class="fas fa-file-excel"></i> Download Excel
                    </button>
                    <button class="export-btn" style="background: var(--gray-800); color: white;">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Section -->
    <section id="login" class="login-section">
        <div class="container">
            <h2 class="section-title animate-on-scroll">Access Your Portal</h2>
            <p class="text-center text-secondary mb-5 animate-on-scroll">Choose your role to login</p>
            
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="row g-4">
                        <!-- Student Login -->
                        <div class="col-md-6">
                            <div class="login-card animate-on-scroll">
                                <div class="login-header">
                                    <i class="fas fa-user-graduate fa-3x mb-3"></i>
                                    <h3 class="mb-2">Student Portal</h3>
                                    <p class="mb-0 opacity-75">Access your attendance dashboard</p>
                                </div>
                                
                                <div class="login-body">
                                    <ul class="login-features">
                                        <li>
                                            <i class="fas fa-qrcode" style="color: var(--primary-blue);"></i>
                                            <span style="color: var(--text-primary);">Personal QR Code</span>
                                        </li>
                                        <li>
                                            <i class="fas fa-chart-line" style="color: var(--success-green);"></i>
                                            <span style="color: var(--text-primary);">Attendance Analysis</span>
                                        </li>
                                        <li>
                                            <i class="fas fa-calendar-alt" style="color: var(--warning-orange);"></i>
                                            <span style="color: var(--text-primary);">Timetable Access</span>
                                        </li>
                                        <li>
                                            <i class="fas fa-bell" style="color: var(--danger-red);"></i>
                                            <span style="color: var(--text-primary);">75% Requirement Alert</span>
                                        </li>
                                    </ul>
                                    
                                    <a href="login.php?role=student" class="login-btn">
                                        <i class="fas fa-sign-in-alt me-2"></i> Student Login
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Faculty Login -->
                        <div class="col-md-6">
                            <div class="login-card animate-on-scroll">
                                <div class="login-header">
                                    <i class="fas fa-chalkboard-teacher fa-3x mb-3"></i>
                                    <h3 class="mb-2">Faculty Portal</h3>
                                    <p class="mb-0 opacity-75">Manage attendance & analytics</p>
                                </div>
                                
                                <div class="login-body">
                                    <ul class="login-features">
                                        <li>
                                            <i class="fas fa-camera" style="color: var(--primary-blue);"></i>
                                            <span style="color: var(--text-primary);">QR Code Scanner</span>
                                        </li>
                                        <li>
                                            <i class="fas fa-users" style="color: var(--success-green);"></i>
                                            <span style="color: var(--text-primary);">360 Student Analysis</span>
                                        </li>
                                        <li>
                                            <i class="fas fa-file-export" style="color: var(--warning-orange);"></i>
                                            <span style="color: var(--text-primary);">Bulk Export</span>
                                        </li>
                                        <li>
                                            <i class="fas fa-chart-bar" style="color: var(--secondary-blue);"></i>
                                            <span style="color: var(--text-primary);">Live Reports</span>
                                        </li>
                                    </ul>
                                    
                                    <a href="login.php?role=faculty" class="login-btn">
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

    <!-- Footer CTA -->
    <section class="footer-cta">
        <div class="container">
            <div class="cta-content">
                <h2 class="cta-title animate-on-scroll">Transform Your Attendance Management</h2>
                <p class="lead mb-4 animate-on-scroll" style="max-width: 700px; margin: 0 auto;">
                    Join the CSE Department in embracing smart, efficient, and accurate 
                    attendance tracking with our QR-based system.
                </p>
                
                <div class="cta-buttons animate-on-scroll">
                    <a href="login.php" class="btn-primary" style="background: white; color: #1e3a8a;">
                        <i class="fas fa-rocket me-2"></i> Get Started Now
                    </a>
                    <a href="#features" class="btn-outline-light">
                        <i class="fas fa-info-circle me-2"></i> Learn More
                    </a>
                </div>
                
                <div class="mt-5 animate-on-scroll">
                    <p class="mb-2">
                        <i class="fas fa-university me-2"></i>
                        <strong>RGUKT RK Valley - Computer Science & Engineering</strong>
                    </p>
                    <p class="mb-0" style="opacity: 0.8;">
                        Idupulapaya, Andhra Pradesh • 5 Sections • 360 Students • 16 Faculty
                    </p>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        
        // Check for saved theme or prefer-color-scheme
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            html.setAttribute('data-bs-theme', 'dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        
        themeToggle.addEventListener('click', () => {
            if (html.getAttribute('data-bs-theme') === 'dark') {
                html.setAttribute('data-bs-theme', 'light');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                localStorage.setItem('theme', 'light');
            } else {
                html.setAttribute('data-bs-theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                localStorage.setItem('theme', 'dark');
            }
        });

        // Scroll Animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.animate-on-scroll').forEach(el => {
            observer.observe(el);
        });

        // Update student status demo
        function updateStudentStatus() {
            const presentCount = Math.floor(Math.random() * 20) + 52; // 52-72
            const absentCount = 72 - presentCount;
            
            document.getElementById('presentCount').textContent = presentCount;
            document.getElementById('absentCount').textContent = absentCount;
            
            // Update student dots
            const studentDots = document.querySelectorAll('.student-dot');
            studentDots.forEach((dot, index) => {
                const isPresent = index < presentCount;
                dot.classList.remove('present', 'absent');
                dot.classList.add(isPresent ? 'present' : 'absent');
            });
        }

        // Update every 3 seconds
        setInterval(updateStudentStatus, 3000);
        updateStudentStatus(); // Initial update

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
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

        // Add hover effects to interactive elements
        document.querySelectorAll('.feature-card, .stat-card, .login-card, .preview-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transition = 'all 0.3s ease';
            });
        });

        // Initialize animations on load
        document.addEventListener('DOMContentLoaded', () => {
            // Trigger initial animations
            setTimeout(() => {
                document.querySelectorAll('.animate-on-scroll').forEach((el, index) => {
                    setTimeout(() => {
                        if (el.getBoundingClientRect().top < window.innerHeight * 0.8) {
                            el.classList.add('visible');
                        }
                    }, index * 100);
                });
            }, 500);
            
            // Add current year if needed
            const yearSpan = document.getElementById('currentYear');
            if (yearSpan) {
                yearSpan.textContent = new Date().getFullYear();
            }
        });
    </script>
</body>
</html>

<?php include 'footer.php'; ?>
