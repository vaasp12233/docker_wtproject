<?php
// login.php - SIMPLIFIED WORKING VERSION
// Start session FIRST
session_start();

// Include config
require_once 'config.php';

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] == 'faculty') {
        header('Location: faculty_dashboard.php');
        exit;
    } elseif ($_SESSION['role'] == 'student') {
        header('Location: student_dashboard.php');
        exit;
    }
}

// Initialize variables
$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    // Get form data
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : 'faculty';
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Extract part before @ for default password
        $email_parts = explode('@', $email);
        $default_password = strtolower($email_parts[0]);
        
        if ($role == 'faculty') {
            // Faculty login
            $query = "SELECT faculty_id, faculty_name, faculty_email, faculty_department, password 
                     FROM faculty WHERE faculty_email = ?";
            $stmt = mysqli_prepare($conn, $query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) == 1) {
                    $faculty = mysqli_fetch_assoc($result);
                    
                    $login_success = false;
                    
                    // Check if faculty has custom password
                    if (!empty($faculty['password'])) {
                        // Verify custom password
                        if (password_verify($password, $faculty['password'])) {
                            $login_success = true;
                        }
                    } else {
                        // Check default password (part before @)
                        if (strtolower($password) === $default_password) {
                            $login_success = true;
                        }
                    }
                    
                    if ($login_success) {
                        // Set session variables
                        $_SESSION['faculty_id'] = $faculty['faculty_id'];
                        $_SESSION['name'] = $faculty['faculty_name'];
                        $_SESSION['email'] = $faculty['faculty_email'];
                        $_SESSION['department'] = $faculty['faculty_department'];
                        $_SESSION['role'] = 'faculty';
                        $_SESSION['logged_in'] = true;
                        
                        // Redirect to faculty dashboard
                        header('Location: faculty_dashboard.php');
                        exit;
                    } else {
                        $error = "Invalid password. " . 
                                (empty($faculty['password']) ? 
                                 "Default password is: " . htmlspecialchars($default_password) : 
                                 "Please enter your custom password.");
                    }
                } else {
                    $error = "Faculty email not found.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Database error. Please try again.";
            }
        } else {
            // Student login
            $query = "SELECT student_id, student_name, student_email, section, student_department 
                     FROM students WHERE student_email = ?";
            $stmt = mysqli_prepare($conn, $query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) == 1) {
                    $student = mysqli_fetch_assoc($result);
                    
                    // Check default password (part before @)
                    if (strtolower($password) === $default_password) {
                        // Set session variables
                        $_SESSION['student_id'] = $student['student_id'];
                        $_SESSION['name'] = $student['student_name'];
                        $_SESSION['email'] = $student['student_email'];
                        $_SESSION['section'] = $student['section'];
                        $_SESSION['department'] = $student['student_department'];
                        $_SESSION['role'] = 'student';
                        $_SESSION['logged_in'] = true;
                        
                        // Redirect to student dashboard
                        header('Location: student_dashboard.php');
                        exit;
                    } else {
                        $error = "Invalid password. Default password is: " . htmlspecialchars($default_password);
                    }
                } else {
                    $error = "Student email not found.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Database error. Please try again.";
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 450px;
            width: 100%;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .logo i {
            font-size: 2.5rem;
            color: #4361ee;
            margin-bottom: 10px;
        }
        
        .role-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .role-btn {
            flex: 1;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            color: #666;
            cursor: pointer;
            text-align: center;
        }
        
        .role-btn.active {
            background: #4361ee;
            color: white;
            border-color: #4361ee;
        }
        
        .btn-login {
            background: #4361ee;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            margin-top: 15px;
        }
        
        .btn-login:hover {
            background: #3a0ca3;
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h3 class="text-primary">CSE Attendance System</h3>
            <p class="text-muted">Login to continue</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
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
                <input type="email" name="email" class="form-control" 
                       placeholder="Enter your email" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" 
                           placeholder="Enter password" required id="passwordField">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small class="text-muted mt-1 d-block" id="passwordHint">
                    Default password is the part before '@' in your email
                </small>
            </div>
            
            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </button>
        </form>
        
        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none">
                <i class="fas fa-arrow-left me-1"></i> Back to Home
            </a>
        </div>
    </div>

    <script>
        function selectRole(role) {
            document.getElementById('facultyBtn').classList.remove('active');
            document.getElementById('studentBtn').classList.remove('active');
            
            if (role === 'faculty') {
                document.getElementById('facultyBtn').classList.add('active');
                document.getElementById('selectedRole').value = 'faculty';
                document.getElementById('passwordHint').innerHTML = 
                    'Faculty: Enter custom password or part before @ in email';
            } else {
                document.getElementById('studentBtn').classList.add('active');
                document.getElementById('selectedRole').value = 'student';
                document.getElementById('passwordHint').innerHTML = 
                    'Student: Password is the part before @ in your email';
            }
        }
        
        function togglePassword() {
            const passwordField = document.getElementById('passwordField');
            const icon = document.querySelector('#passwordField + button i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
