<?php if ($stats['low_stock'] > 0): ?>
    <div class="mt-6 bg-red-500/10 border border-red-500/30 rounded-xl p-4">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                <div>
                    <p class="text-white font-medium">Low Stock Alert</p>
                    <p class="text-text-secondary text-sm"><?php echo $stats['low_stock']; ?> products are below reorder
                        level. Please restock soon.</p>
                </div>
            </div>
            <a href="../inventory/inventory_report.php"
                class="px-4 py-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-lg transition text-sm border border-red-500/30 font-medium">View
                Inventory</a>
        </div>
    </div>
<?php endif; ?>