<?php
/**
 * App Layout - JDH POS System
 * PURE TAILWIND CSS EDITION - Modern Enterprise UI
 * @version 4.0
 */

// Suppress error display in HTML output (but log to error_log)
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// Enable output buffering for faster page rendering
if (!ob_get_level()) {
    ob_start('ob_gzhandler');
}

// Load paths for base_url() first
if (!function_exists('base_url')) {
    require_once __DIR__ . '/../../src/paths.php';
}

// Load auth functions (this will handle session properly)
if (function_exists('safe_require')) {
    safe_require('auth.php', 'src', true);
} else {
    require_once __DIR__ . '/../../src/auth.php';
}

// Now session is properly started by auth.php
// Get session data using the helper functions
$tenant_id = function_exists('get_current_tenant_id') ? get_current_tenant_id() : null;
$current_branch_id = function_exists('get_current_branch_id') ? get_current_branch_id() : null;
$user_id = function_exists('get_current_user_id') ? get_current_user_id() : null;
$user_name = function_exists('get_current_user_name') ? get_current_user_name() : 'Guest';
$user_role = $_SESSION['role'] ?? ($_SESSION['role_name'] ?? 'User');

// Always verify role from database using role_id
$role_id = $_SESSION['role_id'] ?? 0;
if ($role_id > 0 && function_exists('get_db_connection')) {
    try {
        $db = get_db_connection();
        $tenant_id = $_SESSION['tenant_id'] ?? 0;
        $stmt = $db->prepare("SELECT name FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$role_id, $tenant_id]);
        $dbRole = $stmt->fetchColumn();
        if ($dbRole) {
            $user_role = $dbRole;
            $_SESSION['role'] = $dbRole;
        }
    } catch (Exception $e) { error_log("Role fetch error: " . $e->getMessage()); }
}

$branch_id = $current_branch_id;
$branch_name = function_exists('get_current_branch_name') ? get_current_branch_name() : 'Main Branch';
$company_name = $_SESSION['tenant_name'] ?? 'Jakababa POS';

// Language / locale switching (after session is started)
if (isset($_GET['set_locale'])) {
    $newLocale = preg_replace('/[^a-z]/', '', $_GET['set_locale']);
    if ($newLocale && file_exists(__DIR__ . '/../../resources/lang/' . $newLocale . '.json')) {
        $_SESSION['locale'] = $newLocale;
        setcookie('locale', $newLocale, time() + 86400 * 30, '/');
        $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
        header('Location: ' . $cleanUrl);
        exit;
    }
}

// Load db and functions
if (function_exists('safe_require')) {
    safe_require('db.php', 'src', true);
    safe_require('functions.php', 'src', true);
} else {
    require_once __DIR__ . '/../../src/db.php';
    require_once __DIR__ . '/../../src/functions.php';
}

// Initialize comprehensive security system
require_once __DIR__ . '/../../src/Security/SecurityBootstrap.php';
require_once __DIR__ . '/../../src/Security/DynamicSidebarCorrected.php';
require_once __DIR__ . '/../../src/Security/PageSecurity.php';
SecurityBootstrap::initialize();

// Enforce page-level permissions (redirect if user lacks access)
PageSecurity::enforce();

// Fix malformed URLs (before any redirects)
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$needs_redirect = false;
$clean_uri = $request_uri;

// Check for duplicate path segments like /dashboard/dashboard/ or /ajax/ajax/
$segments = ['dashboard', 'ajax', 'users', 'pos', 'products', 'inventory', 'reports', 'customers', 'suppliers', 'settings', 'admin', 'api', 'auth'];
foreach ($segments as $seg) {
    $clean_uri = preg_replace('#(/' . $seg . ')(/' . $seg . '/)#', '$1/', $clean_uri);
    $clean_uri = preg_replace('#(/' . $seg . ')(/' . $seg . ')$#', '$1', $clean_uri);
}

// Check for JDH_POS path duplication
$clean_uri = str_replace('/JDH_POS/public/JDH_POS/', '/JDH_POS/', $clean_uri);
$clean_uri = str_replace('/JDH_POS/public/JDH_POS/public/', '/JDH_POS/', $clean_uri);

// Remove multiple slashes
$clean_uri = preg_replace('#/+#', '/', $clean_uri);

if ($clean_uri !== $request_uri) {
    error_log("URL Redirect: From [$request_uri] To [$clean_uri]");
    header('Location: ' . $clean_uri);
    exit;
}

// Require login (skip for auth pages)
$skip_auth = $skip_auth ?? false;
if (!$skip_auth && function_exists('require_login')) {
    require_login();
    
    // Refresh session data after require_login (in case it was regenerated)
    $tenant_id = function_exists('get_current_tenant_id') ? get_current_tenant_id() : null;
    $user_id = function_exists('get_current_user_id') ? get_current_user_id() : null;
    $user_name = function_exists('get_current_user_name') ? get_current_user_name() : 'Guest';
    $branch_id = function_exists('get_current_branch_id') ? get_current_branch_id() : null;
    $branch_name = function_exists('get_current_branch_name') ? get_current_branch_name() : 'Main Branch';
    $company_name = $_SESSION['tenant_name'] ?? 'Jakababa POS';
}

// Notification count (cache in session to avoid DB hit on every page)
$notif_count = 0;
if ($user_id && function_exists('get_db_connection')) {
    $notif_cache_key = 'notif_count_' . $user_id;
    if (!empty($_SESSION[$notif_cache_key]) && !empty($_SESSION[$notif_cache_key . '_expires']) && $_SESSION[$notif_cache_key . '_expires'] > time()) {
        $notif_count = (int) $_SESSION[$notif_cache_key];
    } else {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $notif_count = (int) $stmt->fetchColumn();
            $_SESSION[$notif_cache_key] = $notif_count;
            $_SESSION[$notif_cache_key . '_expires'] = time() + 30;
        } catch (Exception $e) {
            $notif_count = (int) ($_SESSION[$notif_cache_key] ?? 0);
            error_log("Notification count error: " . $e->getMessage());
        }
    }
}

