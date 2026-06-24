<?php
/**
 * Store Admin — Shared Top Bar Partial
 * @version 3.0
 * 
 * Expects:
 *   - $page_title (string) - Page title
 *   - $page_subtitle (string|optional) - Page subtitle
 *   - $page_actions (string|optional) - Raw HTML for right-side buttons
 *   - $breadcrumbs (array|optional) - Breadcrumb items [['label'=>'Home', 'url'=>'/']]
 *   - $show_search (bool|optional) - Show search bar
 *   - $search_placeholder (string|optional) - Search placeholder text
 *   - $search_action (string|optional) - Search form action URL
 */
?>
<header class="bg-slate-900/80 backdrop-blur-sm border-b border-slate-700/60 px-6 py-4 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 flex-shrink-0">
    <!-- Left: Title & Breadcrumbs -->
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-3">
            <?php if (!empty($page_icon)): ?>
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center flex-shrink-0">
                <i class="fas <?= htmlspecialchars($page_icon) ?> text-amber-400 text-sm"></i>
            </div>
            <?php endif; ?>
            <div>
                <h1 class="text-xl font-bold text-white tracking-tight"><?= htmlspecialchars($page_title) ?></h1>
                <?php if (!empty($page_subtitle)): ?>
                <p class="text-xs text-slate-500 mt-0.5 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <?= htmlspecialchars($page_subtitle) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Breadcrumbs -->
        <?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
        <nav class="mt-1.5 flex items-center gap-1.5 text-xs text-slate-500" aria-label="Breadcrumb">
            <a href="/admin/dashboard.php" class="hover:text-amber-400 transition-colors">
                <i class="fas fa-home"></i>
            </a>
            <?php foreach ($breadcrumbs as $index => $crumb):
                $isLast = $index === count($breadcrumbs) - 1;
            ?>
            <span class="text-slate-600">/</span>
            <?php if ($isLast): ?>
            <span class="text-slate-400 font-medium"><?= htmlspecialchars($crumb['label'] ?? '') ?></span>
            <?php elseif (!empty($crumb['url'])): ?>
            <a href="<?= htmlspecialchars($crumb['url']) ?>" class="hover:text-amber-400 transition-colors">
                <?= htmlspecialchars($crumb['label'] ?? '') ?>
            </a>
            <?php else: ?>
            <span><?= htmlspecialchars($crumb['label'] ?? '') ?></span>
            <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Right: Actions & Search -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full lg:w-auto">
        <!-- Search Bar -->
        <?php if (!empty($show_search)): ?>
        <form method="GET" action="<?= htmlspecialchars($search_action ?? $_SERVER['REQUEST_URI']) ?>" class="relative flex-1 sm:w-64">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                placeholder="<?= htmlspecialchars($search_placeholder ?? 'Search...') ?>"
                class="w-full pl-9 pr-4 py-2 text-xs bg-slate-800/50 border border-slate-700/60 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
        </form>
        <?php endif; ?>

        <!-- Actions -->
        <?php if (!empty($page_actions)): ?>
        <div class="flex items-center gap-2 flex-wrap">
            <?= $page_actions ?>
        </div>
        <?php endif; ?>

        <!-- User Menu / Quick Actions -->
        <div class="flex items-center gap-1.5">
            <!-- Quick Add Button -->
            <?php if (!empty($show_quick_add)): ?>
            <a href="<?= htmlspecialchars($quick_add_url ?? '#') ?>" 
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500 text-white text-xs font-medium hover:bg-amber-600 transition-all shadow-lg shadow-amber-500/20">
                <i class="fas fa-plus text-[10px]"></i> <?= htmlspecialchars($quick_add_label ?? 'Add New') ?>
            </a>
            <?php endif; ?>

            <!-- Refresh Button -->
            <?php if (!empty($show_refresh)): ?>
            <button onclick="location.reload()" 
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800/60 border border-slate-700/60 text-slate-400 hover:bg-slate-700/60 hover:text-white hover:border-slate-600 transition-all" 
                    title="Refresh">
                <i class="fas fa-sync-alt text-xs"></i>
            </button>
            <?php endif; ?>

            <!-- Store View Button -->
            <?php if (!empty($store_url)): ?>
            <a href="<?= htmlspecialchars($store_url) ?>" target="_blank" 
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-800/60 border border-slate-700/60 text-slate-400 text-xs font-medium hover:bg-slate-700/60 hover:text-white hover:border-slate-600 transition-all" 
               title="View Store">
                <i class="fas fa-external-link-alt text-[10px]"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Optional: Status Bar -->
<?php if (!empty($show_status_bar)): ?>
<div class="bg-slate-900/50 border-b border-slate-700/60 px-6 py-1.5 flex items-center gap-4 text-xs text-slate-500 flex-wrap">
    <?php if (!empty($status_items) && is_array($status_items)): ?>
    <?php foreach ($status_items as $item): ?>
    <div class="flex items-center gap-1.5">
        <span class="w-1.5 h-1.5 rounded-full <?= $item['color'] ?? 'bg-slate-500' ?>"></span>
        <span><?= htmlspecialchars($item['label'] ?? '') ?></span>
        <span class="font-semibold text-slate-300"><?= htmlspecialchars($item['value'] ?? '') ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Live Indicator -->
    <div class="flex items-center gap-1.5 ml-auto">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
        <span class="text-[10px] text-slate-500">Live</span>
        <span class="text-[10px] text-slate-600">•</span>
        <span class="text-[10px] text-slate-500" id="liveTime"></span>
    </div>
</div>

<script>
// Update live time
function updateLiveTime() {
    const el = document.getElementById('liveTime');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
}
updateLiveTime();
setInterval(updateLiveTime, 1000);
</script>
<?php endif; ?>