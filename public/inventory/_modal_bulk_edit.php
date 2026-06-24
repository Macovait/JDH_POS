<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * Inventory — Bulk Edit Modal partial
 * Requires: $inventory_items, $csrf_token
 */
?>
<div id="bulkEditModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 max-w-2xl w-full mx-4 max-h-[80vh] flex flex-col shadow-2xl">
        <div class="flex justify-between items-center mb-4 flex-shrink-0">
            <h3 class="text-base font-semibold text-white flex items-center gap-2">
                <i class="fas fa-pen-alt text-amber-400 text-sm"></i> Bulk Edit Inventory
            </h3>
            <button onclick="closeBulkEditModal()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"><i class="fas fa-times text-xs"></i></button>
        </div>
        <form method="POST" class="flex flex-col flex-1 min-h-0">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="action" value="bulk_update">
            <div class="space-y-2 overflow-y-auto flex-1 pr-1">
                <div class="grid grid-cols-[1fr_96px_96px] gap-2 px-1 mb-1">
                    <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Product</span>
                    <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider text-center">Stock</span>
                    <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider text-center">Reorder</span>
                </div>
                <?php foreach ($inventory_items as $item): ?>
                <div class="grid grid-cols-[1fr_96px_96px] gap-2 items-center p-2.5 rounded-lg bg-slate-900/50 border border-slate-700/60">
                    <div>
                        <p class="text-white text-sm font-medium"><?php echo htmlspecialchars($item['name']); ?></p>
                        <p class="text-[11px] text-slate-500">SKU: <?php echo htmlspecialchars($item['sku'] ?: '—'); ?></p>
                    </div>
                    <input type="number" name="products[<?php echo $item['id']; ?>][stock]" value="<?php echo $item['stock']; ?>" min="0" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm text-center focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <input type="number" name="products[<?php echo $item['id']; ?>][reorder_level]" value="<?php echo $item['reorder_level']; ?>" min="0" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm text-center focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-2 pt-3 flex-shrink-0">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors">Update All</button>
                <button type="button" onclick="closeBulkEditModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>
