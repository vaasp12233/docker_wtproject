<?php
// student_profile.php - Fixed for Render + Aiven + GitHub

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
$current_pic = 'default.png'; // Start with default

// ==================== Define upload directory ====================
$upload_dir = "uploads/profiles/";
$default_image = "default.png";

// Create uploads directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    // Create a .htaccess file to protect the directory
    file_put_contents($upload_dir . '.htaccess', "Order Allow,Deny\nDeny from all");
}

// ==================== Get current profile picture ====================
// First, check database field names
$check_columns_query = "SHOW COLUMNS FROM students LIKE '%profile%'";
$check_result = mysqli_query($conn, $check_columns_query);
$profile_column = 'profile_pic'; // Default
if ($check_result && mysqli_num_rows($check_result) > 0) {
    $row = mysqli_fetch_assoc($check_result);
    $profile_column = $row['Field'];
}

// Get current picture from database
$current_pic_query = "SELECT $profile_column FROM students WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $current_pic_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $db_profile_pic);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    if (!empty($db_profile_pic)) {
        // Extract just the filename from the path
        $db_profile_pic = basename($db_profile_pic);
        
        // Check if file exists in uploads directory
        $file_path = $upload_dir . $db_profile_pic;
        if (file_exists($file_path) && $db_profile_pic !== $default_image) {
            $current_pic = $db_profile_pic;
        }
    }
}

