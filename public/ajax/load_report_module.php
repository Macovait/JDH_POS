<?php
/**
 * AJAX Module Loader for Reports
 * Routes requests to the appropriate report module
 */

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/../../src/db.php';

// Suppress all errors AFTER includes (config.php resets them)
error_reporting(0);
ini_set('display_errors', '0');

// Start output buffering to capture any warnings
ob_start();

require_login();

// Determine module early for module-specific permission fallbacks
$module = $_GET['module'] ?? 'sales';

// Validate module is in allowed list (defense in depth - client sends this)
$allowedModules = isset($_GET['allowed_modules']) ? explode(',', $_GET['allowed_modules']) : [];
if (!empty($allowedModules) && !in_array($module, $allowedModules)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Module not in allowed list for your role']);
    exit;
}

// Base permission gate
$hasAccess = is_super_admin() || check_permission('reports.view');

// Module-specific fallbacks (for roles that can view underlying data but not global reports)
if (!$hasAccess) {
    switch ($module) {
        case 'sales':
            $hasAccess = check_permission('sales.view') || check_permission('sales.*');
            break;
        case 'inventory':
            $hasAccess = check_permission('inventory.reports')
                      || check_permission('inventory.view')
                      || check_permission('inventory.*')
                      || check_permission('products.view');
            break;
        case 'customers':
            $hasAccess = check_permission('customers.view') || check_permission('customers.*');
            break;
        case 'tax':
            $hasAccess = check_permission('reports.view') || check_permission('sales.view');
            break;
        case 'profit_loss':
            $hasAccess = check_permission('reports.view') || check_permission('sales.view') || check_permission('expenses.view');
            break;
        case 'custom':
            $hasAccess = check_permission('reports.view') || check_permission('sales.view');
            break;
        default:
            $hasAccess = false;
    }
}

if (!$hasAccess) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get parameters
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$requested_branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$business_type = $_GET['business_type'] ?? 'retail';
$user_id = (int) ($_GET['user_id'] ?? 0);
$currency = $_GET['currency'] ?? $_SESSION['tenant_currency'] ?? 'USD';
$bt_sales_label = $_GET['bt_sales_label'] ?? 'Sales';
$bt_customer_label = $_GET['bt_customer_label'] ?? 'Customer';

$pdo = get_db_connection();
$tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);

// Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
$branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
if ($branch_id <= 0 && $requested_branch_id > 0) {
    $branch_id = 0; // Ignore invalid branch_id for unauthorized users
}

// Validate date parameters
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');

// Set session variables for modules to consume
$_SESSION['report_filters'] = [
    'from' => $from,
    'to' => $to,
    'branch_id' => $branch_id,
    'business_type' => $business_type,
    'user_id' => $user_id,
    'tenant_id' => $tenant_id,
    'currency' => $currency,
    'bt_sales_label' => $bt_sales_label,
    'bt_customer_label' => $bt_customer_label
];

// Set global variables for modules
$GLOBALS['report_from'] = $from;
$GLOBALS['report_to'] = $to;
$GLOBALS['report_branch_id'] = $branch_id;
$GLOBALS['report_business_type'] = $business_type;
$GLOBALS['report_currency'] = $currency;
$GLOBALS['report_user_id'] = $user_id;
$GLOBALS['report_tenant_id'] = $tenant_id;

$response = ['success' => true, 'html' => '', 'error' => null, 'charts' => [], 'table_data' => []];

ob_start();

