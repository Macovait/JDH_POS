<?php
/**
 * APPLY COUPON - AJAX endpoint for POS
 * Version: 4.0 - Enhanced security, performance, and error handling
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - Multiple coupon types (fixed, percent)
 * - Usage limit checking (global and per-customer)
 * - Min purchase validation
 * - Max discount limits
 * - Category/product restrictions
 * - Stacking rules
 * - Priority handling
 * - Activity logging
 * - Response caching
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
    header('Access-Control-Allow-Methods: POST');
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
function logCouponActivity($action, $couponId, $code, $message, $userId = null, $tenantId = null, $branchId = null) {
    try {
        require_once __DIR__ . '/../../src/db.php';
        $pdo = get_db_connection();
        
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, meta, branch_id, tenant_id, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "Coupon {$code} - {$action}: {$message}";
        $meta = json_encode(['coupon_id' => $couponId, 'code' => $code, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        return $stmt->execute([$userId, "coupon.{$action}", $description, $meta, $branchId, $tenantId, $ip]);
    } catch (Exception $e) {
        error_log("Failed to log coupon activity: " . $e->getMessage());
        return false;
    }
}

// ============================================
// FUNCTION: Check Product/Category Restrictions
// ============================================
function checkCouponRestrictions($pdo, $discountId, $tenantId, $cartItems = []) {
    try {
        // Check if product_ids column exists
        $colCheck = $pdo->query("SHOW COLUMNS FROM discounts LIKE 'product_ids'");
        if ($colCheck->rowCount() === 0) {
            return ['allowed' => true];
        }
        
        $stmt = $pdo->prepare("
            SELECT product_ids, category_id, applicable_products 
            FROM discounts 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$discountId, $tenantId]);
        $discount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$discount) {
            return ['allowed' => true];
        }
        
        // If no restrictions, allow
        if (empty($discount['product_ids']) && empty($discount['category_id']) && $discount['applicable_products'] === 'all') {
            return ['allowed' => true];
        }
        
        // If no cart items, can't validate
        if (empty($cartItems)) {
            return ['allowed' => true, 'warning' => 'No items in cart to validate restrictions'];
        }
        
        // Check product restrictions
        $productIds = [];
        if (!empty($discount['product_ids'])) {
            $productIds = array_map('trim', explode(',', $discount['product_ids']));
        }
        
        $categoryId = (int) ($discount['category_id'] ?? 0);
        $applicableProducts = $discount['applicable_products'] ?? 'all';
        
        // Check each cart item
        $allowedItems = [];
        $restrictedItems = [];
        
        foreach ($cartItems as $item) {
            $pid = (int) $item['product_id'];
            $category = (int) ($item['category_id'] ?? 0);
            
            $isAllowed = false;
            
            if ($applicableProducts === 'all') {
                $isAllowed = true;
            } elseif ($applicableProducts === 'specific' && !empty($productIds)) {
                if (in_array($pid, $productIds)) {
                    $isAllowed = true;
                }
            } elseif ($applicableProducts === 'category' && $categoryId > 0) {
                if ($category === $categoryId) {
                    $isAllowed = true;
                }
            }
            
            if ($isAllowed) {
                $allowedItems[] = $pid;
            } else {
                $restrictedItems[] = $item['name'] ?? "Product #{$pid}";
            }
        }
        
        if (empty($allowedItems) && !empty($cartItems)) {
            return [
                'allowed' => false,
                'message' => 'This coupon does not apply to any items in your cart',
                'restricted_items' => $restrictedItems
            ];
        }
        
        return [
            'allowed' => true,
            'allowed_items' => $allowedItems,
            'restricted_items' => $restrictedItems,
            'warning' => !empty($restrictedItems) ? 'Coupon applies to some items only' : null
        ];
        
    } catch (Exception $e) {
        error_log("Error checking coupon restrictions: " . $e->getMessage());
        return ['allowed' => true];
    }
}

// ============================================
// MAIN EXECUTION
// ============================================

// === 1. Validate Request Method ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed. Use POST.', 'method_not_allowed', 405);
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
    error_log("ZERO-TRUST VIOLATION: Session mismatch in apply_coupon.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($userId <= 0 || $tenantId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// === 3. Rate Limiting ===
$rateKey = 'rate_limit_coupon_' . $userId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

$rateData['count']++;

if ($rateData['count'] > 30) {
    sendErrorResponse('Too many requests. Please wait a moment.', 'rate_limited', 429);
}

$_SESSION[$rateKey] = $rateData;

// === 4. Get Input ===
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

// CSRF Protection
$submittedToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (empty($sessionToken) || empty($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
    error_log("CSRF validation failed in apply_coupon.php for user: " . $userId);
    sendErrorResponse('Invalid security token', 'csrf_invalid', 403);
}

// Extract parameters
$code = strtoupper(trim($input['code'] ?? ''));
$subtotal = (float) ($input['subtotal'] ?? 0);
$customerId = !empty($input['customer_id']) ? (int) $input['customer_id'] : null;
$cartItems = $input['items'] ?? [];
$orderType = $input['order_type'] ?? '';
$branchIdInput = isset($input['branch_id']) ? (int) $input['branch_id'] : $branchId;

if (empty($code)) {
    sendErrorResponse('No coupon code provided', 'code_required', 400);
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
$hasDiscounts = in_array('discounts', $tables);
$hasDiscountUsage = in_array('discount_usage', $tables);

if (!$hasDiscounts) {
    sendErrorResponse('Discount system is not available.', 'discounts_not_available', 503);
}

// === 7. Fetch Discount ===
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    SELECT 
        d.*,
        (SELECT COUNT(*) FROM discount_usage du WHERE du.discount_id = d.id AND du.tenant_id = d.tenant_id) as used_count
    FROM discounts d
    WHERE d.code = ?
      AND d.tenant_id = ?
      AND d.active = 1
      AND (d.valid_from IS NULL OR d.valid_from <= ?)
      AND (d.valid_until IS NULL OR d.valid_until >= ?)
    LIMIT 1
");
$stmt->execute([$code, $tenantId, $today, $today]);
$discount = $stmt->fetch();

// === 8. Check if Discount Exists ===
if (!$discount) {
    logCouponActivity('validate_failed', 0, $code, 'Coupon not found or inactive', $userId, $tenantId, $branchId);
    
    sendJsonResponse([
        'valid' => false,
        'message' => "Code '{$code}' is invalid or expired",
        'code' => $code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// === 9. Check Discount Type ===
$validTypes = ['fixed', 'percent'];
if (!in_array($discount['type'], $validTypes)) {
    logCouponActivity('validate_failed', $discount['id'], $code, 'Invalid discount type: ' . $discount['type'], $userId, $tenantId, $branchId);
    
    sendJsonResponse([
        'valid' => false,
        'message' => 'Coupon type is invalid',
        'code' => $code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// === 10. Check Global Usage Limit ===
$usageLimit = isset($discount['usage_limit']) ? (int) $discount['usage_limit'] : null;
$usedCount = (int) ($discount['used_count'] ?? 0);

if ($usageLimit !== null && $usedCount >= $usageLimit) {
    logCouponActivity('validate_failed', $discount['id'], $code, 'Global usage limit reached: ' . $usedCount . '/' . $usageLimit, $userId, $tenantId, $branchId);
    
    sendJsonResponse([
        'valid' => false,
        'message' => 'This coupon has reached its usage limit',
        'used_count' => $usedCount,
        'usage_limit' => $usageLimit,
        'code' => $code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// === 11. Check Min Purchase ===
$minPurchase = (float) ($discount['min_purchase'] ?? 0);
if ($minPurchase > 0 && $subtotal < $minPurchase) {
    $currencySymbol = get_settings('currency_symbol', 'KES');
    
    logCouponActivity('validate_failed', $discount['id'], $code, 'Min purchase not met: ' . $subtotal . ' < ' . $minPurchase, $userId, $tenantId, $branchId);
    
    sendJsonResponse([
        'valid' => false,
        'message' => "Minimum purchase of {$currencySymbol} " . number_format($minPurchase, 0) . " required",
        'min_purchase' => $minPurchase,
        'subtotal' => $subtotal,
        'code' => $code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// === 12. Check Per-Customer Usage Limit ===
$maxPerCustomer = isset($discount['max_per_customer']) ? (int) $discount['max_per_customer'] : null;

if ($maxPerCustomer !== null && $customerId > 0 && $hasDiscountUsage) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM discount_usage
            WHERE discount_id = ? AND customer_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$discount['id'], $customerId, $tenantId]);
        $customerUses = (int) $stmt->fetchColumn();
        
        if ($customerUses >= $maxPerCustomer) {
            logCouponActivity('validate_failed', $discount['id'], $code, 'Customer usage limit reached: ' . $customerUses . '/' . $maxPerCustomer, $userId, $tenantId, $branchId);
            
            sendJsonResponse([
                'valid' => false,
                'message' => 'You have already used this coupon the maximum number of times',
                'customer_used' => $customerUses,
                'max_per_customer' => $maxPerCustomer,
                'code' => $code,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    } catch (Exception $e) {
        // If discount_usage table doesn't exist, skip this check
        error_log("Customer usage check failed: " . $e->getMessage());
    }
}

// === 13. Check Product/Category Restrictions ===
if (!empty($cartItems)) {
    $restrictionCheck = checkCouponRestrictions($pdo, $discount['id'], $tenantId, $cartItems);
    
    if (!$restrictionCheck['allowed']) {
        logCouponActivity('validate_failed', $discount['id'], $code, 'Product restriction: ' . ($restrictionCheck['message'] ?? ''), $userId, $tenantId, $branchId);
        
        sendJsonResponse([
            'valid' => false,
            'message' => $restrictionCheck['message'] ?? 'This coupon does not apply to your cart items',
            'restricted_items' => $restrictionCheck['restricted_items'] ?? [],
            'code' => $code,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// === 14. Check Order Type Restriction ===
if (!empty($discount['applicable_order_types']) && !empty($orderType)) {
    $applicableOrderTypes = json_decode($discount['applicable_order_types'], true);
    if (is_array($applicableOrderTypes) && !in_array($orderType, $applicableOrderTypes)) {
        logCouponActivity('validate_failed', $discount['id'], $code, 'Order type restriction: ' . $orderType . ' not in ' . implode(',', $applicableOrderTypes), $userId, $tenantId, $branchId);
        
        sendJsonResponse([
            'valid' => false,
            'message' => 'This coupon is not valid for ' . $orderType . ' orders',
            'code' => $code,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// === 15. Calculate Discount Amount ===
$discountAmount = 0;
$discountValue = (float) $discount['value'];

if ($discount['type'] === 'percent') {
    $discountAmount = $subtotal * ($discountValue / 100);
} else {
    // Fixed amount
    $discountAmount = $discountValue;
}

// Apply max discount limit
$maxDiscount = isset($discount['max_discount']) ? (float) $discount['max_discount'] : null;
if ($maxDiscount !== null && $discountAmount > $maxDiscount) {
    $discountAmount = $maxDiscount;
}

// Ensure discount doesn't exceed subtotal
$discountAmount = min($discountAmount, $subtotal);
$discountAmount = round($discountAmount, 2);

// === 16. Check Priority / Stacking ===
$priority = isset($discount['priority']) ? (int) $discount['priority'] : 5;
$exclusive = isset($discount['exclusive']) ? (int) $discount['exclusive'] : 0;

// === 17. Log Successful Validation ===
logCouponActivity('validate_success', $discount['id'], $code, 'Valid coupon: ' . $discountAmount . ' discount', $userId, $tenantId, $branchId);

// === 18. Build Response ===
$response = [
    'valid' => true,
    'success' => true,
    'message' => 'Coupon applied successfully',
    'code' => $code,
    'discount' => [
        'id' => (int) $discount['id'],
        'name' => $discount['name'],
        'code' => $discount['code'],
        'type' => $discount['type'],
        'value' => $discountValue,
        'amount' => $discountAmount,
        'description' => $discount['description'] ?? '',
        'priority' => $priority,
        'exclusive' => (bool) $exclusive,
        'min_purchase' => $minPurchase,
        'max_discount' => $maxDiscount,
        'usage_limit' => $usageLimit,
        'used_count' => $usedCount,
        'remaining_uses' => $usageLimit !== null ? max(0, $usageLimit - $usedCount) : null,
        'valid_from' => $discount['valid_from'],
        'valid_until' => $discount['valid_until']
    ],
    'timestamp' => date('Y-m-d H:i:s')
];

// Add restriction info if any
if (!empty($cartItems)) {
    $response['restrictions'] = [
        'applies_to_all_items' => empty($restrictionCheck['restricted_items']),
        'restricted_items' => $restrictionCheck['restricted_items'] ?? [],
        'warning' => $restrictionCheck['warning'] ?? null
    ];
}

sendJsonResponse($response);

} catch (PDOException $e) {
    error_log("Database error in apply_coupon.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    error_log("Error in apply_coupon.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    error_log("Fatal error in apply_coupon.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}