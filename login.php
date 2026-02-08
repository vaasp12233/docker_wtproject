<?php
session_start();
require_once 'config.php'; 

// Check if database connection is available
if (!$conn) {
    $db_error = "⚠️ Database connection failed. Please try again later or contact administrator.";
}

// Redirect if already logged in
if (isset($_SESSION['logged_in'])) {
    if ($_SESSION['role'] == 'faculty') {
        header('Location: faculty_dashboard.php');
    } else {
        header('Location: student_dashboard.php');
    }
    exit;
}

// Handle Traditional Login
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    
    // Check if database is connected
    if (!$conn) {
        $error = "Database connection failed. Please try again later.";
    } else {
        // Use mysqli_real_escape_string instead of $conn->real_escape_string
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = $_POST['password']; // Don't escape password, we'll verify it separately
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            // Get the part before @ in email as expected password
            $email_parts = explode('@', $email);
            if (count($email_parts) < 2) {
                $error = "Invalid email format.";
            } else {
                $expected_password = strtolower($email_parts[0]);
                
                if ($role == 'faculty') {
                    // Faculty login - check if email exists
                    $sql = "SELECT faculty_id, faculty_name, faculty_email, faculty_department, password 
                            FROM faculty WHERE faculty_email = '$email'";
                    $result = mysqli_query($conn, $sql);
                    
                    if ($result && mysqli_num_rows($result) > 0) {
                        $user = mysqli_fetch_assoc($result);
                        
                        // Check password (custom or default)
                        $login_success = false;
                        
                        if (!empty($user['password'])) {
                            // Check hashed custom password
                            $login_success = password_verify($password, $user['password']);
                        } else {
                            // Check default password (part before @ in email)
                            $login_success = (strtolower($password) === $expected_password);
                        }
                        
                        if ($login_success) {
                            $_SESSION['faculty_id'] = $user['faculty_id'];
                            $_SESSION['name'] = $user['faculty_name'];
                            $_SESSION['email'] = $user['faculty_email'];
                            $_SESSION['department'] = $user['faculty_department'];
                            $_SESSION['role'] = 'faculty';
                            $_SESSION['logged_in'] = true;
                            
                            // Set flag if using default password
                            if (empty($user['password'])) {
                                $_SESSION['default_password'] = true;
                            }
                            
                            header('Location: faculty_dashboard.php');
                            exit;
                        } else {
                            $error = "Invalid password. " . (empty($user['password']) ? 
                                    "Default password is the part before @ in your email." : 
                                    "Please enter your custom password.");
                        }
                    } else {
                        $error = "Email not found in faculty database.";
                    }
                } else {
                    // Student login - check if email exists
                    $sql = "SELECT student_id, student_name, student_email, section, student_department 
                            FROM students WHERE student_email = '$email'";
                    $result = mysqli_query($conn, $sql);
                    
                    if ($result && mysqli_num_rows($result) > 0) {
                        $user = mysqli_fetch_assoc($result);
                        
                        // Check if password matches the part before @ in email
                        if (strtolower($password) === $expected_password) {
                            $_SESSION['student_id'] = $user['student_id'];
                            $_SESSION['name'] = $user['student_name'];
                            $_SESSION['email'] = $user['student_email'];
                            $_SESSION['section'] = $user['section'];
                            $_SESSION['department'] = $user['student_department'];
                            $_SESSION['role'] = 'student';
                            $_SESSION['logged_in'] = true;
                            header('Location: student_dashboard.php');
                            exit;
                        } else {
                            $error = "Invalid password. Password should be the part before @ in your email.";
                        }
                    } else {
                        $error = "Email not found in student database.";
                    }
                }
            }
        }
    }
}

