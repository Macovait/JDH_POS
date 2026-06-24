<?php
/**
 * Tax Rate add/edit form for Jakababa POS
 * Standalone form extracted from tax_rates.php
 */

require_once __DIR__ . '/../../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

if (!is_super_admin() && ($_SESSION['user']['role'] ?? '') !== 'Admin') {
    header('Location: tax_rates.php?error=' . urlencode('Access denied'));
    exit;
}

require_once __DIR__ . '/../../../src/db.php';
require_once __DIR__ . '/../../../src/functions.php';

$pdo = get_db_connection();
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$tenant_id = get_current_tenant_id();

$tax_types = ['vat' => 'VAT (Value Added Tax)', 'gst' => 'GST (Goods & Services Tax)', 'sales' => 'Sales Tax', 'income' => 'Income Tax', 'withholding' => 'Withholding Tax', 'excise' => 'Excise Duty', 'custom' => 'Custom Duty', 'other' => 'Other Tax'];
$countries = ['KE' => 'Kenya', 'UG' => 'Uganda', 'TZ' => 'Tanzania', 'RW' => 'Rwanda', 'BI' => 'Burundi', 'SS' => 'South Sudan', 'ET' => 'Ethiopia', 'ZA' => 'South Africa', 'NG' => 'Nigeria', 'GH' => 'Ghana', 'US' => 'United States', 'UK' => 'United Kingdom', 'EU' => 'European Union', 'OTHER' => 'Other'];

// Fetch existing tax rate for edit
$tax = null;
$tax_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($tax_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM tax_rates WHERE id = ?');
    $stmt->execute([$tax_id]);
    $tax = $stmt->fetch();
    if (!$tax) {
        header('Location: tax_rates.php?error=' . urlencode('Tax rate not found'));
        exit;
    }
}

$is_edit = $tax_id > 0;

