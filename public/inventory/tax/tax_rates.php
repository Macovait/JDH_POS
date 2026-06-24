<?php
/**
 * Tax Rates Management page for Jakababa POS
 * 
 * Manages tax rates, VAT settings, and tax exemptions.
 */

require_once __DIR__ . '/../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

if (!check_permission('tax_rates.manage') && !is_super_admin()) {
    enforce_permission('tax_rates.manage');
}

require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$branch_id = (int) ($_SESSION['branch_id'] ?? 1);
$is_super_admin = is_super_admin();

// Handle POST actions (delete, toggle, set_default only; create/update moved to tax_rate_form.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect_msg = '';
    $redirect_type = 'success';

    switch ($action) {
        case 'delete':
            if ($is_super_admin || $user_role === 'Admin') {
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $check_default = $pdo->prepare('SELECT is_default FROM tax_rates WHERE id = ?');
                        $check_default->execute([$id]);
                        if ($check_default->fetchColumn()) {
                            $redirect_msg = 'Cannot delete default tax rate';
                            $redirect_type = 'error';
                        } else {
                            $check_products = $pdo->prepare('SELECT COUNT(*) FROM products WHERE tax_rate_id = ? AND deleted_at IS NULL');
                            $check_products->execute([$id]);
                            $check_sales = $pdo->prepare('SELECT COUNT(*) FROM sales WHERE tax_rate_id = ?');
                            $check_sales->execute([$id]);
                            if ($check_products->fetchColumn() > 0 || $check_sales->fetchColumn() > 0) {
                                $pdo->prepare('UPDATE tax_rates SET is_active = 0 WHERE id = ?')->execute([$id]);
                                $redirect_msg = 'Tax rate deactivated (used in products/sales)';
                            } else {
                                $pdo->prepare('DELETE FROM tax_rates WHERE id = ?')->execute([$id]);
                                $redirect_msg = 'Tax rate deleted successfully';
                            }
                        }
                        log_activity($user_id, 'tax_rate.deleted', ['tax_id' => $id], null, get_current_tenant_id());
                    } catch (PDOException $e) {
                        error_log("Error deleting tax rate: " . $e->getMessage());
                        $redirect_msg = 'Failed to delete tax rate';
                        $redirect_type = 'error';
                    }
                }
            }
            break;

        case 'toggle_status':
            if ($is_super_admin || $user_role === 'Admin') {
                $id = intval($_POST['id'] ?? 0);
                $current_status = intval($_POST['current_status'] ?? 1);
                $new_status = $current_status ? 0 : 1;
                try {
                    $pdo->prepare('UPDATE tax_rates SET is_active = ? WHERE id = ?')->execute([$new_status, $id]);
                    $redirect_msg = 'Tax rate ' . ($new_status ? 'activated' : 'deactivated') . ' successfully';
                    log_activity($user_id, 'tax_rate.status_toggled', ['tax_id' => $id, 'new_status' => $new_status], get_current_tenant_id());
                } catch (PDOException $e) {
                    $redirect_msg = 'Failed to update tax rate status';
                    $redirect_type = 'error';
                }
            }
            break;

        case 'set_default':
            if ($is_super_admin || $user_role === 'Admin') {
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $pdo->beginTransaction();
                        $pdo->exec("UPDATE tax_rates SET is_default = 0");
                        $pdo->prepare('UPDATE tax_rates SET is_default = 1 WHERE id = ?')->execute([$id]);
                        $pdo->commit();
                        $redirect_msg = 'Default tax rate updated successfully';
                        log_activity($user_id, 'tax_rate.set_default', ['tax_id' => $id], null, get_current_tenant_id());
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $redirect_msg = 'Failed to set default tax rate';
                        $redirect_type = 'error';
                    }
                }
            }
            break;
    }

    if ($redirect_msg) {
        $param = $redirect_type === 'error' ? 'error' : 'success';
        header('Location: tax_rates.php?' . $param . '=' . urlencode($redirect_msg));
        exit;
    }
}

