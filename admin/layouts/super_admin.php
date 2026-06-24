<?php
/**
 * Super Admin Layout - JDH POS SaaS
 * Pure Tailwind CSS - Matches all_sales.php style
 * 
 * Usage:
 *   $page_title = 'Dashboard';
 *   $current_page = 'dashboard';
 *   ob_start();
 *   // ... page content ...
 *   $page_content = ob_get_clean();
 *   require_once __DIR__ . '/layouts/super_admin.php';
 */

// Prevent direct access
if (!isset($page_content)) {
    die('This file should not be accessed directly.');
}

$page_title = $page_title ?? 'Super Admin';
$current_page = $current_page ?? 'dashboard';

// Build super admin menu structure
$sidebarSections = [
    [
        'label' => 'Overview',
        'items' => [
            ['icon' => 'fa-chart-pie', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'page' => 'dashboard'],
            ['icon' => 'fa-building', 'label' => 'Tenants', 'url' => 'companies.php', 'page' => 'companies'],
            ['icon' => 'fa-user-check', 'label' => 'Onboarding', 'url' => 'onboarding.php', 'page' => 'onboarding'],
        ]
    ],
    [
        'label' => 'Billing & Plans',
        'items' => [
            ['icon' => 'fa-layer-group', 'label' => 'Pricing Plans', 'url' => 'plans.php', 'page' => 'plans'],
            ['icon' => 'fa-credit-card', 'label' => 'Subscriptions', 'url' => 'subscriptions.php', 'page' => 'subscriptions'],
            ['icon' => 'fa-history', 'label' => 'Billing History', 'url' => 'subscription_history.php', 'page' => 'subscription_history'],
            ['icon' => 'fa-coins', 'label' => 'Revenue', 'url' => 'revenue.php', 'page' => 'revenue'],
            ['icon' => 'fa-wallet', 'label' => 'Credits', 'url' => 'credits.php', 'page' => 'credits'],
            ['icon' => 'fa-sync-alt', 'label' => 'Dunning', 'url' => 'dunning.php', 'page' => 'dunning'],
        ]
    ],
    [
        'label' => 'Features & Usage',
        'items' => [
            ['icon' => 'fa-flag', 'label' => 'Features', 'url' => 'features.php', 'page' => 'features'],
            ['icon' => 'fa-tachometer-alt', 'label' => 'Usage Metering', 'url' => 'usage.php', 'page' => 'usage'],
            ['icon' => 'fa-chart-line', 'label' => 'Analytics', 'url' => 'analytics.php', 'page' => 'analytics'],
        ]
    ],
    [
        'label' => 'Platform',
        'items' => [
            ['icon' => 'fa-headset', 'label' => 'Support Access', 'url' => 'support-access.php', 'page' => 'support_access'],
            ['icon' => 'fa-ticket-alt', 'label' => 'Support Tickets', 'url' => 'support_tickets.php', 'page' => 'support_tickets'],
            ['icon' => 'fa-shield-alt', 'label' => 'Audit Logs', 'url' => 'audit_logs.php', 'page' => 'audit_logs'],
            ['icon' => 'fa-lock', 'label' => 'Security', 'url' => 'security.php', 'page' => 'security'],
            ['icon' => 'fa-heartbeat', 'label' => 'Monitoring', 'url' => 'monitoring.php', 'page' => 'monitoring'],
            ['icon' => 'fa-stethoscope', 'label' => 'Platform Health', 'url' => 'platform_health.php', 'page' => 'platform_health'],
        ]
    ],
    [
        'label' => 'System',
        'items' => [
            ['icon' => 'fa-puzzle-piece', 'label' => 'Plugins', 'url' => 'plugins.php', 'page' => 'plugins'],
            ['icon' => 'fa-user-shield', 'label' => 'Admin Roles', 'url' => 'roles.php', 'page' => 'roles'],
            ['icon' => 'fa-bell', 'label' => 'Notifications', 'url' => 'notifications.php', 'page' => 'notifications'],
            ['icon' => 'fa-envelope', 'label' => 'Email Templates', 'url' => 'email_templates.php', 'page' => 'email_templates'],
            ['icon' => 'fa-paint-brush', 'label' => 'Tenant Branding', 'url' => 'tenant_branding.php', 'page' => 'tenant_branding'],
            ['icon' => 'fa-bolt', 'label' => 'Bulk Operations', 'url' => 'bulk_operations.php', 'page' => 'bulk_operations'],
            ['icon' => 'fa-cog', 'label' => 'Settings', 'url' => 'settings.php', 'page' => 'settings'],
        ]
    ],
];

