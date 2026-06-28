<?php
/**
 * Branch add/edit form for Jakababa POS
 * Standalone form extracted from branches.php
 */

require_once __DIR__ . '/../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

if (!check_permission('branches.manage') && !is_super_admin()) {
    enforce_permission('branches.manage');
}

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_role = $_SESSION['user']['role'] ?? '';

if (!$tenant_id) {
    http_response_code(403);
    exit('Error: Company context not found. Please log in again.');
}

// Get available business types
$business_types = [];
try {
    $bt_stmt = $pdo->query("SELECT id, name, code, icon FROM business_types WHERE is_active = 1 AND branch_id = $current_branch_id ORDER BY sort_order, name");
    $business_types = $bt_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $business_types = [];
}

// Fetch existing branch for edit
$branch = null;
$branch_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($branch_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM branches WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$branch_id, $tenant_id]);
    $branch = $stmt->fetch();
    if (!$branch) {
        header('Location: branches.php?error=' . urlencode('Branch not found'));
        exit;
    }
}

$is_edit = $branch_id > 0;

// Handle POST
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $manager = trim($_POST['manager'] ?? '');
    $tax_rate = floatval($_POST['tax_rate'] ?? 0);
    $opening_time = $_POST['opening_time'] ?? '08:00';
    $closing_time = $_POST['closing_time'] ?? '20:00';
    $business_type_id = intval($_POST['business_type_id'] ?? 0);

    if (empty($name)) {
        $errors[] = 'Branch name is required';
    }
    if (!empty($code) && !preg_match('/^[A-Z0-9]{3,10}$/', $code)) {
        $errors[] = 'Branch code must be 3-10 uppercase letters/numbers';
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                // Update
                $has_bt_col = false;
                try {
                    $has_bt_col = $pdo->query("SHOW COLUMNS FROM branches LIKE 'business_type_id'")->rowCount() > 0;
                } catch (Exception $e) {}

                if ($has_bt_col) {
                    $stmt = $pdo->prepare('UPDATE branches SET name = ?, code = ?, address = ?, phone = ?, email = ?, manager = ?, tax_rate = ?, opening_time = ?, closing_time = ?, business_type_id = ? WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$name, $code, $address, $phone, $email, $manager, $tax_rate, $opening_time, $closing_time, $business_type_id, $id, $tenant_id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE branches SET name = ?, code = ?, address = ?, phone = ?, email = ?, manager = ?, tax_rate = ?, opening_time = ?, closing_time = ? WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$name, $code, $address, $phone, $email, $manager, $tax_rate, $opening_time, $closing_time, $id, $tenant_id]);
                }

                log_activity($user_id, 'branch.updated', ['branch_id' => $id, 'branch_name' => $name], get_current_tenant_id());
                header('Location: branches.php?success=' . urlencode('Branch updated successfully'));
                exit;
            } else {
                // Create — enforce branch quota
                require_once __DIR__ . '/../../src/Security/PlanEnforcement.php';
                PlanEnforcement::loadTenantPlan($pdo, $tenant_id);
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE tenant_id = ? AND deleted_at IS NULL");
                $countStmt->execute([$tenant_id]);
                $branchCount = (int) $countStmt->fetchColumn();
                if (!PlanEnforcement::checkLimit('max_branches', $branchCount)) {
                    $errors[] = 'Branch limit reached for your plan. Please upgrade to add more branches.';
                }

                $check = $pdo->prepare('SELECT id FROM branches WHERE name = ? AND tenant_id = ?');
                $check->execute([$name, $tenant_id]);
                if ($check->fetch()) {
                    $errors[] = 'Branch with this name already exists';
                }
                if (empty($errors)) {
                    $has_bt_col = false;
                    try {
                        $has_bt_col = $pdo->query("SHOW COLUMNS FROM branches LIKE 'business_type_id'")->rowCount() > 0;
                    } catch (Exception $e) {}

                    $pdo->beginTransaction();
                    if ($has_bt_col) {
                        $stmt = $pdo->prepare('INSERT INTO branches (tenant_id, name, code, address, phone, email, manager, tax_rate, opening_time, closing_time, business_type_id, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
                        $stmt->execute([$tenant_id, $name, $code, $address, $phone, $email, $manager, $tax_rate, $opening_time, $closing_time, $business_type_id]);
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO branches (tenant_id, name, code, address, phone, email, manager, tax_rate, opening_time, closing_time, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
                        $stmt->execute([$tenant_id, $name, $code, $address, $phone, $email, $manager, $tax_rate, $opening_time, $closing_time]);
                    }
                    $new_branch_id = $pdo->lastInsertId();

                    // Initialize inventory
                    $pdo->exec("INSERT IGNORE INTO inventory (tenant_id, product_id, branch_id, stock, reorder_level) SELECT ?, id, ?, 0, 0 FROM products WHERE tenant_id = ? AND deleted_at IS NULL");

                    log_activity($user_id, 'branch.created', ['branch_id' => $new_branch_id, 'branch_name' => $name], get_current_tenant_id());
                    $pdo->commit();
                    header('Location: branches.php?success=' . urlencode('Branch added successfully with inventory initialized'));
                    exit;
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error saving branch: " . $e->getMessage());
            $errors[] = 'Database error: Failed to save branch';
        }
    }
}

$page_title = ($is_edit ? 'Edit' : 'Add') . ' Branch | JDH POS';
ob_start();
?>

<div>
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Settings</p>
            <h1 class="text-lg font-bold text-white"><?php echo $is_edit ? 'Edit' : 'Add'; ?> Branch</h1>
            <p class="text-sm text-slate-500 mt-0.5"><?php echo $is_edit ? 'Update branch details' : 'Create a new store location'; ?></p>
        </div>
        <a href="branches.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to Branches
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 bg-red-500/10 border border-red-500 rounded-xl p-4">
            <div class="flex items-start gap-3 text-red-400">
                <i class="fas fa-exclamation-triangle flex-shrink-0 mt-0.5"></i>
                <div class="text-white"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
        <form method="POST" class="space-y-4">
            <input type="hidden" name="id" value="<?php echo $branch_id; ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Branch Name <span class="text-red-400">*</span></label>
                    <input type="text" name="name" required
                        value="<?php echo htmlspecialchars($branch['name'] ?? ''); ?>"
                        class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors"
                        placeholder="e.g. Nairobi Main">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Branch Code</label>
                    <input type="text" name="code"
                        value="<?php echo htmlspecialchars($branch['code'] ?? ''); ?>"
                        class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 font-mono focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors"
                        placeholder="e.g. NRB001">
                    <p class="text-xs text-slate-500 mt-1">3-10 uppercase letters/numbers</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Address</label>
                <textarea name="address" rows="2"
                    class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 resize-none transition-colors"
                    placeholder="Full address"><?php echo htmlspecialchars($branch['address'] ?? ''); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Phone</label>
                    <input type="tel" name="phone"
                        value="<?php echo htmlspecialchars($branch['phone'] ?? ''); ?>"
                        class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors"
                        placeholder="e.g. +254 700 000000">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Email</label>
                    <input type="email" name="email"
                        value="<?php echo htmlspecialchars($branch['email'] ?? ''); ?>"
                        class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors"
                        placeholder="branch@example.com">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Branch Manager</label>
                    <input type="text" name="manager"
                        value="<?php echo htmlspecialchars($branch['manager'] ?? ''); ?>"
                        class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors"
                        placeholder="Full name of manager">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Tax Rate (%)</label>
                    <input type="number" name="tax_rate" min="0" max="100" step="0.01"
                        value="<?php echo isset($branch['tax_rate']) ? $branch['tax_rate'] : 16; ?>"
                        class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Opening Time</label>
                    <input type="time" name="opening_time"
                        value="<?php echo isset($branch['opening_time']) ? $branch['opening_time'] : '08:00'; ?>"
                        class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Closing Time</label>
                    <input type="time" name="closing_time"
                        value="<?php echo isset($branch['closing_time']) ? $branch['closing_time'] : '20:00'; ?>"
                        class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1" for="business_type_id">
                    Business Type <span class="text-red-400">*</span>
                </label>
                <select id="business_type_id" name="business_type_id" required
                    class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
                    <option value="">-- Select Business Type --</option>
                    <?php foreach ($business_types as $bt): ?>
                        <option value="<?php echo (int)$bt['id']; ?>" <?php echo (isset($branch['business_type_id']) && (int)$branch['business_type_id'] === (int)$bt['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($bt['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500 mt-1"><i class="fas fa-info-circle mr-1"></i>Controls which products and features appear in the POS for this branch.</p>
            </div>

            <div class="flex gap-2 pt-4 border-t border-slate-700/60">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors">
                    <i class="fas fa-save text-xs"></i> <?php echo $is_edit ? 'Update' : 'Save'; ?> Branch
                </button>
                <a href="branches.php" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = 'branches.php';
        }
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.querySelector('form').submit();
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
