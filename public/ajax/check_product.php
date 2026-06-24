<?php
/**
 * AJAX endpoint for checking product availability
 * Returns stock status and product details
 */

// Define base path
$root_path = dirname(dirname(__DIR__));

// Load paths.php first
$paths_file = $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';
if (!file_exists($paths_file)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'System configuration not found']);
    exit;
}

require_once $paths_file;

// Load required files
if (!safe_require('auth.php', 'src')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to load authentication']);
    exit;
}

if (!safe_require('db.php', 'src')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to load database']);
    exit;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get parameters
$product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';
$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : get_current_branch_id();

if (!$product_id && empty($barcode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Product ID or barcode required']);
    exit;
}

try {
    $pdo = get_db_connection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $tenant_id = get_current_tenant_id();
    if (!$tenant_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Company context missing']);
        exit;
    }

    // ZERO-TRUST: Validate branch belongs to tenant
    if ($branch_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$branch_id, $tenant_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Branch access denied']);
            exit;
        }
    }

    // Build query
    $has_selling_price = table_has_column($pdo, 'products', 'selling_price');
    $price_col = $has_selling_price ? 'COALESCE(p.selling_price, p.price)' : 'p.price';
    
    if ($product_id) {
        $sql = "
            SELECT p.id, p.name, p.sku, p.barcode, {$price_col} as price, 
                   p.active, p.deleted_at,
                   COALESCE(i.stock, 0) as stock_qty
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
            WHERE p.id = ? AND p.tenant_id = ?
            LIMIT 1
        ";
        $params = [$branch_id, $product_id, $tenant_id];
    } else {
        $sql = "
            SELECT p.id, p.name, p.sku, p.barcode, {$price_col} as price,
                   p.active, p.deleted_at,
                   COALESCE(i.stock, 0) as stock_qty
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
            WHERE p.barcode = ? AND p.tenant_id = ?
            LIMIT 1
        ";
        $params = [$branch_id, $barcode, $tenant_id];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }
    
    // Check if product is active
    if (!$product['active'] || ($product['deleted_at'] && $product['deleted_at'] != '0000-00-00 00:00:00')) {
        echo json_encode([
            'success' => false,
            'error' => 'Product is not active',
            'product' => [
                'id' => $product['id'],
                'name' => $product['name']
            ]
        ]);
        exit;
    }
    
    // Return product info with stock status
    $is_out_of_stock = $product['stock_qty'] <= 0;
    
    echo json_encode([
        'success' => true,
        'product' => [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
            'barcode' => $product['barcode'],
            'price' => (float) $product['price'],
            'stock_qty' => (int) $product['stock_qty'],
            'in_stock' => !$is_out_of_stock,
            'available' => max(0, (int) $product['stock_qty'])
        ]
    ]);
    
} catch (Exception $e) {
    error_log("check_product error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Check if table has column
 */
function table_has_column($pdo, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        try {
            $table_esc = str_replace('``', '``', $table);
            $column_esc = str_replace("'", "\\'", $column);
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_esc}` LIKE '{$column_esc}'");
            $cache[$key] = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $cache[$key] = false;
        }
    }
    return $cache[$key];
}
