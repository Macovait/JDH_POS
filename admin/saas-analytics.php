<?php
/**
 * Modern SaaS Analytics Dashboard
 * Compact Laravel-style with sleek charts
 */

require_once __DIR__ . '/bootstrap.php';

admin_require_super_admin();

$pdo = admin_require_db(['admins', 'pos_tenants', 'pos_subscriptions', 'pos_invoices']);

$period = $_GET['period'] ?? '30days';
$startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end'] ?? date('Y-m-d');

require_once __DIR__ . '/../src/Services/AnalyticsService.php';
$analytics = new \Jakababa\Services\AnalyticsService($pdo);

$metrics = $analytics->getSaaSMetrics($startDate, $endDate);
$growth = $analytics->getGrowthMetrics($startDate, $endDate);
$revenue = $analytics->getRevenueMetrics($startDate, $endDate);
$churn = $analytics->getChurnMetrics($startDate, $endDate);
$customers = $analytics->getCustomerMetrics($startDate, $endDate);

$page_title = 'SaaS Analytics';
$current_page = 'analytics';
ob_start();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white">SaaS Analytics</h1>
        <p class="text-xs text-slate-400 mt-0.5">Revenue, growth & retention metrics</p>
    </div>
    <div class="flex items-center gap-2">
        <select id="periodSelect" onchange="changePeriod(this.value)" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 text-xs py-1.5">
            <option value="7days" <?= $period === '7days' ? 'selected' : '' ?>>7 Days</option>
            <option value="30days" <?= $period === '30days' ? 'selected' : '' ?>>30 Days</option>
            <option value="90days" <?= $period === '90days' ? 'selected' : '' ?>>90 Days</option>
            <option value="12months" <?= $period === '12months' ? 'selected' : '' ?>>12 Months</option>
        </select>
        <button onclick="refreshData()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors text-xs">
            <i class="fas fa-sync-alt"></i>
        </button>
        <button onclick="exportReport()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors text-xs">
            <i class="fas fa-download"></i> Export
        </button>
        <a href="dashboard-v2.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors text-xs">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
    </div>
</div>

<!-- Key Metrics - Primary Row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
    <!-- MRR -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] text-slate-400 uppercase tracking-wide">MRR</span>
            <span class="text-[10px] <?= $metrics['mrr_growth'] >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">
                <i class="fas fa-<?= $metrics['mrr_growth'] >= 0 ? 'arrow-up' : 'arrow-down' ?> text-[10px]"></i>
                <?= number_format(abs($metrics['mrr_growth']), 1) ?>%
            </span>
        </div>
        <p class="text-sm font-bold text-amber-400">$<?= number_format($metrics['mrr'], 0) ?></p>
        <p class="text-xs text-slate-500 leading-none mt-0.5">Monthly Recurring</p>
    </div>

    <!-- ARR -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] text-slate-400 uppercase tracking-wide">ARR</span>
            <i class="fas fa-calendar text-emerald-400 text-xs"></i>
        </div>
        <p class="text-sm font-bold text-emerald-400">$<?= number_format($metrics['arr'], 0) ?></p>
        <p class="text-xs text-slate-500 leading-none mt-0.5">Annual Recurring</p>
    </div>

    <!-- Total Customers -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] text-slate-400 uppercase tracking-wide">Customers</span>
            <span class="text-[10px] text-emerald-400"><i class="fas fa-plus text-[10px]"></i> <?= $metrics['new_customers'] ?></span>
        </div>
        <p class="text-sm font-bold text-blue-400"><?= number_format($metrics['total_customers']) ?></p>
        <p class="text-xs text-slate-500 leading-none mt-0.5">Total Accounts</p>
    </div>

    <!-- Churn Rate -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] text-slate-400 uppercase tracking-wide">Churn</span>
            <i class="fas fa-user-minus text-rose-400 text-xs"></i>
        </div>
        <p class="text-sm font-bold text-rose-400"><?= number_format($metrics['churn_rate'], 1) ?>%</p>
        <p class="text-xs text-slate-500 leading-none mt-0.5"><?= $metrics['churned_customers'] ?> cancelled</p>
    </div>
</div>

<!-- Secondary Metrics -->
<div class="grid grid-cols-3 gap-2 mb-4">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-2 text-center">
        <p class="text-sm font-bold text-white">$<?= number_format($metrics['arpu'], 0) ?></p>
        <p class="text-[10px] text-slate-400 uppercase">ARPU / Month</p>
    </div>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-2 text-center">
        <p class="text-sm font-bold text-amber-400">$<?= number_format($metrics['ltv'], 0) ?></p>
        <p class="text-[10px] text-slate-400 uppercase">Lifetime Value</p>
    </div>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-2 text-center">
        <p class="text-sm font-bold text-purple-400"><?= number_format($metrics['nrr'], 0) ?>%</p>
        <p class="text-[10px] text-slate-400 uppercase">Net Retention</p>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
    <!-- Revenue Chart -->
    <div class="lg:col-span-2 bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <h3 class="text-sm font-semibold text-white"><i class="fas fa-chart-line text-amber-400 mr-2"></i>Revenue Trend</h3>
        <div class="h-40">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Plan Distribution -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <h3 class="text-sm font-semibold text-white"><i class="fas fa-chart-pie text-blue-400 mr-2"></i>Plans</h3>
        <div class="h-40 flex items-center justify-center">
            <canvas id="planChart"></canvas>
        </div>
    </div>
</div>

<!-- Growth & Churn Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
    <!-- Customer Growth -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <h3 class="text-sm font-semibold text-white"><i class="fas fa-users text-emerald-400 mr-2"></i>Customer Growth</h3>
        <div class="h-36">
            <canvas id="growthChart"></canvas>
        </div>
    </div>

    <!-- Churn Analysis -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <h3 class="text-sm font-semibold text-white"><i class="fas fa-user-minus text-rose-400 mr-2"></i>Churn Rate</h3>
        <div class="h-36">
            <canvas id="churnChart"></canvas>
        </div>
    </div>
