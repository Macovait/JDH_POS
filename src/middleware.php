<?php
/**
 * Jakababa POS - Middleware and Route Protection
 * 
 * This file provides middleware functions for route protection, feature access control,
 * and plan-based redirection in the SaaS POS system.
 * 
 * @package JakababaPOS
 * @author Senior SaaS Architect
 * @version 1.0.0
 */

require_once __DIR__ . '/FeatureAccess.php';
require_once __DIR__ . '/auth.php';

/**
 * Middleware to check if user is authenticated
 * 
 * @return bool True if authenticated, false otherwise
 */
function requireAuth()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
        header('Location: ' . login_url(null, $_SERVER['REQUEST_URI']));
        exit;
    }

    return true;
}

/**
 * Middleware to check if user is a system administrator (super admin)
 * 
 * This function ensures only authenticated admins can access admin routes.
 * It checks for admin_id in session and validates admin status.
 * 
 * @return bool True if admin is authenticated, redirects to admin login otherwise
 */
function requireAdmin()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if admin is logged in
    if (!isset($_SESSION['admin_id'])) {
        // Store intended URL for redirect after login
        $_SESSION['admin_intended_url'] = $_SERVER['REQUEST_URI'];
        header('Location: /admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    // Validate admin exists and is active
    try {
        $admin = db_fetch_one("
            SELECT id, username, role, status
            FROM admins
            WHERE id = ? AND deleted_at IS NULL
        ", [$_SESSION['admin_id']]);

        if (!$admin || $admin['status'] !== 'active') {
            // Admin not found or inactive - destroy session
            session_destroy();
            header('Location: /admin/login.php?error=invalid_session');
            exit;
        }

        // Verify session fingerprint for hijacking prevention
        if (isset($_SESSION['fingerprint'])) {
            $current_fingerprint = md5(
                get_client_ip() .
                ($_SERVER['HTTP_USER_AGENT'] ?? '') .
                session_id()
            );
            if ($_SESSION['fingerprint'] !== $current_fingerprint) {
                // Potential session hijacking
                error_log("Session hijacking detected for admin ID: {$admin['id']}");
                session_destroy();
                header('Location: /admin/login.php?error=session_invalid');
                exit;
            }
        }

        // Update session with current admin data
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['is_super_admin'] = ($admin['role'] === 'super_admin');

        return true;

    } catch (Exception $e) {
        error_log("requireAdmin error: " . $e->getMessage());
        session_destroy();
        header('Location: /admin/login.php?error=system_error');
        exit;
    }
}

/**
 * Middleware to check if user is a tenant (company user)
 * 
 * This function ensures only authenticated tenant users can access tenant routes.
 * It checks for user_id and tenant_id in session and validates user status.
 * 
 * @return bool True if tenant is authenticated, redirects to tenant login otherwise
 */
function requireTenant()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
        // Store intended URL for redirect after login
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
        header('Location: /public/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    // Validate user exists and is active
    try {
        $user = db_fetch_one("
            SELECT u.id, u.username, u.status, u.tenant_id,
                   c.status as company_status
            FROM users u
            JOIN companies c ON u.tenant_id = c.id
            WHERE u.id = ? AND u.tenant_id = ? AND u.deleted_at IS NULL
        ", [$_SESSION['user_id'], $_SESSION['tenant_id']]);

        if (!$user) {
            // User not found - destroy session
            session_destroy();
            header('Location: /public/auth/login.php?error=user_not_found');
            exit;
        }

        if ($user['status'] !== 'active') {
            // User inactive - destroy session
            session_destroy();
            header('Location: /public/auth/login.php?error=user_inactive');
            exit;
        }

        if (!in_array($user['company_status'], ['active', 'trial'], true)) {
            // Company inactive - destroy session
            session_destroy();
            header('Location: /public/auth/login.php?error=company_inactive');
            exit;
        }

        // Verify session fingerprint for hijacking prevention
        if (isset($_SESSION['fingerprint'])) {
            $current_fingerprint = md5(
                get_client_ip() .
                ($_SERVER['HTTP_USER_AGENT'] ?? '') .
                session_id()
            );
            if ($_SESSION['fingerprint'] !== $current_fingerprint) {
                // Potential session hijacking
                error_log("Session hijacking detected for user ID: {$user['id']}");
                session_destroy();
                header('Location: /public/auth/login.php?error=session_invalid');
                exit;
            }
        }

        // Ensure tenant_id is set
        $_SESSION['tenant_id'] = (int) $user['tenant_id'];

        return true;

    } catch (Exception $e) {
        error_log("requireTenant error: " . $e->getMessage());
        session_destroy();
        header('Location: /public/auth/login.php?error=system_error');
        exit;
    }
}

/**
 * Check if current user is an admin
 * 
 * @return bool True if admin is logged in
 */
function isAdmin(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_id']);
}

/**
 * Check if current user is a tenant
 * 
 * @return bool True if tenant is logged in
 */
function isTenant(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && isset($_SESSION['tenant_id']);
}

/**
 * Get current admin ID
 * 
 * @return int|null Admin ID or null if not logged in
 */
function get_current_admin_id(): ?int
{
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Get current admin role
 * 
 * @return string|null Admin role or null if not logged in
 */
function get_current_admin_role(): ?string
{
    return $_SESSION['admin_role'] ?? null;
}

// -----------------------------------------------------------------------------
// AJAX Security Middleware
// -----------------------------------------------------------------------------

/**
 * Require AJAX request to be authenticated
 * 
 * This function ensures AJAX requests are from authenticated users.
 * It returns JSON error response if not authenticated.
 * 
 * @param bool $require_admin Whether to require admin authentication (default: false)
 * @return bool True if authenticated, sends JSON error and exits otherwise
 */
function require_ajax_auth($require_admin = false)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if request is AJAX
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (!$is_ajax) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Invalid request type',
            'code' => 'INVALID_REQUEST'
        ]);
        exit;
    }

    // Check authentication
    if ($require_admin) {
        if (!isset($_SESSION['admin_id'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Admin authentication required',
                'code' => 'ADMIN_AUTH_REQUIRED'
            ]);
            exit;
        }
    } else {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required',
                'code' => 'AUTH_REQUIRED'
            ]);
            exit;
        }
    }

    return true;
}

