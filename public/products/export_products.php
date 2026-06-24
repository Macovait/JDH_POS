<?php
/**
 * Export Products to CSV/Excel
 * 
 * Allows users to export product data in various formats.
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'csv';
$category_id = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : get_current_branch_id();

$tenant_id = get_current_tenant_id();
if (!$tenant_id) { http_response_code(403); exit('Unauthorized'); }

try {
    $pdo = get_db_connection();

    // Build query
    $query = "
        SELECT
            p.id,
            p.name,
            p.sku,
            p.barcode,
            p.description,
            p.price,
            p.cost_price,
            p.active,
            c.name as category_name,
            COALESCE(i.stock, 0) as current_stock,
            p.created_at,
            p.updated_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
        WHERE p.tenant_id = ? AND p.deleted_at IS NULL
    ";
    $params = [$branch_id, $tenant_id];

    if ($category_id > 0) {
        $query .= " AND p.category_id = ?";
        $params[] = $category_id;
    }

    $query .= " ORDER BY p.name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log activity
    if (function_exists('log_activity')) {
        log_activity($user_id ?? get_current_user_id(), 'export_products', [
            'format' => $format, 'count' => count($products),
            'category_id' => $category_id
        ], $tenant_id);
    }

    // Export based on format
    if ($format === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=products_' . date('Y-m-d_His') . '.csv');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header row
        fputcsv($output, [
            'ID',
            'Name',
            'SKU',
            'Barcode',
            'Description',
            'Price',
            'Cost Price',
            'Current Stock',
            'Category',
            'Status',
            'Created At',
            'Updated At'
        ]);

        // Data rows
        foreach ($products as $product) {
            fputcsv($output, [
                $product['id'],
                $product['name'],
                $product['sku'],
                $product['barcode'] ?? '',
                $product['description'] ?? '',
                $product['price'],
                $product['cost_price'] ?? 0,
                $product['current_stock'] ?? 0,
                $product['category_name'] ?? '',
                $product['active'] ? 'Active' : 'Inactive',
                $product['created_at'],
                $product['updated_at']
            ]);
        }

        fclose($output);
        exit;

    } elseif ($format === 'json') {
        // Set headers for JSON download
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=products_' . date('Y-m-d_His') . '.json');

        echo json_encode($products, JSON_PRETTY_PRINT);
        exit;

    } else {
        // Default to CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=products_' . date('Y-m-d_His') . '.csv');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header row
        fputcsv($output, [
            'ID',
            'Name',
            'SKU',
            'Barcode',
            'Description',
            'Price',
            'Cost Price',
            'Current Stock',
            'Category',
            'Status',
            'Created At',
            'Updated At'
        ]);

        // Data rows
        foreach ($products as $product) {
            fputcsv($output, [
                $product['id'],
                $product['name'],
                $product['sku'],
                $product['barcode'] ?? '',
                $product['description'] ?? '',
                $product['price'],
                $product['cost_price'] ?? 0,
                $product['current_stock'] ?? 0,
                $product['category_name'] ?? '',
                $product['active'] ? 'Active' : 'Inactive',
                $product['created_at'],
                $product['updated_at']
            ]);
        }

        fclose($output);
        exit;
    }

} catch (Exception $e) {
    error_log("Export products error: " . $e->getMessage());

    // Show error page
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Export Error | <?= APP_NAME ?></title>
        <link rel="stylesheet" href="../assets/css/app.css">
    </head>

    <body class="bg-slate-900 min-h-screen flex items-center justify-center">
        <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-xl p-8 max-w-md w-full">
            <div class="text-center">
                <div class="inline-block bg-red-100 rounded-full p-3 mb-4">
                    <i class="fas fa-exclamation-circle text-3xl text-red-600"></i>
                </div>
                <h1 class="text-2xl font-bold text-white mb-2">Export Failed</h1>
                <p class="text-slate-400 mb-6">An error occurred while exporting products. Please try again.</p>
                <a href="products.php"
                    class="inline-block bg-amber-500 text-black px-6 py-2 rounded-lg hover:bg-amber-600 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Products
                </a>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
}

