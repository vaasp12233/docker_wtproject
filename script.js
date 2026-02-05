// Updated AJAX handler for improved mark_attendance.php
function markAttendance(studentIdentifier) {
    // Visual feedback - pulse effect
    const scannerContainer = document.querySelector('.scanner-container');
    scannerContainer.style.animation = 'pulse 0.5s ease';
    setTimeout(() => {
        scannerContainer.style.animation = '';
    }, 500);
    
    // Show processing state
    const processIndicator = document.createElement('div');
    processIndicator.className = 'process-indicator';
    processIndicator.innerHTML = `
        <div class="process-spinner"></div>
        <div class="process-text">Processing...</div>
    `;
    scannerContainer.appendChild(processIndicator);
    
    // Disable manual input during processing
    document.getElementById('manualStudentId').disabled = true;
    document.querySelector('#manualStudentId + button').disabled = true;
    
    const formData = new FormData();
    formData.append('session_id', sessionId);
    formData.append('student_identifier', studentIdentifier);
    
    fetch('mark_attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        // Remove process indicator
        processIndicator.remove();
        
        // Re-enable inputs
        document.getElementById('manualStudentId').disabled = false;
        document.querySelector('#manualStudentId + button').disabled = false;
        
        if (data.success) {
            handleSuccessResponse(data);
        } else {
            handleErrorResponse(data);
        }
    })
    .catch(error => {
        // Remove process indicator
        processIndicator.remove();
        
        // Re-enable inputs
        document.getElementById('manualStudentId').disabled = false;
        document.querySelector('#manualStudentId + button').disabled = false;
        
        showCustomError({
            title: 'Network Error',
            message: 'Failed to connect to server.',
            type: 'error',
            icon: 'wifi-slash'
        });
        console.error('Network error:', error);
    });
}

function handleSuccessResponse(data) {
    // Play success sound
    playSound('success');
    
    // Show success animation
    showConfetti();
    
    // Create success card
    const successCard = createSuccessCard(data);
    
    // Add to scan log with animation
    addToScanLog(data);
    
    // Update statistics
    updateStats({
        total_students: data.stats?.session_attendance_count || 0,
        present_count: data.stats?.session_attendance_count || 0
    });
    
    // Show success notification
    showNotification({
        title: 'Success!',
        message: data.message,
        type: 'success',
        icon: data.icon,
        duration: 3000,
        data: data
    });
    
    // Auto-resume scanner
    setTimeout(() => {
        if (html5QrCode && scannerActive) {
            html5QrCode.resume();
        }
    }, data.next_action?.auto_scan_resume || 1500);
}

function handleErrorResponse(data) {
    // Play error sound
    playSound('error');
    
    // Show error notification
    showNotification({
        title: data.type === 'warning' ? 'Warning' : 'Error',
        message: data.message,
        details: data.details,
        type: data.type || 'error',
        icon: data.icon || 'exclamation-circle',
        duration: 4000,
        suggestion: data.suggestion
    });
    
    // Resume scanner after delay for errors
    setTimeout(() => {
        if (html5QrCode && scannerActive) {
            html5QrCode.resume();
        }
    }, 2000);
}

function createSuccessCard(data) {
    const card = document.createElement('div');
    card.className = 'success-card';
    card.innerHTML = `
        <div class="success-card-header">
            <div class="success-icon">
                <i class="fas fa-${data.icon}"></i>
            </div>
            <div class="success-title">${data.message}</div>
            <div class="success-subtitle">${data.subject?.name || 'Unknown Subject'}</div>
        </div>
        <div class="success-card-body">
            <div class="student-info">
                <div class="student-photo">
                    <img src="uploads/profiles/${data.student?.photo || 'default.png'}" 
                         alt="${data.student?.name}" 
                         onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(data.student?.name)}&background=random&color=fff&size=100'">
                </div>
                <div class="student-details">
                    <h5>${data.student?.name}</h5>
                    <div class="student-meta">
                        <span class="badge roll-badge">${data.student?.roll_number}</span>
                        <span class="badge section-badge">Section ${data.student?.section}</span>
                        <span class="badge year-badge">Year ${data.student?.year}</span>
                    </div>
                </div>
            </div>
            <div class="attendance-details">
                <div class="detail-item">
                    <i class="fas fa-star"></i>
                    <span>+${data.attendance?.points} Points</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <span>${data.attendance?.scan_time}</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-percentage"></i>
                    <span>${data.stats?.attendance_percentage || 0}% Attendance</span>
                </div>
            </div>
        </div>
        <div class="success-card-footer">
            <small class="text-muted">
                Session: ${data.session?.section} | ${data.attendance?.scan_date}
            </small>
        </div>
    `;
    
    // Add to success container
    const container = document.getElementById('successCardsContainer') || createSuccessContainer();
    container.prepend(card);
    
    // Remove after 5 seconds
    setTimeout(() => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(-20px)';
        setTimeout(() => card.remove(), 300);
    }, 5000);
    
    return card;
}

