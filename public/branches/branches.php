<?php
/**
 * Branches management page for Jakababa POS
 * 
 * Manages branch locations, inventory initialization, and branch-specific settings.
 */

require_once __DIR__ . '/../../src/auth.php';
require_login();

// Check for branches management permission
if (!check_permission('branches.manage') && !is_super_admin()) {
    enforce_permission('branches.manage');
}

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';

$pdo = get_db_connection();

// Get tenant_id for business types - MUST be from session, not hardcoded
$tenant_id = get_current_tenant_id();

if (!$tenant_id) {
    http_response_code(403);
    exit('Error: Company context not found. Please log in again.');
}

// Get available business types
$business_types = [];
try {
    $bt_stmt = $pdo->query("SELECT id, name, code FROM business_types WHERE is_active = 1 ORDER BY sort_order, name");
    $business_types = $bt_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $business_types = [];
}

// Get current user info for navbar
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$current_branch_id = (int) ($_SESSION['user']['branch_id'] ?? 1);

// Handle POST actions (delete, toggle, init_inventory only; create/update moved to branch_form.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect_msg = '';
    $redirect_type = 'success';

    switch ($action) {
        case 'delete':
            if (is_super_admin()) {
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0 && $id != $current_branch_id) {
                    try {
                        $check_users = $pdo->prepare('SELECT COUNT(*) FROM users WHERE branch_id = ? AND tenant_id = ?');
                        $check_users->execute([$id, $tenant_id]);
                        $check_sales = $pdo->prepare('SELECT COUNT(*) FROM sales WHERE branch_id = ? AND tenant_id = ?');
                        $check_sales->execute([$id, $tenant_id]);
                        $check_inventory = $pdo->prepare('SELECT COUNT(*) FROM inventory WHERE branch_id = ? AND tenant_id = ? AND stock > 0');
                        $check_inventory->execute([$id, $tenant_id]);
                        $check_user_branches = $pdo->prepare('SELECT COUNT(*) FROM user_branches WHERE branch_id = ?');
                        $check_user_branches->execute([$id]);
                        if ($check_users->fetchColumn() > 0 || $check_sales->fetchColumn() > 0 || $check_inventory->fetchColumn() > 0 || $check_user_branches->fetchColumn() > 0) {
                            $pdo->prepare('UPDATE branches SET active = 0 WHERE id = ? AND tenant_id = ?')->execute([$id, $tenant_id]);
                            $redirect_msg = 'Branch has been deactivated (has associated data)';
                        } else {
                            $pdo->prepare('DELETE FROM inventory WHERE branch_id = ? AND tenant_id = ?')->execute([$id, $tenant_id]);
                            $pdo->prepare('DELETE FROM user_branches WHERE branch_id = ?')->execute([$id]);
                            $pdo->prepare('DELETE FROM branches WHERE id = ? AND tenant_id = ?')->execute([$id, $tenant_id]);
                            $redirect_msg = 'Branch deleted successfully';
                        }
                        log_activity($user_id, 'branch.deleted', ['branch_id' => $id], null, get_current_tenant_id());
                    } catch (PDOException $e) {
                        error_log("Error deleting branch: " . $e->getMessage());
                        $redirect_msg = 'Failed to delete branch';
                        $redirect_type = 'error';
                    }
                } else {
                    $redirect_msg = 'Cannot delete the current branch';
                    $redirect_type = 'error';
                }
            }
            break;

        case 'toggle_status':
            if (is_super_admin() || $user_role === 'Admin') {
                $id = intval($_POST['id'] ?? 0);
                $current_status = intval($_POST['current_status'] ?? 1);
                $new_status = $current_status ? 0 : 1;
                if ($id > 0) {
                    if ($id == $current_branch_id && $new_status == 0) {
                        $redirect_msg = 'Cannot deactivate your current branch';
                        $redirect_type = 'error';
                    } else {
                        try {
                            $pdo->prepare('UPDATE branches SET active = ? WHERE id = ? AND tenant_id = ?')->execute([$new_status, $id, $tenant_id]);
                            $redirect_msg = 'Branch ' . ($new_status ? 'activated' : 'deactivated') . ' successfully';
                            log_activity($user_id, 'branch.status_changed', ['branch_id' => $id, 'new_status' => $new_status], get_current_tenant_id());
                        } catch (PDOException $e) {
                            error_log("Error toggling branch status: " . $e->getMessage());
                            $redirect_msg = 'Failed to update branch status';
                            $redirect_type = 'error';
                        }
                    }
                }
            }
            break;

        case 'init_inventory':
            if (is_super_admin() || $user_role === 'Admin') {
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $stmt = $pdo->prepare("INSERT IGNORE INTO inventory (tenant_id, product_id, branch_id, stock, reorder_level) SELECT ?, id, ?, 0, 0 FROM products WHERE tenant_id = ? AND deleted_at IS NULL");
                        $stmt->execute([$tenant_id, $id, $tenant_id]);
                        $redirect_msg = 'Inventory initialized successfully for this branch';
                        log_activity($user_id, 'branch.inventory_initialized', ['branch_id' => $id], null, get_current_tenant_id());
                    } catch (PDOException $e) {
                        error_log("Error initializing inventory: " . $e->getMessage());
                        $redirect_msg = 'Failed to initialize inventory: ' . $e->getMessage();
                        $redirect_type = 'error';
                    }
                }
            }
            break;
    }

    if ($redirect_msg) {
        $param = $redirect_type === 'error' ? 'error' : 'success';
        header('Location: branches.php?' . $param . '=' . urlencode($redirect_msg));
        exit;
    }
}

