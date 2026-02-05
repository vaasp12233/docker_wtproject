<?php
$page_title = "Home";
include 'header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card shadow-lg border-0">
            <div class="card-body p-5">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="display-4 fw-bold text-primary">CSE Attendance System</h1>
                        <p class="lead text-muted">
                            A modern, efficient attendance management system for Computer Science Department.
                            Track attendance using QR codes with real-time monitoring.
                        </p>
                        <div class="mt-4">
                            <a href="login.php" class="btn btn-primary btn-lg px-4 me-2">
                                <i class="fas fa-sign-in-alt me-2"></i> Login
                            </a>
                            <a href="#features" class="btn btn-outline-primary btn-lg px-4">
                                <i class="fas fa-info-circle me-2"></i> Learn More
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6 text-center">
                      <svg width="100%" height="400" viewBox="0 0 800 400" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; max-height: 400px;">
    <!-- Dashboard Background -->
    <rect width="800" height="400" fill="white" rx="10"/>
    
    <!-- Dashboard Header -->
    <rect x="20" y="20" width="760" height="60" rx="5" fill="#1565c0"/>
    <text x="50" y="55" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white">CSE Attendance Analytics Dashboard</text>
    
    <!-- Chart Grid -->
    <rect x="30" y="100" width="350" height="250" rx="5" fill="white" stroke="#e0e0e0" stroke-width="1"/>
    <rect x="420" y="100" width="350" height="250" rx="5" fill="white" stroke="#e0e0e0" stroke-width="1"/>
    
    <!-- Bar Chart (Attendance %) -->
    <text x="50" y="140" font-family="Arial" font-size="16" font-weight="600" fill="#333">Monthly Attendance %</text>
    
    <!-- Bars -->
    <rect x="80" y="180" width="40" height="120" fill="#4CAF50" rx="3"/>
    <text x="90" y="175" text-anchor="middle" font-family="Arial" font-size="12" fill="#666">85%</text>
    
    <rect x="140" y="200" width="40" height="100" fill="#2196F3" rx="3"/>
    <text x="160" y="195" text-anchor="middle" font-family="Arial" font-size="12" fill="#666">78%</text>
    
    <rect x="200" y="160" width="40" height="140" fill="#FF9800" rx="3"/>
    <text x="220" y="155" text-anchor="middle" font-family="Arial" font-size="12" fill="#666">92%</text>
    
    <rect x="260" y="220" width="40" height="80" fill="#9C27B0" rx="3"/>
    <text x="280" y="215" text-anchor="middle" font-family="Arial" font-size="12" fill="#666">65%</text>
    
    <rect x="320" y="190" width="40" height="110" fill="#F44336" rx="3"/>
    <text x="340" y="185" text-anchor="middle" font-family="Arial" font-size="12" fill="#666">81%</text>
    
    <!-- Line Graph (Trend) -->
    <text x="440" y="140" font-family="Arial" font-size="16" font-weight="600" fill="#333">Attendance Trend</text>
    
    <!-- Trend Line -->
    <path d="M450,300 L490,280 L530,260 L570,290 L610,250 L650,270 L690,240" 
          fill="none" stroke="#1565c0" stroke-width="3" stroke-linecap="round"/>
    
    <!-- Dots on trend line -->
    <circle cx="450" cy="300" r="4" fill="#1565c0"/>
    <circle cx="490" cy="280" r="4" fill="#1565c0"/>
    <circle cx="530" cy="260" r="4" fill="#1565c0"/>
    <circle cx="570" cy="290" r="4" fill="#1565c0"/>
    <circle cx="610" cy="250" r="4" fill="#1565c0"/>
    <circle cx="650" cy="270" r="4" fill="#1565c0"/>
    <circle cx="690" cy="240" r="4" fill="#1565c0"/>
    
    <!-- Analytics Cards -->
    <rect x="30" y="370" width="230" height="20" rx="3" fill="#e8f5e8"/>
    <text x="40" y="384" font-family="Arial" font-size="12" fill="#2e7d32">
        <tspan font-weight="bold">Present Today:</tspan> 142/150 Students (94.7%)
    </text>
    
    <rect x="285" y="370" width="230" height="20" rx="3" fill="#e3f2fd"/>
    <text x="295" y="384" font-family="Arial" font-size="12" fill="#1565c0">
        <tspan font-weight="bold">Avg. Attendance:</tspan> 87.3% This Month
    </text>
    
    <rect x="540" y="370" width="230" height="20" rx="3" fill="#fff3e0"/>
    <text x="550" y="384" font-family="Arial" font-size="12" fill="#ef6c00">
        <tspan font-weight="bold">Low Attendance:</tspan> CSE-402 (71.2%)
    </text>
    
    <!-- Icons -->
    <g transform="translate(400, 220)">
        <!-- People icon -->
        <circle cx="-320" cy="-40" r="15" fill="#e3f2fd"/>
        <text x="-320" y="-35" text-anchor="middle" font-family="FontAwesome" font-size="16" fill="#1565c0">👥</text>
        
        <!-- Calendar icon -->
        <circle cx="-250" cy="-40" r="15" fill="#e8f5e8"/>
        <text x="-250" y="-35" text-anchor="middle" font-family="FontAwesome" font-size="16" fill="#2e7d32">📅</text>
        
        <!-- Chart icon -->
        <circle cx="-180" cy="-40" r="15" fill="#fff3e0"/>
        <text x="-180" y="-35" text-anchor="middle" font-family="FontAwesome" font-size="16" fill="#ef6c00">📊</text>
    </g>
</svg>
                    </div>
                </div>
            </div>
        </div>

        <div id="features" class="row mt-5">
            <div class="col-md-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-primary bg-gradient text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-qrcode fa-2x"></i>
                        </div>
                        <h4 class="card-title">QR Code Attendance</h4>
                        <p class="card-text">Students scan unique QR codes for automatic attendance marking. Fast and efficient.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-success bg-gradient text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                        <h4 class="card-title">Real-time Reports</h4>
                        <p class="card-text">Faculty can view attendance statistics and generate reports in real-time.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-warning bg-gradient text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-user-shield fa-2x"></i>
                        </div>
                        <h4 class="card-title">Secure Access</h4>
                        <p class="card-text">Role-based access control ensures only authorized users can manage attendance.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>