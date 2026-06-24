<?php
/**
 * Stripe Webhook Endpoint
 * Production-ready receiver for Stripe events.
 *
 * Configure in Stripe Dashboard:
 *   URL: https://yourdomain.com/api/webhooks/stripe.php
 *   Secret: (from STRIPE_WEBHOOK_SECRET env var)
 */

require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload) || empty($sigHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing payload or signature']);
    exit;
}

try {
    $pdo = get_db_connection();
    require_once __DIR__ . '/../../src/Webhooks/StripeWebhookProcessor.php';

    $processor = new \JDH\POS\Webhooks\StripeWebhookProcessor($pdo);
    $result = $processor->handleWebhook($payload, $sigHeader);

    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }

    echo json_encode($result);

} catch (Exception $e) {
    error_log("[StripeWebhookEndpoint] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal processing error']);
}
