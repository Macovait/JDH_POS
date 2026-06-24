<?php
/**
 * Enhanced Header - JDH POS System
 * PURE TAILWIND CSS EDITION
 * Fixed height: 64px (h-16)
 */

// Ensure session is started with correct name
if (!isset($_SESSION)) {
    session_name('jakababa_saas_sid');
    session_start();
}

// Load paths for base_url() if not already loaded
if (!function_exists('base_url')) {
    require_once __DIR__ . '/../../src/paths.php';
}

// Load auth & db helpers if available
if (!function_exists('get_current_branch_name')) {
    $authPath = __DIR__ . '/../../src/auth.php';
    if (file_exists($authPath)) require_once $authPath;
}
if (!function_exists('get_db_connection')) {
    $dbPath = __DIR__ . '/../../src/db.php';
    if (file_exists($dbPath)) require_once $dbPath;
}
if (!function_exists('time_ago')) {
    $functionsPath = __DIR__ . '/../../src/functions.php';
    if (file_exists($functionsPath)) require_once $functionsPath;
}

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();

// Load database if needed for branches
if (!isset($pdo) && function_exists('get_db_connection')) {
    try {
        $pdo = get_db_connection();
    } catch (Exception $e) {
        // Silent fail - will use session data
    }
}

// ============================================
// SESSION DATA - ALL VARIABLES DEFINED HERE
// ============================================
$tenant_id = $_SESSION['tenant_id'] ?? ($_SESSION['user']['tenant_id'] ?? 0);
$user_id   = $_SESSION['user_id']   ?? 0;
$user_name = ($_SESSION['name'] ?? null)
    ?? ($_SESSION['user_name'] ?? null)
    ?? ($_SESSION['user']['name'] ?? null)
    ?: 'User';
$user_email = $_SESSION['email'] ?? '';
$user_role = $_SESSION['role'] ?? ($_SESSION['role_name'] ?? 'User');

// Always verify role from database to ensure correct display
if ($user_id && isset($pdo) && $pdo) {
    try {
        $role_id = $_SESSION['role_id'] ?? 0;
        if ($role_id > 0) {
            // Get role name from roles table with tenant isolation
            $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$role_id, $tenant_id]);
            $roleRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($roleRow['name'])) {
                $user_role = $roleRow['name'];
                $_SESSION['role'] = $user_role;
            }
        }
    } catch (Exception $e) { /* silent */ }
}

$branch_id = $_SESSION['branch_id'] ?? 0;
$branch_name = $_SESSION['branch_name'] ?? '';
$company_name = $_SESSION['tenant_name'] ?? '';
$subscription_status = $_SESSION['subscription_status'] ?? 'active';

// ============================================
// USER AVATAR
// ============================================
$user_avatar = $_SESSION['user']['avatar'] ?? null;
if (empty($user_avatar) && isset($pdo) && $pdo && $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$user_id]);
        $avatar_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($avatar_row['avatar'])) {
            $user_avatar = $avatar_row['avatar'];
            $_SESSION['user']['avatar'] = $user_avatar;
        }
    } catch (PDOException $e) { /* silent */ }
}
// Resolve avatar path robustly — strip leading public/ if stored that way
$avatar_rel = (!empty($user_avatar)) ? ltrim($user_avatar, '/\\') : '';
if (str_starts_with($avatar_rel, 'public/')) {
    $avatar_rel = substr($avatar_rel, 7);
}
$avatar_public_path  = dirname(__DIR__) . DIRECTORY_SEPARATOR . $avatar_rel;
$avatar_root_path    = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $avatar_rel;
$avatar_exists       = file_exists($avatar_public_path) || file_exists($avatar_root_path);

$user_avatar_url = (!empty($avatar_rel) && $avatar_exists) ? base_url($avatar_rel) . '?v=' . (file_exists($avatar_public_path) ? filemtime($avatar_public_path) : filemtime($avatar_root_path)) : null;

// ============================================
// NOTIFICATIONS
// ============================================
$unread_notifications = 0;
if (isset($pdo) && $pdo && $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL");
        $stmt->execute([$user_id]);
        $unread_notifications = (int) $stmt->fetchColumn();
        $_SESSION['unread_notifications'] = $unread_notifications;
    } catch (PDOException $e) {
        $unread_notifications = (int) ($_SESSION['unread_notifications'] ?? 0);
    }
} else {
    $unread_notifications = (int) ($_SESSION['unread_notifications'] ?? 0);
}