// DB connection for settings
$pdo = null;
if (function_exists('get_db_connection')) {
    try {
        $pdo = get_db_connection();
    } catch (Exception $e) { 
        error_log("DB connection error in layout: " . $e->getMessage());
    }
}

// Resolve company logo and settings
$company_logo = '';
$company_logo_exists = false;
$site_title = '';
$site_tagline = '';
$site_icon = '';
$site_icon_exists = false;

if ($pdo && $tenant_id) {
    try {
        $scope_col = function_exists('db_get_scope_column') ? db_get_scope_column('settings') : 'tenant_id';
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE {$scope_col} = ? AND setting_key IN ('company_logo', 'site_title', 'site_tagline', 'site_icon')");
        $stmt->execute([$tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['setting_key']) {
                case 'company_logo':
                    $company_logo = $row['setting_value'] ?: '';
                    $company_logo_exists = $company_logo !== '' && file_exists(PUBLIC_PATH . DIRECTORY_SEPARATOR . ltrim($company_logo, '/\\'));
                    break;
                case 'site_title':   $site_title   = $row['setting_value'] ?: ''; break;
                case 'site_tagline': $site_tagline = $row['setting_value'] ?: ''; break;
                case 'site_icon':
                    $site_icon = $row['setting_value'] ?: '';
                    $site_icon_exists = $site_icon !== '' && file_exists(PUBLIC_PATH . DIRECTORY_SEPARATOR . ltrim($site_icon, '/\\'));
                    break;
            }
        }
    } catch (Exception $e) { 
        error_log("Settings fetch error: " . $e->getMessage());
    }
}

if (empty($company_name)) {
    $company_name = 'Jakababa POS';
}

$company_initials = strtoupper(substr($company_name, 0, 2));

// Page title
$display_title = $site_title ?: $company_name;
$page_title = ($page_title ?? $display_title);