/**
 * Require AJAX request to have valid CSRF token
 * 
 * This function validates CSRF token for AJAX POST requests.
 * 
 * @param string $token CSRF token from request
 * @param string $form_name Optional form name for multiple forms
 * @return bool True if token is valid, sends JSON error and exits otherwise
 */
function require_ajax_csrf($token, $form_name = 'default')
{
    // Only check CSRF for POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }

    if (!verify_csrf_token($token, $form_name)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Invalid security token',
            'code' => 'CSRF_INVALID'
        ]);
        exit;
    }

    return true;
}

/**
 * Validate tenant_id for AJAX request
 * 
 * This function ensures AJAX requests only access data from the user's company.
 * 
 * @param int $tenant_id Tenant ID from request
 * @return bool True if tenant_id matches session, sends JSON error and exits otherwise
 */
function validate_ajax_company($tenant_id)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $session_tenant_id = $_SESSION['tenant_id'] ?? null;

    if (!$session_company_id) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Company context not set',
            'code' => 'COMPANY_NOT_SET'
        ]);
        exit;
    }

    if ((int) $tenant_id !== (int) $session_company_id) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Access denied to company data',
            'code' => 'COMPANY_ACCESS_DENIED'
        ]);
        exit;
    }

    return true;
}

/**
 * Send JSON success response
 * 
 * @param mixed $data Response data
 * @param string $message Success message
 * @return void
 */