// Read flash messages from GET
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

$tax_rates = [];
try {
    $stmt = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM products WHERE tax_rate_id = t.id AND deleted_at IS NULL) as product_count, (SELECT COUNT(*) FROM sales WHERE tax_rate_id = t.id) as sales_count, (SELECT COALESCE(SUM(total), 0) FROM sales WHERE tax_rate_id = t.id) as total_tax_collected FROM tax_rates t AND branch_id = $current_branch_id ORDER BY t.is_default DESC, t.is_active DESC, t.rate ASC");
    $tax_rates = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching tax rates: " . $e->getMessage());
}

$stats = ['total_tax_rates' => count($tax_rates), 'active_rates' => 0, 'default_rate' => null, 'average_rate' => 0, 'total_products_taxed' => 0, 'total_tax_collected' => 0];
$rate_sum = 0;
foreach ($tax_rates as $tax) {
    if ($tax['is_default']) $stats['default_rate'] = $tax;
    if ($tax['is_active']) { $rate_sum += $tax['rate']; $stats['active_rates']++; }
    $stats['total_products_taxed'] += $tax['product_count'];
    $stats['total_tax_collected'] += $tax['total_tax_collected'];
}
$stats['average_rate'] = $stats['active_rates'] > 0 ? round($rate_sum / $stats['active_rates'], 2) : 0;

$tax_types = ['vat' => 'VAT (Value Added Tax)', 'gst' => 'GST (Goods & Services Tax)', 'sales' => 'Sales Tax', 'income' => 'Income Tax', 'withholding' => 'Withholding Tax', 'excise' => 'Excise Duty', 'custom' => 'Custom Duty', 'other' => 'Other Tax'];
$countries = ['KE' => 'Kenya', 'UG' => 'Uganda', 'TZ' => 'Tanzania', 'RW' => 'Rwanda', 'BI' => 'Burundi', 'SS' => 'South Sudan', 'ET' => 'Ethiopia', 'ZA' => 'South Africa', 'NG' => 'Nigeria', 'GH' => 'Ghana', 'US' => 'United States', 'UK' => 'United Kingdom', 'EU' => 'European Union', 'OTHER' => 'Other'];

$page_title = 'Tax Rates | Jakababa POS';
$can_delete = ($is_super_admin || $user_role === 'Admin');
ob_start();

?>

