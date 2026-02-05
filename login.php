<?php
session_start();
require_once 'config.php'; 

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
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    $role = $conn->real_escape_string($_POST['role']);
    
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
                
                if (mysqli_num_rows($result) > 0) {
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
                
                if (mysqli_num_rows($result) > 0) {
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
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 3rem;
            color: #4361ee;
            margin-bottom: 15px;
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
        
        .role-btn.active {
            background: #4361ee;
            color: white;
            border-color: #4361ee;
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
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 15px;
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
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
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
                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                <small class="text-muted">Use your institute email address</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                <small class="text-muted">
                    <?php if (isset($_GET['role']) && $_GET['role'] == 'faculty'): ?>
                        Enter your custom password or default (part before @)
                    <?php else: ?>
                        Password is the part before @ in your email
                    <?php endif; ?>
                </small>
            </div>
            
            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Login to System
            </button>
            
            <div class="forgot-password">
                <a href="index.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Home
                </a>
            </div>
        </form>
    </div>
    
    <!-- SIMPLE TRANSLATE BUTTON - WORKS EVERY TIME -->
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
    
    <script>
        function selectRole(role) {
            const facultyBtn = document.getElementById('facultyBtn');
            const studentBtn = document.getElementById('studentBtn');
            const selectedRole = document.getElementById('selectedRole');
            const passwordHint = document.querySelector('.mb-3:nth-child(3) small');
            
            facultyBtn.classList.remove('active');
            studentBtn.classList.remove('active');
            
            if (role === 'faculty') {
                facultyBtn.classList.add('active');
                selectedRole.value = 'faculty';
                if (passwordHint) {
                    passwordHint.textContent = 'Enter your custom password or default (part before @)';
                }
            } else {
                studentBtn.classList.add('active');
                selectedRole.value = 'student';
                if (passwordHint) {
                    passwordHint.textContent = 'Password is the part before @ in your email';
                }
            }
        }
        
        // SIMPLE WORKING TRANSLATE FUNCTION
        function translatePage() {
            // Try Google Translate first
            if (window.google && google.translate && google.translate.TranslateElement) {
                // If Google Translate is already loaded, use it
                var translateDiv = document.getElementById('google_translate_element');
                if (!translateDiv) {
                    translateDiv = document.createElement('div');
                    translateDiv.id = 'google_translate_element';
                    translateDiv.style.position = 'fixed';
                    translateDiv.style.bottom = '20px';
                    translateDiv.style.right = '20px';
                    translateDiv.style.zIndex = '9999';
                    translateDiv.style.display = 'none';
                    document.body.appendChild(translateDiv);
                }
                
                // Initialize Google Translate
                new google.translate.TranslateElement({
                    pageLanguage: 'en',
                    includedLanguages: 'en,hi,te',
                    layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
                    autoDisplay: false
                }, 'google_translate_element');
                
                // Trigger the dropdown
                setTimeout(function() {
                    var googleBtn = document.querySelector('.goog-te-menu-value');
                    if (googleBtn) {
                        googleBtn.click();
                    }
                }, 100);
                
            } else {
                // Load Google Translate if not loaded
                var script = document.createElement('script');
                script.src = 'https://translate.google.com/translate_a/element.js?cb=initGoogleTranslate';
                document.head.appendChild(script);
                
                // Define the callback
                window.initGoogleTranslate = function() {
                    var translateDiv = document.getElementById('google_translate_element');
                    if (!translateDiv) {
                        translateDiv = document.createElement('div');
                        translateDiv.id = 'google_translate_element';
                        translateDiv.style.position = 'fixed';
                        translateDiv.style.bottom = '20px';
                        translateDiv.style.right = '20px';
                        translateDiv.style.zIndex = '9999';
                        translateDiv.style.display = 'none';
                        document.body.appendChild(translateDiv);
                    }
                    
                    new google.translate.TranslateElement({
                        pageLanguage: 'en',
                        includedLanguages: 'en,hi,te',
                        layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
                        autoDisplay: false
                    }, 'google_translate_element');
                    
                    // Trigger the dropdown
                    setTimeout(function() {
                        var googleBtn = document.querySelector('.goog-te-menu-value');
                        if (googleBtn) {
                            googleBtn.click();
                        }
                    }, 500);
                };
            }
            
            // Button animation
            const btn = document.getElementById('translateBtn');
            btn.style.transform = 'scale(0.9)';
            setTimeout(() => {
                btn.style.transform = 'scale(1)';
            }, 150);
        }
        
        // Button hover effects
        const translateBtn = document.getElementById('translateBtn');
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
        
        // Add tooltip
        const tooltip = document.createElement('div');
        tooltip.textContent = 'Translate';
        tooltip.style.cssText = `
            position: absolute;
            bottom: 70px;
            right: 15px;
            background: #333;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
            pointer-events: none;
            z-index: 10001;
        `;
        translateBtn.appendChild(tooltip);
        
        translateBtn.addEventListener('mouseenter', function() {
            tooltip.style.opacity = '1';
            tooltip.style.transform = 'translateY(0)';
        });
        
        translateBtn.addEventListener('mouseleave', function() {
            tooltip.style.opacity = '0';
            tooltip.style.transform = 'translateY(10px)';
        });
    </script>
    
    <style>
        /* Hide Google Translate elements */
        .goog-te-banner-frame {
            display: none !important;
        }
        
        .skiptranslate {
            display: none !important;
        }
        
        body {
            top: 0 !important;
            position: static !important;
        }
        
        /* Style Google Translate dropdown */
        .goog-te-menu-frame {
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
            border: 1px solid #e0e0e0 !important;
            max-width: 220px !important;
            bottom: 95px !important;
            top: auto !important;
            right: 20px !important;
            left: auto !important;
        }
        
        .goog-te-menu2 {
            max-width: 220px !important;
            border-radius: 12px !important;
            overflow: hidden !important;
            padding: 8px 0 !important;
        }
        
        .goog-te-menu2-item {
            padding: 12px 20px !important;
            font-size: 14px !important;
            color: #333 !important;
        }
        
        .goog-te-menu2-item:hover {
            background-color: #f5f8ff !important;
            color: #1a73e8 !important;
        }
        
        /* Hide Google logo */
        .goog-logo-link {
            display: none !important;
        }
        
        /* Tooltip arrow */
        #translateBtn div:last-child:after {
            content: '';
            position: absolute;
            top: 100%;
            right: 20px;
            border: 5px solid transparent;
            border-top-color: #333;
        }
    </style>
</body>
</html>
