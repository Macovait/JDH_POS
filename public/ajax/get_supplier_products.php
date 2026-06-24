<?php
/**
 * AJAX endpoint to get products from a specific supplier
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');
safe_require('db.php', 'src');

require_login();

header('Content-Type: application/json');

// Get parameters
$supplier_id = isset($_GET['supplier_id']) ? (int) $_GET['supplier_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
$requested_branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;

if ($supplier_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Supplier ID is required'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = $_SESSION['user']['tenant_id'];

    // Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
    $branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
    if ($branch_id <= 0) {
        $branch_id = get_current_branch_id() ?: 0;
    }

    // Verify supplier belongs to company
    $stmt = $pdo->prepare("
        SELECT id FROM suppliers 
        WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$supplier_id, $tenant_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier not found'
        ]);
        exit;
    }

    // Build query
    $query = "
        SELECT p.id, p.name, p.sku, p.description, p.price, p.cost_price, p.image, p.unit,
               COALESCE(i.stock, 0) AS stock,
               COALESCE(i.minimum_stock, 0) AS minimum_stock,
               COALESCE(i.maximum_stock, 0) AS maximum_stock,
               c.name as category_name
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.tenant_id = ? AND p.deleted_at IS NULL
    ";
    $params = [$branch_id, $tenant_id, $tenant_id];

    if (!empty($search)) {
        $query .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
        $search_term = '%' . $search . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $query .= " ORDER BY p.name ASC";

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
            'description' => $product['description'] ?? '',
            'price' => (float) $product['price'],
            'cost_price' => (float) ($product['cost_price'] ?? 0),
            'stock' => (int) ($product['stock'] ?? 0),
            'category' => $product['category_name'],
            'image' => (function($path) {
                if (!$path) return '';
                $rel = ltrim($path, '/');
                if (strpos($rel, 'public/') === 0) $rel = substr($rel, 7);
                $full = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                return file_exists($full) ? base_url($rel) . '?v=' . (@filemtime($full) ?: time()) : '';
            })($product['image'] ?? ''),
            'unit' => $product['unit'] ?? 'piece',
            'min_stock' => (int) ($product['minimum_stock'] ?? 0),
            'max_stock' => (int) ($product['maximum_stock'] ?? 0)
        ];
    }, $products);

    echo json_encode([
        'success' => true,
        'products' => $formatted_products,
        'count' => count($formatted_products),
        'supplier_id' => $supplier_id
    ]);

} catch (Exception $e) {
    error_log("Error in get_supplier_products: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading supplier products'
    ]);
}
