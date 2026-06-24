<?php
/**
 * Email Templates Manager
 * Manage platform email templates for automated communications.
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
            case 'create':
                db_insert('email_templates', [
                    'slug' => $_POST['slug'],
                    'name' => $_POST['name'],
                    'subject' => $_POST['subject'],
                    'body_html' => $_POST['body_html'],
                    'body_text' => $_POST['body_text'] ?: null,
                    'from_name' => $_POST['from_name'] ?: null,
                    'from_email' => $_POST['from_email'] ?: null,
                    'category' => $_POST['category'],
                    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                    'is_system' => 0,
                    'variables' => $_POST['variables'] ?: null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $message = 'Template created.';
                $message_type = 'success';
                break;
            case 'update':
                db_update('email_templates', [
                    'name' => $_POST['name'],
                    'subject' => $_POST['subject'],
                    'body_html' => $_POST['body_html'],
                    'body_text' => $_POST['body_text'] ?: null,
                    'from_name' => $_POST['from_name'] ?: null,
                    'from_email' => $_POST['from_email'] ?: null,
                    'category' => $_POST['category'],
                    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                    'variables' => $_POST['variables'] ?: null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$_POST['template_id']]);
                $message = 'Template updated.';
                $message_type = 'success';
                break;
            case 'toggle':
                $current = db_fetch_one("SELECT is_active FROM email_templates WHERE id = ?", [$_POST['template_id']]);
                db_update('email_templates', ['is_active' => $current['is_active'] ? 0 : 1, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$_POST['template_id']]);
                $message = 'Template toggled.';
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

$category_filter = $_GET['category'] ?? '';
$where = ['1=1'];
$params = [];
if ($category_filter) {
    $where[] = 'category = ?';
    $params[] = $category_filter;
}
$whereSql = implode(' AND ', $where);

$templates = db_fetch_all("SELECT * FROM email_templates WHERE {$whereSql} ORDER BY category, name", $params);
$categories = db_fetch_all("SELECT DISTINCT category FROM email_templates ORDER BY category");

$current_page = 'settings';
$page_title = 'Email Templates';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Email Templates</h1>
            <p class="text-slate-400 text-sm mt-1">Manage automated platform emails</p>
        </div>
        <button onclick="openModal('createModal')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">
            <i class="fas fa-plus mr-1"></i> New Template
        </button>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-emerald-500/20 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/20 border-red-500/30 text-red-400' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" class="flex gap-3">
            <select name="category" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= htmlspecialchars($c['category']) ?>" <?= $category_filter === $c['category'] ? 'selected' : '' ?>><?= ucfirst(htmlspecialchars($c['category'])) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
            <a href="email_templates.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <!-- Templates Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($templates as $tmpl): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 flex flex-col <?= $tmpl['is_active'] ? '' : 'opacity-60' ?>">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <span class="px-2 py-0.5 rounded text-[10px] bg-slate-700/50 text-slate-400"><?= ucfirst($tmpl['category']) ?></span>
                    <?php if ($tmpl['is_system']): ?><span class="px-2 py-0.5 rounded text-[10px] bg-amber-500/20 text-amber-400 ml-1">System</span><?php endif; ?>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] <?= $tmpl['is_active'] ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-500/20 text-slate-400' ?>">
                    <?= $tmpl['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
            </div>
            <h3 class="text-white font-semibold mb-1"><?= htmlspecialchars($tmpl['name']) ?></h3>
            <p class="text-slate-500 text-xs font-mono mb-2"><?= htmlspecialchars($tmpl['slug']) ?></p>
            <p class="text-slate-400 text-sm mb-1 truncate">Subject: <?= htmlspecialchars($tmpl['subject']) ?></p>
            <?php if ($tmpl['variables']): ?>
            <p class="text-slate-500 text-xs mb-3">Vars: <?= htmlspecialchars($tmpl['variables']) ?></p>
            <?php endif; ?>
            <div class="mt-auto flex items-center gap-2 pt-3 border-t border-slate-700/60">
                <button onclick="openModal('editModal<?= $tmpl['id'] ?>')" class="flex-1 px-3 py-1.5 bg-blue-500/20 rounded text-blue-400 text-xs hover:bg-blue-500/30">Edit</button>
                <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="template_id" value="<?= $tmpl['id'] ?>">
                    <button type="submit" class="px-3 py-1.5 bg-slate-700/50 rounded text-slate-300 text-xs hover:bg-slate-700/70">
                        <?= $tmpl['is_active'] ? 'Disable' : 'Enable' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="editModal<?= $tmpl['id'] ?>" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold text-white mb-4">Edit Template: <?= htmlspecialchars($tmpl['name']) ?></h3>
                <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="template_id" value="<?= $tmpl['id'] ?>">
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-400 text-xs mb-1">Name</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($tmpl['name']) ?>" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-slate-400 text-xs mb-1">Category</label>
                                <input type="text" name="category" value="<?= htmlspecialchars($tmpl['category']) ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Subject</label>
                            <input type="text" name="subject" value="<?= htmlspecialchars($tmpl['subject']) ?>" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-400 text-xs mb-1">From Name</label>
                                <input type="text" name="from_name" value="<?= htmlspecialchars($tmpl['from_name'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-slate-400 text-xs mb-1">From Email</label>
                                <input type="email" name="from_email" value="<?= htmlspecialchars($tmpl['from_email'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">HTML Body</label>
                            <textarea name="body_html" rows="6" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm font-mono"><?= htmlspecialchars($tmpl['body_html'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Plain Text Body (optional)</label>
                            <textarea name="body_text" rows="3" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm font-mono"><?= htmlspecialchars($tmpl['body_text'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Variables (comma-separated)</label>
                            <input type="text" name="variables" value="<?= htmlspecialchars($tmpl['variables'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="flex items-center gap-2 text-slate-300 text-sm">
                                <input type="checkbox" name="is_active" value="1" <?= $tmpl['is_active'] ? 'checked' : '' ?>>
                                Active
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" onclick="closeModal('editModal<?= $tmpl['id'] ?>')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Save</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Create Modal -->
    <div id="createModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-bold text-white mb-4">New Email Template</h3>
            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="create">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Slug *</label>
                            <input type="text" name="slug" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="trial_reminder">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Name *</label>
                            <input type="text" name="name" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="Trial Reminder">
                        </div>
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Subject *</label>
                        <input type="text" name="subject" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="Your trial ends soon">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">From Name</label>
                            <input type="text" name="from_name" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">From Email</label>
                            <input type="email" name="from_email" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">HTML Body</label>
                        <textarea name="body_html" rows="6" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm font-mono" placeholder="<p>Hello {{name}},</p>..."></textarea>
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Plain Text Body</label>
                        <textarea name="body_text" rows="3" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm font-mono"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Category</label>
                            <input type="text" name="category" value="transactional" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Variables</label>
                            <input type="text" name="variables" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="name, company, trial_end">
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-slate-300 text-sm">
                            <input type="checkbox" name="is_active" value="1" checked>
                            Active
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Create</button>
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

