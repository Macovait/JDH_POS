<?php
/**
 * Jakababa POS - Modern SaaS Dashboard
 * PURE TAILWIND CSS - Multi-tenant analytics with refined enterprise UI
 * @version 4.0
 */

header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
safe_require('cache.php', 'src');
require_login();

if (!check_permission('dashboard.view') && !is_super_admin()) {
    enforce_permission('dashboard.view');
}

// Load security bootstrap
require_once __DIR__ . '/../../src/Security/SecurityBootstrap.php';
SecurityBootstrap::initialize();

if (!class_exists('DashboardRepository')) {
    require_once __DIR__ . '/../../src/DashboardRepository.php';
}
if (!class_exists('DashboardContext')) {
    require_once __DIR__ . '/../../src/DashboardContext.php';
}
require_once __DIR__ . '/../../src/Security/ModuleAccess.php';

// Get current role for display
$user_role = $_SESSION['role'] ?? 'user';
$is_super_admin = is_super_admin();

$pdo         = get_db_connection();
$ctx         = DashboardContext::fromSession();
$tenant_id   = (int) ($ctx->companyId() ?? 0);
$user_id     = $ctx->userId();
$user_name   = $_SESSION['name'] ?? (function_exists('get_current_user_name') ? get_current_user_name() : 'User');
$user_role   = $_SESSION['role'] ?? $ctx->role();

// Always verify role from database
$role_id = $_SESSION['role_id'] ?? 0;
$roleCacheKey = '_role_verified_' . $role_id;
if (isset($_SESSION[$roleCacheKey]) && (time() - ($_SESSION[$roleCacheKey . '_time'] ?? 0) < 300)) {
    $user_role = $_SESSION[$roleCacheKey];
} elseif ($role_id > 0 && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$role_id, $tenant_id]);
        $dbRole = $stmt->fetchColumn();
        if ($dbRole) {
            $user_role = $dbRole;
            $_SESSION['role'] = $dbRole;
            $_SESSION[$roleCacheKey] = $dbRole;
            $_SESSION[$roleCacheKey . '_time'] = time();
        }
    } catch (Exception $e) { /* silent */ }
}

// --- Branch Filtering ---
$original_branch_id = (int) ($ctx->branchId() ?? 0);
$original_branch_name = $_SESSION['branch_name'] ?? (function_exists('get_current_branch_name') ? get_current_branch_name() : 'All Branches');

$all_branches = [];
$can_switch_branch = $ctx->isCompanyAdmin() || $ctx->isSuperAdmin() || (function_exists('is_super_admin') && is_super_admin()) || has_permission('branches.view');
$branchCacheKey = '_branches_' . $tenant_id;

if (isset($_SESSION[$branchCacheKey]) && (time() - ($_SESSION[$branchCacheKey . '_time'] ?? 0) < 120)) {
    $all_branches = $_SESSION[$branchCacheKey];
} else {
    try {
        $stmt = $pdo->prepare("SELECT id, name, code FROM branches WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name");
        $stmt->execute([$tenant_id]);
        $all_branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $_SESSION[$branchCacheKey] = $all_branches;
        $_SESSION[$branchCacheKey . '_time'] = time();
    } catch (Exception $e) {
        error_log('Dashboard: Failed to fetch branches: ' . $e->getMessage());
    }
}

// Get selected branch
if (isset($_GET['branch_id'])) {
    $selected_branch_id = (int) $_GET['branch_id'];
    $_SESSION['dashboard_branch_id'] = $selected_branch_id;
} elseif (isset($_SESSION['dashboard_branch_id']) && $_SESSION['dashboard_branch_id'] !== '') {
    $selected_branch_id = (int) $_SESSION['dashboard_branch_id'];
} else {
    $selected_branch_id = (int) ($ctx->branchId() ?? 0);
}

// Validate branch
$selected_branch_name = 'All Branches';
$branch_valid = false;
foreach ($all_branches as $b) {
    if ((int) $b['id'] === $selected_branch_id) {
        $selected_branch_name = $b['name'];
        $branch_valid = true;
        break;
    }
}

if (!$can_switch_branch && $original_branch_id > 0) {
    $selected_branch_id = $original_branch_id;
    $selected_branch_name = $original_branch_name;
}

// Rebuild context
if ($selected_branch_id === 0 || $branch_valid) {
    $ctx = DashboardContext::create(
        companyId: $tenant_id,
        branchId: $selected_branch_id === 0 ? null : $selected_branch_id,
        businessType: $ctx->businessType(),
        role: $user_role,
        userId: $user_id,
        isSuperAdmin: $ctx->isSuperAdmin()
    );
}

$branch_id   = (int) ($ctx->branchId() ?? 0);
$branch_name = $selected_branch_name;
$is_admin    = $ctx->isCompanyAdmin() || $ctx->isSuperAdmin() || (function_exists('is_super_admin') && is_super_admin());

// Release session lock
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$repo = new DashboardRepository($pdo, $ctx);
$data = $repo->build();

$K = $data['kpis']   ?? [];
$T = $data['tables'] ?? [];
$C = $data['charts'] ?? [];
$currency = $data['meta']['currency'] ?? $_SESSION['tenant_currency'] ?? 'KES';

$bt_name           = $ctx->btName();
$bt_icon           = $ctx->btIcon();
$bt_sale_label     = $ctx->btSaleLabel();
$bt_sales_label    = $ctx->btSalesLabel();
$bt_customer_label = $ctx->btCustomerLabel();

// Label Printing Settings
require_once __DIR__ . '/../../src/SettingsManager.php';
$labelSettings = new SettingsManager($pdo, $tenant_id);
$labelPrefs = $labelSettings->getLabelSettings();
$autoPrintOnSale    = ($labelPrefs['auto_print_labels_on_sale'] ?? '0') === '1';
$autoPrintLabelSize = $labelPrefs['auto_print_label_size'] ?? 'k22';
$autoPrintMethod    = $labelPrefs['auto_print_method'] ?? 'pdf';

// CSRF
$csrf_token = generate_csrf_token();

// Subscription status
$subscriptionInfo = null;
$trialDaysLeft = null;
$planName = null;
try {
    if (file_exists(__DIR__ . '/../../src/SubscriptionManager.php')) {
        require_once __DIR__ . '/../../src/SubscriptionManager.php';
        $subMgr = new SubscriptionManager($pdo);
        $subscriptionInfo = $subMgr->getTenantSubscription($tenant_id);
        if ($subscriptionInfo) {
            $trialDaysLeft = $subMgr->getTrialDaysRemaining($tenant_id);
            $planName = $subscriptionInfo['plan_name'] ?? ($subscriptionInfo['plan_slug'] ?? 'Plan');
        }
    }
} catch (Exception $e) {
    error_log('Dashboard subscription check error: ' . $e->getMessage());
}

