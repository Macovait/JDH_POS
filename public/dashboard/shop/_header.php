<?php
/**
 * Store Admin — Shared Header/Top Bar Partial
 * @version 3.0
 * 
 * This file is included after the sidebar in the main layout.
 * It renders the top header bar with title, actions, and search.
 * 
 * Expects variables from the calling page:
 *   - $page_title (string) - Page title
 *   - $page_subtitle (string|optional) - Page subtitle
 *   - $page_actions (string|optional) - Raw HTML for right-side buttons
 *   - $page_icon (string|optional) - Font Awesome icon class
 *   - $breadcrumbs (array|optional) - Breadcrumb items [['label'=>'Home', 'url'=>'/']]
 *   - $show_search (bool|optional) - Show search bar
 *   - $search_placeholder (string|optional) - Search placeholder text
 *   - $search_action (string|optional) - Search form action URL
 *   - $show_quick_add (bool|optional) - Show quick add button
 *   - $quick_add_url (string|optional) - Quick add button URL
 *   - $quick_add_label (string|optional) - Quick add button label
 *   - $show_refresh (bool|optional) - Show refresh button
 *   - $store_url (string|optional) - Store preview URL
 *   - $show_status_bar (bool|optional) - Show status bar below header
 *   - $status_items (array|optional) - Status items [['label'=>'Status:', 'value'=>'Active', 'color'=>'bg-emerald-400']]
 *   - $hide_topbar (bool|optional) - Hide topbar entirely
 *   - $hide_breadcrumbs (bool|optional) - Hide breadcrumbs
 */
