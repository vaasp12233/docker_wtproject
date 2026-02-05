<?php
// edit_profile.php
require_once 'config.php';
session_start();

// Security check
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$role = $_SESSION['role'] ?? 'student';

// Get student data
$query = "SELECT * FROM students WHERE student_id = '$student_id'";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

// Get redirect URL based on role
$redirect_url = $role === 'student' ? 'student_dashboard.php' : 'faculty_dashboard.php';
$dashboard_url = $redirect_url;

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - CSE Attendance System</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a0ca3;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --warning: #ff9e00;
            --danger: #f72585;
            --gradient-primary: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            --gradient-secondary: linear-gradient(135deg, #7209b7 0%, #560bad 100%);
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .profile-edit-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .profile-edit-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            max-width: 800px;
            width: 100%;
            animation: cardEntrance 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .profile-header {
            background: var(--gradient-secondary);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }
        
        @keyframes float {
            0% { transform: rotate(0deg) translateY(0); }
            100% { transform: rotate(360deg) translateY(-20px); }
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar i {
            font-size: 3rem;
            color: white;
        }
        
        .profile-body {
            padding: 40px;
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 4px solid var(--secondary);
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-label i {
            color: var(--secondary);
        }
        
        .input-group {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .input-group:focus-within {
            box-shadow: 0 5px 20px rgba(114, 9, 183, 0.2);
            transform: translateY(-2px);
        }
        
        .input-group-text {
            background: var(--gradient-secondary);
            color: white;
            border: none;
            padding: 15px 20px;
            min-width: 50px;
            justify-content: center;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-left: none;
            padding: 15px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: none;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-text {
            margin-top: 8px;
            font-size: 0.9rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .form-text i {
            color: var(--secondary);
        }
        
        .validation-feedback {
            display: none;
            margin-top: 5px;
            font-size: 0.9rem;
            padding: 8px 12px;
            border-radius: 8px;
        }
        
        .is-invalid {
            border-color: var(--danger) !important;
        }
        
        .invalid-feedback {
            display: block;
            color: var(--danger);
            background: rgba(247, 37, 133, 0.1);
            border: 1px solid rgba(247, 37, 133, 0.2);
        }
        
        .is-valid {
            border-color: #4cc9f0 !important;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #e9ecef;
        }
        
        .btn {
            padding: 14px 30px;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: var(--gradient-secondary);
            border: none;
            flex: 1;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.7s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(114, 9, 183, 0.4);
        }
        
        .btn-outline-secondary {
            border: 2px solid #e0e0e0;
            background: white;
            color: #495057;
            flex: 1;
        }
        
        .btn-outline-secondary:hover {
            background: #f8f9fa;
            border-color: #d0d0d0;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--secondary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Toast Notification */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9998;
        }
        
        .toast {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            border: none;
            overflow: hidden;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .toast-success {
            border-left: 5px solid #4cc9f0;
        }
        
        .toast-error {
            border-left: 5px solid #f72585;
        }
        
        .toast-header {
            background: white;
            border: none;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .toast-success .toast-header {
            color: #4cc9f0;
        }
        
        .toast-error .toast-header {
            color: #f72585;
        }
        
        /* Character Counter */
        .char-counter {
            text-align: right;
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .char-counter.warning {
            color: #ff9e00;
        }
        
        .char-counter.danger {
            color: #f72585;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .profile-edit-container {
                padding: 10px;
            }
            
            .profile-header {
                padding: 30px 20px;
            }
            
            .profile-body {
                padding: 25px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <h5 class="text-secondary">Updating Profile...</h5>
        <p class="text-muted">Please wait while we save your changes</p>
    </div>
    
    <div class="profile-edit-container">
        <div class="profile-edit-card">
            <!-- Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php if (!empty($student['profile_pic']) && $student['profile_pic'] !== 'default.png'): ?>
                        <img src="uploads/profiles/<?php echo htmlspecialchars($student['profile_pic']); ?>" 
                             alt="<?php echo htmlspecialchars($student['full_name']); ?>">
                    <?php else: ?>
                        <i class="fas fa-user-edit"></i>
                    <?php endif; ?>
                </div>
                <h3 class="mb-2">Edit Profile</h3>
                <p class="opacity-75">Update your personal information</p>
            </div>
            
            <!-- Body -->
            <div class="profile-body">
                <!-- Student Information -->
                <div class="info-card">
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['full_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Roll Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['roll_number']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Section</span>
                        <span class="info-value badge bg-secondary">Section <?php echo htmlspecialchars($student['section']); ?></span>
                    </div>
                </div>
                
                <!-- Edit Form -->
                <form id="profileForm" novalidate>
                    <!-- Phone Number -->
                    <div class="form-section">
                        <label class="form-label">
                            <i class="fas fa-phone"></i>
                            Phone Number
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-phone-alt"></i>
                            </span>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>"
                                   placeholder="Enter 10-digit phone number"
                                   pattern="[0-9]{10}"
                                   maxlength="10">
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i>
                            Optional - 10 digits without spaces or dashes
                        </div>
                        <div class="validation-feedback" id="phoneFeedback"></div>
                    </div>
                    
                    <!-- Address -->
                    <div class="form-section">
                        <label class="form-label">
                            <i class="fas fa-home"></i>
                            Address
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-map-marker-alt"></i>
                            </span>
                            <textarea class="form-control" 
                                      id="address" 
                                      name="address" 
                                      rows="3"
                                      placeholder="Enter your current address"
                                      maxlength="500"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="char-counter" id="addressCounter">
                            <span id="addressChars">0</span>/500 characters
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i>
                            Optional - Your current residential address
                        </div>
                        <div class="validation-feedback" id="addressFeedback"></div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="<?php echo $dashboard_url; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Form validation and AJAX submission
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('profileForm');
        const phoneInput = document.getElementById('phone');
        const addressInput = document.getElementById('address');
        const addressCounter = document.getElementById('addressCounter');
        const addressChars = document.getElementById('addressChars');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const toastContainer = document.getElementById('toastContainer');
        
        // Initialize character counter
        updateAddressCounter();
        
        // Real-time character counter for address
        addressInput.addEventListener('input', function() {
            updateAddressCounter();
            validateAddress();
        });
        
        // Real-time phone validation
        phoneInput.addEventListener('input', function() {
            validatePhone();
        });
        
        // Format phone input
        phoneInput.addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.keyCode || e.which);
            if (!/^\d+$/.test(char)) {
                e.preventDefault();
            }
        });
        
        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateForm()) {
                submitForm();
            }
        });
        
        function updateAddressCounter() {
            const length = addressInput.value.length;
            addressChars.textContent = length;
            
            // Update color based on length
            if (length > 450) {
                addressCounter.classList.add('danger');
                addressCounter.classList.remove('warning');
            } else if (length > 400) {
                addressCounter.classList.add('warning');
                addressCounter.classList.remove('danger');
            } else {
                addressCounter.classList.remove('warning', 'danger');
            }
        }
        
        function validatePhone() {
            const value = phoneInput.value.trim();
            const feedback = document.getElementById('phoneFeedback');
            
            if (value === '') {
                phoneInput.classList.remove('is-invalid', 'is-valid');
                feedback.style.display = 'none';
                return true;
            }
            
            const phoneRegex = /^\d{10}$/;
            
            if (!phoneRegex.test(value)) {
                phoneInput.classList.add('is-invalid');
                phoneInput.classList.remove('is-valid');
                feedback.textContent = 'Phone number must be exactly 10 digits';
                feedback.className = 'validation-feedback invalid-feedback';
                feedback.style.display = 'block';
                return false;
            } else {
                phoneInput.classList.remove('is-invalid');
                phoneInput.classList.add('is-valid');
                feedback.textContent = 'Valid phone number';
                feedback.className = 'validation-feedback valid-feedback';
                feedback.style.display = 'block';
                setTimeout(() => {
                    feedback.style.display = 'none';
                }, 2000);
                return true;
            }
        }
        
        function validateAddress() {
            const value = addressInput.value.trim();
            const feedback = document.getElementById('addressFeedback');
            
            if (value === '') {
                addressInput.classList.remove('is-invalid', 'is-valid');
                feedback.style.display = 'none';
                return true;
            }
            
            if (value.length > 500) {
                addressInput.classList.add('is-invalid');
                addressInput.classList.remove('is-valid');
                feedback.textContent = 'Address must be less than 500 characters';
                feedback.className = 'validation-feedback invalid-feedback';
                feedback.style.display = 'block';
                return false;
            } else {
                addressInput.classList.remove('is-invalid');
                addressInput.classList.add('is-valid');
                feedback.style.display = 'none';
                return true;
            }
        }
        
        function validateForm() {
            const isPhoneValid = validatePhone();
            const isAddressValid = validateAddress();
            
            return isPhoneValid && isAddressValid;
        }
        
        function submitForm() {
            // Show loading overlay
            loadingOverlay.style.display = 'flex';
            
            // Collect form data
            const formData = new FormData();
            formData.append('phone', phoneInput.value.trim());
            formData.append('address', addressInput.value.trim());
            
            // Send AJAX request
            fetch('update_profile.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                // Hide loading overlay
                loadingOverlay.style.display = 'none';
                
                if (data.success) {
                    if (data.no_changes) {
                        showToast('info', 'No Changes', 'No changes were made to your profile.');
                    } else {
                        showToast('success', 'Success', data.message);
                        
                        // Update UI with new data
                        if (data.data.phone) {
                            phoneInput.value = data.data.phone;
                            phoneInput.classList.add('is-valid');
                        }
                        
                        if (data.data.address) {
                            addressInput.value = data.data.address;
                            addressInput.classList.add('is-valid');
                            updateAddressCounter();
                        }
                        
                        // Redirect after delay if needed
                        if (data.redirect !== false) {
                            setTimeout(() => {
                                window.location.href = '<?php echo $dashboard_url; ?>';
                            }, 2000);
                        }
                    }
                } else {
                    showToast('error', 'Error', data.message);
                    
                    // Show specific field errors
                    if (data.errors) {
                        for (const field in data.errors) {
                            const input = document.getElementById(field);
                            const feedback = document.getElementById(field + 'Feedback');
                            
                            if (input && feedback) {
                                input.classList.add('is-invalid');
                                feedback.textContent = data.errors[field];
                                feedback.className = 'validation-feedback invalid-feedback';
                                feedback.style.display = 'block';
                            }
                        }
                    }
                }
            })
            .catch(error => {
                loadingOverlay.style.display = 'none';
                showToast('error', 'Network Error', 'Failed to update profile. Please check your connection.');
                console.error('Error:', error);
            });
        }
        
        function showToast(type, title, message) {
            const toastId = 'toast-' + Date.now();
            const toastClass = type === 'success' ? 'toast-success' : 'toast-error';
            const toastIcon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            
            const toastHTML = `
                <div class="toast ${toastClass}" id="${toastId}" role="alert">
                    <div class="toast-header">
                        <i class="fas fa-${toastIcon} me-2"></i>
                        <strong class="me-auto">${title}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            // Auto-remove after hide
            toastElement.addEventListener('hidden.bs.toast', function () {
                toastElement.remove();
            });
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                toast.hide();
            }, 5000);
        }
        
        // Check for session messages
        <?php if (isset($_SESSION['profile_message'])): ?>
            const msg = <?php echo json_encode($_SESSION['profile_message']); ?>;
            const msgType = msg.type || 'success';
            const msgText = msg.text || msg;
            const msgIcon = msg.icon || 'info-circle';
            
            showToast(msgType, 
                msgType === 'success' ? 'Success' : 'Error', 
                msgText);
            <?php unset($_SESSION['profile_message']); ?>
        <?php endif; ?>
    });
    </script>
</body>
</html>