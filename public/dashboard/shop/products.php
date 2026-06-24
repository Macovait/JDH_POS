<?php
/**
 * Store Admin — Products (online store view of POS products)
 * @version 3.0
 */
require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$tenant_id = (int)($_SESSION['tenant_id'] ?? get_current_tenant_id() ?? 0);

if (!$tenant_id) {
    echo '<div class="flex items-center justify-center h-screen text-slate-400">Access Denied — no tenant context</div>';
    exit;
}

// Load store settings
$settings = [];
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

$brand_color = $settings['primary_color'] ?? '#f68b1e';
$store_name = $settings['store_name'] ?? $_SESSION['company_name'] ?? 'My Store';
$currency = $settings['currency'] ?? 'KES';

// Load currency symbol
try {
    $row = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'currency' LIMIT 1");
    $row->execute([$tenant_id]);
    $currency = $row->fetchColumn() ?: 'KES';
} catch (Exception $e) {}

// ── Handle Toggle Online Visibility ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_online'])) {
    $pid = (int)$_POST['product_id'];
    $val = (int)$_POST['online_val'];
    try {
        $pdo->prepare("UPDATE products SET active = ? WHERE id = ? AND tenant_id = ?")->execute([$val, $pid, $tenant_id]);
        $_SESSION['flash_message'] = 'Product visibility updated successfully.';
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Update failed: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: products.php');
    exit;
}

// ── Handle Featured Toggle ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_featured'])) {
    $pid = (int)$_POST['product_id'];
    $section = $_POST['feature_section'] ?? 'deals';
    try {
        $existing = $pdo->prepare("SELECT id FROM featured_products WHERE tenant_id = ? AND product_id = ? AND section = ?");
        $existing->execute([$tenant_id, $pid, $section]);
        if ($existing->fetchColumn()) {
            $pdo->prepare("DELETE FROM featured_products WHERE tenant_id = ? AND product_id = ? AND section = ?")->execute([$tenant_id, $pid, $section]);
            $_SESSION['flash_message'] = 'Product removed from ' . ($section === 'deals' ? 'Deals' : 'Featured') . '.';
        } else {
            $pdo->prepare("INSERT INTO featured_products (tenant_id, product_id, section, is_active) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE is_active=1")->execute([$tenant_id, $pid, $section]);
            $_SESSION['flash_message'] = 'Product added to ' . ($section === 'deals' ? 'Deals' : 'Featured') . '!';
        }
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Update failed: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: products.php');
    exit;
}

// ── Handle Bulk Action ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $product_ids = $_POST['product_ids'] ?? [];
    
    if (!empty($product_ids) && in_array($action, ['active', 'inactive', 'delete'])) {
        try {
            $pdo->beginTransaction();
            $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
            
            if ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders) AND tenant_id = ?");
                $params = array_merge($product_ids, [$tenant_id]);
                $msg = count($product_ids) . ' product(s) deleted.';
            } else {
                $status = $action === 'active' ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE products SET active = ? WHERE id IN ($placeholders) AND tenant_id = ?");
                $params = array_merge([$status], $product_ids, [$tenant_id]);
                $msg = count($product_ids) . ' product(s) ' . ($action === 'active' ? 'published' : 'hidden') . '.';
            }
            $stmt->execute($params);
            $pdo->commit();
            $_SESSION['flash_message'] = $msg;
            $_SESSION['flash_type'] = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = 'Bulk action failed: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
    } else {
        $_SESSION['flash_message'] = 'No products selected or invalid action.';
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: products.php');
    exit;
}

// ── Load Settings ──
$blocks_count = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM storefront_blocks WHERE tenant_id = ? AND is_active = 1");
    $st->execute([$tenant_id]);
    $blocks_count = (int)$st->fetchColumn();
} catch (Exception $e) {}

$stats = ['pending_orders' => 0, 'reviews_pending' => 0];
try {
    $r = $pdo->prepare("SELECT COUNT(*) FROM online_orders WHERE tenant_id = ? AND status='pending'");
    $r->execute([$tenant_id]);
    $stats['pending_orders'] = (int)$r->fetchColumn();
    $r = $pdo->prepare("SELECT COUNT(*) FROM product_reviews WHERE tenant_id = ? AND status='pending'");
    $r->execute([$tenant_id]);
    $stats['reviews_pending'] = (int)$r->fetchColumn();
} catch (Exception $e) {}

