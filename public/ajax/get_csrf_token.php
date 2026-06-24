<?php
/**
 * Get a fresh CSRF token for AJAX requests
 * Used by POS to refresh token before each sale submission
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

$root_path = null;
$possible_paths = [
    dirname(dirname(__DIR__)),
    dirname(__DIR__),
    $_SERVER['DOCUMENT_ROOT'] . '/JDH_POS',
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
    echo json_encode(['success' => false, 'error' => 'System configuration not found']);
    exit;
}

require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

if (file_exists($root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'auth.php')) {
    require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'auth.php';
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Check authentication using the same session conventions as the app
$user_id = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? (function_exists('get_current_user_id') ? get_current_user_id() : null);
$tenant_id = $_SESSION['user']['tenant_id'] ?? $_SESSION['tenant_id'] ?? (function_exists('get_current_tenant_id') ? get_current_tenant_id() : null);

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated',
        'debug' => [
            'session_name' => session_name(),
            'has_user_id' => !empty($user_id),
            'has_company_id' => !empty($tenant_id)
        ]
    ]);
    exit;
}

// Generate fresh CSRF token
$newToken = generate_csrf_token();

ob_end_clean();
echo json_encode([
    'success' => true,
    'csrf_token' => $newToken
]);