// Get role from URL if available
$default_role = isset($_GET['role']) && in_array($_GET['role'], ['faculty', 'student']) ? $_GET['role'] : 'faculty';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CSE Smart Attendance System</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
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
        
        body {
            background: linear-gradient(135deg, 
                var(--primary-blue) 0%, 
                var(--secondary-blue) 50%, 
                var(--accent-blue) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
        }
        
        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.3;
        }
        
        .particle {
            position: absolute;
            background: white;
            border-radius: 50%;
            animation: float 15s infinite linear;
        }
        
        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 0.1; }
            90% { opacity: 0.1; }
            100% { transform: translateY(-100px) rotate(360deg); opacity: 0; }
        }
        
        /* Main Container */
        .main-container {
            display: flex;
            max-width: 1200px;
            width: 100%;
            min-height: 700px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 25px 75px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.8s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Left Panel - Info */
        .info-panel {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .info-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 150%;
            height: 150%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            backdrop-filter: blur(10px);
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 40px 0;
        }
        
        .feature-list li {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            font-size: 16px;
            opacity: 0.9;
        }
        
        .feature-list i {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 18px;
        }
        
        /* Right Panel - Login */
        .login-panel {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header h2 {
            color: var(--gray-800);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: var(--gray-800);
            opacity: 0.7;
        }
        
        /* Role Selector - IMPROVED */
        .role-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 35px;
            background: var(--gray-100);
            padding: 8px;
            border-radius: 12px;
        }
        
        .role-tab {
            flex: 1;
            padding: 15px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        
        .role-tab:hover {
            background: rgba(59, 130, 246, 0.1);
        }
        
        .role-tab.active {
            background: white;
            color: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .role-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-control {
            padding: 15px;
            border: 2px solid var(--light-blue);
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .form-hint {
            font-size: 14px;
            color: var(--gray-800);
            opacity: 0.7;
            margin-top: 5px;
            padding: 8px 12px;
            background: var(--light-blue);
            border-radius: 8px;
            border-left: 3px solid var(--primary-blue);
        }
        
        /* Button Styles */
        .btn-login {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 18px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .btn-login i {
            margin-right: 10px;
        }
        
        /* Alert Styles */
        .alert-custom {
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            border: none;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fee, #fff5f5);
            color: var(--danger-red);
            border-left: 4px solid var(--danger-red);
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fef3c7, #fffbeb);
            color: #d97706;
            border-left: 4px solid var(--warning-orange);
        }
        
        /* Back Link */
        .back-link {
            text-align: center;
            margin-top: 25px;
        }
        
        .back-link a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .back-link a:hover {
            color: var(--secondary-blue);
            transform: translateX(-5px);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .main-container {
                flex-direction: column;
                min-height: auto;
            }
            
            .info-panel {
                padding: 30px;
                order: 2;
            }
            
            .login-panel {
                padding: 40px 30px;
                order: 1;
            }
        }
        
        @media (max-width: 576px) {
            .role-selector {
                flex-direction: column;
                gap: 5px;
            }
            
            .info-panel, .login-panel {
                padding: 25px;
            }
        }
        
        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-800);
            opacity: 0.6;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        
        .password-toggle:hover {
            opacity: 1;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        /* Loading Animation */
        .loading {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(59, 130, 246, 0.2);
            border-radius: 50%;
            border-top-color: var(--primary-blue);
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation" id="bgAnimation"></div>
    
    <!-- Main Container -->
    <div class="main-container">
        <!-- Left Info Panel -->
        <div class="info-panel">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h1 class="display-5 fw-bold">CSE Attendance</h1>
                <p>Smart QR-Based System</p>
                <div class="mt-4">
                    <span class="badge bg-light text-primary px-3 py-2">RGUKT RK Valley</span>
                </div>
            </div>
            
            <h4 class="mb-4">Why Choose Our System?</h4>
            <ul class="feature-list">
                <li>
                    <i class="fas fa-qrcode"></i>
                    <span>QR-Based Attendance Scanning</span>
                </li>
                <li>
                    <i class="fas fa-chart-line"></i>
                    <span>Real-time Analytics Dashboard</span>
                </li>
                <li>
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure & Encrypted Data</span>
                </li>
                <li>
                    <i class="fas fa-mobile-alt"></i>
                    <span>Mobile-Friendly Interface</span>
                </li>
                <li>
                    <i class="fas fa-clock"></i>
                    <span>24/7 System Availability</span>
                </li>
            </ul>
            
            <div class="mt-auto">
                <div class="d-flex align-items-center justify-content-center">
                    <div class="text-center">
                        <div class="h4 mb-2">360+ Active Users</div>
                        <small class="opacity-75">Trusted by CSE Department</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Login Panel -->
        <div class="login-panel">
            <div class="login-header">
                <h2>Welcome Back!</h2>
                <p>Sign in to access your dashboard</p>
            </div>
            
            <?php if (isset($db_error)): ?>
                <div class="alert-custom alert-danger animate__animated animate__shakeX">
                    <i class="fas fa-database fa-lg"></i>
                    <div><?php echo htmlspecialchars($db_error); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert-custom alert-danger animate__animated animate__shakeX">
                    <i class="fas fa-exclamation-triangle fa-lg"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <!-- Role Selection - IMPROVED -->
                <div class="role-selector">
                    <button type="button" class="role-tab <?php echo $default_role == 'faculty' ? 'active' : ''; ?>" 
                         onclick="selectRole('faculty')" id="facultyTab">
                        <div class="role-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <span>Faculty</span>
                    </button>
                    
                    <button type="button" class="role-tab <?php echo $default_role == 'student' ? 'active' : ''; ?>" 
                         onclick="selectRole('student')" id="studentTab">
                        <div class="role-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <span>Student</span>
                    </button>
                </div>
                
                <input type="hidden" name="role" id="selectedRole" value="<?php echo $default_role; ?>">
                
                <!-- Email Field -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i>
                        <span>Email Address</span>
                    </label>
                    <input type="email" name="email" class="form-control" 
                           placeholder="example@rguktrkv.ac.in" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <div class="form-hint">
                        Use your institute email address
                    </div>
                </div>
                
                <!-- Password Field -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i>
                        <span>Password</span>
                    </label>
                    <div class="password-wrapper">
                        <input type="password" name="password" class="form-control" 
                               placeholder="Enter your password" required id="passwordInput">
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="form-hint" id="passwordHint">
                        <?php echo $default_role == 'faculty' 
                            ? 'Enter your custom password or default (part before @ in email)' 
                            : 'Password is the part before @ in your email'; ?>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" name="login" class="btn-login" id="loginBtn" 
                        <?php if (!$conn) echo 'disabled'; ?>>
                    <i class="fas fa-sign-in-alt"></i>
                    <?php echo $conn ? 'Sign In to Dashboard' : 'System Unavailable'; ?>
                </button>
                
                <!-- Loading Spinner -->
                <div class="loading" id="loadingSpinner">
                    <div class="spinner"></div>
                </div>
                
                <!-- Back Link -->
                <div class="back-link">
                    <a href="index.php">
                        <i class="fas fa-arrow-left"></i>
                        Back to Home Page
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Initialize with default role
        const defaultRole = '<?php echo $default_role; ?>';
        
        // Create animated background
        function createBackgroundAnimation() {
            const container = document.getElementById('bgAnimation');
            const colors = ['rgba(255,255,255,0.1)', 'rgba(219, 234, 254, 0.2)', 'rgba(147, 197, 253, 0.15)'];
            
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Random properties
                const size = Math.random() * 50 + 10;
                const left = Math.random() * 100;
                const delay = Math.random() * 10;
                const duration = Math.random() * 10 + 10;
                const color = colors[Math.floor(Math.random() * colors.length)];
                
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${left}%`;
                particle.style.background = color;
                particle.style.animationDelay = `${delay}s`;
                particle.style.animationDuration = `${duration}s`;
                
                container.appendChild(particle);
            }
        }
        
        // Role selection - SIMPLIFIED
        function selectRole(role) {
            const facultyTab = document.getElementById('facultyTab');
            const studentTab = document.getElementById('studentTab');
            const selectedRole = document.getElementById('selectedRole');
            const passwordHint = document.getElementById('passwordHint');
            
            // Update tabs
            facultyTab.classList.remove('active');
            studentTab.classList.remove('active');
            
            if (role === 'faculty') {
                facultyTab.classList.add('active');
                selectedRole.value = 'faculty';
                if (passwordHint) {
                    passwordHint.innerHTML = 'Enter your custom password or default (part before @ in email)';
                }
            } else {
                studentTab.classList.add('active');
                selectedRole.value = 'student';
                if (passwordHint) {
                    passwordHint.innerHTML = 'Password is the part before @ in your email';
                }
            }
        }
        
        // Password toggle
        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleBtn = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.classList.remove('fa-eye');
                toggleBtn.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleBtn.classList.remove('fa-eye-slash');
                toggleBtn.classList.add('fa-eye');
            }
        }
        
        // Form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            if (loginBtn.disabled) {
                e.preventDefault();
                return;
            }
            
            // Show loading
            loginBtn.style.opacity = '0.5';
            loadingSpinner.style.display = 'block';
            
            // Simulate loading for better UX
            setTimeout(() => {
                loadingSpinner.style.display = 'none';
                loginBtn.style.opacity = '1';
            }, 2000);
        });
        
        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            createBackgroundAnimation();
            
            // Focus email field
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput) {
                setTimeout(() => {
                    emailInput.focus();
                }, 500);
            }
            
            // Initialize role based on URL
            selectRole(defaultRole);
        });
    </script>
</body>
</html>
