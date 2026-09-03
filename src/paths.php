<?php
declare(strict_types=1);
/**
 * Path definitions for JDH POS
 * Centralized path management for the entire application
 * SaaS-ready with multi-tenant support
 *
 * @package Jakababa
 * @subpackage Paths
 * @version 2.0
 */

// Prevent multiple inclusions
if (defined('PATHS_LOADED')) {
    return;
}
define('PATHS_LOADED', true);

// -----------------------------------------------------------------------------
// Path Constants
// -----------------------------------------------------------------------------

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('SRC_PATH')) {
    define('SRC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'src');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'config');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'storage');
}
if (!defined('UPLOADS_PATH')) {
    define('UPLOADS_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'uploads');
}
if (!defined('BACKUPS_PATH')) {
    define('BACKUPS_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'backups');
}
if (!defined('ASSETS_PATH')) {
    define('ASSETS_PATH', PUBLIC_PATH . DIRECTORY_SEPARATOR . 'assets');
}
if (!defined('AJAX_PATH')) {
    define('AJAX_PATH', PUBLIC_PATH . DIRECTORY_SEPARATOR . 'ajax');
}
if (!defined('CACHE_PATH')) {
    define('CACHE_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'cache');
}
if (!defined('LOGS_PATH')) {
    define('LOGS_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs');
}
if (!defined('SESSIONS_PATH')) {
    define('SESSIONS_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'sessions');
}
if (!defined('VENDOR_PATH')) {
    define('VENDOR_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'vendor');
}
if (!defined('TENANT_PATH')) {
    define('TENANT_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'tenants');
}
if (!defined('PLUGINS_PATH')) {
    define('PLUGINS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'plugins');
}

// -----------------------------------------------------------------------------
// Core File Paths (Centralized)
// -----------------------------------------------------------------------------

// Core application files
define('FILE_DB', SRC_PATH . '/db.php');
define('FILE_AUTH', SRC_PATH . '/auth.php');
define('FILE_FUNCTIONS', SRC_PATH . '/functions.php');
define('FILE_LOGGER', SRC_PATH . '/logger.php');
define('FILE_SAAS', SRC_PATH . '/saas.php');
define('FILE_PATHS', SRC_PATH . '/paths.php');
define('FILE_MIDDLEWARE', SRC_PATH . '/middleware.php');
define('FILE_BILLING', SRC_PATH . '/billing.php');
define('FILE_FEATURE_ACCESS', SRC_PATH . '/FeatureAccess.php');

// Config files
define('FILE_CONFIG', CONFIG_PATH . '/config.php');

// Layout files
define('FILE_LAYOUT_APP', PUBLIC_PATH . '/layouts/app.php');
define('FILE_LAYOUT_HEADER', PUBLIC_PATH . '/layouts/header.php');
define('FILE_LAYOUT_FOOTER', PUBLIC_PATH . '/layouts/footer.php');
define('FILE_LAYOUT_SIDEBAR', PUBLIC_PATH . '/layouts/sidebar.php');
define('FILE_LAYOUT_APP_CLOSE', PUBLIC_PATH . '/layouts/app_close.php');

// AJAX endpoints
define('FILE_AJAX_GET_REPORT', AJAX_PATH . '/get_report_data.php');
define('FILE_AJAX_SWITCH_BRANCH', AJAX_PATH . '/switch_branch.php');

// -----------------------------------------------------------------------------
// Directory Creation
// -----------------------------------------------------------------------------

/**
 * Create necessary directories if they don't exist
 */
function ensure_directories_exist(): void
{
    $directories = [
        STORAGE_PATH,
        UPLOADS_PATH,
        UPLOADS_PATH . DIRECTORY_SEPARATOR . 'products',
        UPLOADS_PATH . DIRECTORY_SEPARATOR . 'categories',
        UPLOADS_PATH . DIRECTORY_SEPARATOR . 'avatars',
        UPLOADS_PATH . DIRECTORY_SEPARATOR . 'invoices',
        UPLOADS_PATH . DIRECTORY_SEPARATOR . 'temp',
        BACKUPS_PATH,
        CACHE_PATH,
        LOGS_PATH,
        SESSIONS_PATH,
        TENANT_PATH
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}
ensure_directories_exist();

// -----------------------------------------------------------------------------
// Safe File Require
// -----------------------------------------------------------------------------

/**
 * Safely require a file by checking multiple possible locations
 *
 * @param string $file File name or relative path (e.g., 'auth.php' or 'db.php')
 * @param string $type Type of file ('src', 'config', 'public', 'ajax')
 * @param bool $required Whether to throw exception if file not found
 * @return bool True if file was loaded, false otherwise
 * @throws RuntimeException If file is required but not found
 */
function safe_require(string $file, string $type = 'src', bool $required = false): bool
{
    static $loaded_files = [];

    $cache_key = $type . ':' . $file;
    if (isset($loaded_files[$cache_key])) {
        return true;
    }

    if (!str_ends_with($file, '.php')) {
        $file .= '.php';
    }

    $paths = [];
    switch ($type) {
        case 'src':
            $paths = [
                SRC_PATH . DIRECTORY_SEPARATOR . $file,
                ROOT_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $file,
                __DIR__ . DIRECTORY_SEPARATOR . $file
            ];
            break;
        case 'config':
            $paths = [
                CONFIG_PATH . DIRECTORY_SEPARATOR . $file,
                ROOT_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $file
            ];
            break;
        case 'public':
            $paths = [
                PUBLIC_PATH . DIRECTORY_SEPARATOR . $file,
                ROOT_PATH . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $file
            ];
            break;
        case 'ajax':
            $paths = [
                AJAX_PATH . DIRECTORY_SEPARATOR . $file,
                PUBLIC_PATH . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . $file
            ];
            break;
        case 'vendor':
            $paths = [
                VENDOR_PATH . DIRECTORY_SEPARATOR . $file,
                ROOT_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $file
            ];
            break;
        default:
            $paths = [
                SRC_PATH . DIRECTORY_SEPARATOR . $file,
                CONFIG_PATH . DIRECTORY_SEPARATOR . $file,
                PUBLIC_PATH . DIRECTORY_SEPARATOR . $file,
                AJAX_PATH . DIRECTORY_SEPARATOR . $file,
                ROOT_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $file,
                ROOT_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $file,
                ROOT_PATH . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $file,
                ROOT_PATH . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . $file,
                __DIR__ . DIRECTORY_SEPARATOR . $file
            ];
    }

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded_files[$cache_key] = true;
            return true;
        }
    }

    $error_msg = "safe_require: Could not find file '{$file}' (type: {$type})";
    error_log($error_msg);

    if ($required) {
        throw new RuntimeException($error_msg);
    }

    return false;
}

/**
 * Load all core application files in one call
 * Use this for AJAX endpoints that need all core functions
 * 
 * @return bool True if all files loaded successfully
 */
function load_core_files(): bool
{
    // Load in order - db first, then auth, then functions
    if (!defined('DB_LOADED')) {
        safe_require('db.php', 'src', true);
    }
    
    if (!function_exists('get_db_connection')) {
        safe_require('db.php', 'src', true);
    }
    
    if (!function_exists('require_login')) {
        safe_require('auth.php', 'src', true);
    }
    
    if (!function_exists('get_current_user_id')) {
        safe_require('auth.php', 'src', true);
    }
    
    if (!function_exists('format_currency')) {
        safe_require('functions.php', 'src', true);
    }
    
    if (!function_exists('log_activity')) {
        safe_require('logger.php', 'src', true);
    }
    
    return true;
}

// -----------------------------------------------------------------------------
// Autoloader Registration
// -----------------------------------------------------------------------------+

/**
 * Register class autoloader (Composer first, then custom)
 */
function register_autoloader(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    // Check for Composer autoloader
    $composer_autoload = VENDOR_PATH . DIRECTORY_SEPARATOR . 'autoload.php';
    if (file_exists($composer_autoload)) {
        require_once $composer_autoload;
        return;
    }

    // Custom autoloader for project classes
    spl_autoload_register(function ($class) {
        $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $locations = [
            SRC_PATH . DIRECTORY_SEPARATOR . $class_path . '.php',
            SRC_PATH . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $class_path . '.php',
            ROOT_PATH . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $class_path . '.php'
        ];

        foreach ($locations as $location) {
            if (file_exists($location)) {
                require_once $location;
                return true;
            }
        }
        return false;
    });
}
register_autoloader();

// -----------------------------------------------------------------------------
// URL Helpers (SaaS-aware)
// -----------------------------------------------------------------------------

/**
 * Get base URL for the application
 *
 * @param string $path Optional path to append
 * @param bool $absolute Whether to return absolute URL
 * @param int|null $tenant_id Tenant ID for tenant-specific URL (currently unused)
 * @return string Full URL
 */
function base_url(string $path = '', bool $absolute = true, ?int $tenant_id = null): string
{
    // Build URL based on configuration
    $config = defined('BASE_URL') ? BASE_URL : 'http://localhost/JDH_POS/public';
    
    // Ensure proper URL construction
    $url = rtrim($config, '/');
    
    if ($path !== '') {
        $normalized = ltrim($path, '/');
        $url .= '/' . $normalized;
    }

    return $url;
}

/**
 * Get asset URL
 *
 * @param string $path
 * @return string
 */
function asset_url(string $path = ''): string
{
    $base = base_url('assets/' . ltrim($path, '/'));

    // Assets live under /public/, not /admin/.
    // When called from admin context, base_url() resolves to /admin/assets/...
    // which doesn't exist. Rewrite to /public/assets/... instead.
    return str_replace('/admin/assets/', '/public/assets/', $base);
}

/**
 * Get upload URL (SaaS-aware – includes tenant folder if company ID is provided)
 *
 * @param string $path
 * @param int|null $tenant_id
 * @return string
 */
function upload_url(string $path = '', ?int $tenant_id = null): string
{
    if ($tenant_id === null && function_exists('get_current_tenant_id')) {
        $tenant_id = get_current_tenant_id();
    }

    $tenant_prefix = $tenant_id ? 'tenants/' . $tenant_id . '/' : '';
    return base_url($tenant_prefix . 'uploads/' . ltrim($path, '/'));
}

/**
 * Get tenant-specific upload path (SaaS feature)
 *
 * @param int|null $tenant_id
 * @param string $subpath
 * @return string
 */
function tenant_upload_path(?int $tenant_id = null, string $subpath = ''): string
{
    if ($tenant_id === null && function_exists('get_current_tenant_id')) {
        $tenant_id = get_current_tenant_id();
    }

    if (!$tenant_id) {
        // Fallback to main uploads
        return UPLOADS_PATH . ($subpath ? DIRECTORY_SEPARATOR . ltrim($subpath, DIRECTORY_SEPARATOR) : '');
    }

    $tenant_dir = TENANT_PATH . DIRECTORY_SEPARATOR . $tenant_id . DIRECTORY_SEPARATOR . 'uploads';
    if ($subpath) {
        $tenant_dir .= DIRECTORY_SEPARATOR . ltrim($subpath, DIRECTORY_SEPARATOR);
    }

    if (!is_dir($tenant_dir)) {
        mkdir($tenant_dir, 0755, true);
    }

    return $tenant_dir;
}

/**
 * Get full system path
 *
 * @param string $path
 * @return string
 */
function system_path(string $path = ''): string
{
    return ROOT_PATH . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

// -----------------------------------------------------------------------------
// Convenience URLs
// -----------------------------------------------------------------------------

function login_url(?string $error = null, ?string $return_to = null): string
{
    $url = base_url('auth/login.php');
    $params = [];
    if ($error) {
        $params[] = 'error=' . urlencode($error);
    }
    if ($return_to) {
        $params[] = 'return=' . urlencode($return_to);
    }
    if ($params) {
        $url .= '?' . implode('&', $params);
    }
    return $url;
}

function dashboard_url(): string
{
    // Always resolve to public/dashboard regardless of calling context
    $base = base_url('');
    // If called from admin context, base is /JDH_POS/admin — swap to /JDH_POS/public
    $base = preg_replace('#/admin$#', '/public', $base);
    return $base . '/dashboard/home.php';
}

/**
 * Build an absolute URL to an admin page.
 * Works regardless of which context (public or admin) it's called from.
 */
function admin_url(string $path = 'index.php'): string
{
    $base = base_url('');

    if (!empty($_SERVER['HTTP_HOST'])) {
        $parts = parse_url($base);
        $scheme = is_https() ? 'https' : 'http';
        $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . ($parts['path'] ?? '');
    }

    // If called from public context, base is /JDH_POS/public — swap to /JDH_POS/admin
    $base = preg_replace('#/public$#', '/admin', $base);
    return $base . '/' . ltrim($path, '/');
}

/**
 * Get dashboard URL based on user's plan
 * Redirects users to appropriate dashboard based on their subscription plan
 * 
 * @return string URL to redirect to after login
 */
function get_plan_based_redirect_url(): string
{
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
        return login_url();
    }

    $user_id = $_SESSION['user_id'];
    $tenant_id = $_SESSION['tenant_id'];

    // Get user's current plan
    $plan = get_user_plan($user_id, $tenant_id);

    if (!$plan) {
        // No plan found, redirect to pricing
        return base_url('pricing.php');
    }

    $plan_slug = $plan['slug'] ?? '';

    // Redirect based on plan
    switch ($plan_slug) {
        case 'free':
            // Free plan - limited dashboard
            return base_url('dashboard/home.php?plan=free');

        case 'starter':
        case 'professional':
        case 'enterprise':
            // Paid plans - full dashboard
            return base_url('dashboard/home.php');

        default:
            // Unknown plan, redirect to pricing
            return base_url('pricing.php');
    }
}

/**
 * Get user's current plan
 * 
 * @param int $user_id User ID
 * @param int $tenant_id Tenant ID
 * @return array|null Plan data or null if not found
 */
function get_user_plan(int $user_id, int $tenant_id): ?array
{
    $db = get_db_connection();
    if (!$db) {
        return null;
    }

    try {
        // Get tenant's active subscription
        $stmt = $db->prepare("
            SELECT sp.* 
            FROM subscriptions s
            JOIN subscription_plans sp ON s.plan_id = sp.id
            WHERE s.tenant_id = ? 
            AND s.status = 'active'
            ORDER BY s.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$tenant_id]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subscription) {
            // No active subscription, check if company has a default plan
            $stmt = $db->prepare("
                SELECT sp.* 
                FROM companies c
                JOIN subscription_plans sp ON c.default_plan_id = sp.id
                WHERE c.id = ?
            ");
            $stmt->execute([$tenant_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Get the plan details
        $stmt = $db->prepare("
            SELECT * FROM subscription_plans WHERE id = ?
        ");
        $stmt->execute([$subscription['plan_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user plan: " . $e->getMessage());
        return null;
    }
}

function logout_url(): string
{
    return base_url('auth/logout.php');
}

/**
 * Get storefront URL for a tenant
 *
 * @param int|null $tenant_id Tenant ID to append as path segment
 * @return string
 */
function storefront_url(?int $tenant_id = null): string
{
    $config_file = CONFIG_PATH . DIRECTORY_SEPARATOR . 'app.php';
    $storefront = 'http://localhost:3000';

    if (file_exists($config_file)) {
        $config = require $config_file;
        $storefront = $config['storefront_url'] ?? $storefront;
    }

    if (getenv('STOREFRONT_URL')) {
        $storefront = getenv('STOREFRONT_URL');
    }

    $url = rtrim($storefront, '/');

    if ($tenant_id) {
        $url .= '/' . $tenant_id;
    }

    return $url;
}

function register_url(): string
{
    return base_url('auth/register_company.php');
}

function forgot_password_url(): string
{
    return base_url('auth/forgot_password.php');
}

// -----------------------------------------------------------------------------
// Environment & Logging
// -----------------------------------------------------------------------------

/**
 * Check if application is in development mode
 *
 * @return bool
 */
function is_dev_mode(): bool
{
    if (getenv('APP_ENV') === 'development') {
        return true;
    }

    $config_file = CONFIG_PATH . DIRECTORY_SEPARATOR . 'config.php';
    if (file_exists($config_file)) {
        $config = require $config_file;
        return isset($config['environment']) && $config['environment'] === 'development';
    }

    return false;
}

/**
 * Get current environment
 *
 * @return string
 */
function get_environment(): string
{
    if (is_dev_mode()) {
        return 'development';
    }
    if (getenv('APP_ENV') === 'staging') {
        return 'staging';
    }
    return 'production';
}

/**
 * Get client IP address (if not already defined)
 */
if (!function_exists('get_client_ip')) {
    function get_client_ip(): string
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}

/**
 * Log a message to file (SaaS-aware)
 */
if (!function_exists('log_message')) {
    function log_message(string $message, string $level = 'info', ?int $tenant_id = null): void
    {
        if ($tenant_id === null && function_exists('get_current_tenant_id')) {
            $tenant_id = get_current_tenant_id();
        }

        $log_dir = LOGS_PATH;
        if ($tenant_id) {
            $log_dir = TENANT_PATH . DIRECTORY_SEPARATOR . $tenant_id . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($log_dir)) {
                mkdir($log_dir, 0755, true);
            }
        }

        $log_file = $log_dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = get_client_ip();
        $user_id = $_SESSION['user_id'] ?? 'guest';
        $company = $tenant_id ? "[Company: {$tenant_id}]" : '';

        $log_entry = sprintf(
            "[%s] [%s] [User: %s] [IP: %s] %s %s%s",
            $timestamp,
            strtoupper($level),
            $user_id,
            $ip,
            $company,
            $message,
            PHP_EOL
        );

        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// -----------------------------------------------------------------------------
// Debug Helper (Development Only)
// -----------------------------------------------------------------------------

/**
 * Display debug information about paths (only in dev mode)
 */
function debug_paths(): void
{
    if (!is_dev_mode()) {
        return;
    }

    echo '<div style="background: #1F2937; color: white; padding: 20px; margin: 20px; border-radius: 10px; font-family: monospace;">';
    echo '<h2 style="color: #FBBF24; margin-bottom: 15px;">🔧 Path Debug Information</h2>';

    echo '<h3 style="color: #60A5FA;">System Paths:</h3>';
    echo '<pre>';
    echo "ROOT_PATH: " . ROOT_PATH . "\n";
    echo "SRC_PATH: " . SRC_PATH . "\n";
    echo "PUBLIC_PATH: " . PUBLIC_PATH . "\n";
    echo "CONFIG_PATH: " . CONFIG_PATH . "\n";
    echo "STORAGE_PATH: " . STORAGE_PATH . "\n";
    echo "UPLOADS_PATH: " . UPLOADS_PATH . "\n";
    echo "BACKUPS_PATH: " . BACKUPS_PATH . "\n";
    echo "ASSETS_PATH: " . ASSETS_PATH . "\n";
    echo "AJAX_PATH: " . AJAX_PATH . "\n";
    echo "CACHE_PATH: " . CACHE_PATH . "\n";
    echo "LOGS_PATH: " . LOGS_PATH . "\n";
    echo "VENDOR_PATH: " . VENDOR_PATH . "\n";
    echo "TENANT_PATH: " . TENANT_PATH . "\n";

    echo "\n<h3 style='color: #60A5FA;'>URLs:</h3>";
    echo "Base URL: " . base_url() . "\n";
    echo "Asset URL: " . asset_url() . "\n";
    echo "Upload URL: " . upload_url() . "\n";

    echo "\n<h3 style='color: #60A5FA;'>Environment:</h3>";
    echo "Environment: " . get_environment() . "\n";
    echo "Dev Mode: " . (is_dev_mode() ? 'Yes' : 'No') . "\n";
    echo "Client IP: " . get_client_ip() . "\n";

    if (function_exists('get_current_tenant_id')) {
        echo "Current Tenant ID: " . (get_current_tenant_id() ?? 'None') . "\n";
    }
    if (function_exists('get_current_branch_id')) {
        echo "Current Branch ID: " . (get_current_branch_id() ?? 'None') . "\n";
    }
    if (function_exists('get_current_user_id')) {
        echo "Current User ID: " . (get_current_user_id() ?? 'None') . "\n";
    }

    echo "\n<h3 style='color: #60A5FA;'>File Checks:</h3>";
    $files = [
        'auth.php' => SRC_PATH . DIRECTORY_SEPARATOR . 'auth.php',
        'db.php' => SRC_PATH . DIRECTORY_SEPARATOR . 'db.php',
        'init.php' => SRC_PATH . DIRECTORY_SEPARATOR . 'init.php',
        'config.php' => CONFIG_PATH . DIRECTORY_SEPARATOR . 'config.php'
    ];
    foreach ($files as $name => $path) {
        echo "{$name} exists: " . (file_exists($path) ? '✅ YES' : '❌ NO') . "\n";
    }

    echo "Storage directory writable: " . (is_writable(STORAGE_PATH) ? '✅ YES' : '❌ NO') . "\n";
    echo "Uploads directory writable: " . (is_writable(UPLOADS_PATH) ? '✅ YES' : '❌ NO') . "\n";
    echo "Logs directory writable: " . (is_writable(LOGS_PATH) ? '✅ YES' : '❌ NO') . "\n";
    echo "Cache directory writable: " . (is_writable(CACHE_PATH) ? '✅ YES' : '❌ NO') . "\n";
    echo "Tenant directory writable: " . (is_writable(TENANT_PATH) ? '✅ YES' : '❌ NO') . "\n";

    echo '</pre>';
    echo '</div>';
}

// -----------------------------------------------------------------------------
// Shutdown Handler (Log Fatal Errors)
// -----------------------------------------------------------------------------

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $tenant_id = function_exists('get_current_tenant_id') ? get_current_tenant_id() : null;
        if (function_exists('log_message')) {
            log_message(
                sprintf(
                    "Fatal error: %s in %s on line %d",
                    $error['message'],
                    $error['file'],
                    $error['line']
                ),
                'error',
                $tenant_id
            );
        } else {
            error_log(sprintf(
                "Fatal error: %s in %s on line %d",
                $error['message'],
                $error['file'],
                $error['line']
            ));
        }
    }
});