// ── Fetch Products ──
$search = trim($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['p'] ?? 1));
$per_page = 24;
$offset = ($page - 1) * $per_page;

$where = "WHERE p.tenant_id = ?";
$params = [$tenant_id];

if ($search) {
    $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($cat_filter) {
    $where .= " AND p.category_id = ?";
    $params[] = $cat_filter;
}

if ($status_filter === 'active') {
    $where .= " AND p.active = 1";
} elseif ($status_filter === 'inactive') {
    $where .= " AND p.active = 0";
} elseif ($status_filter === 'low_stock') {
    $where .= " AND COALESCE(SUM(i.quantity), 0) <= 5 AND COALESCE(SUM(i.quantity), 0) > 0";
} elseif ($status_filter === 'out_of_stock') {
    $where .= " AND COALESCE(SUM(i.quantity), 0) = 0";
}

$products = [];
$total_count = 0;

try {
    $cnt = $pdo->prepare("SELECT COUNT(DISTINCT p.id) FROM products p LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id $where");
    $cnt->execute($params);
    $total_count = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.sku, p.price, p.selling_price, p.image, p.active, p.created_at,
                c.name as category_name, 
                COALESCE(SUM(i.quantity), 0) as stock,
                COALESCE(AVG(r.rating), 0) as avg_rating,
                COUNT(DISTINCT r.id) as review_count
         FROM products p 
         LEFT JOIN categories c ON c.id = p.category_id 
         LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
         LEFT JOIN product_reviews r ON r.product_id = p.id AND r.status = 'approved'
         $where 
         GROUP BY p.id 
         ORDER BY p.created_at DESC 
         LIMIT $per_page OFFSET $offset"
    );
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silence
}

// ── Get Featured Product IDs ──
$dealsIds = [];
$featuredIds = [];
try {
    $r = $pdo->prepare("SELECT product_id, section FROM featured_products WHERE tenant_id = ? AND is_active = 1");
    $r->execute([$tenant_id]);
    foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['section'] === 'deals') $dealsIds[] = (int)$row['product_id'];
        if ($row['section'] === 'featured') $featuredIds[] = (int)$row['product_id'];
    }
} catch (Exception $e) {}

