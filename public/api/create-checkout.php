<?php
/**
 * Create Checkout Session API
 * Creates Stripe or PayPal checkout sessions
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

// Get request body
$input = json_decode(file_get_contents('php://input'), true);
$planId = $input['plan_id'] ?? '';
$billingCycle = $input['billing_cycle'] ?? 'monthly';
$gateway = $input['gateway'] ?? 'stripe';

// Validate
if (!$planId) {
    http_response_code(400);
    echo json_encode(['error' => 'Plan ID required']);
    exit;
}

try {
    $pdo = get_db_connection();
    $paymentService = new PaymentService($pdo);
    
    if ($gateway === 'paypal') {
        $result = $paymentService->createPayPalSubscription($tenantId, $planId, $billingCycle);
    } else {
        $result = $paymentService->createStripeCheckout($tenantId, $planId, $billingCycle);
    }
    
    echo json_encode([
        'success' => true,
        'checkout_url' => $result['checkout_url'],
        'session_id' => $result['session_id'] ?? null,
        'gateway' => $gateway,
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
