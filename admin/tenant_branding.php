<?php
/**
 * Tenant Branding / White Label Manager
 * Manage per-tenant white labeling settings.
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
        $tenant_id = (int) ($_POST['tenant_id'] ?? 0);

        if ($action === 'save_branding') {
            $existing = db_fetch_one("SELECT id FROM tenant_branding WHERE tenant_id = ?", [$tenant_id]);
            $data = [
                'primary_color' => $_POST['primary_color'] ?: null,
                'secondary_color' => $_POST['secondary_color'] ?: null,
                'accent_color' => $_POST['accent_color'] ?: null,
                'logo_url' => $_POST['logo_url'] ?: null,
                'favicon_url' => $_POST['favicon_url'] ?: null,
                'login_background_url' => $_POST['login_background_url'] ?: null,
                'custom_css' => $_POST['custom_css'] ?: null,
                'custom_domain' => $_POST['custom_domain'] ?: null,
                'email_sender_name' => $_POST['email_sender_name'] ?: null,
                'email_sender_address' => $_POST['email_sender_address'] ?: null,
                'is_white_labeled' => !empty($_POST['is_white_labeled']) ? 1 : 0,
                'hide_powered_by' => !empty($_POST['hide_powered_by']) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($existing) {
                db_update('tenant_branding', $data, 'id = ?', [$existing['id']]);
            } else {
                $data['tenant_id'] = $tenant_id;
                $data['created_at'] = date('Y-m-d H:i:s');
                db_insert('tenant_branding', $data);
            }
            $message = 'Branding saved.';
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
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = ['1=1'];
$params = [];
if ($search) {
    $where[] = '(t.name LIKE ? OR t.slug LIKE ? OR b.custom_domain LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$whereSql = implode(' AND ', $where);

$total = db_fetch_value("SELECT COUNT(*) FROM pos_tenants t WHERE {$whereSql}", $params);

$tenants = db_fetch_all(
    "SELECT t.id, t.name, t.slug, t.subdomain,
            b.id as branding_id, b.primary_color, b.secondary_color, b.accent_color,
            b.logo_url, b.custom_domain, b.is_white_labeled, b.hide_powered_by, b.updated_at
     FROM pos_tenants t
     LEFT JOIN tenant_branding b ON t.id = b.tenant_id
     WHERE {$whereSql}
     ORDER BY b.is_white_labeled DESC, t.name ASC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

$current_page = 'settings';
$page_title = 'Tenant Branding';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Tenant Branding</h1>
            <p class="text-slate-400 text-sm mt-1">White-label configuration per tenant</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-emerald-500/20 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/20 border-red-500/30 text-red-400' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" class="flex gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tenants..." class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm flex-1 min-w-[200px]">
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Search</button>
            <a href="tenant_branding.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <!-- Tenants Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($tenants as $t): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="text-white font-semibold text-sm"><?= htmlspecialchars($t['name']) ?></h3>
                    <p class="text-slate-500 text-xs"><?= htmlspecialchars($t['slug']) ?> · <?= htmlspecialchars($t['subdomain'] ?? '') ?></p>
                </div>
                <?php if ($t['is_white_labeled']): ?>
                <span class="px-2 py-0.5 rounded text-[10px] bg-amber-500/20 text-amber-400">White Label</span>
                <?php endif; ?>
            </div>

            <?php if ($t['logo_url']): ?>
            <div class="mb-3 p-2 bg-slate-800/50 rounded-lg flex items-center justify-center h-12">
                <img src="<?= htmlspecialchars($t['logo_url']) ?>" alt="Logo" class="max-h-full max-w-full object-contain">
            </div>
            <?php endif; ?>

            <div class="flex items-center gap-2 mb-3">
                <?php if ($t['primary_color']): ?>
                <div class="w-6 h-6 rounded border border-slate-700/70" style="background-color:<?= htmlspecialchars($t['primary_color']) ?>"></div>
                <?php endif; ?>
                <?php if ($t['secondary_color']): ?>
                <div class="w-6 h-6 rounded border border-slate-700/70" style="background-color:<?= htmlspecialchars($t['secondary_color']) ?>"></div>
                <?php endif; ?>
                <?php if ($t['accent_color']): ?>
                <div class="w-6 h-6 rounded border border-slate-700/70" style="background-color:<?= htmlspecialchars($t['accent_color']) ?>"></div>
                <?php endif; ?>
                <?php if (!$t['primary_color'] && !$t['secondary_color'] && !$t['accent_color']): ?>
                <span class="text-slate-500 text-xs">No colors configured</span>
                <?php endif; ?>
            </div>

            <?php if ($t['custom_domain']): ?>
            <p class="text-slate-400 text-xs mb-2"><i class="fas fa-globe mr-1"></i><?= htmlspecialchars($t['custom_domain']) ?></p>
            <?php endif; ?>

            <div class="flex items-center justify-between pt-3 border-t border-slate-700/60">
                <span class="text-slate-500 text-xs"><?= $t['updated_at'] ? 'Updated ' . date('M j', strtotime($t['updated_at'])) : 'Default branding' ?></span>
                <button onclick="openModal('brandModal<?= $t['id'] ?>')" class="px-3 py-1.5 bg-blue-500/20 rounded text-blue-400 text-xs hover:bg-blue-500/30">Edit</button>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="brandModal<?= $t['id'] ?>" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold text-white mb-4">Edit Branding: <?= htmlspecialchars($t['name']) ?></h3>
                <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="save_branding">
                    <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">

                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-slate-400 text-xs mb-1">Primary</label>
                                <input type="color" name="primary_color" value="<?= htmlspecialchars($t['primary_color'] ?? '#F59E0B') ?>" class="w-full h-8 rounded cursor-pointer bg-transparent border-0">
                            </div>
                            <div>
                                <label class="block text-slate-400 text-xs mb-1">Secondary</label>
                                <input type="color" name="secondary_color" value="<?= htmlspecialchars($t['secondary_color'] ?? '#1F2937') ?>" class="w-full h-8 rounded cursor-pointer bg-transparent border-0">
                            </div>
                            <div>
                                <label class="block text-slate-400 text-xs mb-1">Accent</label>
                                <input type="color" name="accent_color" value="<?= htmlspecialchars($t['accent_color'] ?? '#10B981') ?>" class="w-full h-8 rounded cursor-pointer bg-transparent border-0">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Logo URL</label>
                            <input type="url" name="logo_url" value="<?= htmlspecialchars($t['logo_url'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="https://...">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Favicon URL</label>
                            <input type="url" name="favicon_url" value="<?= htmlspecialchars($t['favicon_url'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="https://...">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Login Background URL</label>
                            <input type="url" name="login_background_url" value="<?= htmlspecialchars($t['login_background_url'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="https://...">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Custom Domain</label>
                            <input type="text" name="custom_domain" value="<?= htmlspecialchars($t['custom_domain'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="pos.client.com">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Email Sender Name</label>
                            <input type="text" name="email_sender_name" value="<?= htmlspecialchars($t['email_sender_name'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Email Sender Address</label>
                            <input type="email" name="email_sender_address" value="<?= htmlspecialchars($t['email_sender_address'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Custom CSS</label>
                            <textarea name="custom_css" rows="3" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm font-mono" placeholder="/* Custom styles */"><?= htmlspecialchars($t['custom_css'] ?? '') ?></textarea>
                        </div>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 text-slate-300 text-sm">
                                <input type="checkbox" name="is_white_labeled" value="1" <?= $t['is_white_labeled'] ? 'checked' : '' ?>>
                                White Label Enabled
                            </label>
                            <label class="flex items-center gap-2 text-slate-300 text-sm">
                                <input type="checkbox" name="hide_powered_by" value="1" <?= $t['hide_powered_by'] ? 'checked' : '' ?>>
                                Hide "Powered By"
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" onclick="closeModal('brandModal<?= $t['id'] ?>')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Save Branding</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php $totalPages = ceil($total / $per_page); if ($totalPages > 1): ?>
    <div class="mt-6 flex items-center justify-between">
        <p class="text-slate-400 text-xs">Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?></p>
        <div class="flex gap-2">
            <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
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

