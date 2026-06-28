<?php

require_once __DIR__ . '/../../../src/Security/CorsHandler.php';
// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * Cart API — REST endpoints for shopping cart
 * Methods: GET, POST, PATCH, DELETE
 */

require_once dirname(dirname(dirname(__DIR__))) . '/src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

header('Content-Type: application/json');
\Jakababa\Security\apply_cors_headers();
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$pdo = get_db_connection();
$tenantId = isset($_GET['tenant']) ? (int) $_GET['tenant'] : 0;
if (!$tenantId) {
    echo json_encode(['success' => false, 'error' => 'Tenant required']);
    exit;
}

// Validate tenant exists to prevent enumeration/data leakage
$tenantCheck = $pdo->prepare("SELECT id FROM pos_tenants WHERE id = ? AND branch_id = $current_branch_id LIMIT 1");
$tenantCheck->execute([$tenantId]);
if (!$tenantCheck->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Tenant not found']);
    exit;
}

// Resolve session
$sessionId = $_COOKIE['shop_session'] ?? bin2hex(random_bytes(16));
if (empty($_COOKIE['shop_session'])) {
    setcookie('shop_session', $sessionId, time() + 86400 * 30, '/');
}

require_once dirname(dirname(dirname(__DIR__))) . '/src/Services/Shop/CartService.php';
$cartService = new Services\Shop\CartService($pdo, $tenantId, $sessionId);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// CSRF verification
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $csrf_token) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            echo json_encode(['success' => true, 'cart' => $cartService->getCart()]);
            break;

        case 'POST':
            $action = $input['action'] ?? '';
            switch ($action) {
                case 'add':
                    if (empty($input['product_id'])) {
                        echo json_encode(['success' => false, 'error' => 'product_id required']);
                        exit;
                    }
                    $result = $cartService->addItem((int) $input['product_id'], (int) ($input['quantity'] ?? 1));
                    echo json_encode($result);
                    break;

                case 'apply_coupon':
                    if (empty($input['code'])) {
                        echo json_encode(['success' => false, 'error' => 'code required']);
                        exit;
                    }
                    echo json_encode($cartService->applyCoupon($input['code']));
                    break;

                case 'remove_coupon':
                    echo json_encode($cartService->removeCoupon());
                    break;

                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
            }
            break;

        case 'PATCH':
            if (empty($input['product_id']) || !isset($input['quantity'])) {
                echo json_encode(['success' => false, 'error' => 'product_id and quantity required']);
                exit;
            }
            echo json_encode($cartService->updateItem((int) $input['product_id'], (int) $input['quantity']));
            break;

        case 'DELETE':
            if (empty($input['product_id'])) {
                echo json_encode(['success' => false, 'error' => 'product_id required']);
                exit;
            }
            echo json_encode($cartService->removeItem((int) $input['product_id']));
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}
