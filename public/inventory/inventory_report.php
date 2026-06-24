<?php
/**
 * Inventory Report Page for Jakababa POS
 * Comprehensive inventory analytics and stock management reports
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Check for reports permission
if (!check_permission('reports.view') && !check_permission('inventory.manage') && !is_super_admin()) {
    enforce_permission('reports.view');
}

$pdo = get_db_connection();

// Get current user info
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$user_branch = (int) ($_SESSION['user']['branch_id'] ?? 1);

// Get filter parameters
$branch_filter = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$category_filter = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$stock_filter = $_GET['stock'] ?? 'all'; // all, low, out, in
$search = trim($_GET['search'] ?? '');
$sort_by = $_GET['sort'] ?? 'name'; // name, stock, price, value
$sort_order = $_GET['order'] ?? 'asc';

// Get all branches for filter
$branches = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM branches WHERE active = 1 ORDER BY name");
    $branches = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching branches: " . $e->getMessage());
}

// Get all categories for filter
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name, color FROM categories WHERE status = 'active' ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Build conditions
$params = [];
$conditions = [];

// Branch condition
if ($branch_filter > 0) {
    $conditions[] = "i.branch_id = :branch_id";
    $params[':branch_id'] = $branch_filter;
} elseif (!check_permission('branches.view') && !is_super_admin()) {
    $conditions[] = "i.branch_id = :branch_id";
    $params[':branch_id'] = $user_branch;
}

// Category condition
if ($category_filter > 0) {
    $conditions[] = "p.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

// Stock condition
if ($stock_filter === 'low') {
    $conditions[] = "i.stock > 0 AND i.stock <= i.reorder_level";
} elseif ($stock_filter === 'out') {
    $conditions[] = "i.stock <= 0";
} elseif ($stock_filter === 'in') {
    $conditions[] = "i.stock > i.reorder_level";
}

// Search condition
if (!empty($search)) {
    $conditions[] = "(p.name LIKE :search OR p.sku LIKE :search OR p.description LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";

// Determine sort
switch ($sort_by) {
    case 'stock':
        $order_by = "i.stock " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'price':
        $order_by = "p.price " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'value':
        $order_by = "stock_value " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'category':
        $order_by = "category_name " . ($sort_order === 'asc' ? 'ASC' : 'DESC') . ", p.name ASC";
        break;
    case 'name':
    default:
        $order_by = "p.name " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
}

// Initialize data arrays
$inventory_items = [];
$summary = [
    'total_products' => 0,
    'total_stock' => 0,
    'total_value' => 0,
    'total_cost' => 0,
    'potential_profit' => 0,
    'low_stock_count' => 0,
    'out_of_stock_count' => 0,
    'categories_count' => 0,
    'avg_stock_per_product' => 0
];
$category_stats = [];
$stock_levels = [
    'critical' => 0,
    'low' => 0,
    'normal' => 0,
    'high' => 0
];
$top_products = [];

try {
    // Main inventory query
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.description,
            p.price as selling_price,
            p.cost_price,
            COALESCE(i.stock, 0) as current_stock,
            COALESCE(i.reorder_level, 0) as reorder_level,
            COALESCE(i.minimum_stock, 0) as minimum_stock,
            COALESCE(i.maximum_stock, 0) as maximum_stock,
            c.id as category_id,
            c.name as category_name,
            c.color as category_color,
            b.id as branch_id,
            b.name as branch_name,
            (COALESCE(i.stock, 0) * p.price) as stock_value,
            (COALESCE(i.stock, 0) * p.cost_price) as stock_cost,
            (COALESCE(i.stock, 0) * (p.price - p.cost_price)) as potential_profit,
            CASE 
                WHEN COALESCE(i.stock, 0) <= 0 THEN 'out'
                WHEN COALESCE(i.stock, 0) <= COALESCE(i.reorder_level, 5) THEN 'low'
                ELSE 'in'
            END as stock_status,
            CASE 
                WHEN COALESCE(i.stock, 0) <= 0 THEN 'Out of Stock'
                WHEN COALESCE(i.stock, 0) <= COALESCE(i.reorder_level, 5) THEN 'Low Stock'
                ELSE 'In Stock'
            END as status_label,
            CASE 
                WHEN COALESCE(i.stock, 0) <= 0 THEN 'text-red-500'
                WHEN COALESCE(i.stock, 0) <= COALESCE(i.reorder_level, 5) THEN 'text-yellow-500'
                ELSE 'text-green-500'
            END as status_color
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN branches b ON i.branch_id = b.id
        " . $where_clause . "
        ORDER BY " . $order_by . "
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventory_items = $stmt->fetchAll();

    // Calculate summary statistics
    $summary['total_products'] = count($inventory_items);
    $summary['total_stock'] = array_sum(array_column($inventory_items, 'current_stock'));
    $summary['total_value'] = array_sum(array_column($inventory_items, 'stock_value'));
    $summary['total_cost'] = array_sum(array_column($inventory_items, 'stock_cost'));
    $summary['potential_profit'] = $summary['total_value'] - $summary['total_cost'];

    // Count stock statuses
    foreach ($inventory_items as $item) {
        if ($item['current_stock'] <= 0) {
            $summary['out_of_stock_count']++;
            $stock_levels['critical']++;
        } elseif ($item['current_stock'] <= $item['reorder_level']) {
            $summary['low_stock_count']++;
            $stock_levels['low']++;
        } elseif ($item['current_stock'] <= 50) {
            $stock_levels['normal']++;
        } else {
            $stock_levels['high']++;
        }
    }

    // Category statistics
    $category_stats_sql = "
        SELECT 
            c.id,
            c.name,
            c.color,
            COUNT(DISTINCT p.id) as product_count,
            COALESCE(SUM(i.stock), 0) as total_stock,
            COALESCE(SUM(i.stock * p.price), 0) as total_value,
            COALESCE(SUM(i.stock * p.cost_price), 0) as total_cost,
            COALESCE(SUM(i.stock * (p.price - p.cost_price)), 0) as total_profit,
            SUM(CASE WHEN i.stock <= i.reorder_level AND i.stock > 0 THEN 1 ELSE 0 END) as low_stock_count,
            SUM(CASE WHEN i.stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id AND p.deleted_at IS NULL
        LEFT JOIN inventory i ON p.id = i.product_id
        " . (!empty($branch_filter) ? " AND i.branch_id = :branch_id" : "") . "
        WHERE c.status = 'active' AND c.deleted_at IS NULL
        GROUP BY c.id
        ORDER BY total_value DESC
    ";

    $cat_params = [];
    if (!empty($branch_filter)) {
        $cat_params[':branch_id'] = $branch_filter;
    }
    $stmt = $pdo->prepare($category_stats_sql);
    $stmt->execute($cat_params);
    $category_stats = $stmt->fetchAll();
    $summary['categories_count'] = count($category_stats);

    // Top products by value
    $top_products_sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            COALESCE(i.stock, 0) as stock,
            p.price as selling_price,
            p.cost_price,
            (COALESCE(i.stock, 0) * p.price) as stock_value,
            (COALESCE(i.stock, 0) * (p.price - p.cost_price)) as potential_profit
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        " . $where_clause . "
        ORDER BY stock_value DESC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($top_products_sql);
    $stmt->execute($params);
    $top_products = $stmt->fetchAll();

    // Get monthly movement (if transaction table exists)
    $movement_data = [];
    try {
        $movement_sql = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(quantity) as items_sold,
                COUNT(DISTINCT sale_id) as transactions
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ";
        $stmt = $pdo->query($movement_sql);
        $movement_data = $stmt->fetchAll();
    } catch (Exception $e) {
        // Table might not exist
    }

} catch (PDOException $e) {
    error_log("Error fetching inventory report: " . $e->getMessage());
    $error = "Failed to load inventory data.";
}

// Calculate average stock per product
$summary['avg_stock_per_product'] = $summary['total_products'] > 0
    ? round($summary['total_stock'] / $summary['total_products'], 1)
    : 0;

$page_title = 'Inventory Report';
try {
    $sm = new SettingsManager($pdo, get_current_tenant_id());
    $currency_symbol = $sm->get('currency_symbol', 'KSh');
} catch (\Throwable $e) {
    $currency_symbol = 'KSh';
}
ob_start();
?>

<div class="fade-in" id="report-content">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Inventory</p>
            <h1 class="text-lg font-bold text-white">Inventory Report</h1>
            <p class="text-slate-500 text-xs mt-0.5">Comprehensive stock analysis and inventory metrics</p>
        </div>
        <div class="flex gap-2">
            <button onclick="exportToExcel()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium hover:bg-emerald-500/20 transition-colors">
                <i class="fas fa-file-csv text-xs"></i> Export CSV
            </button>
            <a href="inventory.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-arrow-left text-xs"></i> Back
            </a>
        </div>
    </div>

        <!-- Filters -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-5">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Branch</label>
                    <select name="branch_id" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="0">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo $branch_filter == $branch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Category</label>
                    <select name="category_id" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Stock Status</label>
                    <select name="stock" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="all" <?php echo $stock_filter === 'all' ? 'selected' : ''; ?>>All Items</option>
                        <option value="in" <?php echo $stock_filter === 'in' ? 'selected' : ''; ?>>In Stock</option>
                        <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock
                        </option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-slate-500 mb-1">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search by name, SKU..." class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-amber-500 text-black text-xs font-semibold rounded-lg hover:bg-amber-600 transition-colors">
                        <i class="fas fa-filter text-xs"></i> Apply
                    </button>
                    <a href="inventory_report.php" class="inline-flex items-center justify-center px-2 py-1.5 bg-slate-700 border border-slate-600 text-slate-400 text-xs rounded-lg hover:bg-slate-600 transition-colors">
                        <i class="fas fa-times text-xs"></i>
                    </a>
                </div>
            </form>
        </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0"><i class="fas fa-box text-blue-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-white"><?php echo number_format($summary['total_products']); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Products</div>
                <div class="text-[10px] text-slate-600"><?php echo $summary['categories_count']; ?> categories</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0"><i class="fas fa-cubes text-emerald-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-white"><?php echo number_format($summary['total_stock']); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Total Stock</div>
                <div class="text-[10px] text-slate-600">Avg <?php echo $summary['avg_stock_per_product']; ?>/product</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-coins text-amber-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-amber-400"><?php echo $currency_symbol; ?> <?php echo number_format($summary['total_value'], 0); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Inventory Value</div>
                <div class="text-[10px] text-slate-600">Cost: <?php echo $currency_symbol; ?> <?php echo number_format($summary['total_cost'], 0); ?></div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0"><i class="fas fa-chart-line text-emerald-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold <?php echo $summary['potential_profit'] >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>"><?php echo $currency_symbol; ?> <?php echo number_format($summary['potential_profit'], 0); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Potential Profit</div>
                <div class="text-[10px] text-slate-600">Margin: <?php echo $summary['total_value'] > 0 ? round(($summary['potential_profit'] / $summary['total_value']) * 100, 1) : 0; ?>%</div>
            </div>
        </div>
    </div>

    <!-- Stock Status Mini Cards -->
    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-slate-800/50 border border-slate-700/60 border-l-4 border-l-emerald-500 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0"><i class="fas fa-check-circle text-emerald-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-white"><?php echo $stock_levels['normal'] + $stock_levels['high']; ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">In Stock</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 border-l-4 border-l-amber-500 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-exclamation-triangle text-amber-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-amber-400"><?php echo $stock_levels['low']; ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Low Stock</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 border-l-4 border-l-red-500 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0"><i class="fas fa-times-circle text-red-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-red-400"><?php echo $stock_levels['critical']; ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Out of Stock</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
        <!-- Category Distribution -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2"><i class="fas fa-chart-pie text-amber-400 text-xs"></i> Inventory by Category</h3>
            <div class="relative" style="height:240px">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
        <!-- Stock Level Distribution -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2"><i class="fas fa-bar-chart text-amber-400 text-xs"></i> Stock Level Distribution</h3>
            <div class="relative" style="height:240px">
                <canvas id="stockLevelChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
        <div class="px-4 py-3 border-b border-slate-700/60">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2"><i class="fas fa-chart-pie text-amber-400 text-xs"></i> Category Breakdown</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-900/50 border-b border-slate-700/60">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                        <th class="px-3 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Products</th>
                        <th class="px-3 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Stock</th>
                        <th class="px-3 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Value</th>
                        <th class="px-3 py-2.5 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Alerts</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php if (empty($category_stats)): ?>
                    <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">No category data available</td></tr>
                    <?php else: foreach ($category_stats as $category): ?>
                    <tr class="hover:bg-slate-700/20 transition-colors">
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <?php if (!empty($category['color'])): ?>
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color:<?php echo htmlspecialchars($category['color']); ?>"></span>
                                <?php endif; ?>
                                <span class="text-slate-200 font-medium"><?php echo htmlspecialchars($category['name']); ?></span>
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-right text-slate-300"><?php echo $category['product_count']; ?></td>
                        <td class="px-3 py-2.5 text-right font-mono text-white"><?php echo number_format($category['total_stock']); ?></td>
                        <td class="px-3 py-2.5 text-right text-amber-400"><?php echo $currency_symbol; ?> <?php echo number_format($category['total_value'], 0); ?></td>
                        <td class="px-3 py-2.5 text-center">
                            <?php if ($category['out_of_stock_count'] > 0): ?>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-500/15 text-red-400 border border-red-500/30 mr-1">
                                <i class="fas fa-times-circle text-[8px]"></i><?php echo $category['out_of_stock_count']; ?> out
                            </span>
                            <?php endif; ?>
                            <?php if ($category['low_stock_count'] > 0): ?>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/15 text-amber-400 border border-amber-500/30">
                                <i class="fas fa-exclamation-triangle text-[8px]"></i><?php echo $category['low_stock_count']; ?> low
                            </span>
                            <?php endif; ?>
                            <?php if ($category['out_of_stock_count'] == 0 && $category['low_stock_count'] == 0): ?>
                            <span class="text-slate-600 text-[10px]">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
        <div class="px-4 py-3 border-b border-slate-700/60 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-clipboard-list text-amber-400 text-xs"></i> Inventory Details
                <span class="text-xs text-slate-500 font-normal">(<?php echo count($inventory_items); ?> items)</span>
            </h3>
            <div class="flex gap-2">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name', 'order' => $sort_by === 'name' && $sort_order === 'asc' ? 'desc' : 'asc'])); ?>" class="text-xs px-2 py-1 rounded-lg <?php echo $sort_by === 'name' ? 'bg-amber-500/10 text-amber-400' : 'text-slate-500 hover:text-white'; ?> transition-colors">Name <i class="fas fa-sort text-[10px] ml-0.5"></i></a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'stock', 'order' => $sort_by === 'stock' && $sort_order === 'asc' ? 'desc' : 'asc'])); ?>" class="text-xs px-2 py-1 rounded-lg <?php echo $sort_by === 'stock' ? 'bg-amber-500/10 text-amber-400' : 'text-slate-500 hover:text-white'; ?> transition-colors">Stock <i class="fas fa-sort text-[10px] ml-0.5"></i></a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'value', 'order' => $sort_by === 'value' && $sort_order === 'asc' ? 'desc' : 'asc'])); ?>" class="text-xs px-2 py-1 rounded-lg <?php echo $sort_by === 'value' ? 'bg-amber-500/10 text-amber-400' : 'text-slate-500 hover:text-white'; ?> transition-colors">Value <i class="fas fa-sort text-[10px] ml-0.5"></i></a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-900/50 border-b border-slate-700/60">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">SKU</th>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                        <th class="px-3 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Stock</th>
                        <th class="px-3 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Price</th>
                        <th class="px-3 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Value</th>
                        <th class="px-3 py-2.5 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php if (empty($inventory_items)): ?>
                    <tr>
                        <td colspan="7" class="px-3 py-12 text-center">
                            <i class="fas fa-box-open text-3xl text-slate-700 mb-3 block"></i>
                            <p class="text-slate-500">No inventory items found</p>
                        </td>
                    </tr>
                    <?php else: foreach ($inventory_items as $item):
                        $sColor = $item['stock_status'] === 'in' ? 'text-emerald-400' : ($item['stock_status'] === 'low' ? 'text-amber-400' : 'text-red-400');
                        $sBadge = $item['stock_status'] === 'in'
                            ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                            : ($item['stock_status'] === 'low'
                                ? 'bg-amber-500/15 text-amber-400 border border-amber-500/30'
                                : 'bg-red-500/15 text-red-400 border border-red-500/30');
                        $sIcon = $item['stock_status'] === 'in' ? 'fa-check-circle' : ($item['stock_status'] === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle');
                    ?>
                    <tr class="hover:bg-slate-700/20 transition-colors">
                        <td class="px-3 py-2.5">
                            <div class="text-slate-200 font-medium"><?php echo htmlspecialchars($item['name']); ?></div>
                            <?php if (!empty($item['description'])): ?>
                            <div class="text-[10px] text-slate-500"><?php echo htmlspecialchars(substr($item['description'], 0, 40)) . (strlen($item['description']) > 40 ? '…' : ''); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5"><code class="font-mono text-slate-400 text-[10px]"><?php echo htmlspecialchars($item['sku'] ?? '—'); ?></code></td>
                        <td class="px-3 py-2.5">
                            <?php if (!empty($item['category_name'])): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium"
                                style="background-color:<?php echo htmlspecialchars($item['category_color'] ?? '#FBBF24'); ?>20;color:<?php echo htmlspecialchars($item['category_color'] ?? '#FBBF24'); ?>">
                                <?php echo htmlspecialchars($item['category_name']); ?>
                            </span>
                            <?php else: ?><span class="text-slate-600">—</span><?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5 text-right">
                            <span class="font-mono font-semibold <?php echo $sColor; ?>"><?php echo number_format($item['current_stock']); ?></span>
                            <?php if ($item['reorder_level'] > 0 && $item['current_stock'] <= $item['reorder_level'] && $item['current_stock'] > 0): ?>
                            <div class="text-[10px] text-amber-500">reorder @ <?php echo $item['reorder_level']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5 text-right text-slate-300"><?php echo $currency_symbol; ?> <?php echo number_format($item['selling_price'], 0); ?></td>
                        <td class="px-3 py-2.5 text-right text-amber-400 font-mono"><?php echo $currency_symbol; ?> <?php echo number_format($item['stock_value'], 0); ?></td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold <?php echo $sBadge; ?>">
                                <i class="fas <?php echo $sIcon; ?> text-[8px]"></i><?php echo $item['status_label']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Products by Value -->
    <?php if (!empty($top_products)): ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
        <div class="px-4 py-3 border-b border-slate-700/60">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2"><i class="fas fa-crown text-amber-400 text-xs"></i> Top 10 Products by Value</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-4">
            <?php foreach ($top_products as $index => $product):
                $barPct = min(100, $product['stock'] > 0 ? ($product['stock'] / max(array_column($top_products,'stock'))) * 100 : 0);
                $barColor = $product['stock'] < 10 ? 'bg-red-500' : ($product['stock'] < 25 ? 'bg-amber-500' : 'bg-emerald-500');
            ?>
            <div class="bg-slate-700/30 border border-slate-700/60 rounded-lg p-3">
                <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center text-[10px] font-bold shrink-0"><?php echo $index + 1; ?></span>
                        <span class="text-xs font-medium text-slate-200 truncate"><?php echo htmlspecialchars($product['name']); ?></span>
                    </div>
                    <span class="text-xs font-bold text-amber-400 ml-2 shrink-0"><?php echo $currency_symbol; ?> <?php echo number_format($product['stock_value'], 0); ?></span>
                </div>
                <div class="flex gap-3 text-[10px] text-slate-400 mb-1.5">
                    <span>Stock: <span class="text-white"><?php echo $product['stock']; ?></span></span>
                    <span>Price: <span class="text-white"><?php echo $currency_symbol; ?> <?php echo number_format($product['selling_price'], 0); ?></span></span>
                    <span>Profit: <span class="text-emerald-400"><?php echo $currency_symbol; ?> <?php echo number_format($product['potential_profit'], 0); ?></span></span>
                </div>
                <div class="h-1.5 bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full <?php echo $barColor; ?> rounded-full transition-all" style="width:<?php echo $barPct; ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Monthly Movement -->
    <?php if (!empty($movement_data)): ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
        <div class="px-4 py-3 border-b border-slate-700/60">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2"><i class="fas fa-chart-line text-amber-400 text-xs"></i> Monthly Stock Movement (Last 6 Months)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-900/50 border-b border-slate-700/60">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Month</th>
                        <th class="px-3 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Items Sold</th>
                        <th class="px-3 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Transactions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php foreach ($movement_data as $movement): ?>
                    <tr class="hover:bg-slate-700/20 transition-colors">
                        <td class="px-3 py-2.5 text-slate-200"><?php echo date('F Y', strtotime($movement['month'] . '-01')); ?></td>
                        <td class="px-3 py-2.5 text-right font-mono text-white"><?php echo number_format($movement['items_sold']); ?></td>
                        <td class="px-3 py-2.5 text-right text-amber-400"><?php echo $movement['transactions']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Report Footer -->
    <div class="text-center text-xs text-slate-600 mt-5 pt-4 border-t border-slate-700/60">
        Generated <?php echo date('d M Y H:i:s'); ?> by <?php echo htmlspecialchars($user_name); ?> &mdash;
        <?php echo $summary['total_products']; ?> products across <?php echo $summary['categories_count']; ?> categories
    </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Category Chart
    const categoryCtx = document.getElementById('categoryChart')?.getContext('2d');
    if (categoryCtx) {
        const categoryData = <?php
            $cat_labels = []; $cat_values = []; $cat_colors = [];
            foreach (array_slice($category_stats, 0, 8) as $cat) {
                $cat_labels[] = $cat['name'];
                $cat_values[] = (float)$cat['total_value'];
                $cat_colors[] = $cat['color'] ?? '#FBBF24';
            }
            echo json_encode(['labels' => $cat_labels, 'values' => $cat_values, 'colors' => $cat_colors]);
        ?>;
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: { labels: categoryData.labels, datasets: [{ data: categoryData.values, backgroundColor: categoryData.colors, borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#94A3B8', font: { size: 11 } } } } }
        });
    }

    // Stock Level Chart
    const stockCtx = document.getElementById('stockLevelChart')?.getContext('2d');
    if (stockCtx) {
        new Chart(stockCtx, {
            type: 'bar',
            data: {
                labels: ['Out of Stock', 'Low Stock', 'Normal', 'High'],
                datasets: [{ data: [<?php echo $stock_levels['critical']; ?>, <?php echo $stock_levels['low']; ?>, <?php echo $stock_levels['normal']; ?>, <?php echo $stock_levels['high']; ?>], backgroundColor: ['#EF4444','#FBBF24','#10B981','#3B82F6'], borderRadius: 4 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#94A3B8', font: { size: 11 } } },
                    x: { grid: { display: false }, ticks: { color: '#94A3B8', font: { size: 11 } } }
                }
            }
        });
    }
});

function exportToExcel() {
    const rows = <?php
        $rows = [['Product','SKU','Category','Stock','Price','Value','Status']];
        foreach ($inventory_items as $it) {
            $rows[] = [
                $it['name'],
                $it['sku'] ?? '',
                $it['category_name'] ?? 'Uncategorized',
                $it['current_stock'],
                $it['selling_price'],
                $it['stock_value'],
                $it['status_label']
            ];
        }
        echo json_encode($rows);
    ?>;
    const csv = "Inventory Report\nGenerated,<?php echo date('Y-m-d H:i:s'); ?>\n\n" + rows.map(r => r.map(v => '"' + String(v).replace(/"/g,'""') + '"').join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = 'inventory_report_<?php echo date('Y-m-d'); ?>.csv';
    a.click();
}
</script>

</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>