$recent_notifications = [];
if (isset($pdo) && $pdo && $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, title, message, created_at, read_at FROM notifications WHERE user_id = ? AND branch_id = $current_branch_id ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $recent_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $recent_notifications = [];
    }
}

// ============================================
// CSRF TOKEN
// ============================================
$csrf_token = generate_csrf_token();

// ============================================
// BRANCH NAME FROM DATABASE
// ============================================
if (isset($pdo) && $pdo && $branch_id) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$branch_id, $tenant_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['name'])) {
            $branch_name = $row['name'];
            $_SESSION['branch_name'] = $branch_name;
        }
    } catch (PDOException $e) { /* silent */ }
}
if (empty($branch_name) && function_exists('get_current_branch_name')) {
    $branch_name = get_current_branch_name();
}
if (empty($branch_name)) {
    $branch_name = 'Unknown Branch';
}

// ============================================
// COMPANY NAME FROM DATABASE
// ============================================
if (isset($pdo) && $pdo && $tenant_id) {
    try {
        if (function_exists('get_current_tenant_name')) {
            $db_company = get_current_tenant_name($tenant_id);
            if (!empty($db_company)) {
                $company_name = $db_company;
                $_SESSION['tenant_name'] = $company_name;
            }
        }
    } catch (Exception $e) { /* silent */ }
}
if (empty($company_name)) {
    $company_name = 'Unknown Company';
}

// ============================================
// SUBSCRIPTION STATUS
// ============================================
$is_subscription_active = $subscription_status === 'active' || $subscription_status === 'trialing';
$is_trial = $subscription_status === 'trialing';

// ============================================
// AVAILABLE BRANCHES (cached)
// ============================================
$available_branches = [];
$branches_cache_key = 'available_branches_' . $tenant_id;
$branches_cache_expires = $branches_cache_key . '_expires';

if (!empty($_SESSION[$branches_cache_key]) && !empty($_SESSION[$branches_cache_expires]) && $_SESSION[$branches_cache_expires] > time()) {
    $available_branches = $_SESSION[$branches_cache_key];
} elseif (isset($pdo) && $pdo && $tenant_id) {
    try {
        $branchOrderSql = 'name';
        if (function_exists('db_has_column') && db_has_column('branches', 'is_default')) {
            $branchOrderSql = 'is_default DESC, name';
        }

        $stmt = $pdo->prepare("SELECT id, name, code, location, active FROM branches WHERE tenant_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY {$branchOrderSql}");
        $stmt->execute([$tenant_id]);
        $available_branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $_SESSION[$branches_cache_key] = $available_branches;
        $_SESSION[$branches_cache_expires] = time() + 10;
    } catch (PDOException $e) {
        error_log("Error fetching branches: " . $e->getMessage());
        $available_branches = $_SESSION[$branches_cache_key] ?? [];
    }
}

// ============================================
// LANGUAGE SETTINGS
// ============================================
$headerLocale = $_SESSION['locale'] ?? 'en';
$langNames = [
    'en' => 'EN', 'es' => 'ES', 'fr' => 'FR', 'de' => 'DE',
    'pt' => 'PT', 'sw' => 'SW', 'hi' => 'HI', 'ar' => 'AR'
];

// ============================================
// COMMAND PALETTE NAVIGATION ITEMS
// ============================================
$commandPaletteNavItems = [
    ['label' => 'Dashboard', 'url' => base_url('dashboard/home.php'), 'icon' => 'fas fa-chart-pie', 'shortcut' => 'D'],
    ['label' => 'POS', 'url' => base_url('pos/pos.php'), 'icon' => 'fas fa-cash-register', 'shortcut' => 'P'],
    ['label' => 'Billing', 'url' => base_url('dashboard/billing.php'), 'icon' => 'fas fa-credit-card', 'shortcut' => ''],
    ['label' => 'Settings', 'url' => base_url('dashboard/settings.php'), 'icon' => 'fas fa-cog', 'shortcut' => ''],
    ['label' => 'Profile', 'url' => base_url('dashboard/profile.php'), 'icon' => 'fas fa-user', 'shortcut' => ''],
    ['label' => 'Support', 'url' => base_url('dashboard/support.php'), 'icon' => 'fas fa-life-ring', 'shortcut' => ''],
];

