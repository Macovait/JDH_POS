<?php
/**
 * M-Pesa Payment Status Checker
 * 
 * Called by JavaScript to poll M-Pesa transaction status in real-time
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

header('Content-Type: application/json');

$response = [
    'success' => false,
    'status' => 'pending',
    'message' => 'Payment pending'
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $checkout_request_id = trim($_POST['checkout_request_id'] ?? '');
    
    if (empty($checkout_request_id)) {
        throw new Exception('Checkout request ID required');
    }

    $pdo = get_db_connection();
    
    // Query M-Pesa transaction status
    $stmt = $pdo->prepare("
        SELECT 
            mt.id,
            mt.status,
            mt.mpesa_receipt,
            mt.result_code,
            mt.result_desc,
            mt.amount
        FROM mpesa_transactions 
        WHERE checkout_request_id = ? 
        LIMIT 1
    ");
    
    $stmt->execute([$checkout_request_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        // Transaction not found - might still be processing
        echo json_encode([
            'success' => false,
            'status' => 'pending',
            'message' => 'Transaction not yet recorded'
        ]);
        exit;
    }

    // Return transaction status
    $response = [
        'success' => true,
        'status' => $transaction['status'],
        'mpesa_receipt' => $transaction['mpesa_receipt'] ?? null,
        'result_code' => $transaction['result_code'] ?? null,
        'result_desc' => $transaction['result_desc'] ?? null,
        'amount' => $transaction['amount'] ?? 0,
        'message' => match($transaction['status']) {
            'completed' => 'Payment successful',
            'failed' => 'Payment declined: ' . ($transaction['result_desc'] ?? 'Unknown error'),
            'pending' => 'Waiting for payment confirmation',
            default => 'Unknown status'
        }
    ];

} catch (Exception $e) {
    error_log("Payment status check error: " . $e->getMessage());
    $response = [
        'success' => false,
        'status' => 'error',
        'message' => 'Error checking payment status: ' . $e->getMessage()
    ];
    http_response_code(400);
}

echo json_encode($response);
exit;