// Current page for active highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Determine current business type
$biz_type_cache_key = 'biz_type_' . ($tenant_id ?: 0);
if (!empty($_SESSION[$biz_type_cache_key]) && !empty($_SESSION[$biz_type_cache_key . '_expires']) && $_SESSION[$biz_type_cache_key . '_expires'] > time()) {
    $_currentBizType = $_SESSION[$biz_type_cache_key];
} else {
    $_currentBizType = function_exists('get_current_business_type') ? get_current_business_type() : 'retail';
    $_SESSION[$biz_type_cache_key] = $_currentBizType;
    $_SESSION[$biz_type_cache_key . '_expires'] = time() + 60;
}
$_isRestaurantLike = in_array($_currentBizType, ['restaurant', 'cafe', 'bakery'], true);
$_isPharmacy = ($_currentBizType === 'pharmacy');

// Generate dynamic sidebar based on user permissions
$dynamicSidebar = DynamicSidebar::getInstance();
$sidebarSections = $dynamicSidebar->generateSidebar();

// User avatar
$user_avatar = $_SESSION['user']['avatar'] ?? null;
if (empty($user_avatar) && $pdo && $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$user_id]);
        $avatar_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($avatar_row['avatar'])) {
            $user_avatar = $avatar_row['avatar'];
        }
    } catch (PDOException $e) { 
        error_log("Avatar fetch error: " . $e->getMessage());
    }
}
$avatar_rel = '';
if (!empty($user_avatar)) {
    $avatar_rel = ltrim((string) $user_avatar, '/\\');
    if (str_starts_with($avatar_rel, 'public/')) {
        $avatar_rel = substr($avatar_rel, 7);
    }
}
$avatar_full_path = $avatar_rel !== '' ? PUBLIC_PATH . DIRECTORY_SEPARATOR . $avatar_rel : '';
$user_avatar_url = $avatar_rel !== '' && $avatar_full_path !== '' && file_exists($avatar_full_path) ? base_url($avatar_rel) . '?v=' . filemtime($avatar_full_path) : null;

// Release session lock to allow concurrent requests
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">

    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? ($display_title ?? 'POS System')); ?></title>
    <?php if (!empty($site_icon) && $site_icon_exists): ?>
    <link rel="icon" href="<?php echo base_url($site_icon); ?>" type="image/png">
    <?php endif; ?>
    
