<?php
/**
 * Comprehensive Reports & Analytics Hub for Jakababa POS - PURE TAILWIND CSS
 * 
 * Unified dashboard integrating:
 * - Sales Report
 * - Profit & Loss  
 * - Custom Report Builder
 * - Inventory Report
 * - Customer Report
 * - Tax Report
 */

$page_title = 'Reports & Analytics';
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
require_once __DIR__ . '/../../src/Security/SecurityBootstrap.php';
SecurityBootstrap::initialize();

if (!check_permission('reports.view') && !is_super_admin()) {
    enforce_permission('reports.view');
}

$pdo = get_db_connection();

// Get current user info from session
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();
$current_branch_id = get_current_branch_id();
$current_branch_name = get_current_branch_name();
$user_name = get_current_user_name();
$user_role = $_SESSION['role'] ?? 'cashier';
$is_super_admin = is_super_admin();

// Business type detection
$business_type = get_current_business_type($tenant_id);
$bt_config = get_business_type_config($business_type) ?? [];
$bt_name = $bt_config['name'] ?? 'Retail';
$bt_sale_label = $bt_config['sale_label'] ?? 'Sale';
$bt_sales_label = $bt_config['sales_label'] ?? 'Sales';
$bt_customer_label = $bt_config['customer_label'] ?? 'Customer';
$currency = get_tenant_currency() ?: 'USD';

// Get filter parameters
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to = $_GET['to'] ?? date('Y-m-d');
$branch_filter = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : $current_branch_id;
$active_module = $_GET['module'] ?? 'sales';

// Get all branches
$branches = [];
if ($tenant_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, code, location FROM branches WHERE tenant_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY name");
        $stmt->execute([$tenant_id]);
        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching branches: " . $e->getMessage());
    }
}

// Get tax rates
$tax_rates = [];
if ($tenant_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, rate, type, is_default FROM tax_rates WHERE tenant_id = ? AND active = 1 ORDER BY rate");
        $stmt->execute([$tenant_id]);
        $tax_rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching tax rates: " . $e->getMessage());
    }
}

$site_title = get_settings('site_title', $tenant_id) ?: 'JAKPOS';
$company_name = get_settings('company_name', $tenant_id) ?: 'JAKPOS';

// Chart color palette - configurable via settings or use defaults
$chartColors = get_settings('chart_colors', $tenant_id);
if (!$chartColors || !is_array($chartColors)) {
    $chartColors = [
        'primary' => '#fbbf24',      // amber - main brand color
        'secondary' => '#60a5fa',    // blue
        'success' => '#34d399',      // green
        'purple' => '#a78bfa',       // purple
        'danger' => '#f87171',       // red
        'cyan' => '#22d3ee',         // cyan
        'orange' => '#fb923c',       // orange
        'pink' => '#f472b6',         // pink
        'muted' => '#9ca3af',        // gray for text/axes
        'dark' => '#1e293b',         // dark background color
        'grid' => 'rgba(255,255,255,0.05)', // grid line color
    ];
}

// UI config
$dateLocale = get_settings('date_locale', $tenant_id) ?: 'en-US';
$toastDuration = (int) (get_settings('toast_duration_ms', $tenant_id) ?: 3000);
$chartJsVersion = defined('CHART_JS_VERSION') ? CHART_JS_VERSION : '4.4.1';
?>

<?php
$chartJsUrl = "https://cdn.jsdelivr.net/npm/chart.js@{$chartJsVersion}/dist/chart.umd.min.js";
?>
<script src="<?php echo htmlspecialchars($chartJsUrl); ?>"></script>

<script>
// Expose chart configuration to JavaScript
window.CHART_CONFIG = {
    colors: <?php echo json_encode(array_values($chartColors)); ?>,
    colorMap: <?php echo json_encode($chartColors); ?>,
    currency: '<?php echo htmlspecialchars($currency); ?>',
    locale: '<?php echo htmlspecialchars($dateLocale); ?>',
    toastDuration: <?php echo $toastDuration; ?>
};
</script>

<?php
// Get brand colors from settings or use defaults
$brandPrimary = get_settings('brand_primary', $tenant_id) ?: '#f59e0b';
$brandPrimaryLight = get_settings('brand_primary_light', $tenant_id) ?: '#fbbf24';
$brandPrimaryDark = get_settings('brand_primary_dark', $tenant_id) ?: '#d97706';
?>

