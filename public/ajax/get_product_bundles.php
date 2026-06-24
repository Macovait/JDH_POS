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
if (!$company_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT b.id, b.name, b.discount_type, b.discount_value,
               GROUP_CONCAT(p.id) as product_ids,
               GROUP_CONCAT(p.name) as product_names,
               GROUP_CONCAT(p.price) as product_prices
        FROM product_bundles b
        JOIN product_bundle_items bi ON bi.bundle_id = b.id
        JOIN products p ON p.id = bi.product_id
        WHERE b.tenant_id = ? AND b.active = 1 AND b.deleted_at IS NULL
        GROUP BY b.id
        ORDER BY b.name
        LIMIT 20
    ");
    $stmt->execute([$company_id]);
    $bundles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'bundles' => $bundles]);
} catch (Throwable $e) {
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit;