// ── Categories ──
$categories = [];
try {
    $r = $pdo->prepare("SELECT id, name FROM categories WHERE tenant_id = ? ORDER BY name LIMIT 100");
    $r->execute([$tenant_id]);
    $categories = $r->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$total_pages = (int)ceil($total_count / $per_page);
$storeUrl = storefront_url($tenant_id);
$page_title = 'Products';

ob_start();
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-box text-amber-400 text-xl"></i>
                Products
            </h1>
            <p class="text-sm text-slate-500 mt-1 flex items-center gap-3">
                <span><?= number_format($total_count) ?> products in catalog</span>
                <?php if ($total_count > 0): ?>
                <span class="text-[10px] bg-slate-700/50 px-2 py-0.5 rounded-full text-slate-400">
                    <i class="fas fa-circle text-emerald-400 text-[6px] mr-1"></i>
                    <?= number_format(array_sum(array_column($products, 'stock'))) ?> total stock
                </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= base_url('products/product_form.php') ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white text-xs font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                <i class="fas fa-plus"></i> Add Product
            </a>
            <a href="<?= htmlspecialchars($storeUrl) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 border border-slate-700/60 text-slate-400 text-xs font-medium hover:bg-slate-700/80 hover:text-white hover:border-slate-600 transition-all duration-200">
                <i class="fas fa-eye text-[10px]"></i> View Store
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col lg:flex-row gap-3">
        <form method="GET" class="flex-1 flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[180px]">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search products, SKU, barcode…" 
                       class="w-full pl-9 pr-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
            </div>
            
            <select name="cat" class="bg-slate-900/50 border border-slate-700/60 rounded-lg px-3 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $cat_filter == (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="status" class="bg-slate-900/50 border border-slate-700/60 rounded-lg px-3 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="low_stock" <?= $status_filter === 'low_stock' ? 'selected' : '' ?>>Low Stock (≤5)</option>
                <option value="out_of_stock" <?= $status_filter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
            </select>
            
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-white text-sm font-medium transition-all hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                <i class="fas fa-search text-xs"></i> Filter
            </button>
            
            <?php if ($search || $cat_filter || $status_filter): ?>
            <a href="products.php" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-slate-700/50 text-slate-300 text-sm font-medium hover:bg-slate-700 hover:text-white transition-all">
                <i class="fas fa-times text-xs"></i> Clear
            </a>
            <?php endif; ?>
        </form>
        
        <!-- Legend -->
        <div class="flex items-center gap-3 text-[10px] text-slate-500 flex-shrink-0">
            <span class="flex items-center gap-1"><span class="text-amber-400">★</span> Deals</span>
            <span class="flex items-center gap-1"><span class="text-orange-400">⭐</span> Featured</span>
        </div>
    </div>

    <!-- Bulk Actions -->
    <?php if (!empty($products)): ?>
    <form method="POST" class="flex items-center gap-2" id="bulkForm">
        <input type="hidden" name="bulk_action" value="" id="bulkAction">
        <div class="flex items-center gap-2 bg-slate-800/40 border border-slate-700/60 rounded-lg px-3 py-1.5">
            <input type="checkbox" id="selectAll" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20" onchange="toggleSelectAll()">
            <span class="text-[10px] text-slate-500">Select All</span>
        </div>
        <select name="bulk_action_select" id="bulkActionSelect" 
                class="text-xs bg-slate-900/50 border border-slate-700/60 rounded-lg px-3 py-1.5 text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
            <option value="">Bulk Action</option>
            <option value="active">Publish Selected</option>
            <option value="inactive">Hide Selected</option>
            <option value="delete">Delete Selected</option>
        </select>
        <button type="submit" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 text-slate-300 text-xs font-medium hover:bg-slate-600/60 hover:text-white transition-all border border-slate-600/40"
                onclick="return confirmBulkAction()">
            <i class="fas fa-arrow-right text-[10px]"></i> Apply
        </button>
    </form>
    <?php endif; ?>

    <!-- Products Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
        <?php if (empty($products)): ?>
        <div class="col-span-full text-center py-16 text-slate-500">
            <div class="w-16 h-16 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-box-open text-2xl text-slate-600"></i>
            </div>
            <p class="font-medium text-slate-400">No products found</p>
            <p class="text-xs mt-1"><?= $search ? 'Try adjusting your search' : 'Get started by adding your first product' ?></p>
            <a href="<?= base_url('products/product_form.php') ?>" class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl text-white text-xs font-bold transition-all hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                <i class="fas fa-plus"></i> Add First Product
            </a>
        </div>
        <?php endif; ?>
        
        <?php foreach ($products as $p):
            $isDeal = in_array($p['id'], $dealsIds);
            $isFeatured = in_array($p['id'], $featuredIds);
            $price = (float)($p['selling_price'] ?: $p['price']);
            $stock = (int)$p['stock'];
            $stockClass = $stock === 0 ? 'text-red-400' : ($stock <= 5 ? 'text-amber-400' : 'text-emerald-400');
            $stockLabel = $stock === 0 ? 'Out of Stock' : ($stock <= 5 ? 'Low Stock' : 'In Stock');
            $avgRating = (float)$p['avg_rating'];
            $reviewCount = (int)$p['review_count'];
            $image = $p['image'] ?? '';
        ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl hover:border-amber-500/30 hover:shadow-glow transition-all duration-200 group relative overflow-hidden card-hover">
            <!-- Badges -->
            <div class="absolute top-1.5 left-1.5 flex flex-col gap-0.5 z-10">
                <?php if ($isDeal): ?>
                <span class="bg-amber-400 text-amber-900 text-[9px] font-black px-1.5 py-0.5 rounded-full">★ DEALS</span>
                <?php endif; ?>
                <?php if ($isFeatured): ?>
                <span class="bg-orange-500 text-white text-[9px] font-black px-1.5 py-0.5 rounded-full">⭐ FEATURED</span>
                <?php endif; ?>
            </div>
            
            <?php if (!$p['active']): ?>
            <div class="absolute top-1.5 right-1.5 bg-slate-500/80 text-white text-[9px] font-black px-1.5 py-0.5 rounded-full z-10 backdrop-blur-sm">HIDDEN</div>
            <?php endif; ?>
            
            <!-- Image -->
            <div class="aspect-square bg-slate-700/30 flex items-center justify-center overflow-hidden border-b border-slate-700/40 relative">
                <?php if (!empty($image)): ?>
                <img src="<?= htmlspecialchars($image) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" alt="<?= htmlspecialchars($p['name']) ?>" onerror="this.parentElement.innerHTML='<i class=\'fas fa-box text-slate-500 text-3xl\'></i>'">
                <?php else: ?>
                <i class="fas fa-box text-slate-600 text-3xl"></i>
                <?php endif; ?>
                
                <!-- Quick view overlay -->
                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                    <a href="<?= base_url('products/product_edit.php') ?>?id=<?= $p['id'] ?>" class="px-3 py-1.5 rounded-lg bg-amber-500 text-white text-xs font-bold hover:bg-amber-600 transition-colors">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>
            
            <!-- Info -->
            <div class="p-2.5">
                <div class="flex items-start justify-between gap-1">
                    <p class="text-xs font-semibold text-white line-clamp-2 leading-tight flex-1"><?= htmlspecialchars($p['name']) ?></p>
                    <input type="checkbox" name="product_ids[]" value="<?= $p['id'] ?>" class="product-checkbox rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20 mt-0.5 flex-shrink-0" onchange="updateSelectAllState()">
                </div>
                
                <p class="text-[10px] text-slate-500 mb-1 truncate"><?= htmlspecialchars($p['category_name'] ?? '—') ?></p>
                
                <div class="flex items-center justify-between">
                    <span class="text-xs font-extrabold text-amber-400"><?= $currency ?> <?= number_format($price, 0) ?></span>
                    <span class="text-[10px] font-medium <?= $stockClass ?> flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full <?= $stockClass === 'text-red-400' ? 'bg-red-400' : ($stockClass === 'text-amber-400' ? 'bg-amber-400' : 'bg-emerald-400') ?>"></span>
                        <?= $stockLabel ?>
                    </span>
                </div>
                
                <!-- Rating -->
                <?php if ($avgRating > 0): ?>
                <div class="flex items-center gap-1 mt-0.5">
                    <div class="flex items-center gap-0.5 text-amber-400 text-[9px]">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star<?= $i <= round($avgRating) ? '' : '-o' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="text-[9px] text-slate-500">(<?= $reviewCount ?>)</span>
                </div>
                <?php endif; ?>
                
                <!-- Actions -->
                <div class="mt-2 flex gap-1">
                    <a href="<?= base_url('products/product_edit.php') ?>?id=<?= $p['id'] ?>" class="flex-1 text-center text-[10px] font-medium py-1 bg-slate-700/50 hover:bg-slate-600 rounded-lg transition text-slate-300 hover:text-white">
                        <i class="fas fa-pen"></i>
                    </a>
                    
                    <form method="POST" class="flex-1">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="toggle_featured" value="1">
                        <input type="hidden" name="feature_section" value="deals">
                        <button type="submit" class="w-full text-[10px] font-medium py-1 rounded-lg transition <?= $isDeal ? 'bg-amber-500/15 text-amber-400 hover:bg-amber-500/25' : 'bg-slate-700/50 text-slate-400 hover:bg-amber-500/10 hover:text-amber-400' ?>" title="Toggle Deals">
                            ★
                        </button>
                    </form>
                    
                    <form method="POST" class="flex-1">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="toggle_featured" value="1">
                        <input type="hidden" name="feature_section" value="featured">
                        <button type="submit" class="w-full text-[10px] font-medium py-1 rounded-lg transition <?= $isFeatured ? 'bg-orange-500/15 text-orange-400 hover:bg-orange-500/25' : 'bg-slate-700/50 text-slate-400 hover:bg-orange-500/10 hover:text-orange-400' ?>" title="Toggle Featured">
                            ⭐
                        </button>
                    </form>
                    
                    <form method="POST" class="flex-1">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="toggle_online" value="1">
                        <input type="hidden" name="online_val" value="<?= $p['active'] ? '0' : '1' ?>">
                        <button type="submit" class="w-full text-[10px] font-medium py-1 rounded-lg transition <?= $p['active'] ? 'bg-emerald-500/15 text-emerald-400 hover:bg-red-500/10 hover:text-red-400' : 'bg-red-500/15 text-red-400 hover:bg-emerald-500/10 hover:text-emerald-400' ?>" title="<?= $p['active'] ? 'Hide from store' : 'Show on store' ?>">
                            <i class="fas <?= $p['active'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
        <p class="text-xs text-slate-500">
            Showing <?= min($offset + 1, $total_count) ?>–<?= min($offset + $per_page, $total_count) ?> of <?= $total_count ?> products
        </p>
        <div class="flex gap-1">
            <?php if ($page > 1): ?>
            <a href="?p=<?= $page - 1 ?><?= $search ? '&q=' . urlencode($search) : '' ?><?= $cat_filter ? '&cat=' . $cat_filter : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>" 
               class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">
                <i class="fas fa-chevron-left text-[10px]"></i> Prev
            </a>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($total_pages, $page + 2);
            $queryParams = ($search ? '&q=' . urlencode($search) : '') . ($cat_filter ? '&cat=' . $cat_filter : '') . ($status_filter ? '&status=' . urlencode($status_filter) : '');
            
            if ($startPage > 1) {
                echo '<a href="?p=1' . $queryParams . '" class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">1</a>';
                if ($startPage > 2) echo '<span class="px-2 text-xs text-slate-500">...</span>';
            }
            for ($i = $startPage; $i <= $endPage; $i++):
                $active = $i === $page;
            ?>
            <a href="?p=<?= $i ?><?= $queryParams ?>" 
               class="px-3 py-1.5 text-xs border rounded-lg transition <?= $active ? 'bg-amber-500 border-amber-500 text-white shadow-lg' : 'border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>">
                <?= $i ?>
            </a>
            <?php endfor;
            if ($endPage < $total_pages) {
                if ($endPage < $total_pages - 1) echo '<span class="px-2 text-xs text-slate-500">...</span>';
                echo '<a href="?p=' . $total_pages . $queryParams . '" class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">' . $total_pages . '</a>';
            }
            ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?p=<?= $page + 1 ?><?= $queryParams ?>" 
               class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">
                Next <i class="fas fa-chevron-right text-[10px]"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Select all functionality
function toggleSelectAll() {
    const master = document.getElementById('selectAll');
    const boxes = document.querySelectorAll('.product-checkbox');
    boxes.forEach(cb => cb.checked = master.checked);
}

function updateSelectAllState() {
    const boxes = document.querySelectorAll('.product-checkbox');
    const checked = document.querySelectorAll('.product-checkbox:checked');
    const master = document.getElementById('selectAll');
    if (master) {
        master.checked = boxes.length > 0 && checked.length === boxes.length;
    }
}

// Bulk action confirmation
function confirmBulkAction() {
    const select = document.getElementById('bulkActionSelect');
    const action = select.value;
    const checked = document.querySelectorAll('.product-checkbox:checked');
    
    if (!action) {
        alert('Please select an action.');
        return false;
    }
    
    if (checked.length === 0) {
        alert('Please select at least one product.');
        return false;
    }
    
    if (action === 'delete' && !confirm(`Are you sure you want to delete ${checked.length} product(s)? This action cannot be undone.`)) {
        return false;
    }
    
    document.getElementById('bulkAction').value = action;
    return true;
}

// Auto-submit bulk action when selecting from dropdown
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('bulkActionSelect');
    if (select) {
        select.addEventListener('change', function() {
            const checked = document.querySelectorAll('.product-checkbox:checked');
            if (this.value && checked.length === 0) {
                alert('Please select at least one product first.');
                this.value = '';
            }
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>