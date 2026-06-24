<?php
/**
 * M-Pesa Payment Initiator
 * 
 * Initiates STK Push payment from POS.
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = get_current_tenant_id();
    $sale_id = intval($_POST['sale_id'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);

    if (empty($sale_id) || empty($phone) || $amount <= 0) {
        $response['message'] = 'Invalid parameters';
        echo json_encode($response);
        exit;
    }

    $mpesa_enabled = get_tenant_setting($tenant_id, 'mpesa_enabled', '0');
    if ($mpesa_enabled !== '1') {
        $response['message'] = 'M-Pesa is not enabled';
        echo json_encode($response);
        exit;
    }

    require_once __DIR__ . '/../../src/billing/mpesa.php';

    try {
        $mpesa = new MPesaClient($tenant_id);
        $result = $mpesa->stkPush($phone, $amount, $sale_id, "POS Sale #$sale_id");

        if (!empty($result['CheckoutRequestID'])) {
            $response['success'] = true;
            $response['checkout_id'] = $result['CheckoutRequestID'];
            $response['message'] = 'Payment request sent to your phone';
        } else {
            $response['message'] = 'Failed to initiate payment';
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("M-Pesa STK error: " . $e->getMessage());
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);