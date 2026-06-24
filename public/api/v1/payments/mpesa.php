<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * M-Pesa API — Callback handler for STK Push
 * Receives Safaricom Daraja callback and updates order
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/src/paths.php';
safe_require('db.php', 'src', true);

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Safaricom callback structure
$callback = $data['Body']['stkCallback'] ?? [];
if (empty($callback)) {
    echo json_encode(['success' => false, 'error' => 'Missing stkCallback']);
    exit;
}

$checkoutRequestId = $callback['CheckoutRequestID'] ?? '';
$resultCode = (string) ($callback['ResultCode'] ?? '1');
$resultDesc = $callback['ResultDesc'] ?? '';

// Extract receipt if successful
$receiptNumber = '';
$amount = 0;
$phone = '';
if ($resultCode === '0' && !empty($callback['CallbackMetadata']['Item'])) {
    foreach ($callback['CallbackMetadata']['Item'] as $item) {
        switch ($item['Name']) {
            case 'MpesaReceiptNumber': $receiptNumber = $item['Value'] ?? ''; break;
            case 'Amount': $amount = $item['Value'] ?? 0; break;
            case 'PhoneNumber': $phone = $item['Value'] ?? ''; break;
        }
    }
}

// Find transaction and tenant
$pdo = get_db_connection();
$stmt = $pdo->prepare("
    SELECT id, tenant_id, order_id FROM mpesa_transactions
    WHERE checkout_request_id = ? LIMIT 1
");
$stmt->execute([$checkoutRequestId]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tx) {
    // Log unknown callback
    error_log("[M-Pesa Callback] Unknown checkout_request_id: {$checkoutRequestId}");
    echo json_encode(['success' => false, 'error' => 'Transaction not found']);
    exit;
}

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/src/Services/Shop/CheckoutService.php';
$checkout = new Services\Shop\CheckoutService($pdo, (int) $tx['tenant_id'], '');

$result = $checkout->handleMpesaCallback([
    'CheckoutRequestID' => $checkoutRequestId,
    'ResultCode' => $resultCode,
    'ResultDesc' => $resultDesc,
    'MpesaReceiptNumber' => $receiptNumber,
    'Amount' => $amount,
    'PhoneNumber' => $phone
]);

// Return success to Safaricom
http_response_code(200);
echo json_encode(['success' => true]);
