<?php
/**
 * Activity Logs - JDH POS System
 * 
 * View and manage system activity logs with multi-tenant isolation
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Get current user context
$tenant_id = get_current_tenant_id() ?: get_current_tenant_id();
$tenant_id = $tenant_id;
$branch_id = get_current_branch_id();
$user_id = get_current_user_id();
$user_role = $_SESSION['role'] ?? 'cashier';
$is_super_admin = is_super_admin();
$is_company_admin = in_array($user_role, ['admin', 'owner']) || $is_super_admin;

// Check permission
if (!check_permission('activity.view') && !$is_super_admin && !$is_company_admin) {
    enforce_permission('activity.view');
}

// Get company/tenant name for display
$company_name = get_current_tenant_name() ?: (get_current_tenant_name($tenant_id) ?? '');

// Get users for filter (company-specific)
$users = [];
if ($is_company_admin || $is_super_admin) {
    try {
        $pdo = get_db_connection();
        if ($is_super_admin) {
            $stmt = $pdo->query("SELECT id, name, email FROM users WHERE status = 1 AND deleted_at IS NULL ORDER BY name");
        } else {
            $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE tenant_id = ? AND status = 1 AND deleted_at IS NULL ORDER BY name");
            $stmt->execute([$tenant_id]);
        }
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching users: " . $e->getMessage());
    }
}

// Get branches for filter
$branches = [];
if ($is_company_admin || $is_super_admin) {
    try {
        $pdo = get_db_connection();
        if ($is_super_admin) {
            $stmt = $pdo->query("SELECT id, name FROM branches WHERE deleted_at IS NULL ORDER BY name");
        } else {
            $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name");
            $stmt->execute([$tenant_id]);
        }
        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching branches: " . $e->getMessage());
    }
}

// Get action types from database
$action_types = [];
try {
    $pdo = get_db_connection();
    $sql = "SELECT DISTINCT action FROM activity_logs WHERE 1=1";
    if (!$is_super_admin && $tenant_id) {
        $sql .= " AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenant_id]);
    } else {
        $stmt = $pdo->query($sql);
    }
    $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $action_types = array_unique($actions);
    sort($action_types);
} catch (Exception $e) {
    error_log("Error fetching action types: " . $e->getMessage());
}

$page_title = 'Activity Logs';
ob_start();

// Action badge Tailwind classes (matching all_sales.php style)
$actionTw = [
    'login'    => 'bg-blue-500/15 text-blue-400 ring-1 ring-blue-500/30',
    'logout'   => 'bg-blue-500/15 text-blue-400 ring-1 ring-blue-500/30',
    'create'   => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
    'add'      => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
    'update'   => 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30',
    'edit'     => 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30',
    'delete'   => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
    'remove'   => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
    'sale'     => 'bg-purple-500/15 text-purple-400 ring-1 ring-purple-500/30',
    'purchase' => 'bg-purple-500/15 text-purple-400 ring-1 ring-purple-500/30',
    'view'     => 'bg-cyan-500/15 text-cyan-400 ring-1 ring-cyan-500/30',
    'export'   => 'bg-orange-500/15 text-orange-400 ring-1 ring-orange-500/30',
    'import'   => 'bg-pink-500/15 text-pink-400 ring-1 ring-pink-500/30',
    'print'    => 'bg-indigo-500/15 text-indigo-400 ring-1 ring-indigo-500/30',
];
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-history text-amber-400"></i> Activity Logs
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Monitor system activity and user actions<?php if (!$is_super_admin && $company_name): ?> · <?php echo htmlspecialchars($company_name); ?><?php endif; ?></p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <?php if ($is_company_admin || $is_super_admin): ?>
        <button onclick="exportLogs()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-download text-xs"></i> Export
        </button>
        <button onclick="clearOldLogs()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/20 transition-colors">
            <i class="fas fa-trash text-xs"></i> Clear Old
        </button>
        <?php endif; ?>
        <button onclick="refreshLogs()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-sync-alt text-xs"></i> Refresh
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mb-5" id="summaryCards">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-history text-amber-400 text-xs"></i></div>
        <div class="min-w-0"><div class="text-sm font-bold text-amber-400 truncate" id="sumTotal">—</div><div class="text-xs text-slate-500 leading-none mt-0.5">Total Logs</div></div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0"><i class="fas fa-sign-in-alt text-blue-400 text-xs"></i></div>
        <div class="min-w-0"><div class="text-sm font-bold text-blue-400 truncate" id="sumLogin">—</div><div class="text-xs text-slate-500 leading-none mt-0.5">Logins</div></div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0"><i class="fas fa-plus text-emerald-400 text-xs"></i></div>
        <div class="min-w-0"><div class="text-sm font-bold text-emerald-400 truncate" id="sumCreate">—</div><div class="text-xs text-slate-500 leading-none mt-0.5">Creates</div></div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-pen text-amber-400 text-xs"></i></div>
        <div class="min-w-0"><div class="text-sm font-bold text-amber-400 truncate" id="sumUpdate">—</div><div class="text-xs text-slate-500 leading-none mt-0.5">Updates</div></div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0"><i class="fas fa-trash text-red-400 text-xs"></i></div>
        <div class="min-w-0"><div class="text-sm font-bold text-red-400 truncate" id="sumDelete">—</div><div class="text-xs text-slate-500 leading-none mt-0.5">Deletes</div></div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center shrink-0"><i class="fas fa-cash-register text-purple-400 text-xs"></i></div>
        <div class="min-w-0"><div class="text-sm font-bold text-purple-400 truncate" id="sumSale">—</div><div class="text-xs text-slate-500 leading-none mt-0.5">Sales</div></div>
    </div>
</div>

<!-- Filters -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4 space-y-3">
    <form id="filterForm" class="flex flex-wrap gap-2 items-end">
        <div class="relative flex-1 min-w-[160px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="search" id="logSearchInline" placeholder="Search logs..."
                   class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <select name="user_id" id="userId" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">All Users</option>
            <?php foreach ($users as $user): ?>
            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="branch_id" id="branchId" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">All Branches</option>
            <?php foreach ($branches as $branch): ?>
            <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="action" id="actionType" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">All Actions</option>
            <?php foreach ($action_types as $action): ?>
            <option value="<?php echo htmlspecialchars($action); ?>"><?php echo ucfirst(str_replace('_', ' ', $action)); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" id="dateFrom" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>"
               class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
        <input type="date" name="date_to" id="dateTo" value="<?php echo date('Y-m-d'); ?>"
               class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
        <button type="submit" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-filter mr-1 text-xs"></i>Filter
        </button>
        <button type="button" onclick="resetFilters()" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times mr-1 text-xs"></i>Clear
        </button>
    </form>

    <div class="flex flex-wrap items-center gap-1.5">
        <span class="text-xs text-slate-500 mr-1">Quick:</span>
        <button onclick="setDatePreset('today')" class="px-2.5 py-1 rounded-full bg-slate-900 border border-slate-700 text-slate-400 text-xs hover:border-amber-500/40 hover:text-amber-400 transition-all">Today</button>
        <button onclick="setDatePreset('yesterday')" class="px-2.5 py-1 rounded-full bg-slate-900 border border-slate-700 text-slate-400 text-xs hover:border-amber-500/40 hover:text-amber-400 transition-all">Yesterday</button>
        <button onclick="setDatePreset('this_week')" class="px-2.5 py-1 rounded-full bg-slate-900 border border-slate-700 text-slate-400 text-xs hover:border-amber-500/40 hover:text-amber-400 transition-all">This Week</button>
        <button onclick="setDatePreset('this_month')" class="px-2.5 py-1 rounded-full bg-slate-900 border border-slate-700 text-slate-400 text-xs hover:border-amber-500/40 hover:text-amber-400 transition-all">This Month</button>
        <button onclick="setDatePreset('last_month')" class="px-2.5 py-1 rounded-full bg-slate-900 border border-slate-700 text-slate-400 text-xs hover:border-amber-500/40 hover:text-amber-400 transition-all">Last Month</button>
        <div class="flex-1"></div>
        <label class="flex items-center gap-1.5 cursor-pointer select-none">
            <input type="checkbox" id="autoRefreshToggle" class="w-3.5 h-3.5 rounded accent-amber-500" checked onchange="toggleAutoRefreshManual()">
            <span class="text-xs text-slate-400">Auto refresh</span>
        </label>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div id="bulkActionsBar" class="hidden flex items-center gap-2 bg-amber-500/10 border border-amber-500/30 rounded-lg px-3 py-2 mb-4">
    <span class="text-xs text-amber-400 font-medium"><span id="selectedCount">0</span> selected</span>
    <div class="flex-1"></div>
    <button onclick="exportSelectedLogs()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-slate-800 border border-slate-700 text-slate-300 text-xs hover:bg-slate-700 transition-colors">
        <i class="fas fa-download text-xs"></i> Export
    </button>
    <button onclick="clearSelectedLogs()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-red-500/10 border border-red-500/30 text-red-400 text-xs hover:bg-red-500/20 transition-colors">
        <i class="fas fa-trash text-xs"></i> Delete
    </button>
    <button onclick="clearSelection()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-slate-800 border border-slate-700 text-slate-400 text-xs hover:bg-slate-700 transition-colors">
        Cancel
    </button>
</div>

<!-- Activity Logs Table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px]" id="logsTable">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-center w-10"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" class="w-3.5 h-3.5 rounded accent-amber-500"></th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Timestamp</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">User</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Action</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Description</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">IP Address</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Branch</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="activityLogsBody" class="divide-y divide-slate-700/40">
                <tr>
                    <td colspan="8" class="px-4 py-14 text-center text-slate-400">
                        <i class="fas fa-spinner fa-spin text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-sm">Loading activity logs...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Table Footer -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
        <p class="text-xs text-slate-500">
            Showing <span class="text-slate-300 font-medium"><span id="showingFrom">0</span>–<span id="showingTo">0</span></span>
            of <span class="text-slate-300 font-medium" id="totalLogs">0</span> entries
            <span id="lastUpdated" class="ml-2 text-slate-600"></span>
        </p>
        <div class="flex items-center gap-1" id="paginationControls">
            <button onclick="changePage('prev')" id="prevBtn" class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white transition-colors" disabled>
                <i class="fas fa-chevron-left text-xs"></i>
            </button>
            <span id="pageInfo" class="text-xs text-slate-400 px-2">Page 1</span>
            <button onclick="changePage('next')" id="nextBtn" class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white transition-colors" disabled>
                <i class="fas fa-chevron-right text-xs"></i>
            </button>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div id="logDetailsModal" class="fixed inset-0 bg-black/60  hidden items-center justify-center z-50">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-lg w-full mx-4 shadow-xl max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Log Details</h3>
            <button onclick="closeModal('logDetailsModal')" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700 text-slate-400 hover:text-white transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div id="logDetailsContent" class="space-y-3 text-sm"></div>
        <div class="flex gap-2 mt-4 pt-3 border-t border-slate-700/60">
            <button onclick="closeModal('logDetailsModal')" class="flex-1 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">Close</button>
        </div>
    </div>
</div>

<script>
    let currentPage = 1;
    let totalPages = 1;
    let logsPerPage = 20;
    let autoRefreshInterval = null;

    document.addEventListener('DOMContentLoaded', function () {
        loadActivityLogs();
        startAutoRefresh();

        document.getElementById('filterForm').addEventListener('submit', function (e) {
            e.preventDefault();
            currentPage = 1;
            loadActivityLogs();
        });

        // Inline search triggers form submit on Enter
        document.getElementById('logSearchInline')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); currentPage = 1; loadActivityLogs(); }
        });

        const resetAutoRefresh = () => { stopAutoRefresh(); startAutoRefresh(); };
        document.getElementById('filterForm').addEventListener('change', resetAutoRefresh);
    });

    function startAutoRefresh() {
        if (autoRefreshInterval) clearInterval(autoRefreshInterval);
        autoRefreshInterval = setInterval(() => {
            if (!document.hidden) {
                loadActivityLogs(false); // false = don't reset page
            }
        }, 30000);
    }

    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
    }

    function resetFilters() {
        // Reset date inputs
        const today = new Date();
        const thirtyDaysAgo = new Date(today);
        thirtyDaysAgo.setDate(today.getDate() - 30);
        
        document.getElementById('dateFrom').value = thirtyDaysAgo.toISOString().split('T')[0];
        document.getElementById('dateTo').value = today.toISOString().split('T')[0];
        document.getElementById('userId').value = '';
        document.getElementById('branchId').value = '';
        document.getElementById('actionType').value = '';
        
        currentPage = 1;
        loadActivityLogs();
    }

    function refreshLogs() {
        loadActivityLogs(true);
        showToast('Refreshing logs...', 'info');
    }

    function loadActivityLogs(resetPage = true) {
        if (resetPage) {
            currentPage = 1;
        }

        const formData = new FormData(document.getElementById('filterForm'));
        formData.append('page', currentPage);
        formData.append('limit', logsPerPage);

        const params = new URLSearchParams(formData);

        fetch(`../ajax/get_activity_logs.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderLogs(data.logs);
                    updatePagination(data.total, data.page, data.limit);
                    updateLastUpdated();
                    if (data.summary) updateSummaryCards(data.summary);
                } else {
                    showError(data.error || 'Failed to load activity logs');
                }
            })
            .catch(error => {
                console.error('Error loading activity logs:', error);
                showError('Error loading activity logs');
            });
    }

    function updateSummaryCards(summary) {
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val !== undefined ? val : '0'; };
        set('sumTotal', summary.total);
        set('sumLogin', summary.login);
        set('sumCreate', summary.create);
        set('sumUpdate', summary.update);
        set('sumDelete', summary.delete);
        set('sumSale', summary.sale);
    }

    function renderLogs(logs) {
        const tbody = document.getElementById('activityLogsBody');

        if (!logs || logs.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="px-4 py-14 text-center text-slate-400">
                        <i class="fas fa-inbox text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-sm">No activity logs found</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = logs.map(log => {
            const badgeClass = getActionBadgeClass(log.action);
            return `
            <tr data-log-id="${log.id}" class="hover:bg-slate-700/30 transition-colors group">
                <td class="px-3 py-2.5 text-center"><input type="checkbox" value="${log.id}" class="log-checkbox w-3.5 h-3.5 rounded accent-amber-500" onchange="updateSelection()"></td>
                <td class="px-3 py-2.5 whitespace-nowrap">
                    <div class="text-sm text-slate-300">${formatDateTime(log.created_at)}</div>
                </td>
                <td class="px-3 py-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold text-slate-900">${getInitials(log.user_name || 'Unknown')}</span>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-white">${escapeHtml(log.user_name || 'System')}</div>
                            <div class="text-xs text-slate-500">${escapeHtml(log.user_email || 'system@local')}</div>
                        </div>
                    </div>
                </td>
                <td class="px-3 py-2.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${badgeClass}">${escapeHtml(log.action)}</span>
                </td>
                <td class="px-3 py-2.5">
                    <div class="text-sm text-slate-400 max-w-xs truncate" title="${escapeHtml(log.description || '')}">
                        ${escapeHtml(log.description || 'No description')}
                    </div>
                </td>
                <td class="px-3 py-2.5">
                    <div class="text-sm text-slate-500 font-mono">${escapeHtml(log.ip_address || 'N/A')}</div>
                </td>
                <td class="px-3 py-2.5">
                    <div class="text-sm text-slate-500">${escapeHtml(log.branch_name || 'N/A')}</div>
                </td>
                <td class="px-3 py-2.5">
                    <div class="flex items-center justify-center gap-1">
                        <button onclick="viewLogDetails(${log.id})" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-blue-400 hover:bg-blue-500/20 hover:text-blue-300 transition-colors" title="View">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                    </div>
                </td>
            </tr>
            `;
        }).join('');
    }

    function updatePagination(total, page, limit) {
        totalPages = Math.ceil(total / limit);
        currentPage = page;

        const from = total > 0 ? ((page - 1) * limit) + 1 : 0;
        const to = Math.min(page * limit, total);

        document.getElementById('showingFrom').textContent = from;
        document.getElementById('showingTo').textContent = to;
        document.getElementById('totalLogs').textContent = total;
        document.getElementById('pageInfo').textContent = `Page ${page} of ${totalPages}`;

        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        if (prevBtn) {
            prevBtn.disabled = page <= 1;
            prevBtn.className = page <= 1
                ? 'w-7 h-7 flex items-center justify-center rounded-lg text-xs border border-slate-700 text-slate-600 pointer-events-none'
                : 'w-7 h-7 flex items-center justify-center rounded-lg text-xs border border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white transition-colors';
        }
        if (nextBtn) {
            nextBtn.disabled = page >= totalPages;
            nextBtn.className = page >= totalPages
                ? 'w-7 h-7 flex items-center justify-center rounded-lg text-xs border border-slate-700 text-slate-600 pointer-events-none'
                : 'w-7 h-7 flex items-center justify-center rounded-lg text-xs border border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white transition-colors';
        }
    }

    function updateLastUpdated() {
        const now = new Date();
        document.getElementById('lastUpdated').innerHTML = `Last updated: ${now.toLocaleTimeString()}`;
    }

    function changePage(direction) {
        if (direction === 'prev' && currentPage > 1) {
            currentPage--;
            loadActivityLogs(false);
        } else if (direction === 'next' && currentPage < totalPages) {
            currentPage++;
            loadActivityLogs(false);
        }
    }

    function viewLogDetails(logId) {
        fetch(`../ajax/get_log_details.php?id=${logId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showLogDetails(data.log);
                } else {
                    showError(data.error || 'Failed to load log details');
                }
            })
            .catch(error => {
                console.error('Error loading log details:', error);
                showError('Error loading log details');
            });
    }

    function showLogDetails(log) {
        const content = document.getElementById('logDetailsContent');
        const badgeClass = getActionBadgeClass(log.action);

        let metadataHtml = '';
        if (log.metadata) {
            try {
                const metadata = typeof log.metadata === 'string' ? JSON.parse(log.metadata) : log.metadata;
                metadataHtml = `
                    <div>
                        <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1.5">Metadata</label>
                        <pre class="bg-slate-900 p-3 rounded-lg text-xs text-slate-400 overflow-x-auto border border-slate-700">${escapeHtml(JSON.stringify(metadata, null, 2))}</pre>
                    </div>
                `;
            } catch (e) {
                metadataHtml = `
                    <div>
                        <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1.5">Metadata</label>
                        <pre class="bg-slate-900 p-3 rounded-lg text-xs text-slate-400 overflow-x-auto border border-slate-700">${escapeHtml(log.metadata)}</pre>
                    </div>
                `;
            }
        }

        content.innerHTML = `
            <div class="space-y-4 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-900/60 rounded-lg p-2.5 border border-slate-700/40">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wide mb-0.5">Timestamp</div>
                        <div class="text-slate-200 font-medium">${formatDateTime(log.created_at)}</div>
                    </div>
                    <div class="bg-slate-900/60 rounded-lg p-2.5 border border-slate-700/40">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wide mb-0.5">User</div>
                        <div class="text-slate-200">${escapeHtml(log.user_name || 'System')} ${log.user_email ? '<span class="text-slate-500">(' + escapeHtml(log.user_email) + ')</span>' : ''}</div>
                    </div>
                    <div class="bg-slate-900/60 rounded-lg p-2.5 border border-slate-700/40">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wide mb-0.5">Action</div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${badgeClass}">${escapeHtml(log.action)}</span>
                    </div>
                    <div class="bg-slate-900/60 rounded-lg p-2.5 border border-slate-700/40">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wide mb-0.5">IP Address</div>
                        <div class="text-slate-200 font-mono text-xs">${escapeHtml(log.ip_address || 'N/A')}</div>
                    </div>
                    ${log.branch_name ? `
                    <div class="bg-slate-900/60 rounded-lg p-2.5 border border-slate-700/40">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wide mb-0.5">Branch</div>
                        <div class="text-slate-200">${escapeHtml(log.branch_name)}</div>
                    </div>
                    ` : ''}
                    ${log.tenant_name ? `
                    <div class="bg-slate-900/60 rounded-lg p-2.5 border border-slate-700/40">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wide mb-0.5">Company</div>
                        <div class="text-slate-200">${escapeHtml(log.tenant_name)}</div>
                    </div>
                    ` : ''}
                </div>
                <div class="bg-slate-900/60 rounded-lg p-2.5 border border-slate-700/40">
                    <div class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">Description</div>
                    <div class="text-slate-300">${escapeHtml(log.description || 'No description')}</div>
                </div>
                ${log.user_agent ? `
                <div class="bg-slate-900/60 rounded-lg p-2.5 border border-slate-700/40">
                    <div class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">User Agent</div>
                    <div class="text-slate-400 text-xs font-mono break-all">${escapeHtml(log.user_agent)}</div>
                </div>
                ` : ''}
                ${metadataHtml}
            </div>
        `;

        openModal('logDetailsModal');
    }

    function exportLogs() {
        const formData = new FormData(document.getElementById('filterForm'));
        const params = new URLSearchParams(formData);
        
        // Show loading indicator
        showToast('Preparing export...', 'info');
        
        window.open(`../ajax/export_activity_logs.php?${params.toString()}`, '_blank');
    }

    function clearOldLogs() {
        const days = prompt('Enter number of days to keep (logs older than this will be deleted):', '90');
        if (!days) return;
        
        if (!confirm(`Are you sure you want to clear logs older than ${days} days? This action cannot be undone.`)) {
            return;
        }

        fetch('../ajax/clear_old_logs.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ days: parseInt(days) })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`Cleared ${data.deleted_count} old log entries`, 'success');
                    loadActivityLogs(true);
                } else {
                    showError(data.error || 'Failed to clear old logs');
                }
            })
            .catch(error => {
                console.error('Error clearing old logs:', error);
                showError('Error clearing old logs');
            });
    }

    function getActionBadgeClass(action) {
        const actionLower = (action || '').toLowerCase();
        if (actionLower.includes('login') || actionLower.includes('logout')) {
            return 'bg-blue-500/15 text-blue-400 ring-1 ring-blue-500/30';
        } else if (actionLower.includes('create') || actionLower.includes('add')) {
            return 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30';
        } else if (actionLower.includes('update') || actionLower.includes('edit')) {
            return 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30';
        } else if (actionLower.includes('delete') || actionLower.includes('remove')) {
            return 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30';
        } else if (actionLower.includes('sale') || actionLower.includes('purchase')) {
            return 'bg-purple-500/15 text-purple-400 ring-1 ring-purple-500/30';
        } else if (actionLower.includes('view')) {
            return 'bg-cyan-500/15 text-cyan-400 ring-1 ring-cyan-500/30';
        } else if (actionLower.includes('export')) {
            return 'bg-orange-500/15 text-orange-400 ring-1 ring-orange-500/30';
        } else if (actionLower.includes('import')) {
            return 'bg-pink-500/15 text-pink-400 ring-1 ring-pink-500/30';
        } else if (actionLower.includes('print')) {
            return 'bg-indigo-500/15 text-indigo-400 ring-1 ring-indigo-500/30';
        }
        return 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30';
    }

    function formatDateTime(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    function getInitials(name) {
        if (name === null || name === undefined || typeof name !== 'string' || name.trim() === '') return '?';
        try {
            const parts = name.trim().split(' ');
            return parts.map(n => n && n[0] ? n[0] : '').join('').toUpperCase().substring(0, 2) || '?';
        } catch (e) {
            return '?';
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function openModal(modalId) {
        const el = document.getElementById(modalId);
        if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        const el = document.getElementById(modalId);
        if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
        document.body.style.overflow = '';
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

    function showError(message) {
        showToast(message, 'error');
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
            case 'today': from = to = todayStr; break;
            case 'yesterday':
                const yest = new Date(today); yest.setDate(yest.getDate() - 1);
                from = to = `${yest.getFullYear()}-${String(yest.getMonth()+1).padStart(2,'0')}-${String(yest.getDate()).padStart(2,'0')}`;
                break;
            case 'this_week':
                const dow = today.getDay();
                const ws = new Date(today); ws.setDate(today.getDate() - dow);
                from = `${ws.getFullYear()}-${String(ws.getMonth()+1).padStart(2,'0')}-${String(ws.getDate()).padStart(2,'0')}`;
                to = todayStr; break;
            case 'this_month':
                from = `${yyyy}-${mm}-01`; to = todayStr; break;
            case 'last_month':
                const lm = new Date(today); lm.setMonth(lm.getMonth() - 1);
                const lmy = lm.getFullYear(), lmm = String(lm.getMonth()+1).padStart(2,'0');
                const lmd = new Date(lmy, lm.getMonth()+1, 0).getDate();
                from = `${lmy}-${lmm}-01`; to = `${lmy}-${lmm}-${String(lmd).padStart(2,'0')}`;
                break;
        }
        if (from && to) {
            document.getElementById('dateFrom').value = from;
            document.getElementById('dateTo').value = to;
            currentPage = 1;
            loadActivityLogs(true);
            showToast('Date range updated', 'success');
        }
    }

    // Bulk selection
    function toggleSelectAll(source) {
        document.querySelectorAll('.log-checkbox').forEach(cb => { cb.checked = source.checked; });
        updateSelection();
    }

    function updateSelection() {
        const checked = document.querySelectorAll('.log-checkbox:checked');
        const bar = document.getElementById('bulkActionsBar');
        const count = document.getElementById('selectedCount');
        if (count) count.textContent = checked.length;
        if (bar) bar.classList.toggle('hidden', checked.length === 0);
    }

    function clearSelection() {
        document.querySelectorAll('.log-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
        updateSelection();
    }

    function getSelectedIds() {
        return Array.from(document.querySelectorAll('.log-checkbox:checked')).map(cb => cb.value);
    }

    function exportSelectedLogs() {
        const ids = getSelectedIds();
        if (!ids.length) { showToast('No logs selected', 'warning'); return; }
        showToast('Preparing export of ' + ids.length + ' logs...', 'info');
        // TODO: implement backend export_selected handler if needed; fallback to client-side
        let csv = 'ID,Timestamp,User,Action,Description,IP,Branch\n';
        const rows = document.querySelectorAll('#logsTable tbody tr[data-log-id]');
        rows.forEach(row => {
            const cb = row.querySelector('.log-checkbox');
            if (cb && cb.checked) {
                const cells = row.querySelectorAll('td');
                const id = row.dataset.logId;
                const ts = cells[1]?.textContent.trim().replace(/,/g, ' ');
                const user = cells[2]?.textContent.trim().replace(/,/g, ' ');
                const action = cells[3]?.textContent.trim().replace(/,/g, ' ');
                const desc = cells[4]?.textContent.trim().replace(/,/g, ' ');
                const ip = cells[5]?.textContent.trim().replace(/,/g, ' ');
                const branch = cells[6]?.textContent.trim().replace(/,/g, ' ');
                csv += `${id},"${ts}","${user}","${action}","${desc}","${ip}","${branch}"\n`;
            }
        });
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `selected_logs_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        showToast('Exported ' + ids.length + ' logs', 'success');
        clearSelection();
    }

    function clearSelectedLogs() {
        const ids = getSelectedIds();
        if (!ids.length) return;
        if (!confirm('Delete ' + ids.length + ' selected log(s)? This cannot be undone.')) return;
        fetch('../ajax/delete_logs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids.map(Number) })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Deleted ' + (data.deleted || ids.length) + ' logs', 'success');
                clearSelection();
                loadActivityLogs(true);
            } else {
                showToast(data.error || 'Failed to delete logs', 'error');
            }
        })
        .catch(() => showToast('Network error deleting logs', 'error'));
    }

    // Manual auto-refresh toggle
    function toggleAutoRefreshManual() {
        const el = document.getElementById('autoRefreshToggle');
        if (el && el.checked) { startAutoRefresh(); showToast('Auto-refresh enabled', 'success'); }
        else { stopAutoRefresh(); showToast('Auto-refresh paused', 'info'); }
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) {
            switch (e.key.toLowerCase()) {
                case 'r': e.preventDefault(); refreshLogs(); break;
                case 'e': e.preventDefault(); exportLogs(); break;
            }
        }
        if (!e.ctrlKey && !e.metaKey && !e.altKey && document.activeElement?.tagName !== 'INPUT') {
            if (e.key === '/') { e.preventDefault(); document.getElementById('logSearchInline')?.focus(); }
        }
    });
</script>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
