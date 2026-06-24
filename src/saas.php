<?php
declare(strict_types=1);
/**
 * SaaS (Software as a Service) functions
 * Multi-tenant subscription and feature management
 *
 * @package Jakababa
 * @subpackage SaaS
 * @version 2.0
 */

// Prevent multiple inclusions
if (defined('SAAS_LOADED')) {
    return;
}
define('SAAS_LOADED', true);

// Include required files (with guards to prevent redeclare)
if (!defined('DB_LOADED')) { require_once __DIR__ . '/db.php'; }
if (!function_exists('get_db_connection')) { require_once __DIR__ . '/db.php'; }
if (!function_exists('require_login')) { require_once __DIR__ . '/auth.php'; }
if (!function_exists('log_activity')) { require_once __DIR__ . '/logger.php'; }
if (!defined('PATHS_LOADED')) { require_once __DIR__ . '/paths.php'; }
if (!function_exists('format_currency')) { require_once __DIR__ . '/functions.php'; }

// -----------------------------------------------------------------------------
// Subscription & Feature Helpers
// -----------------------------------------------------------------------------

/**
 * Get subscription plan features for a company (cached)
 *
 * @param int|null $tenant_id
 * @return array
 */
function get_subscription_features($tenant_id = null): array
{
    $tenant_id = $tenant_id ?: get_current_tenant_id();

    if (!$tenant_id) {
        return [
            'plan_name' => 'Free',
            'status' => 'none',
            'trial_ends_at' => null,
            'current_period_end' => null,
            'features' => []
        ];
    }

    $sub = get_tenant_subscription($tenant_id);

    if (!$sub) {
        return [
            'plan_name' => 'Free',
            'status' => 'none',
            'trial_ends_at' => null,
            'current_period_end' => null,
            'features' => []
        ];
    }

    return [
        'plan_name' => $sub['plan_name'] ?? 'Free',
        'status' => $sub['status'] ?? 'none',
        'trial_ends_at' => $sub['trial_ends_at'] ?? null,
        'current_period_end' => $sub['current_period_end'] ?? null,
        'features' => json_decode($sub['features'] ?? '[]', true)
    ];
}

/**
 * Check if company has access to a specific feature
 *
 * @param string   $feature_name
 * @param int|null $tenant_id
 * @return bool
 */
function has_feature_access($feature_name, $tenant_id = null): bool
{
    $tenant_id = $tenant_id ?: get_current_tenant_id();

    if (!$tenant_id) {
        return false;
    }

    return has_feature($tenant_id, $feature_name);
}

/**
 * Check if company has access to a specific feature (alias for has_feature_access)
 * 
 * This function provides a cleaner API for feature checking.
 * It uses the FeatureAccess class for comprehensive feature control.
 * 
 * @param int|null $tenant_id Tenant ID (uses current if null)
 * @param string   $feature_key Feature key to check
 * @return bool True if feature is enabled, false otherwise
 */