// Get admin info
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['user']['name'] ?? 'Super Admin';
$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> | JDH POS Super Admin</title>
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        html{font-size:14px}
        body{background:#0b1120;font-family:'Inter',sans-serif}

        /* ── Sidebar ── */
        .sidebar{position:fixed;left:0;top:0;bottom:0;width:64px;z-index:50;background:#0b1120;border-right:1px solid rgba(255,255,255,.06);transition:width .2s ease;overflow:hidden}
        .sidebar:hover{width:220px}

        .sidebar .sidebar-text,.sidebar .section-label,.sidebar .brand-text,.sidebar .user-info{display:none}
        .sidebar:hover .sidebar-text,.sidebar:hover .brand-text,.sidebar:hover .user-info{display:inline}
        .sidebar:hover .section-label{display:block}

        .sidebar .nav-item,.sidebar .user-profile,.sidebar .quick-nav{justify-content:center}
        .sidebar:hover .nav-item,.sidebar:hover .user-profile{justify-content:flex-start}
        .sidebar:hover .quick-nav{justify-content:space-between}

        .sidebar-logo-section{height:64px;display:flex;align-items:center;justify-content:center}
        .sidebar:hover .sidebar-logo-section{justify-content:flex-start;padding-left:14px}

        /* Tooltip on collapsed icons */
        .nav-item{position:relative}
        .nav-item[data-tooltip]::after{content:attr(data-tooltip);position:absolute;left:56px;top:50%;transform:translateY(-50%);background:#1e293b;color:#fbbf24;padding:3px 8px;border-radius:6px;font-size:11px;white-space:nowrap;opacity:0;visibility:hidden;transition:opacity .15s;z-index:100;pointer-events:none;border:1px solid #334155;box-shadow:0 4px 12px rgba(0,0,0,.4)}
        .sidebar .nav-item[data-tooltip]:hover::after{opacity:1;visibility:visible}
        .sidebar:hover .nav-item[data-tooltip]::after{display:none}

        /* Active nav */
        .nav-item.active{background:rgba(251,191,36,.12);color:#fbbf24}

        /* Submenu slide */
        .slide-menu{max-height:0;overflow:hidden;transition:max-height .25s ease-out}
        .slide-menu.open{max-height:600px;transition:max-height .3s ease-in}

        /* Main shifts right with sidebar */
        .main-content{margin-left:64px;transition:margin-left .2s ease;min-height:100vh}
        .sidebar:hover~.main-content,.sidebar:hover+.main-content{margin-left:220px}

        /* Scrollbar */
        ::-webkit-scrollbar{width:4px;height:4px}
        ::-webkit-scrollbar-track{background:transparent}
        ::-webkit-scrollbar-thumb{background:rgba(251,191,36,.25);border-radius:99px}
        ::-webkit-scrollbar-thumb:hover{background:rgba(251,191,36,.45)}

        /* Animations */
        @keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
        .animate-fade-in{animation:fadeIn .35s ease-out}

        /* Mobile */
        @media(max-width:1024px){
            .sidebar{left:-220px;width:220px!important}
            .sidebar.open{left:0;box-shadow:4px 0 24px rgba(0,0,0,.5)}
            .sidebar .sidebar-text,.sidebar .section-label,.sidebar .brand-text,.sidebar .user-info{display:inline!important}
            .sidebar .section-label{display:block!important}
            .sidebar .nav-item,.sidebar .user-profile{justify-content:flex-start!important}
            .sidebar .quick-nav{justify-content:flex-start!important;gap:8px}
            .sidebar-logo-section{justify-content:flex-start!important;padding-left:14px!important}
            .sidebar-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:40;display:none;backdrop-filter:blur(2px)}
            .sidebar-overlay.show{display:block}
            .main-content{margin-left:0!important}
            .mobile-menu-btn{display:flex;position:fixed;top:14px;left:14px;z-index:60;width:38px;height:38px;background:#1e293b;border:1px solid #334155;border-radius:8px;align-items:center;justify-content:center;cursor:pointer}
            .grid-cols-4{grid-template-columns:repeat(2,minmax(0,1fr))}
            .lg\\:grid-cols-8{grid-template-columns:repeat(2,minmax(0,1fr))}
            /* Better touch targets */
            button,a{min-height:44px;min-width:44px}
        }
        @media(min-width:1025px){.mobile-menu-btn{display:none}}

        /* Floating Action Button */
        .fab-container{position:fixed;bottom:24px;right:24px;z-index:50}
        .fab-main{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#fbbf24 0%,#f59e0b 100%);color:#0f172a;border:none;box-shadow:0 4px 14px rgba(251,191,36,.4);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s ease}
        .fab-main:hover{transform:scale(1.1);box-shadow:0 6px 20px rgba(251,191,36,.5)}
        .fab-menu{position:absolute;bottom:70px;right:0;display:flex;flex-direction:column;gap:12px;opacity:0;visibility:hidden;transform:translateY(20px);transition:all .3s ease}
        .fab-container.open .fab-menu{opacity:1;visibility:visible;transform:translateY(0)}
        .fab-item{display:flex;align-items:center;gap:12px;padding:12px 16px;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:13px;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,.3);cursor:pointer;transition:all .2s ease}
        .fab-item:hover{background:#334155;color:#fbbf24}
        .fab-item i{width:20px;text-align:center}
        .fab-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:49;opacity:0;visibility:hidden;transition:all .3s ease}
        .fab-container.open .fab-backdrop{opacity:1;visibility:visible}
    </style>
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body class="bg-[#0b1120] font-['Inter'] antialiased text-slate-300">

<?php
// Support Mode Indicator
$inSupportMode = !empty($_SESSION['support_mode']);
if ($inSupportMode):
    $supportCompany = $_SESSION['support_company_name'] ?? 'Unknown';
    $supportExpires = $_SESSION['support_expires_at'] ?? null;
    $timeRemaining = $supportExpires ? strtotime($supportExpires) - time() : 0;
    $minutesRemaining = max(0, floor($timeRemaining / 60));
?>
<!-- Support Mode Banner -->
<div class="fixed top-0 left-0 right-0 h-10 bg-gradient-to-r from-amber-600 via-orange-500 to-amber-600 z-[60] flex items-center justify-center px-4 shadow-lg">
    <div class="flex items-center gap-3 text-white text-sm font-medium">
        <i class="fas fa-user-secret"></i>
        <span>Support Mode: <strong><?= htmlspecialchars($supportCompany) ?></strong></span>
        <span class="px-2 py-0.5 rounded bg-white/20 text-xs">
            <?= $minutesRemaining ?>m remaining
        </span>
        <a href="support-exit.php" class="ml-2 px-3 py-1 rounded bg-white/20 hover:bg-white/30 text-xs transition-colors">
            <i class="fas fa-sign-out-alt mr-1"></i>Exit
        </a>
    </div>
</div>
<style>
    .sidebar { top: 40px !important; }
    .main-content { margin-top: 40px !important; }
    .mobile-menu-btn { top: 54px !important; }
</style>
<?php endif; ?>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn text-white" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Overlay -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Slim Sidebar -->
<aside class="sidebar flex flex-col" id="sidebar">
    <!-- Amber top accent -->
    <div class="h-0.5 bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-500 flex-shrink-0"></div>

    <!-- Logo -->
    <div class="sidebar-logo-section flex-shrink-0">
        <a href="dashboard.php" class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-yellow-500 flex items-center justify-center shadow-lg flex-shrink-0 ring-1 ring-amber-500/30">
                <i class="fas fa-crown text-slate-900 text-xs"></i>
            </div>
            <div class="brand-text min-w-0">
                <p class="text-white font-semibold text-sm truncate leading-tight">JDH POS</p>
                <p class="text-slate-500 text-[10px] leading-tight">Super Admin</p>
            </div>
        </a>
    </div>

    <!-- Scrollable Content -->
    <div class="flex-1 overflow-y-auto overflow-x-hidden">
        
        <!-- User Profile -->
        <div class="px-2.5 py-2.5 border-b border-white/5">
            <div class="user-profile flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-500 to-yellow-500 flex items-center justify-center flex-shrink-0 overflow-hidden ring-2 ring-amber-500/20">
                    <span class="text-slate-900 font-bold text-xs"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></span>
                </div>
                <div class="user-info min-w-0">
                    <p class="text-white font-medium text-xs truncate leading-tight"><?php echo htmlspecialchars($admin_name); ?></p>
                    <p class="text-slate-500 text-[10px] capitalize truncate leading-tight"><?php echo htmlspecialchars($admin_role); ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Nav Circles -->
        <div class="quick-nav px-2.5 py-2 flex gap-1.5 border-b border-white/5">
            <a href="support_tickets.php" class="w-7 h-7 rounded-lg bg-white/5 flex items-center justify-center text-slate-400 hover:bg-amber-500/15 hover:text-amber-400 transition-all flex-shrink-0" data-tooltip="Tickets"><i class="fas fa-ticket-alt text-[10px]"></i></a>
            <a href="audit_logs.php" class="w-7 h-7 rounded-lg bg-white/5 flex items-center justify-center text-slate-400 hover:bg-amber-500/15 hover:text-amber-400 transition-all flex-shrink-0" data-tooltip="Audit"><i class="fas fa-shield-alt text-[10px]"></i></a>
            <a href="settings.php" class="w-7 h-7 rounded-lg bg-white/5 flex items-center justify-center text-slate-400 hover:bg-amber-500/15 hover:text-amber-400 transition-all flex-shrink-0" data-tooltip="Settings"><i class="fas fa-cog text-[10px]"></i></a>
        </div>

        <!-- Navigation -->
        <nav class="px-2 py-2 space-y-2.5">
            <?php foreach ($sidebarSections as $section): ?>
            <div>
                <div class="section-label text-[9px] font-bold text-slate-600 uppercase tracking-widest px-2 mb-1.5"><?php echo $section['label']; ?></div>
                <div class="space-y-1">
                    <?php foreach ($section['items'] as $item):
                        $isActive = $current_page === $item['page'];
                    ?>
                        <?php
                        $navBase  = 'nav-item flex items-center gap-2.5 px-2 py-1.5 rounded-lg text-slate-400 hover:bg-white/5 hover:text-amber-400 transition-all w-full';
                        ?>
                        <a href="<?php echo $item['url']; ?>" class="<?php echo $navBase; ?> $isActive ? 'active' : '';" data-tooltip="<?php echo htmlspecialchars($item['label']); ?>">
                            <i class="fas <?php echo $item['icon']; ?> text-sm w-4 flex-shrink-0"></i>
                            <span class="sidebar-text text-xs font-medium"><?php echo $item['label']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </nav>

    </div>

    <!-- Sign Out (outside scroll area, always visible) -->
    <div class="p-2 border-t border-white/5 flex-shrink-0">
        <a href="logout.php" class="nav-item flex items-center gap-2.5 px-2 py-1.5 rounded-lg text-red-400/70 hover:bg-red-500/10 hover:text-red-400 transition-all" data-tooltip="Sign Out">
            <i class="fas fa-arrow-right-from-bracket text-sm w-4 flex-shrink-0"></i>
            <span class="sidebar-text text-xs font-medium">Sign Out</span>
        </a>
    </div>
</aside>

<!-- Main Content Area -->
<div class="main-content flex flex-col">
    <!-- Header -->
    <header class="h-14 bg-slate-900/80 backdrop-blur border-b border-white/5 flex items-center justify-between px-4 sticky top-0 z-30">
        <div class="flex items-center gap-3 min-w-0">
            <!-- Breadcrumb Navigation -->
            <nav class="flex items-center gap-2 text-sm min-w-0" aria-label="Breadcrumb">
                <a href="dashboard.php" class="text-slate-400 hover:text-white transition-colors flex-shrink-0" title="Dashboard">
                    <i class="fas fa-home"></i>
                </a>
                <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <span class="text-slate-600 flex-shrink-0">/</span>
                        <?php if ($index === count($breadcrumbs) - 1 || !isset($crumb['url'])): ?>
                            <span class="text-white font-medium truncate"><?php echo htmlspecialchars($crumb['label']); ?></span>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($crumb['url']); ?>" class="text-slate-400 hover:text-white transition-colors truncate">
                                <?php echo htmlspecialchars($crumb['label']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-slate-600 flex-shrink-0">/</span>
                    <span class="text-white font-medium truncate"><?php echo htmlspecialchars($page_title); ?></span>
                <?php endif; ?>
            </nav>
        </div>
        <div class="flex items-center gap-3">
            <!-- Global Search Trigger -->
            <button onclick="openGlobalSearch()" class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-xs hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-search text-xs"></i>
                <span class="text-slate-500">Search...</span>
                <kbd class="ml-1 px-1.5 py-0.5 rounded bg-slate-900 text-slate-500 text-[10px] font-mono">/</kbd>
            </button>
            
            <!-- Mobile Search Button -->
            <button onclick="openGlobalSearch()" class="sm:hidden inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-search text-xs"></i>
            </button>
            
            <!-- Notifications Bell -->
            <div class="relative">
                <button onclick="toggleNotifications()" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white transition-colors relative">
                    <i class="fas fa-bell text-xs"></i>
                    <span id="notificationBadge" class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-[9px] font-bold text-white flex items-center justify-center hidden">0</span>
                </button>
                
                <!-- Notifications Dropdown -->
                <div id="notificationsDropdown" class="hidden absolute right-0 top-full mt-2 w-80 bg-slate-900 border border-slate-700 rounded-xl shadow-2xl z-50 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700">
                        <h3 class="text-sm font-semibold text-white">Notifications</h3>
                        <button onclick="markAllNotificationsRead()" class="text-xs text-amber-400 hover:text-amber-300">Mark all read</button>
                    </div>
                    <div id="notificationsList" class="max-h-80 overflow-y-auto">
                        <div class="px-4 py-8 text-center text-slate-500 text-sm">
                            <i class="fas fa-bell-slash mb-2 block text-lg"></i>
                            No notifications
                        </div>
                    </div>
                    <div class="px-4 py-2 bg-slate-800/50 border-t border-slate-700">
                        <a href="notifications.php" class="text-xs text-slate-400 hover:text-white flex items-center justify-center gap-1">
                            View all <i class="fas fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <a href="../dashboard/home.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-xs font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-store text-xs"></i> Back to App
            </a>
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-800/50 border border-slate-700/50">
                <div class="w-6 h-6 rounded-full bg-gradient-to-br from-amber-500 to-yellow-500 flex items-center justify-center">
                    <span class="text-slate-900 font-bold text-xs"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></span>
                </div>
                <span class="text-xs text-slate-300 hidden sm:inline"><?php echo htmlspecialchars($admin_name); ?></span>
            </div>
        </div>
    </header>

    <!-- Global Search Modal -->
    <div id="globalSearchModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-start justify-center pt-[10vh]" onclick="closeGlobalSearchOnBackdrop(event)">
        <div class="w-full max-w-2xl mx-4 bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden animate-fade-in" onclick="event.stopPropagation()">
            <!-- Search Input -->
            <div class="flex items-center gap-3 px-4 py-4 border-b border-slate-700">
                <i class="fas fa-search text-slate-400 text-lg"></i>
                <input type="text" id="globalSearchInput" 
                    placeholder="Search tenants, users, subscriptions..." 
                    class="flex-1 bg-transparent border-none outline-none text-white text-base placeholder-slate-500"
                    oninput="performGlobalSearch(this.value)"
                    onkeydown="handleSearchKeydown(event)">
                <kbd class="px-2 py-1 rounded bg-slate-800 text-slate-500 text-xs font-mono">ESC</kbd>
            </div>
            
            <!-- Search Results -->
            <div id="globalSearchResults" class="max-h-[60vh] overflow-y-auto p-2">
                <!-- Default suggestions -->
                <div id="searchSuggestions" class="space-y-1">
                    <div class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">Quick Navigation</div>
                    <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors group">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                            <i class="fas fa-chart-pie text-amber-400 text-xs"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-white">Dashboard</div>
                            <div class="text-xs text-slate-500">Overview & analytics</div>
                        </div>
                        <i class="fas fa-arrow-right text-slate-600 group-hover:text-amber-400 text-xs"></i>
                    </a>
                    <a href="companies.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors group">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                            <i class="fas fa-building text-blue-400 text-xs"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-white">Tenants</div>
                            <div class="text-xs text-slate-500">Manage all companies</div>
                        </div>
                        <i class="fas fa-arrow-right text-slate-600 group-hover:text-blue-400 text-xs"></i>
                    </a>
                    <a href="subscriptions.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors group">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                            <i class="fas fa-credit-card text-emerald-400 text-xs"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-white">Subscriptions</div>
                            <div class="text-xs text-slate-500">Billing & plans</div>
                        </div>
                        <i class="fas fa-arrow-right text-slate-600 group-hover:text-emerald-400 text-xs"></i>
                    </a>
                    <a href="support_tickets.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors group">
                        <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center">
                            <i class="fas fa-ticket-alt text-purple-400 text-xs"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-white">Support Tickets</div>
                            <div class="text-xs text-slate-500">Customer support</div>
                        </div>
                        <i class="fas fa-arrow-right text-slate-600 group-hover:text-purple-400 text-xs"></i>
                    </a>
                </div>
                
                <!-- Dynamic Results (hidden by default) -->
                <div id="searchResultsList" class="hidden space-y-1"></div>
                
                <!-- Loading State -->
                <div id="searchLoading" class="hidden py-8 text-center">
                    <div class="inline-flex items-center gap-2 text-slate-500">
                        <i class="fas fa-circle-notch fa-spin"></i>
                        <span class="text-sm">Searching...</span>
                    </div>
                </div>
                
                <!-- No Results -->
                <div id="searchNoResults" class="hidden py-8 text-center">
                    <div class="w-12 h-12 rounded-full bg-slate-800 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-search text-slate-500 text-lg"></i>
                    </div>
                    <p class="text-sm text-slate-400">No results found</p>
                    <p class="text-xs text-slate-500 mt-1">Try a different search term</p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-4 py-3 bg-slate-800/50 border-t border-slate-700 flex items-center justify-between text-xs text-slate-500">
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 rounded bg-slate-700 font-mono">↑↓</kbd> Navigate</span>
                    <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 rounded bg-slate-700 font-mono">Enter</kbd> Select</span>
                </div>
                <span>Global Search</span>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <main class="flex-1 p-4 md:p-6 animate-fade-in">
        <?php echo $page_content; ?>
    </main>
</div>

<!-- Floating Action Button (Quick Actions) -->
<div class="fab-container" id="fabContainer">
    <div class="fab-backdrop" onclick="toggleFab()"></div>
    <div class="fab-menu">
        <a href="companies.php?action=create" class="fab-item">
            <i class="fas fa-building text-blue-400"></i>
            <span>Add Tenant</span>
        </a>
        <a href="plans.php" class="fab-item">
            <i class="fas fa-layer-group text-emerald-400"></i>
            <span>Manage Plans</span>
        </a>
        <a href="support-access.php" class="fab-item">
            <i class="fas fa-user-secret text-purple-400"></i>
            <span>Support Access</span>
        </a>
        <a href="analytics.php" class="fab-item">
            <i class="fas fa-chart-line text-amber-400"></i>
            <span>View Analytics</span>
        </a>
    </div>
    <button class="fab-main" onclick="toggleFab()" aria-label="Quick Actions">
        <i class="fas fa-plus text-xl" id="fabIcon"></i>
    </button>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-[200] hidden items-center justify-center" onclick="closeConfirmModalOnBackdrop(event)">
    <div class="w-full max-w-md mx-4 bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden animate-fade-in" onclick="event.stopPropagation()">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div id="confirmIcon" class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 id="confirmTitle" class="text-lg font-semibold text-white mb-1">Confirm Action</h3>
                    <p id="confirmMessage" class="text-sm text-slate-400 leading-relaxed">Are you sure you want to proceed with this action?</p>
                </div>
            </div>
            
            <div class="flex items-center justify-end gap-3 mt-6">
                <button onclick="closeConfirmModal()" class="px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                    Cancel
                </button>
                <button id="confirmButton" onclick="executeConfirmedAction()" class="px-4 py-2 rounded-lg bg-red-500 hover:bg-red-400 text-white text-sm font-medium transition-colors">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('show');
}

document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
    document.getElementById('sidebar')?.classList.remove('open');
    this.classList.remove('show');
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('sidebar')?.classList.remove('open');
        document.getElementById('sidebarOverlay')?.classList.remove('show');
    }
});

// Toast notification function (global)
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'fixed bottom-6 right-6 z-50 flex flex-col gap-2';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-emerald-500/90' : type === 'error' ? 'bg-red-500/90' : 'bg-amber-500/90';
    const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
    
    toast.className = `flex items-center gap-3 px-4 py-2 rounded-lg shadow-lg text-sm text-white animate-fade-in ${bgColor}`;
    toast.innerHTML = `<i class="fas ${icon}"></i><span>${message}</span><button onclick="this.parentElement.remove()" class="ml-4 text-white/70 hover:text-white"><i class="fas fa-times"></i></button>`;
    
    const container = document.getElementById('toastContainer');
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

window.showToast = showToast;

// Floating Action Button Toggle
function toggleFab() {
    const container = document.getElementById('fabContainer');
    const icon = document.getElementById('fabIcon');
    container.classList.toggle('open');
    if (container.classList.contains('open')) {
        icon.classList.remove('fa-plus');
        icon.classList.add('fa-times');
    } else {
        icon.classList.remove('fa-times');
        icon.classList.add('fa-plus');
    }
}
window.toggleFab = toggleFab;

// Close FAB on scroll (optional UX improvement)
let scrollTimeout;
window.addEventListener('scroll', () => {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
        const container = document.getElementById('fabContainer');
        if (container.classList.contains('open')) {
            toggleFab();
        }
    }, 150);
}, { passive: true });

