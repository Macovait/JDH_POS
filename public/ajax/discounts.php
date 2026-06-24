<?php
/**
 * Discount Management API
 * Handle discount codes and coupon validation
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

// Load permission helpers
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                $stmt = $pdo->prepare("SELECT * FROM discounts WHERE tenant_id = ? ORDER BY created_at DESC");
                $stmt->execute([$tenantId]);
                echo json_encode(['success' => true, 'discounts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } elseif ($action === 'validate') {
                $code = $_GET['code'] ?? '';
                $orderAmount = (float)($_GET['order_amount'] ?? 0);
                
                $stmt = $pdo->prepare("SELECT * FROM discounts WHERE code = ? AND tenant_id = ? AND active = 1");
                $stmt->execute([$code, $tenantId]);
                $discount = $stmt->fetch();
                
                if (!$discount) {
                    echo json_encode(['valid' => false, 'error' => 'Invalid discount code']);
                    exit;
                }
                
                // Calculate discount
                $discountAmount = $discount['type'] === 'percentage' ? $orderAmount * ($discount['value'] / 100) : $discount['value'];
                
                echo json_encode([
                    'valid' => true,
                    'discount' => $discount,
                    'discount_amount' => min($discountAmount, $orderAmount, $discount['max_discount'] ?? $orderAmount)
                ]);
            } elseif ($action === 'rates') {
                $stmt = $pdo->prepare("SELECT * FROM exchange_rates WHERE tenant_id = ?");
                $stmt->execute([$tenantId]);
                echo json_encode(['success' => true, 'rates' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            if ($action === 'create') {
                if (!check_permission('discounts.manage') && !is_super_admin()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Permission denied']);
                    exit;
                }
                $stmt = $pdo->prepare("INSERT INTO discounts (tenant_id, branch_id, code, type, value, min_order, max_discount, usage_limit, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([
                    $tenantId,
                    $input['branch_id'] ?? $branchId,
                    $input['code'],
                    $input['type'],
                    $input['value'],
                    $input['min_order'] ?? 0,
                    $input['max_discount'] ?? null,
                    $input['usage_limit'] ?? null
                ]);
                echo json_encode(['success' => true, 'discount_id' => (int)$pdo->lastInsertId()]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;

        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            if ($action === 'deactivate') {
                if (!check_permission('discounts.manage') && !is_super_admin()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Permission denied']);
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE discounts SET active = 0 WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$input['discount_id'], $tenantId]);
                echo json_encode(['success' => true]);
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
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}