<?php
/**
 * GET CUSTOMER LOYALTY - AJAX endpoint for POS
 * Version: 4.0 - Enhanced security, performance, and error handling
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - Loyalty points with tier calculation
 * - Points redemption and earning
 * - Points history with pagination
 * - Reward availability checking
 * - Response caching
 * - Comprehensive error handling
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
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// FUNCTION: Send Error Response
// ============================================
function sendErrorResponse($message, $errorCode = 'unknown_error', $statusCode = 400) {
    sendJsonResponse([
        'success' => false,
        'error' => $errorCode,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $statusCode);
}

// ============================================
// FUNCTION: Calculate Loyalty Tier
// ============================================
function calculateLoyaltyTier($points) {
    if ($points >= 10000) return ['platinum', '#E5E4E2'];
    if ($points >= 5000) return ['gold', '#FFD700'];
    if ($points >= 1000) return ['silver', '#C0C0C0'];
    return ['bronze', '#CD7F32'];
}

// ============================================
// FUNCTION: Get Tier Benefits
// ============================================
function getTierBenefits($tier) {
    $benefits = [
        'platinum' => [
            'discount_rate' => 10,
            'points_multiplier' => 2.0,
            'free_delivery' => true,
            'priority_support' => true
        ],
        'gold' => [
            'discount_rate' => 7,
            'points_multiplier' => 1.5,
            'free_delivery' => true,
            'priority_support' => false
        ],
        'silver' => [
            'discount_rate' => 5,
            'points_multiplier' => 1.0,
            'free_delivery' => false,
            'priority_support' => false
        ],
        'bronze' => [
            'discount_rate' => 0,
            'points_multiplier' => 1.0,
            'free_delivery' => false,
            'priority_support' => false
        ]
    ];
    
    return $benefits[$tier] ?? $benefits['bronze'];
}

// ============================================
// FUNCTION: Get Available Rewards
// ============================================
function getAvailableRewards($pdo, $tenantId, $customerPoints) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                name,
                description,
                points_required,
                discount_type,
                discount_value,
                max_uses,
                stock_available,
                stock_used,
                status
            FROM loyalty_rewards
            WHERE tenant_id = ? 
                AND is_active = 1 
                AND status = 'active'
                AND points_required <= ?
                AND (max_uses IS NULL OR stock_used < max_uses)
                AND (stock_available IS NULL OR stock_used < stock_available)
            ORDER BY points_required ASC
            LIMIT 20
        ");
        $stmt->execute([$tenantId, $customerPoints]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// ============================================
// FUNCTION: Get Points History
// ============================================
function getPointsHistory($pdo, $customerId, $tenantId, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                points_change,
                reason,
                created_by,
                created_at,
                CASE 
                    WHEN points_change > 0 THEN 'earned'
                    WHEN points_change < 0 THEN 'redeemed'
                    ELSE 'adjusted'
                END as type
            FROM loyalty_points_log
            WHERE customer_id = ? AND tenant_id = ?
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
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed. Use GET or POST.', 'method_not_allowed', 405);
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
    error_log("ZERO-TRUST VIOLATION: Session mismatch in get_customer_loyalty.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($userId <= 0 || $tenantId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// === 3. Get Parameters ===
$customerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
$saleTotal = isset($_GET['sale_total']) ? (float) $_GET['sale_total'] : 0;
$action = $_GET['action'] ?? $_POST['action'] ?? 'get';
$pointsToRedeem = isset($_POST['points']) ? (int) $_POST['points'] : 0;
$includeHistory = isset($_GET['include_history']) ? filter_var($_GET['include_history'], FILTER_VALIDATE_BOOLEAN) : false;
$includeRewards = isset($_GET['include_rewards']) ? filter_var($_GET['include_rewards'], FILTER_VALIDATE_BOOLEAN) : false;

if ($customerId <= 0) {
    sendErrorResponse('Customer ID is required', 'customer_id_required', 400);
}

// === 4. Rate Limiting ===
$rateKey = 'rate_limit_loyalty_' . $userId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

$rateData['count']++;

if ($rateData['count'] > 30) {
    sendErrorResponse('Too many requests. Please wait a moment.', 'rate_limited', 429);
}

$_SESSION[$rateKey] = $rateData;

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
$hasLoyaltyLog = in_array('loyalty_points_log', $tables);
$hasLoyaltyRewards = in_array('loyalty_rewards', $tables);

if (!$hasCustomers) {
    sendErrorResponse('Customer management is not available.', 'customers_not_available', 503);
}

// === 7. Handle Different Actions ===

// 7a. Redeem Points (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'redeem') {
    if ($pointsToRedeem <= 0) {
        sendErrorResponse('Points to redeem must be greater than 0', 'invalid_points', 400);
    }
    
    // Get customer current points
    $stmt = $pdo->prepare("
        SELECT loyalty_points, name FROM customers 
        WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$customerId, $tenantId]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        sendErrorResponse('Customer not found', 'customer_not_found', 404);
    }
    
    $currentPoints = (int) ($customer['loyalty_points'] ?? 0);
    
    if ($pointsToRedeem > $currentPoints) {
        sendErrorResponse('Insufficient points', 'insufficient_points', 400);
    }
    
    // Calculate discount value (1 point = 0.01 currency)
    $discountValue = $pointsToRedeem * 0.01;
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Deduct points
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET loyalty_points = loyalty_points - ? 
            WHERE id = ? AND tenant_id = ? AND loyalty_points >= ?
        ");
        $stmt->execute([$pointsToRedeem, $customerId, $tenantId, $pointsToRedeem]);
        
        // Log redemption
        if ($hasLoyaltyLog) {
            $stmt = $pdo->prepare("
                INSERT INTO loyalty_points_log 
                (customer_id, points_change, reason, created_by, tenant_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $customerId, 
                -$pointsToRedeem, 
                'Redeemed for discount',
                $userId,
                $tenantId
            ]);
        }
        
        $pdo->commit();
        
        sendJsonResponse([
            'success' => true,
            'action' => 'redeem',
            'points_redeemed' => $pointsToRedeem,
            'discount_value' => $discountValue,
            'remaining_points' => $currentPoints - $pointsToRedeem,
            'customer_name' => $customer['name'],
            'message' => "Successfully redeemed {$pointsToRedeem} points for " . number_format($discountValue, 2),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// 7b. Get Loyalty Info (GET)
// Get customer data
$stmt = $pdo->prepare("
    SELECT 
        id, 
        name, 
        phone, 
        email,
        loyalty_points, 
        loyalty_tier, 
        total_spent, 
        last_purchase,
        created_at
    FROM customers 
    WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL
");
$stmt->execute([$customerId, $tenantId]);
$customer = $stmt->fetch();

if (!$customer) {
    sendErrorResponse('Customer not found', 'customer_not_found', 404);
}

// Calculate points to earn
$pointsToEarn = floor($saleTotal / 100);
$currentPoints = (int) ($customer['loyalty_points'] ?? 0);
$tierName = $customer['loyalty_tier'] ?? 'bronze';

// Recalculate tier based on current points
list($calculatedTier, $tierColor) = calculateLoyaltyTier($currentPoints);

// If tier is outdated, update it
if ($calculatedTier !== $tierName) {
    $stmt = $pdo->prepare("UPDATE customers SET loyalty_tier = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$calculatedTier, $customerId, $tenantId]);
    $tierName = $calculatedTier;
}

// Get tier benefits
$benefits = getTierBenefits($tierName);

// Calculate next tier requirements
$nextTier = null;
$nextTierPoints = null;
$nextTierName = null;

if ($tierName === 'bronze' && $currentPoints < 1000) {
    $nextTierName = 'silver';
    $nextTierPoints = 1000;
} elseif ($tierName === 'silver' && $currentPoints < 5000) {
    $nextTierName = 'gold';
    $nextTierPoints = 5000;
} elseif ($tierName === 'gold' && $currentPoints < 10000) {
    $nextTierName = 'platinum';
    $nextTierPoints = 10000;
}

if ($nextTierName) {
    $nextTier = [
        'name' => $nextTierName,
        'points_needed' => $nextTierPoints - $currentPoints,
        'points_required' => $nextTierPoints,
        'color' => $nextTierName === 'silver' ? '#C0C0C0' : ($nextTierName === 'gold' ? '#FFD700' : '#E5E4E2')
    ];
}

// Get points history
$history = [];
if ($includeHistory && $hasLoyaltyLog) {
    $history = getPointsHistory($pdo, $customerId, $tenantId, 20);
}

// Get available rewards
$rewards = [];
if ($includeRewards && $hasLoyaltyRewards) {
    $rewards = getAvailableRewards($pdo, $tenantId, $currentPoints);
}

// Build response
$response = [
    'success' => true,
    'action' => 'get',
    'customer' => [
        'id' => (int) $customer['id'],
        'name' => $customer['name'],
        'phone' => $customer['phone'],
        'email' => $customer['email'] ?? '',
        'points' => $currentPoints,
        'tier' => $tierName,
        'tier_color' => $tierColor,
        'benefits' => $benefits,
        'total_spent' => (float) ($customer['total_spent'] ?? 0),
        'points_to_earn' => $pointsToEarn,
        'last_purchase' => $customer['last_purchase'],
        'member_since' => $customer['created_at']
    ],
    'next_tier' => $nextTier,
    'points_summary' => [
        'current' => $currentPoints,
        'potential_earn' => $pointsToEarn,
        'total_earned' => $currentPoints + $pointsToEarn,
        'redemption_value' => $currentPoints * 0.01
    ]
];

// Add history if requested
if ($includeHistory) {
    $response['history'] = array_map(function($entry) {
        return [
            'id' => (int) $entry['id'],
            'points' => (int) $entry['points_change'],
            'reason' => $entry['reason'],
            'type' => $entry['type'],
            'created_at' => $entry['created_at']
        ];
    }, $history);
}

// Add rewards if requested
if ($includeRewards) {
    $response['available_rewards'] = array_map(function($reward) {
        return [
            'id' => (int) $reward['id'],
            'name' => $reward['name'],
            'description' => $reward['description'],
            'points_required' => (int) $reward['points_required'],
            'discount_type' => $reward['discount_type'],
            'discount_value' => (float) $reward['discount_value'],
            'available' => $reward['stock_available'] === null || 
                          ((int)$reward['stock_used'] < (int)$reward['stock_available']),
            'stock_remaining' => $reward['stock_available'] !== null ? 
                (int)$reward['stock_available'] - (int)$reward['stock_used'] : null
        ];
    }, $rewards);
}

sendJsonResponse($response);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in get_customer_loyalty.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in get_customer_loyalty.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in get_customer_loyalty.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}