<!-- Tailwind CSS built locally -->
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    
<style>
         :root { --brand-color: #f68b1e; }
         /* ── Base ── */
         * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            font-size: 14px;
        }
        
        body {
            background: #0b1120;
            font-family: 'Inter', system-ui, sans-serif;
            color: #94a3b8;
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(251, 191, 36, 0.25);
            border-radius: 99px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(251, 191, 36, 0.45);
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 64px;
            z-index: 50;
            background: #0b1120;
            border-right: 1px solid rgba(255, 255, 255, 0.06);
            transition: width 0.2s ease;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .sidebar:hover {
            width: 220px;
        }
        
        .sidebar .sidebar-text,
        .sidebar .section-label,
        .sidebar .brand-text,
        .sidebar .user-info {
            display: none;
        }
        
        .sidebar:hover .sidebar-text,
        .sidebar:hover .brand-text,
        .sidebar:hover .user-info {
            display: inline;
        }
        
        .sidebar:hover .section-label {
            display: block;
        }
        
        .sidebar .nav-item,
        .sidebar .user-profile,
        .sidebar .quick-nav {
            justify-content: center;
        }
        
        .sidebar:hover .nav-item,
        .sidebar:hover .user-profile {
            justify-content: flex-start;
        }
        
        .sidebar:hover .quick-nav {
            justify-content: space-between;
        }
        
        .sidebar-logo-section {
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .sidebar:hover .sidebar-logo-section {
            justify-content: flex-start;
            padding-left: 14px;
        }
        
        .sidebar:hover .sidebar-logo-img {
            display: none;
        }
        
        img.brand-text {
            display: none;
        }
        
        .sidebar:hover img.brand-text {
            display: block;
        }
        
        .nav-item {
            position: relative;
        }
        
        .nav-item[data-tooltip]::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 56px;
            top: 50%;
            transform: translateY(-50%);
            background: #1e293b;
            color: #fbbf24;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.15s;
            z-index: 100;
            pointer-events: none;
            border: 1px solid #334155;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }
        
        .sidebar .nav-item[data-tooltip]:hover::after {
            opacity: 1;
            visibility: visible;
        }
        
        .sidebar:hover .nav-item[data-tooltip]::after {
            display: none;
        }
        
        .nav-item.active {
            background: rgba(251, 191, 36, 0.12);
            color: #fbbf24;
        }
        
        .slide-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.25s ease-out;
        }
        
        .slide-menu.open {
            max-height: 600px;
            transition: max-height 0.3s ease-in;
        }
        
        /* ── Main Content ── */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            width: calc(100% - 64px);
            margin-left: 64px;
            transition: width 0.2s ease, margin-left 0.2s ease;
        }
        
        .sidebar:hover ~ .main-content,
        .sidebar:hover + .main-content {
            width: calc(100% - 220px);
            margin-left: 220px;
        }

        /* ── Card Hover ── */
        .card-hover {
            transition: all 0.2s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            border-color: rgba(245, 158, 11, 0.3);
            box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.4);
        }

        /* ── Progress Bar ── */
        .progress-bar {
            height: 3px;
            background: #1e293b;
            border-radius: 99px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
            border-radius: 99px;
            transition: width 0.5s ease;
        }

        /* ── Animations ── */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-fade-in {
            animation: fadeIn 0.35s ease-out;
        }
        .animate-slide-up {
            animation: slideUp 0.25s ease-out;
        }

        /* ── Table Responsive ── */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* ── Toast Container ── */
        #toastContainer {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        /* ── Mobile ── */
        @media (max-width: 1024px) {
            .sidebar {
                left: -220px;
                width: 220px !important;
            }
            .sidebar.open {
                left: 0;
                box-shadow: 4px 0 24px rgba(0, 0, 0, 0.5);
            }
            .sidebar .sidebar-text,
            .sidebar .section-label,
            .sidebar .brand-text,
            .sidebar .user-info {
                display: inline !important;
            }
            .sidebar .section-label {
                display: block !important;
            }
            .sidebar .sidebar-logo-img {
                display: none !important;
            }
            .sidebar img.brand-text {
                display: block !important;
            }
            .sidebar .nav-item,
            .sidebar .user-profile {
                justify-content: flex-start !important;
            }
            .sidebar .quick-nav {
                justify-content: flex-start !important;
                gap: 8px;
            }
            .sidebar-logo-section {
                justify-content: flex-start !important;
                padding-left: 14px !important;
            }
            .sidebar-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.65);
                z-index: 40;
                display: none;
                backdrop-filter: blur(2px);
            }
            .sidebar-overlay.show {
                display: block;
            }
            .main-content {
                width: 100% !important;
                margin-left: 0 !important;
            }
            .mobile-menu-btn {
                display: flex;
                position: fixed;
                top: 14px;
                left: 14px;
                z-index: 60;
                width: 38px;
                height: 38px;
                background: #1e293b;
                border: 1px solid #334155;
                border-radius: 8px;
                align-items: center;
                justify-content: center;
                cursor: pointer;
            }
        }

        @media (min-width: 1025px) {
            .mobile-menu-btn {
                display: none;
            }
        }

        /* ── Print ── */
        @media print {
            .no-print,
            .sidebar,
            .sidebar-overlay,
            .mobile-menu-btn {
                display: none !important;
            }
            .main-content {
                width: 100% !important;
                margin-left: 0 !important;
            }
            body {
                background: #fff !important;
            }
            .text-white,
            .text-gray-300,
            .text-gray-400 {
                color: #111 !important;
            }
        }

        /* ── Spinner ── */
        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ── Badge ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .badge-dot {
            width: 0.375rem;
            height: 0.375rem;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
        }
        .badge-success .badge-dot {
            background: #34d399;
        }
        .badge-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
        }
        .badge-warning .badge-dot {
            background: #fbbf24;
        }
        .badge-error {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }
        .badge-error .badge-dot {
            background: #f87171;
        }
        .badge-info {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
        }
        .badge-info .badge-dot {
            background: #60a5fa;
        }
        .badge-neutral {
            background: rgba(100, 116, 139, 0.15);
            color: #94a3b8;
        }
        .badge-neutral .badge-dot {
            background: #94a3b8;
        }

        /* ── Form Elements ── */
        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(51, 65, 85, 0.6);
            border-radius: 0.5rem;
            color: #f1f5f9;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: rgba(246, 139, 30, 0.5);
            box-shadow: 0 0 0 3px rgba(246, 139, 30, 0.1);
        }
        .form-input::placeholder {
            color: #475569;
        }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 0.25rem;
            letter-spacing: 0.01em;
        }
    </style>
