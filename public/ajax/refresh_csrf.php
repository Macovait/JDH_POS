<?php
/**
 * AJAX endpoint for refreshing/validating session
 * Returns current token without regenerating to prevent sync issues
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Return a fresh CSRF token
$current_token = generate_csrf_token();

echo json_encode([
    'success' => true, 
    'csrf_token' => $current_token
]);