<style>
    :root {
        --brand-primary: <?php echo htmlspecialchars($brandPrimary); ?>;
        --brand-primary-light: <?php echo htmlspecialchars($brandPrimaryLight); ?>;
        --brand-primary-dark: <?php echo htmlspecialchars($brandPrimaryDark); ?>;
    }
    .brand-text { color: var(--brand-primary-light) !important; }
    .brand-bg { background-color: var(--brand-primary) !important; }
    .brand-bg-light { background-color: color-mix(in srgb, var(--brand-primary-light) 10%, transparent) !important; }
    .brand-bg-medium { background-color: color-mix(in srgb, var(--brand-primary-light) 15%, transparent) !important; }
    .brand-bg-hover:hover { background-color: color-mix(in srgb, var(--brand-primary-light) 25%, transparent) !important; }
    .brand-border { border-color: var(--brand-primary-light) !important; }
    .brand-border-light { border-color: color-mix(in srgb, var(--brand-primary-light) 30%, transparent) !important; }
    .brand-ring:focus { --tw-ring-color: var(--brand-primary) !important; }
</style>

<div class="space-y-4">
    
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-400 uppercase tracking-wider mb-1">
                <i class="fas fa-chart-line text-xs"></i>
                <span>Analytics & Intelligence Hub</span>
                <span class="px-2 py-0.5 rounded-full bg-slate-700/50 text-slate-400 text-[10px] font-medium">
                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user_role))); ?>
                </span>
                <?php if ($is_super_admin): ?>
                <span class="px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-400 text-[10px] font-medium">Super Admin</span>
                <?php endif; ?>
            </div>
            <h1 class="text-lg font-bold text-white">Reports & Analytics</h1>
            <p class="text-sm text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
                <span id="header-branch-name"><?php echo htmlspecialchars($current_branch_name); ?></span>
                <span class="text-slate-700">|</span>
                <i class="far fa-calendar-alt text-amber-400 text-xs"></i>
                <span id="date-from-display"><?php echo date('d M Y', strtotime($date_from)); ?></span>
                <span>—</span>
                <span id="date-to-display"><?php echo date('d M Y', strtotime($date_to)); ?></span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <div class="relative group">
                <button onclick="toggleAutoRefresh()" id="autoRefreshBtn" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-xs hover:bg-slate-700 transition-all" title="Auto Refresh">
                    <i class="fas fa-clock text-slate-400" id="autoRefreshIcon"></i> <span id="autoRefreshLabel" class="hidden sm:inline">Auto</span>
                </button>
                <div class="absolute right-0 mt-1 w-40 bg-slate-800 border border-slate-700 rounded-lg shadow-xl hidden group-hover:block z-50 p-2">
                    <div class="text-xs text-slate-500 mb-1.5 px-1">Refresh every</div>
                    <button onclick="setAutoRefreshInterval(30)" class="w-full text-left px-2 py-1 rounded text-xs text-slate-300 hover:bg-slate-700">30 seconds</button>
                    <button onclick="setAutoRefreshInterval(60)" class="w-full text-left px-2 py-1 rounded text-xs text-slate-300 hover:bg-slate-700">1 minute</button>
                    <button onclick="setAutoRefreshInterval(300)" class="w-full text-left px-2 py-1 rounded text-xs text-slate-300 hover:bg-slate-700">5 minutes</button>
                    <button onclick="stopAutoRefresh()" class="w-full text-left px-2 py-1 rounded text-xs text-red-400 hover:bg-slate-700">Off</button>
                </div>
            </div>
            <button onclick="toggleFullscreen()" class="w-7 h-7 flex items-center justify-center bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 transition-all" title="Fullscreen">
                <i class="fas fa-expand text-purple-400 text-xs"></i>
            </button>
            <button onclick="exportCurrentModule()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-xs hover:bg-slate-700 transition-all">
                <i class="fas fa-download text-emerald-400 text-xs"></i> Export
            </button>
            <button onclick="printReport()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-xs hover:bg-slate-700 transition-all">
                <i class="fas fa-print text-blue-400 text-xs"></i> Print
            </button>
            <button onclick="refreshDashboard()" class="w-7 h-7 flex items-center justify-center bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 transition-all">
                <i class="fas fa-sync-alt text-amber-400 text-xs"></i>
            </button>
        </div>
    </div>

    <!-- Module Tabs - filtered by permissions -->
    <div class="flex flex-wrap gap-1 border-b border-slate-700 pb-0">
        <?php
        // Define tabs with their required permissions
        $tabs = [
            'sales' => ['icon' => 'fa-chart-line', 'label' => 'Sales Report', 'permission' => 'sales.view'],
            'profit_loss' => ['icon' => 'fa-chart-pie', 'label' => 'Profit & Loss', 'permission' => 'reports.advanced'],
            'inventory' => ['icon' => 'fa-boxes', 'label' => 'Inventory', 'permission' => 'inventory.view'],
            'customers' => ['icon' => 'fa-users', 'label' => 'Customers', 'permission' => 'customers.view'],
            'tax' => ['icon' => 'fa-calculator', 'label' => 'Tax Report', 'permission' => 'reports.advanced'],
            'custom' => ['icon' => 'fa-sliders-h', 'label' => 'Custom Builder', 'permission' => 'reports.advanced'],
        ];
        
        // Filter tabs based on user permissions
        $visibleTabs = [];
        foreach ($tabs as $key => $tab) {
            if (has_permission($tab['permission']) || is_super_admin()) {
                $visibleTabs[$key] = $tab;
            }
        }
        
        // If current active module is not visible, default to first visible
        if (!isset($visibleTabs[$active_module]) && !empty($visibleTabs)) {
            $active_module = array_key_first($visibleTabs);
        }
        
        foreach ($visibleTabs as $key => $tab):
            $is_active = $active_module === $key;
        ?>
        <button onclick="switchModule('<?php echo $key; ?>')" 
                id="tab-<?php echo $key; ?>"
                class="module-tab px-4 py-2.5 rounded-t-lg text-sm font-medium transition-all <?php echo $is_active ? 'bg-amber-500/10 text-amber-400 border-b-2 border-amber-400' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50'; ?>">
            <i class="fas <?php echo $tab['icon']; ?> mr-2"></i> <?php echo $tab['label']; ?>
        </button>
        <?php endforeach; ?>
        
        <?php if (empty($visibleTabs)): ?>
        <div class="px-4 py-2.5 text-sm text-slate-500">
            <i class="fas fa-lock mr-2"></i> No report modules available for your role
        </div>
        <?php endif; ?>
    </div>

    <!-- Filter Panel -->
    <div class="bg-slate-800/40  border border-slate-700 rounded-xl overflow-hidden">
        <div class="p-5">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label for="date-from-input" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">From Date</label>
                    <input type="date" name="from" id="date-from-input" value="<?php echo $date_from; ?>"
                           class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all w-36">
                </div>

                <div>
                    <label for="date-to-input" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">To Date</label>
                    <input type="date" name="to" id="date-to-input" value="<?php echo $date_to; ?>"
                           class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all w-36">
                </div>

                <?php if (!empty($branches) && (is_super_admin() || check_permission('branches.view'))): ?>
                <div>
                    <label for="branch-select" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Branch</label>
                    <select name="branch_id" id="branch-select" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all w-40">
                        <option value="0">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo $branch_filter == $branch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div>
                    <button type="button" onclick="applyFilters()" class="inline-flex items-center gap-2 px-5 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm hover:bg-amber-500/25 transition-all">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>

                <div class="flex-1 text-right text-sm text-slate-500">
                    <i class="far fa-calendar-alt mr-1 text-amber-400"></i>
                    <span id="display-date-from"><?php echo date('d M Y', strtotime($date_from)); ?></span>
                    <span>—</span>
                    <span id="display-date-to"><?php echo date('d M Y', strtotime($date_to)); ?></span>
                </div>
            </div>

            <!-- Date Range Presets -->
            <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-slate-700/50">
                <span class="text-xs text-slate-500 mr-1">Quick:</span>
                <button onclick="setDatePreset('today')" class="px-2.5 py-1 rounded-md bg-slate-900 border border-slate-700 text-slate-400 text-xs hover:border-amber-500/40 hover:text-amber-400 transition-all">Today</button>
                <button onclick="setDatePreset('yesterday')" class="px-2.5 py-1 rounded-md bg-slate-900 border border-slate-700 text-slate-400 text-xs hover:border-amber-500/40 hover:text-amber-400 transition-all">Yesterday</button>
                <button onclick="setDatePreset('this_week')" class="px-2.5 py-1 rounded-md bg-slate-900 border border-slate-700 text-slate-400 text-xs hover:border-amber-500/40 hover:text-amber-400 transition-all">This Week</button>
                <button onclick="setDatePreset('this_month')" class="px-2.5 py-1 rounded-md bg-slate-900 border border-slate-700 text-slate-400 text-xs hover:border-amber-500/40 hover:text-amber-400 transition-all">This Month</button>
                <button onclick="setDatePreset('last_month')" class="px-2.5 py-1 rounded-md bg-slate-900 border border-slate-700 text-slate-400 text-xs hover:border-amber-500/40 hover:text-amber-400 transition-all">Last Month</button>
                <button onclick="setDatePreset('this_year')" class="px-2.5 py-1 rounded-md bg-slate-900 border border-slate-700 text-slate-400 text-xs hover:border-amber-500/40 hover:text-amber-400 transition-all">This Year</button>
                <div class="flex-1"></div>
                <label class="flex items-center gap-1.5 cursor-pointer select-none">
                    <input type="checkbox" id="compareToggle" class="w-3.5 h-3.5 rounded accent-amber-500" onchange="toggleCompareMode()">
                    <span class="text-xs text-slate-400">Compare prev. period</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Table Search Bar -->
    <div class="flex items-center gap-3">
        <div class="relative flex-1 max-w-sm">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="text" id="tableSearch" placeholder="Search report data..." onkeyup="filterReportTable(this.value)"
                   class="w-full pl-8 pr-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all">
        </div>
        <span id="reportRowCount" class="text-xs text-slate-500"></span>
    </div>

    <!-- Module Content Area -->
    <div id="module-content" class="space-y-4">
        <div class="text-center py-16">
            <div class="inline-block">
                <div class="w-12 h-12 border-3 border-slate-700 border-t-amber-400 rounded-full animate-spin mx-auto"></div>
            </div>
            <p class="text-slate-500 mt-4">Loading report module...</p>
            <p class="text-xs text-slate-600 mt-1">Please wait while we fetch your data</p>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

    <!-- Report Footer -->
    <div class="text-center text-xs text-slate-500 pt-6 border-t border-slate-700">
        <p><i class="fas fa-chart-line mr-1 text-amber-400"></i> Generated on <?php echo date('d M Y H:i:s'); ?> by <?php echo htmlspecialchars($user_name); ?></p>
        <p class="mt-1"><?php echo htmlspecialchars($site_title ?: $company_name); ?> - Reports & Analytics</p>
        <p class="mt-1">Branch: <span id="footer-branch-name"><?php echo htmlspecialchars($current_branch_name); ?></span></p>
        <p class="mt-1">Period: <span id="footer-date-from"><?php echo date('d M Y', strtotime($date_from)); ?></span> - <span id="footer-date-to"><?php echo date('d M Y', strtotime($date_to)); ?></span></p>
        <p class="mt-2 text-slate-600">
            <span class="inline-flex items-center gap-1"><kbd class="px-1 py-0.5 rounded bg-slate-800 border border-slate-700 text-[10px]">Ctrl</kbd>+<kbd class="px-1 py-0.5 rounded bg-slate-800 border border-slate-700 text-[10px]">E</kbd> Export</span>
            <span class="mx-1">&middot;</span>
            <span class="inline-flex items-center gap-1"><kbd class="px-1 py-0.5 rounded bg-slate-800 border border-slate-700 text-[10px]">Ctrl</kbd>+<kbd class="px-1 py-0.5 rounded bg-slate-800 border border-slate-700 text-[10px]">R</kbd> Refresh</span>
            <span class="mx-1">&middot;</span>
            <span class="inline-flex items-center gap-1"><kbd class="px-1 py-0.5 rounded bg-slate-800 border border-slate-700 text-[10px]">Ctrl</kbd>+<kbd class="px-1 py-0.5 rounded bg-slate-800 border border-slate-700 text-[10px]">F</kbd> Search</span>
            <span class="mx-1">&middot;</span>
            <span class="inline-flex items-center gap-1"><kbd class="px-1 py-0.5 rounded bg-slate-800 border border-slate-700 text-[10px]">1-<?php echo count($visibleTabs); ?></kbd> Modules</span>
        </p>
    </div>
