<?php
/**
 * Feature Flags & Entitlements Management
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
        switch ($action) {
            case 'create_feature':
                db_insert('platform_features', [
                    'slug' => $_POST['slug'],
                    'name' => $_POST['name'],
                    'category' => $_POST['category'],
                    'default_limit' => $_POST['default_limit'] ?: null,
                    'default_unit' => $_POST['default_unit'] ?: null,
                    'is_beta' => !empty($_POST['is_beta']) ? 1 : 0,
                    'description' => $_POST['description'],
                ]);
                $message = 'Feature created successfully!';
                $message_type = 'success';
                break;

            case 'update_feature':
                db_update('platform_features', [
                    'name' => $_POST['name'],
                    'category' => $_POST['category'],
                    'default_limit' => $_POST['default_limit'] ?: null,
                    'default_unit' => $_POST['default_unit'] ?: null,
                    'is_beta' => !empty($_POST['is_beta']) ? 1 : 0,
                    'description' => $_POST['description'],
                ], 'id = ?', [$_POST['feature_id']]);
                $message = 'Feature updated!';
                $message_type = 'success';
                break;

            case 'toggle_global':
                $feature_id = (int) $_POST['feature_id'];
                $enabled = !empty($_POST['enabled']);
                $rollout = (int) ($_POST['rollout_percentage'] ?? 100);
                db_update('platform_features', [
                    'is_beta' => $enabled ? 0 : 1,
                ], 'id = ?', [$feature_id]);
                $message = 'Global toggle updated!';
                $message_type = 'success';
                break;

            case 'override_tenant':
                $feature_id = (int) $_POST['feature_id'];
                $tenant_id = (int) $_POST['tenant_id'];
                $is_enabled = !empty($_POST['is_enabled']) ? 1 : 0;
                $limit_value = $_POST['limit_value'] !== '' ? (int) $_POST['limit_value'] : null;
                $is_unlimited = !empty($_POST['is_unlimited']) ? 1 : 0;
                $effective_from = $_POST['effective_from'] ?? date('Y-m-d');
                $effective_until = $_POST['effective_until'] ?: null;
                $reason = $_POST['override_reason'] ?? '';

                // Deactivate previous overrides for this tenant+feature
                db_update('tenant_entitlements', ['is_enabled' => 0], 'tenant_id = ? AND feature_id = ?', [$tenant_id, $feature_id]);

                db_insert('tenant_entitlements', [
                    'tenant_id' => $tenant_id,
                    'feature_id' => $feature_id,
                    'source' => 'override',
                    'limit_value' => $limit_value,
                    'is_unlimited' => $is_unlimited,
                    'is_enabled' => $is_enabled,
                    'effective_from' => $effective_from,
                    'effective_until' => $effective_until,
                    'overridden_by_admin_id' => $_SESSION['admin_id'] ?? null,
                    'override_reason' => $reason,
                ]);
                $message = 'Tenant override applied!';
                $message_type = 'success';
                break;

            case 'map_plan_feature':
                db_insert('plan_features', [
                    'plan_id' => (int) $_POST['plan_id'],
                    'feature_id' => (int) $_POST['feature_id'],
                    'limit_value' => $_POST['limit_value'] !== '' ? (int) $_POST['limit_value'] : null,
                    'is_unlimited' => !empty($_POST['is_unlimited']) ? 1 : 0,
                    'is_included' => !empty($_POST['is_included']) ? 1 : 0,
                    'overage_rate' => $_POST['overage_rate'] ?: null,
                ]);
                $message = 'Plan feature mapped!';
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

$features = db_fetch_all("SELECT * FROM platform_features ORDER BY category, name");
$plans = db_fetch_all("SELECT * FROM pos_plans WHERE is_active = 1 ORDER BY sort_order");
$tenants = db_fetch_all("SELECT id, name, slug FROM pos_tenants ORDER BY name LIMIT 100");
$planFeatures = db_fetch_all(
    "SELECT pf.*, p.name as plan_name, f.name as feature_name
     FROM plan_features pf
     JOIN pos_plans p ON pf.plan_id = p.id
     JOIN platform_features f ON pf.feature_id = f.id
     ORDER BY p.name, f.name"
);

$current_page = 'features';
$page_title = 'Feature Flags & Entitlements';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-lg font-bold text-white">Feature Flags & Entitlements</h1>
            <p class="text-slate-400 text-sm mt-1">Manage platform features, plan mappings, and tenant overrides</p>
        </div>
        <button onclick="openModal('createFeatureModal')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">
            <i class="fas fa-plus mr-1"></i> Add Feature
        </button>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-emerald-500/20 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/20 border-red-500/30 text-red-400' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Features Grid -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">Platform Features</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="pb-2 pr-4">Feature</th>
                        <th class="pb-2 pr-4">Slug</th>
                        <th class="pb-2 pr-4">Category</th>
                        <th class="pb-2 pr-4">Default Limit</th>
                        <th class="pb-2 pr-4">Status</th>
                        <th class="pb-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($features as $f): ?>
                    <tr class="border-b border-slate-700/30">
                        <td class="py-3 pr-4">
                            <p class="text-white font-medium"><?= htmlspecialchars($f['name']) ?></p>
                            <p class="text-slate-500 text-xs"><?= htmlspecialchars($f['description'] ?? '') ?></p>
                        </td>
                        <td class="py-3 pr-4 text-slate-400"><?= htmlspecialchars($f['slug']) ?></td>
                        <td class="py-3 pr-4">
                            <span class="px-2 py-0.5 rounded text-xs bg-slate-700/50 text-slate-300"><?= ucfirst($f['category']) ?></span>
                        </td>
                        <td class="py-3 pr-4 text-white">
                            <?= $f['is_beta'] ? '—' : ($f['default_limit'] ? number_format($f['default_limit']) . ' ' . $f['default_unit'] : 'Unlimited') ?>
                        </td>
                        <td class="py-3 pr-4">
                            <span class="px-2 py-0.5 rounded text-xs <?= $f['is_beta'] ? 'bg-yellow-500/20 text-yellow-400' : 'bg-emerald-500/20 text-emerald-400' ?>">
                                <?= $f['is_beta'] ? 'Beta' : 'Live' ?>
                            </span>
                        </td>
                        <td class="py-3 text-right">
                            <button onclick="openModal('editFeature<?= $f['id'] ?>')" class="text-blue-400 hover:text-blue-300 mr-2"><i class="fas fa-edit"></i></button>
                            <button onclick="openModal('overrideModal<?= $f['id'] ?>')" class="text-indigo-400 hover:text-indigo-300" title="Tenant Override"><i class="fas fa-user-cog"></i></button>
                        </td>
                    </tr>

                    <!-- Edit Feature Modal -->
                    <div id="editFeature<?= $f['id'] ?>" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
                        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-lg mx-4">
                            <h3 class="text-lg font-bold text-white mb-4">Edit Feature</h3>
                            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="update_feature">
                                <input type="hidden" name="feature_id" value="<?= $f['id'] ?>">
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-slate-400 text-xs mb-1">Name</label>
                                        <input type="text" name="name" value="<?= htmlspecialchars($f['name']) ?>" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-slate-400 text-xs mb-1">Category</label>
                                        <select name="category" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                                            <option value="core" <?= $f['category'] === 'core' ? 'selected' : '' ?>>Core</option>
                                            <option value="add_on" <?= $f['category'] === 'add_on' ? 'selected' : '' ?>>Add-on</option>
                                            <option value="beta" <?= $f['category'] === 'beta' ? 'selected' : '' ?>>Beta</option>
                                            <option value="enterprise" <?= $f['category'] === 'enterprise' ? 'selected' : '' ?>>Enterprise</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-slate-400 text-xs mb-1">Default Limit</label>
                                            <input type="number" name="default_limit" value="<?= $f['default_limit'] ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-slate-400 text-xs mb-1">Unit</label>
                                            <input type="text" name="default_unit" value="<?= htmlspecialchars($f['default_unit'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-slate-300 text-sm">
                                            <input type="checkbox" name="is_beta" value="1" <?= $f['is_beta'] ? 'checked' : '' ?>>
                                            Beta feature (not globally available)
                                        </label>
                                    </div>
                                    <div>
                                        <label class="block text-slate-400 text-xs mb-1">Description</label>
                                        <textarea name="description" rows="2" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm"><?= htmlspecialchars($f['description'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" onclick="closeModal('editFeature<?= $f['id'] ?>')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tenant Override Modal -->
                    <div id="overrideModal<?= $f['id'] ?>" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
                        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-lg mx-4">
                            <h3 class="text-lg font-bold text-white mb-4">Tenant Override: <?= htmlspecialchars($f['name']) ?></h3>
                            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="override_tenant">
                                <input type="hidden" name="feature_id" value="<?= $f['id'] ?>">
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-slate-400 text-xs mb-1">Tenant</label>
                                        <select name="tenant_id" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                                            <option value="">Select tenant...</option>
                                            <?php foreach ($tenants as $t): ?>
                                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['slug']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-slate-300 text-sm">
                                            <input type="checkbox" name="is_enabled" value="1" checked>
                                            Enabled
                                        </label>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-slate-400 text-xs mb-1">Limit</label>
                                            <input type="number" name="limit_value" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                                        </div>
                                        <div>
                                            <label class="flex items-center gap-2 text-slate-300 text-sm mt-6">
                                                <input type="checkbox" name="is_unlimited" value="1">
                                                Unlimited
                                            </label>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-slate-400 text-xs mb-1">Effective From</label>
                                            <input type="date" name="effective_from" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-slate-400 text-xs mb-1">Effective Until</label>
                                            <input type="date" name="effective_until" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-slate-400 text-xs mb-1">Reason</label>
                                        <textarea name="override_reason" rows="2" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm"></textarea>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" onclick="closeModal('overrideModal<?= $f['id'] ?>')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Apply Override</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Plan Features Mapping -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white">Plan Feature Mappings</h2>
            <button onclick="openModal('mapPlanModal')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-3 py-1.5 rounded-lg text-xs font-semibold">+ Map Feature</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="pb-2 pr-4">Plan</th>
                        <th class="pb-2 pr-4">Feature</th>
                        <th class="pb-2 pr-4">Limit</th>
                        <th class="pb-2 pr-4">Included</th>
                        <th class="pb-2 pr-4">Overage Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($planFeatures as $pf): ?>
                    <tr class="border-b border-slate-700/30">
                        <td class="py-2 pr-4 text-white"><?= htmlspecialchars($pf['plan_name']) ?></td>
                        <td class="py-2 pr-4 text-white"><?= htmlspecialchars($pf['feature_name']) ?></td>
                        <td class="py-2 pr-4 text-white"><?= $pf['is_unlimited'] ? 'Unlimited' : number_format($pf['limit_value'] ?? 0) ?></td>
                        <td class="py-2 pr-4">
                            <span class="px-2 py-0.5 rounded text-xs <?= $pf['is_included'] ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-500/20 text-slate-400' ?>">
                                <?= $pf['is_included'] ? 'Yes' : 'No' ?>
                            </span>
                        </td>
                        <td class="py-2 pr-4 text-slate-400"><?= $pf['overage_rate'] ? '$' . $pf['overage_rate'] . '/unit' : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Feature Modal -->
    <div id="createFeatureModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-lg mx-4">
            <h3 class="text-lg font-bold text-white mb-4">Add Platform Feature</h3>
            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="create_feature">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Slug *</label>
                            <input type="text" name="slug" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="api_access">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Name *</label>
                            <input type="text" name="name" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="API Access">
                        </div>
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Category</label>
                        <select name="category" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                            <option value="core">Core</option>
                            <option value="add_on">Add-on</option>
                            <option value="beta">Beta</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Default Limit</label>
                            <input type="number" name="default_limit" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Unit</label>
                            <input type="text" name="default_unit" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="calls/day">
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-slate-300 text-sm">
                            <input type="checkbox" name="is_beta" value="1">
                            Beta feature
                        </label>
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Description</label>
                        <textarea name="description" rows="2" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeModal('createFeatureModal')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Map Plan Feature Modal -->
    <div id="mapPlanModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-lg mx-4">
            <h3 class="text-lg font-bold text-white mb-4">Map Feature to Plan</h3>
            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="map_plan_feature">
                <div class="space-y-3">
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Plan</label>
                        <select name="plan_id" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                            <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Feature</label>
                        <select name="feature_id" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                            <?php foreach ($features as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Limit</label>
                            <input type="number" name="limit_value" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="flex items-center gap-2 text-slate-300 text-sm mt-6">
                                <input type="checkbox" name="is_unlimited" value="1"> Unlimited
                            </label>
                        </div>
                        <div>
                            <label class="flex items-center gap-2 text-slate-300 text-sm mt-6">
                                <input type="checkbox" name="is_included" value="1" checked> Included
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Overage Rate (per unit)</label>
                        <input type="number" step="0.0001" name="overage_rate" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="0.00">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeModal('mapPlanModal')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Map</button>
                </div>
            </form>
        </div>
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
document.querySelectorAll('[id$="Modal"]').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

