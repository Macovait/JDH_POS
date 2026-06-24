<?php
/**
 * Product Information / Detail Page for Jakababa POS
 * Comprehensive product view with inventory, variants, attributes, and history
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

require_login();

if (!check_permission('products.manage') && !check_permission('products.view') && !is_super_admin()) {
    enforce_permission('products.view');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

$branch_id = get_current_branch_id();
$branch_name = get_current_branch_name();
$user_role = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? 'User';
$can_edit = check_permission('products.manage') || is_super_admin();
$can_delete = is_super_admin() || $user_role === 'Admin';

$product_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$product_id) {
    header('Location: products.php');
    exit;
}

$product = null;
$inventory = [];
$variants = [];
$attributes = [];
$tags = [];
$images = [];
$sales_history = [];
$stock_movements = [];

$errors = [];

try {
    // Product with category and brand
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, b.name AS brand_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.id = :id AND p.tenant_id = :tenant_id AND p.deleted_at IS NULL
    ");
    $stmt->execute([':id' => $product_id, ':tenant_id' => $tenant_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $errors[] = 'Product not found or has been deleted.';
    } else {
        // Inventory across branches
        try {
            $istmt = $pdo->prepare("
                SELECT i.*, br.name AS branch_name
                FROM inventory i
                LEFT JOIN branches br ON i.branch_id = br.id AND br.tenant_id = :t1
                WHERE i.product_id = :pid AND i.tenant_id = :t2 AND i.branch_id = :branch_id
                ORDER BY i.branch_id
            ");
            $istmt->execute([':t1' => $tenant_id, ':pid' => $product_id, ':t2' => $tenant_id, ':branch_id' => $branch_id]);
            $inventory = $istmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Product view inventory error: " . $e->getMessage());
            $errors[] = 'Inventory load error: ' . $e->getMessage();
        }

        // Variants
        try {
            $vstmt = $pdo->prepare("
                SELECT * FROM product_variants
                WHERE product_id = :pid AND tenant_id = :tenant_id
                ORDER BY id
            ");
            $vstmt->execute([':pid' => $product_id, ':tenant_id' => $tenant_id]);
            $variants = $vstmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Product view variants error: " . $e->getMessage());
        }

        // Product attributes (legacy product_attributes table)
        try {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'product_attributes'")->fetch();
            if ($tableExists) {
                $astmt = $pdo->prepare("
                    SELECT * FROM product_attributes
                    WHERE product_id = :pid AND tenant_id = :tenant_id
                    ORDER BY attribute_name
                ");
                $astmt->execute([':pid' => $product_id, ':tenant_id' => $tenant_id]);
                $attributes = $astmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Product view attributes error: " . $e->getMessage());
        }

        // Tags via product_tag_relations
        try {
            $tables = $pdo->query("SHOW TABLES LIKE 'product_tags'")->fetch();
            $tables2 = $pdo->query("SHOW TABLES LIKE 'product_tag_relations'")->fetch();
            if ($tables && $tables2) {
                $tstmt = $pdo->prepare("
                    SELECT t.id, t.name, t.color
                    FROM product_tag_relations r
                    JOIN product_tags t ON r.tag_id = t.id
                    WHERE r.product_id = :pid AND r.tenant_id = :tenant_id
                    ORDER BY t.name
                ");
                $tstmt->execute([':pid' => $product_id, ':tenant_id' => $tenant_id]);
                $tags = $tstmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Product view tags error: " . $e->getMessage());
        }

        // Additional images
        try {
            $tables = $pdo->query("SHOW TABLES LIKE 'product_images'")->fetch();
            if ($tables) {
                $imgstmt = $pdo->prepare("
                    SELECT * FROM product_images
                    WHERE product_id = :pid AND tenant_id = :tenant_id
                    ORDER BY id
                ");
                $imgstmt->execute([':pid' => $product_id, ':tenant_id' => $tenant_id]);
                $images = $imgstmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Product view images error: " . $e->getMessage());
        }

        // Recent sales (last 10)
        try {
            $sstmt = $pdo->prepare("
                SELECT s.id, s.invoice_number, s.created_at, s.total_amount,
                       si.quantity, si.price AS sale_item_price, si.total AS line_total,
                       cu.name AS customer_name
                FROM sales s
                JOIN sale_items si ON s.id = si.sale_id
                LEFT JOIN customers cu ON s.customer_id = cu.id
                WHERE si.product_id = :pid AND s.tenant_id = :tenant_id
                ORDER BY s.created_at DESC
                LIMIT 10
            ");
            $sstmt->execute([':pid' => $product_id, ':tenant_id' => $tenant_id]);
            $sales_history = $sstmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Product view sales error: " . $e->getMessage());
        }

        // Activity log entries related to this product
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM activity_logs LIKE 'action'")->fetch();
            if ($cols) {
                $mstmt = $pdo->prepare("
                    SELECT created_at, action, description AS details
                    FROM activity_logs
                    WHERE (action LIKE '%product%' OR description LIKE :search) AND tenant_id = :tenant_id
                    ORDER BY created_at DESC
                    LIMIT 20
                ");
                $mstmt->execute([':search' => '%' . $product['name'] . '%', ':tenant_id' => $tenant_id]);
                $stock_movements = $mstmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Product view activity error: " . $e->getMessage());
        }
    }
} catch (PDOException $e) {
    error_log("Product view main error: " . $e->getMessage());
    $errors[] = 'Database error loading product details: ' . $e->getMessage();
}

function productImageUrl($image_path, $product_name) {
    if ($image_path) {
        $rel = ltrim($image_path, '/');
        if (strpos($rel, 'public/') === 0) {
            $rel = substr($rel, 7);
        }
        $full = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (file_exists($full)) {
            $mtime = @filemtime($full) ?: time();
            return base_url($rel) . '?v=' . $mtime;
        }
    }
    return '';
}

function displayImage($path, $name, $classes = '') {
    $url = productImageUrl($path, $name);
    if ($url) {
        return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($name) . '" class="' . $classes . '" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">';
    }
    return '';
}

$page_title = $product ? htmlspecialchars($product['name']) : 'Product Not Found';
ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <a href="products.php" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-amber-400 transition-colors mb-2">
            <i class="fas fa-arrow-left text-xs"></i> Back to Products
        </a>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-box text-amber-400"></i>
            <?php echo $product ? htmlspecialchars($product['name']) : 'Product Not Found'; ?>
        </h1>
        <?php if ($product): ?>
        <p class="text-sm text-slate-500 mt-0.5">
            SKU: <span class="text-slate-300 font-mono"><?php echo htmlspecialchars($product['sku'] ?: '—'); ?></span>
            <?php if (!empty($product['barcode'])): ?>
            &middot; Barcode: <span class="text-slate-300 font-mono"><?php echo htmlspecialchars($product['barcode']); ?></span>
            <?php endif; ?>
            &middot; <span class="text-amber-400"><?php echo htmlspecialchars($branch_name); ?></span>
        </p>
        <?php endif; ?>
    </div>
    <?php if ($product && $can_edit): ?>
    <div class="shrink-0 flex items-center gap-2">
        <a href="product_edit.php?id=<?php echo $product_id; ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-pen text-xs"></i> Edit
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-4 flex items-start gap-2 px-3 py-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-triangle mt-0.5 shrink-0"></i>
    <div><?php foreach ($errors as $err): ?><p><?php echo htmlspecialchars($err); ?></p><?php endforeach; ?></div>
</div>
<?php endif; ?>

<?php if ($product):
    $total_stock = array_sum(array_column($inventory, 'stock'));
    $margin      = $product['price'] > 0 ? round((($product['price'] - $product['cost_price']) / $product['price']) * 100, 1) : 0;
    $stock_color = $total_stock == 0 ? 'text-red-400' : ($total_stock < ($product['reorder_level'] ?: 5) ? 'text-amber-400' : 'text-emerald-400');
?>

<!-- Stats cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-tag text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-amber-400"><?php echo format_currency((float)$product['price']); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Selling Price</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-slate-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-coins text-slate-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-slate-300"><?php echo format_currency((float)$product['cost_price']); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Cost Price</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-cubes text-blue-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $stock_color; ?>"><?php echo number_format($total_stock); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Total Stock</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-percent text-purple-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-purple-400"><?php echo $margin; ?>%</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Margin</div>
        </div>
    </div>
</div>

<!-- Two-column layout -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- LEFT COLUMN -->
    <div class="space-y-4">

        <!-- Image -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <i class="fas fa-images text-amber-400 text-xs"></i>
                <span class="text-sm font-semibold text-white">Images</span>
            </div>
            <div class="p-4 flex flex-col items-center gap-4">
                <?php $img_url = productImageUrl($product['image'], $product['name']); ?>
                <?php if ($img_url): ?>
                <img src="<?php echo htmlspecialchars($img_url); ?>"
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     class="w-full max-w-[260px] aspect-square object-cover rounded-xl border border-slate-700/60"
                     onerror="this.replaceWith(document.getElementById('img-fallback').content.cloneNode(true))">
                <?php else: ?>
                <div class="w-full max-w-[260px] aspect-square rounded-xl border border-slate-700/60 bg-slate-900/60 flex items-center justify-center">
                    <i class="fas fa-cube text-4xl text-slate-700"></i>
                </div>
                <?php endif; ?>
                <template id="img-fallback">
                    <div class="w-full max-w-[260px] aspect-square rounded-xl border border-slate-700/60 bg-slate-900/60 flex items-center justify-center">
                        <i class="fas fa-cube text-4xl text-slate-700"></i>
                    </div>
                </template>
                <?php if (!empty($images)): ?>
                <div class="flex gap-2 flex-wrap justify-center">
                    <?php foreach ($images as $img):
                        $iu = productImageUrl($img['path'] ?? '', $product['name']);
                        if (!$iu) continue;
                    ?>
                    <img src="<?php echo htmlspecialchars($iu); ?>" alt=""
                         class="w-16 h-16 object-cover rounded-lg border border-slate-700/60 cursor-pointer hover:border-amber-500/60 transition-colors">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Description -->
        <?php if (!empty($product['description'])): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <i class="fas fa-align-left text-amber-400 text-xs"></i>
                <span class="text-sm font-semibold text-white">Description</span>
            </div>
            <div class="p-4">
                <p class="text-slate-300 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attributes -->
        <?php if (!empty($attributes)): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <i class="fas fa-sliders-h text-amber-400 text-xs"></i>
                <span class="text-sm font-semibold text-white">Attributes</span>
            </div>
            <div class="p-4 grid grid-cols-2 gap-3">
                <?php foreach ($attributes as $attr): ?>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-0.5"><?php echo htmlspecialchars($attr['attribute_name']); ?></div>
                    <div class="text-sm text-white"><?php echo htmlspecialchars($attr['attribute_value']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tags -->
        <?php if (!empty($tags)): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <i class="fas fa-tags text-amber-400 text-xs"></i>
                <span class="text-sm font-semibold text-white">Tags</span>
            </div>
            <div class="p-4 flex flex-wrap gap-2">
                <?php foreach ($tags as $tag): ?>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-700/60 border border-slate-600 text-slate-300"
                      style="<?php echo !empty($tag['color']) ? 'border-color:'.htmlspecialchars($tag['color']).';color:'.htmlspecialchars($tag['color']).';' : ''; ?>">
                    <?php echo htmlspecialchars($tag['name']); ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Sales -->
        <?php if (!empty($sales_history)): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <i class="fas fa-shopping-cart text-amber-400 text-xs"></i>
                <span class="text-sm font-semibold text-white">Recent Sales</span>
                <span class="ml-auto text-xs text-slate-500"><?php echo count($sales_history); ?> records</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700/40 bg-slate-800/40">
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Qty</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php foreach ($sales_history as $sale): ?>
                        <tr class="hover:bg-slate-700/20 transition-colors">
                            <td class="px-3 py-2">
                                <a href="../pos/receipts/view_sale.php?id=<?php echo (int)$sale['id']; ?>"
                                   class="text-sm font-mono text-amber-400 hover:text-amber-300 transition-colors">
                                    <?php echo htmlspecialchars($sale['invoice_number'] ?: '#' . $sale['id']); ?>
                                </a>
                            </td>
                            <td class="px-3 py-2 text-sm text-slate-400"><?php echo $sale['created_at'] ? date('d M Y', strtotime($sale['created_at'])) : '—'; ?></td>
                            <td class="px-3 py-2 text-sm text-slate-300"><?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?></td>
                            <td class="px-3 py-2 text-sm text-slate-300 text-right"><?php echo (int)$sale['quantity']; ?></td>
                            <td class="px-3 py-2 text-sm font-semibold text-amber-400 text-right"><?php echo format_currency((float)$sale['line_total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="space-y-4">

        <!-- Product Details -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <i class="fas fa-info-circle text-amber-400 text-xs"></i>
                <span class="text-sm font-semibold text-white">Product Details</span>
            </div>
            <div class="p-4 grid grid-cols-2 gap-x-4 gap-y-3">
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-0.5">Status</div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                        <?php echo $product['active']
                            ? 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30'
                            : 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30'; ?>">
                        <i class="fas <?php echo $product['active'] ? 'fa-check' : 'fa-ban'; ?> text-[9px]"></i>
                        <?php echo $product['active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-0.5">Category</div>
                    <div class="text-sm text-white"><?php echo htmlspecialchars($product['category_name'] ?: '—'); ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-0.5">Brand</div>
                    <div class="text-sm text-white"><?php echo htmlspecialchars($product['brand_name'] ?: '—'); ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-0.5">Unit</div>
                    <div class="text-sm text-white"><?php echo htmlspecialchars($product['unit'] ?: 'pcs'); ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-0.5">Tax Rate</div>
                    <div class="text-sm text-white"><?php echo $product['tax_rate'] ? htmlspecialchars($product['tax_rate']) . '%' : '—'; ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-0.5">Reorder Level</div>
                    <div class="text-sm text-white"><?php echo (int)($product['reorder_level'] ?? 0); ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-0.5">Created</div>
                    <div class="text-sm text-slate-300"><?php echo $product['created_at'] ? date('d M Y, H:i', strtotime($product['created_at'])) : '—'; ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-0.5">Updated</div>
                    <div class="text-sm text-slate-300"><?php echo $product['updated_at'] ? date('d M Y, H:i', strtotime($product['updated_at'])) : '—'; ?></div>
                </div>
            </div>
        </div>

        <!-- Inventory -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <i class="fas fa-warehouse text-amber-400 text-xs"></i>
                <span class="text-sm font-semibold text-white">Inventory</span>
                <span class="ml-auto text-xs text-slate-500"><?php echo htmlspecialchars($branch_name); ?></span>
            </div>
            <?php if (empty($inventory)): ?>
            <div class="p-4 text-sm text-slate-500">No inventory records found.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700/40 bg-slate-800/40">
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Branch</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Stock</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Reorder</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php foreach ($inventory as $inv):
                            $s    = (int)$inv['stock'];
                            $rl   = (int)($inv['reorder_level'] ?? 0);
                            $sc   = $s === 0 ? 'text-red-400' : ($s <= $rl ? 'text-amber-400' : 'text-emerald-400');
                            $si   = $s === 0 ? 'fa-circle-xmark' : ($s <= $rl ? 'fa-triangle-exclamation' : 'fa-check-circle');
                        ?>
                        <tr class="hover:bg-slate-700/20 transition-colors">
                            <td class="px-3 py-2 text-sm text-slate-300"><?php echo htmlspecialchars($inv['branch_name'] ?: 'Branch #' . $inv['branch_id']); ?></td>
                            <td class="px-3 py-2 text-right">
                                <span class="<?php echo $sc; ?> text-sm font-semibold flex items-center justify-end gap-1">
                                    <i class="fas <?php echo $si; ?> text-xs"></i><?php echo number_format($s); ?>
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right text-sm text-slate-400"><?php echo number_format($rl); ?></td>
                            <td class="px-3 py-2">
                                <?php if ($s === 0): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/15 text-red-400 ring-1 ring-red-500/30">Out of Stock</span>
                                <?php elseif ($s <= $rl): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30">Low Stock</span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Variants -->
        <?php if (!empty($variants)): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <i class="fas fa-code-branch text-amber-400 text-xs"></i>
                <span class="text-sm font-semibold text-white">Variants</span>
                <span class="ml-auto text-xs text-slate-500"><?php echo count($variants); ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700/40 bg-slate-800/40">
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Name</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">SKU</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php foreach ($variants as $v): ?>
                        <tr class="hover:bg-slate-700/20 transition-colors">
                            <td class="px-3 py-2 text-sm text-slate-300"><?php echo htmlspecialchars($v['name']); ?></td>
                            <td class="px-3 py-2 font-mono text-xs text-slate-500"><?php echo htmlspecialchars($v['sku'] ?: '—'); ?></td>
                            <td class="px-3 py-2 text-sm font-semibold text-amber-400 text-right"><?php echo format_currency((float)$v['price']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Activity Log -->
        <?php if (!empty($stock_movements)): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <i class="fas fa-history text-amber-400 text-xs"></i>
                <span class="text-sm font-semibold text-white">Activity Log</span>
            </div>
            <div class="p-4 space-y-3">
                <?php foreach ($stock_movements as $m): ?>
                <div class="flex items-start gap-3 pb-3 border-b border-slate-700/40 last:border-0 last:pb-0">
                    <div class="w-1.5 h-1.5 rounded-full bg-amber-500 mt-2 shrink-0"></div>
                    <div class="min-w-0">
                        <p class="text-sm text-slate-300"><?php echo htmlspecialchars($m['action']); ?></p>
                        <?php if (!empty($m['details'])): ?>
                        <p class="text-xs text-slate-500 mt-0.5 truncate"><?php echo htmlspecialchars(is_string($m['details']) ? $m['details'] : json_encode($m['details'])); ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-slate-600 mt-0.5"><?php echo date('d M Y, H:i', strtotime($m['created_at'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<script>
    document.addEventListener('keydown', function(e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.key === 'Escape') window.location.href = 'products.php';
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