</div>

<script>
// Chart registry
const _charts = {};

function _destroyChart(canvasId) {
    if (_charts[canvasId]) {
        _charts[canvasId].destroy();
        delete _charts[canvasId];
    }
    const existing = Chart.getChart(canvasId);
    if (existing) existing.destroy();
}

// Use the permission-filtered active module
let currentModule = '<?php echo $active_module; ?>';
let allowedModules = <?php echo json_encode(array_keys($visibleTabs)); ?>;
let currentFilters = {
    from: '<?php echo $date_from; ?>',
    to: '<?php echo $date_to; ?>',
    branch_id: '<?php echo $branch_filter; ?>'
};
let allSalesData = [];

// Load initial module
document.addEventListener('DOMContentLoaded', function() {
    loadModule();
    
    document.getElementById('date-from-input')?.addEventListener('change', function() {
        currentFilters.from = this.value;
        updateDateDisplay();
    });
    document.getElementById('date-to-input')?.addEventListener('change', function() {
        currentFilters.to = this.value;
        updateDateDisplay();
    });
    document.getElementById('branch-select')?.addEventListener('change', function() {
        currentFilters.branch_id = this.value;
    });
});

function switchModule(module) {
    // Security: prevent switching to unauthorized modules
    if (!allowedModules.includes(module)) {
        showToast('You do not have access to this report module', 'error');
        return;
    }
    currentModule = module;
    
    const url = new URL(window.location);
    url.searchParams.set('module', module);
    url.searchParams.set('from', currentFilters.from);
    url.searchParams.set('to', currentFilters.to);
    url.searchParams.set('branch_id', currentFilters.branch_id);
    window.history.pushState({}, '', url);
    
    document.querySelectorAll('.module-tab').forEach(tab => {
        tab.classList.remove('bg-amber-500/10', 'text-amber-400', 'border-b-2', 'border-amber-400');
        tab.classList.add('text-slate-400');
    });
    const activeTab = document.getElementById(`tab-${module}`);
    if (activeTab) {
        activeTab.classList.add('bg-amber-500/10', 'text-amber-400', 'border-b-2', 'border-amber-400');
        activeTab.classList.remove('text-slate-400');
    }
    
    loadModule();
}

