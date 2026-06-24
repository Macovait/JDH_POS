<?php
require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();
safe_require('db.php', 'src', true);

$role_id = $_GET['id'] ?? 0;
$pdo = get_db_connection();
$role = null;
if ($role_id) {
    $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ?');
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
}
$page_title = $role ? 'Edit Role' : 'Create Role';
ob_start();
?>
<div class="space-y-4" id="role-page-content">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Role Management</p>
            <h1 class="text-lg font-bold text-white"><?php echo htmlspecialchars($page_title); ?></h1>
        </div>
        <div>
            <a href="roles.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-arrow-left text-xs"></i> Back</a>
        </div>
    </div>
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-4">
        <form method="POST" action="roles.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="action" value="save_role">
            <input type="hidden" name="id" value="<?php echo $role_id; ?>">
            <div class="mb-4">
                <label class="block text-xs font-medium text-[#9CA3AF] uppercase mb-1">Role Name <span class="text-red-400">*</span></label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($role['name'] ?? ''); ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:border-amber-400">
            </div>
            <div class="mb-6">
                <label class="block text-xs font-medium text-[#9CA3AF] uppercase mb-1">Description</label>
                <input type="text" name="description" value="<?php echo htmlspecialchars($role['description'] ?? ''); ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:border-amber-400">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors"><i class="fas fa-save text-xs"></i> Save Role</button>
                <a href="roles.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