// ==================== Handle profile picture upload ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
    // Check if file was uploaded
    if ($_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['profile_pic']['name'];
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_size = $_FILES['profile_pic']['size'];
        $file_error = $_FILES['profile_pic']['error'];
        
        // Get file extension
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Validate file
        if (!in_array($file_ext, $allowed_ext)) {
            $message = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
            $message_type = 'danger';
        } elseif ($file_size > 2097152) { // 2MB
            $message = 'File size must be less than 2MB.';
            $message_type = 'danger';
        } elseif (!getimagesize($file_tmp)) {
            $message = 'File is not a valid image.';
            $message_type = 'danger';
        } else {
            // Generate unique filename
            $new_filename = "student_" . $student_id . "_" . time() . "." . $file_ext;
            $destination = $upload_dir . $new_filename;
            
            // Process image based on type
            try {
                switch ($file_ext) {
                    case 'jpg':
                    case 'jpeg':
                        $image = imagecreatefromjpeg($file_tmp);
                        break;
                    case 'png':
                        $image = imagecreatefrompng($file_tmp);
                        break;
                    case 'gif':
                        $image = imagecreatefromgif($file_tmp);
                        break;
                    default:
                        throw new Exception('Unsupported image format');
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
                
                // Fill with white background (for transparent PNGs/GIFs)
                $white = imagecolorallocate($thumb, 255, 255, 255);
                imagefill($thumb, 0, 0, $white);
                
                // Calculate resize to fit square
                if ($width > $height) {
                    // Landscape - crop width
                    $src_x = ($width - $height) / 2;
                    $src_y = 0;
                    $src_size = $height;
                } else {
                    // Portrait or square - crop height
                    $src_x = 0;
                    $src_y = ($height - $width) / 2;
                    $src_size = $width;
                }
                
                // Resize and crop to square
                imagecopyresampled($thumb, $image, 0, 0, $src_x, $src_y, 
                                  $thumb_size, $thumb_size, $src_size, $src_size);
                
                // Save the processed image
                switch ($file_ext) {
                    case 'jpg':
                    case 'jpeg':
                        imagejpeg($thumb, $destination, 85); // 85% quality
                        break;
                    case 'png':
                        imagepng($thumb, $destination, 8); // 8 = medium compression
                        break;
                    case 'gif':
                        imagegif($thumb, $destination);
                        break;
                }
                
                // Clean up memory
                imagedestroy($image);
                imagedestroy($thumb);
                
                // Delete old profile picture if it's not the default
                if ($current_pic !== $default_image && $current_pic !== $new_filename) {
                    $old_file = $upload_dir . $current_pic;
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                // Update database with just filename (not full path)
                $update_sql = "UPDATE students SET $profile_column = ? WHERE student_id = ?";
                $stmt_update = mysqli_prepare($conn, $update_sql);
                
                if ($stmt_update) {
                    mysqli_stmt_bind_param($stmt_update, "ss", $new_filename, $student_id);
                    
                    if (mysqli_stmt_execute($stmt_update)) {
                        $current_pic = $new_filename;
                        $message = 'Profile picture updated successfully!';
                        $message_type = 'success';
                        
                        // Update session
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
                
            } catch (Exception $e) {
                $message = 'Error processing image: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } else {
        // Handle upload errors
        switch ($_FILES['profile_pic']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $message = 'File size is too large. Maximum 2MB allowed.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = 'File was only partially uploaded.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = 'No file was selected.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = 'Missing temporary folder.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = 'Failed to write file to disk.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = 'File upload stopped by extension.';
                break;
            default:
                $message = 'Unknown upload error.';
        }
        $message_type = 'danger';
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

// Include header after processing
include 'header.php';
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
        
        .progress-bar {
            width: 0%;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- Header is included from header.php -->
    
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
                                    <img src="<?php echo $upload_dir . htmlspecialchars($current_pic) . '?v=' . time(); ?>" 
                                         class="profile-img rounded-circle" 
                                         alt="Current Profile Picture"
                                         id="currentImage"
                                         onerror="this.src='<?php echo $upload_dir . $default_image; ?>'">
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
                                                <p class="mb-0 small text-muted">Max size: 2MB • JPG, PNG, GIF, JPEG</p>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Upload Progress -->
                                    <div class="mb-3" id="progressContainer" style="display: none;">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span id="progressText">Uploading...</span>
                                            <span id="progressPercent">0%</span>
                                        </div>
                                        <div class="progress" style="height: 10px;">
                                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                                 role="progressbar" style="width: 0%"></div>
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
                                                <li>Accepted formats: JPG, JPEG, PNG, GIF</li>
                                                <li>Image will be automatically cropped to square</li>
                                                <li>Recommended: Square image, clear face visible</li>
                                                <li>Your old image will be replaced</li>
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

    <!-- Footer is included from footer.php -->
    <?php include 'footer.php'; ?>
    
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
    
    // Form validation and upload simulation
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        const fileInput = document.getElementById('profile_pic');
        const submitBtn = document.getElementById('submitBtn');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressPercent = document.getElementById('progressPercent');
        const progressText = document.getElementById('progressText');
        
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
        
        // Show progress bar
        progressContainer.style.display = 'block';
        progressText.textContent = 'Processing image...';
        
        // Simulate progress
        let progress = 0;
        const interval = setInterval(() => {
            progress += 5;
            if (progress > 90) {
                progress = 90;
                clearInterval(interval);
            }
            progressBar.style.width = progress + '%';
            progressPercent.textContent = progress + '%';
        }, 100);
        
        // Show loading state on button
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
        submitBtn.disabled = true;
    });
    
    // Preview current image in modal
    document.getElementById('currentImage').addEventListener('click', function() {
        const imgSrc = this.src;
        const modalHtml = `
            <div class="modal fade" id="imageModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Profile Picture Preview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${imgSrc}" class="img-fluid rounded" alt="Profile Preview" 
                                 style="max-height: 70vh; object-fit: contain;">
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
    
    // Check if there are session messages to display
    <?php if (isset($_SESSION['profile_message'])): ?>
        // Show session message if exists
        const sessionMessage = '<?php echo addslashes($_SESSION["profile_message"]); ?>';
        const sessionType = '<?php echo $_SESSION["profile_message_type"]; ?>';
        
        if (sessionMessage) {
            // Create alert
            const alertHtml = `
                <div class="alert alert-${sessionType} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${sessionType === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    ${sessionMessage}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // Add to page
            const cardBody = document.querySelector('.card-body');
            if (cardBody) {
                cardBody.insertAdjacentHTML('afterbegin', alertHtml);
            }
            
            // Clear session storage
            <?php 
            unset($_SESSION['profile_message']);
            unset($_SESSION['profile_message_type']);
            ?>
        }
    <?php endif; ?>
    </script>
</body>
</html>
<?php
// Clean up and flush output
if (ob_get_level() > 0) {
    ob_end_flush();
}
