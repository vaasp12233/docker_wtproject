<?php
// logout.php - Fixed for Render + Aiven

// ==================== CRITICAL: Start output buffering ====================
// This catches any stray output before headers
if (!ob_get_level()) {
    ob_start();
}

// ==================== Configure session for Render ====================
ini_set('session.save_path', '/tmp');

// ==================== Destroy session properly ====================
// Clear session data first
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// ==================== Clean buffer before redirect ====================
if (ob_get_length() > 0) {
    ob_end_clean();
}

// ==================== Redirect to login ====================
header('Location: login.php?message=logged_out');
exit;