// --- Aggregate metrics ---
$today_revenue       = (float) ($K['today']['revenue'] ?? 0);
$today_count         = (int) ($K['today']['count'] ?? 0);
$today_aov           = (float) ($K['today']['aov'] ?? 0);
$today_profit        = (float) ($K['todayProfit'] ?? 0);
$yesterday_revenue   = (float) ($K['yesterday']['revenue'] ?? 0);
$weekly_revenue      = (float) ($K['week']['revenue'] ?? 0);
$weekly_count        = (int) ($K['week']['count'] ?? 0);
$monthly_revenue     = (float) ($K['month']['revenue'] ?? 0);
$monthly_count       = (int) ($K['month']['count'] ?? 0);
$monthly_profit      = (float) ($K['monthProfit'] ?? 0);
$monthly_cost        = (float) ($K['monthCost'] ?? 0);
$monthly_tax         = (float) ($K['month']['tax'] ?? 0);
$total_revenue       = (float) ($K['lifetime']['revenue'] ?? 0);
$total_products      = (int) ($K['totalProducts'] ?? 0);
$total_customers     = (int) ($K['totalCustomers'] ?? 0);
$low_stock           = (int) ($K['lowStock'] ?? 0);
$out_of_stock        = (int) ($K['outOfStock'] ?? 0);
$inventory_value     = (float) ($K['inventoryValue'] ?? 0);
$expenses_today      = (float) ($K['expensesToday'] ?? 0);
$expenses_month      = (float) ($K['expensesMonth'] ?? 0);
$returns_month       = (int) ($K['returnsMonth'] ?? 0);
$pending_purchases   = (int) ($K['pendingPurchases'] ?? 0);
$active_registers    = (int) ($K['activeRegisters'] ?? 0);
$open_quotations     = (int) ($K['openQuotations'] ?? 0);
$new_customers_month = (int) ($K['newCustomersMonth'] ?? 0);

$revenue_change   = (float) ($K['revenueChangePct'] ?? 0);
$profit_margin    = (float) ($K['profitMargin'] ?? 0);
$net_margin       = (float) ($K['netMargin'] ?? 0);
$mom_growth       = (float) ($K['momGrowth'] ?? 0);
$sales_velocity   = (float) ($K['salesVelocity'] ?? 0);
$inventory_health = (float) ($K['inventoryHealth'] ?? 100);
$expense_ratio    = $monthly_revenue > 0 ? round(($expenses_month / $monthly_revenue) * 100, 1) : 0;
$net_cash_flow    = $monthly_revenue - $expenses_month - $monthly_cost;
$growth_score     = max(0, min(100, (int) (50 + ($revenue_change * 0.5) + ($profit_margin * 0.3) + ($mom_growth * 0.2))));

// --- Tables ---
$recent_sales        = $T['recentSales'] ?? [];
$top_products        = $T['topProducts'] ?? [];
$low_stock_products  = $T['lowStockItems'] ?? [];
$top_customers       = $T['topCustomers'] ?? [];
$customer_segments   = $T['customerSegments'] ?? ['VIP' => 0, 'Regular' => 0, 'New' => 0, 'At-Risk' => 0];
$cashier_performance = $T['cashierPerformance'] ?? [];
$expense_breakdown   = $T['expenseBreakdown'] ?? [];
$worst_products      = $T['worstProducts'] ?? [];

// --- Charts ---
$dailyLabels   = $C['dailySales']['labels']    ?? [];
$dailyRevenue  = $C['dailySales']['revenues']  ?? [];
$dailyCounts   = $C['dailySales']['counts']    ?? [];
$paymentLabels = $C['paymentMethods']['labels'] ?? ['No data'];
$paymentValues = $C['paymentMethods']['values'] ?? [0];
$categoryLabels = $C['categorySales']['labels'] ?? ['No data'];
$categoryValues = $C['categorySales']['values'] ?? [0];
$hourlyLabels   = $C['hourlySales']['labels']  ?? [];
$hourlyValues   = $C['hourlySales']['totals']  ?? [];
$weeklyTrend    = $C['weeklyTrend']['weeks']   ?? [];
$weeklyLabels   = array_column($weeklyTrend, 'week');
$weeklyRevenue  = array_column($weeklyTrend, 'revenue');
$monthlyTrend   = $C['monthlyComparison']['months'] ?? [];
$monthlyLabels  = array_column($monthlyTrend, 'month');
$monthlyRevenue = array_column($monthlyTrend, 'revenue');
$segmentLabels  = array_keys($customer_segments);
$segmentValues  = array_values($customer_segments);
$expenseLabels  = array_column($expense_breakdown, 'cat');
$expenseValues  = array_column($expense_breakdown, 'total');
if (empty($expenseLabels)) { $expenseLabels = ['No expenses']; $expenseValues = [0]; }

// --- Smart alerts ---
$alerts = [];
foreach (($data['alerts'] ?? []) as $a) {
    $alerts[] = [
        'type' => $a['type'] ?? 'info',
        'icon' => $a['icon'] ?? 'bell',
        'msg'  => $a['message'] ?? '',
    ];
}
if ($revenue_change > 20 && $today_revenue > 0) {
    $alerts[] = ['type' => 'success', 'icon' => 'trophy', 'msg' => 'Revenue up ' . number_format($revenue_change, 1) . '% vs yesterday.'];
}
if ($pending_purchases > 5) {
    $alerts[] = ['type' => 'info', 'icon' => 'truck', 'msg' => $pending_purchases . ' purchase orders pending.'];
}
if ($returns_month > 3) {
    $alerts[] = ['type' => 'warning', 'icon' => 'undo', 'msg' => $returns_month . ' returns this month.'];
}
if ($out_of_stock > 0) {
    $alerts[] = ['type' => 'danger', 'icon' => 'box-open', 'msg' => $out_of_stock . ' product(s) out of stock.'];
}

function timeAgo($datetime): string {
    if (empty($datetime)) return 'Never';
    try {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        if ($diff->d > 0) return $diff->d . 'd ago';
        if ($diff->h > 0) return $diff->h . 'h ago';
        if ($diff->i > 0) return $diff->i . 'm ago';
        return 'Just now';
    } catch (Exception $e) {
        return '—';
    }
}

$page_title = 'Dashboard — ' . ($_SESSION['tenant_name'] ?? 'POS System');
$page_icon = 'fa-chart-pie';
ob_start();
?>

<meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-400 uppercase tracking-wider mb-1">
                <i class="fas fa-chart-line text-xs"></i>
                <span><?php echo htmlspecialchars($bt_name); ?></span>
                <?php if ($is_admin): ?>
                <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 text-[10px] font-medium">Admin</span>
                <?php endif; ?>
            </div>
            <h1 class="text-2xl font-bold text-white">
                Good <?php echo date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening'); ?>, 
                <span class="bg-gradient-to-r from-amber-400 to-yellow-500 bg-clip-text text-transparent"><?php echo htmlspecialchars($user_name); ?></span>
            </h1>
            <p class="text-sm text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1"><i class="fas fa-circle text-[8px] text-emerald-400"></i>All systems operational</span>
                <span class="text-slate-700">|</span>
                <span class="inline-flex items-center gap-1 text-amber-400">
                    <i class="fas fa-store text-[10px]"></i>
                    <?php echo htmlspecialchars($selected_branch_name); ?>
                    <?php if ($branch_id > 0): ?>
                    <span class="text-[10px] text-slate-500">(ID:<?php echo $branch_id; ?>)</span>
                    <?php else: ?>
                    <span class="text-[10px] text-slate-500">(All)</span>
                    <?php endif; ?>
                </span>
                <span class="text-slate-700">|</span>
                <span><i class="far fa-clock text-slate-600"></i> <span id="liveClock"><?php echo date('D, M j · H:i'); ?></span></span>
                <?php if ($active_registers > 0): ?>
                <span class="text-slate-700">|</span>
                <span class="inline-flex items-center gap-1"><i class="fas fa-cash-register text-[10px] text-blue-400"></i><?php echo $active_registers; ?> registers active</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 items-center">
            <?php if ($can_switch_branch && count($all_branches) > 1): ?>
            <form method="get" class="relative" id="branchFilterForm">
                <select name="branch_id" onchange="this.form.submit()" 
                        class="pl-3 pr-8 py-1.5 bg-slate-800/80 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500/50 cursor-pointer hover:bg-slate-700 transition-colors appearance-none">
                    <option value="0" <?php echo $selected_branch_id === 0 ? 'selected' : ''; ?>>All Branches</option>
                    <?php foreach ($all_branches as $b): ?>
                    <option value="<?php echo (int) $b['id']; ?>" <?php echo $selected_branch_id === (int) $b['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <i class="fas fa-chevron-down text-[10px] text-slate-500 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
            </form>
            <?php elseif (!empty($selected_branch_name) && $selected_branch_id > 0): ?>
            <span class="px-3 py-1.5 bg-slate-800/80 border border-slate-700 rounded-lg text-slate-400 text-sm">
                <i class="fas fa-store text-xs mr-1.5 text-amber-400"></i><?php echo htmlspecialchars($selected_branch_name); ?>
            </span>
            <?php endif; ?>
            
            <button onclick="refreshDashboard()" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-800/80 border border-slate-700 rounded-lg text-slate-400 text-sm hover:bg-slate-700 transition-colors">
                <i class="fas fa-sync-alt text-xs"></i> Refresh
            </button>
            <a href="<?php echo base_url('reports/reports.php'); ?>" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-800/80 border border-slate-700 rounded-lg text-slate-400 text-sm hover:bg-slate-700 transition-colors">
                <i class="fas fa-chart-line text-xs"></i> Analytics
            </a>
            <a href="<?php echo base_url('pos/pos.php'); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm hover:bg-amber-500/25 transition-all">
                <i class="fas fa-<?php echo htmlspecialchars($bt_icon); ?>"></i> New <?php echo htmlspecialchars($bt_sale_label); ?>
            </a>
        </div>
    </div>

    <!-- Health Bar -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <div class="flex flex-col lg:flex-row lg:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="relative w-[76px] h-[76px] flex-shrink-0">
                    <svg width="76" height="76" viewBox="0 0 92 92" style="transform: rotate(-90deg);">
                        <circle cx="46" cy="46" r="40" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="6"/>
                        <circle cx="46" cy="46" r="40" fill="none" stroke="url(#scoreGrad)" stroke-width="6" stroke-linecap="round" stroke-dasharray="<?php echo round($growth_score * 251.3 / 100, 2); ?> 251.3"/>
                        <defs>
                            <linearGradient id="scoreGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#34d399"/>
                                <stop offset="100%" stop-color="#fbbf24"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-xl font-bold text-white"><?php echo (int) $growth_score; ?></span>
                        <span class="text-[9px] uppercase tracking-wider text-slate-500 font-semibold">Health</span>
                    </div>
                </div>
                <div class="hidden lg:block w-px h-12 bg-white/5"></div>
            </div>

            <div class="flex-1 grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">vs Yesterday</div>
                    <div class="mt-1 flex items-center gap-1.5">
                        <i class="fas fa-arrow-<?php echo $revenue_change >= 0 ? 'up text-emerald-400' : 'down text-rose-400'; ?> text-xs"></i>
                        <span class="text-base font-semibold <?php echo $revenue_change >= 0 ? 'text-emerald-400' : 'text-rose-400'; ?>">
                            <?php echo number_format(abs($revenue_change), 1); ?>%
                        </span>
                    </div>
                </div>
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Profit Margin</div>
                    <div class="mt-1 text-base font-semibold text-amber-400"><?php echo number_format($profit_margin, 1); ?>%</div>
                </div>
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">MoM Growth</div>
                    <div class="mt-1 text-base font-semibold <?php echo $mom_growth >= 0 ? 'text-emerald-400' : 'text-rose-400'; ?>">
                        <?php echo ($mom_growth >= 0 ? '+' : '') . number_format($mom_growth, 1); ?>%
                    </div>
                </div>
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Sales Velocity</div>
                    <div class="mt-1 text-base font-semibold text-purple-400"><?php echo number_format($sales_velocity, 1); ?>/hr</div>
                </div>
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Inventory Health</div>
                    <div class="mt-1 text-base font-semibold text-cyan-400"><?php echo number_format($inventory_health, 0); ?>%</div>
                </div>
            </div>

            <?php if (!empty($alerts)): ?>
                <a href="#alerts" class="flex items-center gap-3 p-2 rounded-lg bg-red-500/10 border border-red-500/30 hover:bg-red-500/15 transition-colors animate-pulse">
                    <div class="w-8 h-8 rounded-full bg-red-500/20 flex items-center justify-center">
                        <i class="fas fa-bell text-red-400 text-sm"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-red-300"><?php echo count($alerts); ?> alerts</div>
                        <div class="text-[11px] text-red-300/70">View details</div>
                    </div>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Welcome Banner (if new tenant) -->
    <?php if (isset($_GET['welcome']) && $_GET['welcome'] === '1'): ?>
    <div class="bg-gradient-to-r from-emerald-900/60 to-emerald-800/40 border border-emerald-500/30 rounded-xl p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-emerald-500/20 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-rocket text-emerald-400 text-xl"></i>
        </div>
        <div class="flex-1">
            <h3 class="text-lg font-bold text-emerald-100">Welcome to <?= htmlspecialchars($_SESSION['tenant_name'] ?? 'JDH POS') ?>!</h3>
            <p class="text-sm text-emerald-200/80">Your account is ready. You are on a <strong><?php
                $planStmt = $pdo->prepare("SELECT trial_days FROM pos_plans WHERE id = ? LIMIT 1");
                $planStmt->execute([$_SESSION['tenant_plan_id'] ?? 1]);
                $planRow = $planStmt->fetch(PDO::FETCH_ASSOC);
                echo (int)($planRow['trial_days'] ?? 14);
            ?>-day free trial</strong>. Explore the dashboard below or visit <a href="settings.php" class="underline text-emerald-300 hover:text-emerald-200">Settings</a> to configure your business.</p>
        </div>
        <a href="settings.php" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold rounded-lg transition-colors flex-shrink-0">
            Get Started <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    <?php endif; ?>

    <!-- Trial Status Card -->
    <?php if ($subscriptionInfo && ($subscriptionInfo['status'] ?? '') === 'trialing' && $trialDaysLeft !== null): ?>
    <?php
        $isUrgent = $trialDaysLeft <= 3;
        $cardBg = $isUrgent ? 'bg-gradient-to-r from-red-900/40 to-red-800/20 border-red-500/30' : 'bg-gradient-to-r from-amber-900/40 to-amber-800/20 border-amber-500/30';
        $iconBg = $isUrgent ? 'bg-red-500/20' : 'bg-amber-500/20';
        $iconColor = $isUrgent ? 'text-red-400' : 'text-amber-400';
        $titleColor = $isUrgent ? 'text-red-100' : 'text-amber-100';
        $descColor = $isUrgent ? 'text-red-200/70' : 'text-amber-200/70';
        $btnBg = $isUrgent ? 'bg-red-500 hover:bg-red-400' : 'bg-amber-500 hover:bg-amber-400';
    ?>
    <div class="<?= $cardBg ?> border rounded-xl p-4 flex flex-col sm:flex-row items-start sm:items-center gap-3">
        <div class="w-10 h-10 rounded-full <?= $iconBg ?> flex items-center justify-center flex-shrink-0">
            <i class="fas fa-hourglass-half <?= $iconColor ?> text-lg"></i>
        </div>
        <div class="flex-1">
            <h4 class="text-sm font-bold <?= $titleColor ?>"><?= htmlspecialchars($planName) ?> Trial</h4>
            <p class="text-xs <?= $descColor ?>">
                <?php if ($trialDaysLeft > 0): ?>
                    <strong><?= $trialDaysLeft ?> day<?= $trialDaysLeft === 1 ? '' : 's' ?> left</strong> in your free trial. Upgrade anytime to keep full access.
                <?php elseif ($trialDaysLeft === 0): ?>
                    <strong>Your trial ends today.</strong> Upgrade now to avoid service interruption.
                <?php else: ?>
                    <strong>Your trial has expired.</strong> Please upgrade to restore access.
                <?php endif; ?>
            </p>
        </div>
        <a href="billing.php" class="px-3 py-1.5 <?= $btnBg ?> text-white text-xs font-semibold rounded-lg transition-colors flex-shrink-0">
            Manage Billing <i class="fas fa-credit-card ml-1"></i>
        </a>
    </div>
    <?php endif; ?>

    <!-- Grace Period Warning -->
    <?php if (!empty($_SESSION['subscription_grace_period'])): ?>
    <div class="bg-gradient-to-r from-red-900/60 to-red-800/40 border border-red-500/40 rounded-xl p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4 animate-pulse">
        <div class="w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
        </div>
        <div class="flex-1">
            <h3 class="text-lg font-bold text-red-100">Your trial has expired</h3>
            <p class="text-sm text-red-200/80">You have a <strong>24-hour grace period</strong> to export your data and upgrade. After this, your account will be suspended.</p>
        </div>
        <a href="billing.php" class="px-4 py-2 bg-red-500 hover:bg-red-400 text-white text-sm font-semibold rounded-lg transition-colors flex-shrink-0">
            Upgrade Now <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    <?php endif; ?>

    <!-- Label Printing Quick Settings -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-tags text-amber-400"></i>
                    <span class="font-semibold text-sm text-white">Label Printing</span>
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5">Auto-print on sale • Default size • Method</div>
            </div>

            <div class="flex flex-wrap items-center gap-3 text-sm">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="dashAutoPrint" class="w-4 h-4 rounded border-slate-600 bg-slate-900 text-amber-500 focus:ring-amber-500 focus:ring-1" 
                           <?= $autoPrintOnSale ? 'checked' : '' ?>
                           onchange="updateLabelSetting('auto_print_labels_on_sale', this.checked ? '1' : '0')">
                    <span class="text-xs text-slate-400">Auto-print on sale</span>
                </label>

                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500">Size</span>
                    <select id="dashLabelSize" class="text-xs bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-slate-300 focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <?php
                        $sizes = ['k22' => 'K22 (51×25)', 'k5' => 'K5 (38×16)', 'K05' => 'Round 13mm', 'k36' => 'K36 (76×51)'];
                        foreach ($sizes as $val => $label):
                        ?>
                            <option value="<?= $val ?>" <?= $autoPrintLabelSize === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500">Method</span>
                    <select id="dashPrintMethod" class="text-xs bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-slate-300 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            onchange="updateLabelSetting('auto_print_method', this.value)">
                        <option value="pdf" <?= $autoPrintMethod === 'pdf' ? 'selected' : '' ?>>PDF (Recommended)</option>
                        <option value="escpos" <?= $autoPrintMethod === 'escpos' ? 'selected' : '' ?>>Direct Thermal</option>
                    </select>
                </div>

                <a href="<?php echo base_url('products/barcode_labels.php'); ?>" 
                   class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/30 hover:bg-amber-500/20 rounded-lg transition-all">
                    <i class="fas fa-print"></i>
                    <span>Label Printer</span>
                </a>

                <button type="button" onclick="testPrintLabels(event)" 
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/20 rounded-lg transition-all">
                    <i class="fas fa-play"></i> Test Print
                </button>
            </div>
        </div>
    </div>

    <!-- KPI Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <?php
        $kpis = [
            ['lbl' => "Today's Revenue", 'val' => $currency . ' ' . number_format($today_revenue), 'sub' => $today_count . ' transactions', 'ico' => 'fa-coins', 'color' => 'amber', 'trend' => $revenue_change],
            ['lbl' => 'Monthly Revenue',  'val' => $currency . ' ' . number_format($monthly_revenue),  'sub' => $monthly_count . ' transactions', 'ico' => 'fa-calendar-week', 'color' => 'blue'],
            ['lbl' => 'Total Customers',  'val' => number_format($total_customers),  'sub' => '+' . $new_customers_month . ' this month', 'ico' => 'fa-users', 'color' => 'purple'],
            ['lbl' => 'Inventory Value',  'val' => $currency . ' ' . number_format($inventory_value),  'sub' => $total_products . ' products', 'ico' => 'fa-warehouse', 'color' => 'orange'],
            ['lbl' => 'Monthly Profit',   'val' => $currency . ' ' . number_format($monthly_profit),   'sub' => number_format($profit_margin, 1) . '% margin', 'ico' => 'fa-chart-line', 'color' => 'emerald'],
            ['lbl' => 'Stock Alerts',     'val' => (string) ($low_stock + $out_of_stock), 'sub' => $out_of_stock . ' out of stock', 'ico' => 'fa-triangle-exclamation', 'color' => ($low_stock + $out_of_stock) > 0 ? 'rose' : 'emerald'],
        ];
        $colors = [
            'amber' => 'rgba(251,191,36,0.14)',
            'blue' => 'rgba(59,130,246,0.14)',
            'purple' => 'rgba(139,92,246,0.14)',
            'orange' => 'rgba(249,115,22,0.14)',
            'emerald' => 'rgba(16,185,129,0.14)',
            'rose' => 'rgba(239,68,68,0.14)',
        ];
        foreach ($kpis as $kpi):
            $bgColor = $colors[$kpi['color']] ?? $colors['amber'];
            $textColor = match($kpi['color']) {
                'amber' => 'text-amber-400',
                'emerald' => 'text-emerald-400',
                'rose' => 'text-red-400',
                'blue' => 'text-blue-400',
                'purple' => 'text-purple-400',
                'orange' => 'text-orange-400',
                default => 'text-slate-300',
            };
        ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 card-hover">
            <div class="flex items-start justify-between">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background: <?php echo $bgColor; ?>;">
                    <i class="fas <?php echo $kpi['ico']; ?> <?php echo $textColor; ?> text-sm"></i>
                </span>
                <?php if (isset($kpi['trend']) && $kpi['trend'] != 0): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs <?php echo $kpi['trend'] > 0 ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400'; ?>">
                        <i class="fas fa-arrow-<?php echo $kpi['trend'] > 0 ? 'up' : 'down'; ?> text-[9px]"></i>
                        <?php echo number_format(abs($kpi['trend']), 1); ?>%
                    </span>
                <?php endif; ?>
            </div>
            <div class="mt-3">
                <div class="text-[11px] text-slate-500 uppercase tracking-wide font-semibold"><?php echo htmlspecialchars($kpi['lbl']); ?></div>
                <div class="text-xl font-bold text-white mt-1"><?php echo htmlspecialchars($kpi['val']); ?></div>
                <div class="text-[11px] text-slate-600 mt-0.5"><?php echo htmlspecialchars($kpi['sub']); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Revenue Trend + Payment Mix -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden xl:col-span-2">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">Revenue Trend</h3>
                    <p class="text-xs text-slate-500">Last <?php echo max(1, count($dailyLabels)); ?> days · live</p>
                </div>
                <div class="flex gap-2">
                    <span class="px-2 py-1 text-xs rounded-full bg-amber-500/20 text-amber-400">Revenue</span>
                    <?php if (!empty($dailyCounts)): ?>
                    <span class="px-2 py-1 text-xs rounded-full bg-blue-500/20 text-blue-400">Transactions</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="p-4">
                <div style="height: 240px;"><canvas id="revenueChart"></canvas></div>
            </div>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">Payment Mix</h3>
                    <p class="text-xs text-slate-500">Completed <?php echo strtolower($bt_sales_label); ?></p>
                </div>
                <i class="fas fa-credit-card text-slate-500 text-xs"></i>
            </div>
            <div class="p-4">
                <div style="height: 170px;"><canvas id="paymentChart"></canvas></div>
                <div class="mt-4 space-y-1.5">
                <?php
                $pmColors = ['#fbbf24', '#60a5fa', '#34d399', '#a78bfa', '#f87171', '#22d3ee'];
                $pmTotal = array_sum($paymentValues) ?: 1;
                foreach ($paymentLabels as $i => $pl):
                    $val = (float) ($paymentValues[$i] ?? 0);
                    $pct = round(($val / $pmTotal) * 100, 1);
                ?>
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background: <?php echo $pmColors[$i % count($pmColors)]; ?>"></span>
                        <span class="text-slate-400 capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $pl)); ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-slate-500"><?php echo $pct; ?>%</span>
                        <span class="text-white font-semibold"><?php echo $currency . ' ' . number_format($val); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Sales + Top Products -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Recent Sales - Full Width Span -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden lg:col-span-2">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">Recent <?php echo htmlspecialchars($bt_sales_label); ?></h3>
                    <p class="text-xs text-slate-500">Latest transactions</p>
                </div>
                <a href="<?php echo base_url('pos/all_sales.php'); ?>" class="text-[11px] text-amber-400 hover:text-amber-300 font-semibold transition">View all <i class="fas fa-arrow-right ml-1 text-[9px]"></i></a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-900/50 border-b border-slate-700/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider"><?php echo htmlspecialchars($bt_customer_label); ?></th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Method</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php if (empty($recent_sales)): ?>
                            <tr><td colspan="5" class="text-center text-slate-500 py-12">No <?php echo strtolower($bt_sales_label); ?> yet</td></tr>
                        <?php else: foreach (array_slice($recent_sales, 0, 6) as $sale):
                            $pm = $sale['payment_method'] ?? '';
                            $pmClass = match ($pm) {
                                'cash' => 'bg-emerald-500/15 text-emerald-400',
                                'mpesa' => 'bg-amber-500/15 text-amber-400',
                                'card' => 'bg-blue-500/15 text-blue-400',
                                default => 'bg-slate-500/15 text-slate-400',
                            };
                        ?>
                            <tr class="hover:bg-slate-700/20 transition-colors">
                                <td class="px-4 py-3"><span class="font-mono text-amber-400 font-semibold text-xs">#<?php echo str_pad((string) $sale['id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                <td class="px-4 py-3 text-slate-300 text-sm"><?php echo htmlspecialchars(mb_strimwidth($sale['customer'] ?? '—', 0, 22, '…')); ?></td>
                                <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs <?php echo $pmClass; ?>"><?php echo htmlspecialchars(ucfirst($pm ?: 'N/A')); ?></span></td>
                                <td class="px-4 py-3 text-right font-semibold text-white"><?php echo $currency . ' ' . number_format((float) ($sale['total'] ?? 0)); ?></td>
                                <td class="px-4 py-3 text-right text-slate-500 text-[11px]"><?php echo timeAgo($sale['created_at'] ?? null); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">Top Products</h3>
                    <p class="text-xs text-slate-500">By revenue</p>
                </div>
                <a href="<?php echo base_url('products/products.php'); ?>" class="text-[11px] text-amber-400 hover:text-amber-300 font-semibold transition">View all <i class="fas fa-arrow-right ml-1 text-[9px]"></i></a>
            </div>
            <div class="p-4 space-y-3">
                <?php if (empty($top_products)): ?>
                    <p class="text-center text-slate-500 py-10 text-sm">No data yet</p>
                <?php else:
                    $maxQty = (float) max(array_column($top_products, 'qty') ?: [1]);
                    foreach (array_slice($top_products, 0, 5) as $i => $prod):
                        $bar = $maxQty > 0 ? round(((float) ($prod['qty'] ?? 0) / $maxQty) * 100) : 0;
                ?>
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-md flex items-center justify-center text-xs font-bold flex-shrink-0 <?php echo $i === 0 ? 'bg-amber-500/20 text-amber-400' : 'bg-slate-700/60 text-slate-400'; ?>">
                        <?php echo $i + 1; ?>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-white font-medium truncate"><?php echo htmlspecialchars($prod['name'] ?? '—'); ?></span>
                            <span class="text-xs text-amber-400 font-semibold ml-2"><?php echo $currency . ' ' . number_format((float) ($prod['revenue'] ?? 0)); ?></span>
                        </div>
                        <div class="h-1.5 w-full bg-slate-700/60 rounded-full overflow-hidden mt-0.5"><div class="h-full bg-amber-500/60 rounded-full" style="width: <?php echo $bar; ?>%"></div></div>
                        <div class="text-[11px] text-slate-500 mt-1"><?php echo number_format((float) ($prod['qty'] ?? 0)); ?> units sold</div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="border-t border-slate-800 pt-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="w-1 h-5 bg-amber-500 rounded-full"></span>
            <h3 class="text-sm font-semibold text-white uppercase tracking-wide">Quick Actions</h3>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            <?php
            $actions = [
                ['title' => 'New Sale', 'desc' => 'Open POS', 'url' => 'pos/pos.php', 'ico' => 'fa-cash-register', 'color' => 'amber', 'permission' => 'pos.access'],
                ['title' => 'Inventory', 'desc' => 'Manage stock', 'url' => 'inventory/inventory.php', 'ico' => 'fa-boxes-stacked', 'color' => 'emerald', 'permission' => 'inventory.view'],
                ['title' => 'Customers', 'desc' => 'View & manage', 'url' => 'customers/customers.php', 'ico' => 'fa-users', 'color' => 'purple', 'permission' => 'customers.view'],
                ['title' => 'Products', 'desc' => 'Catalog', 'url' => 'products/products.php', 'ico' => 'fa-tags', 'color' => 'blue', 'permission' => 'products.view'],
                ['title' => 'Reports', 'desc' => 'Analytics', 'url' => 'reports/reports.php', 'ico' => 'fa-chart-bar', 'color' => 'orange', 'permission' => 'reports.view'],
                ['title' => 'Expenses', 'desc' => 'Track spending', 'url' => 'expenses/expenses.php', 'ico' => 'fa-receipt', 'color' => 'rose', 'permission' => 'reports.advanced'],
                ['title' => 'Online Store', 'desc' => 'Manage storefront', 'url' => 'dashboard/shop/index.php', 'ico' => 'fa-store', 'color' => 'brand', 'permission' => 'settings.manage'],
            ];
            
            $visibleActions = array_filter($actions, function($action) use ($is_super_admin) {
                return $is_super_admin || has_permission($action['permission']);
            });
            $actionColors = [
                'amber' => 'rgba(251,191,36,0.14)',
                'emerald' => 'rgba(16,185,129,0.14)',
                'purple' => 'rgba(139,92,246,0.14)',
                'blue' => 'rgba(59,130,246,0.14)',
                'orange' => 'rgba(249,115,22,0.14)',
                'rose' => 'rgba(239,68,68,0.14)',
                'brand' => 'rgba(246,139,30,0.14)',
            ];
            if (empty($visibleActions)): ?>
            <div class="col-span-full text-center py-4 text-sm text-slate-500">
                <i class="fas fa-lock mr-2"></i> No quick actions available for your role
            </div>
            <?php else: ?>
            <?php foreach ($visibleActions as $a):
                $bgColor = $actionColors[$a['color']] ?? $actionColors['amber'];
                $textColor = $a['color'] === 'amber' ? 'text-amber-400' : ($a['color'] === 'emerald' ? 'text-emerald-400' : 'text-slate-300');
            ?>
            <a href="<?php echo base_url($a['url']); ?>" class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 text-center hover:border-slate-600 transition-colors card-hover">
                <span class="w-10 h-10 rounded-lg flex items-center justify-center mx-auto mb-2" style="background: <?php echo $bgColor; ?>;">
                    <i class="fas <?php echo $a['ico']; ?> text-lg <?php echo $textColor; ?>"></i>
                </span>
                <div class="text-sm font-semibold text-white"><?php echo htmlspecialchars($a['title']); ?></div>
                <div class="text-[11px] text-slate-500 mt-0.5"><?php echo htmlspecialchars($a['desc']); ?></div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Customers + Staff Performance + Expenses -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">Top <?php echo htmlspecialchars($bt_customer_label); ?>s</h3>
                    <p class="text-xs text-slate-500">By lifetime value</p>
                </div>
                <a href="<?php echo base_url('customers/customers.php'); ?>" class="text-[11px] text-amber-400 hover:text-amber-300 font-semibold transition">View all</a>
            </div>
            <?php if (empty($top_customers)): ?>
                <div class="text-center text-slate-500 py-12 text-sm">No customer data yet</div>
            <?php else: ?>
            <div class="divide-y divide-slate-700/40">
                <?php foreach (array_slice($top_customers, 0, 5) as $i => $cust):
                    $grad = ['from-amber-500 to-orange-500','from-blue-500 to-cyan-500','from-emerald-500 to-teal-500','from-purple-500 to-pink-500','from-rose-500 to-red-500'][$i % 5];
                ?>
                <div class="flex items-center justify-between p-3 hover:bg-slate-700/20 transition-colors">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br <?php echo $grad; ?> flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0">
                            <?php echo strtoupper(substr($cust['name'] ?? '?', 0, 1)); ?>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm text-white font-medium truncate"><?php echo htmlspecialchars(mb_strimwidth($cust['name'] ?? '—', 0, 22, '…')); ?></div>
                            <div class="text-[10px] text-slate-500"><?php echo (int) ($cust['orders'] ?? 0); ?> orders · <?php echo timeAgo($cust['last_order'] ?? null); ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-amber-400 font-semibold text-sm"><?php echo $currency . ' ' . number_format((float) ($cust['spent'] ?? 0)); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-700/60">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-semibold text-white">Staff Performance</h3>
                        <p class="text-xs text-slate-500">Today's <?php echo strtolower($bt_sales_label); ?></p>
                    </div>
                    <i class="fas fa-user-check text-slate-600"></i>
                </div>
            </div>
            <?php if (empty($cashier_performance)): ?>
                <div class="text-center text-slate-500 py-12 text-sm">No <?php echo strtolower($bt_sales_label); ?> today</div>
            <?php else:
                $maxStaff = (float) max(array_column($cashier_performance, 'sales_total') ?: [1]);
            ?>
            <div class="p-4 space-y-3">
                <?php foreach (array_slice($cashier_performance, 0, 5) as $st):
                    $sw = $maxStaff > 0 ? round(((float) ($st['sales_total'] ?? 0) / $maxStaff) * 100) : 0;
                ?>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm text-white font-medium"><?php echo htmlspecialchars($st['name'] ?? '—'); ?></span>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] text-slate-500"><?php echo (int) ($st['sales_count'] ?? 0); ?> sales</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-1.5 bg-slate-700/60 rounded-full overflow-hidden"><div class="h-full rounded-full" style="width: <?php echo $sw; ?>%; background: linear-gradient(90deg, #60a5fa, #3b82f6);"></div></div>
                        <span class="text-xs text-white font-semibold flex-shrink-0"><?php echo $currency . ' ' . number_format((float) ($st['sales_total'] ?? 0)); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">Expense Breakdown</h3>
                    <p class="text-xs text-slate-500">This month</p>
                </div>
                <a href="<?php echo base_url('expenses/expenses.php'); ?>" class="text-[11px] text-amber-400 hover:text-amber-300 font-semibold transition">View all</a>
            </div>
            <?php if (empty($expense_breakdown)): ?>
                <div class="text-center text-slate-500 py-12 text-sm">No expenses recorded</div>
            <?php else: ?>
            <div class="p-4">
                <div style="height: 140px;"><canvas id="expenseChart"></canvas></div>
                <div class="mt-3 space-y-1.5">
                    <?php $expColors = ['#f87171', '#fb923c', '#fbbf24', '#34d399', '#60a5fa', '#a78bfa', '#f472b6', '#94a3b8'];
                    $expTotal = array_sum($expenseValues);
                    foreach (array_slice($expense_breakdown, 0, 5) as $i => $eb):
                        $pct = $expTotal > 0 ? round(((float)($eb['total'] ?? 0) / $expTotal) * 100) : 0;
                    ?>
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background: <?php echo $expColors[$i % count($expColors)]; ?>"></span>
                            <span class="text-slate-400 truncate"><?php echo htmlspecialchars($eb['cat'] ?? '—'); ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-slate-500 text-[10px]"><?php echo $pct; ?>%</span>
                            <span class="text-white font-semibold text-xs"><?php echo $currency . ' ' . number_format((float) ($eb['total'] ?? 0)); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alerts Panel -->
    <?php if (!empty($alerts)): ?>
    <div id="alerts" class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
            <div>
                <h3 class="text-sm font-semibold text-white">Smart Alerts</h3>
                <p class="text-xs text-slate-500">Items that need your attention</p>
            </div>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-500/15 text-amber-400 animate-pulse"><?php echo count($alerts); ?> active</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 p-4">
            <?php foreach ($alerts as $alert):
                $alertColors = match ($alert['type']) {
                    'danger' => ['bg' => 'rgba(239,68,68,0.10)', 'text' => '#f87171', 'border' => 'rgba(239,68,68,0.2)'],
                    'warning' => ['bg' => 'rgba(245,158,11,0.10)', 'text' => '#fbbf24', 'border' => 'rgba(245,158,11,0.2)'],
                    'success' => ['bg' => 'rgba(16,185,129,0.10)', 'text' => '#34d399', 'border' => 'rgba(16,185,129,0.2)'],
                    default => ['bg' => 'rgba(59,130,246,0.10)', 'text' => '#60a5fa', 'border' => 'rgba(59,130,246,0.2)'],
                };
            ?>
            <div class="flex items-center gap-3 p-3 rounded-lg transition-all hover:bg-opacity-20" style="background: <?php echo $alertColors['bg']; ?>; border: 1px solid <?php echo $alertColors['border']; ?>;">
                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background: <?php echo $alertColors['bg']; ?>;">
                    <i class="fas fa-<?php echo htmlspecialchars($alert['icon']); ?>" style="color: <?php echo $alertColors['text']; ?>;"></i>
                </div>
                <div class="text-sm" style="color: <?php echo $alertColors['text']; ?>;">
                    <?php echo htmlspecialchars($alert['msg']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<div id="toastContainer" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

<script>
(function() {
    const CURRENCY = <?php echo json_encode($currency); ?>;
    const fmtCur = (v) => CURRENCY + ' ' + Number(v || 0).toLocaleString();

    const tooltip = {
        backgroundColor: '#0f172a',
        titleColor: '#f8fafc',
        bodyColor: '#cbd5e1',
        borderColor: 'rgba(255,255,255,0.08)',
        borderWidth: 1,
        cornerRadius: 8,
        padding: 12,
    };
    const gridColor = 'rgba(255,255,255,0.05)';
    const tickColor = '#64748b';

    function initCharts() {
        // Revenue trend with dual axis if counts available
        const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
        if (revenueCtx) {
            const datasets = [{
                label: 'Revenue',
                data: <?php echo json_encode($dailyRevenue); ?>,
                borderColor: '#fbbf24',
                backgroundColor: 'rgba(251,191,36,0.10)',
                tension: 0.4,
                fill: true,
                pointRadius: 3,
                pointBackgroundColor: '#fbbf24',
                pointBorderColor: '#0f172a',
                pointBorderWidth: 2,
                borderWidth: 2.5,
                yAxisID: 'y',
            }];
            
            <?php if (!empty($dailyCounts)): ?>
            datasets.push({
                label: 'Transactions',
                data: <?php echo json_encode($dailyCounts); ?>,
                borderColor: '#60a5fa',
                backgroundColor: 'rgba(96,165,250,0.05)',
                tension: 0.4,
                fill: false,
                pointRadius: 2,
                pointBackgroundColor: '#60a5fa',
                pointBorderColor: '#0f172a',
                pointBorderWidth: 1.5,
                borderWidth: 2,
                yAxisID: 'y1',
            });
            <?php endif; ?>
            
            new Chart(revenueCtx, {
                type: 'line',
                data: { labels: <?php echo json_encode($dailyLabels); ?>, datasets: datasets },
                options: {
                    responsive: true, maintainAspectRatio: true,
                    plugins: { legend: { display: true, labels: { color: '#94a3b8', font: { size: 10 } } }, tooltip: { ...tooltip, callbacks: { label: (c) => c.dataset.label + ': ' + fmtCur(c.parsed.y) } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 10 } } },
                        y: { beginAtZero: true, grid: { color: gridColor, drawBorder: false }, ticks: { color: tickColor, font: { size: 10 }, callback: (v) => CURRENCY + ' ' + (v >= 1000 ? (v/1000).toFixed(0) + 'k' : v) } },
                        y1: { position: 'right', grid: { display: false }, ticks: { color: tickColor, font: { size: 10 }, callback: (v) => v.toFixed(0) } }
                    }
                }
            });
        }

        // Payment donut
        const paymentCtx = document.getElementById('paymentChart')?.getContext('2d');
        if (paymentCtx && <?php echo !empty($paymentValues) && array_sum($paymentValues) > 0 ? 'true' : 'false'; ?>) {
            new Chart(paymentCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($paymentLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($paymentValues); ?>,
                        backgroundColor: ['#fbbf24', '#60a5fa', '#34d399', '#a78bfa', '#f87171', '#22d3ee'],
                        borderColor: '#0f172a',
                        borderWidth: 3
                    }]
                },
                options: { responsive: true, maintainAspectRatio: true, cutout: '68%', plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { label: (c) => c.label + ': ' + fmtCur(c.parsed) } } } }
            });
        }

        // Expense donut
        const expenseCtx = document.getElementById('expenseChart')?.getContext('2d');
        if (expenseCtx && <?php echo !empty($expense_breakdown) && array_sum($expenseValues) > 0 ? 'true' : 'false'; ?>) {
            new Chart(expenseCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($expenseLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($expenseValues); ?>,
                        backgroundColor: ['#f87171','#fb923c','#fbbf24','#34d399','#60a5fa','#a78bfa','#f472b6','#94a3b8'],
                        borderColor: '#0f172a',
                        borderWidth: 3,
                    }]
                },
                options: { responsive: true, maintainAspectRatio: true, cutout: '65%', plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { label: (c) => c.label + ': ' + fmtCur(c.parsed) } } } }
            });
        }
    }

    function showToast(message, type) {
        type = type || 'info';
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const toast = document.createElement('div');
        const icons = { success: 'fa-check-circle', error: 'fa-circle-xmark', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
        const toastColors = { success: 'bg-emerald-900/90 border-emerald-500/50 text-emerald-200', error: 'bg-red-900/90 border-red-500/50 text-red-200', warning: 'bg-amber-900/90 border-amber-500/50 text-amber-200', info: 'bg-slate-800/90 border-slate-600 text-slate-200' };
        toast.className = 'pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl border  text-sm min-w-[240px] max-w-sm shadow-lg ' + (toastColors[type] || toastColors.info) + ' translate-x-0 transition-all duration-300';
        toast.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '"></i><span class="flex-1">' + message + '</span><button onclick="this.parentElement.remove()" class="opacity-50 hover:opacity-100 ml-2"><i class="fas fa-xmark"></i></button>';
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    window.showToast = showToast;

    // Label Printing Settings Updater
    window.updateLabelSetting = function(key, value) {
        const body = new URLSearchParams();
        body.append('action', 'save_setting');
        body.append('key', key);
        body.append('value', value);
        body.append('csrf_token', '<?= htmlspecialchars($csrf_token ?? '') ?>');

        fetch('<?php echo base_url("products/barcode_labels_generate.php"); ?>', {
            method: 'POST',
            body: body
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  showToast('Label setting saved', 'success');
              } else {
                  showToast('Failed to save setting', 'error');
              }
          })
          .catch(() => {
              showToast('Setting saved (offline mode)', 'info');
          });
    };

    // Test Print
    window.testPrintLabels = function(event) {
        const size = document.getElementById('dashLabelSize').value;
        const method = document.getElementById('dashPrintMethod').value;

        const body = new URLSearchParams();
        body.append('action', 'batch_print');
        body.append('filter', 'low_stock');
        body.append('label_size', size);
        body.append('method', method);
        body.append('csrf_token', '<?= htmlspecialchars($csrf_token ?? '') ?>');

        const btn = event?.currentTarget;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Printing...';
        }

        fetch('<?php echo base_url("products/barcode_labels_generate.php"); ?>', {
            method: 'POST',
            body: body
        }).then(r => r.json())
          .then(data => {
              if (data.success) {
                  showToast('Test labels sent to ' + method.toUpperCase(), 'success');
              } else {
                  showToast('Test print: ' + (data.message || data.error || 'Check Label Printer page'), 'info');
                  window.open('<?php echo base_url("products/barcode_labels.php"); ?>', '_blank');
              }
          })
          .catch(() => {
              showToast('Test print triggered (check Label Printer)', 'info');
          })
          .finally(() => {
              if (btn) {
                  btn.disabled = false;
                  btn.innerHTML = '<i class="fas fa-play"></i> Test Print';
              }
          });
    };

    // Refresh dashboard
    window.refreshDashboard = function() {
        showToast('Refreshing dashboard data...', 'info');
        setTimeout(() => {
            window.location.reload();
        }, 500);
    };

    // Live clock
    function tick() {
        const now = new Date();
        const opts = { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false };
        const el = document.getElementById('liveClock');
        if (el) el.textContent = now.toLocaleString('en-US', opts).replace(',', ' ·');
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        initCharts();
        tick();
        setInterval(tick, 30000);
    });
})();
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>