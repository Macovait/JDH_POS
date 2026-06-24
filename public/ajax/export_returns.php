<?php
/**
 * Export returns to CSV
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

if (!is_super_admin() && !check_permission('sales.returns')) {
    die('Permission denied');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$requested_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

// Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
$branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
if ($branch_id <= 0 && $requested_branch_id > 0) {
    die('Access denied to this branch');
}

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = ["r.tenant_id = ? AND r.branch_id = ?"];
$params = [$tenant_id, $branch_id];

if ($status !== '') {
    $where[] = "r.status = ?";
    $params[] = $status;
}
if ($search !== '') {
    $where[] = "(r.return_number LIKE ? OR s.invoice_number LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($date_from !== '') {
    $where[] = "DATE(r.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where[] = "DATE(r.created_at) <= ?";
    $params[] = $date_to;
}

$whereSql = implode(' AND ', $where);

$sql = "SELECT r.return_number, r.created_at, r.status, r.amount, r.reason,
               s.invoice_number, c.name as customer_name, c.phone as customer_phone
        FROM returns r
        LEFT JOIN sales s ON r.sale_id = s.id
        LEFT JOIN customers c ON r.customer_id = c.id
        WHERE $whereSql
        ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=returns_export_' . date('Ymd_His') . '.csv');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fputcsv($output, ['Return Number', 'Date', 'Status', 'Amount', 'Reason', 'Invoice Number', 'Customer Name', 'Customer Phone']);

foreach ($returns as $r) {
    fputcsv($output, [
        $r['return_number'],
        date('Y-m-d H:i', strtotime($r['created_at'])),
        ucfirst($r['status']),
        number_format((float)$r['amount'], 0),
        $r['reason'] ?? '',
        $r['invoice_number'] ?: 'N/A',
        $r['customer_name'] ?: 'Walk-in',
        $r['customer_phone'] ?: ''
    ]);
}
fclose($output);
exit;
?>