function applyFilters() {
    currentFilters.from = document.getElementById('date-from-input')?.value || currentFilters.from;
    currentFilters.to = document.getElementById('date-to-input')?.value || currentFilters.to;
    currentFilters.branch_id = document.getElementById('branch-select')?.value || currentFilters.branch_id;
    updateDateDisplay();
    loadModule();
}

function updateDateDisplay() {
    const locale = (typeof CHART_CONFIG !== 'undefined' && CHART_CONFIG.locale) ? CHART_CONFIG.locale : 'en-US';
    const fromDate = new Date(currentFilters.from);
    const toDate = new Date(currentFilters.to);
    const dateOptions = { day: 'numeric', month: 'short', year: 'numeric' };
    const formattedFrom = fromDate.toLocaleDateString(locale, dateOptions);
    const formattedTo = toDate.toLocaleDateString(locale, dateOptions);
    
    document.getElementById('display-date-from').textContent = formattedFrom;
    document.getElementById('display-date-to').textContent = formattedTo;
    document.getElementById('date-from-display').textContent = formattedFrom;
    document.getElementById('date-to-display').textContent = formattedTo;
    document.getElementById('footer-date-from').textContent = formattedFrom;
    document.getElementById('footer-date-to').textContent = formattedTo;
}

function refreshDashboard() {
    loadModule();
    showToast('Dashboard refreshed', 'success');
}

