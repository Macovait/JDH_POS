<?php
/**
 * Vouchers management page for Jakababa POS
 * 
 * Manages promotional vouchers and discount codes.
 */

require_once __DIR__ . '/../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

// Check for vouchers management permission
if (!check_permission('vouchers.manage') && !is_super_admin()) {
    enforce_permission('vouchers.manage');
}

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';

$pdo = get_db_connection();

// Get current user info for navbar
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$branch_id = (int) ($_SESSION['branch_id'] ?? 1);
$tenant_id = get_current_tenant_id();

// Handle CRUD operations
$success_message = '';
$error_message = '';

// Flash messages from redirects (e.g. voucher_form.php)
if (!empty($_GET['success'])) {
    $success_map = [
        'created' => 'Voucher created successfully',
        'updated' => 'Voucher updated successfully',
    ];
    $success_message = $success_map[$_GET['success']] ?? 'Action completed successfully';
}
if (!empty($_GET['error'])) {
    $error_message = htmlspecialchars(urldecode($_GET['error']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'delete':
            // Delete voucher (Admin/Super Admin only)
            if (is_super_admin() || $user_role === 'Admin') {
                $id = intval($_POST['id'] ?? 0);

                if ($id > 0) {
                    try {
                        $stmt = $pdo->prepare('DELETE FROM vouchers WHERE id = ? AND tenant_id = ?');
                        $stmt->execute([$id, $tenant_id]);

                        log_activity($user_id, 'voucher.deleted', ['voucher_id' => $id], null, get_current_tenant_id());

                        $success_message = 'Voucher deleted successfully';
                    } catch (PDOException $e) {
                        error_log("Error deleting voucher: " . $e->getMessage());
                        $error_message = 'Failed to delete voucher';
                    }
                }
            }
            break;

        case 'toggle_status':
            // Toggle voucher active status
            $id = intval($_POST['id'] ?? 0);
            $status = intval($_POST['status'] ?? 0);

            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare('UPDATE vouchers SET active = ? WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$status, $id, $tenant_id]);

                    $success_message = 'Voucher status updated successfully';

                    log_activity($user_id, 'voucher.status_changed', [
                        'voucher_id' => $id, 'new_status' => $status
                    ], get_current_tenant_id());

                } catch (PDOException $e) {
                    error_log("Error toggling voucher status: " . $e->getMessage());
                    $error_message = 'Failed to update voucher status';
                }
            }
            break;
    }
}

// Get search/filter/pagination parameters
$search      = $_GET['search'] ?? '';
$filter      = $_GET['filter'] ?? 'all'; // all, active, expired, upcoming
$type_filter = $_GET['type']   ?? 'all'; // all, fixed, percent
$page        = max(1, (int) ($_GET['page'] ?? 1));
$per_page    = 25;
$offset      = ($page - 1) * $per_page;

// Build WHERE clause (shared by count + stats + paginated fetch)
$where  = " WHERE v.tenant_id = ? ";
$params = [$tenant_id];

if (!empty($search)) {
    $where .= " AND (v.code LIKE ? OR v.description LIKE ?) ";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter === 'active') {
    $where .= " AND v.active = 1 AND (v.expires_at IS NULL OR v.expires_at >= CURDATE()) ";
} elseif ($filter === 'expired') {
    $where .= " AND v.expires_at < CURDATE() ";
} elseif ($filter === 'upcoming') {
    $where .= " AND v.expires_at > CURDATE() ";
}

if ($type_filter !== 'all') {
    $where .= " AND v.type = ? ";
    $params[] = $type_filter;
}

// Stats (always unfiltered by search/tab — counts across all vouchers for the tenant)
$stats_raw = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(v.active = 1 AND (v.expires_at IS NULL OR v.expires_at >= CURDATE())) AS active,
        SUM(v.expires_at IS NOT NULL AND v.expires_at < CURDATE())                AS expired,
        SUM(v.expires_at IS NOT NULL AND v.expires_at > CURDATE())                AS upcoming,
        SUM(v.type = 'fixed')                                                     AS fixed,
        SUM(v.type = 'percent')                                                   AS percent
    FROM vouchers v
    WHERE v.tenant_id = ?
");
$stats_raw->execute([$tenant_id]);
$stats = $stats_raw->fetch(PDO::FETCH_ASSOC);
$stats = array_map('intval', $stats);

// Total filtered count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM vouchers v $where");
$count_stmt->execute($params);
$total_filtered = (int) $count_stmt->fetchColumn();
$total_pages    = max(1, (int) ceil($total_filtered / $per_page));
$page           = min($page, $total_pages);