function json_success($data = null, $message = 'Success')
{
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Send JSON error response
 * 
 * @param string $error Error message
 * @param int $code HTTP status code
 * @param string $error_code Error code for client
 * @return void
 */
function json_error($error, $code = 400, $error_code = 'ERROR')
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $error,
        'code' => $error_code
    ]);
    exit;
}

/**
 * Get JSON input from request body
 * 
 * @return array Decoded JSON data
 */
function get_json_input(): array
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

/**
 * Validate AJAX request method
 * 
 * @param string $expected_method Expected HTTP method (GET, POST, etc.)
 * @return bool True if method matches, sends JSON error and exits otherwise
 */
function validate_ajax_method($expected_method)
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($expected_method)) {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed',
            'code' => 'METHOD_NOT_ALLOWED'
        ]);
        exit;
    }
    return true;
}

/**
 * Rate limiting for AJAX requests
 * 
 * @param string $action Action name for rate limiting
 * @param int $max_requests Maximum requests per window
 * @param int $window_seconds Time window in seconds
 * @return bool True if within rate limit, sends JSON error and exits otherwise
 */
function ajax_rate_limit($action, $max_requests = 60, $window_seconds = 60)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $ip = get_client_ip();
    $key = "rate_limit_{$action}_{$ip}";
    $now = time();

    // Initialize rate limit data
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'window_start' => $now
        ];
    }

    $rate_data = $_SESSION[$key];

    // Reset window if expired
    if (($now - $rate_data['window_start']) > $window_seconds) {
        $_SESSION[$key] = [
            'count' => 1,
            'window_start' => $now
        ];
        return true;
    }

    // Check if limit exceeded
    if ($rate_data['count'] >= $max_requests) {
        http_response_code(429);
        header('Content-Type: application/json');
        header("Retry-After: {$window_seconds}");
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'code' => 'RATE_LIMIT_EXCEEDED'
        ]);
        exit;
    }

    // Increment counter
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * Middleware to check if user has access to a specific feature
 * 
 * @param string $featureKey Feature key to check
 * @param bool $redirect Whether to redirect on failure (default: true)
 * @return bool True if has access, false otherwise
 */
