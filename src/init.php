<?php
declare(strict_types=1);

/**
 * Core initialization file for Jakababa POS (SaaS version)
 * Loads configuration, sets up environment, and initializes core components
 *
 * @package Jakababa
 * @subpackage Core
 * @version 2.0
 */

// -----------------------------------------------------------------------------
// Error Reporting (Set early for debugging)
// -----------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// -----------------------------------------------------------------------------
// Path & Session Setup
// -----------------------------------------------------------------------------

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Load paths.php first (defines constants and safe_require)
if (!defined('PATHS_LOADED')) {
    require_once ROOT_PATH . '/src/paths.php';
}

// Start session if not already started (before any output)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    // Use project-local, writable session path
    if (defined('SESSIONS_PATH')) {
        @mkdir(SESSIONS_PATH, 0755, true);
        session_save_path(SESSIONS_PATH);
    }
    session_name('jakababa_saas_sid');

    $cookieParams = [
        'lifetime' => 0,
        'path' => '/JDH_POS/',
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $cookieParams['secure'] = true;
    }
    session_set_cookie_params($cookieParams);

    session_start();
}

// -----------------------------------------------------------------------------
// Load Configuration
// -----------------------------------------------------------------------------

$app_config = [];
if (safe_require('config.php', 'config', false)) {
    $app_config = require CONFIG_PATH . '/config.php';
}

// Default configuration values
$default_config = [
    'timezone' => 'Africa/Nairobi',
    'session_lifetime' => 7200,
    'app_name' => 'Jakababa POS',
    'app_version' => '2.0.0',
    'environment' => 'development',
    'debug' => true,
    'currency' => 'KES',
    'default_tax_rate' => 16,
    'maintenance_mode' => false,
    'maintenance_message' => 'System under maintenance',
];

$app_config = array_merge($default_config, $app_config);

// Set timezone
date_default_timezone_set($app_config['timezone']);

// Set session lifetime
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', $app_config['session_lifetime']);
    ini_set('session.cookie_lifetime', $app_config['session_lifetime']);
}

// -----------------------------------------------------------------------------
// Core File Loading
// -----------------------------------------------------------------------------

// Load database functions (must be first)
safe_require('db.php', 'src', true);

// Load helper functions
safe_require('functions.php', 'src', false);

// Load auth, logger, middleware, saas
safe_require('auth.php', 'src', false);
safe_require('logger.php', 'src', false);
safe_require('middleware.php', 'src', false);
safe_require('saas.php', 'src', false);

// Load optional modules
safe_require('notifications.php', 'src', false);
safe_require('discounts.php', 'src', false);
safe_require('tax.php', 'src', false);
safe_require('receipt.php', 'src', false);

// Load billing files (if directory exists)
if (is_dir(SRC_PATH . '/billing')) {
    safe_require('billing/mpesa.php', 'src', false);
    safe_require('billing/subscriptions.php', 'src', false);
    safe_require('billing/invoices.php', 'src', false);
}

// Load event/hook system before plugins so they can register hooks
safe_require('Events/HookManager.php', 'src', false);

// ---------------------------------------------------------------------------
// Plugin System
// ---------------------------------------------------------------------------
if (class_exists('JDH\\POS\\Plugin\\PluginManager') && $pdo) {
    $pluginManager = new \JDH\POS\Plugin\PluginManager($pdo, $GLOBALS['tenant_id'] ?? null);
    $pluginManager->loadActive();
    $GLOBALS['plugin_manager'] = $pluginManager;
}

// -----------------------------------------------------------------------------
// Define Global Constants from Config (only if not already defined)
// -----------------------------------------------------------------------------

defined('APP_NAME') || define('APP_NAME', $app_config['app_name']);
defined('APP_VERSION') || define('APP_VERSION', $app_config['app_version']);
defined('APP_ENV') || define('APP_ENV', $app_config['environment']);
defined('APP_DEBUG') || define('APP_DEBUG', $app_config['debug']);
defined('CURRENCY') || define('CURRENCY', $app_config['currency']);
defined('TAX_RATE') || define('TAX_RATE', $app_config['default_tax_rate']);

// -----------------------------------------------------------------------------
// Global Database Connection
// -----------------------------------------------------------------------------

global $pdo;
try {
    $pdo = get_db_connection();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $pdo = null;
}

// Set MySQL session variables for triggers
if ($pdo) {
    try {
        if (isset($_SESSION['user_id'])) {
            $pdo->exec("SET @current_user_id = " . (int) $_SESSION['user_id']);
        }
        if (isset($_SESSION['tenant_id'])) {
            $pdo->exec("SET @current_tenant_id = " . (int) $_SESSION['tenant_id']);
        }
        if (isset($_SESSION['branch_id'])) {
            $pdo->exec("SET @current_branch_id = " . (int) $_SESSION['branch_id']);
        }
    } catch (Exception $e) {
        // Silent fail - triggers may not exist yet
    }
}

// -----------------------------------------------------------------------------
// Session Expiration Check
// -----------------------------------------------------------------------------

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $app_config['session_lifetime'])) {
    session_unset();
    session_destroy();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}
$_SESSION['last_activity'] = time();

// -----------------------------------------------------------------------------
// Global Variables (for convenience)
// -----------------------------------------------------------------------------

$GLOBALS['tenant_id'] = get_current_tenant_id();
$GLOBALS['branch_id'] = get_current_branch_id();
$GLOBALS['user_id'] = get_current_user_id();

// -----------------------------------------------------------------------------
// Maintenance Mode Check
// -----------------------------------------------------------------------------

// Skip maintenance check for admin panel requests (let _bootstrap.php handle auth)
$is_admin_request = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false;
if ($app_config['maintenance_mode'] && !$is_admin_request && !isset($_SESSION['is_admin']) && !isset($_SESSION['admin_id'])) {
    $message = $app_config['maintenance_message'];
    http_response_code(503);
    die("<h1>Maintenance Mode</h1><p>{$message}</p>");
}
