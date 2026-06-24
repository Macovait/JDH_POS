<?php
/**
 * AJAX endpoint to search products
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');
safe_require('db.php', 'src');

require_login();

header('Content-Type: application/json');

// Get parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$requested_branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

// Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
$tenant_id = $_SESSION['user']['tenant_id'];
$branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
if ($branch_id <= 0 && $requested_branch_id > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied to this branch'
    ]);
    exit;
}

if (empty($search)) {
    echo json_encode([
        'success' => false,
        'message' => 'Search term is required'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = $_SESSION['user']['tenant_id'];

    // Build query
    $query = "
        SELECT p.id, p.name, p.sku, p.barcode, p.description, p.price, p.cost_price, p.image, p.unit, p.tax_rate,
               COALESCE(i.stock, 0) AS stock,
               COALESCE(i.minimum_stock, 0) AS minimum_stock,
               COALESCE(i.maximum_stock, 0) AS maximum_stock,
               c.name as category_name, c.color as category_color
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.tenant_id = ? AND p.deleted_at IS NULL
        AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ? OR p.barcode LIKE ?)
    ";
    $search_term = '%' . $search . '%';
    $params = [$branch_id, $tenant_id, $tenant_id, $search_term, $search_term, $search_term, $search_term];

    if ($category_id > 0) {
        $query .= " AND p.category_id = ?";
        $params[] = $category_id;
    }

    $query .= " ORDER BY 
        CASE 
            WHEN p.name LIKE ? THEN 1
            WHEN p.sku LIKE ? THEN 2
            WHEN p.barcode LIKE ? THEN 3
            ELSE 4
        END,
        p.name ASC
    ";
    $params[] = $search . '%';
    $params[] = $search . '%';
    $params[] = $search . '%';

    if ($limit > 0) {
        $query .= " LIMIT ?";
        $params[] = $limit;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format products
    $formatted_products = array_map(function ($product) {
        return [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
            'barcode' => $product['barcode'] ?? '',
            'description' => $product['description'] ?? '',
            'price' => (float) $product['price'],
            'cost_price' => (float) ($product['cost_price'] ?? 0),
            'stock' => (int) ($product['stock'] ?? 0),
            'category' => $product['category_name'],
            'category_color' => $product['category_color'] ?? '#6B7280',
            'image' => (function($path) {
                if (!$path) return '';
                $rel = ltrim($path, '/');
                if (strpos($rel, 'public/') === 0) $rel = substr($rel, 7);
                $full = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                return file_exists($full) ? base_url($rel) . '?v=' . (@filemtime($full) ?: time()) : '';
            })($product['image'] ?? ''),
            'unit' => $product['unit'] ?? 'piece',
            'tax_rate' => (float) ($product['tax_rate'] ?? 0),
            'min_stock' => (int) ($product['minimum_stock'] ?? 0),
            'max_stock' => (int) ($product['maximum_stock'] ?? 0)
        ];
    }, $products);

    echo json_encode([
        'success' => true,
        'products' => $formatted_products,
        'count' => count($formatted_products),
        'search_term' => $search
    ]);

} catch (Exception $e) {
    error_log("Error in search_products: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error searching products'
    ]);
}
