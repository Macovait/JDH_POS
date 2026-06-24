<?php
/**
 * Delete Role Form Page
 * Version: 3.0 - Complete security, zero-trust, and UI enhancements
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - CSRF protection
 * - Role validation with tenant isolation
 * - User assignment checking with user list
 * - Protected role checking
 * - Permission count display
 * - Proper error handling
 * - Pure Tailwind UI
 * - Loading states
 * - Confirmation dialog
 */

// ============================================
// ERROR HANDLING
// ============================================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ============================================
// BOOTSTRAP
// ============================================
$pathsFile = __DIR__ . '/../../../src/paths.php';
if (!file_exists($pathsFile)) { 
    $pathsFile = dirname(__DIR__, 4) . '/src/paths.php'; 
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

// ============================================
// GET CURRENT CONTEXT
// ============================================
$userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : (int) ($_SESSION['user_id'] ?? 0);
$tenantId = function_exists('get_current_tenant_id') ? (int) get_current_tenant_id() : (int) ($_SESSION['tenant_id'] ?? 0);
$branchId = function_exists('get_current_branch_id') ? (int) get_current_branch_id() : (int) ($_SESSION['branch_id'] ?? 0);
$userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
$isSuperAdmin = function_exists('is_super_admin') && is_super_admin();

// ============================================
// ZERO-TRUST: Session Tamper Detection
// ============================================
$sessionTenant = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
$sessionUser = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (($sessionTenant !== null && $sessionTenant !== $tenantId) ||
    ($sessionUser !== null && $sessionUser !== $userId)) {
    error_log("ZERO-TRUST VIOLATION: Session mismatch in delete_role_form.php");
    die('Session validation failed');
}

// ============================================
// AUTHENTICATION CHECK
// ============================================
if ($userId <= 0 || $tenantId <= 0 || $branchId <= 0) {
    die('Unauthorized access');
}

// ============================================
// PERMISSION CHECK
// ============================================
$canManage = false;
if ($isSuperAdmin) {
    $canManage = true;
} elseif (function_exists('check_permission') && check_permission('roles.manage')) {
    $canManage = true;
} elseif (in_array(strtolower($userRole), ['admin', 'administrator', 'owner', 'super_admin'])) {
    $canManage = true;
}

if (!$canManage) {
    error_log("Permission denied for delete_role_form.php. User: {$userId}, Role: {$userRole}");
    die('Permission denied. You cannot delete roles.');
}

// ============================================
// DATABASE CONNECTION
// ============================================
$pdo = get_db_connection();
if (!$pdo) {
    die('Database connection failed');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// ============================================
// GET ROLE ID
// ============================================
$roleId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_GET['role_id']) ? (int) $_GET['role_id'] : 0);

if ($roleId <= 0) {
    die('Invalid role ID');
}

