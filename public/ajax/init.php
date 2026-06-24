<?php
/**
 * AJAX Bootstrap — Common initialization for all AJAX endpoints
 * Handles session safely: starts, validates auth, extracts data, releases lock.
 * Prevents session deadlocks during concurrent API calls.
 */

// Root path detection
$root_path = null;
$possible_paths = [
    dirname(dirname(__DIR__)),
    dirname(__DIR__),
    $_SERVER['DOCUMENT_ROOT'] . '/JDH_POS',
    $_SERVER['DOCUMENT_ROOT'] . '/JDH POS',
    __DIR__ . '/../..'
];
foreach ($possible_paths as $path) {
    if (file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php')) {
        $root_path = $path;
        break;
    }
}

if (!$root_path) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'System configuration error: Root path not found']);
    exit;
}

require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

// Load auth and db
if (function_exists('safe_require')) {
    safe_require('auth.php', 'src');
    safe_require('db.php', 'src');
    safe_require('functions.php', 'src');
} else {
    require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'auth.php';
    require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'db.php';
    require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'functions.php';
}

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_name('jakababa_saas_sid');
    session_start();
}

// Validate auth
require_login();

// Extract session data into variables (pages should use these, not $_SESSION directly)
$ajax_user_id   = get_current_user_id();
$ajax_tenant_id = get_current_tenant_id();
$ajax_branch_id = get_current_branch_id();
$ajax_user_role = $_SESSION['role'] ?? 'User';

// Release session lock immediately — AJAX endpoints are read-only after auth extraction.
// This is CRITICAL: prevents deadlocks when POS page holds session and AJAX fires concurrently.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Set JSON header for all AJAX responses
header('Content-Type: application/json');