function loadModule() {
    const contentDiv = document.getElementById('module-content');
    contentDiv.innerHTML = `
        <div class="text-center py-16">
            <div class="inline-block">
                <div class="w-12 h-12 border-3 border-slate-700 border-t-amber-400 rounded-full animate-spin mx-auto"></div>
            </div>
            <p class="text-slate-500 mt-4">Loading ${currentModule.replace('_', ' ').toUpperCase()} report...</p>
            <p class="text-xs text-slate-600 mt-1">Please wait while we fetch your data</p>
        </div>
    `;
    
    const params = new URLSearchParams({
        module: currentModule,
        from: currentFilters.from,
        to: currentFilters.to,
        branch_id: currentFilters.branch_id,
        business_type: '<?php echo rawurlencode($business_type); ?>',
        user_id: '<?php echo $user_id; ?>',
        currency: '<?php echo $currency; ?>',
        bt_sales_label: '<?php echo htmlspecialchars($bt_sales_label); ?>',
        bt_customer_label: '<?php echo htmlspecialchars($bt_customer_label); ?>',
        allowed_modules: allowedModules.join(',')
    });
    
    const loadUrl = `../ajax/load_report_module.php?${params.toString()}`;
    
    fetch(loadUrl)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                Object.keys(_charts).forEach(_destroyChart);
                contentDiv.innerHTML = data.html;
                
                contentDiv.querySelectorAll('script').forEach(oldScript => {
                    const newScript = document.createElement('script');
                    newScript.textContent = oldScript.textContent;
                    document.head.appendChild(newScript);
                    oldScript.remove();
                });
                
                const branchSelect = document.getElementById('branch-select');
                if (branchSelect) {
                    const selectedOption = branchSelect.options[branchSelect.selectedIndex];
                    const branchName = selectedOption?.text || '<?php echo htmlspecialchars($current_branch_name); ?>';
                    document.getElementById('header-branch-name').textContent = branchName;
                    document.getElementById('footer-branch-name').textContent = branchName;
                }
                
                if (data.charts && data.charts.sales_trend) {
                    initSalesChart(data.charts.sales_trend);
                }
                if (data.charts && data.charts.payment_methods) {
                    initPaymentChart(data.charts.payment_methods);
                }
                
                if (data.table_data) {
                    allSalesData = data.table_data;
                }

                // Reset table search
                const searchInput = document.getElementById('tableSearch');
                if (searchInput) { searchInput.value = ''; filterReportTable(''); }

                showToast(`${currentModule.replace('_', ' ').toUpperCase()} report loaded successfully`, 'success');
            } else {
                throw new Error(data.error || 'Failed to load report');
            }
        })
        .catch(error => {
            console.error('Error loading module:', error);
            contentDiv.innerHTML = getErrorHTML(error.message);
            showToast('Failed to load report: ' + error.message, 'error');
        });
}

