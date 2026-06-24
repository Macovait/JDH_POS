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

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$requested_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

// Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
$branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
if ($branch_id <= 0 && $requested_branch_id > 0) {
    echo json_encode(['success' => false, 'error' => 'Access denied to this branch']);
    exit;
}

$term = trim($_GET['term'] ?? '');

if (strlen($term) < 2) {
    echo json_encode(['success' => false, 'error' => 'Enter at least 2 characters']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.invoice_number, s.total, s.created_at,
               c.name as customer_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.tenant_id = ? AND s.branch_id = ? AND s.invoice_number LIKE ? AND s.status = 'completed' AND s.voided = 0
        " . (db_has_column('sales', 'is_active') ? " AND s.is_active = 1" : "") . "
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$tenant_id, $branch_id, "%$term%"]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sales)) {
        echo json_encode(['success' => false, 'error' => 'No sale found']);
        exit;
    }
    
    if (count($sales) === 1) {
        $s = $sales[0];
        echo json_encode([
            'success' => true,
            'multiple' => false,
            'sale' => [
                'id' => (int)$s['id'],
                'invoice_number' => $s['invoice_number'],
                'total_formatted' => number_format((float)$s['total'], 0),
                'date_formatted' => date('M d, Y', strtotime($s['created_at'])),
                'customer_name' => $s['customer_name'] ?: 'Walk-in'
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'multiple' => true,
            'sales' => array_map(function($s) {
                return [
                    'id' => (int)$s['id'],
                    'invoice_number' => $s['invoice_number'],
                    'total_formatted' => number_format((float)$s['total'], 0),
                    'date_formatted' => date('M d, Y', strtotime($s['created_at'])),
                    'customer_name' => $s['customer_name'] ?: 'Walk-in'
                ];
            }, $sales)
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Lookup failed']);
}