<?php
/**
 * Sales Report Page for Jakababa POS - PURE TAILWIND CSS
 * Comprehensive sales analytics and reporting with filters and visualizations
 */

$page_title = 'Sales Report';
ob_start();

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
safe_require('logger.php', 'src', true);

// Check for reports permission
if (!check_permission('reports.view') && !is_super_admin()) {
    enforce_permission('reports.view');
}

$pdo = get_db_connection();

// Get current user info
$user_id = get_current_user_id();
$user_name = get_current_user_name();
$user_role = $_SESSION['role'] ?? '';
$user_branch = get_current_branch_id();
$tenant_id = get_current_tenant_id();

// Business type detection
$business_type = get_current_business_type($tenant_id);
$bt_config = get_business_type_config($business_type) ?? [];
$bt_name = $bt_config['name'] ?? 'Retail';
$bt_sale_label = $bt_config['sale_label'] ?? 'Sale';
$bt_sales_label = $bt_config['sales_label'] ?? 'Sales';
$bt_customer_label = $bt_config['customer_label'] ?? 'Customer';
$currency_symbol = get_tenant_currency() ?? 'KES';

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$branch_filter = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$user_filter = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$payment_filter = $_GET['payment'] ?? '';
$status_filter = $_GET['status'] ?? 'completed';
$group_by = $_GET['group_by'] ?? 'day'; // day, week, month, year

// Get all branches for filter
$branches = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching branches: " . $e->getMessage());
}

// Build parameters
$params = [
    ':tenant_id' => $tenant_id,
    ':date_from' => $date_from,
    ':date_to' => $date_to
];

$conditions = [];
$conditions[] = "tenant_id = :tenant_id";
$conditions[] = "DATE(created_at) BETWEEN :date_from AND :date_to";

// Branch condition
if ($branch_filter > 0) {
    $conditions[] = "branch_id = :branch_id";
    $params[':branch_id'] = $branch_filter;
} elseif (!check_permission('branches.view') && !is_super_admin()) {
    $conditions[] = "branch_id = :branch_id";
    $params[':branch_id'] = $user_branch;
}

// User condition
if ($user_filter > 0) {
    $conditions[] = "user_id = :user_id";
    $params[':user_id'] = $user_filter;
}

// Payment condition
if (!empty($payment_filter)) {
    $conditions[] = "payment_method = :payment";
    $params[':payment'] = $payment_filter;
}

// Status condition
if (!empty($status_filter) && $status_filter !== 'all') {
    $conditions[] = "status = :status AND voided = 0";
    $params[':status'] = $status_filter;
} else {
    $conditions[] = "status = 'completed' AND voided = 0";
}

$where_clause = implode(' AND ', $conditions);

// Determine grouping SQL
switch ($group_by) {
    case 'week':
        $group_format = "CONCAT(YEAR(created_at), '-W', WEEK(created_at))";
        $group_label = "YEARWEEK(created_at)";
        $chart_label = "Weekly";
        break;
    case 'month':
        $group_format = "DATE_FORMAT(created_at, '%Y-%m')";
        $group_label = "DATE_FORMAT(created_at, '%Y-%m')";
        $chart_label = "Monthly";
        break;
    case 'year':
        $group_format = "YEAR(created_at)";
        $group_label = "YEAR(created_at)";
        $chart_label = "Yearly";
        break;
    default:
        $group_format = "DATE(created_at)";
        $group_label = "DATE(created_at)";
        $chart_label = "Daily";
}

// Initialize data arrays
$sales_data = [];
$summary = [
    'total_revenue' => 0,
    'total_transactions' => 0,
    'avg_transaction_value' => 0,
    'total_discounts' => 0,
    'total_tax' => 0,
    'unique_customers' => 0,
    'active_days' => 0,
    'min_transaction' => 0,
    'max_transaction' => 0
];
$payment_breakdown = [];
$top_products = [];
$category_breakdown = [];
$hourly_data = [];
$weekday_data = [];