function getErrorHTML(message) {
    const defaultModule = currentModule || 'sales';
    const moduleLabel = defaultModule.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    return `
        <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-8 text-center">
            <i class="fas fa-exclamation-triangle text-red-400 text-3xl mb-3 block"></i>
            <h3 class="text-lg font-semibold text-red-400 mb-2">Error Loading Report</h3>
            <p class="text-slate-400 text-sm mb-4">${message}</p>
            <div class="flex gap-3 justify-center">
                <button onclick="loadModule()" class="px-4 py-2 bg-slate-700 rounded-lg text-sm hover:bg-slate-600 transition">
                    <i class="fas fa-sync-alt mr-2"></i> Try Again
                </button>
                <button onclick="switchModule('${defaultModule}')" class="px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm hover:bg-amber-500/25 transition">
                    <i class="fas fa-chart-line mr-2"></i> Load ${moduleLabel} Report
                </button>
            </div>
        </div>
    `;
}

function initSalesChart(data) {
    const ctx = document.getElementById('salesChart')?.getContext('2d');
    if (!ctx || !data?.labels?.length) return;

    const cfg = (typeof CHART_CONFIG !== 'undefined') ? CHART_CONFIG : {};
    const primaryColor = cfg.colorMap?.primary || '#fbbf24';
    const bgColor = primaryColor.replace(')', ', 0.1)').replace('rgb', 'rgba');
    if (!bgColor.includes('rgba')) {
        // hex to rgba fallback
        const hex = primaryColor.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
    }

    _destroyChart('salesChart');
    _charts['salesChart'] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: `Revenue (${cfg.currency || '<?php echo htmlspecialchars($currency); ?>'})`,
                data: data.values,
                borderColor: primaryColor,
                backgroundColor: (typeof primaryColor === 'string' && primaryColor.startsWith('#')) 
                    ? primaryColor + '1A'  // 10% opacity hex
                    : (cfg.colorMap?.primary ? cfg.colorMap.primary + '1A' : 'rgba(251, 191, 36, 0.1)'),
                borderWidth: 2,
                pointBackgroundColor: primaryColor,
                pointBorderColor: cfg.colorMap?.dark || '#1e293b',
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
                tooltip: { callbacks: { label: (ctx) => `${cfg.currency || '<?php echo htmlspecialchars($currency); ?>'} ${ctx.raw.toLocaleString()}` } }
            },
            scales: {
                y: { grid: { color: cfg.colorMap?.grid || 'rgba(255,255,255,0.05)' }, ticks: { color: cfg.colorMap?.muted || '#9ca3af' } },
                x: { grid: { display: false }, ticks: { color: cfg.colorMap?.muted || '#9ca3af' } }
            }
        }
    });
}

