<?php
/**
 * HOLD SALE - AJAX endpoint for POS
 * Version: 5.0 - Enhanced security, performance, and error handling
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - CRUD operations for held sales
 * - Business type filtering
 * - Auto-cleanup of expired holds
 * - Session validation
 * - Activity logging
 * - Comprehensive error handling
 */

// ============================================
// ERROR HANDLING
// ============================================
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/pos_errors.log');

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
    sendJsonResponse(['success' => false, 'error' => 'Unauthorized. Please log in.'], 401);
}

if (!check_permission('sales.create') && !check_permission('pos.sell') && !is_super_admin()) {
    sendJsonResponse(['success' => false, 'error' => 'Permission denied. You do not have access to hold sales.'], 403);
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
// FUNCTION: Log Activity
// ============================================
function logHeldActivity($action, $saleId, $data = [], $userId = null, $tenantId = null, $branchId = null) {
    try {
        require_once __DIR__ . '/../../src/db.php';
        $pdo = get_db_connection();
        
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, meta, branch_id, tenant_id, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "Held sale #{$saleId} - {$action}";
        $meta = json_encode(['data' => $data, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        return $stmt->execute([$userId, "held_sale.{$action}", $description, $meta, $branchId, $tenantId, $ip]);
    } catch (Exception $e) {
        error_log("Failed to log held activity: " . $e->getMessage());
        return false;
    }
}

// ============================================
// FUNCTION: Cleanup Expired Holds
// ============================================
function cleanupExpiredHolds($pdo, $tenantId, $branchId, $expiryHours = 24) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM held_sales 
            WHERE tenant_id = ? AND branch_id = ? 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$tenantId, $branchId, $expiryHours]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Failed to cleanup expired holds: " . $e->getMessage());
        return 0;
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
    error_log("ZERO-TRUST VIOLATION: Session mismatch in hold_sale.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($userId <= 0 || $tenantId <= 0 || $branchId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// === 3. Rate Limiting ===
$rateKey = 'rate_limit_hold_' . $userId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

$rateData['count']++;

if ($rateData['count'] > 20) {
    sendErrorResponse('Too many requests. Please wait a moment.', 'rate_limited', 429);
}

$_SESSION[$rateKey] = $rateData;

// === 4. Database Connection ===
require_once __DIR__ . '/../../src/db.php';
$pdo = get_db_connection();

if (!$pdo) {
    sendErrorResponse('Database connection failed', 'db_connection_error', 500);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// === 5. Check/Create Table ===
$tableExists = $pdo->query("SHOW TABLES LIKE 'held_sales'")->rowCount();

if (!$tableExists) {
    $pdo->exec("
        CREATE TABLE `held_sales` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tenant_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `branch_id` int(11) NOT NULL,
            `business_type_id` int(11) DEFAULT NULL,
            `sale_data` longtext NOT NULL,
            `table_number` varchar(50) DEFAULT NULL,
            `customer_name` varchar(255) DEFAULT NULL,
            `customer_phone` varchar(50) DEFAULT NULL,
            `total` decimal(15,2) DEFAULT 0.00,
            `item_count` int(11) DEFAULT 0,
            `expires_at` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_held_tenant_branch` (`tenant_id`, `branch_id`),
            KEY `idx_held_user` (`user_id`),
            KEY `idx_held_created` (`created_at`),
            KEY `idx_held_business_type` (`business_type_id`),
            CONSTRAINT `fk_held_sales_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} else {
    // Ensure all columns exist
    $columns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM held_sales");
    while ($row = $stmt->fetch()) {
        $columns[] = $row['Field'];
    }
    
    // Add missing columns
    if (!in_array('tenant_id', $columns)) {
        $pdo->exec("ALTER TABLE held_sales ADD COLUMN tenant_id INT(11) NOT NULL AFTER id");
    }
    if (!in_array('business_type_id', $columns)) {
        $pdo->exec("ALTER TABLE held_sales ADD COLUMN business_type_id INT(11) DEFAULT NULL AFTER branch_id");
        $pdo->exec("CREATE INDEX idx_business_type_id ON held_sales(business_type_id)");
    }
    if (!in_array('customer_phone', $columns)) {
        $pdo->exec("ALTER TABLE held_sales ADD COLUMN customer_phone VARCHAR(50) DEFAULT NULL AFTER customer_name");
    }
    if (!in_array('expires_at', $columns)) {
        $pdo->exec("ALTER TABLE held_sales ADD COLUMN expires_at DATETIME DEFAULT NULL");
        $pdo->exec("CREATE INDEX idx_expires_at ON held_sales(expires_at)");
    }
    if (!in_array('updated_at', $columns)) {
        $pdo->exec("ALTER TABLE held_sales ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()");
    }
}

// === 6. Cleanup expired holds ===
$expiryHours = (int) ($_SESSION['held_sale_expiry_hours'] ?? 24);
cleanupExpiredHolds($pdo, $tenantId, $branchId, $expiryHours);

// === 7. Get Action ===
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

// === 8. Handle Actions ===

// 8a. Save (POST)
if ($action === 'save') {
    // CSRF Protection
    if (!verify_csrf_token($csrfToken)) {
        error_log("CSRF validation failed in hold_sale.php (save) for user: " . $userId);
        sendErrorResponse('CSRF validation failed', 'csrf_invalid', 403);
    }
    
    $data = $_POST['data'] ?? '';
    $btId = isset($_POST['bt_id']) ? (int) $_POST['bt_id'] : 0;
    
    if (empty($data)) {
        sendErrorResponse('No data provided', 'data_required', 400);
    }
    
    try {
        $saleData = json_decode($data, true);
        if (!$saleData || !is_array($saleData)) {
            sendErrorResponse('Invalid data format', 'invalid_data', 400);
        }
        
        // Extract data
        $items = $saleData['items'] ?? [];
        $table = $saleData['table'] ?? $saleData['table_number'] ?? 'Takeaway';
        $customerName = $saleData['customer_name'] ?? $saleData['customer'] ?? 'Walk-in';
        $customerPhone = $saleData['customer_phone'] ?? '';
        $total = (float) ($saleData['total'] ?? 0);
        $itemCount = count($items);
        
        // Calculate total if not provided
        if ($total <= 0 && !empty($items)) {
            foreach ($items as $item) {
                $total += ((float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 0));
            }
        }
        
        // Set expiry (24 hours from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $pdo->prepare("
            INSERT INTO held_sales 
            (tenant_id, user_id, branch_id, business_type_id, sale_data, table_number, customer_name, customer_phone, total, item_count, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $tenantId, $userId, $branchId, $btId, $data, 
            $table, $customerName, $customerPhone, $total, $itemCount, $expiresAt
        ]);
        
        $heldId = (int) $pdo->lastInsertId();
        
        // Log activity
        logHeldActivity('saved', $heldId, ['customer' => $customerName, 'items' => $itemCount], $userId, $tenantId, $branchId);
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Sale held successfully',
            'id' => $heldId,
            'expires_at' => $expiresAt,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error saving held sale: " . $e->getMessage());
        sendErrorResponse('Failed to save held sale', 'save_error', 500);
    } catch (Exception $e) {
        error_log("Error saving held sale: " . $e->getMessage());
        sendErrorResponse($e->getMessage(), 'process_error', 500);
    }
}

// 8b. List (GET)
elseif ($action === 'list' || $action === 'load') {
    $btId = isset($_GET['bt_id']) ? (int) $_GET['bt_id'] : 0;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    
    try {
        $sql = "SELECT * FROM held_sales WHERE tenant_id = ? AND branch_id = ?";
        $params = [$tenantId, $branchId];
        
        // Check if business_type_id column exists and filter
        $colCheck = $pdo->query("SHOW COLUMNS FROM held_sales LIKE 'business_type_id'");
        if ($colCheck->rowCount() > 0 && $btId > 0) {
            $sql .= " AND (business_type_id = ? OR business_type_id IS NULL OR business_type_id = 0)";
            $params[] = $btId;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll();
        
        // Format for display
        foreach ($sales as &$sale) {
            $saleData = json_decode($sale['sale_data'], true);
            $sale['formatted_time'] = date('H:i', strtotime($sale['created_at']));
            $sale['formatted_date'] = date('d M Y', strtotime($sale['created_at']));
            $sale['items_preview'] = array_slice($saleData['items'] ?? [], 0, 3);
            $sale['customer_name'] = $sale['customer_name'] ?? ($saleData['customer_name'] ?? 'Walk-in');
            $sale['item_count'] = (int) ($sale['item_count'] ?? count($saleData['items'] ?? []));
            $sale['total'] = (float) ($sale['total'] ?? 0);
        }
        
        sendJsonResponse([
            'success' => true,
            'sales' => $sales,
            'count' => count($sales),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error loading held sales: " . $e->getMessage());
        sendErrorResponse('Failed to load held sales', 'load_error', 500);
    } catch (Exception $e) {
        error_log("Error loading held sales: " . $e->getMessage());
        sendErrorResponse($e->getMessage(), 'process_error', 500);
    }
}

// 8c. Restore (GET)
elseif ($action === 'restore') {
    $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if ($id <= 0) {
        sendErrorResponse('No ID provided', 'id_required', 400);
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            SELECT * FROM held_sales 
            WHERE id = ? AND tenant_id = ? AND branch_id = ? 
            AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");
        $stmt->execute([$id, $tenantId, $branchId]);
        $sale = $stmt->fetch();
        
        if (!$sale) {
            $pdo->rollBack();
            sendErrorResponse('Held order not found or expired', 'not_found', 404);
        }
        
        // Delete after restoring
        $stmt = $pdo->prepare("DELETE FROM held_sales WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$id, $tenantId, $branchId]);
        
        $pdo->commit();
        
        // Log activity
        logHeldActivity('restored', $id, ['customer' => $sale['customer_name']], $userId, $tenantId, $branchId);
        
        sendJsonResponse([
            'success' => true,
            'sale_data' => $sale['sale_data'],
            'message' => 'Order restored successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Database error restoring held sale: " . $e->getMessage());
        sendErrorResponse('Failed to restore held sale', 'restore_error', 500);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error restoring held sale: " . $e->getMessage());
        sendErrorResponse($e->getMessage(), 'process_error', 500);
    }
}

// 8d. Delete (POST)
elseif ($action === 'delete') {
    // CSRF Protection
    if (!verify_csrf_token($csrfToken)) {
        error_log("CSRF validation failed in hold_sale.php (delete) for user: " . $userId);
        sendErrorResponse('CSRF validation failed', 'csrf_invalid', 403);
    }
    
    $id = (int) ($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        sendErrorResponse('No ID provided', 'id_required', 400);
    }
    
    try {
        // Get sale info before deleting for logging
        $stmt = $pdo->prepare("SELECT customer_name FROM held_sales WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$id, $tenantId, $branchId]);
        $sale = $stmt->fetch();
        
        $stmt = $pdo->prepare("DELETE FROM held_sales WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$id, $tenantId, $branchId]);
        
        if ($stmt->rowCount() > 0) {
            logHeldActivity('deleted', $id, ['customer' => $sale['customer_name'] ?? 'Unknown'], $userId, $tenantId, $branchId);
        }
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Held sale deleted successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error deleting held sale: " . $e->getMessage());
        sendErrorResponse('Failed to delete held sale', 'delete_error', 500);
    } catch (Exception $e) {
        error_log("Error deleting held sale: " . $e->getMessage());
        sendErrorResponse($e->getMessage(), 'process_error', 500);
    }
}

// 8e. Get Count (GET)
elseif ($action === 'count') {
    $btId = isset($_GET['bt_id']) ? (int) $_GET['bt_id'] : 0;
    
    try {
        $sql = "SELECT COUNT(*) as total FROM held_sales WHERE tenant_id = ? AND branch_id = ?";
        $params = [$tenantId, $branchId];
        
        $colCheck = $pdo->query("SHOW COLUMNS FROM held_sales LIKE 'business_type_id'");
        if ($colCheck->rowCount() > 0 && $btId > 0) {
            $sql .= " AND (business_type_id = ? OR business_type_id IS NULL OR business_type_id = 0)";
            $params[] = $btId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();
        
        sendJsonResponse([
            'success' => true,
            'count' => $count,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error counting held sales: " . $e->getMessage());
        sendErrorResponse('Failed to get count', 'count_error', 500);
    }
}

// 8f. Update (POST)
elseif ($action === 'update') {
    // CSRF Protection
    if (!verify_csrf_token($csrfToken)) {
        error_log("CSRF validation failed in hold_sale.php (update) for user: " . $userId);
        sendErrorResponse('CSRF validation failed', 'csrf_invalid', 403);
    }
    
    $id = (int) ($_POST['id'] ?? 0);
    $data = $_POST['data'] ?? '';
    
    if ($id <= 0) {
        sendErrorResponse('No ID provided', 'id_required', 400);
    }
    
    if (empty($data)) {
        sendErrorResponse('No data provided', 'data_required', 400);
    }
    
    try {
        $saleData = json_decode($data, true);
        if (!$saleData || !is_array($saleData)) {
            sendErrorResponse('Invalid data format', 'invalid_data', 400);
        }
        
        // Extract data
        $items = $saleData['items'] ?? [];
        $table = $saleData['table'] ?? $saleData['table_number'] ?? 'Takeaway';
        $customerName = $saleData['customer_name'] ?? $saleData['customer'] ?? 'Walk-in';
        $customerPhone = $saleData['customer_phone'] ?? '';
        $total = (float) ($saleData['total'] ?? 0);
        $itemCount = count($items);
        
        // Calculate total if not provided
        if ($total <= 0 && !empty($items)) {
            foreach ($items as $item) {
                $total += ((float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 0));
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE held_sales 
            SET sale_data = ?, table_number = ?, customer_name = ?, customer_phone = ?, total = ?, item_count = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$data, $table, $customerName, $customerPhone, $total, $itemCount, $id, $tenantId, $branchId]);
        
        if ($stmt->rowCount() > 0) {
            logHeldActivity('updated', $id, ['customer' => $customerName, 'items' => $itemCount], $userId, $tenantId, $branchId);
        }
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Held sale updated successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error updating held sale: " . $e->getMessage());
        sendErrorResponse('Failed to update held sale', 'update_error', 500);
    } catch (Exception $e) {
        error_log("Error updating held sale: " . $e->getMessage());
        sendErrorResponse($e->getMessage(), 'process_error', 500);
    }
}

// 8g. Invalid Action
else {
    sendErrorResponse('Invalid action: ' . $action, 'invalid_action', 400);
}

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in hold_sale.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in hold_sale.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in hold_sale.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}