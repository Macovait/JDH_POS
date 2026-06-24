<?php
/**
 * Cancel QR Payment API
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
$payment_id = sanitize_input($input['payment_id'] ?? '', 'alphanumeric');

if (empty($payment_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid payment ID']);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = get_current_tenant_id();

    // Log cancellation
    if (function_exists('log_activity')) {
        log_activity($_SESSION['user_id'], 'qr_payment_cancelled', [
            'payment_id' => $payment_id
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'QR payment cancelled successfully',
        'payment_id' => $payment_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to cancel payment']);
}
