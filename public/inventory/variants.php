<?php
/**
 * Product Variants management page for Jakababa POS
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$current_branch_id = get_current_branch_id();

if (!check_permission('products.manage') && !is_super_admin()) {
    enforce_permission('products.manage');
}

$pdo        = get_db_connection();
$tenant_id  = get_current_tenant_id();
if (!$tenant_id) { http_response_code(403); exit('Company context missing.'); }

$user_id    = (int) ($_SESSION['user_id'] ?? 0);
$user_role  = $_SESSION['role'] ?? '';
$branch_name = get_current_branch_name();
$can_delete = is_super_admin() || $user_role === 'Admin';

$csrf_token = generate_csrf_token();

$success_message = '';
$error_message   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $product_id = intval($_POST['product_id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $price      = floatval($_POST['price'] ?? 0);
        $sku        = trim($_POST['sku'] ?? '');
        $errors     = [];

        if ($product_id <= 0) $errors[] = 'Please select a product';
        if (empty($name))     $errors[] = 'Variant name is required';
        if ($price <= 0)      $errors[] = 'Price must be greater than 0';

        if (!empty($sku)) {
            $stmt = $pdo->prepare('SELECT id FROM product_variants WHERE sku = ? AND tenant_id = ?');
            $stmt->execute([$sku, $tenant_id]);
            if ($stmt->fetch()) $errors[] = 'SKU already exists';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('INSERT INTO product_variants (product_id, tenant_id, name, price, sku) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$product_id, $tenant_id, $name, $price, $sku ?: null]);
                $success_message = 'Variant added successfully';
            } catch (PDOException $e) {
                error_log('Error adding variant: ' . $e->getMessage());
                $error_message = 'Failed to add variant';
            }
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}

if (isset($_GET['delete']) && $can_delete) {
    $variant_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare('DELETE FROM product_variants WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$variant_id, $tenant_id]);
        $success_message = 'Variant deleted successfully';
    } catch (PDOException $e) {
        error_log('Error deleting variant: ' . $e->getMessage());
        $error_message = 'Failed to delete variant';
    }
}

// Get products for dropdown (tenant-scoped)
$pstmt = $pdo->prepare('SELECT id, name FROM products WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name');
$pstmt->execute([$tenant_id]);
$products = $pstmt->fetchAll(PDO::FETCH_ASSOC);

// Get variants with product details (tenant-scoped)
$vstmt = $pdo->prepare('
    SELECT v.id, v.name, v.price, v.sku, p.name AS product, p.id AS product_id
    FROM product_variants v
    JOIN products p ON v.product_id = p.id
    WHERE v.tenant_id = ?
    ORDER BY v.id DESC
');
$vstmt->execute([$tenant_id]);
$variants = $vstmt->fetchAll(PDO::FETCH_ASSOC);

$currency = function_exists('get_settings') ? get_settings('currency', 'KES', $tenant_id) : 'KES';

$selected_product = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$avg_price = count($variants) > 0 ? array_sum(array_column($variants, 'price')) / count($variants) : 0;

$page_title = 'Product Variants';
ob_start();
?>

<div class="fade-in">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Inventory</p>
            <h1 class="text-lg font-bold text-white">Product Variants</h1>
            <p class="text-sm text-slate-500 mt-0.5">Managing variants in <span class="text-amber-400"><?php echo htmlspecialchars($branch_name); ?></span></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="../products/products.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-cube text-xs"></i><span class="hidden sm:inline">Products</span>
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($success_message): ?>
    <div id="flash-success" class="mb-4 flex items-center gap-3 px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-sm">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($success_message); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="mb-4 flex items-start gap-3 px-4 py-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm">
        <i class="fas fa-exclamation-triangle mt-0.5"></i>
        <div><?php echo $error_message; ?></div>
    </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
                <i class="fas fa-cube text-amber-400 text-xs"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-white"><?php echo count($products); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Base Products</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center shrink-0">
                <i class="fas fa-layer-group text-purple-400 text-xs"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-white"><?php echo count($variants); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Total Variants</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
                <i class="fas fa-calculator text-emerald-400 text-xs"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-emerald-400"><?php echo $currency . ' ' . number_format($avg_price, 0); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Avg. Price</div>
            </div>
        </div>
    </div>

    <!-- Add Variant Form -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-5">
        <h2 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
            <i class="fas fa-plus-circle text-amber-400 text-xs"></i> Add New Variant
        </h2>
        <?php $inp = 'w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500'; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div>
                    <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1">Product <span class="text-red-400">*</span></label>
                    <select name="product_id" required class="<?php echo $inp; ?>">
                        <option value="">Select Product</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $selected_product == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1">Variant Name <span class="text-red-400">*</span></label>
                    <input type="text" name="name" required placeholder="e.g., Large, Red, 500ml" class="<?php echo $inp; ?>">
                </div>
                <div>
                    <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1">SKU <span class="text-slate-600">(optional)</span></label>
                    <input type="text" name="sku" placeholder="e.g., PRD-RED-L" class="<?php echo $inp; ?>">
                </div>
                <div>
                    <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1">Price <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"><?php echo $currency; ?></span>
                        <input type="number" name="price" required step="0.01" min="0.01" placeholder="0.00"
                            class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                        <i class="fas fa-plus text-xs"></i> Add Variant
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Variants Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-700/40 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-layer-group text-amber-400 text-xs"></i> Variants List
            </h2>
            <span class="text-xs text-slate-500"><?php echo count($variants); ?> total</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-900/50 border-b border-slate-700/60">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-12">#</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Variant Name</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">SKU</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Price</th>
                        <?php if ($can_delete): ?>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider w-16">Delete</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php if (empty($variants)): ?>
                    <tr>
                        <td colspan="<?php echo $can_delete ? 6 : 5; ?>" class="px-4 py-14 text-center">
                            <i class="fas fa-layer-group text-3xl text-slate-700 mb-3 block"></i>
                            <p class="text-slate-400 font-medium">No variants yet</p>
                            <p class="text-slate-500 text-xs mt-1">Add your first variant using the form above.</p>
                        </td>
                    </tr>
                    <?php else: foreach ($variants as $v): ?>
                    <tr class="hover:bg-slate-700/20 transition-colors">
                        <td class="px-3 py-2.5 font-mono text-slate-600"><?php echo (int)$v['id']; ?></td>
                        <td class="px-3 py-2.5">
                            <span class="flex items-center gap-1.5">
                                <i class="fas fa-cube text-amber-400 text-[10px]"></i>
                                <span class="text-slate-200 font-medium"><?php echo htmlspecialchars($v['product']); ?></span>
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-white font-medium"><?php echo htmlspecialchars($v['name']); ?></td>
                        <td class="px-3 py-2.5">
                            <?php if (!empty($v['sku'])): ?>
                            <code class="font-mono text-xs text-slate-400"><?php echo htmlspecialchars($v['sku']); ?></code>
                            <?php else: ?>
                            <span class="text-slate-600">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5 text-right font-semibold text-amber-400"><?php echo $currency . ' ' . number_format((float)$v['price'], 2); ?></td>
                        <?php if ($can_delete): ?>
                        <td class="px-3 py-2.5 text-center">
                            <button onclick="confirmDelete(<?php echo (int)$v['id']; ?>)" title="Delete variant"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 hover:bg-red-500/20 transition-colors">
                                <i class="fas fa-trash text-[10px]"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($variants)): ?>
        <div class="px-4 py-2.5 bg-slate-800/60 border-t border-slate-700/40 text-xs text-slate-500">
            Showing <span class="text-white font-semibold"><?php echo count($variants); ?></span> variant<?php echo count($variants) !== 1 ? 's' : ''; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-sm mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center shrink-0">
                <i class="fas fa-trash text-red-400 text-sm"></i>
            </div>
            <h3 class="text-base font-semibold text-white">Delete Variant</h3>
        </div>
        <p class="text-slate-400 text-sm mb-5">Are you sure you want to delete this variant? This action cannot be undone.</p>
        <div class="flex gap-2">
            <button onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
            <a id="confirmDeleteBtn" href="#" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-semibold hover:bg-red-500/20 transition-colors">Delete</a>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    document.getElementById('confirmDeleteBtn').href = '?delete=' + id;
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});
<?php if ($success_message): ?>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('flash-success');
    if (el) setTimeout(function() { el.style.transition='opacity .5s'; el.style.opacity='0'; setTimeout(function(){ el.remove(); }, 500); }, 4000);
});
<?php endif; ?>
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