try {
    // Summary statistics
    $summary_sql = "
        SELECT 
            COUNT(*) as total_transactions,
            COALESCE(SUM(total), 0) as total_revenue,
            COALESCE(AVG(total), 0) as avg_transaction_value,
            COALESCE(SUM(discount), 0) as total_discounts,
            COALESCE(SUM(tax), 0) as total_tax,
            COUNT(DISTINCT customer_id) as unique_customers,
            COUNT(DISTINCT DATE(created_at)) as active_days,
            COALESCE(MIN(total), 0) as min_transaction,
            COALESCE(MAX(total), 0) as max_transaction
        FROM sales 
        WHERE " . $where_clause;

    $stmt = $pdo->prepare($summary_sql);
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure numeric values
    foreach ($summary as $key => $value) {
        $summary[$key] = (float)$value;
    }

    // Sales by time period
    $time_sql = "
        SELECT 
            {$group_format} as period,
            DATE(created_at) as date,
            COUNT(*) as transaction_count,
            COALESCE(SUM(total), 0) as total_sales,
            COALESCE(AVG(total), 0) as average_sale,
            COALESCE(SUM(discount), 0) as total_discount
        FROM sales 
        WHERE " . $where_clause . "
        GROUP BY {$group_label}
        ORDER BY period ASC
    ";

    $stmt = $pdo->prepare($time_sql);
    $stmt->execute($params);
    $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Payment method breakdown
    $payment_sql = "
        SELECT 
            payment_method,
            COUNT(*) as count,
            COALESCE(SUM(total), 0) as total,
            COALESCE(AVG(total), 0) as average
        FROM sales 
        WHERE " . $where_clause . "
        GROUP BY payment_method
        ORDER BY total DESC
    ";

    $stmt = $pdo->prepare($payment_sql);
    $stmt->execute($params);
    $payment_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top products
    $products_sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            COUNT(DISTINCT s.id) as times_sold,
            SUM(si.quantity) as quantity_sold,
            COALESCE(SUM(si.quantity * si.price), 0) as revenue,
            COALESCE(AVG(si.price), 0) as avg_price
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        WHERE s.tenant_id = :tenant_id 
            AND DATE(s.created_at) BETWEEN :date_from AND :date_to
            AND s.status = 'completed' AND s.voided = 0
        GROUP BY p.id, p.name, p.sku
        ORDER BY revenue DESC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($products_sql);
    $stmt->execute([':tenant_id' => $tenant_id, ':date_from' => $date_from, ':date_to' => $date_to]);
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Category breakdown
    $category_sql = "
        SELECT 
            c.id,
            COALESCE(c.name, 'Uncategorized') as name,
            c.color,
            COUNT(DISTINCT s.id) as transaction_count,
            SUM(si.quantity) as quantity_sold,
            COALESCE(SUM(si.quantity * si.price), 0) as revenue
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE s.tenant_id = :tenant_id 
            AND DATE(s.created_at) BETWEEN :date_from AND :date_to
            AND s.status = 'completed' AND s.voided = 0
        GROUP BY c.id
        ORDER BY revenue DESC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($category_sql);
    $stmt->execute([':tenant_id' => $tenant_id, ':date_from' => $date_from, ':date_to' => $date_to]);
    $category_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hourly distribution
    $hourly_sql = "
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as transaction_count,
            COALESCE(SUM(total), 0) as total_sales
        FROM sales 
        WHERE " . $where_clause . "
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ";

    $stmt = $pdo->prepare($hourly_sql);
    $stmt->execute($params);
    $hourly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Weekday distribution
    $weekday_sql = "
        SELECT 
            DAYOFWEEK(created_at) as weekday,
            COUNT(*) as transaction_count,
            COALESCE(SUM(total), 0) as total_sales
        FROM sales 
        WHERE " . $where_clause . "
        GROUP BY DAYOFWEEK(created_at)
        ORDER BY weekday
    ";

    $stmt = $pdo->prepare($weekday_sql);
    $stmt->execute($params);
    $weekday_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching sales report: " . $e->getMessage());
}

// Calculate growth compared to previous period
$growth_percentage = 0;
$growth_count = 0;

