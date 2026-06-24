<?php
/**
 * Stock Transfer API
 * Handle inventory transfers between branches
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../src/db.php';

$tenantId = $_SESSION['tenant_id'] ?? 0;
$branchId = $_SESSION['branch_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

if (!$tenantId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            $sql = "SELECT st.*, p.name as product_name FROM stock_transfers st LEFT JOIN products p ON st.product_id = p.id WHERE st.tenant_id = ? AND st.branch_id = ?";
            $params = [$tenantId, $branchId];
            
            if (!empty($_GET['status'])) {
                $sql .= " AND st.status = ?";
                $params[] = $_GET['status'];
            }
            
            $sql .= " ORDER BY st.created_at DESC LIMIT 200";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'transfers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO stock_transfers (tenant_id, branch_id, from_branch_id, to_branch_id, product_id, quantity, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([
                $tenantId,
                $branchId,
                $input['from_branch_id'] ?? $branchId,
                $input['to_branch_id'],
                $input['product_id'],
                $input['quantity'],
                $input['notes'] ?? ''
            ]);
            
            $transferId = (int)$pdo->lastInsertId();
            $pdo->commit();
            
            echo json_encode(['success' => true, 'transfer_id' => $transferId]);
            break;

        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("DELETE FROM stock_transfers WHERE id = ? AND tenant_id = ? AND status = 'pending'");
            $stmt->execute([$input['transfer_id'], $tenantId]);
            echo json_encode(['success' => true]);
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