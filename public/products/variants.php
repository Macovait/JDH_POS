<?php
/**
 * Product Variants Management
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
    exit('Company context missing.');
}

$product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
if (!$product_id) {
    header('Location: products.php');
    exit;
}

// Load parent product
$product = $pdo->prepare("SELECT id, name, sku, price, image FROM products WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
$product->execute([$product_id, $tenant_id]);
$product = $product->fetch();
if (!$product) {
    header('Location: products.php');
    exit;
}

$branch_id = get_current_branch_id();

// AJAX handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $response['message'] = 'Invalid security token.';
        echo json_encode($response);
        exit;
    }

    try {
        if ($_POST['ajax_action'] === 'create') {
            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $stock = intval($_POST['stock'] ?? 0);
            $barcode = trim($_POST['barcode'] ?? '');
            if (empty($name)) throw new Exception('Variant name is required');
            $pdo->prepare("INSERT INTO product_variants (tenant_id, product_id, name, sku, price, stock, barcode, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())")
                ->execute([$tenant_id, $product_id, $name, $sku ?: null, $price, $stock, $barcode ?: null]);
            $new_id = $pdo->lastInsertId();
            $response = ['success' => true, 'message' => 'Variant created', 'id' => $new_id];
        } elseif ($_POST['ajax_action'] === 'quick_edit') {
            $variant_id = (int) ($_POST['variant_id'] ?? 0);
            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';
            if (!$variant_id || !in_array($field, ['name','sku','price','stock','barcode'])) throw new Exception('Invalid parameters');
            if ($field === 'name' && empty(trim($value))) throw new Exception('Name cannot be empty');
            $val = in_array($field, ['price']) ? floatval($value) : (in_array($field, ['stock']) ? intval($value) : trim($value));
            $pdo->prepare("UPDATE product_variants SET `$field` = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ? AND product_id = ?")
                ->execute([$val, $variant_id, $tenant_id, $product_id]);
            $response = ['success' => true, 'message' => 'Updated'];
        } elseif ($_POST['ajax_action'] === 'toggle_status') {
            $variant_id = (int) $_POST['variant_id'];
            $current = (int) $_POST['current_status'];
            $new = $current ? 0 : 1;
            $pdo->prepare("UPDATE product_variants SET active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ? AND product_id = ?")
                ->execute([$new, $variant_id, $tenant_id, $product_id]);
            $response = ['success' => true, 'message' => 'Status updated', 'new_status' => $new];
        } elseif ($_POST['ajax_action'] === 'delete') {
            $variant_id = (int) ($_POST['variant_id'] ?? 0);
            if (!$variant_id) throw new Exception('Variant ID required');
            $pdo->prepare("DELETE FROM product_variants WHERE id = ? AND tenant_id = ? AND product_id = ?")
                ->execute([$variant_id, $tenant_id, $product_id]);
            $response = ['success' => true, 'message' => 'Variant deleted'];
        }
    } catch (Exception $e) { $response['message'] = $e->getMessage(); }
    echo json_encode($response);
    exit;
}

$csrf_token = generate_csrf_token();

// Load variants
$variants = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND tenant_id = ? ORDER BY id DESC");
$variants->execute([$product_id, $tenant_id]);
$variants = $variants->fetchAll();

$page_title = 'Variants: ' . htmlspecialchars($product['name']);
ob_start();
?>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 space-y-2 z-50"></div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/60  hidden items-center justify-center z-50 modal">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 rounded-lg bg-red-500/15 border border-red-500/30 flex items-center justify-center shrink-0">
                <i class="fas fa-trash text-red-400 text-sm"></i>
            </div>
            <h3 class="text-sm font-semibold text-white">Delete Variant</h3>
        </div>
        <p class="text-xs text-slate-400 mb-1">Are you sure you want to delete</p>
        <p id="deleteVariantName" class="text-sm font-semibold text-amber-400 mb-3"></p>
        <p class="text-xs text-slate-500 mb-4">This cannot be undone.</p>
        <div class="flex gap-2">
            <button onclick="confirmDelete()" class="flex-1 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-colors"><i class="fas fa-trash mr-1"></i>Delete</button>
            <button onclick="closeModal('deleteModal')" class="flex-1 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">Cancel</button>
        </div>
    </div>
</div>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-0.5">Product Variants</p>
        <h1 class="text-lg font-bold text-white flex items-center gap-2"><i class="fas fa-code-branch text-purple-400"></i> <?php echo htmlspecialchars($product['name']); ?></h1>
        <p class="text-sm text-slate-500 mt-0.5">SKU: <span class="text-slate-300 font-mono"><?php echo htmlspecialchars($product['sku'] ?: '—'); ?></span> &middot; Price: <span class="text-amber-400"><?php echo format_currency((float)$product['price']); ?></span></p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="products.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back
        </a>
        <a href="product_edit.php?id=<?php echo $product['id']; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-pen text-xs"></i> Edit Product
        </a>
    </div>
</div>

<!-- Add Variant Form -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-5">
    <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2"><i class="fas fa-plus text-xs text-emerald-400"></i> Add New Variant</h3>
    <form id="addVariantForm" class="flex flex-wrap gap-2 items-end">
        <input type="hidden" name="ajax_action" value="create">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="flex-1 min-w-[140px]">
            <label class="block text-xs text-slate-500 mb-1">Name</label>
            <input type="text" name="name" required placeholder="e.g. Large, Red, 500ml" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <div class="w-32">
            <label class="block text-xs text-slate-500 mb-1">SKU</label>
            <input type="text" name="sku" placeholder="SKU" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <div class="w-28">
            <label class="block text-xs text-slate-500 mb-1">Price</label>
            <input type="number" name="price" step="0.01" value="<?php echo $product['price']; ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <div class="w-24">
            <label class="block text-xs text-slate-500 mb-1">Stock</label>
            <input type="number" name="stock" value="0" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <div class="w-32">
            <label class="block text-xs text-slate-500 mb-1">Barcode</label>
            <input type="text" name="barcode" placeholder="Barcode" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-500/15 border border-emerald-500/40 text-emerald-400 text-sm font-medium hover:bg-emerald-500/25 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add
        </button>
    </form>
</div>

<!-- Variants Table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[600px]">
            <thead class="bg-slate-800/60 border-b border-slate-700/40">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Variant</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">SKU</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Price</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Stock</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Barcode</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider w-24">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40" id="variantsTableBody">
                <?php if (empty($variants)): ?>
                <tr>
                    <td colspan="7" class="px-4 py-14 text-center">
                        <i class="fas fa-code-branch text-3xl text-slate-700 mb-3 block"></i>
                        <p class="text-slate-400 font-medium mb-1">No variants yet</p>
                        <p class="text-slate-500 text-xs">Use the form above to add your first variant</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($variants as $v): ?>
                <tr id="variant-row-<?php echo $v['id']; ?>" class="hover:bg-slate-700/20 transition-colors">
                    <td class="px-3 py-2.5">
                        <span class="editable cursor-pointer hover:text-amber-400 transition-colors" title="Double-click to edit"
                              data-id="<?php echo $v['id']; ?>" data-field="name" data-value="<?php echo htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8'); ?>"
                              ondblclick="inlineEdit(this)">
                            <?php echo htmlspecialchars($v['name']); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="font-mono text-xs text-slate-400 editable cursor-pointer hover:text-white transition-colors" title="Double-click to edit"
                              data-id="<?php echo $v['id']; ?>" data-field="sku" data-value="<?php echo htmlspecialchars($v['sku'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                              ondblclick="inlineEdit(this)">
                            <?php echo htmlspecialchars($v['sku'] ?: '—'); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right">
                        <span class="text-amber-400 font-medium editable cursor-pointer hover:underline" title="Double-click to edit"
                              data-id="<?php echo $v['id']; ?>" data-field="price" data-value="<?php echo $v['price']; ?>"
                              ondblclick="inlineEdit(this)">
                            <?php echo format_currency((float)$v['price']); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right">
                        <span class="editable cursor-pointer hover:text-white transition-colors" title="Double-click to edit"
                              data-id="<?php echo $v['id']; ?>" data-field="stock" data-value="<?php echo (int)($v['stock'] ?? 0); ?>"
                              ondblclick="inlineEdit(this)">
                            <?php echo (int)($v['stock'] ?? 0); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="font-mono text-xs text-slate-500 editable cursor-pointer hover:text-white transition-colors" title="Double-click to edit"
                              data-id="<?php echo $v['id']; ?>" data-field="barcode" data-value="<?php echo htmlspecialchars($v['barcode'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                              ondblclick="inlineEdit(this)">
                            <?php echo htmlspecialchars($v['barcode'] ?: '—'); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <button onclick="toggleStatus(<?php echo $v['id']; ?>, <?php echo (int)($v['active'] ?? 1); ?>)"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-colors
                                    <?php echo ($v['active'] ?? 1)
                                        ? 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30 hover:bg-emerald-500/25'
                                        : 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30 hover:bg-slate-500/25'; ?>">
                            <i class="fas <?php echo ($v['active'] ?? 1) ? 'fa-check' : 'fa-ban'; ?> text-[9px]"></i>
                            <?php echo ($v['active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                        </button>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-center gap-1">
                            <button onclick="openDeleteModal(<?php echo $v['id']; ?>, '<?php echo htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8'); ?>')" class="p-1.5 rounded-lg text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition-colors" title="Delete">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="px-3 py-2 border-t border-slate-700/60 bg-slate-800/40 text-xs text-slate-500">
        <?php echo count($variants); ?> variant<?php echo count($variants) !== 1 ? 's' : ''; ?>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token); ?>';
const PRODUCT_ID = <?php echo $product_id; ?>;

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const colors = { success: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400', error: 'bg-red-500/15 border-red-500/30 text-red-400', warning: 'bg-amber-500/15 border-amber-500/30 text-amber-400' };
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-triangle-exclamation' };
    toast.className = `flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium shadow-lg ${colors[type] || colors.success}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.success} text-xs"></i> ${message}`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.transition = 'opacity 0.4s'; toast.style.opacity = '0'; setTimeout(() => toast.remove(), 400); }, 3000);
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('hidden'); m.classList.remove('flex'); }
}

// Add variant
 document.getElementById('addVariantForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('variants.php?product_id=' + PRODUCT_ID, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 600); }
            else showToast(data.message || 'Error', 'error');
        })
        .catch(() => showToast('Network error', 'error'));
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
    input.className = 'bg-slate-900 border border-amber-500 rounded px-1.5 py-0.5 text-sm text-white focus:outline-none';
    if (field === 'price' || field === 'stock') input.className += ' w-20 text-right';
    else if (field !== 'barcode') input.className += ' w-32';
    else input.className += ' w-28';
    el.innerHTML = '';
    el.appendChild(input);
    input.focus();
    input.select();

    function save() {
        const newVal = input.value.trim();
        if (newVal === current) { el.textContent = formatDisplay(field, current); return; }
        const formData = new FormData();
        formData.append('ajax_action', 'quick_edit');
        formData.append('variant_id', id);
        formData.append('field', field);
        formData.append('value', newVal);
        formData.append('csrf_token', CSRF_TOKEN);
        fetch('variants.php?product_id=' + PRODUCT_ID, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => { if (data.success) { showToast('Updated', 'success'); setTimeout(() => window.location.reload(), 500); } else { showToast(data.message || 'Error', 'error'); el.textContent = formatDisplay(field, current); } })
            .catch(() => { showToast('Network error', 'error'); el.textContent = formatDisplay(field, current); });
    }
    function cancel() { el.textContent = formatDisplay(field, current); }
    input.addEventListener('blur', save);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); save(); } if (e.key === 'Escape') { e.preventDefault(); cancel(); } });
}

function formatDisplay(field, value) {
    if (field === 'price') return 'KSh ' + parseFloat(value).toFixed(2);
    if (field === 'sku' || field === 'barcode') return value || '—';
    return value;
}

function toggleStatus(variantId, currentStatus) {
    const newStatus = currentStatus ? 0 : 1;
    if (!confirm(`Are you sure you want to ${newStatus ? 'activate' : 'deactivate'} this variant?`)) return;
    const formData = new FormData();
    formData.append('ajax_action', 'toggle_status');
    formData.append('variant_id', variantId);
    formData.append('current_status', currentStatus);
    formData.append('csrf_token', CSRF_TOKEN);
    fetch('variants.php?product_id=' + PRODUCT_ID, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => { if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 600); } else showToast(data.message, 'error'); })
        .catch(() => showToast('Network error', 'error'));
}

let currentDeleteId = null;
function openDeleteModal(id, name) {
    currentDeleteId = id;
    document.getElementById('deleteVariantName').textContent = name;
    const m = document.getElementById('deleteModal');
    m.classList.remove('hidden'); m.classList.add('flex');
}
function confirmDelete() {
    if (!currentDeleteId) return;
    const formData = new FormData();
    formData.append('ajax_action', 'delete');
    formData.append('variant_id', currentDeleteId);
    formData.append('csrf_token', CSRF_TOKEN);
    fetch('variants.php?product_id=' + PRODUCT_ID, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => { closeModal('deleteModal'); if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 600); } else showToast(data.message, 'error'); })
        .catch(() => { closeModal('deleteModal'); showToast('Network error', 'error'); });
}

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); });
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';

