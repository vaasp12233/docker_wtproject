<?php
// Start session FIRST
session_start();

require_once 'config.php'; 

// Check if database is connected
if (!$conn) {
    $db_error = "Database connection failed. Please try again later.";
    // Continue to show login form but with error
}

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] == 'faculty') {
            header('Location: faculty_dashboard.php');
            exit;
        } elseif ($_SESSION['role'] == 'student') {
            header('Location: student_dashboard.php');
            exit;
        }
    }
}

// Handle Traditional Login
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    
    // Check database connection first
    if (!$conn) {
        $error = "Database connection failed. Please try again later.";
    } else {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $role = isset($_POST['role']) ? $_POST['role'] : 'faculty';
        
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format.";
            } else {
                // Get the part before @ in email
                $email_parts = explode('@', $email);
                if (count($email_parts) < 2) {
                    $error = "Invalid email format. Must contain '@' symbol.";
                } else {
                    $expected_password = strtolower($email_parts[0]);
                    
                    if ($role == 'faculty') {
                        // Faculty login - use prepared statement
                        $sql = "SELECT faculty_id, faculty_name, faculty_email, faculty_department, password 
                                FROM faculty WHERE faculty_email = ?";
                        $stmt = $conn->prepare($sql);
                        
                        if ($stmt) {
                            $stmt->bind_param("s", $email);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                $user = $result->fetch_assoc();
                                $login_success = false;
                                
                                // Check if custom password is set
                                if (!empty($user['password'])) {
                                    // Verify custom password
                                    if (password_verify($password, $user['password'])) {
                                        $login_success = true;
                                    }
                                } else {
                                    // Check default password
                                    if (strtolower($password) === $expected_password) {
                                        $login_success = true;
                                    }
                                }
                                
                                if ($login_success) {
                                    $_SESSION['faculty_id'] = $user['faculty_id'];
                                    $_SESSION['name'] = $user['faculty_name'];
                                    $_SESSION['email'] = $user['faculty_email'];
                                    $_SESSION['department'] = $user['faculty_department'];
                                    $_SESSION['role'] = 'faculty';
                                    $_SESSION['logged_in'] = true;
                                    
                                    // Set default password flag
                                    $_SESSION['default_password'] = empty($user['password']);
                                    
                                    $stmt->close();
                                    header('Location: faculty_dashboard.php');
                                    exit;
                                } else {
                                    $error = "Invalid password. " . (empty($user['password']) ? 
                                            "Default password is the part before '@' in your email." : 
                                            "Please enter your custom password.");
                                }
                            } else {
                                $error = "Email not found in faculty database.";
                            }
                            $stmt->close();
                        } else {
                            $error = "Database error. Please try again.";
                        }
                    } else {
                        // Student login - use prepared statement
                        $sql = "SELECT student_id, student_name, student_email, section, student_department 
                                FROM students WHERE student_email = ?";
                        $stmt = $conn->prepare($sql);
                        
                        if ($stmt) {
                            $stmt->bind_param("s", $email);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                $user = $result->fetch_assoc();
                                
                                // Check password (part before @ in email)
                                if (strtolower($password) === $expected_password) {
                                    $_SESSION['student_id'] = $user['student_id'];
                                    $_SESSION['name'] = $user['student_name'];
                                    $_SESSION['email'] = $user['student_email'];
                                    $_SESSION['section'] = $user['section'];
                                    $_SESSION['department'] = $user['student_department'];
                                    $_SESSION['role'] = 'student';
                                    $_SESSION['logged_in'] = true;
                                    
                                    $stmt->close();
                                    header('Location: student_dashboard.php');
                                    exit;
                                } else {
                                    $error = "Invalid password. Use the part before '@' in your email.";
                                }
                            } else {
                                $error = "Email not found in student database.";
                            }
                            $stmt->close();
                        } else {
                            $error = "Database error. Please try again.";
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CSE Attendance System</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 3rem;
            color: #4361ee;
            margin-bottom: 15px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .logo h2 {
            font-weight: 700;
            color: #2c3e50;
        }
        
        .logo p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .role-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .role-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: white;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .role-btn:hover {
            border-color: #4361ee;
            transform: translateY(-2px);
        }
        
        .role-btn.active {
            background: #4361ee;
            color: white;
            border-color: #4361ee;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 15px;
        }
        
        .forgot-password a {
            color: #4361ee;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .forgot-password a:hover {
            color: #3a0ca3;
            text-decoration: underline;
        }
        
        .password-info {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
            display: block;
        }
        
        /* Database error styling */
        .db-error {
            background: #ff6b6b;
            color: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        
        /* Password toggle button */
        #togglePassword {
            border-radius: 0 10px 10px 0;
            border-left: none;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h2 class="text-primary">CSE Attendance System</h2>
            <p class="text-muted">Department of Computer Science</p>
        </div>
        
        <?php if (isset($db_error)): ?>
            <div class="db-error">
                <i class="fas fa-database me-2"></i>
                <?php echo htmlspecialchars($db_error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php 
        // Show logout message if redirected from logout
        if (isset($_GET['message']) && $_GET['message'] == 'logged_out'): 
        ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Successfully logged out.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm" novalidate>
            <div class="role-selector">
                <div class="role-btn active" onclick="selectRole('faculty')" id="facultyBtn">
                    <i class="fas fa-chalkboard-teacher me-2"></i> Faculty
                </div>
                <div class="role-btn" onclick="selectRole('student')" id="studentBtn">
                    <i class="fas fa-user-graduate me-2"></i> Student
                </div>
            </div>
            
            <input type="hidden" name="role" id="selectedRole" value="faculty">
            
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="Enter your institute email" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <small class="text-muted">Use your institute email address</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required id="passwordField">
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small class="password-info" id="passwordHint">
                    For faculty: Enter custom password or part before @<br>
                    For students: Password is the part before @ in your email
                </small>
            </div>
            
            <button type="submit" name="login" class="btn-login" id="loginButton">
                <i class="fas fa-sign-in-alt me-2"></i> Login to System
            </button>
            
            <div class="forgot-password">
                <a href="index.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Home
                </a>
            </div>
        </form>
    </div>
    
    <!-- SIMPLE TRANSLATE BUTTON -->
    <button onclick="translatePage()" id="translateBtn" 
            style="position: fixed; bottom: 25px; right: 25px; z-index: 10000;
                   width: 60px; height: 60px; background: linear-gradient(135deg, #1a73e8, #0d62d9);
                   border: none; border-radius: 50%; box-shadow: 0 4px 15px rgba(26, 115, 232, 0.4);
                   cursor: pointer; display: flex; align-items: center; justify-content: center;
                   transition: all 0.3s ease;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12.87 15.07L10.33 12.56L10.36 12.53C12.1 10.59 13.34 8.36 14.07 6H17V4H10V2H8V4H1V6H12.17C11.5 7.92 10.44 9.75 9 11.35C8.07 10.32 7.3 9.19 6.69 8H4.69C5.42 9.63 6.42 11.17 7.67 12.56L2.58 17.58L4 19L9 14L12.11 17.11L12.87 15.07ZM18.5 10H16.5L12 22H14L15.12 19H19.87L21 22H23L18.5 10ZM15.88 17L17.5 12.67L19.12 17H15.88Z" fill="white"/>
        </svg>
    </button>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function selectRole(role) {
            const facultyBtn = document.getElementById('facultyBtn');
            const studentBtn = document.getElementById('studentBtn');
            const selectedRole = document.getElementById('selectedRole');
            const passwordHint = document.getElementById('passwordHint');
            
            facultyBtn.classList.remove('active');
            studentBtn.classList.remove('active');
            
            if (role === 'faculty') {
                facultyBtn.classList.add('active');
                selectedRole.value = 'faculty';
                if (passwordHint) {
                    passwordHint.innerHTML = 'For faculty: Enter custom password or part before @<br>Default: Part before @ in your email';
                }
            } else {
                studentBtn.classList.add('active');
                selectedRole.value = 'student';
                if (passwordHint) {
                    passwordHint.innerHTML = 'For students: Password is always the part before @ in your email';
                }
            }
        }
        
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('passwordField');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = this.querySelector('[name="email"]').value;
            const password = this.querySelector('[name="password"]').value;
            const loginButton = document.getElementById('loginButton');
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return;
            }
            
            // Show loading state
            loginButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Logging in...';
            loginButton.disabled = true;
        });
        
        function translatePage() {
            // Simple translate function
            if (typeof google !== 'undefined' && google.translate) {
                try {
                    new google.translate.TranslateElement({
                        pageLanguage: 'en',
                        includedLanguages: 'en,hi,te',
                        layout: google.translate.TranslateElement.InlineLayout.SIMPLE
                    }, 'google_translate_element');
                    
                    // Create element if not exists
                    if (!document.getElementById('google_translate_element')) {
                        const div = document.createElement('div');
                        div.id = 'google_translate_element';
                        div.style.display = 'none';
                        document.body.appendChild(div);
                    }
                    
                    // Trigger translation UI
                    setTimeout(() => {
                        const translateButton = document.querySelector('.goog-te-menu-value');
                        if (translateButton) translateButton.click();
                    }, 100);
                } catch (e) {
                    console.log('Translate error:', e);
                    alert('Translation service unavailable');
                }
            } else {
                // Load Google Translate
                const script = document.createElement('script');
                script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
                document.head.appendChild(script);
                
                window.googleTranslateElementInit = function() {
                    new google.translate.TranslateElement({
                        pageLanguage: 'en',
                        includedLanguages: 'en,hi,te',
                        layout: google.translate.TranslateElement.InlineLayout.SIMPLE
                    }, 'google_translate_element');
                };
            }
        }
        
        // Button hover effects
        const translateBtn = document.getElementById('translateBtn');
        if (translateBtn) {
            translateBtn.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1)';
                this.style.boxShadow = '0 6px 20px rgba(26, 115, 232, 0.5)';
                this.style.background = 'linear-gradient(135deg, #0d62d9, #0a56c4)';
            });
            
            translateBtn.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = '0 4px 15px rgba(26, 115, 232, 0.4)';
                this.style.background = 'linear-gradient(135deg, #1a73e8, #0d62d9)';
            });
        }
    </script>
    
    <style>
        /* Hide Google Translate elements */
        .goog-te-banner-frame, .goog-te-menu-value span, .goog-logo-link {
            display: none !important;
        }
        
        .goog-te-gadget {
            font-size: 0 !important;
        }
        
        /* Loading animation */
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</body>
</html>
