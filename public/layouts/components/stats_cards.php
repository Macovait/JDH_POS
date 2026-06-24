<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-text-secondary text-sm mb-1 font-medium">Today's Sales</p>
                <p class="text-3xl font-bold text-white">
                    <?php echo number_format($stats['today_sales']['total_sales'] ?? 0, 2); ?> <span
                        class="text-sm text-text-muted"><?php echo $currency; ?></span></p>
                <p class="text-text-muted text-xs mt-2"><i class="fas fa-chart-line text-emerald-400 mr-1"></i>
                    <?php echo $stats['transactions_today']; ?> transactions</p>
            </div>
            <div class="w-12 h-12 bg-emerald-500/20 rounded-xl flex items-center justify-center"><i
                    class="fas fa-money-bill-wave text-emerald-400 text-xl"></i></div>
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-text-secondary text-sm mb-1 font-medium">Monthly Revenue</p>
                <p class="text-3xl font-bold text-white"><?php echo number_format($stats['month_sales'], 2); ?> <span
                        class="text-sm text-text-muted"><?php echo $currency; ?></span></p>
                <p class="text-text-muted text-xs mt-2">This month</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center"><i
                    class="fas fa-calendar-alt text-blue-400 text-xl"></i></div>
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-text-secondary text-sm mb-1 font-medium">Low Stock Alert</p>
                <p class="text-3xl font-bold text-<?php echo $stats['low_stock'] > 0 ? 'warning' : 'white'; ?>">
                    <?php echo $stats['low_stock']; ?></p>
                <p class="text-text-muted text-xs mt-2">Products below reorder level</p>
            </div>
            <div class="w-12 h-12 bg-red-500/20 rounded-xl flex items-center justify-center"><i
                    class="fas fa-exclamation-triangle text-red-400 text-xl"></i></div>
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-text-secondary text-sm mb-1 font-medium">Active Customers</p>
                <p class="text-3xl font-bold text-white"><?php echo number_format($stats['customers']); ?></p>
                <p class="text-text-muted text-xs mt-2">Total registered customers</p>
            </div>
            <div class="w-12 h-12 bg-purple/20 rounded-xl flex items-center justify-center"><i
                    class="fas fa-user-friends text-purple text-xl"></i></div>
        </div>
    </div>
</div>