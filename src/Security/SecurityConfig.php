<?php
/**
 * Security Configuration
 * Centralized configuration for all security-related settings
 * All values are loaded from environment or use sensible defaults
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class SecurityConfig {
    
    private static array $config = [];
    private static bool $loaded = false;
    
    /**
     * Load all security configuration
     */
    public static function load(): void {
        if (self::$loaded) return;
        
        self::$config = [
            // Session settings
            'session' => [
                'timeout' => (int) (getenv('SESSION_TIMEOUT') ?: 1800), // 30 minutes
                'warning_time' => (int) (getenv('SESSION_WARNING_TIME') ?: 300), // 5 minutes before timeout
                'check_interval' => (int) (getenv('SESSION_CHECK_INTERVAL') ?: 10), // Check every 10 seconds
                'cookie_name' => getenv('SESSION_COOKIE_NAME') ?: 'jakababa_saas_sid',
                'regenerate_interval' => (int) (getenv('SESSION_REGENERATE_INTERVAL') ?: 1800), // 30 minutes
            ],
            
            // Rate limiting
            'rate_limit' => [
                'login_attempts' => (int) (getenv('RATE_LIMIT_LOGIN_ATTEMPTS') ?: 5),
                'login_window' => (int) (getenv('RATE_LIMIT_LOGIN_WINDOW') ?: 900), // 15 minutes
                'api_requests' => (int) (getenv('RATE_LIMIT_API_REQUESTS') ?: 100),
                'api_window' => (int) (getenv('RATE_LIMIT_API_WINDOW') ?: 60), // 1 minute
            ],
            
            // Password policy
            'password' => [
                'min_length' => (int) (getenv('PASSWORD_MIN_LENGTH') ?: 8),
                'max_length' => (int) (getenv('PASSWORD_MAX_LENGTH') ?: 128),
                'require_uppercase' => filter_var(getenv('PASSWORD_REQUIRE_UPPERCASE') ?: 'true', FILTER_VALIDATE_BOOL),
                'require_lowercase' => filter_var(getenv('PASSWORD_REQUIRE_LOWERCASE') ?: 'true', FILTER_VALIDATE_BOOL),
                'require_numbers' => filter_var(getenv('PASSWORD_REQUIRE_NUMBERS') ?: 'true', FILTER_VALIDATE_BOOL),
                'require_symbols' => filter_var(getenv('PASSWORD_REQUIRE_SYMBOLS') ?: 'true', FILTER_VALIDATE_BOOL),
                'history_count' => (int) (getenv('PASSWORD_HISTORY_COUNT') ?: 5),
                'prevent_common' => filter_var(getenv('PASSWORD_PREVENT_COMMON') ?: 'true', FILTER_VALIDATE_BOOL),
            ],
            
            // Plan limits
            'plans' => [
                'free' => [
                    'max_users' => (int) (getenv('PLAN_FREE_MAX_USERS') ?: 1),
                    'max_products' => (int) (getenv('PLAN_FREE_MAX_PRODUCTS') ?: 100),
                    'max_branches' => (int) (getenv('PLAN_FREE_MAX_BRANCHES') ?: 1),
                    'storage_gb' => (int) (getenv('PLAN_FREE_STORAGE') ?: 1),
                ],
                'starter' => [
                    'max_users' => (int) (getenv('PLAN_STARTER_MAX_USERS') ?: 3),
                    'max_products' => (int) (getenv('PLAN_STARTER_MAX_PRODUCTS') ?: 1000),
                    'max_branches' => (int) (getenv('PLAN_STARTER_MAX_BRANCHES') ?: 1),
                    'storage_gb' => (int) (getenv('PLAN_STARTER_STORAGE') ?: 5),
                ],
                'professional' => [
                    'max_users' => PHP_INT_MAX,
                    'max_products' => PHP_INT_MAX,
                    'max_branches' => PHP_INT_MAX,
                    'storage_gb' => (int) (getenv('PLAN_PRO_STORAGE') ?: 50),
                ],
            ],
            
            // Redis settings (for rate limiting)
            'redis' => [
                'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
                'port' => (int) (getenv('REDIS_PORT') ?: 6379),
                'password' => getenv('REDIS_PASSWORD') ?: null,
                'enabled' => filter_var(getenv('REDIS_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL),
            ],
            
            // Audit logging
            'audit' => [
                'enabled' => filter_var(getenv('AUDIT_LOGGING_ENABLED') ?: 'true', FILTER_VALIDATE_BOOL),
                'retention_days' => (int) (getenv('AUDIT_RETENTION_DAYS') ?: 90),
                'sensitive_fields' => array_filter(explode(',', getenv('AUDIT_SENSITIVE_FIELDS') ?: 'password,ssn,credit_card,token')),
            ],
            
            // CSRF protection
            'csrf' => [
                'enabled' => filter_var(getenv('CSRF_ENABLED') ?: 'true', FILTER_VALIDATE_BOOL),
                'token_lifetime' => (int) (getenv('CSRF_TOKEN_LIFETIME') ?: 3600), // 1 hour
            ],
            
            // Brute force protection
            'brute_force' => [
                'enabled' => filter_var(getenv('BRUTE_FORCE_ENABLED') ?: 'true', FILTER_VALIDATE_BOOL),
                'max_attempts' => (int) (getenv('BRUTE_FORCE_MAX_ATTEMPTS') ?: 5),
                'lockout_duration' => (int) (getenv('BRUTE_FORCE_LOCKOUT_DURATION') ?: 1800), // 30 minutes
                'lockout_multiplier' => (int) (getenv('BRUTE_FORCE_LOCKOUT_MULTIPLIER') ?: 2),
            ],
            
            // Data access scope
            'data_access' => [
                'enforce_tenant_isolation' => filter_var(getenv('ENFORCE_TENANT_ISOLATION') ?: 'true', FILTER_VALIDATE_BOOL),
                'enforce_branch_isolation' => filter_var(getenv('ENFORCE_BRANCH_ISOLATION') ?: 'true', FILTER_VALIDATE_BOOL),
                'strict_mode' => filter_var(getenv('DATA_ACCESS_STRICT_MODE') ?: 'true', FILTER_VALIDATE_BOOL),
            ],
        ];
        
        self::$loaded = true;
    }
    
    /**
     * Get a configuration value using dot notation
     * e.g., get('session.timeout') returns session timeout value
     */
    public static function get(string $key, mixed $default = null): mixed {
        if (!self::$loaded) {
            self::load();
        }
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Get session configuration
     */
    public static function getSessionConfig(): array {
        return self::get('session', []);
    }
    
    /**
     * Get password policy configuration
     */
    public static function getPasswordConfig(): array {
        return self::get('password', []);
    }
    
    /**
     * Get plan configuration
     */
    public static function getPlanConfig(string $plan = null): array {
        if ($plan) {
            return self::get("plans.{$plan}", []);
        }
        return self::get('plans', []);
    }
    
    /**
     * Get rate limit configuration
     */
    public static function getRateLimitConfig(): array {
        return self::get('rate_limit', []);
    }
    
    /**
     * Get Redis configuration
     */
    public static function getRedisConfig(): array {
        return self::get('redis', []);
    }
    
    /**
     * Get audit configuration
     */
    public static function getAuditConfig(): array {
        return self::get('audit', []);
    }
    
    /**
     * Get CSRF configuration
     */
    public static function getCsrfConfig(): array {
        return self::get('csrf', []);
    }
    
    /**
     * Get brute force configuration
     */
    public static function getBruteForceConfig(): array {
        return self::get('brute_force', []);
    }
    
    /**
     * Get data access configuration
     */
    public static function getDataAccessConfig(): array {
        return self::get('data_access', []);
    }
    
    /**
     * Check if a feature is enabled
     */
    public static function isEnabled(string $feature): bool {
        return filter_var(self::get($feature, false), FILTER_VALIDATE_BOOL);
    }
    
    /**
     * Get all configuration
     */
    public static function all(): array {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$config;
    }
    
    /**
     * Reload configuration (useful for testing)
     */
    public static function reload(): void {
        self::$loaded = false;
        self::load();
    }
}
?>
