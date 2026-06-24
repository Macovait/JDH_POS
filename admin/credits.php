<?php
/**
 * Credit System UI
 * Grant, adjust, and track tenant credits.
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

// Generate CSRF token
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $message = 'Invalid security token. Please refresh and try again.';
        $message_type = 'error';
    } else {
        try {
            $action = $_POST['action'] ?? '';
            if ($action === 'grant_credit') {
                db_insert('credits', [
                    'tenant_id' => (int) $_POST['tenant_id'],
                    'amount' => (float) $_POST['amount'],
                    'currency' => $_POST['currency'] ?? 'USD',
                    'type' => $_POST['credit_type'],
                    'reason' => $_POST['reason'] ?? null,
                    'granted_by_admin_id' => $_SESSION['admin_id'] ?? null,
                    'expires_at' => $_POST['expires_at'] ?: null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $message = 'Credit granted successfully.';
                $message_type = 'success';
            } elseif ($action === 'consume_credit') {
                $creditId = (int) $_POST['credit_id'];
                $consumeAmount = (float) $_POST['consume_amount'];
                $credit = db_fetch_one("SELECT * FROM credits WHERE id = ?", [$creditId]);
                if (!$credit) throw new Exception('Credit not found.');
                $remaining = (float) $credit['remaining_amount'];
                if ($consumeAmount > $remaining) throw new Exception('Cannot consume more than remaining balance.');
                $newRemaining = $remaining - $consumeAmount;
                db_update('credits', [
                    'remaining_amount' => $newRemaining,
                    'consumed_amount' => (float) $credit['consumed_amount'] + $consumeAmount,
                    'status' => $newRemaining <= 0 ? 'fully_consumed' : 'partially_consumed',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$creditId]);
                $message = "Credit consumed. Remaining: \${$newRemaining}.";
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ---- FILTERS ----
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$where = ['1=1'];
$params = [];
if ($search) {
    $where[] = '(t.name LIKE ? OR t.slug LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($type_filter) {
    $where[] = 'c.type = ?';
    $params[] = $type_filter;
}
$whereSql = implode(' AND ', $where);

// ---- STATS ----
$totalGranted = db_fetch_value("SELECT COALESCE(SUM(amount), 0) FROM credits");
$totalRemaining = db_fetch_value("SELECT COALESCE(SUM(remaining_amount), 0) FROM credits");
$totalConsumed = db_fetch_value("SELECT COALESCE(SUM(consumed_amount), 0) FROM credits");
$activeCredits = db_fetch_value("SELECT COUNT(*) FROM credits WHERE status IN ('active','partially_consumed')");

$total = db_fetch_value("SELECT COUNT(*) FROM credits c JOIN pos_tenants t ON c.tenant_id = t.id WHERE {$whereSql}", $params);

$credits = db_fetch_all(
    "SELECT c.*, t.name as tenant_name, t.slug
     FROM credits c
     JOIN pos_tenants t ON c.tenant_id = t.id
     WHERE {$whereSql}
     ORDER BY c.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

$tenants = db_fetch_all("SELECT id, name, slug FROM pos_tenants ORDER BY name ASC");

$current_page = 'revenue';
$page_title = 'Credit System';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Credit System</h1>
            <p class="text-slate-400 text-sm mt-1">Grant and manage tenant credits</p>
        </div>
        <button onclick="openModal('grantModal')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold"><i class="fas fa-plus mr-1"></i> Grant Credit</button>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-emerald-500/20 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/20 border-red-500/30 text-red-400' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Total Granted</p>
            <p class="text-white font-semibold text-lg">$<?= number_format($totalGranted, 2) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Total Remaining</p>
            <p class="text-emerald-400 font-semibold text-lg">$<?= number_format($totalRemaining, 2) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Consumed</p>
            <p class="text-amber-400 font-semibold text-lg">$<?= number_format($totalConsumed, 2) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Active Credits</p>
            <p class="text-blue-400 font-semibold text-lg"><?= $activeCredits ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tenants..." class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm flex-1 min-w-[200px]">
            <select name="type" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Types</option>
                <option value="promotional" <?= $type_filter === 'promotional' ? 'selected' : '' ?>>Promotional</option>
                <option value="goodwill" <?= $type_filter === 'goodwill' ? 'selected' : '' ?>>Goodwill</option>
                <option value="referral" <?= $type_filter === 'referral' ? 'selected' : '' ?>>Referral</option>
                <option value="refund" <?= $type_filter === 'refund' ? 'selected' : '' ?>>Refund</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
            <a href="credits.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <form method="POST" action="export_csv.php" class="inline" target="_blank">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="table" value="credits">
        <input type="hidden" name="columns" value='[{"field":"id","label":"Credit ID"},{"field":"tenant_id","label":"Tenant ID"},{"field":"amount","label":"Amount","format":"currency"},{"field":"remaining_amount","label":"Remaining","format":"currency"},{"field":"type","label":"Type"},{"field":"reason","label":"Reason"},{"field":"status","label":"Status","format":"status"},{"field":"created_at","label":"Created","format":"date"}]'>
        <input type="hidden" name="filename" value="credits">
        <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70 mb-4"><i class="fas fa-download mr-1"></i> Export CSV</button>
    </form>

    <!-- Credits Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="px-3 py-2.5">Tenant</th>
                        <th class="px-3 py-2.5">Type</th>
                        <th class="px-3 py-2.5 text-right">Granted</th>
                        <th class="px-3 py-2.5 text-right">Remaining</th>
                        <th class="px-3 py-2.5 text-right">Consumed</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">Expires</th>
                        <th class="px-3 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($credits)): ?>
                    <tr><td colspan="8" class="py-12 text-center text-slate-500"><i class="fas fa-coins text-3xl mb-3 block"></i>No credits found</td></tr>
                    <?php else: foreach ($credits as $c): ?>
                    <?php
                        $statusColor = match($c['status']) {
                            'active' => 'bg-emerald-500/20 text-emerald-400',
                            'partially_consumed' => 'bg-amber-500/20 text-amber-400',
                            'fully_consumed' => 'bg-slate-500/20 text-slate-400',
                            'expired' => 'bg-red-500/20 text-red-400',
                            default => 'bg-slate-500/20 text-slate-400',
                        };
                    ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5">
                            <a href="tenant_detail.php?id=<?= $c['tenant_id'] ?>" class="text-white hover:text-amber-400 transition text-sm font-medium"><?= htmlspecialchars($c['tenant_name']) ?></a>
                            <p class="text-slate-500 text-xs"><?= htmlspecialchars($c['slug']) ?></p>
                        </td>
                        <td class="px-3 py-2.5 text-slate-300 text-xs capitalize"><?= htmlspecialchars($c['type']) ?></td>
                        <td class="px-3 py-2.5 text-right text-white text-sm">$<?= number_format($c['amount'], 2) ?></td>
                        <td class="px-3 py-2.5 text-right text-emerald-400 text-sm font-medium">$<?= number_format($c['remaining_amount'], 2) ?></td>
                        <td class="px-3 py-2.5 text-right text-amber-400 text-sm">$<?= number_format($c['consumed_amount'], 2) ?></td>
                        <td class="px-3 py-2.5"><span class="px-2 py-0.5 rounded text-[10px] <?= $statusColor ?>"><?= ucfirst($c['status']) ?></span></td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= $c['expires_at'] ? date('M j, Y', strtotime($c['expires_at'])) : 'Never' ?></td>
                        <td class="px-3 py-2.5 text-right">
                            <?php if ((float) $c['remaining_amount'] > 0): ?>
                            <button onclick="openConsumeModal(<?= $c['id'] ?>, <?= $c['remaining_amount'] ?>)" class="px-3 py-1 bg-amber-500/20 rounded text-amber-400 text-xs hover:bg-amber-500/30">Consume</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php $totalPages = ceil($total / $per_page); if ($totalPages > 1): ?>
        <div class="px-5 py-4 border-t border-slate-700/60 flex items-center justify-between">
            <p class="text-slate-400 text-xs">Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?></p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&type=<?= urlencode($type_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&type=<?= urlencode($type_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&type=<?= urlencode($type_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Grant Credit Modal -->
<div id="grantModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-sm mx-4">
        <h3 class="text-lg font-bold text-white mb-4">Grant Credit</h3>
        <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="grant_credit">
            <div class="space-y-3">
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Tenant</label>
                    <select name="tenant_id" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        <option value="">Select tenant...</option>
                        <?php foreach ($tenants as $tn): ?>
                        <option value="<?= $tn['id'] ?>"><?= htmlspecialchars($tn['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Amount</label>
                    <input type="number" step="0.01" name="amount" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Type</label>
                    <select name="credit_type" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        <option value="promotional">Promotional</option>
                        <option value="goodwill">Goodwill</option>
                        <option value="referral">Referral</option>
                        <option value="refund">Refund</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Currency</label>
                    <select name="currency" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Reason</label>
                    <textarea name="reason" rows="2" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="Optional note..."></textarea>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Expires At</label>
                    <input type="date" name="expires_at" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeModal('grantModal')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Grant</button>
            </div>
        </form>
    </div>
</div>

<!-- Consume Credit Modal -->
<div id="consumeModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-sm mx-4">
        <h3 class="text-lg font-bold text-white mb-4">Consume Credit</h3>
        <p class="text-slate-400 text-xs mb-3">Remaining: <span id="consumeRemaining" class="text-emerald-400 font-medium"></span></p>
        <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="consume_credit">
            <input type="hidden" name="credit_id" id="consumeCreditId">
            <div>
                <label class="block text-slate-400 text-xs mb-1">Amount to Consume</label>
                <input type="number" step="0.01" name="consume_amount" required id="consumeAmount" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="0.00">
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeModal('consumeModal')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Consume</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.getElementById(id).classList.add('flex');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.getElementById(id).classList.remove('flex');
}
function openConsumeModal(creditId, remaining) {
    document.getElementById('consumeCreditId').value = creditId;
    document.getElementById('consumeRemaining').innerText = '$' + parseFloat(remaining).toFixed(2);
    document.getElementById('consumeAmount').max = remaining;
    openModal('consumeModal');
}
document.querySelectorAll('[id$="Modal"]').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