</head>
<body class="bg-[#0b1120] font-sans antialiased text-slate-300">

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn text-white" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Overlay -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Toast Container -->
<div id="toastContainer"></div>

<!-- Slim Sidebar -->
<aside class="sidebar flex flex-col" id="sidebar">
    <div class="h-0.5 bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-500 flex-shrink-0"></div>

    <div class="sidebar-logo-section flex-shrink-0">
        <a href="<?php echo base_url('dashboard/home.php'); ?>" class="flex items-center gap-2.5 min-w-0">
            <?php if ($company_logo_exists): ?>
                <img src="<?php echo base_url($company_logo); ?>"
                     class="h-8 w-8 object-contain rounded-lg flex-shrink-0 sidebar-logo-img"
                     alt="<?php echo htmlspecialchars($company_name); ?>">
                <img src="<?php echo base_url($company_logo); ?>"
                     class="brand-text h-7 max-w-[140px] object-contain hidden"
                     alt="<?php echo htmlspecialchars($company_name); ?>">
            <?php else: ?>
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-yellow-500 flex items-center justify-center shadow-lg flex-shrink-0 ring-1 ring-amber-500/30">
                    <span class="text-slate-900 font-bold text-xs"><?php echo htmlspecialchars($company_initials); ?></span>
                </div>
                <div class="brand-text min-w-0">
                    <p class="text-white font-semibold text-sm truncate leading-tight"><?php echo htmlspecialchars($company_name); ?></p>
                    <p class="text-slate-500 text-[10px] leading-tight">POS System</p>
                </div>
            <?php endif; ?>
        </a>
    </div>

    <div class="flex-1 overflow-y-auto overflow-x-hidden">
        
        <div class="px-2.5 py-2.5 border-b border-white/5">
            <div class="user-profile flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-500 to-yellow-500 flex items-center justify-center flex-shrink-0 overflow-hidden ring-2 ring-amber-500/20">
                    <?php if ($user_avatar_url): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                    <span class="text-slate-900 font-bold text-xs"><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-info min-w-0">
                    <p class="text-white font-medium text-xs truncate leading-tight"><?php echo htmlspecialchars($user_name); ?></p>
                    <p class="text-slate-500 text-[10px] capitalize truncate leading-tight"><?php echo htmlspecialchars($user_role); ?></p>
                </div>
            </div>
        </div>

        <div class="quick-nav px-2.5 py-2 flex gap-1.5 border-b border-white/5">
            <a href="<?php echo base_url('dashboard/support.php'); ?>" class="w-7 h-7 rounded-lg bg-white/5 flex items-center justify-center text-slate-400 hover:bg-amber-500/15 hover:text-amber-400 transition-all flex-shrink-0" data-tooltip="Support"><i class="fas fa-life-ring text-[10px]"></i></a>
            <a href="<?php echo base_url('dashboard/docs.php'); ?>" class="w-7 h-7 rounded-lg bg-white/5 flex items-center justify-center text-slate-400 hover:bg-amber-500/15 hover:text-amber-400 transition-all flex-shrink-0" data-tooltip="Docs"><i class="fas fa-file-alt text-[10px]"></i></a>
            <a href="<?php echo base_url('dashboard/settings.php'); ?>" class="w-7 h-7 rounded-lg bg-white/5 flex items-center justify-center text-slate-400 hover:bg-amber-500/15 hover:text-amber-400 transition-all flex-shrink-0" data-tooltip="Settings"><i class="fas fa-cog text-[10px]"></i></a>
        </div>

        <nav class="px-2 py-2 space-y-2.5">
            <?php foreach ($sidebarSections as $section): ?>
            <div>
                <div class="section-label text-[9px] font-bold text-slate-600 uppercase tracking-widest px-2 mb-1.5"><?php echo $section['label']; ?></div>
                <div class="space-y-1">
                    <?php foreach ($section['items'] as $item):
                        $hasChildren = !empty($item['children']);
                        $isActive = ($currentPage === $item['page']) || ($currentDir === $item['dir'] && $currentPage === $item['page']);
                        $badge = $item['badge'] ?? null;
                        $isOpen = $hasChildren && ($isActive || ($currentDir === $item['dir']));
                    ?>
                        <?php
                        $badgeBg  = $badge ? ($badge['tone']==='danger' ? 'bg-red-500/20 text-red-400' : ($badge['tone']==='info' ? 'bg-blue-500/20 text-blue-400' : 'bg-slate-500/20 text-slate-400')) : '';
                        $navBase  = 'nav-item flex items-center gap-2.5 px-2 py-1.5 rounded-lg text-slate-400 hover:bg-white/5 hover:text-amber-400 transition-all w-full';
                        ?>
                        <?php if ($hasChildren): ?>
                            <button type="button" class="<?php echo $navBase; ?> <?php echo $isOpen ? 'active' : ''; ?>" onclick="toggleSubmenu(this)" data-tooltip="<?php echo htmlspecialchars($item['label']); ?>">
                                <i class="fas <?php echo $item['icon']; ?> text-sm w-4 flex-shrink-0"></i>
                                <span class="sidebar-text text-xs flex-1 text-left font-medium"><?php echo $item['label']; ?></span>
                                <?php if ($badge): ?><span class="sidebar-text text-[9px] font-bold px-1.5 py-0.5 rounded-full <?php echo $badgeBg; ?> ml-auto"><?php echo $badge['text']; ?></span><?php endif; ?>
                                <i class="fas fa-chevron-right text-[9px] transition-transform duration-200 sidebar-text <?php echo $isOpen ? 'rotate-90' : ''; ?>"></i>
                            </button>
                            <div class="slide-menu <?php echo $isOpen ? 'open' : ''; ?> space-y-0.5 pl-6">
                                <?php foreach ($item['children'] as $child):
                                    $childActive = ($currentPage === $child['page']) || ($currentDir === $child['dir'] && $currentPage === $child['page']);
                                ?>
                                    <a href="<?php echo $child['url']; ?>" class="block px-2 py-1.5 rounded-lg text-[11px] transition-all <?php echo $childActive ? 'text-amber-400 bg-amber-500/10' : 'text-slate-500 hover:text-amber-400 hover:bg-white/5'; ?>">
                                        <?php echo $child['label']; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <a href="<?php echo $item['url']; ?>" class="<?php echo $navBase; ?> <?php echo $isActive ? 'active' : ''; ?>" data-tooltip="<?php echo htmlspecialchars($item['label']); ?>">
                                <i class="fas <?php echo $item['icon']; ?> text-sm w-4 flex-shrink-0"></i>
                                <span class="sidebar-text text-xs font-medium"><?php echo $item['label']; ?></span>
                                <?php if ($badge): ?><span class="sidebar-text ml-auto text-[9px] font-bold px-1.5 py-0.5 rounded-full <?php echo $badgeBg; ?>"><?php echo $badge['text']; ?></span><?php endif; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </nav>

    </div>

    <div class="p-2 border-t border-white/5 flex-shrink-0">
        <a href="<?php echo base_url('auth/logout.php'); ?>" class="nav-item flex items-center gap-2.5 px-2 py-1.5 rounded-lg text-red-400/70 hover:bg-red-500/10 hover:text-red-400 transition-all" data-tooltip="Sign Out">
            <i class="fas fa-arrow-right-from-bracket text-sm w-4 flex-shrink-0"></i>
            <span class="sidebar-text text-xs font-medium">Sign Out</span>
        </a>
    </div>
