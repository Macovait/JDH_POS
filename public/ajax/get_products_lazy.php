<?php
/**
 * LAZY PRODUCT LOADER - AJAX endpoint for POS
 * Version: 5.0 - Fixed parameter binding and optimized queries
 */

require_once __DIR__ . '/../../src/Security/CorsHandler.php';
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
// FUNCTION: Send JSON Response
// ============================================
function sendJsonResponse($data, $statusCode = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    \Jakababa\Security\apply_cors_headers();
    
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
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
// FUNCTION: Check Table/Column Exists
// ============================================
function tableExists($pdo, $table) {
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$table]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $column]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// AUTHENTICATION
// ============================================
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['company_id'] ?? 0;
$branch_id = $_SESSION['branch_id'] ?? 0;

if ($user_id <= 0 || $tenant_id <= 0) {
    sendJsonResponse([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Please login to access products'
    ], 401);
}

// ============================================
// GET PARAMETERS (VALIDATED)
// ============================================
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$limit = min(100, max(10, filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 50));
$search = trim(filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW) ?: '');
$search = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
$category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;
$bt_id = filter_input(INPUT_GET, 'bt_id', FILTER_VALIDATE_INT) ?: 0;
$offset = ($page - 1) * $limit;
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

// ============================================
// DATABASE CONNECTION
// ============================================
$pdo = getDbConnection();

if (!$pdo) {
    sendJsonResponse([
        'success' => false,
        'error' => 'Database connection failed',
        'message' => 'Unable to connect to database. Please check your configuration.'
    ], 500);
}

// ============================================
// CACHE CHECK (Optional - 5 minute cache)
// ============================================
$cache_key = "products_lazy_{$tenant_id}_{$branch_id}_{$bt_id}_{$category}_{$page}_{$limit}_" . md5($search);
$cache_time = 300; // 5 minutes

// If you have APCu, Redis, or file caching, uncomment:
// $cached = apcu_fetch($cache_key);
// if ($cached !== false) {
//     sendJsonResponse($cached);
// }

// ============================================
// CHECK TABLE STRUCTURE ONCE
// ============================================
$has_deleted_at = columnExists($pdo, 'products', 'deleted_at');
$has_barcode = columnExists($pdo, 'products', 'barcode');
$has_bt_id = columnExists($pdo, 'products', 'business_type_id');
$has_stock_table = tableExists($pdo, 'inventory') || tableExists($pdo, 'product_stock');
$stock_table = tableExists($pdo, 'inventory') ? 'inventory' : (tableExists($pdo, 'product_stock') ? 'product_stock' : null);

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
    
    if ($stock_table) {
        // Check if the stock table has the right columns
        $has_quantity = columnExists($pdo, $stock_table, 'quantity') || 
                        columnExists($pdo, $stock_table, 'stock');
        $stock_column = columnExists($pdo, $stock_table, 'quantity') ? 'quantity' : 'stock';
        
        if ($has_quantity) {
            $stock_join = "LEFT JOIN {$stock_table} i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?";
            $stock_select = "COALESCE(i.{$stock_column}, 0) as stock";
            $stock_params = [$branch_id, $tenant_id];
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
        $stock_params,         // For stock join (branch_id, tenant_id)
        $where_params,         // For WHERE clause
        [$limit, $offset]      // For LIMIT and OFFSET
    );
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($final_params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // === 5. Process product images ===
    $public_path = '/JDH_POS/public/';
    foreach ($products as &$product) {
        if (!empty($product['image'])) {
            // Remove leading slashes and ensure correct path
            $image = ltrim($product['image'], '/\\');
            if (strpos($image, 'public/') === 0) {
                $image = substr($image, 7);
            }
            $product['image_url'] = $base_url . $public_path . $image;
        } else {
            $product['image_url'] = null;
        }
        
        // Ensure stock is an integer
        $product['stock'] = (int)($product['stock'] ?? 999);
        $product['price'] = (float)$product['price'];
        $product['cost_price'] = (float)($product['cost_price'] ?? 0);
    }
    unset($product);
    
    // === 6. Get categories with counts ===
    $category_sql = "
        SELECT 
            c.id,
            c.name,
            c.color,
            c.icon,
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
    
    // === 7. Get tags if table exists ===
    $product_tags_map = [];
    if (!empty($products) && tableExists($pdo, 'product_tags')) {
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
            error_log("Failed to load tags: " . $e->getMessage());
        }
    }
    
    // === 8. Build response ===
    $response = [
        'success' => true,
        'products' => $products,
        'categories' => $categories,
        'count' => count($products),
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit),
        'has_more' => ($page * $limit) < $total,
        'branch_id' => $branch_id,
        'tenant_id' => $tenant_id,
        'business_type_id' => $bt_id,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Cache the response (if you have APCu)
    // apcu_store($cache_key, $response, $cache_time);
    
    sendJsonResponse($response);
    
} catch (PDOException $e) {
    error_log("Database error in get_products_lazy: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendJsonResponse([
        'success' => false,
        'error' => 'Database error',
        'message' => 'An error occurred while loading products. Please try again.'
    ], 500);
    
} catch (Throwable $e) {
    error_log("Fatal/Error in get_products_lazy: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendJsonResponse([
        'success' => false,
        'error' => 'Server error',
        'message' => 'An unexpected error occurred. Please refresh the page.',
        'debug' => [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]
    ], 500);
}

} catch (Throwable $outer) {
    // If even sendJsonResponse failed, force raw JSON output
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Critical server error',
        'message' => $outer->getMessage(),
        'line' => $outer->getLine(),
        'file' => basename($outer->getFile())
    ]);
    exit;
}