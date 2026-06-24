<?php
/**
 * Webhook Logs Viewer
 * View processed Stripe webhook events and webhook failures.
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$type_filter = $_GET['type'] ?? '';
$where = '1=1';
$params = [];
if ($type_filter) {
    $where .= " AND type = ?";
    $params[] = $type_filter;
}

$total = db_fetch_value("SELECT COUNT(*) FROM processed_stripe_events WHERE {$where}", $params);
$events = db_fetch_all(
    "SELECT * FROM processed_stripe_events WHERE {$where} ORDER BY processed_at DESC LIMIT {$per_page} OFFSET {$offset}",
    $params
);

$types = db_fetch_all("SELECT DISTINCT type FROM processed_stripe_events ORDER BY type");

$current_page = 'monitoring';
$page_title = 'Webhook Logs';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Webhook Logs</h1>
            <p class="text-slate-400 text-sm">Stripe webhook events received</p>
        </div>
        <a href="monitoring.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70 transition">
            <i class="fas fa-arrow-left mr-1"></i> Back to Monitoring
        </a>
    </div>

    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6">
        <form method="GET" class="flex gap-3 mb-4">
            <select name="type" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Types</option>
                <?php foreach ($types as $t): ?>
                <option value="<?= htmlspecialchars($t['type']) ?>" <?= $type_filter === $t['type'] ? 'selected' : '' ?>><?= htmlspecialchars($t['type']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="pb-2 pr-4">Time</th>
                        <th class="pb-2 pr-4">Event Type</th>
                        <th class="pb-2 pr-4">Stripe Event ID</th>
                        <th class="pb-2">Payload Preview</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-0">
                            <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                                <div class="w-20 h-20 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center mb-5">
                                    <i class="fas fa-webhook text-3xl text-cyan-400"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-white mb-2">No webhook events</h3>
                                <p class="text-sm text-slate-500 max-w-sm mb-5">
                                    Webhook events will appear here when Stripe sends notifications. 
                                    Make sure your webhook endpoint is configured in Stripe Dashboard.
                                </p>
                                <a href="https://dashboard.stripe.com/webhooks" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-sm font-medium hover:bg-cyan-500/20 transition-colors">
                                    <i class="fas fa-external-link-alt text-xs"></i>
                                    Stripe Webhooks
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php else: foreach ($events as $e): ?>
                    <tr class="border-b border-slate-700/30">
                        <td class="py-3 pr-4 text-slate-400 whitespace-nowrap"><?= date('M j, Y g:i:s a', strtotime($e['processed_at'])) ?></td>
                        <td class="py-3 pr-4">
                            <span class="px-2 py-0.5 rounded text-xs bg-slate-700/50 text-slate-300"><?= htmlspecialchars($e['type']) ?></span>
                        </td>
                        <td class="py-3 pr-4 text-slate-400 font-mono text-xs"><?= htmlspecialchars(substr($e['stripe_event_id'], 0, 20)) ?>...</td>
                        <td class="py-3">
                            <?php $payload = json_decode($e['payload'], true); ?>
                            <details class="text-xs">
                                <summary class="text-amber-400 cursor-pointer">View payload</summary>
                                <pre class="mt-2 p-2 bg-black/30 rounded text-slate-300 overflow-x-auto text-[10px]"><?= htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT)) ?></pre>
                            </details>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php $total_pages = ceil($total / $per_page); if ($total_pages > 1): ?>
        <div class="mt-4 flex items-center justify-between">
            <p class="text-slate-400 text-xs">Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?></p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&type=<?= urlencode($type_filter) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&type=<?= urlencode($type_filter) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?><a href="?page=<?= $page + 1 ?>&type=<?= urlencode($type_filter) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

