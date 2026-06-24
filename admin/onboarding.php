<?php
/**
 * Tenant Onboarding Dashboard
 * Track and manage tenant onboarding progress.
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
        if ($action === 'update_progress') {
            $meta = db_fetch_one("SELECT id FROM tenant_metadata WHERE tenant_id = ?", [$_POST['tenant_id']]);
            if ($meta) {
                db_update('tenant_metadata', [
                    'onboarding_progress' => (int) $_POST['onboarding_progress'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$meta['id']]);
            } else {
                db_insert('tenant_metadata', [
                    'tenant_id' => (int) $_POST['tenant_id'],
                    'onboarding_progress' => (int) $_POST['onboarding_progress'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $message = 'Onboarding progress updated.';
            $message_type = 'success';
        } elseif ($action === 'add_note') {
            db_insert('tenant_notes', [
                'tenant_id' => (int) $_POST['tenant_id'],
                'type' => 'onboarding',
                'note' => $_POST['note_text'],
                'created_by_admin_id' => $_SESSION['admin_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $message = 'Note added.';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

// ---- FILTERS ----
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// ---- STATS ----
$totalTenants = db_fetch_value("SELECT COUNT(*) FROM pos_tenants");
$onboardingComplete = db_fetch_value("SELECT COUNT(*) FROM tenant_metadata WHERE onboarding_progress >= 100");
$onboardingInProgress = db_fetch_value("SELECT COUNT(*) FROM tenant_metadata WHERE onboarding_progress > 0 AND onboarding_progress < 100");
$notStarted = db_fetch_value("SELECT COUNT(*) FROM pos_tenants WHERE id NOT IN (SELECT tenant_id FROM tenant_metadata WHERE onboarding_progress > 0)");

// ---- TENANT LIST ----
$where = ['1=1'];
$params = [];
if ($status_filter === 'complete') {
    $where[] = 'EXISTS (SELECT 1 FROM tenant_metadata m WHERE m.tenant_id = t.id AND m.onboarding_progress >= 100)';
} elseif ($status_filter === 'in_progress') {
    $where[] = 'EXISTS (SELECT 1 FROM tenant_metadata m WHERE m.tenant_id = t.id AND m.onboarding_progress > 0 AND m.onboarding_progress < 100)';
} elseif ($status_filter === 'not_started') {
    $where[] = 'NOT EXISTS (SELECT 1 FROM tenant_metadata m WHERE m.tenant_id = t.id AND m.onboarding_progress > 0)';
}
if ($search) {
    $where[] = '(t.name LIKE ? OR t.email LIKE ? OR t.slug LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$whereSql = implode(' AND ', $where);

$total = db_fetch_value("SELECT COUNT(*) FROM pos_tenants t WHERE {$whereSql}", $params);

$tenants = db_fetch_all(
    "SELECT t.id, t.name, t.slug, t.email, t.created_at, t.status,
            COALESCE(m.onboarding_progress, 0) as onboarding_progress,
            m.health_score, m.last_login_at, m.notes
     FROM pos_tenants t
     LEFT JOIN tenant_metadata m ON t.id = m.tenant_id
     WHERE {$whereSql}
     ORDER BY t.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

$current_page = 'companies';
$page_title = 'Tenant Onboarding';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Tenant Onboarding</h1>
            <p class="text-slate-400 text-sm mt-1">Track new tenant setup and activation progress</p>
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
            <p class="text-slate-400 text-xs mb-1">Total Tenants</p>
            <p class="text-white font-semibold text-lg"><?= $totalTenants ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Complete</p>
            <p class="text-emerald-400 font-semibold text-lg"><?= $onboardingComplete ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">In Progress</p>
            <p class="text-amber-400 font-semibold text-lg"><?= $onboardingInProgress ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Not Started</p>
            <p class="text-slate-400 font-semibold text-lg"><?= $notStarted ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tenants..." class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm flex-1 min-w-[200px]">
            <select name="status" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Status</option>
                <option value="complete" <?= $status_filter === 'complete' ? 'selected' : '' ?>>Complete</option>
                <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="not_started" <?= $status_filter === 'not_started' ? 'selected' : '' ?>>Not Started</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
            <a href="onboarding.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <!-- Tenants Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="px-3 py-2.5">Tenant</th>
                        <th class="px-3 py-2.5">Progress</th>
                        <th class="px-3 py-2.5">Health</th>
                        <th class="px-3 py-2.5">Last Login</th>
                        <th class="px-3 py-2.5">Created</th>
                        <th class="px-3 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tenants)): ?>
                    <tr><td colspan="6" class="py-12 text-center text-slate-500"><i class="fas fa-users text-3xl mb-3 block"></i>No tenants found</td></tr>
                    <?php else: foreach ($tenants as $tn): ?>
                    <?php
                        $progress = (int) $tn['onboarding_progress'];
                        $progressColor = $progress >= 100 ? 'bg-emerald-500' : ($progress >= 50 ? 'bg-amber-500' : 'bg-red-500');
                        $health = (float) ($tn['health_score'] ?? 1);
                        $healthPct = (int) ($health * 100);
                        $healthColor = $health >= 0.7 ? 'text-emerald-400' : ($health >= 0.4 ? 'text-yellow-400' : 'text-red-400');
                    ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5">
                            <a href="tenant_detail.php?id=<?= $tn['id'] ?>" class="text-white hover:text-amber-400 transition text-sm font-medium">
                                <?= htmlspecialchars($tn['name']) ?>
                            </a>
                            <p class="text-slate-500 text-xs"><?= htmlspecialchars($tn['slug']) ?> • <?= htmlspecialchars($tn['email'] ?? '') ?></p>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="w-24 bg-slate-700 rounded-full h-2">
                                    <div class="h-2 rounded-full <?= $progressColor ?>" style="width:<?= $progress ?>"></div>
                                </div>
                                <span class="text-white text-xs"><?= $progress ?>%</span>
                            </div>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="text-xs <?= $healthColor ?> font-medium"><?= $healthPct ?>/100</span>
                        </td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs">
                            <?= $tn['last_login_at'] ? date('M j, g:i a', strtotime($tn['last_login_at'])) : 'Never' ?>
                        </td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= date('M j, Y', strtotime($tn['created_at'])) ?></td>
                        <td class="px-3 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="openModal('progressModal<?= $tn['id'] ?>')" class="w-8 h-8 bg-blue-500/20 rounded-lg flex items-center justify-center text-blue-400 hover:bg-blue-500/30" title="Update Progress"><i class="fas fa-edit text-xs"></i></button>
                                <button onclick="openModal('noteModal<?= $tn['id'] ?>')" class="w-8 h-8 bg-indigo-500/20 rounded-lg flex items-center justify-center text-indigo-400 hover:bg-indigo-500/30" title="Add Note"><i class="fas fa-sticky-note text-xs"></i></button>
                            </div>
                        </td>
                    </tr>

                    <!-- Update Progress Modal -->
                    <div id="progressModal<?= $tn['id'] ?>" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
                        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-sm mx-4">
                            <h3 class="text-lg font-bold text-white mb-4">Update Onboarding</h3>
                            <p class="text-slate-400 text-sm mb-4"><?= htmlspecialchars($tn['name']) ?></p>
                            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="update_progress">
                                <input type="hidden" name="tenant_id" value="<?= $tn['id'] ?>">
                                <div class="mb-4">
                                    <label class="block text-slate-400 text-xs mb-1">Progress (%)</label>
                                    <input type="range" name="onboarding_progress" min="0" max="100" value="<?= $progress ?>" class="w-full accent-amber-500" oninput="document.getElementById('progressVal<?= $tn['id'] ?>').innerText = this.value + '%'">
                                    <p id="progressVal<?= $tn['id'] ?>" class="text-white text-sm text-center mt-1"><?= $progress ?>%</p>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="closeModal('progressModal<?= $tn['id'] ?>')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Add Note Modal -->
                    <div id="noteModal<?= $tn['id'] ?>" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
                        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-sm mx-4">
                            <h3 class="text-lg font-bold text-white mb-4">Add Note</h3>
                            <p class="text-slate-400 text-sm mb-4"><?= htmlspecialchars($tn['name']) ?></p>
                            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="add_note">
                                <input type="hidden" name="tenant_id" value="<?= $tn['id'] ?>">
                                <div class="mb-4">
                                    <textarea name="note_text" rows="3" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="Onboarding note..." required></textarea>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="closeModal('noteModal<?= $tn['id'] ?>')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Add Note</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php $totalPages = ceil($total / $per_page); if ($totalPages > 1): ?>
        <div class="px-5 py-4 border-t border-slate-700/60 flex items-center justify-between">
            <p class="text-slate-400 text-xs">Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?></p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
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

