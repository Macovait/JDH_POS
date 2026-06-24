<?php
/**
 * System Alerts
 * View and manage platform alerts from monitoring tables.
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
            $alert_id = (int) ($_POST['alert_id'] ?? 0);
            if ($action === 'acknowledge') {
                db_update('system_alerts', ['status' => 'acknowledged', 'resolved_at' => date('Y-m-d H:i:s')], 'id = ?', [$alert_id]);
                $message = 'Alert acknowledged.';
                $message_type = 'success';
            } elseif ($action === 'resolve') {
                db_update('system_alerts', ['status' => 'resolved', 'resolved_at' => date('Y-m-d H:i:s')], 'id = ?', [$alert_id]);
                $message = 'Alert resolved.';
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

$severity_filter = $_GET['severity'] ?? '';
$status_filter = $_GET['status'] ?? 'active';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$where = ['1=1'];
$params = [];
if ($severity_filter) {
    $where[] = 'severity = ?';
    $params[] = $severity_filter;
}
if ($status_filter) {
    $where[] = 'status = ?';
    $params[] = $status_filter;
}
$whereSql = implode(' AND ', $where);

// ---- STATS ----
$criticalCount = db_fetch_value("SELECT COUNT(*) FROM system_alerts WHERE severity = 'critical' AND status = 'active'");
$warningCount = db_fetch_value("SELECT COUNT(*) FROM system_alerts WHERE severity = 'warning' AND status = 'active'");
$infoCount = db_fetch_value("SELECT COUNT(*) FROM system_alerts WHERE severity = 'info' AND status = 'active'");

// ---- ALERTS ----
$total = db_fetch_value("SELECT COUNT(*) FROM system_alerts WHERE {$whereSql}", $params);
$alerts = db_fetch_all(
    "SELECT * FROM system_alerts WHERE {$whereSql} ORDER BY FIELD(severity, 'critical', 'warning', 'info'), created_at DESC LIMIT {$per_page} OFFSET {$offset}",
    $params
);

$current_page = 'monitoring';
$page_title = 'System Alerts';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">System Alerts</h1>
            <p class="text-slate-400 text-sm mt-1">Monitor platform health and anomalies</p>
        </div>
        <a href="monitoring.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70 transition">
            <i class="fas fa-arrow-left mr-1"></i> Back to Monitoring
        </a>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-emerald-500/20 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/20 border-red-500/30 text-red-400' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 border-red-500/20">
            <p class="text-red-400 text-xs mb-1">Critical</p>
            <p class="text-white font-semibold text-lg"><?= $criticalCount ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 border-yellow-500/20">
            <p class="text-yellow-400 text-xs mb-1">Warnings</p>
            <p class="text-white font-semibold text-lg"><?= $warningCount ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 border-blue-500/20">
            <p class="text-blue-400 text-xs mb-1">Info</p>
            <p class="text-white font-semibold text-lg"><?= $infoCount ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-3">
            <select name="severity" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Severities</option>
                <option value="critical" <?= $severity_filter === 'critical' ? 'selected' : '' ?>>Critical</option>
                <option value="warning" <?= $severity_filter === 'warning' ? 'selected' : '' ?>>Warning</option>
                <option value="info" <?= $severity_filter === 'info' ? 'selected' : '' ?>>Info</option>
            </select>
            <select name="status" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="acknowledged" <?= $status_filter === 'acknowledged' ? 'selected' : '' ?>>Acknowledged</option>
                <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
            <a href="alerts.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <!-- Alerts Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="px-3 py-2.5">Severity</th>
                        <th class="px-3 py-2.5">Alert</th>
                        <th class="px-3 py-2.5">Source</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">Created</th>
                        <th class="px-3 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alerts)): ?>
                    <tr><td colspan="6" class="py-12 text-center text-slate-500"><i class="fas fa-bell text-3xl mb-3 block"></i>No alerts found</td></tr>
                    <?php else: foreach ($alerts as $a): ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5">
                            <span class="px-2 py-0.5 rounded text-xs <?= $a['severity'] === 'critical' ? 'bg-red-500/20 text-red-400' : ($a['severity'] === 'warning' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-blue-500/20 text-blue-400') ?>">
                                <?= ucfirst($a['severity']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <p class="text-white text-sm"><?= htmlspecialchars($a['title']) ?></p>
                            <p class="text-slate-500 text-xs"><?= htmlspecialchars($a['message'] ?? '') ?></p>
                        </td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= htmlspecialchars($a['source'] ?? 'System') ?></td>
                        <td class="px-3 py-2.5">
                            <span class="px-2 py-0.5 rounded text-xs <?= $a['status'] === 'active' ? 'bg-red-500/20 text-red-400' : ($a['status'] === 'acknowledged' ? 'bg-purple-500/20 text-purple-400' : 'bg-emerald-500/20 text-emerald-400') ?>">
                                <?= ucfirst($a['status']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= date('M j, g:i a', strtotime($a['created_at'])) ?></td>
                        <td class="px-3 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <?php if ($a['status'] === 'active'): ?>
                                <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="acknowledge">
                                    <input type="hidden" name="alert_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="px-2 py-1 bg-purple-500/20 rounded text-purple-400 text-xs hover:bg-purple-500/30">Ack</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($a['status'] !== 'resolved'): ?>
                                <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="resolve">
                                    <input type="hidden" name="alert_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="px-2 py-1 bg-emerald-500/20 rounded text-emerald-400 text-xs hover:bg-emerald-500/30">Resolve</button>
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
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&severity=<?= urlencode($severity_filter) ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&severity=<?= urlencode($severity_filter) ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&severity=<?= urlencode($severity_filter) ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