// Paginated fetch
$select_sql = "
    SELECT
        v.*,
        CASE
            WHEN v.expires_at IS NULL                                        THEN 'no-expiry'
            WHEN v.expires_at < CURDATE()                                    THEN 'expired'
            WHEN v.expires_at = CURDATE()                                    THEN 'expires-today'
            WHEN v.expires_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)         THEN 'expires-soon'
            ELSE 'valid'
        END AS expiry_status
    FROM vouchers v
    $where
    ORDER BY v.id DESC
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($select_sql);
$stmt->execute($params);
$vouchers = $stmt->fetchAll();

$page_title = 'Vouchers';
$can_delete = is_super_admin() || $user_role === 'Admin';
$active_tab = $filter ?: 'all';

// Status/type Tailwind maps
$status_tw = [
    'active'   => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
    'expired'  => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
    'inactive' => 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30',
];
$type_tw = [
    'fixed'   => 'bg-blue-500/10 text-blue-400',
    'percent' => 'bg-amber-500/10 text-amber-400',
];

ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-ticket-alt text-amber-400"></i> Vouchers
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo number_format($total_filtered); ?> voucher<?php echo $total_filtered !== 1 ? 's' : ''; ?> found
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="voucher_form.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add Voucher
        </a>
    </div>
</div>

