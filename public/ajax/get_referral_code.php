<?php
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@error_reporting(E_ALL);
while (ob_get_level()) @ob_end_clean();
ob_start();
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) @ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $error['message']]);
        exit;
    }
});
try {
    require_once __DIR__ . '/../../src/paths.php';
    safe_require('auth.php', 'src', true);
    safe_require('db.php', 'src', true);
    safe_require('functions.php', 'src', true);
} catch (Throwable $e) {
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Load error: ' . $e->getMessage()]);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
$company_id = (int) ($input['company_id'] ?? ($_SESSION['tenant_id'] ?? 0));
$customer_id = (int) ($input['customer_id'] ?? 0);
if (!$company_id || !$customer_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT referral_code, referred_count, referral_discount
        FROM customers
        WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$customer_id, $company_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'referral_code' => $data['referral_code'] ?? null,
        'referred_count' => (int) ($data['referred_count'] ?? 0),
        'referral_discount' => (float) ($data['referral_discount'] ?? 0)
    ]);
} catch (Throwable $e) {
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit;
