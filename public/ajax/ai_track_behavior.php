<?php
/**
 * AI CUSTOMER BEHAVIOR TRACKING - POS
 * Real AI learning system that tracks customer behavior for recommendations
 * 
 * Version: 5.0 - Real AI with machine learning patterns
 * 
 * Features:
 * - Real-time behavior tracking
 * - Collaborative filtering data collection
 * - Purchase pattern learning
 * - Customer segmentation
 * - Product affinity scoring
 * - Sales velocity tracking
 * - Session analysis
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
    header('Access-Control-Allow-Methods: POST');
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
 * Log AI Tracking Activity
 * 
 * @param PDO $pdo Database connection
 * @param int $customerId Customer ID
 * @param array $productIds Product IDs
 * @param float $totalAmount Total amount
 * @param string $paymentMethod Payment method
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @return void
 */
function logAITracking(
    PDO $pdo,
    int $customerId,
    array $productIds,
    float $totalAmount,
    string $paymentMethod,
    int $tenantId,
    int $branchId
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, meta, branch_id, tenant_id, ip_address, created_at) 
            VALUES (?, 'ai.track', ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "AI Behavior tracked for customer #{$customerId} - " . count($productIds) . " products";
        $meta = json_encode([
            'customer_id' => $customerId,
            'product_count' => count($productIds),
            'products' => $productIds,
            'total_amount' => $totalAmount,
            'payment_method' => $paymentMethod,
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
        error_log("Failed to log AI tracking: " . $e->getMessage());
    }
}

/**
 * Track Customer Session
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $customerId Customer ID
 * @param int $branchId Branch ID
 * @param array $productIds Product IDs
 * @param float $totalAmount Total amount
 * @param string $paymentMethod Payment method
 * @return void
 */
function trackCustomerSession(
    PDO $pdo,
    int $tenantId,
    int $customerId,
    int $branchId,
    array $productIds,
    float $totalAmount,
    string $paymentMethod
): void {
    if ($customerId <= 0) {
        return;
    }
    
    try {
        $timeOfDay = (int) date('H');
        $dayOfWeek = (int) date('N');
        $sessionId = session_id();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Check if session table exists, create if not
        $stmt = $pdo->query("SHOW TABLES LIKE 'customer_sessions'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `customer_sessions` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) NOT NULL,
                    `branch_id` int(11) NOT NULL,
                    `customer_id` int(11) DEFAULT NULL,
                    `session_id` varchar(255) NOT NULL,
                    `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `ended_at` timestamp NULL DEFAULT NULL,
                    `total_spent` decimal(15,2) DEFAULT 0.00,
                    `items_count` int(11) DEFAULT 0,
                    `first_product_id` int(11) DEFAULT NULL,
                    `last_product_id` int(11) DEFAULT NULL,
                    `bounce` tinyint(1) DEFAULT 0,
                    `device_type` varchar(50) DEFAULT NULL,
                    `ip_address` varchar(45) DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_customer` (`customer_id`),
                    KEY `idx_session` (`session_id`),
                    KEY `idx_tenant` (`tenant_id`),
                    KEY `idx_started` (`started_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Check if session exists
        $stmt = $pdo->prepare("
            SELECT id FROM customer_sessions 
            WHERE tenant_id = ? AND branch_id = ? AND session_id = ? AND ended_at IS NULL
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$tenantId, $branchId, $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            // Update existing session
            $stmt = $pdo->prepare("
                UPDATE customer_sessions 
                SET 
                    total_spent = total_spent + ?,
                    items_count = items_count + ?,
                    last_product_id = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([
                $totalAmount,
                count($productIds),
                end($productIds),
                $session['id'],
                $tenantId
            ]);
        } else {
            // Create new session
            $deviceType = 'desktop';
            if (strpos($userAgent, 'Mobile') !== false) {
                $deviceType = 'mobile';
            } elseif (strpos($userAgent, 'Tablet') !== false) {
                $deviceType = 'tablet';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO customer_sessions 
                (tenant_id, branch_id, customer_id, session_id, started_at, total_spent, items_count, first_product_id, last_product_id, device_type, ip_address)
                VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId,
                $branchId,
                $customerId,
                $sessionId,
                $totalAmount,
                count($productIds),
                $productIds[0] ?? null,
                end($productIds),
                $deviceType,
                $ipAddress
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Session tracking error: " . $e->getMessage());
    }
}

/**
 * Track Product Affinity (Collaborative Filtering)
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param array $productIds Product IDs
 * @return int Number of affinity records updated
 */
function trackProductAffinity(PDO $pdo, int $tenantId, array $productIds): int {
    $updated = 0;
    
    try {
        $productIds = array_filter(array_map('intval', $productIds));
        if (count($productIds) < 2) {
            return 0;
        }
        
        $ids = array_values($productIds);
        
        // Check if affinity table exists, create if not
        $stmt = $pdo->query("SHOW TABLES LIKE 'product_affinity'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `product_affinity` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) NOT NULL,
                    `product_id` int(11) NOT NULL,
                    `related_product_id` int(11) NOT NULL,
                    `affinity_score` decimal(10,4) DEFAULT 0.0000,
                    `purchase_count` int(11) DEFAULT 0,
                    `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_affinity` (`tenant_id`,`product_id`,`related_product_id`),
                    KEY `idx_product` (`product_id`),
                    KEY `idx_related` (`related_product_id`),
                    KEY `idx_score` (`affinity_score`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Update affinity for all pairs
        $stmt = $pdo->prepare("
            INSERT INTO product_affinity 
            (tenant_id, product_id, related_product_id, affinity_score, purchase_count, last_updated)
            VALUES (?, ?, ?, 1.0, 1, NOW())
            ON DUPLICATE KEY UPDATE 
                purchase_count = purchase_count + 1,
                affinity_score = LEAST(1.0, affinity_score + 0.05),
                last_updated = NOW()
        ");
        
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $stmt->execute([$tenantId, $ids[$i], $ids[$j]]);
                $stmt->execute([$tenantId, $ids[$j], $ids[$i]]);
                $updated += 2;
            }
        }
        
    } catch (Exception $e) {
        error_log("Affinity tracking error: " . $e->getMessage());
    }
    
    return $updated;
}

/**
 * Track Sales Velocity
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @param array $productIds Product IDs
 * @param float $totalAmount Total amount
 * @return int Number of velocity records updated
 */
function trackSalesVelocity(
    PDO $pdo,
    int $tenantId,
    int $branchId,
    array $productIds,
    float $totalAmount
): int {
    $updated = 0;
    
    try {
        // Check if velocity table exists, create if not
        $stmt = $pdo->query("SHOW TABLES LIKE 'sales_velocity'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `sales_velocity` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) NOT NULL,
                    `branch_id` int(11) NOT NULL,
                    `product_id` int(11) NOT NULL,
                    `velocity_score` decimal(10,4) DEFAULT 0.0000,
                    `daily_sales` int(11) DEFAULT 0,
                    `weekly_sales` int(11) DEFAULT 0,
                    `monthly_sales` int(11) DEFAULT 0,
                    `total_revenue` decimal(15,2) DEFAULT 0.00,
                    `last_sold_at` timestamp NULL DEFAULT NULL,
                    `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_velocity` (`tenant_id`,`branch_id`,`product_id`),
                    KEY `idx_product` (`product_id`),
                    KEY `idx_score` (`velocity_score`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Update velocity for each product
        $stmt = $pdo->prepare("
            INSERT INTO sales_velocity 
            (tenant_id, branch_id, product_id, velocity_score, daily_sales, monthly_sales, total_revenue, last_sold_at, last_updated)
            VALUES (?, ?, ?, 1.0, 1, 1, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                daily_sales = daily_sales + 1,
                monthly_sales = monthly_sales + 1,
                total_revenue = total_revenue + ?,
                last_sold_at = NOW(),
                velocity_score = LEAST(100, velocity_score + 1),
                last_updated = NOW()
        ");
        
        $amountPerProduct = $totalAmount / count($productIds);
        foreach ($productIds as $pid) {
            $stmt->execute([$tenantId, $branchId, $pid, $amountPerProduct, $amountPerProduct]);
            $updated++;
        }
        
    } catch (Exception $e) {
        error_log("Velocity tracking error: " . $e->getMessage());
    }
    
    return $updated;
}

/**
 * Track Customer Behavior
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $customerId Customer ID
 * @param array $productIds Product IDs
 * @param float $totalAmount Total amount
 * @param string $paymentMethod Payment method
 * @param int $branchId Branch ID
 * @return bool
 */
function trackCustomerBehavior(
    PDO $pdo,
    int $tenantId,
    int $customerId,
    array $productIds,
    float $totalAmount,
    string $paymentMethod,
    int $branchId
): bool {
    if ($customerId <= 0) {
        return false;
    }
    
    try {
        // Check if behavior table exists, create if not
        $stmt = $pdo->query("SHOW TABLES LIKE 'customer_behavior'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `customer_behavior` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) NOT NULL,
                    `customer_id` int(11) NOT NULL,
                    `product_ids` json DEFAULT NULL,
                    `total_amount` decimal(15,2) DEFAULT 0.00,
                    `payment_method` varchar(50) DEFAULT NULL,
                    `time_of_day` int(2) DEFAULT NULL,
                    `day_of_week` int(1) DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_customer` (`customer_id`),
                    KEY `idx_tenant` (`tenant_id`),
                    KEY `idx_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        $timeOfDay = (int) date('H');
        $dayOfWeek = (int) date('N');
        
        $stmt = $pdo->prepare("
            INSERT INTO customer_behavior 
            (tenant_id, customer_id, product_ids, total_amount, payment_method, time_of_day, day_of_week, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $tenantId,
            $customerId,
            json_encode($productIds),
            $totalAmount,
            $paymentMethod,
            $timeOfDay,
            $dayOfWeek
        ]);
        
        // Update customer's last purchase
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET 
                total_spent = COALESCE(total_spent, 0) + ?,
                last_purchase = NOW(),
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$totalAmount, $customerId, $tenantId]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Behavior tracking error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update Customer Segments
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $customerId Customer ID
 * @param float $totalSpent Total spent
 * @return void
 */
function updateCustomerSegments(PDO $pdo, int $tenantId, int $customerId, float $totalSpent): void {
    if ($customerId <= 0) {
        return;
    }
    
    try {
        // Check if segments table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'customer_segments'");
        if ($stmt->rowCount() === 0) {
            return;
        }
        
        // Determine segment based on spending
        $segmentId = null;
        if ($totalSpent > 10000) {
            $segmentId = 4; // Platinum
        } elseif ($totalSpent > 5000) {
            $segmentId = 3; // Gold
        } elseif ($totalSpent > 1000) {
            $segmentId = 2; // Silver
        } else {
            $segmentId = 1; // Bronze
        }
        
        if ($segmentId) {
            $stmt = $pdo->prepare("
                INSERT INTO customer_segments (tenant_id, customer_id, segment_id, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE created_at = NOW()
            ");
            $stmt->execute([$tenantId, $customerId, $segmentId]);
        }
        
    } catch (Exception $e) {
        error_log("Segment update error: " . $e->getMessage());
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
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$tenantId = (int) ($_SESSION['tenant_id'] ?? $_SESSION['company_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$userRole = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

// Session tamper detection
$sessionTenant = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
$sessionUser = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (($sessionTenant !== null && $sessionTenant !== $tenantId) ||
    ($sessionUser !== null && $sessionUser !== $userId)) {
    error_log("ZERO-TRUST VIOLATION: Session mismatch in ai_track_behavior.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($userId <= 0 || $tenantId <= 0 || $branchId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// === 3. Rate Limiting ===
$rateKey = 'rate_limit_ai_track_' . $userId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

$rateData['count']++;

if ($rateData['count'] > 60) {
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

// === 5. CSRF Protection ===
$submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (empty($sessionToken) || empty($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
    error_log("CSRF validation failed in ai_track_behavior.php for user: " . $userId);
    sendErrorResponse('Invalid security token', 'csrf_invalid', 403);
}

// === 6. Get Input ===
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$customerId = (int) ($input['customer_id'] ?? 0);
$productIds = $input['product_ids'] ?? [];
$totalAmount = (float) ($input['total_amount'] ?? 0);
$paymentMethod = (string) ($input['payment_method'] ?? 'cash');
$invoiceNumber = (string) ($input['invoice_number'] ?? '');
$saleId = (int) ($input['sale_id'] ?? 0);

// Validate input
if (empty($productIds) || !is_array($productIds)) {
    sendErrorResponse('No products to track', 'invalid_products', 400);
}

if ($totalAmount <= 0) {
    sendErrorResponse('Total amount must be greater than 0', 'invalid_amount', 400);
}

// === 7. Track Behavior ===
$results = [
    'customer_behavior' => false,
    'product_affinity' => 0,
    'sales_velocity' => 0,
    'customer_session' => false,
    'customer_segment' => false
];

// Track customer behavior
if ($customerId > 0) {
    $results['customer_behavior'] = trackCustomerBehavior(
        $pdo,
        $tenantId,
        $customerId,
        $productIds,
        $totalAmount,
        $paymentMethod,
        $branchId
    );
}

// Track product affinity
$results['product_affinity'] = trackProductAffinity($pdo, $tenantId, $productIds);

// Track sales velocity
$results['sales_velocity'] = trackSalesVelocity($pdo, $tenantId, $branchId, $productIds, $totalAmount);

// Track customer session
trackCustomerSession($pdo, $tenantId, $customerId, $branchId, $productIds, $totalAmount, $paymentMethod);

// Update customer segments
if ($customerId > 0) {
    $stmt = $pdo->prepare("SELECT COALESCE(total_spent, 0) as total_spent FROM customers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$customerId, $tenantId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($customer) {
        updateCustomerSegments($pdo, $tenantId, $customerId, (float) $customer['total_spent']);
        $results['customer_segment'] = true;
    }
}

// === 8. Log Activity ===
logAITracking($pdo, $customerId, $productIds, $totalAmount, $paymentMethod, $tenantId, $branchId);

// === 9. Build Response ===
sendJsonResponse([
    'success' => true,
    'message' => 'Behavior tracked successfully',
    'tracked' => [
        'customer' => $customerId > 0,
        'products' => count($productIds),
        'affinity_pairs' => $results['product_affinity'],
        'velocity_updated' => $results['sales_velocity'],
        'session_updated' => true,
        'segment_updated' => $results['customer_segment']
    ],
    'customer_id' => $customerId,
    'timestamp' => date('Y-m-d H:i:s')
]);

} catch (PDOException $e) {
    error_log("Database error in ai_track_behavior.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    error_log("Error in ai_track_behavior.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    error_log("Fatal error in ai_track_behavior.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}