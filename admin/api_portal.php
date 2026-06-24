<?php
/**
 * API Rate Limits & Developer Portal
 * Manage tenant API keys and view rate limit violations.
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
        if ($action === 'generate_key') {
            $tenant_id = (int) $_POST['tenant_id'];
            $key = 'jsk_live_' . bin2hex(random_bytes(24));
            $secret = 'jss_' . bin2hex(random_bytes(32));
            db_insert('tenant_api_keys', [
                'tenant_id' => $tenant_id,
                'name' => $_POST['name'] ?? 'Default',
                'api_key' => $key,
                'api_secret' => password_hash($secret, PASSWORD_DEFAULT),
                'rate_limit' => (int) ($_POST['rate_limit'] ?? 1000),
                'permissions' => json_encode(explode(',', $_POST['permissions'] ?? 'read')),
                'expires_at' => $_POST['expires_at'] ?: null,
                'created_by_admin_id' => $_SESSION['admin_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $message = "API Key created. Secret (show once): {$secret}";
            $message_type = 'success';
        } elseif ($action === 'revoke_key') {
            db_update('tenant_api_keys', [
                'status' => 'revoked',
                'revoked_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $_POST['key_id']]);
            $message = 'API Key revoked.';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

// ---- FILTERS ----
$search = $_GET['search'] ?? '';
$tab = $_GET['tab'] ?? 'keys';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// ---- STATS ----
$totalKeys = db_fetch_value("SELECT COUNT(*) FROM tenant_api_keys");
$activeKeys = db_fetch_value("SELECT COUNT(*) FROM tenant_api_keys WHERE status = 'active'");
$totalViolations = db_fetch_value("SELECT COUNT(*) FROM api_rate_limit_violations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$totalRequests = db_fetch_value("SELECT COALESCE(SUM(request_count), 0) FROM tenant_api_keys");

$tenants = db_fetch_all("SELECT id, name, slug FROM pos_tenants ORDER BY name ASC LIMIT 50");

if ($tab === 'keys') {
    $where = ['1=1'];
    $params = [];
    if ($search) {
        $where[] = '(t.name LIKE ? OR k.name LIKE ? OR k.api_key LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    $whereSql = implode(' AND ', $where);
    $total = db_fetch_value("SELECT COUNT(*) FROM tenant_api_keys k JOIN pos_tenants t ON k.tenant_id = t.id WHERE {$whereSql}", $params);
    $items = db_fetch_all(
        "SELECT k.*, t.name as tenant_name, t.slug
         FROM tenant_api_keys k
         JOIN pos_tenants t ON k.tenant_id = t.id
         WHERE {$whereSql}
         ORDER BY k.created_at DESC
         LIMIT {$per_page} OFFSET {$offset}",
        $params
    );
} else {
    $where = ['1=1'];
    $params = [];
    if ($search) {
        $where[] = '(t.name LIKE ? OR v.ip_address LIKE ? OR v.endpoint LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    $whereSql = implode(' AND ', $where);
    $total = db_fetch_value("SELECT COUNT(*) FROM api_rate_limit_violations v JOIN pos_tenants t ON v.tenant_id = t.id WHERE {$whereSql}", $params);
    $items = db_fetch_all(
        "SELECT v.*, t.name as tenant_name, t.slug
         FROM api_rate_limit_violations v
         JOIN pos_tenants t ON v.tenant_id = t.id
         WHERE {$whereSql}
         ORDER BY v.created_at DESC
         LIMIT {$per_page} OFFSET {$offset}",
        $params
    );
}

$current_page = 'settings';
$page_title = 'Developer Portal';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Developer Portal</h1>
            <p class="text-slate-400 text-sm mt-1">API keys, rate limits, and violations</p>
        </div>
        <?php if ($tab === 'keys'): ?>
        <button onclick="openModal('keyModal')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold"><i class="fas fa-plus mr-1"></i> New API Key</button>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-emerald-500/20 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/20 border-red-500/30 text-red-400' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Total Keys</p>
            <p class="text-white font-semibold text-lg"><?= $totalKeys ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Active Keys</p>
            <p class="text-emerald-400 font-semibold text-lg"><?= $activeKeys ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Violations (7d)</p>
            <p class="text-rose-400 font-semibold text-lg"><?= $totalViolations ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Total Requests</p>
            <p class="text-blue-400 font-semibold text-lg"><?= number_format($totalRequests) ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-4 border-b border-slate-700/60 pb-2">
        <a href="?tab=keys" class="px-4 py-2 text-sm rounded-lg transition <?= $tab === 'keys' ? 'bg-amber-500/20 text-amber-400 font-medium' : 'text-slate-400 hover:text-white' ?>">API Keys</a>
        <a href="?tab=violations" class="px-4 py-2 text-sm rounded-lg transition <?= $tab === 'violations' ? 'bg-amber-500/20 text-amber-400 font-medium' : 'text-slate-400 hover:text-white' ?>">Rate Limit Violations</a>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" class="flex gap-3">
            <input type="hidden" name="tab" value="<?= $tab ?>">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm flex-1 min-w-[200px]">
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Search</button>
            <a href="api_portal.php?tab=<?= $tab ?>" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <?php if ($tab === 'keys'): ?>
                        <th class="px-3 py-2.5">Tenant</th>
                        <th class="px-3 py-2.5">Name</th>
                        <th class="px-3 py-2.5">API Key</th>
                        <th class="px-3 py-2.5 text-right">Rate Limit</th>
                        <th class="px-3 py-2.5 text-right">Requests</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5 text-right">Actions</th>
                        <?php else: ?>
                        <th class="px-3 py-2.5">Tenant</th>
                        <th class="px-3 py-2.5">IP</th>
                        <th class="px-3 py-2.5">Endpoint</th>
                        <th class="px-3 py-2.5 text-right">Requests</th>
                        <th class="px-3 py-2.5 text-right">Limit</th>
                        <th class="px-3 py-2.5">Blocked</th>
                        <th class="px-3 py-2.5">Time</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="7" class="py-12 text-center text-slate-500"><i class="fas fa-key text-3xl mb-3 block"></i>No records found</td></tr>
                    <?php else: foreach ($items as $it): ?>
                    <?php if ($tab === 'keys'):
                        $statusColor = match($it['status']) {
                            'active' => 'bg-emerald-500/20 text-emerald-400',
                            'revoked' => 'bg-red-500/20 text-red-400',
                            'expired' => 'bg-slate-500/20 text-slate-400',
                            default => 'bg-slate-500/20 text-slate-400',
                        };
                    ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5">
                            <a href="tenant_detail.php?id=<?= $it['tenant_id'] ?>" class="text-white hover:text-amber-400 transition text-sm"><?= htmlspecialchars($it['tenant_name']) ?></a>
                        </td>
                        <td class="px-3 py-2.5 text-slate-300 text-xs"><?= htmlspecialchars($it['name']) ?></td>
                        <td class="px-3 py-2.5">
                            <code class="text-xs text-slate-400 font-mono bg-slate-800/50 px-2 py-1 rounded"><?= substr($it['api_key'], 0, 12) ?>...</code>
                        </td>
                        <td class="px-3 py-2.5 text-right text-slate-300 text-xs"><?= number_format($it['rate_limit']) ?>/min</td>
                        <td class="px-3 py-2.5 text-right text-slate-300 text-xs"><?= number_format($it['request_count'] ?? 0) ?></td>
                        <td class="px-3 py-2.5"><span class="px-2 py-0.5 rounded text-[10px] <?= $statusColor ?>"><?= ucfirst($it['status']) ?></span></td>
                        <td class="px-3 py-2.5 text-right">
                            <?php if ($it['status'] === 'active'): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Revoke this key?')">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="revoke_key">
                                <input type="hidden" name="key_id" value="<?= $it['id'] ?>">
                                <button type="submit" class="px-3 py-1 bg-red-500/20 rounded text-red-400 text-xs hover:bg-red-500/30">Revoke</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5">
                            <a href="tenant_detail.php?id=<?= $it['tenant_id'] ?>" class="text-white hover:text-amber-400 transition text-sm"><?= htmlspecialchars($it['tenant_name']) ?></a>
                        </td>
                        <td class="px-3 py-2.5 text-slate-300 text-xs font-mono"><?= htmlspecialchars($it['ip_address'] ?? 'N/A') ?></td>
                        <td class="px-3 py-2.5 text-slate-300 text-xs"><?= htmlspecialchars($it['endpoint'] ?? '/') ?></td>
                        <td class="px-3 py-2.5 text-right text-rose-400 text-xs font-medium"><?= number_format($it['requests_made'] ?? 0) ?></td>
                        <td class="px-3 py-2.5 text-right text-slate-300 text-xs"><?= number_format($it['rate_limit'] ?? 0) ?></td>
                        <td class="px-3 py-2.5">
                            <?php if (!empty($it['was_blocked'])): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-red-500/20 text-red-400">Blocked</span>
                            <?php else: ?>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-amber-500/20 text-amber-400">Throttled</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= date('M j, g:i a', strtotime($it['created_at'])) ?></td>
                    </tr>
                    <?php endif; endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php $totalPages = ceil($total / $per_page); if ($totalPages > 1): ?>
        <div class="px-5 py-4 border-t border-slate-700/60 flex items-center justify-between">
            <p class="text-slate-400 text-xs">Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?></p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&tab=<?= $tab ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&tab=<?= $tab ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&tab=<?= $tab ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Generate Key Modal -->
<div id="keyModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-sm mx-4">
        <h3 class="text-lg font-bold text-white mb-4">Generate API Key</h3>
        <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="generate_key">
            <div class="space-y-3">
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Tenant</label>
                    <select name="tenant_id" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        <option value="">Select tenant...</option>
                        <?php foreach ($tenants as $tn): ?>
                        <option value="<?= $tn['id'] ?>"><?= htmlspecialchars($tn['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Key Name</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="Production Key">
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Rate Limit (req/min)</label>
                    <input type="number" name="rate_limit" value="1000" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Permissions (comma-separated)</label>
                    <input type="text" name="permissions" value="read,write" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Expires At</label>
                    <input type="date" name="expires_at" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeModal('keyModal')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Generate</button>
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
document.querySelectorAll('[id$="Modal"]').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