// ============================================
// FETCH ROLE WITH TENANT ISOLATION
// ============================================
try {
    // Check if tables exist
    $tables = [];
    $tblStmt = $pdo->query("SHOW TABLES");
    while ($row = $tblStmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $hasUserRoles = in_array('user_roles', $tables);
    $hasRolePermissions = in_array('role_permissions', $tables);
    $hasPermissions = in_array('permissions', $tables);
    $hasUsersRoleId = false;
    
    if (in_array('users', $tables)) {
        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM users");
            $userCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
            $hasUsersRoleId = in_array('role_id', $userCols);
        } catch (PDOException $e) {}
    }
    
    // Fetch role with tenant isolation
    $stmt = $pdo->prepare("
        SELECT id, name, description, is_system, tenant_id, level
        FROM roles 
        WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ");
    $stmt->execute([$roleId, $tenantId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        die('Role not found or access denied');
    }
    
    // Get user count with role isolation and list of users
    $userCount = 0;
    $userList = [];
    
    if ($hasUserRoles) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, GROUP_CONCAT(u.name) as user_names
            FROM user_roles ur
            JOIN users u ON ur.user_id = u.id
            WHERE ur.role_id = ? AND (ur.tenant_id = ? OR ur.tenant_id IS NULL) AND u.tenant_id = ? AND u.deleted_at IS NULL
        ");
        $stmt->execute([$roleId, $tenantId, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $userCount = (int) ($result['count'] ?? 0);
        if ($userCount > 0 && !empty($result['user_names'])) {
            $userList = explode(',', $result['user_names']);
        }
    } elseif ($hasUsersRoleId) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, GROUP_CONCAT(name) as user_names
            FROM users 
            WHERE role_id = ? AND tenant_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$roleId, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $userCount = (int) ($result['count'] ?? 0);
        if ($userCount > 0 && !empty($result['user_names'])) {
            $userList = explode(',', $result['user_names']);
        }
    }
    
    $role['user_count'] = $userCount;
    $role['user_list'] = $userList;
    
    // Get permission count with tenant isolation
    if ($hasRolePermissions && $hasPermissions) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND (rp.tenant_id = ? OR rp.tenant_id IS NULL) AND (p.tenant_id = ? OR p.tenant_id IS NULL)
        ");
        $stmt->execute([$roleId, $tenantId, $tenantId]);
        $role['permission_count'] = (int) $stmt->fetchColumn();
    } else {
        $role['permission_count'] = 0;
    }
    
} catch (PDOException $e) {
    error_log("Error fetching role for deletion: " . $e->getMessage());
    die('Error loading role data');
}

// ============================================
// CHECK IF ROLE IS PROTECTED
// ============================================
$protectedRoles = ['super admin', 'administrator', 'admin', 'owner', 'manager'];
$isProtected = in_array(strtolower($role['name']), $protectedRoles) || ($role['is_system'] == 1);
$canDelete = !$isProtected && $role['user_count'] == 0;

$csrfToken = generate_csrf_token();

// ============================================
// PAGE TITLE
// ============================================
$page_title = 'Delete Role';

ob_start();
?>

<!-- ============================================ -->
<!-- PAGE HEADER -->
<!-- ============================================ -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-red-400 uppercase tracking-wider mb-0.5">
            <i class="fas fa-trash mr-1"></i> Roles
        </p>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            Delete Role
            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700/60 text-slate-400">
                <?php echo htmlspecialchars($role['name']); ?>
            </span>
        </h1>
    </div>
    <div>
        <a href="roles.php" 
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to Roles
        </a>
    </div>
</div>

<!-- ============================================ -->
<!-- ROLE INFO CARDS -->
<!-- ============================================ -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="text-sm font-bold text-white"><?php echo htmlspecialchars($role['name']); ?></div>
        <div class="text-xs text-slate-500">Role Name</div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="text-sm font-bold text-amber-400"><?php echo (int) ($role['permission_count'] ?? 0); ?></div>
        <div class="text-xs text-slate-500">Permissions</div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="text-sm font-bold <?php echo $role['user_count'] > 0 ? 'text-red-400' : 'text-emerald-400'; ?>">
            <?php echo (int) ($role['user_count'] ?? 0); ?>
        </div>
        <div class="text-xs text-slate-500">Users Assigned</div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="text-sm font-bold <?php echo $isProtected ? 'text-red-400' : 'text-purple-400'; ?>">
            <?php echo $isProtected ? '🔒 Protected' : 'Custom'; ?>
        </div>
        <div class="text-xs text-slate-500">Role Type</div>
    </div>
</div>

<!-- ============================================ -->
<!-- DELETE CONFIRMATION -->
<!-- ============================================ -->
<?php if (!$role): ?>
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-8 text-center">
        <i class="fas fa-exclamation-triangle text-amber-400 text-3xl mb-3 block"></i>
        <p class="text-slate-400">Role not found or access denied.</p>
        <a href="roles.php" class="inline-block mt-3 text-amber-400 hover:underline">Go back to roles</a>
    </div>
<?php else: ?>

<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
    <!-- Warning Section -->
    <?php if ($isProtected): ?>
        <div class="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400">
            <div class="flex items-start gap-2">
                <i class="fas fa-shield-alt text-sm mt-0.5"></i>
                <div>
                    <p class="font-semibold text-sm">Cannot Delete Protected Role</p>
                    <p class="text-xs text-red-400/80">This is a system role and cannot be deleted.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($role['user_count'] > 0): ?>
        <div class="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400">
            <div class="flex items-start gap-2">
                <i class="fas fa-users text-sm mt-0.5"></i>
                <div>
                    <p class="font-semibold text-sm">Role Has Assigned Users</p>
                    <p class="text-xs text-red-400/80">
                        This role is assigned to <strong><?php echo $role['user_count']; ?></strong> user(s):
                        <?php 
                        $userNames = array_slice($role['user_list'], 0, 5);
                        echo htmlspecialchars(implode(', ', $userNames));
                        if (count($role['user_list']) > 5) {
                            echo ' and ' . (count($role['user_list']) - 5) . ' more';
                        }
                        ?>
                    </p>
                    <p class="text-xs text-red-400/60 mt-1">Please reassign these users before deleting.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($role['permission_count'] > 0): ?>
        <div class="mb-4 p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg text-amber-400">
            <div class="flex items-start gap-2">
                <i class="fas fa-key text-sm mt-0.5"></i>
                <div>
                    <p class="font-semibold text-sm">Permissions Will Be Removed</p>
                    <p class="text-xs text-amber-400/80">
                        This role has <strong><?php echo $role['permission_count']; ?></strong> permission(s) that will be removed.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Delete Form -->
    <?php if ($canDelete): ?>
        <form method="POST" action="roles.php" class="space-y-4" id="deleteForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="role_id" value="<?php echo $roleId; ?>">
            
            <div class="p-3 bg-red-500/5 border border-red-500/20 rounded-lg">
                <p class="text-slate-300 text-sm">
                    Are you sure you want to delete the role 
                    <span class="text-white font-semibold"><?php echo htmlspecialchars($role['name']); ?></span>?
                </p>
                <p class="text-slate-500 text-xs mt-1">This action cannot be undone.</p>
            </div>
            
            <div class="flex flex-wrap gap-3">
                <button type="submit" 
                        id="deleteBtn"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-semibold hover:bg-red-500/25 transition-colors">
                    <i class="fas fa-trash text-xs"></i> Yes, Delete Role
                </button>
                <a href="roles.php" 
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="flex flex-wrap gap-3">
            <a href="roles.php" 
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-arrow-left text-xs"></i> Back to Roles
            </a>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<!-- ============================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('deleteForm');
    const deleteBtn = document.getElementById('deleteBtn');
    
    if (form && deleteBtn) {
        // Confirm before deletion
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs mr-1"></i> Deleting...';
            deleteBtn.disabled = true;
        });
    }
});

// Prevent duplicate submission
let submitted = false;
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('deleteForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (submitted) {
                e.preventDefault();
                return;
            }
            submitted = true;
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>