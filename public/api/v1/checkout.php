<?php
/**
 * Checkout API — Multi-step checkout flow
 * Step 1: GET summary | Step 2: POST shipping | Step 3: POST place-order
 */

require_once dirname(dirname(dirname(__DIR__))) . '/src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$pdo = get_db_connection();
$tenantId = isset($_GET['tenant']) ? (int) $_GET['tenant'] : 0;
if (!$tenantId) {
    echo json_encode(['success' => false, 'error' => 'Tenant required']);
    exit;
}

$sessionId = $_COOKIE['shop_session'] ?? '';
if (!$sessionId) {
    echo json_encode(['success' => false, 'error' => 'Session expired']);
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/src/Services/Shop/CheckoutService.php';
$checkout = new Services\Shop\CheckoutService($pdo, $tenantId, $sessionId);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// CSRF verification
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $csrf_token) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    if ($method === 'GET') {
        // Step 1: Get checkout summary
        echo json_encode($checkout->getCheckoutSummary());
        exit;
    }

    if ($method === 'POST') {
        $action = $input['action'] ?? '';

        if ($action === 'shipping') {
            // Step 2: Calculate shipping
            if (empty($input['zone_id'])) {
                echo json_encode(['success' => false, 'error' => 'zone_id required']);
                exit;
            }
            $cart = (new Services\Shop\CartService($pdo, $tenantId, $sessionId))->getCart();
            $weight = 0;
            foreach ($cart['items'] ?? [] as $item) {
                $weight += (float) ($item['weight_kg'] ?? 0) * $item['quantity'];
            }
            echo json_encode($checkout->calculateShipping(
                (int) $input['zone_id'],
                $weight,
                (float) ($cart['subtotal'] ?? 0)
            ));
            exit;
        }

        if ($action === 'place-order') {
            // Step 3: Place order
            echo json_encode($checkout->placeOrder($input));
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}
