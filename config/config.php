<?php
/**
 * Configuration file for Jakababa POS (SaaS Version)
 * 
 * Loads environment variables from .env and sets up application constants.
 * 
 * @package JDH_POS
 * @version 2.0
 */

// ----------------------------------------------------------------------
// Load environment variables from .env file (if present)
// ----------------------------------------------------------------------
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove inline comments
            $commentPos = strpos($value, '#');
            if ($commentPos !== false) {
                $value = trim(substr($value, 0, $commentPos));
            }
            // Remove quotes if present
            if (preg_match('/^["\'].*["\']$/', $value)) {
                $value = substr($value, 1, -1);
            }
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// ----------------------------------------------------------------------
// Helper function to get environment variables with defaults
// ----------------------------------------------------------------------
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        
        // Handle boolean values
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }
        
        // Handle null values
        if (strtolower($value) === 'null') {
            return null;
        }
        
        return $value;
    }
}

// ----------------------------------------------------------------------
// Default configuration (overridden by environment variables)
// ----------------------------------------------------------------------
$config = [];

// Database Configuration
$config['db'] = [
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', 3306),
    'name' => env('DB_NAME', 'jdh_pos'),
    'user' => env('DB_USER', 'root'),
    'pass' => env('DB_PASS', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
    'timezone' => env('DB_TIMEZONE', '+03:00'),
];

// Application Configuration
$config['app'] = [
    'name' => env('APP_NAME', 'Jakababa POS'),
    'version' => env('APP_VERSION', '2.0.0'),
    'env' => env('APP_ENV', 'development'),
    'debug' => env('APP_DEBUG', false),
    'timezone' => env('APP_TIMEZONE', 'Africa/Nairobi'),
    'url' => env('APP_URL', 'http://localhost'),
    'use_subdomains' => env('APP_USE_SUBDOMAINS', false),
    'encryption_key' => env('ENCRYPTION_KEY', ''),
    'session_lifetime' => env('SESSION_LIFETIME', 7200),
    'maintenance_mode' => env('MAINTENANCE_MODE', false),
    'maintenance_message' => env('MAINTENANCE_MESSAGE', 'System under maintenance. Please check back later.'),
];

// Email Configuration
$config['mail'] = [
    'from_email' => env('EMAIL_FROM', 'noreply@jakababa.com'),
    'from_name' => env('EMAIL_FROM_NAME', 'Jakababa POS'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@jakababa.com'),
    'backup_email' => env('BACKUP_EMAIL', 'backup@jakababa.com'),
    'smtp_host' => env('SMTP_HOST', ''),
    'smtp_port' => env('SMTP_PORT', 587),
    'smtp_user' => env('SMTP_USER', ''),
    'smtp_pass' => env('SMTP_PASS', ''),
    'smtp_encryption' => env('SMTP_ENCRYPTION', 'tls'),
];

// Business Configuration
$config['business'] = [
    'default_currency' => env('DEFAULT_CURRENCY', 'KES'),
    'default_tax_rate' => env('DEFAULT_TAX_RATE', 16),
    'default_plan_id' => env('DEFAULT_PLAN_ID', 1),
    'allow_self_register' => env('ALLOW_SELF_REGISTER', true),
    'allow_tenant_register' => env('ALLOW_TENANT_REGISTER', true),
];

// File Upload Configuration
$config['upload'] = [
    'max_file_size' => env('MAX_FILE_SIZE', 10485760),
    'allowed_extensions' => explode(',', env('ALLOWED_UPLOAD_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx')),
    'allowed_mime_types' => [
        'image/jpeg', 'image/png', 'image/gif', 'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ],
];

// Cache Configuration
$config['cache'] = [
    'enabled' => env('CACHE_ENABLED', true),
    'lifetime' => env('CACHE_LIFETIME', 3600),
    'driver' => env('CACHE_DRIVER', 'file'), // file, redis, memcached
    'prefix' => env('CACHE_PREFIX', 'jakababa_'),
];

// Logging Configuration
$config['logging'] = [
    'enabled' => env('LOG_ERRORS', true),
    'level' => env('LOG_LEVEL', 'error'),
    'path' => defined('STORAGE_PATH') ? STORAGE_PATH . '/logs' : dirname(__DIR__) . '/storage/logs',
];

// Security Configuration
$config['security'] = [
    'csrf_lifetime' => env('CSRF_LIFETIME', 3600),
    'password_min_length' => env('PASSWORD_MIN_LENGTH', 8),
    'password_require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
    'password_require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
    'password_require_number' => env('PASSWORD_REQUIRE_NUMBER', true),
    'password_require_special' => env('PASSWORD_REQUIRE_SPECIAL', false),
    'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),
    'login_lockout_time' => env('LOGIN_LOCKOUT_TIME', 900), // 15 minutes in seconds
    'session_idle_timeout' => env('SESSION_IDLE_TIMEOUT', 1800), // 30 minutes
];

// API Configuration
$config['api'] = [
    'rate_limit' => env('API_RATE_LIMIT', 1000),
    'rate_limit_window' => env('API_RATE_LIMIT_WINDOW', 3600), // 1 hour in seconds
    'default_scope' => env('API_DEFAULT_SCOPE', 'read'),
];

// Payment Gateway Configuration
$config['payment'] = [
    'mpesa_enabled' => env('MPESA_ENABLED', true),
    'mpesa_consumer_key' => env('MPESA_CONSUMER_KEY', ''),
    'mpesa_consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),
    'mpesa_shortcode' => env('MPESA_SHORTCODE', ''),
    'mpesa_passkey' => env('MPESA_PASSKEY', ''),
    'stripe_enabled' => env('STRIPE_ENABLED', false),
    'stripe_public_key' => env('STRIPE_PUBLIC_KEY', ''),
    'stripe_secret_key' => env('STRIPE_SECRET_KEY', ''),
    'flutterwave_enabled' => env('FLUTTERWAVE_ENABLED', false),
    'flutterwave_public_key' => env('FLUTTERWAVE_PUBLIC_KEY', ''),
    'flutterwave_secret_key' => env('FLUTTERWAVE_SECRET_KEY', ''),
];

// Brand Colors
$config['brand'] = [
    'primary' => env('BRAND_PRIMARY', '#f59e0b'),
    'primary_dark' => env('BRAND_PRIMARY_DARK', '#d97706'),
    'primary_light' => env('BRAND_PRIMARY_LIGHT', '#fbbf24'),
    'accent' => env('BRAND_ACCENT', '#f59e0b'),
    'accent_dark' => env('BRAND_ACCENT_DARK', '#d97706'),
    'success' => env('BRAND_SUCCESS', '#10b981'),
    'warning' => env('BRAND_WARNING', '#f59e0b'),
    'danger' => env('BRAND_DANGER', '#ef4444'),
    'info' => env('BRAND_INFO', '#3b82f6'),
];

// ----------------------------------------------------------------------
// Validate critical settings & secrets
// ----------------------------------------------------------------------
$validatorPath = dirname(__DIR__) . '/src/Security/SecretsValidator.php';
if (file_exists($validatorPath)) {
    require_once $validatorPath;
    $validator = new \Jakababa\Security\SecretsValidator();
    // In production, throw on error; in development, log warnings only
    $validator->validate($config['app']['env'] === 'production');
}

// Legacy validation (fallback if SecretsValidator is not available)
if (empty($config['app']['encryption_key']) && $config['app']['env'] === 'production') {
    throw new RuntimeException('ENCRYPTION_KEY is required in production mode. Please set it in .env');
}

// Validate database configuration
if (empty($config['db']['host']) || empty($config['db']['name'])) {
    throw new RuntimeException('Database configuration is incomplete. Please check .env file');
}

// ----------------------------------------------------------------------
// Set PHP runtime settings
// ----------------------------------------------------------------------
date_default_timezone_set($config['app']['timezone']);
error_reporting($config['app']['debug'] ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');
ini_set('log_errors', $config['logging']['enabled'] ? '1' : '0');

if ($config['logging']['enabled']) {
    $logDir = $config['logging']['path'];
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/php-error.log');
}

// Session settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', $config['app']['session_lifetime']);
    ini_set('session.cookie_lifetime', $config['app']['session_lifetime']);
}

// Upload settings
ini_set('upload_max_filesize', ceil($config['upload']['max_file_size'] / 1048576) . 'M');
ini_set('post_max_size', ceil(($config['upload']['max_file_size'] * 2) / 1048576) . 'M');
ini_set('memory_limit', '512M');

// Performance settings
ini_set('output_buffering', '4096');
ini_set('implicit_flush', '0');
ini_set('max_execution_time', '120');

// Enable gzip compression if available and not already enabled
if (!ini_get('zlib.output_compression') && !ob_get_level()) {
    ob_start('ob_gzhandler');
}

// ----------------------------------------------------------------------
// Define application constants (for backward compatibility)
// ----------------------------------------------------------------------
defined('APP_NAME') || define('APP_NAME', $config['app']['name']);
defined('APP_VERSION') || define('APP_VERSION', $config['app']['version']);
defined('APP_ENV') || define('APP_ENV', $config['app']['env']);
defined('APP_DEBUG') || define('APP_DEBUG', $config['app']['debug']);
defined('APP_URL') || define('APP_URL', $config['app']['url']);
defined('CURRENCY') || define('CURRENCY', $config['business']['default_currency']);
defined('TAX_RATE') || define('TAX_RATE', $config['business']['default_tax_rate']);
defined('DEFAULT_TIMEZONE') || define('DEFAULT_TIMEZONE', $config['app']['timezone']);

// Brand color constants
defined('APP_BRAND_COLOR_PRIMARY') || define('APP_BRAND_COLOR_PRIMARY', $config['brand']['primary']);
defined('APP_BRAND_COLOR_PRIMARY_DARK') || define('APP_BRAND_COLOR_PRIMARY_DARK', $config['brand']['primary_dark']);
defined('APP_BRAND_COLOR_PRIMARY_LIGHT') || define('APP_BRAND_COLOR_PRIMARY_LIGHT', $config['brand']['primary_light']);
defined('APP_BRAND_COLOR_ACCENT') || define('APP_BRAND_COLOR_ACCENT', $config['brand']['accent']);
defined('APP_BRAND_COLOR_ACCENT_DARK') || define('APP_BRAND_COLOR_ACCENT_DARK', $config['brand']['accent_dark']);
defined('APP_BRAND_COLOR_SUCCESS') || define('APP_BRAND_COLOR_SUCCESS', $config['brand']['success']);
defined('APP_BRAND_COLOR_WARNING') || define('APP_BRAND_COLOR_WARNING', $config['brand']['warning']);
defined('APP_BRAND_COLOR_DANGER') || define('APP_BRAND_COLOR_DANGER', $config['brand']['danger']);
defined('APP_BRAND_COLOR_INFO') || define('APP_BRAND_COLOR_INFO', $config['brand']['info']);

// Registration settings
defined('ALLOW_SELF_REGISTER') || define('ALLOW_SELF_REGISTER', $config['business']['allow_self_register']);
defined('ALLOW_TENANT_REGISTER') || define('ALLOW_TENANT_REGISTER', $config['business']['allow_tenant_register']);

// Session constants
defined('SESSION_LIFETIME') || define('SESSION_LIFETIME', $config['app']['session_lifetime']);
defined('SESSION_IDLE_TIMEOUT') || define('SESSION_IDLE_TIMEOUT', $config['security']['session_idle_timeout']);

// ----------------------------------------------------------------------
// Create helper function to access config
// ----------------------------------------------------------------------
if (!function_exists('config')) {
    /**
     * Get configuration value by dot notation
     * 
     * @param string $key Configuration key (e.g., 'db.host', 'app.name')
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    function config($key, $default = null) {
        static $config = null;
        
        if ($config === null) {
            $config = $GLOBALS['config'] ?? [];
        }
        
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $segment) {
            if (isset($value[$segment])) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
}

// ----------------------------------------------------------------------
// Return the configuration array (for scripts that need it)
// ----------------------------------------------------------------------
return $config;