<?php
/**
 * Profit & Loss Statement page for Jakababa POS - PURE TAILWIND CSS
 * 
 * Generates comprehensive profit and loss reports with filters and visualizations.
 */

$page_title = 'Profit & Loss Statement';
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

// Check for reports permission
if (!check_permission('reports.view') && !is_super_admin()) {
    enforce_permission('reports.view');
}

$pdo = get_db_connection();

// Get current user info from session (SaaS-aware)
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_name = get_current_user_name();
$user_role = $_SESSION['role'] ?? 'cashier';
$is_super_admin = is_super_admin();

// Get business type for this company
$business_type = get_current_business_type($tenant_id);
$currency_symbol = get_tenant_currency() ?? 'KES';

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$branch_filter = isset($_GET['branch']) && $_GET['branch'] !== '' ? (int) $_GET['branch'] : $branch_id;
$compare_with = $_GET['compare'] ?? 'none';
$include_tax = isset($_GET['include_tax']) ? (int) $_GET['include_tax'] : 1;

// User filter - regular users see only their own data
$user_filter = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$is_admin = in_array(strtolower($user_role), ['admin', 'owner', 'superadmin', 'super_admin'], true) || $is_super_admin;
$current_user_id = get_current_user_id();

// Get all users for filter dropdown (admin only)
$users = [];
if ($is_admin) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE tenant_id = ? AND status = 1 ORDER BY name");
        $stmt->execute([$tenant_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
    }
}

// Get branches for filter (company-specific)
$branches = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching branches: " . $e->getMessage());
}

// Build branch condition (must filter by tenant_id for SaaS multi-tenancy)
$branch_condition = "";
$params = [$date_from, $date_to];
if (!empty($branch_filter)) {
    $branch_condition = " AND branch_id = ?";
    $params[] = $branch_filter;
} else {
    $branch_condition = " AND branch_id = ?";
    $params[] = $branch_id;
}
// Always filter by tenant_id
$branch_condition .= " AND tenant_id = ?";
$params[] = $tenant_id;

// User filtering - non-admins only see their own data
$user_condition = "";
if (!$is_admin) {
    $user_condition = " AND user_id = ?";
    $params[] = $current_user_id;
} elseif ($user_filter > 0) {
    $user_condition = " AND user_id = ?";
    $params[] = $user_filter;
}