// Handle POST
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $rate = floatval($_POST['rate'] ?? 0);
    $type = $_POST['type'] ?? 'vat';
    $description = trim($_POST['description'] ?? '');
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    $is_compound = isset($_POST['is_compound']) ? 1 : 0;
    $is_recoverable = isset($_POST['is_recoverable']) ? 1 : 0;
    $effective_from = $_POST['effective_from'] ?? date('Y-m-d');
    $effective_to = $_POST['effective_to'] ?? null;
    $country = trim($_POST['country'] ?? 'KE');
    $region = trim($_POST['region'] ?? '');
    $tax_number = trim($_POST['tax_number'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) $errors[] = 'Tax name is required';
    if ($rate < 0 || $rate > 100) $errors[] = 'Tax rate must be between 0 and 100 percent';

    if (empty($errors)) {
        try {
            if ($is_default) {
                $pdo->exec("UPDATE tax_rates SET is_default = 0 WHERE is_default = 1");
            }
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE tax_rates SET name=?, rate=?, type=?, description=?, is_default=?, is_compound=?, is_recoverable=?, effective_from=?, effective_to=?, country=?, region=?, tax_number=?, is_active=?, updated_at=NOW() WHERE id=?');
                $stmt->execute([$name, $rate, $type, $description, $is_default, $is_compound, $is_recoverable, $effective_from, $effective_to, $country, $region, $tax_number, $is_active, $id]);
                log_activity($user_id, 'tax_rate.updated', ['tax_id' => $id, 'tax_name' => $name, 'rate' => $rate], $tenant_id);
                header('Location: tax_rates.php?success=' . urlencode('Tax rate updated successfully'));
                exit;
            } else {
                $check = $pdo->prepare('SELECT id FROM tax_rates WHERE name = ?');
                $check->execute([$name]);
                if ($check->fetch()) {
                    $errors[] = 'Tax rate with this name already exists';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO tax_rates (name,rate,type,description,is_default,is_compound,is_recoverable,effective_from,effective_to,country,region,tax_number,is_active,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
                    $stmt->execute([$name, $rate, $type, $description, $is_default, $is_compound, $is_recoverable, $effective_from, $effective_to, $country, $region, $tax_number, $is_active, $user_id]);
                    log_activity($user_id, 'tax_rate.created', ['tax_name' => $name, 'rate' => $rate], $tenant_id);
                    header('Location: tax_rates.php?success=' . urlencode('Tax rate created successfully'));
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log("Error saving tax rate: " . $e->getMessage());
            $errors[] = 'Database error: Failed to save tax rate';
        }
    }
}

$page_title = ($is_edit ? 'Edit' : 'Create') . ' Tax Rate | JDH POS';
ob_start();
?>

<div class="max-w-3xl mx-auto ">
    <div class="flex items-center gap-3 mb-6">
        <a href="tax_rates.php" class="text-gray-400 hover:text-white transition">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wider"><?php echo $is_edit ? 'Edit' : 'New'; ?></div>
            <h1 class="text-2xl font-bold text-white"><?php echo $is_edit ? 'Edit' : 'Create'; ?> Tax Rate</h1>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 bg-red-500/10 border border-red-500 rounded-xl p-4">
            <div class="flex items-start gap-3 text-red-400">
                <i class="fas fa-exclamation-triangle flex-shrink-0 mt-0.5"></i>
                <div class="text-white"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card p-6">
        <form method="POST" class="space-y-5" id="taxForm">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="id" value="<?php echo $tax_id; ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Tax Name *</label>
                    <input type="text" name="name" required
                        value="<?php echo htmlspecialchars($tax['name'] ?? ''); ?>"
                        class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none"
                        placeholder="e.g., VAT 16%">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Tax Rate (%) *</label>
                    <input type="number" name="rate" required step="0.01" min="0" max="100"
                        value="<?php echo isset($tax['rate']) ? $tax['rate'] : ''; ?>"
                        class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none"
                        placeholder="16.00">
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Tax Type</label>
                <select name="type" class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none">
                    <?php foreach ($tax_types as $v => $l): ?>
                        <option value="<?php echo $v; ?>" <?php echo (isset($tax['type']) && $tax['type'] === $v) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Description</label>
                <textarea name="description" rows="2"
                    class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none"
                    placeholder="Brief description"><?php echo htmlspecialchars($tax['description'] ?? ''); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_compound" id="is_compound" value="1" <?php echo (!empty($tax['is_compound'])) ? 'checked' : ''; ?> class="rounded accent-[#FBBF24]">
                    <label for="is_compound" class="text-sm text-gray-400">Compound Tax</label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_recoverable" id="is_recoverable" value="1" <?php echo (!empty($tax['is_recoverable'])) ? 'checked' : ''; ?> class="rounded accent-[#FBBF24]">
                    <label for="is_recoverable" class="text-sm text-gray-400">Recoverable</label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_default" id="is_default" value="1" <?php echo (!empty($tax['is_default'])) ? 'checked' : ''; ?> class="rounded accent-[#FBBF24]">
                    <label for="is_default" class="text-sm text-gray-400">Set as Default</label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Effective From</label>
                    <input type="date" name="effective_from"
                        value="<?php echo htmlspecialchars($tax['effective_from'] ?? date('Y-m-d')); ?>"
                        class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Effective To (Optional)</label>
                    <input type="date" name="effective_to"
                        value="<?php echo htmlspecialchars($tax['effective_to'] ?? ''); ?>"
                        class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Country</label>
                    <select name="country" class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none">
                        <?php foreach ($countries as $c => $n): ?>
                            <option value="<?php echo $c; ?>" <?php echo (isset($tax['country']) && $tax['country'] === $c) || (!isset($tax['country']) && $c === 'KE') ? 'selected' : ''; ?>><?php echo $n; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Region/State</label>
                    <input type="text" name="region"
                        value="<?php echo htmlspecialchars($tax['region'] ?? ''); ?>"
                        class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none"
                        placeholder="e.g., Nairobi">
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Tax Number / PIN</label>
                <input type="text" name="tax_number"
                    value="<?php echo htmlspecialchars($tax['tax_number'] ?? ''); ?>"
                    class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none"
                    placeholder="e.g., P051234567A">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo (!isset($tax['is_active']) || $tax['is_active']) ? 'checked' : ''; ?> class="rounded accent-[#FBBF24]">
                <label for="is_active" class="text-sm text-gray-400">Active</label>
            </div>

            <div class="flex gap-3 pt-4 border-t border-[#374151]">
                <button type="submit" class="flex-1 px-4 py-2 bg-[#FBBF24] text-black rounded-lg font-medium hover:bg-[#F59E0B] transition">
                    <i class="fas fa-save mr-2"></i><?php echo $is_edit ? 'Update' : 'Save'; ?> Tax Rate
                </button>
                <a href="tax_rates.php" class="flex-1 px-4 py-2 bg-[#374151] text-white rounded-lg font-medium hover:bg-[#4B5563] transition text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('taxForm').addEventListener('submit', function (e) {
        const name = document.querySelector('input[name="name"]').value.trim();
        const rate = parseFloat(document.querySelector('input[name="rate"]').value);
        if (!name) { e.preventDefault(); alert('Tax name is required'); return; }
        if (isNaN(rate) || rate < 0 || rate > 100) { e.preventDefault(); alert('Please enter a valid tax rate between 0 and 100'); return; }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = 'tax_rates.php';
        }
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.querySelector('form').submit();
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app_close.php';
require_once __DIR__ . '/../../layouts/app.php';
