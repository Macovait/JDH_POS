<?php
/**
 * Companies Management - SaaS Admin
 * 
 * Manage all companies/tenants in the system.
 * Super Admin only.
 */

require_once __DIR__ . '/bootstrap.php';

admin_require_super_admin();

$pdo = admin_require_db(['admins', 'pos_tenants', 'pos_plans', 'pos_subscriptions']);

// Load business types dynamically from canonical config (no hardcoding)
$app_config = require dirname(__DIR__) . '/config/app.php';
$business_types = [];
foreach ($app_config['business_types'] ?? [] as $code => $cfg) {
    $business_types[$code] = $cfg['name'] ?? $code;
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf, 'admin_companies')) {
        $message = 'Invalid or expired security token. Please refresh the page and try again.';
        $message_type = 'error';
    } else {
        try {
        $pdo = admin_require_db(['admins', 'pos_tenants', 'pos_plans', 'pos_subscriptions']);

        switch ($action) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $industry = trim($_POST['industry'] ?? '');
                $business_type = $_POST['business_type'] ?? 'retail';
                $timezone = $_POST['timezone'] ?? 'Africa/Nairobi';
                $currency = $_POST['currency'] ?? 'KES';
                $plan_id = (int) ($_POST['plan_id'] ?? 1);
                $trial_days = (int) ($_POST['trial_days'] ?? 14);

                if (empty($name) || empty($slug) || empty($email)) {
                    throw new Exception('Name, slug, and email are required.');
                }

                // Check if slug already exists
                $existing = db_fetch_one("SELECT id FROM pos_tenants WHERE slug = ?", [$slug]);
                if ($existing) {
                    throw new Exception('Company slug already exists.');
                }

                // Get plan limits
                $plan = db_fetch_one("SELECT * FROM pos_plans WHERE id = ?", [$plan_id]);
                if (!$plan) {
                    throw new Exception('Invalid plan selected.');
                }

                // Create company
                // Create tenant with settings as JSON
                $settings = json_encode([
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'industry' => $industry,
                    'business_type' => $business_type,
                    'timezone' => $timezone,
                    'currency' => $currency,
                    'max_users' => $plan['max_users'],
                    'max_branches' => $plan['max_branches'],
                    'max_products' => $plan['max_products'],
                    'storage_limit_mb' => $plan['max_storage_mb']
                ]);

                $tenant_id = db_insert('pos_tenants', [
                    'uuid' => bin2hex(random_bytes(16)),
                    'name' => $name,
                    'slug' => $slug,
                    'status' => 'trial',
                    'plan_id' => $plan_id,
                    'settings' => $settings,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                // Create subscription
                db_insert('pos_subscriptions', [
                    'tenant_id' => $tenant_id,
                    'plan_id' => $plan_id,
                    'status' => 'trialing',
                    'billing_cycle' => 'monthly',
                    'amount' => $plan['price_monthly'],
                    'currency' => $currency,
                    'trial_ends_at' => date('Y-m-d H:i:s', strtotime("+{$trial_days} days")),
                    'current_period_start' => date('Y-m-d H:i:s'),
                    'current_period_end' => date('Y-m-d H:i:s', strtotime("+{$trial_days} days")),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                $message = 'Company created successfully!';
                $message_type = 'success';
                break;

            case 'update':
                $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $industry = trim($_POST['industry'] ?? '');
                $business_type = $_POST['business_type'] ?? 'retail';
                $timezone = $_POST['timezone'] ?? 'Africa/Nairobi';
                $currency = $_POST['currency'] ?? 'KES';
                $status = $_POST['status'] ?? 'active';

                if (empty($name) || empty($email)) {
                    throw new Exception('Name and email are required.');
                }

                // Get current settings and update
                $current = db_fetch_one("SELECT settings FROM pos_tenants WHERE id = ?", [$tenant_id]);
                $currentSettings = json_decode($current['settings'] ?? '{}', true) ?: [];

                $updatedSettings = array_merge($currentSettings, [
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'industry' => $industry,
                    'business_type' => $business_type,
                    'timezone' => $timezone,
                    'currency' => $currency
                ]);

                db_update('pos_tenants', [
                    'name' => $name,
                    'status' => $status,
                    'settings' => json_encode($updatedSettings),
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$tenant_id]);

                $message = 'Company updated successfully!';
                $message_type = 'success';
                break;

            case 'delete':
                $tenant_id = (int) ($_POST['tenant_id'] ?? 0);

                // Update status to cancelled (pos_tenants doesn't have deleted_at)
                db_update('pos_tenants', [
                    'status' => 'cancelled',
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$tenant_id]);

                $message = 'Company deleted successfully!';
                $message_type = 'success';
                break;

            case 'suspend':
                $tenant_id = (int) ($_POST['tenant_id'] ?? 0);

                db_update('pos_tenants', [
                    'status' => 'suspended',
                ], 'id = ?', [$tenant_id]);

                $message = 'Company suspended successfully!';
                $message_type = 'success';
                break;

            case 'activate':
                $tenant_id = (int) ($_POST['tenant_id'] ?? 0);

                db_update('pos_tenants', [
                    'status' => 'active',
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$tenant_id]);

                $message = 'Company activated successfully!';
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
$business_type_filter = $_GET['business_type'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where = ['1=1'];
$params = [];

if ($status_filter) {
    if ($status_filter === 'expired') {
        $where[] = 'ts.status = ?';
        $params[] = 'expired';
    } else {
        $where[] = 't.status = ?';
        $params[] = $status_filter;
    }
}

if ($business_type_filter) {
    $where[] = 'JSON_UNQUOTE(JSON_EXTRACT(t.settings, "$.business_type")) = ?';
    $params[] = $business_type_filter;
}

if ($search) {
    $where[] = '(t.name LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(t.settings, "$.email")) LIKE ? OR t.slug LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$where_clause = implode(' AND ', $where);

// Get total count (needs same joins when filtering by subscription status)
$totalSql = "SELECT COUNT(*) FROM pos_tenants t";
if ($status_filter === 'expired') {
    $totalSql .= " LEFT JOIN (
        SELECT s1.*
        FROM pos_subscriptions s1
        INNER JOIN (
            SELECT tenant_id, MAX(created_at) as max_created
            FROM pos_subscriptions
            GROUP BY tenant_id
        ) s2 ON s1.tenant_id = s2.tenant_id AND s1.created_at = s2.max_created
    ) ts ON t.id = ts.tenant_id";
}
$totalSql .= " WHERE {$where_clause}";
$total = db_fetch_value($totalSql, $params);

// Get tenants (latest subscription per tenant, any status)
$companies = db_fetch_all(
    "SELECT t.*,
            JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.email')) as email,
            JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.phone')) as phone,
            JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.business_type')) as business_type,
            pp.name as plan_name,
            pp.price_monthly as plan_price,
            ts.status as subscription_status,
            ts.current_period_end as subscription_expires
     FROM pos_tenants t
     LEFT JOIN (
         SELECT s1.*
         FROM pos_subscriptions s1
         INNER JOIN (
             SELECT tenant_id, MAX(created_at) as max_created
             FROM pos_subscriptions
             GROUP BY tenant_id
         ) s2 ON s1.tenant_id = s2.tenant_id AND s1.created_at = s2.max_created
     ) ts ON t.id = ts.tenant_id
     LEFT JOIN pos_plans pp ON ts.plan_id = pp.id
     WHERE {$where_clause}
     ORDER BY t.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

// Get plans for dropdown
$plans = db_fetch_all("SELECT * FROM pos_plans WHERE is_active = 1 ORDER BY sort_order");

// Pagination
$total_pages = ceil($total / $per_page);

$current_page = 'companies';
$page_title = 'Tenant Management';
$breadcrumbs = [
    ['label' => 'Tenants']
];

// Start output buffering for layout
ob_start();
?>
<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-building text-amber-400"></i> Tenant Management
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= number_format($total); ?> tenant<?= $total !== 1 ? 's' : ''; ?> registered
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <form method="POST" action="export_csv.php" class="inline" target="_blank">
            <input type="hidden" name="table" value="pos_tenants">
            <input type="hidden" name="columns" value='[{"field":"id","label":"ID"},{"field":"name","label":"Name"},{"field":"slug","label":"Slug"},{"field":"email","label":"Email"},{"field":"business_type","label":"Business Type"},{"field":"status","label":"Status","format":"status"},{"field":"created_at","label":"Created","format":"date"}]'>
            <input type="hidden" name="filename" value="tenants">
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-download text-xs"></i> Export CSV
            </button>
        </form>
        <button onclick="openModal('createModal')"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add Tenant
        </button>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg <?= $message_type === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border-red-500/30 text-red-400' ?> text-sm">
    <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
    <form method="GET" class="flex flex-wrap gap-2 items-end">
        <div class="relative flex-1 min-w-[160px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search tenants..."
                   class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <select name="status" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">All Status</option>
            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="trial" <?= $status_filter === 'trial' ? 'selected' : '' ?>>Trial</option>
            <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>Expired (Trial)</option>
        </select>
        <select name="business_type" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">All Types</option>
            <?php foreach ($business_types as $bt_code => $bt_name): ?>
                <option value="<?= htmlspecialchars($bt_code) ?>" <?= $business_type_filter === $bt_code ? 'selected' : '' ?>><?= htmlspecialchars($bt_name) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-filter mr-1 text-xs"></i>Filter
        </button>
        <a href="companies.php" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times mr-1 text-xs"></i>Clear
        </a>
    </form>
</div>

<!-- Companies Table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700/60">
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">Company</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">Plan</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">Business Type</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">Status</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">Usage</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-300">Created</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-slate-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($companies)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-0">
                                    <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                                        <div class="w-20 h-20 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center mb-5">
                                            <i class="fas fa-building text-3xl text-amber-400"></i>
                                        </div>
                                        <h3 class="text-lg font-semibold text-white mb-2">
                                            <?= $search ? 'No matching tenants' : 'No tenants yet' ?>
                                        </h3>
                                        <p class="text-sm text-slate-500 max-w-sm mb-5">
                                            <?= $search 
                                                ? 'Try adjusting your search terms or filters to find what you\'re looking for.' 
                                                : 'Get started by creating your first tenant. They\'ll be able to access the platform immediately.' 
                                            ?>
                                        </p>
                                        <?php if (!$search): ?>
                                            <button onclick="openModal('createModal')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
                                                <i class="fas fa-plus text-xs"></i>
                                                Add First Tenant
                                            </button>
                                        <?php else: ?>
                                            <a href="companies.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                                                <i class="fas fa-times text-xs"></i>
                                                Clear Filters
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($companies as $company): ?>
                                <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-amber-500/20 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-building text-accent"></i>
                                            </div>
                                            <div>
                                                <p class="text-white font-semibold"><?= htmlspecialchars($company['name']) ?>
                                                </p>
                                                <p class="text-slate-400 text-sm"><?= htmlspecialchars($company['email']) ?></p>
                                                <p class="text-slate-500 text-xs"><?= htmlspecialchars($company['slug']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-white"><?= htmlspecialchars($company['plan_name'] ?? 'Free') ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-slate-700/50 border border-slate-700/60 text-slate-300">
                                            <i class="fas <?= htmlspecialchars($app_config['business_types'][$company['business_type'] ?? 'retail']['icon'] ?? 'fa-store') ?>" style="color:#fbbf24; font-size:0.65rem;"></i>
                                            <?= htmlspecialchars($business_types[$company['business_type'] ?? 'retail'] ?? 'Retail') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium status-<?= $company['status'] ?>">
                                                <?= ucfirst($company['status']) ?>
                                            </span>
                                            <?php if (!empty($company['subscription_status']) && $company['subscription_status'] !== $company['status']): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $company['subscription_status'] === 'expired' ? 'bg-orange-500/20 text-orange-400' : ($company['subscription_status'] === 'trialing' ? 'bg-blue-500/20 text-blue-400' : 'bg-slate-500/20 text-slate-400') ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $company['subscription_status'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <p class="text-slate-300"><?= $company['max_users'] ?> users</p>
                                            <p class="text-slate-400"><?= $company['max_branches'] ?> branches</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="text-slate-400 text-sm"><?= date('M d, Y', strtotime($company['created_at'])) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="tenant_detail.php?id=<?= $company['id'] ?>"
                                                class="w-8 h-8 bg-indigo-500/20 rounded-lg flex items-center justify-center text-indigo-400 hover:bg-indigo-500/30 transition-colors"
                                                title="View">
                                                <i class="fas fa-eye text-sm"></i>
                                            </a>
                                            <button onclick="openModal('editModal<?= $company['id'] ?>')"
                                                class="w-8 h-8 bg-blue-500/20 rounded-lg flex items-center justify-center text-blue-400 hover:bg-blue-500/30 transition-colors"
                                                title="Edit">
                                                <i class="fas fa-edit text-sm"></i>
                                            </button>
                                            <?php if ($company['status'] === 'active'): ?>
                                                <form method="POST" class="inline" id="suspendForm<?= $company['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token('admin_companies')) ?>">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <input type="hidden" name="tenant_id" value="<?= $company['id'] ?>">
                                                    <button type="button"
                                                        class="w-8 h-8 bg-yellow-500/20 rounded-lg flex items-center justify-center text-yellow-400 hover:bg-yellow-500/30 transition-colors"
                                                        title="Suspend"
                                                        onclick="showConfirmModal({title: 'Suspend Company', message: 'Are you sure you want to suspend <?= htmlspecialchars(addslashes($company['name'])) ?>? They will lose access immediately.', type: 'warning', confirmText: 'Suspend', onConfirm: () => document.getElementById('suspendForm<?= $company['id'] ?>').submit()})">
                                                        <i class="fas fa-pause text-sm"></i>
                                                    </button>
                                                </form>
                                            <?php elseif ($company['status'] === 'suspended'): ?>
                                                <form method="POST" class="inline" id="activateForm<?= $company['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token('admin_companies')) ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="tenant_id" value="<?= $company['id'] ?>">
                                                    <button type="button"
                                                        class="w-8 h-8 bg-green-500/20 rounded-lg flex items-center justify-center text-green-400 hover:bg-green-500/30 transition-colors"
                                                        title="Activate"
                                                        onclick="showConfirmModal({title: 'Activate Company', message: 'Reactivate <?= htmlspecialchars(addslashes($company['name'])) ?>?', type: 'info', confirmText: 'Activate', onConfirm: () => document.getElementById('activateForm<?= $company['id'] ?>').submit()})">
                                                        <i class="fas fa-play text-sm"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="inline" id="deleteForm<?= $company['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token('admin_companies')) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="tenant_id" value="<?= $company['id'] ?>">
                                                <button type="button"
                                                    class="w-8 h-8 bg-red-500/20 rounded-lg flex items-center justify-center text-red-400 hover:bg-red-500/30 transition-colors"
                                                    title="Delete"
                                                    onclick="showConfirmModal({title: 'Delete Company', message: 'Delete <?= htmlspecialchars(addslashes($company['name'])) ?> permanently? All data will be lost.', type: 'danger', confirmText: 'Delete', onConfirm: () => document.getElementById('deleteForm<?= $company['id'] ?>').submit()})">
                                                    <i class="fas fa-trash text-sm"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div id="editModal<?= $company['id'] ?>"
                                    class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
                                    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-8 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
                                        <div class="flex items-center justify-between mb-6">
                                            <h2 class="text-lg font-bold text-white">Edit Company</h2>
                                            <button onclick="closeModal('editModal<?= $company['id'] ?>')"
                                                class="text-slate-400 hover:text-white">
                                                <i class="fas fa-times text-xl"></i>
                                            </button>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token('admin_companies')) ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="tenant_id" value="<?= $company['id'] ?>">

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Company Name *</label>
                                                    <input type="text" name="name"
                                                        value="<?= htmlspecialchars($company['name']) ?>" required
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                </div>
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Email *</label>
                                                    <input type="email" name="email"
                                                        value="<?= htmlspecialchars($company['email']) ?>" required
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                </div>
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Phone</label>
                                                    <input type="text" name="phone"
                                                        value="<?= htmlspecialchars($company['phone'] ?? '') ?>"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                </div>
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Industry</label>
                                                    <input type="text" name="industry"
                                                        value="<?= htmlspecialchars($company['industry'] ?? '') ?>"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                </div>
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Business Type</label>
                                                    <select name="business_type"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                        <?php foreach ($business_types as $bt_code => $bt_name): ?>
                                                            <option value="<?= htmlspecialchars($bt_code) ?>" <?= ($company['business_type'] ?? 'retail') === $bt_code ? 'selected' : '' ?>><?= htmlspecialchars($bt_name) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Status</label>
                                                    <select name="status"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                        <option value="active" <?= $company['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="trial" <?= $company['status'] === 'trial' ? 'selected' : '' ?>>Trial</option>
                                                        <option value="suspended" <?= $company['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                        <option value="cancelled" <?= $company['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-slate-500 text-xs mb-1.5">Currency</label>
                                                    <select name="currency"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                        <option value="KES" <?= $company['currency'] === 'KES' ? 'selected' : '' ?>>KES - Kenyan Shilling</option>
                                                        <option value="USD" <?= $company['currency'] === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                                                        <option value="EUR" <?= $company['currency'] === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                                                        <option value="GBP" <?= $company['currency'] === 'GBP' ? 'selected' : '' ?>>GBP - British Pound</option>
                                                    </select>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <label class="block text-slate-500 text-xs mb-1.5">Address</label>
                                                    <textarea name="address" rows="2"
                                                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
                                                </div>
                                            </div>

                                            <div class="flex justify-end gap-3 mt-6">
                                                <button type="button" onclick="closeModal('editModal<?= $company['id'] ?>')"
                                                    class="px-6 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors">
                                                    Update Company
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
                        Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?> companies
                    </p>
                    <div class="flex items-center gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&business_type=<?= urlencode($business_type_filter) ?>"
                                class="px-3 py-1 bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&business_type=<?= urlencode($business_type_filter) ?>"
                                class="px-3 py-1 rounded <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?> transition-colors">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&business_type=<?= urlencode($business_type_filter) ?>"
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
                <h2 class="text-lg font-bold text-white">Add New Company</h2>
                <button onclick="closeModal('createModal')" class="text-slate-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token('admin_companies')) ?>">
                <input type="hidden" name="action" value="create">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Company Name *</label>
                        <input type="text" name="name" required
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Slug *</label>
                        <input type="text" name="slug" required placeholder="tenant-name"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Email *</label>
                        <input type="email" name="email" required
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Phone</label>
                        <input type="text" name="phone"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Industry</label>
                        <input type="text" name="industry"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Business Type</label>
                        <select name="business_type"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <?php foreach ($business_types as $bt_code => $bt_name): ?>
                                <option value="<?= htmlspecialchars($bt_code) ?>"><?= htmlspecialchars($bt_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Subscription Plan</label>
                        <select name="plan_id"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?= $plan['id'] ?>"><?= htmlspecialchars($plan['name']) ?> - KSh
                                    <?= number_format($plan['price_monthly'], 2) ?>/mo</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Trial Days</label>
                        <input type="number" name="trial_days" value="14" min="0"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Currency</label>
                        <select name="currency"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="KES">KES - Kenyan Shilling</option>
                            <option value="USD">USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - British Pound</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Timezone</label>
                        <select name="timezone"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="Africa/Nairobi">Africa/Nairobi (EAT)</option>
                            <option value="UTC">UTC</option>
                            <option value="America/New_York">America/New_York (EST)</option>
                            <option value="Europe/London">Europe/London (GMT)</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-slate-500 text-xs mb-1.5">Address</label>
                        <textarea name="address" rows="2"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('createModal')"
                        class="px-6 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors">
                        Create Company
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

        // Auto-open create modal when navigated with #create hash (from admin dashboard)
        if (window.location.hash === '#create') {
            openModal('createModal');
        }
    </script>
</div>
<?php
// Get buffered content
$page_content = ob_get_clean();

// Include layout
require_once __DIR__ . '/layouts/super_admin.php';
?>
