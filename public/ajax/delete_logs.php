<?php
/**
 * AJAX Endpoint: Delete Selected Logs
 *
 * Deletes specific activity log entries by ID
 * Multi-tenant aware (tenant_id isolation)
 */

require_once __DIR__ . '/../../src/paths.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('jakababa_saas_sid');
    session_start();
}

require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

safe_require('auth.php', 'src', true);
if (!is_super_admin() && !check_permission('settings.manage') && !check_permission('activity.delete')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = (int) $_SESSION['tenant_id'];

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }

    $ids = isset($input['ids']) && is_array($input['ids']) ? array_map('intval', $input['ids']) : [];
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No log IDs provided']);
        exit;
    }

    // Tenant isolation: only delete logs belonging to this tenant
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM activity_logs WHERE id IN ($placeholders) AND tenant_id = ?";
    $params = array_merge($ids, [$tenant_id]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $deleted = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'deleted' => $deleted,
        'message' => "Deleted {$deleted} log(s)"
    ]);
} catch (Exception $e) {
    error_log("Delete logs error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
