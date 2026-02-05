<?php
// Common header for all pages

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>CSE Attendance System</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        /* Light Mode (Default) */
        body {
            background: #f8f9fa;
            color: #212529;
            transition: all 0.3s ease;
        }
        
        .navbar {
            background: white !important;
            transition: all 0.3s ease;
        }
        
        /* Dark Mode */
        body.dark-mode {
            background: #121212;
            color: #e0e0e0;
        }
        
        body.dark-mode .navbar {
            background: #1e1e1e !important;
            border-bottom: 1px solid #333;
        }
        
        body.dark-mode .nav-link {
            color: #e0e0e0 !important;
        }
        
        body.dark-mode .navbar-brand {
            color: #4dabf7 !important;
        }
        
        /* FIX FOR NAVBAR TOGGLER (HAMBURGER MENU) IN DARK MODE */
        body.dark-mode .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.3) !important;
            background-color: rgba(255, 255, 255, 0.1) !important;
        }
        
        body.dark-mode .navbar-toggler-icon {
            /* Invert the hamburger icon color for dark mode */
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        }
        
        body.dark-mode .navbar-toggler:focus {
            box-shadow: 0 0 0 0.25rem rgba(77, 171, 247, 0.25) !important;
        }
        
        /* Dark/Light Mode Toggle Button - VISIBLE IN BOTH MODES */
        .theme-toggle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .theme-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .theme-toggle i {
            font-size: 18px;
            transition: transform 0.5s ease;
        }
        
        /* Rotate sun icon on dark mode */
        body.dark-mode .theme-toggle i.fa-sun {
            transform: rotate(180deg);
        }
        
        /* Ensure button is visible in dark mode */
        body.dark-mode .theme-toggle {
            background: linear-gradient(135deg, #4dabf7, #4361ee);
            box-shadow: 0 2px 10px rgba(77, 171, 247, 0.3);
        }
        
        body.dark-mode .theme-toggle:hover {
            background: linear-gradient(135deg, #6bc1ff, #4dabf7);
            box-shadow: 0 4px 15px rgba(77, 171, 247, 0.4);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: #4361ee !important;
        }
        
        .nav-link {
            color: #333 !important;
            font-weight: 500;
        }
        
        /* Fix for all text elements in dark mode */
        body.dark-mode h1,
        body.dark-mode h2,
        body.dark-mode h3,
        body.dark-mode h4,
        body.dark-mode h5,
        body.dark-mode h6,
        body.dark-mode p,
        body.dark-mode span,
        body.dark-mode label,
        body.dark-mode .text-dark,
        body.dark-mode .text-black,
        body.dark-mode .text-muted,
        body.dark-mode .text-body {
            color: #e0e0e0 !important;
        }
        
        /* Fix for muted text */
        body.dark-mode .text-muted {
            color: #a0a0a0 !important;
        }
        
        /* Fix for form labels */
        body.dark-mode .form-label {
            color: #e0e0e0 !important;
        }
        
        /* Fix for table text */
        body.dark-mode table,
        body.dark-mode th,
        body.dark-mode td {
            color: #e0e0e0 !important;
            border-color: #444 !important;
        }
        
        /* Fix for cards */
        body.dark-mode .card {
            background: #1e1e1e;
            color: #e0e0e0;
            border-color: #333;
        }
        
        body.dark-mode .card-header {
            background: #252525;
            border-color: #333;
            color: #e0e0e0;
        }
        
        /* Fix for buttons */
        body.dark-mode .btn-outline-primary {
            border-color: #4dabf7;
            color: #4dabf7;
        }
        
        body.dark-mode .btn-outline-primary:hover {
            background: #4dabf7;
            color: white;
        }
        
        body.dark-mode .btn-primary {
            background: #4dabf7;
            border-color: #4dabf7;
        }
        
        body.dark-mode .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
        }
        
        /* Fix for form controls */
        body.dark-mode .form-control,
        body.dark-mode .form-select,
        body.dark-mode .form-check-input {
            background: #2d2d2d;
            color: #e0e0e0;
            border-color: #444;
        }
        
        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            background: #2d2d2d;
            color: #e0e0e0;
            border-color: #4dabf7;
            box-shadow: 0 0 0 0.25rem rgba(77, 171, 247, 0.25);
        }
        
        body.dark-mode .form-check-label {
            color: #e0e0e0;
        }
        
        /* Container adjustment */
        .container {
            transition: all 0.3s ease;
        }
        
        /* Fix for links */
        body.dark-mode a:not(.nav-link, .navbar-brand, .btn, .theme-toggle) {
            color: #4dabf7 !important;
        }
        
        body.dark-mode a:hover:not(.nav-link, .navbar-brand, .btn, .theme-toggle) {
            color: #6bc1ff !important;
        }
        
        /* Fix for list items */
        body.dark-mode li {
            color: #e0e0e0;
        }
        
        /* Fix for hr */
        body.dark-mode hr {
            border-color: #444;
        }
        
        /* Fix for dropdowns in dark mode */
        body.dark-mode .dropdown-menu {
            background: #1e1e1e;
            border-color: #333;
        }
        
        body.dark-mode .dropdown-item {
            color: #e0e0e0;
        }
        
        body.dark-mode .dropdown-item:hover,
        body.dark-mode .dropdown-item:focus {
            background: #333;
            color: #fff;
        }
        
        body.dark-mode .dropdown-divider {
            border-color: #444;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-graduation-cap me-2"></i>
                CSE Attendance System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php if(isset($_SESSION['logged_in'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $_SESSION['role'] == 'faculty' ? 'faculty_dashboard.php' : 'student_dashboard.php'; ?>">
                                <i class="fas fa-home me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Dark/Light Mode Toggle Button - NOW VISIBLE IN DARK MODE -->
                    <li class="nav-item">
                        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode">
                            <i class="fas fa-moon" id="themeIcon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
    
    <script>
    // Dark/Light Mode Toggle
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const body = document.body;
    
    // Check for saved theme or prefer-color-scheme
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    const currentTheme = localStorage.getItem('theme');
    
    // Set initial theme
    if (currentTheme === 'dark' || (!currentTheme && prefersDarkScheme.matches)) {
        enableDarkMode();
    } else {
        enableLightMode();
    }
    
    // Toggle theme on button click
    themeToggle.addEventListener('click', function() {
        if (body.classList.contains('dark-mode')) {
            enableLightMode();
        } else {
            enableDarkMode();
        }
    });
    
    function enableDarkMode() {
        body.classList.add('dark-mode');
        themeIcon.classList.remove('fa-moon');
        themeIcon.classList.add('fa-sun');
        localStorage.setItem('theme', 'dark');
        
        // Update button visibility
        updateThemeButtonForDarkMode();
    }
    
    function enableLightMode() {
        body.classList.remove('dark-mode');
        themeIcon.classList.remove('fa-sun');
        themeIcon.classList.add('fa-moon');
        localStorage.setItem('theme', 'light');
        
        // Update button visibility
        updateThemeButtonForLightMode();
    }
    
    // Make button clearly visible in dark mode
    function updateThemeButtonForDarkMode() {
        const button = document.getElementById('themeToggle');
        if (button) {
            // Bright blue gradient for dark mode
            button.style.background = 'linear-gradient(135deg, #4dabf7, #4361ee)';
            button.style.boxShadow = '0 2px 10px rgba(77, 171, 247, 0.3)';
            button.style.color = 'white';
            
            // Make icon white
            const icon = button.querySelector('i');
            if (icon) {
                icon.style.color = 'white';
            }
        }
    }
    
    // Reset button for light mode
    function updateThemeButtonForLightMode() {
        const button = document.getElementById('themeToggle');
        if (button) {
            // Original gradient for light mode
            button.style.background = 'linear-gradient(135deg, #4361ee, #3a0ca3)';
            button.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            button.style.color = 'white';
        }
    }
    
    // Update all text elements for dark mode
    function updateAllTextForDarkMode() {
        // Update all text elements
        document.querySelectorAll('h1, h2, h3, h4, h5, h6, p, span, label, .text-dark, .text-black, .text-muted, .text-body, .form-label, th, td, li').forEach(el => {
            if (!el.classList.contains('nav-link') && !el.classList.contains('navbar-brand') && !el.classList.contains('btn') && !el.classList.contains('theme-toggle')) {
                const computedStyle = window.getComputedStyle(el);
                const currentColor = computedStyle.color;
                
                // If text is black/dark, make it light
                if (currentColor === 'rgb(0, 0, 0)' || 
                    currentColor === 'rgb(33, 37, 41)' || 
                    currentColor === 'rgb(52, 58, 64)' ||
                    currentColor.includes('rgb(0,') ||
                    currentColor.includes('rgb(33,') ||
                    currentColor.includes('rgb(52,')) {
                    el.style.color = '#e0e0e0';
                }
                
                // Fix for muted text
                if (el.classList.contains('text-muted')) {
                    el.style.color = '#a0a0a0';
                }
            }
        });
    }
    
    // Reset text colors for light mode
    function updateAllTextForLightMode() {
        document.querySelectorAll('h1, h2, h3, h4, h5, h6, p, span, label, .text-dark, .text-black, .text-muted, .text-body, .form-label, th, td, li').forEach(el => {
            if (!el.classList.contains('nav-link') && !el.classList.contains('navbar-brand') && !el.classList.contains('btn') && !el.classList.contains('theme-toggle')) {
                el.style.color = '';
            }
        });
    }
    
    // Listen for system theme changes
    prefersDarkScheme.addEventListener('change', function(e) {
        if (!localStorage.getItem('theme')) {
            if (e.matches) {
                enableDarkMode();
            } else {
                enableLightMode();
            }
        }
    });
    
    // Apply dark mode to elements added after page load
    const observer = new MutationObserver(function(mutations) {
        if (body.classList.contains('dark-mode')) {
            setTimeout(updateAllTextForDarkMode, 100);
            setTimeout(updateThemeButtonForDarkMode, 100);
        } else {
            setTimeout(updateThemeButtonForLightMode, 100);
        }
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
    </script>