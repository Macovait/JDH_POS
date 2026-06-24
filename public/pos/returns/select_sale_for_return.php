<?php
/**
 * Select Sale for Return - PURE TAILWIND EDITION
 * Features: AI Predictions, Approval Workflow, Saved Filters, Real-time Alerts
 * @version 5.3 - Consistent Font Sizes
 */

header_remove('X-Powered-By');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();

if (!check_permission('sales.returns') && !is_super_admin()) {
    enforce_permission('sales.returns');
}

safe_require('db.php', 'src', true);

$page_title = 'Select Sale for Return';
ob_start();

// Get current tenant and branch info
$tenant_id = get_current_tenant_id();
$current_branch_id = get_current_branch_id();
$current_branch_name = get_current_branch_name();
$current_user_id = get_current_user_id();
$is_super_admin = is_super_admin();

// Get user role for approval limits
$user_role = 'cashier';
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT r.name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$current_user_id]);
    $role_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($role_data) $user_role = strtolower($role_data['name']);
} catch (Exception $e) {}

// Get all branches for super admin
$branches = [];
if ($is_super_admin || check_permission('branches.view')) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE active = 1 AND tenant_id = ? ORDER BY name");
        $stmt->execute([(int)$tenant_id]);
        $branches = $stmt->fetchAll();
    } catch (PDOException $e) {}
}

$can_switch_branch = $is_super_admin || check_permission('branches.view');
$selected_branch_id = $current_branch_id;
$selected_branch_name = $current_branch_name;

// Handle branch switch via GET
if ($can_switch_branch && isset($_GET['branch_id'])) {
    $requested_branch_id = (int)$_GET['branch_id'];
    foreach ($branches as $branch) {
        if ((int)$branch['id'] === $requested_branch_id) {
            $selected_branch_id = $requested_branch_id;
            $selected_branch_name = $branch['name'];
            break;
        }
    }
}

$currency_symbol = 'KSh';
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = ?");
    $stmt->execute([(int)$tenant_id]);
    $curr = $stmt->fetchColumn();
    if ($curr) $currency_symbol = $curr;
} catch (Exception $e) {}

// ============================================================================
// STATISTICS
// ============================================================================
$return_stats = ['total_returns' => 0, 'completed' => 0, 'pending' => 0, 'rejected' => 0, 'total_amount' => 0, 'completed_amount' => 0];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_returns,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as completed_amount
        FROM returns 
        WHERE tenant_id = ? AND branch_id = ?
    ");
    $stmt->execute([$tenant_id, $selected_branch_id]);
    $stats_result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($stats_result) $return_stats = $stats_result;
} catch (Exception $e) {}

// ============================================================================
// HIGH VALUE RETURN THRESHOLD
// ============================================================================
$high_value_threshold = 10000;
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'high_value_return_threshold' AND tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $threshold = $stmt->fetchColumn();
    if ($threshold) $high_value_threshold = (float)$threshold;
} catch (Exception $e) {}

// ============================================================================
// USER APPROVAL LIMITS
// ============================================================================
$user_approval_limit = 5000;
if ($user_role === 'manager') $user_approval_limit = 50000;
if ($user_role === 'admin' || $is_super_admin) $user_approval_limit = 999999;

// ============================================================================
// TOP RETURNED PRODUCTS
// ============================================================================
$top_returned_products = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT p.name, COUNT(ri.id) as return_count, SUM(ri.quantity) as total_quantity,
               SUM(ri.subtotal) as total_value
        FROM return_items ri
        JOIN returns r ON ri.return_id = r.id
        JOIN products p ON ri.product_id = p.id
        WHERE r.tenant_id = ? AND r.branch_id = ? AND r.status = 'completed'
        GROUP BY p.id
        ORDER BY return_count DESC
        LIMIT 5
    ");
    $stmt->execute([$tenant_id, $selected_branch_id]);
    $top_returned_products = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================================
// RETURN REASONS
// ============================================================================
$return_reasons = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT reason, COUNT(*) as count, SUM(amount) as total_amount
        FROM returns 
        WHERE tenant_id = ? AND branch_id = ? AND status = 'completed' AND reason IS NOT NULL AND reason != ''
        GROUP BY reason 
        ORDER BY count DESC
        LIMIT 5
    ");
    $stmt->execute([$tenant_id, $selected_branch_id]);
    $return_reasons = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================================