// Global Search Functions
let searchTimeout = null;
let selectedResultIndex = -1;

function openGlobalSearch() {
    const modal = document.getElementById('globalSearchModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        document.getElementById('globalSearchInput').focus();
    }, 50);
    resetSearchState();
}

function closeGlobalSearch() {
    const modal = document.getElementById('globalSearchModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    resetSearchState();
}

function closeGlobalSearchOnBackdrop(event) {
    if (event.target === event.currentTarget) {
        closeGlobalSearch();
    }
}

function resetSearchState() {
    document.getElementById('globalSearchInput').value = '';
    document.getElementById('searchSuggestions').classList.remove('hidden');
    document.getElementById('searchResultsList').classList.add('hidden');
    document.getElementById('searchLoading').classList.add('hidden');
    document.getElementById('searchNoResults').classList.add('hidden');
    selectedResultIndex = -1;
}

function performGlobalSearch(query) {
    clearTimeout(searchTimeout);
    
    if (!query.trim()) {
        document.getElementById('searchSuggestions').classList.remove('hidden');
        document.getElementById('searchResultsList').classList.add('hidden');
        document.getElementById('searchLoading').classList.add('hidden');
        document.getElementById('searchNoResults').classList.add('hidden');
        selectedResultIndex = -1;
        return;
    }
    
    document.getElementById('searchSuggestions').classList.add('hidden');
    document.getElementById('searchResultsList').classList.add('hidden');
    document.getElementById('searchNoResults').classList.add('hidden');
    document.getElementById('searchLoading').classList.remove('hidden');
    selectedResultIndex = -1;
    
    // Debounce search
    searchTimeout = setTimeout(() => {
        fetch(`ajax_search.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('searchLoading').classList.add('hidden');
                
                if (data.results && data.results.length > 0) {
                    renderSearchResults(data.results);
                    document.getElementById('searchResultsList').classList.remove('hidden');
                } else {
                    document.getElementById('searchNoResults').classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                document.getElementById('searchLoading').classList.add('hidden');
                document.getElementById('searchNoResults').classList.remove('hidden');
            });
    }, 300);
}

function renderSearchResults(results) {
    const container = document.getElementById('searchResultsList');
    container.innerHTML = '';
    
    results.forEach((result, index) => {
        const item = document.createElement('a');
        item.href = result.url;
        item.className = `flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors group search-result-item ${index === 0 ? 'bg-slate-800/50' : ''}`;
        item.dataset.index = index;
        
        const iconClass = result.icon || 'fa-circle';
        const iconColor = result.color || 'amber';
        
        item.innerHTML = `
            <div class="w-8 h-8 rounded-lg bg-${iconColor}-500/10 flex items-center justify-center">
                <i class="fas ${iconClass} text-${iconColor}-400 text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm text-white truncate">${escapeHtml(result.title)}</div>
                <div class="text-xs text-slate-500 truncate">${escapeHtml(result.subtitle)}</div>
            </div>
            <span class="text-[10px] uppercase px-1.5 py-0.5 rounded bg-slate-700 text-slate-400">${escapeHtml(result.type)}</span>
        `;
        
        container.appendChild(item);
    });
}

function handleSearchKeydown(event) {
    const results = document.querySelectorAll('.search-result-item');
    const suggestions = document.querySelectorAll('#searchSuggestions a');
    const items = document.getElementById('searchResultsList').classList.contains('hidden') ? suggestions : results;
    
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        selectedResultIndex = Math.min(selectedResultIndex + 1, items.length - 1);
        updateSelection(items);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        selectedResultIndex = Math.max(selectedResultIndex - 1, -1);
        updateSelection(items);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        if (selectedResultIndex >= 0 && items[selectedResultIndex]) {
            items[selectedResultIndex].click();
        }
    } else if (event.key === 'Escape') {
        closeGlobalSearch();
    }
}

function updateSelection(items) {
    items.forEach((item, index) => {
        if (index === selectedResultIndex) {
            item.classList.add('bg-slate-800');
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.classList.remove('bg-slate-800');
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Keyboard shortcut: / to open search
document.addEventListener('keydown', function(e) {
    // Only trigger if not in an input and pressing /
    if (e.key === '/' && !e.metaKey && !e.ctrlKey && !e.altKey) {
        const activeElement = document.activeElement;
        if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA' && activeElement.isContentEditable !== true) {
            e.preventDefault();
            openGlobalSearch();
        }
    }
    // ESC to close
    if (e.key === 'Escape') {
        closeGlobalSearch();
    }
});

// Make functions globally available
window.openGlobalSearch = openGlobalSearch;
window.closeGlobalSearch = closeGlobalSearch;
window.closeGlobalSearchOnBackdrop = closeGlobalSearchOnBackdrop;
window.performGlobalSearch = performGlobalSearch;
window.handleSearchKeydown = handleSearchKeydown;

// Confirmation Dialog
let confirmCallback = null;
function showConfirmModal(opts) {
    confirmCallback = opts.onConfirm;
    document.getElementById('confirmTitle').textContent = opts.title || 'Confirm';
    document.getElementById('confirmMessage').textContent = opts.message || 'Proceed?';
    document.getElementById('confirmButton').textContent = opts.confirmText || 'Confirm';
    const types = {
        danger: {bg: 'bg-red-500/10', border: 'border-red-500/20', icon: 'text-red-400', btn: 'bg-red-500 hover:bg-red-400'},
        warning: {bg: 'bg-amber-500/10', border: 'border-amber-500/20', icon: 'text-amber-400', btn: 'bg-amber-500 hover:bg-amber-400'},
        info: {bg: 'bg-blue-500/10', border: 'border-blue-500/20', icon: 'text-blue-400', btn: 'bg-blue-500 hover:bg-blue-400'}
    };
    const t = types[opts.type] || types.danger;
    const ic = document.getElementById('confirmIcon');
    ic.className = `w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 border ${t.bg} ${t.border}`;
    ic.innerHTML = `<i class="fas ${opts.type === 'warning' ? 'fa-exclamation-circle' : opts.type 'info' 'fa-info-circle' 'fa-exclamation-triangle'} ${t.icon} text-xl"></i>`;
    document.getElementById('confirmButton').className = `px-4 py-2 rounded-lg text-white text-sm font-medium transition-colors ${t.btn}`;
    const m = document.getElementById('confirmModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeConfirmModal() {
    const m = document.getElementById('confirmModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
    confirmCallback = null;
}
function closeConfirmModalOnBackdrop(e) {
    if (e.target === e.currentTarget) closeConfirmModal();
}
function executeConfirmedAction() {
    if (confirmCallback) confirmCallback();
    closeConfirmModal();
}
window.showConfirmModal = showConfirmModal;
window.closeConfirmModal = closeConfirmModal;
window.closeConfirmModalOnBackdrop = closeConfirmModalOnBackdrop;
window.executeConfirmedAction = executeConfirmedAction;

// Empty State Helper
function renderEmptyState(options) {
    const {
        icon = 'fa-inbox',
        title = 'No data found',
        message = 'There are no items to display at this time.',
        actionText = null,
        actionHref = null,
        actionOnClick = null,
        variant = 'default' // default, search, error
    } = options;
    
    const variants = {
        default: { iconBg: 'bg-slate-800', iconColor: 'text-slate-500', iconBorder: 'border-slate-700' },
        search: { iconBg: 'bg-amber-500/10', iconColor: 'text-amber-400', iconBorder: 'border-amber-500/20' },
        error: { iconBg: 'bg-red-500/10', iconColor: 'text-red-400', iconBorder: 'border-red-500/20' }
    };
    
    const v = variants[variant] || variants.default;
    
    let actionHtml = '';
    if (actionText) {
        const actionAttr = actionOnClick ? `onclick="${actionOnClick}"` : `href="${actionHref || '#'}"`;
        actionHtml = `
            <a ${actionAttr} class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
                <i class="fas fa-plus text-xs"></i>
                ${actionText}
            </a>
        `;
    }
    
    return `
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <div class="w-20 h-20 rounded-2xl ${v.iconBg} border ${v.iconBorder} flex items-center justify-center mb-5">
                <i class="fas ${icon} text-3xl ${v.iconColor}"></i>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">${title}</h3>
            <p class="text-sm text-slate-500 max-w-sm mb-5">${message}</p>
            ${actionHtml}
        </div>
    `;
}
window.renderEmptyState = renderEmptyState;

// Notification System
let notifications = [];
let notificationCheckInterval = null;

function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
        fetchNotifications();
    } else {
        dropdown.classList.add('hidden');
    }
}

function fetchNotifications() {
    fetch('ajax_notifications.php?limit=10')
        .then(r => r.json())
        .then(data => {
            notifications = data.notifications || [];
            renderNotifications();
            updateNotificationBadge();
        })
        .catch(e => console.error('Failed to fetch notifications:', e));
}

function renderNotifications() {
    const container = document.getElementById('notificationsList');
    if (!notifications.length) {
        container.innerHTML = `
            <div class="px-4 py-8 text-center text-slate-500 text-sm">
                <i class="fas fa-bell-slash mb-2 block text-lg"></i>
                No notifications
            </div>`;
        return;
    }
    
    const typeIcons = {
        'warning': 'fa-triangle-exclamation text-amber-400',
        'error': 'fa-circle-xmark text-red-400',
        'success': 'fa-circle-check text-emerald-400',
        'info': 'fa-circle-info text-blue-400'
    };
    
    container.innerHTML = notifications.map(n => `
        <div class="px-4 py-3 border-b border-slate-700/50 hover:bg-slate-800/50 transition-colors ${n.is_read ? 'opacity-60' : ''}">
            <div class="flex items-start gap-3">
                <i class="fas ${typeIcons[n.type] || typeIcons.info} mt-0.5"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-white truncate">${escapeHtml(n.title)}</p>
                    <p class="text-xs text-slate-500 truncate">${escapeHtml(n.message)}</p>
                    <p class="text-[10px] text-slate-600 mt-1">${timeAgo(n.created_at)}</p>
                </div>
                ${!n.is_read ? `<span class="w-2 h-2 bg-amber-500 rounded-full flex-shrink-0 mt-1"></span>` : ''}
            </div>
        </div>
    `).join('');
}

function updateNotificationBadge() {
    const badge = document.getElementById('notificationBadge');
    const unread = notifications.filter(n => !n.is_read).length;
    if (unread > 0) {
        badge.textContent = unread > 99 ? '99+' : unread;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function markAllNotificationsRead() {
    fetch('ajax_notifications.php?action=mark_all_read', {method: 'POST'})
        .then(() => {
            notifications.forEach(n => n.is_read = true);
            renderNotifications();
            updateNotificationBadge();
        });
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'Just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days}d ago`;
    return date.toLocaleDateString();
}

// Auto-fetch notifications on page load and every 30 seconds
document.addEventListener('DOMContentLoaded', () => {
    fetchNotifications();
    notificationCheckInterval = setInterval(fetchNotifications, 30000);
});

// Close notifications dropdown when clicking outside
document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('notificationsDropdown');
    const bell = document.querySelector('button[onclick="toggleNotifications()"]');
    if (!dropdown?.contains(e.target) && !bell?.contains(e.target)) {
        dropdown?.classList.add('hidden');
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (notificationCheckInterval) clearInterval(notificationCheckInterval);
});

window.toggleNotifications = toggleNotifications;
window.markAllNotificationsRead = markAllNotificationsRead;
</script>

<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
