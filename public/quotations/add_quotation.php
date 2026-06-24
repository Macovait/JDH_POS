<?php
/**
 * Add Quotation Page for Jakababa POS
 * Create and manage customer quotations with product selection
 * ZERO-TRUST TENANT ISOLATION - All data access is scoped to company/branch
 * Pure Tailwind CSS - Complete working version
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('quotations.create') && !is_super_admin()) {
    enforce_permission('quotations.create');
}

$page_title = 'New Quotation';
$user_id = get_current_user_id();
$user_name = htmlspecialchars(get_current_user_name());
$user_role = $_SESSION['role'] ?? '';
$tenant_id = get_current_tenant_id();

// ZERO-TRUST: Block if no tenant context
if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

$current_branch_id = get_current_branch_id();
$current_branch_name = get_current_branch_name();

$pdo = get_db_connection();

// ============================================
// ZERO-TRUST: Permission Checks
// ============================================
$is_super_admin = is_super_admin();
$can_view_all_branches = $is_super_admin || check_permission('branches.view');

// Get all branches (only if user has permission)
$branches = [];
if ($can_view_all_branches) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, business_type_id FROM branches WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name");
        $stmt->execute([$tenant_id]);
        $branches = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching branches: " . $e->getMessage());
    }
}

// ============================================
// LOAD BUSINESS TYPES
// ============================================
$business_types = [];
try {
    $stmt = $pdo->prepare("SELECT id, code, name, icon, color FROM business_types WHERE is_active = 1 ORDER BY sort_order, name");
    $stmt->execute();
    $business_types = $stmt->fetchAll();
} catch (Exception $e) {
    $business_types = [
        ['id' => 1, 'code' => 'retail', 'name' => 'Retail Store', 'icon' => 'fa-store', 'color' => '#3B82F6'],
        ['id' => 2, 'code' => 'supermarket', 'name' => 'Supermarket', 'icon' => 'fa-shopping-cart', 'color' => '#10B981'],
        ['id' => 3, 'code' => 'restaurant', 'name' => 'Restaurant', 'icon' => 'fa-utensils', 'color' => '#F59E0B'],
    ];
}

// ============================================
// ZERO-TRUST: Fetch products (tenant/branch scoped)
// ============================================
$products = [];
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('products', $tables)) {
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.name,
                p.sku,
                COALESCE(p.selling_price, p.price, 0) as selling_price,
                p.cost_price,
                p.image,
                p.category_id,
                c.name as category_name,
                c.color as category_color,
                COALESCE(i.stock, 0) as stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
            WHERE p.tenant_id = ? AND p.deleted_at IS NULL AND (p.active = 1 OR p.active IS NULL)
            ORDER BY p.name
        ");
        $stmt->execute([$current_branch_id, $tenant_id]);
        $products = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
}

// ============================================
// ZERO-TRUST: Fetch customers (tenant scoped)
// ============================================
$customers = [];
try {
    if (in_array('customers', $tables)) {
        $stmt = $pdo->prepare("
            SELECT id, name, phone, email, credit_limit, current_balance
            FROM customers
            WHERE tenant_id = ? AND deleted_at IS NULL AND (status = 1 OR status IS NULL)
            ORDER BY name
            LIMIT 100
        ");
        $stmt->execute([$tenant_id]);
        $customers = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Error fetching customers: " . $e->getMessage());
}

// ============================================
// Get company settings
// ============================================
$tax_rate = 16;
$currency_symbol = 'KSh';
$company_name = 'Jakababa POS';

try {
    if (in_array('settings', $tables)) {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ? AND setting_key IN ('tax_rate', 'currency', 'company_name')");
        $stmt->execute([$tenant_id]);
        while ($row = $stmt->fetch()) {
            if ($row['setting_key'] === 'tax_rate') $tax_rate = floatval($row['setting_value']);
            if ($row['setting_key'] === 'currency') $currency_symbol = $row['setting_value'];
            if ($row['setting_key'] === 'company_name') $company_name = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Generate quotation number
$quotation_number = 'Q' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

$csrf_token = generate_csrf_token();

// Get branch business type
$branch_business_type_id = null;
foreach ($branches as $b) {
    if ($b['id'] == $current_branch_id && !empty($b['business_type_id'])) {
        $branch_business_type_id = $b['business_type_id'];
        break;
    }
}

ob_start();
?>

<style>
    input[type="number"]::-webkit-inner-spin-button,
    input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type="number"] { -moz-appearance: textfield; }
    .product-row { transition: all 0.2s ease; background: rgba(15,23,42,0.4); border: 1px solid transparent; border-radius: 0.75rem; }
    .product-row:hover { background: rgba(30,41,59,0.6); border-color: rgba(251,191,36,0.3); }
    .product-row-remove { opacity: 0; transition: opacity 0.2s; }
    .product-row:hover .product-row-remove { opacity: 1; }
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
    .modal-overlay.active { opacity: 1; visibility: visible; }
    .toast-container { position: fixed; bottom: 1rem; right: 1rem; z-index: 9999; }
    .toast { background: #1e293b; border-left: 3px solid; padding: 0.75rem 1rem; border-radius: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 0.75rem; min-width: 250px; }
    .toast.success { border-left-color: #10b981; }
    .toast.error { border-left-color: #ef4444; }
    .toast.warning { border-left-color: #f59e0b; }
    .toast.info { border-left-color: #fbbf24; }
    .toast.success i:first-child { color: #10b981; }
    .toast.error i:first-child { color: #ef4444; }
    input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.6); cursor: pointer; }
</style>

<div class="space-y-4">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2 text-[10px] font-semibold text-amber-400 uppercase tracking-wider mb-1">
                <i class="fas fa-file-invoice text-xs"></i>
                <span>Quotations</span>
            </div>
            <h1 class="text-2xl font-bold text-white">New Quotation</h1>
            <p class="text-sm text-slate-500 mt-0.5">Create a new customer quotation</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400 bg-slate-800/60 border border-slate-700/60 px-2.5 py-1 rounded-full">
                <i class="fas fa-store mr-1 text-amber-400/60"></i> <?php echo htmlspecialchars($current_branch_name); ?>
            </span>
            <span class="text-xs text-slate-400 bg-slate-800/60 border border-slate-700/60 px-2.5 py-1 rounded-full font-mono">
                <i class="fas fa-tag mr-1 text-amber-400/60"></i> <?php echo $quotation_number; ?>
            </span>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-cube text-purple-400 text-xs"></i>
                </div>
                <div><p class="text-xs text-slate-500 uppercase tracking-wider">Products</p><p class="text-sm font-bold text-white"><?php echo count($products); ?></p></div>
            </div>
        </div>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-users text-emerald-400 text-xs"></i>
                </div>
                <div><p class="text-xs text-slate-500 uppercase tracking-wider">Customers</p><p class="text-sm font-bold text-white"><?php echo count($customers); ?></p></div>
            </div>
        </div>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-calendar text-amber-400 text-xs"></i>
                </div>
                <div><p class="text-xs text-slate-500 uppercase tracking-wider">Valid Until</p><p class="text-sm font-bold text-white"><?php echo date('d M Y', strtotime('+7 days')); ?></p></div>
            </div>
        </div>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-tag text-blue-400 text-xs"></i>
                </div>
                <div><p class="text-xs text-slate-500 uppercase tracking-wider">Tax Rate</p><p class="text-sm font-bold text-white"><?php echo $tax_rate; ?>%</p></div>
            </div>
        </div>
    </div>

    <!-- Main Form -->
    <form id="quotationForm" method="POST" action="process_quotation.php" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="quotation_number" value="<?php echo htmlspecialchars($quotation_number); ?>">
        <input type="hidden" name="business_type_id" value="<?php echo $branch_business_type_id; ?>">

        <!-- Customer & Validity -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <h2 class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                <i class="fas fa-user text-xs"></i> Customer & Validity
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Select Customer <span class="text-red-400">*</span></label>
                    <select id="customer_id" name="customer_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors">
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>">
                                <?php echo htmlspecialchars($customer['name']); ?>
                                <?php if (!empty($customer['phone'])): ?> - <?php echo $customer['phone']; ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Valid Until</label>
                    <input type="date" id="valid_until" name="valid_until" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors">
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-3">
                <h2 class="text-xs font-semibold text-amber-400 uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-cube text-xs"></i> Products
                </h2>
                <div class="flex gap-2 w-full sm:w-auto">
                    <div class="relative flex-1 sm:w-48">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="text" id="productSearch" placeholder="Search products..."
                            class="w-full pl-8 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors">
                    </div>
                    <button type="button" onclick="addProductRow()"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
                        <i class="fas fa-plus text-xs"></i> Add
                    </button>
                </div>
            </div>

            <div id="products-container" class="space-y-2 max-h-[400px] overflow-y-auto pr-1"></div>

            <div id="emptyState" class="text-center py-8">
                <i class="fas fa-shopping-cart text-3xl text-slate-700 mb-2 block"></i>
                <p class="text-slate-500 text-sm">No items added</p>
                <p class="text-xs text-slate-600 mt-0.5">Click "Add" to start</p>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-700/50">
                <div class="flex flex-col items-end">
                    <div class="w-full md:w-64 space-y-1.5 text-sm">
                        <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span id="subtotal" class="font-mono text-slate-200"><?php echo $currency_symbol; ?> 0</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Tax (<?php echo $tax_rate; ?>%)</span><span id="tax" class="font-mono text-slate-200"><?php echo $currency_symbol; ?> 0</span></div>
                        <div class="flex justify-between pt-1.5 border-t border-slate-700/50 font-bold"><span class="text-amber-400">Total</span><span id="total" class="text-amber-400 font-mono"><?php echo $currency_symbol; ?> 0</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes & Terms -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <h2 class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                <i class="fas fa-file-alt text-xs"></i> Notes & Terms
            </h2>
            <textarea name="notes" rows="2" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-amber-500/60 resize-none transition-colors" placeholder="Additional notes..."></textarea>
            <textarea name="terms" rows="2" class="w-full mt-2 px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-amber-500/60 resize-none transition-colors" placeholder="Terms and conditions...">Payment due within 30 days. Prices valid for 7 days.</textarea>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-2 justify-end">
            <button type="submit" name="action" value="save" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-500/15 border border-emerald-500/30 rounded-lg text-emerald-400 text-sm font-medium hover:bg-emerald-500/25 transition-colors"><i class="fas fa-check text-xs"></i> Create Quotation</button>
            <button type="submit" name="action" value="draft" class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors"><i class="fas fa-save text-xs"></i> Save Draft</button>
            <a href="list_quotation.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</a>
        </div>
    </form>
</div>

<!-- Product Search Modal -->
<div id="productSearchModal" class="modal-overlay">
    <div class="bg-slate-800 rounded-xl border border-slate-700 w-full max-w-2xl max-h-[80vh] overflow-hidden shadow-2xl">
        <div class="px-4 py-3 border-b border-slate-700 flex justify-between items-center">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2"><i class="fas fa-search text-amber-400 text-xs"></i> Select Product</h3>
            <button onclick="closeProductSearch()" class="text-slate-400 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-4">
            <div class="relative mb-3"><i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i><input type="text" id="modalProductSearch" placeholder="Search products..." class="w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors"></div>
            <div id="modalProductList" class="space-y-2 max-h-96 overflow-y-auto"></div>
        </div>
    </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script>
const products = <?php echo json_encode($products); ?>;
let rowCount = 0;
const taxRate = <?php echo $tax_rate; ?>;
const currencySymbol = '<?php echo $currency_symbol; ?>';

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') + '"></i><span>' + message + '</span>';
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 4000);
}

function updateEmptyState() {
    const container = document.getElementById('products-container');
    const emptyState = document.getElementById('emptyState');
    if (emptyState) emptyState.style.display = container.children.length === 0 ? 'block' : 'none';
}

function addProductRow(productId = null, quantity = 1, price = null) {
    const container = document.getElementById('products-container');
    const emptyState = document.getElementById('emptyState');
    if (emptyState) emptyState.style.display = 'none';

    const row = document.createElement('div');
    row.className = 'product-row grid grid-cols-12 gap-2 items-center p-2';
    row.id = `row-${rowCount}`;

    let productOptions = '<option value="">Select Product</option>';
    products.forEach(p => {
        const selected = (productId && p.id == productId) ? 'selected' : '';
        productOptions += `<option value="${p.id}" data-price="${p.selling_price}" data-stock="${p.stock || 0}" data-name="${p.name}" ${selected}>${p.name}</option>`;
    });

    row.innerHTML = `
        <div class="col-span-5"><select name="products[${rowCount}][id]" onchange="updateProductDetails(${rowCount})" id="product-${rowCount}" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors" required>${productOptions}</select></div>
        <div class="col-span-2"><input type="number" name="products[${rowCount}][qty]" id="qty-${rowCount}" onchange="calculateRow(${rowCount})" min="1" value="${quantity}" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm text-center focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors" required></div>
        <div class="col-span-2"><input type="number" name="products[${rowCount}][price]" id="price-${rowCount}" onchange="calculateRow(${rowCount})" step="0.01" value="${price || ''}" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm text-right focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors" required></div>
        <div class="col-span-2 text-center text-sm font-semibold text-amber-400"><span id="row-total-${rowCount}">${currencySymbol} 0</span></div>
        <div class="col-span-1 text-right"><button type="button" onclick="removeRow(${rowCount})" class="product-row-remove w-7 h-7 rounded-lg bg-red-500/10 flex items-center justify-center text-red-400 hover:bg-red-500/20 transition-colors ml-auto"><i class="fas fa-trash text-xs"></i></button></div>
    `;
    container.appendChild(row);
    if (productId && price === null) updateProductDetails(rowCount);
    rowCount++;
}

function updateProductDetails(rowId) {
    const select = document.getElementById(`product-${rowId}`);
    const priceInput = document.getElementById(`price-${rowId}`);
    const selected = select.options[select.selectedIndex];
    if (selected && selected.value && !priceInput.value) priceInput.value = selected.dataset.price;
    calculateRow(rowId);
}

function calculateRow(rowId) {
    const qty = parseFloat(document.getElementById(`qty-${rowId}`).value) || 0;
    const price = parseFloat(document.getElementById(`price-${rowId}`).value) || 0;
    document.getElementById(`row-total-${rowId}`).innerText = `${currencySymbol} ${(qty * price).toLocaleString()}`;
    calculateSummary();
}

function calculateSummary() {
    let subtotal = 0;
    for (let i = 0; i < rowCount; i++) {
        const rowTotal = document.getElementById(`row-total-${i}`);
        if (rowTotal) subtotal += parseFloat(rowTotal.innerText.replace(/[^0-9.-]/g, '')) || 0;
    }
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    document.getElementById('subtotal').innerText = `${currencySymbol} ${subtotal.toLocaleString()}`;
    document.getElementById('tax').innerText = `${currencySymbol} ${tax.toLocaleString()}`;
    document.getElementById('total').innerText = `${currencySymbol} ${total.toLocaleString()}`;
}

function removeRow(rowId) {
    const row = document.getElementById(`row-${rowId}`);
    if (row) { row.remove(); calculateSummary(); updateEmptyState(); }
}

// Product search
document.getElementById('productSearch').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    if (term.length < 2) return;
    const modal = document.getElementById('productSearchModal');
    const modalList = document.getElementById('modalProductList');
    const filtered = products.filter(p => p.name.toLowerCase().includes(term) || (p.category_name && p.category_name.toLowerCase().includes(term)) || (p.sku && p.sku.toLowerCase().includes(term)));
    modalList.innerHTML = filtered.map(p => `<div class="px-3 py-2 bg-slate-800/60 border border-slate-700/40 rounded-lg hover:bg-slate-700/60 hover:border-amber-500/30 cursor-pointer transition-colors" onclick="selectProductFromSearch(${p.id}, '${p.name.replace(/'/g, "\\'")}', ${p.selling_price})"><div class="flex justify-between items-center"><div><p class="text-sm text-white font-medium">${p.name}</p><p class="text-xs text-slate-500 mt-0.5">Stock: ${p.stock || 0}</p></div><p class="text-sm font-bold text-amber-400 ml-3">${currencySymbol} ${Number(p.selling_price).toLocaleString()}</p></div></div>`).join('');
    modal.classList.add('active');
});

document.getElementById('modalProductSearch').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    const modalList = document.getElementById('modalProductList');
    const filtered = products.filter(p => p.name.toLowerCase().includes(term) || (p.category_name && p.category_name.toLowerCase().includes(term)) || (p.sku && p.sku.toLowerCase().includes(term)));
    modalList.innerHTML = filtered.map(p => `<div class="px-3 py-2 bg-slate-800/60 border border-slate-700/40 rounded-lg hover:bg-slate-700/60 hover:border-amber-500/30 cursor-pointer transition-colors" onclick="selectProductFromSearch(${p.id}, '${p.name.replace(/'/g, "\\'")}', ${p.selling_price})"><div class="flex justify-between items-center"><div><p class="text-sm text-white font-medium">${p.name}</p><p class="text-xs text-slate-500 mt-0.5">Stock: ${p.stock || 0}</p></div><p class="text-sm font-bold text-amber-400 ml-3">${currencySymbol} ${Number(p.selling_price).toLocaleString()}</p></div></div>`).join('');
});

function closeProductSearch() { document.getElementById('productSearchModal').classList.remove('active'); }
function selectProductFromSearch(id, name, price) { addProductRow(id, 1, price); closeProductSearch(); showToast(`Added ${name}`, 'success'); }

// Form validation
document.getElementById('quotationForm').addEventListener('submit', function(e) {
    let hasProducts = false;
    for (let i = 0; i < rowCount; i++) {
        const select = document.getElementById(`product-${i}`);
        const qty = document.getElementById(`qty-${i}`);
        if (select && select.value && qty && parseFloat(qty.value) > 0) { hasProducts = true; break; }
    }
    if (!hasProducts) { e.preventDefault(); showToast('Please add at least one product', 'error'); }
});

// Add first row
addProductRow();

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.target.matches('input, textarea, select')) return;
    if (e.ctrlKey && e.key === 's') { e.preventDefault(); document.querySelector('button[name="action"][value="save"]').click(); }
    if (e.ctrlKey && e.key === 'd') { e.preventDefault(); document.querySelector('button[name="action"][value="draft"]').click(); }
    if (e.key === 'Escape') closeProductSearch();
});

// Close modal on outside click
document.getElementById('productSearchModal').addEventListener('click', function(e) {
    if (e.target === this) closeProductSearch();
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>