// CHART DATA
// ============================================================================
$chart_dates = [];
$chart_counts = [];
$chart_amounts = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_dates[] = date('M d', strtotime($date));
    $chart_counts[] = 0;
    $chart_amounts[] = 0;
}
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as return_date, COUNT(*) as count, SUM(amount) as total_amount
        FROM returns 
        WHERE tenant_id = ? AND branch_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY return_date ASC
    ");
    $stmt->execute([$tenant_id, $selected_branch_id]);
    $daily_data = $stmt->fetchAll();
    
    $chart_dates_display = [];
    $chart_counts_display = [];
    $chart_amounts_display = [];
    for ($i = 29; $i >= 0; $i--) {
        $date_val = date('Y-m-d', strtotime("-$i days"));
        $date_label = date('M d', strtotime($date_val));
        $chart_dates_display[] = $date_label;
        $found = false;
        foreach ($daily_data as $dd) {
            if ($dd['return_date'] == $date_val) {
                $chart_counts_display[] = (int)$dd['count'];
                $chart_amounts_display[] = (float)$dd['total_amount'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $chart_counts_display[] = 0;
            $chart_amounts_display[] = 0;
        }
    }
    $chart_dates = $chart_dates_display;
    $chart_counts = $chart_counts_display;
    $chart_amounts = $chart_amounts_display;
} catch (Exception $e) {}

// ============================================================================
// SAVED FILTERS
// ============================================================================
$saved_filters = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT * FROM saved_filters 
        WHERE user_id = ? AND tenant_id = ? 
        ORDER BY is_default DESC, filter_name ASC
    ");
    $stmt->execute([$current_user_id, $tenant_id]);
    $saved_filters = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================================
// RECENT RETURNS
// ============================================================================
$recent_returns = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT r.id, r.return_number, r.status, r.amount, r.created_at,
               s.invoice_number, c.name as customer_name,
               CASE WHEN r.amount > ? THEN 1 ELSE 0 END as high_value
        FROM returns r
        LEFT JOIN sales s ON r.sale_id = s.id
        LEFT JOIN customers c ON r.customer_id = c.id
        WHERE r.tenant_id = ? AND r.branch_id = ?
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$high_value_threshold, $tenant_id, $selected_branch_id]);
    $recent_returns = $stmt->fetchAll();
    foreach ($recent_returns as &$ret) {
        $ret['amount_formatted'] = number_format((float) ($ret['amount'] ?? 0), 0);
        $ret['date_formatted'] = date('M d, H:i', strtotime($ret['created_at']));
    }
} catch (Exception $e) {}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
    .risk-low { background: rgba(16,185,129,0.12); color: #10b981; }
    .risk-medium { background: rgba(245,158,11,0.12); color: #f59e0b; }
    .risk-high { background: rgba(239,68,68,0.12); color: #ef4444; }
    .risk-critical { background: rgba(139,0,0,0.25); color: #f87171; animation: pulse 1s infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-spinner { width: 20px; height: 20px; border: 2px solid rgba(251,191,36,0.2); border-top-color: #fbbf24; border-radius: 50%; animation: spin 0.8s linear infinite; }
    .expand-row { display: none; }
    .expand-row.open { display: table-row; }
    .expand-toggle { cursor: pointer; transition: transform 0.2s; display: inline-block; }
    .expand-toggle.rotated { transform: rotate(180deg); }
    @media (max-width: 768px) { .desktop-only { display: none; } .mobile-only { display: block; } }
    @media (min-width: 769px) { .mobile-only { display: none; } .desktop-only { display: block; } }
</style>

<div class="space-y-4">
    
    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-20 right-4 z-50 space-y-2"></div>

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-undo-alt text-amber-400"></i> Select Sale for Return
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">AI-powered predictions &bull; Approval workflow &bull; Real-time analytics</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button onclick="openAnalyticsModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-chart-line text-xs"></i> Analytics
            </button>
            <a href="list_sell_return.php?branch_id=<?php echo $selected_branch_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-list text-xs"></i> All Returns
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 mb-5">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
                <i class="fas fa-exchange-alt text-amber-400 text-xs"></i>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-bold text-amber-400"><?php echo number_format((float) ($return_stats['total_returns'] ?? 0)); ?></div>
                <div class="text-xs text-slate-500 leading-none mt-0.5">Total Returns</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
                <i class="fas fa-check-circle text-emerald-400 text-xs"></i>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-bold text-emerald-400"><?php echo number_format((float) ($return_stats['completed'] ?? 0)); ?></div>
                <div class="text-xs text-slate-500 leading-none mt-0.5">Completed</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
                <i class="fas fa-money-bill-wave text-amber-400 text-xs"></i>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-bold text-amber-400 truncate"><?php echo $currency_symbol . ' ' . number_format((float) ($return_stats['completed_amount'] ?? 0), 0); ?></div>
                <div class="text-xs text-slate-500 leading-none mt-0.5">Return Value</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center shrink-0">
                <i class="fas fa-chart-line text-purple-400 text-xs"></i>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-bold text-purple-400"><?php echo number_format((float) (($return_stats['completed_amount'] ?? 0) / max(1, (float) ($return_stats['completed'] ?? 0))), 0); ?></div>
                <div class="text-xs text-slate-500 leading-none mt-0.5">Avg Return</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center shrink-0">
                <i class="fas fa-chart-simple text-cyan-400 text-xs"></i>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-bold text-cyan-400"><?php echo round(((float) ($return_stats['completed'] ?? 0) / max(1, (float) ($return_stats['total_returns'] ?? 0))) * 100, 1); ?>%</div>
                <div class="text-xs text-slate-500 leading-none mt-0.5">Approval Rate</div>
                <div class="progress-bar mt-1"><div class="progress-fill" style="width: <?php echo round(((float) ($return_stats['completed'] ?? 0) / max(1, (float) ($return_stats['total_returns'] ?? 0))) * 100, 1); ?>%"></div></div>
            </div>
        </div>
    </div>

    <!-- AI Risk Banner -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl py-2 px-3">
        <div class="flex items-center gap-2">
            <i class="fas fa-brain text-amber-400 text-xs"></i>
            <span class="text-sm font-medium text-slate-300">AI Risk Assessment Active</span>
            <span class="text-xs text-slate-500 ml-auto"><i class="fas fa-chart-line mr-1"></i>Predicting return probability for each sale</span>
        </div>
    </div>

    <!-- Quick Scan -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="px-3 py-2.5 border-b border-slate-700/60 flex items-center gap-2">
            <i class="fas fa-qrcode text-amber-400 text-xs"></i>
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Quick Scan / Invoice Lookup</span>
            <span class="text-xs text-slate-500 ml-auto"><i class="fas fa-keyboard mr-1"></i>Enter to search</span>
        </div>
        <div class="p-3">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="text" id="barcode-input" class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm font-mono placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="Scan barcode, type invoice number, or customer phone...">
                </div>
                <button onclick="scanSale()" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">Scan</button>
            </div>
            <div id="scan-result" class="hidden mt-1 text-xs"></div>
        </div>
    </div>

    <!-- Branch Filter (if applicable) -->
    <?php if ($can_switch_branch && !empty($branches)): ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="px-3 py-2.5 border-b border-slate-700/60 flex items-center gap-2">
            <i class="fas fa-store text-amber-400 text-xs"></i>
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Branch Selection</span>
        </div>
        <div class="p-3 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
            <select id="branch-filter" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500" onchange="window.location.href='?branch_id='+this.value">
                <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo $branch['id']; ?>" <?php echo $selected_branch_id == $branch['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="text-xs text-slate-500">
                <i class="fas fa-info-circle mr-1"></i>
                Viewing: <span class="text-amber-400 font-medium"><?php echo htmlspecialchars($selected_branch_name); ?></span>
                <?php if ($user_role === 'cashier'): ?>
                    <span class="ml-2 text-yellow-500">&#9888; Limit: <?php echo $currency_symbol . ' ' . number_format((float) ($user_approval_limit ?? 0)); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 space-y-3">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <i class="fas fa-filter text-amber-400 text-xs"></i>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Smart Filters</span>
            </div>
            <?php if (!empty($saved_filters)): ?>
            <div class="flex items-center gap-2">
                <select id="saved-filter-select" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:ring-1 focus:ring-amber-500" onchange="loadSavedFilter(this.value)">
                    <option value="">Load saved filter...</option>
                    <?php foreach ($saved_filters as $filter): ?>
                        <option value="<?php echo $filter['id']; ?>"><?php echo htmlspecialchars($filter['filter_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button onclick="openSavedFiltersModal()" class="inline-flex items-center gap-1 px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-save text-amber-400 text-xs"></i>Save</button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Date Pills -->
        <div class="flex flex-wrap gap-1.5">
            <button data-range="today" class="date-pill px-3 py-1 text-sm font-medium rounded-full bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 transition-colors" onclick="setDateRange(this, 'today')">Today</button>
            <button data-range="yesterday" class="date-pill px-3 py-1 text-sm font-medium rounded-full bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 transition-colors" onclick="setDateRange(this, 'yesterday')">Yesterday</button>
            <button data-range="7days" class="date-pill px-3 py-1 text-sm font-medium rounded-full bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 transition-colors" onclick="setDateRange(this, '7days')">Last 7 Days</button>
            <button data-range="30days" class="date-pill active px-3 py-1 text-sm font-medium rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30 transition-colors" onclick="setDateRange(this, '30days')">Last 30 Days</button>
            <button data-range="custom" class="date-pill px-3 py-1 text-sm font-medium rounded-full bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 transition-colors" onclick="setDateRange(this, 'custom')">Custom</button>
            <button onclick="toggleBulkReturnMode()" class="px-3 py-1 text-sm font-medium rounded-full bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 transition-colors ml-auto">
                <i class="fas fa-layer-group mr-1"></i> Bulk Mode
            </button>
        </div>

        <!-- Search + Date Row -->
        <div class="flex flex-wrap gap-2 items-end">
            <div class="relative flex-1 min-w-[160px]">
                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                <input type="text" id="sale-search" class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="Search by invoice #, customer name, or phone...">
            </div>
            <input type="date" id="date-from" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
            <input type="date" id="date-to" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500" value="<?php echo date('Y-m-d'); ?>">
            <button onclick="loadSales(1)" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors"><i class="fas fa-filter mr-1 text-xs"></i>Filter</button>
            <button onclick="resetFilters()" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-times mr-1 text-xs"></i>Clear</button>
        </div>

        <!-- Advanced Filters Toggle -->
        <div>
            <button onclick="toggleAdvancedFilters()" class="text-xs text-slate-500 hover:text-amber-400 transition-colors">
                <i class="fas fa-chevron-down mr-1" id="advanced-toggle-icon"></i> Advanced Filters
            </button>
            <div id="advanced-filters" class="hidden mt-2 flex flex-wrap gap-2">
                <select id="risk-filter" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="">All Risk Levels</option>
                    <option value="low">Low Risk</option>
                    <option value="medium">Medium Risk</option>
                    <option value="high">High Risk</option>
                    <option value="critical">Critical Risk</option>
                </select>
                <select id="amount-filter" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="">All Amounts</option>
                    <option value="0-1000">Under <?php echo $currency_symbol; ?> 1,000</option>
                    <option value="1000-5000"><?php echo $currency_symbol; ?> 1,000 - 5,000</option>
                    <option value="5000-10000"><?php echo $currency_symbol; ?> 5,000 - 10,000</option>
                    <option value="10000+">Over <?php echo $currency_symbol; ?> 10,000</option>
                </select>
                <select id="customer-type-filter" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="">All Customers</option>
                    <option value="walkin">Walk-in</option>
                    <option value="registered">Registered</option>
                    <option value="frequent">Frequent (5+ purchases)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Bulk Return Panel -->
    <div id="bulk-return-panel" class="hidden bg-amber-500/10 border border-amber-500/30 rounded-xl p-3">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <i class="fas fa-layer-group text-amber-400 text-sm"></i>
                <span class="text-sm font-medium text-slate-300">Bulk Return Mode</span>
                <span id="bulk-selected-count" class="text-amber-400 font-bold">0</span>
                <span class="text-slate-400 text-sm">sale(s) selected</span>
            </div>
            <div class="flex gap-2">
                <button onclick="processBulkReturns()" id="bulk-process-btn" class="px-3 py-1.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>Process Selected</button>
                <button onclick="clearBulkSelection()" class="px-3 py-1.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">Clear</button>
                <button onclick="toggleBulkReturnMode()" class="px-3 py-1.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">Exit</button>
            </div>
        </div>
    </div>

    <!-- Results Section -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden relative">
        <div class="px-3 py-2.5 border-b border-slate-700/60 flex items-center gap-2">
            <i class="fas fa-receipt text-amber-400 text-xs"></i>
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">AI-Analyzed Sales</span>
            <span class="text-xs text-slate-500 ml-auto"><i class="fas fa-microchip mr-1"></i>Risk scores calculated in real-time</span>
        </div>

        <div id="loading-overlay" class="absolute inset-0 bg-slate-900/80  flex items-center justify-center z-10 hidden">
            <div class="text-center"><div class="loading-spinner mx-auto mb-2"></div><p class="text-xs text-slate-400">AI is analyzing sales data...</p></div>
        </div>

        <!-- Desktop Table -->
        <div class="overflow-x-auto desktop-only">
            <table class="w-full min-w-[860px]" id="sales-table">
                <thead>
                    <tr class="border-b border-slate-700/60 bg-slate-800/60">
                        <th class="w-6 px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider"><input type="checkbox" id="bulk-select-all" class="hidden"></th>
                        <th class="w-6 px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider"></th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Invoice</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Date / Time</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Items</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Cashier</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">AI Risk</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="sales-tbody" class="divide-y divide-slate-700/40">
                    <tr><td colspan="11" class="text-center py-8 text-slate-500 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Loading sales...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div class="mobile-only p-3" id="mobile-cards">
            <div class="text-center py-6 text-slate-500 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Loading sales...</div>
        </div>

        <div id="pagination-wrap" class="hidden px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-2">
                <div id="pagination-info" class="text-xs text-slate-500"></div>
                <div id="pagination-buttons" class="flex gap-1"></div>
            </div>
        </div>
    </div>

    <!-- Two Column Footer -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <?php if (!empty($top_returned_products)): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-3 py-2.5 border-b border-slate-700/60 flex items-center gap-2">
                <i class="fas fa-chart-bar text-amber-400 text-xs"></i>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Most Returned Products</span>
                <span class="text-xs text-slate-500 ml-auto">Last 30 days</span>
            </div>
            <div class="p-3 space-y-2">
                <?php $maxCount = !empty($top_returned_products) ? $top_returned_products[0]['return_count'] : 1; ?>
                <?php foreach ($top_returned_products as $product): ?>
                <div>
                    <div class="flex justify-between text-xs mb-0.5">
                        <span class="text-slate-300 truncate"><?php echo htmlspecialchars($product['name']); ?></span>
                        <span class="text-slate-500"><?php echo (int) ($product['return_count'] ?? 0); ?> returns (<?php echo $currency_symbol . ' ' . number_format((float) ($product['total_value'] ?? 0), 0); ?>)</span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill" style="width: <?php echo ($product['return_count'] / max(1, $maxCount)) * 100; ?>%"></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($return_reasons)): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-3 py-2.5 border-b border-slate-700/60 flex items-center gap-2">
                <i class="fas fa-question-circle text-amber-400 text-xs"></i>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Return Reasons Analysis</span>
            </div>
            <div class="p-3 grid grid-cols-2 gap-2">
                <?php foreach ($return_reasons as $reason): ?>
                <div class="bg-slate-800/60 rounded-xl p-2 text-center">
                    <div class="text-xs font-semibold text-amber-400"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $reason['reason']))); ?></div>
                    <div class="text-xs text-slate-500 mt-0.5"><?php echo $reason['count']; ?> returns</div>
                    <div class="text-xs text-slate-600"><?php echo $currency_symbol . ' ' . number_format((float) ($reason['total_amount'] ?? 0), 0); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Returns -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="px-3 py-2.5 border-b border-slate-700/60 flex items-center gap-2">
            <i class="fas fa-bell text-amber-400 text-xs"></i>
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Recent Returns &amp; Alerts</span>
            <span class="text-xs text-slate-500 ml-auto"><i class="fas fa-clock mr-1"></i>Last 5 returns</span>
        </div>
        <div id="recent-returns-list" class="divide-y divide-slate-700/40">
            <?php if (empty($recent_returns)): ?>
                <div class="p-4 text-center text-sm text-slate-500">No recent returns</div>
            <?php else: ?>
                <?php foreach ($recent_returns as $ret): ?>
                <a href="view_sell_return.php?id=<?php echo $ret['id']; ?>" class="flex items-center gap-3 px-3 py-2.5 hover:bg-slate-700/30 transition-colors <?php echo $ret['high_value'] ? 'bg-red-500/5' : ''; ?>">
                    <div class="w-8 h-8 rounded-lg <?php echo $ret['high_value'] ? 'bg-red-500/20' : 'bg-amber-500/10'; ?> flex items-center justify-center shrink-0">
                        <i class="fas fa-undo-alt <?php echo $ret['high_value'] ? 'text-red-400' : 'text-amber-400'; ?> text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-sm font-semibold text-white">#<?php echo htmlspecialchars($ret['return_number']); ?></span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $ret['status'] === 'completed' ? 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30' : ($ret['status'] === 'pending' ? 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30' : 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30'); ?>">
                                <?php echo ucfirst($ret['status']); ?>
                            </span>
                            <?php if ($ret['high_value']): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/20 text-red-400">
                                <i class="fas fa-exclamation-triangle mr-1"></i>High Value
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-slate-500 truncate mt-0.5"><?php echo htmlspecialchars($ret['customer_name'] ?? 'Walk-in'); ?> &bull; Inv: <?php echo htmlspecialchars($ret['invoice_number'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-sm font-bold <?php echo $ret['high_value'] ? 'text-red-400' : 'text-amber-400'; ?>"><?php echo $currency_symbol . ' ' . $ret['amount_formatted']; ?></div>
                        <div class="text-xs text-slate-500"><?php echo $ret['date_formatted']; ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Saved Filters Modal -->
<div id="savedFiltersModal" class="fixed inset-0 bg-black/80  flex items-center justify-center z-[9999] hidden">
    <div class="bg-slate-800 rounded-xl max-w-md w-full mx-4 border border-slate-700 shadow-2xl">
        <div class="px-4 py-3 border-b border-slate-700 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <i class="fas fa-save text-amber-400"></i>
                <h3 class="text-base font-semibold text-white">Save Current Filter</h3>
            </div>
            <button onclick="closeSavedFiltersModal()" class="text-slate-400 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-4 space-y-3">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Filter Name</label>
                <input type="text" id="filter-name" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="e.g., High Value Returns Last Week">
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="filter-default" class="rounded border-slate-600 bg-slate-900 text-amber-500 focus:ring-amber-500">
                <span class="text-sm text-slate-400">Set as default filter</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="filter-public" class="rounded border-slate-600 bg-slate-900 text-amber-500 focus:ring-amber-500">
                <span class="text-sm text-slate-400">Share with team (public)</span>
            </label>
        </div>
        <div class="px-4 py-3 border-t border-slate-700 flex justify-end gap-2">
            <button onclick="closeSavedFiltersModal()" class="px-3 py-1.5 bg-slate-700 border border-slate-600 rounded-lg text-sm text-slate-300 hover:bg-slate-600 transition-colors">Cancel</button>
            <button onclick="saveCurrentFilter()" class="px-3 py-1.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">Save Filter</button>
        </div>
    </div>
</div>

<!-- Analytics Modal -->
<div id="analyticsModal" class="fixed inset-0 bg-black/80  flex items-center justify-center z-[9999] hidden">
    <div class="bg-slate-800 rounded-xl max-w-4xl w-full mx-4 border border-slate-700 shadow-2xl">
        <div class="px-4 py-3 border-b border-slate-700 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <i class="fas fa-chart-line text-amber-400"></i>
                <h3 class="text-base font-semibold text-white">Return Analytics Dashboard</h3>
            </div>
            <button onclick="closeAnalyticsModal()" class="text-slate-400 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-slate-900/60 rounded-xl p-3 text-center border border-slate-700/60">
                    <div class="text-xl font-bold text-amber-400"><?php echo (int) ($return_stats['total_returns'] ?? 0); ?></div>
                    <div class="text-xs text-slate-500 mt-0.5">Total Returns</div>
                </div>
                <div class="bg-slate-900/60 rounded-xl p-3 text-center border border-slate-700/60">
                    <div class="text-xl font-bold text-emerald-400"><?php echo $currency_symbol . ' ' . number_format((float) ($return_stats['total_amount'] ?? 0), 0); ?></div>
                    <div class="text-xs text-slate-500 mt-0.5">Total Value</div>
                </div>
                <div class="bg-slate-900/60 rounded-xl p-3 text-center border border-slate-700/60">
                    <div class="text-xl font-bold text-purple-400"><?php echo $return_stats['pending']; ?></div>
                    <div class="text-xs text-slate-500 mt-0.5">Pending Approval</div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <canvas id="return-chart-count" height="180"></canvas>
                    <p class="text-center text-xs text-slate-500 mt-2">Returns by day (last 30 days)</p>
                </div>
                <div>
                    <canvas id="return-chart-amount" height="180"></canvas>
                    <p class="text-center text-xs text-slate-500 mt-2">Return value by day (<?php echo $currency_symbol; ?>)</p>
                </div>
            </div>
        </div>
        <div class="px-4 py-3 border-t border-slate-700 flex justify-end">
            <button onclick="closeAnalyticsModal()" class="px-3 py-1.5 bg-slate-700 border border-slate-600 rounded-lg text-sm text-slate-300 hover:bg-slate-600 transition-colors">Close</button>
        </div>
    </div>
</div>

<script>
// PHP to JavaScript variables
const API_BASE = '../ajax/';
const CURRENCY = '<?php echo $currency_symbol; ?>';
const ACTIVE_BRANCH_ID = <?php echo (int)$selected_branch_id; ?>;
const CAN_SWITCH_BRANCH = <?php echo $can_switch_branch ? 'true' : 'false'; ?>;
const CHART_DATES = <?php echo json_encode($chart_dates); ?>;
const CHART_COUNTS = <?php echo json_encode($chart_counts); ?>;
const CHART_AMOUNTS = <?php echo json_encode($chart_amounts); ?>;
const USER_APPROVAL_LIMIT = <?php echo $user_approval_limit; ?>;
const HIGH_VALUE_THRESHOLD = <?php echo $high_value_threshold; ?>;

let currentPage = 1, currentLimit = 10, bulkMode = false, selectedBulkSales = new Map();
let returnChartCount = null, returnChartAmount = null, advancedFiltersVisible = false;

function getBranchId() {
    if (!CAN_SWITCH_BRANCH) return ACTIVE_BRANCH_ID;
    const branchFilter = document.getElementById('branch-filter');
    return (branchFilter && branchFilter.value) ? parseInt(branchFilter.value, 10) || ACTIVE_BRANCH_ID : ACTIVE_BRANCH_ID;
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const colors = { success: 'bg-green-500/90', error: 'bg-red-500/90', warning: 'bg-amber-500/90', info: 'bg-blue-500/90' };
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    const toast = document.createElement('div');
    toast.className = `toast-notification px-3 py-1.5 rounded-lg shadow-lg text-sm ${colors[type]} text-white flex items-center gap-1.5 mb-1`;
    toast.innerHTML = `<i class="fas ${icons[type]}"></i> ${message}`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

function init() {
    loadSales(1);
    loadRecentReturns();
    const ctxCount = document.getElementById('return-chart-count');
    if (ctxCount) returnChartCount = new Chart(ctxCount, { type: 'line', data: { labels: CHART_DATES, datasets: [{ label: 'Number of Returns', data: CHART_COUNTS, borderColor: '#fbbf24', backgroundColor: 'rgba(251,191,36,0.1)', fill: true, tension: 0.3, pointRadius: 2 }] }, options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { labels: { color: '#94a3b8', font: { size: 10 } } } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.1)' } }, x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } }, grid: { color: 'rgba(148,163,184,0.1)' } } } } });
    const ctxAmount = document.getElementById('return-chart-amount');
    if (ctxAmount) returnChartAmount = new Chart(ctxAmount, { type: 'bar', data: { labels: CHART_DATES, datasets: [{ label: 'Return Value (' + CURRENCY + ')', data: CHART_AMOUNTS, backgroundColor: 'rgba(251,191,36,0.5)', borderColor: '#fbbf24', borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { labels: { color: '#94a3b8', font: { size: 10 } } } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.1)' }, ticks: { callback: v => CURRENCY + ' ' + v.toLocaleString(), font: { size: 9 } } }, x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } }, grid: { color: 'rgba(148,163,184,0.1)' } } } } });
    document.getElementById('sale-search')?.addEventListener('input', () => loadSales(1));
    document.getElementById('barcode-input')?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); scanSale(); } });
    document.getElementById('risk-filter')?.addEventListener('change', () => loadSales(1));
    document.getElementById('amount-filter')?.addEventListener('change', () => loadSales(1));
    document.getElementById('customer-type-filter')?.addEventListener('change', () => loadSales(1));
    setTimeout(() => document.getElementById('barcode-input')?.focus(), 300);
}

function toggleAdvancedFilters() {
    advancedFiltersVisible = !advancedFiltersVisible;
    const filters = document.getElementById('advanced-filters');
    const icon = document.getElementById('advanced-toggle-icon');
    if (filters) filters.classList.toggle('hidden');
    if (icon) icon.classList.toggle('fa-chevron-down', !advancedFiltersVisible);
    if (icon) icon.classList.toggle('fa-chevron-up', advancedFiltersVisible);
}

function setDateRange(btn, range) {
    const today = new Date(), fmt = d => d.toISOString().split('T')[0];
    let from = '', to = fmt(today);
    switch(range) {
        case 'today': from = to; break;
        case 'yesterday': const y = new Date(today); y.setDate(y.getDate()-1); from = fmt(y); to = fmt(y); break;
        case '7days': const d7 = new Date(today); d7.setDate(d7.getDate()-7); from = fmt(d7); break;
        case '30days': const d30 = new Date(today); d30.setDate(d30.getDate()-30); from = fmt(d30); break;
        case 'custom': from = document.getElementById('date-from').value; to = document.getElementById('date-to').value; break;
    }
    document.getElementById('date-from').value = from;
    document.getElementById('date-to').value = to;
    document.querySelectorAll('.date-pill').forEach(p => p.classList.remove('active', 'bg-amber-500/20', 'text-amber-400', 'border-amber-500/30'));
    document.querySelectorAll('.date-pill').forEach(p => p.classList.add('bg-slate-800', 'text-slate-400', 'border-slate-700'));
    if (btn) { btn.classList.remove('bg-slate-800', 'text-slate-400', 'border-slate-700'); btn.classList.add('active', 'bg-amber-500/20', 'text-amber-400', 'border-amber-500/30'); }
    if (range !== 'custom') loadSales(1);
}

function resetFilters() {
    document.getElementById('sale-search').value = '';
    document.getElementById('risk-filter').value = '';
    document.getElementById('amount-filter').value = '';
    document.getElementById('customer-type-filter').value = '';
    setDateRange(null, '30days');
    loadSales(1);
    showToast('Filters reset', 'info');
}

function getRiskBadgeHtml(risk) {
    const map = { low: 'risk-low', medium: 'risk-medium', high: 'risk-high', critical: 'risk-critical' };
    const cls = map[risk] || 'risk-low';
    const text = (risk || 'Low').charAt(0).toUpperCase() + (risk || 'low').slice(1);
    return `<span class="${cls} px-1.5 py-0.5 rounded-full text-xs font-medium">${text}</span>`;
}

function toggleBulkReturnMode() {
    bulkMode = !bulkMode;
    const panel = document.getElementById('bulk-return-panel');
    const checkboxes = document.querySelectorAll('.bulk-checkbox');
    const bulkBtn = document.getElementById('bulk-mode-btn');
    if (bulkMode) {
        panel.classList.remove('hidden');
        checkboxes.forEach(cb => cb.classList.remove('hidden'));
        if (bulkBtn) { bulkBtn.classList.add('bg-amber-500/20', 'text-amber-400', 'border-amber-500/30'); bulkBtn.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Exit Bulk Mode'; }
        showToast('Bulk mode activated. Select multiple sales to process returns.', 'info');
    } else {
        panel.classList.add('hidden');
        checkboxes.forEach(cb => { cb.classList.add('hidden'); cb.checked = false; });
        selectedBulkSales.clear();
        updateBulkSelectionCount();
        if (bulkBtn) { bulkBtn.classList.remove('bg-amber-500/20', 'text-amber-400', 'border-amber-500/30'); bulkBtn.innerHTML = '<i class="fas fa-layer-group mr-1"></i> Bulk Mode'; }
    }
}

function updateBulkSelectionCount() {
    const count = selectedBulkSales.size;
    let total = 0;
    for (const amt of selectedBulkSales.values()) total += amt;
    document.getElementById('bulk-selected-count').textContent = count;
    document.getElementById('bulk-process-btn').disabled = count === 0;
}

function clearBulkSelection() {
    selectedBulkSales.clear();
    document.querySelectorAll('.bulk-checkbox').forEach(cb => cb.checked = false);
    updateBulkSelectionCount();
    showToast('Bulk selection cleared', 'info');
}

function processBulkReturns() {
    if (selectedBulkSales.size === 0) { showToast('Please select at least one sale.', 'warning'); return; }
    let totalAmount = 0;
    for (const amount of selectedBulkSales.values()) totalAmount += amount;
    if (totalAmount > USER_APPROVAL_LIMIT && !confirm(`⚠️ Bulk return total (${CURRENCY} ${totalAmount.toLocaleString()}) exceeds your approval limit (${CURRENCY} ${USER_APPROVAL_LIMIT.toLocaleString()}). Continue?`)) return;
    window.location.href = 'process_bulk_return.php?sale_ids=' + Array.from(selectedBulkSales.keys()).join(',') + '&branch_id=' + getBranchId();
}

function showLoading(show) {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.classList.toggle('hidden', !show);
}

function loadSales(page) {
    currentPage = page;
    showLoading(true);
    const search = document.getElementById('sale-search')?.value.trim() || '';
    const dateFrom = document.getElementById('date-from')?.value || '';
    const dateTo = document.getElementById('date-to')?.value || '';
    const risk = document.getElementById('risk-filter')?.value || '';
    const amount = document.getElementById('amount-filter')?.value || '';
    const customer = document.getElementById('customer-type-filter')?.value || '';
    fetch(`${API_BASE}search_sales_for_return.php?page=${page}&limit=${currentLimit}&search=${encodeURIComponent(search)}&date_from=${dateFrom}&date_to=${dateTo}&branch_id=${getBranchId()}&risk=${risk}&amount=${amount}&customer_type=${customer}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) { renderError(data.error || 'Failed to load sales'); showLoading(false); return; }
            renderSales(data.sales || [], data.total || 0, data.page || 1, data.total_pages || 1);
            showLoading(false);
        })
        .catch(err => { console.error(err); renderError('Network error. Please try again.'); showLoading(false); });
}

function renderSales(sales, total, page, totalPages) {
    const tbody = document.getElementById('sales-tbody');
    const mobile = document.getElementById('mobile-cards');
    if (!sales || !sales.length) {
        const empty = '<tr><td colspan="11" class="text-center py-10 text-slate-500 text-sm"><i class="fas fa-receipt text-2xl mb-2 block opacity-40"></i>No eligible sales found</td></tr>';
        if (tbody) tbody.innerHTML = empty;
        if (mobile) mobile.innerHTML = '<div class="text-center py-8 text-slate-500 text-sm"><i class="fas fa-receipt text-2xl mb-2 block opacity-40"></i>No eligible sales found</div>';
        document.getElementById('pagination-wrap').classList.add('hidden');
        return;
    }
    let tableHtml = '', mobileHtml = '';
    for (const s of sales) {
        const riskBadge = getRiskBadgeHtml(s.risk_level);
        const statusBadge = s.return_status === 'none'
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30">No Return</span>'
            : (s.return_status === 'partial'
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30">Partial</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30">Returned</span>');
        const bulkCheckbox = bulkMode ? `<input type="checkbox" class="bulk-checkbox" data-sale-id="${s.id}" data-amount="${s.total_value}" onchange="toggleBulkSale(${s.id}, ${s.total_value}, this.checked)">` : '';
        const rowClass = s.risk_level === 'critical' ? 'bg-red-900/20' : (s.risk_level === 'high' ? 'bg-red-900/10' : '');
        tableHtml += `<tr class="${rowClass} hover:bg-slate-700/30 transition-colors">
            <td class="px-3 py-2.5">${bulkCheckbox}</td>
            <td class="px-3 py-2.5"><button onclick="toggleExpand(${s.id})" class="expand-toggle text-slate-500 hover:text-amber-400" id="toggle-${s.id}"><i class="fas fa-chevron-down text-xs"></i></button></td>
            <td class="px-3 py-2.5"><span class="font-mono text-sm font-semibold text-amber-400">${escapeHtml(s.invoice_number)}</span></td>
            <td class="px-3 py-2.5"><div class="text-sm text-slate-300">${s.date_formatted}</div><div class="text-xs text-slate-500">${s.time_formatted}</div></td>
            <td class="px-3 py-2.5"><div class="text-sm text-white">${escapeHtml(s.customer_name || 'Walk-in')}</div>${s.customer_phone ? `<div class="text-xs text-slate-500">${escapeHtml(s.customer_phone)}</div>` : ''}</td>
            <td class="text-center px-3 py-2.5"><span class="text-xs text-blue-400">${s.item_count} items</span></td>
            <td class="text-right px-3 py-2.5"><span class="text-sm font-bold ${s.total_value > HIGH_VALUE_THRESHOLD ? 'text-red-400' : 'text-amber-400'}">${CURRENCY} ${s.total_formatted}</span></td>
            <td class="px-3 py-2.5 text-sm text-slate-400">${escapeHtml(s.cashier_name || '—')}</td>
            <td class="text-center px-3 py-2.5">${riskBadge}</td>
            <td class="text-center px-3 py-2.5">${statusBadge}</td>
            <td class="px-3 py-2.5"><div class="flex items-center justify-center"><a href="process_return.php?sale_id=${s.id}&branch_id=${getBranchId()}" class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-700/60 text-orange-400 hover:bg-orange-500/20 hover:text-orange-300 transition-colors" title="Process Return"><i class="fas fa-undo-alt text-xs"></i></a></div></td>
        </tr><tr class="expand-row" id="expand-${s.id}"><td colspan="11"><div class="expand-content p-3 bg-slate-900/50" id="expand-content-${s.id}"><div class="flex items-center gap-2 text-slate-400 text-sm"><div class="loading-spinner" style="width:16px;height:16px;"></div>Loading items...</div></div></tr>`;
        mobileHtml += `<div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 mb-2 ${s.risk_level === 'critical' ? 'border-red-500/40' : ''}">
            <div class="flex justify-between items-start mb-2"><div><span class="font-mono text-sm font-semibold text-amber-400">${escapeHtml(s.invoice_number)}</span><div class="text-xs text-slate-500 mt-0.5">${s.date_formatted} ${s.time_formatted}</div></div>${riskBadge}</div>
            <div class="text-sm font-medium text-white mb-1">${escapeHtml(s.customer_name || 'Walk-in')}</div>
            <div class="flex justify-between items-center mb-2"><span class="text-xs text-blue-400">${s.item_count} items</span><span class="text-sm font-bold text-amber-400">${CURRENCY} ${s.total_formatted}</span></div>
            <div class="flex gap-2"><button onclick="toggleExpand(${s.id})" class="flex-1 py-1.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">Preview</button><a href="process_return.php?sale_id=${s.id}&branch_id=${getBranchId()}" class="flex-1 py-1.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium text-center hover:bg-amber-500/25 transition-colors">Process</a></div>
            <div class="hidden mt-2" id="mobile-expand-${s.id}"><div class="bg-slate-900 rounded-lg p-2 text-xs border border-slate-700/60" id="mobile-expand-content-${s.id}"><div class="loading-spinner" style="width:12px;height:12px;"></div></div></div>
        </div>`;
    }
    if (tbody) tbody.innerHTML = tableHtml;
    if (mobile) mobile.innerHTML = mobileHtml;
    const totalPagesCalc = Math.ceil(total / currentLimit);
    const wrap = document.getElementById('pagination-wrap');
    if (wrap) {
        if (totalPagesCalc <= 1) { wrap.classList.add('hidden'); return; }
        wrap.classList.remove('hidden');
        const offset = (page - 1) * currentLimit;
        document.getElementById('pagination-info').textContent = `Showing ${offset+1}–${Math.min(offset+currentLimit, total)} of ${total} sales`;
        let btnHtml = '';
        if (page > 1) btnHtml += `<button onclick="loadSales(${page-1})" class="px-2.5 py-1 bg-slate-700 border border-slate-600 rounded-lg text-sm text-slate-400 hover:bg-slate-600 transition-colors">« Prev</button>`;
        for (let p = Math.max(1, page-2); p <= Math.min(totalPagesCalc, page+2); p++) {
            btnHtml += `<button onclick="loadSales(${p})" class="px-2.5 py-1 rounded-lg text-sm border ${p === page ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' : 'bg-slate-700 border-slate-600 text-slate-400 hover:bg-slate-600 transition-colors'}">${p}</button>`;
        }
        if (page < totalPagesCalc) btnHtml += `<button onclick="loadSales(${page+1})" class="px-2.5 py-1 bg-slate-700 border border-slate-600 rounded-lg text-sm text-slate-400 hover:bg-slate-600 transition-colors">Next »</button>`;
        document.getElementById('pagination-buttons').innerHTML = btnHtml;
    }
}

function toggleBulkSale(id, amount, checked) {
    if (checked) selectedBulkSales.set(id, amount);
    else selectedBulkSales.delete(id);
    updateBulkSelectionCount();
}

function renderError(msg) {
    const tbody = document.getElementById('sales-tbody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="11" class="text-center py-8 text-red-400 text-sm">${escapeHtml(msg)}</td></tr>`;
    document.getElementById('pagination-wrap').style.display = 'none';
}

async function toggleExpand(saleId) {
    const row = document.getElementById(`expand-${saleId}`);
    const toggle = document.getElementById(`toggle-${saleId}`);
    const content = document.getElementById(`expand-content-${saleId}`);
    const mobileExpand = document.getElementById(`mobile-expand-${saleId}`);
    const mobileContent = document.getElementById(`mobile-expand-content-${saleId}`);
    const isOpen = row && row.classList.contains('open');
    document.querySelectorAll('.expand-row.open').forEach(r => r.classList.remove('open'));
    document.querySelectorAll('.expand-toggle.rotated').forEach(t => t.classList.remove('rotated'));
    document.querySelectorAll('[id^="mobile-expand-"]').forEach(el => { if(el) el.classList.add('hidden'); });
    if (isOpen) return;
    if (row) row.classList.add('open');
    if (toggle) toggle.classList.add('rotated');
    if (mobileExpand) mobileExpand.classList.remove('hidden');
    if (content && !content.dataset.loaded) {
        try {
            const res = await fetch(`${API_BASE}get_sale_items.php?sale_id=${saleId}`);
            const data = await res.json();
            if (data.success && data.items) {
                let itemsHtml = '<div class="overflow-x-auto"><table class="w-full text-xs"><thead><tr class="text-slate-500 border-b border-slate-700"><th class="text-left py-1.5 px-1">Product</th><th class="text-center py-1.5">Qty</th><th class="text-center py-1.5">Price</th><th class="text-right py-1.5 px-1">Subtotal</th></tr></thead><tbody>';
                for (const it of data.items) itemsHtml += `<tr class="border-b border-slate-700/40"><td class="py-1.5 px-1 text-slate-300">${escapeHtml(it.product_name)}</td><td class="text-center text-slate-300">${it.quantity}</td><td class="text-center text-slate-300">${it.unit_price_formatted}</td><td class="text-right px-1 text-amber-400 font-semibold">${it.subtotal_formatted}</td></tr>`;
                itemsHtml += '</tbody></table></div><div class="text-xs text-slate-500 mt-2"><i class="fas fa-info-circle mr-1"></i>Available = Sold - Already Returned</div>';
                content.innerHTML = itemsHtml;
                content.dataset.loaded = '1';
                if (mobileContent) mobileContent.innerHTML = itemsHtml;
            } else content.innerHTML = '<p class="text-red-400 text-xs">Failed to load items</p>';
        } catch (err) { content.innerHTML = '<p class="text-red-400 text-xs">Network error</p>'; }
    }
}

async function scanSale() {
    const input = document.getElementById('barcode-input'), term = input?.value.trim();
    if (!term) return;
    const resultEl = document.getElementById('scan-result');
    if (resultEl) { resultEl.classList.remove('hidden'); resultEl.innerHTML = '<span class="text-slate-400 text-xs"><i class="fas fa-spinner fa-spin mr-1"></i> AI is searching...</span>'; }
    try {
        const res = await fetch(`${API_BASE}lookup_sale.php?term=${encodeURIComponent(term)}`);
        const data = await res.json();
        if (!resultEl) return;
        if (!data.success) { resultEl.innerHTML = `<span class="text-red-400 text-xs"><i class="fas fa-times-circle mr-1"></i> ${escapeHtml(data.error || 'Not found')}</span>`; return; }
        if (data.multiple) { resultEl.innerHTML = `<span class="text-amber-400 text-xs"><i class="fas fa-list mr-1"></i> ${data.sales.length} matches found. Showing results below.</span>`; document.getElementById('sale-search').value = term; loadSales(1); }
        else { resultEl.innerHTML = '<span class="text-green-400 text-xs"><i class="fas fa-check-circle mr-1"></i> Sale found! Redirecting...</span>'; if (input) input.classList.add('scan-highlight'); setTimeout(() => window.location.href = `process_return.php?sale_id=${data.sale.id}&branch_id=${getBranchId()}`, 600); }
    } catch (err) { if (resultEl) resultEl.innerHTML = '<span class="text-red-400 text-xs"><i class="fas fa-times-circle mr-1"></i> Network error</span>'; }
}

async function loadRecentReturns() {
    try {
        const res = await fetch(`${API_BASE}get_recent_returns.php?branch_id=${getBranchId()}`);
        const data = await res.json();
        const container = document.getElementById('recent-returns-list');
        if (!container) return;
        if (!data.success || !data.returns || !data.returns.length) { container.innerHTML = '<div class="p-4 text-center text-sm text-slate-500">No recent returns</div>'; return; }
        let html = '';
        for (const r of data.returns) {
            const statusClass = r.status === 'pending' ? 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30' : (r.status === 'completed' ? 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30' : 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30');
            html += `<a href="view_sell_return.php?id=${r.id}" class="flex items-center gap-3 px-3 py-2.5 hover:bg-slate-700/30 transition-colors ${r.high_value ? 'bg-red-500/5' : ''}">
                <div class="w-8 h-8 rounded-lg ${r.high_value ? 'bg-red-500/20' : 'bg-amber-500/10'} flex items-center justify-center shrink-0"><i class="fas fa-undo-alt ${r.high_value ? 'text-red-400' : 'text-amber-400'} text-xs"></i></div>
                <div class="flex-1 min-w-0"><div class="flex items-center gap-1.5 flex-wrap"><span class="text-sm font-semibold text-white">#${escapeHtml(r.return_number)}</span><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusClass}">${escapeHtml(r.status)}</span>${r.high_value ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/20 text-red-400"><i class="fas fa-exclamation-triangle mr-1"></i>High Value</span>' : ''}</div>
                <div class="text-xs text-slate-500 truncate mt-0.5">${r.customer_name || 'Walk-in'} &bull; ${r.invoice_number || 'N/A'}</div></div>
                <div class="text-right shrink-0"><div class="text-sm font-bold ${r.high_value ? 'text-red-400' : 'text-amber-400'}">${CURRENCY} ${r.amount_formatted}</div><div class="text-xs text-slate-500">${r.date_formatted}</div></div>
            </a>`;
        }
        container.innerHTML = html;
    } catch (err) { console.error(err); }
}

function saveCurrentFilter() {
    const name = document.getElementById('filter-name')?.value.trim();
    if (!name) { showToast('Please enter a filter name', 'warning'); return; }
    fetch(`${API_BASE}save_filter.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, config: { search: document.getElementById('sale-search')?.value || '', date_from: document.getElementById('date-from')?.value || '', date_to: document.getElementById('date-to')?.value || '', risk: document.getElementById('risk-filter')?.value || '', amount: document.getElementById('amount-filter')?.value || '', customer_type: document.getElementById('customer-type-filter')?.value || '' }, is_default: document.getElementById('filter-default')?.checked || false, is_public: document.getElementById('filter-public')?.checked || false, branch_id: getBranchId() })
    }).then(r => r.json()).then(data => { if (data.success) { showToast('Filter saved successfully', 'success'); closeSavedFiltersModal(); setTimeout(() => location.reload(), 1000); } else showToast(data.error || 'Failed to save filter', 'error'); }).catch(() => showToast('Network error', 'error'));
}

function loadSavedFilter(id) { if (!id) return; fetch(`${API_BASE}load_filter.php?id=${id}`).then(r=>r.json()).then(data=>{if(data.success&&data.config){ document.getElementById('sale-search').value=data.config.search||''; document.getElementById('date-from').value=data.config.date_from||''; document.getElementById('date-to').value=data.config.date_to||''; document.getElementById('risk-filter').value=data.config.risk||''; document.getElementById('amount-filter').value=data.config.amount||''; document.getElementById('customer-type-filter').value=data.config.customer_type||''; loadSales(1); showToast(`Loaded filter: ${data.name}`,'success'); } else showToast('Failed to load filter','error'); }).catch(()=>showToast('Network error','error')); }

function openSavedFiltersModal() { const modal = document.getElementById('savedFiltersModal'); if(modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); } }
function closeSavedFiltersModal() { const modal = document.getElementById('savedFiltersModal'); if(modal) modal.classList.add('hidden'); }
function openAnalyticsModal() { const modal = document.getElementById('analyticsModal'); if(modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); } if(returnChartCount) returnChartCount.update(); if(returnChartAmount) returnChartAmount.update(); }
function closeAnalyticsModal() { const modal = document.getElementById('analyticsModal'); if(modal) modal.classList.add('hidden'); }

function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m])); }

// Keyboard shortcuts
document.addEventListener('keydown', e => {
    if (e.target?.matches('input, textarea, select')) return;
    if (e.ctrlKey && e.key === 'f') { e.preventDefault(); document.getElementById('sale-search')?.focus(); }
    if (e.ctrlKey && e.key === 'n') { e.preventDefault(); window.location.href = `process_return.php?branch_id=${getBranchId()}`; }
    if (e.ctrlKey && e.key === 'b') { e.preventDefault(); toggleBulkReturnMode(); }
    if (e.ctrlKey && e.key === 'a') { e.preventDefault(); openAnalyticsModal(); }
    if (e.key === 'Escape') { if (bulkMode) toggleBulkReturnMode(); document.querySelectorAll('.expand-row.open').forEach(r=>r.classList.remove('open')); document.querySelectorAll('.expand-toggle.rotated').forEach(t=>t.classList.remove('rotated')); }
});

init();
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>