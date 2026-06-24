<?php
/**
 * Send Payment Link API
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
$sale_id = intval($input['sale_id'] ?? 0);
$method = sanitize_input($input['method'] ?? 'sms', 'alphanumeric');
$recipient = sanitize_input($input['recipient'] ?? '', 'string');

if ($sale_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid sale ID']);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = get_current_tenant_id();

    // Verify sale exists and belongs to tenant
    $stmt = $pdo->prepare("SELECT id, total FROM sales WHERE id = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$sale_id, $tenant_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        echo json_encode(['success' => false, 'error' => 'Sale not found']);
        exit;
    }

    // Generate payment link
    $payment_link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/JDH_POS/public/pay/' . $sale_id;

    // Log activity
    if (function_exists('log_activity')) {
        log_activity($_SESSION['user_id'], 'payment_link_sent', [
            'sale_id' => $sale_id,
            'method' => $method,
            'recipient' => $recipient
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment link generated successfully',
        'payment_link' => $payment_link,
        'sale_id' => $sale_id,
        'amount' => floatval($sale['total'])
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to generate payment link']);
}
