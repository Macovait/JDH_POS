<?php
/**
 * Discount Form - Add/Edit Discount
 * Standalone page matching app layout + Tailwind CSS
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('sales.discounts') && !is_super_admin()) {
    enforce_permission('sales.discounts');
}

$user_id    = (int) ($_SESSION['user_id'] ?? 0);
$tenant_id  = (int) get_current_tenant_id();
$branch_id  = (int) ($_SESSION['branch_id'] ?? 1);
$currency   = function_exists('current_currency') ? current_currency() : ['symbol' => 'KSh'];
$currency_symbol = $currency['symbol'] ?? 'KSh';

$success_message = '';
$error_message   = '';
$errors          = [];

$pdo = get_db_connection();
if (!$pdo) {
    $error_message = 'Unable to connect to the database.';
}

$discount_id = intval($_GET['id'] ?? 0);
$is_edit     = $discount_id > 0;

$discount  = null;
$products  = [];
$categories = [];

if ($pdo) {
    $stmt = $pdo->prepare('SELECT id, name FROM products WHERE tenant_id = ? AND active = 1 AND (branch_id = ? OR branch_id = 0 OR branch_id IS NULL) ORDER BY name');
    $stmt->execute([$tenant_id, $branch_id]);
    $products = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE tenant_id = ? AND (branch_id = ? OR branch_id = 0 OR branch_id IS NULL) ORDER BY name');
    $stmt->execute([$tenant_id, $branch_id]);
    $categories = $stmt->fetchAll();

    if ($is_edit) {
        $stmt = $pdo->prepare('SELECT * FROM discounts WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$discount_id, $tenant_id]);
        $discount = $stmt->fetch();
        if (!$discount) {
            header('Location: discounts.php?error=' . urlencode('Discount not found'));
            exit;
        }
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $id                = intval($_POST['id'] ?? 0);
    $name              = trim($_POST['name'] ?? '');
    $type              = $_POST['type'] ?? 'fixed';
    $value             = floatval($_POST['value'] ?? 0);
    $active            = isset($_POST['active']) ? 1 : 0;
    $description       = trim($_POST['description'] ?? '');
    $min_purchase      = floatval($_POST['min_purchase'] ?? 0);
    $max_discount      = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
    $valid_from        = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
    $valid_until       = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    $applicable_products = $_POST['applicable_products'] ?? 'all';
    $product_ids       = isset($_POST['product_ids']) ? json_encode($_POST['product_ids']) : null;
    $category_id       = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $usage_limit       = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $priority          = intval($_POST['priority'] ?? 0);
    $code              = !empty($_POST['code']) ? strtoupper(trim($_POST['code'])) : null;
    $max_per_customer  = !empty($_POST['max_per_customer']) ? intval($_POST['max_per_customer']) : null;

    if (empty($name))           $errors[] = 'Discount name is required';
    if ($value <= 0)            $errors[] = 'Value must be greater than 0';
    if ($type === 'percent' && $value > 100) $errors[] = 'Percentage cannot exceed 100%';

    if ($code !== null) {
        $codeCheck = $pdo->prepare('SELECT id FROM discounts WHERE code = ? AND tenant_id = ? AND id != ?');
        $codeCheck->execute([$code, $tenant_id, $id]);
        if ($codeCheck->fetch()) $errors[] = "Coupon code '{$code}' is already in use";
    }

    if ($valid_from && $valid_until && $valid_from > $valid_until) {
        $errors[] = 'Valid from date must be before valid until date';
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare('
                    UPDATE discounts
                    SET name = ?, type = ?, value = ?, active = ?, description = ?,
                        min_purchase = ?, max_discount = ?, valid_from = ?, valid_until = ?,
                        applicable_products = ?, product_ids = ?, category_id = ?,
                        usage_limit = ?, priority = ?, code = ?, max_per_customer = ?
                    WHERE id = ? AND tenant_id = ?
                ');
                $stmt->execute([
                    $name, $type, $value, $active, $description,
                    $min_purchase, $max_discount, $valid_from, $valid_until,
                    $applicable_products, $product_ids, $category_id,
                    $usage_limit, $priority, $code, $max_per_customer, $id, $tenant_id
                ]);
                log_activity($user_id, 'discount.updated', ['discount_id' => $id, 'discount_name' => $name], $tenant_id);
                header('Location: discounts.php?success=' . urlencode('Discount updated successfully'));
                exit;
            } else {
                $check = $pdo->prepare('SELECT id FROM discounts WHERE name = ? AND tenant_id = ?');
                $check->execute([$name, $tenant_id]);
                if ($check->fetch()) {
                    $errors[] = 'Discount with this name already exists';
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO discounts (
                            name, type, value, active, description, min_purchase,
                            max_discount, valid_from, valid_until, applicable_products,
                            product_ids, category_id, usage_limit, priority,
                            code, max_per_customer, usage_count, tenant_id, branch_id, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW())
                    ');
                    $stmt->execute([
                        $name, $type, $value, $active, $description,
                        $min_purchase, $max_discount, $valid_from, $valid_until,
                        $applicable_products, $product_ids, $category_id,
                        $usage_limit, $priority, $code, $max_per_customer,
                        $tenant_id, $branch_id
                    ]);
                    $new_id = $pdo->lastInsertId();
                    log_activity($user_id, 'discount.created', ['discount_id' => $new_id, 'discount_name' => $name], $tenant_id);
                    header('Location: discounts.php?success=' . urlencode('Discount added successfully'));
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('Error saving discount: ' . $e->getMessage());
            $errors[] = 'Database error: Failed to save discount';
        }
    }

    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    }
}

$page_title = ($is_edit ? 'Edit' : 'Add') . ' Discount';
ob_start();

$inp = 'w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors';
$lbl = 'block text-xs font-medium text-slate-400 mb-1';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-tag text-amber-400"></i>
            <?php echo $is_edit ? 'Edit Discount' : 'Add New Discount'; ?>
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo $is_edit ? 'Update existing discount details' : 'Create a new discount rule or promotion'; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($is_edit): ?>
        <button type="button" onclick="duplicateDiscount(<?php echo $discount_id; ?>)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-sky-500/15 border border-sky-500/30 text-sky-400 text-sm font-medium hover:bg-sky-500/25 transition-colors"
            title="Duplicate this discount">
            <i class="fas fa-copy text-xs"></i> Duplicate
        </button>
        <?php endif; ?>
        <a href="discounts.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to Discounts
        </a>
    </div>
</div>

<?php if ($error_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
</div>
<?php endif; ?>

<form method="POST" class="space-y-4">
    <input type="hidden" name="id" value="<?php echo $discount_id; ?>">

    <!-- Basic Information -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-tag text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Basic Information</span>
        </div>
        <div class="p-4 space-y-4">
            <div>
                <label for="name" class="<?php echo $lbl; ?>">Discount Name <span class="text-red-400">*</span></label>
                <input type="text" id="name" name="name"
                       value="<?php echo htmlspecialchars($discount['name'] ?? ''); ?>"
                       class="<?php echo $inp; ?>" placeholder="e.g. Summer Sale" required>
            </div>
            <div>
                <label for="description" class="<?php echo $lbl; ?>">Description</label>
                <textarea id="description" name="description" rows="2"
                          class="<?php echo $inp; ?>"
                          placeholder="Brief description"><?php echo htmlspecialchars($discount['description'] ?? ''); ?></textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="type" class="<?php echo $lbl; ?>">Type</label>
                    <select id="type" name="type" class="<?php echo $inp; ?>">
                        <option value="fixed"   <?php echo (isset($discount['type']) && $discount['type'] === 'fixed')   ? 'selected' : ''; ?>>Fixed Amount</option>
                        <option value="percent" <?php echo (isset($discount['type']) && $discount['type'] === 'percent') ? 'selected' : ''; ?>>Percentage</option>
                    </select>
                </div>
                <div>
                    <label for="value" class="<?php echo $lbl; ?>">Value <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span id="valuePrefix" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs">
                            <?php echo (isset($discount['type']) && $discount['type'] === 'percent') ? '%' : htmlspecialchars($currency_symbol); ?>
                        </span>
                        <input type="number" id="value" name="value" step="0.01" min="0" required
                               value="<?php echo htmlspecialchars($discount['value'] ?? ''); ?>"
                               class="<?php echo $inp; ?> <?php echo (isset($discount['type']) && $discount['type'] === 'percent') ? 'pl-7' : 'pl-10'; ?>"
                               placeholder="0.00">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Coupon Code -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-ticket text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Coupon Code</span>
            <span class="ml-auto text-xs text-slate-500">Optional — leave blank for auto-applied</span>
        </div>
        <div class="p-4 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="code" class="<?php echo $lbl; ?>">Promo / Coupon Code</label>
                    <div class="relative flex gap-2">
                        <div class="relative flex-1">
                            <i class="fas fa-ticket absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                            <input type="text" id="code" name="code"
                                   value="<?php echo htmlspecialchars($discount['code'] ?? ''); ?>"
                                   class="<?php echo $inp; ?> pl-8 uppercase tracking-widest"
                                   placeholder="SAVE20" maxlength="50"
                                   oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9_-]/g,'')">
                        </div>
                        <button type="button" onclick="generateCode()"
                            class="shrink-0 px-2.5 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors"
                            title="Generate random code">
                            <i class="fas fa-dice text-[10px]"></i>
                        </button>
                        <button type="button" id="copyCodeBtn" onclick="copyCode()"
                            class="shrink-0 px-2.5 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors hidden"
                            title="Copy code">
                            <i class="fas fa-copy text-[10px]"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-600 mt-1">Alphanumeric, hyphens and underscores only.</p>
                </div>
                <div>
                    <label for="max_per_customer" class="<?php echo $lbl; ?>">Max Uses per Customer</label>
                    <input type="number" id="max_per_customer" name="max_per_customer" min="1"
                           value="<?php echo htmlspecialchars($discount['max_per_customer'] ?? ''); ?>"
                           class="<?php echo $inp; ?>" placeholder="Unlimited">
                </div>
            </div>
            <?php if ($is_edit && isset($discount['usage_count'])): ?>
            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900/60 border border-slate-700/40 text-xs text-slate-400">
                <i class="fas fa-chart-bar text-amber-400"></i>
                Used <span class="text-white font-semibold mx-1"><?php echo (int)$discount['usage_count']; ?></span> time<?php echo (int)$discount['usage_count'] !== 1 ? 's' : ''; ?> total
                <?php if (!empty($discount['usage_limit'])): ?>
                &nbsp;/&nbsp; limit: <span class="text-white font-semibold mx-1"><?php echo (int)$discount['usage_limit']; ?></span>
                <div class="ml-2 flex-1 max-w-[120px] h-1.5 bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-500 rounded-full" style="width:<?php echo min(100, round((int)$discount['usage_count'] / max(1,(int)$discount['usage_limit']) * 100)); ?>%"></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Discount Rules -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-sliders-h text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Discount Rules</span>
        </div>
        <div class="p-4 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="priority" class="<?php echo $lbl; ?>">Priority (0–10)</label>
                    <input type="number" id="priority" name="priority" min="0" max="10"
                           value="<?php echo htmlspecialchars($discount['priority'] ?? '5'); ?>"
                           class="<?php echo $inp; ?>">
                    <p class="text-xs text-slate-600 mt-1">Higher priority applied first</p>
                </div>
                <div>
                    <label for="min_purchase" class="<?php echo $lbl; ?>">Min. Purchase</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"><?php echo htmlspecialchars($currency_symbol); ?></span>
                        <input type="number" id="min_purchase" name="min_purchase" step="0.01" min="0"
                               value="<?php echo htmlspecialchars($discount['min_purchase'] ?? '0'); ?>"
                               class="<?php echo $inp; ?> pl-10" placeholder="0.00">
                    </div>
                </div>
                <div>
                    <label for="max_discount" class="<?php echo $lbl; ?>">Max Discount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"><?php echo htmlspecialchars($currency_symbol); ?></span>
                        <input type="number" id="max_discount" name="max_discount" step="0.01" min="0"
                               value="<?php echo htmlspecialchars($discount['max_discount'] ?? ''); ?>"
                               class="<?php echo $inp; ?> pl-10" placeholder="No limit">
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="valid_from" class="<?php echo $lbl; ?>">Valid From</label>
                    <input type="date" id="valid_from" name="valid_from"
                           value="<?php echo htmlspecialchars($discount['valid_from'] ?? ''); ?>"
                           class="<?php echo $inp; ?>">
                </div>
                <div>
                    <label for="valid_until" class="<?php echo $lbl; ?>">Valid Until</label>
                    <input type="date" id="valid_until" name="valid_until"
                           value="<?php echo htmlspecialchars($discount['valid_until'] ?? ''); ?>"
                           class="<?php echo $inp; ?>">
                </div>
                <div>
                    <label for="usage_limit" class="<?php echo $lbl; ?>">Total Usage Limit</label>
                    <input type="number" id="usage_limit" name="usage_limit" min="0"
                           value="<?php echo htmlspecialchars($discount['usage_limit'] ?? ''); ?>"
                           class="<?php echo $inp; ?>" placeholder="Unlimited">
                </div>
            </div>
        </div>
    </div>

    <!-- Live Preview -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-calculator text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Live Preview</span>
            <span class="ml-auto text-xs text-slate-500">See how this discount works</span>
        </div>
        <div class="p-4 space-y-3">
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <label class="<?php echo $lbl; ?>">Sample Purchase Amount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"><?php echo htmlspecialchars($currency_symbol); ?></span>
                        <input type="number" id="previewAmount" step="0.01" min="0"
                               value="1000"
                               class="<?php echo $inp; ?> pl-10"
                               placeholder="Enter amount">
                    </div>
                </div>
                <button type="button" onclick="updatePreview()"
                    class="mt-5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-xs font-medium hover:bg-amber-500/25 transition-colors">
                    <i class="fas fa-play text-[10px]"></i> Calculate
                </button>
            </div>
            <div id="previewResult" class="hidden grid grid-cols-3 gap-3">
                <div class="px-3 py-2 rounded-lg bg-slate-900/60 border border-slate-700/40 text-center">
                    <div class="text-xs text-slate-500">Original</div>
                    <div id="previewOriginal" class="text-sm font-semibold text-white mt-0.5">—</div>
                </div>
                <div class="px-3 py-2 rounded-lg bg-slate-900/60 border border-slate-700/40 text-center">
                    <div class="text-xs text-slate-500">Discount</div>
                    <div id="previewDiscount" class="text-sm font-semibold text-amber-400 mt-0.5">—</div>
                </div>
                <div class="px-3 py-2 rounded-lg bg-slate-900/60 border border-emerald-500/30 text-center">
                    <div class="text-xs text-emerald-400/70">Final Price</div>
                    <div id="previewFinal" class="text-sm font-semibold text-emerald-400 mt-0.5">—</div>
                </div>
            </div>
            <div id="previewAlert" class="hidden text-xs text-amber-400 bg-amber-500/10 border border-amber-500/20 rounded-lg px-3 py-2">
                <i class="fas fa-info-circle mr-1"></i> <span id="previewAlertText"></span>
            </div>
        </div>
    </div>

    <!-- Applicability -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-bullseye text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Applicability</span>
        </div>
        <div class="p-4 space-y-4">
            <div>
                <label for="applicable_products" class="<?php echo $lbl; ?>">Applicable To</label>
                <select id="applicable_products" name="applicable_products" class="<?php echo $inp; ?> max-w-xs">
                    <option value="all"      <?php echo (isset($discount['applicable_products']) && $discount['applicable_products'] === 'all')      ? 'selected' : ''; ?>>All Products</option>
                    <option value="specific" <?php echo (isset($discount['applicable_products']) && $discount['applicable_products'] === 'specific') ? 'selected' : ''; ?>>Specific Products</option>
                    <option value="category" <?php echo (isset($discount['applicable_products']) && $discount['applicable_products'] === 'category') ? 'selected' : ''; ?>>Product Category</option>
                </select>
            </div>

            <div id="productSelection" class="<?php echo (isset($discount['applicable_products']) && $discount['applicable_products'] === 'specific') ? '' : 'hidden'; ?>">
                <label for="product_ids" class="<?php echo $lbl; ?>">Select Products</label>
                <select id="product_ids" name="product_ids[]" multiple size="5"
                        class="<?php echo $inp; ?> max-w-md">
                    <?php
                    $selected_product_ids = [];
                    if (isset($discount['product_ids']) && $discount['product_ids']) {
                        $selected_product_ids = json_decode($discount['product_ids'], true) ?: [];
                    }
                    foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>" <?php echo in_array($product['id'], $selected_product_ids) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($product['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-600 mt-1">Hold Ctrl/Cmd to select multiple</p>
            </div>

            <div id="categorySelection" class="<?php echo (isset($discount['applicable_products']) && $discount['applicable_products'] === 'category') ? '' : 'hidden'; ?>">
                <label for="category_id" class="<?php echo $lbl; ?>">Select Category</label>
                <select id="category_id" name="category_id" class="<?php echo $inp; ?> max-w-xs">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo (isset($discount['category_id']) && $discount['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Status -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-toggle-on text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Status</span>
        </div>
        <div class="p-4">
            <label class="flex items-center gap-3 cursor-pointer select-none w-fit">
                <div class="relative">
                    <input type="checkbox" name="active" value="1" id="activeToggle"
                           class="sr-only peer"
                           <?php echo (!isset($discount['active']) || $discount['active']) ? 'checked' : ''; ?>>
                    <div class="w-10 h-6 bg-slate-700 rounded-full peer peer-checked:bg-emerald-500 transition-colors"></div>
                    <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                </div>
                <span class="text-sm text-slate-300">Active</span>
                <span class="text-xs text-slate-500">— Inactive discounts will not be applied</span>
            </label>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex gap-2 pt-1">
        <button type="submit"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-check text-xs"></i> <?php echo $is_edit ? 'Update Discount' : 'Save Discount'; ?>
        </button>
        <a href="discounts.php"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            Cancel
        </a>
    </div>
</form>

<script>
    const currencySymbol = '<?php echo htmlspecialchars($currency_symbol); ?>';

    // Toggle value prefix
    document.getElementById('type')?.addEventListener('change', function () {
        const prefix = document.getElementById('valuePrefix');
        const valueInput = document.getElementById('value');
        if (this.value === 'percent') {
            prefix.textContent = '%';
            valueInput.classList.remove('pl-10');
            valueInput.classList.add('pl-7');
        } else {
            prefix.textContent = currencySymbol;
            valueInput.classList.remove('pl-7');
            valueInput.classList.add('pl-10');
        }
        updatePreview();
    });

    // Show/hide product/category selection
    document.getElementById('applicable_products')?.addEventListener('change', function () {
        const productSel = document.getElementById('productSelection');
        const categorySel = document.getElementById('categorySelection');
        if (this.value === 'specific') {
            productSel.classList.remove('hidden');
            categorySel.classList.add('hidden');
        } else if (this.value === 'category') {
            productSel.classList.add('hidden');
            categorySel.classList.remove('hidden');
        } else {
            productSel.classList.add('hidden');
            categorySel.classList.add('hidden');
        }
    });

    // Auto-show copy button when code has value
    document.getElementById('code')?.addEventListener('input', function () {
        document.getElementById('copyCodeBtn').classList.toggle('hidden', !this.value);
    });
    // Init copy button visibility
    (function() {
        const c = document.getElementById('code');
        if (c && c.value) document.getElementById('copyCodeBtn').classList.remove('hidden');
    })();

    function generateCode() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        const segments = 2;
        const segLen = 4;
        let code = '';
        for (let s = 0; s < segments; s++) {
            let seg = '';
            for (let i = 0; i < segLen; i++) seg += chars.charAt(Math.floor(Math.random() * chars.length));
            code += (s > 0 ? '-' : '') + seg;
        }
        const input = document.getElementById('code');
        input.value = code;
        input.dispatchEvent(new Event('input'));
        showToast('Generated code: ' + code, 'success');
    }

    function copyCode() {
        const input = document.getElementById('code');
        if (!input.value) return;
        navigator.clipboard.writeText(input.value).then(() => {
            showToast('Code copied to clipboard', 'success');
        }).catch(() => {
            // fallback
            input.select();
            document.execCommand('copy');
            showToast('Code copied to clipboard', 'success');
        });
    }

    function duplicateDiscount(id) {
        if (!confirm('Duplicate this discount?')) return;
        const btn = event.currentTarget;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin text-[10px]"></i>';
        fetch('discounts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'duplicate', id: id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Discount duplicated!', 'success');
                setTimeout(() => window.location.href = 'discount_form.php?id=' + data.new_id, 600);
            } else {
                showToast(data.message || 'Failed to duplicate', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-copy text-[10px]"></i> Duplicate';
            }
        })
        .catch(() => {
            showToast('Network error', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-copy text-[10px]"></i> Duplicate';
        });
    }

    function updatePreview() {
        const type = document.getElementById('type').value;
        const value = parseFloat(document.getElementById('value').value) || 0;
        const minPurchase = parseFloat(document.getElementById('min_purchase').value) || 0;
        const maxDiscount = parseFloat(document.getElementById('max_discount').value) || 0;
        const amount = parseFloat(document.getElementById('previewAmount').value) || 0;
        const resultBox = document.getElementById('previewResult');
        const alertBox = document.getElementById('previewAlert');
        const alertText = document.getElementById('previewAlertText');

        if (!value || amount <= 0) {
            resultBox.classList.add('hidden');
            alertBox.classList.add('hidden');
            return;
        }
        resultBox.classList.remove('hidden');

        let discount = 0;
        if (type === 'percent') {
            discount = amount * (value / 100);
        } else {
            discount = value;
        }
        if (maxDiscount > 0 && discount > maxDiscount) discount = maxDiscount;
        let finalPrice = amount - discount;
        if (finalPrice < 0) finalPrice = 0;

        document.getElementById('previewOriginal').textContent = currencySymbol + amount.toFixed(2);
        document.getElementById('previewDiscount').textContent = '-' + currencySymbol + discount.toFixed(2) + (type === 'percent' ? ' (' + value + '%)' : '');
        document.getElementById('previewFinal').textContent = currencySymbol + finalPrice.toFixed(2);

        if (minPurchase > 0 && amount < minPurchase) {
            alertBox.classList.remove('hidden');
            alertText.textContent = 'Minimum purchase of ' + currencySymbol + minPurchase.toFixed(2) + ' required — discount will not apply.';
            document.getElementById('previewFinal').textContent = currencySymbol + amount.toFixed(2);
            document.getElementById('previewDiscount').textContent = 'N/A';
            document.getElementById('previewFinal').parentElement.classList.replace('border-emerald-500/30', 'border-slate-700/40');
            document.getElementById('previewFinal').classList.replace('text-emerald-400', 'text-slate-400');
        } else {
            alertBox.classList.add('hidden');
            document.getElementById('previewFinal').parentElement.classList.replace('border-slate-700/40', 'border-emerald-500/30');
            document.getElementById('previewFinal').classList.replace('text-slate-400', 'text-emerald-400');
        }
    }

    // Auto-update preview on relevant field changes
    ['value','min_purchase','max_discount'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', updatePreview);
    });

    // AJAX form submission
    document.querySelector('form')?.addEventListener('submit', function(e) {
        // Only intercept if JS is fully loaded — let normal POST happen if fetch fails
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Saving...';

        fetch(window.location.href, {
            method: 'POST',
            body: new FormData(this)
        })
        .then(r => {
            if (r.redirected) {
                window.location.href = r.url;
                return;
            }
            return r.text();
        })
        .then(html => {
            if (!html) return; // redirect handled
            // Check for error messages in returned HTML
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const err = doc.querySelector('.bg-red-500\\/10');
            if (err) {
                // Show error on current page
                let existing = document.querySelector('.ajax-error');
                if (!existing) {
                    existing = document.createElement('div');
                    existing.className = 'ajax-error mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm';
                    document.querySelector('form').before(existing);
                }
                existing.innerHTML = err.innerHTML;
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            } else {
                // Success — likely a redirect was expected but didn't happen
                showToast('Discount saved successfully!', 'success');
                setTimeout(() => window.location.href = 'discounts.php?success=' + encodeURIComponent('Discount saved successfully'), 600);
            }
        })
        .catch(() => {
            // Fallback: submit normally
            this.submit();
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.querySelector('form')?.dispatchEvent(new Event('submit'));
        }
    });

    // Show toast helper
    function showToast(message, type) {
        const container = document.getElementById('toastContainer') || (function(){
            const c = document.createElement('div');
            c.id = 'toastContainer';
            c.className = 'fixed bottom-4 right-4 space-y-2 z-50';
            document.body.appendChild(c);
            return c;
        })();
        const toast = document.createElement('div');
        const icons = { success: 'fa-check-circle', error: 'fa-circle-xmark', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
        toast.className = 'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium shadow-lg transition-all duration-300 ' +
            (type === 'success' ? 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-400' :
             type === 'error' ? 'bg-red-500/15 border border-red-500/30 text-red-400' :
             'bg-amber-500/15 border border-amber-500/30 text-amber-400');
        toast.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '"></i><span>' + message + '</span>';
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
