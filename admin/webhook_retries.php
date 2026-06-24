<?php
/**
 * Webhook Retry UI
 * Manage failed Stripe webhooks and retry delivery.
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
        if ($action === 'retry') {
            $failure = db_fetch_one("SELECT * FROM webhook_failures WHERE id = ?", [(int) $_POST['failure_id']]);
            if (!$failure) throw new Exception('Failure record not found.');
            // Simulate retry by updating status
            db_update('webhook_failures', [
                'status' => 'retried',
                'retry_count' => (int) $failure['retry_count'] + 1,
                'last_retry_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$failure['id']]);
            $message = 'Webhook marked for retry.';
            $message_type = 'success';
        } elseif ($action === 'dismiss') {
            db_update('webhook_failures', [
                'status' => 'dismissed',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $_POST['failure_id']]);
            $message = 'Webhook failure dismissed.';
            $message_type = 'success';
        } elseif ($action === 'bulk_retry') {
            $ids = array_map('intval', $_POST['failure_ids'] ?? []);
            if (empty($ids)) throw new Exception('No items selected.');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            db_query("UPDATE webhook_failures SET status = 'retried', retry_count = retry_count + 1, last_retry_at = NOW(), updated_at = NOW() WHERE id IN ({$placeholders})", $ids);
            $message = count($ids) . ' webhook(s) marked for retry.';
            $message_type = 'success';
        } elseif ($action === 'bulk_dismiss') {
            $ids = array_map('intval', $_POST['failure_ids'] ?? []);
            if (empty($ids)) throw new Exception('No items selected.');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            db_query("UPDATE webhook_failures SET status = 'dismissed', updated_at = NOW() WHERE id IN ({$placeholders})", $ids);
            $message = count($ids) . ' webhook failure(s) dismissed.';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

// ---- FILTERS ----
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// ---- STATS ----
$totalFailures = db_fetch_value("SELECT COUNT(*) FROM webhook_failures");
$pendingFailures = db_fetch_value("SELECT COUNT(*) FROM webhook_failures WHERE status = 'pending'");
$retriedCount = db_fetch_value("SELECT COUNT(*) FROM webhook_failures WHERE status = 'retried'");
$dismissedCount = db_fetch_value("SELECT COUNT(*) FROM webhook_failures WHERE status = 'dismissed'");

$where = ['1=1'];
$params = [];
if ($status_filter) {
    $where[] = 'w.status = ?';
    $params[] = $status_filter;
}
if ($search) {
    $where[] = '(t.name LIKE ? OR w.webhook_type LIKE ? OR w.error_message LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$whereSql = implode(' AND ', $where);

$total = db_fetch_value("SELECT COUNT(*) FROM webhook_failures w JOIN pos_tenants t ON w.tenant_id = t.id WHERE {$whereSql}", $params);

$failures = db_fetch_all(
    "SELECT w.*, t.name as tenant_name, t.slug
     FROM webhook_failures w
     JOIN pos_tenants t ON w.tenant_id = t.id
     WHERE {$whereSql}
     ORDER BY w.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

$current_page = 'monitoring';
$page_title = 'Webhook Retries';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Webhook Retries</h1>
            <p class="text-slate-400 text-sm mt-1">Manage failed webhook deliveries and retry them</p>
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
            <p class="text-slate-400 text-xs mb-1">Total Failures</p>
            <p class="text-white font-semibold text-lg"><?= $totalFailures ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Pending Retry</p>
            <p class="text-rose-400 font-semibold text-lg"><?= $pendingFailures ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Retried</p>
            <p class="text-blue-400 font-semibold text-lg"><?= $retriedCount ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Dismissed</p>
            <p class="text-slate-400 font-semibold text-lg"><?= $dismissedCount ?></p>
        </div>
    </div>

    <!-- Filters + Bulk Actions -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" id="filterForm" class="flex flex-wrap gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search webhooks..." class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm flex-1 min-w-[200px]">
            <select name="status" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="retried" <?= $status_filter === 'retried' ? 'selected' : '' ?>>Retried</option>
                <option value="dismissed" <?= $status_filter === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
            <a href="webhook_retries.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <!-- Bulk Actions Bar -->
    <div class="flex gap-2 mb-4">
        <button type="button" onclick="bulkAction('bulk_retry')" class="px-3 py-1.5 bg-blue-500/20 rounded text-blue-400 text-xs hover:bg-blue-500/30"><i class="fas fa-redo mr-1"></i> Retry Selected</button>
        <button type="button" onclick="bulkAction('bulk_dismiss')" class="px-3 py-1.5 bg-slate-500/20 rounded text-slate-400 text-xs hover:bg-slate-500/30"><i class="fas fa-times mr-1"></i> Dismiss Selected</button>
        <label class="flex items-center gap-2 text-slate-400 text-xs ml-auto cursor-pointer">
            <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
            Select All
        </label>
    </div>

    <!-- Failures Table -->
    <form method="POST" id="bulkForm">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                            <th class="px-3 py-2.5 w-8"></th>
                            <th class="px-3 py-2.5">Tenant</th>
                            <th class="px-3 py-2.5">Webhook Type</th>
                            <th class="px-3 py-2.5">Error</th>
                            <th class="px-3 py-2.5 text-right">Retries</th>
                            <th class="px-3 py-2.5">Status</th>
                            <th class="px-3 py-2.5">Failed At</th>
                            <th class="px-3 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($failures)): ?>
                        <tr><td colspan="8" class="py-12 text-center text-slate-500"><i class="fas fa-plug text-3xl mb-3 block"></i>No webhook failures found</td></tr>
                        <?php else: foreach ($failures as $f):
                            $statusColor = match($f['status']) {
                                'pending' => 'bg-rose-500/20 text-rose-400',
                                'retried' => 'bg-blue-500/20 text-blue-400',
                                'dismissed' => 'bg-slate-500/20 text-slate-400',
                                default => 'bg-slate-500/20 text-slate-400',
                            };
                        ?>
                        <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                            <td class="px-3 py-2.5"><input type="checkbox" name="failure_ids[]" value="<?= $f['id'] ?>" class="bulk-checkbox accent-amber-500"></td>
                            <td class="px-3 py-2.5">
                                <a href="tenant_detail.php?id=<?= $f['tenant_id'] ?>" class="text-white hover:text-amber-400 transition text-sm"><?= htmlspecialchars($f['tenant_name']) ?></a>
                            </td>
                            <td class="px-3 py-2.5 text-slate-300 text-xs font-mono"><?= htmlspecialchars($f['webhook_type']) ?></td>
                            <td class="px-3 py-2.5 text-slate-400 text-xs max-w-xs truncate" title="<?= htmlspecialchars($f['error_message']) ?>"><?= htmlspecialchars($f['error_message']) ?></td>
                            <td class="px-3 py-2.5 text-right text-slate-300 text-xs"><?= $f['retry_count'] ?></td>
                            <td class="px-3 py-2.5"><span class="px-2 py-0.5 rounded text-[10px] <?= $statusColor ?>"><?= ucfirst($f['status']) ?></span></td>
                            <td class="px-3 py-2.5 text-slate-400 text-xs"><?= date('M j, g:i a', strtotime($f['created_at'])) ?></td>
                            <td class="px-3 py-2.5 text-right">
                                <?php if ($f['status'] === 'pending'): ?>
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="action" value="retry">
                                        <input type="hidden" name="failure_id" value="<?= $f['id'] ?>">
                                        <button type="submit" class="w-7 h-7 bg-blue-500/20 rounded flex items-center justify-center text-blue-400 hover:bg-blue-500/30" title="Retry"><i class="fas fa-redo text-[10px]"></i></button>
                                    </form>
                                    <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="action" value="dismiss">
                                        <input type="hidden" name="failure_id" value="<?= $f['id'] ?>">
                                        <button type="submit" class="w-7 h-7 bg-slate-500/20 rounded flex items-center justify-center text-slate-400 hover:bg-slate-500/30" title="Dismiss"><i class="fas fa-times text-[10px]"></i></button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <?php $totalPages = ceil($total / $per_page); if ($totalPages > 1): ?>
    <div class="mt-4 flex items-center justify-between">
        <p class="text-slate-400 text-xs">Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?></p>
        <div class="flex gap-2">
            <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleAll(checkbox) {
    document.querySelectorAll('.bulk-checkbox').forEach(cb => cb.checked = checkbox.checked);
}
function bulkAction(action) {
    const checked = document.querySelectorAll('.bulk-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one item.');
        return;
    }
    if (!confirm(checked.length + ' item(s) selected. Proceed?')) return;
    const form = document.getElementById('bulkForm');
    let input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'action';
    input.value = action;
    form.appendChild(input);
    form.submit();
}
</script>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

