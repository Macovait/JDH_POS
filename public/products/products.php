<?php
/**
 * Products management page for Jakababa POS
 * Professional design for supermarkets, hotels, restaurants, chemists
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('products.manage') && !is_super_admin()) {
    enforce_permission('products.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

// Get current business type for multi-tenant isolation
$business_type = get_current_business_type($tenant_id);
$business_type_id = get_current_business_type_id($tenant_id);

// Check if business_type columns exist in tables
$has_business_type = false;
$has_business_type_id = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'business_type'")->fetchAll();
    $has_business_type = !empty($cols);
    $cols_id = $pdo->query("SHOW COLUMNS FROM products LIKE 'business_type_id'")->fetchAll();
    $has_business_type_id = !empty($cols_id);
} catch (Exception $e) {}

// For categories
$has_cat_business_type = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM categories LIKE 'business_type'")->fetchAll();
    $has_cat_business_type = !empty($cols);
} catch (Exception $e) {}

$user_id = get_current_user_id();
$user_name = htmlspecialchars(get_current_user_name());
$user_role = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? 'User';
$branch_id = get_current_branch_id();
$branch_name = get_current_branch_name();

$branches = [];
if (is_super_admin() || $user_role === 'Admin' || check_permission('branches.view')) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name");
        $stmt->execute([$tenant_id]);
        $branches = $stmt->fetchAll();
    } catch (PDOException $e) { error_log("Error fetching branches: " . $e->getMessage()); }
}

if (isset($_GET['branch_id']) && (is_super_admin() || $user_role === 'Admin' || check_permission('branches.view'))) {
    $new_branch_id = (int) $_GET['branch_id'];
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND active = 1 AND deleted_at IS NULL");
    $stmt->execute([$new_branch_id, $tenant_id]);
    if ($stmt->fetch()) {
        $branch_id = $new_branch_id;
        $_SESSION['user']['branch_id'] = $branch_id;
        $_SESSION['current_branch']['id'] = $branch_id;
        $_SESSION['branch_id'] = $branch_id;
        $stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$branch_id, $tenant_id]);
        $branch = $stmt->fetch();
        if ($branch) {
            $_SESSION['current_branch']['name'] = $branch['name'];
            $_SESSION['branch_name'] = $branch['name'];
            $branch_name = $branch['name'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $response['message'] = 'Invalid security token. Please refresh and try again.';
        echo json_encode($response);
        exit;
    }

    $rate_key = "product_ajax_" . get_current_user_id();
    $attempts = $_SESSION['rate_limits'][$rate_key] ?? 0;
    if ($attempts > 100) {
        $response['message'] = 'Rate limit exceeded. Please wait.';
        echo json_encode($response);
        exit;
    }
    $_SESSION['rate_limits'][$rate_key] = $attempts + 1;

    try {
        if ($_POST['ajax_action'] === 'toggle_status') {
            $product_id = (int) $_POST['product_id'];
            $current_status = (int) $_POST['current_status'];
            $new_status = $current_status ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE products SET active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$new_status, $product_id, $tenant_id]);
            $response = ['success' => true, 'message' => 'Product status updated', 'new_status' => $new_status];
        } elseif ($_POST['ajax_action'] === 'bulk_delete') {
            $ids = $_POST['ids'] ?? [];
            if (empty($ids)) throw new Exception('No products selected');
            $ids = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.product_id IN ($placeholders) AND s.tenant_id = ?");
            $stmt->execute(array_merge($ids, [$tenant_id]));
            if ($stmt->fetchColumn() > 0) throw new Exception('Cannot delete products with sales history');
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id IN ($placeholders) AND tenant_id = ?");
            $stmt->execute(array_merge($ids, [$tenant_id]));
            foreach ($stmt->fetchAll() as $img) { if ($img['image'] && file_exists(PUBLIC_PATH . $img['image'])) @unlink(PUBLIC_PATH . $img['image']); }
            $pdo->prepare("DELETE FROM inventory WHERE product_id IN ($placeholders)")->execute($ids);
            $pdo->prepare("DELETE FROM product_variants WHERE product_id IN ($placeholders)")->execute($ids);
            $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders) AND tenant_id = ?")->execute(array_merge($ids, [$tenant_id]));
            $response = ['success' => true, 'message' => count($ids) . ' products deleted successfully'];
        } elseif ($_POST['ajax_action'] === 'bulk_status') {
            $ids = $_POST['ids'] ?? [];
            $status = (int) $_POST['status'];
            if (empty($ids)) throw new Exception('No products selected');
            $ids = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE products SET active = ?, updated_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ?")->execute(array_merge([$status], $ids, [$tenant_id]));
            $response = ['success' => true, 'message' => count($ids) . ' products ' . ($status ? 'activated' : 'deactivated')];
        } elseif ($_POST['ajax_action'] === 'quick_search') {
            $search = $_POST['search'] ?? '';
            if (strlen($search) < 1) throw new Exception('Search term required');

            $bt_condition = '';
            $bt_params = [];
            if ($has_business_type) {
                $bt_condition = " AND (p.business_type = ? OR p.business_type IS NULL OR p.business_type = 'general')";
                $bt_params[] = $business_type;
            }

            $stmt = $pdo->prepare("SELECT p.id, p.name, p.sku, p.price, p.image, c.name AS category FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.tenant_id = ? AND p.active = 1 AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?) $bt_condition LIMIT 20");
            $searchParam = "%$search%";
            $stmt->execute(array_merge([$tenant_id, $searchParam, $searchParam, $searchParam], $bt_params));
            $response = ['success' => true, 'products' => $stmt->fetchAll()];
        } elseif ($_POST['ajax_action'] === 'quick_edit') {
            $product_id = (int) ($_POST['product_id'] ?? 0);
            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';
            if (!$product_id || !in_array($field, ['name','price','stock'])) throw new Exception('Invalid parameters');

            $pdo->beginTransaction();
            if ($field === 'name') {
                if (empty(trim($value))) throw new Exception('Name cannot be empty');
                $pdo->prepare("UPDATE products SET name = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?")->execute([trim($value), $product_id, $tenant_id]);
            } elseif ($field === 'price') {
                $price = floatval($value);
                if ($price < 0) throw new Exception('Price cannot be negative');
                $pdo->prepare("UPDATE products SET price = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?")->execute([$price, $product_id, $tenant_id]);
            } elseif ($field === 'stock') {
                $stock = max(0, intval($value));
                $invCheck = $pdo->prepare("SELECT 1 FROM inventory WHERE product_id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1");
                $invCheck->execute([$product_id, $tenant_id, $branch_id]);
                if ($invCheck->fetch()) {
                    $pdo->prepare("UPDATE inventory SET stock = ?, updated_at = NOW() WHERE product_id = ? AND tenant_id = ? AND branch_id = ?")->execute([$stock, $product_id, $tenant_id, $branch_id]);
                } else {
                    $pdo->prepare("INSERT INTO inventory (product_id, tenant_id, branch_id, stock, reorder_level, created_at, updated_at) VALUES (?, ?, ?, ?, 5, NOW(), NOW())")->execute([$product_id, $tenant_id, $branch_id, $stock]);
                }
            }
            $pdo->commit();
            $response = ['success' => true, 'message' => 'Updated'];
        } elseif ($_POST['ajax_action'] === 'quick_view') {
            $product_id = (int) ($_POST['product_id'] ?? 0);
            if (!$product_id) throw new Exception('Product ID required');

            try {
                $stmt = $pdo->prepare("
                    SELECT p.*, c.name AS category_name, b.name AS brand_name
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN brands b ON p.brand_id = b.id
                    WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$product_id, $tenant_id]);
            } catch (PDOException $e) {
                $stmt = $pdo->prepare("
                    SELECT p.*, c.name AS category_name, NULL AS brand_name
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$product_id, $tenant_id]);
            }
            $product = $stmt->fetch();
            if (!$product) throw new Exception('Product not found');

            $invStmt = $pdo->prepare("SELECT stock, reorder_level, minimum_stock, maximum_stock FROM inventory WHERE product_id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1");
            $invStmt->execute([$product_id, $tenant_id, $branch_id]);
            $inventory = $invStmt->fetch() ?: ['stock'=>0, 'reorder_level'=>5, 'minimum_stock'=>0, 'maximum_stock'=>0];

            $salesStmt = $pdo->prepare("SELECT COUNT(*) as sale_count, COALESCE(SUM(si.quantity),0) as total_sold FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.product_id = ? AND s.tenant_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $salesStmt->execute([$product_id, $tenant_id]);
            $sales = $salesStmt->fetch();

            $response = ['success' => true, 'product' => $product, 'inventory' => $inventory, 'sales' => $sales];
        } elseif ($_POST['ajax_action'] === 'duplicate') {
            $product_id = (int) ($_POST['product_id'] ?? 0);
            if (!$product_id) throw new Exception('Product ID required');

            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$product_id, $tenant_id]);
            $product = $stmt->fetch();
            if (!$product) throw new Exception('Product not found');

            unset($product['id'], $product['created_at'], $product['updated_at']);
            $product['name'] = $product['name'] . ' (Copy)';
            $product['sku'] = !empty($product['sku']) ? $product['sku'] . '-COPY-' . time() : null;
            $product['barcode'] = null;

            $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($product)));
            $vals = array_values($product);
            $ph = implode(', ', array_fill(0, count($vals), '?'));
            $pdo->prepare("INSERT INTO products ($cols, created_at, updated_at) VALUES ($ph, NOW(), NOW())")->execute($vals);
            $new_id = $pdo->lastInsertId();

            $brStmt = $pdo->prepare("SELECT id FROM branches WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL");
            $brStmt->execute([$tenant_id]);
            $invIns = $pdo->prepare("INSERT INTO inventory (tenant_id, product_id, branch_id, stock, reorder_level, created_at, updated_at) VALUES (?, ?, ?, 0, 5, NOW(), NOW())");
            foreach ($brStmt->fetchAll(PDO::FETCH_COLUMN) as $bid) $invIns->execute([$tenant_id, $new_id, $bid]);

            $response = ['success' => true, 'message' => 'Product duplicated', 'new_id' => $new_id];
        }
    } catch (Exception $e) { $response['message'] = $e->getMessage(); }
    echo json_encode($response);
    exit;
}

$csrf_token = generate_csrf_token();

$category_filter = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : 'all';
$search_term = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$tag_id_filter = isset($_GET['tag_id']) && is_numeric($_GET['tag_id']) ? (int)$_GET['tag_id'] : 0;
$tag_name_filter = $_GET['tag_name'] ?? '';
$sort_by = $_GET['sort'] ?? 'id_desc';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// DEBUG: Show active filters
if (isset($_GET['debug'])) {
    echo "<!-- DEBUG FILTERS: category=$category_filter, search=$search_term, status=$status_filter, sort=$sort_by, branch=$branch_id -->";
}

$sort_options = [
    'id_desc' => 'p.id DESC', 'id_asc' => 'p.id ASC',
    'name_asc' => 'p.name ASC', 'name_desc' => 'p.name DESC',
    'price_asc' => 'p.price ASC', 'price_desc' => 'p.price DESC',
    'stock_asc' => 'total_stock ASC', 'stock_desc' => 'total_stock DESC',
    'updated_desc' => 'p.updated_at DESC',
];
$order_by = $sort_options[$sort_by] ?? 'p.id DESC';

$sql = "SELECT p.id, p.name, p.sku, p.price, p.cost_price, p.active, p.image, p.created_at, p.updated_at,
        c.name AS category, c.id AS category_id,
        (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variant_count,
        COALESCE((SELECT SUM(stock) FROM inventory WHERE product_id = p.id AND branch_id = :branch_id AND tenant_id = :tenant_id_stock), 0) as total_stock
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.tenant_id = :tenant_id AND p.deleted_at IS NULL";
$params = [':branch_id' => $branch_id, ':tenant_id_stock' => $tenant_id, ':tenant_id' => $tenant_id];

// Add business type filter for multi-tenant isolation
if ($has_business_type) {
    $sql .= " AND (p.business_type = :business_type OR p.business_type IS NULL OR p.business_type = 'general')";
    $params[':business_type'] = $business_type;
}
if ($has_business_type_id) {
    $sql .= " AND (p.business_type_id = :business_type_id OR p.business_type_id IS NULL)";
    $params[':business_type_id'] = $business_type_id;
}

// Show all products - both with and without inventory

if ($category_filter !== 'all') { $sql .= " AND p.category_id = :category"; $params[':category'] = $category_filter; }
if ($tag_id_filter > 0) {
    $sql .= " AND EXISTS (SELECT 1 FROM product_tag_relations r2 WHERE r2.product_id = p.id AND r2.tag_id = :tag_id AND r2.tenant_id = :tenant_id_tag)";
    $params[':tag_id'] = $tag_id_filter;
    $params[':tenant_id_tag'] = $tenant_id;
}
if ($status_filter !== 'all') {
    if ($status_filter === 'active') { $sql .= " AND p.active = :status"; $params[':status'] = 1; }
    elseif ($status_filter === 'inactive') { $sql .= " AND p.active = :status"; $params[':status'] = 0; }
    elseif ($status_filter === 'low_stock') {
        $sql .= " AND p.active = 1 AND EXISTS (SELECT 1 FROM inventory i2 WHERE i2.product_id = p.id AND i2.branch_id = :branch_id AND i2.tenant_id = :tenant_id_low AND i2.stock > 0 AND i2.stock < i2.reorder_level)";
        $params[':tenant_id_low'] = $tenant_id;
    }
    elseif ($status_filter === 'out_of_stock') {
        $sql .= " AND p.active = 1 AND (COALESCE((SELECT SUM(stock) FROM inventory i3 WHERE i3.product_id = p.id AND i3.branch_id = :branch_id AND i3.tenant_id = :tenant_id_out), 0) = 0)";
        $params[':tenant_id_out'] = $tenant_id;
    }
}
if (!empty($search_term)) { $sql .= " AND (p.name LIKE :search OR p.sku LIKE :search OR p.barcode LIKE :search)"; $params[':search'] = "%$search_term%"; }
$sql .= " ORDER BY $order_by";
$sql .= " LIMIT $per_page OFFSET $offset";

// Get total filtered count for pagination
$count_sql = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.tenant_id = :tenant_id AND p.deleted_at IS NULL";
$count_params = [':tenant_id' => $tenant_id];
if ($has_business_type) {
    $count_sql .= " AND (p.business_type = :business_type OR p.business_type IS NULL OR p.business_type = 'general')";
    $count_params[':business_type'] = $business_type;
}
if ($has_business_type_id) {
    $count_sql .= " AND (p.business_type_id = :business_type_id OR p.business_type_id IS NULL)";
    $count_params[':business_type_id'] = $business_type_id;
}
if ($category_filter !== 'all') { $count_sql .= " AND p.category_id = :category"; $count_params[':category'] = $category_filter; }
if ($tag_id_filter > 0) {
    $count_sql .= " AND EXISTS (SELECT 1 FROM product_tag_relations r2 WHERE r2.product_id = p.id AND r2.tag_id = :tag_id AND r2.tenant_id = :tenant_id_tag)";
    $count_params[':tag_id'] = $tag_id_filter;
    $count_params[':tenant_id_tag'] = $tenant_id;
}
if ($status_filter !== 'all') {
    if ($status_filter === 'active') { $count_sql .= " AND p.active = :status"; $count_params[':status'] = 1; }
    elseif ($status_filter === 'inactive') { $count_sql .= " AND p.active = :status"; $count_params[':status'] = 0; }
    elseif ($status_filter === 'low_stock') {
        $count_sql .= " AND p.active = 1 AND EXISTS (SELECT 1 FROM inventory i2 WHERE i2.product_id = p.id AND i2.branch_id = :branch_id AND i2.tenant_id = :tenant_id_low AND i2.stock > 0 AND i2.stock < i2.reorder_level)";
        $count_params[':branch_id'] = $branch_id;
        $count_params[':tenant_id_low'] = $tenant_id;
    }
    elseif ($status_filter === 'out_of_stock') {
        $count_sql .= " AND p.active = 1 AND (COALESCE((SELECT SUM(stock) FROM inventory i3 WHERE i3.product_id = p.id AND i3.branch_id = :branch_id AND i3.tenant_id = :tenant_id_out), 0) = 0)";
        $count_params[':branch_id'] = $branch_id;
        $count_params[':tenant_id_out'] = $tenant_id;
    }
}
if (!empty($search_term)) { $count_sql .= " AND (p.name LIKE :search OR p.sku LIKE :search OR p.barcode LIKE :search)"; $count_params[':search'] = "%$search_term%"; }
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_filtered = (int) $count_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_filtered / $per_page));

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = [];
try {
    $cat_sql = "SELECT DISTINCT c.id, c.name FROM categories c JOIN products p ON c.id = p.category_id WHERE p.tenant_id = ? AND c.tenant_id = ? AND c.status = 'active' AND (c.deleted_at IS NULL)";
    $cat_params = [$tenant_id, $tenant_id];
    
    // Filter categories by business type
    if ($has_cat_business_type) {
        $cat_sql .= " AND (p.business_type = ? OR p.business_type IS NULL OR p.business_type = 'general')";
        $cat_params[] = $business_type;
    }
    $cat_sql .= " ORDER BY c.name";
    
    $stmt = $pdo->prepare($cat_sql);
    $stmt->execute($cat_params);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) { error_log("Error fetching categories: " . $e->getMessage()); }

$low_stock_count = 0; $low_stock_products = [];
if (check_permission('inventory.manage') || is_super_admin()) {
    $inv_sql = "SELECT p.name, i.stock, i.reorder_level FROM inventory i JOIN products p ON i.product_id = p.id WHERE i.branch_id = ? AND p.tenant_id = ? AND i.stock < i.reorder_level AND i.stock > 0";
    $inv_params = [$branch_id, $tenant_id];
    
    // Filter by business type
    if ($has_business_type) {
        $inv_sql .= " AND (p.business_type = ? OR p.business_type IS NULL OR p.business_type = 'general')";
        $inv_params[] = $business_type;
    }
    $inv_sql .= " ORDER BY (i.reorder_level - i.stock) DESC LIMIT 5";
    
    $stmt = $pdo->prepare($inv_sql);
    $stmt->execute($inv_params);
    $low_stock_products = $stmt->fetchAll();
    $low_stock_count = count($low_stock_products);
}

$total_products_in_branch = count($products);
$total_global_sql = "SELECT COUNT(*) FROM products WHERE tenant_id = ? AND deleted_at IS NULL";
$total_global_params = [$tenant_id];
if ($has_business_type) {
    $total_global_sql .= " AND (business_type = ? OR business_type IS NULL OR business_type = 'general')";
    $total_global_params[] = $business_type;
}
$total_products_global = $pdo->prepare($total_global_sql);
$total_products_global->execute($total_global_params);
$total_products_global = (int) $total_products_global->fetchColumn();
$products_not_in_branch = max(0, $total_products_global - $total_products_in_branch);
$active_products = count(array_filter($products, fn($p) => $p['active'] == 1));
$inactive_products = $total_products_in_branch - $active_products;
$products_with_variants = count(array_filter($products, fn($p) => $p['variant_count'] > 0));
$total_stock = array_sum(array_column($products, 'total_stock'));
$total_value = array_sum(array_map(fn($p) => $p['price'] * $p['total_stock'], $products));
$out_of_stock = count(array_filter($products, fn($p) => $p['total_stock'] == 0));

function displayProductImage($image_path, $product_name) {
    if ($image_path) {
        $rel = ltrim($image_path, '/');
        if (strpos($rel, 'public/') === 0) {
            $rel = substr($rel, 7);
        }
        $full_path = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (file_exists($full_path)) {
            $mtime = @filemtime($full_path) ?: time();
            return '<img src="' . htmlspecialchars(base_url($rel) . '?v=' . $mtime, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8') . '" class="product-thumb w-11 h-11 rounded-lg object-cover">';
        }
    }
    return '<div class="w-11 h-11 rounded-lg bg-slate-700/60 flex items-center justify-center text-slate-500"><i class="fas fa-cube text-xs"></i></div>';
}

$currency = function_exists('get_settings') ? get_settings('currency', 'KES', $tenant_id) : 'KES';
$page_title = 'Products';
ob_start();
?>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/60  hidden items-center justify-center z-50 modal">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 rounded-lg bg-red-500/15 border border-red-500/30 flex items-center justify-center shrink-0">
                <i class="fas fa-trash text-red-400 text-sm"></i>
            </div>
            <h3 class="text-sm font-semibold text-white">Delete Product</h3>
        </div>
        <p class="text-xs text-slate-400 mb-1">Are you sure you want to delete</p>
        <p id="deleteProductName" class="text-sm font-semibold text-amber-400 mb-3"></p>
        <p class="text-xs text-slate-500 mb-4">This cannot be undone. Products with sales history cannot be deleted.</p>
        <div class="flex gap-2">
            <button onclick="confirmDelete()"
                    class="flex-1 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-colors">
                <i class="fas fa-trash mr-1"></i>Delete
            </button>
            <button onclick="closeModal('deleteModal')"
                    class="flex-1 px-3 py-1.5 rounded-lg bg-slate-700/60 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-700 transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Bulk Delete Modal -->
<div id="bulkDeleteModal" class="fixed inset-0 bg-black/60  hidden items-center justify-center z-50 modal">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 rounded-lg bg-red-500/15 border border-red-500/30 flex items-center justify-center shrink-0">
                <i class="fas fa-trash text-red-400 text-sm"></i>
            </div>
            <h3 class="text-sm font-semibold text-white">Delete Selected Products</h3>
        </div>
        <p class="text-xs text-slate-400 mb-3">Delete <span id="bulkDeleteCount" class="text-amber-400 font-bold">0</span> selected products? This cannot be undone.</p>
        <p class="text-xs text-slate-500 mb-4">Products with sales history cannot be deleted.</p>
        <div class="flex gap-2">
            <button onclick="confirmBulkDelete()"
                    class="flex-1 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-colors">
                <i class="fas fa-trash mr-1"></i>Delete All
            </button>
            <button onclick="closeModal('bulkDeleteModal')"
                    class="flex-1 px-3 py-1.5 rounded-lg bg-slate-700/60 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-700 transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Low Stock Modal -->
<div id="lowStockModal" class="fixed inset-0 bg-black/60  hidden items-center justify-center z-50 modal">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 rounded-lg bg-amber-500/15 border border-amber-500/30 flex items-center justify-center shrink-0">
                <i class="fas fa-triangle-exclamation text-amber-400 text-sm"></i>
            </div>
            <h3 class="text-sm font-semibold text-white">Low Stock Alert</h3>
        </div>
        <div class="space-y-2 mb-4">
            <?php foreach ($low_stock_products as $item): ?>
            <div class="flex justify-between items-center px-3 py-2 bg-slate-900/60 rounded-lg border border-slate-700/40">
                <span class="text-sm text-slate-300 truncate max-w-[160px]"><?php echo htmlspecialchars($item['name']); ?></span>
                <div class="flex items-center gap-1.5 shrink-0 ml-2">
                    <span class="text-sm font-bold text-red-400"><?php echo $item['stock']; ?></span>
                    <span class="text-slate-600">/</span>
                    <span class="text-sm text-slate-400"><?php echo $item['reorder_level']; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button onclick="closeModal('lowStockModal')"
                class="w-full px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            Close
        </button>
    </div>
</div>

<!-- Quick View Modal -->
<div id="quickViewModal" class="fixed inset-0 bg-black/60  hidden items-center justify-center z-50 modal">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-md w-full mx-4 shadow-xl max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Product Details</h3>
            <button onclick="closeModal('quickViewModal')" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700 text-slate-400 hover:text-white transition-colors"><i class="fas fa-times text-xs"></i></button>
        </div>
        <div id="quickViewContent" class="space-y-4">
            <div class="flex items-center gap-3">
                <div id="qvImage"></div>
                <div>
                    <div id="qvName" class="text-base font-bold text-white"></div>
                    <div id="qvSku" class="text-xs text-slate-400 font-mono mt-0.5"></div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div class="bg-slate-900/60 rounded-lg p-2 border border-slate-700/40"><div class="text-xs text-slate-500">Category</div><div id="qvCategory" class="text-slate-300 font-medium"></div></div>
                <div class="bg-slate-900/60 rounded-lg p-2 border border-slate-700/40"><div class="text-xs text-slate-500">Brand</div><div id="qvBrand" class="text-slate-300 font-medium"></div></div>
                <div class="bg-slate-900/60 rounded-lg p-2 border border-slate-700/40"><div class="text-xs text-slate-500">Price</div><div id="qvPrice" class="text-amber-400 font-bold"></div></div>
                <div class="bg-slate-900/60 rounded-lg p-2 border border-slate-700/40"><div class="text-xs text-slate-500">Cost</div><div id="qvCost" class="text-slate-300 font-medium"></div></div>
                <div class="bg-slate-900/60 rounded-lg p-2 border border-slate-700/40"><div class="text-xs text-slate-500">Tax</div><div id="qvTax" class="text-slate-300 font-medium"></div></div>
                <div class="bg-slate-900/60 rounded-lg p-2 border border-slate-700/40"><div class="text-xs text-slate-500">Unit</div><div id="qvUnit" class="text-slate-300 font-medium"></div></div>
            </div>
            <div class="bg-slate-900/60 rounded-lg p-3 border border-slate-700/40">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-slate-500">Stock</span>
                    <span id="qvStock" class="text-sm font-bold"></span>
                </div>
                <div class="w-full bg-slate-700 rounded-full h-2"><div id="qvStockBar" class="bg-emerald-500 h-2 rounded-full transition-all" style="width:0%"></div></div>
                <div class="flex justify-between text-xs text-slate-500 mt-1"><span>Reorder: <span id="qvReorder"></span></span><span>Max: <span id="qvMax"></span></span></div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div class="bg-slate-900/60 rounded-lg p-2 border border-slate-700/40"><div class="text-xs text-slate-500">30-Day Sales</div><div id="qvSales" class="text-emerald-400 font-bold"></div></div>
                <div class="bg-slate-900/60 rounded-lg p-2 border border-slate-700/40"><div class="text-xs text-slate-500">Total Sold</div><div id="qvTotalSold" class="text-slate-300 font-medium"></div></div>
            </div>
            <div id="qvDescription" class="text-xs text-slate-400 bg-slate-900/40 rounded-lg p-2 border border-slate-700/30 hidden"></div>
        </div>
        <div class="flex gap-2 mt-4 pt-3 border-t border-slate-700/60">
            <a id="qvEditLink" href="#" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-xs font-medium hover:bg-amber-500/25 transition-colors"><i class="fas fa-pen text-xs"></i> Edit</a>
            <button onclick="closeModal('quickViewModal')" class="flex-1 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">Close</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 space-y-2 z-50"></div>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-box text-amber-400"></i> Products
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo number_format($total_filtered); ?> product<?php echo $total_filtered !== 1 ? 's' : ''; ?> &middot;
            <span class="text-amber-400"><?php echo htmlspecialchars($branch_name); ?></span>
            <?php if ($products_not_in_branch > 0): ?>
            &middot; <span class="text-slate-600"><?php echo $products_not_in_branch; ?> not in this branch</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2 shrink-0">
        <?php if (!empty($branches)): ?>
        <form method="GET" class="flex items-center">
            <select name="branch_id" onchange="this.form.submit()"
                    class="px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
                <?php foreach ($branches as $b): ?>
                <option value="<?php echo $b['id']; ?>" <?php echo $branch_id == $b['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($b['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <?php if ($low_stock_count > 0): ?>
        <button onclick="openModal('lowStockModal')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-triangle-exclamation text-xs"></i> <?php echo $low_stock_count; ?> Low Stock
        </button>
        <?php endif; ?>
        <a href="barcode_labels.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-barcode text-xs"></i> Labels
        </a>
        <a href="brands.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-trademark text-xs"></i> Brands
        </a>
        <a href="import_products.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-upload text-xs"></i> Import
        </a>
        <button onclick="exportProducts()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-download text-xs"></i> Export
        </button>
        <a href="product_form.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-xs font-semibold hover:bg-amber-600 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add Product
        </a>
    </div>
</div>

<!-- Flash messages -->
<?php if (!empty($_SESSION['success_message'])): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm" id="flashSuccess">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
</div>
<?php endif; ?>
<?php if (!empty($_SESSION['error_message'])): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mb-5">
    <?php
    $cards = [
        ['label'=>'Total',        'value'=>number_format($total_products_global), 'icon'=>'fa-box',           'color'=>'text-slate-300',  'bg'=>'bg-slate-500/10'],
        ['label'=>'Active',       'value'=>$active_products,                      'icon'=>'fa-check-circle',  'color'=>'text-emerald-400','bg'=>'bg-emerald-500/10'],
        ['label'=>'Inactive',     'value'=>$inactive_products,                    'icon'=>'fa-ban',           'color'=>'text-slate-400',  'bg'=>'bg-slate-500/10'],
        ['label'=>'In Stock',     'value'=>number_format($total_stock),           'icon'=>'fa-cubes',         'color'=>'text-amber-400',  'bg'=>'bg-amber-500/10'],
        ['label'=>'Out of Stock', 'value'=>$out_of_stock,                         'icon'=>'fa-box-open',      'color'=>'text-red-400',    'bg'=>'bg-red-500/10'],
        ['label'=>'With Variants','value'=>$products_with_variants,               'icon'=>'fa-code-branch',   'color'=>'text-purple-400', 'bg'=>'bg-purple-500/10'],
    ];
    foreach ($cards as $card):
    ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?>"><?php echo $card['value']; ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tag filter badge -->
<?php if ($tag_id_filter > 0): ?>
<div class="mb-3">
    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm">
        <i class="fas fa-tag text-xs"></i>
        Tag: <strong><?php echo htmlspecialchars($tag_name_filter ?: 'Tag #' . $tag_id_filter); ?></strong>
        <a href="?<?php echo http_build_query(array_diff_key($_GET, array_flip(['tag_id','tag_name']))); ?>"
           class="ml-1 text-amber-300 hover:text-white transition-colors">
            <i class="fas fa-times text-xs"></i>
        </a>
    </span>
</div>
<?php endif; ?>

<!-- Bulk actions bar -->
<div id="bulkActionsBar" class="hidden mb-4 px-3 py-2.5 bg-slate-800/60 border border-slate-700/60 rounded-xl">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div class="flex items-center gap-3">
            <span class="text-sm text-slate-400"><span id="selectedCount" class="text-white font-semibold">0</span> selected</span>
            <button onclick="selectAll()" class="text-xs text-amber-400 hover:text-amber-300 transition-colors">Select All</button>
            <button onclick="clearSelection()" class="text-xs text-slate-500 hover:text-slate-300 transition-colors">Clear</button>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="bulkStatusUpdate(1)"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium hover:bg-emerald-500/20 transition-colors">
                <i class="fas fa-check text-xs"></i> Activate
            </button>
            <button onclick="bulkStatusUpdate(0)"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-ban text-xs"></i> Deactivate
            </button>
            <button onclick="exportSelected()"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-download text-xs"></i> Export Selected
            </button>
            <?php if (is_super_admin() || $user_role === 'Admin'): ?>
            <button onclick="openBulkDeleteModal()"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-colors">
                <i class="fas fa-trash text-xs"></i> Delete
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
    <form method="GET" class="flex flex-wrap gap-2 items-end">
        <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
        <?php if ($tag_id_filter > 0): ?><input type="hidden" name="tag_id" value="<?php echo $tag_id_filter; ?>"><?php endif; ?>

        <!-- Status pills -->
        <div class="w-full flex flex-wrap gap-1.5 pb-2 border-b border-slate-700/60 mb-1">
            <?php
            $pills = [
                'all'          => ['All',          $total_products_global, 'bg-amber-500/20 text-amber-400 border-amber-500/30',       'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
                'active'       => ['Active',        $active_products,       'bg-emerald-500/20 text-emerald-400 border-emerald-500/30', 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
                'inactive'     => ['Inactive',      $inactive_products,     'bg-slate-500/20 text-slate-300 border-slate-500/30',       'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
                'low_stock'    => ['Low Stock',     $low_stock_count,       'bg-amber-500/20 text-amber-400 border-amber-500/30',       'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
                'out_of_stock' => ['Out of Stock',  $out_of_stock,          'bg-red-500/20 text-red-400 border-red-500/30',             'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            ];
            $active_tab = $status_filter ?: 'all';
            foreach ($pills as $val => $pill):
                $href = $val === 'all'
                    ? '?' . http_build_query(array_filter(array_diff_key($_GET, array_flip(['status','page'])), fn($v) => $v !== ''))
                    : '?' . http_build_query(array_merge(array_diff_key($_GET, array_flip(['status','page'])), ['status'=>$val]));
            ?>
            <a href="<?php echo htmlspecialchars($href); ?>"
               class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium border transition-colors <?php echo $active_tab === $val ? $pill[2] : $pill[3]; ?>">
                <?php echo $pill[0]; ?> <span class="opacity-70">(<?php echo (int)$pill[1]; ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Search + selects -->
        <div class="relative flex-1 min-w-[180px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search_term); ?>"
                   placeholder="Search name, SKU, barcode…"
                   class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>

        <select name="category" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="all">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="sort" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="id_desc"      <?php echo $sort_by === 'id_desc'      ? 'selected' : ''; ?>>Newest First</option>
            <option value="id_asc"       <?php echo $sort_by === 'id_asc'       ? 'selected' : ''; ?>>Oldest First</option>
            <option value="name_asc"     <?php echo $sort_by === 'name_asc'     ? 'selected' : ''; ?>>Name A–Z</option>
            <option value="name_desc"    <?php echo $sort_by === 'name_desc'    ? 'selected' : ''; ?>>Name Z–A</option>
            <option value="price_asc"    <?php echo $sort_by === 'price_asc'    ? 'selected' : ''; ?>>Price Low–High</option>
            <option value="price_desc"   <?php echo $sort_by === 'price_desc'   ? 'selected' : ''; ?>>Price High–Low</option>
            <option value="stock_asc"    <?php echo $sort_by === 'stock_asc'    ? 'selected' : ''; ?>>Stock Low–High</option>
            <option value="stock_desc"   <?php echo $sort_by === 'stock_desc'   ? 'selected' : ''; ?>>Stock High–Low</option>
            <option value="updated_desc" <?php echo $sort_by === 'updated_desc' ? 'selected' : ''; ?>>Recently Updated</option>
        </select>

        <button type="submit"
                class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-filter mr-1 text-xs"></i>Filter
        </button>
        <?php if (!empty($search_term) || $category_filter !== 'all' || $status_filter !== 'all' || $sort_by !== 'id_desc' || $tag_id_filter > 0): ?>
        <a href="products.php?branch_id=<?php echo $branch_id; ?>"
           class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times mr-1 text-xs"></i>Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Products table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[780px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 w-8">
                        <input type="checkbox" id="selectAllCheckbox" class="w-3.5 h-3.5 rounded accent-amber-500 cursor-pointer" onchange="toggleSelectAll(this)">
                    </th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">SKU</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Price</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Cost</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Stock</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="9" class="px-4 py-14 text-center">
                        <i class="fas fa-box-open text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm mb-3">
                            <?php if (!empty($search_term) || $category_filter !== 'all' || $status_filter !== 'all' || $tag_id_filter > 0): ?>
                                No products match your filters
                            <?php else: ?>
                                No products yet — get started by adding your first product
                            <?php endif; ?>
                        </p>
                        <a href="product_form.php"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
                            <i class="fas fa-plus text-xs"></i> Add Product
                        </a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $product):
                    $stock      = (int)$product['total_stock'];
                    $stock_color = $stock === 0 ? 'text-red-400' : ($stock < 5 ? 'text-amber-400' : 'text-emerald-400');
                    $stock_icon  = $stock === 0 ? 'fa-circle-xmark' : ($stock < 5 ? 'fa-triangle-exclamation' : 'fa-check-circle');
                    $margin      = $product['price'] > 0 ? round((($product['price'] - $product['cost_price']) / $product['price']) * 100, 1) : 0;
                ?>
                <tr id="product-row-<?php echo $product['id']; ?>" class="hover:bg-slate-700/30 transition-colors group">
                    <td class="px-3 py-2.5">
                        <input type="checkbox" class="product-checkbox w-3.5 h-3.5 rounded accent-amber-500 cursor-pointer" value="<?php echo $product['id']; ?>" onchange="updateSelection()">
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-2.5">
                            <?php echo displayProductImage($product['image'], $product['name']); ?>
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-white truncate max-w-[180px] editable-name cursor-pointer hover:text-amber-400 transition-colors" title="Double-click to edit"
                                     data-id="<?php echo $product['id']; ?>" data-field="name" data-value="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                     ondblclick="inlineEdit(this)">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </div>
                                <?php if ($product['variant_count'] > 0): ?>
                                <span class="text-xs text-purple-400">
                                    <i class="fas fa-code-branch mr-0.5 text-xs"></i><?php echo $product['variant_count']; ?> variant<?php echo $product['variant_count'] > 1 ? 's' : ''; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="font-mono text-xs text-slate-400"><?php echo htmlspecialchars($product['sku'] ?: '—'); ?></span>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if ($product['category']): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-700/60 text-slate-300">
                            <?php echo htmlspecialchars($product['category']); ?>
                        </span>
                        <?php else: ?>
                        <span class="text-slate-600 text-sm">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5 text-right">
                        <span class="text-sm font-semibold text-amber-400 editable-price cursor-pointer hover:underline" title="Double-click to edit"
                              data-id="<?php echo $product['id']; ?>" data-field="price" data-value="<?php echo $product['price']; ?>"
                              ondblclick="inlineEdit(this)">
                            <?php echo format_currency((float)$product['price']); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right">
                        <span class="text-sm text-slate-400"><?php echo format_currency((float)$product['cost_price']); ?></span>
                        <?php if ($margin > 0): ?>
                        <span class="text-xs text-emerald-500 ml-1"><?php echo $margin; ?>%</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5 text-right">
                        <div class="flex flex-col items-end gap-1">
                            <span class="<?php echo $stock_color; ?> flex items-center gap-1 text-sm font-medium editable-stock cursor-pointer" title="Double-click to edit"
                                  data-id="<?php echo $product['id']; ?>" data-field="stock" data-value="<?php echo $stock; ?>"
                                  ondblclick="inlineEdit(this)">
                                <i class="fas <?php echo $stock_icon; ?> text-xs"></i><?php echo $stock; ?>
                            </span>
                            <?php
                            $max_stock_display = max($stock, 20);
                            $pct = min(100, max(0, $stock > 0 ? ($stock / $max_stock_display) * 100 : 0));
                            $barColor = $stock === 0 ? 'bg-red-500' : ($stock < 5 ? 'bg-amber-500' : 'bg-emerald-500');
                            ?>
                            <div class="w-16 bg-slate-700 rounded-full h-1.5">
                                <div class="<?php echo $barColor; ?> h-1.5 rounded-full transition-all" style="width:<?php echo $pct; ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2.5">
                        <button onclick="toggleStatus(<?php echo $product['id']; ?>, <?php echo $product['active']; ?>)"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-colors
                                    <?php echo $product['active']
                                        ? 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30 hover:bg-emerald-500/25'
                                        : 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30 hover:bg-slate-500/25'; ?>">
                            <i class="fas <?php echo $product['active'] ? 'fa-check' : 'fa-ban'; ?> text-[9px]"></i>
                            <?php echo $product['active'] ? 'Active' : 'Inactive'; ?>
                        </button>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-center gap-1.5">
                            <button onclick="openQuickView(<?php echo $product['id']; ?>)"
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-slate-600 hover:text-white transition-colors" title="Quick View">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                            <a href="product_edit.php?id=<?php echo $product['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-amber-400 hover:bg-amber-500/20 transition-colors" title="Edit">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <?php if ($product['variant_count'] > 0): ?>
                            <a href="variants.php?product_id=<?php echo $product['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-purple-400 hover:bg-purple-500/20 transition-colors" title="Variants">
                                <i class="fas fa-code-branch text-xs"></i>
                            </a>
                            <?php endif; ?>
                            <button onclick="duplicateProduct(<?php echo $product['id']; ?>)"
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-amber-500/20 hover:text-amber-400 transition-colors" title="Duplicate">
                                <i class="fas fa-clone text-xs"></i>
                            </button>
                            <?php if (is_super_admin() || $user_role === 'Admin'): ?>
                            <button onclick="openDeleteModal(<?php echo $product['id']; ?>, <?php echo htmlspecialchars(json_encode($product['name']), ENT_QUOTES, 'UTF-8'); ?>)"
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-500 hover:bg-red-500/20 hover:text-red-400 transition-colors" title="Delete">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Table footer + pagination -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
        <p class="text-xs text-slate-500">
            <?php if ($total_filtered > 0): ?>
            Showing <span class="text-slate-300 font-medium"><?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $total_filtered); ?></span>
            of <span class="text-slate-300 font-medium"><?php echo number_format($total_filtered); ?></span> &middot;
            Stock: <span class="text-amber-400 font-medium"><?php echo number_format($total_stock); ?></span> &middot;
            Value: <span class="text-purple-400 font-medium"><?php echo format_currency($total_value); ?></span>
            <?php else: ?>
            No products found
            <?php endif; ?>
        </p>
        <?php if ($total_pages > 1):
            $pb = array_diff_key($_GET, array_flip(['page']));
        ?>
        <div class="flex items-center gap-1">
            <a href="?<?php echo http_build_query(array_merge($pb, ['page'=>1])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page <= 1 ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-angle-double-left text-xs"></i>
            </a>
            <a href="?<?php echo http_build_query(array_merge($pb, ['page'=>max(1,$page-1)])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page <= 1 ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-chevron-left text-xs"></i>
            </a>
            <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($pb, ['page'=>$i])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border font-medium transition-colors <?php echo $i === $page ? 'bg-amber-500/20 border-amber-500/40 text-amber-400' : 'bg-slate-800 border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <a href="?<?php echo http_build_query(array_merge($pb, ['page'=>min($total_pages,$page+1)])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page >= $total_pages ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-chevron-right text-xs"></i>
            </a>
            <a href="?<?php echo http_build_query(array_merge($pb, ['page'=>$total_pages])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page >= $total_pages ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-angle-double-right text-xs"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const CSRF_TOKEN = <?php echo json_encode($csrf_token); ?>;
    let selectedIds = new Set();

    function updateSelection() {
        const checkboxes = document.querySelectorAll('.product-checkbox:checked');
        selectedIds.clear();
        checkboxes.forEach(cb => selectedIds.add(cb.value));
        document.getElementById('selectedCount').textContent = selectedIds.size;
        const bar = document.getElementById('bulkActionsBar');
        bar.classList.toggle('hidden', selectedIds.size === 0);
        if (selectedIds.size > 0) bar.classList.add('');
    }

    function toggleSelectAll(checkbox) {
        document.querySelectorAll('.product-checkbox').forEach(cb => {
            cb.checked = checkbox.checked;
        });
        updateSelection();
    }

    function selectAll() {
        document.querySelectorAll('.product-checkbox').forEach(cb => { cb.checked = true; });
        document.getElementById('selectAllCheckbox').checked = true;
        updateSelection();
    }

    function clearSelection() {
        document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAllCheckbox').checked = false;
        selectedIds.clear();
        updateSelection();
    }

    function toggleStatus(productId, currentStatus) {
        const formData = new FormData();
        formData.append('ajax_action', 'toggle_status');
        formData.append('product_id', productId);
        formData.append('current_status', currentStatus);
        formData.append('csrf_token', CSRF_TOKEN);
        fetch('products.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.message || 'Error updating status', 'error');
                }
            })
            .catch(() => showToast('Network error', 'error'));
    }

    // Modal helpers
    function openModal(id) {
        const m = document.getElementById(id);
        m.classList.remove('hidden');
        m.classList.add('flex');
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    let currentDeleteId = null;
    function openDeleteModal(id, name) {
        currentDeleteId = id;
        document.getElementById('deleteProductName').textContent = name;
        openModal('deleteModal');
    }
    function confirmDelete() {
        if (!currentDeleteId) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'product_delete.php';
        const idInput = document.createElement('input');
        idInput.type = 'hidden'; idInput.name = 'id'; idInput.value = currentDeleteId;
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden'; csrfInput.name = 'csrf_token'; csrfInput.value = CSRF_TOKEN;
        form.appendChild(idInput); form.appendChild(csrfInput);
        document.body.appendChild(form); form.submit();
    }

    function openBulkDeleteModal() {
        if (selectedIds.size === 0) { showToast('No products selected', 'warning'); return; }
        document.getElementById('bulkDeleteCount').textContent = selectedIds.size;
        openModal('bulkDeleteModal');
    }
    function confirmBulkDelete() {
        if (selectedIds.size === 0) return;
        const formData = new FormData();
        formData.append('ajax_action', 'bulk_delete');
        formData.append('csrf_token', CSRF_TOKEN);
        Array.from(selectedIds).forEach(id => formData.append('ids[]', id));
        fetch('products.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                closeModal('bulkDeleteModal');
                if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 800); }
                else showToast(data.message || 'Error deleting products', 'error');
                clearSelection();
            })
            .catch(() => { closeModal('bulkDeleteModal'); showToast('Network error', 'error'); });
    }

    function bulkStatusUpdate(status) {
        if (selectedIds.size === 0) { showToast('No products selected', 'warning'); return; }
        const formData = new FormData();
        formData.append('ajax_action', 'bulk_status');
        formData.append('status', status);
        formData.append('csrf_token', CSRF_TOKEN);
        Array.from(selectedIds).forEach(id => formData.append('ids[]', id));
        fetch('products.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 800); }
                else showToast(data.message || 'Error updating', 'error');
                clearSelection();
            })
            .catch(() => showToast('Network error', 'error'));
    }

    function exportProducts() { window.location.href = 'export_products.php?format=csv&branch_id=<?php echo $branch_id; ?>'; }

    document.addEventListener('error', function(e) {
        if (e.target.tagName === 'IMG' && e.target.classList.contains('product-thumb') && !e.target.dataset.fallback) {
            e.target.dataset.fallback = '1';
            const div = document.createElement('div');
            div.className = 'w-11 h-11 rounded-lg bg-slate-700/60 flex items-center justify-center text-slate-500';
            div.innerHTML = '<i class="fas fa-cube text-xs"></i>';
            e.target.replaceWith(div);
        }
    }, true);

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    // Inline editing
    function inlineEdit(el) {
        if (el.querySelector('input')) return;
        const id = el.dataset.id;
        const field = el.dataset.field;
        const current = el.dataset.value;
        const input = document.createElement('input');
        input.type = field === 'price' ? 'number' : (field === 'stock' ? 'number' : 'text');
        input.step = field === 'price' ? '0.01' : '1';
        input.value = current;
        input.className = 'bg-slate-900 border border-amber-500 rounded px-1.5 py-0.5 text-sm text-white focus:outline-none w-full';
        if (field === 'name') input.className += ' max-w-[160px]';
        if (field === 'price' || field === 'stock') input.className += ' w-20 text-right';
        el.innerHTML = '';
        el.appendChild(input);
        input.focus();
        input.select();

        function save() {
            const newVal = input.value.trim();
            if (newVal === current) { el.innerHTML = formatDisplay(field, current); return; }
            const formData = new FormData();
            formData.append('ajax_action', 'quick_edit');
            formData.append('product_id', id);
            formData.append('field', field);
            formData.append('value', newVal);
            formData.append('csrf_token', CSRF_TOKEN);
            fetch('products.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('Updated', 'success');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        showToast(data.message || 'Error', 'error');
                        el.innerHTML = formatDisplay(field, current);
                    }
                })
                .catch(() => { showToast('Network error', 'error'); el.innerHTML = formatDisplay(field, current); });
        }
        function cancel() { el.innerHTML = formatDisplay(field, current); }

        input.addEventListener('blur', save);
        input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); save(); } if (e.key === 'Escape') { e.preventDefault(); cancel(); } });
    }

    const currencySymbol = '<?php echo addslashes($currency); ?>';
    function formatDisplay(field, value) {
        if (field === 'price') return '<span class="text-sm font-semibold text-amber-400">' + currencySymbol + ' ' + parseFloat(value).toFixed(2) + '</span>';
        if (field === 'stock') return '<i class="fas fa-cubes text-xs"></i> ' + value;
        return value;
    }

    // Quick View
    function openQuickView(productId) {
        const formData = new FormData();
        formData.append('ajax_action', 'quick_view');
        formData.append('product_id', productId);
        formData.append('csrf_token', CSRF_TOKEN);
        fetch('products.php', { method: 'POST', body: formData })
            .then(r => r.text())
            .then(text => {
                let data;
                try { data = JSON.parse(text.replace(/^\uFEFF/, '')); }
                catch(e) { console.error('Quick view non-JSON response:', text.substring(0, 500)); showToast('Server error — check console', 'error'); return; }
                if (!data.success) { showToast(data.message || 'Error loading product', 'error'); return; }
                const p = data.product;
                const inv = data.inventory;
                const sales = data.sales;
                document.getElementById('qvName').textContent = p.name;
                document.getElementById('qvSku').textContent = p.sku || 'No SKU';
                document.getElementById('qvCategory').textContent = p.category_name || '—';
                document.getElementById('qvBrand').textContent = p.brand_name || '—';
                document.getElementById('qvPrice').textContent = currencySymbol + ' ' + parseFloat(p.price || 0).toFixed(2);
                document.getElementById('qvCost').textContent = currencySymbol + ' ' + parseFloat(p.cost_price || 0).toFixed(2);
                document.getElementById('qvTax').textContent = (p.tax_rate || 0) + '%';
                document.getElementById('qvUnit').textContent = p.unit || 'pcs';
                document.getElementById('qvStock').textContent = inv.stock;
                document.getElementById('qvReorder').textContent = inv.reorder_level;
                document.getElementById('qvMax').textContent = inv.maximum_stock || '—';
                document.getElementById('qvSales').textContent = (sales.sale_count || 0) + ' orders';
                document.getElementById('qvTotalSold').textContent = (sales.total_sold || 0) + ' units';
                document.getElementById('qvEditLink').href = 'product_edit.php?id=' + p.id;
                const desc = document.getElementById('qvDescription');
                if (p.description) { desc.textContent = p.description; desc.classList.remove('hidden'); } else { desc.classList.add('hidden'); }
                const imgDiv = document.getElementById('qvImage');
                if (p.image) {
                    const rel = p.image.replace(/^\//, '').replace(/^public\//, '');
                    imgDiv.innerHTML = '<img src="<?php echo rtrim(base_url(''), '/'); ?>/' + rel + '" class="w-14 h-14 rounded-xl object-cover border border-slate-700">';
                } else {
                    imgDiv.innerHTML = '<div class="w-14 h-14 rounded-xl bg-slate-700 flex items-center justify-center text-slate-500"><i class="fas fa-cube text-lg"></i></div>';
                }
                const bar = document.getElementById('qvStockBar');
                const maxRef = Math.max(inv.stock, inv.reorder_level * 2, 20);
                const pct = Math.min(100, Math.max(0, (inv.stock / maxRef) * 100));
                bar.style.width = pct + '%';
                bar.className = 'h-2 rounded-full transition-all ' + (inv.stock === 0 ? 'bg-red-500' : (inv.stock < inv.reorder_level ? 'bg-amber-500' : 'bg-emerald-500'));
                openModal('quickViewModal');
            })
            .catch(err => { console.error('Quick view fetch error:', err); showToast('Network error', 'error'); });
    }

    // Duplicate product
    function duplicateProduct(productId) {
        if (!confirm('Duplicate this product?')) return;
        const formData = new FormData();
        formData.append('ajax_action', 'duplicate');
        formData.append('product_id', productId);
        formData.append('csrf_token', CSRF_TOKEN);
        fetch('products.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 800); }
                else showToast(data.message || 'Error duplicating product', 'error');
            })
            .catch(() => showToast('Network error', 'error'));
    }

    // Debounced search
    let searchTimeout;
    document.getElementById('searchInput')?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { if (this.value.length === 0 || this.value.length >= 2) this.closest('form').submit(); }, 600);
    });

    // Export selected
    function exportSelected() {
        if (selectedIds.size === 0) { showToast('No products selected', 'warning'); return; }
        const ids = Array.from(selectedIds).join(',');
        window.location.href = 'export_products.php?format=csv&branch_id=<?php echo $branch_id; ?>&ids=' + encodeURIComponent(ids);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const fs = document.getElementById('flashSuccess');
        if (fs) setTimeout(() => { fs.style.transition = 'opacity 0.5s'; fs.style.opacity = '0'; setTimeout(() => fs.remove(), 500); }, 4000);
    });

    document.addEventListener('keydown', function(e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.altKey && e.key === 'n') { e.preventDefault(); window.location.href = '<?php echo base_url("pos/pos-enterprise.php"); ?>'; }
        if (e.altKey && e.key === 'p') { e.preventDefault(); window.location.href = '<?php echo base_url("products/products.php"); ?>'; }
        if (e.key === 'Escape') document.querySelectorAll('.modal:not(.hidden)').forEach(m => closeModal(m.id));
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
