<?php
/**
 * List Quotations Page - PURE TAILWIND CSS
 * Standard font size: 14px (text-sm) for body text
 * Larger fonts for emphasis: prices, totals, amounts
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('quotations.view') && !is_super_admin()) {
    enforce_permission('quotations.view');
}

$page_title = 'Quotations';
$tenant_id = get_current_tenant_id();
$current_branch_id = get_current_branch_id();
$current_branch_name = get_current_branch_name();

if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

$pdo = get_db_connection();

// Permissions
$is_super_admin = is_super_admin();
$can_view_all_branches = $is_super_admin || check_permission('branches.view');

// Get branches
$branches = [];
if ($can_view_all_branches) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name");
        $stmt->execute([$tenant_id]);
        $branches = $stmt->fetchAll();
    } catch (PDOException $e) {}
}

// Business types
$business_types = [
    ['id' => 1, 'name' => 'Retail Store', 'color' => '#3B82F6'],
    ['id' => 2, 'name' => 'Supermarket', 'color' => '#10B981'],
    ['id' => 3, 'name' => 'Restaurant', 'color' => '#F59E0B'],
    ['id' => 4, 'name' => 'Pharmacy', 'color' => '#EF4444'],
    ['id' => 5, 'name' => 'Salon', 'color' => '#EC4899'],
    ['id' => 6, 'name' => 'Electronics', 'color' => '#8B5CF6'],
    ['id' => 7, 'name' => 'Hardware', 'color' => '#F97316'],
    ['id' => 8, 'name' => 'Liquor Store', 'color' => '#7C3AED'],
    ['id' => 9, 'name' => 'Stationery', 'color' => '#0EA5E9'],
];

// Filters
$branch_filter = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : $current_branch_id;
$business_type_filter = isset($_GET['business_type_id']) ? (int) $_GET['business_type_id'] : 0;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Fetch quotations
$quotations = [];
$total_quotations = 0;
$summary = ['total_amount' => 0, 'draft_count' => 0, 'sent_count' => 0, 'accepted_count' => 0, 'expired_count' => 0, 'rejected_count' => 0];

try {
    // Check if quotations table exists
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('quotations', $tables)) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `quotations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `quotation_number` varchar(50) NOT NULL,
                `customer_id` int(11) DEFAULT NULL,
                `tenant_id` bigint(20) UNSIGNED NOT NULL,
                `branch_id` int(11) NOT NULL,
                `business_type_id` int(11) DEFAULT NULL,
                `created_by` int(11) NOT NULL,
                `total` decimal(15,2) DEFAULT 0.00,
                `status` enum('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
                `valid_until` date DEFAULT NULL,
                `notes` text DEFAULT NULL,
                `terms` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `quotation_number` (`quotation_number`),
                KEY `idx_tenant_branch` (`tenant_id`, `branch_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    if (!in_array('quotation_items', $tables)) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `quotation_items` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `quotation_id` int(11) NOT NULL,
                `product_id` int(11) DEFAULT NULL,
                `product_name` varchar(255) DEFAULT NULL,
                `quantity` int(11) NOT NULL,
                `price` decimal(15,2) NOT NULL,
                `subtotal` decimal(15,2) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_quotation` (`quotation_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    $sql = "SELECT q.*, c.name as customer_name, c.phone, u.name as created_by_name,
                   (SELECT COUNT(*) FROM quotation_items WHERE quotation_id = q.id) as item_count
            FROM quotations q
            LEFT JOIN customers c ON q.customer_id = c.id AND c.tenant_id = q.tenant_id
            LEFT JOIN users u ON q.created_by = u.id
            WHERE q.tenant_id = ? AND q.branch_id = ?";
    $params = [$tenant_id, $branch_filter];
    
    if ($business_type_filter > 0) {
        $sql .= " AND q.business_type_id = ?";
        $params[] = $business_type_filter;
    }
    if (!empty($status_filter)) {
        $sql .= " AND q.status = ?";
        $params[] = $status_filter;
    }
    if (!empty($date_from)) {
        $sql .= " AND DATE(q.created_at) >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $sql .= " AND DATE(q.created_at) <= ?";
        $params[] = $date_to;
    }
    if (!empty($search)) {
        $sql .= " AND (q.quotation_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Count total
    $countSql = preg_replace('/SELECT.*FROM/', 'SELECT COUNT(*) as total FROM', $sql);
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total_quotations = (int) $stmt->fetchColumn();
    
    // Get paginated results
    $sql .= " ORDER BY q.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $quotations = $stmt->fetchAll();
    
    // Summary
    $summarySql = "SELECT COALESCE(SUM(total),0) as total_amount,
                   SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) as draft_count,
                   SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) as sent_count,
                   SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) as accepted_count,
                   SUM(CASE WHEN status='expired' THEN 1 ELSE 0 END) as expired_count,
                   SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected_count
                   FROM quotations WHERE tenant_id=? AND branch_id=?";
    $summaryParams = [$tenant_id, $branch_filter];
    if ($business_type_filter > 0) {
        $summarySql .= " AND business_type_id=?";
        $summaryParams[] = $business_type_filter;
    }
    $stmt = $pdo->prepare($summarySql);
    $stmt->execute($summaryParams);
    $summary = $stmt->fetch() ?: $summary;
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

$total_pages = $total_quotations > 0 ? ceil($total_quotations / $limit) : 1;
$currency_symbol = get_settings('currency', 'KSh', $tenant_id);

ob_start();
?>

<style>
    input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.6); cursor: pointer; }
</style>

<div class="space-y-4">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2 text-sm font-semibold text-amber-400 uppercase tracking-wider mb-1">
                <i class="fas fa-file-invoice text-sm"></i>
                <span>QUOTATIONS</span>
            </div>
            <h1 class="text-2xl font-bold text-white">Quotations</h1>
            <p class="text-sm text-slate-500 mt-0.5">Manage and track customer quotations &bull; <i class="fas fa-store text-xs"></i> <?php echo htmlspecialchars($current_branch_name); ?></p>
        </div>
        <div class="flex items-center gap-3">
            <a href="add_quotation.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-all">
                <i class="fas fa-plus text-sm"></i> New Quotation
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-coins text-blue-400"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-slate-500 uppercase tracking-wider">Total Value</p>
                    <p class="text-base font-bold text-white truncate"><?php echo format_currency($summary['total_amount']); ?></p>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-slate-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-file text-slate-400"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wider">Draft</p>
                    <p class="text-2xl font-bold text-slate-400"><?php echo $summary['draft_count']; ?></p>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-paper-plane text-blue-400"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wider">Sent</p>
                    <p class="text-2xl font-bold text-blue-400"><?php echo $summary['sent_count']; ?></p>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-check-circle text-emerald-400"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wider">Accepted</p>
                    <p class="text-2xl font-bold text-emerald-400"><?php echo $summary['accepted_count']; ?></p>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-orange-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-clock text-orange-400"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wider">Expired</p>
                    <p class="text-2xl font-bold text-orange-400"><?php echo $summary['expired_count']; ?></p>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-times-circle text-red-400"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wider">Rejected</p>
                    <p class="text-2xl font-bold text-red-400"><?php echo $summary['rejected_count']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
        <form method="GET" class="space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <?php if ($can_view_all_branches && !empty($branches)): ?>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Branch</label>
                    <select name="branch_id" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors">
                        <option value="0">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo $branch_filter == $branch['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Business Type</label>
                    <select name="business_type_id" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors">
                        <option value="0">All Types</option>
                        <?php foreach ($business_types as $bt): ?>
                            <option value="<?php echo $bt['id']; ?>" <?php echo $business_type_filter == $bt['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($bt['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div class="md:col-span-2 relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by number, customer, phone..." class="w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 placeholder-slate-600 transition-colors">
                </div>
                <select name="status" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors">
                    <option value="">All Status</option>
                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                    <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                    <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors">
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors">
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">Apply</button>
                    <?php if (!empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($search) || $business_type_filter > 0): ?>
                        <a href="list_quotation.php<?php echo $can_view_all_branches ? '?branch_id=' . $branch_filter : ''; ?>" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-300 text-sm hover:bg-slate-600 transition-colors">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Quotations Table -->
    <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-900/50 border-b border-slate-700/50">
                    <tr class="text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-4 py-3 text-left">Quote #</th>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Customer</th>
                        <th class="px-4 py-3 text-left hidden lg:table-cell">Created By</th>
                        <th class="px-4 py-3 text-center w-16">Items</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Valid Until</th>
                        <th class="px-4 py-3 text-center w-32">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php if (empty($quotations)): ?>
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center">
                                <i class="fas fa-inbox text-4xl text-slate-700 mb-3 block"></i>
                                <p class="text-slate-500 text-sm">No quotations found</p>
                                <a href="add_quotation.php" class="inline-block mt-3 text-sm text-amber-400 hover:underline">Create your first quotation →</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($quotations as $q):
                            $statusColors = [
                                'draft' => 'bg-slate-500/15 text-slate-400',
                                'sent' => 'bg-blue-500/15 text-blue-400',
                                'accepted' => 'bg-emerald-500/15 text-emerald-400',
                                'expired' => 'bg-orange-500/15 text-orange-400',
                                'rejected' => 'bg-red-500/15 text-red-400'
                            ];
                            $statusColor = $statusColors[$q['status']] ?? 'bg-slate-500/15 text-slate-400';
                            $statusIcon = $q['status'] === 'accepted' ? 'fa-check-circle' : ($q['status'] === 'sent' ? 'fa-paper-plane' : ($q['status'] === 'expired' ? 'fa-clock' : ($q['status'] === 'rejected' ? 'fa-times-circle' : 'fa-file')));
                            $valid_until = $q['valid_until'] ? date('d M Y', strtotime($q['valid_until'])) : '—';
                            $is_expiring = $q['valid_until'] && strtotime($q['valid_until']) <= strtotime('+3 days') && $q['status'] !== 'expired';
                            $customerName = htmlspecialchars($q['customer_name'] ?? 'Walk-in Customer');
                            $quoteNumber = htmlspecialchars($q['quotation_number']);
                        ?>
                            <tr class="cursor-pointer hover:bg-slate-700/20 transition-colors" onclick="window.location.href='view_quotation.php?id=<?php echo $q['id']; ?>'">
                                <td class="px-4 py-3">
                                    <span class="font-mono text-sm text-amber-400 font-semibold"><?php echo $quoteNumber; ?></span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-300"><?php echo date('d M Y', strtotime($q['created_at'])); ?></td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-white font-medium"><?php echo $customerName; ?></div>
                                    <?php if (!empty($q['phone'])): ?>
                                        <div class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($q['phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell text-sm text-slate-400"><?php echo htmlspecialchars($q['created_by_name'] ?? '-'); ?></td>
                                <td class="px-4 py-3 text-center text-sm text-slate-400"><?php echo (int) $q['item_count']; ?></td>
                                <td class="px-4 py-3 text-right">
                                    <span class="text-base font-bold text-amber-400"><?php echo format_currency($q['total']); ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-center">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                                            <i class="fas <?php echo $statusIcon; ?> text-xs"></i>
                                            <?php echo ucfirst($q['status']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-center">
                                        <span class="text-sm <?php echo $is_expiring && $q['status'] !== 'expired' ? 'text-amber-400 font-medium' : 'text-slate-400'; ?>">
                                            <?php echo $valid_until; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5" onclick="event.stopPropagation()">
                                        <a href="view_quotation.php?id=<?php echo $q['id']; ?>" class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-400 hover:bg-blue-500/20 transition-colors" title="View">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                        <?php if ($q['status'] === 'accepted'): ?>
                                            <a href="convert_to_sale.php?id=<?php echo $q['id']; ?>" class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400 hover:bg-emerald-500/20 transition-colors" title="Convert to Sale">
                                                <i class="fas fa-cash-register text-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($q['status'] === 'draft'): ?>
                                            <a href="edit_quotation.php?id=<?php echo $q['id']; ?>" class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400 hover:bg-amber-500/20 transition-colors" title="Edit">
                                                <i class="fas fa-pen text-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="print_quotation.php?id=<?php echo $q['id']; ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center text-slate-400 hover:bg-slate-700 hover:text-slate-200 transition-colors" title="Print">
                                            <i class="fas fa-print text-xs"></i>
                                        </a>
                                        <?php if ($q['status'] === 'draft' || $is_super_admin): ?>
                                            <button onclick="deleteQuotation(<?php echo $q['id']; ?>)" class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center text-red-400 hover:bg-red-500/20 transition-colors" title="Delete">
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

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-4 py-3 border-t border-slate-700/50 flex flex-wrap justify-between items-center gap-3">
            <div class="text-sm text-slate-500">
                Showing <span class="text-white font-semibold"><?php echo $offset + 1; ?></span> to <span class="text-white font-semibold"><?php echo min($offset + $limit, $total_quotations); ?></span> of <span class="text-white font-semibold"><?php echo $total_quotations; ?></span> quotations
            </div>
            <div class="flex gap-1.5">
                <?php
                $query_params = array_filter([
                    'branch_id' => $can_view_all_branches ? $branch_filter : null,
                    'business_type_id' => $business_type_filter ?: null,
                    'status' => $status_filter,
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'search' => $search
                ]);
                $query_string = http_build_query($query_params);
                $query_prefix = $query_string ? '?' . $query_string . '&' : '?';
                ?>
                <?php if ($page > 1): ?>
                    <a href="list_quotation.php<?php echo $query_prefix . 'page=' . ($page - 1); ?>" class="px-3 py-1.5 rounded-lg text-sm bg-slate-800/60 border border-slate-700/60 text-slate-400 hover:bg-amber-500/10 hover:text-amber-400 transition-colors">
                        <i class="fas fa-chevron-left mr-1"></i> Prev
                    </a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="list_quotation.php<?php echo $query_prefix . 'page=' . $i; ?>" class="px-3 py-1.5 rounded-lg text-sm transition-colors <?php echo $i === $page ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30 font-semibold' : 'bg-slate-800/60 border border-slate-700/60 text-slate-400 hover:bg-amber-500/10 hover:text-amber-400'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="list_quotation.php<?php echo $query_prefix . 'page=' . ($page + 1); ?>" class="px-3 py-1.5 rounded-lg text-sm bg-slate-800/60 border border-slate-700/60 text-slate-400 hover:bg-amber-500/10 hover:text-amber-400 transition-colors">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function deleteQuotation(id) {
    if (confirm('Are you sure you want to delete this quotation? This action cannot be undone.')) {
        window.location.href = 'delete_quotation.php?id=' + id;
    }
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>