function initPaymentChart(data) {
    const ctx = document.getElementById('paymentChart')?.getContext('2d');
    if (!ctx || !data?.labels?.length) return;

    const cfg = (typeof CHART_CONFIG !== 'undefined') ? CHART_CONFIG : {};
    const palette = cfg.colors || ['#fbbf24', '#60a5fa', '#34d399', '#a78bfa', '#f87171', '#22d3ee'];

    _destroyChart('paymentChart');
    _charts['paymentChart'] = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: palette.slice(0, data.labels.length),
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: cfg.colorMap?.muted || '#9ca3af', font: { size: 10 } } },
                tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${cfg.currency || '<?php echo htmlspecialchars($currency); ?>'} ${ctx.raw.toLocaleString()}` } }
            }
        }
    });
}

function exportCurrentModule() {
    if (!allSalesData.length) {
        showToast('No data to export', 'error');
        return;
    }
    
    let csv = `"<?php echo htmlspecialchars($site_title ?: $company_name); ?> - ${currentModule.toUpperCase()} Report"\n`;
    csv += `"Generated",${new Date().toLocaleString()}\n`;
    csv += `"Branch",${document.getElementById('header-branch-name')?.textContent || ''}\n`;
    csv += `"Period",${document.getElementById('display-date-from')?.textContent || ''} to ${document.getElementById('display-date-to')?.textContent || ''}\n\n`;
    csv += `"Period","Transactions","Revenue","Average","Discounts","Cash","Card","M-Pesa"\n`;
    
    allSalesData.forEach(row => {
        csv += `"${row.period}",${row.transaction_count || 0},${row.total_sales || 0},${row.average_sale || 0},${row.total_discount || 0},${row.cash_sales || 0},${row.card_sales || 0},${row.mpesa_sales || 0}\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${currentModule}_report_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    showToast('Report exported successfully', 'success');
}

function printReport() {
    window.print();
}

// ==================== ADVANCED FEATURES ====================

// Date range presets
function setDatePreset(preset) {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const todayStr = `${yyyy}-${mm}-${dd}`;

    let from, to;
    switch (preset) {
        case 'today':
            from = to = todayStr;
            break;
        case 'yesterday':
            const yest = new Date(today); yest.setDate(yest.getDate() - 1);
            from = to = `${yest.getFullYear()}-${String(yest.getMonth()+1).padStart(2,'0')}-${String(yest.getDate()).padStart(2,'0')}`;
            break;
        case 'this_week':
            const dow = today.getDay();
            const weekStart = new Date(today); weekStart.setDate(today.getDate() - dow);
            from = `${weekStart.getFullYear()}-${String(weekStart.getMonth()+1).padStart(2,'0')}-${String(weekStart.getDate()).padStart(2,'0')}`;
            to = todayStr;
            break;
        case 'this_month':
            from = `${yyyy}-${mm}-01`; to = todayStr;
            break;
        case 'last_month':
            const lm = new Date(today); lm.setMonth(lm.getMonth() - 1);
            const lmy = lm.getFullYear(), lmm = String(lm.getMonth()+1).padStart(2,'0');
            const lmd = new Date(lmy, lm.getMonth()+1, 0).getDate();
            from = `${lmy}-${lmm}-01`; to = `${lmy}-${lmm}-${String(lmd).padStart(2,'0')}`;
            break;
        case 'this_year':
            from = `${yyyy}-01-01`; to = todayStr;
            break;
    }
    if (from && to) {
        document.getElementById('date-from-input').value = from;
        document.getElementById('date-to-input').value = to;
        currentFilters.from = from;
        currentFilters.to = to;
        updateDateDisplay();
        loadModule();
        showToast('Date range updated', 'success');
    }
}

