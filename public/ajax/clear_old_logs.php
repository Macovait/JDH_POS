<?php
/**
 * AJAX Endpoint: Clear Old Logs
 * 
 * Deletes activity logs older than specified days
 * Multi-tenant aware (tenant_id isolation)
 */

// Load paths first (needed for helper functions)
require_once __DIR__ . '/../../src/paths.php';

// Start session and load dependencies
if (session_status() === PHP_SESSION_NONE) {
    session_name('jakababa_saas_sid');
    session_start();
}

// Load dependencies
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$csrf_token = generate_csrf_token();

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

// Permission check — only admins/managers can purge logs
safe_require('auth.php', 'src', true);
if (!is_super_admin() && !check_permission('settings.manage')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Permission denied'
    ]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = (int) $_SESSION['tenant_id'];

    // Get days from request body
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }

    // CSRF verification
    $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    if ($token !== $csrf_token) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    $days = isset($input['days']) ? (int) $input['days'] : 90;

    // Validate days
    if ($days < 1 || $days > 365) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid days value. Must be between 1 and 365.'
        ]);
        exit;
    }

    // Delete old logs
    $sql = "
        DELETE FROM activity_logs 
        WHERE tenant_id = ? 
        AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenant_id, $days]);
    $deleted_count = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'deleted_count' => $deleted_count,
        'message' => "Successfully deleted $deleted_count log entries older than $days days"
    ]);

} catch (Exception $e) {
    error_log("Error in clear_old_logs.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to clear old logs'
    ]);
}