<style>
    .card { background: #1F2937; border: 1px solid #4B5563; border-radius: 1rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); }
    .stats-card { background: #1F2937; border: 1px solid #4B5563; border-radius: 1rem; padding: 1.25rem; transition: all 0.2s; }
    .stats-card:hover { border-color: #FBBF24; transform: translateY(-2px); }
    .tax-card { background: #1F2937; border: 1px solid #4B5563; border-radius: 1rem; padding: 1.25rem; transition: all 0.2s; position: relative; overflow: hidden; }
    .tax-card:hover { border-color: #FBBF24; transform: translateY(-2px); box-shadow: 0 10px 20px -10px rgba(0,0,0,0.5); }
    .tax-card.default { border: 2px solid #FBBF24; background: linear-gradient(135deg, #1F2937, #2D3748); }
    .tax-card.inactive { opacity: 0.7; }
    .tax-rate-badge { position: absolute; top: 1rem; right: 1rem; font-size: 1.5rem; font-weight: 700; color: #FBBF24; opacity: 0.2; }
    .badge-active { background: #065F46; color: #D1FAE5; border: 1px solid #10B981; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
    .badge-inactive { background: #7F1D1D; color: #FEE2E2; border: 1px solid #EF4444; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
    .badge-default { background: #78350F; color: #FEF3C7; border: 1px solid #FBBF24; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
    .badge-compound { background: #5B21B6; color: #EDE9FE; border: 1px solid #8B5CF6; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
    .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 { background: #111827; border: 1px solid #4B5563; border-radius: 0.5rem; padding: 0.5rem 0.75rem; color: #F9FAFB; width: 100%; transition: all 0.2s; }
    .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500:focus { outline: none; border-color: #FBBF24; box-shadow: 0 0 0 3px rgba(251,191,36,0.3); }
    .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500::placeholder { color: #6B7280; }
    .btn-primary { background: #FBBF24; color: #1E3A8A; font-weight: 600; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.2s; border: 1px solid #FBBF24; }
    .btn-primary:hover { background: #F59E0B; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(251,191,36,0.3); }
    .btn-secondary { background: #374151; color: #F3F4F6; font-weight: 500; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.2s; border: 1px solid #4B5563; }
    .btn-secondary:hover { background: #4B5563; border-color: #6B7280; }
    .btn-danger { background: #7F1D1D; color: #FEE2E2; font-weight: 500; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.2s; border: 1px solid #EF4444; }
    .btn-danger:hover { background: #991B1B; border-color: #DC2626; }
    .action-btn { color: #9CA3AF; transition: all 0.2s; padding: 0.25rem; border-radius: 0.25rem; }
    .action-btn:hover { color: #FBBF24; background: #374151; }
    .action-btn.delete:hover { color: #EF4444; }
    .message-success { background: #065F46; color: #D1FAE5; border: 1px solid #10B981; border-radius: 0.5rem; padding: 1rem; }
    .message-error { background: #7F1D1D; color: #FEE2E2; border: 1px solid #EF4444; border-radius: 0.5rem; padding: 1rem; }
    .fade-in { animation: fadeIn 0.4s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .rate-display { font-size: 2.5rem; font-weight: 700; color: #FBBF24; line-height: 1; }
</style>

<div class="fade-in">

    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Tax Rate Management</h1>
            <p class="text-gray-400 text-sm mt-1">Configure tax rates for products and transactions</p>
        </div>
        <?php if ($is_super_admin || $user_role === 'Admin'): ?>
            <a href="tax_rate_form.php" class="btn-primary flex items-center gap-2">
                <i class="fas fa-plus-circle"></i><span>Add Tax Rate</span>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($success_message): ?>
        <div class="mb-4 message-success"><i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="mb-4 message-error"><i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stats-card">
            <div class="flex items-center justify-between mb-2"><span class="text-gray-400 text-sm">Total Tax Rates</span><i class="fas fa-percent text-amber-400"></i></div>
            <p class="text-2xl font-bold text-white"><?php echo $stats['total_tax_rates']; ?></p>
            <p class="text-xs text-gray-400 mt-1"><?php echo $stats['active_rates']; ?> active</p>
        </div>
        <div class="stats-card">
            <div class="flex items-center justify-between mb-2"><span class="text-gray-400 text-sm">Average Rate</span><i class="fas fa-chart-line text-emerald-400"></i></div>
            <p class="text-2xl font-bold text-white"><?php echo $stats['average_rate']; ?>%</p>
            <p class="text-xs text-gray-400 mt-1">Active rates average</p>
        </div>
        <div class="stats-card">
            <div class="flex items-center justify-between mb-2"><span class="text-gray-400 text-sm">Products Taxed</span><i class="fas fa-cubes text-amber-400"></i></div>
            <p class="text-2xl font-bold text-white"><?php echo number_format($stats['total_products_taxed']); ?></p>
            <p class="text-xs text-gray-400 mt-1">Products using tax</p>
        </div>
        <div class="stats-card">
            <div class="flex items-center justify-between mb-2"><span class="text-gray-400 text-sm">Tax Collected</span><i class="fas fa-coins text-emerald-400"></i></div>
            <p class="text-2xl font-bold text-white">KSh <?php echo number_format($stats['total_tax_collected'], 0); ?></p>
            <p class="text-xs text-gray-400 mt-1">Lifetime total</p>
        </div>
    </div>

    <?php if ($stats['default_rate']): ?>
        <div class="mb-6 p-4 bg-amber-500/10 border border-amber-500 rounded-lg">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-amber-500 flex items-center justify-center"><i class="fas fa-star text-slate-900"></i></div>
                <div>
                    <p class="text-sm text-amber-400 font-semibold">Default Tax Rate</p>
                    <p class="text-white"><span class="font-bold"><?php echo htmlspecialchars($stats['default_rate']['name']); ?></span> (<?php echo $stats['default_rate']['rate']; ?>%) - <?php echo htmlspecialchars($stats['default_rate']['description']); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tax Rates Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if (empty($tax_rates)): ?>
            <div class="col-span-full text-center py-12">
                <div class="w-20 h-20 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-percent text-3xl text-amber-400"></i></div>
                <h3 class="text-xl font-semibold text-white mb-2">No Tax Rates Found</h3>
                <p class="text-gray-400 mb-6">Create your first tax rate to get started</p>
                <?php if ($is_super_admin || $user_role === 'Admin'): ?>
                    <button onclick="openCreateModal()" class="btn-primary"><i class="fas fa-plus mr-2"></i>Add Tax Rate</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($tax_rates as $tax): ?>
                <?php $is_active = $tax['is_active'] ?? 1; $is_default = $tax['is_default'] ?? 0; $is_compound = $tax['is_compound'] ?? 0; ?>
                <div class="tax-card <?php echo !$is_active ? 'inactive' : ''; ?> <?php echo $is_default ? 'default' : ''; ?>">
                    <div class="tax-rate-badge"><?php echo $tax['rate']; ?>%</div>
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-semibold text-white"><?php echo htmlspecialchars($tax['name']); ?></h3>
                                <?php if ($is_default): ?><span class="badge-default">Default</span><?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($tax['description'] ?: 'No description'); ?></p>
                        </div>
                        <div>
                            <?php if ($is_active): ?><span class="badge-active">Active</span><?php else: ?><span class="badge-inactive">Inactive</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="bg-primary-dark rounded-lg p-2"><p class="text-xs text-gray-400">Rate</p><p class="text-xl font-bold text-amber-400"><?php echo $tax['rate']; ?>%</p></div>
                        <div class="bg-primary-dark rounded-lg p-2"><p class="text-xs text-gray-400">Type</p><p class="font-medium text-white"><?php echo $tax_types[$tax['type']] ?? $tax['type']; ?></p></div>
                    </div>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <?php if ($is_compound): ?><span class="badge-compound"><i class="fas fa-layer-group mr-1"></i>Compound</span><?php endif; ?>
                        <?php if ($tax['is_recoverable']): ?><span class="badge-active"><i class="fas fa-undo-alt mr-1"></i>Recoverable</span><?php endif; ?>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-400 mb-3">
                        <div><i class="fas fa-calendar-alt mr-1"></i>Effective: <?php echo date('d M Y', strtotime($tax['effective_from'])); ?></div>
                        <?php if ($tax['effective_to']): ?><div><i class="fas fa-calendar-times mr-1"></i>Until: <?php echo date('d M Y', strtotime($tax['effective_to'])); ?></div><?php endif; ?>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs mb-3">
                        <div class="bg-primary-dark rounded-lg p-2"><p class="text-gray-400">Products</p><p class="font-semibold text-white"><?php echo $tax['product_count']; ?></p></div>
                        <div class="bg-primary-dark rounded-lg p-2"><p class="text-gray-400">Sales</p><p class="font-semibold text-white"><?php echo $tax['sales_count']; ?></p></div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-slate-700">
                        <div class="flex items-center gap-2">
                            <?php if (!$is_default && ($is_super_admin || $user_role === 'Admin')): ?>
                                <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="set_default"><input type="hidden" name="id" value="<?php echo $tax['id']; ?>">
                                    <button type="submit" class="action-btn" title="Set as Default" onclick="return confirm('Set this as the default tax rate?')"><i class="fas fa-star"></i></button>
                                </form>
                            <?php endif; ?>
                            <a href="tax_rate_form.php?id=<?php echo $tax['id']; ?>" class="action-btn" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php if ($can_delete && !$is_default): ?>
                                <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?php echo $tax['id']; ?>"><input type="hidden" name="current_status" value="<?php echo $tax['is_active']; ?>">
                                    <button type="submit" class="action-btn" title="<?php echo $is_active ? 'Deactivate' : 'Activate'; ?>" onclick="return confirm('<?php echo $is_active ? 'Deactivate' : 'Activate'; ?> this tax rate?')"><i class="fas <?php echo $is_active ? 'fa-ban' : 'fa-check-circle'; ?>"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($can_delete && !$is_default): ?>
                                <button onclick="openDeleteModal(<?php echo $tax['id']; ?>, '<?php echo htmlspecialchars(addslashes($tax['name'])); ?>', <?php echo $tax['product_count'] + $tax['sales_count']; ?>)" class="action-btn delete" title="Delete"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/80 flex items-center justify-center hidden z-50">
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 max-w-md w-full mx-4">
        <div class="flex items-center gap-3 text-red-400 mb-4"><i class="fas fa-exclamation-triangle text-2xl"></i><h3 class="text-xl font-semibold text-white">Delete Tax Rate</h3></div>
        <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="deleteTaxId">
            <p class="text-gray-400 mb-4">Are you sure you want to delete <span id="deleteTaxName" class="text-white font-semibold"></span>?</p>
            <div id="deleteTaxWarning" class="bg-red-900/50 text-red-200 p-3 rounded-lg mb-4 text-sm hidden"><i class="fas fa-exclamation-circle mr-2"></i>This tax rate is used in <span id="deleteUsageCount">0</span> product(s) or sale(s). It will be deactivated instead.</div>
            <p class="text-gray-400 text-sm mb-6">This action cannot be undone.</p>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 btn-danger" id="deleteButton">Delete Tax Rate</button>
                <button type="button" onclick="closeDeleteModal()" class="flex-1 btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openDeleteModal(id, name, usageCount) {
        document.getElementById('deleteTaxId').value = id;
        document.getElementById('deleteTaxName').textContent = name;
        document.getElementById('deleteUsageCount').textContent = usageCount;
        const warning = document.getElementById('deleteTaxWarning');
        const btn = document.getElementById('deleteButton');
        if (usageCount > 0) { warning.classList.remove('hidden'); btn.textContent = 'Deactivate Tax Rate'; } else { warning.classList.add('hidden'); btn.textContent = 'Delete Tax Rate'; }
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); }

    document.querySelectorAll('.fixed').forEach(modal => {
        modal.addEventListener('click', function (e) { if (e.target === this) this.classList.add('hidden'); });
    });

    function updateOnlineStatus() {
        const el = document.getElementById('connection-status');
        if (el) {
            if (navigator.onLine) { el.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>'; el.className = 'fixed bottom-4 left-4 text-xs text-emerald-400 flex items-center gap-1 bg-slate-800/80  px-3 py-2 rounded-full border border-slate-700'; }
            else { el.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline</span>'; el.className = 'fixed bottom-4 left-4 text-xs text-red-400 flex items-center gap-1 bg-slate-800/80  px-3 py-2 rounded-full border border-slate-700'; }
        }
    }
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);

    setTimeout(() => {
        const msg = document.querySelector('.message-success');
        if (msg) { msg.style.transition = 'opacity 0.5s'; msg.style.opacity = '0'; setTimeout(() => msg.remove(), 500); }
    }, 5000);
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app_close.php';
require_once __DIR__ . '/../../layouts/app.php';
?>
