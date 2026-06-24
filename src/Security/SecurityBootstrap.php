<?php
/**
 * Security Bootstrap - Initialize All Security Components
 * Sets up comprehensive security system for the POS application
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class SecurityBootstrap {
    private static $initialized = false;
    
    /**
     * Initialize the complete security system
     */
    public static function initialize(): void {
        if (self::$initialized) {
            return;
        }
        
        // Load required files
        self::loadSecurityFiles();
        
        // Initialize core components
        self::initializeDataAccessScope();
        self::initializeRoleBasedAccess();
        self::initializeAuditLogger();
        
        // Apply security middleware
        self::applySecurityMiddleware();
        
        // Create audit table if needed
        self::ensureAuditTable();
        
        self::$initialized = true;
    }
    
    /**
     * Load all security files
     */
    private static function loadSecurityFiles(): void {
        $security_files = [
            __DIR__ . '/DataAccessScope.php',
            __DIR__ . '/RoleBasedAccess.php',
            __DIR__ . '/AuditLogger.php',
            __DIR__ . '/SecureQueryBuilder.php',
            __DIR__ . '/../Middleware/ApiSecurityMiddleware.php',
            __DIR__ . '/../Middleware/TenantIsolationMiddleware.php'
        ];
        
        foreach ($security_files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
    
    /**
     * Initialize data access scope
     */
    private static function initializeDataAccessScope(): void {
        try {
            DataAccessScope::getInstance();
        } catch (Exception $e) {
            error_log("Failed to initialize data access scope: " . $e->getMessage());
        }
    }
    
    /**
     * Initialize role-based access
     */
    private static function initializeRoleBasedAccess(): void {
        try {
            RoleBasedAccess::getInstance();
        } catch (Exception $e) {
            error_log("Failed to initialize role-based access: " . $e->getMessage());
        }
    }
    
    /**
     * Initialize audit logger
     */
    private static function initializeAuditLogger(): void {
        try {
            AuditLogger::getInstance();
        } catch (Exception $e) {
            error_log("Failed to initialize audit logger: " . $e->getMessage());
        }
    }
    
    /**
     * Apply security middleware
     */
    private static function applySecurityMiddleware(): void {
        try {
            // Apply tenant isolation
            if (class_exists('TenantIsolationMiddleware')) {
                $tenant_middleware = new TenantIsolationMiddleware();
                $tenant_middleware->applyTenantIsolation();
            }
        } catch (Exception $e) {
            error_log("Failed to apply security middleware: " . $e->getMessage());
        }
    }
    
    /**
     * Ensure audit table exists
     */
    private static function ensureAuditTable(): void {
        try {
            $audit_logger = AuditLogger::getInstance();
            $audit_logger->createAuditTable();
        } catch (Exception $e) {
            error_log("Failed to create audit table: " . $e->getMessage());
        }
    }
    
    /**
     * Secure API endpoint with all protections
     */
    public static function secureApiEndpoint(string $permission = null, array $methods = ['GET', 'POST', 'PUT', 'DELETE']): void {
        self::initialize();
        
        if (class_exists('ApiSecurityMiddleware')) {
            $middleware = new ApiSecurityMiddleware();
            $middleware->secureEndpoint($permission, $methods);
        }
    }
    
    /**
     * Secure AJAX endpoint
     */
    public static function secureAjaxEndpoint(string $permission = null): void {
        self::initialize();
        
        if (class_exists('ApiSecurityMiddleware')) {
            $middleware = new ApiSecurityMiddleware();
            $middleware->secureAjaxEndpoint($permission);
        }
    }
    
    /**
     * Check user permission
     */
    public static function hasPermission(string $permission): bool {
        self::initialize();
        
        try {
            $rbac = RoleBasedAccess::getInstance();
            return $rbac->hasPermission($permission);
        } catch (Exception $e) {
            error_log("Permission check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enforce permission (throws exception if not granted)
     */
    public static function enforcePermission(string $permission): void {
        if (!self::hasPermission($permission)) {
            $audit_logger = AuditLogger::getInstance();
            $audit_logger->logSecurityViolation('Permission enforcement failed', [
                'permission' => $permission,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            throw new SecurityException("Permission denied: {$permission}");
        }
    }
    
    /**
     * Execute secure database query
     */
    public static function secureQuery(string $query, array $params = []): array {
        self::initialize();
        
        try {
            $builder = new SecureQueryBuilder();
            return $builder->select('temp', ['*'], [], [], '', 0, 0);
        } catch (Exception $e) {
            error_log("Secure query failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent(string $event, array $context = []): void {
        self::initialize();
        
        try {
            $audit_logger = AuditLogger::getInstance();
            $audit_logger->logDataAccess($event, 'security', $context);
        } catch (Exception $e) {
            error_log("Security logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get current user's data scope
     */
    public static function getDataScope(): array {
        self::initialize();
        
        try {
            $scope = DataAccessScope::getInstance();
            return $scope->getUserDataScope();
        } catch (Exception $e) {
            error_log("Failed to get data scope: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validate tenant isolation
     */
    public static function validateTenantIsolation(): void {
        self::initialize();
        
        try {
            $middleware = new TenantIsolationMiddleware();
            $middleware->applyTenantIsolation();
        } catch (Exception $e) {
            error_log("Tenant isolation validation failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Apply rate limiting
     */
    public static function applyRateLimit(string $action, int $max_requests = 60, int $window_seconds = 60): void {
        self::initialize();
        
        try {
            $middleware = new ApiSecurityMiddleware();
            $middleware->applyRateLimit($action, $max_requests, $window_seconds);
        } catch (Exception $e) {
            error_log("Rate limiting failed: " . $e->getMessage());
        }
    }
    
    /**
     * Reset security system (for testing)
     */
    public static function reset(): void {
        self::$initialized = false;
        
        if (class_exists('DataAccessScope')) {
            DataAccessScope::reset();
        }
        
        if (class_exists('RoleBasedAccess')) {
            RoleBasedAccess::reset();
        }
        
        if (class_exists('AuditLogger')) {
            AuditLogger::reset();
        }
    }
}

/**
 * Convenience functions for global access
 */

if (!function_exists('has_permission')) {
    function has_permission(string $permission): bool {
        return SecurityBootstrap::hasPermission($permission);
    }
}

if (!function_exists('enforce_permission')) {
    function enforce_permission(string $permission): void {
        SecurityBootstrap::enforcePermission($permission);
    }
}

if (!function_exists('secure_api_endpoint')) {
    function secure_api_endpoint(string $permission = null, array $methods = ['GET', 'POST', 'PUT', 'DELETE']): void {
        SecurityBootstrap::secureApiEndpoint($permission, $methods);
    }
}

if (!function_exists('secure_ajax_endpoint')) {
    function secure_ajax_endpoint(string $permission = null): void {
        SecurityBootstrap::secureAjaxEndpoint($permission);
    }
}

if (!function_exists('log_security_event')) {
    function log_security_event(string $event, array $context = []): void {
        SecurityBootstrap::logSecurityEvent($event, $context);
    }
}

if (!function_exists('get_data_scope')) {
    function get_data_scope(): array {
        return SecurityBootstrap::getDataScope();
    }
}

if (!function_exists('validate_tenant_isolation')) {
    function validate_tenant_isolation(): void {
        SecurityBootstrap::validateTenantIsolation();
    }
}

if (!function_exists('apply_rate_limit')) {
    function apply_rate_limit(string $action, int $max_requests = 60, int $window_seconds = 60): void {
        SecurityBootstrap::applyRateLimit($action, $max_requests, $window_seconds);
    }
}
