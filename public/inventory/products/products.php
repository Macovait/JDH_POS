<?php
/**
 * Products management page for Jakababa POS
 */

require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
require_login();

if (!check_permission('products.manage') && !is_super_admin()) {
    enforce_permission('products.manage');
}

$pdo = get_db_connection();

$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$branch_id = get_current_branch_id();
$branch_name = get_current_branch_name();

$branches = [];
if (is_super_admin() || $user_role === 'Admin' || check_permission('branches.view')) {
    try {
        $stmt = $pdo->query("SELECT id, name FROM branches WHERE active = 1 ORDER BY name");
        $branches = $stmt->fetchAll();
    } catch (PDOException $e) { error_log("Error fetching branches: " . $e->getMessage()); }
}

if (isset($_GET['branch_id']) && (is_super_admin() || $user_role === 'Admin' || check_permission('branches.view'))) {
    $new_branch_id = (int) $_GET['branch_id'];
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND active = 1");
    $stmt->execute([$new_branch_id]);
    if ($stmt->fetch()) {
        $branch_id = $new_branch_id;
        $_SESSION['user']['branch_id'] = $branch_id;
        $_SESSION['current_branch']['id'] = $branch_id;
        $stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $branch = $stmt->fetch();
        if ($branch) { $_SESSION['current_branch']['name'] = $branch['name']; $branch_name = $branch['name']; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    try {
        if ($_POST['ajax_action'] === 'toggle_status') {
            $product_id = (int) $_POST['product_id'];
            $current_status = (int) $_POST['current_status'];
            $new_status = $current_status ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE products SET active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $product_id]);
            $response = ['success' => true, 'message' => 'Product status updated', 'new_status' => $new_status];
        } elseif ($_POST['ajax_action'] === 'bulk_delete') {
            $ids = $_POST['ids'] ?? [];
            if (empty($ids)) throw new Exception('No products selected');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id IN ($placeholders)");
            $stmt->execute($ids);
            if ($stmt->fetchColumn() > 0) throw new Exception('Cannot delete products with sales history');
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id IN ($placeholders) AND deleted_at IS NULL");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $img) { if ($img['image'] && file_exists(__DIR__ . '/..' . $img['image'])) unlink(__DIR__ . '/..' . $img['image']); }
            $pdo->prepare("UPDATE products SET deleted_at = NOW(), active = 0, updated_at = NOW() WHERE id IN ($placeholders)")->execute($ids);
            $response = ['success' => true, 'message' => count($ids) . ' products deleted successfully'];
        } elseif ($_POST['ajax_action'] === 'bulk_status') {
            $ids = $_POST['ids'] ?? [];
            $status = (int) $_POST['status'];
            if (empty($ids)) throw new Exception('No products selected');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE products SET active = ?, updated_at = NOW() WHERE id IN ($placeholders)")->execute(array_merge([$status], $ids));
            $response = ['success' => true, 'message' => count($ids) . ' products ' . ($status ? 'activated' : 'deactivated')];
        }
    } catch (Exception $e) { $response['message'] = $e->getMessage(); }
    echo json_encode($response); exit;
}

$category_filter = $_GET['category'] ?? 'all';
$search_term = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'id_desc';

$sort_options = ['id_desc' => 'p.id DESC', 'id_asc' => 'p.id ASC', 'name_asc' => 'p.name ASC', 'name_desc' => 'p.name DESC', 'price_asc' => 'p.price ASC', 'price_desc' => 'p.price DESC', 'stock_asc' => 'total_stock ASC', 'stock_desc' => 'total_stock DESC'];
$order_by = $sort_options[$sort_by] ?? 'p.id DESC';

