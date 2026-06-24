<?php
/**
 * Get Today's Sales Summary API
 * Returns today's sales total and count for POS header refresh
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('functions.php', 'src', true);
safe_require('db.php', 'src', true);

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!check_permission('sales.view') && !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

// Release session lock immediately
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json');

$pdo = get_db_connection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$branch_id = (int) ($_GET['branch_id'] ?? 0);
$session_tenant_id = (int) (get_current_tenant_id() ?? ($_SESSION['tenant_id'] ?? 0));
$requested_tenant_id = (int) ($_GET['tenant_id'] ?? $_GET['company_id'] ?? 0);

if (!$session_tenant_id) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($requested_tenant_id && $requested_tenant_id !== $session_tenant_id) {
    echo json_encode(['success' => false, 'error' => 'Tenant mismatch']);
    exit;
}

$tenant_id = $requested_tenant_id ?: $session_tenant_id;

if ($branch_id && $tenant_id) {
    $validated_branch = resolve_branch_id($branch_id, $tenant_id);
    if ($validated_branch <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid branch']);
        exit;
    }
    $branch_id = $validated_branch;
}

if (!$branch_id || !$tenant_id) {
    echo json_encode(['success' => false, 'error' => 'branch_id and tenant_id required']);
    exit;
}

try {
    $sql = "SELECT
                COUNT(*) as sale_count,
                COALESCE(SUM(total), 0) as total,
                COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash,
                COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card,
                COALESCE(SUM(CASE WHEN payment_method = 'mpesa' THEN total ELSE 0 END), 0) as mpesa
            FROM sales
            WHERE branch_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'";
    $params = [$branch_id];

    if (db_has_column('sales', 'tenant_id')) {
        $sql .= " AND tenant_id = ?";
        $params[] = $tenant_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'sale_count' => (int) ($row['sale_count'] ?? 0),
        'total' => (float) ($row['total'] ?? 0),
        'cash' => (float) ($row['cash'] ?? 0),
        'card' => (float) ($row['card'] ?? 0),
        'mpesa' => (float) ($row['mpesa'] ?? 0)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Query error']);
}