// ============================================
// TIME AGO FALLBACK FUNCTION
// ============================================
if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        if (empty($datetime)) return '';
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d', $timestamp);
    }
}

// Release session lock
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
?>
<!-- Header - Fixed height 64px (h-16) -->
<header class="bg-[#0b1120] border-b border-white/5 w-full h-16">
    <div class="w-full h-full px-4 md:px-6">
        <div class="flex items-center justify-between h-full gap-3">
            
            <!-- Left Section: Branch Selector -->
            <div class="flex items-center gap-3 flex-shrink-0">
                <?php if (count($available_branches) > 1): ?>
                <div class="relative" id="branchSelector">
                    <button onclick="toggleBranchDropdown()" class="flex items-center gap-2 h-9 px-3 bg-slate-800 border border-slate-700 rounded-lg text-slate-300 text-sm hover:border-amber-500 transition-all whitespace-nowrap">
                        <i class="fas fa-map-marker-alt text-amber-400 text-xs"></i>
                        <span class="max-w-[120px] truncate"><?php echo htmlspecialchars($branch_name); ?></span>
                        <i class="fas fa-chevron-down text-slate-500 text-[10px] transition-transform duration-200" id="branchChevron"></i>
                    </button>
                    <div id="branchDropdown" class="hidden absolute top-full left-0 mt-2 w-64 bg-slate-800 border border-slate-700 rounded-xl shadow-xl z-50">
                        <div class="px-3 py-2 border-b border-slate-700 text-xs font-semibold text-slate-400 uppercase flex items-center gap-2">
                            <i class="fas fa-exchange-alt text-amber-400"></i>
                            <span>Switch Branch</span>
                        </div>
                        <?php foreach ($available_branches as $branch): ?>
                        <a href="<?php echo htmlspecialchars(base_url('ajax/switch_branch.php?branch_id=' . urlencode((string)($branch['id'] ?? '')) . '&redirect=' . urlencode((string)($_SERVER['REQUEST_URI'] ?? '')))); ?>" 
                           class="flex items-center gap-3 px-3 py-2 hover:bg-slate-700 transition-all <?php echo ($branch['id'] ?? 0) == $branch_id ? 'bg-amber-500/10' : ''; ?>">
                            <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center">
                                <i class="fas fa-store text-slate-400 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm text-white font-medium"><?php echo htmlspecialchars($branch['name']); ?></div>
                                <?php if (!empty($branch['location'])): ?>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($branch['location']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($branch['id'] == $branch_id): ?>
                            <i class="fas fa-check text-amber-400 text-sm"></i>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="flex items-center gap-2 h-9 px-3 bg-slate-800 rounded-lg">
                    <i class="fas fa-map-marker-alt text-amber-400 text-xs"></i>
                    <span class="text-slate-300 text-sm"><?php echo htmlspecialchars($branch_name); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Center: Global Search -->
            <div class="flex-1 max-w-xl mx-4">
                <div class="relative w-full">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                    <input type="text" id="global-search" placeholder="Search products, customers, sales..." 
                           class="w-full pl-10 pr-12 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                    <button onclick="clearSearch()" id="searchClearBtn" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-red-400">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                    <kbd class="hidden md:inline-flex absolute right-3 top-1/2 -translate-y-1/2 text-[10px] text-slate-500 bg-slate-700 px-1.5 py-0.5 rounded">Ctrl K</kbd>
                </div>
                <div id="searchResults" class="hidden absolute top-full left-0 right-0 mt-2 bg-slate-800 border border-slate-700 rounded-xl shadow-2xl z-50 max-h-96 overflow-y-auto">
                    <div id="searchResultsContent" class="p-2"></div>
                    <div class="border-t border-slate-700 px-3 py-2 text-xs text-slate-500 flex justify-center gap-4">
                        <span><kbd class="px-1.5 py-0.5 bg-slate-700 rounded text-[10px]">↑↓</kbd> Navigate</span>
                        <span><kbd class="px-1.5 py-0.5 bg-slate-700 rounded text-[10px]">Enter</kbd> Open</span>
                        <span><kbd class="px-1.5 py-0.5 bg-slate-700 rounded text-[10px]">Esc</kbd> Close</span>
                    </div>
                </div>
            </div>

            <!-- Right Section: Actions & User -->
            <div class="flex items-center gap-2 flex-shrink-0">
                <?php if ($is_trial): ?>
                <a href="<?php echo base_url('dashboard/billing.php'); ?>" class="flex items-center gap-1.5 px-2.5 py-1.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-xs font-medium hover:bg-amber-500/25 transition-all whitespace-nowrap">
                    <i class="fas fa-clock text-[10px]"></i>
                    <span>Trial</span>
                </a>
                <?php endif; ?>

                <a href="<?php echo base_url('pos/pos.php'); ?>" class="flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-amber-500 to-yellow-500 rounded-lg text-slate-900 text-sm font-semibold hover:from-amber-600 hover:to-yellow-600 transition-all whitespace-nowrap">
                    <i class="fas fa-cash-register text-xs"></i>
                    <span class="hidden sm:inline">POS</span>
                </a>

                <!-- Notifications -->
                <div class="relative" id="notificationsWrapper">
                    <button onclick="toggleNotificationsDropdown()" class="relative w-9 h-9 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 hover:border-amber-500 hover:text-amber-400 transition-all flex items-center justify-center">
                        <i class="fas fa-bell text-sm"></i>
                        <?php if ($unread_notifications > 0): ?>
                        <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"><?php echo $unread_notifications > 99 ? '99+' : $unread_notifications; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notificationsDropdown" class="hidden absolute top-full right-0 mt-2 w-80 bg-slate-800 border border-slate-700 rounded-xl shadow-xl z-50">
                        <div class="flex justify-between items-center px-4 py-2 border-b border-slate-700">
                            <span class="text-xs font-semibold text-slate-400 uppercase">Notifications</span>
                            <?php if ($unread_notifications > 0): ?>
                            <a href="javascript:void(0)" onclick="markAllNotificationsRead(event)" class="text-xs text-amber-400 hover:text-amber-300">Mark all read</a>
                            <?php endif; ?>
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            <?php if (empty($recent_notifications) && $unread_notifications === 0): ?>
                            <div class="text-center py-8 text-slate-500">
                                <i class="fas fa-bell-slash text-3xl mb-2"></i>
                                <p class="text-sm">No new notifications</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($recent_notifications as $notif):
                                    $isUnread = empty($notif['read_at']);
                                    $timeAgo = !empty($notif['created_at']) ? time_ago($notif['created_at']) : '';
                                ?>
                                <div class="notification-item <?php echo $isUnread ? 'bg-amber-500/5' : ''; ?> p-3 border-b border-slate-700 hover:bg-slate-700/50 transition-all cursor-pointer" data-id="<?php echo (int) $notif['id']; ?>" onclick="markNotificationRead(this)">
                                    <div class="flex gap-3">
                                        <div class="w-2 h-2 rounded-full bg-amber-400 mt-1.5 flex-shrink-0"></div>
                                        <div class="flex-1">
                                            <p class="text-sm text-white"><?php echo htmlspecialchars($notif['title'] ?? $notif['message'] ?? 'Notification'); ?></p>
                                            <?php if (!empty($notif['message']) && !empty($notif['title'])): ?>
                                            <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars(mb_strimwidth($notif['message'], 0, 60, '...')); ?></p>
                                            <?php endif; ?>
                                            <?php if ($timeAgo): ?>
                                            <span class="text-[10px] text-slate-600 mt-1 block"><?php echo $timeAgo; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php if ($unread_notifications > count($recent_notifications)): ?>
                                <div class="p-3 border-t border-slate-700 text-center">
                                    <a href="<?php echo base_url('dashboard/notifications.php'); ?>" class="text-sm text-amber-400 hover:text-amber-300">+<?php echo $unread_notifications - count($recent_notifications); ?> more unread</a>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="border-t border-slate-700 p-2 text-center">
                            <a href="<?php echo base_url('dashboard/notifications.php'); ?>" class="text-xs text-slate-500 hover:text-amber-400">View Notification Center</a>
                        </div>
                    </div>
                </div>

                <!-- Language Switcher -->
                <div class="relative" id="langWrapper">
                    <button onclick="toggleLangDropdown()" class="w-9 h-9 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 hover:border-amber-500 hover:text-amber-400 transition-all flex items-center justify-center text-sm font-semibold">
                        <?php echo $langNames[$headerLocale] ?? strtoupper($headerLocale); ?>
                    </button>
                    <div id="langDropdown" class="hidden absolute top-full right-0 mt-2 w-28 bg-slate-800 border border-slate-700 rounded-lg shadow-xl z-50">
                        <?php foreach ($langNames as $code => $label): ?>
                        <a href="?set_locale=<?php echo $code; ?>" class="block px-3 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-amber-400 transition-all"><?php echo $label; ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- User Menu -->
                <div class="relative" id="userMenuWrapper">
                    <button onclick="toggleUserDropdown()" class="flex items-center gap-2 h-9 px-2 bg-slate-800 border border-slate-700 rounded-lg hover:border-amber-500 transition-all">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-amber-500 to-yellow-600 flex items-center justify-center text-slate-900 font-bold text-sm overflow-hidden flex-shrink-0">
                            <?php if ($user_avatar_url): ?>
                                <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="hidden md:block text-left">
                            <div class="text-white text-xs font-medium truncate max-w-[80px]"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="text-slate-500 text-[10px] capitalize"><?php echo htmlspecialchars($user_role); ?></div>
                        </div>
                        <i class="fas fa-chevron-down text-slate-500 text-[10px] transition-transform duration-200"></i>
                    </button>
                    <div id="userDropdown" class="hidden absolute top-full right-0 mt-2 w-64 bg-slate-800 border border-slate-700 rounded-xl shadow-xl z-50">
                        <div class="flex items-center gap-3 p-4 border-b border-slate-700">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-amber-500 to-yellow-600 flex items-center justify-center text-slate-900 font-bold text-lg overflow-hidden flex-shrink-0">
                                <?php if ($user_avatar_url): ?>
                                    <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="text-white font-medium"><?php echo htmlspecialchars($user_name); ?></div>
                                <div class="text-slate-500 text-xs"><?php echo htmlspecialchars($user_email ?: $user_name); ?></div>
                            </div>
                        </div>
                        <div class="py-1">
                            <a href="<?php echo base_url('dashboard/profile.php'); ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-amber-400 transition-all"><i class="fas fa-user w-4"></i> Profile</a>
                            <a href="<?php echo base_url('dashboard/settings.php'); ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-amber-400 transition-all"><i class="fas fa-cog w-4"></i> Settings</a>
                            <a href="<?php echo base_url('dashboard/billing.php'); ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-amber-400 transition-all"><i class="fas fa-credit-card w-4"></i> Billing</a>
                            <div class="h-px bg-slate-700 my-1"></div>
                            <a href="<?php echo base_url('auth/logout.php'); ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-red-400 hover:bg-red-500/10 transition-all"><i class="fas fa-sign-out-alt w-4"></i> Sign Out</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Subscription Warning Banner -->
<?php if (!$is_subscription_active): ?>
<div class="bg-gradient-to-r from-red-500/15 to-amber-500/15 border-b border-red-500/30">
    <div class="max-w-7xl mx-auto px-4 py-2 flex items-center justify-center gap-3 flex-wrap">
        <i class="fas fa-exclamation-triangle text-red-400 text-sm"></i>
        <span class="text-white text-sm">Your subscription has expired. Please renew to continue using all features.</span>
        <a href="<?php echo base_url('dashboard/billing.php'); ?>" class="inline-flex items-center gap-1 px-3 py-1 bg-red-500 text-white text-sm font-medium rounded-lg hover:bg-red-600 transition-all">
            Renew Now <i class="fas fa-arrow-right text-xs"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Command Palette Modal -->
<div id="commandPalette" class="fixed inset-0 z-[2000] hidden items-start justify-center pt-[15vh]">
    <div class="absolute inset-0 bg-black/60 " onclick="closeCommandPalette()"></div>
    <div class="relative w-full max-w-lg bg-slate-800 border border-slate-700 rounded-xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-3 p-4 border-b border-slate-700">
            <i class="fas fa-search text-slate-500"></i>
            <input type="text" id="paletteInput" placeholder="Search pages, features, or actions..." 
                   class="flex-1 bg-transparent border-none outline-none text-white text-sm placeholder-slate-500">
            <kbd class="px-2 py-1 bg-slate-700 rounded text-xs text-slate-400">ESC</kbd>
        </div>
        <div id="paletteResults" class="max-h-96 overflow-y-auto p-2">
            <div class="text-xs font-semibold text-slate-500 uppercase px-3 py-2">Quick Navigation</div>
            <?php foreach ($commandPaletteNavItems as $navItem): ?>
            <a href="<?php echo $navItem['url']; ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-700 transition-all">
                <i class="<?php echo $navItem['icon']; ?> w-5 text-slate-400"></i>
                <span class="flex-1"><?php echo htmlspecialchars($navItem['label']); ?></span>
                <?php if (!empty($navItem['shortcut'])): ?>
                <kbd class="px-1.5 py-0.5 bg-slate-700 rounded text-[10px] text-slate-400">⌘ <?php echo $navItem['shortcut']; ?></kbd>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// ============================================
// DROPDOWN TOGGLES
// ============================================
function toggleBranchDropdown() {
    const dropdown = document.getElementById('branchDropdown');
    const chevron = document.getElementById('branchChevron');
    const isOpen = !dropdown.classList.contains('hidden');
    closeAllDropdowns();
    if (!isOpen) {
        dropdown.classList.remove('hidden');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
    }
}

function toggleNotificationsDropdown() {
    const dropdown = document.getElementById('notificationsDropdown');
    const isOpen = !dropdown.classList.contains('hidden');
    closeAllDropdowns();
    if (!isOpen) dropdown.classList.remove('hidden');
}

function toggleLangDropdown() {
    const dropdown = document.getElementById('langDropdown');
    const isOpen = !dropdown.classList.contains('hidden');
    closeAllDropdowns();
    if (!isOpen) dropdown.classList.remove('hidden');
}

function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    const isOpen = !dropdown.classList.contains('hidden');
    closeAllDropdowns();
    if (!isOpen) dropdown.classList.remove('hidden');
}

