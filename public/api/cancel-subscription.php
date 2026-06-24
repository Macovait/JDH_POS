<?php
/**
 * Cancel Subscription API
 */

require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../vendor/autoload.php';
safe_require('auth.php', 'src', true);

use Jakababa\Services\PaymentService;

// Require login
if (empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tenantId = $_SESSION['tenant_id'];

try {
    $pdo = get_db_connection();
    $paymentService = new PaymentService($pdo);
    
    $result = $paymentService->cancelSubscription($tenantId, false);
    
    echo json_encode([
        'success' => true,
        'message' => 'Subscription cancelled successfully',
        'access_until' => $result['access_until'],
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
