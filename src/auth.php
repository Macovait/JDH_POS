<?php
declare(strict_types=1);

/**
 * Authentication and authorization functions - SaaS Version
 * Multi-tenant support with company isolation
 *
 * @package Jakababa
 * @subpackage Auth
 * @version 4.0 - Complete with all fixes
 */

// Prevent multiple inclusions
if (defined('AUTH_LOADED')) {
    return;
}
define('AUTH_LOADED', true);

// Include core files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/paths.php';

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------

define('SESSION_LIFETIME_DEFAULT', 7200);
define('SESSION_IDLE_TIMEOUT_DEFAULT', 1800);
define('SESSION_COOKIE_PATH_DEFAULT', '/');
define('CSRF_TOKEN_LIFETIME', 3600);

// -----------------------------------------------------------------------------
// Session Management
// -----------------------------------------------------------------------------

if (!function_exists('start_session_secure')) {
    /**
     * Start a secure session with proper configuration
     */
    function start_session_secure(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Set secure session name
        session_name('jakababa_saas_sid');

        // Get cookie path from config, auto-detect, or use default
        if (defined('SESSION_COOKIE_PATH')) {
            $cookiePath = SESSION_COOKIE_PATH;
        } else {
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $cookiePath = '/';
            if ($scriptName && preg_match('#^(/[^/]+/)#', $scriptName, $m)) {
                $cookiePath = $m[1];
            }
        }

        $cookieParams = [
            'lifetime' => 0,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax'
        ];

        session_set_cookie_params($cookieParams);
        
        // Only start session if not already active (prevents duplicate session_start)
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Attempt to start session, log but don't throw on failure
            if (!session_start()) {
                error_log('Warning: session_start() returned false, but continuing execution');
                // Don't throw - allow graceful degradation
            }
        }

        // If the session still isn't active (e.g. headers already sent), bail out
        // early. Proceeding would call regenerate_session(), which calls back into
        // start_session_secure() and recurses infinitely until the script times out.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // Set security headers
        set_security_headers();

        // Initialize session security if not already done
        if (!isset($_SESSION['initialized'])) {
            session_regenerate_id(true);
            $_SESSION['initialized'] = true;
            $_SESSION['created_at'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['fingerprint'] = generate_session_fingerprint();
        }

        // Check for session expiration
        if (is_session_expired()) {
            destroy_session();
            session_start();
            $_SESSION['initialized'] = true;
            $_SESSION['created_at'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['fingerprint'] = generate_session_fingerprint();
        }

        // Update last activity
        $_SESSION['last_activity'] = time();

        // Periodic session regeneration (every 30 minutes)
        if (!isset($_SESSION['last_regeneration']) || 
            (time() - $_SESSION['last_regeneration']) > 1800) {
            regenerate_session();
        }
    }
}

if (!function_exists('set_security_headers')) {
    /**
     * Set security headers for all responses
     */
    function set_security_headers(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        if (is_https()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
}

if (!function_exists('is_https')) {
    /**
     * Check if the current request is using HTTPS
     */
    function is_https(): bool
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
    }
}

if (!function_exists('generate_session_fingerprint')) {
    /**
     * Generate a unique session fingerprint
     */
    function generate_session_fingerprint(): string
    {
        return hash('sha256', 
            get_client_ip() . 
            ($_SERVER['HTTP_USER_AGENT'] ?? '') . 
            session_id()
        );
    }
}

if (!function_exists('destroy_session')) {
    /**
     * Completely destroy the current session
     */
    function destroy_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            
            session_destroy();
        }
    }
}

// -----------------------------------------------------------------------------
// Session Getter Helpers
// -----------------------------------------------------------------------------

if (!function_exists('get_current_user_id')) {
    /**
     * Get current user ID from session
     */
    function get_current_user_id(): ?int
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (is_array($userId)) {
            $userId = $userId[0] ?? null;
        }
        
        return $userId ? (int) $userId : null;
    }
}

if (!function_exists('get_current_tenant_id')) {
    /**
     * Get current company ID from session
     */
    function get_current_tenant_id(): ?int
    {
        $tenantId = $_SESSION['tenant_id'] ?? null;
        
        if (is_array($tenantId)) {
            $tenantId = $tenantId[0] ?? null;
        }
        
        if ($tenantId) {
            return (int) $tenantId;
        }
        
        // Fallback: derive tenant_id from the user's branch
        $branchId = get_current_branch_id();
        if ($branchId && function_exists('get_db_connection')) {
            try {
                $tid = db_fetch_value("SELECT tenant_id FROM branches WHERE id = ? LIMIT 1", [$branchId]);
                if ($tid) {
                    $_SESSION['tenant_id'] = (int) $tid;
                    return (int) $tid;
                }
            } catch (Exception $e) {
                error_log('Failed to derive tenant_id from branch: ' . $e->getMessage());
            }
        }
        
        return null;
    }
}

if (!function_exists('get_current_branch_id')) {
    /**
     * Get current branch ID from session
     */
    function get_current_branch_id(): ?int
    {
        $branchId = $_SESSION['branch_id'] ?? null;
        
        if (is_array($branchId)) {
            $branchId = $branchId[0] ?? null;
        }
        
        return $branchId ? (int) $branchId : null;
    }
}