</aside>

<div class="main-content">
    <?php include_once __DIR__ . '/_header.php'; ?>

    <?php if (function_exists('render_breadcrumbs')): ?>
    <div class="px-4 md:px-6 py-1.5 border-b border-white/5">
        <?php echo render_breadcrumbs(); ?>
    </div>
    <?php endif; ?>

    <main class="flex-1 p-4 md:p-6 animate-fade-in overflow-y-auto">
        <?php
        if (isset($page_content)) {
            $has_tables = strpos($page_content, '<table') !== false && strpos($page_content, '<script') === false;
            if ($has_tables) {
                $page_content = preg_replace('/<table(.*?)>/i', '<div class="table-responsive"><table$1>', $page_content);
                $page_content = str_replace('</table>', '</table></div>', $page_content);
            }
            echo $page_content;
        }
        ?>
    </main>

    <?php include_once __DIR__ . '/_footer.php'; ?>
</div>

<script>
// Sidebar Functions
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

function toggleSubmenu(btn) {
    btn.classList.toggle('active');
    const chevron = btn.querySelector('.fa-chevron-right');
    if (chevron) chevron.classList.toggle('rotate-90');
    const menu = btn.nextElementSibling;
    if (menu && menu.classList.contains('slide-menu')) {
        menu.classList.toggle('open');
    }
}