function requireFeature($featureKey, $redirect = true)
{
    requireAuth();

    global $db;

    if (!isset($db)) {
        error_log("Middleware: Database connection not available");
        if ($redirect) {
            header('Location: /public/upgrade.php?error=database');
            exit;
        }
        return false;
    }

    $featureAccess = new FeatureAccess($db);

    if (!$featureAccess->hasFeature($featureKey)) {
        if ($redirect) {
            // Store intended URL for redirect after upgrade
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /public/upgrade.php?feature=' . urlencode($featureKey));
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Middleware to check if user has access to any of the specified features
 * 
 * @param array $featureKeys Array of feature keys to check
 * @param bool $redirect Whether to redirect on failure (default: true)
 * @return bool True if has access to any feature, false otherwise
 */
function requireAnyFeature($featureKeys, $redirect = true)
{
    requireAuth();

    global $db;

    if (!isset($db)) {
        error_log("Middleware: Database connection not available");
        if ($redirect) {
            header('Location: /public/upgrade.php?error=database');
            exit;
        }
        return false;
    }

    $featureAccess = new FeatureAccess($db);

    if (!$featureAccess->hasAnyFeature($featureKeys)) {
        if ($redirect) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /public/upgrade.php?features=' . urlencode(implode(',', $featureKeys)));
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Middleware to check if user has access to all of the specified features
 * 
 * @param array $featureKeys Array of feature keys to check
 * @param bool $redirect Whether to redirect on failure (default: true)
 * @return bool True if has access to all features, false otherwise
 */
function requireAllFeatures($featureKeys, $redirect = true)
{
    requireAuth();

    global $db;

    if (!isset($db)) {
        error_log("Middleware: Database connection not available");
        if ($redirect) {
            header('Location: /public/upgrade.php?error=database');
            exit;
        }
        return false;
    }

    $featureAccess = new FeatureAccess($db);

    if (!$featureAccess->hasAllFeatures($featureKeys)) {
        if ($redirect) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /public/upgrade.php?features=' . urlencode(implode(',', $featureKeys)));
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Middleware to check if subscription is active
 * 
 * @param bool $redirect Whether to redirect on failure (default: true)
 * @return bool True if subscription is active, false otherwise
 */
function requireActiveSubscription($redirect = true)
{
    requireAuth();

    global $db;

    if (!isset($db)) {
        error_log("Middleware: Database connection not available");
        if ($redirect) {
            header('Location: /public/upgrade.php?error=database');
            exit;
        }
        return false;
    }

    $featureAccess = new FeatureAccess($db);

    if (!$featureAccess->isSubscriptionActive()) {
        if ($redirect) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /public/upgrade.php?error=subscription');
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Middleware to check if trial has expired
 * 
 * @param bool $redirect Whether to redirect on failure (default: true)
 * @return bool True if trial has not expired, false otherwise
 */
function requireTrialNotExpired($redirect = true)
{
    requireAuth();

    global $db;

    if (!isset($db)) {
        error_log("Middleware: Database connection not available");
        if ($redirect) {
            header('Location: /public/upgrade.php?error=database');
            exit;
        }
        return false;
    }

    $featureAccess = new FeatureAccess($db);

    if ($featureAccess->isTrialExpired()) {
        if ($redirect) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /public/upgrade.php?error=trial_expired');
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Middleware to check user limit
 * 
 * @param bool $redirect Whether to redirect on failure (default: true)
 * @return bool True if user limit not reached, false otherwise
 */
function requireUserLimitNotReached($redirect = true)
{
    requireAuth();

    global $db;

    if (!isset($db)) {
        error_log("Middleware: Database connection not available");
        if ($redirect) {
            header('Location: /public/upgrade.php?error=database');
            exit;
        }
        return false;
    }

    $featureAccess = new FeatureAccess($db);

    if ($featureAccess->hasReachedUserLimit()) {
        if ($redirect) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /public/upgrade.php?error=user_limit');
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Middleware to check branch limit
 * 
 * @param bool $redirect Whether to redirect on failure (default: true)
 * @return bool True if branch limit not reached, false otherwise
 */
function requireBranchLimitNotReached($redirect = true)
{
    requireAuth();

    global $db;

    if (!isset($db)) {
        error_log("Middleware: Database connection not available");
        if ($redirect) {
            header('Location: /public/upgrade.php?error=database');
            exit;
        }
        return false;
    }

    $featureAccess = new FeatureAccess($db);

    if ($featureAccess->hasReachedBranchLimit()) {
        if ($redirect) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /public/upgrade.php?error=branch_limit');
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Middleware to check product limit
 * 
 * @param bool $redirect Whether to redirect on failure (default: true)
 * @return bool True if product limit not reached, false otherwise
 */
function requireProductLimitNotReached($redirect = true)
{
    requireAuth();

    global $db;

    if (!isset($db)) {
        error_log("Middleware: Database connection not available");
        if ($redirect) {
            header('Location: /public/upgrade.php?error=database');
            exit;
        }
        return false;
    }

    $featureAccess = new FeatureAccess($db);

    if ($featureAccess->hasReachedProductLimit()) {
        if ($redirect) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /public/upgrade.php?error=product_limit');
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Get route to feature mapping
 * 
 * @return array Array mapping routes to required features
 */
function getRouteFeatureMap()
{
    return [
        // POS Module
        '/public/pos/pos.php' => 'pos_access',
        '/public/pos/sales.php' => 'sales_history',
        '/public/pos/returns/list_sell_return.php' => 'sales_returns',
        '/public/pos/returns/return_sale.php' => 'sales_returns',
        '/public/pos/view_sale.php' => 'sales_history',
        '/public/pos/all_sales.php' => 'sales_history',
        '/public/pos/add_sales.php' => 'pos_access',
        '/public/pos/process_sales.php' => 'pos_access',
        '/public/pos/hold_sale.php' => 'pos_access',
        '/public/pos/list_pos.php' => 'pos_access',

        // Products Module
        '/public/products/products.php' => 'product_management',
        '/public/products/product_form.php' => 'product_management',
        '/public/products/import_products.php' => 'product_import',
        '/public/products/export_products.php' => 'product_import',

        // Categories Module
        '/public/categories/list_categories.php' => 'category_management',
        '/public/categories/add_category.php' => 'category_management',
        '/public/categories/edit_category.php' => 'category_management',
        '/public/categories/category_hierarchy.php' => 'category_hierarchy',

        // Inventory Module
        '/public/inventory/inventory.php' => 'inventory_tracking',
        '/public/inventory/transfers.php' => 'inventory_transfers',
        '/public/inventory/alerts.php' => 'inventory_alerts',
        '/public/inventory/suppliers.php' => 'supplier_management',
        '/public/inventory/supplier_form.php' => 'supplier_management',

        // Customers Module
        '/public/customers/customers.php' => 'customer_management',
        '/public/customers/add_customer.php' => 'customer_management',
        '/public/customers/loyalty.php' => 'customer_loyalty',

        // Purchases Module
        '/public/purchases/purchase_orders.php' => 'purchase_orders',
        '/public/purchases/add_draft.php' => 'purchase_orders',
        '/public/purchases/purchase_receive.php' => 'purchase_receiving',
        '/public/purchases/view_draft.php' => 'purchase_orders',
        '/public/purchases/list_draft.php' => 'purchase_orders',

        // Reports Module
        '/public/reports/reports.php' => 'reports_basic',
        '/public/reports/sales_report.php' => 'reports_basic',
        '/public/reports/inventory_report.php' => 'reports_basic',
        '/public/reports/advanced.php' => 'reports_advanced',

        // Expenses Module
        '/public/expenses/expenses.php' => 'expense_tracking',
        '/public/expenses/add_expense.php' => 'expense_tracking',
        '/public/expenses/expense_categories.php' => 'expense_categories',

        // Branches Module
        '/public/branches/branches.php' => 'multi_branch',
        '/public/branches/add_branch.php' => 'multi_branch',
        '/public/branches/edit_branch.php' => 'multi_branch',

        // Users Module
        '/public/users/users.php' => 'user_management',
        '/public/users/add_user.php' => 'user_management',
        '/public/users/roles.php' => 'role_management',

        // Quotations Module
        '/public/quotations/list_quotation.php' => 'sales_quotes',
        '/public/quotations/add_quotation.php' => 'sales_quotes',

        // Shipments Module
        '/public/shipments/shipments.php' => 'pos_access',
        '/public/shipments/create_shipment.php' => 'pos_access',

        // Vouchers Module
        '/public/vouchers/vouchers.php' => 'reports_advanced',
    ];
}

/**
 * Check if current route requires feature protection
 * 
 * @param string $requestUri Current request URI
 * @return string|null Feature key if protected, null otherwise
 */
function getRequiredFeatureForRoute($requestUri)
{
    $routeMap = getRouteFeatureMap();

    // Normalize the request URI
    $normalizedUri = parse_url($requestUri, PHP_URL_PATH);

    // Check for exact match
    if (isset($routeMap[$normalizedUri])) {
        return $routeMap[$normalizedUri];
    }

    // Check for partial match (for dynamic routes)
    foreach ($routeMap as $route => $feature) {
        if (strpos($normalizedUri, $route) === 0) {
            return $feature;
        }
    }

    return null;
}

/**
 * Auto-protect route based on feature mapping
 * 
 * @return void
 */
function autoProtectRoute()
{
    $currentUri = $_SERVER['REQUEST_URI'];
    $requiredFeature = getRequiredFeatureForRoute($currentUri);

    if ($requiredFeature !== null) {
        requireFeature($requiredFeature);
    }
}

/**
 * Get dashboard redirect based on plan
 * 
 * @return string Dashboard URL based on plan
 */
function getDashboardRedirect()
{
    global $db;

    if (!isset($db)) {
        return '/public/dashboard/home.php';
    }

    $featureAccess = new FeatureAccess($db);
    $plan = $featureAccess->getCompanyPlan();

    if (!$plan) {
        return '/public/upgrade.php';
    }

    // Redirect based on plan
    switch ($plan['slug']) {
        case 'free':
            return '/public/dashboard/home.php?plan=free';
        case 'starter':
            return '/public/dashboard/home.php?plan=starter';
        case 'professional':
            return '/public/dashboard/home.php?plan=professional';
        case 'enterprise':
            return '/public/dashboard/home.php?plan=enterprise';
        default:
            return '/public/dashboard/home.php';
    }
}

/**
 * Handle post-login redirection based on plan
 * 
 * @return void
 */
function handlePostLoginRedirect()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if there's an intended URL
    if (isset($_SESSION['intended_url'])) {
        $intendedUrl = $_SESSION['intended_url'];
        unset($_SESSION['intended_url']);
        header('Location: ' . $intendedUrl);
        exit;
    }

    // Redirect to appropriate dashboard
    $dashboardUrl = getDashboardRedirect();
    header('Location: ' . $dashboardUrl);
    exit;
}

/**
 * Check if feature is locked and return lock status
 * 
 * @param string $featureKey Feature key to check
 * @return array Array with lock status and message
 */
function getFeatureLockStatus($featureKey)
{
    global $db;

    if (!isset($db)) {
        return [
            'locked' => true,
            'message' => 'Database connection error',
            'upgrade_url' => '/public/upgrade.php'
        ];
    }

    $featureAccess = new FeatureAccess($db);
    $hasAccess = $featureAccess->hasFeature($featureKey);

    if ($hasAccess) {
        return [
            'locked' => false,
            'message' => '',
            'upgrade_url' => ''
        ];
    }

    // Get plan info for better messaging
    $plan = $featureAccess->getCompanyPlan();
    $planName = $plan ? $plan['name'] : 'Free';

    return [
        'locked' => true,
        'message' => "This feature requires a higher plan. You're currently on the {$planName} plan.",
        'upgrade_url' => '/public/upgrade.php?feature=' . urlencode($featureKey),
        'current_plan' => $planName
    ];
}

/**
 * Render feature lock UI
 * 
 * @param string $featureKey Feature key
 * @param string $featureName Human-readable feature name
 * @return string HTML for feature lock UI
 */
function renderFeatureLock($featureKey, $featureName)
{
    $lockStatus = getFeatureLockStatus($featureKey);

    if (!$lockStatus['locked']) {
        return '';
    }

    $html = '<div class="feature-locked-overlay">';
    $html .= '<div class="feature-locked-content">';
    $html .= '<div class="feature-locked-icon">🔒</div>';
    $html .= '<h3>' . htmlspecialchars($featureName) . '</h3>';
    $html .= '<p>' . htmlspecialchars($lockStatus['message']) . '</p>';
    $html .= '<a href="' . htmlspecialchars($lockStatus['upgrade_url']) . '" class="btn btn-primary">';
    $html .= 'Upgrade Plan';
    $html .= '</a>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Check if current page is protected
 * 
 * @return bool True if page is protected, false otherwise
 */
function isPageProtected()
{
    $currentUri = $_SERVER['REQUEST_URI'];
    return getRequiredFeatureForRoute($currentUri) !== null;
}

/**
 * Get protection message for current page
 * 
 * @return string Protection message
 */
function getProtectionMessage()
{
    $currentUri = $_SERVER['REQUEST_URI'];
    $requiredFeature = getRequiredFeatureForRoute($currentUri);

    if ($requiredFeature === null) {
        return '';
    }

    $lockStatus = getFeatureLockStatus($requiredFeature);
    return $lockStatus['message'];
}