function closeAllDropdowns() {
    document.querySelectorAll('#branchDropdown, #notificationsDropdown, #langDropdown, #userDropdown').forEach(d => {
        d.classList.add('hidden');
    });
    const chevron = document.getElementById('branchChevron');
    if (chevron) chevron.style.transform = 'rotate(0deg)';
}

// ============================================
// COMMAND PALETTE
// ============================================
function openCommandPalette() {
    const palette = document.getElementById('commandPalette');
    const input = document.getElementById('paletteInput');
    palette.classList.remove('hidden');
    palette.style.display = 'flex';
    input.focus();
}

function closeCommandPalette() {
    const palette = document.getElementById('commandPalette');
    palette.classList.add('hidden');
    palette.style.display = 'none';
}

// ============================================
// NOTIFICATIONS
// ============================================
async function markNotificationRead(element) {
    const id = element.getAttribute('data-id');
    if (!id) return;
    try {
        const res = await fetch('<?php echo base_url("api/notifications/mark-read.php"); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent('<?php echo $csrf_token; ?>')
        });
        const data = await res.json();
        if (data.success) {
            element.classList.remove('bg-amber-500/5');
            element.removeAttribute('onclick');
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                let count = parseInt(badge.textContent) || 0;
                count = Math.max(0, count - 1);
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                } else {
                    badge.remove();
                }
            }
        }
    } catch (e) {}
}

