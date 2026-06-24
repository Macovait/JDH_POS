<?php
/**
 * AJAX endpoint for product management operations
 * Handles: toggle_status, bulk_delete, bulk_status, quick_search, get_details
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');
safe_require('db.php', 'src');

require_login();

$csrf_token = generate_csrf_token();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

// CSRF verification
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$response = ['success' => false, 'message' => 'Invalid action'];

try {
    switch ($action) {
        case 'toggle_status':
            if (!check_permission('products.manage') && !is_super_admin()) {
                throw new Exception('Permission denied');
            }
            $product_id = (int) ($_POST['product_id'] ?? 0);
            if ($product_id <= 0) throw new Exception('Invalid product ID');
            
            $stmt = $pdo->prepare("SELECT active FROM products WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
            $stmt->execute([$product_id, $tenant_id]);
            $current = $stmt->fetchColumn();
            if ($current === false) throw new Exception('Product not found');
            
            $new_status = $current ? 0 : 1;
            $pdo->prepare("UPDATE products SET active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?")->execute([$new_status, $product_id, $tenant_id]);
            $response = ['success' => true, 'message' => 'Status updated', 'new_status' => $new_status];
            break;

        case 'bulk_delete':
            if (!is_super_admin() && !($_SESSION['user']['role'] ?? '' === 'Admin')) {
                throw new Exception('Permission denied');
            }
            $ids = $_POST['ids'] ?? [];
            if (empty($ids)) throw new Exception('No products selected');
            $ids = array_map('intval', array_filter($ids, fn($id) => $id > 0));
            if (empty($ids)) throw new Exception('Invalid product IDs');
            
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.product_id IN ($placeholders) AND s.tenant_id = ?");
            $stmt->execute(array_merge($ids, [$tenant_id]));
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete products with sales history');
            }
            
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id IN ($placeholders) AND tenant_id = ? AND deleted_at IS NULL");
            $stmt->execute(array_merge($ids, [$tenant_id]));
            foreach ($stmt->fetchAll() as $img) {
                if ($img['image'] && file_exists($root_path . '/public' . $img['image'])) {
                    @unlink($root_path . '/public' . $img['image']);
                }
            }
            
            $pdo->prepare("UPDATE products SET deleted_at = NOW(), active = 0, updated_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ?")->execute(array_merge($ids, [$tenant_id]));
            
            $response = ['success' => true, 'message' => count($ids) . ' product(s) deleted'];
            break;

        case 'bulk_status':
            if (!check_permission('products.manage') && !is_super_admin()) {
                throw new Exception('Permission denied');
            }
            $ids = $_POST['ids'] ?? [];
            $status = (int) ($_POST['status'] ?? 1);
            if (empty($ids)) throw new Exception('No products selected');
            $ids = array_map('intval', array_filter($ids, fn($id) => $id > 0));
            if (empty($ids)) throw new Exception('Invalid product IDs');
            
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE products SET active = ?, updated_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ?")->execute(array_merge([$status], $ids, [$tenant_id]));
            
            $label = $status ? 'activated' : 'deactivated';
            $response = ['success' => true, 'message' => count($ids) . " product(s) $label"];
            break;

        case 'quick_search':
            $search = trim($_POST['search'] ?? '');
            $branch_id = (int) ($_POST['branch_id'] ?? get_current_branch_id());
            if (strlen($search) < 1) throw new Exception('Search term required');
            
            $searchParam = "%$search%";
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.sku, p.price, p.image, p.active,
                       c.name AS category, c.color AS category_color,
                       COALESCE((SELECT SUM(stock) FROM inventory WHERE product_id = p.id AND branch_id = ? AND tenant_id = ?), 0) as stock
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.tenant_id = ? AND p.deleted_at IS NULL AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)
                ORDER BY p.name ASC LIMIT 25
            ");
            $stmt->execute([$branch_id, $tenant_id, $tenant_id, $searchParam, $searchParam, $searchParam]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $formatted = array_map(function($p) {
                return [
                    'id' => (int)$p['id'],
                    'name' => $p['name'],
                    'sku' => $p['sku'],
                    'price' => (float)$p['price'],
                    'image' => $p['image'],
                    'active' => (int)$p['active'],
                    'category' => $p['category'],
                    'category_color' => $p['category_color'],
                    'stock' => (int)$p['stock'],
                ];
            }, $products);
            
            $response = ['success' => true, 'products' => $formatted, 'count' => count($formatted)];
            break;

        case 'get_details':
            $product_id = (int) ($_POST['product_id'] ?? 0);
            if ($product_id <= 0) throw new Exception('Invalid product ID');
            
            $stmt = $pdo->prepare("
                SELECT p.*, c.name AS category_name,
                       (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id AND tenant_id = ?) as variant_count,
                       (SELECT COALESCE(SUM(stock), 0) FROM inventory WHERE product_id = p.id AND tenant_id = ?) as total_stock
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
            ");
            $stmt->execute([$tenant_id, $tenant_id, $product_id, $tenant_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) throw new Exception('Product not found');
            
            $stmt = $pdo->prepare("SELECT branch_id, stock, reorder_level FROM inventory WHERE product_id = ? AND tenant_id = ?");
            $stmt->execute([$product_id, $tenant_id]);
            $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = ['success' => true, 'product' => $product, 'inventory' => $inventory];
            break;

        default:
            $response = ['success' => false, 'message' => 'Unknown action: ' . $action];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
