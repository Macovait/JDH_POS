<?php
/**
 * Platform Health & Schema Status
 * Verify all P0 tables, migrations, and platform readiness.
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

$requiredTables = [
    // Core SaaS
    'pos_tenants', 'pos_subscriptions', 'pos_plans', 'pos_invoices',
    // P0 Entitlements
    'platform_features', 'plan_features', 'tenant_entitlements', 'tenant_add_ons',
    // P0 Usage Metering
    'usage_metering_types', 'usage_metering', 'usage_metering_events', 'usage_metering_aggregates',
    // P0 Billing
    'credits', 'prorations', 'deferred_revenue', 'invoice_line_items', 'taxes', 'refunds', 'credit_notes',
    // P0 Subscription Lifecycle
    'subscription_history', 'cancellation_reasons', 'payment_attempts', 'dunning_campaigns',
    // P0 Tenant Management
    'tenant_metadata', 'tenant_tags', 'tenant_notes', 'support_tickets', 'tenant_branding',
    // P0 Email & Monitoring
    'email_templates', 'email_logs', 'webhook_failures', 'api_rate_limit_violations',
    'suspicious_activity_logs', 'system_alerts',
    // Stripe
    'processed_stripe_events',
    // System
    'system_settings', 'admins', 'audit_logs',
];

$tableStatus = [];
$missingTables = [];
$totalRows = 0;

foreach ($requiredTables as $table) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->rowCount() > 0;
        $rowCount = 0;
        if ($exists) {
            $rowCount = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            $totalRows += $rowCount;
        } else {
            $missingTables[] = $table;
        }
        $tableStatus[$table] = ['exists' => $exists, 'rows' => $rowCount];
    } catch (Exception $e) {
        $tableStatus[$table] = ['exists' => false, 'rows' => 0, 'error' => $e->getMessage()];
        $missingTables[] = $table;
    }
}

// Stripe columns check
$stripeCols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM pos_tenants LIKE 'stripe_%'")->fetchAll(PDO::FETCH_COLUMN);
    $stripeCols['pos_tenants'] = $cols;
} catch (Exception $e) {
    $stripeCols['pos_tenants'] = ['error' => $e->getMessage()];
}
try {
    $cols = $pdo->query("SHOW COLUMNS FROM pos_subscriptions LIKE 'stripe_%'")->fetchAll(PDO::FETCH_COLUMN);
    $stripeCols['pos_subscriptions'] = $cols;
} catch (Exception $e) {
    $stripeCols['pos_subscriptions'] = ['error' => $e->getMessage()];
}

// Core metrics
$tenantCount = db_fetch_value("SELECT COUNT(*) FROM pos_tenants");
$activeSubs = db_fetch_value("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'active'");
$trialCount = db_fetch_value("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'trial'");
$totalMRR = db_fetch_value("SELECT COALESCE(SUM(amount), 0) FROM pos_subscriptions WHERE status = 'active'");

// PHP & DB info
$phpVersion = PHP_VERSION;
$dbVersion = $pdo->query("SELECT VERSION()")->fetchColumn();

$current_page = 'monitoring';
$page_title = 'Platform Health';
$breadcrumbs = [
    ['label' => 'Platform', 'url' => 'dashboard.php'],
    ['label' => 'Health']
];

// Calculate health score
$healthScore = 100;
$tableHealth = count($requiredTables) > 0 ? (count($requiredTables) - count($missingTables)) / count($requiredTables) * 100 : 0;
$healthScore = $tableHealth;

// Get system metrics
$memoryUsage = memory_get_usage(true);
$memoryPeak = memory_get_peak_usage(true);
$memoryLimit = ini_get('memory_limit');
$maxExecutionTime = ini_get('max_execution_time');
$uploadMaxSize = ini_get('upload_max_filesize');

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <!-- Header with Health Score -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Platform Health</h1>
            <p class="text-slate-400 text-sm mt-1">Schema status and database readiness</p>
        </div>
        <div class="flex items-center gap-4">
            <!-- Health Score Circle -->
            <div class="flex items-center gap-3 bg-slate-800/50 rounded-xl px-4 py-3 border border-slate-700">
                <div class="relative w-14 h-14">
                    <svg class="w-full h-full transform -rotate-90">
                        <circle cx="28" cy="28" r="24" fill="none" stroke="#1e293b" stroke-width="4"></circle>
                        <circle cx="28" cy="28" r="24" fill="none" stroke="<?= $healthScore >= 90 ? '#10b981' : ($healthScore >= 70 ? '#f59e0b' : '#ef4444') ?>" stroke-width="4" stroke-dasharray="<?= 150.8 * $healthScore / 100 ?> 150.8"></circle>
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-sm font-bold <?= $healthScore >= 90 ? 'text-emerald-400' : ($healthScore >= 70 ? 'text-amber-400' : 'text-red-400') ?>"><?= round($healthScore) ?>%</span>
                </div>
                <div>
                    <p class="text-sm font-medium text-white">Health Score</p>
                    <p class="text-xs text-slate-400"><?= count($requiredTables) - count($missingTables) ?>/<?= count($requiredTables) ?> tables</p>
                </div>
            </div>
            <a href="monitoring.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70 transition">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>

    <!-- Environment & System -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">PHP Version</p>
            <p class="text-white font-semibold text-lg"><?= htmlspecialchars($phpVersion) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">MySQL Version</p>
            <p class="text-white font-semibold text-lg"><?= htmlspecialchars($dbVersion) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Tables</p>
            <p class="text-white font-semibold text-lg"><?= count($requiredTables) - count($missingTables) ?>/<?= count($requiredTables) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Total Rows</p>
            <p class="text-white font-semibold text-lg"><?= number_format($totalRows) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Memory Limit</p>
            <p class="text-white font-semibold text-lg"><?= htmlspecialchars($memoryLimit) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Max Exec Time</p>
            <p class="text-white font-semibold text-lg"><?= $maxExecutionTime ?>s</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Upload Max</p>
            <p class="text-white font-semibold text-lg"><?= htmlspecialchars($uploadMaxSize) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Memory Used</p>
            <p class="text-white font-semibold text-lg"><?= round($memoryUsage / 1024 / 1024, 1) ?>MB</p>
        </div>
    </div>

    <!-- Missing Tables Alert -->
    <?php if (!empty($missingTables)): ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 mb-6 border-red-500/30">
        <h2 class="text-red-400 font-semibold mb-3 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle"></i> Missing Tables (<?= count($missingTables) ?>)
        </h2>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($missingTables as $mt): ?>
            <span class="px-2 py-1 bg-red-500/10 rounded text-red-400 text-xs border border-red-500/20"><?= $mt ?></span>
            <?php endforeach; ?>
        </div>
        <p class="text-slate-400 text-xs mt-3">Run the P0 migration scripts in <code>database/migrations/</code> to create missing tables.</p>
    </div>
    <?php else: ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 mb-6 border-emerald-500/30">
        <h2 class="text-emerald-400 font-semibold mb-2 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> All Required Tables Present
        </h2>
        <p class="text-slate-400 text-xs">All <?= count($requiredTables) ?> required tables are in the database.</p>
    </div>
    <?php endif; ?>

    <!-- Stripe Columns -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 mb-6">
        <h2 class="text-white font-semibold mb-4">Stripe Integration Columns</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-slate-800/50 rounded-lg p-3">
                <p class="text-slate-400 text-xs mb-2">pos_tenants</p>
                <?php if (isset($stripeCols['pos_tenants']['error'])): ?>
                <p class="text-red-400 text-xs"><?= $stripeCols['pos_tenants']['error'] ?></p>
                <?php elseif (empty($stripeCols['pos_tenants'])): ?>
                <p class="text-red-400 text-xs">No stripe columns found</p>
                <?php else: ?>
                <div class="flex flex-wrap gap-1">
                    <?php foreach ($stripeCols['pos_tenants'] as $col): ?>
                    <span class="px-2 py-0.5 bg-emerald-500/10 rounded text-emerald-400 text-[10px]"><?= $col ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="bg-slate-800/50 rounded-lg p-3">
                <p class="text-slate-400 text-xs mb-2">pos_subscriptions</p>
                <?php if (isset($stripeCols['pos_subscriptions']['error'])): ?>
                <p class="text-red-400 text-xs"><?= $stripeCols['pos_subscriptions']['error'] ?></p>
                <?php elseif (empty($stripeCols['pos_subscriptions'])): ?>
                <p class="text-red-400 text-xs">No stripe columns found</p>
                <?php else: ?>
                <div class="flex flex-wrap gap-1">
                    <?php foreach ($stripeCols['pos_subscriptions'] as $col): ?>
                    <span class="px-2 py-0.5 bg-emerald-500/10 rounded text-emerald-400 text-[10px]"><?= $col ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Table Status Grid -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-6">
        <div class="p-4 border-b border-slate-700/60">
            <h2 class="text-white font-semibold text-sm">Table Status</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="px-3 py-2.5">Table</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5 text-right">Rows</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tableStatus as $table => $info): ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5 text-white text-xs font-mono"><?= $table ?></td>
                        <td class="px-3 py-2.5">
                            <?php if ($info['exists']): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-500/20 text-emerald-400">OK</span>
                            <?php else: ?>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-red-500/20 text-red-400">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5 text-right text-slate-400 text-xs"><?= number_format($info['rows']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Tenants</p>
            <p class="text-white font-semibold text-lg"><?= $tenantCount ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Active Subs</p>
            <p class="text-emerald-400 font-semibold text-lg"><?= $activeSubs ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Trials</p>
            <p class="text-blue-400 font-semibold text-lg"><?= $trialCount ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">MRR</p>
            <p class="text-amber-400 font-semibold text-lg">$<?= number_format($totalMRR, 2) ?></p>
        </div>
    </div>

    <!-- Last Check -->
    <div class="flex items-center justify-between text-xs text-slate-500">
        <p>Last checked: <?= date('M j, Y H:i:s T') ?></p>
        <button onclick="location.reload()" class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