if (!function_exists('get_current_branch_name')) {
    /**
     * Get current branch name — queries DB when available, falls back to session
     */
    function get_current_branch_name(): string
    {
        $branchId = get_current_branch_id();
        $tenantId = get_current_tenant_id();

        if ($branchId && $tenantId && function_exists('get_db_connection')) {
            try {
                $name = db_fetch_value(
                    "SELECT name FROM branches WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1",
                    [$branchId, $tenantId]
                );
                
                if ($name) {
                    $_SESSION['branch_name'] = $name;
                    return $name;
                }
            } catch (Exception $e) {
                error_log('Failed to fetch branch name: ' . $e->getMessage());
            }
        }

        return $_SESSION['branch_name'] ?? '';
    }
}

if (!function_exists('get_current_user_name')) {
    /**
     * Get current user name from session
     */
    function get_current_user_name(): string
    {
        $name = $_SESSION['user_name']
            ?? $_SESSION['name']
            ?? ($_SESSION['user']['name'] ?? null)
            ?? '';
        
        return $name ?: 'User';
    }
}

if (!function_exists('get_current_user_email')) {
    /**
     * Get current user email from session
     */
    function get_current_user_email(): string
    {
        return $_SESSION['user_email'] ?? '';
    }
}

// -----------------------------------------------------------------------------
// Tenant Helpers (SaaS)
// -----------------------------------------------------------------------------

if (!function_exists('get_current_tenant_name')) {
    /**
     * Get current tenant name — queries DB when available, falls back to session
     */
    function get_current_tenant_name(): string
    {
        $tenantId = get_current_tenant_id();
        
        if (!$tenantId) {
            return $_SESSION['tenant_name'] ?? '';
        }

        if (function_exists('get_db_connection')) {
            try {
                // Check for tenants table first using db_table_exists
                if (function_exists('db_table_exists') && db_table_exists('tenants')) {
                    $name = db_fetch_value(
                        "SELECT name FROM tenants WHERE id = ? AND deleted_at IS NULL LIMIT 1",
                        [$tenantId]
                    );
                    
                    if ($name) {
                        $_SESSION['tenant_name'] = $name;
                        return $name;
                    }
                }
                
                // Fallback to companies table
                if (function_exists('db_table_exists') && db_table_exists('companies')) {
                    $name = db_fetch_value(
                        "SELECT name FROM companies WHERE id = ? LIMIT 1",
                        [$tenantId]
                    );
                    
                    if ($name) {
                        $_SESSION['tenant_name'] = $name;
                        return $name;
                    }
                }
            } catch (Exception $e) {
                error_log('Failed to fetch tenant name: ' . $e->getMessage());
            }
        }

        return $_SESSION['tenant_name'] ?? '';
    }
}

if (!function_exists('get_current_tenant_slug')) {
    /**
     * Get current tenant slug/subdomain from session
     */
    function get_current_tenant_slug(): string
    {
        return $_SESSION['tenant_slug'] ?? $_SESSION['tenant_code'] ?? '';
    }
}

// -----------------------------------------------------------------------------
// Login & Logout
// -----------------------------------------------------------------------------

if (!function_exists('logout_user')) {
    /**
     * Log out the current user
     */
    function logout_user(): void
    {
        start_session_secure();

        // Log activity before destroying session
        $userId = get_current_user_id();
        $tenantId = get_current_tenant_id();
        $username = $_SESSION['username'] ?? 'Unknown';

        if ($userId) {
            log_activity(
                'auth.logout', 
                'User logged out', 
                ['username' => $username], 
                $userId,
                $tenantId
            );
        }

        destroy_session();
    }
}

if (!function_exists('init_user_session')) {
    /**
     * Initialize user session after successful login
     *
     * @param array $user User data from database
     * @throws RuntimeException If tenant context is missing
     */
    function init_user_session(array $user): void
    {
        start_session_secure();

        // Get tenant ID
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        
        if ($tenantId <= 0) {
            throw new RuntimeException('Unable to initialize session without tenant context.');
        }

        // Fetch tenant data
        $tenant = fetch_tenant_data($tenantId);

        // Get branch information
        [$branchId, $branchName] = get_branch_info($user, $tenantId);

        // Get role information
        $roleName = $user['role_name'] ?? ($user['role'] ?? 'User');

        // Set core session variables
        set_core_session_vars($user, $tenantId, $branchId, $branchName, $roleName);

        // Set tenant session variables
        set_tenant_session_vars($tenant, $tenantId);

        // Set business context
        set_business_context($user, $tenant);

        // Set permissions and roles
        load_user_permissions_and_roles((int) $user['id']);

        // Set MySQL session variables
        set_mysql_session_vars((int) $user['id'], $tenantId, $branchId);

        // Update last login
        update_last_login((int) $user['id']);
    }
}

