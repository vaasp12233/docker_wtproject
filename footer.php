<?php
// Common footer for all pages
?>



    </div> <!-- End container -->

    <footer class="mt-5 py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>CSE Attendance System</h5>
                    <p class="mb-0">Department of Computer Science RK Valley</p>
                    <p class="mb-0">Automated QR-based attendance management system</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">
                        &copy; <?php echo date('Y'); ?> CSE Department RK Valley. All rights reserved.
                    </p>
                    <p class="mb-0">
                        <i class="fas fa-envelope me-1"></i> cse@rguktrkv.ac.in
                    </p>
                    <!-- LIVE TIME ADDED HERE -->
                    <p class="mb-0 mt-2">
                        <i class="fas fa-clock me-1"></i>
                        <span id="liveTime" style="font-family: monospace; font-weight: 500;">
                            Loading time...
                        </span>
                    </p>
                    <!-- Current Date -->
                    <p class="mb-0">
                        <i class="fas fa-calendar-day me-1"></i>
                        <span id="currentDate">
                            <?php echo date('l, F j, Y'); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php 
    // Ensure custom_scripts variable exists before trying to echo it
    if (isset($custom_scripts) && !empty($custom_scripts)): 
        echo $custom_scripts;
    endif; 
    ?>
    
    <!-- Live Time Script -->
    <script>
    // Function to update live time
    function updateLiveTime() {
        const now = new Date();
        
        // Format time as HH:MM:SS AM/PM
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        
        // Convert to 12-hour format
        hours = hours % 12;
        hours = hours ? hours : 12; // 0 should be 12
        
        // Add leading zeros
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        
        // Format the time string
        const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
        
        // Update the element
        document.getElementById('liveTime').textContent = timeString;
        
        // Update the date if it's a new day
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        
        // Optional: Add a blinking colon effect
        const colonElement = document.getElementById('liveTime');
        if (seconds % 2 === 0) {
            colonElement.style.opacity = '1';
        } else {
            colonElement.style.opacity = '0.9';
        }
    }
    
    // Start updating time immediately and every second
    document.addEventListener('DOMContentLoaded', function() {
        updateLiveTime();
        setInterval(updateLiveTime, 1000);
    });
    </script>
    
    <!-- Google Translate Widget -->
    <div id="google_translate_element" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;"></div>
    
    <script type="text/javascript">
    function googleTranslateElementInit() {
        if (typeof google === 'undefined' || !google.translate) {
            console.error('Google Translate API not loaded');
            return;
        }
        
        try {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,hi,te',
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
                autoDisplay: false
            }, 'google_translate_element');
            
            // Replace Google's button with our clean circular button
            setTimeout(function() {
                const googleBtn = document.querySelector('.goog-te-gadget-simple');
                if (!googleBtn) {
                    console.warn('Google Translate button not found');
                    return;
                }
                
                // Hide Google's button but keep functionality
                googleBtn.style.cssText = `
                    opacity: 0 !important;
                    position: absolute !important;
                    width: 1px !important;
                    height: 1px !important;
                    overflow: hidden !important;
                    clip: rect(0,0,0,0) !important;
                    border: 0 !important;
                    margin: 0 !important;
                    padding: 0 !important;
                `;
                
                // Remove any existing custom button to prevent duplicates
                const existingBtn = document.querySelector('.translate-circle-btn');
                if (existingBtn) {
                    existingBtn.remove();
                }
                
                // Create our clean circular button
                const customBtn = document.createElement('button');
                customBtn.className = 'translate-circle-btn';
                customBtn.type = 'button';
                customBtn.setAttribute('aria-label', 'Translate page');
                customBtn.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12.87 15.07L10.33 12.56L10.36 12.53C12.1 10.59 13.34 8.36 14.07 6H17V4H10V2H8V4H1V6H12.17C11.5 7.92 10.44 9.75 9 11.35C8.07 10.32 7.3 9.19 6.69 8H4.69C5.42 9.63 6.42 11.17 7.67 12.56L2.58 17.58L4 19L9 14L12.11 17.11L12.87 15.07ZM18.5 10H16.5L12 22H14L15.12 19H19.87L21 22H23L18.5 10ZM15.88 17L17.5 12.67L19.12 17H15.88Z" fill="white"/>
                    </svg>
                `;
                
                customBtn.style.cssText = `
                    position: fixed;
                    bottom: 25px;
                    right: 25px;
                    width: 60px;
                    height: 60px;
                    background: linear-gradient(135deg, #1a73e8, #0d62d9);
                    border: none;
                    border-radius: 50%;
                    box-shadow: 0 4px 15px rgba(26, 115, 232, 0.4);
                    cursor: pointer;
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                `;
                
                document.body.appendChild(customBtn);
                
                // Click handler - trigger Google's hidden button
                customBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (googleBtn) {
                        googleBtn.click();
                    }
                    
                    // Click animation
                    this.style.transform = 'scale(0.9)';
                    this.style.boxShadow = '0 2px 10px rgba(26, 115, 232, 0.3)';
                    
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                        this.style.boxShadow = '0 4px 15px rgba(26, 115, 232, 0.4)';
                    }, 150);
                });
                
                // Hover effects
                customBtn.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1) rotate(5deg)';
                    this.style.boxShadow = '0 6px 20px rgba(26, 115, 232, 0.5)';
                    this.style.background = 'linear-gradient(135deg, #0d62d9, #0a56c4)';
                });
                
                customBtn.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0deg)';
                    this.style.boxShadow = '0 4px 15px rgba(26, 115, 232, 0.4)';
                    this.style.background = 'linear-gradient(135deg, #1a73e8, #0d62d9)';
                });
                
                // Add tooltip
                const tooltip = document.createElement('div');
                tooltip.className = 'translate-tooltip';
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
                customBtn.appendChild(tooltip);
                
                // Show/hide tooltip
                customBtn.addEventListener('mouseenter', function() {
                    tooltip.style.opacity = '1';
                    tooltip.style.transform = 'translateY(0)';
                });
                
                customBtn.addEventListener('mouseleave', function() {
                    tooltip.style.opacity = '0';
                    tooltip.style.transform = 'translateY(10px)';
                });
                
            }, 500); // Increased timeout to ensure Google Translate is loaded
        } catch (error) {
            console.error('Error initializing Google Translate:', error);
        }
    }
    
    // Load Google Translate script
    function loadGoogleTranslate() {
        if (document.querySelector('script[src*="translate.google.com"]')) {
            return;
        }
        
        const script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
        script.async = true;
        script.onerror = function() {
            console.error('Failed to load Google Translate script');
        };
        document.head.appendChild(script);
    }
    
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(loadGoogleTranslate, 1000); // Delay loading to avoid blocking
    });
    
    </script>
    
    <style>
    /* Hide Google's top banner */
    .goog-te-banner-frame {
        display: none !important;
    }
    
    body {
        top: 0 !important;
        position: static !important;
    }
    
    /* Style the dropdown */
    .goog-te-menu-frame {
        border-radius: 12px !important;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
        border: 1px solid #e0e0e0 !important;
        max-width: 220px !important;
        bottom: 95px !important;
        top: auto !important;
        right: 20px !important;
        left: auto !important;
        animation: slideUp 0.2s ease-out !important;
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .goog-te-menu2 {
        max-width: 220px !important;
        border-radius: 12px !important;
        overflow: hidden !important;
        padding: 8px 0 !important;
        background: white !important;
    }
    
    .goog-te-menu2-item {
        padding: 12px 20px !important;
        font-size: 14px !important;
        color: #333 !important;
        transition: all 0.2s ease !important;
        font-weight: 500 !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
    }
    
    .goog-te-menu2-item:hover {
        background-color: #f5f8ff !important;
        color: #1a73e8 !important;
        padding-left: 25px !important;
    }
    
    .goog-te-menu2-item-selected {
        background-color: #e8f0fe !important;
        color: #1a73e8 !important;
        font-weight: 600 !important;
        position: relative;
    }
    
    .goog-te-menu2-item-selected:before {
        content: "✓";
        position: absolute;
        left: 10px;
        font-weight: bold;
        color: #1a73e8;
    }
    
    /* Hide "Powered by Google" */
    .goog-logo-link {
        display: none !important;
    }
    
    .goog-te-gadget span a[href] {
        display: none !important;
    }
    
    /* Hide original Google button */
    .goog-te-gadget-simple {
        opacity: 0 !important;
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        overflow: hidden !important;
        clip: rect(0,0,0,0) !important;
        border: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    /* Tooltip arrow */
    .translate-tooltip:after {
        content: '';
        position: absolute;
        top: 100%;
        right: 20px;
        border: 5px solid transparent;
        border-top-color: #333;
    }
    
    /* Mobile responsiveness */
    @media (max-width: 768px) {
        #google_translate_element {
            bottom: 15px !important;
            right: 15px !important;
        }
        
        .translate-circle-btn {
            width: 55px !important;
            height: 55px !important;
            bottom: 20px !important;
            right: 20px !important;
        }
        
        .goog-te-menu-frame {
            bottom: 85px !important;
            right: 15px !important;
            max-width: 200px !important;
        }
        
        .translate-tooltip {
            font-size: 11px !important;
            padding: 5px 10px !important;
        }
    }
    
    /* Active state */
    .translate-circle-btn:active {
        transform: scale(0.95) !important;
        box-shadow: 0 2px 8px rgba(26, 115, 232, 0.3) !important;
    }
    
    /* Smooth SVG icon */
    .translate-circle-btn svg {
        transition: transform 0.3s ease;
    }
    
    .translate-circle-btn:hover svg {
        transform: scale(1.1);
    }
    
    /* Pulse animation for attention */
    @keyframes pulse {
        0% {
            box-shadow: 0 4px 15px rgba(26, 115, 232, 0.4);
        }
        50% {
            box-shadow: 0 4px 20px rgba(26, 115, 232, 0.6);
        }
        100% {
            box-shadow: 0 4px 15px rgba(26, 115, 232, 0.4);
        }
    }
    
    .translate-circle-btn {
        animation: pulse 3s infinite;
    }
    
    .translate-circle-btn:hover {
        animation: none;
    }
    
    /* Prevent button overlap issues */
    @media (max-width: 576px) {
        .translate-circle-btn {
            bottom: 15px !important;
            right: 15px !important;
            width: 50px !important;
            height: 50px !important;
        }
        
        .goog-te-menu-frame {
            bottom: 75px !important;
            right: 10px !important;
        }
    }
    
    /* Live time styling */
    #liveTime {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #4cc9f0;
        text-shadow: 0 0 5px rgba(76, 201, 240, 0.3);
        transition: opacity 0.5s ease;
    }
    
    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
        #liveTime {
            color: #64d8ff;
            text-shadow: 0 0 8px rgba(100, 216, 255, 0.5);
        }
    }
    </style>
    
</body>
</html>
