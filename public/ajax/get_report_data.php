<?php
/**
 * AJAX endpoint for fetching report data
 * Returns JSON data for reports based on filters
 * Enhanced with better error handling and data validation
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Define base path correctly
$root_path = dirname(dirname(__DIR__)); // This goes from /ajax to /public to /JDH POS/

// Load paths.php first
$paths_file = $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';
if (!file_exists($paths_file)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'System configuration not found']);
    exit;
}

require_once $paths_file;

// Load all core files at once
load_core_files();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check login (SaaS session keys)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Check for reports permission
if (!check_permission('reports.view') && !is_super_admin()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

header('Content-Type: application/json');

// Get filter parameters with defaults
$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$branch_filter = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : get_current_branch_id();
$report_type = isset($_GET['type']) ? $_GET['type'] : 'sales';
$user_id = get_current_user_id();

// ZERO-TRUST: Tenant ID ONLY from session - NEVER from URL parameters
if (!empty($_GET['tenant_id'])) {
    error_log("ZERO-TRUST VIOLATION: tenant_id submitted in URL by user {$user_id}");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}
$tenant_id = (int)($_SESSION['tenant_id'] ?? 0);

// ZERO-TRUST: reject if tenant cannot be determined
if ($tenant_id <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Company context missing']);
    exit;
}

// ZERO-TRUST: Validate branch belongs to tenant
if ($branch_filter > 0) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$branch_filter, $tenant_id]);
    if (!$stmt->fetch()) {
        error_log("ZERO-TRUST: Branch access denied - branch_id={$branch_filter} not in tenant {$tenant_id}");
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Branch access denied']);
        exit;
    }
}
$business_type = get_current_business_type($tenant_id);
$bt_config = get_business_type_config($business_type) ?? [];
$bt_name = $bt_config['name'] ?? 'Retail';
$bt_sale_label = $bt_config['sale_label'] ?? 'Sale';
$bt_sales_label = $bt_config['sales_label'] ?? 'Sales';
$bt_customer_label = $bt_config['customer_label'] ?? 'Customer';

// Validate dates
try {
    $date_from_obj = new DateTime($date_from);
    $date_to_obj = new DateTime($date_to);
    $date_from = $date_from_obj->format('Y-m-d');
    $date_to = $date_to_obj->format('Y-m-d');
} catch (Exception $e) {
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-d');
}

// Log for debugging
error_log("get_report_data - Branch: $branch_filter, From: $date_from, To: $date_to, Type: $report_type");

try {
    $pdo = get_db_connection();

    if (!$pdo) {
        throw new Exception("Failed to get database connection");
    }

    // Verify user has access to selected branch
    if (!is_super_admin() && !check_permission('branches.view')) {
        $branch_filter = get_current_branch_id();
    }

    // Column availability (avoid SQL errors on older schemas)
    $has_company_col = db_has_column('sales', 'tenant_id');
    $has_branch_col = db_has_column('sales', 'branch_id');
    $has_bt_col = db_has_column('sales', 'business_type');
    $has_user_col = db_has_column('sales', 'user_id');

    // Build parameters array
    $params = [
        ':date_from' => $date_from,
        ':date_to' => $date_to
    ];

    // Add tenant_id filter for SaaS multi-tenancy (required!)
    $company_condition = "";
    if ($tenant_id > 0 && $has_company_col) {
        $company_condition = " AND s.tenant_id = :tenant_id";
        $params[':tenant_id'] = $tenant_id;
    }

    // Add branch parameter if needed
    $branch_condition = "";
    if ($branch_filter > 0 && $has_branch_col) {
        $branch_condition = " AND s.branch_id = :branch_id";
        $params[':branch_id'] = $branch_filter;
    }

    // Business type filter (only when the column exists)
    $bt_condition = "";
    if ($has_bt_col && !empty($business_type)) {
        $bt_condition = " AND (s.business_type = :business_type OR s.business_type IS NULL OR s.business_type = '')";
        $params[':business_type'] = $business_type;
    }

    // User filter (restrict to current user when the column exists)
    $user_condition = "";
    if ($has_user_col && $user_id > 0) {
        $user_condition = " AND s.user_id = :user_id";
        $params[':user_id'] = $user_id;
    }

    $filter_conditions = $company_condition . $branch_condition . $bt_condition . $user_condition;

    // Determine group by based on date range
    $days_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24);

    if ($days_diff <= 7) {
        $group_by = "DATE(s.created_at)";
        $chart_label = "Daily";
    } elseif ($days_diff <= 31) {
        $group_by = "DATE(s.created_at)";
        $chart_label = "Daily";
    } elseif ($days_diff <= 93) {
        $group_by = "YEARWEEK(s.created_at, 1)";
        $chart_label = "Weekly";
    } else {
        $group_by = "DATE_FORMAT(s.created_at, '%Y-%m')";
        $chart_label = "Monthly";
    }

    // Get sales by period
    $sales_sql = "
        SELECT 
            {$group_by} as period,
            DATE(s.created_at) as date,
            COUNT(DISTINCT s.id) as transaction_count,
            COALESCE(SUM(s.total), 0) as total_sales,
            COALESCE(SUM(s.discount), 0) as total_discount,
            COALESCE(AVG(s.total), 0) as average_sale,
            COALESCE(SUM(CASE WHEN LOWER(s.payment_method) = 'cash' THEN s.total ELSE 0 END), 0) as cash_sales,
            COALESCE(SUM(CASE WHEN LOWER(s.payment_method) = 'card' THEN s.total ELSE 0 END), 0) as card_sales,
            COALESCE(SUM(CASE WHEN LOWER(s.payment_method) = 'mpesa' THEN s.total ELSE 0 END), 0) as mpesa_sales,
            COALESCE(SUM(CASE WHEN LOWER(s.payment_method) = 'bank_transfer' THEN s.total ELSE 0 END), 0) as bank_sales,
            COALESCE(SUM(CASE WHEN LOWER(s.payment_method) = 'split' THEN s.total ELSE 0 END), 0) as split_sales
        FROM sales s
        WHERE DATE(s.created_at) BETWEEN :date_from AND :date_to
        AND s.status = 'completed'
    " . $filter_conditions . "
        GROUP BY period
        ORDER BY period ASC
    ";

    $stmt = $pdo->prepare($sales_sql);
    $stmt->execute($params);
    $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Sales data count: " . count($sales_data));

    // Get summary statistics
    $summary_sql = "
        SELECT 
            COUNT(DISTINCT s.id) as total_transactions,
            COALESCE(SUM(s.total), 0) as total_revenue,
            COALESCE(AVG(s.total), 0) as avg_transaction,
            COALESCE(SUM(s.discount), 0) as total_discounts,
            COUNT(DISTINCT DATE(s.created_at)) as active_days,
            COUNT(DISTINCT s.customer_id) as unique_customers,
            COALESCE(SUM(CASE WHEN LOWER(s.payment_method) = 'cash' THEN s.total ELSE 0 END), 0) as cash_total,
            COALESCE(SUM(CASE WHEN LOWER(s.payment_method) = 'card' THEN s.total ELSE 0 END), 0) as card_total,
            COALESCE(SUM(CASE WHEN LOWER(s.payment_method) = 'mpesa' THEN s.total ELSE 0 END), 0) as mpesa_total,
            COALESCE(SUM(CASE WHEN LOWER(s.payment_method) = 'bank_transfer' THEN s.total ELSE 0 END), 0) as bank_total,
            COALESCE(SUM(CASE WHEN LOWER(s.payment_method) = 'split' THEN s.total ELSE 0 END), 0) as split_total
        FROM sales s
        WHERE DATE(s.created_at) BETWEEN :date_from AND :date_to
        AND s.status = 'completed'
    " . $filter_conditions;

    $stmt = $pdo->prepare($summary_sql);
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$summary) {
        $summary = [
            'total_transactions' => 0,
            'total_revenue' => 0,
            'avg_transaction' => 0,
            'total_discounts' => 0,
            'active_days' => 0,
            'unique_customers' => 0,
            'cash_total' => 0,
            'card_total' => 0,
            'mpesa_total' => 0,
            'bank_total' => 0,
            'split_total' => 0
        ];
    }

    error_log("Summary revenue: " . $summary['total_revenue']);

    // Get top products
    $top_products_sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.price as unit_price,
            COALESCE(SUM(si.quantity), 0) as quantity_sold,
            COALESCE(SUM(si.quantity * si.price), 0) as revenue,
            COALESCE(SUM(p.cost_price * si.quantity), 0) as total_cost,
            COUNT(DISTINCT s.id) as times_sold
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        WHERE DATE(s.created_at) BETWEEN :date_from AND :date_to
        AND s.status = 'completed'
    " . $filter_conditions . "
        GROUP BY p.id
        ORDER BY revenue DESC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($top_products_sql);
    $stmt->execute($params);
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Top products count: " . count($top_products));

    // Get hourly data
    $hourly_sql = "
        SELECT 
            HOUR(s.created_at) as hour,
            COUNT(*) as transaction_count,
            COALESCE(SUM(s.total), 0) as total_sales
        FROM sales s
        WHERE DATE(s.created_at) BETWEEN :date_from AND :date_to
        AND s.status = 'completed'
    " . $filter_conditions . "
        GROUP BY HOUR(s.created_at)
        ORDER BY hour
    ";

    $stmt = $pdo->prepare($hourly_sql);
    $stmt->execute($params);
    $hourly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fill in missing hours with zeros
    $complete_hourly = [];
    for ($i = 0; $i < 24; $i++) {
        $found = false;
        foreach ($hourly_data as $hour) {
            if ($hour['hour'] == $i) {
                $complete_hourly[] = $hour;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $complete_hourly[] = [
                'hour' => $i,
                'transaction_count' => 0,
                'total_sales' => 0
            ];
        }
    }
    $hourly_data = $complete_hourly;

    // Get payment methods
    $payment_sql = "
        SELECT 
            COALESCE(s.payment_method, 'cash') as payment_method,
            COUNT(*) as count,
            COALESCE(SUM(s.total), 0) as total,
            COALESCE(AVG(s.total), 0) as average
        FROM sales s
        WHERE DATE(s.created_at) BETWEEN :date_from AND :date_to
        AND s.status = 'completed'
    " . $filter_conditions . "
        GROUP BY s.payment_method
        ORDER BY total DESC
    ";

    $stmt = $pdo->prepare($payment_sql);
    $stmt->execute($params);
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no payment methods found, provide default
    if (empty($payment_methods)) {
        $payment_methods = [
            ['payment_method' => 'cash', 'count' => 0, 'total' => 0, 'average' => 0]
        ];
    }

    // Calculate previous period for growth
    $prev_date_from = date('Y-m-d', strtotime($date_from . ' -' . ($days_diff + 1) . ' days'));
    $prev_date_to = date('Y-m-d', strtotime($date_to . ' -' . ($days_diff + 1) . ' days'));

    $prev_params = [
        ':date_from' => $prev_date_from,
        ':date_to' => $prev_date_to
    ];

    if ($branch_filter > 0 && $has_branch_col) {
        $prev_params[':branch_id'] = $branch_filter;
    }
    if ($has_company_col && $tenant_id > 0) {
        $prev_params[':tenant_id'] = $tenant_id;
    }
    if ($has_bt_col && !empty($business_type)) {
        $prev_params[':business_type'] = $business_type;
    }
    if ($has_user_col && $user_id > 0) {
        $prev_params[':user_id'] = $user_id;
    }

    $previous_sql = "
        SELECT COALESCE(SUM(s.total), 0) as total
        FROM sales s
        WHERE DATE(s.created_at) BETWEEN :date_from AND :date_to
        AND s.status = 'completed'
    " . $filter_conditions;

    $stmt = $pdo->prepare($previous_sql);
    $stmt->execute($prev_params);
    $previous_total = $stmt->fetchColumn();

    if (!$previous_total) {
        $previous_total = 0;
    }

    $growth_percentage = $previous_total > 0 ? round((($summary['total_revenue'] - $previous_total) / $previous_total) * 100, 1) : 0;

    // Get branch name
    $branch_name = 'Main Branch';
    if ($branch_filter > 0) {
        $branch_sql = "SELECT name FROM branches WHERE id = ?";
        $branch_params = [$branch_filter];
        if ($tenant_id > 0 && db_has_column('branches', 'tenant_id')) {
            $branch_sql .= " AND tenant_id = ?";
            $branch_params[] = $tenant_id;
        }
        $stmt = $pdo->prepare($branch_sql);
        $stmt->execute($branch_params);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($branch) {
            $branch_name = $branch['name'];
        }
    }

    // Calculate additional metrics
    $customer_retention = ($summary['unique_customers'] ?? 0) > 0 ?
        round(($summary['total_transactions'] ?? 0) / $summary['unique_customers'], 1) : 0;

    $discount_rate = ($summary['total_revenue'] ?? 0) > 0 ?
        round(($summary['total_discounts'] ?? 0) / $summary['total_revenue'] * 100, 1) : 0;

    $daily_average = ($summary['active_days'] ?? 0) > 0 ?
        round($summary['total_revenue'] / $summary['active_days'], 2) : 0;

    // Prepare response with all data
    $response = [
        'success' => true,
        'branch' => [
            'id' => $branch_filter,
            'name' => $branch_name
        ],
        'bt_config' => [
            'type' => $business_type,
            'name' => $bt_name,
            'sale_label' => $bt_sale_label,
            'sales_label' => $bt_sales_label,
            'customer_label' => $bt_customer_label,
            'icon' => $bt_config['icon'] ?? 'fa-store',
        ],
        'summary' => $summary,
        'sales_data' => $sales_data,
        'top_products' => $top_products,
        'hourly_data' => $hourly_data,
        'payment_methods' => $payment_methods,
        'growth_percentage' => $growth_percentage,
        'chart_label' => $chart_label,
        'date_from' => $date_from,
        'date_to' => $date_to,
        'metrics' => [
            'daily_average' => $daily_average,
            'customer_retention' => $customer_retention,
            'discount_rate' => $discount_rate
        ],
        'formatted' => [
            'total_revenue' => 'KSh ' . number_format($summary['total_revenue'] ?? 0, 2),
            'total_transactions' => number_format($summary['total_transactions'] ?? 0),
            'avg_transaction' => 'KSh ' . number_format($summary['avg_transaction'] ?? 0, 2),
            'total_discounts' => 'KSh ' . number_format($summary['total_discounts'] ?? 0, 2),
            'unique_customers' => number_format($summary['unique_customers'] ?? 0),
            'customer_retention' => $customer_retention,
            'discount_rate' => $discount_rate . '%',
            'daily_average' => 'KSh ' . number_format($daily_average, 2)
        ]
    ];

// Add debug info if requested
    if (isset($_GET['debug'])) {
        $response['debug'] = [
            'session_company_id' => $_SESSION['tenant_id'] ?? 'not set',
            'url_company_id' => $_GET['tenant_id'] ?? 'not set',
            'resolved_company_id' => $tenant_id,
            'branch_filter' => $branch_filter,
            'user_id' => $user_id,
            'business_type' => $business_type,
            'params' => $params,
            'company_condition' => $company_condition,
            'branch_condition' => $branch_condition,
            'business_type_condition' => $bt_condition,
            'user_condition' => $user_condition,
            'days_diff' => $days_diff,
            'group_by' => $group_by
        ];
    }

    echo json_encode($response);
    error_log("get_report_data completed successfully");

} catch (PDOException $e) {
    $err_msg = "Database error: " . $e->getMessage();
    error_log("Database error in get_report_data: " . $err_msg);
    $response = ['success' => false, 'error' => $err_msg];
    if (isset($_GET['debug'])) {
        $response['debug']['params_keys'] = array_keys($params);
        $response['debug']['company_id_used'] = $tenant_id;
        $response['debug']['branch_filter'] = $branch_filter;
        $response['debug']['user_id'] = $user_id;
        $response['debug']['company_condition'] = $company_condition;
        $response['debug']['branch_condition'] = $branch_condition;
        $response['debug']['business_type_condition'] = $bt_condition ?? '';
        $response['debug']['user_condition'] = $user_condition ?? '';
    }
    echo json_encode($response);
} catch (Exception $e) {
    $err_msg = $e->getMessage();
    error_log("General error in get_report_data: " . $err_msg);
    $response = ['success' => false, 'error' => $err_msg];
    if (isset($_GET['debug'])) {
        $response['debug']['exception'] = $e->getTraceAsString();
    }
    echo json_encode($response);
}
