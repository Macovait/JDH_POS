<?php
/**
 * Tenant Detail View
 * Comprehensive single-tenant dashboard with all SaaS metrics.
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

$tenant_id = (int) ($_GET['id'] ?? 0);
if (!$tenant_id) {
    header('Location: companies.php');
    exit;
}

// ---- FETCH TENANT ----
$tenant = db_fetch_one(
    "SELECT t.*,
            JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.email')) as email,
            JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.phone')) as phone,
            JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.address')) as address,
            JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.industry')) as industry,
            JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.business_type')) as business_type,
            JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.timezone')) as timezone,
            JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.currency')) as currency
     FROM pos_tenants t WHERE t.id = ?",
    [$tenant_id]
);

if (!$tenant) {
    header('Location: companies.php');
    exit;
}

// ---- SUBSCRIPTION ----
$subscription = db_fetch_one(
    "SELECT s.*, p.name as plan_name, p.price_monthly, p.price_yearly
     FROM pos_subscriptions s
     LEFT JOIN pos_plans p ON s.plan_id = p.id
     WHERE s.tenant_id = ? ORDER BY s.created_at DESC LIMIT 1",
    [$tenant_id]
);

// ---- METADATA ----
$metadata = db_fetch_one("SELECT * FROM tenant_metadata WHERE tenant_id = ?", [$tenant_id]);
if (!$metadata) {
    $metadata = [
        'health_score' => 1.00,
        'onboarding_progress' => 0,
        'support_priority' => 'normal',
        'churn_risk_score' => null,
    ];
}

// ---- USAGE ----
$usage = db_fetch_all(
    "SELECT m.*, mt.slug, mt.name, mt.unit
     FROM usage_metering m
     JOIN usage_metering_types mt ON m.type_id = mt.id
     WHERE m.tenant_id = ? AND m.period_start <= CURDATE() AND m.period_end >= CURDATE()
     ORDER BY mt.slug",
    [$tenant_id]
);

// ---- INVOICES ----
$invoices = db_fetch_all(
    "SELECT * FROM pos_invoices WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 10",
    [$tenant_id]
);

// ---- CREDIT BALANCE ----
$creditBalance = db_fetch_value(
    "SELECT COALESCE(SUM(amount), 0) FROM credits WHERE tenant_id = ?",
    [$tenant_id]
);

// ---- TAGS ----
$tags = db_fetch_all("SELECT tag FROM tenant_tags WHERE tenant_id = ?", [$tenant_id]);

// ---- NOTES ----
$notes = db_fetch_all(
    "SELECT n.*, a.name as admin_name
     FROM tenant_notes n
     LEFT JOIN admins a ON n.created_by_admin_id = a.id
     WHERE n.tenant_id = ? ORDER BY n.is_pinned DESC, n.created_at DESC LIMIT 20",
    [$tenant_id]
);

// ---- SUPPORT TICKETS ----
$tickets = db_fetch_all(
    "SELECT t.*, a.name as assigned_name
     FROM support_tickets t
     LEFT JOIN admins a ON t.assigned_to_admin_id = a.id
     WHERE t.tenant_id = ? ORDER BY t.created_at DESC LIMIT 10",
    [$tenant_id]
);

// ---- AUDIT LOGS ----
$auditLogs = db_fetch_all(
    "SELECT * FROM admin_audit_log
     WHERE tenant_id = ? OR entity_type = 'tenant' AND entity_id = ?
     ORDER BY created_at DESC LIMIT 20",
    [$tenant_id, $tenant_id]
);
if (empty($auditLogs)) {
    $auditLogs = [];
}

// ---- BRANDING ----
$branding = db_fetch_one("SELECT * FROM tenant_branding WHERE tenant_id = ?", [$tenant_id]);

// ---- ENTITLEMENTS ----
$entitlements = db_fetch_all(
    "SELECT e.*, f.slug as feature_slug, f.name as feature_name
     FROM tenant_entitlements e
     JOIN platform_features f ON e.feature_id = f.id
     WHERE e.tenant_id = ? AND e.is_enabled = 1
       AND (e.effective_until IS NULL OR e.effective_until >= CURDATE())
     ORDER BY f.name",
    [$tenant_id]
);

// ---- ACTIVE TAB ----
$tab = $_GET['tab'] ?? 'overview';
$validTabs = ['overview', 'usage', 'invoices', 'store', 'audit', 'notes', 'support'];
if (!in_array($tab, $validTabs)) $tab = 'overview';

// ---- PAGE META ----
$current_page = 'companies';
$page_title = htmlspecialchars($tenant['name']) . ' — Tenant Detail';
$breadcrumbs = [
    ['label' => 'Tenants', 'url' => 'companies.php'],
    ['label' => $tenant['name']]
];

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-start justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="companies.php" class="w-10 h-10 bg-slate-700/50 rounded-lg flex items-center justify-center text-slate-300 hover:bg-slate-700/70 transition">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-lg font-bold text-white"><?= htmlspecialchars($tenant['name']) ?></h1>
                <p class="text-slate-400 text-sm"><?= htmlspecialchars($tenant['slug']) ?> · <?= htmlspecialchars($tenant['email']) ?></p>
            </div>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium status-<?= $tenant['status'] ?>"><?= ucfirst($tenant['status']) ?></span>
        </div>
        <div class="flex items-center gap-2">
            <a href="support-entry.php?tenant_id=<?= $tenant_id ?>" target="_blank"
               class="px-4 py-2 bg-indigo-500/20 text-indigo-400 rounded-lg text-sm hover:bg-indigo-500/30 transition">
                <i class="fas fa-user-secret mr-1"></i> Impersonate
            </a>
            <form method="POST" action="companies.php" class="inline">
                <input type="hidden" name="action" value="<?= $tenant['status'] === 'suspended' ? 'activate' : 'suspend' ?>">
                <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
                <button type="submit" class="px-4 py-2 bg-yellow-500/20 text-yellow-400 rounded-lg text-sm hover:bg-yellow-500/30 transition"
                        onclick="return confirm('<?= $tenant['status'] === 'suspended' ? 'Activate' : 'Suspend' ?> this tenant?')">
                    <i class="fas fa-<?= $tenant['status'] === 'suspended' ? 'play' : 'pause' ?> mr-1"></i>
                    <?= $tenant['status'] === 'suspended' ? 'Activate' : 'Suspend' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Tags -->
    <?php if (!empty($tags)): ?>
    <div class="flex flex-wrap gap-2 mb-4">
        <?php foreach ($tags as $tg): ?>
        <span class="px-2 py-1 rounded text-xs bg-slate-700/50 text-slate-300 border border-slate-700/60"><?= htmlspecialchars($tg['tag']) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Quick Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Plan</p>
            <p class="text-white font-semibold"><?= htmlspecialchars($subscription['plan_name'] ?? 'Free') ?></p>
            <p class="text-slate-500 text-xs"><?= htmlspecialchars($subscription['billing_cycle'] ?? 'monthly') ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">MRR</p>
            <p class="text-white font-semibold">KSh <?= number_format($subscription['amount'] ?? 0, 2) ?></p>
            <p class="text-slate-500 text-xs"><?= ucfirst($subscription['status'] ?? 'N/A') ?></p>
        </div>
        <?php
        $hs = $metadata['health_score'] ?? 1;
        $hsPct = (int)($hs * 100);
        $hsColor = $hs >= 0.7 ? 'bg-emerald-500' : ($hs >= 0.4 ? 'bg-yellow-500' : 'bg-red-500');
        ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Health Score</p>
            <p class="text-white font-semibold"><?= $hsPct ?>/100</p>
            <div class="w-full bg-slate-700 rounded-full h-1.5 mt-2">
                <div class="h-1.5 rounded-full <?= $hsColor ?>" style="width:<?= $hsPct ?>"></div>
            </div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Credit Balance</p>
            <p class="text-white font-semibold">KSh <?= number_format($creditBalance, 2) ?></p>
            <p class="text-slate-500 text-xs">Available</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl mb-6 overflow-hidden">
        <div class="flex border-b border-slate-700/60 overflow-x-auto">
            <?php foreach ([
                'overview' => 'Overview',
                'usage' => 'Usage',
                'invoices' => 'Invoices',
                'store' => 'Store',
                'audit' => 'Audit',
                'notes' => 'Notes',
                'support' => 'Support'
            ] as $t => $label): ?>
            <a href="?id=<?= $tenant_id ?>&tab=<?= $t ?>"
               class="px-4 py-2.5 text-sm font-medium whitespace-nowrap <?= $tab === $t ? 'text-amber-400 border-b-2 border-amber-400 bg-slate-800/50' : 'text-slate-400 hover:text-white hover:bg-slate-700/30' ?> transition">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="p-6">
            <?php if ($tab === 'overview'): ?>
                <!-- Overview Tab -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Profile -->
                    <div class="lg:col-span-2 space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-white mb-4">Business Profile</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-slate-800/50 rounded-lg p-3">
                                    <p class="text-slate-500 text-xs">Industry</p>
                                    <p class="text-white"><?= htmlspecialchars($tenant['industry'] ?? 'N/A') ?></p>
                                </div>
                                <div class="bg-slate-800/50 rounded-lg p-3">
                                    <p class="text-slate-500 text-xs">Business Type</p>
                                    <p class="text-white"><?= htmlspecialchars($tenant['business_type'] ?? 'N/A') ?></p>
                                </div>
                                <div class="bg-slate-800/50 rounded-lg p-3">
                                    <p class="text-slate-500 text-xs">Phone</p>
                                    <p class="text-white"><?= htmlspecialchars($tenant['phone'] ?? 'N/A') ?></p>
                                </div>
                                <div class="bg-slate-800/50 rounded-lg p-3">
                                    <p class="text-slate-500 text-xs">Timezone</p>
                                    <p class="text-white"><?= htmlspecialchars($tenant['timezone'] ?? 'UTC') ?></p>
                                </div>
                                <div class="bg-slate-800/50 rounded-lg p-3 col-span-2">
                                    <p class="text-slate-500 text-xs">Address</p>
                                    <p class="text-white"><?= htmlspecialchars($tenant['address'] ?? 'N/A') ?></p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-white mb-4">Subscription</h3>
                            <div class="bg-slate-800/50 rounded-lg p-4">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <p class="text-slate-500 text-xs">Plan</p>
                                        <p class="text-white font-medium"><?= htmlspecialchars($subscription['plan_name'] ?? 'N/A') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-xs">Status</p>
                                        <p class="text-white font-medium"><?= ucfirst($subscription['status'] ?? 'N/A') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-xs">Amount</p>
                                        <p class="text-white font-medium">KSh <?= number_format($subscription['amount'] ?? 0, 2) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-xs">Period End</p>
                                        <p class="text-white font-medium"><?= $subscription['current_period_end'] ? date('M j, Y', strtotime($subscription['current_period_end'])) : 'N/A' ?></p>
                                    </div>
                                </div>
                                <?php if ($subscription['trial_ends_at']): ?>
                                <div class="mt-3 pt-3 border-t border-slate-700/60">
                                    <p class="text-slate-500 text-xs">Trial ends</p>
                                    <p class="text-amber-400 text-sm"><?= date('M j, Y', strtotime($subscription['trial_ends_at'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-white mb-4">Feature Entitlements</h3>
                            <?php if (empty($entitlements)): ?>
                                <p class="text-slate-500 text-sm">No active entitlements.</p>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <?php foreach ($entitlements as $e): ?>
                                    <div class="bg-slate-800/50 rounded-lg p-3 flex items-center justify-between">
                                        <div>
                                            <p class="text-white text-sm"><?= htmlspecialchars($e['feature_name']) ?></p>
                                            <p class="text-slate-500 text-xs">Source: <?= ucfirst($e['source']) ?></p>
                                        </div>
                                        <div class="text-right">
                                            <?php if ($e['is_unlimited']): ?>
                                                <span class="text-emerald-400 text-xs font-semibold">Unlimited</span>
                                            <?php else: ?>
                                                <span class="text-white text-sm font-semibold"><?= number_format($e['limit_value'] ?? 0) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sidebar Info -->
                    <div class="space-y-6">
                        <?php $onboardingPct = (int)($metadata['onboarding_progress'] ?? 0); ?>
                        <div class="bg-slate-800/50 rounded-lg p-4">
                            <h4 class="text-white font-semibold mb-3">Onboarding</h4>
                            <div class="w-full bg-slate-700 rounded-full h-2 mb-2">
                                <div class="bg-amber-400 h-2 rounded-full" style="width:<?= $onboardingPct ?>%"></div>
                            </div>
                            <p class="text-slate-400 text-xs"><?= (int)($metadata['onboarding_progress'] ?? 0) ?>% complete</p>
                            <?php if ($metadata['onboarding_completed_at']): ?>
                                <p class="text-emerald-400 text-xs mt-1">Completed <?= date('M j, Y', strtotime($metadata['onboarding_completed_at'])) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="bg-slate-800/50 rounded-lg p-4">
                            <h4 class="text-white font-semibold mb-3">Risk & Value</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-slate-500 text-xs">Churn Risk</span>
                                    <span class="text-white text-xs"><?= $metadata['churn_risk_score'] !== null ? ((int)($metadata['churn_risk_score'] * 100)) . '%' : 'N/A' ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 text-xs">Expected LTV</span>
                                    <span class="text-white text-xs">KSh <?= number_format($metadata['expected_ltv'] ?? 0, 2) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 text-xs">CAC</span>
                                    <span class="text-white text-xs">KSh <?= number_format($metadata['cac'] ?? 0, 2) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-slate-800/50 rounded-lg p-4">
                            <h4 class="text-white font-semibold mb-3">Activity</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-slate-500 text-xs">Last Login</span>
                                    <span class="text-white text-xs"><?= $metadata['last_login_at'] ? date('M j, g:i a', strtotime($metadata['last_login_at'])) : 'Never' ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 text-xs">Last Activity</span>
                                    <span class="text-white text-xs"><?= $metadata['last_activity_at'] ? date('M j, g:i a', strtotime($metadata['last_activity_at'])) : 'Never' ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 text-xs">Created</span>
                                    <span class="text-white text-xs"><?= date('M j, Y', strtotime($tenant['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if ($branding): ?>
                        <div class="bg-slate-800/50 rounded-lg p-4">
                            <h4 class="text-white font-semibold mb-3">Branding</h4>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full" style="background:<?= htmlspecialchars($branding['primary_color']) ?>"></div>
                                <div class="w-8 h-8 rounded-full" style="background:<?= htmlspecialchars($branding['secondary_color']) ?>"></div>
                                <span class="text-slate-400 text-xs"><?= ucfirst($branding['theme_mode']) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($tab === 'usage'): ?>
                <!-- Usage Tab -->
                <h3 class="text-lg font-semibold text-white mb-4">Current Period Usage</h3>
                <?php if (empty($usage)): ?>
                    <p class="text-slate-500">No usage data recorded for the current period.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($usage as $u): ?>
                        <div class="bg-slate-800/50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <p class="text-white font-medium"><?= htmlspecialchars($u['name']) ?></p>
                                    <p class="text-slate-500 text-xs"><?= htmlspecialchars($u['unit']) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-white font-semibold"><?= number_format($u['usage_value'], 2) ?> / <?= $u['limit_value'] ? number_format($u['limit_value'], 2) : 'Unlimited' ?></p>
                                    <?php if ($u['limit_value']): ?>
                                        <?php $pct = min(100, ($u['usage_value'] / max(1, $u['limit_value'])) * 100); ?>
                                        <p class="text-xs <?= $pct >= 95 ? 'text-red-400' : ($pct >= 80 ? 'text-yellow-400' : 'text-emerald-400') ?>"><?= (int)$pct ?>% used</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($u['limit_value']):
                                $barColor = $pct >= 95 ? 'bg-red-500' : ($pct >= 80 ? 'bg-yellow-500' : 'bg-emerald-500');
                                $barPct = (int)$pct;
                            ?>
                            <div class="w-full bg-slate-700 rounded-full h-2">
                                <div class="h-2 rounded-full <?= $barColor ?>" style="width:<?= $barPct ?>%"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'invoices'): ?>
                <!-- Invoices Tab -->
                <h3 class="text-lg font-semibold text-white mb-4">Invoices</h3>
                <?php if (empty($invoices)): ?>
                    <p class="text-slate-500">No invoices found.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                                    <th class="pb-2 pr-4">Number</th>
                                    <th class="pb-2 pr-4">Amount</th>
                                    <th class="pb-2 pr-4">Status</th>
                                    <th class="pb-2 pr-4">Due Date</th>
                                    <th class="pb-2 pr-4">Paid</th>
                                    <th class="pb-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv): ?>
                                <tr class="border-b border-slate-700/30">
                                    <td class="py-3 pr-4 text-white"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                    <td class="py-3 pr-4 text-white">KSh <?= number_format($inv['amount'], 2) ?></td>
                                    <td class="py-3 pr-4">
                                        <span class="px-2 py-0.5 rounded text-xs <?= $inv['status'] === 'paid' ? 'bg-emerald-500/20 text-emerald-400' : ($inv['status'] === 'pending' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400') ?>">
                                            <?= ucfirst($inv['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-400"><?= date('M j, Y', strtotime($inv['due_date'])) ?></td>
                                    <td class="py-3 pr-4 text-slate-400"><?= $inv['paid_at'] ? date('M j, Y', strtotime($inv['paid_at'])) : '—' ?></td>
                                    <td class="py-3 text-right">
                                        <?php if ($inv['pdf_path']): ?>
                                        <a href="<?= base_url($inv['pdf_path']) ?>" target="_blank" class="text-amber-400 hover:text-amber-300"><i class="fas fa-file-pdf"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'store'): ?>
                <!-- Store Tab -->
                <h3 class="text-lg font-semibold text-white mb-4">Online Store</h3>
                <?php
                $storefront = db_fetch_one("SELECT * FROM storefront_settings WHERE tenant_id = ?", [$tenant_id]);
                ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-800/50 rounded-lg p-4">
                        <p class="text-slate-500 text-xs">Store Status</p>
                        <p class="text-white font-medium"><?= $storefront ? ($storefront['is_active'] ? 'Active' : 'Inactive') : 'Not Configured' ?></p>
                    </div>
                    <div class="bg-slate-800/50 rounded-lg p-4">
                        <p class="text-slate-500 text-xs">Store Name</p>
                        <p class="text-white font-medium"><?= htmlspecialchars($storefront['store_name'] ?? 'N/A') ?></p>
                    </div>
                    <div class="bg-slate-800/50 rounded-lg p-4">
                        <p class="text-slate-500 text-xs">Custom Domain</p>
                        <p class="text-white font-medium"><?= htmlspecialchars($storefront['custom_domain'] ?? 'N/A') ?></p>
                    </div>
                    <div class="bg-slate-800/50 rounded-lg p-4">
                        <p class="text-slate-500 text-xs">Theme</p>
                        <p class="text-white font-medium"><?= htmlspecialchars($storefront['theme'] ?? 'Default') ?></p>
                    </div>
                </div>

            <?php elseif ($tab === 'audit'): ?>
                <!-- Audit Tab -->
                <h3 class="text-lg font-semibold text-white mb-4">Audit Logs</h3>
                <?php if (empty($auditLogs)): ?>
                    <p class="text-slate-500">No audit logs found.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                                    <th class="pb-2 pr-4">Time</th>
                                    <th class="pb-2 pr-4">Action</th>
                                    <th class="pb-2 pr-4">Entity</th>
                                    <th class="pb-2">Admin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditLogs as $log): ?>
                                <tr class="border-b border-slate-700/30">
                                    <td class="py-3 pr-4 text-slate-400 whitespace-nowrap"><?= date('M j, Y g:i a', strtotime($log['created_at'])) ?></td>
                                    <td class="py-3 pr-4 text-white"><?= ucfirst($log['action']) ?></td>
                                    <td class="py-3 pr-4 text-white"><?= htmlspecialchars($log['entity_type']) ?> #<?= $log['entity_id'] ?></td>
                                    <td class="py-3 text-slate-400"><?= htmlspecialchars($log['admin_name'] ?? 'System') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'notes'): ?>
                <!-- Notes Tab -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white">Admin Notes</h3>
                </div>
                <?php if (empty($notes)): ?>
                    <p class="text-slate-500">No notes yet.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($notes as $note): ?>
                        <div class="bg-slate-800/50 rounded-lg p-4 <?= $note['is_pinned'] ? 'border-l-2 border-amber-400' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded text-xs bg-slate-700/50 text-slate-300"><?= ucfirst($note['type']) ?></span>
                                    <?php if ($note['is_pinned']): ?><i class="fas fa-thumbtack text-amber-400 text-xs"></i><?php endif; ?>
                                    <?php if ($note['is_internal']): ?><span class="text-slate-600 text-xs">Internal</span><?php endif; ?>
                                </div>
                                <span class="text-slate-500 text-xs"><?= date('M j, Y g:i a', strtotime($note['created_at'])) ?> by <?= htmlspecialchars($note['admin_name'] ?? 'Admin') ?></span>
                            </div>
                            <p class="text-slate-300 text-sm"><?= nl2br(htmlspecialchars($note['note'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'support'): ?>
                <!-- Support Tab -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white">Support Tickets</h3>
                </div>
                <?php if (empty($tickets)): ?>
                    <p class="text-slate-500">No support tickets.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($tickets as $ticket): ?>
                        <div class="bg-slate-800/50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-white font-medium"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
                                    <span class="px-2 py-0.5 rounded text-xs <?= $ticket['status'] === 'open' ? 'bg-red-500/20 text-red-400' : ($ticket['status'] === 'resolved' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-yellow-500/20 text-yellow-400') ?>">
                                        <?= ucfirst($ticket['status']) ?>
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-xs bg-slate-700/50 text-slate-300"><?= ucfirst($ticket['priority']) ?></span>
                                </div>
                                <span class="text-slate-500 text-xs"><?= date('M j, Y', strtotime($ticket['created_at'])) ?></span>
                            </div>
                            <p class="text-white text-sm mb-1"><?= htmlspecialchars($ticket['subject']) ?></p>
                            <p class="text-slate-400 text-xs"><?= htmlspecialchars($ticket['assigned_name'] ?? 'Unassigned') ?> · <?= ucfirst($ticket['category']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

