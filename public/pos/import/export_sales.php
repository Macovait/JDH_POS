<?php
/**
 * Export Sales to CSV/Excel
 * 
 * Allows users to export sales data in various formats.
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
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');
$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : get_current_branch_id();
$payment_method = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';

try {
    $pdo = get_db_connection();
    $tenant_id = $_SESSION['user']['tenant_id'];

    // Build query
    $query = "
        SELECT 
            s.id,
            s.invoice_number,
            s.total_amount,
            s.tax_amount,
            s.discount_amount,
            s.paid_amount,
            s.change_amount,
            s.payment_method,
            s.status,
            s.notes,
            s.created_at,
            u.name as cashier_name,
            c.name as customer_name,
            c.phone as customer_phone,
            b.name as branch_name
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN branches b ON s.branch_id = b.id
        WHERE s.tenant_id = ? 
        AND s.branch_id = ?
        AND DATE(s.created_at) BETWEEN ? AND ?
    ";
    $params = [$tenant_id, $branch_id, $start_date, $end_date];

    if (!empty($payment_method)) {
        $query .= " AND s.payment_method = ?";
        $params[] = $payment_method;
    }

    $query .= " ORDER BY s.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get sale items for each sale
    foreach ($sales as &$sale) {
        $stmt = $pdo->prepare("
            SELECT 
                si.quantity,
                si.unit_price,
                si.total_price,
                p.name as product_name,
                p.sku
            FROM sale_items si
            LEFT JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = ?
        ");
        $stmt->execute([$sale['id']]);
        $sale['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($sale);

    // Log activity
    if (function_exists('log_activity')) {
        log_activity($_SESSION['user']['id'], 'export_sales', [
            'format' => $format, 'count' => count($sales, get_current_tenant_id()),
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
    }

    // Export based on format
    if ($format === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=sales_' . date('Y-m-d_His') . '.csv');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header row
        fputcsv($output, [
            'Invoice Number',
            'Date',
            'Cashier',
            'Customer',
            'Customer Phone',
            'Branch',
            'Total Amount',
            'Tax Amount',
            'Discount Amount',
            'Paid Amount',
            'Change Amount',
            'Payment Method',
            'Status',
            'Notes',
            'Items'
        ]);

        // Data rows
        foreach ($sales as $sale) {
            // Format items as string
            $items_str = '';
            foreach ($sale['items'] as $item) {
                $items_str .= $item['product_name'] . ' (' . $item['sku'] . ') x' . $item['quantity'] . ' @ ' . $item['unit_price'] . '; ';
            }
            $items_str = rtrim($items_str, '; ');

            fputcsv($output, [
                $sale['invoice_number'],
                $sale['created_at'],
                $sale['cashier_name'] ?? '',
                $sale['customer_name'] ?? '',
                $sale['customer_phone'] ?? '',
                $sale['branch_name'] ?? '',
                $sale['total_amount'],
                $sale['tax_amount'] ?? 0,
                $sale['discount_amount'] ?? 0,
                $sale['paid_amount'],
                $sale['change_amount'] ?? 0,
                $sale['payment_method'],
                $sale['status'],
                $sale['notes'] ?? '',
                $items_str
            ]);
        }

        fclose($output);
        exit;

    } elseif ($format === 'json') {
        // Set headers for JSON download
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=sales_' . date('Y-m-d_His') . '.json');

        echo json_encode($sales, JSON_PRETTY_PRINT);
        exit;

    } else {
        // Default to CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=sales_' . date('Y-m-d_His') . '.csv');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header row
        fputcsv($output, [
            'Invoice Number',
            'Date',
            'Cashier',
            'Customer',
            'Customer Phone',
            'Branch',
            'Total Amount',
            'Tax Amount',
            'Discount Amount',
            'Paid Amount',
            'Change Amount',
            'Payment Method',
            'Status',
            'Notes',
            'Items'
        ]);

        // Data rows
        foreach ($sales as $sale) {
            // Format items as string
            $items_str = '';
            foreach ($sale['items'] as $item) {
                $items_str .= $item['product_name'] . ' (' . $item['sku'] . ') x' . $item['quantity'] . ' @ ' . $item['unit_price'] . '; ';
            }
            $items_str = rtrim($items_str, '; ');

            fputcsv($output, [
                $sale['invoice_number'],
                $sale['created_at'],
                $sale['cashier_name'] ?? '',
                $sale['customer_name'] ?? '',
                $sale['customer_phone'] ?? '',
                $sale['branch_name'] ?? '',
                $sale['total_amount'],
                $sale['tax_amount'] ?? 0,
                $sale['discount_amount'] ?? 0,
                $sale['paid_amount'],
                $sale['change_amount'] ?? 0,
                $sale['payment_method'],
                $sale['status'],
                $sale['notes'] ?? '',
                $items_str
            ]);
        }

        fclose($output);
        exit;
    }

} catch (Exception $e) {
    error_log("Export sales error: " . $e->getMessage());

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
                <p class="text-gray-600 mb-6">An error occurred while exporting sales. Please try again.</p>
                <a href="../sales.php"
                    class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Sales
                </a>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
}

ob_end_flush();
