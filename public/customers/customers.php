<?php
/**
 * Customers management page for Jakababa POS
 * Multi-tenant SaaS - Each company sees only their own customers
 * Supports business type specific customer fields and loyalty programs
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Check for customers management permission
if (!check_permission('customers.manage') && !is_super_admin()) {
    enforce_permission('customers.manage');
}

$pdo = get_db_connection();

// Get current user and company context
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['name'] ?? 'User');
$user_role = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? 'cashier';
$is_superadmin = is_super_admin();
$is_company_admin = $is_superadmin || in_array($user_role, ['admin', 'owner']);
$is_branch_manager = in_array($user_role, ['branch_manager', 'manager']);

// Get business type for customer fields customization
$business_type = get_current_business_type($tenant_id);
$bt_config = get_business_type_config($business_type);
$bt_customer_label = $bt_config['customer_label'] ?? 'Customer';
$bt_customer_fields = $bt_config['customer_fields'] ?? [];

// Ensure tenant_id exists for data isolation
if (!$tenant_id && !$is_superadmin) {
    die("Company context missing. Please log in again.");
}

// Handle POST actions (delete, points, toggle only; create/update moved to customer_form.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        header('Location: customers.php?error=' . urlencode('Security validation failed. Please refresh and try again.'));
        exit;
    }

    $redirect_msg = '';
    $redirect_type = 'success';

    switch ($action) {
        case 'delete':
            if ($is_company_admin || $is_superadmin) {
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $check = $pdo->prepare('SELECT COUNT(*) FROM sales WHERE customer_id = ? AND tenant_id = ?');
                        $check->execute([$id, $tenant_id]);
                        if ($check->fetchColumn() > 0) {
                            $pdo->prepare('UPDATE customers SET status = 0, deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$id, $tenant_id]);
                            $redirect_msg = 'Customer has been deactivated (has sales history)';
                        } else {
                            $pdo->prepare('DELETE FROM customers WHERE id = ? AND tenant_id = ?')->execute([$id, $tenant_id]);
                            $redirect_msg = 'Customer deleted successfully';
                        }
                        log_activity($user_id, 'customer.deleted', ['customer_id' => $id, 'tenant_id' => $tenant_id], get_current_tenant_id());
                    } catch (PDOException $e) {
                        error_log("Error deleting customer: " . $e->getMessage());
                        $redirect_msg = 'Failed to delete customer';
                        $redirect_type = 'error';
                    }
                }
            } else {
                $redirect_msg = 'You do not have permission to delete customers';
                $redirect_type = 'error';
            }
            break;

        case 'add_points':
            $id = intval($_POST['id'] ?? 0);
            $points_to_add = intval($_POST['points'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if ($id > 0 && $points_to_add != 0) {
                try {
                    $pdo->beginTransaction();
                    $verify = $pdo->prepare('SELECT loyalty_points FROM customers WHERE id = ? AND tenant_id = ?');
                    $verify->execute([$id, $tenant_id]);
                    $current_points = $verify->fetchColumn();
                    if ($current_points === false) throw new Exception('Customer not found');
                    $pdo->prepare('UPDATE customers SET loyalty_points = loyalty_points + ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$points_to_add, $id, $tenant_id]);
                    $stmt = $pdo->prepare('SELECT loyalty_points FROM customers WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$id, $tenant_id]);
                    $new_points = $stmt->fetchColumn();
                    $pdo->prepare('INSERT INTO loyalty_points_log (tenant_id, customer_id, points_change, reason, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())')->execute([$tenant_id, $id, $points_to_add, $reason, $user_id]);
                    $pdo->commit();
                    $redirect_msg = 'Points ' . ($points_to_add > 0 ? 'added' : 'removed') . ' successfully. New balance: ' . number_format($new_points);
                    log_activity($user_id, 'customer.points_adjusted', [
                        'customer_id' => $id, 'points_change' => $points_to_add,
                        'new_balance' => $new_points, 'tenant_id' => $tenant_id
                    ], get_current_tenant_id());
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Error adjusting points: " . $e->getMessage());
                    $redirect_msg = 'Failed to adjust points: ' . $e->getMessage();
                    $redirect_type = 'error';
                }
            }
            break;

        case 'toggle_status':
            if ($is_company_admin || $is_superadmin) {
                $id = intval($_POST['id'] ?? 0);
                $current_status = intval($_POST['current_status'] ?? 1);
                $new_status = $current_status ? 0 : 1;
                try {
                    $pdo->prepare('UPDATE customers SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$new_status, $id, $tenant_id]);
                    $redirect_msg = 'Customer ' . ($new_status ? 'activated' : 'deactivated') . ' successfully';
                    log_activity($user_id, 'customer.status_toggled', [
                        'customer_id' => $id, 'new_status' => $new_status, 'tenant_id' => $tenant_id
                    ], get_current_tenant_id());
                } catch (PDOException $e) {
                    error_log("Error toggling customer status: " . $e->getMessage());
                    $redirect_msg = 'Failed to update customer status';
                    $redirect_type = 'error';
                }
            }
            break;

        case 'bulk_delete':
            if ($can_delete) {
                $ids = $_POST['ids'] ?? [];
                if (is_array($ids) && !empty($ids)) {
                    $deleted = 0; $deactivated = 0;
                    try {
                        foreach ($ids as $raw_id) {
                            $id = intval($raw_id);
                            if ($id <= 0) continue;
                            $check = $pdo->prepare('SELECT COUNT(*) FROM sales WHERE customer_id = ? AND tenant_id = ?');
                            $check->execute([$id, $tenant_id]);
                            if ($check->fetchColumn() > 0) {
                                $pdo->prepare('UPDATE customers SET status = 0, deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$id, $tenant_id]);
                                $deactivated++;
                            } else {
                                $pdo->prepare('DELETE FROM customers WHERE id = ? AND tenant_id = ?')->execute([$id, $tenant_id]);
                                $deleted++;
                            }
                        }
                        $redirect_msg = 'Deleted ' . $deleted . ' and deactivated ' . $deactivated . ' customer(s)';
                        log_activity($user_id, 'customer.bulk_deleted', ['deleted' => $deleted, 'deactivated' => $deactivated, 'tenant_id' => $tenant_id], get_current_tenant_id());
                    } catch (PDOException $e) {
                        error_log("Error bulk deleting customers: " . $e->getMessage());
                        $redirect_msg = 'Failed to delete some customers';
                        $redirect_type = 'error';
                    }
                }
            } else {
                $redirect_msg = 'You do not have permission to delete customers';
                $redirect_type = 'error';
            }
            break;
    }

    if ($redirect_msg) {
        $param = $redirect_type === 'error' ? 'error' : 'success';
        header('Location: customers.php?' . $param . '=' . urlencode($redirect_msg));
        exit;
    }
}

// Read flash messages from GET
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// Generate CSRF token
$csrf_token = generate_csrf_token();

$customer_columns = [];
try {
    $customer_columns = array_flip(array_column($pdo->query('SHOW COLUMNS FROM customers')->fetchAll(PDO::FETCH_ASSOC), 'Field'));
} catch (PDOException $e) {
    error_log("Error reading customer columns: " . $e->getMessage());
}
$customer_has_column = static fn(string $column): bool => isset($customer_columns[$column]);

// Get customer groups for this company
$customer_groups = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, min_spent, discount_rate, color 
        FROM customer_groups 
        WHERE tenant_id = ? AND deleted_at IS NULL 
        ORDER BY min_spent ASC
    ");
    $stmt->execute([$tenant_id]);
    $customer_groups = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching customer groups: " . $e->getMessage());
}

// Get search/filter parameters
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';
$group_filter = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query with proper company isolation
$sql = "
    SELECT 
        c.*,
        (SELECT COUNT(*) FROM sales s 
         WHERE s.customer_id = c.id 
         AND s.status = 'completed'
         AND s.tenant_id = c.tenant_id) as total_transactions,
        (SELECT COALESCE(SUM(s.total), 0) FROM sales s 
         WHERE s.customer_id = c.id 
         AND s.status = 'completed'
         AND s.tenant_id = c.tenant_id) as total_spent,
        (SELECT MAX(s.created_at) FROM sales s 
         WHERE s.customer_id = c.id 
         AND s.status = 'completed'
         AND s.tenant_id = c.tenant_id) as last_purchase,
        (SELECT COALESCE(SUM(points_change), 0) FROM loyalty_points_log lpl 
         WHERE lpl.customer_id = c.id 
         AND lpl.tenant_id = c.tenant_id) as total_points_earned,
        COALESCE(cg.name, 'None') as group_name,
        COALESCE(cg.color, '#6B7280') as group_color
    FROM customers c
    LEFT JOIN customer_groups cg ON c.group_id = cg.id AND cg.tenant_id = c.tenant_id
    WHERE c.tenant_id = ?
";

$params = [$tenant_id];

// Add branch filter for branch managers
if ($is_branch_manager && $branch_id) {
    $sql .= " AND (c.branch_id = ? OR c.branch_id IS NULL)";
    $params[] = $branch_id;
}

// Apply search
if (!empty($search)) {
    $search_columns = ['name', 'phone', 'email', 'city'];
    if ($customer_has_column('customer_code')) {
        $search_columns[] = 'customer_code';
    }
    $sql .= ' AND (' . implode(' OR ', array_map(static fn(string $column): string => 'c.' . $column . ' LIKE ?', $search_columns)) . ')';
    $search_param = "%$search%";
    $params = array_merge($params, array_fill(0, count($search_columns), $search_param));
}

// Apply group filter
if ($group_filter) {
    $sql .= " AND c.group_id = ?";
    $params[] = $group_filter;
}

// Apply status filter
if ($filter === 'active') {
    $sql .= " AND c.status = 1";
} elseif ($filter === 'inactive') {
    $sql .= " AND c.status = 0";
} elseif ($filter === 'has_purchases') {
    $sql .= " AND EXISTS (SELECT 1 FROM sales s WHERE s.customer_id = c.id AND s.status = 'completed')";
} elseif ($filter === 'no_purchases') {
    $sql .= " AND NOT EXISTS (SELECT 1 FROM sales s WHERE s.customer_id = c.id AND s.status = 'completed')";
} elseif ($filter === 'high_value') {
    $sql .= " AND (SELECT COALESCE(SUM(s.total), 0) FROM sales s WHERE s.customer_id = c.id AND s.status = 'completed') > 1000000";
} elseif ($filter === 'with_points') {
    $sql .= " AND c.loyalty_points > 0";
}

// Apply sorting
switch ($sort) {
    case 'name':
        $sql .= " ORDER BY c.name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY c.name DESC";
        break;
    case 'points':
        $sql .= " ORDER BY c.loyalty_points DESC";
        break;
    case 'points_asc':
        $sql .= " ORDER BY c.loyalty_points ASC";
        break;
    case 'spent':
        $sql .= " ORDER BY total_spent DESC";
        break;
    case 'spent_asc':
        $sql .= " ORDER BY total_spent ASC";
        break;
    case 'recent':
        $sql .= " ORDER BY last_purchase DESC NULLS LAST";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY c.id DESC";
        break;
}

// Add pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM customers c 
    WHERE c.tenant_id = ?
";
$count_params = [$tenant_id];
if ($is_branch_manager && $branch_id) {
    $count_sql .= " AND (c.branch_id = ? OR c.branch_id IS NULL)";
    $count_params[] = $branch_id;
}
if (!empty($search)) {
    $count_sql .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
    $count_params = array_merge($count_params, [$search_param, $search_param, $search_param]);
}
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_customers = $count_stmt->fetchColumn();
$total_pages = ceil($total_customers / $per_page);

// Get statistics (scoped to company)
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
        COALESCE(SUM(loyalty_points), 0) as total_points,
        COALESCE((
            SELECT SUM(s.total) FROM sales s 
            WHERE s.customer_id = c.id AND s.status = 'completed'
        ), 0) as total_spent
    FROM customers c
    WHERE c.tenant_id = ?
";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute([$tenant_id]);
$stats_row = $stats_stmt->fetch();

$stats = [
    'total' => $stats_row['total'] ?? 0,
    'active' => $stats_row['active'] ?? 0,
    'total_points' => $stats_row['total_points'] ?? 0,
    'total_spent' => $stats_row['total_spent'] ?? 0,
    'avg_spent' => $stats_row['total'] > 0 ? $stats_row['total_spent'] / $stats_row['total'] : 0,
    'with_purchases' => 0 // Calculate separately if needed
];

$page_title = $bt_customer_label . ' Management';
$can_delete = $is_company_admin || $is_superadmin;
$can_toggle_status = $is_company_admin || $is_superadmin || $is_branch_manager;

ob_start();
?>

<!-- ═══ Header ═══════════════════════════════════════════════════════ -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-users text-amber-400"></i>
            <?php echo htmlspecialchars($bt_customer_label); ?> Management
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">Manage <?php echo strtolower($bt_customer_label); ?>s, loyalty points &amp; purchase history</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="customer_form.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-semibold hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-plus text-[9px]"></i> Add <?php echo $bt_customer_label; ?>
        </a>
    </div>
</div>

<?php if ($success_message): ?>
<div id="flash-success" class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs">
    <i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success_message); ?></span>
</div>
<?php endif; ?>
<?php if ($error_message): ?>
<div id="flash-error" class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs">
    <i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error_message); ?></span>
</div>
<?php endif; ?>

<?php $inactive_count = $stats['total'] - $stats['active']; ?>

<!-- ═══ KPI Cards ════════════════════════════════════════════════════ -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
    <?php foreach ([
        ['label'=>'Total','value'=>number_format($stats['total']),'sub'=>number_format($stats['active']).' active','icon'=>'fa-users','color'=>'text-amber-400','bg'=>'bg-amber-500/10'],
        ['label'=>'Active Rate','value'=>($stats['total']>0?round(($stats['active']/$stats['total'])*100):0).'%','sub'=>number_format($stats['active']).' of '.number_format($stats['total']),'icon'=>'fa-chart-line','color'=>'text-emerald-400','bg'=>'bg-emerald-500/10'],
        ['label'=>'Total Points','value'=>number_format($stats['total_points']),'sub'=>'Avg '.($stats['total']>0?round($stats['total_points']/$stats['total']):0).' pts','icon'=>'fa-star','color'=>'text-amber-400','bg'=>'bg-amber-500/10'],
        ['label'=>'Total Spent','value'=>format_currency($stats['total_spent']),'sub'=>'Avg '.format_currency($stats['avg_spent']),'icon'=>'fa-coins','color'=>'text-emerald-400','bg'=>'bg-emerald-500/10'],
    ] as $card): ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?> truncate"><?php echo $card['value']; ?></div>
            <div class="text-[10px] text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
            <div class="text-[10px] text-slate-600 truncate"><?php echo $card['sub']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═══ Filters ══════════════════════════════════════════════════════ -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
    <div class="flex flex-wrap gap-1.5 mb-3">
        <?php
        $pills = [
            ''              => ['label'=>'All',          'count'=>$stats['total']],
            'active'        => ['label'=>'Active',       'count'=>$stats['active']],
            'inactive'      => ['label'=>'Inactive',     'count'=>$inactive_count],
            'with_points'   => ['label'=>'Has Points',   'count'=>''],
            'has_purchases' => ['label'=>'Purchased',    'count'=>''],
        ];
        $pill_on  = [''=>'bg-amber-500/20 text-amber-400 border-amber-500/30','active'=>'bg-emerald-500/20 text-emerald-400 border-emerald-500/30','inactive'=>'bg-slate-500/20 text-slate-300 border-slate-500/30','with_points'=>'bg-amber-500/20 text-amber-400 border-amber-500/30','has_purchases'=>'bg-blue-500/20 text-blue-400 border-blue-500/30'];
        $pill_off = 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700';
        foreach ($pills as $val => $pill):
            $is_on = ($filter===$val)||($val===''&&($filter==='all'||$filter===''));
            $href  = $val===''?'?'.http_build_query(array_diff_key($_GET,array_flip(['filter','page']))):'?'.http_build_query(array_merge(array_diff_key($_GET,array_flip(['filter','page'])),['filter'=>$val]));
        ?>
        <a href="<?php echo htmlspecialchars($href); ?>"
           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium border transition-colors <?php echo $is_on ? $pill_on[$val] : $pill_off; ?>">
            <?php echo $pill['label']; ?><?php if($pill['count']!=='') echo ' <span class="opacity-60">('.(int)$pill['count'].')</span>'; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <form method="GET" class="flex flex-wrap gap-2 items-end">
        <?php foreach (array_diff_key($_GET, array_flip(['search','filter','sort','page','group_id'])) as $k => $v): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
        <?php endforeach; ?>
        <div class="relative flex-1 min-w-[160px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Name, phone, email, code…"
                   class="w-full pl-7 pr-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <?php if (!empty($customer_groups)): ?>
        <select name="group_id" class="px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">All Groups</option>
            <?php foreach ($customer_groups as $g): ?>
            <option value="<?php echo $g['id']; ?>" <?php echo $group_filter==$g['id']?'selected':''; ?>><?php echo htmlspecialchars($g['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select name="sort" class="px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="newest"    <?php echo $sort==='newest'?'selected':''; ?>>Newest</option>
            <option value="name"      <?php echo $sort==='name'?'selected':''; ?>>Name A–Z</option>
            <option value="name_desc" <?php echo $sort==='name_desc'?'selected':''; ?>>Name Z–A</option>
            <option value="points"    <?php echo $sort==='points'?'selected':''; ?>>Highest Points</option>
            <option value="spent"     <?php echo $sort==='spent'?'selected':''; ?>>Highest Spent</option>
            <option value="recent"    <?php echo $sort==='recent'?'selected':''; ?>>Recent Purchase</option>
        </select>
        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-amber-500/20 border border-amber-500/40 text-amber-400 text-xs font-semibold hover:bg-amber-500/30 transition-colors">
            <i class="fas fa-filter text-[9px]"></i> Filter
        </button>
        <?php if (!empty($search)||$filter!=='all'||$sort!=='newest'||$group_filter): ?>
        <a href="customers.php" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-slate-700 border border-slate-600 text-slate-400 hover:text-white hover:bg-slate-600 transition-colors" title="Clear">
            <i class="fas fa-times text-[9px]"></i>
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Bulk Actions Bar -->
<div id="bulkActionsBar" class="hidden flex items-center gap-2 bg-amber-500/10 border border-amber-500/30 rounded-lg px-3 py-2 mb-4">
    <span class="text-xs text-amber-400 font-medium"><span id="selectedCount">0</span> selected</span>
    <div class="flex-1"></div>
    <button onclick="exportSelected()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-slate-800 border border-slate-700 text-slate-300 text-xs hover:bg-slate-700 transition-colors">
        <i class="fas fa-download text-xs"></i> Export
    </button>
    <button onclick="deleteSelected()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-red-500/10 border border-red-500/30 text-red-400 text-xs hover:bg-red-500/20 transition-colors">
        <i class="fas fa-trash text-xs"></i> Delete
    </button>
    <button onclick="clearSelection()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-slate-800 border border-slate-700 text-slate-400 text-xs hover:bg-slate-700 transition-colors">
        Cancel
    </button>
</div>

<!-- ═══ Table ════════════════════════════════════════════════════════ -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[780px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-center w-10"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" class="w-3.5 h-3.5 rounded accent-amber-500"></th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider"><?php echo $bt_customer_label; ?></th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Contact</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Group</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Points</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Spent</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Last Purchase</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="9" class="px-4 py-14 text-center">
                        <i class="fas fa-users text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm">No <?php echo strtolower($bt_customer_label); ?>s found</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($customers as $customer): ?>
                <tr class="hover:bg-slate-700/30 transition-colors group" data-id="<?php echo $customer['id']; ?>">
                    <td class="px-3 py-2.5 text-center"><input type="checkbox" value="<?php echo $customer['id']; ?>" class="row-checkbox w-3.5 h-3.5 rounded accent-amber-500" onchange="updateSelection()"></td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-full bg-amber-500/10 flex items-center justify-center shrink-0 text-amber-400 text-xs font-bold">
                                <?php echo strtoupper(substr($customer['name'],0,1)); ?>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($customer['name']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($customer['customer_code'] ?? '#'.$customer['id']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2.5 text-xs space-y-0.5">
                        <?php if(!empty($customer['phone'])): ?><div class="flex items-center gap-1 text-slate-300"><i class="fas fa-phone text-slate-500 w-3"></i><?php echo htmlspecialchars($customer['phone']); ?></div><?php endif; ?>
                        <?php if(!empty($customer['email'])): ?><div class="flex items-center gap-1 text-slate-400"><i class="fas fa-envelope text-slate-500 w-3"></i><?php echo htmlspecialchars($customer['email']); ?></div><?php endif; ?>
                        <?php if(!empty($customer['city'])): ?><div class="flex items-center gap-1 text-slate-500"><i class="fas fa-map-pin text-slate-600 w-3"></i><?php echo htmlspecialchars($customer['city']); ?></div><?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if ($customer['group_id']): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium"
                              style="background:<?php echo $customer['group_color']; ?>20;color:<?php echo $customer['group_color']; ?>;border:1px solid <?php echo $customer['group_color']; ?>40">
                            <i class="fas fa-tag text-[8px]"></i><?php echo htmlspecialchars($customer['group_name']); ?>
                        </span>
                        <?php else: ?><span class="text-slate-600 text-xs">—</span><?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="text-sm font-semibold text-amber-400"><?php echo number_format($customer['loyalty_points']); ?></span>
                        <span class="text-slate-500 text-xs ml-0.5">pts</span>
                        <div class="text-xs text-slate-600"><?php echo $customer['total_transactions']; ?> sales</div>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="text-sm font-semibold text-emerald-400"><?php echo format_currency($customer['total_spent']); ?></span>
                    </td>
                    <td class="px-3 py-2.5 text-xs">
                        <?php if ($customer['last_purchase']): ?>
                        <div class="text-slate-300"><?php echo date('d M Y', strtotime($customer['last_purchase'])); ?></div>
                        <div class="text-slate-500"><?php echo time_ago($customer['last_purchase']); ?></div>
                        <?php else: ?><span class="text-slate-600">Never</span><?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if ($customer['status']): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30">
                            <i class="fas fa-check-circle mr-1 text-[10px]"></i>Active
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30">
                            <i class="fas fa-ban mr-1 text-[10px]"></i>Inactive
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-center gap-1">
                            <button onclick="viewDetails(<?php echo htmlspecialchars(json_encode($customer)); ?>)"
                                class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-blue-500/20 hover:text-blue-400 transition-colors" title="View Details">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                            <a href="customer_form.php?id=<?php echo $customer['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-amber-400 hover:bg-amber-500/20 hover:text-amber-300 transition-colors" title="Edit">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <a href="customer_form.php?duplicate_from=<?php echo $customer['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-cyan-500/20 hover:text-cyan-400 transition-colors" title="Duplicate">
                                <i class="fas fa-copy text-xs"></i>
                            </a>
                            <button onclick="openPointsModal(<?php echo $customer['id']; ?>,'<?php echo htmlspecialchars(addslashes($customer['name'])); ?>',<?php echo (int)$customer['loyalty_points']; ?>)"
                                class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-amber-500/20 hover:text-amber-400 transition-colors" title="Adjust Points">
                                <i class="fas fa-star text-xs"></i>
                            </button>
                            <button onclick="viewHistory(<?php echo $customer['id']; ?>,'<?php echo htmlspecialchars(addslashes($customer['name'])); ?>')"
                                class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-purple-500/20 hover:text-purple-400 transition-colors" title="History">
                                <i class="fas fa-history text-xs"></i>
                            </button>
                            <?php if ($can_toggle_status): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?php echo $customer['id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $customer['status']; ?>">
                                <button type="submit"
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-<?php echo $customer['status']?'orange':'emerald'; ?>-500/20 hover:text-<?php echo $customer['status']?'orange':'emerald'; ?>-400 transition-colors"
                                    title="<?php echo $customer['status']?'Deactivate':'Activate'; ?>"
                                    onclick="return confirm('<?php echo $customer['status']?'Deactivate':'Activate'; ?> this customer?')">
                                    <i class="fas fa-<?php echo $customer['status']?'ban':'check-circle'; ?> text-xs"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($can_delete): ?>
                            <button onclick="openDeleteModal(<?php echo $customer['id']; ?>,'<?php echo htmlspecialchars(addslashes($customer['name'])); ?>')"
                                class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-red-500/20 hover:text-red-400 transition-colors" title="Delete">
                                <i class="fas fa-trash-alt text-xs"></i>
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
    <div class="flex items-center justify-between px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
        <p class="text-xs text-slate-500">
            Showing <span class="text-slate-300 font-medium"><?php echo count($customers); ?></span> of <span class="text-slate-300 font-medium"><?php echo number_format($total_customers); ?></span> <?php echo strtolower($bt_customer_label); ?>s
        </p>
        <?php if ($total_pages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page-1])); ?>"
               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs hover:bg-slate-600 transition-colors">
                <i class="fas fa-chevron-left text-[9px]"></i> Prev
            </a>
            <?php endif; ?>
            <span class="px-2.5 py-1 rounded-lg bg-amber-500/20 border border-amber-500/30 text-amber-400 text-xs font-semibold">
                <?php echo $page; ?> / <?php echo $total_pages; ?>
            </span>
            <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page+1])); ?>"
               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs hover:bg-slate-600 transition-colors">
                Next <i class="fas fa-chevron-right text-[9px]"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ Points Modal ══════════════════════════════════════════════════ -->
<div id="pointsModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-white flex items-center gap-2"><i class="fas fa-star text-amber-400"></i> Adjust Loyalty Points</h3>
            <button onclick="closeModal('pointsModal')" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="add_points">
            <input type="hidden" name="id" id="pointsCustomerId">
            <div>
                <p id="pointsCustomerName" class="text-sm font-medium text-white"></p>
                <p class="text-xs text-slate-500 mt-0.5">Current: <span id="currentPoints" class="text-amber-400 font-semibold"></span> pts</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Adjustment</label>
                <input type="number" name="points" required placeholder="e.g. 50 or -10"
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                <p class="text-[10px] text-slate-500 mt-1">Positive to add, negative to remove</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Reason (optional)</label>
                <input type="text" name="reason" placeholder="e.g. Purchase bonus"
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                    <i class="fas fa-save text-xs"></i> Apply
                </button>
                <button type="button" onclick="closeModal('pointsModal')" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Delete Modal ═══════════════════════════════════════════════════ -->
<div id="deleteModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-md w-full mx-4">
        <div class="flex items-center gap-2.5 mb-4">
            <i class="fas fa-exclamation-triangle text-red-400"></i>
            <h3 class="text-base font-semibold text-white">Delete <?php echo $bt_customer_label; ?></h3>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteCustomerId">
            <p class="text-slate-300 text-sm mb-5">Delete <span id="deleteCustomerName" class="text-white font-semibold"></span>? This cannot be undone.</p>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-semibold hover:bg-red-500/25 transition-colors">
                    <i class="fas fa-trash-alt text-xs"></i> Delete
                </button>
                <button type="button" onclick="closeModal('deleteModal')" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Details Modal ══════════════════════════════════════════════════ -->
<div id="detailsModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-2xl w-full mx-4 max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4 sticky top-0 bg-slate-800 z-10">
            <h3 class="text-base font-semibold text-white">Customer Details</h3>
            <button onclick="closeModal('detailsModal')" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div id="detailsContent"></div>
    </div>
</div>

<!-- ═══ History Modal ══════════════════════════════════════════════════ -->
<div id="historyModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-3xl w-full mx-4 max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4 sticky top-0 bg-slate-800 z-10">
            <h3 class="text-base font-semibold text-white">Purchase History</h3>
            <button onclick="closeModal('historyModal')" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div id="historyContent">
            <div class="text-center text-slate-500 py-8"><i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>Loading…</div>
        </div>
    </div>
</div>

<script>
function escapeHtml(t) { if(!t) return ''; const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function openPointsModal(id, name, points) {
    document.getElementById('pointsCustomerId').value = id;
    document.getElementById('pointsCustomerName').textContent = name;
    document.getElementById('currentPoints').textContent = points;
    document.getElementById('pointsModal').classList.remove('hidden');
}

function openDeleteModal(id, name) {
    document.getElementById('deleteCustomerId').value = id;
    document.getElementById('deleteCustomerName').textContent = name;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function viewDetails(customer) {
    document.getElementById('detailsContent').innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center gap-3 border-b border-slate-700 pb-4">
                <div class="w-12 h-12 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-400 text-xl font-bold shrink-0">${customer.name.charAt(0).toUpperCase()}</div>
                <div>
                    <p class="text-base font-semibold text-white">${escapeHtml(customer.name)}</p>
                    <p class="text-xs text-slate-400">${customer.customer_code || '#'+customer.id}</p>
                    <p class="text-xs text-slate-500">Added: ${new Date(customer.created_at).toLocaleDateString()}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><p class="text-xs text-slate-500 uppercase mb-1">Contact</p><p class="text-white">${customer.phone||'—'}</p><p class="text-slate-400 text-xs">${customer.email||'—'}</p></div>
                <div><p class="text-xs text-slate-500 uppercase mb-1">Location</p><p class="text-white">${customer.city||'—'}</p><p class="text-slate-400 text-xs">${customer.address||''}</p></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-slate-900 rounded-lg p-3 text-center"><p class="text-xs text-slate-500">Points</p><p class="text-lg font-bold text-amber-400">${Number(customer.loyalty_points).toLocaleString()}</p></div>
                <div class="bg-slate-900 rounded-lg p-3 text-center"><p class="text-xs text-slate-500">Spent</p><p class="text-lg font-bold text-emerald-400">${Number(customer.total_spent).toLocaleString()}</p></div>
                <div class="bg-slate-900 rounded-lg p-3 text-center"><p class="text-xs text-slate-500">Transactions</p><p class="text-lg font-bold text-white">${customer.total_transactions}</p></div>
            </div>
            ${customer.notes ? `<div><p class="text-xs text-slate-500 uppercase mb-1">Notes</p><p class="text-sm text-slate-300">${escapeHtml(customer.notes)}</p></div>` : ''}
        </div>`;
    document.getElementById('detailsModal').classList.remove('hidden');
}

function viewHistory(customerId, customerName) {
    const modal = document.getElementById('historyModal');
    document.getElementById('historyContent').innerHTML = '<div class="text-center text-slate-500 py-8"><i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>Loading…</div>';
    modal.classList.remove('hidden');
    fetch('../ajax/get_customer_history.php?customer_id=' + customerId)
        .then(r => {
            if (!r.ok) throw new Error('Server error: ' + r.status);
            return r.text().then(text => {
                try { return JSON.parse(text); }
                catch (e) { console.error('Invalid JSON:', text); throw new Error('Invalid server response'); }
            });
        })
        .then(data => {
            if (data.success && data.history.length > 0) {
                let html = `<p class="text-sm font-semibold text-white mb-3">History for ${escapeHtml(customerName)}</p><div class="space-y-2">`;
                data.history.forEach(sale => {
                    html += `<div class="bg-slate-900 rounded-lg p-3 flex justify-between items-center">
                        <div><p class="text-sm text-white font-medium">#${sale.invoice_number}</p><p class="text-xs text-slate-500">${new Date(sale.created_at).toLocaleString()}</p></div>
                        <div class="text-right"><p class="text-sm font-bold text-amber-400">${Number(sale.total).toLocaleString()}</p><p class="text-xs text-slate-500">${sale.items} items</p></div>
                    </div>`;
                });
                html += '</div>';
                document.getElementById('historyContent').innerHTML = html;
            } else {
                document.getElementById('historyContent').innerHTML = '<div class="text-center text-slate-500 py-8"><i class="fas fa-shopping-cart text-3xl mb-2 block opacity-40"></i>No purchase history</div>';
            }
        })
        .catch(err => {
            console.error('History error:', err);
            document.getElementById('historyContent').innerHTML = `<div class="text-center text-red-400 py-8"><i class="fas fa-exclamation-circle text-3xl mb-2 block"></i>Error: ${escapeHtml(err.message)}</div>`;
        });
}

document.querySelectorAll('#pointsModal,#deleteModal,#detailsModal,#historyModal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.add('hidden'); });
});

document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('#pointsModal,#deleteModal,#detailsModal,#historyModal').forEach(m => m.classList.add('hidden')); });

// Auto-hide flash messages
['flash-success','flash-error'].forEach(id => {
    const el = document.getElementById(id);
    if (el) setTimeout(() => { el.style.transition='opacity .5s'; el.style.opacity='0'; setTimeout(() => el.remove(), 500); }, 5000);
});

// ==================== ADVANCED FEATURES ====================

function toggleSelectAll(source) {
    document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = source.checked; });
    updateSelection();
}

function updateSelection() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    const bar = document.getElementById('bulkActionsBar');
    const count = document.getElementById('selectedCount');
    const selectAll = document.getElementById('selectAll');
    const total = document.querySelectorAll('.row-checkbox').length;
    if (count) count.textContent = checked.length;
    if (bar) bar.classList.toggle('hidden', checked.length === 0);
    if (selectAll) selectAll.checked = total > 0 && checked.length === total;
}

function clearSelection() {
    document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = false; });
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
    updateSelection();
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    const colors = { success: 'bg-emerald-800/95 border-l-4 border-emerald-400 text-white', error: 'bg-red-800/95 border-l-4 border-red-400 text-white', warning: 'bg-amber-800/95 border-l-4 border-amber-400 text-white', info: 'bg-slate-800 border-l-4 border-blue-400 text-slate-200' };
    toast.className = `${colors[type] || colors.info} px-4 py-3 rounded-lg text-sm font-medium shadow-2xl `;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>${escapeHtml(message)}`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 3000);
}

function exportSelected() {
    const ids = getSelectedIds();
    if (!ids.length) { showToast('No customers selected', 'warning'); return; }
    const rows = Array.from(document.querySelectorAll('tr[data-id]')).filter(tr => ids.includes(tr.dataset.id));
    let csv = 'ID,Name,Code,Phone,Email,City,Group,Points,Spent,Status\n';
    rows.forEach(tr => {
        const tds = tr.querySelectorAll('td');
        const id = tr.dataset.id;
        const name = tds[1]?.textContent?.trim().replace(/\n+/g,' ').replace(/\s+/g,' ') || '';
        const code = tds[1]?.querySelector('.text-xs')?.textContent?.trim() || '';
        const contact = tds[2]?.textContent?.trim().replace(/\n+/g,' ').replace(/\s+/g,' ') || '';
        const phone = contact.match(/[\d\s\-+()]+/)?.[0]?.trim() || '';
        const email = contact.match(/[\w.-]+@[\w.-]+\.\w+/)?.[0] || '';
        const city = tds[2]?.querySelector('.fa-map-pin')?.parentNode?.textContent?.trim() || '';
        const group = tds[3]?.textContent?.trim() || '';
        const points = tds[4]?.textContent?.trim().replace(/[^\d]/g,'') || '';
        const spent = tds[5]?.textContent?.trim().replace(/[^\d]/g,'') || '';
        const status = tds[7]?.textContent?.trim() || '';
        csv += `${id},"${name}","${code}","${phone}","${email}","${city}","${group}",${points},${spent},"${status}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `customers_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    showToast('Exported ' + ids.length + ' customer(s)', 'success');
}

function deleteSelected() {
    const ids = getSelectedIds();
    if (!ids.length) return;
    if (!confirm('Delete ' + ids.length + ' selected customer(s)? This cannot be undone.')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'customers.php';
    form.style.display = 'none';
    const add = (n, v) => { const i = document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
    add('csrf_token', '<?php echo $csrf_token; ?>');
    add('action', 'bulk_delete');
    ids.forEach(id => { const i = document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value=id; form.appendChild(i); });
    document.body.appendChild(form);
    form.submit();
}

// Debounced search
let searchTimeout;
document.querySelector('input[name="search"]')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { this.closest('form')?.submit(); }, 500);
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch (e.key.toLowerCase()) {
            case 'e': e.preventDefault(); exportSelected(); break;
        }
    }
    if (!e.ctrlKey && !e.metaKey && !e.altKey && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA' && document.activeElement?.tagName !== 'SELECT') {
        if (e.key === '/') { e.preventDefault(); document.querySelector('input[name="search"]')?.focus(); }
    }
});
</script>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';