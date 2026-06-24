<?php
/**
 * Inventory — Settings Modal partial
 * Requires: $csrf_token, $INP, $LBL
 */
?>
<div id="settingsModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 max-w-md w-full mx-4 shadow-2xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-white flex items-center gap-2">
                <i class="fas fa-cog text-amber-400 text-sm"></i> Inventory Settings
            </h3>
            <button onclick="closeSettingsModal()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"><i class="fas fa-times text-xs"></i></button>
        </div>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="action" value="update_settings">
            <div>
                <label class="<?php echo $LBL; ?>">Global Reorder Level</label>
                <input type="number" name="global_reorder" value="5" min="0" class="<?php echo $INP; ?>">
                <p class="text-xs text-slate-500 mt-1">Default reorder level for all products in this branch</p>
            </div>
            <div class="bg-slate-900/60 border border-slate-700/60 rounded-lg p-3">
                <p class="text-xs font-medium text-slate-400 mb-2">Stock Status Definitions</p>
                <ul class="text-xs space-y-1.5 text-slate-500">
                    <li class="flex items-center gap-2"><span class="w-2 h-2 bg-emerald-400 rounded-full"></span>Good Stock: &gt; 2&times; reorder level</li>
                    <li class="flex items-center gap-2"><span class="w-2 h-2 bg-amber-400 rounded-full"></span>Medium Stock: &le; 2&times; reorder level</li>
                    <li class="flex items-center gap-2"><span class="w-2 h-2 bg-red-400 rounded-full"></span>Low Stock: &le; reorder level</li>
                    <li class="flex items-center gap-2"><span class="w-2 h-2 bg-slate-500 rounded-full"></span>Out of Stock: 0</li>
                </ul>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors">Save Settings</button>
                <button type="button" onclick="closeSettingsModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>
