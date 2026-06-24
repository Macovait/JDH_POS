<?php
/**
 * LAZY PRODUCT LOADER - AJAX endpoint for POS
 * Version: 6.0 - Enhanced security, performance, and error handling
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - CSRF protection
 * - Rate limiting
 * - Response caching
 * - Proper parameter binding
 * - Comprehensive error handling
 * - Audit logging
 */

// ============================================
// ERROR HANDLING - MUST BE FIRST
// ============================================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Disable default error output
ob_start();

// Top-level try/catch to ensure JSON error response for ANY failure
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

if (!check_permission('products.view') && !check_permission('pos.sell') && !is_super_admin()) {
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
// FUNCTION: Database Connection
// ============================================
function getDbConnection() {
    try {
        require_once __DIR__ . '/../../src/db.php';
        return get_db_connection();
    } catch (PDOException $e) {
        error_log("DB Connection failed: " . $e->getMessage());
        return null;
    }
}

// ============================================
// FUNCTION: Check Table/Column Exists (Cached)
// ============================================
$tableCache = [];
$columnCache = [];

function tableExistsCached($pdo, $table) {
    global $tableCache;
    
    if (isset($tableCache[$table])) {
        return $tableCache[$table];
    }
    
    try {
        $result = $pdo->query("SHOW TABLES LIKE '{$table}'");
        $exists = $result && $result->rowCount() > 0;
        $tableCache[$table] = $exists;
        return $exists;
    } catch (Exception $e) {
        return false;
    }
}

function columnExistsCached($pdo, $table, $column) {
    global $columnCache;
    $key = $table . '.' . $column;
    
    if (isset($columnCache[$key])) {
        return $columnCache[$key];
    }
    
    try {
        $result = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        $exists = $result && $result->rowCount() > 0;
        $columnCache[$key] = $exists;
        return $exists;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// FUNCTION: Rate Limiting
// ============================================
function checkRateLimit($key, $limit = 60, $window = 60) {
    // Simple rate limiting using session
    $rateKey = 'rate_limit_' . $key;
    $rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + $window];
    
    if (time() > $rateData['reset']) {
        $rateData = ['count' => 0, 'reset' => time() + $window];
    }
    
    $rateData['count']++;
    
    if ($rateData['count'] > $limit) {
        return false;
    }
    
    $_SESSION[$rateKey] = $rateData;
    return true;
}

// ============================================
// AUTHENTICATION - Zero Trust Validation
// ============================================
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['company_id'] ?? 0;
$branch_id = $_SESSION['branch_id'] ?? 0;
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';

// Session tamper detection
$session_tenant_id = $_SESSION['tenant_id'] ?? null;
$session_user_id = $_SESSION['user_id'] ?? null;

if (($session_tenant_id && (int)$session_tenant_id !== $tenant_id) ||
    ($session_user_id && (int)$session_user_id !== $user_id)) {
    error_log("ZERO-TRUST VIOLATION: Session mismatch in get_products.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($user_id <= 0 || $tenant_id <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// Rate limiting check
if (!checkRateLimit('products_' . $user_id, 100, 60)) {
    sendErrorResponse('Too many requests. Please wait a moment.', 'rate_limited', 429);
}

// ============================================
// CSRF PROTECTION (Optional - for POST requests)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        error_log("CSRF validation failed in get_products.php for user: " . $user_id);
        sendErrorResponse('CSRF validation failed', 'csrf_invalid', 403);
    }
}

// ============================================
// GET PARAMETERS (VALIDATED & SANITIZED)
// ============================================
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$limit = min(200, max(10, filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 50));
$search = trim(filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW) ?: '');
$search = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
$category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;
$bt_id = filter_input(INPUT_GET, 'bt_id', FILTER_VALIDATE_INT) ?: 0;
$include_stock = filter_input(INPUT_GET, 'include_stock', FILTER_VALIDATE_BOOLEAN) ?: true;
$include_categories = filter_input(INPUT_GET, 'include_categories', FILTER_VALIDATE_BOOLEAN) ?: true;
$include_tags = filter_input(INPUT_GET, 'include_tags', FILTER_VALIDATE_BOOLEAN) ?: true;

$offset = ($page - 1) * $limit;
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

// ============================================
// DATABASE CONNECTION
// ============================================
$pdo = getDbConnection();

if (!$pdo) {
    sendErrorResponse('Database connection failed', 'db_connection_error', 500);
}

// Set PDO attributes
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// ============================================
// CACHE CHECK (5 minute cache)
// ============================================
$cache_key = "products_lazy_{$tenant_id}_{$branch_id}_{$bt_id}_{$category}_{$page}_{$limit}_" . md5($search);
$cache_time = 300; // 5 minutes

// Try APCu cache first
if (function_exists('apcu_fetch')) {
    $cached = apcu_fetch($cache_key);
    if ($cached !== false) {
        // Add cache hit indicator
        $cached['_cached'] = true;
        $cached['_cache_time'] = date('Y-m-d H:i:s');
        sendJsonResponse($cached);
    }
}

// Try file cache as fallback
$cache_dir = __DIR__ . '/../../cache/';
if (is_dir($cache_dir) && is_writable($cache_dir)) {
    $cache_file = $cache_dir . md5($cache_key) . '.json';
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if ($cached && isset($cached['success']) && $cached['success']) {
            $cached['_cached'] = true;
            $cached['_cache_time'] = date('Y-m-d H:i:s', filemtime($cache_file));
            sendJsonResponse($cached);
        }
    }
}

