<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden lg:col-span-2">
    <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center">
        <h3 class="font-semibold text-white" style="font-family: 'Poppins', sans-serif;"><i
                class="fas fa-receipt text-emerald-400 mr-2"></i> Recent Transactions</h3>
        <a href="../pos/all_sales.php" class="text-xs text-amber-400 hover:text-amber-400-dark transition font-medium">View All →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full data-table">
            <thead>
                <tr class="bg-ui-hover/50">
                    <th class="px-6 py-3 text-left text-xs font-semibold text-text-secondary uppercase">Invoice</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-text-secondary uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-text-secondary uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-text-secondary uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-text-secondary uppercase">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_sales)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-text-muted">No sales recorded</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_sales as $sale): ?>
                        <tr class="border-b border-slate-700 hover:bg-ui-hover/30 transition">
                            <td class="px-6 py-3 text-sm font-medium text-white">
                                <?php echo htmlspecialchars($sale['invoice_number']); ?></td>
                            <td class="px-6 py-3 text-sm text-text-secondary">
                                <?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                            <td class="px-6 py-3 text-sm font-semibold text-amber-400">
                                <?php echo number_format($sale['total'], 2); ?>         <?php echo $currency; ?></td>
                            <td class="px-6 py-3 text-sm text-text-muted">
                                <?php echo date('d M, H:i', strtotime($sale['created_at'])); ?></td>
                            <td class="px-6 py-3 text-sm"><a href="../pos/view_sale.php?id=<?php echo $sale['id']; ?>"
                                    class="text-amber-400 hover:text-amber-400-dark transition"><i class="fas fa-eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>