</div>

<!-- Data Tables Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
    <!-- Top Customers -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-white"><i class="fas fa-trophy text-amber-400 mr-2"></i>Top Customers</h3>
            <a href="companies.php" class="text-xs text-blue-400 hover:text-blue-300">View All →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px]">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Plan</th>
                        <th class="text-right">MRR</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($customers['top_by_mrr'], 0, 5) as $customer): ?>
                    <tr>
                        <td>
                            <p class="font-medium text-white"><?= htmlspecialchars($customer['name']) ?></p>
                            <p class="text-[10px] text-slate-500"><?= htmlspecialchars($customer['email']) ?></p>
                        </td>
                        <td><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400"><?= ucfirst($customer['plan_id']) ?></span></td>
                        <td class="text-right font-semibold text-white">$<?= number_format($customer['monthly_amount'], 0) ?></td>
                        <td><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $customer['status'] === 'active' ? 'emerald' : 'amber' ?>-500/20 text-<?= $customer['status'] === 'active' ? 'emerald' : 'amber' ?>-400"><?= ucfirst($customer['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Churn -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-white"><i class="fas fa-times-circle text-rose-400 mr-2"></i>Recent Churn</h3>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-500/20 text-rose-400"><?= count($churn['recent']) ?> this period</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px]">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Plan</th>
                        <th>Tenure</th>
                        <th>Cancelled</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($churn['recent'], 0, 5) as $c): ?>
                    <tr>
                        <td class="font-medium text-white"><?= htmlspecialchars($c['name']) ?></td>
                        <td><?= ucfirst($c['plan_id']) ?></td>
                        <td><?= $c['tenure_months'] ?> mo</td>
                        <td class="text-slate-400"><?= date('M j', strtotime($c['cancelled_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Cohort Analysis -->
<?php if (!empty($customers['cohorts'])): ?>
<div class="mt-4 bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
    <h3 class="text-sm font-semibold text-white"><i class="fas fa-table text-purple-400 mr-2"></i>Cohort Retention Analysis</h3>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] text-center">
            <thead>
                <tr>
                    <th class="text-left">Cohort</th>
                    <th>Users</th>
                    <?php for ($i = 0; $i <= 6; $i++): ?>
                    <th>M<?= $i ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($customers['cohorts'], 0, 6) as $cohort): ?>
                <tr>
                    <td class="text-left font-medium text-white"><?= $cohort['month'] ?></td>
                    <td><?= $cohort['count'] ?></td>
                    <?php foreach (array_slice($cohort['retention'], 0, 7) as $rate): ?>
                    <td>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $rate >= 80 ? 'emerald' : ($rate >= 50 ? 'amber' : 'rose') ?>-500/20 text-<?= $rate >= 80 ? 'emerald' : ($rate >= 50 ? 'amber' : 'rose') ?>-400" style="min-width: 36px;">
                            <?= $rate ?>
                        </span>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script src="../public/assets/js/chart.js.min.js"></script>
<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($revenue['labels']) ?>,
        datasets: [{
            label: 'MRR',
            data: <?= json_encode($revenue['mrr_data']) ?>,
            borderColor: '#FBBF24',
            backgroundColor: 'rgba(251, 191, 36, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 0,
            pointHoverRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { color: '#6B7280', font: { size: 9 } } },
            y: { grid: { color: 'rgba(75, 85, 99, 0.2)' }, ticks: { color: '#6B7280', font: { size: 9 }, callback: v => '$' + (v/1000).toFixed(0) + 'k' } }
        },
        interaction: { intersect: false, mode: 'index' }
    }
});

// Plan Chart
const planCtx = document.getElementById('planChart').getContext('2d');
new Chart(planCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($customers['by_plan'], 'plan_id')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($customers['by_plan'], 'count')) ?>,
            backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: { position: 'right', labels: { color: '#9CA3AF', font: { size: 10 }, boxWidth: 10 } }
        }
    }
});

// Growth Chart
const growthCtx = document.getElementById('growthChart').getContext('2d');
new Chart(growthCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($growth['labels']) ?>,
        datasets: [{
            label: 'New',
            data: <?= json_encode($growth['new_customers']) ?>,
            backgroundColor: '#10B981',
            borderRadius: 2
        }, {
            label: 'Churned',
            data: <?= json_encode($growth['churned']) ?>,
            backgroundColor: '#EF4444',
            borderRadius: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, labels: { color: '#9CA3AF', font: { size: 10 }, boxWidth: 10 } } },
        scales: {
            x: { stacked: true, grid: { display: false }, ticks: { color: '#6B7280', font: { size: 9 } } },
            y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(75, 85, 99, 0.2)' }, ticks: { color: '#6B7280', font: { size: 9 } } }
        }
    }
});

// Churn Chart
const churnCtx = document.getElementById('churnChart').getContext('2d');
new Chart(churnCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($churn['labels']) ?>,
        datasets: [{
            label: 'Churn %',
            data: <?= json_encode($churn['rates']) ?>,
            borderColor: '#EF4444',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { color: '#6B7280', font: { size: 9 } } },
            y: { beginAtZero: true, max: 20, grid: { color: 'rgba(75, 85, 99, 0.2)' }, ticks: { color: '#6B7280', font: { size: 9 }, callback: v => v + '%' } }
        }
    }
});

function changePeriod(period) {
    window.location.href = `?period=${period}`;
}
function refreshData() {
    window.location.reload();
}
function exportReport() {
    alert('Export coming soon!');
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>

