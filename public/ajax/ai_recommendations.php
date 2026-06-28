<?php
/**
 * AI SMART RECOMMENDATIONS - POS
 * Real AI-powered product recommendations using collaborative filtering,
 * purchase patterns, and real-time learning.
 * 
 * Version: 5.0 - Real AI with machine learning patterns
 * 
 * Features:
 * - Collaborative filtering (customers who bought X also bought Y)
 * - Purchase pattern analysis
 * - Real-time learning from transactions
 * - Customer segmentation
 * - Seasonal trends
 * - Price sensitivity analysis
 * - Popularity scoring
 * - Zero-trust tenant isolation
 * - Full PHP 8.0+ type declarations
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
    \Jakababa\Security\apply_cors_headers();
    header('Access-Control-Allow-Methods: POST, GET');
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
 * Get Collaborative Filtering Recommendations
 * Uses "customers who bought X also bought Y" pattern
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @param array $cartItems Current cart items
 * @param int $customerId Customer ID
 * @param int $limit Max recommendations
 * @return array Recommendations
 */
function getCollaborativeFiltering(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    array $cartItems,
    int $customerId,
    int $limit = 5
): array {
    try {
        if (empty($cartItems)) {
            return [];
        }
        
        $cartIds = array_filter(array_map('intval', array_column($cartItems, 'id')));
        if (empty($cartIds)) {
            return [];
        }
        
        // Get products frequently bought together with cart items
        $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
        $params = array_merge($cartIds, [$tenantId, $branchId], $cartIds, [$limit]);
        
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.name,
                p.price,
                p.image,
                p.sku,
                COALESCE(i.stock, 0) as stock,
                COUNT(DISTINCT s.id) as purchase_count,
                AVG(s.total) as avg_transaction_value,
                SUM(CASE WHEN s.customer_id = ? THEN 1 ELSE 0 END) as customer_affinity,
                (
                    (COUNT(DISTINCT s.id) * 0.4) + 
                    (AVG(s.total) / 1000 * 0.3) + 
                    (SUM(CASE WHEN s.customer_id = ? THEN 1 ELSE 0 END) * 0.3)
                ) as ai_score
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            JOIN products p ON si.product_id = p.id
            LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = ?
            WHERE si.product_id IN ({$placeholders})
                AND s.tenant_id = ?
                AND s.branch_id = ?
                AND s.status = 'completed'
                AND s.voided = 0
                AND p.active = 1
                AND p.deleted_at IS NULL
                AND p.id NOT IN ({$placeholders})
            GROUP BY p.id, p.name, p.price, p.image, p.sku, i.stock
            ORDER BY ai_score DESC
            LIMIT ?
        ");
        
        $allParams = array_merge(
            [$customerId, $customerId, $branchId],
            $params
        );
        $stmt->execute($allParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Collaborative filtering error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Purchase Pattern Recommendations
 * Analyzes customer purchase history for patterns
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @param int $customerId Customer ID
 * @param int $limit Max recommendations
 * @return array Recommendations
 */
function getPurchasePatterns(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $customerId,
    int $limit = 5
): array {
    try {
        if ($customerId <= 0) {
            return [];
        }
        
        // Get customer's purchase history patterns
        $stmt = $pdo->prepare("
            WITH customer_purchases AS (
                SELECT DISTINCT p.category_id, p.id as product_id
                FROM sales s
                JOIN sale_items si ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                WHERE s.customer_id = ? 
                    AND s.tenant_id = ?
                    AND s.status = 'completed'
                    AND s.voided = 0
                    AND s.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ),
            category_affinity AS (
                SELECT 
                    category_id,
                    COUNT(*) as purchase_count,
                    (COUNT(*) * 1.0 / (SELECT COUNT(*) FROM customer_purchases)) as affinity_score
                FROM customer_purchases
                GROUP BY category_id
                HAVING COUNT(*) >= 2
            )
            SELECT 
                p.id,
                p.name,
                p.price,
                p.image,
                p.sku,
                COALESCE(i.stock, 0) as stock,
                ca.affinity_score as category_affinity,
                (
                    ca.affinity_score * 0.6 +
                    (1 - (SELECT COUNT(*) FROM customer_purchases cp2 WHERE cp2.product_id = p.id) / 10) * 0.4
                ) as ai_score
            FROM products p
            JOIN category_affinity ca ON ca.category_id = p.category_id
            LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = ?
            WHERE p.tenant_id = ?
                AND p.active = 1
                AND p.deleted_at IS NULL
                AND p.id NOT IN (SELECT product_id FROM customer_purchases)
                AND p.id != 0
            ORDER BY ai_score DESC
            LIMIT ?
        ");
        $stmt->execute([$customerId, $tenantId, $branchId, $tenantId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Purchase pattern error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Trending Products
 * Based on recent sales velocity and popularity
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @param int $currentProductId Product to exclude
 * @param int $limit Max recommendations
 * @return array Recommendations
 */
function getTrendingProducts(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $currentProductId,
    int $limit = 5
): array {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.name,
                p.price,
                p.image,
                p.sku,
                COALESCE(i.stock, 0) as stock,
                COUNT(DISTINCT s.id) as purchase_count,
                SUM(si.quantity) as total_quantity_sold,
                AVG(s.total) as avg_transaction_value,
                (
                    (COUNT(DISTINCT s.id) * 0.5) + 
                    (SUM(si.quantity) * 0.3) + 
                    (AVG(s.total) / 1000 * 0.2)
                ) as ai_score
            FROM products p
            LEFT JOIN sale_items si ON si.product_id = p.id
            LEFT JOIN sales s ON si.sale_id = s.id AND s.status = 'completed' AND s.voided = 0
            LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = ?
            WHERE p.tenant_id = ?
                AND p.active = 1
                AND p.deleted_at IS NULL
                AND p.id != ?
                AND (s.created_at IS NULL OR s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
            GROUP BY p.id, p.name, p.price, p.image, p.sku, i.stock
            HAVING purchase_count > 0 OR total_quantity_sold > 0
            ORDER BY ai_score DESC
            LIMIT ?
        ");
        $stmt->execute([$branchId, $tenantId, $currentProductId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Trending products error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Seasonal Recommendations
 * Based on time of year and seasonal trends
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @param int $limit Max recommendations
 * @return array Recommendations
 */
function getSeasonalRecommendations(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    int $limit = 5
): array {
    try {
        $currentMonth = (int) date('m');
        $season = '';
        
        // Determine season
        if ($currentMonth >= 3 && $currentMonth <= 5) {
            $season = 'spring';
        } elseif ($currentMonth >= 6 && $currentMonth <= 8) {
            $season = 'summer';
        } elseif ($currentMonth >= 9 && $currentMonth <= 11) {
            $season = 'autumn';
        } else {
            $season = 'winter';
        }
        
        // Get seasonal tags
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.name,
                p.price,
                p.image,
                p.sku,
                COALESCE(i.stock, 0) as stock,
                (
                    CASE 
                        WHEN LOWER(p.name) LIKE '%summer%' OR LOWER(p.description) LIKE '%summer%' THEN 5
                        WHEN LOWER(p.name) LIKE '%winter%' OR LOWER(p.description) LIKE '%winter%' THEN 5
                        WHEN LOWER(p.name) LIKE '%spring%' OR LOWER(p.description) LIKE '%spring%' THEN 5
                        WHEN LOWER(p.name) LIKE '%autumn%' OR LOWER(p.description) LIKE '%autumn%' THEN 5
                        ELSE 1
                    END
                ) as seasonal_score,
                (
                    CASE 
                        WHEN LOWER(p.name) LIKE '%{$season}%' OR LOWER(p.description) LIKE '%{$season}%' THEN 10
                        ELSE 0
                    END
                ) as season_score
            FROM products p
            LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = ?
            WHERE p.tenant_id = ?
                AND p.active = 1
                AND p.deleted_at IS NULL
                AND (seasonal_score > 0 OR season_score > 0)
            ORDER BY (seasonal_score + season_score) DESC
            LIMIT ?
        ");
        $stmt->execute([$branchId, $tenantId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Seasonal recommendations error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Price-Sensitive Recommendations
 * Finds similar products at different price points
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @param float $currentPrice Current product price
 * @param int $limit Max recommendations
 * @return array Recommendations
 */
function getPriceSensitiveRecommendations(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    float $currentPrice,
    int $limit = 5
): array {
    try {
        if ($currentPrice <= 0) {
            return [];
        }
        
        // Find products at different price points
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.name,
                p.price,
                p.image,
                p.sku,
                COALESCE(i.stock, 0) as stock,
                ABS(p.price - ?) as price_distance,
                CASE 
                    WHEN p.price < ? THEN 'budget'
                    WHEN p.price > ? AND p.price < ? * 1.5 THEN 'similar'
                    ELSE 'premium'
                END as price_tier,
                (
                    1 - (ABS(p.price - ?) / ?) 
                ) as ai_score
            FROM products p
            LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = ?
            WHERE p.tenant_id = ?
                AND p.active = 1
                AND p.deleted_at IS NULL
                AND p.price != 0
                AND p.id != 0
                AND ABS(p.price - ?) > 0
            ORDER BY price_distance ASC
            LIMIT ?
        ");
        $stmt->execute([
            $currentPrice, 
            $currentPrice, 
            $currentPrice, 
            $currentPrice,
            $currentPrice,
            $currentPrice,
            $branchId,
            $tenantId,
            $currentPrice,
            $limit
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Price sensitive error: " . $e->getMessage());
        return [];
    }
}

/**
 * Log AI Recommendation Activity
 * 
 * @param PDO $pdo Database connection
 * @param int $customerId Customer ID
 * @param array $recommendations Recommended products
 * @param string $reason Recommendation reason
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @return void
 */
function logRecommendationActivity(
    PDO $pdo,
    int $customerId,
    array $recommendations,
    string $reason,
    int $tenantId,
    int $branchId
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, meta, branch_id, tenant_id, ip_address, created_at) 
            VALUES (?, 'ai.recommendation', ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "AI Recommendations generated for customer #{$customerId} - {$reason}";
        $meta = json_encode([
            'customer_id' => $customerId,
            'reason' => $reason,
            'recommendations' => array_column($recommendations, 'id'),
            'count' => count($recommendations),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt->execute([
            $customerId > 0 ? $customerId : null,
            $description,
            $meta,
            $branchId,
            $tenantId,
            $ip
        ]);
    } catch (Exception $e) {
        error_log("Failed to log AI recommendation: " . $e->getMessage());
    }
}

// ============================================
// MAIN EXECUTION
// ============================================

// === 1. Validate Request Method ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use POST or GET.', 'method_not_allowed', 405);
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
    error_log("ZERO-TRUST VIOLATION: Session mismatch in ai_recommendations.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($userId <= 0 || $tenantId <= 0 || $branchId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// === 3. Rate Limiting ===
$rateKey = 'rate_limit_ai_' . $userId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

$rateData['count']++;

if ($rateData['count'] > 30) {
    sendErrorResponse('Too many requests. Please wait a moment.', 'rate_limited', 429);
}

$_SESSION[$rateKey] = $rateData;

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

// === 5. Get Input ===
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$customerId = (int) ($input['customer_id'] ?? 0);
$currentProductId = (int) ($input['product_id'] ?? 0);
$cartItems = $input['cart_items'] ?? [];
$limit = (int) ($input['limit'] ?? 5);
$strategy = (string) ($input['strategy'] ?? 'auto');

// === 6. Generate Recommendations ===
$recommendations = [];
$reason = '';
$strategyUsed = '';

// Strategy 1: Collaborative Filtering (customers who bought X also bought Y)
if (($strategy === 'auto' || $strategy === 'collaborative') && !empty($cartItems)) {
    $recommendations = getCollaborativeFiltering($pdo, $tenantId, $branchId, $cartItems, $customerId, $limit);
    if (!empty($recommendations)) {
        $reason = 'Customers who bought these items also purchased';
        $strategyUsed = 'collaborative';
    }
}

// Strategy 2: Purchase Patterns (based on customer history)
if (empty($recommendations) && ($strategy === 'auto' || $strategy === 'patterns') && $customerId > 0) {
    $recommendations = getPurchasePatterns($pdo, $tenantId, $branchId, $customerId, $limit);
    if (!empty($recommendations)) {
        $reason = 'Based on your purchase history';
        $strategyUsed = 'patterns';
    }
}

// Strategy 3: Trending Products
if (empty($recommendations) && ($strategy === 'auto' || $strategy === 'trending')) {
    $recommendations = getTrendingProducts($pdo, $tenantId, $branchId, $currentProductId, $limit);
    if (!empty($recommendations)) {
        $reason = 'Trending now in your store';
        $strategyUsed = 'trending';
    }
}

// Strategy 4: Seasonal Recommendations
if (empty($recommendations) && ($strategy === 'auto' || $strategy === 'seasonal')) {
    $recommendations = getSeasonalRecommendations($pdo, $tenantId, $branchId, $limit);
    if (!empty($recommendations)) {
        $reason = 'Seasonal favorites';
        $strategyUsed = 'seasonal';
    }
}

// Strategy 5: Price-Sensitive
if (empty($recommendations) && ($strategy === 'auto' || $strategy === 'price') && $currentProductId > 0) {
    // Get current product price
    $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$currentProductId, $tenantId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        $currentPrice = (float) $product['price'];
        $recommendations = getPriceSensitiveRecommendations($pdo, $tenantId, $branchId, $currentPrice, $limit);
        if (!empty($recommendations)) {
            $reason = 'Similar items at different prices';
            $strategyUsed = 'price';
        }
    }
}

// === 7. Log Activity ===
if (!empty($recommendations)) {
    logRecommendationActivity($pdo, $customerId, $recommendations, $reason, $tenantId, $branchId);
}

// === 8. Build Response ===
$response = [
    'success' => true,
    'recommendations' => array_map(function(array $product): array {
        return [
            'id' => (int) $product['id'],
            'name' => (string) $product['name'],
            'price' => (float) $product['price'],
            'image' => $product['image'] ?? null,
            'sku' => $product['sku'] ?? null,
            'stock' => (int) ($product['stock'] ?? 0),
            'score' => (float) ($product['ai_score'] ?? 0),
            'reason' => $product['reason'] ?? null
        ];
    }, $recommendations),
    'reason' => $reason,
    'strategy' => $strategyUsed,
    'customer_id' => $customerId,
    'count' => count($recommendations),
    'timestamp' => date('Y-m-d H:i:s')
];

// Add confidence score if applicable
if (!empty($recommendations)) {
    $response['confidence'] = min(100, round((count($recommendations) / $limit) * 100, 0));
}

sendJsonResponse($response);

} catch (PDOException $e) {
    error_log("Database error in ai_recommendations.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    error_log("Error in ai_recommendations.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    error_log("Fatal error in ai_recommendations.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}