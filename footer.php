<?php
// footer.php - Fixed for Render + Aiven
// This must be included AFTER all content but BEFORE closing body/html

// Make sure no output has been sent prematurely
if (!ob_get_level()) {
    ob_start();
}
?>
        </div> <!-- End main-content container -->
    </div> <!-- End main-content wrapper -->

    <footer class="mt-5 py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>CSE Attendance System</h5>
                    <p class="mb-0">Department of Computer Science</p>
                    <p class="mb-0">Automated QR-based attendance management system</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">
                        &copy; <?php echo date('Y'); ?> CSE Department. All rights reserved.
                    </p>
                    <p class="mb-0">
                        <i class="fas fa-envelope me-1"></i> cse-attendance@college.edu
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (isset($custom_scripts)): ?>
        <?php echo $custom_scripts; ?>
    <?php endif; ?>
    
    <!-- Dark/Light Mode Script -->
    <script>
    // Dark/Light Mode Toggle - Make it global
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const body = document.body;
    
    // Check for saved theme or prefer-color-scheme
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    const currentTheme = localStorage.getItem('theme');
    
    // Set initial theme if not already set
    if (!body.classList.contains('dark-mode') && !body.classList.contains('light-mode-set')) {
        if (currentTheme === 'dark' || (!currentTheme && prefersDarkScheme.matches)) {
            enableDarkMode();
        } else {
            enableLightMode();
        }
    }
    
    // Function to enable dark mode
    function enableDarkMode() {
        body.classList.add('dark-mode');
        if (themeIcon) {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        }
        localStorage.setItem('theme', 'dark');
        body.classList.add('light-mode-set');
    }
    
    // Function to enable light mode
    function enableLightMode() {
        body.classList.remove('dark-mode');
        if (themeIcon) {
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
        }
        localStorage.setItem('theme', 'light');
        body.classList.add('light-mode-set');
    }
    
    // Toggle theme on button click
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            if (body.classList.contains('dark-mode')) {
                enableLightMode();
            } else {
                enableDarkMode();
            }
        });
    }
    
    // Listen for system theme changes (optional)
    if (prefersDarkScheme.addEventListener) {
        prefersDarkScheme.addEventListener('change', function(e) {
            if (!localStorage.getItem('theme')) {
                if (e.matches) {
                    enableDarkMode();
                } else {
                    enableLightMode();
                }
            }
        });
    }
    </script>
    
    <!-- Google Translate Widget -->
    <div id="google_translate_element" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: none;"></div>
    
    <script type="text/javascript">
    // Load Google Translate only if needed
    function loadGoogleTranslate() {
        if (typeof google === 'undefined' || !google.translate) {
            var script = document.createElement('script');
            script.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
            script.async = true;
            document.head.appendChild(script);
        } else {
            googleTranslateElementInit();
        }
    }
    
    function googleTranslateElementInit() {
        if (typeof google !== 'undefined' && google.translate) {
            try {
                new google.translate.TranslateElement({
                    pageLanguage: 'en',
                    includedLanguages: 'en,hi,te',
                    layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
                    autoDisplay: false
                }, 'google_translate_element');
                
                // Create custom translate button
                createTranslateButton();
            } catch (e) {
                console.error('Google Translate error:', e);
            }
        }
    }
    
    function createTranslateButton() {
        // Remove existing custom button if any
        const existingBtn = document.querySelector('.translate-circle-btn');
        if (existingBtn) {
            existingBtn.remove();
        }
        
        // Get Google's button
        const googleBtn = document.querySelector('.goog-te-gadget-simple');
        if (!googleBtn) {
            // Try again after a delay
            setTimeout(createTranslateButton, 500);
            return;
        }
        
        // Hide Google's button
        googleBtn.style.cssText = `
            opacity: 0 !important;
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            overflow: hidden !important;
            clip: rect(0,0,0,0) !important;
            border: 0 !important;
        `;
        
        // Create our custom circular button
        const customBtn = document.createElement('button');
        customBtn.className = 'translate-circle-btn';
        customBtn.setAttribute('aria-label', 'Translate page');
        customBtn.setAttribute('title', 'Translate');
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
        customBtn.addEventListener('click', function() {
            googleBtn.click();
            
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
    }
    
    // Load Google Translate after page loads
    window.addEventListener('DOMContentLoaded', function() {
        // Delay loading to improve page load performance
        setTimeout(loadGoogleTranslate, 1000);
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
    
    /* Hide original Google Translate elements */
    .skiptranslate {
        display: none !important;
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
    }
    
    .goog-te-menu2-item:hover {
        background-color: #f5f8ff !important;
        color: #1a73e8 !important;
    }
    
    /* Hide "Powered by Google" */
    .goog-logo-link {
        display: none !important;
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
        .translate-circle-btn {
            width: 55px !important;
            height: 55px !important;
            bottom: 20px !important;
            right: 20px !important;
        }
        
        .goog-te-menu-frame {
            bottom: 85px !important;
            right: 15px !important;
        }
        
        .translate-tooltip {
            font-size: 11px !important;
            padding: 5px 10px !important;
        }
    }
    
    /* Dark mode support */
    body.dark-mode .goog-te-menu2 {
        background: #1e1e1e !important;
    }
    
    body.dark-mode .goog-te-menu2-item {
        color: #e0e0e0 !important;
    }
    
    body.dark-mode .goog-te-menu2-item:hover {
        background-color: #333 !important;
        color: #4dabf7 !important;
    }
    
    body.dark-mode .goog-te-menu-frame {
        border-color: #444 !important;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    }
    </style>
    
</body>
</html>
<?php
// Clean output buffer if active
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
