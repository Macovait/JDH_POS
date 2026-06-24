<?php
/**
 * Fix broken dashboard.php HTML structure
 */

$file = __DIR__ . '/../admin/dashboard.php';
$content = file_get_contents($file);

// Extract PHP logic (lines 1-231)
$phpEnd = strpos($content, 'ob_start();');
$phpLogic = substr($content, 0, $phpEnd + strlen('ob_start();')) . "\n?>\n\n";

// Extract bottom section (from Secondary Stats onwards)
$bottomStart = strpos($content, '<!-- Secondary Stats - Mini Cards -->');
$bottomSection = substr($content, $bottomStart);

// Build clean middle section
$cleanMiddle = <<<'HTML'
<?php if ($error): ?>
<div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm mb-4">
    <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white">Dashboard</h1>
        <p class="text-sm text-slate-500 mt-0.5">Platform overview & key metrics</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <span class="text-xs text-slate-500">Updated: <?= date('M j, g:i a') ?></span>
        <a href="saas-analytics.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-xs font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-chart-line text-xs"></i> Analytics
        </a>
        <a href="<?php echo base_url('auth/signup.php'); ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-plus text-xs"></i> New Tenant
        </a>
    </div>
</div>

<!-- Primary Stats - Compact Row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <!-- Total Companies -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-blue-500/20 text-blue-400 shrink-0">
                <i class="fas fa-building text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-white"><?= number_format($stats['companies']['total']) ?></p>
                <p class="text-xs text-slate-500 leading-none mt-0.5">Total Companies</p>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-[10px] text-emerald-400"><i class="fas fa-circle text-[6px]"></i> <?= $stats['companies']['active'] ?> Active</span>
                    <span class="text-[10px] text-blue-400"><i class="fas fa-circle text-[6px]"></i> <?= $stats['companies']['trial'] ?> Trial</span>
                </div>
            </div>
        </div>
    </div>

    <!-- MRR -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 border-amber-500/30">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-amber-500/20 text-amber-400 shrink-0">
                <i class="fas fa-coins text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-amber-400"><?= formatCurrency($stats['revenue']['mrr']) ?></p>
                <p class="text-xs text-slate-500 leading-none mt-0.5">Monthly Recurring</p>
                <p class="text-[10px] text-slate-500"><?= $stats['revenue']['paying_companies'] ?> paying</p>
            </div>
        </div>
    </div>

    <!-- POS Sales Today -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-emerald-500/20 text-emerald-400 shrink-0">
                <i class="fas fa-cash-register text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-white"><?= formatCurrency($stats['pos_sales']['today']) ?></p>
                <p class="text-xs text-slate-500 leading-none mt-0.5">Sales Today</p>
                <?php if ($stats['pos_sales']['yesterday'] > 0): ?>
                    <?php $dayGrowth = $stats['pos_sales']['yesterday'] > 0 ? round((($stats['pos_sales']['today'] - $stats['pos_sales']['yesterday']) / $stats['pos_sales']['yesterday']) * 100, 1) : 0; ?>
                    <?php if ($dayGrowth >= 0): ?>
                    <span class="text-[10px] text-emerald-400"><i class="fas fa-arrow-up text-[10px]"></i> <?= $dayGrowth ?>% vs yest</span>
                    <?php else: ?>
                    <span class="text-[10px] text-red-400"><i class="fas fa-arrow-down text-[10px]"></i> <?= abs($dayGrowth) ?>% vs yest</span>
                    <?php endif; ?>
                <?php else: ?>
                <span class="text-xs text-slate-500"><?= $stats['pos_sales']['today_count'] ?> transactions</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Users -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-purple-500/20 text-purple-400 shrink-0">
                <i class="fas fa-users text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-white"><?= number_format($stats['usage']['total_active_users']) ?></p>
                <p class="text-xs text-slate-500 leading-none mt-0.5">Active Users</p>
                <p class="text-[10px] text-slate-500"><?= $stats['usage']['total_branches'] ?> Branches</p>
            </div>
        </div>
    </div>
</div>

HTML;

// Also fix remaining gray references in bottom section
$bottomSection = str_replace('bg-gray-900/30', 'bg-slate-900/30', $bottomSection);
$bottomSection = str_replace('hover:bg-gray-900/50', 'hover:bg-slate-900/50', $bottomSection);
$bottomSection = str_replace('text-gray-600', 'text-slate-600', $bottomSection);

// Fix duplicate text-sm text-xs in quick action buttons
$bottomSection = preg_replace('/text-sm font-medium ([^"]*?)text-xs/', 'text-xs font-medium $1', $bottomSection);
$bottomSection = preg_replace('/text-xs font-medium ([^"]*?)text-sm/', 'text-xs font-medium $1', $bottomSection);

// Fix statusBadge function bg-gray
$phpLogic = str_replace("bg-gray-500/20 text-slate-400", "bg-slate-500/20 text-slate-400", $phpLogic);

$newContent = $phpLogic . $cleanMiddle . $bottomSection;

file_put_contents($file, $newContent);
echo "Fixed dashboard.php\n";
