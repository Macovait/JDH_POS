<?php
/**
 * Credit Management API
 * Handle customer credit sales and payments
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../src/db.php';

$tenantId = $_SESSION['tenant_id'] ?? 0;
$branchId = $_SESSION['branch_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

if (!$tenantId || !$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                $sql = "SELECT cs.*, c.name as customer_name FROM credit_sales cs LEFT JOIN customers c ON cs.customer_id = c.id WHERE cs.tenant_id = ? AND cs.branch_id = ?";
                $params = [$tenantId, $branchId];
                $sql .= " ORDER BY cs.created_at DESC LIMIT 200";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'credits' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } elseif ($action === 'check_limit') {
                $customerId = (int)($_GET['customer_id'] ?? 0);
                $amount = (float)($_GET['amount'] ?? 0);
                
                $stmt = $pdo->prepare("SELECT credit_limit, COALESCE(SUM(amount), 0) as outstanding FROM customers c LEFT JOIN credit_sales cs ON c.id = cs.customer_id AND cs.status IN ('pending', 'approved', 'partial') WHERE c.id = ? AND c.tenant_id = ? GROUP BY c.id");
                $stmt->execute([$customerId, $tenantId]);
                $result = $stmt->fetch();
                
                if ($result) {
                    $available = (float)$result['credit_limit'] - (float)$result['outstanding'];
                    echo json_encode(['valid' => true, 'available' => $available, 'limit' => $result['credit_limit'], 'outstanding' => $result['outstanding'], 'approved' => $amount <= $available]);
                } else {
                    echo json_encode(['valid' => false, 'error' => 'Customer not found']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            if ($action === 'create') {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO credit_sales (tenant_id, branch_id, customer_id, user_id, amount, due_date, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
                $stmt->execute([$tenantId, $branchId, $input['customer_id'], $userId, $input['amount'], $input['due_date'] ?? date('Y-m-d', strtotime('+30 days')), $input['notes'] ?? '']);
                $creditId = (int)$pdo->lastInsertId();
                $pdo->commit();
                echo json_encode(['success' => true, 'credit_id' => $creditId]);
            } elseif ($action === 'payment') {
                $stmt = $pdo->prepare("INSERT INTO credit_payments (tenant_id, branch_id, credit_sale_id, amount, payment_method, reference, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$tenantId, $branchId, $input['credit_id'], $input['amount'], $input['payment_method'] ?? 'cash', $input['reference'] ?? '']);
                echo json_encode(['success' => true, 'message' => 'Payment recorded']);
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