function showNotification(options) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${options.type}`;
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas fa-${options.icon}"></i>
        </div>
        <div class="notification-content">
            <div class="notification-title">${options.title}</div>
            <div class="notification-message">${options.message}</div>
            ${options.details ? `<div class="notification-details">${options.details}</div>` : ''}
            ${options.suggestion ? `<div class="notification-suggestion"><small><i class="fas fa-lightbulb"></i> ${options.suggestion}</small></div>` : ''}
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    const container = document.getElementById('notificationContainer') || createNotificationContainer();
    container.appendChild(notification);
    
    // Auto-remove after duration
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, options.duration || 3000);
}

// Add these CSS styles to your scanner page
const additionalStyles = `
    /* Success Card */
    .success-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 10px 30px rgba(76, 201, 240, 0.2);
        border-left: 5px solid #4cc9f0;
        animation: slideIn 0.5s ease;
        transition: all 0.3s ease;
    }
    
    .success-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(76, 201, 240, 0.3);
    }
    
    .success-card-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .success-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #4cc9f0 0%, #4895ef 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }
    
    .success-title {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .success-subtitle {
        font-size: 0.9rem;
        color: #6c757d;
    }
    
    .student-info {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .student-photo {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid #4cc9f0;
    }
    
    .student-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .student-meta {
        display: flex;
        gap: 8px;
        margin-top: 5px;
        flex-wrap: wrap;
    }
    
    .attendance-details {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        background: rgba(76, 201, 240, 0.1);
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 15px;
    }
    
    .detail-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .detail-item i {
        font-size: 1.5rem;
        color: #4cc9f0;
        margin-bottom: 5px;
    }
    
    /* Notifications */
    .notification-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: 400px;
    }
    
    .notification {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: flex-start;
        gap: 15px;
        animation: slideInRight 0.5s ease;
        transition: all 0.3s ease;
        border-left: 5px solid;
    }
    
    .notification-success {
        border-left-color: #4cc9f0;
    }
    
    .notification-error {
        border-left-color: #f72585;
    }
    
    .notification-warning {
        border-left-color: #ff9e00;
    }
    
    .notification-info {
        border-left-color: #4361ee;
    }
    
    .notification-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    
    .notification-success .notification-icon {
        background: rgba(76, 201, 240, 0.1);
        color: #4cc9f0;
    }
    
    .notification-error .notification-icon {
        background: rgba(247, 37, 133, 0.1);
        color: #f72585;
    }
    
    .notification-warning .notification-icon {
        background: rgba(255, 158, 0, 0.1);
        color: #ff9e00;
    }
    
    .notification-info .notification-icon {
        background: rgba(67, 97, 238, 0.1);
        color: #4361ee;
    }
    
    .notification-content {
        flex: 1;
    }
    
    .notification-title {
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .notification-message {
        color: #495057;
        margin-bottom: 5px;
    }
    
    .notification-details {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 5px;
    }
    
    .notification-suggestion {
        color: #7209b7;
        font-size: 0.85rem;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 5px;
        transition: color 0.3s;
    }
    
    .notification-close:hover {
        color: #f72585;
    }
    
    /* Process Indicator */
    .process-indicator {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.95);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: 20px;
    }
    
    .process-spinner {
        width: 60px;
        height: 60px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #4361ee;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 15px;
    }
    
    .process-text {
        font-weight: 600;
        color: #4361ee;
    }
    
    /* Animations */
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(67, 97, 238, 0.7); }
        70% { box-shadow: 0 0 0 20px rgba(67, 97, 238, 0); }
        100% { box-shadow: 0 0 0 0 rgba(67, 97, 238, 0); }
    }
    
    /* Confetti Animation */
    .confetti-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 1000;
    }
    
    .confetti {
        position: absolute;
        width: 10px;
        height: 10px;
        background: var(--confetti-color, #4cc9f0);
        border-radius: 50%;
        animation: confettiFall 2s linear forwards;
    }
    
    @keyframes confettiFall {
        0% {
            transform: translateY(-100px) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(100vh) rotate(360deg);
            opacity: 0;
        }
    }
    
    /* Badge Styles */
    .badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .roll-badge {
        background: rgba(114, 9, 183, 0.1);
        color: #7209b7;
    }
    
    .section-badge {
        background: rgba(67, 97, 238, 0.1);
        color: #4361ee;
    }
    
    .year-badge {
        background: rgba(255, 158, 0, 0.1);
        color: #ff9e00;
    }
`;

// Add these helper functions
function createSuccessContainer() {
    const container = document.createElement('div');
    container.id = 'successCardsContainer';
    container.className = 'success-container';
    container.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        width: 350px;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
        z-index: 999;
    `;
    document.body.appendChild(container);
    return container;
}

function createNotificationContainer() {
    const container = document.createElement('div');
    container.id = 'notificationContainer';
    container.className = 'notification-container';
    document.body.appendChild(container);
    return container;
}

function showConfetti() {
    const container = document.createElement('div');
    container.className = 'confetti-container';
    
    const colors = ['#4361ee', '#7209b7', '#4cc9f0', '#ff9e00', '#f72585'];
    
    for (let i = 0; i < 100; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.cssText = `
            left: ${Math.random() * 100}%;
            background: ${colors[Math.floor(Math.random() * colors.length)]};
            animation-delay: ${Math.random() * 1}s;
            width: ${Math.random() * 10 + 5}px;
            height: ${Math.random() * 10 + 5}px;
        `;
        container.appendChild(confetti);
    }
    
    document.body.appendChild(container);
    
    setTimeout(() => {
        container.remove();
    }, 2000);
}