try {
    switch ($module) {
        case 'sales':
            // ==================== SALES REPORT MODULE ====================
            // Build query
            $sql = "SELECT 
                        DATE(created_at) as period,
                        COUNT(*) as transaction_count,
                        COALESCE(SUM(total), 0) as total_sales,
                        COALESCE(AVG(total), 0) as average_sale,
                        COALESCE(SUM(discount_amount), 0) as total_discount,
                        COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_sales,
                        COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card_sales,
                        COALESCE(SUM(CASE WHEN payment_method = 'mpesa' THEN total ELSE 0 END), 0) as mpesa_sales
                    FROM sales 
                    WHERE tenant_id = ? 
                        AND DATE(created_at) BETWEEN ? AND ?
                        AND status = 'completed'
                        AND voided = 0";
            
            $params = [$tenant_id, $from, $to];
            
            if ($branch_id > 0) {
                $sql .= " AND branch_id = ?";
                $params[] = $branch_id;
            }
            
            $sql .= " GROUP BY DATE(created_at) ORDER BY period DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $sales_data = $stmt->fetchAll();
            $response['table_data'] = $sales_data;
            
            // Calculate totals
            $total_revenue = array_sum(array_column($sales_data, 'total_sales'));
            $total_transactions = array_sum(array_column($sales_data, 'transaction_count'));
$avg_transaction = $total_transactions > 0 ? $total_revenue / $total_transactions : 0;
            
            // Get unique customers
            $cust_sql = "SELECT COUNT(DISTINCT customer_id) as unique_customers FROM sales WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ? AND status = 'completed' AND voided = 0";
            $cust_params = [$tenant_id, $from, $to];
            if ($branch_id > 0) {
                $cust_sql .= " AND branch_id = ?";
                $cust_params[] = $branch_id;
            }
            $cust_stmt = $pdo->prepare($cust_sql);
            $cust_stmt->execute($cust_params);
            $unique_customers = $cust_stmt->fetchColumn() ?: 0;
            
            // Get payment methods breakdown
            $pay_sql = "SELECT 
                            payment_method,
                            COALESCE(SUM(total), 0) as total
                        FROM sales 
                        WHERE tenant_id = ? 
                            AND DATE(created_at) BETWEEN ? AND ?
                            AND status = 'completed' AND voided = 0";
            $pay_params = [$tenant_id, $from, $to];
            if ($branch_id > 0) {
                $pay_sql .= " AND branch_id = ?";
                $pay_params[] = $branch_id;
            }
            $pay_sql .= " GROUP BY payment_method ORDER BY total DESC";
            $pay_stmt = $pdo->prepare($pay_sql);
            $pay_stmt->execute($pay_params);
            $payment_methods = $pay_stmt->fetchAll();
            
            $colors = ['cash' => '#10b981', 'card' => '#3b82f6', 'mpesa' => '#f59e0b', 'bank_transfer' => '#8b5cf6'];
            
            // Get top products
            $prod_sql = "SELECT 
                            p.name,
                            p.sku,
                            SUM(si.quantity) as quantity_sold,
                            COALESCE(SUM(si.subtotal), 0) as revenue
                        FROM sale_items si
                        JOIN products p ON si.product_id = p.id
                        JOIN sales s ON si.sale_id = s.id
                        WHERE s.tenant_id = ? 
                            AND DATE(s.created_at) BETWEEN ? AND ?
                            AND s.status = 'completed' AND s.voided = 0";
            $prod_params = [$tenant_id, $from, $to];
            if ($branch_id > 0) {
                $prod_sql .= " AND s.branch_id = ?";
                $prod_params[] = $branch_id;
            }
            $prod_sql .= " GROUP BY si.product_id ORDER BY revenue DESC LIMIT 10";
            $prod_stmt = $pdo->prepare($prod_sql);
            $prod_stmt->execute($prod_params);
            $top_products = $prod_stmt->fetchAll();
            
// Get hourly distribution
            $hour_sql = "SELECT 
                            HOUR(created_at) as hour,
                            COALESCE(SUM(total), 0) as total_sales
                        FROM sales 
                        WHERE tenant_id = ? 
                            AND DATE(created_at) BETWEEN ? AND ?
                            AND status = 'completed' AND voided = 0";
            $hour_params = [$tenant_id, $from, $to];
            if ($branch_id > 0) {
                $hour_sql .= " AND branch_id = ?";
                $hour_params[] = $branch_id;
            }
            $hour_sql .= " GROUP BY HOUR(created_at) ORDER BY hour";
            $hour_stmt = $pdo->prepare($hour_sql);
            $hour_stmt->execute($hour_params);
            $hourly_data = $hour_stmt->fetchAll();
            $hourly_values = array_fill(0, 24, 0);
            foreach ($hourly_data as $h) {
                $hourly_values[(int)$h['hour']] = (float)$h['total_sales'];
            }
            
            // Get weekday distribution
            $week_sql = "SELECT 
                            DAYOFWEEK(created_at) as weekday,
                            COALESCE(SUM(total), 0) as total_sales
                        FROM sales 
                        WHERE tenant_id = ? 
                            AND DATE(created_at) BETWEEN ? AND ?
                            AND status = 'completed' AND voided = 0";
            $week_params = [$tenant_id, $from, $to];
if ($branch_id > 0) {
                $week_sql .= " AND branch_id = ?";
                $week_params[] = $branch_id;
            }
            $week_sql .= " GROUP BY DAYOFWEEK(created_at)";
            $week_stmt = $pdo->prepare($week_sql);
            $week_stmt->execute($week_params);
            $weekday_data = $week_stmt->fetchAll();
            $weekday_values = array_fill(0, 7, 0);
            $weekday_labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            foreach ($weekday_data as $w) {
                $idx = (int)$w['weekday'] - 1;
                if ($idx >= 0 && $idx < 7) {
                    $weekday_values[$idx] = (float)$w['total_sales'];
                }
            }
            
            // Prepare chart data
            $response['charts']['sales_trend'] = [
                'labels' => array_column($sales_data, 'period'),
                'values' => array_column($sales_data, 'total_sales')
            ];
            $response['charts']['payment_methods'] = [
                'labels' => array_column($payment_methods, 'payment_method'),
                'values' => array_column($payment_methods, 'total')
            ];
            ?>
            <div class="space-y-6">
                <!-- KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs text-gray-500 uppercase tracking-wider">Total Revenue</span>
                        </div>
                        <p class="text-2xl font-bold text-white"><?php echo $currency . ' ' . number_format($total_revenue, 2); ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?php echo $total_transactions; ?> transactions</p>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs text-gray-500 uppercase tracking-wider">Average Transaction</span>
                        </div>
                        <p class="text-2xl font-bold text-white"><?php echo $currency . ' ' . number_format($avg_transaction, 2); ?></p>
                        <p class="text-xs text-gray-500 mt-1">Per sale</p>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs text-gray-500 uppercase tracking-wider">Unique Customers</span>
                        </div>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($unique_customers); ?></p>
                        <p class="text-xs text-gray-500 mt-1">Active customers</p>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs text-gray-500 uppercase tracking-wider">Discounts Given</span>
                        </div>
                        <p class="text-2xl font-bold text-white"><?php echo $currency . ' ' . number_format(array_sum(array_column($sales_data, 'total_discount')), 2); ?></p>
                        <p class="text-xs text-gray-500 mt-1">Total discounts</p>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <h3 class="text-sm font-semibold text-white mb-4">Sales Trend</h3>
                        <div class="h-64">
                            <canvas id="salesTrendChart"></canvas>
                        </div>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <h3 class="text-sm font-semibold text-white mb-4">Payment Methods</h3>
                        <div class="h-64">
                            <canvas id="paymentChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Hourly & Weekly Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <h3 class="text-sm font-semibold text-white mb-4">Hourly Distribution</h3>
                        <div class="h-64">
                            <canvas id="hourlyChart"></canvas>
                        </div>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <h3 class="text-sm font-semibold text-white mb-4">Sales by Day</h3>
                        <div class="h-64">
                            <canvas id="weekdayChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Products -->
                <?php if (!empty($top_products)): ?>
                <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden card-hover">
                    <div class="px-5 py-3 border-b border-slate-700">
                        <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-crown text-amber-400"></i> Top Selling Products
                        </h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <?php foreach ($top_products as $idx => $product): ?>
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 rounded-md bg-amber-500/20 text-amber-400 flex items-center justify-center text-xs font-bold"><?php echo $idx + 1; ?></span>
                            <div class="flex-1">
                                <div class="flex justify-between">
                                    <span class="text-white"><?php echo htmlspecialchars($product['name']); ?></span>
                                    <span class="text-amber-400 text-xs"><?php echo $product['quantity_sold']; ?> sold</span>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Revenue</span>
                                    <span><?php echo $currency . ' ' . number_format($product['revenue'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sales Table -->
                <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden card-hover">
                    <div class="px-5 py-3 border-b border-slate-700">
                        <h3 class="text-sm font-semibold text-white">Sales Breakdown</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-900/50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Transactions</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Revenue</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Average</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php foreach ($sales_data as $row): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-5 py-3 text-gray-300"><?php echo date('d M Y', strtotime($row['period'])); ?></td>
                                    <td class="px-5 py-3 text-right text-gray-300"><?php echo number_format($row['transaction_count']); ?></td>
                                    <td class="px-5 py-3 text-right text-emerald-400 font-semibold"><?php echo $currency . ' ' . number_format($row['total_sales'], 2); ?></td>
                                    <td class="px-5 py-3 text-right text-gray-300"><?php echo $currency . ' ' . number_format($row['average_sale'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                const currency = '<?php echo $currency; ?>';
                
                // Sales Trend Chart
                const trendCtx = document.getElementById('salesTrendChart')?.getContext('2d');
                if (trendCtx) {
                    new Chart(trendCtx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode(array_column($sales_data, 'period')); ?>,
                            datasets: [{
                                label: 'Revenue',
                                data: <?php echo json_encode(array_column($sales_data, 'total_sales')); ?>,
                                borderColor: '#fbbf24',
                                backgroundColor: 'rgba(251,191,36,0.1)',
                                borderWidth: 2,
                                pointBackgroundColor: '#fbbf24',
                                pointBorderColor: '#1e293b',
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => currency + ' ' + ctx.raw.toLocaleString() } } },
                            scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }, x: { grid: { display: false }, ticks: { color: '#94a3b8' } } }
                        }
                    });
                }

                // Payment Chart
                const paymentCtx = document.getElementById('paymentChart')?.getContext('2d');
                if (paymentCtx && <?php echo json_encode($payment_methods); ?>?.length) {
                    const colors = { cash: '#10b981', card: '#3b82f6', mpesa: '#f59e0b', bank_transfer: '#8b5cf6' };
                    new Chart(paymentCtx, {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode(array_column($payment_methods, 'payment_method')); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_column($payment_methods, 'total')); ?>,
                                backgroundColor: <?php echo json_encode(array_map(function($m) use ($colors) { return $colors[$m['payment_method']] ?? '#fbbf24'; }, $payment_methods)); ?>,
                                borderWidth: 0
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8' } } } }
                    });
                }

                // Hourly Chart
                const hourlyCtx = document.getElementById('hourlyChart')?.getContext('2d');
                if (hourlyCtx) {
                    new Chart(hourlyCtx, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode(range(0, 23)); ?>,
                            datasets: [{
                                label: 'Sales',
                                data: <?php echo json_encode($hourly_values); ?>,
                                backgroundColor: '#fbbf24',
                                borderRadius: 4
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }, x: { grid: { display: false }, ticks: { color: '#94a3b8', maxRotation: 45 } } } }
                    });
                }

                // Weekday Chart
                const weekdayCtx = document.getElementById('weekdayChart')?.getContext('2d');
                if (weekdayCtx) {
                    new Chart(weekdayCtx, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($weekday_labels); ?>,
                            datasets: [{
                                label: 'Sales',
                                data: <?php echo json_encode($weekday_values); ?>,
                                backgroundColor: '#fbbf24',
                                borderRadius: 4
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }, x: { grid: { display: false }, ticks: { color: '#94a3b8' } } } }
                    });
                }
            })();
            </script>
            <?php
