<?php
require_once 'config.php';

// Security check
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$message = '';

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
    $target_dir = "uploads/profiles/";
    $target_file = $target_dir . "student_" . $student_id . ".png";
    
    // Check if file is an actual image
    $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
    if ($check === false) {
        $message = '<div class="alert alert-danger">File is not an image.</div>';
    } 
    // Check file size (max 2MB)
    elseif ($_FILES["profile_pic"]["size"] > 2000000) {
        $message = '<div class="alert alert-danger">Image size must be less than 2MB.</div>';
    }
    // Allow certain file formats
    else {
        $imageFileType = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
            $message = '<div class="alert alert-danger">Only JPG, JPEG & PNG files are allowed.</div>';
        } else {
            // Convert and save as PNG
            if ($imageFileType == "jpg" || $imageFileType == "jpeg") {
                $image = imagecreatefromjpeg($_FILES["profile_pic"]["tmp_name"]);
            } else {
                $image = imagecreatefrompng($_FILES["profile_pic"]["tmp_name"]);
            }
            
            // Resize image to 300x300
            $resized = imagescale($image, 300, 300);
            
            // Save as PNG
            if (imagepng($resized, $target_file)) {
                // Update database
                $profile_path = "uploads/profiles/student_" . $student_id . ".png";
                $update_sql = "UPDATE students SET profile_pic = '$profile_path' WHERE student_id = '$student_id'";
                mysqli_query($conn, $update_sql);
                
                $message = '<div class="alert alert-success">Profile picture updated successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Failed to upload image.</div>';
            }
            
            // Free memory
            imagedestroy($image);
            imagedestroy($resized);
        }
    }
}

// Get current profile picture
$current_pic_query = "SELECT profile_pic FROM students WHERE student_id = '$student_id'";
$current_result = mysqli_query($conn, $current_pic_query);
$current_data = mysqli_fetch_assoc($current_result);
$current_pic = !empty($current_data['profile_pic']) ? $current_data['profile_pic'] : 'uploads/profiles/default.png';
if (!file_exists($current_pic)) {
    $current_pic = 'uploads/profiles/default.png';
}

$page_title = "Update Profile";
include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>Update Profile Picture</h4>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                
                <div class="row">
                    <div class="col-md-6 text-center mb-4">
                        <h5>Current Picture</h5>
                        <img src="<?php echo $current_pic; ?>" 
                             class="rounded-circle border shadow" 
                             style="width: 200px; height: 200px; object-fit: cover;"
                             alt="Current Profile">
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Upload New Picture</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="profile_pic" class="form-label">Choose Image</label>
                                <input class="form-control" type="file" id="profile_pic" name="profile_pic" accept="image/*" required>
                                <div class="form-text">
                                    Max size: 2MB. Allowed: JPG, JPEG, PNG
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-upload me-2"></i> Upload & Update
                                </button>
                                <a href="student_dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>