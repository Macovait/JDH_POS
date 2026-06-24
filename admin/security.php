<?php
/**
 * Security & Threats Dashboard
 * View suspicious activity logs and API rate limit violations.
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
        $log_id = (int) ($_POST['log_id'] ?? 0);
        if ($action === 'dismiss_suspicious') {
            db_update('suspicious_activity_logs', ['status' => 'dismissed'], 'id = ?', [$log_id]);
            $message = 'Dismissed.';
            $message_type = 'success';
        } elseif ($action === 'ban_ip') {
            // Insert into a simple banned_ips table if it exists, otherwise just log
            $ip = $_POST['ip_address'] ?? '';
            if ($ip) {
                $pdo->prepare("INSERT INTO suspicious_activity_logs (tenant_id, event_type, description, ip_address, severity, status, created_at) VALUES (0, 'ip_banned', 'IP manually banned from security dashboard', ?, 'high', 'active', NOW())")
                    ->execute([$ip]);
            }
            $message = 'IP marked for blocking.';
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
$tab = $_GET['tab'] ?? 'suspicious';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// ---- STATS ----
$suspiciousToday = db_fetch_value("SELECT COUNT(*) FROM suspicious_activity_logs WHERE DATE(created_at) = CURDATE()");
$rateLimitToday = db_fetch_value("SELECT COUNT(*) FROM api_rate_limit_violations WHERE DATE(created_at) = CURDATE()");
$highSeverity = db_fetch_value("SELECT COUNT(*) FROM suspicious_activity_logs WHERE severity IN ('high', 'critical') AND status = 'active'");
$uniqueIPs = db_fetch_value("SELECT COUNT(DISTINCT ip_address) FROM suspicious_activity_logs WHERE DATE(created_at) = CURDATE() AND ip_address IS NOT NULL");

// ---- SUSPICIOUS ACTIVITY ----
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

$totalSuspicious = db_fetch_value("SELECT COUNT(*) FROM suspicious_activity_logs WHERE {$whereSql}", $params);
$suspiciousLogs = db_fetch_all(
    "SELECT s.*, t.name as tenant_name, t.slug
     FROM suspicious_activity_logs s
     LEFT JOIN pos_tenants t ON s.tenant_id = t.id
     WHERE {$whereSql}
     ORDER BY s.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

// ---- RATE LIMIT VIOLATIONS ----
$rateLimitViolations = db_fetch_all(
    "SELECT v.*, t.name as tenant_name, t.slug
     FROM api_rate_limit_violations v
     LEFT JOIN pos_tenants t ON v.tenant_id = t.id
     ORDER BY v.created_at DESC
     LIMIT 50"
);

// ---- TOP OFFENDING IPS ----
$topIPs = db_fetch_all(
    "SELECT ip_address, COUNT(*) as count, MAX(created_at) as last_seen
     FROM suspicious_activity_logs
     WHERE ip_address IS NOT NULL
     GROUP BY ip_address
     ORDER BY count DESC
     LIMIT 10"
);

$current_page = 'audit_logs';
$page_title = 'Security & Threats';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Security & Threats</h1>
            <p class="text-slate-400 text-sm mt-1">Suspicious activity and rate limit monitoring</p>
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
            <p class="text-slate-400 text-xs mb-1">Suspicious Today</p>
            <p class="text-white font-semibold text-lg"><?= $suspiciousToday ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Rate Limits Today</p>
            <p class="text-yellow-400 font-semibold text-lg"><?= $rateLimitToday ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">High Severity</p>
            <p class="text-red-400 font-semibold text-lg"><?= $highSeverity ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Unique IPs Today</p>
            <p class="text-blue-400 font-semibold text-lg"><?= $uniqueIPs ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-4 border-b border-slate-700/60 mb-6">
        <a href="?tab=suspicious" class="pb-3 text-sm font-medium <?= $tab === 'suspicious' ? 'text-amber-400 border-b-2 border-amber-400' : 'text-slate-400 hover:text-white' ?>">Suspicious Activity</a>
        <a href="?tab=rate_limits" class="pb-3 text-sm font-medium <?= $tab === 'rate_limits' ? 'text-amber-400 border-b-2 border-amber-400' : 'text-slate-400 hover:text-white' ?>">Rate Limit Violations</a>
        <a href="?tab=ips" class="pb-3 text-sm font-medium <?= $tab === 'ips' ? 'text-amber-400 border-b-2 border-amber-400' : 'text-slate-400 hover:text-white' ?>">Top Offending IPs</a>
    </div>

    <?php if ($tab === 'suspicious'): ?>
    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" class="flex gap-3">
            <input type="hidden" name="tab" value="suspicious">
            <select name="severity" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Severities</option>
                <option value="low" <?= $severity_filter === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="medium" <?= $severity_filter === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="high" <?= $severity_filter === 'high' ? 'selected' : '' ?>>High</option>
                <option value="critical" <?= $severity_filter === 'critical' ? 'selected' : '' ?>>Critical</option>
            </select>
            <select name="status" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="investigating" <?= $status_filter === 'investigating' ? 'selected' : '' ?>>Investigating</option>
                <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="dismissed" <?= $status_filter === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
            <a href="security.php?tab=suspicious" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <!-- Suspicious Activity Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="px-3 py-2.5">Time</th>
                        <th class="px-3 py-2.5">Tenant</th>
                        <th class="px-3 py-2.5">Event</th>
                        <th class="px-3 py-2.5">Severity</th>
                        <th class="px-3 py-2.5">IP Address</th>
                        <th class="px-3 py-2.5">Description</th>
                        <th class="px-3 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suspiciousLogs)): ?>
                    <tr><td colspan="7" class="py-12 text-center text-slate-500"><i class="fas fa-shield-alt text-3xl mb-3 block"></i>No suspicious activity found</td></tr>
                    <?php else: foreach ($suspiciousLogs as $log): ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= date('M j, g:i a', strtotime($log['created_at'])) ?></td>
                        <td class="px-3 py-2.5">
                            <?php if ($log['tenant_id']): ?>
                            <a href="tenant_detail.php?id=<?= $log['tenant_id'] ?>" class="text-white hover:text-amber-400 transition text-sm"><?= htmlspecialchars($log['tenant_name'] ?? 'Unknown') ?></a>
                            <?php else: ?>
                            <span class="text-slate-500 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5 text-white text-xs"><?= htmlspecialchars($log['event_type']) ?></td>
                        <td class="px-3 py-2.5">
                            <span class="px-2 py-0.5 rounded text-xs <?= $log['severity'] === 'critical' ? 'bg-red-500/20 text-red-400' : ($log['severity'] === 'high' ? 'bg-orange-500/20 text-orange-400' : ($log['severity'] === 'medium' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-slate-500/20 text-slate-400')) ?>">
                                <?= ucfirst($log['severity']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs font-mono"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs max-w-xs truncate" title="<?= htmlspecialchars($log['description'] ?? '') ?>"><?= htmlspecialchars($log['description'] ?? '—') ?></td>
                        <td class="px-3 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <?php if ($log['ip_address']): ?>
                                <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="ban_ip">
                                    <input type="hidden" name="ip_address" value="<?= htmlspecialchars($log['ip_address']) ?>">
                                    <button type="submit" class="px-2 py-1 bg-red-500/20 rounded text-red-400 text-xs hover:bg-red-500/30">Ban IP</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($log['status'] === 'active'): ?>
                                <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="dismiss_suspicious">
                                    <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                    <button type="submit" class="px-2 py-1 bg-slate-500/20 rounded text-slate-400 text-xs hover:bg-slate-500/30">Dismiss</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php $totalPages = ceil($totalSuspicious / $per_page); if ($totalPages > 1): ?>
        <div class="px-5 py-4 border-t border-slate-700/60 flex items-center justify-between">
            <p class="text-slate-400 text-xs">Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $totalSuspicious) ?> of <?= $totalSuspicious ?></p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?><a href="?tab=suspicious&page=<?= $page - 1 ?>&severity=<?= urlencode($severity_filter) ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?tab=suspicious&page=<?= $i ?>&severity=<?= urlencode($severity_filter) ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?tab=suspicious&page=<?= $page + 1 ?>&severity=<?= urlencode($severity_filter) ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'rate_limits'): ?>
    <!-- Rate Limit Violations -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="px-3 py-2.5">Time</th>
                        <th class="px-3 py-2.5">Tenant</th>
                        <th class="px-3 py-2.5">Endpoint</th>
                        <th class="px-3 py-2.5 text-right">Requests</th>
                        <th class="px-3 py-2.5 text-right">Limit</th>
                        <th class="px-3 py-2.5">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rateLimitViolations)): ?>
                    <tr><td colspan="6" class="py-12 text-center text-slate-500"><i class="fas fa-tachometer-alt text-3xl mb-3 block"></i>No rate limit violations</td></tr>
                    <?php else: foreach ($rateLimitViolations as $rl): ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= date('M j, g:i a', strtotime($rl['created_at'])) ?></td>
                        <td class="px-3 py-2.5">
                            <?php if ($rl['tenant_id']): ?>
                            <a href="tenant_detail.php?id=<?= $rl['tenant_id'] ?>" class="text-white hover:text-amber-400 transition text-sm"><?= htmlspecialchars($rl['tenant_name'] ?? 'Unknown') ?></a>
                            <?php else: ?>
                            <span class="text-slate-500 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5 text-white text-xs font-mono"><?= htmlspecialchars($rl['endpoint'] ?? '—') ?></td>
                        <td class="px-3 py-2.5 text-right text-red-400 text-xs font-medium"><?= $rl['request_count'] ?></td>
                        <td class="px-3 py-2.5 text-right text-slate-400 text-xs"><?= $rl['limit_value'] ?></td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs font-mono"><?= htmlspecialchars($rl['ip_address'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($tab === 'ips'): ?>
    <!-- Top Offending IPs -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="px-3 py-2.5">IP Address</th>
                        <th class="px-3 py-2.5 text-right">Events</th>
                        <th class="px-3 py-2.5">Last Seen</th>
                        <th class="px-3 py-2.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topIPs)): ?>
                    <tr><td colspan="4" class="py-12 text-center text-slate-500"><i class="fas fa-network-wired text-3xl mb-3 block"></i>No data</td></tr>
                    <?php else: foreach ($topIPs as $ip): ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5 text-white text-xs font-mono"><?= htmlspecialchars($ip['ip_address']) ?></td>
                        <td class="px-3 py-2.5 text-right text-red-400 text-xs font-medium"><?= $ip['count'] ?></td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= date('M j, g:i a', strtotime($ip['last_seen'])) ?></td>
                        <td class="px-3 py-2.5 text-right">
                            <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="ban_ip">
                                <input type="hidden" name="ip_address" value="<?= htmlspecialchars($ip['ip_address']) ?>">
                                <button type="submit" class="px-2 py-1 bg-red-500/20 rounded text-red-400 text-xs hover:bg-red-500/30">Ban</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

