<?php
/**
 * Export Brands to CSV/JSON
 *
 * Allows users to export brand data in various formats.
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('functions.php', 'src', true);

require_login();

if (!check_permission('products.manage') && !is_super_admin()) {
    enforce_permission('products.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$user_id = get_current_user_id();
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'csv';

try {
    $query = "
        SELECT
            b.id,
            b.name,
            b.description,
            b.active,
            b.created_at,
            b.updated_at,
            COUNT(p.id) as product_count
        FROM brands b
        LEFT JOIN products p ON b.id = p.brand_id AND p.tenant_id = b.tenant_id AND p.deleted_at IS NULL
        WHERE b.tenant_id = ? AND b.deleted_at IS NULL
        GROUP BY b.id
        ORDER BY b.name ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id]);
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log activity
    if (function_exists('log_activity')) {
        log_activity($user_id, 'brand_export', [
            'format' => $format, 'count' => count($brands, get_current_tenant_id()),
            'tenant_id' => $tenant_id
        ], $tenant_id);
    }

    // Export based on format
    if ($format === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=brands_' . date('Y-m-d_His') . '.csv');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header row
        fputcsv($output, [
            'ID',
            'Name',
            'Description',
            'Product Count',
            'Status',
            'Created At',
            'Updated At'
        ]);

        // Data rows
        foreach ($brands as $brand) {
            fputcsv($output, [
                $brand['id'],
                $brand['name'],
                $brand['description'] ?? '',
                $brand['product_count'] ?? 0,
                $brand['active'] ? 'Active' : 'Inactive',
                $brand['created_at'],
                $brand['updated_at']
            ]);
        }

        fclose($output);
        exit;

    } elseif ($format === 'json') {
        // Set headers for JSON download
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=brands_' . date('Y-m-d_His') . '.json');

        echo json_encode($brands, JSON_PRETTY_PRINT);
        exit;

    } else {
        // Default to CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=brands_' . date('Y-m-d_His') . '.csv');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header row
        fputcsv($output, [
            'ID',
            'Name',
            'Description',
            'Product Count',
            'Status',
            'Created At',
            'Updated At'
        ]);

        // Data rows
        foreach ($brands as $brand) {
            fputcsv($output, [
                $brand['id'],
                $brand['name'],
                $brand['description'] ?? '',
                $brand['product_count'] ?? 0,
                $brand['active'] ? 'Active' : 'Inactive',
                $brand['created_at'],
                $brand['updated_at']
            ]);
        }

        fclose($output);
        exit;
    }

} catch (Exception $e) {
    error_log("Export brands error: " . $e->getMessage());

    // Show error page
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Export Error | JDH POS</title>
        <link rel="stylesheet" href="../assets/css/app.css">
    </head>

    <body class="bg-slate-900 min-h-screen flex items-center justify-center">
        <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-xl p-8 max-w-md w-full">
            <div class="text-center">
                <div class="inline-block bg-red-100 rounded-full p-3 mb-4">
                    <i class="fas fa-exclamation-circle text-3xl text-red-600"></i>
                </div>
                <h1 class="text-2xl font-bold text-white mb-2">Export Failed</h1>
                <p class="text-slate-400 mb-6">An error occurred while exporting brands. Please try again.</p>
                <a href="brands.php"
                    class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Brands
                </a>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
}
?>