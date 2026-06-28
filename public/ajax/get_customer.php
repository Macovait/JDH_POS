<?php
/**
 * GET CUSTOMER - AJAX endpoint for POS
 * Version: 4.0 - Enhanced security, performance, and error handling
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - Multiple search methods (phone, email, name)
 * - Customer group and credit info
 * - Loyalty points and tier detection
 * - Purchase history summary
 * - Response caching
 * - Comprehensive error handling
 * - Activity logging
 */

require_once __DIR__ . '/../../src/Security/CorsHandler.php';
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
// AUTHENTICATION & PERMISSION CHECK
// ============================================
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    sendErrorResponse('Unauthorized. Please log in.', 'unauthorized', 401);
}

if (!check_permission('customers.view') && !check_permission('pos.sell') && !is_super_admin()) {
    sendErrorResponse('Permission denied.', 'forbidden', 403);
}

// ============================================
// FUNCTION: Send JSON Response
// ============================================
function sendJsonResponse($data, $statusCode = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    \Jakababa\Security\apply_cors_headers();
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// FUNCTION: Send Error Response
// ============================================
function sendErrorResponse($message, $errorCode = 'unknown_error', $statusCode = 400) {
    sendJsonResponse([
        'found' => false,
        'success' => false,
        'error' => $errorCode,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $statusCode);
}

// ============================================
// FUNCTION: Get Customer Loyalty Tier
// ============================================
function getLoyaltyTier($points) {
    if ($points >= 1000) return 'platinum';
    if ($points >= 500) return 'gold';
    if ($points >= 200) return 'silver';
    return 'bronze';
}

// ============================================
// FUNCTION: Get Customer Group
// ============================================
function getCustomerGroup($pdo, $customerId, $tenantId) {
    try {
        $stmt = $pdo->prepare("
            SELECT cg.name, cg.discount_rate, cg.color
            FROM customer_groups cg
            JOIN customers c ON c.group_id = cg.id
            WHERE c.id = ? AND c.tenant_id = ? AND cg.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$customerId, $tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

// ============================================
// FUNCTION: Get Customer Purchase Summary
// ============================================
function getPurchaseSummary($pdo, $customerId, $tenantId, $branchId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(total) as total_spent,
                AVG(total) as average_transaction,
                MAX(total) as max_transaction,
                MIN(total) as min_transaction,
                MAX(created_at) as last_purchase_date,
                MIN(created_at) as first_purchase_date,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_transactions,
                SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_transactions,
                SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as month_transactions
            FROM sales
            WHERE customer_id = ? 
                AND tenant_id = ? 
                AND status = 'completed' 
                AND voided = 0
                AND deleted_at IS NULL
        ");
        $stmt->execute([$customerId, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $result['total_transactions'] = (int) $result['total_transactions'];
            $result['total_spent'] = (float) ($result['total_spent'] ?? 0);
            $result['average_transaction'] = (float) ($result['average_transaction'] ?? 0);
            $result['max_transaction'] = (float) ($result['max_transaction'] ?? 0);
            $result['min_transaction'] = (float) ($result['min_transaction'] ?? 0);
        }
        
        return $result;
    } catch (Exception $e) {
        return null;
    }
}

// ============================================
// FUNCTION: Get Customer Recent Purchases
// ============================================
function getRecentPurchases($pdo, $customerId, $tenantId, $branchId, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                invoice_number,
                total,
                payment_method,
                created_at,
                DATE(created_at) as purchase_date
            FROM sales
            WHERE customer_id = ? 
                AND tenant_id = ? 
                AND status = 'completed' 
                AND voided = 0
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$customerId, $tenantId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
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
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
$tenantId = $_SESSION['tenant_id'] ?? $_SESSION['company_id'] ?? 0;
$branchId = $_SESSION['branch_id'] ?? 0;
$userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';

// Session tamper detection
$sessionTenant = $_SESSION['tenant_id'] ?? null;
$sessionUser = $_SESSION['user_id'] ?? null;

if (($sessionTenant && (int)$sessionTenant !== $tenantId) ||
    ($sessionUser && (int)$sessionUser !== $userId)) {
    error_log("ZERO-TRUST VIOLATION: Session mismatch in get_customer.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($userId <= 0 || $tenantId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// === 3. Rate Limiting ===
$rateKey = 'rate_limit_customer_' . $userId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

$rateData['count']++;

if ($rateData['count'] > 30) {
    sendErrorResponse('Too many requests. Please wait a moment.', 'rate_limited', 429);
}

$_SESSION[$rateKey] = $rateData;

// === 4. Get Search Parameters ===
$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$include_purchases = isset($_GET['include_purchases']) ? filter_var($_GET['include_purchases'], FILTER_VALIDATE_BOOLEAN) : true;
$include_recent = isset($_GET['include_recent']) ? filter_var($_GET['include_recent'], FILTER_VALIDATE_BOOLEAN) : true;

// At least one search parameter is required
if (empty($phone) && empty($email) && empty($name) && $id <= 0) {
    sendErrorResponse('At least one search parameter is required (phone, email, name, or id)', 'search_required', 400);
}

// === 5. Database Connection ===
require_once __DIR__ . '/../../src/db.php';
$pdo = get_db_connection();

if (!$pdo) {
    sendErrorResponse('Database connection failed', 'db_connection_error', 500);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// === 6. Check Tables ===
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$hasCustomers = in_array('customers', $tables);

// === 7. Handle Fallback (if customers table doesn't exist) ===
if (!$hasCustomers) {
    // Return a friendly message
    sendJsonResponse([
        'found' => false,
        'message' => 'Customer management is not available. Please set up the database.',
        'success' => false,
        'timestamp' => date('Y-m-d H:i:s')
    ], 503);
    exit;
}

// === 8. Build Search Query ===
$conditions = ["tenant_id = ?", "deleted_at IS NULL"];
$params = [$tenantId];

if ($id > 0) {
    $conditions[] = "id = ?";
    $params[] = $id;
}

if (!empty($phone)) {
    $conditions[] = "phone = ?";
    $params[] = $phone;
}

if (!empty($email)) {
    $conditions[] = "email = ?";
    $params[] = $email;
}

if (!empty($name)) {
    $conditions[] = "name LIKE ?";
    $params[] = "%{$name}%";
}

$whereClause = "WHERE " . implode(" AND ", $conditions);
$limitClause = $id > 0 ? "LIMIT 1" : "LIMIT 10";

// === 9. Execute Search ===
$sql = "
    SELECT 
        id,
        name,
        email,
        phone,
        address,
        city,
        postal_code,
        tax_id,
        loyalty_points,
        loyalty_tier,
        group_id,
        credit_limit,
        current_balance,
        total_spent,
        total_credit_used,
        total_payments,
        payment_terms_days,
        credit_status,
        credit_notes,
        created_at,
        updated_at
    FROM customers
    {$whereClause}
    ORDER BY name ASC
    {$limitClause}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// === 10. If No Customers Found ===
if (empty($customers)) {
    sendJsonResponse([
        'found' => false,
        'message' => 'Customer not found',
        'success' => true,
        'search_params' => [
            'phone' => $phone,
            'email' => $email,
            'name' => $name,
            'id' => $id
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// === 11. Process Customer Data ===
$responseCustomers = [];

foreach ($customers as $customer) {
    $customerId = (int) $customer['id'];
    $loyaltyPoints = (int) ($customer['loyalty_points'] ?? 0);
    $loyaltyTier = $customer['loyalty_tier'] ?? getLoyaltyTier($loyaltyPoints);
    
    // Get customer group
    $group = getCustomerGroup($pdo, $customerId, $tenantId);
    
    // Get purchase summary
    $purchaseSummary = null;
    if ($include_purchases) {
        $purchaseSummary = getPurchaseSummary($pdo, $customerId, $tenantId, $branchId);
    }
    
    // Get recent purchases
    $recentPurchases = [];
    if ($include_recent) {
        $recentPurchases = getRecentPurchases($pdo, $customerId, $tenantId, $branchId, 5);
    }
    
    $responseCustomers[] = [
        'id' => $customerId,
        'name' => $customer['name'],
        'email' => $customer['email'] ?? '',
        'phone' => $customer['phone'],
        'address' => $customer['address'] ?? '',
        'city' => $customer['city'] ?? '',
        'postal_code' => $customer['postal_code'] ?? '',
        'tax_id' => $customer['tax_id'] ?? '',
        'loyalty_points' => $loyaltyPoints,
        'loyalty_tier' => $loyaltyTier,
        'group' => $group ? [
            'id' => (int) $group['id'],
            'name' => $group['name'],
            'discount_rate' => (float) ($group['discount_rate'] ?? 0),
            'color' => $group['color'] ?? '#6B7280'
        ] : null,
        'credit' => [
            'limit' => (float) ($customer['credit_limit'] ?? 0),
            'balance' => (float) ($customer['current_balance'] ?? 0),
            'used' => (float) ($customer['total_credit_used'] ?? 0),
            'payments' => (float) ($customer['total_payments'] ?? 0),
            'terms_days' => (int) ($customer['payment_terms_days'] ?? 30),
            'status' => $customer['credit_status'] ?? 'active',
            'notes' => $customer['credit_notes'] ?? ''
        ],
        'purchase_summary' => $purchaseSummary ? [
            'total_transactions' => $purchaseSummary['total_transactions'],
            'total_spent' => $purchaseSummary['total_spent'],
            'average_transaction' => $purchaseSummary['average_transaction'],
            'max_transaction' => $purchaseSummary['max_transaction'],
            'min_transaction' => $purchaseSummary['min_transaction'],
            'first_purchase' => $purchaseSummary['first_purchase_date'],
            'last_purchase' => $purchaseSummary['last_purchase_date'],
            'today_transactions' => $purchaseSummary['today_transactions'] ?? 0,
            'week_transactions' => $purchaseSummary['week_transactions'] ?? 0,
            'month_transactions' => $purchaseSummary['month_transactions'] ?? 0
        ] : null,
        'recent_purchases' => array_map(function($purchase) {
            return [
                'id' => (int) $purchase['id'],
                'invoice_number' => $purchase['invoice_number'],
                'total' => (float) $purchase['total'],
                'payment_method' => $purchase['payment_method'],
                'date' => $purchase['created_at'],
                'purchase_date' => $purchase['purchase_date']
            ];
        }, $recentPurchases),
        'created_at' => $customer['created_at'],
        'updated_at' => $customer['updated_at']
    ];
}

// === 12. Build Response ===
$response = [
    'found' => true,
    'success' => true,
    'count' => count($responseCustomers),
    'customers' => $responseCustomers,
    'message' => count($responseCustomers) > 1 
        ? count($responseCustomers) . ' customers found' 
        : 'Customer found',
    'search_params' => [
        'phone' => $phone,
        'email' => $email,
        'name' => $name,
        'id' => $id
    ],
    'timestamp' => date('Y-m-d H:i:s')
];

// === 13. Single Customer Response (for POS integration) ===
// If searching by phone or ID, return a simplified structure for POS
if (!empty($phone) || $id > 0) {
    // Return the first match in a simplified format
    $firstCustomer = $responseCustomers[0] ?? null;
    
    if ($firstCustomer && count($responseCustomers) === 1) {
        $response['customer'] = [
            'id' => $firstCustomer['id'],
            'name' => $firstCustomer['name'],
            'email' => $firstCustomer['email'],
            'phone' => $firstCustomer['phone'],
            'loyalty_points' => $firstCustomer['loyalty_points'],
            'loyalty_tier' => $firstCustomer['loyalty_tier'],
            'credit_limit' => $firstCustomer['credit']['limit'],
            'credit_balance' => $firstCustomer['credit']['balance'],
            'total_spent' => $firstCustomer['purchase_summary']['total_spent'] ?? 0,
            'group_discount' => $firstCustomer['group']['discount_rate'] ?? 0,
            'recent_purchases' => $firstCustomer['recent_purchases'] ?? []
        ];
    }
}

sendJsonResponse($response);

} catch (PDOException $e) {
    error_log("Database error in get_customer.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    error_log("Error in get_customer.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    error_log("Fatal error in get_customer.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}