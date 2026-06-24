<?php
/**
 * Returns API Endpoints
 * Handle return requests, approvals, and refunds
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
require_once __DIR__ . '/../../src/db.php';

$tenantId = $_SESSION['tenant_id'] ?? 0;
$branchId = $_SESSION['branch_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

if (!$tenantId || !$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!check_permission('sales.returns') && !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

function logActivity(PDO $pdo, int $userId, string $action, string $description, int $tenantId): void
{
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, branch_id, tenant_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $description, $_SESSION['branch_id'] ?? 0, $tenantId]);
    } catch (Exception $e) {
        error_log("Failed to log: " . $e->getMessage());
    }
}

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                $sql = "SELECT r.*, s.receipt_number FROM returns r LEFT JOIN sales s ON r.sale_id = s.id WHERE r.tenant_id = ? AND r.branch_id = ?";
                $params = [$tenantId, $branchId];
                $sql .= " ORDER BY r.created_at DESC LIMIT 200";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'returns' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            if ($action === 'create') {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("INSERT INTO returns (tenant_id, branch_id, sale_id, user_id, reason, status, notes, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())");
                $stmt->execute([$tenantId, $branchId, $input['sale_id'], $userId, $input['reason'] ?? 'refund', $input['notes'] ?? '']);
                $returnId = (int)$pdo->lastInsertId();

                foreach ($input['items'] ?? [] as $item) {
                    $stmt = $pdo->prepare("INSERT INTO return_items (return_id, product_id, sale_item_id, quantity, price, reason) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$returnId, $item['product_id'], $item['sale_item_id'], $item['quantity'], $item['price'], $item['reason'] ?? 'damaged']);
                }

                $pdo->commit();
                logActivity($pdo, $userId, 'return.create', "Return created: {$returnId}", $tenantId);
                
                echo json_encode(['success' => true, 'return_id' => $returnId]);
            } elseif ($action === 'approve') {
                $returnId = (int)$input['return_id'];
                
                $stmt = $pdo->prepare("UPDATE returns SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND tenant_id = ? AND status = 'pending'");
                $stmt->execute([$userId, $returnId, $tenantId]);
                
                logActivity($pdo, $userId, 'return.approve', "Return approved: {$returnId}", $tenantId);
                echo json_encode(['success' => true, 'message' => 'Return approved']);
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE returns SET status = 'rejected', rejected_by = ?, rejection_reason = ? WHERE id = ? AND tenant_id = ? AND status = 'pending'");
                $stmt->execute([$userId, $input['reason'] ?? '', $input['return_id'], $tenantId]);
                
                logActivity($pdo, $userId, 'return.reject', "Return rejected", $tenantId);
                echo json_encode(['success' => true, 'message' => 'Return rejected']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}