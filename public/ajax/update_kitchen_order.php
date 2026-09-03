<?php
/**
 * Update kitchen_orders.status (and cascade to its items).
 * Body: { id: int, status: 'pending'|'preparing'|'ready'|'served'|'cancelled', csrf_token: string }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../src/paths.php';
load_core_files();

$csrf_token = generate_csrf_token();


if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) $input = $_POST;

$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$id        = (int)($input['id'] ?? 0);
$status    = $input['status'] ?? '';

$allowed = ['pending', 'preparing', 'ready', 'served', 'cancelled'];
if ($id <= 0 || !in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = get_current_tenant_id();
    $branch_id = get_current_branch_id();

    // Verify ownership
    $sql = "SELECT id FROM kitchen_orders WHERE id = ? AND branch_id = ? AND tenant_id = ?";
    $params = [$id, $branch_id, $tenant_id];
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    $pdo->beginTransaction();
    $updateSql = "UPDATE kitchen_orders SET status = ?, updated_at = NOW() WHERE id = ? AND branch_id = ? AND tenant_id = ?";
    $updateParams = [$status, $id, $branch_id, $tenant_id];
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute($updateParams);

    $itemSql = "UPDATE kitchen_order_items SET status = ? WHERE kitchen_order_id = ? AND EXISTS (SELECT 1 FROM kitchen_orders ko WHERE ko.id = ? AND ko.branch_id = ? AND ko.tenant_id = ?";
    $itemParams = [$status, $id, $id, $branch_id, $tenant_id];
    $itemSql .= ")";
    $stmt = $pdo->prepare($itemSql);
    $stmt->execute($itemParams);
    $pdo->commit();

    echo json_encode(['success' => true, 'id' => $id, 'status' => $status]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("update_kitchen_order: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
