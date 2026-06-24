<?php
/**
 * Advanced User Permissions & Role Management - COMPLETE
 * Slim & Compact Design - All features included
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Check permissions
require_login();
if (!check_permission('users.manage') && !is_super_admin()) {
    enforce_permission('users.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

$csrf_token = generate_csrf_token();

// ============================================================================
// PERMISSION TEMPLATES (with colors)
// ============================================================================
$permission_templates = [
    'full_access' => ['name' => 'Full Access', 'description' => 'All permissions enabled', 'icon' => 'fa-crown', 'color' => 'amber'],
    'pos_only' => ['name' => 'POS Only', 'description' => 'Point of sale operations', 'icon' => 'fa-cash-register', 'color' => 'blue'],
    'inventory_manager' => ['name' => 'Inventory Manager', 'description' => 'Product & stock management', 'icon' => 'fa-boxes', 'color' => 'green'],
    'reports_only' => ['name' => 'Reports Only', 'description' => 'View analytics', 'icon' => 'fa-chart-line', 'color' => 'purple'],
    'customer_service' => ['name' => 'Customer Service', 'description' => 'Customer management', 'icon' => 'fa-headset', 'color' => 'pink'],
    'accountant' => ['name' => 'Accountant', 'description' => 'Financial operations', 'icon' => 'fa-calculator', 'color' => 'teal'],
    'branch_manager' => ['name' => 'Branch Manager', 'description' => 'Branch operations', 'icon' => 'fa-building', 'color' => 'indigo'],
    'waiter' => ['name' => 'Waiter', 'description' => 'Restaurant orders', 'icon' => 'fa-utensils', 'color' => 'orange'],
    'kitchen_staff' => ['name' => 'Kitchen Staff', 'description' => 'Kitchen tickets', 'icon' => 'fa-fire', 'color' => 'red'],
    'delivery_rider' => ['name' => 'Delivery Rider', 'description' => 'Delivery tracking', 'icon' => 'fa-motorcycle', 'color' => 'yellow'],
    'auditor_read_only' => ['name' => 'Auditor', 'description' => 'Read-only access', 'icon' => 'fa-eye', 'color' => 'gray']
];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed');
    }
    
    $action = $_POST['action'] ?? '';
    $redirect_msg = '';
    $redirect_type = 'success';

    try {
        $pdo->beginTransaction();

        switch ($action) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $permissions = $_POST['permissions'] ?? [];

                if (empty($name)) throw new Exception('Role name is required');
                $check = $pdo->prepare('SELECT id FROM roles WHERE name = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');
                $check->execute([$name]);
                if ($check->fetch()) throw new Exception('Role name already exists');

                $stmt = $pdo->prepare('INSERT INTO roles (tenant_id, name, description, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$tenant_id, $name, $description]);
                $role_id = $pdo->lastInsertId();

                foreach ($permissions as $perm_id) {
                    $stmt = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)');
                    $stmt->execute([$role_id, $perm_id]);
                }
                $redirect_msg = "Role '$name' created successfully";
                break;

            case 'update':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $permissions = $_POST['permissions'] ?? [];

                if (empty($name)) throw new Exception('Role name is required');
                $check = $pdo->prepare('SELECT id FROM roles WHERE name = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL AND id != ?');
                $check->execute([$name, $id]);
                if ($check->fetch()) throw new Exception('Role name already exists');

                $stmt = $pdo->prepare('UPDATE roles SET name = ?, description = ? WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL)');
                $stmt->execute([$name, $description, $id, $tenant_id]);

                $stmt = $pdo->prepare('DELETE FROM role_permissions WHERE (tenant_id = ? OR tenant_id IS NULL) WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)');
                $stmt->execute([$id]);

                foreach ($permissions as $perm_id) {
                    $stmt = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)');
                    $stmt->execute([$id, $perm_id]);
                }
                $redirect_msg = "Role '$name' updated successfully";
                break;

            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                $protected_roles = ['Super Admin', 'Admin', 'Manager', 'Cashier'];
                $stmt = $pdo->prepare('SELECT name FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');
                $stmt->execute([$id, $tenant_id]);
                $role_name = $stmt->fetchColumn();
                if (in_array($role_name, $protected_roles)) throw new Exception('Cannot delete protected system roles');

                $user_count = 0;
                try {
                    $check = $pdo->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)');
                    $check->execute([$id]);
                    $user_count = $check->fetchColumn();
                } catch (PDOException $e) {
                    $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
                    $check->execute([$id]);
                    $user_count = $check->fetchColumn();
                }
                if ($user_count > 0) throw new Exception("Cannot delete role assigned to $user_count user(s)");

                $stmt = $pdo->prepare('UPDATE roles SET deleted_at = NOW() WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');
                $stmt->execute([$id]);
                $redirect_msg = "Role '$role_name' deleted successfully";
                break;

            case 'duplicate':
                $id = intval($_POST['id'] ?? 0);
                $new_name = trim($_POST['new_name'] ?? '');
                if (empty($new_name)) throw new Exception('New role name is required');
                $check = $pdo->prepare('SELECT id FROM roles WHERE name = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');
                $check->execute([$new_name]);
                if ($check->fetch()) throw new Exception('Role name already exists');

                $stmt = $pdo->prepare('SELECT description FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');
                $stmt->execute([$id]);
                $original = $stmt->fetch();

                $stmt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE (tenant_id = ? OR tenant_id IS NULL) WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)');
                $stmt->execute([$id]);
                $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $stmt = $pdo->prepare('INSERT INTO roles (tenant_id, name, description, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$tenant_id, $new_name, $original['description'] ?? 'Copy of existing role']);
                $new_id = $pdo->lastInsertId();

                foreach ($permissions as $perm_id) {
                    $stmt = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)');
                    $stmt->execute([$new_id, $perm_id]);
                }
                $redirect_msg = "Role '$new_name' created successfully";
                break;

            case 'apply_template':
                $id = intval($_POST['id'] ?? 0);
                $template = $_POST['template'] ?? '';
                
                $template_perms = [];
                switch ($template) {
                    case 'full_access':
                        $template_perms = $pdo->query("SELECT id FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
                        break;
                    case 'pos_only':
                        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code LIKE 'pos.%' OR code LIKE 'sales.%'");
                        $stmt->execute();
                        $template_perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        break;
                    case 'inventory_manager':
                        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code LIKE 'products.%' OR code LIKE 'inventory.%'");
                        $stmt->execute();
                        $template_perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        break;
                    case 'reports_only':
                        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code LIKE 'reports.%'");
                        $stmt->execute();
                        $template_perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        break;
                    default:
                        $template_perms = [];
                }
                $stmt = $pdo->prepare('DELETE FROM role_permissions WHERE (tenant_id = ? OR tenant_id IS NULL) WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)');
                $stmt->execute([$id]);
                foreach ($template_perms as $perm_id) {
                    $stmt = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)');
                    $stmt->execute([$id, $perm_id]);
                }
                $redirect_msg = "Permission template applied successfully";
                break;

            case 'assign_user_role':
                $user_id_assign = intval($_POST['user_id'] ?? 0);
                $role_id_assign = intval($_POST['role_id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
                $stmt->execute([$user_id_assign]);
                if ($role_id_assign > 0) {
                    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt->execute([$user_id_assign, $role_id_assign]);
                }
                $redirect_msg = "User role assigned successfully";
                break;
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $redirect_msg = $e->getMessage();
        $redirect_type = 'error';
    }

    if ($redirect_msg) {
        header('Location: permissions.php?' . ($redirect_type === 'error' ? 'error' : 'success') . '=' . urlencode($redirect_msg));
        exit;
    }
}

// Read flash messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Get data
$roles = [];
try {
    $tables = [];
    $tbls = $pdo->query("SHOW TABLES");
    while ($row = $tbls->fetch(PDO::FETCH_NUM)) { $tables[] = $row[0]; }
    $has_role_permissions = in_array('role_permissions', $tables);
    $perm_count_sub = $has_role_permissions
        ? "(SELECT COUNT(*) FROM role_permissions WHERE (tenant_id = ? OR tenant_id IS NULL) WHERE role_id = r.id)"
        : '0';
    $user_join = $has_role_permissions
        ? "LEFT JOIN user_roles ur ON r.id = ur.role_id"
        : '';
    $group_by = $has_role_permissions ? "GROUP BY r.id" : "GROUP BY r.id";

    $stmt = $pdo->prepare("
        SELECT r.*, COUNT(DISTINCT ur.user_id) as user_count,
               $perm_count_sub as permission_count
        FROM roles r
        $user_join
        WHERE r.tenant_id = ? OR r.tenant_id IS NULL
        $group_by
        ORDER BY FIELD(r.name, 'Super Admin', 'Admin', 'Manager'), r.name
    ");
    $stmt->execute([$tenant_id]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Roles error: " . $e->getMessage()); }

$permissions = [];
$grouped_permissions = [];
try {
    $tables = [];
    if (empty($tables)) {
        $tbls2 = $pdo->query("SHOW TABLES");
        while ($row = $tbls2->fetch(PDO::FETCH_NUM)) { $tables[] = $row[0]; }
    }
    if (!in_array('permissions', $tables)) throw new PDOException("permissions table missing");
    $stmt = $pdo->query("
        SELECT id, code, description, 
               SUBSTRING_INDEX(code, '.', 1) as module
        FROM permissions WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL ORDER BY module, code
    ");
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($permissions as $perm) {
        $module = $perm['module'] ?: 'other';
        if (!isset($grouped_permissions[$module])) $grouped_permissions[$module] = [];
        $grouped_permissions[$module][] = $perm;
    }
} catch (PDOException $e) { error_log("Permissions error: " . $e->getMessage()); }

$users = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.last_login, u.is_active,
               r.id as role_id, r.name as role_name,
               b.name as branch_name
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.tenant_id = ?
        ORDER BY u.name
    ");
    $stmt->execute([$tenant_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Users error: " . $e->getMessage()); }

$branches = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND branch_id = $current_branch_id ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Role permission map for JS
$role_permissions_map = [];
try {
    $stmt = $pdo->query("SELECT role_id, permission_id FROM role_permissions WHERE (tenant_id = ? OR tenant_id IS NULL)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rid = (int)$row['role_id'];
        $pid = (int)$row['permission_id'];
        if (!isset($role_permissions_map[$rid])) $role_permissions_map[$rid] = [];
        $role_permissions_map[$rid][] = $pid;
    }
} catch (PDOException $e) {}

$roles_by_id = [];
foreach ($roles as $r) { $roles_by_id[(int)$r['id']] = $r; }

$page_title = 'Permissions & Roles';
ob_start();
?>

<!-- Slim & Compact Layout -->
<div class="space-y-4" id="adv-perm-content">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Administration</p>
            <h1 class="text-lg font-bold text-white"><?php echo htmlspecialchars($page_title); ?></h1>
        </div>
        <div>
            <a href="roles.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors"><i class="fas fa-plus text-xs"></i> New Role</a>
        </div>
    </div>

    <!-- Messages -->
    <div id="messageContainer"></div>
    <?php if ($success): ?>
        <div class="mb-3 p-2 rounded-lg bg-emerald-500/20 text-emerald-400 text-xs"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="mb-3 p-2 rounded-lg bg-red-500/20 text-red-400 text-xs"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-slate-800/50 border border-slate-700 rounded-lg p-3 text-center">
            <div class="text-[9px] text-slate-500 uppercase tracking-wider mb-0.5">Roles</div>
            <div class="text-xl font-bold text-amber-400"><?php echo count($roles); ?></div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-lg p-3 text-center">
            <div class="text-[9px] text-slate-500 uppercase tracking-wider mb-0.5">Permissions</div>
            <div class="text-xl font-bold text-emerald-400"><?php echo count($permissions); ?></div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-lg p-3 text-center">
            <div class="text-[9px] text-slate-500 uppercase tracking-wider mb-0.5">Users</div>
            <div class="text-xl font-bold text-blue-400"><?php echo count($users); ?></div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-lg p-3 text-center">
            <div class="text-[9px] text-slate-500 uppercase tracking-wider mb-0.5">Branches</div>
            <div class="text-xl font-bold text-purple-400"><?php echo count($branches); ?></div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="flex flex-col lg:flex-row gap-4">
        
        <!-- LEFT COLUMN: Roles List -->
        <div class="flex-1">
            <div class="bg-slate-800/50 border border-slate-700 rounded-lg">
                <div class="px-3 py-2 border-b border-slate-700 flex justify-between items-center">
                    <h2 class="text-xs font-semibold text-amber-400 uppercase tracking-wider">System Roles</h2>
                    <div class="flex gap-2">
                        <div class="relative">
                            <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-slate-500 text-[9px]"></i>
                            <input type="text" id="roleSearch" placeholder="Search roles..." class="pl-6 pr-2 py-1 bg-slate-800 border border-slate-700 rounded-md text-white text-[10px] w-28 focus:border-amber-500 focus:outline-none">
                        </div>
                        <button onclick="exportRoles()" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded-md text-[9px]" title="Export CSV"><i class="fas fa-download text-[9px]"></i></button>
                    </div>
                </div>
                <div class="divide-y divide-slate-700 max-h-[500px] overflow-y-auto custom-scroll">
                    <?php foreach ($roles as $role):
                        $is_protected = in_array($role['name'], ['Super Admin', 'Admin', 'Manager', 'Cashier']);
                        $role_color = $role['name'] == 'Super Admin' ? 'amber' : ($role['name'] == 'Admin' ? 'purple' : ($role['name'] == 'Manager' ? 'blue' : 'slate'));
                    ?>
                        <div class="p-3 hover:bg-slate-700/20 transition-colors role-row" data-role-name="<?php echo strtolower(htmlspecialchars($role['name'])); ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-lg bg-<?php echo $role_color; ?>-500/20 flex items-center justify-center">
                                        <i class="fas <?php echo $role['name'] == 'Super Admin' ? 'fa-crown' : 'fa-user-tag'; ?> text-<?php echo $role_color; ?>-400 text-[10px]"></i>
                                    </div>
                                    <div>
                                        <span class="text-white text-xs font-medium"><?php echo htmlspecialchars($role['name']); ?></span>
                                        <?php if ($is_protected): ?>
                                            <span class="ml-1 inline-flex px-1 py-0.5 rounded-full text-[8px] font-semibold bg-purple-500/15 text-purple-400">Protected</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex gap-1.5 relative z-10">
                                    <a href="roles/edit_role.php?id=<?php echo $role['id']; ?>" class="text-blue-400 hover:text-blue-300" title="Edit"><i class="fas fa-edit text-[10px]"></i></a>
                                    <a href="roles/duplicate_role.php?id=<?php echo $role['id']; ?>" class="text-emerald-400 hover:text-emerald-300" title="Duplicate"><i class="fas fa-copy text-[10px]"></i></a>
                                    <a href="roles/manage_permissions.php?id=<?php echo $role['id']; ?>" class="text-purple-400 hover:text-purple-300" title="Permissions"><i class="fas fa-key text-[10px]"></i></a>
                                    <?php if (!$is_protected && $role['name'] !== 'Super Admin'): ?>
                                        <a href="roles/delete_role.php?id=<?php echo $role['id']; ?>" class="text-red-400 hover:text-red-300" title="Delete" onclick="return confirm('Delete role?')"><i class="fas fa-trash-alt text-[10px]"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mb-2 line-clamp-2"><?php echo htmlspecialchars($role['description'] ?: 'No description'); ?></p>
                            <div class="flex items-center gap-3 text-[9px] text-slate-500">
                                <span><i class="fas fa-users mr-0.5"></i> <?php echo $role['user_count']; ?> users</span>
                                <span><i class="fas fa-key mr-0.5"></i> <?php echo $role['permission_count']; ?> perms</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: User Role Assignments -->
        <div class="flex-1">
            <div class="bg-slate-800/50 border border-slate-700 rounded-lg">
                <div class="px-3 py-2 border-b border-slate-700 flex justify-between items-center">
                    <h2 class="text-xs font-semibold text-amber-400 uppercase tracking-wider">User Role Assignments</h2>
                    <button onclick="openAssignUserModal()" class="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 rounded text-[9px] hover:bg-emerald-500/30">
                        <i class="fas fa-plus text-[8px] mr-0.5"></i> Assign
                    </button>
                </div>
                <div class="overflow-x-auto max-h-[500px] overflow-y-auto custom-scroll">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-800 sticky top-0">
                            <tr class="text-[9px] text-slate-400 uppercase">
                                <th class="text-left px-3 py-2">User</th>
                                <th class="text-left px-3 py-2">Role</th>
                                <th class="text-left px-3 py-2">Branch</th>
                                <th class="text-left px-3 py-2">Last Login</th>
                                <th class="text-center px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700">
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-slate-700/20 transition-colors">
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-slate-700 flex items-center justify-center text-[9px] font-bold text-white">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="text-white text-xs"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <div class="text-[9px] text-slate-500"><?php echo htmlspecialchars($user['email'] ?: 'No email'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <?php if ($user['role_name']): ?>
                                            <span class="inline-flex px-1.5 py-0.5 rounded-full text-[9px] font-medium bg-blue-500/20 text-blue-400">
                                                <?php echo htmlspecialchars($user['role_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-500 text-[9px]">No role</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-slate-400 text-[10px]"><?php echo htmlspecialchars($user['branch_name'] ?: '—'); ?></td>
                                    <td class="px-3 py-2 text-slate-500 text-[9px]">
                                        <?php echo $user['last_login'] ? date('M d, H:i', strtotime($user['last_login'])) : 'Never'; ?>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button onclick="editUserRole(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['name'])); ?>', <?php echo $user['role_id'] ?: 'null'; ?>)" class="text-blue-400 hover:text-blue-300" title="Change Role">
                                            <i class="fas fa-edit text-[11px]"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Role Modal -->
<div id="roleModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50 p-4">
    <div class="bg-slate-800 rounded-lg w-full max-w-sm p-4 border border-slate-700">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-bold text-amber-400" id="roleModalTitle"><i class="fas fa-plus text-xs mr-1"></i> Create Role</h3>
            <button onclick="closeRoleModal()" class="text-slate-400 hover:text-white text-lg">&times;</button>
        </div>
        <form id="roleForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" id="roleAction" value="create">
            <input type="hidden" name="id" id="roleId" value="0">
            <div class="mb-3">
                <label class="block text-[10px] text-slate-400 mb-0.5">Role Name <span class="text-red-400">*</span></label>
                <input type="text" name="name" id="roleName" required class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-md text-white text-xs focus:border-amber-500 focus:outline-none">
            </div>
            <div class="mb-4">
                <label class="block text-[10px] text-slate-400 mb-0.5">Description</label>
                <input type="text" name="description" id="roleDescription" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-md text-white text-xs focus:border-amber-500 focus:outline-none">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-2 py-1.5 bg-amber-500/15 border border-amber-500/40 hover:bg-amber-500/25 text-amber-400 rounded-md text-xs font-semibold"><i class="fas fa-save text-[9px] mr-0.5"></i> Save</button>
                <button type="button" onclick="closeRoleModal()" class="flex-1 px-2 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-md text-xs text-slate-300">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Duplicate Role Modal -->
<div id="duplicateRoleModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50 p-4">
    <div class="bg-slate-800 rounded-lg w-full max-w-sm p-4 border border-slate-700">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-bold text-amber-400"><i class="fas fa-copy text-xs mr-1"></i> Duplicate Role</h3>
            <button onclick="closeDuplicateRoleModal()" class="text-slate-400 hover:text-white text-lg">&times;</button>
        </div>
        <form id="duplicateForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="id" id="duplicateRoleId">
            <div class="mb-3">
                <label class="block text-[10px] text-slate-400 mb-0.5">Original Role</label>
                <p id="duplicateRoleName" class="text-white bg-slate-900 border border-slate-700 p-1.5 rounded-md text-xs"></p>
            </div>
            <div class="mb-4">
                <label class="block text-[10px] text-slate-400 mb-0.5">New Role Name <span class="text-red-400">*</span></label>
                <input type="text" name="new_name" id="newRoleName" required class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-md text-white text-xs focus:border-amber-500 focus:outline-none">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-2 py-1.5 bg-amber-500/15 border border-amber-500/40 hover:bg-amber-500/25 text-amber-400 rounded-md text-xs font-semibold"><i class="fas fa-copy text-[9px] mr-0.5"></i> Duplicate</button>
                <button type="button" onclick="closeDuplicateRoleModal()" class="flex-1 px-2 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-md text-xs text-slate-300">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Role Modal -->
<div id="deleteRoleModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50 p-4">
    <div class="bg-slate-800 rounded-lg w-full max-w-sm p-4 border border-slate-700">
        <div class="flex items-center gap-2 text-red-400 mb-3">
            <i class="fas fa-exclamation-triangle text-sm"></i>
            <h3 class="text-sm font-bold text-white">Delete Role</h3>
        </div>
        <form id="deleteForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="id" id="deleteRoleId">
            <p class="text-slate-300 text-xs mb-3">Delete <span id="deleteRoleName" class="text-white font-semibold"></span>?</p>
            <div id="deleteRoleWarning" class="bg-red-900/30 border border-red-500/30 text-red-400 p-2 rounded-md mb-3 text-[10px] hidden">
                <i class="fas fa-exclamation-circle mr-1"></i> Assigned to <span id="deleteUserCount">0</span> user(s)
            </div>
            <div class="flex gap-2">
                <button type="submit" id="deleteRoleButton" class="flex-1 px-2 py-1.5 bg-red-500/15 border border-red-500/40 hover:bg-red-500/25 text-red-400 rounded-md text-xs font-semibold"><i class="fas fa-trash text-[9px] mr-0.5"></i> Delete</button>
                <button type="button" onclick="closeDeleteRoleModal()" class="flex-1 px-2 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-md text-xs text-slate-300">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Permissions Modal -->
<div id="permissionsModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50 p-4">
    <div class="bg-slate-800 rounded-lg w-full max-w-3xl max-h-[80vh] overflow-y-auto p-4 border border-slate-700">
        <div class="flex justify-between items-center mb-3 sticky top-0 bg-slate-800 pb-2 z-10">
            <h3 class="text-sm font-bold text-amber-400">Manage Permissions</h3>
            <div class="flex gap-2">
                <button onclick="selectAllPermissions()" class="px-2 py-0.5 bg-slate-700 hover:bg-slate-600 rounded text-[9px]"><i class="fas fa-check-double text-[8px]"></i> All</button>
                <button onclick="deselectAllPermissions()" class="px-2 py-0.5 bg-slate-700 hover:bg-slate-600 rounded text-[9px]"><i class="fas fa-times text-[8px]"></i> None</button>
                <button onclick="closePermissionsModal()" class="text-slate-400 hover:text-white text-lg">&times;</button>
            </div>
        </div>
        
        <!-- Quick Templates -->
        <div class="mb-4 pb-2 border-b border-slate-700">
            <div class="flex flex-wrap gap-1">
                <span class="text-[9px] text-slate-500 mr-1"><i class="fas fa-magic text-[8px]"></i> Templates:</span>
                <?php foreach ($permission_templates as $key => $template): ?>
                    <button onclick="applyTemplateToPermissions('<?php echo $key; ?>')" class="px-1.5 py-0.5 text-[9px] rounded bg-<?php echo $template['color']; ?>-500/10 text-<?php echo $template['color']; ?>-400 hover:bg-<?php echo $template['color']; ?>-500/20 transition-colors">
                        <i class="fas <?php echo $template['icon']; ?> text-[8px]"></i> <?php echo $template['name']; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <form id="permissionsForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="permRoleId">
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                <?php foreach ($grouped_permissions as $module => $perms): ?>
                    <div class="bg-slate-900/30 rounded-lg p-2">
                        <div class="flex justify-between items-center mb-2 border-b border-slate-700 pb-1">
                            <h4 class="text-[10px] font-semibold text-amber-400 uppercase"><?php echo ucfirst(htmlspecialchars($module)); ?></h4>
                            <label class="flex items-center gap-0.5 cursor-pointer">
                                <input type="checkbox" class="category-select-all w-2.5 h-2.5 accent-amber-500 rounded" data-category="<?php echo htmlspecialchars($module); ?>">
                                <span class="text-[8px] text-slate-500 hover:text-amber-400">All</span>
                            </label>
                        </div>
                        <div class="space-y-1 max-h-32 overflow-y-auto" data-category="<?php echo htmlspecialchars($module); ?>">
                            <?php foreach ($perms as $perm): ?>
                                <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-800 p-0.5 rounded">
                                    <input type="checkbox" name="permissions[]" value="<?php echo $perm['id']; ?>" class="permission-checkbox w-2.5 h-2.5 accent-amber-500 rounded">
                                    <span class="text-[9px] text-slate-300 truncate"><?php echo htmlspecialchars($perm['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="flex gap-2 mt-4 pt-2 border-t border-slate-700">
                <button type="button" onclick="closePermissionsModal()" class="flex-1 px-2 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-md text-xs text-slate-300">Cancel</button>
                <button type="submit" class="flex-1 px-2 py-1.5 bg-amber-500/15 border border-amber-500/40 hover:bg-amber-500/25 text-amber-400 rounded-md text-xs font-semibold"><i class="fas fa-save text-[9px] mr-0.5"></i> Save Permissions</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign User Role Modal -->
<div id="assignUserModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50 p-4">
    <div class="bg-slate-800 rounded-lg w-full max-w-sm p-4 border border-slate-700">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-bold text-amber-400" id="assignUserTitle">Assign Role</h3>
            <button onclick="closeAssignUserModal()" class="text-slate-400 hover:text-white text-lg">&times;</button>
        </div>
        <form id="assignUserForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="assign_user_role">
            <input type="hidden" name="user_id" id="assignUserId">
            <div class="mb-3">
                <label class="block text-[10px] text-slate-400 mb-0.5">User</label>
                <select id="assignUserSelect" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-md text-white text-xs focus:border-amber-500 focus:outline-none">
                    <option value="">Select user...</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name'] . ' (' . ($u['email'] ?: 'no email') . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-[10px] text-slate-400 mb-0.5">Role</label>
                <select name="role_id" id="assignRoleId" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-md text-white text-xs focus:border-amber-500 focus:outline-none">
                    <option value="0">-- Remove role --</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="closeAssignUserModal()" class="flex-1 px-2 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-md text-xs text-slate-300">Cancel</button>
                <button type="submit" class="flex-1 px-2 py-1.5 bg-amber-500/15 border border-amber-500/40 hover:bg-amber-500/25 text-amber-400 rounded-md text-xs font-semibold"><i class="fas fa-save text-[9px] mr-0.5"></i> Assign</button>
            </div>
        </form>
    </div>
</div>

<style>
.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
.custom-scroll::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>

<script>
const API_BASE = window.location.pathname.replace(/\/[^/]*$/, '/');
let currentRolePermissions = [];

function showMessage(message, type = 'success') {
    const container = document.getElementById('messageContainer');
    const bgColor = type === 'success' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    const msgDiv = document.createElement('div');
    msgDiv.className = `mb-3 p-2 rounded-lg ${bgColor} text-xs flex items-center justify-between`;
    msgDiv.innerHTML = `<div class="flex items-center gap-2"><i class="fas ${icon} text-[10px]"></i><span>${message}</span></div><button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white">&times;</button>`;
    container.appendChild(msgDiv);
    setTimeout(() => msgDiv?.remove(), 3000);
}

async function apiCall(action, data) {
    try {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('csrf_token', '<?php echo htmlspecialchars($csrf_token); ?>');
        Object.keys(data).forEach(key => {
            if (Array.isArray(data[key])) {
                data[key].forEach(val => formData.append(`${key}[]`, val));
            } else {
                formData.append(key, data[key]);
            }
        });
        const response = await fetch(`${API_BASE}permissions.php`, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showMessage(result.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(result.message, 'error');
        }
        return result;
    } catch (error) {
        showMessage('Network error: ' + error.message, 'error');
        return { success: false, message: error.message };
    }
}

// Role CRUD
function openCreateModal() {
    document.getElementById('roleModalTitle').innerHTML = '<i class="fas fa-plus text-xs mr-1"></i> Create Role';
    document.getElementById('roleAction').value = 'create';
    document.getElementById('roleId').value = '0';
    document.getElementById('roleName').value = '';
    document.getElementById('roleDescription').value = '';
    document.getElementById('roleModal').classList.remove('hidden');
}

function editRole(roleId) {
    fetch(`${API_BASE}get_role.php?id=${roleId}`)
        .then(res => res.json())
        .then(role => {
            if (role) {
                document.getElementById('roleModalTitle').innerHTML = '<i class="fas fa-edit text-xs mr-1"></i> Edit Role';
                document.getElementById('roleAction').value = 'update';
                document.getElementById('roleId').value = role.id;
                document.getElementById('roleName').value = role.name || '';
                document.getElementById('roleDescription').value = role.description || '';
                document.getElementById('roleModal').classList.remove('hidden');
            }
        });
}

function closeRoleModal() { document.getElementById('roleModal').classList.add('hidden'); }

function duplicateRole(id, name) {
    document.getElementById('duplicateRoleId').value = id;
    document.getElementById('duplicateRoleName').textContent = name;
    document.getElementById('duplicateRoleModal').classList.remove('hidden');
}

function closeDuplicateRoleModal() { document.getElementById('duplicateRoleModal').classList.add('hidden'); }

function deleteRole(id, name, userCount) {
    document.getElementById('deleteRoleId').value = id;
    document.getElementById('deleteRoleName').textContent = name;
    document.getElementById('deleteUserCount').textContent = userCount;
    const warning = document.getElementById('deleteRoleWarning');
    const btn = document.getElementById('deleteRoleButton');
    if (userCount > 0) {
        warning.classList.remove('hidden');
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        warning.classList.add('hidden');
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
    document.getElementById('deleteRoleModal').classList.remove('hidden');
}

function closeDeleteRoleModal() { document.getElementById('deleteRoleModal').classList.add('hidden'); }

// Permissions Management
function manageRolePermissions(roleId) {
    document.getElementById('permRoleId').value = roleId;
    // Fetch current permissions
    fetch(`${API_BASE}get_role.php?id=${roleId}`)
        .then(res => res.json())
        .then(role => {
            currentRolePermissions = role.permission_ids ? String(role.permission_ids).split(',').map(id => id.trim()) : [];
            document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => {
                cb.checked = currentRolePermissions.includes(cb.value);
            });
            updateCategorySelectAll();
        });
    document.getElementById('permissionsModal').classList.remove('hidden');
}

function closePermissionsModal() { document.getElementById('permissionsModal').classList.add('hidden'); }

function selectAllPermissions() {
    document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => cb.checked = true);
    updateCategorySelectAll();
}

function deselectAllPermissions() {
    document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => cb.checked = false);
    updateCategorySelectAll();
}

function updateCategorySelectAll() {
    document.querySelectorAll('#permissionsModal [data-category]').forEach(categoryDiv => {
        const checkboxes = categoryDiv.querySelectorAll('.permission-checkbox');
        const checkedBoxes = categoryDiv.querySelectorAll('.permission-checkbox:checked');
        const selectAllCb = document.querySelector(`#permissionsModal .category-select-all[data-category="${categoryDiv.dataset.category}"]`);
        if (selectAllCb) selectAllCb.checked = checkboxes.length > 0 && checkedBoxes.length === checkboxes.length;
    });
}

function applyTemplateToPermissions(template) {
    document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => cb.checked = false);
    if (template === 'full_access') {
        document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => cb.checked = true);
    } else if (template === 'pos_only') {
        document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => {
            const text = cb.closest('label')?.innerText || '';
            cb.checked = text.includes('pos.') || text.includes('sales.');
        });
    } else if (template === 'inventory_manager') {
        document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => {
            const text = cb.closest('label')?.innerText || '';
            cb.checked = text.includes('products.') || text.includes('inventory.');
        });
    } else if (template === 'reports_only') {
        document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => {
            const text = cb.closest('label')?.innerText || '';
            cb.checked = text.includes('reports.');
        });
    }
    updateCategorySelectAll();
    showMessage(`Template applied`, 'success');
}

// User Role Assignment
function openAssignUserModal() {
    document.getElementById('assignUserTitle').textContent = 'Assign Role';
    document.getElementById('assignUserId').value = '';
    document.getElementById('assignUserSelect').value = '';
    document.getElementById('assignRoleId').value = '';
    document.getElementById('assignUserModal').classList.remove('hidden');
}

function editUserRole(userId, userName, currentRoleId) {
    document.getElementById('assignUserTitle').textContent = `Change Role: ${userName}`;
    document.getElementById('assignUserId').value = userId;
    document.getElementById('assignUserSelect').value = userId;
    document.getElementById('assignUserSelect').disabled = true;
    document.getElementById('assignRoleId').value = currentRoleId || '';
    document.getElementById('assignUserModal').classList.remove('hidden');
}

function closeAssignUserModal() {
    document.getElementById('assignUserSelect').disabled = false;
    document.getElementById('assignUserModal').classList.add('hidden');
}

// Form submissions
document.getElementById('roleForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const action = document.getElementById('roleAction').value;
    const data = {
        name: document.getElementById('roleName').value,
        description: document.getElementById('roleDescription').value
    };
    if (action === 'update') data.id = document.getElementById('roleId').value;
    await apiCall(action, data);
});

document.getElementById('duplicateForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    await apiCall('duplicate', {
        id: document.getElementById('duplicateRoleId').value,
        new_name: document.getElementById('newRoleName').value
    });
});

document.getElementById('deleteForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    await apiCall('delete', { id: document.getElementById('deleteRoleId').value });
});

document.getElementById('permissionsForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const selectedPerms = Array.from(document.querySelectorAll('#permissionsModal .permission-checkbox:checked')).map(cb => cb.value);
    await apiCall('update', {
        id: document.getElementById('permRoleId').value,
        name: document.getElementById('permRoleName')?.value || '',
        description: '',
        permissions: selectedPerms
    });
});

document.getElementById('assignUserForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const userId = document.getElementById('assignUserId').value || document.getElementById('assignUserSelect').value;
    await apiCall('assign_user_role', { user_id: userId, role_id: document.getElementById('assignRoleId').value });
});

// Search
document.getElementById('roleSearch')?.addEventListener('input', e => {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.role-row').forEach(row => {
        row.style.display = row.dataset.roleName?.includes(term) ? '' : 'none';
    });
});

// Category select all
document.querySelectorAll('.category-select-all').forEach(cb => {
    cb.addEventListener('change', function() {
        const container = document.querySelector(`[data-category="${this.dataset.category}"]`);
        if (container) container.querySelectorAll('.permission-checkbox').forEach(permCb => permCb.checked = this.checked);
    });
});

// Export
function exportRoles() {
    const rows = document.querySelectorAll('.role-row');
    let csv = 'Role Name,Description,Users,Permissions\n';
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const name = row.querySelector('.text-white')?.innerText || '';
            const desc = row.querySelector('.text-gray-400')?.innerText || '';
            const stats = row.querySelectorAll('.text-gray-500 span');
            const users = stats[0]?.innerText.match(/\d+/) || ['0'];
            const perms = stats[1]?.innerText.match(/\d+/) || ['0'];
            csv += `"${name}","${desc}",${users[0]},${perms[0]}\n`;
        }
    });
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `roles_export_${new Date().toISOString().slice(0,19)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// Close modals on backdrop click
document.getElementById('roleModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeRoleModal(); });
document.getElementById('duplicateRoleModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeDuplicateRoleModal(); });
document.getElementById('deleteRoleModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeDeleteRoleModal(); });
document.getElementById('permissionsModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closePermissionsModal(); });
document.getElementById('assignUserModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeAssignUserModal(); });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>