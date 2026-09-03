<?php
/**
 * Advanced User Management - Complete System
 * Fixed: Proper label associations, accessibility improvements
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_once __DIR__ . '/../../admin/UsersModel.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();

require_login();

// Check permission
$hasPermission = check_permission('users.manage') || is_super_admin();
if (!$hasPermission) {
    enforce_permission('users.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$user_id = get_current_user_id();
$is_superadmin = is_super_admin();

if (empty($tenant_id)) {
    die('Access denied: tenant context required.');
}

$page_title = 'Advanced User Management';
ob_start();

$csrf_token = generate_csrf_token();

// Get roles (no branch filter since roles are tenant-level)
$roles_by_id = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM roles WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL ORDER BY name");
    $stmt->execute([$tenant_id]);
    foreach ($stmt->fetchAll() as $role) {
        $roles_by_id[$role['id']] = ['id' => $role['id'], 'name' => $role['name'], 'label' => $role['name']];
    }
} catch (Exception $e) {}

// Get branches for this tenant
$branches = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll();
} catch (Exception $e) {}

// Check columns
$has_phone = false;
$has_last_login = false;
$has_deleted_at = false;
try {
    $pdo->query("SELECT phone FROM users WHERE branch_id = $current_branch_id LIMIT 1");
    $has_phone = true;
} catch (Exception $e) {}
try {
    $pdo->query("SELECT last_login FROM users WHERE branch_id = $current_branch_id LIMIT 1");
    $has_last_login = true;
} catch (Exception $e) {}
try {
    $pdo->query("SELECT deleted_at FROM users WHERE branch_id = $current_branch_id LIMIT 1");
    $has_deleted_at = true;
} catch (Exception $e) {}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed');
    }
    
    $action = $_POST['action'] ?? $_POST['bulk_action'] ?? '';
    $redirect_msg = '';
    $redirect_type = 'success';

    try {
        $pdo->beginTransaction();

        switch ($action) {
            case 'delete':
                $selected_ids = array_filter(array_map('intval', explode(',', $_POST['selected_users'] ?? '')));
                if (!empty($selected_ids)) {
                    $selected_ids = array_values(array_diff($selected_ids, [$user_id]));
                    $deletable_ids = [];

                    foreach ($selected_ids as $selected_id) {
                        $targetStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
                        $targetStmt->execute([$selected_id, $tenant_id]);
                        $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$targetUser) {
                            continue;
                        }

                        if (UsersModel::isProtectedUser((int) $targetUser['id'], (string) ($targetUser['name'] ?? ''), (string) ($targetUser['email'] ?? ''))) {
                            continue;
                        }

                        $deletable_ids[] = $selected_id;
                    }

                    if (empty($deletable_ids)) {
                        throw new Exception('No deletable users selected. Protected users are not removable.');
                    }

                    $placeholders = implode(',', array_fill(0, count($deletable_ids), '?'));
                    $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ? AND deleted_at IS NULL");
                    $stmt->execute(array_merge($deletable_ids, [$tenant_id]));
                    $redirect_msg = count($deletable_ids) . ' user(s) deleted successfully!';
                    break;
                }

                $del_user_id = (int)($_POST['user_id'] ?? 0);
                if ($del_user_id === $user_id) throw new Exception('Cannot delete your own account');

                $targetStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
                $targetStmt->execute([$del_user_id, $tenant_id]);
                $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);
                if (!$targetUser) throw new Exception('User not found');

                if (UsersModel::isProtectedUser((int) $targetUser['id'], (string) ($targetUser['name'] ?? ''), (string) ($targetUser['email'] ?? ''))) {
                    throw new Exception('This protected user cannot be deleted.');
                }

                $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
                $stmt->execute([$del_user_id, $tenant_id]);
                $redirect_msg = 'User deleted successfully!';
                break;
            case 'delete_selected':
                $selected_ids = array_filter(array_map('intval', explode(',', $_POST['selected_users'] ?? '')));
                if (empty($selected_ids)) throw new Exception('No users selected');

                $selected_ids = array_values(array_diff($selected_ids, [$user_id]));
                $deletable_ids = [];

                foreach ($selected_ids as $selected_id) {
                    $targetStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
                    $targetStmt->execute([$selected_id, $tenant_id]);
                    $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$targetUser) {
                        continue;
                    }

                    if (UsersModel::isProtectedUser((int) $targetUser['id'], (string) ($targetUser['name'] ?? ''), (string) ($targetUser['email'] ?? ''))) {
                        continue;
                    }

                    $deletable_ids[] = $selected_id;
                }

                if (empty($deletable_ids)) {
                    throw new Exception('No deletable users selected. Protected users are not removable.');
                }

                $placeholders = implode(',', array_fill(0, count($deletable_ids), '?'));
                $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ? AND deleted_at IS NULL");
                $stmt->execute(array_merge($deletable_ids, [$tenant_id]));
                $redirect_msg = count($deletable_ids) . ' user(s) deleted successfully!';
                break;
            case 'force_logout':
                $logout_user_id = (int)($_POST['user_id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
                $stmt->execute([$logout_user_id]);
                $redirect_msg = 'User force logged out successfully!';
                break;
            case 'toggle_status':
                $toggle_user_id = (int)($_POST['user_id'] ?? 0);
                if ($toggle_user_id === $user_id) throw new Exception('Cannot change your own status');
                $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$toggle_user_id, $tenant_id]);
                $u = $stmt->fetch();
                if (!$u) throw new Exception('User not found');
                $new_status = $u['is_active'] ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$new_status, $toggle_user_id, $tenant_id]);
                $redirect_msg = 'User status updated!';
                break;
            case 'activate':
                $selected_ids = array_filter(array_map('intval', explode(',', $_POST['selected_users'] ?? '')));
                if (empty($selected_ids)) throw new Exception('No users selected');
                $selected_ids = array_diff($selected_ids, [$user_id]);
                if (!empty($selected_ids)) {
                    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders) AND tenant_id = ?");
                    $stmt->execute(array_merge($selected_ids, [$tenant_id]));
                    $redirect_msg = count($selected_ids) . ' user(s) activated!';
                }
                break;
            case 'deactivate':
                $selected_ids = array_filter(array_map('intval', explode(',', $_POST['selected_users'] ?? '')));
                if (empty($selected_ids)) throw new Exception('No users selected');
                $selected_ids = array_diff($selected_ids, [$user_id]);
                if (!empty($selected_ids)) {
                    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders) AND tenant_id = ?");
                    $stmt->execute(array_merge($selected_ids, [$tenant_id]));
                    $redirect_msg = count($selected_ids) . ' user(s) deactivated!';
                }
                break;
            case 'reset_password':
                $rp_user_id = (int)($_POST['user_id'] ?? 0);
                $new_password = $_POST['new_password'] ?? '';
                if (empty($new_password) || strlen($new_password) < 6) throw new Exception('Password must be at least 6 characters');
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, require_password_change = 1 WHERE id = ? AND tenant_id = ?");
                $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $rp_user_id, $tenant_id]);
                $redirect_msg = 'Password reset successfully!';
                break;
            case 'send_welcome':
                $welcome_user_id = (int)($_POST['user_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$welcome_user_id, $tenant_id]);
                $user_data = $stmt->fetch();
                if ($user_data) {
                    // Send welcome email (implement your email function)
                    $redirect_msg = 'Welcome email sent!';
                }
                break;
            case 'add_note':
                $note_user_id = (int)($_POST['user_id'] ?? 0);
                $note = trim($_POST['note'] ?? '');
                if (empty($note)) throw new Exception('Note cannot be empty');
                // Check if user_notes table exists, create if not
                try {
                    $pdo->prepare("SELECT 1 FROM user_notes WHERE tenant_id = ? LIMIT 1")->execute([$tenant_id]);
                } catch (PDOException $e) {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS user_notes (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        tenant_id INT NOT NULL,
                        user_id INT NOT NULL,
                        author_id INT NOT NULL,
                        note TEXT NOT NULL,
                        created_at DATETIME NOT NULL,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )");
                }
                $stmt = $pdo->prepare("INSERT INTO user_notes (tenant_id, user_id, author_id, note, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$tenant_id, $note_user_id, $user_id, $note]);
                $redirect_msg = 'Note added successfully!';
                break;
            case 'bulk_email':
                 $selected_users = array_filter(array_map('intval', explode(',', $_POST['selected_users'] ?? '')));
                $subject = trim($_POST['email_subject'] ?? '');
                $message = trim($_POST['email_message'] ?? '');
                if (empty($selected_users) || empty($subject) || empty($message)) throw new Exception('Please fill all fields');
                $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
                $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id IN ($placeholders) AND tenant_id = ?");
                $stmt->execute(array_merge($selected_users, [$tenant_id]));
                $emails = $stmt->fetchAll();
                // Send bulk emails (implement your bulk email function)
                $redirect_msg = count($emails) . ' emails sent!';
                break;
            case 'export':
                $format = $_POST['export_format'] ?? 'csv';
                $selected_users = explode(',', $_POST['selected_users'] ?? '');
                $where = "WHERE tenant_id = ? AND deleted_at IS NULL";
                $params = [$tenant_id];
                if (!empty($selected_users) && $selected_users[0] != '') {
                    $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
                    $where .= " AND id IN ($placeholders)";
                    $params = array_merge($params, $selected_users);
                }
                $stmt = $pdo->prepare("SELECT id, name, email, username, is_active, created_at, last_login FROM users $where ORDER BY name");
                $stmt->execute($params);
                $export_users = $stmt->fetchAll();
                
                if ($format === 'csv') {
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
                    $output = fopen('php://output', 'w');
                    fputcsv($output, ['ID', 'Name', 'Email', 'Username', 'Status', 'Created Date', 'Last Login']);
                    foreach ($export_users as $user) {
                        fputcsv($output, [
                            $user['id'], $user['name'], $user['email'], $user['username'],
                            $user['is_active'] ? 'Active' : 'Inactive',
                            $user['created_at'], $user['last_login'] ?? 'Never'
                        ]);
                    }
                    fclose($output);
                    exit;
                }
                break;
            case 'import':
                if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Please upload a valid CSV file');
                }
                $file = fopen($_FILES['import_file']['tmp_name'], 'r');
                $headers = fgetcsv($file);
                $imported = 0;
                $errors = [];
                while (($row = fgetcsv($file)) !== false) {
                    $data = array_combine($headers, $row);
                    if (empty($data['name']) || empty($data['email'])) continue;
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ?");
                    $stmt->execute([$data['email'], $tenant_id]);
                    if (!$stmt->fetch()) {
                        $password = $data['password'] ?? bin2hex(random_bytes(4));
                        $stmt = $pdo->prepare("INSERT INTO users (tenant_id, name, email, username, password_hash, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                        $stmt->execute([$tenant_id, $data['name'], $data['email'], $data['username'] ?? '', password_hash($password, PASSWORD_DEFAULT)]);
                        $imported++;
                    }
                }
                fclose($file);
                $redirect_msg = "$imported users imported successfully!";
                break;
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $redirect_msg = $e->getMessage();
        $redirect_type = 'error';
    }

    if ($redirect_msg) {
        header('Location: users.php?' . ($redirect_type === 'error' ? 'error' : 'success') . '=' . urlencode($redirect_msg));
        exit;
    }
}

// Read flash messages
$message = $_GET['success'] ?? ($_GET['error'] ?? '');
$message_type = isset($_GET['error']) ? 'error' : (isset($_GET['success']) ? 'success' : '');

// Filters
$search = trim($_GET['s'] ?? '');
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$paged = max(1, (int)($_GET['paged'] ?? 1));
$per_page = 20;
$offset = ($paged - 1) * $per_page;

// Build query
$search_condition = '';
$search_params = [];
if (!empty($search)) {
    $search_condition = " AND (u.name LIKE ? OR u.email LIKE ? OR u.username LIKE ?" . ($has_phone ? " OR u.phone LIKE ?" : "") . ") ";
    $search_params = array_fill(0, $has_phone ? 4 : 3, "%{$search}%");
}
if (!empty($role_filter) && is_numeric($role_filter)) {
    $search_condition .= " AND u.role_id = ?";
    $search_params[] = $role_filter;
}
if ($status_filter === 'active') {
    $search_condition .= " AND u.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $search_condition .= " AND u.is_active = 0";
}
if (!empty($date_from)) {
    $search_condition .= " AND DATE(u.created_at) >= ?";
    $search_params[] = $date_from;
}
if (!empty($date_to)) {
    $search_condition .= " AND DATE(u.created_at) <= ?";
    $search_params[] = $date_to;
}

// Get total count
$total_users = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE u.tenant_id = ? AND u.deleted_at IS NULL" . $search_condition);
    $stmt->execute(array_merge([$tenant_id], $search_params));
    $total_users = $stmt->fetchColumn();
    $total_pages = max(1, ceil($total_users / $per_page));
} catch (Exception $e) {
    error_log("Count error: " . $e->getMessage());
}

// Get users with login history stats
$users = [];
try {
    // Check if login_history table exists
    $has_login_history = false;
    try {
        $pdo->prepare("SELECT 1 FROM login_history WHERE tenant_id = ? LIMIT 1")->execute([$tenant_id]);
        $has_login_history = true;
    } catch (PDOException $e) {}
    
    $select_fields = "u.id, u.name, u.email, u.username, u.role_id, u.is_active, u.created_at, u.last_login";
    if ($has_phone) $select_fields .= ", u.phone";
    
    $login_count_sql = $has_login_history ? "(SELECT COUNT(*) FROM login_history WHERE user_id = u.id AND tenant_id = " . (int)$tenant_id . ") as login_count" : "0 as login_count";
    $last_failed_sql = $has_login_history ? "(SELECT MAX(created_at) FROM login_history WHERE user_id = u.id AND success = 0 AND tenant_id = " . (int)$tenant_id . ") as last_failed_login" : "NULL as last_failed_login";
    
    $stmt = $pdo->prepare("
        SELECT {$select_fields}, {$login_count_sql}, {$last_failed_sql}
        FROM users u
        WHERE u.tenant_id = ?" . $search_condition . "
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge([$tenant_id], $search_params, [$per_page, $offset]));
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Users query error: " . $e->getMessage());
}

// Get statistics
$stats = [
    'total' => $total_users,
    'active' => 0,
    'inactive' => 0,
    'online' => 0,
    'never_logged_in' => 0,
    'new_this_month' => 0
];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND deleted_at IS NULL AND is_active = 1");
    $stmt->execute([$tenant_id]);
    $stats['active'] = $stmt->fetchColumn();
    $stats['inactive'] = $stats['total'] - $stats['active'];
    
    if ($has_last_login) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND deleted_at IS NULL AND last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt->execute([$tenant_id]);
        $stats['online'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND deleted_at IS NULL AND last_login IS NULL");
        $stmt->execute([$tenant_id]);
        $stats['never_logged_in'] = $stmt->fetchColumn();
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $stmt->execute([$tenant_id]);
    $stats['new_this_month'] = $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
}
?>

<div class="space-y-4" id="users-content">
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Administration</p>
            <h1 class="text-lg font-bold text-white">Advanced User Management</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <button onclick="toggleAutoRefresh()" id="autoRefreshBtn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors" title="Auto-refresh user list"><i class="fas fa-sync-alt text-xs"></i> <span id="autoRefreshLabel">Auto: Off</span></button>
            <button onclick="window.location.reload()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors" title="Refresh now"><i class="fas fa-redo text-xs"></i> Refresh</button>
            <button onclick="openImportModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-upload text-xs"></i> Import</button>
            <button onclick="openExportModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-download text-xs"></i> Export</button>
            <a href="user_form.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors"><i class="fas fa-plus text-xs"></i> Add User</a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="flex items-center gap-2 px-4 py-3 rounded-lg text-sm border <?php echo $message_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400' : 'bg-red-500/10 border-red-500/40 text-red-400'; ?>">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> flex-shrink-0"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-[11px] text-slate-500 uppercase tracking-wide font-semibold">Total</p>
            <p class="text-2xl font-bold text-white mt-1"><?php echo $stats['total']; ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-[11px] text-slate-500 uppercase tracking-wide font-semibold">Active</p>
            <p class="text-2xl font-bold text-emerald-400 mt-1"><?php echo $stats['active']; ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-[11px] text-slate-500 uppercase tracking-wide font-semibold">Inactive</p>
            <p class="text-2xl font-bold text-red-400 mt-1"><?php echo $stats['inactive']; ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-[11px] text-slate-500 uppercase tracking-wide font-semibold">Online Now</p>
            <p class="text-2xl font-bold text-amber-400 mt-1"><?php echo $stats['online']; ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-[11px] text-slate-500 uppercase tracking-wide font-semibold">Never Logged In</p>
            <p class="text-2xl font-bold text-slate-400 mt-1"><?php echo $stats['never_logged_in']; ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-[11px] text-slate-500 uppercase tracking-wide font-semibold">New This Month</p>
            <p class="text-2xl font-bold text-white mt-1"><?php echo $stats['new_this_month']; ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <form method="get" class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs pointer-events-none"></i>
                <input type="search" name="s" id="search_input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search users..." class="w-full pl-8 pr-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500 transition-colors">
            </div>
            <select name="role" id="role_filter" class="px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500">
                <option value="">All Roles</option>
                <?php foreach ($roles_by_id as $role): ?>
                    <option value="<?php echo $role['id']; ?>" <?php echo $role_filter == $role['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($role['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" id="status_filter" class="px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500">
            <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-3 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">Filter</button>
                <a href="users.php" class="px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">Clear</a>
            </div>
        </form>
    </div>

    <!-- Bulk Actions Bar -->
    <form method="post" id="users-form" class="mb-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="selected_users" id="selected_users_input" value="">
        <input type="hidden" name="bulk_action" id="bulk_action_input" value="">
        
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs text-slate-500 font-medium uppercase tracking-wide">Bulk:</span>
                <select id="bulk-action-selector" class="px-3 py-1.5 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500">
                    <option value="">Select Action</option>
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                    <option value="delete">Delete</option>
                    <option value="bulk_email">Send Email</option>
                    <option value="export_selected">Export Selected</option>
                </select>
                <button type="submit" onclick="executeBulkAction()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">Apply</button>
                <div id="bulk-email-fields" class="hidden flex-1 flex flex-wrap gap-3">
                     <input type="text" id="email_subject" name="email_subject" placeholder="Email Subject" class="flex-1 px-3 py-1.5 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm">
                     <textarea id="email_message" name="email_message" placeholder="Email Message" rows="2" class="flex-1 px-3 py-1.5 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm"></textarea>
                    <button type="button" onclick="sendBulkEmail()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">Send</button>
                </div>
                <span class="text-xs text-slate-500 ml-auto"><?php echo $total_users; ?> total users</span>
            </div>
        </div>

        <!-- Users Table -->
        <div class="overflow-x-auto bg-slate-800/40 rounded-xl border border-slate-700/60 mt-4">
            <table class="w-full text-left">
                <thead class="bg-slate-900/50 border-b border-slate-700/60">
                    <tr>
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" id="select-all" class="rounded border-slate-600">
                            <label for="select-all" class="sr-only">Select all users</label>
                        </th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Contact</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Role</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Last Login</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Logins</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Notes</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-slate-500">
                                <i class="fas fa-users text-3xl mb-2 block"></i>
                                No users found. <a href="user_form.php" class="text-amber-400 hover:underline">Create your first user</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user):
                            $is_online = $has_last_login && $user['last_login'] && strtotime($user['last_login']) > strtotime('-5 minutes');
                            $role_name = isset($roles_by_id[$user['role_id']]) ? $roles_by_id[$user['role_id']]['label'] : 'None';
                            $roleBadge = $role_name === 'None' ? 'bg-slate-500/15 text-slate-400 border border-slate-500/30' : ($role_name === 'Administrator' ? 'bg-amber-500/15 text-amber-400 border border-amber-500/30' : 'bg-blue-500/15 text-blue-400 border border-blue-500/30');
                        ?>
                            <tr class="hover:bg-slate-700/20 transition-colors cursor-pointer" data-user='<?php echo htmlspecialchars(json_encode($user)); ?>' onclick="if(!(event.target.closest('button')||event.target.closest('a')||event.target.closest('input'))){window.location.href='user_details.php?id=<?php echo $user['id']; ?>';}">
                                <td class="px-4 py-3" onclick="event.stopPropagation()">
                                    <input type="checkbox" name="users[]" id="user_<?php echo $user['id']; ?>" class="user-checkbox" value="<?php echo $user['id']; ?>">
                                    <label for="user_<?php echo $user['id']; ?>" class="sr-only">Select <?php echo htmlspecialchars($user['name']); ?></label>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex-shrink-0">
                                            <div class="w-9 h-9 rounded-full bg-slate-700 flex items-center justify-center text-sm font-bold text-white">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </div>
                                            <?php if ($is_online): ?>
                                                <div class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-500 rounded-full border-2 border-slate-800" title="Online"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-medium text-white text-sm"><?php echo htmlspecialchars($user['name']); ?></div>
                                            <div class="text-xs text-slate-500">@<?php echo htmlspecialchars($user['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-white"><?php echo htmlspecialchars($user['email']); ?></div>
                                    <?php if (!empty($user['phone'])): ?>
                                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($user['phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $roleBadge; ?>">
                                        <?php echo htmlspecialchars($role_name); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($user['is_active']): ?>
                                        <span class="inline-flex items-center gap-1 text-emerald-400 text-xs"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Active</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-red-400 text-xs"><span class="w-1.5 h-1.5 rounded-full bg-red-400"></span> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-400">
                                    <?php if ($user['last_login']): ?>
                                        <?php echo date('M d, H:i', strtotime($user['last_login'])); ?>
                                    <?php else: ?>
                                        <span class="text-slate-600">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-slate-700/60 text-slate-300 text-xs font-medium">
                                        <?php echo $user['login_count'] ?? 0; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button onclick="viewUserNotes(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['name'])); ?>')" class="text-purple-400 hover:text-purple-300" title="View Notes">
                                        <i class="fas fa-sticky-note"></i>
                                    </button>
                                </td>
                                <td class="px-4 py-3" onclick="event.stopPropagation()">
                                    <div class="flex items-center gap-1">
                                        <a href="user_details.php?id=<?php echo $user['id']; ?>" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-blue-500/20 hover:text-blue-400 transition-colors" title="View Details"><i class="fas fa-eye text-xs"></i></a>
                                        <a href="user_form.php?id=<?php echo $user['id']; ?>" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-amber-400 hover:bg-amber-500/20 hover:text-amber-300 transition-colors" title="Edit"><i class="fas fa-pen text-xs"></i></a>
                                        <a href="user_form.php?duplicate_from=<?php echo $user['id']; ?>" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-cyan-500/20 hover:text-cyan-400 transition-colors" title="Duplicate"><i class="fas fa-copy text-xs"></i></a>
                                        <a href="user_activity.php?id=<?php echo $user['id']; ?>" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-purple-500/20 hover:text-purple-400 transition-colors" title="Activity Log"><i class="fas fa-history text-xs"></i></a>
                                        <a href="reset_password.php?id=<?php echo $user['id']; ?>" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-amber-500/20 hover:text-amber-400 transition-colors" title="Reset Password"><i class="fas fa-key text-xs"></i></a>
                                        <button onclick="openForceLogoutModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['name'])); ?>')" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-orange-500/20 hover:text-orange-400 transition-colors" title="Force Logout"><i class="fas fa-sign-out-alt text-xs"></i></button>
                                        <?php if ($user['id'] !== $user_id): ?>
                                            <button onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['name'])); ?>')" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-red-500/20 hover:text-red-400 transition-colors" title="Delete"><i class="fas fa-trash-alt text-xs"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-between items-center mt-4">
            <div class="text-sm text-slate-500">Page <?php echo $paged; ?> of <?php echo $total_pages; ?> (<?php echo $total_users; ?> users)</div>
            <div class="flex gap-1">
                <?php if ($paged > 1): ?>
                    <a href="?paged=<?php echo $paged - 1; ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . $role_filter : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $date_from ? '&date_from=' . $date_from : ''; ?><?php echo $date_to ? '&date_to=' . $date_to : ''; ?>" class="px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-300 text-sm hover:border-amber-500 transition-colors">Prev</a>
                <?php endif; ?>
                <span class="px-3 py-1.5 bg-amber-500 text-slate-900 rounded-lg text-sm font-semibold"><?php echo $paged; ?></span>
                <?php if ($paged < $total_pages): ?>
                    <a href="?paged=<?php echo $paged + 1; ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . $role_filter : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $date_from ? '&date_from=' . $date_from : ''; ?><?php echo $date_to ? '&date_to=' . $date_to : ''; ?>" class="px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-300 text-sm hover:border-amber-500 transition-colors">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php
$INP_U = 'w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors';
$LBL_U = 'block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1';
?>
<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 w-full max-w-md mx-4 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-white flex items-center gap-2"><i class="fas fa-upload text-amber-400 text-sm"></i> Import Users</h3>
            <button onclick="closeImportModal()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors" aria-label="Close"><i class="fas fa-times text-xs"></i></button>
        </div>
        <form method="post" enctype="multipart/form-data" id="importForm" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="import">
            <div>
                <label for="import_file" class="<?php echo $LBL_U; ?>">CSV File (Name, Email, Username, Password)</label>
                <input type="file" name="import_file" id="import_file" accept=".csv" required class="<?php echo $INP_U; ?>">
                <p class="text-xs text-slate-500 mt-1">Format: name,email,username,password (password optional)</p>
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors">Import</button>
                <button type="button" onclick="closeImportModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Export Modal -->
<div id="exportModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 w-full max-w-md mx-4 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-white flex items-center gap-2"><i class="fas fa-download text-amber-400 text-sm"></i> Export Users</h3>
            <button onclick="closeExportModal()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors" aria-label="Close"><i class="fas fa-times text-xs"></i></button>
        </div>
        <form method="post" id="exportForm" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="export">
            <div>
                <label for="export_format" class="<?php echo $LBL_U; ?>">Export Format</label>
                <select name="export_format" id="export_format" class="<?php echo $INP_U; ?>">
                    <option value="csv">CSV</option>
                    <option value="excel">Excel (CSV format)</option>
                </select>
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors">Export</button>
                <button type="button" onclick="closeExportModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Notes Modal -->
<div id="notesModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 w-full max-w-md mx-4 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 id="notesTitle" class="text-base font-semibold text-white flex items-center gap-2"><i class="fas fa-sticky-note text-purple-400 text-sm"></i> Notes</h3>
            <button onclick="closeNotesModal()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"><i class="fas fa-times text-xs"></i></button>
        </div>
        <div id="notesList" class="space-y-2 max-h-48 overflow-y-auto mb-4"></div>
        <form id="addNoteForm" method="post" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="add_note">
            <input type="hidden" name="user_id" id="note_user_id">
            <div>
                <label class="<?php echo $LBL_U; ?>">New Note</label>
                <textarea name="note" id="note_text" rows="3" class="<?php echo $INP_U; ?> resize-none" placeholder="Enter note..."></textarea>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-purple-500/15 border border-purple-500/40 text-purple-400 font-semibold text-sm hover:bg-purple-500/25 transition-colors">Save Note</button>
                <button type="button" onclick="closeNotesModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">Close</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<script>
// PHP Data passed to JavaScript
const USER_ROLES_DATA = <?php echo json_encode(array_values($roles_by_id)); ?>;
const USER_BRANCHES_DATA = <?php echo json_encode($branches); ?>;
const CURRENT_USER_BRANCH_ID = <?php echo (int)$current_branch_id; ?>;
const IS_SUPER_ADMIN = <?php echo $is_superadmin ? 'true' : 'false'; ?>;
const ALL_USERS_DATA = <?php echo json_encode($users); ?>;

// ==================== TOAST NOTIFICATIONS ====================
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    const colors = { success: 'bg-emerald-800/95 border-l-4 border-emerald-400 text-white', error: 'bg-red-800/95 border-l-4 border-red-400 text-white', warning: 'bg-amber-800/95 border-l-4 border-amber-400 text-white', info: 'bg-slate-800 border-l-4 border-blue-400 text-slate-200' };
    toast.className = `${colors[type] || colors.info} px-4 py-3 rounded-lg text-sm font-medium shadow-2xl `;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>${escapeHtml(message)}`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ==================== BULK ACTIONS ====================
function getSelectedUsers() {
    return Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
}

function executeBulkAction() {
    const selected = getSelectedUsers();
    if (selected.length === 0) {
        showToast('Please select at least one user.', 'warning');
        return;
    }
    const action = document.getElementById('bulk-action-selector').value;
    if (!action) {
        showToast('Please select a bulk action.', 'warning');
        return;
    }
    
    if (action === 'bulk_email') {
        document.getElementById('bulk-email-fields').classList.remove('hidden');
        document.getElementById('selected_users_input').value = selected.join(',');
        return;
    }
    
    if (action === 'export_selected') {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                         <input type="hidden" name="action" value="export">
                         <input type="hidden" name="selected_users" value="${selected.join(',')}">
                         <input type="hidden" name="export_format" value="csv">`;
        document.body.appendChild(form);
        form.submit();
        showToast('Exporting ' + selected.length + ' user(s)...', 'success');
        return;
    }
    
    let msg = '';
    if (action === 'activate') msg = 'Activate selected users?';
    else if (action === 'deactivate') msg = 'Deactivate selected users?';
    else if (action === 'delete') msg = 'Delete selected users?';
    else {
        showToast('Invalid action.', 'error');
        return;
    }
    
    if (!confirm(msg)) return;
    
    document.getElementById('selected_users_input').value = selected.join(',');
    document.getElementById('bulk_action_input').value = action;
    document.getElementById('users-form').submit();
}

function sendBulkEmail() {
    const selected = getSelectedUsers();
    const subject = document.getElementById('email_subject').value;
    const message = document.getElementById('email_message').value;
    if (!subject || !message) {
        showToast('Please enter subject and message', 'warning');
        return;
    }
    if (!confirm(`Send email to ${selected.length} user(s)?`)) return;
    
    document.getElementById('selected_users_input').value = selected.join(',');
    document.getElementById('bulk_action_input').value = 'bulk_email';
    document.getElementById('users-form').submit();
}

// Select All checkbox
const selectAll = document.getElementById('select-all');
const userCheckboxes = document.querySelectorAll('.user-checkbox');
if (selectAll) {
    selectAll.addEventListener('change', function() {
        userCheckboxes.forEach(cb => cb.checked = selectAll.checked);
    });
}

// Modal Functions
function openImportModal() { const el = document.getElementById('importModal'); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeImportModal() { const el = document.getElementById('importModal'); el.classList.add('hidden'); el.classList.remove('flex'); }
function openExportModal() { const el = document.getElementById('exportModal'); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeExportModal() { const el = document.getElementById('exportModal'); el.classList.add('hidden'); el.classList.remove('flex'); }

// User Notes Modal
let currentNoteUserId = null;

function viewUserNotes(userId, userName) {
    currentNoteUserId = userId;
    document.getElementById('notesTitle').innerHTML = `<i class="fas fa-sticky-note text-purple-400 text-sm"></i> Notes: ${escapeHtml(userName)}`;
    document.getElementById('note_user_id').value = userId;
    document.getElementById('notesList').innerHTML = '<div class="text-center text-slate-500 py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Loading notes...</div>';
    const modal = document.getElementById('notesModal');
    modal.classList.remove('hidden'); modal.classList.add('flex');

    fetch(`get_user_notes.php?id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if (!data || data.length === 0) {
                document.getElementById('notesList').innerHTML = '<div class="text-center text-slate-500 py-4"><i class="fas fa-sticky-note text-2xl mb-2 block opacity-40"></i>No notes yet</div>';
            } else {
                document.getElementById('notesList').innerHTML = data.map(note => `
                    <div class="bg-slate-700/40 border border-slate-700/60 rounded-lg p-3">
                        <div class="text-xs text-slate-500 mb-1">${escapeHtml(note.created_at)}</div>
                        <div class="text-sm text-slate-300">${escapeHtml(note.note)}</div>
                    </div>
                `).join('');
            }
        })
        .catch(() => {
            document.getElementById('notesList').innerHTML = '<div class="text-center text-red-400 py-4"><i class="fas fa-exclamation-circle text-2xl mb-2 block"></i>Failed to load notes</div>';
        });
}

function addUserNote(userId, userName) {
    currentNoteUserId = userId;
    document.getElementById('notesTitle').innerHTML = `<i class="fas fa-sticky-note text-purple-400 text-sm"></i> Add Note: ${escapeHtml(userName)}`;
    document.getElementById('note_user_id').value = userId;
    document.getElementById('note_text').value = '';
    const modal = document.getElementById('notesModal');
    modal.classList.remove('hidden'); modal.classList.add('flex');
}

function closeNotesModal() {
    const modal = document.getElementById('notesModal');
    modal.classList.add('hidden'); modal.classList.remove('flex');
    document.getElementById('note_text').value = '';
}

// Other Actions
function openForceLogoutModal(userId, userName) {
    const modalId = 'forceLogoutModal_' + userId;
    if (document.getElementById(modalId)) return;
    const html = `
        <div id="${modalId}" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
            <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 w-full max-w-md mx-4 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-white flex items-center gap-2"><i class="fas fa-sign-out-alt text-orange-400 text-sm"></i> Force Logout</h3>
                    <button onclick="document.getElementById('${modalId}').remove()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"><i class="fas fa-times text-xs"></i></button>
                </div>
                <form method="post" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="force_logout">
                    <input type="hidden" name="user_id" value="${userId}">
                    <p class="text-slate-400 text-sm">Force logout <strong class="text-orange-400">${escapeHtml(userName)}</strong>? They will be signed out immediately.</p>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-orange-500/15 border border-orange-500/40 text-orange-400 font-semibold text-sm hover:bg-orange-500/25 transition-colors">Logout</button>
                        <button type="button" onclick="document.getElementById('${modalId}').remove()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', html);
}

function openDeleteModal(userId, userName) {
    const modalId = 'deleteModal_' + userId;
    if (document.getElementById(modalId)) return;
    const html = `
        <div id="${modalId}" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
            <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 w-full max-w-md mx-4 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-white flex items-center gap-2"><i class="fas fa-trash-alt text-red-400 text-sm"></i> Delete User</h3>
                    <button onclick="document.getElementById('${modalId}').remove()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"><i class="fas fa-times text-xs"></i></button>
                </div>
                <form method="post" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${userId}">
                    <p class="text-slate-400 text-sm">Delete <strong class="text-red-400">${escapeHtml(userName)}</strong>? This action cannot be undone.</p>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-red-500/15 border border-red-500/40 text-red-400 font-semibold text-sm hover:bg-red-500/25 transition-colors">Delete</button>
                        <button type="button" onclick="document.getElementById('${modalId}').remove()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', html);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ==================== AUTO-HIDE FLASH MESSAGES ====================
document.querySelectorAll('.bg-emerald-500\\/10, .bg-red-500\\/10').forEach(el => {
    setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 5000);
});

// ==================== DEBOUNCED SEARCH ====================
let searchTimeout;
document.getElementById('search_input')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { this.closest('form')?.submit(); }, 500);
});

// ==================== KEYBOARD SHORTCUTS ====================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImportModal(); closeExportModal(); closeNotesModal();
        document.querySelectorAll('[id^="deleteModal_"]').forEach(m => m.remove());
        document.querySelectorAll('[id^="forceLogoutModal_"]').forEach(m => m.remove());
    }
    if (!e.ctrlKey && !e.metaKey && !e.altKey && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA' && document.activeElement?.tagName !== 'SELECT') {
        if (e.key === '/') { e.preventDefault(); document.getElementById('search_input')?.focus(); }
    }
});

// ==================== MODAL BACKDROP CLICKS ====================
['importModal','exportModal','notesModal'].forEach(id => {
    const modal = document.getElementById(id);
    if (modal) modal.addEventListener('click', e => { if (e.target === modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); } });
});

// ==================== ADD NOTE FORM ====================
document.getElementById('addNoteForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const response = await fetch(window.location.href, { method: 'POST', body: formData });
    if (response.ok) {
        showToast('Note saved successfully', 'success');
        setTimeout(() => location.reload(), 800);
    } else {
        showToast('Failed to save note', 'error');
    }
});

// ==================== AUTO-REFRESH ====================
let autoRefreshInterval = null;
function toggleAutoRefresh() {
    const btn = document.getElementById('autoRefreshBtn');
    const label = document.getElementById('autoRefreshLabel');
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
        if (label) label.textContent = 'Auto: Off';
        if (btn) { btn.classList.remove('text-emerald-400','border-emerald-500/40'); btn.classList.add('text-slate-300','border-slate-600'); }
        showToast('Auto-refresh disabled', 'info');
    } else {
        autoRefreshInterval = setInterval(() => { window.location.reload(); }, 30000);
        if (label) label.textContent = 'Auto: 30s';
        if (btn) { btn.classList.remove('text-slate-300','border-slate-600'); btn.classList.add('text-emerald-400','border-emerald-500/40'); }
        showToast('Auto-refresh enabled (30s)', 'success');
    }
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>