// Read flash messages from GET
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// Get search/filter parameters
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all'; // all, active, inactive
$business_type_filter = $_GET['business_type'] ?? '';

// Build query with enhanced stats
$sql = "
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND tenant_id = b.tenant_id) as user_count,
        (SELECT COUNT(DISTINCT user_id) FROM user_branches WHERE branch_id = b.id) as assigned_user_count,
        (SELECT COUNT(*) FROM inventory WHERE branch_id = b.id AND tenant_id = b.tenant_id) as product_count,
        (SELECT COALESCE(SUM(stock), 0) FROM inventory WHERE branch_id = b.id AND tenant_id = b.tenant_id) as total_stock,
        (SELECT COALESCE(SUM(stock * cost_price), 0) 
         FROM inventory i 
         JOIN products p ON i.product_id = p.id AND i.tenant_id = p.tenant_id
         WHERE i.branch_id = b.id AND i.tenant_id = b.tenant_id) as inventory_value,
        (SELECT COUNT(*) FROM sales WHERE branch_id = b.id AND tenant_id = b.tenant_id AND DATE(created_at) = CURDATE()) as today_sales,
        (SELECT COALESCE(SUM(total), 0) FROM sales WHERE branch_id = b.id AND tenant_id = b.tenant_id AND DATE(created_at) = CURDATE()) as today_revenue,
        (SELECT COUNT(*) FROM sales WHERE branch_id = b.id AND tenant_id = b.tenant_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as monthly_sales
    FROM branches b
    WHERE b.tenant_id = ?";

$params = [$tenant_id];

if (!empty($search)) {
    $sql .= " AND (b.name LIKE ? OR b.code LIKE ? OR b.address LIKE ? OR b.phone LIKE ? OR b.manager LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if ($filter === 'active') {
    $sql .= " AND b.active = 1";
} elseif ($filter === 'inactive') {
    $sql .= " AND b.active = 0";
}

// Filter by business_type if explicitly selected in the filter UI
if (!empty($business_type_filter)) {
    $sql .= " AND b.business_type_id = ?";
    $params[] = (int) $business_type_filter;
}

$sql .= " ORDER BY b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$branches = $stmt->fetchAll();

// Get statistics with better contrast
$stats = [
    'total' => count($branches),
    'active' => count(array_filter($branches, fn($b) => isset($b['active']) && $b['active'])),
    'inactive' => count(array_filter($branches, fn($b) => isset($b['active']) && !$b['active'])),
    'total_products' => array_sum(array_column($branches, 'product_count')),
    'total_stock' => array_sum(array_column($branches, 'total_stock')),
    'total_users' => array_sum(array_column($branches, 'user_count')),
    'total_assigned_users' => array_sum(array_column($branches, 'assigned_user_count')),
    'total_inventory_value' => array_sum(array_column($branches, 'inventory_value')),
    'total_today_revenue' => array_sum(array_column($branches, 'today_revenue')),
];

$page_title = 'Branch Management';
$current_year = date('Y');
$can_delete = is_super_admin();
ob_start();
?>


<div>

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Settings</p>
            <h1 class="text-lg font-bold text-white">Branch Management</h1>
            <p class="text-sm text-slate-500 mt-0.5">Manage store locations and their settings</p>
        </div>
        <?php if (is_super_admin() || $user_role === 'Admin'): ?>
            <a href="branch_form.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                <i class="fas fa-plus text-xs"></i> Add New Branch
            </a>
        <?php endif; ?>
    </div>

        <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="mb-4 flex items-center gap-2 px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-xl text-sm">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="mb-4 flex items-center gap-2 px-4 py-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl text-sm">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 hover:border-amber-500/30 transition-colors">
            <div class="flex items-center justify-between mb-1"><span class="text-xs text-slate-500">Total Branches</span><i class="fas fa-store text-amber-400 text-xs"></i></div>
            <p class="text-xl font-bold text-white"><?php echo $stats['total']; ?></p>
            <p class="text-xs text-slate-500 mt-0.5"><?php echo $stats['active']; ?> active, <?php echo $stats['inactive']; ?> inactive</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 hover:border-emerald-500/30 transition-colors">
            <div class="flex items-center justify-between mb-1"><span class="text-xs text-slate-500">Total Staff</span><i class="fas fa-users text-emerald-400 text-xs"></i></div>
            <p class="text-xl font-bold text-white"><?php echo $stats['total_users']; ?></p>
            <p class="text-xs text-slate-500 mt-0.5"><?php echo $stats['total_assigned_users']; ?> multi-branch</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 hover:border-amber-500/30 transition-colors">
            <div class="flex items-center justify-between mb-1"><span class="text-xs text-slate-500">Total Stock</span><i class="fas fa-cubes text-amber-400 text-xs"></i></div>
            <p class="text-xl font-bold text-white"><?php echo number_format($stats['total_stock']); ?></p>
            <p class="text-xs text-slate-500 mt-0.5"><?php echo $stats['total_products']; ?> products</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 hover:border-emerald-500/30 transition-colors">
            <div class="flex items-center justify-between mb-1"><span class="text-xs text-slate-500">Today's Revenue</span><i class="fas fa-chart-line text-emerald-400 text-xs"></i></div>
            <p class="text-xl font-bold text-white">KSh <?php echo number_format($stats['total_today_revenue'], 0); ?></p>
            <p class="text-xs text-slate-500 mt-0.5">Across all branches</p>
        </div>
    </div>

    <!-- Status Tabs -->
    <?php $active_tab = $filter ?: 'all'; ?>
    <div class="flex items-center gap-1 mb-4 border-b border-slate-700/60 pb-2 overflow-x-auto">
        <a href="?<?php echo http_build_query(array_diff_key($_GET, array_flip(['filter','page']))); ?>"
           class="px-3 py-1.5 text-xs rounded-lg whitespace-nowrap font-medium transition-colors <?php echo $active_tab === 'all' ? 'bg-amber-500/20 border border-amber-500/40 text-amber-400' : 'text-slate-400 hover:text-white hover:bg-slate-800 border border-transparent'; ?>">
            All (<?php echo $stats['total']; ?>)
        </a>
        <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, array_flip(['filter','page'])), ['filter'=>'active'])); ?>"
           class="px-3 py-1.5 text-xs rounded-lg whitespace-nowrap font-medium transition-colors <?php echo $active_tab === 'active' ? 'bg-emerald-500/20 border border-emerald-500/40 text-emerald-400' : 'text-slate-400 hover:text-white hover:bg-slate-800 border border-transparent'; ?>">
            Active (<?php echo $stats['active']; ?>)
        </a>
        <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, array_flip(['filter','page'])), ['filter'=>'inactive'])); ?>"
           class="px-3 py-1.5 text-xs rounded-lg whitespace-nowrap font-medium transition-colors <?php echo $active_tab === 'inactive' ? 'bg-slate-600/50 border border-slate-500/40 text-slate-300' : 'text-slate-400 hover:text-white hover:bg-slate-800 border border-transparent'; ?>">
            Inactive (<?php echo $stats['inactive']; ?>)
        </a>
        <div class="ml-auto">
            <select onchange="window.location.href=this.value" class="px-2 py-1 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
                <option value="">Sort by</option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'name_asc'])); ?>">Name A-Z</option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'name_desc'])); ?>">Name Z-A</option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'recent'])); ?>">Recently Updated</option>
            </select>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
        <form method="GET" class="flex flex-wrap gap-2">
            <?php foreach (array_diff_key($_GET, array_flip(['search','filter','sort','page'])) as $k => $v): ?>
                <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
            <?php endforeach; ?>
            <div class="flex-1 min-w-[180px] relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search branches..."
                    class="w-full pl-8 pr-4 py-1.5 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
            </div>
            <select name="business_type" class="px-3 py-1.5 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
                <option value="">All Business Types</option>
                <?php foreach ($business_types as $bt): ?>
                    <option value="<?php echo $bt['id']; ?>" <?php echo $business_type_filter == $bt['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($bt['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
                <i class="fas fa-filter text-xs"></i> Filter
            </button>
            <?php if (!empty($search) || $filter !== 'all' || !empty($business_type_filter)): ?>
                <a href="branches.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                    <i class="fas fa-times text-xs"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Branches Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/60 border-b border-slate-700/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Contact</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Manager</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Staff</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Products</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Today</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                        <?php if (empty($branches)): ?>
                            <tr>
                                <td colspan="10" class="px-4 py-10 text-center">
                                    <i class="fas fa-store-slash text-3xl text-slate-600 mb-2"></i>
                                    <p class="text-slate-500 text-sm">No branches found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($branches as $branch): ?>
                                <?php $is_current = $branch['id'] == $current_branch_id; ?>
                                <tr class="hover:bg-slate-700/20 transition-colors <?php echo $is_current ? 'bg-amber-900/10 border-l-2 border-amber-500' : ''; ?>">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-amber-500/15 flex items-center justify-center">
                                                <span class="text-amber-400 font-bold text-sm">
                                                    <?php echo strtoupper(substr($branch['name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <div class="font-medium text-white text-sm">
                                                    <?php echo htmlspecialchars($branch['name']); ?>
                                                    <?php if ($is_current): ?>
                                                        <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-semibold bg-amber-500/20 border border-amber-500/40 text-amber-400 rounded">Current</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs text-slate-500">ID: #<?php echo $branch['id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($branch['code'])): ?>
                                            <span class="px-1.5 py-0.5 text-[11px] font-mono font-semibold bg-blue-500/15 border border-blue-500/30 text-blue-400 rounded"><?php echo htmlspecialchars($branch['code']); ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-600">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php 
                                        // Show business type name from the business_types table if we have business_type_id
                                        if (!empty($branch['business_type_id'])) {
                                            // Try to get business type name from the $business_types array
                                            $bt_name = '';
                                            foreach ($business_types as $bt) {
                                                if ($bt['id'] == $branch['business_type_id']) {
                                                    $bt_name = $bt['name'];
                                                    break;
                                                }
                                            }
                                            if ($bt_name) {
                                                echo '<span class="text-[10px] bg-violet-500/15 border border-violet-500/30 text-violet-400 px-1.5 py-0.5 rounded">' . htmlspecialchars($bt_name) . '</span>';
                                            } else {
                                                echo '<span class="text-slate-600">—</span>';
                                            }
                                        } else {
                                            echo '<span class="text-slate-600">—</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="space-y-1">
                                            <?php if (!empty($branch['address'])): ?>
                                                <div class="text-xs text-slate-300"><i class="fas fa-map-pin text-slate-500 w-3 mr-1"></i><?php echo htmlspecialchars($branch['address']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($branch['phone'])): ?>
                                                <div class="text-xs text-slate-300"><i class="fas fa-phone text-slate-500 w-3 mr-1"></i><?php echo htmlspecialchars($branch['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($branch['email'])): ?>
                                                <div class="text-xs text-slate-300"><i class="fas fa-envelope text-slate-500 w-3 mr-1"></i><?php echo htmlspecialchars($branch['email']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($branch['manager'])): ?>
                                            <span class="text-sm text-slate-300"><?php echo htmlspecialchars($branch['manager']); ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-600">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>
                                            <span class="font-semibold text-amber-400"><?php echo $branch['user_count']; ?></span>
                                            <span class="text-slate-500 text-xs"> assigned</span>
                                        </div>
                                        <?php if ($branch['assigned_user_count'] > $branch['user_count']): ?>
                                            <div class="text-xs text-emerald-400"><i class="fas fa-users mr-1"></i>+<?php echo $branch['assigned_user_count'] - $branch['user_count']; ?> multi</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>
                                            <span class="font-semibold text-white text-sm"><?php echo $branch['product_count']; ?></span>
                                            <span class="text-slate-500 text-xs"> items</span>
                                        </div>
                                        <div class="text-xs text-slate-500"><?php echo number_format($branch['total_stock']); ?> units</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-emerald-400 text-sm"><?php echo $branch['today_sales']; ?></div>
                                        <div class="text-xs text-slate-500">KSh <?php echo number_format($branch['today_revenue'], 0); ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if ($branch['active']): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400"><i class="fas fa-circle text-[5px]"></i> Active</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-red-500/15 border border-red-500/30 text-red-400"><i class="fas fa-circle text-[5px]"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <a href="branch_form.php?id=<?php echo $branch['id']; ?>" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-amber-400 hover:bg-slate-700 transition-colors" title="Edit Branch">
                                                <i class="fas fa-edit text-[10px]"></i>
                                            </a>

                                            <!-- Initialize Inventory -->
                                            <?php if (is_super_admin() || $user_role === 'Admin'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="init_inventory">
                                                    <input type="hidden" name="id" value="<?php echo $branch['id']; ?>">
                                                    <button type="submit" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-emerald-400 hover:bg-slate-700 transition-colors" title="Initialize Inventory"
                                                        onclick="return confirm('Initialize inventory for this branch? This will add all products with zero stock.')">
                                                        <i class="fas fa-boxes text-[10px]"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Toggle Status -->
                                            <?php if ((is_super_admin() || $user_role === 'Admin') && $branch['id'] != $current_branch_id): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?php echo $branch['id']; ?>">
                                                    <input type="hidden" name="current_status"
                                                        value="<?php echo $branch['active']; ?>">
                                                    <button type="submit" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-amber-400 hover:bg-slate-700 transition-colors"
                                                        title="<?php echo $branch['active'] ? 'Deactivate' : 'Activate'; ?>"
                                                        onclick="return confirm('<?php echo $branch['active'] ? 'Deactivate' : 'Activate'; ?> this branch?')">
                                                        <i class="fas <?php echo $branch['active'] ? 'fa-ban' : 'fa-check-circle'; ?> text-[10px]"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <a href="../inventory/inventory.php?branch_id=<?php echo $branch['id']; ?>" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-blue-400 hover:bg-slate-700 transition-colors" title="View Inventory">
                                                <i class="fas fa-clipboard-list text-[10px]"></i>
                                            </a>
                                            <a href="../users/users.php?branch=<?php echo $branch['id']; ?>" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-violet-400 hover:bg-slate-700 transition-colors" title="View Users">
                                                <i class="fas fa-users text-[10px]"></i>
                                            </a>

                                            <!-- Delete (Super Admin only) -->
                                            <?php if ($can_delete && $branch['id'] != $current_branch_id): ?>
                                                <button onclick="openDeleteModal(<?php echo $branch['id']; ?>, '<?php echo htmlspecialchars(addslashes($branch['name'])); ?>')"
                                                    class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition-colors" title="Delete Branch">
                                                    <i class="fas fa-trash text-[10px]"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php if (!empty($branches)): ?>
            <div class="px-4 py-2.5 bg-slate-900/40 border-t border-slate-700/40 text-xs text-slate-500 flex items-center justify-between">
                <span><i class="fas fa-store mr-1"></i> <?php echo count($branches); ?> branches</span>
                <span class="flex items-center gap-3">
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span> Active</span>
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 bg-red-400 rounded-full"></span> Inactive</span>
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 bg-amber-400 rounded-full"></span> Current</span>
                </span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-sm w-full mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 rounded-lg bg-red-500/15 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-red-400"></i>
            </div>
            <h3 class="text-base font-semibold text-white">Delete Branch</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteBranchId">
            <p class="text-sm text-slate-400 mb-5">Delete <span id="deleteBranchName" class="text-white font-semibold"></span>? This cannot be undone.</p>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-red-500/15 border border-red-500/40 text-red-400 font-semibold text-sm hover:bg-red-500/25 transition-colors">
                    Delete Branch
                </button>
                <button type="button" onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

    <script>
        // Delete Modal Functions
        function openDeleteModal(id, name) {
            document.getElementById('deleteBranchId').value = id;
            document.getElementById('deleteBranchName').textContent = name;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.querySelectorAll('.fixed').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });
        });

        setTimeout(() => {
            document.querySelectorAll('[class*="bg-emerald-500/10"]').forEach(el => {
                el.style.transition = 'opacity 0.5s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 5000);
    </script>

    <?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>