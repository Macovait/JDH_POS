<?php
/**
 * AJAX handler to get product by barcode/SKU
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Define base path correctly
$root_path = dirname(dirname(__DIR__)); // This goes from /ajax to /public to /JDH POS/

// Load paths.php first
$paths_file = $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';
if (!file_exists($paths_file)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'System configuration not found']);
    exit;
}

require_once $paths_file;

// Now use safe_require to load auth and db
if (!safe_require('auth.php', 'src')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to load authentication module']);
    exit;
}

if (!safe_require('db.php', 'src')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to load database module']);
    exit;
}

safe_require('functions.php', 'src');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!check_permission('products.view') && !check_permission('pos.sell') && !is_super_admin()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

header('Content-Type: application/json');

// Get parameters
$barcode = $_GET['barcode'] ?? '';
$current_user_id = (int) (get_current_user_id() ?? 0);
$current_tenant_id = (int) (get_current_tenant_id() ?? 0);
$current_branch_id = (int) (get_current_branch_id() ?? 0);
$current_business_type = (string) (get_current_business_type($current_tenant_id) ?? ($_SESSION['business_type'] ?? ''));
$current_business_type_id = (int) (get_current_business_type_id($current_tenant_id) ?? 0);

// ZERO-TRUST: Reject URL overrides for tenant and user context
if (!empty($_GET['tenant_id']) || !empty($_GET['user_id'])) {
    error_log("ZERO-TRUST VIOLATION: URL parameter override attempt in get_product_by_barcode.php");
    http_response_code(403);
    echo json_encode(['found' => false, 'message' => 'Invalid request']);
    exit;
}

$requested_tenant_id = $current_tenant_id;
$requested_branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : $current_branch_id;
$requested_business_type = trim((string) ($_GET['business_type'] ?? $current_business_type));
$requested_business_type_id = isset($_GET['business_type_id']) ? (int) $_GET['business_type_id'] : $current_business_type_id;

if ($current_user_id <= 0 || $current_tenant_id <= 0) {
    http_response_code(401);
    echo json_encode(['found' => false, 'message' => 'Session expired']);
    exit;
}

if ($current_business_type !== '' && $requested_business_type !== '' && $requested_business_type !== $current_business_type) {
    http_response_code(403);
    echo json_encode(['found' => false, 'message' => 'Business type mismatch']);
    exit;
}

if ($current_business_type_id > 0 && $requested_business_type_id > 0 && $requested_business_type_id !== $current_business_type_id) {
    http_response_code(403);
    echo json_encode(['found' => false, 'message' => 'Business type mismatch']);
    exit;
}

$tenant_id = $current_tenant_id;
$branch_id = $requested_branch_id > 0 ? $requested_branch_id : $current_branch_id;
$business_type = $current_business_type !== '' ? $current_business_type : $requested_business_type;
$business_type_id = $current_business_type_id > 0 ? $current_business_type_id : $requested_business_type_id;

$can_view_other_branches = (function_exists('is_super_admin') && is_super_admin())
    || (function_exists('check_permission') && (check_permission('branches.view') || check_permission('branches.manage')));

if (!$can_view_other_branches && $current_branch_id > 0 && $branch_id !== $current_branch_id) {
    http_response_code(403);
    echo json_encode(['found' => false, 'message' => 'You are not allowed to view this branch']);
    exit;
}

try {
    $branchCheck = get_db_connection();
    if ($branchCheck) {
        $stmt = $branchCheck->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$branch_id, $tenant_id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(403);
            echo json_encode(['found' => false, 'message' => 'Branch does not belong to your tenant']);
            exit;
        }
    }
} catch (Exception $e) {
}

if (empty($barcode)) {
    echo json_encode(['found' => false, 'message' => 'No barcode provided']);
    exit;
}

try {
    $pdo = get_db_connection();

    $has_product_company = false;
    $has_product_branch = false;
    $has_product_business_type_id = false;
    $has_product_business_type = false;
    $has_deleted_at = false;
    $has_active = false;
    $has_status = false;
    $has_product_stock = false;
    $has_inventory = false;
    $has_inventory_company = false;
    $has_barcode_column = false;

    try {
        $columns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
        $productColumns = array_flip($columns);
        $has_product_company = isset($productColumns['tenant_id']);
        $has_product_branch = isset($productColumns['branch_id']);
        $has_product_business_type_id = isset($productColumns['business_type_id']);
        $has_product_business_type = isset($productColumns['business_type']);
        $has_deleted_at = isset($productColumns['deleted_at']);
        $has_active = isset($productColumns['active']);
        $has_status = isset($productColumns['status']);
        $has_product_stock = isset($productColumns['stock']);
        $has_barcode_column = isset($productColumns['barcode']);
    } catch (Exception $e) {
    }

    try {
        $inventoryColumns = $pdo->query("SHOW COLUMNS FROM inventory")->fetchAll(PDO::FETCH_COLUMN);
        $inventoryColumns = array_flip($inventoryColumns);
        $has_inventory = true;
        $has_inventory_company = isset($inventoryColumns['tenant_id']);
    } catch (Exception $e) {
    }

    $where = [];
    $params = [];

    if ($has_product_company && $tenant_id > 0) {
        $where[] = 'p.tenant_id = :tenant_id';
        $params[':tenant_id'] = $tenant_id;
    }

    if ($has_product_branch && $branch_id > 0) {
        $where[] = '(p.branch_id = :branch_id OR p.branch_id IS NULL)';
        $params[':branch_id'] = $branch_id;
    }

    if ($has_product_business_type_id && $business_type_id > 0) {
        $where[] = 'p.business_type_id = :business_type_id';
        $params[':business_type_id'] = $business_type_id;
    } elseif ($has_product_business_type && $business_type !== '') {
        $where[] = 'p.business_type = :business_type';
        $params[':business_type'] = $business_type;
    }

    if ($has_deleted_at) {
        $where[] = "(p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')";
    }

    if ($has_active) {
        $where[] = 'p.active = 1';
    }

    if ($has_status) {
        $where[] = "(p.status = 'active' OR p.status IS NULL OR p.status = '')";
    }

    if ($has_barcode_column) {
        $where[] = '(p.barcode = :barcode OR p.sku = :sku)';
        $params[':barcode'] = $barcode;
        $params[':sku'] = $barcode;
    } else {
        $where[] = 'p.sku = :sku';
        $params[':sku'] = $barcode;
    }

    $inventoryJoin = '';
    $stockExpr = $has_product_stock ? 'COALESCE(p.stock, 0)' : '0';

    if ($has_inventory) {
        $joinConditions = ['p.id = i.product_id', 'i.branch_id = :inventory_branch_id'];
        $params[':inventory_branch_id'] = $branch_id;

        if ($has_inventory_company && $tenant_id > 0) {
            $joinConditions[] = 'i.tenant_id = :inventory_company_id';
            $params[':inventory_company_id'] = $tenant_id;
        }

        $inventoryJoin = 'LEFT JOIN inventory i ON ' . implode(' AND ', $joinConditions);
        $stockExpr = $has_product_stock ? 'COALESCE(i.stock, p.stock, 0)' : 'COALESCE(i.stock, 0)';
    }

    $sql = "
        SELECT
            p.id,
            p.name,
            COALESCE(p.selling_price, p.price, 0) AS price,
            {$stockExpr} AS stock,
            p.sku,
            p.image,
            c.name AS category_name
        FROM products p
        {$inventoryJoin}
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE " . implode(' AND ', $where) . "
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // Log barcode scan
        if (function_exists('log_activity')) {
            log_activity($_SESSION['user']['id'] ?? 0, 'barcode_scan', [
                'sku' => $barcode, 'product_id' => $product['id'],
                'product_name' => $product['name']
            ], get_current_tenant_id());
        }

        echo json_encode([
            'found' => true,
            'product' => [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => (float) $product['price'],
                'stock' => (int) ($product['stock'] ?? 0),
                'sku' => $product['sku'],
                'category' => $product['category_name'],
                'image' => (function($path) {
                    if (!$path) return '';
                    $rel = ltrim($path, '/');
                    if (strpos($rel, 'public/') === 0) $rel = substr($rel, 7);
                    $full = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                    return file_exists($full) ? base_url($rel) . '?v=' . (@filemtime($full) ?: time()) : '';
                })($product['image'])
            ]
        ]);
    } else {
        echo json_encode(['found' => false, 'message' => 'Product not found']);
    }

} catch (PDOException $e) {
    error_log("Barcode lookup error: " . $e->getMessage());
    echo json_encode(['found' => false, 'message' => 'Database error']);
}
