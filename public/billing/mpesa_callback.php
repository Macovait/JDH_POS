<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * M-Pesa Callback Handler
 * 
 * Receives real-time payment notifications from Safaricom Daraja API.
 * Updates transaction status in real-time.
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

$pdo = get_db_connection();

header('Content-Type: application/json');

$raw_input = file_get_contents('php://input');
$callback_data = json_decode($raw_input, true);

if (empty($callback_data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid callback']);
    exit;
}

log_mpesa_callback($raw_input);

$body = $callback_data['body'] ?? [];
$stk_callback = $body['stkCallback'] ?? [];

if (empty($stk_callback)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No STK callback']);
    exit;
}

$checkout_request_id = $stk_callback['CheckoutRequestID'] ?? '';
$result_code = $stk_callback['ResultCode'] ?? 99;
$result_desc = $stk_callback['ResultDesc'] ?? '';

if (empty($checkout_request_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No CheckoutRequestID']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM mpesa_transactions WHERE checkout_request_id = ?');
    $stmt->execute([$checkout_request_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        error_log("M-Pesa callback: Transaction not found for $checkout_request_id");
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit;
    }

    $new_status = ($result_code === 0) ? 'completed' : 'failed';
    $receipt_number = '';
    $phone = '';
    $amount = 0;

    if ($result_code === 0) {
        $callback_metadata = $stk_callback['CallbackMetadata'] ?? [];
        $metadata_items = $callback_metadata['Item'] ?? [];
        
        foreach ($metadata_items as $item) {
            $name = $item['Name'] ?? '';
            $value = $item['Value'] ?? '';
            
            if ($name === 'MpesaReceiptNumber') {
                $receipt_number = $value;
            } elseif ($name === 'PhoneNumber') {
                $phone = $value;
            } elseif ($name === 'Amount') {
                $amount = $value;
            }
        }
    }

    $stmt = $pdo->prepare('
        UPDATE mpesa_transactions 
        SET status = ?, receipt_number = ?, result_code = ?, result_desc = ?, 
            completed_at = NOW(), updated_at = NOW()
        WHERE checkout_request_id = ?
    ');
    $stmt->execute([$new_status, $receipt_number, $result_code, $result_desc, $checkout_request_id]);

    if ($new_status === 'completed' && $transaction['sale_id']) {
        $stmt = $pdo->prepare('
            UPDATE sales 
            SET payment_status = "paid", payment_method = "mpesa", 
                mpesa_receipt = ?, updated_at = NOW()
            WHERE id = ?
        ');
        $stmt->execute([$receipt_number, $transaction['sale_id']]);

        $stmt = $pdo->prepare('
            INSERT INTO sales_notifications (sale_id, type, message, created_at)
            VALUES (?, "payment", "Payment received via M-Pesa", NOW())
        ');
        $stmt->execute([$transaction['sale_id']]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Callback processed',
        'transaction_id' => $transaction['id'],
        'new_status' => $new_status
    ]);

} catch (PDOException $e) {
    error_log("M-Pesa callback error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

function log_mpesa_callback($data) {
    $log_file = __DIR__ . '/../../logs/mpesa_callbacks.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $data\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}