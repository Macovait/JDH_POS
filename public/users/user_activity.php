<?php
/**
 * User Activity Log - Standalone Page
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

// Fetch user info
$user = null;
try {
    $stmt = $pdo->prepare("SELECT id, name, email, username, role_id, is_active, created_at, last_login FROM users WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL");
    $stmt->execute([$target_user_id, $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user: " . $e->getMessage());
}

if (!$user) {
    header('Location: users.php?error=' . urlencode('User not found'));
    exit;
}

// Fetch role name
$role_name = 'Unknown';
try {
    $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
    $stmt->execute([$user['role_id']]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($role) $role_name = $role['name'];
} catch (Exception $e) {}

// Fetch activity logs
$activities = [];
try {
    $sql = "SELECT action, meta, created_at FROM activity_logs WHERE user_id = ?";
    $params = [$target_user_id];
    if ($tenant_id) {
        $sql .= " AND tenant_id = ?";
        $params[] = $tenant_id;
    }
    $sql .= " ORDER BY created_at DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching activity: " . $e->getMessage());
}

$page_title = 'Activity: ' . htmlspecialchars($user['name']);
ob_start();
?>

<div class="space-y-4" id="user-activity-content">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Users</p>
            <h1 class="text-lg font-bold text-white">Activity Log</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="users.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-arrow-left text-xs"></i> Back</a>
            <a href="user_details.php?id=<?php echo $target_user_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-user text-xs"></i> View Details</a>
            <a href="user_form.php?id=<?php echo $target_user_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors"><i class="fas fa-pen text-xs"></i> Edit</a>
        </div>
    </div>

    <!-- User Card -->
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-amber-500/15 flex items-center justify-center text-amber-400 font-bold text-sm ring-1 ring-amber-500/30">
                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
            </div>
            <div>
                <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($user['name']); ?></p>
                <p class="text-xs text-slate-400">@<?php echo htmlspecialchars($user['username']); ?> · <?php echo htmlspecialchars($role_name); ?></p>
            </div>
        </div>
    </div>

    <!-- Activity List -->
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-700/60 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white"><i class="fas fa-history text-cyan-400 text-xs mr-1.5"></i>Recent Activity</h3>
            <span class="text-xs text-slate-500"><?php echo count($activities); ?> entries</span>
        </div>
        <div class="divide-y divide-slate-700/30">
            <?php if (empty($activities)): ?>
                <div class="px-4 py-8 text-center text-slate-500">
                    <i class="fas fa-history text-2xl mb-2 block opacity-40"></i>
                    <p class="text-sm">No activity found for this user.</p>
                </div>
            <?php else: ?>
                <?php foreach ($activities as $activity):
                    $meta = json_decode($activity['meta'] ?? '{}', true);
                    $description = $meta['description'] ?? '';
                    $action = htmlspecialchars($activity['action']);
                    $time = $activity['created_at'] ? date('M d, Y \a\t g:i A', strtotime($activity['created_at'])) : 'Unknown';
                ?>
                <div class="px-4 py-3 hover:bg-slate-700/20 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-300 font-medium"><?php echo $action; ?></p>
                            <?php if ($description): ?>
                                <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($description); ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs text-slate-500 whitespace-nowrap shrink-0"><?php echo $time; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
