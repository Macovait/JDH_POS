<?php
/**
 * Payment Status Check API
 * 
 * Real-time polling endpoint for payment status updates
 * Used by QR payments, payment links, and other async payment methods
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!check_permission('sales.view') && !check_permission('pos.sell') && !is_super_admin()) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$payment_id = intval($input['payment_id'] ?? 0);
$company_id = intval($input['company_id'] ?? 0);

if (!$payment_id || !$company_id) {
    echo json_encode(['success' => false, 'error' => 'Payment ID and Company ID required']);
    exit;
}

try {
    $pdo = get_db_connection();

    // Get payment status
    $sql = "
        SELECT 
            qp.id,
            qp.reference,
            qp.amount,
            qp.payment_method,
            qp.status,
            qp.transaction_ref,
            qp.customer_id,
            qp.sale_id,
            qp.mpesa_receipt_number,
            qp.stripe_payment_intent_id,
            qp.expires_at,
            qp.created_at,
            s.status as sale_status
        FROM qr_payments qp
        LEFT JOIN sales s ON qp.sale_id = s.id
        WHERE qp.id = ? AND qp.company_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$payment_id, $company_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        echo json_encode(['success' => false, 'error' => 'Payment not found']);
        exit;
    }

    // Check if expired
    if ($payment['status'] === 'pending' && strtotime($payment['expires_at']) < time()) {
        // Update to expired
        $sql = "UPDATE qr_payments SET status = 'expired' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$payment_id]);
        
        $payment['status'] = 'expired';
    }

    // If M-Pesa, check status with provider
    if ($payment['status'] === 'pending' && $payment['payment_method'] === 'mpesa' && $payment['mpesa_receipt_number']) {
        // This would integrate with M-Pesa API to verify status
        // For now, we rely on webhook updates
    }

    // If Stripe, check payment intent status
    if ($payment['status'] === 'pending' && $payment['payment_method'] === 'stripe' && $payment['stripe_payment_intent_id']) {
        // This would integrate with Stripe API
        // For now, we rely on webhook updates
    }

    $response = [
        'success' => true,
        'payment_id' => $payment['id'],
        'reference' => $payment['reference'],
        'status' => $payment['status'],
        'amount' => floatval($payment['amount']),
        'payment_method' => $payment['payment_method']
    ];

    // Add additional data based on status
    if ($payment['status'] === 'completed') {
        $response['transaction_ref'] = $payment['transaction_ref'];
        $response['sale_id'] = $payment['sale_id'];
        $response['completed_at'] = $payment['created_at'];
    }

    if ($payment['status'] === 'failed') {
        $response['error'] = 'Payment failed or was cancelled';
    }

    if ($payment['status'] === 'expired') {
        $response['error'] = 'Payment expired';
        $response['expired_at'] = $payment['expires_at'];
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Payment status check error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check payment status'
    ]);
}
