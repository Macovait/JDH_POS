<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center">
        <h3 class="font-semibold text-white" style="font-family: 'Poppins', sans-serif;"><i
                class="fas fa-crown text-amber-400 mr-2"></i> Top Selling Products (30 days)</h3>
        <a href="../products/products.php" class="text-xs text-amber-400 hover:text-amber-400-dark transition font-medium">View All →</a>
    </div>
    <div class="p-6">
        <?php if (empty($top_products)): ?>
            <p class="text-text-muted text-center py-4">No sales data available</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($top_products as $product): ?>
                    <div class="flex items-center justify-between py-2 border-b border-slate-700">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-ui-hover rounded-full flex items-center justify-center"><i
                                    class="fas fa-cube text-text-muted"></i></div>
                            <span class="text-text-primary font-medium"><?php echo htmlspecialchars($product['name']); ?></span>
                        </div>
                        <span class="font-semibold text-amber-400"><?php echo $product['total_sold']; ?> units</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>