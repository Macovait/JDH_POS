<?php
declare(strict_types=1);

/**
 * Logout Page (SaaS Version) - Ultra Slim
 * 
 * Terminates the user session and redirects to login.
 */

// Start output buffering to prevent header issues
ob_start();

// Bootstrap the application
require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);
safe_require('logger.php', 'src', true);

// Get current user info before destroying session
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();
$username = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Unknown';
$session_id = session_id();

// Log the logout activity
if ($user_id && function_exists('log_activity')) {
    log_activity(
        'auth.logout', 
        'User logged out', 
        ['username' => $username, 'tenant_id' => $tenant_id],
        $user_id,
        $tenant_id
    );
}

// Clear remember me token from database if exists
if ($user_id && isset($_COOKIE['remember_token'])) {
    try {
        $token = $_COOKIE['remember_token'];
        $token_hash = hash('sha256', $token);
        
        db_query(
            "DELETE FROM user_tokens WHERE user_id = ? AND token_hash = ? AND type = 'remember_me'",
            [$user_id, $token_hash]
        );
    } catch (Exception $e) {
        error_log("Failed to clear remember token: " . $e->getMessage());
    }
    
    // Clear the cookie
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Destroy the session using the logout_user function from auth.php
if (function_exists('logout_user')) {
    logout_user();
} else {
    // Fallback manual logout
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION = [];
    
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
    
    session_destroy();
}

// Determine redirect URL
$redirect_url = base_url('auth/login.php');

// Add logout parameter for success message
if (strpos($redirect_url, '?') !== false) {
    $redirect_url .= '&logout=success';
} else {
    $redirect_url .= '?logout=success';
}

// Handle subdomain redirect
$host = $_SERVER['HTTP_HOST'] ?? '';
if (preg_match('/^([a-z0-9-]+)\./i', $host, $matches)) {
    $subdomain = strtolower($matches[1]);
    if ($subdomain !== 'www' && $subdomain !== 'admin') {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $redirect_url = "{$protocol}://{$host}" . base_url('auth/login.php?logout=success');
    }
}

// Redirect to login page
redirect($redirect_url);

// Flush output buffer
ob_end_flush();
exit;