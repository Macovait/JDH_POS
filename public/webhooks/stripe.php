<?php
/**
 * Stripe Webhook Handler
 * Receives and processes Stripe webhook events
 */

require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Jakababa\Services\PaymentService;

// Get raw payload
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Initialize payment service
$pdo = get_db_connection();
$paymentService = new PaymentService($pdo);

// Handle webhook
$result = $paymentService->handleStripeWebhook($payload, $sigHeader);

// Log webhook
$logFile = __DIR__ . '/../../logs/webhooks.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logEntry = sprintf(
    "[%s] Stripe Webhook: %s\n",
    date('Y-m-d H:i:s'),
    json_encode($result)
);
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Return response
header('Content-Type: application/json');
if ($result['success']) {
    http_response_code(200);
    echo json_encode(['received' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => $result['error'] ?? 'Invalid request']);
}
