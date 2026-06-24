<?php
/**
 * PayPal Webhook Handler
 * Receives and processes PayPal webhook events
 */

require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Jakababa\Services\PaymentService;

// Get raw payload
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Initialize payment service
$pdo = get_db_connection();
$paymentService = new PaymentService($pdo);

// Handle webhook
$result = $paymentService->handlePayPalWebhook($data);

// Log webhook
$logFile = __DIR__ . '/../../logs/webhooks.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logEntry = sprintf(
    "[%s] PayPal Webhook: %s\nPayload: %s\n\n",
    date('Y-m-d H:i:s'),
    json_encode($result),
    $payload
);
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Return response
header('Content-Type: application/json');
http_response_code(200);
echo json_encode(['success' => true]);
