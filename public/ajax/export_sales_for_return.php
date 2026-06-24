<?php
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

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$format = strtolower($_GET['format'] ?? 'csv');

if ($format !== 'csv') {
    die('Invalid format');
}

// Build WHERE clause
$where = ["s.tenant_id = ? AND s.branch_id = ?", "s.status = 'completed'", "s.voided = 0"];
$params = [$tenant_id, $branch_id];

if ($date_from !== '') {
    $where[] = "DATE(s.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where[] = "DATE(s.created_at) <= ?";
    $params[] = $date_to;
}

$whereSql = implode(' AND ', $where);

// Get currency
$currency = 'KSh';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $curr = $stmt->fetchColumn();
    if ($curr) $currency = $curr;
} catch (Exception $e) {}

try {
    $stmt = $pdo->prepare("
        SELECT s.invoice_number, s.created_at, s.payment_method, s.status,
               c.name as customer_name, c.phone as customer_phone,
               u.name as cashier_name,
               CASE 
                 WHEN EXISTS (SELECT 1 FROM return_items ri JOIN sale_items si ON ri.sale_item_id = si.id WHERE si.sale_id = s.id) THEN 'Returned'
                 ELSE 'No Return'
               END as return_status
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE $whereSql
        ORDER BY s.created_at DESC
    ");
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=returns_export_' . date('Ymd_His') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice Number', 'Sale Date', 'Customer Name', 'Customer Phone', 'Amount', 'Currency', 'Payment Method', 'Cashier', 'Sale Status', 'Return Status']);
    
    foreach ($sales as $sale) {
        fputcsv($output, [
            $sale['invoice_number'],
            date('Y-m-d H:i', strtotime($sale['created_at'])),
            $sale['customer_name'] ?: 'Walk-in',
            $sale['customer_phone'] ?: '',
            number_format((float)$sale['total'], 0),
            $currency,
            $sale['payment_method'] ?: 'N/A',
            $sale['cashier_name'] ?: 'N/A',
            ucfirst($sale['status']),
            $sale['return_status']
        ]);
    }
    fclose($output);
    exit;
} catch (Exception $e) {
    die('Export failed');
}