// Get Income (Sales) Data
$income = [];
$total_income = 0;
$total_income_tax = 0;
try {
    // Sales by payment method
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(total), 0) as amount,
            COALESCE(SUM(tax), 0) as tax_amount
        FROM sales 
        WHERE DATE(created_at) BETWEEN ? AND ? 
            AND status = 'completed' AND voided = 0
            $branch_condition
            $user_condition
        GROUP BY payment_method
        ORDER BY amount DESC
    ");
    $stmt->execute($params);
    $income['by_payment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($income['by_payment'] as $row) {
        $total_income += (float)$row['amount'];
        $total_income_tax += (float)$row['tax_amount'];
    }

    // Daily sales for chart
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COALESCE(SUM(total), 0) as amount,
            COALESCE(SUM(tax), 0) as tax,
            COUNT(*) as transactions
        FROM sales 
        WHERE DATE(created_at) BETWEEN ? AND ? 
            AND status = 'completed' AND voided = 0
            $branch_condition
            $user_condition
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute($params);
    $income['daily'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Product sales (top products)
    $stmt = $pdo->prepare("
        SELECT 
            p.name as product_name,
            p.sku,
            SUM(si.quantity) as quantity_sold,
            COALESCE(SUM(si.price * si.quantity), 0) as revenue,
            COALESCE(SUM(p.cost_price * si.quantity), 0) as cost
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.created_at) BETWEEN ? AND ? 
            AND s.status = 'completed' AND s.voided = 0
            $branch_condition
            $user_condition
        GROUP BY p.id, p.name, p.sku
        ORDER BY revenue DESC
        LIMIT 20
    ");
    $stmt->execute($params);
    $income['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching income data: " . $e->getMessage());
}

// Get Expense Data
$expenses = [];
$total_expenses = 0;
$total_expense_tax = 0;
try {
    // Expenses by category
    $stmt = $pdo->prepare("
        SELECT 
            ec.name as category_name,
            ec.id as category_id,
            COUNT(*) as count,
            COALESCE(SUM(e.amount), 0) as amount,
            COALESCE(SUM(e.tax_amount), 0) as tax_amount
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE DATE(e.expense_date) BETWEEN ? AND ? 
            AND e.status = 'approved'
            $branch_condition
        GROUP BY ec.id, ec.name
        ORDER BY amount DESC
    ");
    $stmt->execute($params);
    $expenses['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expenses['by_category'] as $row) {
        $total_expenses += (float)$row['amount'];
        $total_expense_tax += (float)$row['tax_amount'];
    }

    // Daily expenses for chart
    $stmt = $pdo->prepare("
        SELECT 
            DATE(expense_date) as date,
            COALESCE(SUM(amount), 0) as amount,
            COALESCE(SUM(tax_amount), 0) as tax,
            COUNT(*) as transactions
        FROM expenses 
        WHERE DATE(expense_date) BETWEEN ? AND ? 
            AND status = 'approved'
            $branch_condition
        GROUP BY DATE(expense_date)
        ORDER BY date
    ");
    $stmt->execute($params);
    $expenses['daily'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Expenses by payment method
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as amount
        FROM expenses 
        WHERE DATE(expense_date) BETWEEN ? AND ? 
            AND status = 'approved'
            $branch_condition
        GROUP BY payment_method
        ORDER BY amount DESC
    ");
    $stmt->execute($params);
    $expenses['by_payment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching expense data: " . $e->getMessage());
}

// Calculate Profit/Loss
$gross_profit = $total_income - $total_expenses;
$net_profit = $gross_profit;
$profit_margin = $total_income > 0 ? ($gross_profit / $total_income) * 100 : 0;

// Get comparison data if requested
$comparison = [];
if ($compare_with !== 'none') {
    try {
        $compare_params = [];

        if ($compare_with === 'previous_period') {
            // Calculate previous period of same length
            $period_days = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24);
            $prev_date_to = date('Y-m-d', strtotime($date_from . ' -1 day'));
            $prev_date_from = date('Y-m-d', strtotime($prev_date_to . " -$period_days days"));

            $compare_params = [$prev_date_from, $prev_date_to];
            if (!empty($branch_filter)) {
                $compare_params[] = $branch_filter;
                $compare_params[] = $tenant_id;
            } else {
                $compare_params[] = $branch_id;
                $compare_params[] = $tenant_id;
            }

            // Get previous period income
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(total), 0) as amount
                FROM sales 
                WHERE DATE(created_at) BETWEEN ? AND ? 
                    AND status = 'completed' AND voided = 0
                    $branch_condition
            ");
            $stmt->execute($compare_params);
            $comparison['prev_income'] = (float)$stmt->fetchColumn();

            // Get previous period expenses
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as amount
                FROM expenses 
                WHERE DATE(expense_date) BETWEEN ? AND ? 
                    AND status = 'approved'
                    $branch_condition
            ");
            $stmt->execute($compare_params);
            $comparison['prev_expenses'] = (float)$stmt->fetchColumn();

            $comparison['prev_profit'] = $comparison['prev_income'] - $comparison['prev_expenses'];
            $comparison['period_label'] = 'Previous Period';

        } elseif ($compare_with === 'previous_year') {
            // Compare with same period last year
            $prev_date_from = date('Y-m-d', strtotime($date_from . ' -1 year'));
            $prev_date_to = date('Y-m-d', strtotime($date_to . ' -1 year'));

            $compare_params = [$prev_date_from, $prev_date_to];
            if (!empty($branch_filter)) {
                $compare_params[] = $branch_filter;
                $compare_params[] = $tenant_id;
            } else {
                $compare_params[] = $branch_id;
                $compare_params[] = $tenant_id;
            }

            // Get previous year income
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(total), 0) as amount
                FROM sales 
                WHERE DATE(created_at) BETWEEN ? AND ? 
                    AND status = 'completed' AND voided = 0
                    $branch_condition
            ");
            $stmt->execute($compare_params);
            $comparison['prev_income'] = (float)$stmt->fetchColumn();

            // Get previous year expenses
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as amount
                FROM expenses 
                WHERE DATE(expense_date) BETWEEN ? AND ? 
                    AND status = 'approved'
                    $branch_condition
            ");
            $stmt->execute($compare_params);
            $comparison['prev_expenses'] = (float)$stmt->fetchColumn();

            $comparison['prev_profit'] = $comparison['prev_income'] - $comparison['prev_expenses'];
            $comparison['period_label'] = 'Last Year';
        }

        // Calculate changes
        $comparison['income_change'] = $comparison['prev_income'] > 0
            ? (($total_income - $comparison['prev_income']) / $comparison['prev_income']) * 100
            : 0;
        $comparison['expense_change'] = $comparison['prev_expenses'] > 0
            ? (($total_expenses - $comparison['prev_expenses']) / $comparison['prev_expenses']) * 100
            : 0;
        $comparison['profit_change'] = $comparison['prev_profit'] > 0
            ? (($gross_profit - $comparison['prev_profit']) / $comparison['prev_profit']) * 100
            : 0;

    } catch (PDOException $e) {
        error_log("Error fetching comparison data: " . $e->getMessage());
    }
}

// Calculate key metrics
$total_transactions = array_sum(array_column($income['by_payment'] ?? [], 'transaction_count'));
$avg_transaction_value = $total_income > 0 && $total_transactions > 0 ? $total_income / $total_transactions : 0;
$expense_ratio = $total_income > 0 ? ($total_expenses / $total_income) * 100 : 0;
$tax_ratio = $total_income > 0 ? (($total_income_tax + $total_expense_tax) / $total_income) * 100 : 0;
$daily_avg_income = $total_income > 0 ? $total_income / max(1, count($income['daily'] ?? [])) : 0;
$daily_avg_expense = $total_expenses > 0 ? $total_expenses / max(1, count($expenses['daily'] ?? [])) : 0;

$current_year = date('Y');
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="space-y-4">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-400 uppercase tracking-wider mb-1">
                <i class="fas fa-chart-pie text-xs"></i>
                <span>Financial Analytics</span>
            </div>
            <h1 class="text-lg font-bold text-white">Profit & Loss Statement</h1>
            <p class="text-sm text-slate-500 mt-1">Financial performance summary with period comparison</p>
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

    <!-- Filter Panel -->
    <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">From Date</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                       class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 w-36">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">To Date</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                       class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 w-36">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Branch</label>
                <select name="branch" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 w-40">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo (int)$branch['id']; ?>" <?php echo $branch_filter == $branch['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Compare</label>
                <select name="compare" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 w-40">
                    <option value="none" <?php echo $compare_with === 'none' ? 'selected' : ''; ?>>No Comparison</option>
                    <option value="previous_period" <?php echo $compare_with === 'previous_period' ? 'selected' : ''; ?>>Previous Period</option>
                    <option value="previous_year" <?php echo $compare_with === 'previous_year' ? 'selected' : ''; ?>>Previous Year</option>
                </select>
            </div>
            <div>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm hover:bg-amber-500/25 transition-all">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </div>
            <div class="flex-1 text-right text-sm text-slate-500">
                <i class="far fa-calendar-alt mr-1 text-amber-400"></i>
                <?php echo date('d M Y', strtotime($date_from)); ?> — <?php echo date('d M Y', strtotime($date_to)); ?>
            </div>
        </form>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Total Income</p>
            <p class="text-xl font-bold text-emerald-400"><?php echo $currency_symbol; ?> <?php echo number_format($total_income, 2); ?></p>
            <?php if (isset($comparison['income_change'])): ?>
                <p class="text-xs mt-1 <?php echo $comparison['income_change'] >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                    <i class="fas fa-arrow-<?php echo $comparison['income_change'] >= 0 ? 'up' : 'down'; ?> mr-1"></i>
                    <?php echo number_format(abs($comparison['income_change']), 1); ?>% vs <?php echo htmlspecialchars($comparison['period_label']); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Total Expenses</p>
            <p class="text-xl font-bold text-red-400"><?php echo $currency_symbol; ?> <?php echo number_format($total_expenses, 2); ?></p>
            <?php if (isset($comparison['expense_change'])): ?>
                <p class="text-xs mt-1 <?php echo $comparison['expense_change'] <= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                    <i class="fas fa-arrow-<?php echo $comparison['expense_change'] <= 0 ? 'down' : 'up'; ?> mr-1"></i>
                    <?php echo number_format(abs($comparison['expense_change']), 1); ?>% vs <?php echo htmlspecialchars($comparison['period_label']); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Gross Profit</p>
            <p class="text-xl font-bold <?php echo $gross_profit >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                <?php echo $currency_symbol; ?> <?php echo number_format($gross_profit, 2); ?>
            </p>
            <?php if (isset($comparison['profit_change'])): ?>
                <p class="text-xs mt-1 <?php echo $comparison['profit_change'] >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                    <i class="fas fa-arrow-<?php echo $comparison['profit_change'] >= 0 ? 'up' : 'down'; ?> mr-1"></i>
                    <?php echo number_format(abs($comparison['profit_change']), 1); ?>% vs <?php echo htmlspecialchars($comparison['period_label']); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Profit Margin</p>
            <p class="text-xl font-bold <?php echo $profit_margin >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                <?php echo number_format($profit_margin, 1); ?>%
            </p>
            <p class="text-xs text-slate-500 mt-1">Expense Ratio: <?php echo number_format($expense_ratio, 1); ?>%</p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Income vs Expense Chart -->
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <h3 class="text-sm font-semibold text-white mb-4">Daily Income vs Expenses</h3>
            <div class="h-72">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

        <!-- Income Breakdown Chart -->
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <h3 class="text-sm font-semibold text-white mb-4">Income by Payment Method</h3>
            <div class="h-72">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Income Statement Table -->
    <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
        <div class="px-5 py-3 border-b border-slate-700">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-arrow-up text-emerald-400"></i>
                Income Statement
            </h3>
        </div>
        
        <div class="p-5">
            <!-- Income by Payment Method -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-amber-400 mb-3 flex items-center gap-2">
                    <i class="fas fa-chart-pie text-xs"></i> Income by Payment Method
                </h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-900/50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Payment Method</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Transactions</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Amount</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Tax</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">% of Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            <?php foreach ($income['by_payment'] ?? [] as $row):
                                $method = $row['payment_method'] ?? 'cash';
                                $methodIcons = [
                                    'cash' => 'fa-money-bill-wave text-emerald-400',
                                    'card' => 'fa-credit-card text-blue-400',
                                    'mpesa' => 'fa-mobile-alt text-amber-400',
                                    'bank_transfer' => 'fa-university text-purple-400',
                                    'split' => 'fa-code-branch text-pink-400'
                                ];
                                $icon = $methodIcons[$method] ?? 'fa-circle text-slate-400';
                            ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-4 py-2">
                                        <i class="fas <?php echo $icon; ?> mr-2"></i>
                                        <span class="text-white capitalize"><?php echo htmlspecialchars($method); ?></span>
                                    </td>
                                    <td class="px-4 py-2 text-right text-slate-300"><?php echo number_format($row['transaction_count']); ?></td>
                                    <td class="px-4 py-2 text-right text-emerald-400 font-semibold"><?php echo $currency_symbol; ?> <?php echo number_format($row['amount'], 2); ?></td>
                                    <td class="px-4 py-2 text-right text-slate-400"><?php echo $currency_symbol; ?> <?php echo number_format($row['tax_amount'], 2); ?></td>
                                    <td class="px-4 py-2 text-right text-amber-400"><?php echo $total_income > 0 ? number_format(($row['amount'] / $total_income) * 100, 1) : 0; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bg-slate-900/50 font-bold">
                                <td class="px-4 py-2 text-white">Total Income</td>
                                <td class="px-4 py-2 text-right text-white"><?php echo number_format($total_transactions); ?></td>
                                <td class="px-4 py-2 text-right text-emerald-400"><?php echo $currency_symbol; ?> <?php echo number_format($total_income, 2); ?></td>
                                <td class="px-4 py-2 text-right text-slate-400"><?php echo $currency_symbol; ?> <?php echo number_format($total_income_tax, 2); ?></td>
                                <td class="px-4 py-2 text-right text-amber-400">100%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Expenses by Category -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-amber-400 mb-3 flex items-center gap-2">
                    <i class="fas fa-chart-line text-xs"></i> Expenses by Category
                </h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-900/50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Category</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Count</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Amount</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Tax</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">% of Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            <?php foreach ($expenses['by_category'] ?? [] as $row): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-4 py-2">
                                        <span class="inline-block w-3 h-3 rounded-full mr-2 bg-red-500"></span>
                                        <span class="text-white"><?php echo htmlspecialchars($row['category_name']); ?></span>
                                    </td>
                                    <td class="px-4 py-2 text-right text-slate-300"><?php echo (int)$row['count']; ?></td>
                                    <td class="px-4 py-2 text-right text-red-400 font-semibold"><?php echo $currency_symbol; ?> <?php echo number_format($row['amount'], 2); ?></td>
                                    <td class="px-4 py-2 text-right text-slate-400"><?php echo $currency_symbol; ?> <?php echo number_format($row['tax_amount'], 2); ?></td>
                                    <td class="px-4 py-2 text-right text-amber-400"><?php echo $total_expenses > 0 ? number_format(($row['amount'] / $total_expenses) * 100, 1) : 0; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bg-slate-900/50 font-bold">
                                <td class="px-4 py-2 text-white">Total Expenses</td>
                                <td class="px-4 py-2 text-right text-white"><?php echo array_sum(array_column($expenses['by_category'] ?? [], 'count')); ?></td>
                                <td class="px-4 py-2 text-right text-red-400"><?php echo $currency_symbol; ?> <?php echo number_format($total_expenses, 2); ?></td>
                                <td class="px-4 py-2 text-right text-slate-400"><?php echo $currency_symbol; ?> <?php echo number_format($total_expense_tax, 2); ?></td>
                                <td class="px-4 py-2 text-right text-amber-400">100%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary Row -->
            <div class="bg-slate-900/50 rounded-lg p-4">
                <div class="flex justify-between items-center py-2">
                    <span class="text-white font-semibold">Gross Profit</span>
                    <span class="text-xl font-bold <?php echo $gross_profit >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                        <?php echo $currency_symbol; ?> <?php echo number_format($gross_profit, 2); ?>
                    </span>
                </div>
                <div class="flex justify-between items-center py-2 border-t border-slate-700">
                    <span class="text-white font-semibold">Profit Margin</span>
                    <span class="text-xl font-bold <?php echo $profit_margin >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                        <?php echo number_format($profit_margin, 1); ?>%
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products Section -->
    <?php if (!empty($income['top_products'])): ?>
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl overflow-hidden transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <div class="px-5 py-3 border-b border-slate-700">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-crown text-amber-400"></i>
                    Top Selling Products
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Product</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase">SKU</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Quantity</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Revenue</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Cost</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Profit</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach ($income['top_products'] as $product):
                            $product_profit = (float)$product['revenue'] - (float)$product['cost'];
                            $product_margin = (float)$product['revenue'] > 0 ? ($product_profit / (float)$product['revenue']) * 100 : 0;
                        ?>
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="px-4 py-2 text-white"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td class="px-4 py-2 text-slate-400 text-xs font-mono"><?php echo htmlspecialchars($product['sku']); ?></td>
                                <td class="px-4 py-2 text-right text-slate-300"><?php echo number_format($product['quantity_sold']); ?></td>
                                <td class="px-4 py-2 text-right text-emerald-400"><?php echo $currency_symbol; ?> <?php echo number_format($product['revenue'], 2); ?></td>
                                <td class="px-4 py-2 text-right text-red-400"><?php echo $currency_symbol; ?> <?php echo number_format($product['cost'], 2); ?></td>
                                <td class="px-4 py-2 text-right text-amber-400"><?php echo $currency_symbol; ?> <?php echo number_format($product_profit, 2); ?></td>
                                <td class="px-4 py-2 text-right text-amber-400"><?php echo number_format($product_margin, 1); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Average Transaction</p>
            <p class="text-lg font-bold text-white"><?php echo $currency_symbol; ?> <?php echo number_format($avg_transaction_value, 2); ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Daily Avg Income</p>
            <p class="text-lg font-bold text-emerald-400"><?php echo $currency_symbol; ?> <?php echo number_format($daily_avg_income, 2); ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Daily Avg Expenses</p>
            <p class="text-lg font-bold text-red-400"><?php echo $currency_symbol; ?> <?php echo number_format($daily_avg_expense, 2); ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-4 transition-all hover:border-amber-500/30 hover:bg-slate-800/60">
            <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Tax Ratio</p>
            <p class="text-lg font-bold text-white"><?php echo number_format($tax_ratio, 1); ?>%</p>
        </div>
    </div>

    <!-- Report Footer -->
    <div class="text-center text-xs text-slate-500 pt-6 border-t border-slate-700">
        <p><i class="fas fa-chart-pie mr-1 text-amber-400"></i> Generated on <?php echo date('d M Y H:i:s'); ?> by <?php echo htmlspecialchars($user_name); ?></p>
        <p class="mt-1">This report is computer generated and does not require a signature.</p>
    </div>
</div>

<script>
// Daily Income vs Expenses Chart
const dailyCtx = document.getElementById('dailyChart')?.getContext('2d');
if (dailyCtx) {
    const dailyData = <?php
    $daily_labels = [];
    $daily_income = [];
    $daily_expenses = [];

    $all_dates = [];
    foreach ($income['daily'] ?? [] as $row) {
        $all_dates[$row['date']] = true;
    }
    foreach ($expenses['daily'] ?? [] as $row) {
        $all_dates[$row['date']] = true;
    }
    ksort($all_dates);

    foreach (array_keys($all_dates) as $date) {
        $daily_labels[] = date('d M', strtotime($date));
        $found_income = 0;
        foreach ($income['daily'] ?? [] as $row) {
            if ($row['date'] == $date) {
                $found_income = (float)$row['amount'];
                break;
            }
        }
        $daily_income[] = $found_income;
        $found_expense = 0;
        foreach ($expenses['daily'] ?? [] as $row) {
            if ($row['date'] == $date) {
                $found_expense = (float)$row['amount'];
                break;
            }
        }
        $daily_expenses[] = $found_expense;
    }
    echo json_encode(['labels' => $daily_labels, 'income' => $daily_income, 'expenses' => $daily_expenses]);
    ?>;

    new Chart(dailyCtx, {
        type: 'bar',
        data: {
            labels: dailyData.labels,
            datasets: [
                { label: 'Income', data: dailyData.income, backgroundColor: '#10b981', borderRadius: 4 },
                { label: 'Expenses', data: dailyData.expenses, backgroundColor: '#ef4444', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#94a3b8' } } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8', callback: (v) => '<?php echo $currency_symbol; ?> ' + v.toLocaleString() } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });
}

// Payment Method Chart
const paymentCtx = document.getElementById('paymentChart')?.getContext('2d');
if (paymentCtx) {
    const paymentData = <?php
    $payment_labels = [];
    $payment_values = [];
    $payment_colors = [];
    foreach ($income['by_payment'] ?? [] as $row) {
        $method = $row['payment_method'] ?? 'cash';
        $payment_labels[] = ucfirst($method);
        $payment_values[] = (float)$row['amount'];
        switch ($method) {
            case 'cash': $payment_colors[] = '#10b981'; break;
            case 'card': $payment_colors[] = '#3b82f6'; break;
            case 'mpesa': $payment_colors[] = '#f59e0b'; break;
            case 'bank_transfer': $payment_colors[] = '#8b5cf6'; break;
            default: $payment_colors[] = '#fbbf24';
        }
    }
    echo json_encode(['labels' => $payment_labels, 'values' => $payment_values, 'colors' => $payment_colors]);
    ?>;

    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: paymentData.labels,
            datasets: [{ data: paymentData.values, backgroundColor: paymentData.colors, borderWidth: 0 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#94a3b8' } },
                tooltip: { callbacks: { label: (ctx) => `${ctx.label}: <?php echo $currency_symbol; ?> ${ctx.raw.toLocaleString()} (${((ctx.raw / ctx.dataset.data.reduce((a,b)=>a+b,0))*100).toFixed(1)}%)` } }
            }
        }
    });
}

function exportToPDF() {
    const element = document.querySelector('.space-y-4');
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: 'profit_loss_<?php echo date('Y-m-d'); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}

function exportToExcel() {
    let csv = "Profit & Loss Statement\n";
    csv += "Period,<?php echo $date_from; ?> to <?php echo $date_to; ?>\n\n";
    csv += "Income Statement\n";
    csv += "Payment Method,Transactions,Amount (<?php echo $currency_symbol; ?>),Tax (<?php echo $currency_symbol; ?>),% of Total\n";
    <?php foreach ($income['by_payment'] ?? [] as $row): ?>
        csv += "<?php echo addslashes($row['payment_method']); ?>,<?php echo (int)$row['transaction_count']; ?>,<?php echo (float)$row['amount']; ?>,<?php echo (float)$row['tax_amount']; ?>,<?php echo $total_income > 0 ? (($row['amount'] / $total_income) * 100) : 0; ?>\n";
    <?php endforeach; ?>
    csv += "Total Income,<?php echo (int)$total_transactions; ?>,<?php echo (float)$total_income; ?>,<?php echo (float)$total_income_tax; ?>,100\n\n";
    csv += "Expenses by Category\n";
    csv += "Category,Count,Amount (<?php echo $currency_symbol; ?>),Tax (<?php echo $currency_symbol; ?>),% of Total\n";
    <?php foreach ($expenses['by_category'] ?? [] as $row): ?>
        csv += "<?php echo addslashes($row['category_name']); ?>,<?php echo (int)$row['count']; ?>,<?php echo (float)$row['amount']; ?>,<?php echo (float)$row['tax_amount']; ?>,<?php echo $total_expenses > 0 ? (($row['amount'] / $total_expenses) * 100) : 0; ?>\n";
    <?php endforeach; ?>
    csv += "Total Expenses,<?php echo (int)array_sum(array_column($expenses['by_category'] ?? [], 'count')); ?>,<?php echo (float)$total_expenses; ?>,<?php echo (float)$total_expense_tax; ?>,100\n\n";
    csv += "Summary\n";
    csv += "Gross Profit,<?php echo (float)$gross_profit; ?>\n";
    csv += "Profit Margin,<?php echo number_format($profit_margin, 1); ?>%\n";
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'profit_loss_<?php echo date('Y-m-d'); ?>.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>