try {
    $days_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24);
    $prev_date_from = date('Y-m-d', strtotime($date_from . ' -' . ($days_diff + 1) . ' days'));
    $prev_date_to = date('Y-m-d', strtotime($date_from . ' -1 day'));

    $prev_sql = "
        SELECT COALESCE(SUM(total), 0) as total, COUNT(*) as count
        FROM sales 
        WHERE tenant_id = :tenant_id 
            AND DATE(created_at) BETWEEN :date_from AND :date_to
            AND status = 'completed' AND voided = 0
    ";

    $stmt = $pdo->prepare($prev_sql);
    $stmt->execute([':tenant_id' => $tenant_id, ':date_from' => $prev_date_from, ':date_to' => $prev_date_to]);
    $prev_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $growth_percentage = ($prev_data['total'] ?? 0) > 0
        ? round((($summary['total_revenue'] - $prev_data['total']) / $prev_data['total']) * 100, 1)
        : 0;

    $growth_count = ($prev_data['count'] ?? 0) > 0
        ? round((($summary['total_transactions'] - $prev_data['count']) / $prev_data['count']) * 100, 1)
        : 0;

} catch (Exception $e) {
    error_log("Error calculating growth: " . $e->getMessage());
}

$weekdays = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="space-y-4">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-400 uppercase tracking-wider mb-1">
                <i class="fas fa-chart-line text-xs"></i>
                <span>Analytics</span>
            </div>
            <h1 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($bt_sales_label); ?> Report</h1>
            <p class="text-sm text-slate-500 mt-1">Comprehensive <?php echo strtolower($bt_sales_label); ?> analytics and performance metrics</p>
        </div>
        <div class="flex gap-2">
            <button onclick="exportToPDF()" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm hover:bg-slate-700 transition-all">
                <i class="fas fa-file-pdf text-red-400"></i> PDF
            </button>
            <button onclick="exportToExcel()" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm hover:bg-slate-700 transition-all">
                <i class="fas fa-file-csv text-emerald-400"></i> CSV
            </button>
            <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm hover:bg-slate-700 transition-all">
                <i class="fas fa-print text-blue-400"></i> Print
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-5">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Branch</label>
                <select name="branch_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <option value="0">All Branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo (int)$branch['id']; ?>" <?php echo $branch_filter == $branch['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Payment</label>
                <select name="payment" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <option value="">All Payments</option>
                    <option value="cash" <?php echo $payment_filter === 'cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="card" <?php echo $payment_filter === 'card' ? 'selected' : ''; ?>>Card</option>
                    <option value="mpesa" <?php echo $payment_filter === 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
                    <option value="bank_transfer" <?php echo $payment_filter === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                    <option value="credit" <?php echo $payment_filter === 'credit' ? 'selected' : ''; ?>>Credit</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Group By</label>
                <select name="group_by" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <option value="day" <?php echo $group_by === 'day' ? 'selected' : ''; ?>>Daily</option>
                    <option value="week" <?php echo $group_by === 'week' ? 'selected' : ''; ?>>Weekly</option>
                    <option value="month" <?php echo $group_by === 'month' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="year" <?php echo $group_by === 'year' ? 'selected' : ''; ?>>Yearly</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm hover:bg-amber-500/25 transition-all">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="px-4 py-2 bg-slate-700 rounded-lg text-slate-400 hover:bg-slate-600 transition-all">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <div class="flex justify-between items-start mb-2">
                <span class="text-xs text-slate-500 uppercase tracking-wider">Total Revenue</span>
                <span class="text-xs <?php echo $growth_percentage >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                    <i class="fas fa-arrow-<?php echo $growth_percentage >= 0 ? 'up' : 'down'; ?> mr-1"></i>
                    <?php echo abs($growth_percentage); ?>%
                </span>
            </div>
            <p class="text-2xl font-bold text-white"><?php echo $currency_symbol; ?> <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></p>
            <p class="text-xs text-slate-500 mt-1">vs previous period</p>
        </div>

        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <div class="flex justify-between items-start mb-2">
                <span class="text-xs text-slate-500 uppercase tracking-wider">Transactions</span>
                <span class="text-xs <?php echo $growth_count >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                    <i class="fas fa-arrow-<?php echo $growth_count >= 0 ? 'up' : 'down'; ?> mr-1"></i>
                    <?php echo abs($growth_count); ?>%
                </span>
            </div>
            <p class="text-2xl font-bold text-white"><?php echo number_format($summary['total_transactions'] ?? 0); ?></p>
            <p class="text-xs text-slate-500 mt-1"><?php echo (int)($summary['active_days'] ?? 0); ?> active days</p>
        </div>

        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <span class="text-xs text-slate-500 uppercase tracking-wider block mb-2">Average Order</span>
            <p class="text-2xl font-bold text-white"><?php echo $currency_symbol; ?> <?php echo number_format($summary['avg_transaction_value'] ?? 0, 2); ?></p>
            <p class="text-xs text-slate-500 mt-1">Min: <?php echo $currency_symbol; ?> <?php echo number_format($summary['min_transaction'] ?? 0, 2); ?></p>
        </div>

        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <span class="text-xs text-slate-500 uppercase tracking-wider block mb-2">Unique Customers</span>
            <p class="text-2xl font-bold text-white"><?php echo number_format($summary['unique_customers'] ?? 0); ?></p>
            <p class="text-xs text-slate-500 mt-1">Total distinct customers</p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Sales Trend Chart -->
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <h3 class="text-sm font-semibold text-white mb-4">Sales Trend (<?php echo $chart_label; ?>)</h3>
            <div class="h-64">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>

        <!-- Payment Methods Chart -->
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <h3 class="text-sm font-semibold text-white mb-4">Payment Methods</h3>
            <div class="h-64">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Hourly & Weekday Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Hourly Distribution -->
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <h3 class="text-sm font-semibold text-white mb-4">Hourly Sales Distribution</h3>
            <div class="h-64">
                <canvas id="hourlyChart"></canvas>
            </div>
        </div>

        <!-- Weekday Distribution -->
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <h3 class="text-sm font-semibold text-white mb-4">Sales by Day of Week</h3>
            <div class="h-64">
                <canvas id="weekdayChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Products & Category Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Top Products -->
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <div class="px-5 py-3 border-b border-slate-700">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-crown text-amber-400"></i>
                    Top Selling Products
                </h3>
            </div>
            <div class="p-4 space-y-3">
                <?php if (empty($top_products)): ?>
                    <p class="text-slate-500 text-center py-4">No product data available</p>
                <?php else: ?>
                    <?php 
                    $max_revenue = (float)($top_products[0]['revenue'] ?? 1);
                    foreach ($top_products as $idx => $product): 
                        $width = ((float)$product['revenue'] / $max_revenue) * 100;
                    ?>
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 rounded-md <?php echo $idx === 0 ? 'bg-amber-500/30' : 'bg-slate-700/50'; ?> text-amber-400 flex items-center justify-center text-xs font-bold flex-shrink-0"><?php echo $idx + 1; ?></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center">
                                    <span class="font-medium text-white text-sm truncate"><?php echo htmlspecialchars($product['name']); ?></span>
                                    <span class="text-amber-400 text-xs font-semibold"><?php echo (int)$product['quantity_sold']; ?> sold</span>
                                </div>
                                <div class="flex justify-between text-xs text-slate-500 mt-0.5">
                                    <span class="truncate">SKU: <?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></span>
                                    <span><?php echo $currency_symbol; ?> <?php echo number_format($product['revenue'], 2); ?></span>
                                </div>
                                <div class="w-full bg-slate-700 rounded-full h-1 mt-1.5 overflow-hidden">
                                    <div class="bg-gradient-to-r from-amber-400 to-amber-500 h-1 rounded-full transition-all" style="width: <?php echo $width; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <div class="px-5 py-3 border-b border-slate-700">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-tags text-amber-400"></i>
                    Sales by Category
                </h3>
            </div>
            <div class="p-4 space-y-3">
                <?php if (empty($category_breakdown)): ?>
                    <p class="text-slate-500 text-center py-4">No category data available</p>
                <?php else: ?>
                    <?php 
                    $max_cat_revenue = (float)($category_breakdown[0]['revenue'] ?? 1);
                    foreach ($category_breakdown as $category): 
                        $cat_width = ((float)$category['revenue'] / $max_cat_revenue) * 100;
                    ?>
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <div class="flex items-center gap-2">
                                    <?php if (!empty($category['color'])): ?>
                                        <span class="w-3 h-3 rounded-full" style="background-color: <?php echo htmlspecialchars($category['color']); ?>"></span>
                                    <?php else: ?>
                                        <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                                    <?php endif; ?>
                                    <span class="text-white text-sm"><?php echo htmlspecialchars($category['name']); ?></span>
                                </div>
                                <span class="text-amber-400 text-xs font-semibold"><?php echo $currency_symbol; ?> <?php echo number_format($category['revenue'], 2); ?></span>
                            </div>
                            <div class="w-full bg-slate-700 rounded-full h-1 overflow-hidden">
                                <div class="bg-gradient-to-r from-amber-400 to-amber-500 h-1 rounded-full transition-all" style="width: <?php echo $cat_width; ?>%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-slate-500 mt-1">
                                <span><?php echo (int)$category['transaction_count']; ?> transactions</span>
                                <span><?php echo (int)$category['quantity_sold']; ?> items</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sales Data Table -->
    <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
        <div class="px-5 py-3 border-b border-slate-700">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-table text-amber-400"></i>
                Sales Breakdown
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Period</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Transactions</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Revenue</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Average</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Discounts</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    <?php if (empty($sales_data)): ?>
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-slate-500">No sales data available for selected period</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales_data as $row): ?>
                            <tr>
                                <td class="px-5 py-3 text-slate-300 font-medium"><?php echo htmlspecialchars($row['period']); ?></td>
                                <td class="px-5 py-3 text-right text-slate-300"><?php echo number_format($row['transaction_count']); ?></td>
                                <td class="px-5 py-3 text-right text-emerald-400 font-semibold"><?php echo $currency_symbol; ?> <?php echo number_format($row['total_sales'], 2); ?></td>
                                <td class="px-5 py-3 text-right text-slate-300"><?php echo $currency_symbol; ?> <?php echo number_format($row['average_sale'], 2); ?></td>
                                <td class="px-5 py-3 text-right text-red-400"><?php echo $currency_symbol; ?> <?php echo number_format($row['total_discount'] ?? 0, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Report Footer -->
    <div class="text-center text-xs text-slate-500 pt-6 border-t border-slate-700">
        <p><i class="fas fa-chart-line mr-1 text-amber-400"></i> Generated on <?php echo date('d M Y H:i:s'); ?> by <?php echo htmlspecialchars($user_name); ?></p>
        <p class="mt-1">Report period: <?php echo date('d M Y', strtotime($date_from)); ?> — <?php echo date('d M Y', strtotime($date_to)); ?></p>
    </div>
</div>

<script>
// Sales Trend Chart
const trendCtx = document.getElementById('salesTrendChart')?.getContext('2d');
if (trendCtx) {
    const trendData = <?php
    $labels = [];
    $values = [];
    foreach ($sales_data as $row) {
        $labels[] = $row['period'];
        $values[] = (float)$row['total_sales'];
    }
    echo json_encode(['labels' => $labels, 'values' => $values]);
    ?>;

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendData.labels,
            datasets: [{
                label: 'Revenue',
                data: trendData.values,
                borderColor: '#fbbf24',
                backgroundColor: 'rgba(251, 191, 36, 0.1)',
                borderWidth: 2,
                pointBackgroundColor: '#fbbf24',
                pointBorderColor: '#1e293b',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => '<?php echo $currency_symbol; ?> ' + ctx.raw.toLocaleString() } }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8', callback: (v) => '<?php echo $currency_symbol; ?> ' + v.toLocaleString() } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });
}

// Payment Chart
const paymentCtx = document.getElementById('paymentChart')?.getContext('2d');
if (paymentCtx) {
    const paymentData = <?php
    $labels = [];
    $values = [];
    $colors = [];
    foreach ($payment_breakdown as $method) {
        $labels[] = ucfirst($method['payment_method'] ?? 'Unknown');
        $values[] = (float)($method['total'] ?? 0);
        switch ($method['payment_method'] ?? '') {
            case 'cash': $color = '#10b981'; break;
            case 'card': $color = '#3b82f6'; break;
            case 'mpesa': $color = '#f59e0b'; break;
            case 'bank_transfer': $color = '#8b5cf6'; break;
            case 'credit': $color = '#ef4444'; break;
            default: $color = '#fbbf24';
        }
        $colors[] = $color;
    }
    echo json_encode(['labels' => $labels, 'values' => $values, 'colors' => $colors]);
    ?>;

    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: paymentData.labels,
            datasets: [{
                data: paymentData.values,
                backgroundColor: paymentData.colors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 10 } } },
                tooltip: { callbacks: { label: (ctx) => `${ctx.label}: <?php echo $currency_symbol; ?> ${ctx.raw.toLocaleString()} (${((ctx.raw / ctx.dataset.data.reduce((a,b)=>a+b,0))*100).toFixed(1)}%)` } }
            }
        }
    });
}