async function markAllNotificationsRead(event) {
    if (event) event.preventDefault();
    try {
        const res = await fetch('<?php echo base_url("api/notifications/mark-all-read.php"); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent('<?php echo $csrf_token; ?>')
        });
        const data = await res.json();
        if (data.success) {
            document.querySelectorAll('.notification-item').forEach(el => {
                el.classList.remove('bg-amber-500/5');
                el.removeAttribute('onclick');
            });
            const badge = document.querySelector('.notification-badge');
            if (badge) badge.remove();
        }
    } catch (e) {}
}

// ============================================
// GLOBAL SEARCH
// ============================================
let globalSearchTimeout = null;
let searchResults = [];
let selectedResultIndex = -1;

const searchInput = document.getElementById('global-search');
const searchResultsDiv = document.getElementById('searchResults');
const searchResultsContent = document.getElementById('searchResultsContent');
const searchClearBtn = document.getElementById('searchClearBtn');

if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        if (query.length > 0) {
            searchClearBtn.style.display = 'flex';
            clearTimeout(globalSearchTimeout);
            globalSearchTimeout = setTimeout(() => performSearch(query), 300);
        } else {
            searchClearBtn.style.display = 'none';
            closeSearch();
        }
    });
    
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            navigateResults(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            navigateResults(-1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (searchResults[selectedResultIndex]) {
                openResult(searchResults[selectedResultIndex]);
            }
        } else if (e.key === 'Escape') {
            closeSearch();
        }
    });
}

