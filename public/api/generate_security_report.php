<?php
/**
 * Generate Security Report - HTML Report View
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

require_login();
if (!is_super_admin() && !check_permission('system.admin')) {
    http_response_code(403);
    die('Unauthorized');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id() ?? 0;

// ── Fetch Data ──
$active_users = 0;
$data_access_count = 0;
$security_events_count = 0;
$violations_count = 0;
$security_events = [];
$top_tables = [];
$permission_denials = [];

try {
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    if (in_array('users', $tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (tenant_id = ? OR tenant_id IS NULL) AND last_login_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND deleted_at IS NULL");
        $stmt->execute([$tenant_id]);
        $active_users = (int) $stmt->fetchColumn();
    }

    if (in_array('data_access_logs', $tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM data_access_logs WHERE (tenant_id = ? OR tenant_id IS NULL) AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([$tenant_id]);
        $data_access_count = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT table_name, operation, COUNT(*) as access_count, COUNT(DISTINCT user_id) as unique_users FROM data_access_logs WHERE (tenant_id = ? OR tenant_id IS NULL) AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY table_name, operation ORDER BY access_count DESC LIMIT 10");
        $stmt->execute([$tenant_id]);
        $top_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (in_array('security_events', $tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM security_events WHERE (tenant_id = ? OR tenant_id IS NULL) AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([$tenant_id]);
        $security_events_count = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT event_type, severity, description, created_at FROM security_events WHERE (tenant_id = ? OR tenant_id IS NULL) ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$tenant_id]);
        $security_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (in_array('permission_checks', $tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM permission_checks WHERE (tenant_id = ? OR tenant_id IS NULL) AND granted = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([$tenant_id]);
        $violations_count = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT permission, COUNT(*) as denial_count FROM permission_checks WHERE (tenant_id = ? OR tenant_id IS NULL) AND granted = 0 GROUP BY permission ORDER BY denial_count DESC LIMIT 10");
        $stmt->execute([$tenant_id]);
        $permission_denials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Security report error: " . $e->getMessage());
}

$severity_map = [
    'low'      => ['bg-blue-500/15 text-blue-400 ring-1 ring-blue-500/30', 'Low'],
    'medium'   => ['bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30', 'Medium'],
    'high'     => ['bg-orange-500/15 text-orange-400 ring-1 ring-orange-500/30', 'High'],
    'critical' => ['bg-red-500/15 text-red-400 ring-1 ring-red-500/30', 'Critical'],
];

$generated_at = date('F d, Y H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Report - <?= htmlspecialchars($generated_at) ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-slate-900 text-white min-h-screen p-6">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-6 border-b border-slate-700/60">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-shield-halved text-amber-400"></i> Security Report
                </h1>
                <p class="text-sm text-slate-400 mt-1">Generated on <?= htmlspecialchars($generated_at) ?></p>
            </div>
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
                <i class="fas fa-print text-xs"></i> Print / Save PDF
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-users text-emerald-400 text-xs"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-emerald-400 truncate"><?= number_format($active_users) ?></div>
                    <div class="text-xs text-slate-500 leading-none mt-0.5">Active Users</div>
                </div>
            </div>
            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-database text-blue-400 text-xs"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-blue-400 truncate"><?= number_format($data_access_count) ?></div>
                    <div class="text-xs text-slate-500 leading-none mt-0.5">Data Access Events</div>
                </div>
            </div>
            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-shield-alt text-amber-400 text-xs"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-amber-400 truncate"><?= number_format($security_events_count) ?></div>
                    <div class="text-xs text-slate-500 leading-none mt-0.5">Security Events</div>
                </div>
            </div>
            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-400 text-xs"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-red-400 truncate"><?= number_format($violations_count) ?></div>
                    <div class="text-xs text-slate-500 leading-none mt-0.5">Violations</div>
                </div>
            </div>
        </div>

        <!-- Security Events -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-4">
            <h2 class="text-base font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-clock text-amber-400 text-sm"></i> Recent Security Events
            </h2>
            <?php if (!empty($security_events)): ?>
            <div class="space-y-2">
                <?php foreach ($security_events as $event):
                    $sev = strtolower($event['severity'] ?? 'low');
                    [$badge_class, $badge_label] = $severity_map[$sev] ?? $severity_map['low'];
                ?>
                <div class="flex items-start gap-3 p-3 bg-slate-900/30 rounded-lg border border-slate-700/30">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-white"><?= htmlspecialchars($event['event_type']) ?></span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $badge_class ?>"><?= $badge_label ?></span>
                        </div>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($event['description'] ?? '') ?></p>
                        <p class="text-xs text-slate-500 mt-1"><i class="far fa-clock mr-1"></i><?= htmlspecialchars($event['created_at']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-sm text-slate-500 py-4 text-center">No security events recorded.</p>
            <?php endif; ?>
        </div>

        <!-- Two Column -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-2 mb-4">
            <!-- Top Accessed Tables -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <h2 class="text-base font-semibold text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-chart-line text-amber-400 text-sm"></i> Top Accessed Tables
                </h2>
                <?php if (!empty($top_tables)): ?>
                <div class="space-y-2">
                    <?php foreach ($top_tables as $table): ?>
                    <div class="flex items-center justify-between p-3 bg-slate-900/30 rounded-lg border border-slate-700/30">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-blue-500/10 rounded-lg flex items-center justify-center">
                                <i class="fas fa-table text-blue-400 text-xs"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-white"><?= htmlspecialchars($table['table_name']) ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($table['operation']) ?> operations</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium text-white"><?= number_format((int)$table['access_count']) ?></div>
                            <div class="text-xs text-slate-500"><?= number_format((int)$table['unique_users']) ?> users</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-slate-500 py-4 text-center">No data access recorded.</p>
                <?php endif; ?>
            </div>

            <!-- Permission Denials -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <h2 class="text-base font-semibold text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-ban text-red-400 text-sm"></i> Permission Denials
                </h2>
                <?php if (!empty($permission_denials)): ?>
                <div class="space-y-2">
                    <?php foreach ($permission_denials as $denial): ?>
                    <div class="flex items-center justify-between p-3 bg-slate-900/30 rounded-lg border border-slate-700/30">
                        <div>
                            <div class="text-sm font-medium text-white"><?= htmlspecialchars($denial['permission']) ?></div>
                            <div class="text-xs text-slate-400"><?= number_format((int)$denial['denial_count']) ?> denials</div>
                        </div>
                        <span class="px-2 py-0.5 bg-red-500/15 text-red-400 text-xs rounded-full ring-1 ring-red-500/30">Denied</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-slate-500 py-4 text-center">No permission denials recorded.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 pt-4 border-t border-slate-700/60 text-center">
            <p class="text-xs text-slate-500">Jakababa POS Security Report &middot; Confidential</p>
        </div>
    </div>
</body>
</html>
