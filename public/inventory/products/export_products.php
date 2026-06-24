<?php
/**
 * Export Products to CSV/Excel
 * 
 * Allows users to export product data in various formats.
 */

// Start output buffering
ob_start();

// Bootstrap the application
require_once __DIR__ . '/../../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);

// Require login
require_login();

// Get parameters
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'csv';
$category_id = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : get_current_branch_id();

try {
    $pdo = get_db_connection();
    $tenant_id = $_SESSION['user']['tenant_id'];

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
            p.unit,
            p.tax_rate,
            COALESCE(i.minimum_stock, 0) as minimum_stock,
            COALESCE(i.maximum_stock, 0) as maximum_stock,
            p.status,
            c.name as category_name,
            i.stock as current_stock,
            p.created_at,
            p.updated_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = p.tenant_id
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
        log_activity($_SESSION['user']['id'], 'export_products', [
            'format' => $format, 'count' => count($products, get_current_tenant_id()),
            'category_id' => $category_id
        ]);
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
            'Unit',
            'Tax Rate (%)',
            'Min Stock',
            'Max Stock',
            'Current Stock',
            'Category',
            'Supplier',
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
                $product['unit'] ?? 'piece',
                $product['tax_rate'] ?? 0,
            $product['minimum_stock'] ?? 0,
            $product['maximum_stock'] ?? 0,
                $product['current_stock'] ?? 0,
                $product['category_name'] ?? '',
                $product['supplier_name'] ?? '',
                $product['status'] ?? 'active',
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
            'Unit',
            'Tax Rate (%)',
            'Min Stock',
            'Max Stock',
            'Current Stock',
            'Category',
            'Supplier',
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
                $product['unit'] ?? 'piece',
                $product['tax_rate'] ?? 0,
            $product['minimum_stock'] ?? 0,
            $product['maximum_stock'] ?? 0,
                $product['current_stock'] ?? 0,
                $product['category_name'] ?? '',
                $product['supplier_name'] ?? '',
                $product['status'] ?? 'active',
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
        <link rel="stylesheet" href="../../assets/css/app.css">
    </head>

    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full">
            <div class="text-center">
                <div class="inline-block bg-red-100 rounded-full p-3 mb-4">
                    <i class="fas fa-exclamation-circle text-3xl text-red-600"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Export Failed</h1>
                <p class="text-gray-600 mb-6">An error occurred while exporting products. Please try again.</p>
                <a href="products.php"
                    class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Products
                </a>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
}

ob_end_flush();
