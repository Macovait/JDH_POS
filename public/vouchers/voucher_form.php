<?php
/**
 * Voucher Form - Add/Edit Voucher
 * Standalone page matching Laravel layout style + Tailwind CSS
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('vouchers.manage') && !is_super_admin()) {
    enforce_permission('vouchers.manage');
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? '';
$tenant_id = get_current_tenant_id();

$success_message = '';
$error_message = '';

$db_error = null;
$pdo = null;
try {
    $pdo = get_db_connection();
} catch (Throwable $e) {
    $db_error = 'Unable to connect to the database. Please ensure MySQL is running and try again.';
    error_log('Voucher form database connection failed: ' . $e->getMessage());
}

$currency_symbol = 'KSh';
if ($pdo) {
    try {
        $currency = function_exists('current_currency') ? current_currency() : ['symbol' => get_settings('currency', 'KES')];
        $currency_symbol = $currency['symbol'] ?? 'KSh';
    } catch (Throwable $e) {
        error_log('Voucher form currency lookup failed: ' . $e->getMessage());
    }
}

$voucher_id = intval($_GET['id'] ?? 0);
$is_edit = $voucher_id > 0;

// Auto-generate a unique voucher code for new vouchers
function generate_voucher_code(PDO $pdo, int $tenant_id): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $prefix = substr(str_shuffle($chars), 0, 4);
        $suffix = substr(str_shuffle($chars), 0, 4);
        $code   = $prefix . '-' . $suffix;
        $stmt   = $pdo->prepare('SELECT id FROM vouchers WHERE code = ? AND tenant_id = ?');
        $stmt->execute([$code, $tenant_id]);
    } while ($stmt->fetch());
    return $code;
}

$auto_code = '';
if (!$is_edit && $pdo) {
    $auto_code = generate_voucher_code($pdo, $tenant_id);
}

$voucher = null;
if ($pdo && $is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM vouchers WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        header('Location: vouchers.php?error=' . urlencode('Voucher not found'));
        exit;
    }
}

if (!$pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $error_message = 'Database connection failed. Please ensure MySQL is running, then try again.';
} elseif ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $type = $_POST['type'] ?? 'fixed';
    $value = floatval($_POST['value'] ?? 0);
    $expires = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $active = isset($_POST['active']) ? 1 : 0;
    $min_purchase = floatval($_POST['min_purchase'] ?? 0);
    $max_discount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
    $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $description = trim($_POST['description'] ?? '');

    $errors = [];

    if (empty($code)) {
        $errors[] = 'Voucher code is required';
    }
    if ($value <= 0) {
        $errors[] = 'Value must be greater than 0';
    }
    if ($type === 'percent' && $value > 100) {
        $errors[] = 'Percentage cannot exceed 100%';
    }

    if ($id > 0) {
        $check = $pdo->prepare('SELECT id FROM vouchers WHERE code = ? AND tenant_id = ? AND id != ?');
        $check->execute([$code, $tenant_id, $id]);
        if ($check->fetch()) {
            $errors[] = 'Voucher code already exists';
        }
    } else {
        $check = $pdo->prepare('SELECT id FROM vouchers WHERE code = ? AND tenant_id = ?');
        $check->execute([$code, $tenant_id]);
        if ($check->fetch()) {
            $errors[] = 'Voucher code already exists';
        }
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare('
                    UPDATE vouchers
                    SET code = ?, type = ?, value = ?, expires_at = ?, active = ?,
                        min_purchase = ?, max_discount = ?, usage_limit = ?, description = ?,
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ');
                $stmt->execute([$code, $type, $value, $expires, $active, $min_purchase, $max_discount, $usage_limit, $description, $id, $tenant_id]);

                log_activity($user_id, 'voucher.updated', ['voucher_id' => $id, 'voucher_code' => $code], $tenant_id);

                $success_message = 'Voucher updated successfully';
                header('Location: vouchers.php?success=updated');
                exit;
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO vouchers (tenant_id, code, type, value, expires_at, active, min_purchase, max_discount, usage_limit, description, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([$tenant_id, $code, $type, $value, $expires, $active, $min_purchase, $max_discount, $usage_limit, $description]);

                $new_id = $pdo->lastInsertId();
                log_activity($user_id, 'voucher.created', ['voucher_id' => $new_id, 'voucher_code' => $code], $tenant_id);

                $success_message = 'Voucher added successfully';
                header('Location: vouchers.php?success=created');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Error saving voucher: ' . $e->getMessage());
            $errors[] = 'Database error: Failed to save voucher';
        }
    }

    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    }
}

$page_title = ($is_edit ? 'Edit' : 'Add') . ' Voucher';

ob_start();

$inp = 'w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors';
$lbl = 'block text-xs font-medium text-slate-400 mb-1';
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-ticket-alt text-amber-400"></i>
            <?php echo $is_edit ? 'Edit Voucher' : 'Add New Voucher'; ?>
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo $is_edit ? 'Update voucher details and settings' : 'Create a new discount voucher for your customers'; ?>
        </p>
    </div>
    <div class="shrink-0">
        <a href="vouchers.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to Vouchers
        </a>
    </div>
</div>

<?php if ($db_error): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-database"></i>
    <?php echo htmlspecialchars($db_error); ?>
    <button onclick="window.location.reload()" class="ml-auto underline hover:no-underline">Retry</button>
</div>
<?php endif; ?>

<?php if ($success_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
</div>
<?php endif; ?>

<form method="POST" class="space-y-4 <?php echo $db_error ? 'opacity-60 pointer-events-none' : ''; ?>">
    <input type="hidden" name="id" value="<?php echo $voucher_id; ?>">

    <!-- Basic Information -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-ticket-alt text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Basic Information</span>
        </div>
        <div class="p-4 space-y-4">
            <div>
                <label for="code" class="<?php echo $lbl; ?>">Voucher Code <span class="text-red-400">*</span></label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <i class="fas fa-ticket-alt absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="text" id="code" name="code" required
                               value="<?php echo htmlspecialchars($voucher['code'] ?? $auto_code); ?>"
                               class="<?php echo $inp; ?> pl-8 uppercase tracking-widest font-mono"
                               placeholder="e.g. ABCD-1234"
                               maxlength="50"
                               oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <?php if (!$is_edit): ?>
                    <button type="button" id="btnGenCode"
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 hover:text-white transition-colors" title="Generate new code">
                        <i class="fas fa-sync-alt text-xs"></i> Generate
                    </button>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-slate-600 mt-1">Auto-generated — click Generate for a new one, or type your own.</p>
            </div>
            <div>
                <label for="description" class="<?php echo $lbl; ?>">Description</label>
                <textarea id="description" name="description" rows="2"
                          class="<?php echo $inp; ?>"
                          placeholder="Brief description of this voucher"><?php echo htmlspecialchars($voucher['description'] ?? ''); ?></textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="voucherType" class="<?php echo $lbl; ?>">Type</label>
                    <select id="voucherType" name="type" class="<?php echo $inp; ?>">
                        <option value="fixed"   <?php echo (isset($voucher['type']) && $voucher['type'] === 'fixed')   ? 'selected' : ''; ?>>Fixed Amount</option>
                        <option value="percent" <?php echo (isset($voucher['type']) && $voucher['type'] === 'percent') ? 'selected' : ''; ?>>Percentage</option>
                    </select>
                </div>
                <div>
                    <label for="voucherValue" class="<?php echo $lbl; ?>">Value <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span id="valuePrefix" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs">
                            <?php echo (isset($voucher['type']) && $voucher['type'] === 'percent') ? '%' : htmlspecialchars($currency_symbol); ?>
                        </span>
                        <input type="number" id="voucherValue" name="value" step="0.01" min="0" required
                               value="<?php echo htmlspecialchars($voucher['value'] ?? ''); ?>"
                               class="<?php echo $inp; ?> <?php echo (isset($voucher['type']) && $voucher['type'] === 'percent') ? 'pl-7' : 'pl-10'; ?>"
                               placeholder="0.00">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Voucher Rules -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-sliders-h text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Voucher Rules</span>
        </div>
        <div class="p-4 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="min_purchase" class="<?php echo $lbl; ?>">Min. Purchase</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"><?php echo htmlspecialchars($currency_symbol); ?></span>
                        <input type="number" id="min_purchase" name="min_purchase" step="0.01" min="0"
                               value="<?php echo htmlspecialchars($voucher['min_purchase'] ?? '0'); ?>"
                               class="<?php echo $inp; ?> pl-10" placeholder="0.00">
                    </div>
                </div>
                <div>
                    <label for="max_discount" class="<?php echo $lbl; ?>">Max Discount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"><?php echo htmlspecialchars($currency_symbol); ?></span>
                        <input type="number" id="max_discount" name="max_discount" step="0.01" min="0"
                               value="<?php echo htmlspecialchars($voucher['max_discount'] ?? ''); ?>"
                               class="<?php echo $inp; ?> pl-10" placeholder="No limit">
                    </div>
                </div>
                <div>
                    <label for="usage_limit" class="<?php echo $lbl; ?>">Usage Limit</label>
                    <input type="number" id="usage_limit" name="usage_limit" min="0"
                           value="<?php echo htmlspecialchars($voucher['usage_limit'] ?? ''); ?>"
                           class="<?php echo $inp; ?>" placeholder="Unlimited">
                    <p class="text-xs text-slate-600 mt-1">Total times this voucher can be used</p>
                </div>
            </div>
            <div>
                <label for="expires_at" class="<?php echo $lbl; ?>">Expiry Date</label>
                <input type="date" id="expires_at" name="expires_at"
                       value="<?php echo htmlspecialchars($voucher['expires_at'] ?? ''); ?>"
                       min="<?php echo date('Y-m-d'); ?>"
                       class="<?php echo $inp; ?> max-w-xs">
                <p class="text-xs text-slate-600 mt-1">Leave blank for no expiry</p>
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
                           <?php echo (!isset($voucher['active']) || $voucher['active']) ? 'checked' : ''; ?>>
                    <div class="w-10 h-6 bg-slate-700 rounded-full peer peer-checked:bg-emerald-500 transition-colors"></div>
                    <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                </div>
                <span class="text-sm text-slate-300">Active</span>
                <span class="text-xs text-slate-500">— Inactive vouchers will not be accepted at checkout</span>
            </label>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex gap-2 pt-1">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-check text-xs"></i> <?php echo $is_edit ? 'Update Voucher' : 'Save Voucher'; ?>
        </button>
        <a href="vouchers.php"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            Cancel
        </a>
    </div>
</form>

<script>
    // Generate random voucher code client-side
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    function randCode() {
        const seg = (n) => Array.from({length: n}, () => chars[Math.floor(Math.random() * chars.length)]).join('');
        return seg(4) + '-' + seg(4);
    }
    document.getElementById('btnGenCode')?.addEventListener('click', function () {
        const input = document.getElementById('code');
        input.value = randCode();
        input.classList.add('ring-1', 'ring-amber-500');
        setTimeout(() => input.classList.remove('ring-1', 'ring-amber-500'), 800);
    });

    document.getElementById('voucherType')?.addEventListener('change', function () {
        const prefix = document.getElementById('valuePrefix');
        const valueInput = document.getElementById('voucherValue');
        if (this.value === 'percent') {
            prefix.textContent = '%';
            valueInput.classList.remove('pl-10');
            valueInput.classList.add('pl-7');
            valueInput.max = '100';
            valueInput.step = '0.1';
        } else {
            prefix.textContent = '<?php echo htmlspecialchars($currency_symbol); ?>';
            valueInput.classList.remove('pl-7');
            valueInput.classList.add('pl-10');
            valueInput.removeAttribute('max');
            valueInput.step = '0.01';
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.querySelector('form').submit();
        }
        if (e.key === 'Escape') {
            window.location.href = 'vouchers.php';
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
