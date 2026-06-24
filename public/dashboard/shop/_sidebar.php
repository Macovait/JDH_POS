<?php
/**
 * Store Admin Sidebar — shared include
 * @version 3.0
 * 
 * Expects:
 *   - $store_name (string) - Store name
 *   - $nav_page (string) - Current page identifier
 *   - $storeUrl (string) - Store preview URL
 *   - $stats (array) - Stats for badges
 *   - $settings (array) - Store settings
 *   - $blocks_count (int) - Number of active blocks
 * 
 * Optional:
 *   - $user_name (string) - Current user name
 *   - $user_avatar (string) - User avatar URL
 *   - $collapsed (bool) - Start with sidebar collapsed
 */

// Default values
$settings = $settings ?? [];
$status = $status ?? ['online' => false, 'pending_orders' => 0, 'reviews_pending' => 0];
$blocks_count = $blocks_count ?? 0;
$stats = $stats ?? ['pending_orders' => 0, 'reviews_pending' => 0];

$brand_color = $settings['primary_color'] ?? '#f68b1e';
$is_online = $status['online'] ?? false;
$pending_orders = $stats['pending_orders'] ?? 0;
$reviews_pending = $stats['reviews_pending'] ?? 0;
$has_logo = !empty($settings['logo_url'] ?? '');
$has_blocks = $blocks_count > 0;
$user_name = $user_name ?? 'Admin';
$collapsed = $collapsed ?? false;

// Helper for active nav
function isActive($page, $current) {
    return $page === $current ? 'active' : '';
}

// Navigation items structure
$nav_items = [
    'overview' => [
        'label' => 'Overview',
        'items' => [
            ['id' => 'index', 'label' => 'Dashboard', 'icon' => 'fa-chart-line', 'url' => 'index.php'],
            ['id' => 'orders', 'label' => 'Orders', 'icon' => 'fa-shopping-bag', 'url' => 'orders.php', 'badge' => $pending_orders > 0 ? $pending_orders : null, 'badge_color' => 'red'],
        ]
    ],
    'catalog' => [
        'label' => 'Catalog',
        'items' => [
            ['id' => 'products', 'label' => 'Products', 'icon' => 'fa-box', 'url' => 'products.php'],
            ['id' => 'add-product', 'label' => 'Add Product', 'icon' => 'fa-plus', 'url' => base_url('products/product_form.php')],
            ['id' => 'categories', 'label' => 'Categories', 'icon' => 'fa-folder', 'url' => base_url('categories/list_categories.php')],
            ['id' => 'banners', 'label' => 'Banners', 'icon' => 'fa-image', 'url' => 'banners.php'],
            ['id' => 'blocks', 'label' => 'Content Blocks', 'icon' => 'fa-cubes', 'url' => 'blocks.php', 'badge' => $blocks_count, 'badge_color' => 'orange'],
            ['id' => 'coupons', 'label' => 'Coupons', 'icon' => 'fa-tag', 'url' => base_url('vouchers/vouchers.php')],
        ]
    ],
    'growth' => [
        'label' => 'Growth',
        'items' => [
            ['id' => 'reviews', 'label' => 'Reviews', 'icon' => 'fa-star', 'url' => 'reviews.php', 'badge' => $reviews_pending > 0 ? $reviews_pending : null, 'badge_color' => 'yellow'],
            ['id' => 'customers', 'label' => 'Customers', 'icon' => 'fa-users', 'url' => 'customers.php'],
        ]
    ],
    'configuration' => [
        'label' => 'Configuration',
        'items' => [
            ['id' => 'customize', 'label' => 'Customize Store', 'icon' => 'fa-paint-brush', 'url' => 'customize.php', 
             'badge' => !$is_online ? 'OFF' : ($has_logo ? '✓' : '!'),
             'badge_color' => !$is_online ? 'red' : ($has_logo ? 'green' : 'yellow')],
            ['id' => 'settings', 'label' => 'Store Settings', 'icon' => 'fa-sliders-h', 'url' => 'settings.php'],
            ['id' => 'shipping', 'label' => 'Shipping', 'icon' => 'fa-truck', 'url' => 'shipping.php'],
        ]
    ]
];
?>

<style>
/* Sidebar scrollbar */
.sidebar-scroll::-webkit-scrollbar {
    width: 4px;
}
.sidebar-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar-scroll::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 20px;
}
.sidebar-scroll::-webkit-scrollbar-thumb:hover {
    background: #4b5563;
}

