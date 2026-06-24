<?php
/**
 * AJAX endpoint to apply filters to dashboard data
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');
safe_require('db.php', 'src');

require_login();

header('Content-Type: application/json');

// Get filter parameters
$start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : date('Y-m-d');
$branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : get_current_branch_id();

try {
    $pdo = get_db_connection();
    $tenant_id = get_current_tenant_id();

    // Get sales summary
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(total), 0) as total_revenue,
            COALESCE(SUM(profit), 0) as total_profit,
            COALESCE(AVG(total), 0) as average_sale
        FROM sales 
        WHERE tenant_id = ? 
        AND branch_id = ?
        AND DATE(created_at) BETWEEN ? AND ?
        AND status = 'completed'
    ");
    $stmt->execute([$tenant_id, $branch_id, $start_date, $end_date]);
    $sales_summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get today's sales
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as today_sales,
            COALESCE(SUM(total), 0) as today_revenue
        FROM sales 
        WHERE tenant_id = ? 
        AND branch_id = ?
        AND DATE(created_at) = CURDATE()
        AND status = 'completed'
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $today_sales = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get product count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_products
        FROM products 
        WHERE tenant_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id]);
    $product_count = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get low stock products
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as low_stock_count
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE p.tenant_id = ? 
        AND i.branch_id = ?
        AND i.stock <= COALESCE(i.minimum_stock, 0)
        AND p.deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $low_stock = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get customer count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_customers
        FROM customers 
        WHERE tenant_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id]);
    $customer_count = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent sales
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as cashier_name
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.tenant_id = ? 
        AND s.branch_id = ?
        AND s.status = 'completed'
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get top products
    $stmt = $pdo->prepare("
        SELECT p.name, p.sku, SUM(si.quantity) as total_sold, SUM(si.quantity * si.price) as total_revenue
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s ON si.sale_id = s.id
        WHERE s.tenant_id = ? 
        AND s.branch_id = ?
        AND DATE(s.created_at) BETWEEN ? AND ?
        AND s.status = 'completed'
        GROUP BY si.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute([$tenant_id, $branch_id, $start_date, $end_date]);
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'sales_summary' => [
                'total_sales' => (int) $sales_summary['total_sales'],
                'total_revenue' => (float) $sales_summary['total_revenue'],
                'total_profit' => (float) $sales_summary['total_profit'],
                'average_sale' => (float) $sales_summary['average_sale']
            ],
            'today_sales' => [
                'count' => (int) $today_sales['today_sales'],
                'revenue' => (float) $today_sales['today_revenue']
            ],
            'products' => [
                'total' => (int) $product_count['total_products'],
                'low_stock' => (int) $low_stock['low_stock_count']
            ],
            'customers' => [
                'total' => (int) $customer_count['total_customers']
            ],
            'recent_sales' => $recent_sales,
            'top_products' => $top_products,
            'filters' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'branch_id' => $branch_id
            ]
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in dashboard_filters: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading dashboard data'
    ]);
}
