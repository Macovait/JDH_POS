<?php
// CRITICAL: Suppress ALL error output before ANYTHING else for JSON API
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@error_reporting(E_ALL);

// Start fresh output buffer to catch any stray output
while (ob_get_level()) @ob_end_clean();
ob_start();

// Register fatal error handler FIRST (catches compile/parse errors)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) @ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $error['message'], 'total' => 0, 'sales' => []]);
        exit;
    }
});

try {
    require_once __DIR__ . '/../../src/paths.php';
    safe_require('auth.php', 'src', true);
    safe_require('db.php', 'src', true);
    safe_require('functions.php', 'src', true);
} catch (Throwable $e) {
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Load error: ' . $e->getMessage(), 'total' => 0, 'sales' => []]);
    exit;
}

// Custom error handler - converts all errors to JSON response (after files are loaded)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "Error [$errno]: $errstr", 'total' => 0, 'sales' => []]);
    exit;
});

// Check permission
if (!is_super_admin() && !check_permission('sales.returns')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$requested_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

// Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
$branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
if ($branch_id <= 0 && $requested_branch_id > 0) {
    echo json_encode(['success' => false, 'error' => 'Access denied to this branch']);
    exit;
}

$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

// Get currency
$currency = 'KSh';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $curr = $stmt->fetchColumn();
    if ($curr) $currency = $curr;
} catch (Exception $e) {}

// Build WHERE clause
$where = ["s.tenant_id = ?", "s.branch_id = ?"];
$params = [$tenant_id, $branch_id];

if ($search !== '') {
    $where[] = "(s.invoice_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($date_from !== '') {
    $where[] = "DATE(s.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {
    $where[] = "DATE(s.created_at) <= ?";
    $params[] = $date_to;
}

$whereSql = implode(' AND ', $where);

// Apply business_type filtering if column exists in sales table
$has_bt_col = false;
try {
    $chk = $pdo->query("SHOW COLUMNS FROM sales LIKE 'business_type_id'");
    $has_bt_col = $chk && $chk->rowCount() > 0;
} catch (Exception $e) {}
if ($has_bt_col) {
    $bt_id = get_current_business_type_id();
    if ($bt_id) {
        $where[] = "(s.business_type_id = ? OR s.business_type_id IS NULL OR s.business_type_id = 0)";
        $params[] = $bt_id;
    }
    $whereSql = implode(' AND ', $where);
}

// Count total
try {
    $countSql = "SELECT COUNT(*) FROM sales s 
                 LEFT JOIN customers c ON s.customer_id = c.id 
                 WHERE $whereSql AND s.status = 'completed' AND s.voided = 0"
                 . (db_has_column('sales', 'is_active') ? " AND s.is_active = 1" : "");
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
} catch (Exception $e) {
    $total = 0;
}

$total_pages = max(1, (int)ceil($total / $limit));

// Fetch sales
try {
    if (!function_exists('db_has_column')) {
        throw new Exception('Required function db_has_column not found');
    }
    $sql = "SELECT s.id, s.invoice_number, s.total, s.created_at, s.payment_method, s.status,
               c.name as customer_name, c.phone as customer_phone,
               u.name as cashier_name,
               COUNT(DISTINCT si.id) as item_count,
               COALESCE(SUM(ri.quantity), 0) as returned_qty,
               (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id AND tenant_id = ?) as total_items
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN sale_items si ON si.sale_id = s.id
        LEFT JOIN return_items ri ON ri.sale_item_id = si.id AND ri.tenant_id = ?
        WHERE $whereSql AND s.status = 'completed' AND s.voided = 0"
        . (db_has_column('sales', 'is_active') ? " AND s.is_active = 1" : "")
        . " GROUP BY s.id ORDER BY s.created_at DESC LIMIT $limit OFFSET $offset";

    // Add the two tenant_id params for the subquery and JOIN condition
    $queryParams = array_merge([$tenant_id, $tenant_id], $params);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = ['success' => true, 'sales' => [], 'total' => (int)$total, 'page' => $page, 'total_pages' => $total_pages, 'currency' => $currency];

    // Calculate risk level for each sale
    $high_value_threshold = 10000;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'high_value_return_threshold' AND tenant_id = ?");
        $stmt->execute([$tenant_id]);
        $threshold = $stmt->fetchColumn();
        if ($threshold) $high_value_threshold = (float)$threshold;
    } catch (Exception $e) {}

    foreach ($sales as $sale) {
        $sale_id = $sale['id'];
        $total_items_in_sale = (int)$sale['total_items'];
        $total_returned_qty = (int)$sale['returned_qty'];
        
        if ($total_returned_qty >= $total_items_in_sale && $total_items_in_sale > 0) {
            $return_status = 'full';
        } elseif ($total_returned_qty > 0) {
            $return_status = 'partial';
        } else {
            $return_status = 'none';
        }
        
        // Calculate AI risk level based on amount and return history
        $amount = (float)$sale['total'];
        if ($amount > $high_value_threshold) {
            $risk_level = 'critical';
        } elseif ($amount > $high_value_threshold * 0.5) {
            $risk_level = 'high';
        } elseif ($return_status === 'partial') {
            $risk_level = 'medium';
        } else {
            $risk_level = 'low';
        }

        $result['sales'][] = [
            'id' => (int)$sale_id,
            'invoice_number' => $sale['invoice_number'],
            'total' => $amount,
            'total_formatted' => number_format($amount, 0),
            'total_value' => $amount, // for bulk selection
            'created_at' => $sale['created_at'],
            'date_formatted' => date('M d, Y', strtotime($sale['created_at'])),
            'time_formatted' => date('H:i', strtotime($sale['created_at'])),
            'customer_name' => $sale['customer_name'] ?: 'Walk-in',
            'customer_phone' => $sale['customer_phone'] ?: '',
            'cashier_name' => $sale['cashier_name'] ?: 'N/A',
            'payment_method' => $sale['payment_method'] ?: 'N/A',
            'item_count' => (int)$sale['item_count'],
            'return_status' => $return_status,
            'risk_level' => $risk_level
        ];
    }
    
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to load sales: ' . $e->getMessage(), 'total' => 0, 'sales' => [], 'page' => $page, 'total_pages' => 1, 'currency' => $currency]);
}

// Flush output buffer
if (ob_get_level()) ob_end_flush();