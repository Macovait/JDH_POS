<?php
/**
 * Mobile Manager API
 * Handles manager mobile app operations for real-time dashboard and management
 */

require_once __DIR__ . '/../mobile/index.php';

$auth = jwt_authenticate($pdo);
$user = $auth['user'];
$company = $auth['tenant'];

switch ($action) {
    case 'dashboard':
        get_manager_dashboard($pdo, $company);
        break;

    case 'sales':
        get_manager_sales($pdo, $company);
        break;

    case 'inventory':
        get_manager_inventory($pdo, $company);
        break;

    case 'staff':
        get_manager_staff($pdo, $company);
        break;

    case 'reports':
        get_manager_reports($pdo, $company);
        break;

    case 'alerts':
        get_manager_alerts($pdo, $company);
        break;

    default:
        mobile_error('Invalid manager action', 400);
}

function get_manager_dashboard($pdo, $company) {
    try {
        $branch_id = (int) ($_GET['branch_id'] ?? 0);

        $branch_filter = '';
        $params = [];

        if ($branch_id > 0) {
            $branch_filter = 'AND branch_id = ?';
            $params[] = $branch_id;
        }

    // Today's sales summary
    $sales_params = [$company['id']];
    if ($branch_id > 0) {
        $sales_params[] = $branch_id;
    }

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_sales,
            COALESCE(SUM(total), 0) as total_revenue,
            COALESCE(SUM(discount), 0) as total_discounts,
            COUNT(DISTINCT customer_id) as unique_customers
        FROM sales
        WHERE tenant_id = ?
        AND DATE(created_at) = CURDATE()
        $branch_filter
    ");
    $stmt->execute($sales_params);
    $today_sales = $stmt->fetch(PDO::FETCH_ASSOC);

    // Payment method breakdown
    $payment_params = [$company['id']];
    if ($branch_id > 0) {
        $payment_params[] = $branch_id;
    }

    $stmt = $pdo->prepare("
        SELECT
            payment_method,
            COUNT(*) as count,
            COALESCE(SUM(total), 0) as amount
        FROM sales
        WHERE tenant_id = ?
        AND DATE(created_at) = CURDATE()
        $branch_filter
        GROUP BY payment_method
    ");
    $stmt->execute($payment_params);
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Low stock alerts
    $stmt = $pdo->prepare("
        SELECT
            p.id, p.name, p.sku, COALESCE(i.stock, 0) as current_stock,
            COALESCE(i.reorder_level, 10) as reorder_level
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id AND i.tenant_id = ?
        WHERE p.tenant_id = ?
        AND p.active = 1
        AND p.deleted_at IS NULL
        AND COALESCE(i.stock, 0) <= COALESCE(i.reorder_level, 10)
        AND COALESCE(i.stock, 0) > 0
        ORDER BY COALESCE(i.stock, 0) ASC
        LIMIT 5
    ");
    $stmt->execute([$company['id'], $company['id']]);
    $low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent sales
    $recent_params = [$company['id']];
    $recent_sql = "
        SELECT
            s.id, s.total, s.payment_method, s.created_at,
            c.name as customer_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.tenant_id = ?
    ";

    if ($branch_id > 0) {
        $recent_sql .= " AND s.branch_id = ?";
        $recent_params[] = $branch_id;
    }

    $recent_sql .= " ORDER BY s.created_at DESC LIMIT 10";

    $stmt = $pdo->prepare($recent_sql);
    $stmt->execute($recent_params);
    $recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top selling products today
    $top_params = [$company['id']];
    $top_sql = "
        SELECT
            p.name,
            SUM(si.quantity) as total_quantity,
            SUM(si.subtotal) as total_revenue
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        WHERE s.tenant_id = ?
        AND DATE(s.created_at) = CURDATE()
    ";

    if ($branch_id > 0) {
        $top_sql .= " AND s.branch_id = ?";
        $top_params[] = $branch_id;
    }

    $top_sql .= " GROUP BY si.product_id ORDER BY total_revenue DESC LIMIT 5";

    $stmt = $pdo->prepare($top_sql);
    $stmt->execute($top_params);
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        mobile_success([
            'today_sales' => [
                'total_sales' => (int) $today_sales['total_sales'],
                'total_revenue' => (float) $today_sales['total_revenue'],
                'total_discounts' => (float) $today_sales['total_discounts'],
                'unique_customers' => (int) $today_sales['unique_customers']
            ],
            'payment_methods' => $payment_methods,
            'low_stock_alerts' => $low_stock,
            'recent_sales' => $recent_sales,
            'top_products' => $top_products
        ]);
    } catch (Exception $e) {
        error_log("Dashboard error: " . $e->getMessage());
        mobile_error('Failed to load dashboard data', 500);
    }
}

function get_manager_sales($pdo, $company) {
    $period = $_GET['period'] ?? 'today'; // today, week, month
    $branch_id = (int) ($_GET['branch_id'] ?? 0);

    // Build date filter
    $date_filter = '';
    switch ($period) {
        case 'today':
            $date_filter = 'DATE(s.created_at) = CURDATE()';
            break;
        case 'week':
            $date_filter = 'YEARWEEK(s.created_at) = YEARWEEK(CURDATE())';
            break;
        case 'month':
            $date_filter = 'MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())';
            break;
        default:
            $date_filter = 'DATE(s.created_at) = CURDATE()';
    }

    $branch_filter = '';
    $params = [$company['id']];

    if ($branch_id > 0) {
        $branch_filter = 'AND s.branch_id = ?';
        $params[] = $branch_id;
    }

    // Sales summary
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_sales,
            COALESCE(SUM(s.total), 0) as total_revenue,
            COALESCE(SUM(s.discount), 0) as total_discounts,
            AVG(s.total) as avg_sale,
            COUNT(DISTINCT s.customer_id) as unique_customers
        FROM sales s
        WHERE s.tenant_id = ?
        AND $date_filter
        $branch_filter
    ");
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Hourly sales breakdown
    $stmt = $pdo->prepare("
        SELECT
            HOUR(s.created_at) as hour,
            COUNT(*) as sales_count,
            COALESCE(SUM(s.total), 0) as revenue
        FROM sales s
        WHERE s.tenant_id = ?
        AND $date_filter
        $branch_filter
        GROUP BY HOUR(s.created_at)
        ORDER BY hour
    ");
    $stmt->execute($params);
    $hourly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Category performance
    $stmt = $pdo->prepare("
        SELECT
            cat.name as category_name,
            COUNT(si.id) as items_sold,
            COALESCE(SUM(si.total), 0) as revenue
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        LEFT JOIN categories cat ON p.category_id = cat.id
        WHERE s.tenant_id = ?
        AND $date_filter
        $branch_filter
        GROUP BY cat.id, cat.name
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $category_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_success([
        'period' => $period,
        'summary' => [
            'total_sales' => (int) $summary['total_sales'],
            'total_revenue' => (float) $summary['total_revenue'],
            'total_discounts' => (float) $summary['total_discounts'],
            'avg_sale' => round((float) $summary['avg_sale'], 2),
            'unique_customers' => (int) $summary['unique_customers']
        ],
        'hourly_sales' => $hourly_sales,
        'category_performance' => $category_performance
    ]);
}

function get_manager_inventory($pdo, $company) {
    $branch_id = (int) ($_GET['branch_id'] ?? 0);

    $branch_filter = '';
    $params = [$company['id']];

    if ($branch_id > 0) {
        $branch_filter = 'AND i.branch_id = ?';
        $params[] = $branch_id;
    }

    // Inventory summary
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT p.id) as total_products,
            COALESCE(SUM(i.stock), 0) as total_stock_value,
            COUNT(CASE WHEN COALESCE(i.stock, 0) <= COALESCE(i.reorder_level, 10) THEN 1 END) as low_stock_items,
            COUNT(CASE WHEN COALESCE(i.stock, 0) = 0 THEN 1 END) as out_of_stock_items
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id $branch_filter AND i.tenant_id = ?
        WHERE p.tenant_id = ?
        AND p.active = 1
        AND p.deleted_at IS NULL
    ");
    $stmt->execute(array_merge($params, [$company['id']]));
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Low stock items
    $stmt = $pdo->prepare("
        SELECT
            p.id, p.name, p.sku, cat.name as category_name,
            COALESCE(i.stock, 0) as current_stock,
            COALESCE(i.reorder_level, 10) as reorder_level
        FROM products p
        LEFT JOIN categories cat ON p.category_id = cat.id
        LEFT JOIN inventory i ON p.id = i.product_id $branch_filter AND i.tenant_id = ?
        WHERE p.tenant_id = ?
        AND p.active = 1
        AND p.deleted_at IS NULL
        AND COALESCE(i.stock, 0) <= COALESCE(i.reorder_level, 10)
        AND COALESCE(i.stock, 0) > 0
        ORDER BY COALESCE(i.stock, 0) ASC
        LIMIT 20
    ");
    $stmt->execute(array_merge($params, [$company['id']]));
    $low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Out of stock items
    $stmt = $pdo->prepare("
        SELECT
            p.id, p.name, p.sku, cat.name as category_name,
            COALESCE(i.stock, 0) as current_stock
        FROM products p
        LEFT JOIN categories cat ON p.category_id = cat.id
        LEFT JOIN inventory i ON p.id = i.product_id $branch_filter AND i.tenant_id = ?
        WHERE p.tenant_id = ?
        AND p.active = 1
        AND p.deleted_at IS NULL
        AND COALESCE(i.stock, 0) = 0
        ORDER BY p.name
        LIMIT 20
    ");
    $stmt->execute(array_merge($params, [$company['id']]));
    $out_of_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_success([
        'summary' => [
            'total_products' => (int) $summary['total_products'],
            'total_stock_value' => (float) $summary['total_stock_value'],
            'low_stock_items' => (int) $summary['low_stock_items'],
            'out_of_stock_items' => (int) $summary['out_of_stock_items']
        ],
        'low_stock' => $low_stock,
        'out_of_stock' => $out_of_stock
    ]);
}

function get_manager_staff($pdo, $company) {
    $branch_id = (int) ($_GET['branch_id'] ?? 0);

    $branch_filter = '';
    $params = [$company['id']];

    if ($branch_id > 0) {
        $branch_filter = 'AND u.branch_id = ?';
        $params[] = $branch_id;
    }

    // Staff performance today
    $stmt = $pdo->prepare("
        SELECT
            u.id, u.name, u.username, u.role,
            COUNT(s.id) as sales_count,
            COALESCE(SUM(s.total), 0) as total_sales,
            AVG(s.total) as avg_sale,
            MAX(s.created_at) as last_sale
        FROM users u
        LEFT JOIN sales s ON u.id = s.user_id AND DATE(s.created_at) = CURDATE()
        WHERE u.tenant_id = ?
        AND u.active = 1
        $branch_filter
        GROUP BY u.id, u.name, u.username, u.role
        ORDER BY total_sales DESC
    ");
    $stmt->execute($params);
    $staff_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Active shifts
    $stmt = $pdo->prepare("
        SELECT
            s.id, u.name as staff_name, s.start_time,
            TIMESTAMPDIFF(MINUTE, s.start_time, NOW()) as minutes_active,
            s.opening_balance
        FROM shifts s
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'active'
        AND u.tenant_id = ?
        $branch_filter
        ORDER BY s.start_time DESC
    ");
    $stmt->execute($params);
    $active_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_success([
        'staff_performance' => $staff_performance,
        'active_shifts' => $active_shifts
    ]);
}

function get_manager_reports($pdo, $company) {
    $report_type = $_GET['type'] ?? 'daily';
    $branch_id = (int) ($_GET['branch_id'] ?? 0);
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    $branch_filter = '';
    $params = [$company['id'], $start_date, $end_date];

    if ($branch_id > 0) {
        $branch_filter = 'AND branch_id = ?';
        $params[] = $branch_id;
    }

    switch ($report_type) {
        case 'daily':
            $stmt = $pdo->prepare("
                SELECT
                    DATE(created_at) as date,
                    COUNT(*) as sales_count,
                    COALESCE(SUM(total), 0) as total_revenue,
                    COALESCE(SUM(discount), 0) as total_discounts,
                    COUNT(DISTINCT customer_id) as unique_customers
                FROM sales
                WHERE tenant_id = ?
                AND DATE(created_at) BETWEEN ? AND ?
                $branch_filter
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            break;

        case 'category':
            $stmt = $pdo->prepare("
                SELECT
                    cat.name as category_name,
                    COUNT(si.id) as items_sold,
                    SUM(si.quantity) as quantity_sold,
                    COALESCE(SUM(si.total), 0) as revenue
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                LEFT JOIN categories cat ON p.category_id = cat.id
                WHERE s.tenant_id = ?
                AND DATE(s.created_at) BETWEEN ? AND ?
                $branch_filter
                GROUP BY cat.id, cat.name
                ORDER BY revenue DESC
            ");
            break;

        case 'product':
            $stmt = $pdo->prepare("
                SELECT
                    p.name as product_name,
                    p.sku,
                    COUNT(si.id) as times_sold,
                    SUM(si.quantity) as quantity_sold,
                    COALESCE(SUM(si.total), 0) as revenue
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                WHERE s.tenant_id = ?
                AND DATE(s.created_at) BETWEEN ? AND ?
                $branch_filter
                GROUP BY p.id, p.name, p.sku
                ORDER BY revenue DESC
                LIMIT 50
            ");
            break;
    }

    $stmt->execute($params);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_success([
        'report_type' => $report_type,
        'date_range' => ['start' => $start_date, 'end' => $end_date],
        'data' => $report_data
    ]);
}

function get_manager_alerts($pdo, $company) {
    $branch_id = (int) ($_GET['branch_id'] ?? 0);

    $alerts = [];

    // Low stock alerts
    $stmt = $pdo->prepare("
        SELECT
            'low_stock' as type,
            CONCAT(p.name, ' (', COALESCE(i.stock, 0), ' remaining)') as message,
            'warning' as severity,
            p.id as item_id
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id AND i.tenant_id = ?
        WHERE p.tenant_id = ?
        AND p.deleted_at IS NULL
        AND p.active = 1
        AND COALESCE(i.stock, 0) <= COALESCE(i.reorder_level, 10)
        AND COALESCE(i.stock, 0) > 0
        ORDER BY COALESCE(i.stock, 0) ASC
        LIMIT 5
    ");
    $stmt->execute([$company['id'], $company['id']]);
    $alerts = array_merge($alerts, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Out of stock alerts
    $stmt = $pdo->prepare("
        SELECT
            'out_of_stock' as type,
            CONCAT(p.name, ' is out of stock') as message,
            'danger' as severity,
            p.id as item_id
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id AND i.tenant_id = ?
        WHERE p.tenant_id = ?
        AND p.active = 1
        AND p.deleted_at IS NULL
        AND COALESCE(i.stock, 0) = 0
        ORDER BY p.name
        LIMIT 5
    ");
    $stmt->execute([$company['id'], $company['id']]);
    $alerts = array_merge($alerts, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Pending orders/quotes
    $stmt = $pdo->prepare("
        SELECT
            'pending_orders' as type,
            CONCAT('Quote #', q.id, ' is pending approval') as message,
            'info' as severity,
            q.id as item_id
        FROM quotations q
        WHERE q.tenant_id = ?
        AND q.status = 'pending'
        ORDER BY q.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$company['id']]);
    $alerts = array_merge($alerts, $stmt->fetchAll(PDO::FETCH_ASSOC));

    mobile_success(['alerts' => $alerts]);
}