// Toast Notification System
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-emerald-500/90' : type === 'error' ? 'bg-red-500/90' : type === 'warning' ? 'bg-amber-500/90' : 'bg-blue-500/90';
    const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
    toast.className = `flex items-center gap-3 px-4 py-2.5 rounded-xl shadow-xl text-sm text-white ${bgColor}`;
    toast.innerHTML = `<i class="fas ${icon}"></i><span>${escapeHtml(message)}</span><button onclick="this.parentElement.remove()" class="ml-4 text-white/70 hover:text-white transition-colors"><i class="fas fa-times"></i></button>`;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Session Keep-Alive
let keepAliveInterval = null;

function startKeepAlive() {
    if (keepAliveInterval) clearInterval(keepAliveInterval);
    keepAliveInterval = setInterval(function() {
        fetch('<?php echo base_url('ajax/keep_alive.php'); ?>', {
            method: 'HEAD',
            cache: 'no-cache',
            credentials: 'same-origin'
        }).catch(function(e) {
            console.debug('Keep-alive ping failed:', e);
        });
    }, 300000);
}

// CSRF Token Helper
function getCsrfToken() {
    const tokenEl = document.querySelector('input[name="csrf_token"]');
    return tokenEl ? tokenEl.value : '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    startKeepAlive();
    
    const csrfToken = getCsrfToken();
    if (csrfToken && window.fetch) {
        const originalFetch = window.fetch;
        window.fetch = function(url, options = {}) {
            if (options.method && options.method !== 'GET' && !options.headers?.['X-CSRF-Token']) {
                options.headers = options.headers || {};
                options.headers['X-CSRF-Token'] = csrfToken;
            }
            return originalFetch(url, options);
        };
    }
});

// Make functions globally available
window.showToast = showToast;
window.toggleSidebar = toggleSidebar;
window.toggleSubmenu = toggleSubmenu;
window.getCsrfToken = getCsrfToken;
</script>

<!-- Security Configuration -->
<script src="<?php echo base_url('ajax/get_security_config.php'); ?>"></script>

<!-- Session Manager - Timeout Warnings -->
<script src="<?php echo asset_url('js/session-manager.js'); ?>"></script>

<!-- Loading States Manager -->
<script src="<?php echo asset_url('js/loading-states.js'); ?>"></script>

<!-- Form Validator -->
<script src="<?php echo asset_url('js/form-validator.js'); ?>"></script>

</body>
</html>