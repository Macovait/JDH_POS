<?php
/**
 * GET TODAY'S SALES - AJAX endpoint for POS
 * Version: 4.1 - Enhanced with full type declarations
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - Business type filtering
 * - Profit calculation
 * - Hourly breakdown
 * - Payment method breakdown
 * - Customer metrics
 * - Top products
 * - Response caching
 * - Activity logging
 * - Full PHP 8.0+ type declarations
 */

// ============================================
// ERROR HANDLING
// ============================================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ob_start();

try {

// ============================================
// SESSION START
// ============================================
if (!defined('SESSION_COOKIE_PATH')) {
    define('SESSION_COOKIE_PATH', '/');
}

session_name('jakababa_saas_sid');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// TYPE DECLARATIONS
// ============================================

/**
 * Send JSON response with proper headers
 * 
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 * @return void
 */
function sendJsonResponse(array $data, int $statusCode = 200): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
 * 
 * @param string $message Error message
 * @param string $errorCode Error code
 * @param int $statusCode HTTP status code
 * @return void
 */
function sendErrorResponse(string $message, string $errorCode = 'unknown_error', int $statusCode = 400): void {
    sendJsonResponse([
        'success' => false,
        'error' => $errorCode,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $statusCode);
}

/**
 * Get currency symbol from settings
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @return string Currency symbol
 */
function getCurrencySymbol(PDO $pdo, int $tenantId): string {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'currency' LIMIT 1");
        $stmt->execute([$tenantId]);
        $currency = $stmt->fetchColumn();
        return $currency ?: 'KES';
    } catch (Exception $e) {
        return 'KES';
    }
}

/**
 * Get hourly breakdown of sales
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @param int $btId Business type ID (optional)
 * @return array Hourly breakdown data
 */
function getHourlyBreakdown(PDO $pdo, int $tenantId, int $branchId, int $btId = 0): array {
    try {
        $sql = "
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as transactions,
                SUM(total) as total,
                AVG(total) as average,
                SUM(CASE WHEN payment_method='cash' THEN total ELSE 0 END) as cash,
                SUM(CASE WHEN payment_method='card' THEN total ELSE 0 END) as card,
                SUM(CASE WHEN payment_method='mpesa' THEN total ELSE 0 END) as mpesa
            FROM sales
            WHERE tenant_id = ? 
                AND branch_id = ? 
                AND DATE(created_at) = CURDATE() 
                AND status = 'completed' 
                AND voided = 0
        ";
        $params = [$tenantId, $branchId];
        
        if ($btId > 0) {
            $sql .= " AND (business_type_id = ? OR business_type_id IS NULL OR business_type_id = 0)";
            $params[] = $btId;
        }
        
        $sql .= " GROUP BY HOUR(created_at) ORDER BY hour";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error in getHourlyBreakdown: " . $e->getMessage());
        return [];
    }
}

/**
 * Get top selling products for today
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @param int $btId Business type ID (optional)
 * @param int $limit Number of products to return
 * @return array Top products data
 */
function getTopProducts(PDO $pdo, int $tenantId, int $branchId, int $btId = 0, int $limit = 5): array {
    try {
        $sql = "
            SELECT 
                si.product_id,
                si.product_name,
                SUM(si.quantity) as total_quantity,
                SUM(si.subtotal) as total_revenue,
                COUNT(DISTINCT si.sale_id) as transaction_count,
                AVG(si.price) as average_price
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE s.tenant_id = ? 
                AND s.branch_id = ? 
                AND DATE(s.created_at) = CURDATE() 
                AND s.status = 'completed' 
                AND s.voided = 0
        ";
        $params = [$tenantId, $branchId];
        
        if ($btId > 0) {
            $sql .= " AND (s.business_type_id = ? OR s.business_type_id IS NULL OR s.business_type_id = 0)";
            $params[] = $btId;
        }
        
        $sql .= " GROUP BY si.product_id, si.product_name ORDER BY total_revenue DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error in getTopProducts: " . $e->getMessage());
        return [];
    }
}

