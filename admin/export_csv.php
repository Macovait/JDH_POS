<?php
/**
 * CSV Export Utility for Admin Pages
 * Usage: POST with table, columns JSON, and optional WHERE clause
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

$table = preg_replace('/[^a-z_]/', '', $_POST['table'] ?? '');
$columns = json_decode($_POST['columns'] ?? '[]', true);
$where = $_POST['where'] ?? '1=1';
$where = preg_replace('/[^a-zA-Z0-9_\s=\'"\(\)%,<>!\.\-\*\+]/', '', $where); // Basic sanitization
$filename = preg_replace('/[^a-z0-9_\-]/', '', $_POST['filename'] ?? $table) . '_' . date('Ymd_His') . '.csv';
$order = $_POST['order'] ?? 'id DESC';
$order = preg_replace('/[^a-zA-Z0-9_\s,\.\(\)]/', '', $order);

if (empty($table) || empty($columns)) {
    die('Invalid export parameters');
}

// Build SELECT
$selectCols = array_map(function($col) {
    if (is_array($col)) {
        return $col['raw'] ?? $col['field'];
    }
    return $col;
}, $columns);
$selectSql = implode(', ', $selectCols);

// Fetch data
$stmt = $pdo->query("SELECT {$selectSql} FROM {$table} WHERE {$where} ORDER BY {$order} LIMIT 50000");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Headers
$headers = array_map(function($col) {
    if (is_array($col)) {
        return $col['label'] ?? $col['field'];
    }
    return $col;
}, $columns);
fputcsv($output, $headers);

// Data rows
foreach ($rows as $row) {
    $line = [];
    foreach ($columns as $col) {
        $field = is_array($col) ? ($col['field'] ?? '') : $col;
        $val = $row[$field] ?? '';
        // Format based on type hints
        if (is_array($col) && isset($col['format'])) {
            switch ($col['format']) {
                case 'currency':
                    $val = '$' . number_format((float) $val, 2);
                    break;
                case 'date':
                    $val = $val ? date('Y-m-d H:i', strtotime($val)) : '';
                    break;
                case 'status':
                    $val = ucfirst((string) $val);
                    break;
            }
        }
        $line[] = $val;
    }
    fputcsv($output, $line);
}

fclose($output);
exit;
