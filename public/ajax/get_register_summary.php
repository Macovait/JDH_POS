<?php
/**
 * GET REGISTER SUMMARY - AJAX endpoint for POS
 * Version: 4.0 - Enhanced with type declarations and improved features
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - Complete shift summary
 * - Live sales totals
 * - Payment method breakdown
 * - Recent sales list
 * - Expected cash calculation
 * - Comprehensive error handling
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
 * Validate and resolve branch ID
 * 
 * @param int $requestedBranchId Requested branch ID
 * @param int $tenantId Tenant ID
 * @param PDO $pdo Database connection
 * @return int Valid branch ID or 0
 */
function resolveBranchId(int $requestedBranchId, int $tenantId, PDO $pdo): int {
    if ($requestedBranchId <= 0) {
        return 0;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$requestedBranchId, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['id'] : 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get current open session
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $branchId Branch ID
 * @return array|null Session data or null
 */
function getOpenSession(PDO $pdo, int $userId, int $branchId): ?array {
    try {
        $stmt = $pdo->prepare("
            SELECT id, tenant_id, branch_id, user_id, opening_cash, closing_cash,
                   expected_cash, cash_difference, total_sales, total_cash_sales,
                   total_card_sales, total_other_sales, sale_count, notes, status,
                   opened_at, closed_at
            FROM register_sessions
            WHERE user_id = ? AND branch_id = ? AND status = 'open'
            ORDER BY opened_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $branchId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get sales totals for the session
 * 
 * @param PDO $pdo Database connection
 * @param int $branchId Branch ID
 * @param int $userId User ID
 * @param string $openedAt Session opened timestamp
 * @return array Sales totals
 */
function getSessionTotals(PDO $pdo, int $branchId, int $userId, string $openedAt): array {
    try {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(s.total), 0) as total_sales,
                COALESCE(SUM(CASE WHEN s.payment_method = 'cash' THEN s.total ELSE 0 END), 0) as cash_sales,
                COALESCE(SUM(CASE WHEN s.payment_method = 'card' THEN s.total ELSE 0 END), 0) as card_sales,
                COALESCE(SUM(CASE WHEN s.payment_method = 'mpesa' THEN s.total ELSE 0 END), 0) as mpesa_sales,
                COALESCE(SUM(CASE WHEN s.payment_method NOT IN ('cash','card','mpesa') THEN s.total ELSE 0 END), 0) as other_sales,
                COUNT(*) as sale_count,
                COALESCE(SUM(s.discount), 0) as total_discounts,
                COALESCE(SUM(s.tax), 0) as total_tax,
                COALESCE(SUM(s.subtotal), 0) as total_subtotal,
                COALESCE(AVG(s.total), 0) as average_sale,
                COALESCE(MAX(s.total), 0) as max_sale,
                COALESCE(MIN(s.total), 0) as min_sale
            FROM sales s
            WHERE s.branch_id = ? AND s.user_id = ? 
              AND s.status = 'completed' AND s.voided = 0
              AND s.created_at >= ?
        ");
        $stmt->execute([$branchId, $userId, $openedAt]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: [
            'total_sales' => 0,
            'cash_sales' => 0,
            'card_sales' => 0,
            'mpesa_sales' => 0,
            'other_sales' => 0,
            'sale_count' => 0,
            'total_discounts' => 0,
            'total_tax' => 0,
            'total_subtotal' => 0,
            'average_sale' => 0,
            'max_sale' => 0,
            'min_sale' => 0
        ];
    } catch (Exception $e) {
        error_log("Error getting session totals: " . $e->getMessage());
        return [
            'total_sales' => 0,
            'cash_sales' => 0,
            'card_sales' => 0,
            'mpesa_sales' => 0,
            'other_sales' => 0,
            'sale_count' => 0,
            'total_discounts' => 0,
            'total_tax' => 0,
            'total_subtotal' => 0,
            'average_sale' => 0,
            'max_sale' => 0,
            'min_sale' => 0
        ];
    }
}

/**
 * Get recent sales for the session
 * 
 * @param PDO $pdo Database connection
 * @param int $branchId Branch ID
 * @param int $userId User ID
 * @param string $openedAt Session opened timestamp
 * @param int $limit Number of sales to return
 * @return array Recent sales
 */
function getRecentSales(PDO $pdo, int $branchId, int $userId, string $openedAt, int $limit = 20): array {
    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.invoice_number, s.total, s.payment_method, s.created_at,
                   s.subtotal, s.discount, s.tax,
                   COALESCE(c.name, 'Walk-in Customer') as customer_name
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id AND c.tenant_id = s.tenant_id
            WHERE s.branch_id = ? AND s.user_id = ? 
              AND s.status = 'completed' AND s.voided = 0
              AND s.created_at >= ?
            ORDER BY s.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$branchId, $userId, $openedAt, $limit]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting recent sales: " . $e->getMessage());
        return [];
    }
}

/**
 * Get hourly breakdown for the session
 * 
 * @param PDO $pdo Database connection
 * @param int $branchId Branch ID
 * @param int $userId User ID
 * @param string $openedAt Session opened timestamp
 * @return array Hourly breakdown
 */
function getHourlyBreakdown(PDO $pdo, int $branchId, int $userId, string $openedAt): array {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as transactions,
                SUM(total) as total,
                AVG(total) as average
            FROM sales
            WHERE branch_id = ? AND user_id = ? 
              AND status = 'completed' AND voided = 0
              AND created_at >= ?
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ");
        $stmt->execute([$branchId, $userId, $openedAt]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get payment method breakdown for the session
 * 
 * @param PDO $pdo Database connection
 * @param int $branchId Branch ID
 * @param int $userId User ID
 * @param string $openedAt Session opened timestamp
 * @return array Payment breakdown
 */
function getPaymentBreakdown(PDO $pdo, int $branchId, int $userId, string $openedAt): array {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                payment_method,
                COUNT(*) as count,
                SUM(total) as total
            FROM sales
            WHERE branch_id = ? AND user_id = ? 
              AND status = 'completed' AND voided = 0
              AND created_at >= ?
            GROUP BY payment_method
            ORDER BY total DESC
        ");
        $stmt->execute([$branchId, $userId, $openedAt]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: [];
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
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$tenantId = (int) ($_SESSION['tenant_id'] ?? $_SESSION['company_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$userRole = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

// Session tamper detection
$sessionTenant = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
$sessionUser = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (($sessionTenant !== null && $sessionTenant !== $tenantId) ||
    ($sessionUser !== null && $sessionUser !== $userId)) {
    error_log("ZERO-TRUST VIOLATION: Session mismatch in get_register_summary.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($userId <= 0 || $tenantId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// === 3. Rate Limiting ===
$rateKey = 'rate_limit_register_summary_' . $userId;
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

// === 5. Validate Branch ===
$requestedBranchId = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$validBranchId = resolveBranchId($requestedBranchId, $tenantId, $pdo);

if ($requestedBranchId > 0 && $validBranchId <= 0) {
    sendErrorResponse('Access denied to this branch', 'branch_denied', 403);
}

$branchId = $validBranchId > 0 ? $validBranchId : $branchId;

if ($branchId <= 0) {
    sendErrorResponse('Branch context missing', 'branch_missing', 403);
}

// === 6. Verify Branch Belongs to Tenant ===
$stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL AND is_active = 1");
$stmt->execute([$branchId, $tenantId]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    sendErrorResponse('Branch access denied', 'branch_denied', 403);
}

// === 7. Get Open Session ===
$session = getOpenSession($pdo, $userId, $branchId);

if (!$session) {
    sendJsonResponse([
        'success' => false,
        'message' => 'No open register session found',
        'has_open_session' => false
    ]);
}

// === 8. Calculate Session Totals ===
$openedAt = (string) $session['opened_at'];
$totals = getSessionTotals($pdo, $branchId, $userId, $openedAt);

// === 9. Get Recent Sales ===
$recentSales = getRecentSales($pdo, $branchId, $userId, $openedAt, 20);

// === 10. Get Hourly Breakdown ===
$hourlyBreakdown = getHourlyBreakdown($pdo, $branchId, $userId, $openedAt);

// === 11. Get Payment Breakdown ===
$paymentBreakdown = getPaymentBreakdown($pdo, $branchId, $userId, $openedAt);

// === 12. Build Response ===
$response = [
    'success' => true,
    'has_open_session' => true,
    'session' => [
        'id' => (int) $session['id'],
        'opening_cash' => (float) $session['opening_cash'],
        'opened_at' => $session['opened_at'],
        'duration' => [
            'hours' => (int) (time() - strtotime($session['opened_at'])) / 3600,
            'minutes' => (int) (time() - strtotime($session['opened_at'])) / 60,
        ],
        'sale_count' => (int) $totals['sale_count'],
        'total_sales' => (float) $totals['total_sales'],
        'cash_sales' => (float) $totals['cash_sales'],
        'card_sales' => (float) $totals['card_sales'],
        'mpesa_sales' => (float) ($totals['mpesa_sales'] ?? 0),
        'other_sales' => (float) $totals['other_sales'],
        'total_discounts' => (float) $totals['total_discounts'],
        'total_tax' => (float) $totals['total_tax'],
        'total_subtotal' => (float) ($totals['total_subtotal'] ?? 0),
        'average_sale' => (float) ($totals['average_sale'] ?? 0),
        'max_sale' => (float) ($totals['max_sale'] ?? 0),
        'min_sale' => (float) ($totals['min_sale'] ?? 0),
        'expected_cash' => (float) $session['opening_cash'] + (float) $totals['cash_sales'],
        'status' => $session['status'],
        'notes' => $session['notes'] ?? '',
        'recent_sales' => array_map(function(array $sale): array {
            return [
                'id' => (int) $sale['id'],
                'invoice_number' => (string) ($sale['invoice_number'] ?? ''),
                'total' => (float) $sale['total'],
                'subtotal' => (float) ($sale['subtotal'] ?? 0),
                'discount' => (float) ($sale['discount'] ?? 0),
                'tax' => (float) ($sale['tax'] ?? 0),
                'payment_method' => (string) $sale['payment_method'],
                'customer_name' => (string) $sale['customer_name'],
                'created_at' => (string) $sale['created_at']
            ];
        }, $recentSales),
        'hourly_breakdown' => array_map(function(array $hour): array {
            return [
                'hour' => (int) $hour['hour'],
                'hour_display' => date('g A', mktime((int)$hour['hour'], 0, 0)),
                'transactions' => (int) $hour['transactions'],
                'total' => (float) $hour['total'],
                'average' => (float) $hour['average']
            ];
        }, $hourlyBreakdown),
        'payment_breakdown' => array_map(function(array $payment): array {
            return [
                'method' => (string) $payment['payment_method'],
                'count' => (int) $payment['count'],
                'total' => (float) $payment['total']
            ];
        }, $paymentBreakdown)
    ],
    'branch' => [
        'id' => (int) $branch['id'],
        'name' => (string) $branch['name']
    ],
    'timestamp' => date('Y-m-d H:i:s')
];

sendJsonResponse($response);

} catch (PDOException $e) {
    error_log("Database error in get_register_summary.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    error_log("Error in get_register_summary.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    error_log("Fatal error in get_register_summary.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}