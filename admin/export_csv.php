<?php
/**
 * CSV Export Utility for Admin Pages
 *
 * Accepts POST requests from the admin panel to export table data as CSV.
 * All inputs are validated against the database schema — no raw SQL from user input.
 *
 * Usage: POST with table, columns JSON, and optional filter parameters.
 *
 * @package JDH_POS\Admin
 * @version 2.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

// --- Input validation ---

$table = $_POST['table'] ?? '';
$columns = json_decode($_POST['columns'] ?? '[]', true);
$filterColumn = $_POST['filter_column'] ?? '';
$filterValue = $_POST['filter_value'] ?? '';
$filterOperator = $_POST['filter_operator'] ?? '=';
$sortColumn = $_POST['sort_column'] ?? 'id';
$sortDirection = strtoupper($_POST['sort_direction'] ?? 'DESC');
$filename = $_POST['filename'] ?? '';

if (!is_array($columns) || empty($columns) || empty($table)) {
    http_response_code(400);
    die('Invalid export parameters: table and columns are required.');
}

// Validate table name: only alphanumeric and underscores, must exist in database
if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
    http_response_code(400);
    die('Invalid table name.');
}

if (!admin_table_exists($table)) {
    http_response_code(400);
    die('Table does not exist.');
}

// Get actual columns from the database to validate against
$validColumns = admin_table_columns($table);
if (empty($validColumns)) {
    http_response_code(500);
    die('Could not retrieve table schema.');
}

// Validate and extract column fields — only allow columns that exist in the table
$selectFields = [];
$headerLabels = [];

foreach ($columns as $col) {
    $field = is_array($col) ? ($col['field'] ?? '') : (string)$col;

    if (!in_array($field, $validColumns, true)) {
        continue; // Skip columns not in the schema
    }

    // Use backtick-quoted identifiers for safety
    $selectFields[] = '`' . $field . '`';
    $headerLabels[] = is_array($col) ? ($col['label'] ?? $field) : $field;
}

if (empty($selectFields)) {
    http_response_code(400);
    die('No valid columns specified for export.');
}

// Validate sort column
if (!in_array($sortColumn, $validColumns, true)) {
    $sortColumn = 'id';
    if (!in_array('id', $validColumns, true)) {
        $sortColumn = $validColumns[0];
    }
}

// Validate sort direction
if (!in_array($sortDirection, ['ASC', 'DESC'], true)) {
    $sortDirection = 'DESC';
}

// Validate filter operator
$allowedOperators = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IS NULL', 'IS NOT NULL'];
if (!in_array(strtoupper($filterOperator), $allowedOperators, true)) {
    $filterOperator = '=';
}

// Build query with parameterized WHERE clause
$selectSql = implode(', ', $selectFields);
$params = [];

$whereSql = '1=1';
if (!empty($filterColumn) && in_array($filterColumn, $validColumns, true)) {
    $upperOp = strtoupper($filterOperator);

    if ($upperOp === 'IS NULL') {
        $whereSql = '`' . $filterColumn . '` IS NULL';
    } elseif ($upperOp === 'IS NOT NULL') {
        $whereSql = '`' . $filterColumn . '` IS NOT NULL';
    } else {
        $whereSql = '`' . $filterColumn . '` ' . $upperOp . ' ?';
        if ($upperOp === 'LIKE' || $upperOp === 'NOT LIKE') {
            $params[] = '%' . $filterValue . '%';
        } else {
            $params[] = $filterValue;
        }
    }
}

$sql = "SELECT {$selectSql} FROM `{$table}` WHERE {$whereSql} ORDER BY `{$sortColumn}` {$sortDirection} LIMIT 50000";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('CSV export query failed: ' . $e->getMessage());
    http_response_code(500);
    die('Export query failed.');
}

// Sanitize filename
$safeFilename = preg_replace('/[^a-zA-Z0-9_\-]/', '', $filename ?: $table);
$safeFilename .= '_' . date('Ymd_His') . '.csv';

// Output CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// Headers
fputcsv($output, $headerLabels);

// Data rows
foreach ($rows as $row) {
    $line = [];
    foreach ($columns as $col) {
        $field = is_array($col) ? ($col['field'] ?? '') : (string)$col;

        if (!in_array($field, $validColumns, true)) {
            continue;
        }

        $val = $row[$field] ?? '';

        // Format based on type hints
        if (is_array($col) && isset($col['format'])) {
            switch ($col['format']) {
                case 'currency':
                    $val = number_format((float)$val, 2);
                    break;
                case 'date':
                    $val = $val ? date('Y-m-d H:i', strtotime($val)) : '';
                    break;
                case 'status':
                    $val = ucfirst((string)$val);
                    break;
            }
        }
        $line[] = $val;
    }
    fputcsv($output, $line);
}

fclose($output);
exit;