// ============================================
// CHECK TABLE STRUCTURE (Cached)
// ============================================
$has_deleted_at = columnExistsCached($pdo, 'products', 'deleted_at');
$has_barcode = columnExistsCached($pdo, 'products', 'barcode');
$has_bt_id = columnExistsCached($pdo, 'products', 'business_type_id');
$has_stock_table = tableExistsCached($pdo, 'inventory') || tableExistsCached($pdo, 'product_stock');
$has_product_tags = tableExistsCached($pdo, 'product_tags') && tableExistsCached($pdo, 'product_tag_relations');
$stock_table = tableExistsCached($pdo, 'inventory') ? 'inventory' : (tableExistsCached($pdo, 'product_stock') ? 'product_stock' : null);

// ============================================
// MAIN QUERY - FIXED PARAMETER BINDING
// ============================================
try {
    // === 1. Build WHERE clause with proper parameter placeholders ===
    $where_conditions = ["p.tenant_id = ?", "p.active = 1"];
    $where_params = [$tenant_id];
    
    if ($has_deleted_at) {
        $where_conditions[] = "p.deleted_at IS NULL";
    }
    
    // Search filter
    if (!empty($search)) {
        $search_conditions = ["p.name LIKE ?", "p.sku LIKE ?"];
        $search_params = ["%{$search}%", "%{$search}%"];
        
        if ($has_barcode) {
            $search_conditions[] = "p.barcode LIKE ?";
            $search_params[] = "%{$search}%";
        }
        
        $where_conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
        $where_params = array_merge($where_params, $search_params);
    }
    
    // Category filter
    if ($category > 0) {
        $where_conditions[] = "p.category_id = ?";
        $where_params[] = $category;
    }
    
    // Business type filter
    if ($has_bt_id && $bt_id > 0) {
        $where_conditions[] = "(p.business_type_id = ? OR p.business_type_id IS NULL OR p.business_type_id = 0)";
        $where_params[] = $bt_id;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // === 2. Stock join (only if table exists) ===
    $stock_select = "0 as stock";
    $stock_join = "";
    $stock_params = [];
    
    if ($stock_table && $include_stock) {
        // Check if the stock table has the right columns
        $has_quantity = columnExistsCached($pdo, $stock_table, 'quantity') || 
                        columnExistsCached($pdo, $stock_table, 'stock');
        $stock_column = columnExistsCached($pdo, $stock_table, 'quantity') ? 'quantity' : 'stock';
        
        if ($has_quantity) {
            $stock_join = "LEFT JOIN {$stock_table} i ON p.id = i.product_id AND i.branch_id = ?";
            $stock_select = "COALESCE(i.{$stock_column}, 0) as stock";
            $stock_params = [$branch_id];
        }
    }
    
    // === 3. Get total count ===
    $count_sql = "SELECT COUNT(*) as total FROM products p {$where_clause}";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($where_params);
    $total = (int)$count_stmt->fetchColumn();
    
    // === 4. Get products ===
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.price,
            p.cost_price,
            p.image,
            p.category_id,
            p.description,
            p.business_type_id,
            p.manage_stock,
            p.unit,
            p.reorder_level,
            p.barcode,
            {$stock_select},
            c.name as category_name,
            c.color as category_color,
            c.icon as category_icon
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id AND c.tenant_id = ? AND c.status = 'active'
        {$stock_join}
        {$where_clause}
        ORDER BY p.name ASC
        LIMIT ? OFFSET ?
    ";
    
    // Build final parameters in the correct order
    $final_params = array_merge(
        [$tenant_id],          // For categories join
        $stock_params,         // For stock join (branch_id)
        $where_params,         // For WHERE clause
        [$limit, $offset]      // For LIMIT and OFFSET
    );
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($final_params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // === 5. Process product images (FAST: no file_exists I/O calls) ===
    $public_path = '/JDH_POS/public/';
    foreach ($products as &$product) {
        if (!empty($product['image'])) {
            $image = ltrim($product['image'], '/\\');
            if (strpos($image, 'public/') === 0) {
                $image = substr($image, 7);
            }
            $product['image_url'] = $base_url . $public_path . $image;
            $product['image_exists'] = true;
        } else {
            $product['image_url'] = null;
            $product['image_exists'] = false;
        }

        $product['stock'] = (int)($product['stock'] ?? 999);
        $product['price'] = (float)$product['price'];
        $product['cost_price'] = (float)($product['cost_price'] ?? 0);
        $product['manage_stock'] = (bool)($product['manage_stock'] ?? false);
        $product['is_low_stock'] = $product['manage_stock'] && $product['stock'] <= $product['reorder_level'];
        $product['is_out_of_stock'] = $product['manage_stock'] && $product['stock'] <= 0;
    }
    unset($product);
    
    // === 6. Get categories with counts ===
    $categories = [];
    if ($include_categories) {
        $category_sql = "
            SELECT 
                c.id,
                c.name,
                c.color,
                c.icon,
                c.parent_id,
                COUNT(p.id) as product_count
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id 
                AND p.tenant_id = ?
                AND p.active = 1
        ";
        
        if ($has_deleted_at) {
            $category_sql .= " AND p.deleted_at IS NULL";
        }
        
        $category_sql .= "
            WHERE c.tenant_id = ? 
                AND c.status = 'active'
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
        ";
        
        $cat_stmt = $pdo->prepare($category_sql);
        $cat_stmt->execute([$tenant_id, $tenant_id]);
        $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add product counts for each category
        $category_product_counts = [];
        foreach ($categories as $cat) {
            $category_product_counts[$cat['id']] = $cat['product_count'];
        }
    }
    
    // === 7. Get tags if table exists ===
    $product_tags_map = [];
    if (!empty($products) && $include_tags && $has_product_tags) {
        try {
            $product_ids = array_column($products, 'id');
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            
            $tag_sql = "
                SELECT r.product_id, t.id as tag_id, t.name, t.color, t.icon
                FROM product_tag_relations r
                JOIN product_tags t ON r.tag_id = t.id
                WHERE r.tenant_id = ? 
                    AND r.product_id IN ({$placeholders}) 
                    AND t.is_active = 1
            ";
            $tag_stmt = $pdo->prepare($tag_sql);
            $tag_stmt->execute(array_merge([$tenant_id], $product_ids));
            
            while ($row = $tag_stmt->fetch(PDO::FETCH_ASSOC)) {
                $pid = (int)$row['product_id'];
                if (!isset($product_tags_map[$pid])) {
                    $product_tags_map[$pid] = [];
                }
                $product_tags_map[$pid][] = [
                    'id' => (int)$row['tag_id'],
                    'name' => $row['name'],
                    'color' => $row['color'],
                    'icon' => $row['icon'] ?? 'fa-tag'
                ];
            }
            
            foreach ($products as &$p) {
                $pid = (int)$p['id'];
                $p['tags'] = $product_tags_map[$pid] ?? [];
                $p['tag_ids'] = array_column($p['tags'], 'id');
            }
            unset($p);
            
        } catch (Exception $e) {
            // Tags failed - just continue without them
            error_log("Failed to load tags in get_products.php: " . $e->getMessage());
        }
    } else {
        // Ensure tags array exists for all products
        foreach ($products as &$p) {
            $p['tags'] = [];
            $p['tag_ids'] = [];
        }
        unset($p);
    }
    
    // === 8. Log successful product load ===
    error_log("Products loaded: " . count($products) . " products for tenant {$tenant_id} branch {$branch_id}");
    
    // === 9. Build response ===
    $response = [
        'success' => true,
        'products' => $products,
        'categories' => $categories ?? [],
        'count' => count($products),
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => max(1, ceil($total / $limit)),
        'has_more' => ($page * $limit) < $total,
        'branch_id' => (int)$branch_id,
        'tenant_id' => (int)$tenant_id,
        'business_type_id' => (int)$bt_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'query_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
        '_cached' => false
    ];
    
    // === 10. Cache the response ===
    try {
        // APCu cache
        if (function_exists('apcu_store')) {
            apcu_store($cache_key, $response, $cache_time);
        }
        
        // File cache
        if (is_dir($cache_dir) && is_writable($cache_dir)) {
            $cache_file = $cache_dir . md5($cache_key) . '.json';
            file_put_contents($cache_file, json_encode($response));
        }
    } catch (Exception $e) {
        // Cache failure is non-critical - just log it
        error_log("Cache store failed: " . $e->getMessage());
    }
    
    sendJsonResponse($response);
    
} catch (PDOException $e) {
    $errorCode = $e->getCode();
    $errorMessage = $e->getMessage();
    
    error_log("Database error in get_products.php: " . $errorMessage);
    error_log("SQL State: " . $errorCode);
    error_log("Error Line: " . $e->getLine());
    
    // Check if it's a connection issue
    if (strpos($errorMessage, 'server has gone away') !== false || 
        strpos($errorMessage, 'MySQL server has gone away') !== false) {
        sendErrorResponse('Database connection lost. Please refresh the page.', 'db_connection_lost', 503);
    } elseif (strpos($errorMessage, 'Duplicate entry') !== false) {
        sendErrorResponse('Duplicate entry error.', 'db_duplicate', 409);
    } else {
        sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    }
    
} catch (Throwable $e) {
    error_log("Fatal error in get_products.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse(
        'An unexpected error occurred. Please refresh the page.',
        'server_error',
        500
    );
}

} catch (Throwable $outer) {
    // If even sendErrorResponse failed, force raw JSON output
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'critical_server_error',
        'message' => $outer->getMessage(),
        'line' => $outer->getLine(),
        'file' => basename($outer->getFile()),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}