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
$limit = min((int) ($input['limit'] ?? 20), 100);
if (!$company_id || !$customer_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT points, reason, multiplier, created_at
        FROM loyalty_points
        WHERE customer_id = ? AND tenant_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$customer_id, $company_id, $limit]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'history' => $history]);
} catch (Throwable $e) {
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit;
