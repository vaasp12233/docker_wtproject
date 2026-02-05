<?php
// student_profile.php - Fixed for Render + Aiven

// ==================== CRITICAL: Start output buffering ====================
if (!ob_get_level()) {
    ob_start();
}

// ==================== Configure session for Render ====================
ini_set('session.save_path', '/tmp');
ini_set('session.cookie_lifetime', 86400);

// ==================== Start session ====================
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ==================== Include database config ====================
require_once 'config.php';

// ==================== Security check ====================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Clean buffer before redirect
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Role check ====================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    // Clean buffer before redirect
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Get student ID ====================
$student_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null;
if (!$student_id) {
    // Clean buffer before redirect
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

// ==================== Initialize variables ====================
$message = '';
$message_type = ''; // success, danger, warning, info
$current_pic = 'uploads/profiles/default.png';

// ==================== Get current profile picture ====================
$current_pic_query = "SELECT profile_pic FROM students WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $current_pic_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $db_profile_pic);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    if (!empty($db_profile_pic)) {
        $current_pic = $db_profile_pic;
        // Check if file exists, if not use default
        if (!file_exists($current_pic)) {
            $current_pic = 'uploads/profiles/default.png';
        }
    }
}

// ==================== Handle profile picture upload ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
    $target_dir = "uploads/profiles/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . "student_" . $student_id . ".png";
    
    // Check if file was uploaded without errors
    if ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
        $message = 'File upload error: ' . $_FILES['profile_pic']['error'];
        $message_type = 'danger';
    } 
    // Check if file is an actual image
    elseif (!getimagesize($_FILES["profile_pic"]["tmp_name"])) {
        $message = 'File is not a valid image.';
        $message_type = 'danger';
    } 
    // Check file size (max 2MB = 2,000,000 bytes)
    elseif ($_FILES["profile_pic"]["size"] > 2000000) {
        $message = 'Image size must be less than 2MB.';
        $message_type = 'danger';
    }
    // Allow certain file formats
    else {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $file_type = $_FILES["profile_pic"]["type"];
        
        if (!in_array($file_type, $allowed_types)) {
            $message = 'Only JPG, JPEG & PNG files are allowed.';
            $message_type = 'danger';
        } else {
            try {
                // Load image based on type
                $tmp_name = $_FILES["profile_pic"]["tmp_name"];
                
                if ($file_type == 'image/jpeg' || $file_type == 'image/jpg') {
                    $image = imagecreatefromjpeg($tmp_name);
                } elseif ($file_type == 'image/png') {
                    $image = imagecreatefrompng($tmp_name);
                }
                
                if (!$image) {
                    throw new Exception('Failed to create image from file.');
                }
                
                // Get original dimensions
                $width = imagesx($image);
                $height = imagesy($image);
                
                // Create square thumbnail (300x300)
                $thumb_size = 300;
                $thumb = imagecreatetruecolor($thumb_size, $thumb_size);
                
                // Fill with white background (for transparent PNGs)
                $white = imagecolorallocate($thumb, 255, 255, 255);
                imagefill($thumb, 0, 0, $white);
                
                // Calculate resize
                $src_x = 0;
                $src_y = 0;
                $src_w = $width;
                $src_h = $height;
                
                // Crop to square if needed
                if ($width > $height) {
                    $src_x = floor(($width - $height) / 2);
                    $src_w = $height;
                } elseif ($height > $width) {
                    $src_y = floor(($height - $width) / 2);
                    $src_h = $width;
                }
                
                // Resize and crop
                imagecopyresampled($thumb, $image, 0, 0, $src_x, $src_y, 
                                  $thumb_size, $thumb_size, $src_w, $src_h);
                
                // Save as PNG
                if (imagepng($thumb, $target_file, 9)) { // 9 = maximum compression
                    // Update database
                    $update_sql = "UPDATE students SET profile_pic = ? WHERE student_id = ?";
                    $stmt_update = mysqli_prepare($conn, $update_sql);
                    
                    if ($stmt_update) {
                        mysqli_stmt_bind_param($stmt_update, "ss", $target_file, $student_id);
                        
                        if (mysqli_stmt_execute($stmt_update)) {
                            $message = 'Profile picture updated successfully!';
                            $message_type = 'success';
                            $current_pic = $target_file; // Update current picture
                            
                            // Update session if needed
                            $_SESSION['profile_updated'] = true;
                        } else {
                            $message = 'Database update failed: ' . mysqli_error($conn);
                            $message_type = 'warning';
                        }
                        mysqli_stmt_close($stmt_update);
                    } else {
                        $message = 'Failed to prepare database update.';
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'Failed to save image.';
                    $message_type = 'danger';
                }
                
                // Clean up
                imagedestroy($image);
                imagedestroy($thumb);
                
            } catch (Exception $e) {
                $message = 'Error processing image: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
    
    // Store message in session for persistence if redirecting
    $_SESSION['profile_message'] = $message;
    $_SESSION['profile_message_type'] = $message_type;
}

// ==================== Clean buffer before output ====================
if (ob_get_length() > 0 && !headers_sent()) {
    ob_end_clean();
    ob_start();
}

// ==================== Set page title ====================
$page_title = "Update Profile";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - CSE Attendance System</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding-bottom: 50px;
        }
        
        .card {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            border-radius: 20px 20px 0 0 !important;
            padding: 20px;
        }
        
        .profile-img {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .btn {
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #3a0ca3 0%, #4361ee 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
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
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: block;
            padding: 15px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-input-label:hover {
            background: #e9ecef;
            border-color: #4361ee;
        }
        
        .preview-container {
            position: relative;
            display: inline-block;
        }
        
        .preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .preview-container:hover .preview-overlay {
            opacity: 1;
        }
        
        .preview-overlay i {
            color: white;
            font-size: 2rem;
        }
        
        /* Dark mode support */
        body.dark-mode {
            background: linear-gradient(135deg, #121212 0%, #1e1e1e 100%);
            color: #e0e0e0;
        }
        
        body.dark-mode .card {
            background: #1e1e1e;
            color: #e0e0e0;
        }
        
        body.dark-mode .form-control {
            background: #2d2d2d;
            color: #e0e0e0;
            border-color: #444;
        }
        
        body.dark-mode .file-input-label {
            background: #2d2d2d;
            border-color: #444;
            color: #e0e0e0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
        <div class="container">
            <a class="navbar-brand" href="student_dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                CSE Attendance - Profile Update
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="student_dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card shadow-lg">
                    <div class="card-header text-white">
                        <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>Update Profile Picture</h4>
                    </div>
                    <div class="card-body p-4">
                        <!-- Display Messages -->
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?php 
                                    echo $message_type == 'success' ? 'check-circle' : 
                                         ($message_type == 'danger' ? 'exclamation-triangle' : 
                                         ($message_type == 'warning' ? 'exclamation-circle' : 'info-circle')); 
                                ?> me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Current Picture -->
                            <div class="col-md-6 text-center mb-4">
                                <h5 class="mb-4">Current Picture</h5>
                                <div class="preview-container">
                                    <img src="<?php echo htmlspecialchars($current_pic); ?>?v=<?php echo time(); ?>" 
                                         class="profile-img rounded-circle" 
                                         alt="Current Profile Picture"
                                         id="currentImage">
                                    <div class="preview-overlay">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                </div>
                                <p class="mt-3 text-muted">
                                    <small>Click on the image to preview</small>
                                </p>
                            </div>
                            
                            <!-- Upload Form -->
                            <div class="col-md-6">
                                <h5 class="mb-4">Upload New Picture</h5>
                                <form method="POST" enctype="multipart/form-data" id="profileForm">
                                    <div class="mb-4">
                                        <label class="form-label mb-3">Select Image File</label>
                                        <div class="file-input-wrapper">
                                            <input class="form-control" 
                                                   type="file" 
                                                   id="profile_pic" 
                                                   name="profile_pic" 
                                                   accept="image/*" 
                                                   required
                                                   onchange="previewImage(event)">
                                            <label for="profile_pic" class="file-input-label">
                                                <i class="fas fa-cloud-upload-alt fa-2x mb-3"></i>
                                                <h6>Click to choose image</h6>
                                                <p class="mb-0 small text-muted">or drag and drop here</p>
                                                <p class="mb-0 small text-muted">Max size: 2MB • JPG, PNG, JPEG</p>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Image Preview -->
                                    <div class="mb-4 text-center" id="imagePreviewContainer" style="display: none;">
                                        <h6 class="mb-3">Preview</h6>
                                        <img id="imagePreview" 
                                             class="rounded-circle border" 
                                             style="width: 150px; height: 150px; object-fit: cover;"
                                             alt="Image Preview">
                                    </div>
                                    
                                    <!-- Image Info -->
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <div>
                                            <strong>Image Requirements:</strong>
                                            <ul class="mb-0">
                                                <li>Maximum file size: 2MB</li>
                                                <li>Accepted formats: JPG, JPEG, PNG</li>
                                                <li>Image will be cropped to square</li>
                                                <li>Recommended: Square image, clear face visible</li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Buttons -->
                                    <div class="d-grid gap-3 mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                            <i class="fas fa-upload me-2"></i> Upload & Update Profile
                                        </button>
                                        <a href="student_dashboard.php" class="btn btn-secondary btn-lg">
                                            <i class="fas fa-arrow-left me-2"></i> Cancel & Return to Dashboard
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-5 py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h6>CSE Attendance System</h6>
                    <p class="mb-0 small">Department of Computer Science</p>
                    <p class="mb-0 small">Student Profile Management</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 small">&copy; <?php echo date('Y'); ?> CSE Department</p>
                    <p class="mb-0 small">Student ID: <?php echo htmlspecialchars($student_id); ?></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Image preview function
    function previewImage(event) {
        const input = event.target;
        const preview = document.getElementById('imagePreview');
        const container = document.getElementById('imagePreviewContainer');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                container.style.display = 'block';
            }
            
            reader.readAsDataURL(input.files[0]);
        } else {
            container.style.display = 'none';
        }
    }
    
    // Form validation
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        const fileInput = document.getElementById('profile_pic');
        const submitBtn = document.getElementById('submitBtn');
        
        if (!fileInput.files || fileInput.files.length === 0) {
            e.preventDefault();
            alert('Please select an image file.');
            return;
        }
        
        const file = fileInput.files[0];
        const maxSize = 2 * 1024 * 1024; // 2MB in bytes
        
        if (file.size > maxSize) {
            e.preventDefault();
            alert('File size must be less than 2MB.');
            return;
        }
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Uploading...';
        submitBtn.disabled = true;
    });
    
    // Preview current image in modal
    document.getElementById('currentImage').addEventListener('click', function() {
        const imgSrc = this.src;
        const modalHtml = `
            <div class="modal fade" id="imageModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Profile Picture Preview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${imgSrc}" class="img-fluid rounded" alt="Profile Preview">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('imageModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
        modal.show();
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    </script>
</body>
</html>
<?php
// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
