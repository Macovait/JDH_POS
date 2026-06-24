<?php
/**
 * Subscription Plans Management - SaaS Admin - Zero-Trust Implementation
 *
 * Manage subscription plans with controlled SaaS admin access.
 * Super Admin only with audited plan modifications.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/PlansModel.php';

admin_require_super_admin();

// Initialize Zero-Trust Context and Plans Model
$context = TenantContext::getInstance();
$plansModel = new PlansModel();

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
        switch ($action) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = (float) ($_POST['price'] ?? 0);
                $currency = $_POST['currency'] ?? 'KES';
                $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
                $max_users = (int) ($_POST['max_users'] ?? 1);
                $max_branches = (int) ($_POST['max_branches'] ?? 1);
                $max_products = (int) ($_POST['max_products'] ?? 100);
                $max_storage_mb = (int) ($_POST['max_storage_mb'] ?? 1024);
                $max_api_calls = (int) ($_POST['max_api_calls'] ?? 1000);
                $trial_days = (int) ($_POST['trial_days'] ?? 0);
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                $sort_order = (int) ($_POST['sort_order'] ?? 0);

                if (empty($name) || empty($slug)) {
                    throw new Exception('Name and slug are required.');
                }

                $plansModel->createPlan([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'price' => $price,
                    'currency' => $currency,
                    'billing_cycle' => $billing_cycle,
                    'max_users' => $max_users,
                    'max_branches' => $max_branches,
                    'max_products' => $max_products,
                    'max_storage_mb' => $max_storage_mb,
                    'max_api_calls' => $max_api_calls,
                    'is_active' => 1,
                    'is_featured' => $is_featured,
                    'trial_days' => $trial_days,
                    'sort_order' => $sort_order,
                ]);

                $message = 'Plan created successfully!';
                $message_type = 'success';
                break;

            case 'update':
                $plan_id = (int) ($_POST['plan_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = (float) ($_POST['price'] ?? 0);
                $currency = $_POST['currency'] ?? 'KES';
                $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
                $max_users = (int) ($_POST['max_users'] ?? 1);
                $max_branches = (int) ($_POST['max_branches'] ?? 1);
                $max_products = (int) ($_POST['max_products'] ?? 100);
                $max_storage_mb = (int) ($_POST['max_storage_mb'] ?? 1024);
                $max_api_calls = (int) ($_POST['max_api_calls'] ?? 1000);
                $trial_days = (int) ($_POST['trial_days'] ?? 0);
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $sort_order = (int) ($_POST['sort_order'] ?? 0);

                if (empty($name)) {
                    throw new Exception('Name is required.');
                }

                $plansModel->updatePlan($plan_id, [
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'currency' => $currency,
                    'billing_cycle' => $billing_cycle,
                    'max_users' => $max_users,
                    'max_branches' => $max_branches,
                    'max_products' => $max_products,
                    'max_storage_mb' => $max_storage_mb,
                    'max_api_calls' => $max_api_calls,
                    'is_active' => $is_active,
                    'is_featured' => $is_featured,
                    'trial_days' => $trial_days,
                    'sort_order' => $sort_order,
                ]);

                $message = 'Plan updated successfully!';
                $message_type = 'success';
                break;

            case 'delete':
                $plan_id = (int) ($_POST['plan_id'] ?? 0);

                // Delete plan using controlled method (includes usage validation)
                $plansModel->deletePlan($plan_id);

                $message = 'Plan deleted successfully!';
                $message_type = 'success';
                break;

            case 'toggle_active':
                $plan_id = (int) ($_POST['plan_id'] ?? 0);

                // Toggle plan status using controlled method
                $plansModel->togglePlanStatus($plan_id);

                $message = 'Plan status updated successfully!';
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

// Get all plans with controlled access
$plans = $plansModel->getPlans();

$current_page = 'plans';
$page_title = 'Subscription Plans';

// Start output buffering for layout
ob_start();

// Extra CSS for this page
$extra_css = '
<style>
    .plan-card {
        transition: all 0.3s ease;
    }
    .plan-card:hover {
        transform: translateY(-5px);
    }
    .popular-badge {
        background: linear-gradient(135deg, #FBBF24 0%, #F59E0B 100%);
    }
</style>
';
?>
<?php
$totalPlans = count($plans);
$activePlans = count(array_filter($plans, fn($p) => $p['is_active']));
$featuredPlans = count(array_filter($plans, fn($p) => $p['is_featured']));
?>
<div class="space-y-5">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-400 uppercase tracking-wider mb-1">
                <i class="fas fa-layer-group text-xs"></i>
                <span>Pricing & Plans</span>
            </div>
            <h1 class="text-2xl font-bold text-white">Subscription Plans</h1>
            <p class="text-sm text-slate-500 mt-1">Manage pricing tiers and feature limits for tenants</p>
        </div>
        <div class="flex gap-2 items-center">
            <button onclick="window.location.reload()" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-800/80 border border-slate-700 rounded-lg text-slate-400 text-sm hover:bg-slate-700 transition-colors">
                <i class="fas fa-sync-alt text-xs"></i> Refresh
            </button>
            <button onclick="openModal('createModal')" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-all">
                <i class="fas fa-plus"></i> Add Plan
            </button>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-slate-900/50 rounded-lg p-3">
                <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Total Plans</div>
                <div class="mt-1 text-xl font-bold text-white"><?= $totalPlans ?></div>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-3">
                <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Active</div>
                <div class="mt-1 text-xl font-bold text-emerald-400"><?= $activePlans ?></div>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-3">
                <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Inactive</div>
                <div class="mt-1 text-xl font-bold <?= ($totalPlans - $activePlans) > 0 ? 'text-amber-400' : 'text-slate-500' ?>"><?= $totalPlans - $activePlans ?></div>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-3">
                <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Featured</div>
                <div class="mt-1 text-xl font-bold text-amber-400"><?= $featuredPlans ?></div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="flex items-center gap-2 px-4 py-3 rounded-lg <?= $message_type === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border border-red-500/30 text-red-400' ?> text-sm">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-1"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Plans Grid -->
    <?php if (empty($plans)): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex flex-col items-center justify-center py-20 px-6 text-center">
                <div class="w-16 h-16 rounded-xl bg-amber-500/10 flex items-center justify-center mb-5">
                    <i class="fas fa-layer-group text-2xl text-amber-400"></i>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">No subscription plans yet</h3>
                <p class="text-sm text-slate-500 max-w-md mb-6">
                    Create your first plan to start offering tiered pricing to tenants.
                </p>
                <div class="flex items-center gap-3">
                    <button onclick="openModal('createModal')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                        <i class="fas fa-plus"></i> Create First Plan
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <?php foreach ($plans as $plan): ?>
                <?php
                $cycleLbl = ['monthly' => '/mo', 'quarterly' => '/qtr', 'annual' => '/yr'];
                $cycleTag = $cycleLbl[$plan['billing_cycle'] ?? 'monthly'] ?? '/mo';
                ?>
                <div class="plan-card bg-slate-800/40 border <?= $plan['is_featured'] ? 'border-amber-500/40' : 'border-slate-700/60' ?> rounded-xl overflow-hidden relative <?= !$plan['is_active'] ? 'opacity-60' : '' ?>">
                    <?php if ($plan['is_featured']): ?>
                        <div class="popular-badge text-center py-1 text-[10px] font-bold text-slate-900 uppercase tracking-wider">
                            Featured
                        </div>
                    <?php endif; ?>

                    <div class="p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h3 class="text-base font-bold text-white"><?= htmlspecialchars($plan['name']) ?></h3>
                                <p class="text-slate-500 text-xs mt-0.5"><?= htmlspecialchars($plan['description'] ?? '') ?></p>
                            </div>
                            <div class="flex gap-1">
                                <?php if ($plan['is_active']): ?>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-500/15 text-emerald-400">Active</span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-500/15 text-slate-400">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="text-2xl font-bold text-amber-400">
                                <?= htmlspecialchars($plan['currency'] ?? 'KES') ?> <?= number_format($plan['price'], 2) ?>
                                <span class="text-xs text-slate-500 font-normal"><?= $cycleTag ?></span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5 capitalize"><?= htmlspecialchars($plan['billing_cycle'] ?? 'monthly') ?> billing</p>
                        </div>

                        <div class="space-y-2 mb-4">
                            <div class="flex items-center gap-2 text-sm text-slate-300">
                                <i class="fas fa-users text-amber-400/70 w-4 text-xs"></i>
                                <span><?= $plan['max_users'] ?> Users</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-300">
                                <i class="fas fa-store text-amber-400/70 w-4 text-xs"></i>
                                <span><?= $plan['max_branches'] ?> Branches</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-300">
                                <i class="fas fa-box text-amber-400/70 w-4 text-xs"></i>
                                <span><?= number_format($plan['max_products']) ?> Products</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-300">
                                <i class="fas fa-database text-amber-400/70 w-4 text-xs"></i>
                                <span><?= number_format($plan['max_storage_mb']) ?> MB Storage</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-300">
                                <i class="fas fa-code text-amber-400/70 w-4 text-xs"></i>
                                <span><?= number_format($plan['max_api_calls']) ?> API Calls</span>
                            </div>
                            <?php if ($plan['trial_days'] > 0): ?>
                            <div class="flex items-center gap-2 text-sm text-slate-300">
                                <i class="fas fa-clock text-amber-400/70 w-4 text-xs"></i>
                                <span><?= $plan['trial_days'] ?>-day trial</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex gap-2">
                            <button onclick="openModal('editModal<?= $plan['id'] ?>')"
                                class="flex-1 px-3 py-1.5 bg-blue-500/10 border border-blue-500/20 rounded-lg text-blue-400 hover:bg-blue-500/20 transition-colors text-xs font-medium">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>
                            <form method="POST" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                <button type="submit"
                                    class="w-full px-3 py-1.5 <?= $plan['is_active'] ? 'bg-amber-500/10 border border-amber-500/20 text-amber-400 hover:bg-amber-500/20' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/20' ?> rounded-lg transition-colors text-xs font-medium">
                                    <i class="fas <?= $plan['is_active'] ? 'fa-pause' : 'fa-play' ?> mr-1"></i>
                                    <?= $plan['is_active'] ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                        </div>

                        <?php if ($plan['slug'] !== 'free'): ?>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                            <button type="submit"
                                class="w-full px-3 py-1.5 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 hover:bg-red-500/20 transition-colors text-xs font-medium"
                                onclick="return confirm('Are you sure you want to delete this plan?')">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div id="editModal<?= $plan['id'] ?>"
                    class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
                    <div class="bg-slate-900 border border-slate-700/60 rounded-xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
                        <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
                            <h2 class="text-base font-bold text-white">Edit Plan</h2>
                            <button onclick="closeModal('editModal<?= $plan['id'] ?>')"
                                class="text-slate-400 hover:text-white transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form method="POST" class="p-6">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">Plan Name *</label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($plan['name']) ?>" required
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">Description</label>
                                    <input type="text" name="description"
                                        value="<?= htmlspecialchars($plan['description'] ?? '') ?>"
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">Price</label>
                                    <input type="number" name="price" value="<?= $plan['price'] ?>"
                                        step="0.01" min="0"
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">Currency</label>
                                    <input type="text" name="currency" value="<?= htmlspecialchars($plan['currency'] ?? 'KES') ?>" maxlength="3"
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">Billing Cycle</label>
                                    <select name="billing_cycle"
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                                        <option value="monthly" <?= ($plan['billing_cycle'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                        <option value="quarterly" <?= ($plan['billing_cycle'] ?? '') === 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                                        <option value="annual" <?= ($plan['billing_cycle'] ?? '') === 'annual' ? 'selected' : '' ?>>Annual</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">Max Users</label>
                                    <input type="number" name="max_users" value="<?= $plan['max_users'] ?>" min="1"
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">Max Branches</label>
                                    <input type="number" name="max_branches" value="<?= $plan['max_branches'] ?>" min="1"
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">Max Products</label>
                                    <input type="number" name="max_products" value="<?= $plan['max_products'] ?>" min="1"
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">Storage (MB)</label>
                                    <input type="number" name="max_storage_mb" value="<?= $plan['max_storage_mb'] ?>"
                                        min="1"
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">API Calls</label>
                                    <input type="number" name="max_api_calls" value="<?= $plan['max_api_calls'] ?>" min="0"
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">Trial Days</label>
                                    <input type="number" name="trial_days" value="<?= $plan['trial_days'] ?>" min="0"
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-slate-500 text-xs mb-1.5">Sort Order</label>
                                    <input type="number" name="sort_order" value="<?= $plan['sort_order'] ?>" min="0"
                                        class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="flex items-center gap-2 text-slate-300 text-sm">
                                        <input type="checkbox" name="is_featured" <?= $plan['is_featured'] ? 'checked' : '' ?> class="rounded">
                                        Mark as Featured
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center gap-2 text-slate-300 text-sm">
                                        <input type="checkbox" name="is_active" <?= $plan['is_active'] ? 'checked' : '' ?> class="rounded">
                                        Active
                                    </label>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-700/60">
                                <button type="button" onclick="closeModal('editModal<?= $plan['id'] ?>')" class="px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm hover:bg-slate-700 hover:text-white transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                                    <i class="fas fa-save text-xs"></i> Update Plan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <!-- Create Modal -->
    <div id="createModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-slate-900 border border-slate-700/60 rounded-xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
                <h2 class="text-base font-bold text-white">Create New Plan</h2>
                <button onclick="closeModal('createModal')" class="text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="create">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Plan Name *</label>
                        <input type="text" name="name" required
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Slug *</label>
                        <input type="text" name="slug" required placeholder="plan-name"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-slate-500 text-xs mb-1.5">Description</label>
                        <input type="text" name="description"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Price</label>
                        <input type="number" name="price" value="0" step="0.01" min="0"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Currency</label>
                        <input type="text" name="currency" value="KES" maxlength="3"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Billing Cycle</label>
                        <select name="billing_cycle"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="annual">Annual</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Max Users</label>
                        <input type="number" name="max_users" value="1" min="1"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Max Branches</label>
                        <input type="number" name="max_branches" value="1" min="1"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Max Products</label>
                        <input type="number" name="max_products" value="100" min="1"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Storage (MB)</label>
                        <input type="number" name="max_storage_mb" value="1024" min="1"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">API Calls</label>
                        <input type="number" name="max_api_calls" value="1000" min="0"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Trial Days</label>
                        <input type="number" name="trial_days" value="0" min="0"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs mb-1.5">Sort Order</label>
                        <input type="number" name="sort_order" value="0" min="0"
                            class="w-full px-3 py-2 bg-slate-800/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 text-slate-300 text-sm">
                            <input type="checkbox" name="is_featured" class="rounded">
                            Mark as Featured
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-700/60">
                    <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm hover:bg-slate-700 hover:text-white transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                        <i class="fas fa-plus text-xs"></i> Create Plan
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
