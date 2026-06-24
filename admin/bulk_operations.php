<?php
/**
 * Bulk Operations
 * Perform bulk actions across tenants, subscriptions, invoices, and credits.
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

$message = '';
$message_type = '';
$affected = 0;

// Generate CSRF token
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $message = 'Invalid security token. Please refresh and try again.';
        $message_type = 'error';
    } else {
    try {
        $op = $_POST['operation'] ?? '';
        $scope = $_POST['scope'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));

        if (empty($ids) && $scope !== 'all_matching') {
            throw new Exception('No items selected.');
        }

        switch ($op) {
            case 'extend_trial':
                $days = (int) ($_POST['days'] ?? 7);
                if ($scope === 'all_matching') {
                    $affected = db_query("UPDATE pos_subscriptions SET trial_ends_at = DATE_ADD(COALESCE(trial_ends_at, NOW()), INTERVAL {$days} DAY), updated_at = NOW() WHERE status = 'trialing'");
                } else {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $affected = db_query("UPDATE pos_subscriptions SET trial_ends_at = DATE_ADD(COALESCE(trial_ends_at, NOW()), INTERVAL {$days} DAY), updated_at = NOW() WHERE id IN ({$placeholders})", $ids);
                }
                $message = "Extended trial for {$affected} subscription(s).";
                break;

            case 'change_plan':
                if (empty($ids)) throw new Exception('Select subscriptions.');
                $newPlanId = (int) $_POST['new_plan_id'];
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $subs = db_fetch_all("SELECT id, tenant_id, plan_id FROM pos_subscriptions WHERE id IN ({$placeholders})", $ids);
                foreach ($subs as $sub) {
                    db_insert('subscription_history', [
                        'tenant_id' => $sub['tenant_id'],
                        'subscription_id' => $sub['id'],
                        'event_type' => 'plan_change',
                        'old_plan_id' => $sub['plan_id'],
                        'new_plan_id' => $newPlanId,
                        'admin_id' => $_SESSION['admin_id'] ?? null,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                $affected = db_query("UPDATE pos_subscriptions SET plan_id = ?, updated_at = NOW() WHERE id IN ({$placeholders})", array_merge([$newPlanId], $ids));
                $message = "Changed plan for {$affected} subscription(s).";
                break;

            case 'cancel_subscriptions':
                if (empty($ids)) throw new Exception('Select subscriptions.');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $subs = db_fetch_all("SELECT id, tenant_id, plan_id FROM pos_subscriptions WHERE id IN ({$placeholders})", $ids);
                foreach ($subs as $sub) {
                    db_insert('subscription_history', [
                        'tenant_id' => $sub['tenant_id'],
                        'subscription_id' => $sub['id'],
                        'event_type' => 'cancelled',
                        'old_plan_id' => $sub['plan_id'],
                        'admin_id' => $_SESSION['admin_id'] ?? null,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                $affected = db_query("UPDATE pos_subscriptions SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() WHERE id IN ({$placeholders})", $ids);
                $message = "Cancelled {$affected} subscription(s).";
                break;

            case 'mark_invoices_paid':
                if (empty($ids)) throw new Exception('Select invoices.');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $affected = db_query("UPDATE pos_invoices SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE id IN ({$placeholders})", $ids);
                $message = "Marked {$affected} invoice(s) as paid.";
                break;

            case 'suspend_tenants':
                if (empty($ids)) throw new Exception('Select tenants.');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $affected = db_query("UPDATE pos_tenants SET status = 'suspended', updated_at = NOW() WHERE id IN ({$placeholders})", $ids);
                $message = "Suspended {$affected} tenant(s).";
                break;

            case 'activate_tenants':
                if (empty($ids)) throw new Exception('Select tenants.');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $affected = db_query("UPDATE pos_tenants SET status = 'active', updated_at = NOW() WHERE id IN ({$placeholders})", $ids);
                $message = "Activated {$affected} tenant(s).";
                break;

            case 'grant_credits':
                if (empty($ids)) throw new Exception('Select tenants.');
                $amount = (float) $_POST['credit_amount'];
                $reason = $_POST['credit_reason'] ?? 'Bulk credit';
                foreach ($ids as $tenantId) {
                    db_insert('credits', [
                        'tenant_id' => $tenantId,
                        'amount' => $amount,
                        'remaining_amount' => $amount,
                        'currency' => 'USD',
                        'type' => 'promotional',
                        'reason' => $reason,
                        'granted_by_admin_id' => $_SESSION['admin_id'] ?? null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                $affected = count($ids);
                $message = "Granted credits to {$affected} tenant(s).";
                break;

            case 'send_email':
                if (empty($ids)) throw new Exception('Select tenants.');
                $templateId = (int) $_POST['template_id'];
                $template = db_fetch_one("SELECT * FROM email_templates WHERE id = ?", [$templateId]);
                if (!$template) throw new Exception('Template not found.');
                $affected = count($ids);
                $message = "Queued {$affected} email(s) using template \"" . htmlspecialchars($template['name']) . "\".";
                break;

            default:
                throw new Exception('Unknown operation.');
        }
        $message_type = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

// ---- DATA FOR SELECTION ----
$tenants = db_fetch_all("SELECT id, name, slug, status FROM pos_tenants ORDER BY name ASC LIMIT 200");
$subscriptions = db_fetch_all(
    "SELECT s.id, s.status, s.trial_ends_at, t.name as tenant_name, p.name as plan_name
     FROM pos_subscriptions s
     JOIN pos_tenants t ON s.tenant_id = t.id
     LEFT JOIN pos_plans p ON s.plan_id = p.id
     ORDER BY s.created_at DESC LIMIT 200"
);
$invoices = db_fetch_all(
    "SELECT i.id, i.amount, i.status, i.currency, t.name as tenant_name
     FROM pos_invoices i
     JOIN pos_tenants t ON i.tenant_id = t.id
     WHERE i.status IN ('pending','overdue')
     ORDER BY i.created_at DESC LIMIT 200"
);
$plans = db_fetch_all("SELECT id, name FROM pos_plans WHERE status = 'active' ORDER BY name ASC");
$emailTemplates = db_fetch_all("SELECT id, name, subject FROM email_templates WHERE status = 'active' ORDER BY name ASC");

$current_page = 'settings';
$page_title = 'Bulk Operations';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Bulk Operations</h1>
            <p class="text-slate-400 text-sm mt-1">Perform actions on multiple records at once</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-emerald-500/20 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/20 border-red-500/30 text-red-400' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="bulkOpsForm" onsubmit="return validateForm()">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <!-- Step 1: Choose Operation -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 mb-4">
            <h2 class="text-white font-semibold text-sm mb-3">1. Select Operation</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="operation" value="extend_trial" class="peer hidden" onchange="showPanel('extend_trial')">
                    <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/60 hover:bg-slate-700/50 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 transition">
                        <i class="fas fa-calendar-plus text-blue-400 text-lg mb-1 block"></i>
                        <p class="text-white text-xs font-medium">Extend Trial</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="operation" value="change_plan" class="peer hidden" onchange="showPanel('change_plan')">
                    <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/60 hover:bg-slate-700/50 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 transition">
                        <i class="fas fa-exchange-alt text-amber-400 text-lg mb-1 block"></i>
                        <p class="text-white text-xs font-medium">Change Plan</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="operation" value="cancel_subscriptions" class="peer hidden" onchange="showPanel('cancel_subscriptions')">
                    <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/60 hover:bg-slate-700/50 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 transition">
                        <i class="fas fa-ban text-red-400 text-lg mb-1 block"></i>
                        <p class="text-white text-xs font-medium">Cancel Subs</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="operation" value="mark_invoices_paid" class="peer hidden" onchange="showPanel('mark_invoices_paid')">
                    <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/60 hover:bg-slate-700/50 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 transition">
                        <i class="fas fa-check-circle text-emerald-400 text-lg mb-1 block"></i>
                        <p class="text-white text-xs font-medium">Mark Paid</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="operation" value="suspend_tenants" class="peer hidden" onchange="showPanel('suspend_tenants')">
                    <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/60 hover:bg-slate-700/50 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 transition">
                        <i class="fas fa-pause-circle text-amber-400 text-lg mb-1 block"></i>
                        <p class="text-white text-xs font-medium">Suspend</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="operation" value="activate_tenants" class="peer hidden" onchange="showPanel('activate_tenants')">
                    <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/60 hover:bg-slate-700/50 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 transition">
                        <i class="fas fa-play-circle text-emerald-400 text-lg mb-1 block"></i>
                        <p class="text-white text-xs font-medium">Activate</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="operation" value="grant_credits" class="peer hidden" onchange="showPanel('grant_credits')">
                    <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/60 hover:bg-slate-700/50 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 transition">
                        <i class="fas fa-wallet text-purple-400 text-lg mb-1 block"></i>
                        <p class="text-white text-xs font-medium">Grant Credits</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="operation" value="send_email" class="peer hidden" onchange="showPanel('send_email')">
                    <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/60 hover:bg-slate-700/50 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 transition">
                        <i class="fas fa-envelope text-indigo-400 text-lg mb-1 block"></i>
                        <p class="text-white text-xs font-medium">Send Email</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Step 2: Operation Options -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 mb-4">
            <h2 class="text-white font-semibold text-sm mb-3">2. Configure Options</h2>
            <div id="optionPanels">
                <div id="panel-extend_trial" class="option-panel hidden">
                    <label class="block text-slate-400 text-xs mb-1">Extend by (days)</label>
                    <input type="number" name="days" value="7" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm w-32">
                </div>
                <div id="panel-change_plan" class="option-panel hidden">
                    <label class="block text-slate-400 text-xs mb-1">New Plan</label>
                    <select name="new_plan_id" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        <?php foreach ($plans as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="panel-grant_credits" class="option-panel hidden">
                    <div class="flex gap-3">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Amount ($)</label>
                            <input type="number" step="0.01" name="credit_amount" value="10.00" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm w-32">
                        </div>
                        <div class="flex-1">
                            <label class="block text-slate-400 text-xs mb-1">Reason</label>
                            <input type="text" name="credit_reason" value="Promotional bulk credit" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm w-full">
                        </div>
                    </div>
                </div>
                <div id="panel-send_email" class="option-panel hidden">
                    <label class="block text-slate-400 text-xs mb-1">Email Template</label>
                    <select name="template_id" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        <?php foreach ($emailTemplates as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> — <?= htmlspecialchars($t['subject']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="panel-default" class="option-panel text-slate-500 text-sm">
                    Select an operation above to see options.
                </div>
            </div>
        </div>

        <!-- Step 3: Select Targets -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 mb-4">
            <h2 class="text-white font-semibold text-sm mb-3">3. Select Targets</h2>

            <!-- Tabs -->
            <div class="flex gap-2 mb-3 border-b border-slate-700/60 pb-2">
                <button type="button" onclick="showTargetTab('tenants')" id="tab-tenants" class="target-tab px-3 py-1.5 text-xs rounded-lg text-slate-400 hover:text-white transition">Tenants</button>
                <button type="button" onclick="showTargetTab('subscriptions')" id="tab-subscriptions" class="target-tab px-3 py-1.5 text-xs rounded-lg text-slate-400 hover:text-white transition">Subscriptions</button>
                <button type="button" onclick="showTargetTab('invoices')" id="tab-invoices" class="target-tab px-3 py-1.5 text-xs rounded-lg text-slate-400 hover:text-white transition">Invoices</button>
            </div>

            <input type="hidden" name="scope" id="scope" value="selected">

            <!-- Tenants -->
            <div id="target-tenants" class="target-panel hidden">
                <div class="max-h-64 overflow-y-auto pr-1 space-y-1">
                    <?php foreach ($tenants as $t): ?>
                    <label class="flex items-center gap-3 p-2 rounded-lg bg-slate-800/50 hover:bg-slate-700/50 cursor-pointer transition">
                        <input type="checkbox" name="tenant_ids[]" value="<?= $t['id'] ?>" class="target-checkbox accent-amber-500" data-type="tenant" onchange="updateIds()">
                        <div class="flex-1 min-w-0">
                            <p class="text-white text-xs font-medium"><?= htmlspecialchars($t['name']) ?></p>
                            <p class="text-slate-500 text-[10px]"><?= htmlspecialchars($t['slug']) ?> · <?= $t['status'] ?></p>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Subscriptions -->
            <div id="target-subscriptions" class="target-panel hidden">
                <div class="max-h-64 overflow-y-auto pr-1 space-y-1">
                    <?php foreach ($subscriptions as $s): ?>
                    <label class="flex items-center gap-3 p-2 rounded-lg bg-slate-800/50 hover:bg-slate-700/50 cursor-pointer transition">
                        <input type="checkbox" name="sub_ids[]" value="<?= $s['id'] ?>" class="target-checkbox accent-amber-500" data-type="subscription" onchange="updateIds()">
                        <div class="flex-1 min-w-0">
                            <p class="text-white text-xs font-medium"><?= htmlspecialchars($s['tenant_name']) ?></p>
                            <p class="text-slate-500 text-[10px]"><?= htmlspecialchars($s['plan_name'] ?? 'No plan') ?> · <?= $s['status'] ?> <?= $s['trial_ends_at'] ? '· Trial ends ' . date('M j', strtotime($s['trial_ends_at'])) : '' ?></p>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Invoices -->
            <div id="target-invoices" class="target-panel hidden">
                <div class="max-h-64 overflow-y-auto pr-1 space-y-1">
                    <?php foreach ($invoices as $i): ?>
                    <label class="flex items-center gap-3 p-2 rounded-lg bg-slate-800/50 hover:bg-slate-700/50 cursor-pointer transition">
                        <input type="checkbox" name="invoice_ids[]" value="<?= $i['id'] ?>" class="target-checkbox accent-amber-500" data-type="invoice" onchange="updateIds()">
                        <div class="flex-1 min-w-0">
                            <p class="text-white text-xs font-medium"><?= htmlspecialchars($i['tenant_name']) ?></p>
                            <p class="text-slate-500 text-[10px]">#<?= $i['id'] ?> · $<?= number_format($i['amount'], 2) ?> <?= $i['currency'] ?> · <?= $i['status'] ?></p>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <input type="hidden" name="ids" id="idsInput" value="">

            <div class="mt-3 flex items-center justify-between">
                <label class="flex items-center gap-2 text-slate-400 text-xs cursor-pointer">
                    <input type="checkbox" id="selectAllTargets" onchange="toggleAllTargets(this)">
                    Select All Visible
                </label>
                <p class="text-slate-400 text-xs"><span id="selectedCount">0</span> selected</p>
            </div>
        </div>

        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-6 py-3 rounded-lg text-sm font-semibold w-full md:w-auto">
            <i class="fas fa-bolt mr-1"></i> Execute Bulk Operation
        </button>
    </form>
</div>

<script>
function showPanel(op) {
    document.querySelectorAll('.option-panel').forEach(p => p.classList.add('hidden'));
    const panel = document.getElementById('panel-' + op);
    if (panel) panel.classList.remove('hidden');
    document.getElementById('panel-default').classList.add('hidden');
}
function showTargetTab(tab) {
    document.querySelectorAll('.target-panel').forEach(p => p.classList.add('hidden'));
    document.getElementById('target-' + tab).classList.remove('hidden');
    document.querySelectorAll('.target-tab').forEach(t => t.classList.remove('bg-amber-500/20', 'text-amber-400'));
    document.getElementById('tab-' + tab).classList.add('bg-amber-500/20', 'text-amber-400');
    updateIds();
}
function toggleAllTargets(checkbox) {
    const activePanel = document.querySelector('.target-panel:not(.hidden)');
    if (activePanel) {
        activePanel.querySelectorAll('.target-checkbox').forEach(cb => cb.checked = checkbox.checked);
    }
    updateIds();
}
function updateIds() {
    const checked = document.querySelectorAll('.target-checkbox:checked');
    const ids = Array.from(checked).map(cb => cb.value);
    document.getElementById('idsInput').value = ids.join(',');
    document.getElementById('selectedCount').innerText = ids.length;
}
function validateForm() {
    const op = document.querySelector('input[name="operation"]:checked');
    if (!op) { alert('Please select an operation.'); return false; }
    const ids = document.getElementById('idsInput').value;
    if (!ids) { alert('Please select at least one target.'); return false; }
    return confirm('This action will affect ' + document.getElementById('selectedCount').innerText + ' record(s). Proceed?');
}
// Default tab
showTargetTab('tenants');
</script>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

