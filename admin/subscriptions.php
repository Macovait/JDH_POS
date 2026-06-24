<?php
/**
 * Subscriptions Management - SaaS Admin
 * 
 * Manage company subscriptions and billing.
 * Super Admin only.
 */

require_once __DIR__ . '/bootstrap.php';

admin_require_super_admin();

$pdo = admin_require_db(['admins', 'pos_tenants', 'pos_plans', 'pos_subscriptions']);

    // Handle form submissions
    $message = '';
    $message_type = '';

    // Generate CSRF token
    $csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
            $message = 'Invalid security token. Please refresh and try again.';
            $message_type = 'error';
        } else {
        $action = $_POST['action'] ?? '';

        try {
            $pdo = admin_require_db(['admins', 'pos_tenants', 'pos_plans', 'pos_subscriptions']);

            switch ($action) {
                case 'create':
                    $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
                    $plan_id = (int) ($_POST['plan_id'] ?? 0);
                    $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
                    $status = $_POST['status'] ?? 'trialing';
                    $trial_days = (int) ($_POST['trial_days'] ?? 14);
                    $payment_method = $_POST['payment_method'] ?? '';
                    $payment_reference = trim($_POST['payment_reference'] ?? '');
                    $notes = trim($_POST['notes'] ?? '');

                    if (!$tenant_id || !$plan_id) {
                        throw new Exception('Tenant and plan are required.');
                    }

                    // Get plan details
                    $plan = db_fetch_one("SELECT * FROM pos_plans WHERE id = ?", [$plan_id]);
                    if (!$plan) {
                        throw new Exception('Invalid plan selected.');
                    }

                    // Get tenant details
                    $tenant = db_fetch_one("SELECT * FROM pos_tenants WHERE id = ?", [$tenant_id]);
                    if (!$tenant) {
                        throw new Exception('Tenant not found.');
                    }

                    // Calculate amount
                    $amount = $billing_cycle === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly'];

                    // Calculate dates
                    $now = date('Y-m-d H:i:s');
                    $trial_ends_at = null;
                    $current_period_end = null;

                    if ($status === 'trialing' && $trial_days > 0) {
                        $trial_ends_at = date('Y-m-d H:i:s', strtotime("+{$trial_days} days"));
                        $current_period_end = $trial_ends_at;
                    } else {
                        $current_period_end = $billing_cycle === 'yearly'
                            ? date('Y-m-d H:i:s', strtotime('+1 year'))
                            : date('Y-m-d H:i:s', strtotime('+1 month'));
                    }

                    // Create subscription
                    $subscription_id = db_insert('pos_subscriptions', [
                        'tenant_id' => $tenant_id,
                        'plan_id' => $plan_id,
                        'status' => $status,
                        'billing_cycle' => $billing_cycle,
                        'amount' => $amount,
                        'currency' => $tenant['currency'] ?? 'KES',
                        'trial_ends_at' => $trial_ends_at,
                        'current_period_start' => $now,
                        'current_period_end' => $current_period_end,
                        'payment_method' => $payment_method,
                        'payment_reference' => $payment_reference,
                        'notes' => $notes,
                    ]);

                    // Update tenant limits
                    db_update('pos_tenants', [
                        'settings' => json_encode(array_merge(
                            json_decode($tenant['settings'] ?? '{}', true) ?: [],
                            ['max_users' => $plan['max_users']]
                        )),
                        'plan_id' => $plan_id,
                        'status' => $status === 'trialing' ? 'trial' : 'active',
                ], 'id = ?', [$tenant_id]);

                $message = 'Subscription created successfully!';
                $message_type = 'success';
                break;

            case 'update':
                $subscription_id = (int) ($_POST['subscription_id'] ?? 0);
                $plan_id = (int) ($_POST['plan_id'] ?? 0);
                $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
                $status = $_POST['status'] ?? 'active';
                $payment_method = $_POST['payment_method'] ?? '';
                $payment_reference = trim($_POST['payment_reference'] ?? '');
                $notes = trim($_POST['notes'] ?? '');

                if (!$subscription_id || !$plan_id) {
                    throw new Exception('Subscription and plan are required.');
                }

                // Get current subscription
                $subscription = db_fetch_one("SELECT * FROM pos_subscriptions WHERE id = ?", [$subscription_id]);
                if (!$subscription) {
                    throw new Exception('Subscription not found.');
                }

                // Get plan details
                $plan = db_fetch_one("SELECT * FROM pos_plans WHERE id = ?", [$plan_id]);
                if (!$plan) {
                    throw new Exception('Invalid plan selected.');
                }

                // Calculate amount
                $amount = $billing_cycle === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly'];

                // Calculate new period end
                $current_period_end = $billing_cycle === 'yearly'
                    ? date('Y-m-d H:i:s', strtotime('+1 year'))
                    : date('Y-m-d H:i:s', strtotime('+1 month'));

                // Update subscription
                db_update('pos_subscriptions', [
                    'plan_id' => $plan_id,
                    'billing_cycle' => $billing_cycle,
                    'amount' => $amount,
                    'status' => $status,
                    'current_period_end' => $current_period_end,
                    'payment_method' => $payment_method,
                    'payment_reference' => $payment_reference,
                    'metadata' => json_encode(['notes' => $notes]),
                ], 'id = ?', [$subscription_id]);

                // Update tenant status in settings
                $tenantCurrent = db_fetch_one("SELECT settings FROM pos_tenants WHERE id = ?", [$subscription['tenant_id']]);
                $tenantSettings = json_decode($tenantCurrent['settings'] ?? '{}', true) ?: [];
                $updatedTenantSettings = array_merge($tenantSettings, [
                    'max_users' => $plan['max_users'],
                    'max_branches' => $plan['max_branches'],
                    'max_products' => $plan['max_products'],
                    'storage_limit_mb' => $plan['max_storage_mb'],
                ]);
                db_update('pos_tenants', [
                    'settings' => json_encode($updatedTenantSettings),
                    'status' => $status === 'trialing' ? 'trial' : 'active',
                ], 'id = ?', [$subscription['tenant_id']]);

                $message = 'Subscription updated successfully!';
                $message_type = 'success';
                break;

            case 'cancel':
                $subscription_id = (int) ($_POST['subscription_id'] ?? 0);
                $cancel_reason = trim($_POST['cancel_reason'] ?? '');

                db_update('pos_subscriptions', [
                    'status' => 'cancelled',
                    'cancelled_at' => date('Y-m-d H:i:s'),
                    'cancel_reason' => $cancel_reason,
                ], 'id = ?', [$subscription_id]);

                // Update tenant status
                $subscription = db_fetch_one("SELECT tenant_id FROM pos_subscriptions WHERE id = ?", [$subscription_id]);
                if ($subscription) {
                    db_update('pos_tenants', [
                        'status' => 'cancelled',
                    ], 'id = ?', [$subscription['tenant_id']]);
                }

                $message = 'Subscription cancelled successfully!';
                $message_type = 'success';
                break;

            case 'renew':
                $subscription_id = (int) ($_POST['subscription_id'] ?? 0);

                $subscription = db_fetch_one("SELECT * FROM pos_subscriptions WHERE id = ?", [$subscription_id]);
                if (!$subscription) {
                    throw new Exception('Subscription not found.');
                }

                $new_period_end = $subscription['billing_cycle'] === 'yearly'
                    ? date('Y-m-d H:i:s', strtotime('+1 year'))
                    : date('Y-m-d H:i:s', strtotime('+1 month'));

                db_update('pos_subscriptions', [
                    'status' => 'active',
                    'current_period_start' => date('Y-m-d H:i:s'),
                    'current_period_end' => $new_period_end,
                    'cancelled_at' => null,
                    'cancel_reason' => null,
                ], 'id = ?', [$subscription_id]);

                // Update tenant status
                db_update('pos_tenants', [
                    'status' => 'active',
                ], 'id = ?', [$subscription['tenant_id']]);

                $message = 'Subscription renewed successfully!';
                $message_type = 'success';
                break;

            case 'extend_trial':
                $subscription_id = (int) ($_POST['subscription_id'] ?? 0);
                $extra_days = (int) ($_POST['extra_days'] ?? 7);
                if ($extra_days < 1 || $extra_days > 365) {
                    throw new Exception('Extension must be between 1 and 365 days.');
                }

                $subscription = db_fetch_one("SELECT * FROM pos_subscriptions WHERE id = ?", [$subscription_id]);
                if (!$subscription) {
                    throw new Exception('Subscription not found.');
                }

                $currentTrialEnd = $subscription['trial_ends_at'] ?? $subscription['current_period_end'] ?? date('Y-m-d H:i:s');
                $newTrialEnd = date('Y-m-d H:i:s', strtotime($currentTrialEnd . " +{$extra_days} days"));
                $newPeriodEnd = date('Y-m-d H:i:s', strtotime($newTrialEnd));

                db_update('pos_subscriptions', [
                    'status' => 'trialing',
                    'trial_ends_at' => $newTrialEnd,
                    'current_period_end' => $newPeriodEnd,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$subscription_id]);

                // Reactivate tenant if suspended
                db_update('pos_tenants', [
                    'status' => 'active',
                    'is_suspended' => 0,
                ], 'id = ?', [$subscription['tenant_id']]);

                $message = "Trial extended by {$extra_days} days. New end: " . date('M d, Y', strtotime($newTrialEnd));
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$company_filter = $_GET['tenant_id'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where = [];
$params = [];

if ($status_filter) {
    $where[] = 'cs.status = ?';
    $params[] = $status_filter;
}

if ($company_filter) {
    $where[] = 'cs.tenant_id = ?';
    $params[] = $company_filter;
}

if ($search) {
    $where[] = '(c.name LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(c.settings, "$.email")) LIKE ? OR sp.name LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$total = db_fetch_value(
    "SELECT COUNT(*) FROM pos_subscriptions cs
     LEFT JOIN pos_tenants c ON cs.tenant_id = c.id
     LEFT JOIN pos_plans sp ON cs.plan_id = sp.id
     {$where_clause}",
    $params
);

// Get subscriptions
$subscriptions = db_fetch_all(
    "SELECT cs.*, c.name as company_name,
            JSON_UNQUOTE(JSON_EXTRACT(c.settings, '$.email')) as company_email,
            c.slug as company_slug,
            sp.name as plan_name, sp.price_monthly, sp.price_yearly
     FROM pos_subscriptions cs
     LEFT JOIN pos_tenants c ON cs.tenant_id = c.id
     LEFT JOIN pos_plans sp ON cs.plan_id = sp.id
     {$where_clause}
     ORDER BY cs.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

// Get companies and plans for dropdowns
$companies = db_fetch_all("SELECT id, name, slug FROM pos_tenants ORDER BY name");
$plans = db_fetch_all("SELECT * FROM pos_plans WHERE is_active = 1 ORDER BY sort_order");

// Pagination
$total_pages = ceil($total / $per_page);

// Get statistics
$stats = [
    'total' => db_fetch_value("SELECT COUNT(*) FROM pos_subscriptions"),
    'active' => db_fetch_value("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'active'"),
    'trialing' => db_fetch_value("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'trialing'"),
    'cancelled' => db_fetch_value("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'cancelled'"),
    'mrr' => db_fetch_value("SELECT COALESCE(SUM(amount), 0) FROM pos_subscriptions WHERE status = 'active' AND billing_cycle = 'monthly'"),
    'arr' => db_fetch_value("SELECT COALESCE(SUM(amount), 0) FROM pos_subscriptions WHERE status = 'active' AND billing_cycle = 'yearly'"),
];

$current_page = 'subscriptions';
$page_title = 'Subscriptions Management';

// Start output buffering for layout
ob_start();
?>
<div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-white">Subscriptions Management</h1>
                <p class="text-slate-300 mt-1">Manage company subscriptions and billing</p>
            </div>
            <button onclick="openModal('createModal')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-6 py-3 rounded-xl text-slate-900 font-semibold flex items-center gap-2">
                <i class="fas fa-plus"></i>
                Add Subscription
            </button>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div
                class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-green-500/20 border border-green-500/30 text-green-400' : 'bg-red-500/20 border-red-500/30 text-red-400' ?>">
                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <p class="text-slate-400 text-sm">Total</p>
                <p class="text-lg font-bold text-white"><?= $stats['total'] ?></p>
            </div>
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <p class="text-slate-400 text-sm">Active</p>
                <p class="text-2xl font-bold text-green-400"><?= $stats['active'] ?></p>
            </div>
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <p class="text-slate-400 text-sm">Trialing</p>
                <p class="text-2xl font-bold text-blue-400"><?= $stats['trialing'] ?></p>
            </div>
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <p class="text-slate-400 text-sm">Cancelled</p>
                <p class="text-2xl font-bold text-red-400"><?= $stats['cancelled'] ?></p>
            </div>
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <p class="text-slate-400 text-sm">MRR</p>
                <p class="text-2xl font-bold text-accent">KSh <?= number_format($stats['mrr'], 2) ?></p>
            </div>
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <p class="text-slate-400 text-sm">ARR</p>
                <p class="text-2xl font-bold text-accent">KSh <?= number_format($stats['arr'], 2) ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 mb-6">
            <form method="GET" class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search companies or plans..."
                        class="w-full px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
                <div class="min-w-[150px]">
                    <select name="status"
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="trialing" <?= $status_filter === 'trialing' ? 'selected' : '' ?>>Trialing</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled
                        </option>
                        <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                        <option value="past_due" <?= $status_filter === 'past_due' ? 'selected' : '' ?>>Past Due</option>
                    </select>
                </div>
                <div class="min-w-[200px]">
                    <select name="tenant_id"
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= $company['id'] ?>" <?= $company_filter == $company['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($company['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit"
                    class="px-6 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
                <a href="subscriptions.php"
                    class="px-6 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
            </form>
        </div>

        <form method="POST" action="export_csv.php" class="inline" target="_blank">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="table" value="pos_subscriptions">
            <input type="hidden" name="columns" value='[{"field":"id","label":"ID"},{"field":"tenant_id","label":"Tenant ID"},{"field":"plan_id","label":"Plan ID"},{"field":"status","label":"Status","format":"status"},{"field":"amount","label":"Amount","format":"currency"},{"field":"billing_cycle","label":"Billing"},{"field":"created_at","label":"Created","format":"date"}]'>
            <input type="hidden" name="filename" value="subscriptions">
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70 mb-4"><i class="fas fa-download mr-1"></i> Export CSV</button>
        </form>

        <!-- Subscriptions Table -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700/60">
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">Company</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">Plan</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">Status</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">Billing</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">Period</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-slate-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subscriptions)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-0">
                                    <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                                        <div class="w-20 h-20 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mb-5">
                                            <i class="fas fa-credit-card text-3xl text-emerald-400"></i>
                                        </div>
                                        <h3 class="text-lg font-semibold text-white mb-2">
                                            <?= $search ? 'No matching subscriptions' : 'No active subscriptions' ?>
                                        </h3>
                                        <p class="text-sm text-slate-500 max-w-sm mb-5">
                                            <?= $search 
                                                ? 'Try adjusting your search to find the subscription you are looking for.'
                                                : 'Subscriptions will appear here when tenants sign up for paid plans. Free plan users are not listed here.'
                                            ?>
                                        </p>
                                        <?php if (!$search): ?>
                                            <a href="companies.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/20 transition-colors">
                                                <i class="fas fa-building text-xs"></i>
                                                View Tenants
                                            </a>
                                        <?php else: ?>
                                            <a href="subscriptions.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                                                <i class="fas fa-times text-xs"></i>
                                                Clear Filters
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subscriptions as $sub): ?>
                                <?php
                                $metadata = json_decode($sub['metadata'] ?? '{}', true);
                                ?>
                                <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="text-white font-semibold">
                                                <?= htmlspecialchars($sub['company_name'] ?? 'Unknown') ?></p>
                                            <p class="text-slate-400 text-sm">
                                                <?= htmlspecialchars($sub['company_email'] ?? '') ?></p>
                                            <p class="text-slate-500 text-xs"><?= htmlspecialchars($sub['company_slug'] ?? '') ?>
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-white"><?= htmlspecialchars($sub['plan_name'] ?? 'Unknown') ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium status-<?= $sub['status'] ?>">
                                            <?= ucfirst($sub['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="text-white">KSh <?= number_format($sub['amount'], 2) ?></p>
                                            <p class="text-slate-400 text-sm"><?= ucfirst($sub['billing_cycle'] ?? 'monthly') ?>
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <p class="text-slate-300">
                                                <?= date('M d, Y', strtotime($sub['current_period_start'])) ?></p>
                                            <p class="text-slate-400">to
                                                <?= date('M d, Y', strtotime($sub['current_period_end'])) ?></p>
                                            <?php if ($sub['trial_ends_at']): ?>
                                                <p class="text-blue-400 text-xs mt-1">
                                                    Trial ends: <?= date('M d, Y', strtotime($sub['trial_ends_at'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick="openModal('editModal<?= $sub['id'] ?>')"
                                                class="w-8 h-8 bg-blue-500/20 rounded-lg flex items-center justify-center text-blue-400 hover:bg-blue-500/30 transition-colors"
                                                title="Edit">
                                                <i class="fas fa-edit text-sm"></i>
                                            </button>
                                            <?php if ($sub['status'] === 'active' || $sub['status'] === 'trialing'): ?>
                                                <button onclick="openModal('cancelModal<?= $sub['id'] ?>')"
                                                    class="w-8 h-8 bg-red-500/20 rounded-lg flex items-center justify-center text-red-400 hover:bg-red-500/30 transition-colors"
                                                    title="Cancel">
                                                    <i class="fas fa-times text-sm"></i>
                                                </button>
                                                <button onclick="openModal('extendModal<?= $sub['id'] ?>')"
                                                    class="w-8 h-8 bg-amber-500/20 rounded-lg flex items-center justify-center text-amber-400 hover:bg-amber-500/30 transition-colors"
                                                    title="Extend Trial">
                                                    <i class="fas fa-calendar-plus text-sm"></i>
                                                </button>
                                            <?php elseif ($sub['status'] === 'cancelled' || $sub['status'] === 'expired' || $sub['status'] === 'past_due'): ?>
                                                <button onclick="openModal('extendModal<?= $sub['id'] ?>')"
                                                    class="w-8 h-8 bg-amber-500/20 rounded-lg flex items-center justify-center text-amber-400 hover:bg-amber-500/30 transition-colors"
                                                    title="Extend Trial">
                                                    <i class="fas fa-calendar-plus text-sm"></i>
                                                </button>
                                                <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                    <input type="hidden" name="action" value="renew">
                                                    <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
                                                    <button type="submit"
                                                        class="w-8 h-8 bg-green-500/20 rounded-lg flex items-center justify-center text-green-400 hover:bg-green-500/30 transition-colors"
                                                        title="Renew">
                                                        <i class="fas fa-redo text-sm"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div id="editModal<?= $sub['id'] ?>"
                                    class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
                                    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-8 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
                                        <div class="flex items-center justify-between mb-6">
                                            <h2 class="text-lg font-bold text-white">Edit Subscription</h2>
                                            <button onclick="closeModal('editModal<?= $sub['id'] ?>')"
                                                class="text-slate-400 hover:text-white">
                                                <i class="fas fa-times text-xl"></i>
                                            </button>
                                        </div>
                                        <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Plan</label>
                                                    <select name="plan_id"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                        <?php foreach ($plans as $plan): ?>
                                                            <option value="<?= $plan['id'] ?>" <?= $sub['plan_id'] == $plan['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($plan['name']) ?> - KSh
                                                                <?= number_format($plan['price_monthly'], 2) ?>/mo
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Status</label>
                                                    <select name="status"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                        <option value="active" <?= $sub['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="trialing" <?= $sub['status'] === 'trialing' ? 'selected' : '' ?>>Trialing</option>
                                                        <option value="cancelled" <?= $sub['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                        <option value="expired" <?= $sub['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                                                        <option value="past_due" <?= $sub['status'] === 'past_due' ? 'selected' : '' ?>>Past Due</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Billing Cycle</label>
                                                    <select name="billing_cycle"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                        <option value="monthly" <?= $sub['billing_cycle'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                                        <option value="yearly" <?= $sub['billing_cycle'] === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Payment Method</label>
                                                    <select name="payment_method"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                        <option value="">Select Method</option>
                                                        <option value="mpesa" <?= $sub['payment_method'] === 'mpesa' ? 'selected' : '' ?>>M-Pesa</option>
                                                        <option value="card" <?= $sub['payment_method'] === 'card' ? 'selected' : '' ?>>Credit/Debit Card</option>
                                                        <option value="bank" <?= $sub['payment_method'] === 'bank' ? 'selected' : '' ?>>Bank Transfer</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Payment Reference</label>
                                                    <input type="text" name="payment_reference"
                                                        value="<?= htmlspecialchars($sub['payment_reference'] ?? '') ?>"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                </div>
                                                <div class="md:col-span-2">
                                                    <label class="block text-slate-500 text-xs mb-1.5">Notes</label>
                                                    <textarea name="notes" rows="2"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"><?= htmlspecialchars($metadata['notes'] ?? '') ?></textarea>
                                                </div>
                                            </div>

                                            <div class="flex justify-end gap-3 mt-6">
                                                <button type="button" onclick="closeModal('editModal<?= $sub['id'] ?>')"
                                                    class="px-6 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors">
                                                    Update Subscription
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Cancel Modal -->
                                <div id="cancelModal<?= $sub['id'] ?>"
                                    class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
                                    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-8 w-full max-w-md mx-4">
                                        <div class="flex items-center justify-between mb-6">
                                            <h2 class="text-lg font-bold text-white">Cancel Subscription</h2>
                                            <button onclick="closeModal('cancelModal<?= $sub['id'] ?>')"
                                                class="text-slate-400 hover:text-white">
                                                <i class="fas fa-times text-xl"></i>
                                            </button>
                                        </div>
                                        <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">

                                            <p class="text-slate-300 mb-4">Are you sure you want to cancel the subscription for
                                                <strong><?= htmlspecialchars($sub['company_name'] ?? 'Unknown') ?></strong>?</p>

                                            <div class="mb-4">
                                                <label class="block text-slate-500 text-xs mb-1.5">Reason for cancellation</label>
                                                <textarea name="cancel_reason" rows="3"
                                                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"></textarea>
                                            </div>

                                            <div class="flex justify-end gap-3">
                                                <button type="button" onclick="closeModal('cancelModal<?= $sub['id'] ?>')"
                                                    class="px-6 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                                                    Keep Subscription
                                                </button>
                                                <button type="submit"
                                                    class="px-6 py-2 bg-red-500 rounded-lg text-white font-semibold hover:bg-red-600 transition-colors">
                                                    Cancel Subscription
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Extend Trial Modal -->
                                <div id="extendModal<?= $sub['id'] ?>"
                                    class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
                                    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-8 w-full max-w-md mx-4">
                                        <div class="flex items-center justify-between mb-6">
                                            <h2 class="text-lg font-bold text-white">Extend Trial</h2>
                                            <button onclick="closeModal('extendModal<?= $sub['id'] ?>')"
                                                class="text-slate-400 hover:text-white">
                                                <i class="fas fa-times text-xl"></i>
                                            </button>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="action" value="extend_trial">
                                            <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">

                                            <p class="text-slate-300 mb-4">Extend trial for <strong><?= htmlspecialchars($sub['company_name'] ?? 'Unknown') ?></strong>.</p>

                                            <div class="mb-4">
                                                <label class="block text-slate-500 text-xs mb-1.5">Extra days (1–365)</label>
                                                <input type="number" name="extra_days" value="7" min="1" max="365"
                                                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                            </div>

                                            <div class="flex justify-end gap-3">
                                                <button type="button" onclick="closeModal('extendModal<?= $sub['id'] ?>')"
                                                    class="px-6 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                    class="px-6 py-2 bg-amber-500 rounded-lg text-white font-semibold hover:bg-amber-600 transition-colors">
                                                    Extend Trial
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t border-slate-700/60 flex items-center justify-between">
                    <p class="text-slate-400 text-sm">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?> subscriptions
                    </p>
                    <div class="flex items-center gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&tenant_id=<?= urlencode($company_filter) ?>"
                                class="px-3 py-1 bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&tenant_id=<?= urlencode($company_filter) ?>"
                                class="px-3 py-1 rounded <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?> transition-colors">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&tenant_id=<?= urlencode($company_filter) ?>"
                                class="px-3 py-1 bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Modal -->
    <div id="createModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-8 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-white">Create Subscription</h2>
                <button onclick="closeModal('createModal')" class="text-slate-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="create">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Company *</label>
                        <select name="tenant_id" required
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?>
                                    (<?= htmlspecialchars($company['slug']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Plan *</label>
                        <select name="plan_id" required
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="">Select Plan</option>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?= $plan['id'] ?>"><?= htmlspecialchars($plan['name']) ?> - KSh
                                    <?= number_format($plan['price_monthly'], 2) ?>/mo</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Status</label>
                        <select name="status"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="trialing">Trialing</option>
                            <option value="active">Active</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Billing Cycle</label>
                        <select name="billing_cycle"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Trial Days</label>
                        <input type="number" name="trial_days" value="14" min="0"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Payment Method</label>
                        <select name="payment_method"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="">Select Method</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Payment Reference</label>
                        <input type="text" name="payment_reference"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-slate-500 text-xs mb-1.5">Notes</label>
                        <textarea name="notes" rows="2"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('createModal')"
                        class="px-6 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors">
                        Create Subscription
                    </button>
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

        // Close modal when clicking outside
        document.querySelectorAll('[id$="Modal"]').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    </script>
</div>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>