<?php if ($success_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm" id="successBanner">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<!-- Summary cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 mb-5">
    <?php
    $cards = [
        ['label'=>'Total',   'value'=>$stats['total'],   'icon'=>'fa-ticket-alt',      'color'=>'text-amber-400',  'bg'=>'bg-amber-500/10'],
        ['label'=>'Active',  'value'=>$stats['active'],  'icon'=>'fa-check-circle',    'color'=>'text-emerald-400','bg'=>'bg-emerald-500/10'],
        ['label'=>'Expired', 'value'=>$stats['expired'], 'icon'=>'fa-clock',           'color'=>'text-red-400',    'bg'=>'bg-red-500/10'],
        ['label'=>'Fixed',   'value'=>$stats['fixed'],   'icon'=>'fa-money-bill-wave', 'color'=>'text-blue-400',   'bg'=>'bg-blue-500/10'],
        ['label'=>'Percent', 'value'=>$stats['percent'], 'icon'=>'fa-percent',         'color'=>'text-amber-400',  'bg'=>'bg-amber-500/10'],
    ];
    foreach ($cards as $card):
    ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?>"><?php echo $card['value']; ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Status pills + filters -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4 space-y-3">

    <!-- Status pills -->
    <div class="flex flex-wrap gap-1.5">
        <?php
        $pills = [
            'all'      => ['label'=>'All',      'count'=>$stats['total'],          'on'=>'bg-amber-500/20 text-amber-400 border-amber-500/30',       'off'=>'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            'active'   => ['label'=>'Active',   'count'=>$stats['active'],         'on'=>'bg-emerald-500/20 text-emerald-400 border-emerald-500/30', 'off'=>'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            'expired'  => ['label'=>'Expired',  'count'=>$stats['expired'],        'on'=>'bg-red-500/20 text-red-400 border-red-500/30',             'off'=>'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            'upcoming' => ['label'=>'Upcoming', 'count'=>$stats['upcoming'] ?? 0,  'on'=>'bg-blue-500/20 text-blue-400 border-blue-500/30',          'off'=>'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
        ];
        foreach ($pills as $val => $pill):
            $href = $val === 'all'
                ? '?' . http_build_query(array_diff_key($_GET, array_flip(['filter','page'])))
                : '?' . http_build_query(array_merge(array_diff_key($_GET, array_flip(['filter','page'])), ['filter'=>$val]));
        ?>
        <a href="<?php echo htmlspecialchars($href); ?>"
           class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border transition-colors <?php echo $active_tab === $val ? $pill['on'] : $pill['off']; ?>">
            <?php echo $pill['label']; ?>
            <span class="text-xs opacity-70">(<?php echo (int)$pill['count']; ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filter row -->
    <form method="GET" class="flex flex-wrap gap-2 items-end">
        <?php if ($active_tab !== 'all'): ?>
        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($active_tab); ?>">
        <?php endif; ?>

        <!-- Search -->
        <div class="relative flex-1 min-w-[160px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search code or description…"
                   class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>

        <!-- Type filter -->
        <select name="type" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="all"     <?php echo $type_filter === 'all'     ? 'selected' : ''; ?>>All Types</option>
            <option value="fixed"   <?php echo $type_filter === 'fixed'   ? 'selected' : ''; ?>>Fixed</option>
            <option value="percent" <?php echo $type_filter === 'percent' ? 'selected' : ''; ?>>Percent</option>
        </select>

        <button type="submit" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-filter mr-1 text-xs"></i>Filter
        </button>
        <a href="vouchers.php" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times mr-1 text-xs"></i>Clear
        </a>
    </form>
</div>

<!-- Vouchers table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Code</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Type</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Value</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Min Purchase</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Max Discount</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Expires</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($vouchers)): ?>
                <tr>
                    <td colspan="8" class="px-4 py-14 text-center">
                        <i class="fas fa-ticket-alt text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm">No vouchers found</p>
                        <?php if (!empty($search) || $active_tab !== 'all' || $type_filter !== 'all'): ?>
                        <a href="vouchers.php" class="mt-2 inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                            <i class="fas fa-times text-xs"></i> Clear filters
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($vouchers as $voucher):
                    $exp_text  = '';
                    $exp_class = '';
                    if ($voucher['expiry_status'] === 'expired')       { $exp_text = 'Expired';       $exp_class = 'text-red-400'; }
                    elseif ($voucher['expiry_status'] === 'expires-today') { $exp_text = 'Expires today'; $exp_class = 'text-orange-400'; }
                    elseif ($voucher['expiry_status'] === 'expires-soon')  { $exp_text = 'Expires soon';  $exp_class = 'text-amber-400'; }

                    $is_active  = $voucher['active'] && (is_null($voucher['expires_at']) || $voucher['expires_at'] >= date('Y-m-d'));
                    $is_expired = !is_null($voucher['expires_at']) && $voucher['expires_at'] < date('Y-m-d');

                    $st_key   = $is_active ? 'active' : ($is_expired ? 'expired' : 'inactive');
                    $st_class = $status_tw[$st_key];
                    $st_label = ucfirst($st_key);
                    $pm_class = $type_tw[$voucher['type']] ?? 'bg-slate-500/10 text-slate-400';
                ?>
                <tr class="hover:bg-slate-700/30 transition-colors group">
                    <td class="px-3 py-2.5">
                        <span class="font-mono text-sm font-semibold text-amber-400"><?php echo htmlspecialchars($voucher['code']); ?></span>
                        <?php if (!empty($voucher['description'])): ?>
                        <div class="text-xs text-slate-500 mt-0.5 truncate max-w-[200px]"><?php echo htmlspecialchars($voucher['description']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $pm_class; ?>">
                            <?php echo $voucher['type'] === 'fixed' ? 'Fixed' : 'Percent'; ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if ($voucher['type'] === 'fixed'): ?>
                        <span class="text-sm font-bold text-emerald-400"><?php echo number_format($voucher['value'], 2); ?></span>
                        <?php else: ?>
                        <span class="text-sm font-bold text-amber-400"><?php echo $voucher['value']; ?>%</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?php echo $voucher['min_purchase'] > 0 ? number_format($voucher['min_purchase'], 2) : '—'; ?></td>
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?php echo !is_null($voucher['max_discount']) ? number_format($voucher['max_discount'], 2) : '—'; ?></td>
                    <td class="px-3 py-2.5">
                        <?php if ($voucher['expires_at']): ?>
                        <div class="text-sm text-slate-300"><?php echo date('d M Y', strtotime($voucher['expires_at'])); ?></div>
                        <?php if ($exp_text): ?><div class="text-xs <?php echo $exp_class; ?>"><?php echo $exp_text; ?></div><?php endif; ?>
                        <?php else: ?>
                        <span class="text-sm text-slate-500">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $st_class; ?>">
                            <?php echo $st_label; ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-center gap-2">
                            <a href="view_voucher.php?id=<?php echo $voucher['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-slate-600 hover:text-white transition-colors" title="View">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                            <a href="voucher_form.php?id=<?php echo $voucher['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-amber-400 hover:bg-amber-500/20 hover:text-amber-300 transition-colors" title="Edit">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <button onclick="toggleStatus(<?php echo $voucher['id']; ?>, <?php echo $voucher['active']; ?>)"
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 <?php echo $voucher['active'] ? 'text-amber-400 hover:bg-amber-500/20' : 'text-emerald-400 hover:bg-emerald-500/20'; ?> transition-colors"
                                    title="<?php echo $voucher['active'] ? 'Deactivate' : 'Activate'; ?>">
                                <i class="fas fa-<?php echo $voucher['active'] ? 'pause' : 'play'; ?> text-xs"></i>
                            </button>
                            <?php if ($can_delete): ?>
                            <button onclick="openDeleteModal(<?php echo $voucher['id']; ?>, '<?php echo htmlspecialchars($voucher['code'], ENT_QUOTES); ?>')"
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-500 hover:bg-red-500/20 hover:text-red-400 transition-colors" title="Delete">
                                <i class="fas fa-trash text-xs"></i>
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

    <!-- Table footer + pagination -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
        <p class="text-xs text-slate-500">
            <?php if ($total_filtered > 0): ?>
            Showing <span class="text-slate-300 font-medium"><?php echo ($offset + 1); ?>–<?php echo min($offset + $per_page, $total_filtered); ?></span>
            of <span class="text-slate-300 font-medium"><?php echo number_format($total_filtered); ?></span> voucher<?php echo $total_filtered !== 1 ? 's' : ''; ?>
            <?php else: ?>
            No vouchers found
            <?php endif; ?>
        </p>
        <?php if ($total_pages > 1):
            $qs = function(array $extra) use ($search, $filter, $type_filter, $page) {
                return http_build_query(array_filter(array_merge(
                    ['search'=>$search,'filter'=>$filter,'type'=>$type_filter,'page'=>$page],
                    $extra
                ), fn($v) => $v !== '' && $v !== 'all'));
            };
        ?>
        <div class="flex items-center gap-1">
            <a href="?<?php echo htmlspecialchars($qs(['page' => max(1, $page-1)])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page <= 1 ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-chevron-left text-xs"></i>
            </a>
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?<?php echo htmlspecialchars($qs(['page' => $i])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border font-medium transition-colors <?php echo $i === $page ? 'bg-amber-500/20 border-amber-500/40 text-amber-400' : 'bg-slate-800 border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <a href="?<?php echo htmlspecialchars($qs(['page' => min($total_pages, $page+1)])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page >= $total_pages ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-chevron-right text-xs"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/60  flex items-center justify-center hidden z-50 modal">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 rounded-lg bg-red-500/15 border border-red-500/30 flex items-center justify-center shrink-0">
                <i class="fas fa-trash text-red-400 text-sm"></i>
            </div>
            <h3 class="text-sm font-semibold text-white">Delete Voucher</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteVoucherId">
            <p class="text-xs text-slate-400 mb-4">
                Delete voucher <span id="deleteVoucherCode" class="text-white font-semibold font-mono"></span>? This cannot be undone.
            </p>
            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-colors">
                    <i class="fas fa-trash mr-1"></i>Delete
                </button>
                <button type="button" onclick="closeDeleteModal()"
                        class="flex-1 px-3 py-1.5 rounded-lg bg-slate-700/60 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-700 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Notification Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 space-y-2 z-50"></div>

<script>
    // Toggle Status Function
    function toggleStatus(id, currentStatus) {
        const form = document.createElement('form');
        form.method = 'POST';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'toggle_status';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;

        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = currentStatus ? 0 : 1;

        form.appendChild(actionInput);
        form.appendChild(idInput);
        form.appendChild(statusInput);

        document.body.appendChild(form);
        form.submit();
    }

    // Delete Modal Functions
    function openDeleteModal(id, code) {
        document.getElementById('deleteVoucherId').value = id;
        document.getElementById('deleteVoucherCode').textContent = code;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });

    // Toast notification function
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');

        const colors = {
            success: 'bg-emerald-500 text-white',
            error:   'bg-red-500 text-white',
            info:    'bg-blue-500 text-white',
            warning: 'bg-amber-500 text-slate-900'
        };

        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle',
            warning: 'fa-exclamation-triangle'
        };

        toast.className = `flex items-center gap-2 ${colors[type]} px-4 py-3 rounded-xl shadow-lg `;
        toast.innerHTML = `<i class="fas ${icons[type]}"></i><span>${message}</span>`;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) {
            return;
        }

        // Alt + A - Add voucher
        if (e.altKey && e.key === 'a') {
            e.preventDefault();
            window.location.href = 'voucher_form.php';
        }

        // Alt + D - Dashboard
        if (e.altKey && e.key === 'd') {
            e.preventDefault();
            window.location.href = 'index.php';
        }

        // Esc - Close any open modal
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                if (!modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                }
            });
        }
    });

    // Auto-hide success banner
    document.addEventListener('DOMContentLoaded', function () {
        const banner = document.getElementById('successBanner');
        if (banner) {
            setTimeout(() => {
                banner.style.transition = 'opacity 0.5s ease';
                banner.style.opacity = '0';
                setTimeout(() => banner.remove(), 500);
            }, 5000);
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