// Compare previous period
let compareMode = false;
function toggleCompareMode() {
    compareMode = document.getElementById('compareToggle')?.checked || false;
    if (compareMode) {
        showToast('Compare mode enabled - previous period data will be shown where available', 'info');
    }
    loadModule();
}

// Auto refresh
let autoRefreshTimer = null;
let autoRefreshIntervalSec = 60;
function toggleAutoRefresh() {
    if (autoRefreshTimer) { stopAutoRefresh(); } else { setAutoRefreshInterval(autoRefreshIntervalSec); }
}
function setAutoRefreshInterval(seconds) {
    stopAutoRefresh();
    autoRefreshIntervalSec = seconds;
    autoRefreshTimer = setInterval(() => { loadModule(); }, seconds * 1000);
    const icon = document.getElementById('autoRefreshIcon');
    const label = document.getElementById('autoRefreshLabel');
    if (icon) icon.className = 'fas fa-sync-alt text-emerald-400 animate-spin';
    if (label) label.textContent = `Every ${seconds < 60 ? seconds + 's' : (seconds/60) + 'm'}`;
    showToast(`Auto-refresh every ${seconds < 60 ? seconds + 's' : (seconds/60) + 'm'}`, 'success');
}
function stopAutoRefresh() {
    if (autoRefreshTimer) { clearInterval(autoRefreshTimer); autoRefreshTimer = null; }
    const icon = document.getElementById('autoRefreshIcon');
    const label = document.getElementById('autoRefreshLabel');
    if (icon) icon.className = 'fas fa-clock text-slate-400';
    if (label) label.textContent = 'Auto';
}

// Fullscreen
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => showToast('Fullscreen not supported', 'error'));
    } else {
        document.exitFullscreen();
    }
}

// Client-side table search
function filterReportTable(query) {
    const q = query.toLowerCase().trim();
    const content = document.getElementById('module-content');
    if (!content) return;
    const rows = content.querySelectorAll('table tbody tr');
    let visible = 0;
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const show = !q || text.includes(q);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const countEl = document.getElementById('reportRowCount');
    if (countEl) countEl.textContent = rows.length ? `${visible} of ${rows.length} rows` : '';
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch (e.key.toLowerCase()) {
            case 'e': e.preventDefault(); exportCurrentModule(); break;
            case 'p': e.preventDefault(); printReport(); break;
            case 'r': e.preventDefault(); refreshDashboard(); break;
            case 'f': e.preventDefault(); document.getElementById('tableSearch')?.focus(); break;
        }
    }
    if (!e.ctrlKey && !e.metaKey && !e.altKey && document.activeElement?.tagName !== 'INPUT') {
        const modIndex = parseInt(e.key);
        if (modIndex >= 1 && modIndex <= allowedModules.length) {
            switchModule(allowedModules[modIndex - 1]);
        }
        if (e.key === '/') { e.preventDefault(); document.getElementById('tableSearch')?.focus(); }
    }
});

// Toast notifications (bottom-right)
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    const colors = { success: 'bg-emerald-800/95 border-l-4 border-emerald-400 text-white', error: 'bg-red-800/95 border-l-4 border-red-400 text-white', warning: 'bg-amber-800/95 border-l-4 border-amber-400 text-white', info: 'bg-slate-800 border-l-4 border-blue-400 text-slate-200' };
    toast.className = `${colors[type] || colors.info} px-4 py-3 rounded-lg text-sm font-medium shadow-2xl `;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>${message}`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 3000);
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>