if (!function_exists('hasFeature')) {
function hasFeature($tenant_id, $feature_key): bool
{
    if (!$tenant_id) {
        return false;
    }

    try {
        $pdo = get_db_connection();
        $featureAccess = new FeatureAccess($pdo, $tenant_id);
        return $featureAccess->hasFeature($feature_key);
    } catch (Exception $e) {
        error_log("hasFeature error: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Check if company has access to any of the specified features
 * 
 * @param int|null $tenant_id Tenant ID (uses current if null)
 * @param array    $feature_keys Array of feature keys to check
 * @return bool True if any feature is enabled, false otherwise
 */
if (!function_exists('hasAnyFeature')) {
function hasAnyFeature($tenant_id, $feature_keys): bool
{
    if (!$tenant_id) {
        return false;
    }

    try {
        $pdo = get_db_connection();
        $featureAccess = new FeatureAccess($pdo, $tenant_id);
        return $featureAccess->hasAnyFeature($feature_keys);
    } catch (Exception $e) {
        error_log("hasAnyFeature error: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Check if company has access to all of the specified features
 * 
 * @param int|null $tenant_id Tenant ID (uses current if null)
 * @param array    $feature_keys Array of feature keys to check
 * @return bool True if all features are enabled, false otherwise
 */
if (!function_exists('hasAllFeatures')) {
function hasAllFeatures($tenant_id, $feature_keys): bool
{
    if (!$tenant_id) {
        return false;
    }

    try {
        $pdo = get_db_connection();
        $featureAccess = new FeatureAccess($pdo, $tenant_id);
        return $featureAccess->hasAllFeatures($feature_keys);
    } catch (Exception $e) {
        error_log("hasAllFeatures error: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Get all features for a tenant's current plan
 * 
 * @param int|null $tenant_id Tenant ID (uses current if null)
 * @return array Array of feature keys that are enabled
 */
if (!function_exists('getCompanyFeatures')) {
function getCompanyFeatures($tenant_id = null): array
{
    $tenant_id = $tenant_id ?: get_current_tenant_id();

    if (!$tenant_id) {
        return [];
    }

    try {
        $pdo = get_db_connection();
        $featureAccess = new FeatureAccess($pdo, $tenant_id);
        return $featureAccess->getCompanyFeatures();
    } catch (Exception $e) {
        error_log("getCompanyFeatures error: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Get tenant's current plan information
 * 
 * @param int|null $tenant_id Tenant ID (uses current if null)
 * @return array|null Plan information or null if not found
 */
if (!function_exists('getCompanyPlan')) {
function getCompanyPlan($tenant_id = null): ?array
{
    $tenant_id = $tenant_id ?: get_current_tenant_id();

    if (!$tenant_id) {
        return null;
    }

    try {
        $pdo = get_db_connection();
        $featureAccess = new FeatureAccess($pdo, $tenant_id);
        return $featureAccess->getCompanyPlan();
    } catch (Exception $e) {
        error_log("getCompanyPlan error: " . $e->getMessage());
        return null;
    }
}
}

/**
 * Check if tenant's subscription is active
 * 
 * @param int|null $tenant_id Tenant ID (uses current if null)
 * @return bool True if subscription is active, false otherwise
 */
function isSubscriptionActive($tenant_id = null): bool
{
    $tenant_id = $tenant_id ?: get_current_tenant_id();

    if (!$tenant_id) {
        return false;
    }

    try {
        $pdo = get_db_connection();
        $featureAccess = new FeatureAccess($pdo, $tenant_id);
        return $featureAccess->isSubscriptionActive();
    } catch (Exception $e) {
        error_log("isSubscriptionActive error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if company is on a trial period
 * 
 * @param int|null $tenant_id Tenant ID (uses current if null)
 * @return bool True if on trial, false otherwise
 */
function isOnTrial($tenant_id = null): bool
{
    $tenant_id = $tenant_id ?: get_current_tenant_id();

    if (!$tenant_id) {
        return false;
    }

    try {
        $pdo = get_db_connection();
        $featureAccess = new FeatureAccess($pdo, $tenant_id);
        return $featureAccess->isOnTrial();
    } catch (Exception $e) {
        error_log("isOnTrial error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get days remaining in trial
 * 
 * @param int|null $tenant_id Tenant ID (uses current if null)
 * @return int Number of days remaining (0 if not on trial or expired)
 */
function getTrialDaysRemaining($tenant_id = null): int
{
    $tenant_id = $tenant_id ?: get_current_tenant_id();

    if (!$tenant_id) {
        return 0;
    }

    try {
        $pdo = get_db_connection();
        $featureAccess = new FeatureAccess($pdo, $tenant_id);
        return $featureAccess->getTrialDaysRemaining();
    } catch (Exception $e) {
        error_log("getTrialDaysRemaining error: " . $e->getMessage());
        return 0;
    }
}

// -----------------------------------------------------------------------------
// Limit Checking
// -----------------------------------------------------------------------------

/**
 * Check usage limits for company
 *
 * @param string   $limit_type (users, branches, products, storage)
 * @param int|null $tenant_id
 * @return array ['within_limit' => bool, 'current' => int, 'max' => int]
 */
if (!function_exists('check_company_limit')) {
    function check_company_limit($limit_type, $tenant_id = null): array
    {
        $tenant_id = $tenant_id ?: get_current_tenant_id();

        if (!$tenant_id) {
            return ['within_limit' => false, 'current' => 0, 'max' => 0];
        }

        try {
            $pdo = get_db_connection();

            // Try to use stored procedure first
            $stmt = $pdo->prepare("SHOW PROCEDURE STATUS WHERE Name = 'check_company_limits'");
            $stmt->execute();
            $procedure_exists = $stmt->fetch();

            if ($procedure_exists) {
                $stmt = $pdo->prepare("CALL check_company_limits(?, ?, @within_limit, @current_usage, @max_limit)");
                $stmt->execute([$tenant_id, $limit_type]);

                $result = $pdo->query("SELECT @within_limit, @current_usage, @max_limit")->fetch();
                return [
                    'within_limit' => (bool) ($result['@within_limit'] ?? false),
                    'current' => (int) ($result['@current_usage'] ?? 0),
                    'max' => (int) ($result['@max_limit'] ?? 0)
                ];
            }

            // Fallback: direct queries
            $limits = get_tenant_limits_from_subscription($tenant_id);

            switch ($limit_type) {
                case 'users':
                    $current = db_fetch_value(
                        "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 1",
                        [$tenant_id]
                    );
                    $max = $limits['max_users'] ?? 999999;
                    break;

                case 'branches':
                    $current = db_fetch_value(
                        "SELECT COUNT(*) FROM branches WHERE tenant_id = ? AND deleted_at IS NULL",
                        [$tenant_id]
                    );
                    $max = $limits['max_branches'] ?? 999999;
                    break;

                case 'products':
                    $current = db_fetch_value(
                        "SELECT COUNT(*) FROM products WHERE tenant_id = ? AND deleted_at IS NULL",
                        [$tenant_id]
                    );
                    $max = $limits['max_products'] ?? 999999;
                    break;

                case 'storage':
                    $current = (int) db_fetch_value(
                        "SELECT COALESCE(storage_used_mb, 0) FROM companies WHERE id = ?",
                        [$tenant_id]
                    );
                    $max = $limits['max_storage_mb'] ?? 999999;
                    break;

                default:
                    return ['within_limit' => true, 'current' => 0, 'max' => 0];
            }

            return [
                'within_limit' => $current < $max,
                'current' => (int) $current,
                'max' => (int) $max
            ];

        } catch (Exception $e) {
            error_log("check_company_limit failed: " . $e->getMessage());
            return ['within_limit' => true, 'current' => 0, 'max' => 999999];
        }
    }

    /**
     * Get company limits from subscription (cached)
     *
     * @param int|null $tenant_id
     * @return array
     */
    function get_tenant_limits_from_subscription($tenant_id = null): array
    {
        $tenant_id = $tenant_id ?: get_current_tenant_id();

        if (!$tenant_id) {
            return [];
        }

        static $cache = [];

        if (!isset($cache[$tenant_id])) {
            $cache[$tenant_id] = db_fetch_one(
                "SELECT
                COALESCE(c.max_users, sp.max_users, 999999) AS max_users,
                COALESCE(c.max_branches, sp.max_branches, 999999) AS max_branches,
                COALESCE(c.max_products, sp.max_products, 999999) AS max_products,
                COALESCE(c.storage_limit_mb, sp.max_storage_mb, 999999) AS max_storage_mb
            FROM companies c
            LEFT JOIN company_subscriptions cs ON c.id = cs.tenant_id AND cs.status = 'active'
            LEFT JOIN subscription_plans sp ON cs.plan_id = sp.id
            WHERE c.id = ?",
                [$tenant_id]
            ) ?: [];
        }

        return $cache[$tenant_id];
    }

    // -----------------------------------------------------------------------------
// Enforcement & UI
// -----------------------------------------------------------------------------

    /**
     * Enforce company limits before creating new records
     *
     * @param string   $limit_type
     * @param int|null $tenant_id
     * @return bool
     * @throws Exception (HTTP 403)
     */
    function enforce_company_limit($limit_type, $tenant_id = null): bool
    {
        $limit = check_company_limit($limit_type, $tenant_id);

        if (!$limit['within_limit']) {
            // Log the limit hit
            log_activity(get_current_user_id(), 'limit_reached', "Company reached {$limit_type} limit: {$limit['current']}/{$limit['max']}", [
                    'limit_type' => $limit_type,
                    'current_usage' => $limit['current'],
                    'max_limit' => $limit['max']
                ], get_current_tenant_id());

            // Display error page
            http_response_code(403);
            ?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Limit Reached | Jakababa POS</title>
                <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
                <style>
                    body {
                        background: linear-gradient(135deg, #1E3A8A 0%, #0F2B5E 100%);
                        font-family: 'Inter', sans-serif;
                    }
                </style>
            </head>

            <body class="min-h-screen flex items-center justify-center p-4">
                <div class="max-w-md w-full bg-white/10 backdrop-blur-xl rounded-3xl border border-white/20 p-8 text-center">
                    <div class="flex justify-center mb-4">
                        <div class="w-20 h-20 bg-yellow-500/20 rounded-2xl flex items-center justify-center">
                            <i class="fas fa-chart-line text-4xl text-yellow-400"></i>
                        </div>
                    </div>
                    <h1 class="text-2xl font-bold text-white mb-2">Limit Reached</h1>
                    <p class="text-gray-300 mb-4">Your company has reached its <?= ucfirst($limit_type) ?> limit.</p>
                    <p class="text-sm text-gray-400 mb-6">
                        Current: <?= number_format($limit['current']) ?> /
                        Max: <?= number_format($limit['max']) ?>
                    </p>
                    <div class="space-y-3">
                        <a href="<?= base_url('subscription.php') ?>"
                            class="block w-full px-4 py-3 bg-yellow-400 text-gray-900 rounded-xl font-semibold hover:bg-yellow-500 transition-colors">
                            <i class="fas fa-arrow-up mr-2"></i> Upgrade Plan
                        </a>
                        <a href="<?= base_url('index.php') ?>"
                            class="block w-full px-4 py-3 bg-gray-800 border border-gray-700 text-white rounded-xl font-semibold hover:bg-gray-700 transition-colors">
                            <i class="fas fa-home mr-2"></i> Return to Dashboard
                        </a>
                    </div>
                </div>
            </body>

            </html>
            <?php
            exit;
        }

        return true;
    }

    // -----------------------------------------------------------------------------
// Usage Statistics
// -----------------------------------------------------------------------------

    /**
     * Get company usage statistics
     *
     * @param int|null $tenant_id
     * @return array
     */
    function get_tenant_usage($tenant_id = null): array
    {
        $tenant_id = $tenant_id ?: get_current_tenant_id();

        if (!$tenant_id) {
            return [];
        }

        $row = db_fetch_one("
        SELECT
            (SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 1) AS user_count,
            (SELECT COUNT(*) FROM branches WHERE tenant_id = ? AND deleted_at IS NULL) AS branch_count,
            (SELECT COUNT(*) FROM products WHERE tenant_id = ? AND deleted_at IS NULL) AS product_count,
            (SELECT COUNT(*) FROM sales WHERE tenant_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed' AND voided = 0) AS today_sales_count,
            (SELECT COALESCE(SUM(total), 0) FROM sales WHERE tenant_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed' AND voided = 0) AS today_revenue,
            (SELECT COUNT(*) FROM sales WHERE tenant_id = ? AND status = 'completed' AND voided = 0) AS total_sales_count,
            (SELECT COALESCE(SUM(total), 0) FROM sales WHERE tenant_id = ? AND status = 'completed' AND voided = 0) AS total_revenue,
            (SELECT COALESCE(storage_used_mb, 0) FROM companies WHERE id = ?) AS storage_used_mb
    ", [$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id]);

        if (!$row) {
            $row = [];
        }

        $sub = get_subscription_features($tenant_id);
        $row['plan_name'] = $sub['plan_name'] ?? 'Free';
        $row['subscription_status'] = $sub['status'] ?? 'none';

        return $row;
    }

    // -----------------------------------------------------------------------------
// Trial Helpers
// -----------------------------------------------------------------------------

    /**
     * Check if company is on trial
     *
     * @param int|null $tenant_id
     * @return bool
     */
    function is_on_trial($tenant_id = null): bool
    {
        $tenant_id = $tenant_id ?: get_current_tenant_id();

        if (!$tenant_id) {
            return false;
        }

        $sub = get_tenant_subscription($tenant_id);
        return $sub && $sub['status'] === 'trialing' && $sub['trial_ends_at'] && strtotime($sub['trial_ends_at']) > time();
    }

    /**
     * Get days remaining in trial
     *
     * @param int|null $tenant_id
     * @return int
     */
    function get_trial_days_remaining($tenant_id = null): int
    {
        $tenant_id = $tenant_id ?: get_current_tenant_id();

        if (!$tenant_id) {
            return 0;
        }

        $sub = get_tenant_subscription($tenant_id);
        if ($sub && $sub['status'] === 'trialing' && $sub['trial_ends_at']) {
            $now = new DateTime();
            $trial_end = new DateTime($sub['trial_ends_at']);
            $diff = $now->diff($trial_end);
            return max(0, (int) $diff->days);
        }
        return 0;
    }

    // -----------------------------------------------------------------------------
// UI Banners
// -----------------------------------------------------------------------------

    /**
     * Show trial banner if applicable
     */
    function show_trial_banner(): void
    {
        if (is_on_trial()) {
            $days_left = get_trial_days_remaining();
            if ($days_left > 0 && $days_left <= 14) {
                ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4 rounded-r-lg">
                    <div class="flex items-center justify-between flex-wrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-hourglass-half text-yellow-400 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Trial Period:</strong> You have <?= $days_left ?> day<?= $days_left > 1 ? 's' : '' ?> left
                                    in your trial.
                                </p>
                            </div>
                        </div>
                        <div class="mt-2 sm:mt-0">
                            <a href="<?= base_url('subscription.php') ?>"
                                class="inline-flex items-center px-3 py-1 bg-yellow-500 text-white text-sm font-medium rounded-md hover:bg-yellow-600 transition">
                                <i class="fas fa-arrow-up mr-1"></i> Upgrade Now
                            </a>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
    }

    /**
     * Show upgrade banner for disabled features
     *
     * @param string $feature_name
     * @param string|null $feature_label
     */
    function show_upgrade_banner($feature_name, $feature_label = null): void
    {
        if (!has_feature_access($feature_name)) {
            ?>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                <i class="fas fa-lock text-gray-400 text-4xl mb-3"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Feature Locked</h3>
                <p class="text-gray-600 mb-4">
                    <?= htmlspecialchars($feature_label ?: ucfirst(str_replace('_', ' ', $feature_name))) ?>
                    is available on premium plans.
                </p>
                <a href="<?= base_url('subscription.php') ?>"
                    class="inline-flex items-center px-4 py-2 bg-yellow-400 text-gray-900 rounded-lg font-semibold hover:bg-yellow-500 transition">
                    <i class="fas fa-arrow-up mr-2"></i> Upgrade Your Plan
                </a>
            </div>
            <?php
        }
    }

    // -----------------------------------------------------------------------------
// Cron & Maintenance
// -----------------------------------------------------------------------------

    /**
     * Update company usage statistics (run via cron)
     *
     * @param int|null $tenant_id If null, updates all companies
     */
    function update_company_usage_stats($tenant_id = null): void
    {
        try {
            $pdo = get_db_connection();
            if ($tenant_id) {
                $stmt = $pdo->prepare("CALL update_company_usage(?)");
                $stmt->execute([$tenant_id]);
            } else {
                // Get all active companies
                $stmt = $pdo->query("SELECT id FROM companies WHERE deleted_at IS NULL");
                $companies = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($companies as $cid) {
                    $update = $pdo->prepare("CALL update_company_usage(?)");
                    $update->execute([$cid]);
                }
            }
        } catch (Exception $e) {
            error_log("update_company_usage_stats failed: " . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------------
// Plans
// -----------------------------------------------------------------------------

    /**
     * Get available subscription plans
     *
     * @param bool $include_inactive
     * @return array
     */
    function get_subscription_plans($include_inactive = false): array
    {
        try {
            $pdo = get_db_connection();
            $sql = "SELECT * FROM subscription_plans";
            if (!$include_inactive) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY price_monthly ASC";
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("get_subscription_plans failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get current plan details for company
     *
     * @param int|null $tenant_id
     * @return array|null
     */
    function get_current_plan($tenant_id = null): ?array
    {
        $features = get_subscription_features($tenant_id);
        $plan_name = $features['plan_name'];

        if ($plan_name === 'Free') {
            return null;
        }

        $plans = get_subscription_plans();
        foreach ($plans as $plan) {
            if ($plan['name'] === $plan_name) {
                return $plan;
            }
        }
        return null;
    }

    // -----------------------------------------------------------------------------
// Convenience Helpers
// -----------------------------------------------------------------------------

    /**
     * Check if company can create new record of type
     *
     * @param string   $type
     * @param int|null $tenant_id
     * @return bool
     */
    function can_create_record($type, $tenant_id = null): bool
    {
        $limit = check_company_limit($type, $tenant_id);
        return $limit['within_limit'];
    }

    /**
     * Get usage percentage for a limit type
     *
     * @param string   $limit_type
     * @param int|null $tenant_id
     * @return float
     */
    function get_usage_percentage($limit_type, $tenant_id = null): float
    {
        $limit = check_company_limit($limit_type, $tenant_id);
        if ($limit['max'] == 0) {
            return 0;
        }
        return round(($limit['current'] / $limit['max']) * 100, 2);
    }
}