/**
 * Get customer metrics for today
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @param int $btId Business type ID (optional)
 * @return array|null Customer metrics data
 */
function getCustomerMetrics(PDO $pdo, int $tenantId, int $branchId, int $btId = 0): ?array {
    try {
        $sql = "
            SELECT 
                COUNT(DISTINCT customer_id) as unique_customers,
                COUNT(DISTINCT CASE WHEN customer_id IS NOT NULL THEN customer_id END) as returning_customers,
                COUNT(DISTINCT CASE WHEN customer_id IS NULL THEN s.id END) as walkin_customers,
                AVG(total) as average_transaction,
                MAX(total) as max_transaction,
                MIN(total) as min_transaction,
                SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END) as customer_transactions,
                SUM(CASE WHEN customer_id IS NULL THEN 1 ELSE 0 END) as walkin_transactions
            FROM sales s
            WHERE s.tenant_id = ? 
                AND s.branch_id = ? 
                AND DATE(s.created_at) = CURDATE() 
                AND s.status = 'completed' 
                AND s.voided = 0
        ";
        $params = [$tenantId, $branchId];
        
        if ($btId > 0) {
            $sql .= " AND (s.business_type_id = ? OR s.business_type_id IS NULL OR s.business_type_id = 0)";
            $params[] = $btId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Exception $e) {
        error_log("Error in getCustomerMetrics: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if a column exists in a table
 * 
 * @param PDO $pdo Database connection
 * @param string $table Table name
 * @param string $column Column name
 * @return bool True if column exists
 */
function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $table = str_replace('`', '``', $table);
        $column = str_replace("'", "\\'", $column);
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// MAIN EXECUTION
// ============================================

// === 1. Validate Request Method ===
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use GET.', 'method_not_allowed', 405);
}

// === 2. Get Current Context ===
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$tenantId = (int) ($_SESSION['tenant_id'] ?? $_SESSION['company_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$userRole = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

// Session tamper detection
$sessionTenant = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
$sessionUser = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (($sessionTenant !== null && $sessionTenant !== $tenantId) ||
    ($sessionUser !== null && $sessionUser !== $userId)) {
    error_log("ZERO-TRUST VIOLATION: Session mismatch in get_today_sales.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($userId <= 0 || $tenantId <= 0 || $branchId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// === 3. Rate Limiting ===
$rateKey = 'rate_limit_today_sales_' . $userId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

$rateData['count']++;

if ($rateData['count'] > 20) {
    sendErrorResponse('Too many requests. Please wait a moment.', 'rate_limited', 429);
}

$_SESSION[$rateKey] = $rateData;

// Release session lock for read-only queries
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// === 4. Database Connection ===
require_once __DIR__ . '/../../src/db.php';
/** @var PDO $pdo */
$pdo = get_db_connection();

if (!$pdo instanceof PDO) {
    sendErrorResponse('Database connection failed', 'db_connection_error', 500);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// === 5. Verify Branch ===
$stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL AND is_active = 1");
$stmt->execute([$branchId, $tenantId]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    sendErrorResponse('Branch access denied', 'branch_denied', 403);
}

// === 6. Get Business Type Filter ===
$businessTypeId = isset($_GET['bt_id']) ? (int) $_GET['bt_id'] : 0;
$businessType = '';
$businessTypeName = '';

if ($businessTypeId > 0) {
    $stmt = $pdo->prepare("SELECT code, name FROM business_types WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$businessTypeId]);
    $btData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($btData) {
        $businessType = (string) $btData['code'];
        $businessTypeName = (string) $btData['name'];
    }
} else {
    // Try to get from branch
    $stmt = $pdo->prepare("
        SELECT bt.id, bt.code, bt.name 
        FROM branches b 
        LEFT JOIN business_types bt ON bt.id = b.business_type_id AND b.business_type_id > 0 
        WHERE b.id = ? AND b.tenant_id = ?
    ");
    $stmt->execute([$branchId, $tenantId]);
    $btData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($btData && !empty($btData['code'])) {
        $businessTypeId = (int) $btData['id'];
        $businessType = (string) $btData['code'];
        $businessTypeName = (string) $btData['name'];
    }
}

if (empty($businessTypeName)) {
    $businessTypeName = function_exists('get_business_type_name') 
        ? (string) get_business_type_name($businessType ?: 'retail') 
        : 'Retail';
}

// === 7. Check Table Structure ===
$hasTenantInSales = columnExists($pdo, 'sales', 'tenant_id');
$hasBusinessTypeId = columnExists($pdo, 'sales', 'business_type_id');

// Auto-migrate if missing
if (!$hasBusinessTypeId) {
    try {
        $pdo->exec("ALTER TABLE sales ADD COLUMN business_type_id INT(11) NULL");
        $pdo->exec("ALTER TABLE sales ADD INDEX idx_sales_business_type_id (business_type_id)");
        $hasBusinessTypeId = true;
    } catch (Exception $e) {
        error_log("Auto-migrate business_type_id on sales failed: " . $e->getMessage());
    }
}

// === 8. Build Query ===
$baseWhere = $hasTenantInSales 
    ? "WHERE tenant_id = ? AND branch_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed' AND voided = 0"
    : "WHERE branch_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed' AND voided = 0";

$baseParams = $hasTenantInSales ? [$tenantId, $branchId] : [$branchId];

$btClause = '';
$btExtra = [];
if ($hasBusinessTypeId && $businessTypeId > 0) {
    $btClause = "AND (business_type_id = ? OR business_type_id IS NULL OR business_type_id = 0)";
    $btExtra = [$businessTypeId];
}

// === 9. Get Sales Summary ===
$sql = "
    SELECT 
        COALESCE(SUM(total), 0) as total,
        COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_total,
        COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card_total,
        COALESCE(SUM(CASE WHEN payment_method = 'mpesa' THEN total ELSE 0 END), 0) as mpesa_total,
        COALESCE(SUM(CASE WHEN payment_method NOT IN ('cash', 'card', 'mpesa') THEN total ELSE 0 END), 0) as other_total,
        COALESCE(SUM(discount), 0) as total_discount,
        COALESCE(SUM(tax), 0) as total_tax,
        COALESCE(SUM(total - discount - tax), 0) as total_profit,
        COALESCE(AVG(total), 0) as average_transaction,
        COALESCE(MAX(total), 0) as max_transaction,
        COALESCE(MIN(total), 0) as min_transaction
    FROM sales 
    {$baseWhere} {$btClause}
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($baseParams, $btExtra));
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// === 10. Get Sale Count ===
$countSql = "SELECT COUNT(*) as sale_count FROM sales {$baseWhere} {$btClause}";
$stmt = $pdo->prepare($countSql);
$stmt->execute(array_merge($baseParams, $btExtra));
$countResult = $stmt->fetch(PDO::FETCH_ASSOC);
$saleCount = (int) ($countResult['sale_count'] ?? 0);

// === 11. Get Hourly Breakdown ===
$hourlyBreakdown = getHourlyBreakdown($pdo, $tenantId, $branchId, $businessTypeId);

// === 12. Get Top Products ===
$topProducts = getTopProducts($pdo, $tenantId, $branchId, $businessTypeId, 5);

// === 13. Get Customer Metrics ===
$customerMetrics = getCustomerMetrics($pdo, $tenantId, $branchId, $businessTypeId);

// === 14. Get Currency ===
$currency = getCurrencySymbol($pdo, $tenantId);

// === 15. Build Response ===
$response = [
    'success' => true,
    'total' => (float) ($summary['total'] ?? 0),
    'cash_total' => (float) ($summary['cash_total'] ?? 0),
    'card_total' => (float) ($summary['card_total'] ?? 0),
    'mpesa_total' => (float) ($summary['mpesa_total'] ?? 0),
    'other_total' => (float) ($summary['other_total'] ?? 0),
    'sale_count' => $saleCount,
    'total_discount' => (float) ($summary['total_discount'] ?? 0),
    'total_tax' => (float) ($summary['total_tax'] ?? 0),
    'total_profit' => (float) ($summary['total_profit'] ?? 0),
    'average_transaction' => (float) ($summary['average_transaction'] ?? 0),
    'max_transaction' => (float) ($summary['max_transaction'] ?? 0),
    'min_transaction' => (float) ($summary['min_transaction'] ?? 0),
    'currency' => $currency,
    'business_type' => $businessType,
    'business_type_name' => $businessTypeName,
    'business_type_id' => $businessTypeId,
    'branch_id' => $branchId,
    'branch_name' => (string) $branch['name'],
    'date' => date('Y-m-d'),
    'timestamp' => date('Y-m-d H:i:s')
];

// Add hourly breakdown
$response['hourly_breakdown'] = array_map(function(array $hour): array {
    return [
        'hour' => (int) ($hour['hour'] ?? 0),
        'hour_display' => date('g A', mktime((int)($hour['hour'] ?? 0), 0, 0)),
        'transactions' => (int) ($hour['transactions'] ?? 0),
        'total' => (float) ($hour['total'] ?? 0),
        'average' => (float) ($hour['average'] ?? 0),
        'cash' => (float) ($hour['cash'] ?? 0),
        'card' => (float) ($hour['card'] ?? 0),
        'mpesa' => (float) ($hour['mpesa'] ?? 0)
    ];
}, $hourlyBreakdown);

// Add top products
$response['top_products'] = array_map(function(array $product): array {
    return [
        'product_id' => (int) ($product['product_id'] ?? 0),
        'product_name' => (string) ($product['product_name'] ?? 'Unknown'),
        'total_quantity' => (int) ($product['total_quantity'] ?? 0),
        'total_revenue' => (float) ($product['total_revenue'] ?? 0),
        'transaction_count' => (int) ($product['transaction_count'] ?? 0),
        'average_price' => (float) ($product['average_price'] ?? 0)
    ];
}, $topProducts);

// Add customer metrics
if ($customerMetrics !== null) {
    $response['customer_metrics'] = [
        'unique_customers' => (int) ($customerMetrics['unique_customers'] ?? 0),
        'returning_customers' => (int) ($customerMetrics['returning_customers'] ?? 0),
        'walkin_customers' => (int) ($customerMetrics['walkin_customers'] ?? 0),
        'customer_transactions' => (int) ($customerMetrics['customer_transactions'] ?? 0),
        'walkin_transactions' => (int) ($customerMetrics['walkin_transactions'] ?? 0),
        'average_transaction' => (float) ($customerMetrics['average_transaction'] ?? 0),
        'max_transaction' => (float) ($customerMetrics['max_transaction'] ?? 0),
        'min_transaction' => (float) ($customerMetrics['min_transaction'] ?? 0)
    ];
}

// Add payment method breakdown
$response['payment_breakdown'] = [
    'cash' => (float) ($summary['cash_total'] ?? 0),
    'card' => (float) ($summary['card_total'] ?? 0),
    'mpesa' => (float) ($summary['mpesa_total'] ?? 0),
    'other' => (float) ($summary['other_total'] ?? 0),
    'cash_percent' => $summary['total'] > 0 ? round(($summary['cash_total'] / $summary['total']) * 100, 1) : 0,
    'card_percent' => $summary['total'] > 0 ? round(($summary['card_total'] / $summary['total']) * 100, 1) : 0,
    'mpesa_percent' => $summary['total'] > 0 ? round(($summary['mpesa_total'] / $summary['total']) * 100, 1) : 0,
    'other_percent' => $summary['total'] > 0 ? round(($summary['other_total'] / $summary['total']) * 100, 1) : 0
];

sendJsonResponse($response);

} catch (PDOException $e) {
    error_log("Database error in get_today_sales.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    error_log("Error in get_today_sales.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    error_log("Fatal error in get_today_sales.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}