<?php
/**
 * Duplicate Role Form Page
 * Version: 3.0 - Complete security, zero-trust, and UI enhancements
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - CSRF protection
 * - Role validation
 * - Permission display
 * - User count display
 * - Proper error handling
 * - Pure Tailwind UI
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
    error_log("ZERO-TRUST VIOLATION: Session mismatch in duplicate_role_form.php");
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
    error_log("Permission denied for duplicate_role_form.php. User: {$userId}, Role: {$userRole}");
    die('Permission denied. You cannot duplicate roles.');
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
$roleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($roleId <= 0) {
    die('Invalid role ID');
}

// ============================================
// FETCH ROLE WITH TENANT ISOLATION
// ============================================
try {
    // Check if role_permissions and permissions tables exist
    $tables = [];
    $tblStmt = $pdo->query("SHOW TABLES");
    while ($row = $tblStmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $hasRolePermissions = in_array('role_permissions', $tables);
    $hasPermissions = in_array('permissions', $tables);
    $hasUserRoles = in_array('user_roles', $tables);
    $hasUsers = in_array('users', $tables);
    
    // Fetch role with tenant isolation
    $sql = "
        SELECT r.*
    ";
    
    if ($hasRolePermissions && $hasPermissions) {
        $sql .= ", GROUP_CONCAT(DISTINCT p.code ORDER BY p.code) as permission_codes";
        $sql .= ", COUNT(DISTINCT p.id) as permission_count";
    }
    
    if ($hasUserRoles && $hasUsers) {
        $sql .= ", COUNT(DISTINCT u.id) as user_count";
    }
    
    $sql .= " FROM roles r";
    
    if ($hasRolePermissions && $hasPermissions) {
        $sql .= " LEFT JOIN role_permissions rp ON r.id = rp.role_id AND (rp.tenant_id = ? OR rp.tenant_id IS NULL)";
        $sql .= " LEFT JOIN permissions p ON rp.permission_id = p.id AND (p.tenant_id = ? OR p.tenant_id IS NULL)";
    }
    
    if ($hasUserRoles && $hasUsers) {
        $sql .= " LEFT JOIN user_roles ur ON r.id = ur.role_id AND (ur.tenant_id = ? OR ur.tenant_id IS NULL)";
        $sql .= " LEFT JOIN users u ON ur.user_id = u.id AND u.tenant_id = ?";
    } elseif ($hasUsers) {
        // Check if users table has role_id column
        $colStmt = $pdo->query("SHOW COLUMNS FROM users");
        $userCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('role_id', $userCols)) {
            $sql .= " LEFT JOIN users u ON r.id = u.role_id AND u.tenant_id = ?";
        }
    }
    
    $sql .= " WHERE r.id = ? AND (r.tenant_id = ? OR r.tenant_id IS NULL) AND r.deleted_at IS NULL";
    $sql .= " GROUP BY r.id";
    
    // Build parameters
    $params = [];
    if ($hasRolePermissions && $hasPermissions) {
        $params[] = $tenantId;
        $params[] = $tenantId;
    }
    if ($hasUserRoles && $hasUsers) {
        $params[] = $tenantId;
        $params[] = $tenantId;
    } elseif ($hasUsers && strpos($sql, 'u.tenant_id') !== false) {
        $params[] = $tenantId;
    }
    $params[] = $roleId;
    $params[] = $tenantId;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        die('Role not found or access denied');
    }
    
    // Parse permission codes
    $permissionCodes = [];
    if (!empty($role['permission_codes'])) {
        $permissionCodes = explode(',', $role['permission_codes']);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching role for duplication: " . $e->getMessage());
    die('Error loading role data');
}

$csrfToken = generate_csrf_token();

// ============================================
// PAGE TITLE
// ============================================
$page_title = 'Duplicate Role';

ob_start();
?>

<!-- ============================================ -->
<!-- PAGE HEADER -->
<!-- ============================================ -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-amber-400 uppercase tracking-wider mb-0.5">
            <i class="fas fa-copy mr-1"></i> Roles
        </p>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            Duplicate Role
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
        <div class="text-xs text-slate-500">Source Role</div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="text-sm font-bold text-amber-400"><?php echo (int) ($role['permission_count'] ?? 0); ?></div>
        <div class="text-xs text-slate-500">Permissions</div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="text-sm font-bold text-emerald-400"><?php echo (int) ($role['user_count'] ?? 0); ?></div>
        <div class="text-xs text-slate-500">Users Assigned</div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="text-sm font-bold text-purple-400"><?php echo $role['is_system'] ? 'System' : 'Custom'; ?></div>
        <div class="text-xs text-slate-500">Role Type</div>
    </div>
</div>

<!-- ============================================ -->
<!-- DUPLICATE FORM -->
<!-- ============================================ -->
<?php if (!$role): ?>
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-8 text-center">
        <i class="fas fa-exclamation-triangle text-amber-400 text-3xl mb-3 block"></i>
        <p class="text-slate-400">Role not found or access denied.</p>
        <a href="roles.php" class="inline-block mt-3 text-amber-400 hover:underline">Go back to roles</a>
    </div>
<?php else: ?>

<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
    <!-- Original Role Info -->
    <div class="mb-4 p-3 bg-slate-900/50 rounded-lg border border-slate-700/60">
        <div class="flex items-center gap-2 mb-2">
            <i class="fas fa-info-circle text-amber-400 text-xs"></i>
            <span class="text-xs text-slate-400 font-medium">Original Role</span>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <div>
                <span class="text-xs text-slate-500">Name:</span>
                <span class="text-white font-semibold"><?php echo htmlspecialchars($role['name']); ?></span>
            </div>
            <?php if (!empty($role['description'])): ?>
            <div>
                <span class="text-xs text-slate-500">Description:</span>
                <span class="text-slate-300"><?php echo htmlspecialchars($role['description']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($permissionCodes)): ?>
            <div>
                <span class="text-xs text-slate-500">Permissions:</span>
                <span class="text-emerald-400"><?php echo count($permissionCodes); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($permissionCodes)): ?>
        <div class="mt-2 flex flex-wrap gap-1">
            <?php 
            $displayCodes = array_slice($permissionCodes, 0, 5);
            foreach ($displayCodes as $code): 
            ?>
                <span class="inline-block px-1.5 py-0.5 rounded bg-slate-700/60 text-[10px] text-slate-300 font-mono">
                    <?php echo htmlspecialchars($code); ?>
                </span>
            <?php endforeach; ?>
            <?php if (count($permissionCodes) > 5): ?>
                <span class="inline-block px-1.5 py-0.5 rounded bg-slate-700/60 text-[10px] text-slate-400">
                    +<?php echo count($permissionCodes) - 5; ?> more
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Duplicate Form -->
    <form method="POST" action="roles.php" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="duplicate">
        <input type="hidden" name="id" value="<?php echo $roleId; ?>">
        
        <div>
            <label for="new_name" class="block text-xs font-medium text-slate-400 mb-1.5">
                New Role Name <span class="text-red-400">*</span>
            </label>
            <input type="text" 
                   id="new_name" 
                   name="new_name" 
                   required 
                   placeholder="e.g. <?php echo htmlspecialchars($role['name']); ?> Copy"
                   class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                   autofocus>
            <p class="mt-1 text-xs text-slate-500">Choose a unique name for the duplicated role.</p>
        </div>
        
        <div class="flex flex-wrap gap-3">
            <button type="submit" 
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                <i class="fas fa-copy text-xs"></i> Duplicate Role
            </button>
            <a href="roles.php" 
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>

<!-- ============================================ -->
<!-- HELPER TEXT -->
<!-- ============================================ -->
<div class="bg-slate-800/30 border border-slate-700/60 rounded-xl p-3 flex items-start gap-2">
    <i class="fas fa-lightbulb text-amber-400 text-xs mt-0.5"></i>
    <div class="text-xs text-slate-500">
        <p>Duplicating a role will copy all permissions to the new role. 
        <span class="text-slate-400">Users will not be automatically assigned to the new role.</span></p>
    </div>
</div>

<?php endif; ?>

<!-- ============================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================ -->
<script>
// Auto-generate suggested name
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('new_name');
    if (input && !input.value) {
        const originalName = <?php echo json_encode($role['name'] ?? ''); ?>;
        if (originalName) {
            input.placeholder = originalName + ' Copy';
        }
    }
    
    // Form submission loading state
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs mr-1"></i> Duplicating...';
                submitBtn.disabled = true;
            }
        });
    }
});

// Prevent duplicate submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    let submitted = false;
    
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