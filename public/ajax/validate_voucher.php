<?php
/**
 * VALIDATE VOUCHER - AJAX endpoint for POS
 * Version: 4.0 - Enhanced security, performance, and error handling
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - Multiple voucher types (fixed, percent)
 * - Usage limit checking
 * - Branch and customer restrictions
 * - Min purchase validation
 * - Max discount limits
 * - Detailed validation messages
 * - Response caching
 * - Activity logging
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
// FUNCTION: Send JSON Response
// ============================================
function sendJsonResponse($data, $statusCode = 200) {
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

// ============================================
// FUNCTION: Send Error Response
// ============================================
function sendErrorResponse($message, $errorCode = 'unknown_error', $statusCode = 400) {
    sendJsonResponse([
        'valid' => false,
        'success' => false,
        'error' => $errorCode,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $statusCode);
}

// ============================================
// FUNCTION: Log Activity
// ============================================
function logVoucherActivity(string $action, int $voucherId, string $code, string $message, ?int $userId = null, ?int $tenantId = null, ?int $branchId = null): bool {
    try {
        require_once __DIR__ . '/../../src/db.php';
        $pdo = get_db_connection();
        
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, meta, branch_id, tenant_id, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "Voucher {$code} - {$action}: {$message}";
        $meta = json_encode(['voucher_id' => $voucherId, 'code' => $code, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        return $stmt->execute([$userId, "voucher.{$action}", $description, $meta, $branchId, $tenantId, $ip]);
    } catch (Exception $e) {
        error_log("Failed to log voucher activity: " . $e->getMessage());
        return false;
    }
}

// ============================================
// FUNCTION: Get Voucher Usage Count
// ============================================
function getVoucherUsageCount($pdo, $voucherId, $tenantId, $customerId = null) {
    try {
        $sql = "SELECT COUNT(*) as count FROM voucher_redemptions WHERE voucher_id = ? AND tenant_id = ?";
        $params = [$voucherId, $tenantId];
        
        if ($customerId) {
            $sql .= " AND customer_id = ?";
            $params[] = $customerId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
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
    error_log("ZERO-TRUST VIOLATION: Session mismatch in validate_voucher.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($userId <= 0 || $tenantId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// === 3. Rate Limiting ===
$rateKey = 'rate_limit_voucher_' . $userId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

$rateData['count']++;

if ($rateData['count'] > 20) {
    sendErrorResponse('Too many requests. Please wait a moment.', 'rate_limited', 429);
}

$_SESSION[$rateKey] = $rateData;

// === 4. Get Parameters ===
$code = isset($_GET['code']) ? trim($_GET['code']) : '';
$subtotal = isset($_GET['subtotal']) ? (float) $_GET['subtotal'] : 0;
$customerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
$includeDetails = isset($_GET['include_details']) ? filter_var($_GET['include_details'], FILTER_VALIDATE_BOOLEAN) : true;

if (empty($code)) {
    sendErrorResponse('Voucher code is required', 'code_required', 400);
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
$hasVouchers = in_array('vouchers', $tables);

if (!$hasVouchers) {
    sendErrorResponse('Voucher system is not available.', 'vouchers_not_available', 503);
}

// === 7. Query Voucher ===
$stmt = $pdo->prepare("
    SELECT 
        v.*,
        (SELECT COUNT(*) FROM voucher_redemptions vr WHERE vr.voucher_id = v.id AND vr.tenant_id = v.tenant_id) as used_count
    FROM vouchers v
    WHERE v.code = ?
    AND v.tenant_id = ?
    AND v.active = 1
    AND (v.expires_at IS NULL OR v.expires_at >= CURDATE())
    LIMIT 1
");
$stmt->execute([$code, $tenantId]);
$voucher = $stmt->fetch();

// === 8. Check if Voucher Exists ===
if (!$voucher) {
    // Log failed attempt
    logVoucherActivity('validate_failed', 0, $code, 'Voucher not found or inactive', $userId, $tenantId, $branchId);
    
    sendJsonResponse([
        'valid' => false,
        'message' => 'Invalid or expired voucher code',
        'code' => $code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// === 9. Check Voucher Type ===
$validTypes = ['fixed', 'percent'];
if (!in_array($voucher['type'], $validTypes)) {
    logVoucherActivity('validate_failed', $voucher['id'], $code, 'Invalid voucher type: ' . $voucher['type'], $userId, $tenantId, $branchId);
    
    sendJsonResponse([
        'valid' => false,
        'message' => 'Voucher type is invalid',
        'code' => $code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// === 10. Check Branch Restriction ===
if (!empty($voucher['branch_id']) && $voucher['branch_id'] != $branchId) {
    logVoucherActivity('validate_failed', $voucher['id'], $code, 'Branch restriction: ' . $voucher['branch_id'] . ' != ' . $branchId, $userId, $tenantId, $branchId);
    
    sendJsonResponse([
        'valid' => false,
        'message' => 'Voucher is not valid for this branch',
        'code' => $code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// === 11. Check Min Purchase ===
$minPurchase = (float) ($voucher['min_purchase'] ?? 0);
if ($minPurchase > 0 && $subtotal < $minPurchase) {
    logVoucherActivity('validate_failed', $voucher['id'], $code, 'Min purchase not met: ' . $subtotal . ' < ' . $minPurchase, $userId, $tenantId, $branchId);
    
    sendJsonResponse([
        'valid' => false,
        'message' => 'Minimum purchase of ' . number_format($minPurchase, 2) . ' required',
        'min_purchase' => $minPurchase,
        'subtotal' => $subtotal,
        'code' => $code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// === 12. Check Usage Limit ===
$usageLimit = isset($voucher['usage_limit']) ? (int) $voucher['usage_limit'] : null;
$usedCount = (int) ($voucher['used_count'] ?? 0);

if ($usageLimit !== null && $usedCount >= $usageLimit) {
    logVoucherActivity('validate_failed', $voucher['id'], $code, 'Usage limit reached: ' . $usedCount . '/' . $usageLimit, $userId, $tenantId, $branchId);
    
    sendJsonResponse([
        'valid' => false,
        'message' => 'Voucher usage limit has been reached',
        'used_count' => $usedCount,
        'usage_limit' => $usageLimit,
        'code' => $code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// === 13. Check Max Per Customer ===
$maxPerCustomer = isset($voucher['max_per_customer']) ? (int) $voucher['max_per_customer'] : null;

if ($maxPerCustomer !== null && $customerId > 0) {
    $customerUsage = getVoucherUsageCount($pdo, $voucher['id'], $tenantId, $customerId);
    
    if ($customerUsage >= $maxPerCustomer) {
        logVoucherActivity('validate_failed', $voucher['id'], $code, 'Customer usage limit reached: ' . $customerUsage . '/' . $maxPerCustomer, $userId, $tenantId, $branchId);
        
        sendJsonResponse([
            'valid' => false,
            'message' => 'You have already used this voucher the maximum number of times',
            'customer_used' => $customerUsage,
            'max_per_customer' => $maxPerCustomer,
            'code' => $code,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// === 14. Calculate Discount ===
$voucherValue = (float) $voucher['value'];
$discountAmount = 0;

if ($voucher['type'] === 'fixed') {
    $discountAmount = $voucherValue;
} else {
    // Percentage
    $discountAmount = $subtotal * ($voucherValue / 100);
}

// Apply max discount limit
$maxDiscount = isset($voucher['max_discount']) ? (float) $voucher['max_discount'] : null;
if ($maxDiscount !== null && $discountAmount > $maxDiscount) {
    $discountAmount = $maxDiscount;
}

// Ensure discount doesn't exceed subtotal
$discountAmount = min($discountAmount, $subtotal);

// === 15. Log Successful Validation ===
logVoucherActivity('validate_success', $voucher['id'], $code, 'Valid voucher: ' . $voucherValue . ' ' . $voucher['type'] . ' discount', $userId, $tenantId, $branchId);

// === 16. Build Response ===
$response = [
    'valid' => true,
    'success' => true,
    'message' => 'Voucher applied successfully',
    'code' => $code,
    'voucher' => [
        'id' => (int) $voucher['id'],
        'code' => $voucher['code'],
        'type' => $voucher['type'],
        'value' => $voucherValue,
        'discount_amount' => $discountAmount,
        'min_purchase' => $minPurchase,
        'max_discount' => $maxDiscount,
        'description' => $voucher['description'] ?? '',
        'usage_limit' => $usageLimit,
        'used_count' => $usedCount,
        'remaining_uses' => $usageLimit !== null ? max(0, $usageLimit - $usedCount) : null,
        'expires_at' => $voucher['expires_at']
    ],
    'timestamp' => date('Y-m-d H:i:s')
];

// Add detailed info if requested
if ($includeDetails) {
    $response['details'] = [
        'subtotal' => $subtotal,
        'discount_percent' => $voucher['type'] === 'percent' ? $voucherValue : null,
        'discount_fixed' => $voucher['type'] === 'fixed' ? $voucherValue : null,
        'remaining_uses' => $response['voucher']['remaining_uses'],
        'customer_eligible' => $customerId > 0 ? 'Yes' : 'N/A'
    ];
}

sendJsonResponse($response);

} catch (PDOException $e) {
    error_log("Database error in validate_voucher.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    error_log("Error in validate_voucher.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    error_log("Fatal error in validate_voucher.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}