?>
<header class="bg-slate-900/80 backdrop-blur-sm border-b border-slate-700/60 px-4 md:px-6 py-3 md:py-4 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-3 lg:gap-4 flex-shrink-0">
    
    <!-- Left: Title, Icon & Breadcrumbs -->
    <div class="flex-1 min-w-0 w-full lg:w-auto">
        <div class="flex items-center gap-3">
            <!-- Page Icon -->
            <?php if (!empty($page_icon)): ?>
            <div class="w-9 h-9 md:w-10 md:h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center flex-shrink-0 pulse-glow">
                <i class="fas <?= htmlspecialchars($page_icon) ?> text-amber-400 text-sm md:text-base"></i>
            </div>
            <?php endif; ?>
            
            <!-- Title & Subtitle -->
            <div>
                <h1 class="text-lg md:text-xl font-bold text-white tracking-tight truncate">
                    <?= htmlspecialchars($page_title ?? 'Dashboard') ?>
                </h1>
                <?php if (!empty($page_subtitle)): ?>
                <p class="text-[11px] md:text-xs text-slate-500 mt-0.5 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <?= htmlspecialchars($page_subtitle) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Breadcrumbs -->
        <?php if (!empty($breadcrumbs) && is_array($breadcrumbs) && empty($hide_breadcrumbs)): ?>
        <nav class="mt-1.5 flex items-center gap-1.5 text-xs text-slate-500 overflow-x-auto pb-0.5" aria-label="Breadcrumb">
            <a href="/admin/dashboard.php" class="hover:text-amber-400 transition-colors flex-shrink-0">
                <i class="fas fa-home text-[11px]"></i>
            </a>
            <?php foreach ($breadcrumbs as $index => $crumb):
                $isLast = $index === count($breadcrumbs) - 1;
            ?>
            <span class="text-slate-600 flex-shrink-0">/</span>
            <?php if ($isLast): ?>
            <span class="text-slate-400 font-medium truncate"><?= htmlspecialchars($crumb['label'] ?? '') ?></span>
            <?php elseif (!empty($crumb['url'])): ?>
            <a href="<?= htmlspecialchars($crumb['url']) ?>" class="hover:text-amber-400 transition-colors truncate flex-shrink">
                <?= htmlspecialchars($crumb['label'] ?? '') ?>
            </a>
            <?php else: ?>
            <span class="truncate"><?= htmlspecialchars($crumb['label'] ?? '') ?></span>
            <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Right: Actions & Search -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full lg:w-auto flex-shrink-0">
        
        <!-- Search Bar -->
        <?php if (!empty($show_search)): ?>
        <form method="GET" action="<?= htmlspecialchars($search_action ?? $_SERVER['REQUEST_URI']) ?>" class="relative flex-1 sm:w-56 md:w-64">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-[11px]"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                placeholder="<?= htmlspecialchars($search_placeholder ?? 'Search...') ?>"
                class="w-full pl-8 pr-3 py-1.5 md:py-2 text-xs md:text-sm bg-slate-800/50 border border-slate-700/60 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
        </form>
        <?php endif; ?>

        <!-- Actions Container -->
        <div class="flex items-center gap-1.5 flex-wrap justify-end">
            
            <!-- Custom Page Actions -->
            <?php if (!empty($page_actions)): ?>
            <div class="flex items-center gap-1.5">
                <?= $page_actions ?>
            </div>
            <?php endif; ?>

            <!-- Quick Add Button -->
            <?php if (!empty($show_quick_add)): ?>
            <a href="<?= htmlspecialchars($quick_add_url ?? '#') ?>" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 md:px-3.5 md:py-2 rounded-lg bg-amber-500 text-white text-[11px] md:text-xs font-medium hover:bg-amber-600 transition-all shadow-lg shadow-amber-500/20 hover:shadow-amber-500/30">
                <i class="fas fa-plus text-[10px]"></i> 
                <span class="hidden xs:inline"><?= htmlspecialchars($quick_add_label ?? 'Add New') ?></span>
                <span class="xs:hidden"><i class="fas fa-plus"></i></span>
            </a>
            <?php endif; ?>

            <!-- Refresh Button -->
            <?php if (!empty($show_refresh)): ?>
            <button onclick="location.reload()" 
                    class="inline-flex items-center justify-center w-8 h-8 md:w-9 md:h-9 rounded-lg bg-slate-800/60 border border-slate-700/60 text-slate-400 hover:bg-slate-700/60 hover:text-white hover:border-slate-600 transition-all" 
                    title="Refresh">
                <i class="fas fa-sync-alt text-[11px] md:text-xs"></i>
            </button>
            <?php endif; ?>

            <!-- Store Preview Button -->
            <?php if (!empty($store_url)): ?>
            <a href="<?= htmlspecialchars($store_url) ?>" target="_blank" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 md:px-3.5 md:py-2 rounded-lg bg-slate-800/60 border border-slate-700/60 text-slate-400 text-[11px] md:text-xs font-medium hover:bg-slate-700/60 hover:text-white hover:border-slate-600 transition-all" 
               title="View Store">
                <i class="fas fa-external-link-alt text-[10px]"></i>
                <span class="hidden sm:inline">Preview</span>
            </a>
            <?php endif; ?>

            <!-- User/Profile Dropdown (optional) -->
            <?php if (!empty($show_user_menu)): ?>
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" 
                        class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-800/60 transition-all border border-transparent hover:border-slate-700/60">
                    <div class="w-7 h-7 md:w-8 md:h-8 rounded-full bg-gradient-to-br from-amber-500/20 to-orange-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400 text-xs font-bold">
                        <?= strtoupper(substr($user_name ?? 'A', 0, 1)) ?>
                    </div>
                    <span class="hidden md:inline text-xs text-slate-300 font-medium">
                        <?= htmlspecialchars($user_name ?? 'Admin') ?>
                    </span>
                    <i class="fas fa-chevron-down text-[10px] text-slate-500"></i>
                </button>
                <!-- Dropdown menu -->
                <div x-show="open" @click.away="open = false"
                     class="absolute right-0 mt-1.5 w-48 bg-slate-800 border border-slate-700/60 rounded-xl shadow-xl py-1 z-50">
                    <a href="/admin/profile.php" class="flex items-center gap-2 px-4 py-2 text-xs text-slate-300 hover:bg-slate-700/50 transition-colors">
                        <i class="fas fa-user text-slate-500 text-[11px]"></i> Profile
                    </a>
                    <a href="/admin/settings.php" class="flex items-center gap-2 px-4 py-2 text-xs text-slate-300 hover:bg-slate-700/50 transition-colors">
                        <i class="fas fa-cog text-slate-500 text-[11px]"></i> Settings
                    </a>
                    <hr class="border-slate-700/40 my-1">
                    <a href="/logout.php" class="flex items-center gap-2 px-4 py-2 text-xs text-red-400 hover:bg-red-500/10 transition-colors">
                        <i class="fas fa-sign-out-alt text-[11px]"></i> Logout
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Status Bar (below header) -->
<?php if (!empty($show_status_bar)): ?>
<div class="bg-slate-900/50 border-b border-slate-700/60 px-4 md:px-6 py-1.5 flex items-center gap-3 md:gap-4 text-[11px] text-slate-500 flex-wrap flex-shrink-0">
    
    <!-- Status Items -->
    <?php if (!empty($status_items) && is_array($status_items)): ?>
    <?php foreach ($status_items as $item): ?>
    <div class="flex items-center gap-1.5">
        <span class="w-1.5 h-1.5 rounded-full <?= $item['color'] ?? 'bg-slate-500' ?>"></span>
        <span><?= htmlspecialchars($item['label'] ?? '') ?></span>
        <span class="font-semibold text-slate-300"><?= htmlspecialchars($item['value'] ?? '') ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Right side: Live indicator & time -->
    <div class="flex items-center gap-3 ml-auto">
        <!-- Online Status -->
        <div class="flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="text-[10px] text-slate-500">Live</span>
        </div>
        
        <!-- Separator -->
        <span class="text-slate-700">•</span>
        
        <!-- Live Clock -->
        <div class="flex items-center gap-1.5 text-[10px] text-slate-500">
            <i class="far fa-clock text-[10px]"></i>
            <span id="liveTime" class="font-mono"></span>
        </div>
        
        <!-- Memory / Stats (optional) -->
        <?php if (!empty($show_stats)): ?>
        <span class="text-slate-700">•</span>
        <div class="flex items-center gap-3 text-[10px] text-slate-500">
            <span>PHP: <?= phpversion() ?></span>
            <?php if (function_exists('memory_get_usage')): ?>
            <span>Memory: <?= round(memory_get_usage() / 1024 / 1024, 1) ?> MB</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Update live time
(function() {
    function updateLiveTime() {
        const el = document.getElementById('liveTime');
        if (el) {
            const now = new Date();
            el.textContent = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: false 
            });
        }
    }
    updateLiveTime();
    setInterval(updateLiveTime, 1000);
})();
</script>
<?php endif; ?>