/* Sidebar transitions */
.sidebar-transition {
    transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                min-width 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                padding 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-collapsed .nav-label {
    opacity: 0;
    width: 0;
    overflow: hidden;
    transition: opacity 0.2s ease;
}

.sidebar-collapsed .nav-icon {
    margin-right: 0;
}

.sidebar-collapsed .nav-badge {
    display: none;
}

.sidebar-collapsed .sidebar-logo-text {
    display: none;
}

.sidebar-collapsed .sidebar-store-status {
    display: none;
}

.sidebar-collapsed .sidebar-quick-stats {
    display: none;
}

.sidebar-collapsed .sidebar-user-info {
    display: none;
}

.sidebar-collapsed .sidebar-expand-btn {
    display: flex;
}

.sidebar-collapsed .sidebar-collapse-btn {
    display: none;
}

.sidebar-collapsed .sidebar-section-label {
    display: none;
}

.sidebar-expand-btn {
    display: none;
}

/* Nav item active state */
.nav-item.active {
    background: rgba(246, 139, 30, 0.15);
    color: #fbbf24;
    border-right: 2px solid #f68b1e;
}

.nav-item.active .nav-icon {
    color: #fbbf24;
}

.nav-item:hover:not(.active) {
    background: rgba(255, 255, 255, 0.05);
    color: #f1f5f9;
}

/* Badge styles */
.badge-red {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.badge-yellow {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}

.badge-green {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.badge-orange {
    background: rgba(246, 139, 30, 0.2);
    color: #fbbf24;
}

.badge-gray {
    background: rgba(100, 116, 139, 0.2);
    color: #94a3b8;
}
</style>

<aside id="sidebar" 
       class="sidebar-transition <?= $collapsed ? 'sidebar-collapsed' : '' ?> 
              w-60 min-w-[240px] bg-slate-900/95 backdrop-blur-sm 
              flex flex-col h-screen overflow-hidden flex-shrink-0 
              border-r border-slate-700/60 shadow-2xl shadow-slate-900/50 z-30">

    <!-- ==================== SIDEBAR HEADER ==================== -->
    <div class="relative px-3 py-3.5 border-b border-slate-700/60 flex-shrink-0">
        <div class="flex items-center gap-2.5">
            <!-- Logo -->
            <a href="index.php" class="flex items-center gap-2.5 no-underline group flex-1 min-w-0">
                <?php if (!empty($settings['logo_url'])): ?>
                    <img src="<?= htmlspecialchars(base_url(ltrim($settings['logo_url'], '/'))) ?>" 
                         alt="Logo" 
                         class="w-9 h-9 rounded-xl object-contain bg-white/5 p-1 flex-shrink-0 border border-slate-700/40"
                         onerror="this.style.display='none';this.nextElementSibling&&this.nextElementSibling.tagName==='DIV'&&(this.nextElementSibling.style.display='flex');">
                <?php else: ?>
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white font-black text-sm flex-shrink-0 shadow-lg pulse-glow"
                         style="background:linear-gradient(135deg, <?= $brand_color ?>, <?= $brand_color ?>cc)">
                        <?= htmlspecialchars(mb_substr($store_name ?? 'S', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <div class="sidebar-logo-text min-w-0 flex-1">
                    <div class="text-white font-bold text-sm truncate">
                        <?= htmlspecialchars(mb_substr($store_name ?? 'My Store', 0, 20)) ?>
                    </div>
                    <div class="sidebar-store-status flex items-center gap-1.5 mt-0.5">
                        <span class="inline-flex items-center gap-1 text-[10px] <?= $is_online ? 'text-emerald-400' : 'text-slate-500' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $is_online ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' ?>"></span>
                            <?= $is_online ? 'Live' : 'Offline' ?>
                        </span>
                        <?php if (!$is_online): ?>
                            <span class="text-[9px] text-amber-400">⚠</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>

            <!-- Toggle Collapse Button -->
            <button onclick="toggleSidebar()" 
                    class="sidebar-collapse-btn flex-shrink-0 w-7 h-7 rounded-lg bg-slate-800/60 border border-slate-700/60 text-slate-400 hover:bg-slate-700/60 hover:text-white transition-all flex items-center justify-center"
                    title="Collapse sidebar">
                <i class="fas fa-chevron-left text-[10px]"></i>
            </button>
            
            <button onclick="toggleSidebar()" 
                    class="sidebar-expand-btn flex-shrink-0 w-7 h-7 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 hover:bg-amber-500/20 transition-all items-center justify-center"
                    title="Expand sidebar">
                <i class="fas fa-chevron-right text-[10px]"></i>
            </button>
        </div>
    </div>

    <!-- ==================== QUICK STATS ==================== -->
    <div class="sidebar-quick-stats px-3 py-2 border-b border-slate-700/60 flex-shrink-0">
        <div class="grid grid-cols-2 gap-1.5">
            <div class="bg-slate-800/50 rounded-lg px-2 py-1.5 text-center border border-slate-700/40">
                <span class="block text-sm font-bold text-white"><?= $pending_orders ?></span>
                <span class="text-[10px] text-slate-500">Orders</span>
            </div>
            <div class="bg-slate-800/50 rounded-lg px-2 py-1.5 text-center border border-slate-700/40">
                <span class="block text-sm font-bold text-white"><?= $blocks_count ?></span>
                <span class="text-[10px] text-slate-500">Blocks</span>
            </div>
        </div>
    </div>

    <!-- ==================== USER INFO ==================== -->
    <div class="sidebar-user-info px-3 py-2 border-b border-slate-700/60 flex-shrink-0">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-500/20 to-orange-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400 text-xs font-bold flex-shrink-0">
                <?= strtoupper(substr($user_name, 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-medium text-white truncate"><?= htmlspecialchars($user_name) ?></div>
                <div class="text-[10px] text-slate-500">Administrator</div>
            </div>
            <a href="/admin/profile.php" class="text-slate-500 hover:text-slate-300 transition-colors">
                <i class="fas fa-cog text-[10px]"></i>
            </a>
        </div>
    </div>

    <!-- ==================== NAVIGATION ==================== -->
    <nav class="flex-1 overflow-y-auto sidebar-scroll px-2.5 py-2">
        <?php foreach ($nav_items as $section_key => $section): ?>
            <!-- Section Label -->
            <div class="sidebar-section-label text-[10px] font-bold tracking-widest uppercase text-slate-500 px-3 mt-3 mb-1 <?= $section_key === 'overview' ? 'mt-0' : '' ?>">
                <?= htmlspecialchars($section['label']) ?>
            </div>
            
            <!-- Section Items -->
            <?php foreach ($section['items'] as $item): 
                $active = isActive($item['id'], $nav_page);
                $has_badge = isset($item['badge']) && $item['badge'] !== null && $item['badge'] !== false;
                $badge_color = $item['badge_color'] ?? 'gray';
            ?>
            <a href="<?= htmlspecialchars($item['url']) ?>" 
               class="nav-item flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium no-underline transition-all duration-200
                      <?= $active ? 'active' : 'text-slate-400 hover:text-white hover:bg-white/5' ?>">
                
                <i class="nav-icon fas <?= htmlspecialchars($item['icon']) ?> w-4 text-center <?= $active ? 'text-amber-400' : 'text-slate-500' ?>"></i>
                
                <span class="nav-label flex-1 truncate"><?= htmlspecialchars($item['label']) ?></span>
                
                <?php if ($has_badge): ?>
                    <span class="nav-badge text-[9px] font-extrabold px-1.5 py-0.5 rounded-full badge-<?= $badge_color ?> <?= is_numeric($item['badge']) && $item['badge'] === 0 ? 'opacity-50' : '' ?>">
                        <?= htmlspecialchars($item['badge']) ?>
                    </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <!-- ==================== SIDEBAR FOOTER ==================== -->
    <div class="px-2.5 py-2 border-t border-slate-700/60 flex-shrink-0 space-y-0.5">
        <!-- View Store -->
        <a href="<?= htmlspecialchars($storeUrl ?? '#') ?>" target="_blank" 
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium no-underline transition-all duration-200 text-amber-400 hover:text-amber-300 hover:bg-amber-500/10">
            <i class="fas fa-external-link-alt w-4 text-center text-amber-400/70"></i>
            <span class="nav-label">View Store</span>
        </a>
        
        <!-- Back to POS -->
        <a href="<?= base_url('dashboard/home.php') ?>" 
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium no-underline transition-all duration-200 text-slate-400 hover:text-white hover:bg-white/5">
            <i class="fas fa-arrow-left w-4 text-center text-slate-500"></i>
            <span class="nav-label">Back to POS</span>
        </a>
        
        <!-- Logout -->
        <a href="<?= base_url('auth/logout.php') ?>" 
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium no-underline transition-all duration-200 text-slate-500 hover:text-red-400 hover:bg-red-500/10">
            <i class="fas fa-sign-out-alt w-4 text-center text-slate-500"></i>
            <span class="nav-label">Logout</span>
        </a>
    </div>
</aside>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
// Toggle sidebar collapse/expand
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    
    sidebar.classList.toggle('sidebar-collapsed');
    
    // Save state to localStorage
    const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
    localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
}

// Restore sidebar state on load
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        sidebar.classList.add('sidebar-collapsed');
    }
});

// Keyboard shortcut: Alt+S to toggle sidebar
document.addEventListener('keydown', function(e) {
    if (e.altKey && e.key === 's') {
        e.preventDefault();
        toggleSidebar();
    }
});

// Console message
console.log('%c 🏪 Store Admin Sidebar ', 'background: #0b1120; color: #fbbf24; font-size: 12px; padding: 4px 8px; border: 1px solid #f68b1e; border-radius: 4px;');
</script>