// Hourly Chart
const hourlyCtx = document.getElementById('hourlyChart')?.getContext('2d');
if (hourlyCtx) {
    const hourlyData = <?php
    $labels = [];
    $values = [];
    for ($i = 0; $i < 24; $i++) {
        $labels[] = $i . ':00';
        $found = false;
        foreach ($hourly_data as $row) {
            if (($row['hour'] ?? '') == $i) {
                $values[] = (float)$row['total_sales'];
                $found = true;
                break;
            }
        }
        if (!$found) $values[] = 0;
    }
    echo json_encode(['labels' => $labels, 'values' => $values]);
    ?>;

    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: hourlyData.labels,
            datasets: [{
                label: 'Sales',
                data: hourlyData.values,
                backgroundColor: '#fbbf24',
                borderRadius: 4,
                barPercentage: 0.85
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => '<?php echo $currency_symbol; ?> ' + ctx.raw.toLocaleString() } } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8', maxRotation: 45, font: { size: 9 } } }
            }
        }
    });
}

// Weekday Chart
const weekdayCtx = document.getElementById('weekdayChart')?.getContext('2d');
if (weekdayCtx) {
    const weekdayData = <?php
    $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $values = array_fill(0, 7, 0);
    foreach ($weekday_data as $row) {
        $index = ($row['weekday'] ?? 1) - 1;
        if ($index >= 0 && $index < 7) {
            $values[$index] = (float)$row['total_sales'];
        }
    }
    echo json_encode(['labels' => $labels, 'values' => $values]);
    ?>;

    new Chart(weekdayCtx, {
        type: 'bar',
        data: {
            labels: weekdayData.labels,
            datasets: [{
                label: 'Sales',
                data: weekdayData.values,
                backgroundColor: '#fbbf24',
                borderRadius: 4,
                barPercentage: 0.7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => '<?php echo $currency_symbol; ?> ' + ctx.raw.toLocaleString() } } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });
}

function exportToPDF() {
    const element = document.querySelector('.space-y-4');
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: 'sales_report_<?php echo date('Y-m-d'); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}

function exportToExcel() {
    let csv = "Sales Report\n";
    csv += "Period,<?php echo $date_from; ?> to <?php echo $date_to; ?>\n\n";
    csv += "Period,Transactions,Revenue,Average,Discounts\n";
    
    <?php foreach ($sales_data as $row): ?>
        csv += "<?php echo addslashes($row['period']); ?>,<?php echo (int)$row['transaction_count']; ?>,<?php echo (float)$row['total_sales']; ?>,<?php echo (float)$row['average_sale']; ?>,<?php echo (float)($row['total_discount'] ?? 0); ?>\n";
    <?php endforeach; ?>
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sales_report_<?php echo date('Y-m-d'); ?>.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>