break;
            
        case 'profit_loss':
            // ==================== PROFIT & LOSS MODULE ====================
            // Get Income (Sales)
            $income_sql = "SELECT COALESCE(SUM(total), 0) as total FROM sales WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ? AND status = 'completed' AND voided = 0";
            $income_params = [$tenant_id, $from, $to];
            if ($branch_id > 0) {
                $income_sql .= " AND branch_id = ?";
                $income_params[] = $branch_id;
            }
            $income_stmt = $pdo->prepare($income_sql);
            $income_stmt->execute($income_params);
            $total_income = $income_stmt->fetchColumn() ?: 0;
            
            // Get Expenses
            $expense_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE tenant_id = ? AND DATE(expense_date) BETWEEN ? AND ? AND status = 'approved'";
            $expense_params = [$tenant_id, $from, $to];
            if ($branch_id > 0) {
                $expense_sql .= " AND branch_id = ?";
                $expense_params[] = $branch_id;
            }
            $expense_stmt = $pdo->prepare($expense_sql);
            $expense_stmt->execute($expense_params);
            $total_expenses = $expense_stmt->fetchColumn() ?: 0;
            
// Get Income by Payment Method
            $pay_sql = "SELECT payment_method, COALESCE(SUM(total), 0) as total FROM sales WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ? AND status = 'completed' AND voided = 0";
            $pay_params = [$tenant_id, $from, $to];
            if ($branch_id > 0) {
                $pay_sql .= " AND branch_id = ?";
                $pay_params[] = $branch_id;
            }
            $pay_sql .= " GROUP BY payment_method ORDER BY total DESC";
            $pay_stmt = $pdo->prepare($pay_sql);
            $pay_stmt->execute($pay_params);
            $income_by_payment = $pay_stmt->fetchAll();
            
            // Get Expenses by Category
            $cat_sql = "SELECT ec.name as category, COALESCE(SUM(e.amount), 0) as total FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id WHERE e.tenant_id = ? AND DATE(e.expense_date) BETWEEN ? AND ? AND e.status = 'approved'";
            $cat_params = [$tenant_id, $from, $to];
            if ($branch_id > 0) {
                $cat_sql .= " AND e.branch_id = ?";
                $cat_params[] = $branch_id;
            }
            $cat_sql .= " GROUP BY ec.id ORDER BY total DESC LIMIT 10";
            $cat_stmt = $pdo->prepare($cat_sql);
            $cat_stmt->execute($cat_params);
            $expense_by_category = $cat_stmt->fetchAll();
            
            $gross_profit = $total_income - $total_expenses;
            $profit_margin = $total_income > 0 ? ($gross_profit / $total_income) * 100 : 0;
            ?>
            <div class="space-y-6">
                <!-- KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs text-gray-500 uppercase tracking-wider">Total Income</span>
                        </div>
                        <p class="text-2xl font-bold text-emerald-400"><?php echo $currency . ' ' . number_format($total_income, 2); ?></p>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs text-gray-500 uppercase tracking-wider">Total Expenses</span>
                        </div>
                        <p class="text-2xl font-bold text-red-400"><?php echo $currency . ' ' . number_format($total_expenses, 2); ?></p>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs text-gray-500 uppercase tracking-wider">Gross Profit</span>
                        </div>
                        <p class="text-2xl font-bold <?php echo $gross_profit >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                            <?php echo $currency . ' ' . number_format($gross_profit, 2); ?>
                        </p>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs text-gray-500 uppercase tracking-wider">Profit Margin</span>
                        </div>
                        <p class="text-2xl font-bold <?php echo $profit_margin >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                            <?php echo number_format($profit_margin, 1); ?>%
                        </p>
                    </div>
                </div>

                <!-- Income by Payment Method -->
                <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden card-hover">
                    <div class="px-5 py-3 border-b border-slate-700">
                        <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-chart-pie text-amber-400"></i> Income by Payment Method
                        </h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <?php foreach ($income_by_payment as $method): ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-white capitalize"><?php echo $method['payment_method']; ?></span>
                                <span class="text-emerald-400 font-semibold"><?php echo $currency . ' ' . number_format($method['total'], 2); ?></span>
                            </div>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $total_income > 0 ? ($method['total'] / $total_income) * 100 : 0; ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Expenses by Category -->
                <?php if (!empty($expense_by_category)): ?>
                <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden card-hover">
                    <div class="px-5 py-3 border-b border-slate-700">
                        <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-chart-line text-amber-400"></i> Expenses by Category
                        </h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <?php foreach ($expense_by_category as $category): ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-white"><?php echo htmlspecialchars($category['category']); ?></span>
                                <span class="text-red-400 font-semibold"><?php echo $currency . ' ' . number_format($category['total'], 2); ?></span>
                            </div>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $total_expenses > 0 ? ($category['total'] / $total_expenses) * 100 : 0; ?>%; background: linear-gradient(90deg, #ef4444, #dc2626);"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Summary -->
                <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-5 card-hover">
                    <div class="flex justify-between items-center py-2">
                        <span class="text-white font-semibold">Total Income</span>
                        <span class="text-emerald-400 font-bold"><?php echo $currency . ' ' . number_format($total_income, 2); ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-white font-semibold">Total Expenses</span>
                        <span class="text-red-400 font-bold"><?php echo $currency . ' ' . number_format($total_expenses, 2); ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-t border-slate-700 mt-2 pt-2">
                        <span class="text-white font-bold">Net Profit / (Loss)</span>
                        <span class="text-xl font-bold <?php echo $gross_profit >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                            <?php echo $currency . ' ' . number_format($gross_profit, 2); ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php
            break;
            
        case 'inventory':
            // ==================== INVENTORY REPORT MODULE ====================
            $inv_sql = "SELECT p.id, p.name, p.sku, p.price, COALESCE(i.stock, 0) as stock, COALESCE(i.reorder_level, 5) as reorder_level, c.name as category_name
                        FROM products p
                        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
                        LEFT JOIN categories c ON p.category_id = c.id
                        WHERE p.tenant_id = ? AND p.status = 1
                        ORDER BY i.stock ASC
                        LIMIT 50";
            $inv_branch = $branch_id > 0 ? $branch_id : (int) (get_current_branch_id() ?? 0);
            $inv_stmt = $pdo->prepare($inv_sql);
            $inv_stmt->execute([$inv_branch, $tenant_id]);
            $inventory_items = $inv_stmt->fetchAll();
            
            $low_stock_count = 0;
            $out_of_stock_count = 0;
            $total_stock_value = 0;
            foreach ($inventory_items as $item) {
                $stock = (int)($item['stock'] ?? 0);
                $reorder = (int)($item['reorder_level'] ?? 5);
                if ($stock <= 0) $out_of_stock_count++;
                elseif ($stock <= $reorder) $low_stock_count++;
                $total_stock_value += $stock * (float)($item['price'] ?? 0);
            }
            ?>
            <div class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Total Products</div>
                        <div class="text-2xl font-bold text-white"><?php echo count($inventory_items); ?></div>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Low Stock Alert</div>
                        <div class="text-2xl font-bold text-amber-400"><?php echo $low_stock_count; ?></div>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Out of Stock</div>
                        <div class="text-2xl font-bold text-red-400"><?php echo $out_of_stock_count; ?></div>
                    </div>
                </div>
                
                <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-700">
                        <h3 class="text-sm font-semibold text-white">Inventory Status</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-900/50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Product</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">SKU</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Stock</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Reorder Level</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php foreach ($inventory_items as $item): 
                                    $stock = (int)($item['stock'] ?? 0);
                                    $reorder = (int)($item['reorder_level'] ?? 5);
                                    $status = $stock <= 0 ? 'Out of Stock' : ($stock <= $reorder ? 'Low Stock' : 'In Stock');
                                    $statusColor = $stock <= 0 ? 'text-red-400' : ($stock <= $reorder ? 'text-amber-400' : 'text-emerald-400');
                                ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-5 py-3 text-gray-300"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td class="px-5 py-3 text-gray-400 text-xs font-mono"><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></td>
                                    <td class="px-5 py-3 text-right font-semibold <?php echo $statusColor; ?>"><?php echo $stock; ?></td>
                                    <td class="px-5 py-3 text-right text-gray-400"><?php echo $reorder; ?></td>
                                    <td class="px-5 py-3"><span class="text-xs <?php echo $statusColor; ?>"><?php echo $status; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
            break;
            
case 'customers':
             // ==================== CUSTOMERS REPORT MODULE ====================
             $cust_sql = "SELECT c.id, c.name, c.phone, c.email, c.loyalty_points, c.total_spent, c.last_purchase,
                                 COUNT(s.id) as purchase_count
                         FROM customers c
                         LEFT JOIN sales s ON c.id = s.customer_id AND DATE(s.created_at) BETWEEN ? AND ? AND s.status = 'completed' AND s.voided = 0
                         WHERE c.tenant_id = ?
                         GROUP BY c.id
                         ORDER BY c.total_spent DESC
                         LIMIT 50";
             $cust_stmt = $pdo->prepare($cust_sql);
             $cust_stmt->execute([$from, $to, $tenant_id]);
             $customers = $cust_stmt->fetchAll();
            ?>
            <div class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Total Customers</div>
                        <div class="text-2xl font-bold text-white"><?php echo count($customers); ?></div>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Active This Period</div>
                        <div class="text-2xl font-bold text-amber-400">
                            <?php echo count(array_filter($customers, fn($c) => ($c['purchase_count'] ?? 0) > 0)); ?>
                        </div>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Loyalty Points</div>
                        <div class="text-2xl font-bold text-emerald-400">
                            <?php echo number_format(array_sum(array_column($customers, 'loyalty_points'))); ?>
                        </div>
                    </div>
                </div>
                
                <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-700">
                        <h3 class="text-sm font-semibold text-white">Top Customers by Spend</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-900/50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Customer</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Contact</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Purchases</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Total Spent</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Loyalty Points</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php foreach (array_slice($customers, 0, 20) as $cust): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center text-white text-xs font-bold">
                                                <?php echo strtoupper(substr($cust['name'] ?? '?', 0, 1)); ?>
                                            </div>
                                            <span class="text-white font-medium"><?php echo htmlspecialchars($cust['name'] ?? '—'); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-gray-400 text-sm"><?php echo htmlspecialchars($cust['phone'] ?? $cust['email'] ?? '—'); ?></td>
                                    <td class="px-5 py-3 text-right text-gray-300"><?php echo (int)($cust['purchase_count'] ?? 0); ?></td>
                                    <td class="px-5 py-3 text-right text-emerald-400 font-semibold"><?php echo $currency . ' ' . number_format($cust['total_spent'] ?? 0, 2); ?></td>
                                    <td class="px-5 py-3 text-right text-amber-400"><?php echo number_format($cust['loyalty_points'] ?? 0); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
</div>
            <?php
            break;
            
        case 'tax':
            // ==================== TAX REPORT MODULE ===================
            $tax_sql = "SELECT 
                            DATE(created_at) as sale_date,
                            COALESCE(SUM(tax_amount), 0) as tax_collected,
                            COUNT(*) as transaction_count
                        FROM sales 
                        WHERE tenant_id = ? 
                            AND DATE(created_at) BETWEEN ? AND ?
                            AND status = 'completed' AND voided = 0";
            $tax_params = [$tenant_id, $from, $to];
            if ($branch_id > 0) {
                $tax_sql .= " AND branch_id = ?";
                $tax_params[] = $branch_id;
            }
            $tax_sql .= " GROUP BY DATE(created_at) ORDER BY sale_date DESC";
            $tax_stmt = $pdo->prepare($tax_sql);
            $tax_stmt->execute($tax_params);
            $tax_data = $tax_stmt->fetchAll();
            $total_tax = array_sum(array_column($tax_data, 'tax_collected'));
            ?>
            <div class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Total Tax Collected</div>
                        <div class="text-2xl font-bold text-white"><?php echo $currency . ' ' . number_format($total_tax, 2); ?></div>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Taxable Transactions</div>
                        <div class="text-2xl font-bold text-white"><?php echo count($tax_data); ?></div>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 card-hover">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Tax Rate</div>
                        <div class="text-2xl font-bold text-amber-400">16%</div>
                    </div>
                 </div>
                
                <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-700">
                        <h3 class="text-sm font-semibold text-white">Tax Collected Breakdown</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-900/50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Transactions</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Tax Collected</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php foreach ($tax_data as $row): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-5 py-3 text-gray-300"><?php echo date('d M Y', strtotime($row['sale_date'])); ?></td>
                                    <td class="px-5 py-3 text-right text-gray-300"><?php echo number_format($row['transaction_count']); ?></td>
                                    <td class="px-5 py-3 text-right text-emerald-400 font-semibold"><?php echo $currency . ' ' . number_format($row['tax_collected'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
            break;
            
        case 'custom':
        default:
            // ==================== CUSTOM BUILDER MODULE ====================
            ?>
            <div class="space-y-6">
                <!-- Builder Controls -->
                <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <i class="fas fa-sliders-h text-2xl text-amber-400"></i>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Custom Report Builder</h3>
                            <p class="text-xs text-gray-500">Build reports from live data with your own criteria</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label for="crb_report_type" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Report Type</label>
                            <select id="crb_report_type" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <option value="sales">Sales</option>
                                <option value="products">Products</option>
                                <option value="customers">Customers</option>
                                <option value="inventory">Inventory</option>
                            </select>
                        </div>
                        <div>
                            <label for="crb_group_by" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Group By</label>
                            <select id="crb_group_by" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <option value="day">Daily</option>
                                <option value="week">Weekly</option>
                                <option value="month">Monthly</option>
                                <option value="year">Yearly</option>
                            </select>
                        </div>
                        <div>
                            <label for="crb_sort_by" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Sort By</label>
                            <select id="crb_sort_by" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <option value="date">Date / Name</option>
                                <option value="revenue">Revenue</option>
                                <option value="transactions">Transactions / Qty</option>
                            </select>
                        </div>
                        <div>
                            <label for="crb_limit" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Limit</label>
                            <select id="crb_limit" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <option value="10">10 rows</option>
                                <option value="25" selected>25 rows</option>
                                <option value="50">50 rows</option>
                                <option value="100">100 rows</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 mt-5">
                        <button onclick="crbGenerate()" id="crb_btn_generate"
                            class="inline-flex items-center gap-2 px-5 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm hover:bg-amber-500/25 transition-all">
                            <i class="fas fa-chart-line"></i> Generate Report
                        </button>
                        <button onclick="crbExportCSV()" id="crb_btn_export"
                            class="inline-flex items-center gap-2 px-5 py-2 bg-slate-800 border border-slate-700 rounded-lg text-gray-400 text-sm hover:bg-slate-700 transition-all disabled:opacity-40">
                            <i class="fas fa-file-csv text-emerald-400"></i> Export CSV
                        </button>
                        <span id="crb_row_count" class="text-xs text-gray-500 ml-auto"></span>
                    </div>
                </div>

                <!-- Results -->
                <div id="crb_results" class="hidden bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-700 flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-white" id="crb_results_title">Results</h4>
                        <span class="text-xs text-gray-500" id="crb_results_meta"></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-900/60">
                                <tr id="crb_thead"></tr>
                            </thead>
                            <tbody id="crb_tbody" class="divide-y divide-slate-700/50"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty state -->
                <div id="crb_empty" class="hidden text-center py-10 text-gray-600">
                    <i class="fas fa-table text-3xl mb-3 block"></i>
                    <p class="text-sm">No data found for the selected criteria</p>
                </div>
            </div>

            <script>
            var _crbData = { headers: [], keys: [], rows: [] };

            function crbGenerate() {
                    const btn = document.getElementById('crb_btn_generate');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Loading...';
                    document.getElementById('crb_results').classList.add('hidden');
                    document.getElementById('crb_empty').classList.add('hidden');
                    document.getElementById('crb_row_count').textContent = '';

                    const params = new URLSearchParams({
                        report_type: document.getElementById('crb_report_type').value,
                        group_by:    document.getElementById('crb_group_by').value,
                        sort_by:     document.getElementById('crb_sort_by').value,
                        limit:       document.getElementById('crb_limit').value,
                        from:        currentFilters?.from  || '<?php echo $from; ?>',
                        to:          currentFilters?.to    || '<?php echo $to; ?>',
                        branch_id:   currentFilters?.branch_id || '<?php echo $branch_id; ?>',
                        currency:    '<?php echo htmlspecialchars($currency); ?>',
                    });

                    fetch('../ajax/custom_report_query.php?' + params.toString())
                        .then(r => r.json())
                        .then(data => {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-chart-line mr-2"></i> Generate Report';

                            if (!data.success) {
                                showToast(data.error || 'Query failed', 'error');
                                return;
                            }

                            _crbData = data;

                            if (!data.rows.length) {
                                document.getElementById('crb_empty').classList.remove('hidden');
                                document.getElementById('crb_row_count').textContent = '0 rows';
                                return;
                            }

                            // Headers
                            document.getElementById('crb_thead').innerHTML = data.headers
                                .map(h => `<th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">${h}</th>`)
                                .join('');

                            // Rows
                            document.getElementById('crb_tbody').innerHTML = data.rows.map((row, i) =>
                                `<tr class="${i % 2 === 0 ? '' : 'bg-slate-900/20'} hover:bg-amber-500/5 transition-colors">` +
                                data.keys.map(k => `<td class="px-4 py-2.5 text-gray-300 whitespace-nowrap">${row[k] ?? '—'}</td>`).join('') +
                                `</tr>`
                            ).join('');

                            document.getElementById('crb_results').classList.remove('hidden');
                            document.getElementById('crb_results_title').textContent =
                                document.getElementById('crb_report_type').options[document.getElementById('crb_report_type').selectedIndex].text + ' Report';
                            document.getElementById('crb_results_meta').textContent =
                                `${data.from} → ${data.to}`;
                            document.getElementById('crb_row_count').textContent = `${data.count} row${data.count !== 1 ? 's' : ''}`;

                            showToast('Report generated — ' + data.count + ' rows', 'success');
                        })
                        .catch(err => {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-chart-line mr-2"></i> Generate Report';
                            showToast('Request failed: ' + err.message, 'error');
                        });
            }

            function crbExportCSV() {
                    if (!_crbData.rows.length) { showToast('Generate a report first', 'error'); return; }
                    let csv = _crbData.headers.map(h => `"${h}"`).join(',') + '\n';
                    csv += _crbData.rows.map(row =>
                        _crbData.keys.map(k => `"${(row[k] ?? '').toString().replace(/"/g, '""')}"`).join(',')
                    ).join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url  = URL.createObjectURL(blob);
                    const a    = document.createElement('a');
                    a.href     = url;
                    a.download = `custom_report_${document.getElementById('crb_report_type').value}_${new Date().toISOString().slice(0,10)}.csv`;
                    a.click();
                    URL.revokeObjectURL(url);
                    showToast('CSV exported', 'success');
            }
            </script>
            <?php
            break;
    }
    
    $response['html'] = ob_get_clean();
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_end_clean(); // Discard any output buffer content (potential warnings)
    }
    $response['success'] = false;
    $response['error'] = $e->getMessage();
} catch (PDOException $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    $response['success'] = false;
    $response['error'] = 'Database error: ' . $e->getMessage();
}

// Ensure clean JSON output - discard any remaining output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>