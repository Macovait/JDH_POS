<?php
/**
 * Export Drafts — CSV export of draft sales
 * Matches filter params from list_draft.php
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('sales.view')) {
    enforce_permission('sales.view');
}

$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(403);
    exit('Unauthorized');
}

$user_branch = (int) ($_SESSION['user']['branch_id'] ?? get_current_branch_id());

// Get filter params
$search        = trim($_GET['search'] ?? '');
$date_from     = $_GET['date_from'] ?? '';
$date_to       = $_GET['date_to'] ?? '';
$customer_filter = $_GET['customer'] ?? '';

$pdo = get_db_connection();
$currency = get_tenant_currency();

// Build query (same filters as list_draft.php)
$query = "
    SELECT s.*,
           c.name as customer_name,
           c.phone as customer_phone,
           u.name as created_by_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.branch_id = ? AND s.tenant_id = ? AND s.status = 'draft'
";

$params = [$user_branch, $tenant_id];

if (!empty($search)) {
    $query .= " AND (CAST(s.id AS CHAR) LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR s.notes LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($date_from)) {
    $query .= " AND DATE(s.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(s.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($customer_filter)) {
    $query .= " AND s.customer_id = ?";
    $params[] = $customer_filter;
}

$query .= " ORDER BY s.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Export drafts error: ' . $e->getMessage());
    http_response_code(500);
    exit('Export failed');
}

// Get item counts per draft
$item_counts = [];
try {
    $stmt = $pdo->prepare("SELECT sale_id, COUNT(*) as cnt, COALESCE(SUM(quantity), 0) as qty FROM sale_items WHERE tenant_id = ? GROUP BY sale_id");
    $stmt->execute([$tenant_id]);
    foreach ($stmt->fetchAll() as $row) {
        $item_counts[$row['sale_id']] = ['count' => (int)$row['cnt'], 'qty' => (int)$row['qty']];
    }
} catch (PDOException $e) {
    error_log('Export item counts error: ' . $e->getMessage());
}

// Output CSV
$filename = 'draft_sales_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM for Excel
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Headers
fputcsv($output, [
    'Draft ID',
    'Date Created',
    'Customer',
    'Phone',
    'Item Count',
    'Total Qty',
    'Subtotal',
    'Discount',
    'Tax',
    'Total',
    'Notes',
    'Created By',
    'Branch'
]);

foreach ($drafts as $d) {
    $items = $item_counts[$d['id']] ?? ['count' => 0, 'qty' => 0];
    fputcsv($output, [
        '#' . str_pad($d['id'], 6, '0', STR_PAD_LEFT),
        date('Y-m-d H:i', strtotime($d['created_at'])),
        $d['customer_name'] ?? 'Walk-in',
        $d['customer_phone'] ?? '',
        $items['count'],
        $items['qty'],
        number_format((float)($d['subtotal'] ?? 0), 2),
        number_format((float)($d['discount_amount'] ?? $d['discount'] ?? 0), 2),
        number_format((float)($d['tax_amount'] ?? $d['tax'] ?? 0), 2),
        number_format((float)($d['total'] ?? 0), 2),
        $d['notes'] ?? '',
        $d['created_by_name'] ?? '',
        $d['branch_id'] ?? ''
    ]);
}

fclose($output);
exit;
