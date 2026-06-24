<?php
// Suppress ALL error output before ANYTHING else for JSON API
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@error_reporting(E_ALL);

// Start fresh output buffer
while (ob_get_level()) @ob_end_clean();
ob_start();

// Register fatal error handler
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

if (!is_super_admin() && !check_permission('sales.returns')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

header('Content-Type: application/json');

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$requested_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

// Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
$branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
if ($branch_id <= 0 && $requested_branch_id > 0) {
    echo json_encode(['success' => false, 'error' => 'Access denied to this branch']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.return_number, r.status, r.amount, r.created_at,
               s.invoice_number, c.name as customer_name
        FROM returns r
        LEFT JOIN sales s ON r.sale_id = s.id AND s.voided = 0
        LEFT JOIN customers c ON r.customer_id = c.id
        WHERE r.tenant_id = ? AND r.branch_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = ['success' => true, 'returns' => []];
    
    foreach ($returns as $ret) {
        $result['returns'][] = [
            'id' => (int)$ret['id'],
            'return_number' => $ret['return_number'],
            'status' => $ret['status'],
            'amount' => (float)$ret['amount'],
            'amount_formatted' => number_format((float)$ret['amount'], 0),
            'created_at' => $ret['created_at'],
            'date_formatted' => date('M d, H:i', strtotime($ret['created_at'])),
            'invoice_number' => $ret['invoice_number'] ?: 'N/A',
            'customer_name' => $ret['customer_name'] ?: 'Walk-in'
        ];
    }
    
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => true, 'returns' => []]);
}