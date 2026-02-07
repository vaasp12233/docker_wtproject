<?php
// ==================== Start output buffering ====================
if (!ob_get_level()) {
    ob_start();
}

// ==================== Configure session for Render ====================
ini_set('session.save_path', '/tmp');
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);

// ==================== Start session ====================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

// ==================== Role check (optional) ====================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Clean buffer before output ====================
if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

// ==================== Handle POST request ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gender = $_POST['gender'] ?? '';
    
    // Validate gender
    if (!in_array($gender, ['male', 'female'])) {
        $error = "Please select a valid gender";
    } else {
        // Update gender in database - USING PREPARED STATEMENT
        $sql = "UPDATE students SET gender = ? WHERE student_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $gender, $student_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Update session if needed
            $_SESSION['gender'] = $gender;
            
            // Redirect to dashboard
            header('Location: student_dashboard.php');
            exit;
        } else {
            $error = "Database error. Please try again.";
        }
    }
}

$page_title = "Set Your Gender";
include 'header.php';

// ==================== Get student name for personalization ====================
$student_name = "";
if ($conn) {
    $query = "SELECT student_name FROM students WHERE student_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $student_name = $row['student_name'];
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<style>
    /* Gender Selection Page Styles */
    .gender-card {
        max-width: 500px;
        margin: 2rem auto;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        border: none;
    }
    
    .gender-card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem;
        text-align: center;
        color: white;
    }
    
    .gender-option {
        cursor: pointer;
        padding: 20px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .gender-option:hover {
        border-color: #007bff;
        background-color: rgba(0, 123, 255, 0.05);
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .gender-option input[type="radio"] {
        display: none;
    }
    
    .gender-option.selected {
        border-color: #28a745;
        background-color: rgba(40, 167, 69, 0.1);
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.2);
    }
    
    .gender-icon {
        font-size: 4rem;
        margin-bottom: 15px;
        display: block;
    }
    
    .gender-icon.male {
        color: #007bff;
    }
    
    .gender-icon.female {
        color: #e83e8c;
    }
    
    .gender-label {
        font-size: 1.2rem;
        font-weight: 500;
        margin-bottom: 5px;
    }
    
    .gender-description {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 0;
    }
    
    .submit-btn {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: none;
        padding: 12px 30px;
        font-size: 1.1rem;
        border-radius: 8px;
        width: 100%;
        margin-top: 20px;
        transition: all 0.3s ease;
    }
    
    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
    }
    
    .submit-btn:disabled {
        background: #6c757d;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    .welcome-text {
        text-align: center;
        margin-bottom: 2rem;
        font-size: 1.1rem;
    }
    
    /* Mobile responsive */
    @media (max-width: 576px) {
        .gender-card {
            margin: 1rem;
        }
        
        .gender-icon {
            font-size: 3rem;
        }
        
        .gender-card-header {
            padding: 1.5rem;
        }
        
        .gender-card-header h3 {
            font-size: 1.5rem;
        }
    }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="gender-card">
                <div class="gender-card-header">
                    <h3 class="mb-2">
                        <i class="fas fa-user-check me-2"></i>Complete Your Profile
                    </h3>
                    <p class="mb-0">One last step to personalize your experience</p>
                </div>
                
                <div class="card-body p-4">
                    <?php if (!empty($student_name)): ?>
                    <div class="welcome-text">
                        <p class="text-primary mb-1">
                            <i class="fas fa-user-graduate me-2"></i>
                            Welcome, <strong><?php echo htmlspecialchars($student_name); ?></strong>!
                        </p>
                        <p class="text-muted mb-0">Please select your gender to continue</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="genderForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="gender-option" for="male">
                                    <input type="radio" name="gender" id="male" value="male" required>
                                    <span class="gender-icon male">
                                        <i class="fas fa-mars"></i>
                                    </span>
                                    <span class="gender-label">Male</span>
                                    <span class="gender-description">
                                        For male students
                                    </span>
                                </label>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="gender-option" for="female">
                                    <input type="radio" name="gender" id="female" value="female" required>
                                    <span class="gender-icon female">
                                        <i class="fas fa-venus"></i>
                                    </span>
                                    <span class="gender-label">Female</span>
                                    <span class="gender-description">
                                        For female students
                                    </span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn submit-btn" id="submitBtn" disabled>
                                <i class="fas fa-save me-2"></i>Save & Continue to Dashboard
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                This helps personalize your avatar and experience
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript for gender selection UI
document.addEventListener('DOMContentLoaded', function() {
    const genderOptions = document.querySelectorAll('.gender-option');
    const submitBtn = document.getElementById('submitBtn');
    
    // Handle gender selection
    genderOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove selected class from all options
            genderOptions.forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            this.classList.add('selected');
            
            // Check the radio button
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Enable submit button
            submitBtn.disabled = false;
            
            // Add visual feedback
            this.style.transform = 'scale(1.02)';
            setTimeout(() => {
                this.style.transform = '';
            }, 300);
        });
    });
    
    // Form validation
    const form = document.getElementById('genderForm');
    form.addEventListener('submit', function(e) {
        const selectedGender = document.querySelector('input[name="gender"]:checked');
        
        if (!selectedGender) {
            e.preventDefault();
            alert('Please select your gender before continuing.');
            return false;
        }
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        submitBtn.disabled = true;
        
        return true;
    });
    
    // Add animation on page load
    setTimeout(() => {
        genderOptions.forEach(option => {
            option.style.opacity = '0';
            option.style.transform = 'translateY(20px)';
        });
        
        let delay = 0;
        genderOptions.forEach(option => {
            setTimeout(() => {
                option.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                option.style.opacity = '1';
                option.style.transform = 'translateY(0)';
            }, delay);
            delay += 200;
        });
    }, 100);
});
</script>

<?php 
include 'footer.php';

// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
