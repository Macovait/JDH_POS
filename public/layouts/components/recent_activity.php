<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center">
        <h3 class="font-semibold text-white" style="font-family: 'Poppins', sans-serif;"><i
                class="fas fa-clock text-blue-400 mr-2"></i> Recent Activity</h3>
        <a href="../dashboard/activity_logs.php" class="text-xs text-amber-400 hover:text-amber-400-dark transition font-medium">View All
            →</a>
    </div>
    <div class="p-6">
        <?php if (empty($recent_activities)): ?>
            <p class="text-text-muted text-center py-4">No recent activity</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="flex items-start space-x-3 text-sm py-2 border-b border-slate-700">
                        <i class="fas fa-circle text-amber-400 text-[8px] mt-1.5"></i>
                        <div class="flex-1">
                            <p class="text-text-primary font-medium">
                                <?= htmlspecialchars($activity['description'] ?? $activity['action']) ?>
                            </p>
                            <p class="text-text-muted text-xs">
                                <?= time_ago($activity['created_at']) ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>