$sql = "SELECT p.id, p.name, p.sku, p.price, p.cost_price, p.active, p.image, p.created_at, p.updated_at, c.name AS category, c.id AS category_id, (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variant_count, (SELECT COALESCE(SUM(stock), 0) FROM inventory WHERE product_id = p.id AND branch_id = :branch_id AND tenant_id = :tenant_id1) as total_stock FROM products p LEFT JOIN categories c ON p.category_id = c.id AND c.deleted_at IS NULL WHERE p.deleted_at IS NULL AND p.tenant_id = :tenant_id2 AND p.id IN (SELECT DISTINCT product_id FROM inventory WHERE branch_id = :branch_id2 AND tenant_id = :tenant_id3)";
$params = [':branch_id' => $branch_id, ':branch_id2' => $branch_id, ':tenant_id1' => $tenant_id, ':tenant_id2' => $tenant_id, ':tenant_id3' => $tenant_id];

if ($category_filter !== 'all') { $sql .= " AND p.category_id = :category"; $params[':category'] = $category_filter; }
if ($status_filter !== 'all') { $sql .= " AND p.active = :status"; $params[':status'] = $status_filter === 'active' ? 1 : 0; }
if (!empty($search_term)) { $sql .= " AND (p.name LIKE :search OR p.sku LIKE :search OR p.barcode LIKE :search)"; $params[':search'] = "%$search_term%"; }
$sql .= " ORDER BY $order_by";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT c.id, c.name FROM categories c JOIN products p ON c.id = p.category_id JOIN inventory i ON p.id = i.product_id WHERE i.branch_id = ? AND c.status = 'active' AND c.deleted_at IS NULL AND p.deleted_at IS NULL ORDER BY c.name");
    $stmt->execute([$branch_id]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) { error_log("Error fetching categories: " . $e->getMessage()); }

$low_stock_count = 0; $low_stock_products = [];
if (check_permission('inventory.manage') || is_super_admin()) {
    $stmt = $pdo->prepare("SELECT p.name, i.stock, i.reorder_level FROM inventory i JOIN products p ON i.product_id = p.id WHERE i.branch_id = ? AND p.deleted_at IS NULL AND i.stock < i.reorder_level AND i.stock > 0 ORDER BY (i.reorder_level - i.stock) DESC LIMIT 5");
    $stmt->execute([$branch_id]);
    $low_stock_products = $stmt->fetchAll();
    $low_stock_count = count($low_stock_products);
}

$total_products_in_branch = count($products);
$total_products_global = $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND tenant_id = $tenant_id")->fetchColumn();
$products_not_in_branch = $total_products_global - $total_products_in_branch;
$active_products = count(array_filter($products, fn($p) => $p['active'] == 1));
$products_with_variants = count(array_filter($products, fn($p) => $p['variant_count'] > 0));
$total_stock = array_sum(array_column($products, 'total_stock'));

function displayProductImage($image_path, $product_name) {
    if ($image_path) {
        $rel = ltrim($image_path, '/');
        if (strpos($rel, 'public/') === 0) {
            $rel = substr($rel, 7);
        }
        $full_path = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (file_exists($full_path)) {
            $mtime = @filemtime($full_path) ?: time();
            return '<img src="' . htmlspecialchars(base_url($rel) . '?v=' . $mtime) . '" alt="' . htmlspecialchars($product_name) . '" class="w-12 h-12 rounded-lg object-cover bg-slate-800" onerror="this.onerror=null; this.parentElement.innerHTML=\'<div class=\\"w-12 h-12 rounded-lg bg-gradient-to-br from-primary-dark to-primary flex items-center justify-center\\"><i class=\\"fas fa-cube text-amber-400/50 text-xl\\"></i></div>\';">';
        }
    }
    return '<div class="w-12 h-12 rounded-lg bg-gradient-to-br from-primary-dark to-primary flex items-center justify-center"><i class="fas fa-cube text-amber-400/50 text-xl"></i></div>';
}

