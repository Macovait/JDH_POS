<?php
/**
 * User Details - Standalone Page
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('users.manage') && !is_super_admin()) {
    enforce_permission('users.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$current_user_id = get_current_user_id();

if (empty($tenant_id)) {
    header('Location: users.php?error=' . urlencode('Tenant context required'));
    exit;
}

$target_user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($target_user_id <= 0) {
    header('Location: users.php?error=' . urlencode('Invalid user ID'));
    exit;
}

// Fetch user info with role
$user = null;
$role_name = 'Unknown';
try {
    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? AND (u.tenant_id = ? OR u.tenant_id IS NULL)");
    $stmt->execute([$target_user_id, $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $role_name = $user['role_name'] ?? 'Unknown';
    }
} catch (Exception $e) {
    error_log("Error fetching user: " . $e->getMessage());
}

if (!$user) {
    header('Location: users.php?error=' . urlencode('User not found'));
    exit;
}

$is_online = false;
if (!empty($user['last_login'])) {
    $last_login_time = strtotime($user['last_login']);
    $is_online = (time() - $last_login_time) < 300;
}

$page_title = 'Details: ' . htmlspecialchars($user['name']);
ob_start();
?>

<div class="space-y-4" id="user-details-content">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Users</p>
            <h1 class="text-lg font-bold text-white">User Details</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="users.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-arrow-left text-xs"></i> Back</a>
            <a href="user_form.php?id=<?php echo $target_user_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors"><i class="fas fa-pen text-xs"></i> Edit</a>
            <a href="user_activity.php?id=<?php echo $target_user_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cyan-500/15 border border-cyan-500/40 text-cyan-400 text-sm font-medium hover:bg-cyan-500/25 transition-colors"><i class="fas fa-history text-xs"></i> Activity</a>
        </div>
    </div>

    <!-- Profile Card -->
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-5">
        <div class="flex items-center gap-4 mb-5">
            <div class="w-14 h-14 rounded-full bg-amber-500/15 flex items-center justify-center text-amber-400 font-bold text-lg ring-1 ring-amber-500/30">
                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
            </div>
            <div>
                <p class="text-base font-semibold text-white"><?php echo htmlspecialchars($user['name']); ?></p>
                <p class="text-xs text-slate-400">@<?php echo htmlspecialchars($user['username']); ?></p>
                <p class="text-xs mt-0.5"><?php echo $is_online ? '<span class="text-emerald-400"><i class="fas fa-circle text-[8px] mr-1"></i>Online</span>' : '<span class="text-slate-500"><i class="fas fa-circle text-[8px] mr-1"></i>Offline</span>'; ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Email</p>
                <p class="text-white"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Role</p>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-500/15 text-blue-400 ring-1 ring-blue-500/30"><?php echo htmlspecialchars($role_name); ?></span>
            </div>
            <?php if (!empty($user['phone'])): ?>
            <div>
                <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Phone</p>
                <p class="text-white"><?php echo htmlspecialchars($user['phone']); ?></p>
            </div>
            <?php endif; ?>
            <div>
                <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Status</p>
                <p class="text-white"><?php echo ($user['is_active'] ?? 0) ? '<span class="text-emerald-400"><i class="fas fa-check-circle mr-1"></i>Active</span>' : '<span class="text-red-400"><i class="fas fa-ban mr-1"></i>Inactive</span>'; ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Created</p>
                <p class="text-white"><?php echo !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A'; ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Last Login</p>
                <p class="text-white"><?php echo !empty($user['last_login']) ? date('M d, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Login Count</p>
                <p class="text-white"><?php echo $user['login_count'] ?? 0; ?></p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-4">
        <h3 class="text-sm font-semibold text-white mb-3">Quick Actions</h3>
        <div class="flex flex-wrap gap-2">
            <a href="user_form.php?duplicate_from=<?php echo $target_user_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cyan-500/15 border border-cyan-500/40 text-cyan-400 text-sm font-medium hover:bg-cyan-500/25 transition-colors"><i class="fas fa-copy text-xs"></i> Duplicate</a>
            <a href="reset_password.php?id=<?php echo $target_user_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors"><i class="fas fa-key text-xs"></i> Reset Password</a>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
