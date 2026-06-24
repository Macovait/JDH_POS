<?php
/**
 * Export Returns
 * Export returns data to CSV or PDF
 */

// Bootstrap paths and core dependencies (works from /pos and deeper)
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();

if (!check_permission('sales.returns') && !is_super_admin()) {
    header('Location: list_sell_return.php?error=Permission denied');
    exit;
}

safe_require('db.php', 'src', true);

$tenant_id = (int) get_current_tenant_id();
$current_branch_id = get_current_branch_id();
$branch_id = $current_branch_id;
$can_switch_branch = is_super_admin() || check_permission('branches.view');
$status = $_POST['status'] ?? '';
$date_from = $_POST['date_from'] ?? date('Y-m-d', strtotime('-90 days'));
$date_to = $_POST['date_to'] ?? date('Y-m-d');
$search = $_POST['search'] ?? '';
$format = $_POST['format'] ?? 'csv';
$include_items = isset($_POST['include_items']);
$include_reason = isset($_POST['include_reason']);

try {
    $pdo = get_db_connection();

    if ($can_switch_branch && !empty($_POST['branch_id'])) {
        $requested_branch_id = (int) $_POST['branch_id'];
        if ($requested_branch_id > 0) {
            $branch_stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND active = 1");
            $branch_stmt->execute([$requested_branch_id, $tenant_id]);
            if ($branch_stmt->fetchColumn()) {
                $branch_id = $requested_branch_id;
            }
        }
    }

    $query = "
        SELECT r.*, s.invoice_number, c.name as customer_name, c.phone as customer_phone,
               u.name as processed_by_name
        FROM returns r
        LEFT JOIN sales s ON r.sale_id = s.id
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN users u ON r.processed_by = u.id
        WHERE r.branch_id = ? AND r.tenant_id = ?
    ";
    $params = [$branch_id, $tenant_id];

    if (!empty($status)) {
        $query .= " AND r.status = ?";
        $params[] = $status;
    }
    if (!empty($date_from)) {
        $query .= " AND DATE(r.created_at) >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $query .= " AND DATE(r.created_at) <= ?";
        $params[] = $date_to;
    }
    if (!empty($search)) {
        $query .= " AND (r.return_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR s.invoice_number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    $query .= " ORDER BY r.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $returns = $stmt->fetchAll();

    $currency_symbol = 'KSh';
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND (tenant_id = ? OR tenant_id IS NULL)");
    $stmt->execute([$tenant_id]);
    $currency = $stmt->fetchColumn();
    if ($currency) {
        $currency_symbol = $currency;
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=returns_' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        $headers = ['Return #', 'Date', 'Invoice #', 'Customer', 'Amount', 'Status', 'Processed By'];
        if ($include_reason) $headers[] = 'Reason';
        fputcsv($output, $headers);

        foreach ($returns as $return) {
            $row = [
                $return['return_number'],
                $return['created_at'],
                $return['invoice_number'],
                $return['customer_name'] ?? 'N/A',
                $currency_symbol . ' ' . number_format($return['amount'], 2),
                ucfirst($return['status']),
                $return['processed_by_name']
            ];
            if ($include_reason) $row[] = $return['reason'];
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    } else {
        // PDF format - redirect with message for now
        header('Location: list_sell_return.php?info=PDF export coming soon&branch_id=' . $branch_id);
        exit;
    }
} catch (Exception $e) {
    error_log("Error exporting returns: " . $e->getMessage());
    header('Location: list_sell_return.php?error=' . urlencode($e->getMessage()) . '&branch_id=' . $branch_id);
    exit;
}
