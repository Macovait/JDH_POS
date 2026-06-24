<?php
/**
 * QR Code Payment Generation API
 * 
 * Creates QR code payments for seamless integration with:
 * - M-Pesa
 * - Stripe
 * - Other QR-enabled payment providers
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!check_permission('sales.create') && !check_permission('pos.sell') && !is_super_admin()) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = floatval($input['amount'] ?? 0);
$company_id = intval($input['company_id'] ?? 0);
$branch_id = intval($input['branch_id'] ?? 0);
$user_id = intval($input['user_id'] ?? 0);
$customer_id = intval($input['customer_id'] ?? null);
$expiry_minutes = intval($input['expiry_minutes'] ?? 5);

if ($amount <= 0 || !$company_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid amount or company ID']);
    exit;
}

try {
    $pdo = get_db_connection();

    // Generate unique reference
    $reference = 'QR' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_minutes} minutes"));

    // Determine which payment provider to use
    $sql = "
        SELECT gateway_code, config 
        FROM payment_gateways 
        WHERE tenant_id = ? AND is_active = 1 AND gateway_code IN ('mpesa', 'stripe')
        ORDER BY FIELD(gateway_code, 'mpesa', 'stripe')
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$company_id]);
    $gateway = $stmt->fetch(PDO::FETCH_ASSOC);

    $payment_method = $gateway ? $gateway['gateway_code'] : 'generic';

    // Create payment record
    $sql = "
        INSERT INTO qr_payments (
            company_id, branch_id, user_id, customer_id,
            reference, amount, payment_method, status,
            expires_at, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $company_id, $branch_id, $user_id, $customer_id,
        $reference, $amount, $payment_method, $expires_at
    ]);
    
    $payment_id = $pdo->lastInsertId();

    // Generate QR code data
    $qr_data = [
        'payment_id' => $payment_id,
        'reference' => $reference,
        'amount' => $amount,
        'currency' => 'KES', // Configurable
        'method' => $payment_method,
        'expires_at' => $expires_at
    ];

    // Create QR code image
    $qr_json = json_encode($qr_data);
    $qr_base64 = base64_encode($qr_json);
    
    // Use a QR code generation service or library
    // For now, we'll create a data URL format
    $qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qr_json);

    // If M-Pesa is configured, initiate STK push as alternative
    $stk_push_data = null;
    if ($payment_method === 'mpesa' && $gateway) {
        // STK push can be offered as an alternative
        $stk_push_data = [
            'available' => true,
            'phone_required' => true
        ];
    }

    echo json_encode([
        'success' => true,
        'payment_id' => $payment_id,
        'reference' => $reference,
        'amount' => $amount,
        'qr_data' => $qr_data,
        'qr_image_url' => $qr_image_url,
        'qr_base64' => $qr_base64,
        'expires_at' => $expires_at,
        'payment_method' => $payment_method,
        'stk_push' => $stk_push_data
    ]);

} catch (Exception $e) {
    error_log('QR payment creation error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create QR payment'
    ]);
}