function performSearch(query) {
    fetch(`<?php echo base_url('ajax/global_search.php'); ?>?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data.results || [], query);
        })
        .catch(error => {
            console.error('Search error:', error);
            displaySearchResults([], query);
        });
}

function displaySearchResults(results, query) {
    searchResults = results;
    selectedResultIndex = -1;
    
    if (results.length === 0) {
        searchResultsContent.innerHTML = `
            <div class="p-4 text-center text-slate-500">
                <p class="text-sm">No results found</p>
                <p class="text-xs mt-1">Try different keywords</p>
            </div>
        `;
    } else {
        searchResultsContent.innerHTML = results.map((result, index) => `
            <div class="search-result-item p-3 rounded-lg cursor-pointer hover:bg-slate-700 transition-all" data-index="${index}" onclick="openResult(searchResults[${index}])">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-slate-700/50 text-${result.color === 'amber' ? 'amber' : (result.color === 'blue' ? 'blue' : 'gray')}-400">
                        <i class="${result.icon} text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <div class="text-white text-sm font-medium">${highlightText(result.name || result.title, query)}</div>
                        <div class="text-slate-500 text-xs">${result.type}</div>
                    </div>
                    <div class="text-slate-600 text-xs uppercase">${result.type}</div>
                </div>
            </div>
        `).join('');
    }
    
    searchResultsDiv.style.display = 'block';
}

function highlightText(text, query) {
    if (!text) return '';
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark class="bg-amber-500/30 text-amber-400">$1</mark>');
}

function navigateResults(direction) {
    const items = document.querySelectorAll('.search-result-item');
    if (selectedResultIndex >= 0) {
        items[selectedResultIndex]?.classList.remove('bg-slate-700');
    }
    selectedResultIndex += direction;
    if (selectedResultIndex < 0) selectedResultIndex = items.length - 1;
    if (selectedResultIndex >= items.length) selectedResultIndex = 0;
    items[selectedResultIndex]?.classList.add('bg-slate-700');
    items[selectedResultIndex]?.scrollIntoView({ block: 'nearest' });
}

function openResult(result) {
    if (result && result.url) {
        window.location.href = result.url;
    }
    closeSearch();
}

function closeSearch() {
    searchResultsDiv.style.display = 'none';
    searchResults = [];
    selectedResultIndex = -1;
}

function clearSearch() {
    if (searchInput) searchInput.value = '';
    searchClearBtn.style.display = 'none';
    closeSearch();
}

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const palette = document.getElementById('commandPalette');
        if (palette.classList.contains('hidden')) {
            openCommandPalette();
        } else {
            closeCommandPalette();
        }
    }
    if (e.key === 'Escape') {
        closeCommandPalette();
        closeAllDropdowns();
        closeSearch();
    }
});

// ============================================
// CLICK OUTSIDE TO CLOSE DROPDOWNS
// ============================================
document.addEventListener('click', function(e) {
    if (!e.target.closest('#branchSelector') && !e.target.closest('#branchDropdown')) {
        document.getElementById('branchDropdown')?.classList.add('hidden');
        const chevron = document.getElementById('branchChevron');
        if (chevron) chevron.style.transform = 'rotate(0deg)';
    }
    if (!e.target.closest('#notificationsWrapper')) {
        document.getElementById('notificationsDropdown')?.classList.add('hidden');
    }
    if (!e.target.closest('#langWrapper')) {
        document.getElementById('langDropdown')?.classList.add('hidden');
    }
    if (!e.target.closest('#userMenuWrapper')) {
        document.getElementById('userDropdown')?.classList.add('hidden');
    }
});

// ============================================
// NOTIFICATION POLLING
// ============================================
setInterval(() => {
    fetch('<?php echo base_url("api/notifications/count.php"); ?>')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const btn = document.querySelector('#notificationsWrapper button');
                const existingBadge = btn?.querySelector('.notification-badge');
                if (data.has_unread) {
                    if (existingBadge) {
                        existingBadge.textContent = data.badge_text;
                    } else {
                        const badge = document.createElement('span');
                        badge.className = 'absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center';
                        badge.textContent = data.badge_text;
                        btn?.appendChild(badge);
                    }
                } else if (existingBadge) {
                    existingBadge.remove();
                }
            }
        })
        .catch(() => {});
}, 60000);
</script>

<!-- Keyboard Shortcuts Modal -->
<?php include_once __DIR__ . '/../includes/keyboard_shortcuts.php'; ?>