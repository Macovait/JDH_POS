<?php
/**
 * Mark All Notifications as Read
 * POST { csrf_token: string }
 */

require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$current_branch_id = get_current_branch_id();

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF validation
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$tenant_id = get_current_tenant_id();

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user']);
    exit;
}

try {
    $pdo = get_db_connection();

    $sql = "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL";
    $params = [$user_id];

    if ($tenant_id) {
        $sql .= " AND tenant_id = ?";
        $params[] = $tenant_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $affected = $stmt->rowCount();

    echo json_encode([
        'success'  => true,
        'affected' => $affected,
        'marked'   => $affected > 0
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error'
    ]);
}
