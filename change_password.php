<?php
require_once 'config.php';
session_start(); // Add session start

// Security check
if (!isset($_SESSION['faculty_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];
$success = '';
$error = '';

// Get faculty details using prepared statement
$faculty_query = "SELECT faculty_id, faculty_email, password FROM faculty WHERE faculty_id = ?";
$stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($stmt, "s", $faculty_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$faculty = mysqli_fetch_assoc($result);

if (!$faculty) {
    $_SESSION['error'] = "Faculty not found.";
    header('Location: faculty_dashboard.php');
    exit;
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    // Sanitize and validate inputs
    $current_password = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if faculty has set a custom password
        if (empty($faculty['password']) || $faculty['password'] === null) {
            // First time password setup - verify using email part
            $email_parts = explode('@', $faculty['faculty_email']);
            $email_part = strtolower($email_parts[0] ?? '');
            
            if (strtolower($current_password) !== $email_part) {
                $error = "Current password is incorrect.";
            } else {
                // Hash and save new password using prepared statement
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE faculty SET password = ? WHERE faculty_id = ?";
                $stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $faculty_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Password changed successfully!";
                    // Refresh faculty data
                    $faculty['password'] = $hashed_password;
                } else {
                    $error = "Failed to update password. Please try again.";
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            // Verify current password
            if (password_verify($current_password, $faculty['password'])) {
                // Check if new password is same as old
                if (password_verify($new_password, $faculty['password'])) {
                    $error = "New password cannot be the same as current password.";
                } else {
                    // Hash and save new password using prepared statement
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE faculty SET password = ? WHERE faculty_id = ?";
                    $stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $faculty_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Password changed successfully!";
                        // Refresh faculty data
                        $faculty['password'] = $hashed_password;
                    } else {
                        $error = "Failed to update password. Please try again.";
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }
}

$page_title = "Change Password";
include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-lock me-2"></i> Change Password</h4>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <div class="mt-2">
                            <a href="faculty_dashboard.php" class="btn btn-sm btn-success">
                                <i class="fas fa-home me-1"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" autocomplete="off">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" name="current_password" class="form-control" id="currentPassword" required autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('currentPassword', 'currentEye')">
                                    <i class="fas fa-eye" id="currentEye"></i>
                                </button>
                            </div>
                            <small class="text-muted">
                                <?php if (empty($faculty['password']) || $faculty['password'] === null): ?>
                                    <i class="fas fa-info-circle me-1"></i> Default is the part before '@' in your email address
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" class="form-control" id="newPassword" required minlength="6" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('newPassword', 'newEye')">
                                    <i class="fas fa-eye" id="newEye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" class="form-control" id="confirmPassword" required minlength="6" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirmPassword', 'confirmEye')">
                                    <i class="fas fa-eye" id="confirmEye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="change_password" class="btn btn-primary btn-lg">
                                <i class="fas fa-key me-2"></i> Change Password
                            </button>
                            <a href="faculty_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                    
                    <div class="alert alert-info mt-4">
                        <h6><i class="fas fa-shield-alt me-2"></i> Password Security Tips:</h6>
                        <ul class="mb-0">
                            <li>Use at least 8 characters</li>
                            <li>Include uppercase, lowercase, numbers, and symbols</li>
                            <li>Avoid using personal information</li>
                            <li>Don't reuse passwords from other sites</li>
                            <li>Change password regularly</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(passwordId, eyeId) {
    const passwordField = document.getElementById(passwordId);
    const eyeIcon = document.getElementById(eyeId);
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        passwordField.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}
</script>

<?php include 'footer.php'; ?>
