<?php
/**
 * Reset User Password - Standalone Page
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
    $stmt = $pdo->prepare("SELECT id, name, email, username FROM users WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL");
    $stmt->execute([$target_user_id, $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user: " . $e->getMessage());
}

if (!$user) {
    header('Location: users.php?error=' . urlencode('User not found'));
    exit;
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || strlen($new_password) < 6) {
        $message = 'Password must be at least 6 characters';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match';
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, require_password_change = 1 WHERE id = ? AND tenant_id = ?");
            $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $target_user_id, $tenant_id]);
            $message = 'Password reset successfully!';
            $message_type = 'success';
        } catch (Exception $e) {
            error_log("Error resetting password: " . $e->getMessage());
            $message = 'Failed to reset password. Please try again.';
            $message_type = 'error';
        }
    }
}

$page_title = 'Reset Password: ' . htmlspecialchars($user['name']);
ob_start();
?>

<div class="space-y-4 max-w-lg mx-auto" id="reset-password-content">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Users</p>
            <h1 class="text-lg font-bold text-white">Reset Password</h1>
        </div>
        <a href="users.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-arrow-left text-xs"></i> Back</a>
    </div>

    <!-- User Info -->
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-amber-500/15 flex items-center justify-center text-amber-400 font-bold text-sm ring-1 ring-amber-500/30">
                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
            </div>
            <div>
                <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($user['name']); ?></p>
                <p class="text-xs text-slate-400">@<?php echo htmlspecialchars($user['username']); ?> · <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="flex items-center gap-2 px-4 py-3 rounded-lg text-sm border <?php echo $message_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400' : 'bg-red-500/10 border-red-500/40 text-red-400'; ?>">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> flex-shrink-0"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Reset Form -->
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-5">
        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">New Password</label>
                <input type="password" name="new_password" id="new_password" required minlength="6" class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="Minimum 6 characters">
                <p class="text-xs text-slate-500 mt-1">Minimum 6 characters</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required minlength="6" class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="Re-enter password">
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors"><i class="fas fa-key text-xs mr-1.5"></i> Reset Password</button>
                <a href="users.php" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