if (!function_exists('fetch_tenant_data')) {
    /**
     * Fetch tenant data from database
     */
    function fetch_tenant_data(int $tenantId): array
    {
        try {
            return db_fetch_one(
                "SELECT id, uuid, name, subdomain, status, business_type, 
                        currency, timezone, is_active, is_suspended
                 FROM tenants
                 WHERE id = ? AND deleted_at IS NULL
                 LIMIT 1",
                [$tenantId]
            ) ?: [];
        } catch (Exception $e) {
            error_log('Failed to fetch tenant data: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('get_branch_info')) {
    /**
     * Get branch information for the user
     */
    function get_branch_info(array $user, int $tenantId): array
    {
        $branchId = (int) ($user['branch_id'] ?? 1);
        $branchName = $user['branch_name'] ?? 'Main Branch';
        
        if ($branchId > 0 && empty($user['branch_name'])) {
            try {
                $branch = db_fetch_one(
                    "SELECT name FROM branches
                     WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL
                     LIMIT 1",
                    [$branchId, $tenantId]
                );
                
                if ($branch && !empty($branch['name'])) {
                    $branchName = $branch['name'];
                }
            } catch (Exception $e) {
                error_log('Failed to fetch branch info: ' . $e->getMessage());
            }
        }
        
        return [$branchId, $branchName];
    }
}

if (!function_exists('set_core_session_vars')) {
    /**
     * Set core user session variables
     */
    function set_core_session_vars(array $user, int $tenantId, int $branchId, string $branchName, string $roleName): void
    {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username'] = $user['username'] ?? $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['role'] = $roleName;
        $_SESSION['role_id'] = $user['role_id'] ?? null;
        $_SESSION['branch_id'] = $branchId;
        $_SESSION['branch_name'] = $branchName;
        
        // Check for super admin based on actual role name (not hardcoded IDs)
        if (is_super_admin_role($roleName)) {
            $_SESSION['is_super_admin'] = true;
        }
        
        // User array for compatibility
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'username' => $user['username'] ?? $user['name'],
            'email' => $user['email'] ?? '',
            'role' => $roleName,
            'role_id' => $user['role_id'] ?? null,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'avatar' => $user['avatar'] ?? null,
        ];
    }
}

if (!function_exists('set_tenant_session_vars')) {
    /**
     * Set tenant session variables
     */
    function set_tenant_session_vars(array $tenant, int $tenantId): void
    {
        $tenantName = $tenant['name'] ?? '';
        $tenantSlug = $tenant['subdomain'] ?? '';
        $tenantStatus = $tenant['status'] ?? (($tenant['is_active'] ?? false) ? 'active' : 'inactive');
        
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['tenant_uuid'] = $tenant['uuid'] ?? '';
        $_SESSION['tenant_name'] = $tenantName;
        $_SESSION['tenant_slug'] = $tenantSlug;
        $_SESSION['tenant_code'] = $tenantSlug;
        $_SESSION['tenant_status'] = $tenantStatus;
        $_SESSION['tenant_currency'] = $tenant['currency'] ?? (defined('CURRENCY') ? CURRENCY : 'KES');
        $_SESSION['tenant_timezone'] = $tenant['timezone'] ?? 'Africa/Nairobi';
        $_SESSION['tenant_validated'] = true;
        $_SESSION['subscription_status'] = $tenantStatus;
    }
}

if (!function_exists('set_business_context')) {
    /**
     * Set business context variables
     */
    function set_business_context(array $user, array $tenant): void
    {
        $businessType = $user['business_type'] ?? $tenant['business_type'] ?? 'retail';
        $_SESSION['business_type'] = $businessType;
    }
}

if (!function_exists('load_user_permissions_and_roles')) {
    /**
     * Load user permissions and roles into session
     */
    function load_user_permissions_and_roles(int $userId): void
    {
        try {
            $permissions = db_fetch_all("
                SELECT DISTINCT p.code
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN user_roles ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = ?
            ", [$userId]);
            
            $_SESSION['permissions'] = array_column($permissions, 'code');
            
            $roles = db_fetch_all("
                SELECT r.id, r.name
                FROM roles r
                JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
            ", [$userId]);
            
            $_SESSION['user_roles'] = $roles;
            
            // Check for super admin in roles
            foreach ($roles as $role) {
                $name = strtolower(trim((string) ($role['name'] ?? '')));
                if (in_array($name, ['super admin', 'super_admin', 'superadmin'], true)) {
                    $_SESSION['is_super_admin'] = true;
                    break;
                }
            }
        } catch (Exception $e) {
            error_log('Failed to load permissions and roles: ' . $e->getMessage());
            $_SESSION['permissions'] = [];
            $_SESSION['user_roles'] = [];
        }
    }
}

if (!function_exists('set_mysql_session_vars')) {
    /**
     * Set MySQL session variables for row-level security
     */
    function set_mysql_session_vars(int $userId, int $tenantId, int $branchId): void
    {
        try {
            $pdo = get_db_connection();
            $pdo->exec("SET @current_user_id = " . $userId);
            $pdo->exec("SET @current_tenant_id = " . $tenantId);
            $pdo->exec("SET @current_branch_id = " . $branchId);
        } catch (Exception $e) {
            error_log('Failed to set MySQL session variables: ' . $e->getMessage());
        }
    }
}

if (!function_exists('update_last_login')) {
    /**
     * Update user's last login timestamp
     */
    function update_last_login(int $userId): void
    {
        try {
            db_query(
                "UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
                [get_client_ip(), $userId]
            );
        } catch (Exception $e) {
            error_log('Failed to update last login: ' . $e->getMessage());
        }
    }
}

// -----------------------------------------------------------------------------
// Tenant Module & Permission Helpers (SaaS)
// -----------------------------------------------------------------------------

if (!function_exists('get_tenant_modules')) {
    /**
     * Get enabled modules for a tenant
     */
    function get_tenant_modules(int $tenantId): array
    {
        try {
            $tenant = db_fetch_one(
                "SELECT features, settings FROM tenants WHERE id = ?",
                [$tenantId]
            );
            
            $modules = [];
            
            if ($tenant) {
                $features = json_decode($tenant['features'] ?? '{}', true);
                if (is_array($features) && !empty($features['enabled_modules'])) {
                    $modules = $features['enabled_modules'];
                }
                
                $settings = json_decode($tenant['settings'] ?? '{}', true);
                if (is_array($settings) && !empty($settings['modules'])) {
                    $modules = array_merge($modules, array_keys($settings['modules']));
                }
            }
            
            // Default modules if none configured
            if (empty($modules)) {
                $modules = ['pos', 'inventory', 'sales', 'customers', 'reports'];
            }
            
            return array_unique($modules);
        } catch (Exception $e) {
            error_log('Failed to get tenant modules: ' . $e->getMessage());
            return ['pos', 'inventory', 'sales'];
        }
    }
}

if (!function_exists('get_user_permissions')) {
    /**
     * Get permissions for a user within a tenant
     */
    function get_user_permissions(int $userId, int $tenantId): array
    {
        try {
            $permissions = db_fetch_all("
                SELECT DISTINCT p.code
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN user_roles ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = ?
            ", [$userId]);
            
            return array_values(array_unique(array_map('strval', array_column($permissions, 'code'))));
        } catch (Exception $e) {
            error_log('Failed to get user permissions: ' . $e->getMessage());
            return [];
        }
    }
}

// -----------------------------------------------------------------------------
// Authentication Checks
// -----------------------------------------------------------------------------

if (!function_exists('require_login')) {
    /**
     * Require user to be logged in; redirect if not
     * Includes Zero-Trust tenant context validation
     */
    function require_login(): void
    {
        start_session_secure();

        $userId = get_current_user_id();
        $tenantId = get_current_tenant_id();

        if (!$userId || !$tenantId) {
            // Prevent redirect loop
            $currentUri = $_SERVER['REQUEST_URI'] ?? '';
            $isLoginPage = preg_match('#/auth/login(\.php)?(\?.*)?$#i', $currentUri);
            
            if (!$isLoginPage) {
                destroy_session();
                redirect(base_url('auth/login.php'));
            }
            return;
        }

        $_SESSION['tenant_id'] = $tenantId;

        if (!validate_current_tenant()) {
            destroy_session();
            redirect(base_url('auth/login.php?error=tenant_inactive'));
        }

        // Subscription / trial check
        if (!validate_current_subscription()) {
            destroy_session();
            redirect(base_url('auth/login.php?error=subscription_expired'));
        }

        ensure_current_branch();
    }
}

if (!function_exists('validate_current_subscription')) {
    /**
     * Validate current tenant subscription is active or within trial period.
     * Allows a 24-hour grace period after trial expiry for data export.
     * Returns true if access should be granted.
     */
    function validate_current_subscription(): bool
    {
        $tenantId = get_current_tenant_id();
        if (!$tenantId) {
            return false;
        }

        // Super admins bypass subscription checks
        if (function_exists('is_super_admin') && is_super_admin()) {
            return true;
        }

        try {
            if (!class_exists('SubscriptionManager')) {
                $subMgrPath = __DIR__ . '/SubscriptionManager.php';
                if (file_exists($subMgrPath)) {
                    require_once $subMgrPath;
                }
            }

            if (class_exists('SubscriptionManager')) {
                $pdo = get_db_connection();
                $mgr = new SubscriptionManager($pdo);

                if ($mgr->isTrialExpired($tenantId)) {
                    // Check grace period (24h after expiry)
                    if ($mgr->isInGracePeriod($tenantId)) {
                        $_SESSION['subscription_grace_period'] = true;
                        return true;
                    }

                    // Grace period over — auto-suspend
                    $mgr->suspendExpiredTenant($tenantId);
                    unset($_SESSION['subscription_grace_period']);
                    $_SESSION['tenant_validated'] = false;
                    return false;
                }

                unset($_SESSION['subscription_grace_period']);
            }
        } catch (Exception $e) {
            error_log('Subscription validation error: ' . $e->getMessage());
        }

        return true;
    }
}

if (!function_exists('validate_current_tenant')) {
    /**
     * Validate current company exists and is active
     */
    function validate_current_tenant(): bool
    {
        $tenantId = get_current_tenant_id();
        
        if (!$tenantId) {
            return false;
        }

        // Check cache first
        if (isset($_SESSION['tenant_validated']) && $_SESSION['tenant_validated'] === true) {
            return true;
        }

        try {
            $tenant = db_fetch_one("
                SELECT id, name, status, is_active, is_suspended
                FROM tenants
                WHERE id = ? AND deleted_at IS NULL
            ", [$tenantId]);

            $validStatuses = ['active', 'trial'];
            $isActive = $tenant 
                && !($tenant['is_suspended'] ?? false)
                && ((int) ($tenant['is_active'] ?? 0) === 1 
                    || in_array((string) ($tenant['status'] ?? ''), $validStatuses, true));

            if ($isActive) {
                $_SESSION['tenant_id'] = (int) $tenant['id'];
                $_SESSION['tenant_name'] = $tenant['name'] ?? ($_SESSION['tenant_name'] ?? '');
                $_SESSION['tenant_status'] = $tenant['status'] ?? 'active';
                $_SESSION['tenant_validated'] = true;
                return true;
            }

            if ($tenant) {
                error_log("Tenant {$tenantId} rejected: status={$tenant['status']}, suspended={$tenant['is_suspended']}");
            } else {
                error_log("Tenant {$tenantId} not found or deleted");
            }
        } catch (Exception $e) {
            error_log('Tenant validation failed: ' . $e->getMessage());
        }

        $_SESSION['tenant_validated'] = false;
        return false;
    }
}

if (!function_exists('ensure_tenant_id')) {
    /**
     * Ensure tenant ID is available in session
     */
    function ensure_tenant_id(): void
    {
        start_session_secure();

        if (!empty($_SESSION['tenant_id'])) {
            return;
        }

        $userId = get_current_user_id();
        
        if ($userId > 0) {
            $user = db_fetch_one("
                SELECT u.tenant_id, t.name, t.status
                FROM users u
                JOIN tenants t ON u.tenant_id = t.id
                WHERE u.id = ? AND u.status = 1 AND t.deleted_at IS NULL
                LIMIT 1
            ", [$userId]);

            if ($user && !empty($user['tenant_id'])) {
                $_SESSION['tenant_id'] = (int) $user['tenant_id'];
                $_SESSION['tenant_name'] = $user['name'] ?? ($_SESSION['tenant_name'] ?? '');
                $_SESSION['tenant_status'] = $user['status'] ?? 'active';
                $_SESSION['tenant_validated'] = true;
                $_SESSION['subscription_status'] = $user['status'] ?? 'active';
                return;
            }
        }

        error_log('No tenant context found for user ID: ' . ($userId ?? 'null'));
        logout_user();
        redirect(base_url('auth/login.php?error=no_tenant'));
    }
}

if (!function_exists('ensure_current_branch')) {
    /**
     * Ensure current branch is set in session
     */
    function ensure_current_branch(): void
    {
        start_session_secure();

        if (!empty($_SESSION['branch_id'])) {
            return;
        }

        $userId = get_current_user_id();
        $tenantId = get_current_tenant_id();

        if ($userId > 0 && $tenantId > 0) {
            $branchId = db_fetch_value(
                "SELECT branch_id FROM users WHERE id = ? AND tenant_id = ? AND status = 1",
                [$userId, $tenantId]
            );

            if ($branchId > 0) {
                set_branch_session((int) $branchId, $tenantId);
                return;
            }
        }

        // Default branch
        $_SESSION['branch_id'] = 1;
        $_SESSION['branch_name'] = 'Main Branch';
    }
}

if (!function_exists('set_branch_session')) {
    /**
     * Set branch information in session
     */
    function set_branch_session(int $branchId, int $tenantId): void
    {
        $_SESSION['branch_id'] = $branchId;
        
        $branch = db_fetch_one(
            "SELECT name, code, location, tax_rate
             FROM branches
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL
             LIMIT 1",
            [$branchId, $tenantId]
        );

        if ($branch) {
            $_SESSION['branch_name'] = $branch['name'];
            $_SESSION['branch_code'] = $branch['code'] ?? '';
            $_SESSION['branch_location'] = $branch['location'] ?? '';
            $_SESSION['branch_tax_rate'] = $branch['tax_rate'] ?? 0;
        }
    }
}

// -----------------------------------------------------------------------------
// Permission Helpers
// -----------------------------------------------------------------------------

if (!function_exists('is_super_admin')) {
    /**
     * Check if current user is super admin
     */
    function is_super_admin(): bool
    {
        start_session_secure();

        // Quick flag check
        if (!empty($_SESSION['is_super_admin'])) {
            return true;
        }

        // Admin panel session
        if (!empty($_SESSION['admin_id'])) {
            return true;
        }

        // Check by role name
        $roleName = $_SESSION['role'] ?? '';
        if (is_super_admin_role($roleName)) {
            return true;
        }

        // Check in roles array
        if (isset($_SESSION['user_roles']) && is_array($_SESSION['user_roles'])) {
            foreach ($_SESSION['user_roles'] as $role) {
                $name = is_array($role) 
                    ? ($role['name'] ?? ($role['slug'] ?? '')) 
                    : (string) $role;
                    
                if (is_super_admin_role($name)) {
                    $_SESSION['is_super_admin'] = true;
                    return true;
                }
            }
        }

        // Database check
        $sessionUserId = $_SESSION['user_id'] ?? 0;
        if ($sessionUserId && check_database_super_admin($sessionUserId)) {
            $_SESSION['is_super_admin'] = true;
            return true;
        }

        return false;
    }
}

if (!function_exists('is_super_admin_role')) {
    /**
     * Check if a role name indicates super admin
     */
    function is_super_admin_role(string $roleName): bool
    {
        $roleLower = strtolower(trim($roleName));
        return in_array($roleLower, ['super admin', 'super_admin', 'superadmin'], true);
    }
}

if (!function_exists('check_database_super_admin')) {
    /**
     * Check database for super admin privileges
     */
    function check_database_super_admin(int $userId): bool
    {
        if (!function_exists('get_db_connection')) {
            return false;
        }
        
        try {
            // Check users table is_superadmin flag
            $isSuperAdmin = db_fetch_value("SELECT is_superadmin FROM users WHERE id = ? LIMIT 1", [$userId]);
            
            if ($isSuperAdmin) {
                return true;
            }
            
            // Check if user has admin role via admins table
            $adminExists = db_fetch_value(
                "SELECT COUNT(*) FROM admins WHERE user_id = ? AND active = 1 LIMIT 1",
                [$userId]
            );
            
            return $adminExists > 0;
        } catch (Exception $e) {
            error_log('Database super admin check failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('check_permission')) {
    /**
     * Check if user has a specific permission
     */
    function check_permission(string $permission): bool
    {
        start_session_secure();

        // Super admin has all permissions
        if (is_super_admin()) {
            return true;
        }

        // Get permissions from session
        $sessionPermissions = get_session_permissions();
        
        // Check if permission matches
        if (matches_permission($sessionPermissions, $permission)) {
            return true;
        }

        // Try to load from database
        $userId = get_current_user_id();
        $tenantId = get_current_tenant_id();
        
        if ($userId && $tenantId) {
            try {
                $dbPermissions = get_user_permissions($userId, $tenantId);
                $_SESSION['user_permissions'] = $dbPermissions;
                
                if (matches_permission($dbPermissions, $permission)) {
                    return true;
                }
            } catch (Throwable $e) {
                error_log('Permission check failed: ' . $e->getMessage());
            }
        }

        // Fallback: check role-based access permissions
        if (!class_exists('RoleBasedAccess', false)) {
            $rbacFile = __DIR__ . '/Security/RoleBasedAccess.php';
            if (file_exists($rbacFile)) {
                require_once $rbacFile;
            }
        }

        if (class_exists('RoleBasedAccess', false)) {
            try {
                $rbac = RoleBasedAccess::getInstance();
                if ($rbac->hasPermission($permission)) {
                    return true;
                }
            } catch (Throwable $e) {
                error_log('RoleBasedAccess check failed: ' . $e->getMessage());
            }
        }

        return false;
    }
}

if (!function_exists('get_session_permissions')) {
    /**
     * Get permissions from session
     */
    function get_session_permissions(): array
    {
        $permissions = [];
        
        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            $permissions = array_merge($permissions, $_SESSION['permissions']);
        }
        
        if (isset($_SESSION['user_permissions']) && is_array($_SESSION['user_permissions'])) {
            $permissions = array_merge($permissions, $_SESSION['user_permissions']);
        }
        
        return array_values(array_unique(array_filter(array_map('trim', $permissions))));
    }
}

if (!function_exists('matches_permission')) {
    /**
     * Check if a permission matches any in the list (supports wildcards)
     */
    function matches_permission(array $permissions, string $needle): bool
    {
        if (in_array($needle, $permissions, true)) {
            return true;
        }

        foreach ($permissions as $candidate) {
            if (!is_string($candidate) || strpos($candidate, '*') === false) {
                continue;
            }

            $pattern = '/^' . str_replace('\\*', '.*', preg_quote($candidate, '/')) . '$/i';
            if (preg_match($pattern, $needle) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('enforce_permission')) {
    /**
     * Enforce a permission; if not granted, show 403 error page
     */
    function enforce_permission(string $permission): void
    {
        if (check_permission($permission)) {
            return;
        }

        http_response_code(403);
        
        error_log(sprintf(
            'Permission denied: User %s (Tenant: %s) tried to access %s (requires: %s)',
            get_current_user_id() ?? 'guest',
            get_current_tenant_id() ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown',
            $permission
        ));

        show_403_page($permission);
        exit;
    }
}

if (!function_exists('show_403_page')) {
    /**
     * Display a styled 403 forbidden page
     */
    function show_403_page(string $permission): void
    {
        $primaryColor = defined('APP_BRAND_COLOR_PRIMARY') ? APP_BRAND_COLOR_PRIMARY : '#1E3A8A';
        $primaryDark = defined('APP_BRAND_COLOR_PRIMARY_DARK') ? APP_BRAND_COLOR_PRIMARY_DARK : '#0F2B5E';
        $accentColor = defined('APP_BRAND_COLOR_ACCENT') ? APP_BRAND_COLOR_ACCENT : '#FBBF24';
        $accentDark = defined('APP_BRAND_COLOR_ACCENT_DARK') ? APP_BRAND_COLOR_ACCENT_DARK : '#F59E0B';
        $appName = defined('APP_NAME') ? APP_NAME : 'Jakababa POS';
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 Forbidden | <?= htmlspecialchars($appName) ?></title>
            <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
            <style>
                :root {
                    --primary: <?= htmlspecialchars($primaryColor) ?>;
                    --primary-dark: <?= htmlspecialchars($primaryDark) ?>;
                    --accent: <?= htmlspecialchars($accentColor) ?>;
                    --accent-dark: <?= htmlspecialchars($accentDark) ?>;
                }
                body {
                    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
                    font-family: 'Inter', sans-serif;
                }
            </style>
        </head>
        <body class="min-h-screen flex items-center justify-center p-4">
            <div class="max-w-md w-full bg-white rounded-2xl p-8 text-center shadow-xl">
                <div class="flex justify-center mb-4">
                    <div class="w-20 h-20 bg-red-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-lock text-4xl text-red-500"></i>
                    </div>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Access Denied</h1>
                <p class="text-gray-600 mb-4">You don't have permission to access this page.</p>
                <p class="text-sm text-gray-500 mb-6">
                    Required permission: <code class="bg-gray-100 px-2 py-1 rounded text-amber-600"><?= htmlspecialchars($permission) ?></code>
                </p>
                <div class="space-y-3">
                    <a href="<?= base_url('dashboard/home.php') ?>" 
                       class="block w-full px-4 py-3 bg-amber-500 text-white rounded-xl font-semibold hover:bg-amber-600 transition">
                        <i class="fas fa-home mr-2"></i> Return to Dashboard
                    </a>
                    <a href="<?= base_url('auth/logout.php') ?>" 
                       class="block w-full px-4 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}

if (!function_exists('has_any_permission')) {
    /**
     * Check if user has any of the given permissions
     */
    function has_any_permission(array $permissions): bool
    {
        foreach ($permissions as $perm) {
            if (check_permission($perm)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('has_all_permissions')) {
    /**
     * Check if user has all of the given permissions
     */
    function has_all_permissions(array $permissions): bool
    {
        foreach ($permissions as $perm) {
            if (!check_permission($perm)) {
                return false;
            }
        }
        return true;
    }
}

// -----------------------------------------------------------------------------
// Activity Logging
// -----------------------------------------------------------------------------

if (!function_exists('log_activity')) {
    /**
     * Log user activity (SaaS-aware)
     *
     * @param string $action
     * @param string|null $description
     * @param mixed|null $meta
     * @param int|null $userId
     * @param int|null $tenantId
     * @param int|null $branchId
     * @return bool
     */
    function log_activity(
        string $action, 
        ?string $description = null, 
        $meta = null, 
        ?int $userId = null, 
        ?int $tenantId = null, 
        ?int $branchId = null
    ): bool {
        // Use current context if not provided
        $userId = $userId ?? get_current_user_id();
        $tenantId = $tenantId ?? get_current_tenant_id();
        $branchId = $branchId ?? get_current_branch_id();

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $metaJson = $meta ? (is_array($meta) ? json_encode($meta) : $meta) : null;

        try {
            $data = [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'action' => $action,
                'description' => $description,
                'meta' => $metaJson,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Remove null values
            $data = array_filter($data, fn($v) => $v !== null);
            
            return db_insert('activity_logs', $data) !== false;
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }
}

// -----------------------------------------------------------------------------
// Helper Functions
// -----------------------------------------------------------------------------

if (!function_exists('get_client_ip')) {
    /**
     * Get client IP address with proper header handling
     */
    function get_client_ip(): string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}

if (!function_exists('branch_filter_condition')) {
    /**
     * Get SQL condition for branch filtering
     *
     * @param string $tableAlias
     * @param bool $includeAll If true, super admins can see all branches
     * @return string
     */
    function branch_filter_condition(string $tableAlias = '', bool $includeAll = false): string
    {
        if ($includeAll && is_super_admin()) {
            return '1=1';
        }

        $branchId = get_current_branch_id();
        
        if ($branchId > 0) {
            $prefix = $tableAlias ? $tableAlias . '.' : '';
            return $prefix . 'branch_id = ' . (int) $branchId;
        }

        return '1=0';
    }
}

if (!function_exists('tenant_filter_condition')) {
    /**
     * Get SQL condition for company filtering
     *
     * @param string $tableAlias
     * @return string
     */
    function tenant_filter_condition(string $tableAlias = ''): string
    {
        $tenantId = get_current_tenant_id();

        if ($tenantId > 0) {
            $prefix = $tableAlias ? $tableAlias . '.' : '';
            return $prefix . 'tenant_id = ' . (int) $tenantId;
        }

        return '1=0';
    }
}

// Alias for backward compatibility
if (!function_exists('company_filter_condition')) {
    function company_filter_condition(string $tableAlias = ''): string
    {
        return tenant_filter_condition($tableAlias);
    }
}

// -----------------------------------------------------------------------------
// CSRF Protection
// -----------------------------------------------------------------------------

if (!function_exists('generate_csrf_token')) {
    /**
     * Generate a CSRF token and store it in session
     * 
     * @param string $formName Optional form name for multiple forms
     * @return string CSRF token
     * @throws RuntimeException If random bytes generation fails
     */
    function generate_csrf_token(string $formName = 'default'): string
    {
        start_session_secure();

        try {
            $token = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            throw new RuntimeException('Failed to generate CSRF token: ' . $e->getMessage());
        }

        $_SESSION['csrf_tokens'][$formName] = [
            'token' => $token,
            'expires' => time() + CSRF_TOKEN_LIFETIME
        ];

        clean_expired_csrf_tokens();

        return $token;
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Verify a CSRF token
     * 
     * @param string $token Token to verify
     * @param string $formName Optional form name for multiple forms
     * @return bool True if token is valid, false otherwise
     */
    function verify_csrf_token(string $token, string $formName = 'default'): bool
    {
        start_session_secure();

        if (!isset($_SESSION['csrf_tokens'][$formName])) {
            return false;
        }

        $storedToken = $_SESSION['csrf_tokens'][$formName];

        if ($storedToken['expires'] < time()) {
            unset($_SESSION['csrf_tokens'][$formName]);
            return false;
        }

        return hash_equals($storedToken['token'], $token);
    }
}

if (!function_exists('get_csrf_token_input')) {
    /**
     * Get HTML hidden input field for CSRF token
     * 
     * @param string $formName Optional form name for multiple forms
     * @return string HTML input field
     */
    function get_csrf_token_input(string $formName = 'default'): string
    {
        $token = generate_csrf_token($formName);
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get CSRF token (alias for generate_csrf_token)
     * 
     * @param string $formName Optional form name for multiple forms
     * @return string CSRF token
     */
    function csrf_token(string $formName = 'default'): string
    {
        return generate_csrf_token($formName);
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Get HTML hidden input field for CSRF token (alias for get_csrf_token_input)
     * 
     * @param string $formName Optional form name for multiple forms
     * @return string HTML input field
     */
    function csrf_field(string $formName = 'default'): string
    {
        return get_csrf_token_input($formName);
    }
}

if (!function_exists('clean_expired_csrf_tokens')) {
    /**
     * Clean expired CSRF tokens from session
     */
    function clean_expired_csrf_tokens(): void
    {
        start_session_secure();

        if (!isset($_SESSION['csrf_tokens'])) {
            return;
        }

        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $formName => $tokenData) {
            if ($tokenData['expires'] < $now) {
                unset($_SESSION['csrf_tokens'][$formName]);
            }
        }
    }
}

// -----------------------------------------------------------------------------
// Session Security
// -----------------------------------------------------------------------------

if (!function_exists('regenerate_session')) {
    /**
     * Regenerate session ID to prevent session fixation
     * 
     * @param bool $deleteOldSession Whether to delete old session data
     * @return void
     */
    function regenerate_session(bool $deleteOldSession = true): void
    {
        start_session_secure();
        session_regenerate_id($deleteOldSession);
        $_SESSION['fingerprint'] = generate_session_fingerprint();
        $_SESSION['last_regeneration'] = time();
    }
}

if (!function_exists('validate_session_fingerprint')) {
    /**
     * Validate session fingerprint for hijacking prevention
     * 
     * @return bool True if fingerprint is valid, false otherwise
     */
    function validate_session_fingerprint(): bool
    {
        start_session_secure();

        if (!isset($_SESSION['fingerprint'])) {
            regenerate_session();
            return true;
        }

        return $_SESSION['fingerprint'] === generate_session_fingerprint();
    }
}

if (!function_exists('init_session_security')) {
    /**
     * Initialize session security settings
     */
    function init_session_security(): void
    {
        start_session_secure();

        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = generate_session_fingerprint();
        }

        if (!isset($_SESSION['session_start'])) {
            $_SESSION['session_start'] = time();
        }

        $_SESSION['last_activity'] = time();
    }
}

if (!function_exists('is_session_expired')) {
    /**
     * Check if session has expired due to inactivity
     * 
     * @param int $maxLifetime Maximum session lifetime in seconds
     * @return bool True if session has expired, false otherwise
     */
    function is_session_expired(int $maxLifetime = SESSION_IDLE_TIMEOUT_DEFAULT): bool
    {
        start_session_secure();

        if (!isset($_SESSION['last_activity'])) {
            if (isset($_SESSION['initialized'])) {
                $_SESSION['last_activity'] = time();
                return false;
            }
            return true;
        }

        return (time() - $_SESSION['last_activity']) > $maxLifetime;
    }
}

// -----------------------------------------------------------------------------
// Redirect Helper
// -----------------------------------------------------------------------------

if (!function_exists('redirect')) {
    /**
     * Redirect to a URL
     * 
     * @param string $url
     * @param int $statusCode HTTP status code (302, 301, etc.)
     */
    function redirect(string $url, int $statusCode = 302): void
    {
        if (headers_sent()) {
            echo '<script>window.location.href="' . htmlspecialchars($url) . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
            echo '<p>Redirecting to <a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a></p>';
            exit;
        }
        
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }
}

// -----------------------------------------------------------------------------
// Initialize session security on load
// -----------------------------------------------------------------------------

init_session_security();