function _REMOVED_PLACEHOLDER($image_path, $product_name) {
    if ($image_path) {
        $full_path = __DIR__ . '/..' . $image_path;
        if (file_exists($full_path)) {
            return '<img src="' . htmlspecialchars($image_path) . '?v=' . filemtime($full_path) . '" alt="' . htmlspecialchars($product_name) . '" class="w-12 h-12 rounded-lg object-cover bg-slate-800" onerror="this.onerror=null; this.parentElement.innerHTML=\'<div class=\\\"w-12 h-12 rounded-lg bg-gradient-to-br from-primary-dark to-primary flex items-center justify-center\\\"><i class=\\\"fas fa-cube text-amber-400/50 text-xl\\\"></i></div>\';">';
        }
    }
    return '<div class="w-12 h-12 rounded-lg bg-gradient-to-br from-primary-dark to-primary flex items-center justify-center"><i class="fas fa-cube text-amber-400/50 text-xl"></i></div>';
}

$page_title = 'Products Management | Jakababa POS';
ob_start();
?>

<style>
    .table-row-hover:hover { background: rgba(251, 191, 36, 0.05); }
    .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem; }
    .status-active { background: rgba(16, 185, 129, 0.1); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.2); }
    .status-inactive { background: rgba(239, 68, 68, 0.1); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.2); }
    .loading { position: relative; pointer-events: none; opacity: 0.7; }
    .currency { font-family: 'Courier New', monospace; font-weight: 600; }
    .bulk-actions { position: sticky; top: 4rem; z-index: 40; background: rgba(31, 41, 55, 0.95); backdrop-filter: blur(8px); border: 1px solid #374151; border-radius: 0.75rem; padding: 0.75rem 1rem; margin-bottom: 1rem; display: none; align-items: center; justify-content: space-between; }
    .bulk-actions.visible { display: flex; }
    .selection-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #FBBF24; }
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; }
    .modal-overlay.active { display: flex; }
    .modal-content { background: #1F2937; border: 1px solid #374151; border-radius: 1.5rem; max-width: 500px; width: 90%; animation: slideUp 0.3s ease-out; }
    .product-image { transition: transform 0.2s ease; }
    .product-image:hover { transform: scale(1.1); }
    @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>

<div class="fade-in">

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content p-6">
            <div class="flex items-center gap-3 text-red-400 mb-4"><i class="fas fa-exclamation-triangle text-2xl"></i><h3 class="text-xl font-bold">Delete Product</h3></div>
            <p class="text-gray-300 mb-2">Are you sure you want to delete <span id="deleteProductName" class="font-semibold text-amber-400"></span>?</p>
            <p class="text-sm text-gray-400 mb-6">This action cannot be undone. Products with sales history cannot be deleted.</p>
            <div class="flex gap-3"><button onclick="confirmDelete()" class="flex-1 bg-red-500 hover:bg-red-600 text-white py-3 rounded-lg font-semibold transition">Delete</button><button onclick="closeDeleteModal()" class="flex-1 bg-slate-800 border border-slate-700 hover:border-amber-500 text-white py-3 rounded-lg font-semibold transition">Cancel</button></div>
        </div>
    </div>

    <!-- Bulk Delete Modal -->
    <div id="bulkDeleteModal" class="modal-overlay">
        <div class="modal-content p-6">
            <div class="flex items-center gap-3 text-red-400 mb-4"><i class="fas fa-exclamation-triangle text-2xl"></i><h3 class="text-xl font-bold">Delete Selected Products</h3></div>
            <p class="text-gray-300 mb-2">Are you sure you want to delete <span id="bulkDeleteCount">0</span> selected products?</p>
            <p class="text-sm text-gray-400 mb-6">Products with sales history cannot be deleted.</p>
            <div class="flex gap-3"><button onclick="confirmBulkDelete()" class="flex-1 bg-red-500 hover:bg-red-600 text-white py-3 rounded-lg font-semibold transition">Delete All</button><button onclick="closeBulkDeleteModal()" class="flex-1 bg-slate-800 border border-slate-700 hover:border-amber-500 text-white py-3 rounded-lg font-semibold transition">Cancel</button></div>
        </div>
    </div>

    <!-- Low Stock Modal -->
    <div id="lowStockModal" class="modal-overlay">
        <div class="modal-content p-6">
            <div class="flex items-center gap-3 text-red-400 mb-4"><i class="fas fa-exclamation-triangle text-2xl"></i><h3 class="text-xl font-bold">Low Stock Alert</h3></div>
            <div class="space-y-3 mb-6">
                <?php foreach ($low_stock_products as $item): ?>
                    <div class="flex justify-between items-center p-3 bg-primary-dark/50 rounded-lg">
                        <span class="font-medium"><?php echo htmlspecialchars($item['name']); ?></span>
                        <span class="text-red-400 font-bold"><?php echo $item['stock']; ?> / <?php echo $item['reorder_level']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-end"><button onclick="closeLowStockModal()" class="px-6 py-2 bg-amber-500 text-black rounded-lg hover:bg-amber-600 transition">Close</button></div>
        </div>
    </div>

    <!-- Bulk Actions Bar -->
    <div id="bulkActionsBar" class="bulk-actions mx-auto max-w-7xl px-4">
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-400"><span id="selectedCount">0</span> products selected</span>
            <button onclick="selectAll()" class="text-xs text-amber-400 hover:text-amber-400-dark px-2 py-1 rounded">Select All</button>
            <button onclick="clearSelection()" class="text-xs text-gray-400 hover:text-white px-2 py-1 rounded">Clear</button>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="bulkStatusUpdate(1)" class="px-3 py-1.5 bg-emerald-500/20 text-emerald-400 rounded-lg hover:bg-emerald-500/30 transition text-sm flex items-center gap-1"><i class="fas fa-check-circle"></i>Activate</button>
            <button onclick="bulkStatusUpdate(0)" class="px-3 py-1.5 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 transition text-sm flex items-center gap-1"><i class="fas fa-ban"></i>Deactivate</button>
            <?php if (is_super_admin() || $user_role === 'Admin'): ?>
                <button onclick="openBulkDeleteModal()" class="px-3 py-1.5 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 transition text-sm flex items-center gap-1"><i class="fas fa-trash"></i>Delete</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Header with Actions -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8 ">
        <div>
            <h1 class="text-3xl font-bold text-white">Products Management</h1>
            <p class="text-gray-400 mt-1">Products available in <span class="text-amber-400 font-semibold"><?php echo htmlspecialchars($branch_name); ?></span> (<?php echo $total_products_in_branch; ?> products)
                <?php if ($products_not_in_branch > 0): ?><span class="text-xs text-gray-500 ml-2"><?php echo $products_not_in_branch; ?> not in this branch</span><?php endif; ?>
            </p>
        </div>
        <div class="flex gap-3">
            <?php if (!empty($branches)): ?>
                <div class="relative">
                    <form method="GET" id="branchForm" class="flex items-center gap-2">
                        <select name="branch_id" onchange="this.form.submit()" class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-white text-sm appearance-none pr-8">
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['id']; ?>" <?php echo $branch_id == $branch['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($search_term)): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>"><?php endif; ?>
                        <?php if ($category_filter !== 'all'): ?><input type="hidden" name="category" value="<?php echo $category_filter; ?>"><?php endif; ?>
                        <?php if ($status_filter !== 'all'): ?><input type="hidden" name="status" value="<?php echo $status_filter; ?>"><?php endif; ?>
                        <?php if ($sort_by !== 'id_desc'): ?><input type="hidden" name="sort" value="<?php echo $sort_by; ?>"><?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
            <?php if ($low_stock_count > 0): ?>
                <button onclick="openLowStockModal()" class="flex items-center gap-1 text-red-400 hover:text-red-400-dark transition-colors"><i class="fas fa-exclamation-triangle animate-pulse"></i><span class="text-sm hidden sm:inline"><?php echo $low_stock_count; ?> low stock</span></button>
            <?php endif; ?>
            <button onclick="exportProducts()" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800/50 rounded-xl border border-slate-700 text-gray-300 hover:text-white hover:border-amber-500 transition-all"><i class="fas fa-download"></i><span class="text-sm">Export</span></button>
            <a href="product_form.php" class="inline-flex items-center gap-2 px-6 py-2 bg-amber-500 text-black rounded-xl font-semibold hover:bg-amber-600 transition-all"><i class="fas fa-plus"></i><span>Add Product</span></a>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4 mb-6 ">
        <form method="GET" class="flex flex-col md:flex-row gap-4">
            <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search by name, SKU, or barcode..." class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none">
            </div>
            <div class="flex flex-wrap gap-2">
                <select name="category" class="px-4 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?><option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?>
                </select>
                <select name="status" class="px-4 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <select name="sort" class="px-4 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none">
                    <option value="id_desc" <?php echo $sort_by === 'id_desc' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="id_asc" <?php echo $sort_by === 'id_asc' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                    <option value="name_desc" <?php echo $sort_by === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                    <option value="price_asc" <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>Price Low-High</option>
                    <option value="price_desc" <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>Price High-Low</option>
                    <option value="stock_asc" <?php echo $sort_by === 'stock_asc' ? 'selected' : ''; ?>>Stock Low-High</option>
                    <option value="stock_desc" <?php echo $sort_by === 'stock_desc' ? 'selected' : ''; ?>>Stock High-Low</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-amber-500 text-black rounded-xl hover:bg-amber-600 transition-colors flex items-center gap-2"><i class="fas fa-filter"></i><span class="hidden sm:inline">Filter</span></button>
                <?php if (!empty($search_term) || $category_filter !== 'all' || $status_filter !== 'all' || $sort_by !== 'id_desc'): ?>
                    <a href="products.php?branch_id=<?php echo $branch_id; ?>" class="px-4 py-2 bg-slate-800 border border-slate-700 rounded-xl text-gray-400 hover:text-white hover:border-amber-500 transition-colors flex items-center gap-2"><i class="fas fa-times"></i><span class="hidden sm:inline">Clear</span></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Products Table -->
    <div class="bg-slate-800/40 rounded-xl border border-slate-700 overflow-hidden ">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-800/50 border-b border-slate-700">
                    <tr>
                        <th class="px-4 py-4 w-10"><input type="checkbox" id="selectAllCheckbox" class="selection-checkbox" onchange="toggleSelectAll(this)"></th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Product</th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">SKU</th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Category</th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Price</th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Cost</th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Stock</th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    <?php if (empty($products)): ?>
                        <tr><td colspan="9" class="px-6 py-12 text-center text-gray-500"><div class="flex flex-col items-center gap-3"><i class="fas fa-cube text-4xl text-gray-600"></i><p class="text-lg">No products found in this branch</p><a href="product_form.php" class="mt-2 px-4 py-2 bg-amber-500 text-black rounded-xl text-sm font-semibold hover:bg-amber-600 transition-colors">Add new product</a></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <?php $stock_class = $product['total_stock'] < 5 ? 'text-red-400' : 'text-emerald-400'; $stock_icon = $product['total_stock'] < 5 ? 'exclamation-triangle' : 'check-circle'; ?>
                            <tr class="table-row-hover transition-colors" id="product-row-<?php echo $product['id']; ?>">
                                <td class="px-4 py-4"><input type="checkbox" class="product-checkbox selection-checkbox" value="<?php echo $product['id']; ?>" onchange="updateSelection()"></td>
                                <td class="px-4 py-4"><div class="flex items-center gap-3"><?php echo displayProductImage($product['image'], $product['name']); ?><div><div class="font-medium text-white"><?php echo htmlspecialchars($product['name']); ?></div><?php if ($product['variant_count'] > 0): ?><span class="text-xs text-amber-400"><i class="fas fa-code-branch mr-1"></i><?php echo $product['variant_count']; ?> variants</span><?php endif; ?></div></div></td>
                                <td class="px-4 py-4 font-mono text-sm text-gray-400"><?php echo htmlspecialchars($product['sku'] ?: '-'); ?></td>
                                <td class="px-4 py-4"><?php if ($product['category']): ?><span class="px-2 py-1 bg-amber-500/10 text-amber-400 rounded-lg text-xs"><?php echo htmlspecialchars($product['category']); ?></span><?php else: ?><span class="text-gray-600">-</span><?php endif; ?></td>
                                <td class="px-4 py-4"><span class="font-semibold text-amber-400 currency">KSh <?php echo number_format((float) $product['price'], 2); ?></span></td>
                                <td class="px-4 py-4"><span class="text-gray-400 currency">KSh <?php echo number_format((float) $product['cost_price'], 2); ?></span></td>
                                <td class="px-4 py-4"><span class="<?php echo $stock_class; ?> flex items-center gap-1"><i class="fas fa-<?php echo $stock_icon; ?> text-xs"></i><?php echo (int) $product['total_stock']; ?> units</span></td>
                                <td class="px-4 py-4"><button onclick="toggleStatus(<?php echo $product['id']; ?>, <?php echo $product['active']; ?>)" class="status-badge <?php echo $product['active'] ? 'status-active' : 'status-inactive'; ?> hover:opacity-80 transition"><i class="fas fa-<?php echo $product['active'] ? 'check-circle' : 'ban'; ?>"></i><?php echo $product['active'] ? 'Active' : 'Inactive'; ?></button></td>
                                <td class="px-4 py-4"><div class="flex items-center gap-2">
                                    <a href="../../products/product_edit.php?id=<?php echo $product['id']; ?>" class="p-2 text-gray-400 hover:text-amber-400 transition-colors" title="Edit"><i class="fas fa-pen"></i></a>
                                    <?php if ($product['variant_count'] > 0): ?><a href="../../products/variants.php?product_id=<?php echo $product['id']; ?>" class="p-2 text-gray-400 hover:text-amber-400 transition-colors" title="Variants"><i class="fas fa-code-branch"></i></a><?php endif; ?>
                                    <?php if (is_super_admin() || $user_role === 'Admin'): ?><button onclick="openDeleteModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')" class="p-2 text-gray-400 hover:text-red-400 transition-colors" title="Delete"><i class="fas fa-trash"></i></button><?php endif; ?>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 bg-slate-800/50 border-t border-slate-700 flex flex-wrap justify-between items-center gap-4 text-sm text-gray-400">
            <div class="flex items-center gap-4">
                <span><i class="fas fa-cube mr-1"></i>Total: <span class="text-white font-semibold"><?php echo $total_products_in_branch; ?></span></span>
                <span><i class="fas fa-check-circle text-emerald-400 mr-1"></i>Active: <span class="text-emerald-400 font-semibold"><?php echo $active_products; ?></span></span>
                <span><i class="fas fa-boxes text-blue-400 mr-1"></i>Stock: <span class="text-blue-400 font-semibold"><?php echo $total_stock; ?></span></span>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 space-y-2 z-50"></div>

<script>
    let selectedIds = new Set();

    function updateSelection() {
        const checkboxes = document.querySelectorAll('.product-checkbox');
        selectedIds.clear();
        checkboxes.forEach(cb => { if (cb.checked) selectedIds.add(cb.value); });
        document.getElementById('selectedCount').textContent = selectedIds.size;
        document.getElementById('bulkActionsBar').classList.toggle('visible', selectedIds.size > 0);
    }

    function toggleSelectAll(checkbox) {
        document.querySelectorAll('.product-checkbox').forEach(cb => { cb.checked = checkbox.checked; if (checkbox.checked) selectedIds.add(cb.value); else selectedIds.delete(cb.value); });
        updateSelection();
    }

    function selectAll() {
        document.querySelectorAll('.product-checkbox').forEach(cb => { cb.checked = true; selectedIds.add(cb.value); });
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
        fetch('products.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(data => { if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 1000); } else showToast(data.message, 'error'); })
            .catch(e => { showToast('Error updating status', 'error'); });
    }

    let currentDeleteId = null;
    function openDeleteModal(id, name) { currentDeleteId = id; document.getElementById('deleteProductName').textContent = name; document.getElementById('deleteModal').classList.add('active'); }
    function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); currentDeleteId = null; }
    function confirmDelete() {
        if (!currentDeleteId) return;
        const form = document.createElement('form'); form.method = 'POST'; form.action = 'product_delete.php';
        const input = document.createElement('input'); input.type = 'hidden'; input.name = 'id'; input.value = currentDeleteId;
        form.appendChild(input); document.body.appendChild(form); form.submit();
    }

    function openBulkDeleteModal() { if (selectedIds.size === 0) { showToast('No products selected', 'warning'); return; } document.getElementById('bulkDeleteCount').textContent = selectedIds.size; document.getElementById('bulkDeleteModal').classList.add('active'); }
    function closeBulkDeleteModal() { document.getElementById('bulkDeleteModal').classList.remove('active'); }
    function confirmBulkDelete() {
        if (selectedIds.size === 0) return;
        const formData = new FormData(); formData.append('ajax_action', 'bulk_delete');
        Array.from(selectedIds).forEach(id => formData.append('ids[]', id));
        fetch('products.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(data => { if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 1000); } else showToast(data.message, 'error'); closeBulkDeleteModal(); clearSelection(); })
            .catch(e => { showToast('Error deleting products', 'error'); closeBulkDeleteModal(); });
    }

    function bulkStatusUpdate(status) {
        if (selectedIds.size === 0) { showToast('No products selected', 'warning'); return; }
        const formData = new FormData(); formData.append('ajax_action', 'bulk_status'); formData.append('status', status);
        Array.from(selectedIds).forEach(id => formData.append('ids[]', id));
        fetch('products.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(data => { if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 1000); } else showToast(data.message, 'error'); clearSelection(); })
            .catch(e => { showToast('Error updating products', 'error'); });
    }

    function openLowStockModal() { document.getElementById('lowStockModal').classList.add('active'); }
    function closeLowStockModal() { document.getElementById('lowStockModal').classList.remove('active'); }
    function exportProducts() { window.location.href = 'export_products.php?format=csv&branch_id=<?php echo $branch_id; ?>'; }

    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        const colors = { success: 'bg-emerald-500 text-white', error: 'bg-red-500 text-white', info: 'bg-amber-500 text-black', warning: 'bg-red-500 text-white' };
        const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle', warning: 'exclamation-triangle' };
        toast.className = `flex items-center gap-2 ${colors[type]} px-4 py-3 rounded-xl shadow-lg `;
        toast.innerHTML = `<i class="fas fa-${icons[type]}"></i><span>${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s ease'; setTimeout(() => toast.remove(), 300); }, 3000);
    }

    document.addEventListener('click', function (e) {
        if (e.target === document.getElementById('deleteModal')) closeDeleteModal();
        if (e.target === document.getElementById('bulkDeleteModal')) closeBulkDeleteModal();
        if (e.target === document.getElementById('lowStockModal')) closeLowStockModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.altKey && e.key === 'n') { e.preventDefault(); window.location.href = 'product_form.php'; }
        if (e.key === 'Escape') { closeDeleteModal(); closeBulkDeleteModal(); closeLowStockModal(); }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app_close.php';
require_once __DIR__ . '/../../layouts/app.php';
?>
