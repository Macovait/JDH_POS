<?php
/**
 * Subscription History Timeline
 * Visual timeline of subscription events per tenant.
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

// ---- FILTERS ----
$tenant_id = (int) ($_GET['tenant_id'] ?? 0);
$event_type = $_GET['event_type'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;

// ---- STATS ----
$totalEvents = db_fetch_value("SELECT COUNT(*) FROM subscription_history");
$activeSubs = db_fetch_value("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'active'");
$totalUpgrades = db_fetch_value("SELECT COUNT(*) FROM subscription_history WHERE event_type = 'plan_change' AND new_plan_id > old_plan_id");
$totalDowngrades = db_fetch_value("SELECT COUNT(*) FROM subscription_history WHERE event_type = 'plan_change' AND new_plan_id < old_plan_id");

// ---- TENANTS FOR FILTER ----
$tenants = db_fetch_all("SELECT id, name, slug FROM pos_tenants ORDER BY name ASC LIMIT 50");

// ---- HISTORY QUERY ----
$where = ['1=1'];
$params = [];
if ($tenant_id) {
    $where[] = 'h.tenant_id = ?';
    $params[] = $tenant_id;
}
if ($event_type) {
    $where[] = 'h.event_type = ?';
    $params[] = $event_type;
}
if ($search) {
    $where[] = '(t.name LIKE ? OR t.slug LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$whereSql = implode(' AND ', $where);

$total = db_fetch_value("SELECT COUNT(*) FROM subscription_history h JOIN pos_tenants t ON h.tenant_id = t.id WHERE {$whereSql}", $params);

$events = db_fetch_all(
    "SELECT h.*, t.name as tenant_name, t.slug,
            old.name as old_plan_name, new.name as new_plan_name
     FROM subscription_history h
     JOIN pos_tenants t ON h.tenant_id = t.id
     LEFT JOIN pos_plans old ON h.old_plan_id = old.id
     LEFT JOIN pos_plans new ON h.new_plan_id = new.id
     WHERE {$whereSql}
     ORDER BY h.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

$current_page = 'subscriptions';
$page_title = 'Subscription History';

// Timeline icon map
$iconMap = [
    'created' => ['fa-plus-circle', 'emerald'],
    'activated' => ['fa-check-circle', 'emerald'],
    'renewed' => ['fa-sync-alt', 'blue'],
    'cancelled' => ['fa-times-circle', 'red'],
    'plan_change' => ['fa-exchange-alt', 'amber'],
    'payment_success' => ['fa-check', 'emerald'],
    'payment_failed' => ['fa-exclamation-circle', 'red'],
    'trial_started' => ['fa-play-circle', 'blue'],
    'trial_ended' => ['fa-stop-circle', 'gray'],
    'suspended' => ['fa-pause-circle', 'amber'],
    'resumed' => ['fa-play', 'emerald'],
];

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Subscription History</h1>
            <p class="text-slate-400 text-sm mt-1">Timeline of all subscription lifecycle events</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Total Events</p>
            <p class="text-white font-semibold text-lg"><?= number_format($totalEvents) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Active Subs</p>
            <p class="text-emerald-400 font-semibold text-lg"><?= $activeSubs ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Upgrades</p>
            <p class="text-amber-400 font-semibold text-lg"><?= $totalUpgrades ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Downgrades</p>
            <p class="text-rose-400 font-semibold text-lg"><?= $totalDowngrades ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-3">
            <select name="tenant_id" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Tenants</option>
                <?php foreach ($tenants as $tn): ?>
                <option value="<?= $tn['id'] ?>" <?= $tenant_id === (int)$tn['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tn['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="event_type" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Events</option>
                <?php foreach (array_keys($iconMap) as $et): ?>
                <option value="<?= $et ?>" <?= $event_type === $et ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $et)) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm flex-1 min-w-[150px]">
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
            <a href="subscription_history.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <!-- Timeline -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 mb-6">
        <?php if (empty($events)): ?>
        <div class="text-center py-12">
            <i class="fas fa-history text-3xl text-slate-600 mb-3 block"></i>
            <p class="text-slate-500">No events found matching your filters.</p>
        </div>
        <?php else: ?>
        <div class="relative">
            <!-- Vertical line -->
            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-slate-700"></div>

            <div class="space-y-6">
                <?php foreach ($events as $e):
                    $iconInfo = $iconMap[$e['event_type']] ?? ['fa-circle', 'gray'];
                    $iconClass = $iconInfo[0];
                    $color = $iconInfo[1];
                ?>
                <div class="relative flex items-start gap-4 pl-10">
                    <!-- Icon dot -->
                    <div class="absolute left-0 top-0 w-8 h-8 rounded-full bg-<?= $color ?>-500/20 border border-<?= $color ?>-500/40 flex items-center justify-center shrink-0">
                        <i class="fas <?= $iconClass ?> text-<?= $color ?>-400 text-xs"></i>
                    </div>

                    <div class="flex-1 min-w-0 bg-slate-800/50 rounded-lg p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-white text-sm font-medium"><?= ucfirst(str_replace('_', ' ', $e['event_type'])) ?></span>
                                    <?php if ($e['old_plan_name'] && $e['new_plan_name']): ?>
                                    <span class="text-slate-400 text-xs"><?= htmlspecialchars($e['old_plan_name']) ?> → <?= htmlspecialchars($e['new_plan_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <a href="tenant_detail.php?id=<?= $e['tenant_id'] ?>" class="text-amber-400 text-xs hover:text-amber-300"><?= htmlspecialchars($e['tenant_name']) ?></a>
                                <?php if ($e['metadata']):
                                    $meta = json_decode($e['metadata'], true);
                                    if ($meta && is_array($meta)):
                                ?>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <?php foreach (array_slice($meta, 0, 3) as $k => $v): ?>
                                    <span class="px-1.5 py-0.5 bg-slate-800/50 rounded text-slate-500 text-[10px]"><?= htmlspecialchars($k) ?>: <?= htmlspecialchars((string)$v) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; endif; ?>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-slate-400 text-xs whitespace-nowrap"><?= date('M j, Y', strtotime($e['created_at'])) ?></p>
                                <p class="text-slate-600 text-[10px]"><?= date('g:i a', strtotime($e['created_at'])) ?></p>
                                <?php if ($e['admin_id']): ?>
                                <p class="text-slate-600 text-[10px] mt-0.5">by Admin #<?= $e['admin_id'] ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php $totalPages = ceil($total / $per_page); if ($totalPages > 1): ?>
    <div class="flex items-center justify-between">
        <p class="text-slate-400 text-xs">Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?></p>
        <div class="flex gap-2">
            <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&tenant_id=<?= $tenant_id ?>&event_type=<?= urlencode($event_type) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?page=<?= $i ?>&tenant_id=<?= $tenant_id ?>&event_type=<?= urlencode($event_type) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&tenant_id=<?= $tenant_id ?>&event_type=<?= urlencode($event_type) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

