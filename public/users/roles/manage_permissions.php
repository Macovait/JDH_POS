<?php
/**
 * Manage Permissions - Pure Tailwind
 * Version: 3.0 - Complete security, zero-trust, and UI enhancements
 */

require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();

// ============================================
// ZERO-TRUST: Verify tenant context
// ============================================
$tenant_id = function_exists('get_current_tenant_id') ? (int) get_current_tenant_id() : (int) ($_SESSION['tenant_id'] ?? 0);
$user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : (int) ($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';

if ($tenant_id <= 0 || $user_id <= 0) {
    die('Unauthorized access');
}

// ============================================
// GET ROLE ID WITH VALIDATION
// ============================================
$role_id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_GET['role_id']) ? (int) $_GET['role_id'] : 0);

if ($role_id <= 0) {
    die('Invalid role ID');
}

// ============================================
// DATABASE CONNECTION
// ============================================
$pdo = get_db_connection();
if (!$pdo) {
    die('Database connection failed');
}

// ============================================
// FETCH ROLE WITH TENANT ISOLATION
// ============================================
$role = null;
$assigned = [];

try {
    // Fetch role with tenant isolation
    $stmt = $pdo->prepare('
        SELECT * FROM roles 
        WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ');
    $stmt->execute([$role_id, $tenant_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($role) {
        // Fetch assigned permissions
        $stmt2 = $pdo->prepare('
            SELECT p.code, p.id 
            FROM permissions p 
            JOIN role_permissions rp ON p.id = rp.permission_id 
            WHERE rp.role_id = ? AND (rp.tenant_id = ? OR rp.tenant_id IS NULL)
        ');
        $stmt2->execute([$role_id, $tenant_id]);
        $assigned = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching role permissions: " . $e->getMessage());
    $role = null;
}

// ============================================
// PAGE TITLE
// ============================================
$page_title = $role ? 'Manage Permissions: ' . htmlspecialchars($role['name']) : 'Permissions';

// ============================================
// FETCH ALL PERMISSIONS WITH TENANT ISOLATION
// ============================================
$permissions = [];
$grouped = [];
$assignedIds = [];

try {
    // Get assigned permission IDs
    foreach ($assigned as $a) {
        $assignedIds[] = $a['id'];
    }

    // Fetch all permissions
    $stmt = $pdo->prepare('
        SELECT * FROM permissions 
        WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
        ORDER BY code
    ');
    $stmt->execute([$tenant_id]);
    $permissions = $stmt->fetchAll();

    // Group permissions
    foreach ($permissions as $perm) {
        $cat = explode('.', $perm['code'])[0] ?? 'other';
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][] = $perm;
    }

    // Sort categories
    ksort($grouped);
} catch (PDOException $e) {
    error_log("Error fetching permissions: " . $e->getMessage());
    $permissions = [];
    $grouped = [];
}

// ============================================
// PERMISSION ICONS
// ============================================
$icons = [
    'pos' => 'fa-cash-register',
    'sales' => 'fa-chart-line',
    'products' => 'fa-boxes',
    'inventory' => 'fa-warehouse',
    'customers' => 'fa-users',
    'users' => 'fa-user-shield',
    'reports' => 'fa-file-alt',
    'expenses' => 'fa-wallet',
    'settings' => 'fa-cog',
    'branches' => 'fa-building',
    'hr' => 'fa-users-cog',
    'delivery' => 'fa-truck',
    'kitchen' => 'fa-fire',
    'accounting' => 'fa-calculator',
    'audit' => 'fa-clipboard-list',
    'other' => 'fa-key'
];

$csrf_token = generate_csrf_token();

ob_start();
?>

<!-- ============================================ -->
<!-- PAGE HEADER -->
<!-- ============================================ -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-amber-400 uppercase tracking-wider mb-0.5">
            <i class="fas fa-key mr-1"></i> Permissions
        </p>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <?php if ($role): ?>
                <span class="text-slate-400 font-normal">Role:</span>
                <?php echo htmlspecialchars($role['name']); ?>
                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700/60 text-slate-400">
                    <?php echo count($assigned); ?> permissions
                </span>
            <?php else: ?>
                Permissions
            <?php endif; ?>
        </h1>
    </div>
    <div class="flex items-center gap-2">
        <a href="roles.php" 
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to Roles
        </a>
    </div>
</div>

<!-- ============================================ -->
<!-- PERMISSION STATS -->
<!-- ============================================ -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="text-sm font-bold text-white"><?php echo count($assigned); ?></div>
        <div class="text-xs text-slate-500">Assigned Permissions</div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="text-sm font-bold text-amber-400"><?php echo count($permissions); ?></div>
        <div class="text-xs text-slate-500">Total Available</div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="text-sm font-bold text-emerald-400"><?php echo count($grouped); ?></div>
        <div class="text-xs text-slate-500">Categories</div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="text-sm font-bold text-purple-400">
            <?php echo $role && $role['is_system'] ? 'System' : 'Custom'; ?>
        </div>
        <div class="text-xs text-slate-500">Role Type</div>
    </div>
</div>

<!-- ============================================ -->
<!-- PERMISSIONS FORM -->
<!-- ============================================ -->
<?php if (!$role): ?>
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-8 text-center">
        <i class="fas fa-exclamation-triangle text-amber-400 text-3xl mb-3 block"></i>
        <p class="text-slate-400">Role not found or access denied.</p>
        <a href="roles.php" class="inline-block mt-3 text-amber-400 hover:underline">Go back to roles</a>
    </div>
<?php else: ?>

<form id="permForm" method="POST" action="roles.php" class="space-y-4">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="id" value="<?php echo $role_id; ?>">
    
    <!-- Bulk Actions -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 flex flex-wrap items-center gap-2">
        <span class="text-xs text-slate-500 font-medium mr-2">Bulk Actions:</span>
        <button type="button" onclick="selectAll()" 
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium hover:bg-emerald-500/20 transition-colors">
            <i class="fas fa-check-double text-xs"></i> Select All
        </button>
        <button type="button" onclick="deselectAll()" 
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times text-xs"></i> Deselect All
        </button>
        <button type="button" onclick="selectCategory('pos')" 
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-blue-500/10 border border-blue-500/30 text-blue-400 text-xs font-medium hover:bg-blue-500/20 transition-colors">
            <i class="fas fa-cash-register text-xs"></i> POS
        </button>
        <button type="button" onclick="selectCategory('inventory')" 
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-green-500/10 border border-green-500/30 text-green-400 text-xs font-medium hover:bg-green-500/20 transition-colors">
            <i class="fas fa-warehouse text-xs"></i> Inventory
        </button>
        <button type="button" onclick="selectCategory('reports')" 
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-purple-500/10 border border-purple-500/30 text-purple-400 text-xs font-medium hover:bg-purple-500/20 transition-colors">
            <i class="fas fa-file-alt text-xs"></i> Reports
        </button>
        <div class="ml-auto text-xs text-slate-500">
            <span id="selectedCount">0</span> selected
        </div>
    </div>

    <!-- Permissions Grid -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            <?php foreach ($grouped as $cat => $perms): 
                $catIcon = $icons[strtolower($cat)] ?? 'fa-key';
                $catAssigned = 0;
                foreach ($perms as $p) {
                    if (in_array($p['id'], $assignedIds)) {
                        $catAssigned++;
                    }
                }
                $totalPerms = count($perms);
                $allChecked = $catAssigned === $totalPerms && $totalPerms > 0;
            ?>
                <div class="bg-slate-900/50 border border-slate-700/60 rounded-xl overflow-hidden">
                    <!-- Category Header -->
                    <div class="flex items-center justify-between px-3 py-2 bg-slate-800/50 border-b border-slate-700/60">
                        <div class="flex items-center gap-1.5">
                            <i class="fas <?php echo $catIcon; ?> text-amber-400 text-xs"></i>
                            <span class="text-xs font-semibold text-white capitalize"><?php echo htmlspecialchars($cat); ?></span>
                            <span class="text-[10px] text-slate-500">(<?php echo $totalPerms; ?>)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] text-slate-500">
                                <?php echo $catAssigned; ?>/<?php echo $totalPerms; ?>
                            </span>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" 
                                       class="cat-all w-3.5 h-3.5 rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500 focus:ring-1" 
                                       data-cat="<?php echo htmlspecialchars($cat); ?>"
                                       <?php echo $allChecked ? 'checked' : ''; ?>
                                       title="Select all <?php echo htmlspecialchars($cat); ?> permissions">
                            </label>
                        </div>
                    </div>
                    
                    <!-- Permission Items -->
                    <div class="p-2 space-y-0.5 max-h-48 overflow-y-auto scrollbar-thin" data-cat="<?php echo htmlspecialchars($cat); ?>">
                        <?php foreach ($perms as $p): 
                            $isChecked = in_array($p['id'], $assignedIds);
                        ?>
                            <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-700/30 px-1.5 py-1 rounded transition-colors group">
                                <input type="checkbox" 
                                       name="perms[]" 
                                       value="<?php echo $p['id']; ?>" 
                                       class="perm-cb w-3.5 h-3.5 rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500 focus:ring-1 shrink-0"
                                       <?php echo $isChecked ? 'checked' : ''; ?>>
                                <span class="text-[11px] text-slate-300 font-mono truncate group-hover:text-white transition-colors">
                                    <?php echo htmlspecialchars($p['code']); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($grouped)): ?>
            <div class="text-center py-8 text-slate-500">
                <i class="fas fa-key text-2xl mb-2 block text-slate-600"></i>
                No permissions found for this branch.
            </div>
        <?php endif; ?>
    </div>

    <!-- Form Actions -->
    <div class="flex flex-wrap gap-3">
        <button type="submit" 
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-save text-xs"></i> Save Permissions
        </button>
        <a href="roles.php" 
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            Cancel
        </a>
        <div class="ml-auto text-xs text-slate-500 flex items-center">
            <i class="fas fa-info-circle mr-1"></i>
            Changes will affect all users with this role
        </div>
    </div>
</form>
<?php endif; ?>

<!-- ============================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================ -->
<script>
// ============================================
// PERMISSION SELECTION HELPERS
// ============================================

function updateSelectedCount() {
    const count = document.querySelectorAll('.perm-cb:checked').length;
    const total = document.querySelectorAll('.perm-cb').length;
    const el = document.getElementById('selectedCount');
    if (el) el.textContent = count + '/' + total;
}

function updateCategorySelectAll() {
    document.querySelectorAll('[data-cat]').forEach(container => {
        const cat = container.dataset.cat;
        const checkboxes = container.querySelectorAll('.perm-cb');
        const checked = container.querySelectorAll('.perm-cb:checked');
        const catAll = document.querySelector(`.cat-all[data-cat="${cat}"]`);
        if (catAll) {
            catAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
        }
    });
}

function selectAll() {
    document.querySelectorAll('.perm-cb').forEach(c => c.checked = true);
    document.querySelectorAll('.cat-all').forEach(c => c.checked = true);
    updateSelectedCount();
}

function deselectAll() {
    document.querySelectorAll('.perm-cb').forEach(c => c.checked = false);
    document.querySelectorAll('.cat-all').forEach(c => c.checked = false);
    updateSelectedCount();
}

function selectCategory(category) {
    document.querySelectorAll('.perm-cb').forEach(c => {
        const container = c.closest('[data-cat]');
        if (container && container.dataset.cat === category) {
            c.checked = true;
        }
    });
    updateCategorySelectAll();
    updateSelectedCount();
}

// Category "Select All" toggle
document.querySelectorAll('.cat-all').forEach(cb => {
    cb.addEventListener('change', function() {
        const cat = this.dataset.cat;
        document.querySelectorAll(`.perm-cb`).forEach(c => {
            const container = c.closest('[data-cat]');
            if (container && container.dataset.cat === cat) {
                c.checked = this.checked;
            }
        });
        updateSelectedCount();
    });
});

// Individual permission toggle
document.querySelectorAll('.perm-cb').forEach(cb => {
    cb.addEventListener('change', function() {
        const container = this.closest('[data-cat]');
        if (!container) return;
        
        const cat = container.dataset.cat;
        const checkboxes = container.querySelectorAll('.perm-cb');
        const checked = container.querySelectorAll('.perm-cb:checked');
        const catAll = document.querySelector(`.cat-all[data-cat="${cat}"]`);
        
        if (catAll) {
            catAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
        }
        updateSelectedCount();
    });
});

// Initialize count
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
});

// ============================================
// FORM SUBMISSION WITH LOADING STATE
// ============================================
document.getElementById('permForm')?.addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs mr-1"></i> Saving...';
        submitBtn.disabled = true;
    }
});
</script>

<!-- ============================================ -->
<!-- STYLES -->
<!-- ============================================ -->
<style>
.scrollbar-thin::-webkit-scrollbar { width: 3px; }
.scrollbar-thin::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.03); border-radius: 10px; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
.scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #d97706; }
</style>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>