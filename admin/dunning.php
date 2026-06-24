<?php
/**
 * Dunning Campaigns & Payment Attempts
 * View failed payment retries and dunning campaign status.
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
        $campaign_id = (int) ($_POST['campaign_id'] ?? 0);
        if ($action === 'cancel_dunning') {
            db_update('dunning_campaigns', ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$campaign_id]);
            $message = 'Dunning campaign cancelled.';
            $message_type = 'success';
        } elseif ($action === 'mark_recovered') {
            db_update('dunning_campaigns', ['status' => 'recovered', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$campaign_id]);
            $message = 'Marked as recovered.';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

$status_filter = $_GET['status'] ?? 'active';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$where = ['1=1'];
$params = [];
if ($status_filter) {
    $where[] = 'd.status = ?';
    $params[] = $status_filter;
}
$whereSql = implode(' AND ', $where);

// ---- STATS ----
$activeDunning = db_fetch_value("SELECT COUNT(*) FROM dunning_campaigns WHERE status = 'active'");
$recoveredCount = db_fetch_value("SELECT COUNT(*) FROM dunning_campaigns WHERE status = 'recovered' AND DATE(updated_at) = CURDATE()");
$churnedCount = db_fetch_value("SELECT COUNT(*) FROM dunning_campaigns WHERE status = 'churned'");
$totalAttempts = db_fetch_value("SELECT COUNT(*) FROM payment_attempts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");

// ---- CAMPAIGNS ----
$total = db_fetch_value("SELECT COUNT(*) FROM dunning_campaigns d WHERE {$whereSql}", $params);
$campaigns = db_fetch_all(
    "SELECT d.*, t.name as tenant_name, t.slug, t.email as tenant_email,
            s.plan_name, s.amount as subscription_amount
     FROM dunning_campaigns d
     LEFT JOIN pos_tenants t ON d.tenant_id = t.id
     LEFT JOIN pos_subscriptions s ON d.subscription_id = s.id
     WHERE {$whereSql}
     ORDER BY FIELD(d.status, 'active', 'recovered', 'cancelled', 'churned'), d.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

// ---- RECENT PAYMENT ATTEMPTS ----
$recentAttempts = db_fetch_all(
    "SELECT pa.*, t.name as tenant_name
     FROM payment_attempts pa
     LEFT JOIN pos_tenants t ON pa.tenant_id = t.id
     ORDER BY pa.created_at DESC
     LIMIT 20"
);

$current_page = 'subscriptions';
$page_title = 'Dunning Campaigns';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Dunning Campaigns</h1>
            <p class="text-slate-400 text-sm mt-1">Failed payment recovery and retry tracking</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-emerald-500/20 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/20 border-red-500/30 text-red-400' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Active Dunning</p>
            <p class="text-red-400 font-semibold text-lg"><?= $activeDunning ?></p>
            <p class="text-slate-500 text-xs">Retry campaigns</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Recovered Today</p>
            <p class="text-emerald-400 font-semibold text-lg"><?= $recoveredCount ?></p>
            <p class="text-slate-500 text-xs">Payments fixed</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Churned</p>
            <p class="text-slate-400 font-semibold text-lg"><?= $churnedCount ?></p>
            <p class="text-slate-500 text-xs">Lost to dunning</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Attempts (24h)</p>
            <p class="text-white font-semibold text-lg"><?= $totalAttempts ?></p>
            <p class="text-slate-500 text-xs">Payment retries</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" class="flex gap-3">
            <select name="status" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="recovered" <?= $status_filter === 'recovered' ? 'selected' : '' ?>>Recovered</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="churned" <?= $status_filter === 'churned' ? 'selected' : '' ?>>Churned</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
            <a href="dunning.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <!-- Campaigns Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-6">
        <div class="p-4 border-b border-slate-700/60">
            <h2 class="text-white font-semibold text-sm">Dunning Campaigns</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="px-3 py-2.5">Tenant</th>
                        <th class="px-3 py-2.5">Plan</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5 text-center">Attempt #</th>
                        <th class="px-3 py-2.5">Next Retry</th>
                        <th class="px-3 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                    <tr><td colspan="6" class="py-12 text-center text-slate-500"><i class="fas fa-sync-alt text-3xl mb-3 block"></i>No campaigns found</td></tr>
                    <?php else: foreach ($campaigns as $dc): ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5">
                            <a href="tenant_detail.php?id=<?= $dc['tenant_id'] ?>" class="text-white hover:text-amber-400 transition text-sm">
                                <?= htmlspecialchars($dc['tenant_name'] ?? 'Unknown') ?>
                            </a>
                            <p class="text-slate-500 text-xs"><?= htmlspecialchars($dc['tenant_email'] ?? '') ?></p>
                        </td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= htmlspecialchars($dc['plan_name'] ?? '-') ?></td>
                        <td class="px-3 py-2.5">
                            <span class="px-2 py-0.5 rounded text-xs <?= $dc['status'] === 'active' ? 'bg-red-500/20 text-red-400' : ($dc['status'] === 'recovered' ? 'bg-emerald-500/20 text-emerald-400' : ($dc['status'] === 'cancelled' ? 'bg-slate-500/20 text-slate-400' : 'bg-orange-500/20 text-orange-400')) ?>">
                                <?= ucfirst($dc['status']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center text-white text-sm"><?= $dc['attempt_count'] ?></td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs">
                            <?= $dc['next_retry_at'] ? date('M j, g:i a', strtotime($dc['next_retry_at'])) : '—' ?>
                        </td>
                        <td class="px-3 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <?php if ($dc['status'] === 'active'): ?>
                                <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="mark_recovered">
                                    <input type="hidden" name="campaign_id" value="<?= $dc['id'] ?>">
                                    <button type="submit" class="px-2 py-1 bg-emerald-500/20 rounded text-emerald-400 text-xs hover:bg-emerald-500/30">Recover</button>
                                </form>
                                <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="cancel_dunning">
                                    <input type="hidden" name="campaign_id" value="<?= $dc['id'] ?>">
                                    <button type="submit" class="px-2 py-1 bg-slate-500/20 rounded text-slate-400 text-xs hover:bg-slate-500/30">Cancel</button>
                                </form>
                                <?php endif; ?>
                            </div>
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
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Payment Attempts -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="p-4 border-b border-slate-700/60">
            <h2 class="text-white font-semibold text-sm">Recent Payment Attempts</h2>
        </div>
        <div class="overflow-x-auto max-h-80 overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-800/50 sticky top-0">
                    <tr class="text-slate-400 text-xs">
                        <th class="text-left py-2 px-4">Tenant</th>
                        <th class="text-left py-2 px-4">Gateway</th>
                        <th class="text-right py-2 px-4">Amount</th>
                        <th class="text-left py-2 px-4">Status</th>
                        <th class="text-left py-2 px-4">Error</th>
                        <th class="text-left py-2 px-4">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/30">
                    <?php if (empty($recentAttempts)): ?>
                    <tr><td colspan="6" class="py-6 text-center text-slate-500 text-xs">No payment attempts</td></tr>
                    <?php else: foreach ($recentAttempts as $pa): ?>
                    <tr class="hover:bg-slate-700/30">
                        <td class="py-2 px-4 text-white text-xs"><?= htmlspecialchars($pa['tenant_name'] ?? 'Unknown') ?></td>
                        <td class="py-2 px-4 text-slate-400 text-xs"><?= ucfirst($pa['gateway'] ?? 'N/A') ?></td>
                        <td class="py-2 px-4 text-right text-white text-xs">$<?= number_format($pa['amount'] ?? 0, 2) ?></td>
                        <td class="py-2 px-4">
                            <span class="px-1.5 py-0.5 rounded text-[10px] <?= $pa['status'] === 'succeeded' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400' ?>">
                                <?= ucfirst($pa['status']) ?>
                            </span>
                        </td>
                        <td class="py-2 px-4 text-slate-500 text-xs"><?= htmlspecialchars($pa['error_message'] ?? '—') ?></td>
                        <td class="py-2 px-4 text-slate-400 text-xs"><?= date('M j, g:i a', strtotime($pa['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

