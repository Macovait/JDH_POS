<?php
/**
 * ENTERPRISE POS SYSTEM - PURE TAILWIND EDITION
 * Complete POS with business types, branch management, shift handling
 * ZERO-TRUST TENANT ISOLATION - All data access is scoped to company/branch
 * 
 * @version 6.0 - PURE TAILWIND (No custom CSS except essential animations)
 * @total_lines ~3800
 */

// ============================================
// SESSION START (MUST BE FIRST - before any output)
// ============================================
if (!defined('SESSION_COOKIE_PATH')) {
    define('SESSION_COOKIE_PATH', '/');
}
session_name('jakababa_saas_sid');
session_start();

// ============================================
// ENVIRONMENT & ERROR HANDLING
// ============================================
$isDevelopment = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);

if ($isDevelopment) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/pos_errors.log');
}

// Security Headers (after session_start)
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('X-XSS-Protection: 1; mode=block');

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
require_once __DIR__ . '/../../src/cache_functions.php';
safe_require('functions.php', 'src', true);
require_once __DIR__ . '/../../src/Pos/PosBootstrapService.php';

// Auto-print labels toast
if (!empty($_SESSION['auto_labels_printed'])) {
    $count = (int)$_SESSION['auto_labels_printed'];
    $method = $_SESSION['auto_labels_method'] ?? 'pdf';
    $toastMsg = "Auto-printed {$count} labels via " . strtoupper($method);
    echo "<script>document.addEventListener('DOMContentLoaded', () => { if (typeof showToast === 'function') showToast('" . addslashes($toastMsg) . "', 'success'); });</script>";
    unset($_SESSION['auto_labels_printed'], $_SESSION['auto_labels_method']);
}

// CSRF Token
if (empty($_SESSION['csrf_token']) || (time() - ($_SESSION['csrf_token_time'] ?? 0)) > 7200) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// Session lock released after branch switch logic further below

// ============================================
// ZERO-TRUST TENANT CONTEXT
// ============================================
$tenant_id = $user_id = $branch_id = null;
$is_super_admin = false;

try {
    require_once __DIR__ . '/../../src/SecureTenantContext.php';
    $context = SecureTenantContext::getInstance();
    
    if ($context && $context->isValid()) {
        $tenant_id = $context->getCompanyId();
        $user_id = $context->getUserId();
        $branch_id = $context->getBranchId();
        $is_super_admin = $context->isSuperAdmin();
    } else {
        throw new Exception('Invalid tenant context');
    }
} catch (Throwable $e) {
    error_log("SecureTenantContext failed: " . $e->getMessage());
    header('Location: ../auth/login.php?error=tenant_validation_failed');
    exit;
}

// Session tamper detection
$session_tenant_id = $_SESSION['tenant_id'] ?? null;
$session_user_id = $_SESSION['user_id'] ?? null;

if (($session_tenant_id && (int)$session_tenant_id !== $tenant_id) ||
    ($session_user_id && (int)$session_user_id !== $user_id)) {
    error_log("ZERO-TRUST VIOLATION: Session mismatch");
    header('Location: ../auth/login.php?error=tenant_mismatch');
    exit;
}

if (!$tenant_id || !$user_id || $tenant_id <= 0 || $user_id <= 0) {
    error_log("ZERO-TRUST BLOCKED: Invalid context");
    header('Location: ../auth/login.php?error=invalid_tenant');
    exit;
}

// ============================================
// DATABASE CONNECTION
// ============================================
$pdo = get_db_connection();
if (!$pdo) {
    die("Database connection failed");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// ============================================
// ZERO-TRUST: RE-VALIDATE EVERY REQUEST
// Never trust session alone - verify user, tenant, branch from DB
// ============================================
try {
    // Check if user can access multiple branches
    $canValidateAnyBranch = $is_super_admin || (function_exists('check_permission') && check_permission('branches.view'));
    
    if ($canValidateAnyBranch) {
        // For branch-switching users, validate branch belongs to tenant
        $stmt = $pdo->prepare("
            SELECT u.id as user_id, u.tenant_id, u.is_active as user_active,
                   c.id as tenant_exists, c.status as tenant_status,
                   b.id as branch_exists, b.is_active as branch_active, b.name as branch_name
            FROM users u
            JOIN tenants c ON u.tenant_id = c.id
            LEFT JOIN branches b ON b.tenant_id = c.id AND b.id = ?
            WHERE u.id = ? AND c.id = ?
            LIMIT 1
        ");
        $stmt->execute([$branch_id, $user_id, $tenant_id]);
    } else {
        // For regular users, validate strict assignment
        $stmt = $pdo->prepare("
            SELECT u.id as user_id, u.tenant_id, u.branch_id, u.is_active as user_active,
                   c.id as tenant_exists, c.status as tenant_status,
                   b.id as branch_exists, b.is_active as branch_active
            FROM users u
            JOIN tenants c ON u.tenant_id = c.id
            JOIN branches b ON b.tenant_id = c.id AND b.id = u.branch_id
            WHERE u.id = ? AND c.id = ? AND b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $tenant_id, $branch_id]);
    }
    $validation = $stmt->fetch();

    if (!$validation || !$validation['user_active']) {
        error_log("ZERO-TRUST BLOCKED: User not found or inactive");
        session_destroy();
        header('Location: ../auth/login.php?error=zero_trust_blocked');
        exit;
    }

    $validTenantStatuses = ['active', 'trial'];
    if (!$validation['tenant_exists'] || !in_array($validation['tenant_status'], $validTenantStatuses, true)) {
        error_log("ZERO-TRUST BLOCKED: Tenant not found or inactive");
        session_destroy();
        header('Location: ../auth/login.php?error=zero_trust_blocked');
        exit;
    }

    // For users with branch switching permission, verify branch exists and is active
    if ($canValidateAnyBranch) {
        if (!$validation['branch_exists']) {
            error_log("ZERO-TRUST BLOCKED: Branch not found");
            session_destroy();
            header('Location: ../auth/login.php?error=zero_trust_blocked');
            exit;
        }
        // Get branch status - active column may be is_active
        $branch_active = $validation['branch_active'] ?? $validation['is_active'] ?? 0;
        if (!$branch_active) {
            error_log("ZERO-TRUST BLOCKED: Branch not active");
            session_destroy();
            header('Location: ../auth/login.php?error=zero_trust_blocked');
            exit;
        }
    } else {
        // For regular users, strict branch assignment check
        if (!$validation['branch_exists'] || !$validation['branch_active']) {
            error_log("ZERO-TRUST BLOCKED: Branch not found or inactive");
            session_destroy();
            header('Location: ../auth/login.php?error=zero_trust_blocked');
            exit;
        }
    }

    // Update branch info in session if needed (for mobile app branch selection)
    if (!empty($validation['branch_name'])) {
        $_SESSION['branch_name'] = $validation['branch_name'];
    }
} catch (Exception $e) {
    error_log("ZERO-TRUST ERROR: Re-validation exception - " . $e->getMessage());
    header('Location: ../auth/login.php?error=zero_trust_error');
    exit;
}

$current_user_name = function_exists('get_current_user_name') ? get_current_user_name() : 'Cashier';
$session_branch_id = $branch_id;

// Branch switch permission
$_zt_role = strtolower($_SESSION['role'] ?? $_SESSION['user']['role'] ?? $_SESSION['user_role'] ?? '');
$_zt_admin_roles = ['admin', 'administrator', 'manager', 'owner', 'super_admin', 'superadmin'];
$can_switch_branch = $is_super_admin
    || in_array($_zt_role, $_zt_admin_roles, true)
    || (function_exists('check_permission') && check_permission('branches.view'));

// ============================================
// HELPER FUNCTIONS
// ============================================
if (!function_exists('sanitize_input')) {
    function sanitize_input($data, $type = 'string') {
        if ($data === null) return null;
        
        $filters = [
            'int' => FILTER_VALIDATE_INT,
            'float' => FILTER_VALIDATE_FLOAT,
            'email' => FILTER_SANITIZE_EMAIL,
            'url' => FILTER_SANITIZE_URL,
            'bool' => FILTER_VALIDATE_BOOLEAN,
            'string' => FILTER_SANITIZE_FULL_SPECIAL_CHARS
        ];
        
        $filter = $filters[$type] ?? FILTER_SANITIZE_FULL_SPECIAL_CHARS;
        $value = filter_var($data, $filter);
        
        return $type === 'bool' ? $value : ($value ?: null);
    }
}

if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// ============================================
// BUSINESS TYPES CONFIGURATION
// ============================================
$business_types = [];

$colors = [
    'retail' => '#3B82F6', 'supermarket' => '#10B981', 'restaurant' => '#F59E0B',
    'pharmacy' => '#EF4444', 'salon' => '#EC4899', 'electronics' => '#8B5CF6',
    'hardware' => '#F97316', 'butchery' => '#DC2626', 'bakery' => '#D97706',
    'hotel' => '#6366F1', 'wholesale' => '#06B6D4', 'service' => '#14B8A6',
    'grocery' => '#22C55E', 'liquor' => '#7C3AED', 'fashion' => '#DB2777',
    'stationery' => '#0EA5E9',
];

$featureMap = [
    'barcode_scanning' => 'barcode', 'track_stock' => 'stock', 'weight_based' => 'weight',
    'table_management' => 'table', 'kitchen_display' => 'kitchen_notes', 'recipe_management' => 'kitchen_notes',
    'prescription_required' => 'prescription', 'appointments' => 'appointment',
    'serial_tracking' => 'serial', 'warranty_tracking' => 'warranty',
    'bulk_pricing' => 'bulk', 'expiry_tracking' => 'expiry', 'expiry_alerts' => 'expiry',
    'room_service' => 'room_charge', 'age_verification' => 'age_verify',
    'staff_commission' => 'staff_select', 'batch_tracking' => 'batch_number'
];

if (function_exists('get_business_type_config')) {
    $central = get_business_type_config();
    if (is_array($central) && !empty($central)) {
        foreach ($central as $code => $cfg) {
            $features = [];
            foreach ($featureMap as $centralKey => $uiKey) {
                if (!empty($cfg['features'][$centralKey])) {
                    $features[$uiKey] = true;
                }
            }
            
            $orderTypes = [];
            foreach ($cfg['order_types'] ?? ['walkin' => 'Walk-in'] as $otCode => $otConfig) {
                if (!is_string($otCode)) {
                    $otCode = is_array($otConfig) && isset($otConfig['code']) ? (string) $otConfig['code'] : (string) $otConfig;
                }
                
                $key = str_replace('-', '', $otCode);
                $labelConfig = $cfg['order_labels'][$otCode] ?? null;
                $orderLabel = is_array($labelConfig) ? ($labelConfig['label'] ?? null) : $labelConfig;
                $orderIcon = is_array($labelConfig) ? ($labelConfig['icon'] ?? null) : null;
                $orderTypes[$key] = [
                    'label' => is_string($orderLabel) && $orderLabel !== '' ? $orderLabel : ucwords(str_replace('-', ' ', $otCode)),
                    'icon' => is_string($orderIcon) && $orderIcon !== '' ? $orderIcon : 'fa-user',
                    'code' => $otCode,
                ];
            }
            
            $business_types[$code] = [
                'name' => $cfg['name'] ?? ucfirst($code),
                'icon' => $cfg['icon'] ?? 'fa-store',
                'color' => $colors[$code] ?? '#3B82F6',
                'order_types' => $orderTypes ?: ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'code' => 'walkin']],
                'features' => $features,
                'sale_label' => $cfg['sale_label'] ?? 'Sale',
                'sales_label' => $cfg['sales_label'] ?? 'Sales',
                'customer_label' => $cfg['customer_label'] ?? 'Customer',
            ];
        }
    }
}

// Fallback business types if central config failed
if (empty($business_types)) {
    $business_types = [
        'retail' => [
            'name' => 'Retail Store', 'icon' => 'fa-store', 'color' => '#3B82F6',
            'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'code' => 'walkin']],
            'features' => ['barcode' => true, 'stock' => true],
            'sale_label' => 'Sale', 'sales_label' => 'Sales', 'customer_label' => 'Customer'
        ],
        'supermarket' => [
            'name' => 'Supermarket', 'icon' => 'fa-shopping-cart', 'color' => '#10B981',
            'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'code' => 'walkin'], 'online' => ['label' => 'Online', 'icon' => 'fa-globe', 'code' => 'online']],
            'features' => ['barcode' => true, 'stock' => true, 'weight' => true],
            'sale_label' => 'Sale', 'sales_label' => 'Sales', 'customer_label' => 'Customer'
        ],
        'restaurant' => [
            'name' => 'Restaurant / Cafe', 'icon' => 'fa-utensils', 'color' => '#F59E0B',
            'order_types' => [
                'dinein' => ['label' => 'Dine-in', 'icon' => 'fa-chair', 'code' => 'dine-in'],
                'takeaway' => ['label' => 'Takeaway', 'icon' => 'fa-bag', 'code' => 'takeaway'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck', 'code' => 'delivery']
            ],
            'features' => ['kitchen_notes' => true, 'table' => true],
            'sale_label' => 'Order', 'sales_label' => 'Orders', 'customer_label' => 'Guest'
        ],
        'pharmacy' => [
            'name' => 'Pharmacy', 'icon' => 'fa-pills', 'color' => '#EF4444',
            'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'code' => 'walkin'], 'prescription' => ['label' => 'Prescription', 'icon' => 'fa-file-medical', 'code' => 'prescription']],
            'features' => ['prescription' => true, 'stock' => true, 'batch_number' => true],
            'sale_label' => 'Sale', 'sales_label' => 'Sales', 'customer_label' => 'Patient'
        ],
        'salon' => [
            'name' => 'Salon / Beauty', 'icon' => 'fa-cut', 'color' => '#EC4899',
            'order_types' => ['appointment' => ['label' => 'Appointment', 'icon' => 'fa-calendar', 'code' => 'appointment'], 'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'code' => 'walkin']],
            'features' => ['appointment' => true, 'staff_select' => true],
            'sale_label' => 'Service', 'sales_label' => 'Services', 'customer_label' => 'Client'
        ],
        'electronics' => [
            'name' => 'Electronics', 'icon' => 'fa-laptop', 'color' => '#8B5CF6',
            'order_types' => ['retail' => ['label' => 'Retail', 'icon' => 'fa-store', 'code' => 'retail'], 'installment' => ['label' => 'Installment', 'icon' => 'fa-calendar-check', 'code' => 'installment']],
            'features' => ['serial' => true, 'warranty' => true],
            'sale_label' => 'Sale', 'sales_label' => 'Sales', 'customer_label' => 'Customer'
        ],
        'hardware' => [
            'name' => 'Hardware Store', 'icon' => 'fa-hammer', 'color' => '#F97316',
            'order_types' => ['retail' => ['label' => 'Retail', 'icon' => 'fa-store', 'code' => 'retail'], 'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes', 'code' => 'wholesale']],
            'features' => ['barcode' => true, 'stock' => true, 'weight' => true],
            'sale_label' => 'Sale', 'sales_label' => 'Sales', 'customer_label' => 'Customer'
        ],
        'butchery' => [
            'name' => 'Butchery', 'icon' => 'fa-drumstick-bite', 'color' => '#DC2626',
            'order_types' => ['retail' => ['label' => 'Retail', 'icon' => 'fa-store', 'code' => 'retail'], 'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes', 'code' => 'wholesale']],
            'features' => ['weight' => true, 'stock' => true],
            'sale_label' => 'Sale', 'sales_label' => 'Sales', 'customer_label' => 'Customer'
        ],
        'bakery' => [
            'name' => 'Bakery', 'icon' => 'fa-bread-slice', 'color' => '#D97706',
            'order_types' => ['dinein' => ['label' => 'Dine-in', 'icon' => 'fa-chair', 'code' => 'dine-in'], 'takeaway' => ['label' => 'Takeaway', 'icon' => 'fa-bag', 'code' => 'takeaway'], 'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck', 'code' => 'delivery']],
            'features' => ['expiry' => true, 'stock' => true],
            'sale_label' => 'Sale', 'sales_label' => 'Sales', 'customer_label' => 'Customer'
        ],
        'hotel' => [
            'name' => 'Hotel / Lodging', 'icon' => 'fa-hotel', 'color' => '#6366F1',
            'order_types' => ['room' => ['label' => 'Room Service', 'icon' => 'fa-bed', 'code' => 'room'], 'dinein' => ['label' => 'Restaurant', 'icon' => 'fa-utensils', 'code' => 'dine-in']],
            'features' => ['room_charge' => true],
            'sale_label' => 'Charge', 'sales_label' => 'Charges', 'customer_label' => 'Guest'
        ],
        'liquor_store' => [
            'name' => 'Liquor Store', 'icon' => 'fa-wine-bottle', 'color' => '#7C3AED',
            'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'code' => 'walkin'], 'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes', 'code' => 'wholesale'], 'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck', 'code' => 'delivery']],
            'features' => ['barcode' => true, 'stock' => true, 'age_verify' => true, 'bulk' => true],
            'sale_label' => 'Sale', 'sales_label' => 'Sales', 'customer_label' => 'Customer'
        ],
        'stationery' => [
            'name' => 'Stationery / Office', 'icon' => 'fa-pencil-alt', 'color' => '#0EA5E9',
            'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'code' => 'walkin'], 'online' => ['label' => 'Online', 'icon' => 'fa-globe', 'code' => 'online'], 'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes', 'code' => 'wholesale'], 'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck', 'code' => 'delivery']],
            'features' => ['barcode' => true, 'stock' => true, 'bulk' => true],
            'sale_label' => 'Sale', 'sales_label' => 'Sales', 'customer_label' => 'Customer'
        ],
    ];
}

// Detect business type — priority: ?bt_id= URL param > branch record > fallback
$detected_bt = 'retail';
try {
    $override_bt_id = isset($_GET['bt_id']) ? (int)$_GET['bt_id'] : 0;
    if ($override_bt_id > 0) {
        // URL param override — verify it belongs to this tenant's context
        $bt_ovr = $pdo->prepare("SELECT code FROM business_types WHERE id = ? AND is_active = 1 LIMIT 1");
        $bt_ovr->execute([$override_bt_id]);
        $bt_ovr_row = $bt_ovr->fetch(PDO::FETCH_ASSOC);
        if (!empty($bt_ovr_row['code'])) $detected_bt = $bt_ovr_row['code'];
    } else {
        // Read from branch record
        $bt_detect_stmt = $pdo->prepare("
            SELECT bt.code, bt.id as bt_id
            FROM branches b
            LEFT JOIN business_types bt ON bt.id = b.business_type_id AND b.business_type_id > 0
            WHERE b.id = ? AND b.tenant_id = ?
            LIMIT 1
        ");
        $bt_detect_stmt->execute([$branch_id, $tenant_id]);
        $bt_detect_row = $bt_detect_stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($bt_detect_row['code'])) {
            $detected_bt = $bt_detect_row['code'];
        } elseif (function_exists('get_current_business_type')) {
            $detected_bt = get_current_business_type($tenant_id) ?: 'retail';
        }
    }
} catch (Exception $e) { /* fallback to retail */ }

$current_business_type = isset($business_types[$detected_bt]) ? $detected_bt : 'retail';
$business_type_config = $business_types[$current_business_type];
$order_types = $business_type_config['order_types'] ?? ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'code' => 'walkin']];
$bt_features = $business_type_config['features'] ?? [];

// ============================================
// LOAD SETTINGS
// ============================================
$settings = ['tax_rate' => 16, 'currency' => 'KES', 'company_name' => 'Jakababa POS', 'business_type' => 'retail', 'site_title' => '', 'site_icon' => ''];
$site_icon_exists = false;

if (!empty($bootstrap_data['settings'])) {
    $settings = array_merge($settings, $bootstrap_data['settings']);
} else {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Settings query error: " . $e->getMessage());
    }
}
$site_icon_exists = !empty($settings['site_icon']) && file_exists(PUBLIC_PATH . DIRECTORY_SEPARATOR . ltrim((string) $settings['site_icon'], '/\\'));

$pos_page_title = !empty($settings['site_title']) ? $settings['site_title'] : ($settings['company_name'] ?? 'POS') . ' - POS';

// ============================================
// PAYMENT METHODS
// ============================================
$payment_methods_config = [
    ['id' => 'cash', 'label' => 'Cash', 'icon' => 'fa-money-bill', 'default' => true],
    ['id' => 'card', 'label' => 'Card', 'icon' => 'fa-credit-card', 'default' => true],
    ['id' => 'mpesa', 'label' => 'M-Pesa', 'icon' => 'fa-mobile-alt', 'default' => true],
    ['id' => 'credit', 'label' => 'Credit', 'icon' => 'fa-user-clock', 'default' => false],
    ['id' => 'bank', 'label' => 'Bank Transfer', 'icon' => 'fa-university', 'default' => false],
];

$enabled_payment_methods = array_filter($payment_methods_config, function($pm) use ($settings) {
    $settingKey = 'enable_' . $pm['id'];
    return $pm['default'] || ($settings[$settingKey] ?? '0') === '1';
});
$enabled_payment_methods = array_values($enabled_payment_methods);

if (empty($enabled_payment_methods)) {
    $enabled_payment_methods = [['id' => 'cash', 'label' => 'Cash', 'icon' => 'fa-money-bill']];
}

// ============================================
// LOAD BRANCHES
// ============================================
$branches = [];
$current_branch = ['id' => $branch_id, 'name' => 'Branch', 'code' => '', 'tax_rate' => 0.00];

if ($tenant_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, code, tax_rate, location, active, is_active FROM branches WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name");
        $stmt->execute([$tenant_id]);
        $branches = $stmt->fetchAll();

        $matched = false;
        foreach ($branches as $b) {
            if ($b['id'] == $branch_id) {
                $current_branch = $b;
                $matched = true;
                break;
            }
        }

        // If branch_id not found in active list, fetch it directly by ID
        if (!$matched && $branch_id) {
            $stmt2 = $pdo->prepare("SELECT id, name, code, tax_rate, location, active, is_active FROM branches WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmt2->execute([$branch_id, $tenant_id]);
            $direct = $stmt2->fetch();
            if ($direct) {
                $current_branch = $direct;
            }
        }
    } catch (Exception $e) {
        error_log("Failed to load branches: " . $e->getMessage());
    }
}

$target_branch = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$branch_token = $_GET['branch_token'] ?? '';
$_pos_csrf = $_SESSION['csrf_token'] ?? '';
$_branch_csrf_valid = !empty($_pos_csrf) && !empty($branch_token) && hash_equals($_pos_csrf, $branch_token);
if ($target_branch && $can_switch_branch && $_branch_csrf_valid) {
    foreach ($branches as $b) {
        if ((int)$b['id'] === $target_branch) {
            // Persist branch switch to session
            $_SESSION['branch_id']      = (int)$b['id'];
            $_SESSION['branch_name']    = $b['name'] ?? '';
            $_SESSION['current_branch'] = ['id' => (int)$b['id'], 'name' => $b['name'] ?? ''];
            session_write_close();
            // Redirect cleanly so the page re-renders with the new session branch_id
            $btParam = isset($_GET['bt_id']) ? '&bt_id=' . (int)$_GET['bt_id'] : '';
            header('Location: pos.php?switched=1' . $btParam);
            exit;
        }
    }
}

// Release session lock
session_write_close();

// ============================================
// LOAD CATEGORIES (WITH CACHE)
// ============================================
$categories = [];
if (!empty($bootstrap_data['categories'])) {
    $categories = $bootstrap_data['categories'];
} else {
    try {
        // Use cached function for better performance
        $categories = db_get_categories_cached($tenant_id, 300);
    } catch (Exception $e) {
        error_log("Failed to load categories: " . $e->getMessage());
        $categories = [];
    }
}

// ============================================
// LOAD PRODUCTS WITH STOCK (WITH CACHE)
// ============================================
$products = [];
// Resolve numeric business_type_id for filtering
$current_bt_id = null;
try {
    $bt_row = $pdo->prepare("SELECT id FROM business_types WHERE code = ? LIMIT 1");
    $bt_row->execute([$current_business_type]);
    $bt_result = $bt_row->fetch(PDO::FETCH_ASSOC);
    $current_bt_id = $bt_result ? (int)$bt_result['id'] : null;
} catch (Exception $e) { /* ignore */ }

if (!empty($bootstrap_data['products'])) {
    $products = $bootstrap_data['products'];
    if ($current_bt_id) {
        // Strict: show products matching this bt_id, OR products with no bt_id set (shared catalog)
        $products = array_values(array_filter($products, fn($p) =>
            !isset($p['business_type_id']) || (int)$p['business_type_id'] === 0
            || is_null($p['business_type_id']) || (int)$p['business_type_id'] === $current_bt_id
        ));
    }
} else {
    // Products loaded via AJAX for faster page load - skip PHP loading
    $products = [];
}

// ============================================
// LOAD SHIFT STATUS
// ============================================
$current_shift = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM register_sessions WHERE tenant_id = ? AND branch_id = ? AND user_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1");
    $stmt->execute([$tenant_id, $branch_id, $user_id]);
    $current_shift = $stmt->fetch();
} catch (Exception $e) {
    error_log("Failed to load shift status: " . $e->getMessage());
}

// ============================================
// TODAY'S STATS
// ============================================
$today_stats = ['total' => 0, 'sale_count' => 0, 'cash' => 0, 'card' => 0, 'mpesa' => 0];
try {
    $bt_stats_filter = '';
    $bt_stats_params = [$tenant_id, $branch_id];
    // Filter by business_type_id if resolved
    $bt_has_col = false;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM sales LIKE 'business_type_id'");
        $bt_has_col = $chk && $chk->rowCount() > 0;
    } catch (Exception $e) {}
    if ($bt_has_col && $current_bt_id) {
        $bt_stats_filter = "AND (business_type_id = ? OR business_type_id IS NULL OR business_type_id = 0)";
        $bt_stats_params[] = $current_bt_id;
    }
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as sale_count, COALESCE(SUM(total), 0) as total, 
               COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash,
               COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card,
               COALESCE(SUM(CASE WHEN payment_method = 'mpesa' THEN total ELSE 0 END), 0) as mpesa
        FROM sales 
        WHERE tenant_id = ? AND branch_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed' AND voided = 0
        $bt_stats_filter
    ");
    $stmt->execute($bt_stats_params);
    $today_stats = $stmt->fetch();
} catch (Exception $e) {
    error_log("Failed to load today's stats: " . $e->getMessage());
}

// ============================================
// HELD SALES COUNT
// ============================================
$held_count = 0;
try {
    $held_bt_filter = '';
    $held_params = [$tenant_id, $branch_id];
    // Check column exists before filtering
    $hc_chk = $pdo->query("SHOW COLUMNS FROM held_sales LIKE 'business_type_id'");
    if ($hc_chk && $hc_chk->rowCount() > 0 && $current_bt_id) {
        $held_bt_filter = "AND (business_type_id = ? OR business_type_id IS NULL OR business_type_id = 0)";
        $held_params[] = $current_bt_id;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM held_sales WHERE tenant_id = ? AND branch_id = ? $held_bt_filter");
    $stmt->execute($held_params);
    $held_count = $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Failed to load held sales count: " . $e->getMessage());
}

// ============================================
// HOTEL: RECENT ROOMS/GUESTS
// ============================================
$recent_room_numbers = [];
$recent_guest_names = [];

if (!empty($bt_features['room_charge'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT room_number, guest_name FROM sales 
            WHERE tenant_id = ? AND branch_id = ? 
                AND (room_number IS NOT NULL OR guest_name IS NOT NULL)
                AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                AND room_number != '' AND room_number IS NOT NULL
            ORDER BY id DESC LIMIT 10
        ");
        $stmt->execute([$tenant_id, $branch_id]);
        while ($row = $stmt->fetch()) {
            if (!empty($row['room_number'])) $recent_room_numbers[] = $row['room_number'];
            if (!empty($row['guest_name'])) $recent_guest_names[] = $row['guest_name'];
        }
        $recent_room_numbers = array_unique($recent_room_numbers);
        $recent_guest_names = array_unique($recent_guest_names);
    } catch (Exception $e) {
        error_log("Failed to load recent rooms/guests: " . $e->getMessage());
    }
}

// ============================================
// SALON: TODAY'S APPOINTMENTS
// ============================================
$today_appointments = [];
if (!empty($bt_features['appointment'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.id, a.appointment_time, a.customer_id, a.service_name, a.status,
                   c.name as customer_name
            FROM appointments a
            LEFT JOIN customers c ON c.id = a.customer_id
            WHERE a.tenant_id = ? AND a.branch_id = ? 
                AND DATE(a.appointment_date) = CURDATE()
                AND a.status IN ('scheduled', 'confirmed')
            ORDER BY a.appointment_time LIMIT 20
        ");
        $stmt->execute([$tenant_id, $branch_id]);
        $today_appointments = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Failed to load appointments: " . $e->getMessage());
    }
}

// ============================================
// DISCOUNTS
// ============================================
$discounts = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM discounts 
        WHERE tenant_id = ? AND active = 1 
            AND (valid_from IS NULL OR valid_from <= NOW()) 
            AND (valid_until IS NULL OR valid_until >= NOW())
        ORDER BY priority DESC, value DESC
        LIMIT 50
    ");
    $stmt->execute([$tenant_id]);
    $discounts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to load discounts: " . $e->getMessage());
}

// ============================================
// PRODUCT TAGS
// ============================================
$availableTags = [];
$productTagsMap = [];

try {
    if (function_exists('db_table_exists') && db_table_exists('product_tags') && db_table_exists('product_tag_relations')) {
        $tagStmt = $pdo->prepare("
            SELECT id, name, color, icon 
            FROM product_tags 
            WHERE tenant_id = ? AND is_active = 1 
            ORDER BY name
        ");
        $tagStmt->execute([$tenant_id]);
        $availableTags = $tagStmt->fetchAll();
        
        if (!empty($products)) {
            $productIds = array_column($products, 'id');
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $relStmt = $pdo->prepare("
                SELECT r.product_id, t.id as tag_id, t.name, t.color, t.icon
                FROM product_tag_relations r
                JOIN product_tags t ON r.tag_id = t.id
                WHERE r.tenant_id = ? AND r.product_id IN ($placeholders) AND t.is_active = 1
            ");
            $relStmt->execute(array_merge([$tenant_id], $productIds));
            while ($row = $relStmt->fetch()) {
                $pid = (int) $row['product_id'];
                if (!isset($productTagsMap[$pid])) $productTagsMap[$pid] = [];
                $productTagsMap[$pid][] = [
                    'id' => (int) $row['tag_id'],
                    'name' => $row['name'],
                    'color' => $row['color'],
                    'icon' => $row['icon']
                ];
            }
            foreach ($products as &$p) {
                $p['tags'] = $productTagsMap[(int) $p['id']] ?? [];
                $p['tag_ids'] = array_column($p['tags'], 'id');
            }
            unset($p);
        }
    }
} catch (Exception $e) {
    error_log("Failed to load product tags: " . $e->getMessage());
}

$tax_rate = (int)($settings['tax_rate'] ?? 16);
$currency_symbol = $settings['currency'];
$company_name = $settings['company_name'] ?? 'POS';
$branch_switch_token = $_SESSION['csrf_token'] ?? '';

// Wholesale tiers from settings (fallback to standard tiers)
$wholesale_tiers = [];
if (!empty($settings['wholesale_tiers'])) {
    $wt = json_decode($settings['wholesale_tiers'], true);
    if (is_array($wt)) $wholesale_tiers = $wt;
}
if (empty($wholesale_tiers)) {
    $wholesale_tiers = [
        ['value' => 'retail',  'label' => 'Retail',    'discount' => 0],
        ['value' => 'tier1',   'label' => 'Tier 1',    'discount' => (int)($settings['wholesale_tier1_pct'] ?? 5)],
        ['value' => 'tier2',   'label' => 'Tier 2',    'discount' => (int)($settings['wholesale_tier2_pct'] ?? 10)],
        ['value' => 'tier3',   'label' => 'Tier 3',    'discount' => (int)($settings['wholesale_tier3_pct'] ?? 15)],
    ];
}

// Warranty periods from settings (fallback to standard)
$warranty_periods = [];
if (!empty($settings['warranty_periods'])) {
    $wp = json_decode($settings['warranty_periods'], true);
    if (is_array($wp)) $warranty_periods = $wp;
}
if (empty($warranty_periods)) {
    $warranty_periods = [
        ['value' => '',    'label' => 'None'],
        ['value' => '1m',  'label' => '1 Month'],
        ['value' => '3m',  'label' => '3 Months'],
        ['value' => '6m',  'label' => '6 Months'],
        ['value' => '12m', 'label' => '1 Year'],
        ['value' => '24m', 'label' => '2 Years'],
    ];
    // Trim to only what's configured if setting exists
    if (!empty($settings['warranty_periods_list'])) {
        $allowed = array_map('trim', explode(',', $settings['warranty_periods_list']));
        $warranty_periods = array_filter($warranty_periods, fn($p) => $p['value'] === '' || in_array($p['value'], $allowed));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0f172a">
    <title><?php echo htmlspecialchars($pos_page_title); ?></title>
    <?php if (!empty($settings['site_icon']) && $site_icon_exists): ?>
    <link rel="icon" href="<?php echo base_url($settings['site_icon']); ?>" type="image/png">
    <?php endif; ?>
    
    <!-- Tailwind CSS - Built locally for production -->
    <link rel="stylesheet" href="pos.css?v=<?php echo filemtime(__DIR__.'/pos.css'); ?>">
    
    <link rel="stylesheet" href="../assets/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="pos-stock.css?v=<?php echo filemtime(__DIR__.'/pos-stock.css'); ?>">
    
    <style>
        @keyframes fall {
            to { transform: translateY(100vh) rotate(720deg); }
        }
        .confetti-piece {
            position: absolute;
            animation: fall 2s linear forwards;
        }
        input[type="number"]::-webkit-inner-spin-button, 
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .scrollbar-thin::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: rgba(251,191,36,0.3);
            border-radius: 10px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: rgba(251,191,36,0.5);
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
        }
        /* ── Mobile-first responsive ── */
        @media (max-width: 767px) {
            /* Cart slides in from right as a drawer */
            .cart-panel {
                position: fixed;
                right: -100%;
                top: 0;
                bottom: 56px; /* above bottom nav */
                width: 88%;
                max-width: 340px;
                z-index: 1050;
                transition: right 0.28s cubic-bezier(.4,0,.2,1);
                border-radius: 12px 0 0 12px !important;
                box-shadow: -8px 0 32px rgba(0,0,0,.6);
            }
            .cart-panel.open { right: 0; }
            /* Cart backdrop */
            .cart-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,.55);
                z-index: 1049;
                backdrop-filter: blur(2px);
            }
            .cart-backdrop.open { display: block; }
            /* FAB hidden — replaced by bottom nav */
            .pos-cart-fab { display: none !important; }
            /* Bottom nav bar */
            .mobile-bottom-nav {
                display: flex;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: calc(56px + env(safe-area-inset-bottom));
                background: #0f172a;
                border-top: 1px solid rgba(51,65,85,.6);
                z-index: 1040;
                padding: 0 4px env(safe-area-inset-bottom);
            }
            /* Push main content up so bottom nav doesn't overlap */
            body.has-bottom-nav .flex-1.overflow-hidden { padding-bottom: 56px; }
            .desktop-only { display: none !important; }
            /* Header: hide clock and some labels */
            #liveDate, .header-branch-pill { display: none; }
            /* Toolbar: wrap on mobile */
            .toolbar-row { flex-wrap: wrap; gap: 6px 4px; }
            /* Modals full-width on mobile */
            .modal-inner { margin-left: 8px !important; margin-right: 8px !important; max-height: 92vh; overflow-y: auto; }
            /* Product grid 3-col minimum on tiny screens */
            #productsGrid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (min-width: 480px) and (max-width: 767px) {
            #productsGrid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (min-width: 768px) {
            .mobile-only { display: none !important; }
            .mobile-bottom-nav { display: none !important; }
            .pos-cart-fab { display: none !important; }
        }
        /* Tablet — slightly narrower cart */
        @media (min-width: 768px) and (max-width: 1023px) {
            .cart-panel { width: 240px; }
        }
        .product-card.processing { opacity: .65; transform: scale(.97); }
        .receipt-dashes { background-image: repeating-linear-gradient(90deg, transparent, transparent 4px, rgba(100,116,139,0.3) 4px, rgba(100,116,139,0.3) 8px); height: 1px; }
        .stock-bar-fill { transition: width 0.4s ease; }
        @keyframes slideIn { from { opacity:0; transform:translateX(8px); } to { opacity:1; transform:translateX(0); } }
        .cart-item { animation: slideIn 0.15s ease forwards; }
        .product-card { touch-action: manipulation; -webkit-tap-highlight-color: transparent; }
        .product-card:active { transform: scale(0.94); }
        /* Stock styles live in pos-stock.css (loaded after Tailwind) */
    </style>
</head>
<body class="bg-slate-950 font-['Inter'] antialiased overflow-hidden has-bottom-nav">

<div class="h-screen w-full overflow-hidden flex flex-col">
    
    <!-- HEADER -->
    <header class="bg-slate-900 border-b border-slate-700/40 px-2 md:px-3 h-13 flex items-center justify-between gap-1.5 md:gap-2 no-print flex-shrink-0" style="height:48px">
        <!-- Left: brand + branch + user -->
        <div class="flex items-center gap-2 min-w-0">
            <!-- Brand badge: icon only on xs, full on sm+ -->
            <button onclick="goHome()" class="flex items-center gap-2 pl-1 pr-2 sm:pr-3 py-1 rounded-xl bg-gradient-to-r from-amber-500/15 to-transparent border border-amber-500/20 hover:border-amber-500/40 transition-colors shrink-0 group" aria-label="Go to Home">
                <div class="w-7 h-7 rounded-lg bg-amber-500 flex items-center justify-center shadow-lg shadow-amber-500/30">
                    <i class="fas fa-store text-slate-900 text-xs"></i>
                </div>
                <div class="hidden sm:block leading-none">
                    <span class="text-white font-bold text-sm block truncate max-w-[110px] group-hover:text-amber-400 transition-colors"><?php echo htmlspecialchars($settings['company_name'] ?? 'POS'); ?></span>
                    <span class="text-amber-500/60 text-[9px] uppercase tracking-widest font-medium"><?php echo htmlspecialchars($business_type_config['name'] ?? 'Retail'); ?></span>
                </div>
            </button>
            <div class="hidden sm:block w-px h-5 bg-slate-700/60 shrink-0"></div>
            <?php if (count($branches) > 1): ?>
            <div class="hidden sm:block relative">
                <i class="fas fa-map-marker-alt absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-[10px]"></i>
                <select id="branchSelect" name="branch_id" class="pl-7 pr-5 py-1 bg-slate-800 border border-slate-700/60 rounded-lg text-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-amber-500" aria-label="Select Branch">
                    <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo $branch['id']; ?>" <?php echo $branch['id'] == $current_branch['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="hidden md:flex items-center gap-1.5 px-2 py-1 bg-slate-800/60 rounded-lg">
                <i class="fas fa-map-marker-alt text-slate-600 text-[9px]"></i>
                <span class="text-slate-400 text-xs"><?php echo htmlspecialchars($current_branch['name']); ?></span>
            </div>
            <?php endif; ?>
            <div class="hidden lg:flex items-center gap-1.5 px-2 py-1 bg-slate-800/60 rounded-lg">
                <div class="w-4 h-4 rounded-full bg-slate-700 flex items-center justify-center">
                    <i class="fas fa-user text-slate-400 text-[8px]"></i>
                </div>
                <span class="text-slate-400 text-xs truncate max-w-[90px]"><?php echo htmlspecialchars($current_user_name); ?></span>
            </div>
        </div>
        <!-- Center: live clock -->
        <div class="hidden lg:flex flex-col items-center">
            <span class="text-white font-mono font-bold text-sm leading-none" id="liveClock">--:--</span>
            <span class="text-slate-600 text-[9px] font-mono" id="liveDate"></span>
        </div>
        <!-- Right: actions -->
        <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
            <!-- Today stats (md+) -->
            <div class="hidden md:flex items-center gap-2 px-2.5 py-1 bg-slate-800/60 border border-slate-700/40 rounded-lg cursor-default">
                <div class="flex flex-col items-end">
                    <span class="text-amber-400 font-bold text-xs font-mono leading-none" id="todaySalesValue"><?php echo $settings['currency']; ?> <?php echo number_format($today_stats['total'], 0); ?></span>
                    <span class="text-slate-600 text-[9px] leading-none mt-0.5"><span id="todaySalesCount"><?php echo (int)$today_stats['sale_count']; ?></span> sales today</span>
                </div>
            </div>
            <!-- Shift status -->
            <button onclick="showModal('shiftModal'); <?php echo $current_shift ? 'refreshShiftSummary();' : ''; ?>" class="flex items-center gap-1 sm:gap-1.5 px-1.5 sm:px-2.5 py-1 rounded-lg text-xs transition-colors border <?php echo $current_shift ? 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' : 'bg-red-500/10 border-red-500/30 hover:bg-red-500/20'; ?>" aria-label="Shift Status">
                <span class="w-1.5 h-1.5 rounded-full <?php echo $current_shift ? 'bg-emerald-400 animate-pulse' : 'bg-red-400'; ?>"></span>
                <span class="hidden xs:inline <?php echo $current_shift ? 'text-emerald-400' : 'text-red-400'; ?> font-medium"><?php echo $current_shift ? 'Open' : 'Closed'; ?></span>
            </button>
            <!-- Held sales (hidden on mobile — in bottom nav) -->
            <button onclick="showModal('heldModal'); loadHeldSales();" class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 bg-slate-800 border border-slate-700/60 rounded-lg text-xs text-slate-300 hover:bg-slate-700 transition-colors" aria-label="Held Sales">
                <i class="fas fa-layer-group text-slate-400 text-[10px]"></i>
                <span>Held</span>
                <span id="heldCountBadge" class="bg-amber-500 text-slate-900 text-[9px] font-bold px-1.5 py-0.5 rounded-full leading-none<?php echo $held_count > 0 ? '' : ' hidden'; ?>"><?php echo $held_count; ?></span>
            </button>
            <div class="hidden sm:block w-px h-5 bg-slate-700/60 shrink-0"></div>
            <button onclick="showModal('customerModal')" class="hidden sm:flex w-8 h-8 items-center justify-center rounded-lg bg-slate-800 border border-slate-700/60 text-slate-400 hover:bg-slate-700 hover:text-amber-400 transition-colors" aria-label="Customer"><i class="fas fa-user text-xs"></i></button>
            <button onclick="openCalculator()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-800 border border-slate-700/60 text-slate-400 hover:bg-slate-700 hover:text-amber-400 transition-colors" aria-label="Calculator"><i class="fas fa-calculator text-xs"></i></button>
            <button onclick="viewLastSale()" class="hidden md:flex w-8 h-8 items-center justify-center rounded-lg bg-slate-800 border border-slate-700/60 text-slate-400 hover:bg-slate-700 hover:text-amber-400 transition-colors" aria-label="Last Receipt" title="View last receipt"><i class="fas fa-receipt text-xs"></i></button>
            <button onclick="openPosHelp()" class="hidden md:flex w-8 h-8 items-center justify-center rounded-lg bg-slate-800 border border-slate-700/60 text-slate-400 hover:bg-slate-700 hover:text-amber-400 transition-colors" aria-label="Help"><i class="fas fa-question text-xs"></i></button>
            <!-- Quick User Switch (shared terminal) - Large touch button -->
            <button onclick="openQuickSwitch()" class="flex w-10 h-10 sm:w-8 sm:h-8 items-center justify-center rounded-xl sm:rounded-lg bg-slate-800 border border-slate-700/60 text-slate-400 hover:bg-amber-500/20 hover:text-amber-400 transition-all" aria-label="Switch User" title="Switch User">
                <i class="fas fa-user-friends text-sm sm:text-xs"></i>
            </button>
            <!-- Quick Logout button -->
            <a href="<?php echo base_url('auth/logout.php'); ?>" class="flex w-10 h-10 sm:w-8 sm:h-8 items-center justify-center rounded-xl sm:rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500/20 hover:text-red-300 transition-all" aria-label="Logout" title="Logout">
                <i class="fas fa-sign-out-alt text-sm sm:text-xs"></i>
            </a>
</div>
    </header>
    
    <!-- Quick Switch User - Traditional POS Terminal Style -->
    <div id="quickSwitchModal" class="fixed inset-0 z-[2000] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/90" onclick="closeModal('quickSwitchModal')"></div>
        <div class="relative bg-slate-900 rounded-xl w-full max-w-md border-t-4 border-amber-500 shadow-2xl">
            <!-- Logo/Header -->
            <div class="flex flex-col items-center pt-8 pb-6">
                <div class="w-20 h-20 rounded-xl bg-gradient-to-br from-amber-500 to-yellow-600 flex items-center justify-center mb-4">
                    <i class="fas fa-user-friends text-3xl text-slate-900"></i>
                </div>
                <h2 class="text-white font-bold text-2xl">Switch User</h2>
                <p class="text-slate-500 text-sm">Enter your credentials to continue</p>
            </div>
            
            <!-- PIN Display -->
            <div class="px-8 mb-6">
                <input type="password" id="quickSwitchPin" readonly placeholder="Enter PIN"
                       class="w-full h-20 bg-slate-800 border-2 border-amber-500/50 rounded-xl text-white text-4xl text-center font-bold tracking-widest focus:outline-none pin-display">
            </div>
            
            <!-- User ID Input -->
            <div class="px-8 mb-6">
                <input type="text" id="quickSwitchUser" placeholder="User ID" 
                       class="w-full h-16 px-4 bg-slate-800 border-2 border-slate-700 rounded-xl text-white text-2xl text-center font-medium focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                       inputmode="numeric" autocomplete="off">
            </div>
            
            <!-- Virtual Keypad Grid -->
            <div class="px-8 pb-8">
                <div class="grid grid-cols-3 gap-4">
                    <button type="button" class="keypad-btn h-20 bg-slate-800 rounded-xl text-white text-3xl font-bold border-2 border-slate-700 hover:bg-slate-700 active:bg-amber-500 active:text-slate-900 transition-all" data-key="1">1</button>
                    <button type="button" class="keypad-btn h-20 bg-slate-800 rounded-xl text-white text-3xl font-bold border-2 border-slate-700 hover:bg-slate-700 active:bg-amber-500 active:text-slate-900 transition-all" data-key="2">2</button>
                    <button type="button" class="keypad-btn h-20 bg-slate-800 rounded-xl text-white text-3xl font-bold border-2 border-slate-700 hover:bg-slate-700 active:bg-amber-500 active:text-slate-900 transition-all" data-key="3">3</button>
                    <button type="button" class="keypad-btn h-20 bg-slate-800 rounded-xl text-white text-3xl font-bold border-2 border-slate-700 hover:bg-slate-700 active:bg-amber-500 active:text-slate-900 transition-all" data-key="4">4</button>
                    <button type="button" class="keypad-btn h-20 bg-slate-800 rounded-xl text-white text-3xl font-bold border-2 border-slate-700 hover:bg-slate-700 active:bg-amber-500 active:text-slate-900 transition-all" data-key="5">5</button>
                    <button type="button" class="keypad-btn h-20 bg-slate-800 rounded-xl text-white text-3xl font-bold border-2 border-slate-700 hover:bg-slate-700 active:bg-amber-500 active:text-slate-900 transition-all" data-key="6">6</button>
                    <button type="button" class="keypad-btn h-20 bg-slate-800 rounded-xl text-white text-3xl font-bold border-2 border-slate-700 hover:bg-slate-700 active:bg-amber-500 active:text-slate-900 transition-all" data-key="7">7</button>
                    <button type="button" class="keypad-btn h-20 bg-slate-800 rounded-xl text-white text-3xl font-bold border-2 border-slate-700 hover:bg-slate-700 active:bg-amber-500 active:text-slate-900 transition-all" data-key="8">8</button>
                    <button type="button" class="keypad-btn h-20 bg-slate-800 rounded-xl text-white text-3xl font-bold border-2 border-slate-700 hover:bg-slate-700 active:bg-amber-500 active:text-slate-900 transition-all" data-key="9">9</button>
                    <button type="button" class="keypad-btn h-20 bg-red-500/20 rounded-xl text-red-400 text-2xl font-bold border-2 border-red-500/30 hover:bg-red-500/40 active:bg-red-500 active:text-white transition-all" data-key="clear">
                        <i class="fas fa-backspace"></i>
                    </button>
                    <button type="button" class="keypad-btn h-20 bg-slate-800 rounded-xl text-white text-3xl font-bold border-2 border-slate-700 hover:bg-slate-700 active:bg-amber-500 active:text-slate-900 transition-all" data-key="0">0</button>
                    <button type="button" class="keypad-btn h-20 bg-emerald-500/20 rounded-xl text-emerald-400 text-2xl font-bold border-2 border-emerald-500/30 hover:bg-emerald-500/40 active:bg-emerald-500 active:text-white transition-all" data-key="enter">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
                <p class="text-slate-500 text-xs text-center mt-4">Cart will be cleared when switching user</p>
            </div>
        </div>
    </div>
    
    <script>
    // Virtual keypad for PIN entry
    document.addEventListener('DOMContentLoaded', function() {
        const pinInput = document.getElementById('quickSwitchPin');
        document.querySelectorAll('.keypad-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const key = this.dataset.key;
                if (key === 'clear') {
                    pinInput.value = '';
                } else if (key === 'enter') {
                    performQuickSwitch();
                } else if (pinInput.value.length < 6) {
                    pinInput.value += key;
                }
            });
        });
    });
    </script>
    
    <!-- MAIN CONTENT -->
<div class="flex-1 overflow-hidden p-1.5 md:p-3">
    <div class="h-full flex gap-1.5 md:gap-3">
        
        <!-- LEFT COLUMN -->
        <div class="flex-1 flex flex-col overflow-hidden min-w-0 gap-2">

            <!-- Row 1: Categories + Tags -->
            <div class="flex-shrink-0">
                <div class="flex items-center gap-1 md:gap-1.5 overflow-x-auto pb-0.5 scrollbar-thin">
                    <button class="category-btn px-3 py-1.5 rounded-lg bg-slate-700 text-white text-xs whitespace-nowrap font-medium" data-category="all" onclick="filterCategory('all', this)">
                        <i class="fas fa-th-large mr-1"></i>All
                    </button>
                    <?php foreach ($categories as $cat): ?>
                    <button class="category-btn px-3 py-1.5 rounded-lg bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white transition-colors text-xs whitespace-nowrap" data-category="<?php echo $cat['id']; ?>" onclick="filterCategory('<?php echo $cat['id']; ?>', this)">
                        <i class="fas <?php echo htmlspecialchars($cat['icon'] ?? 'fa-tag'); ?> mr-1"></i><?php echo htmlspecialchars($cat['name']); ?>
                    </button>
                    <?php endforeach; ?>
                    <?php if (!empty($availableTags)): ?>
                    <div class="w-px h-4 bg-slate-700 mx-0.5 shrink-0"></div>
                    <?php foreach ($availableTags as $tag): ?>
                    <button class="tag-filter-btn px-2.5 py-1 rounded-full border transition-colors text-[10px] whitespace-nowrap" data-tag-id="<?php echo $tag['id']; ?>" onclick="filterTag(<?php echo $tag['id']; ?>, this)" style="border-color:<?php echo $tag['color']; ?>40;background:<?php echo $tag['color']; ?>15;color:<?php echo $tag['color']; ?>">
                        <i class="fas <?php echo htmlspecialchars($tag['icon'] ?: 'fa-tag'); ?> mr-1"></i><?php echo htmlspecialchars($tag['name']); ?>
                    </button>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Row 2: Order Types + Business Features + Search + Reports (single toolbar) -->
            <div class="flex-shrink-0">
                <div class="toolbar-row flex items-center gap-1 md:gap-1.5 overflow-x-auto scrollbar-thin bg-slate-800/40 border border-slate-700/40 rounded-lg px-2 md:px-2.5 py-1.5">
                    <!-- Order Types -->
                    <?php
                        $ot_colors = [
                            'walk_in'   => 'text-slate-300 border-slate-600 hover:border-slate-400 data-active:bg-slate-700 data-active:text-white',
                            'delivery'  => 'text-blue-400 border-blue-500/30 hover:border-blue-400',
                            'wholesale' => 'text-violet-400 border-violet-500/30 hover:border-violet-400',
                            'dine_in'   => 'text-emerald-400 border-emerald-500/30 hover:border-emerald-400',
                            'takeaway'  => 'text-amber-400 border-amber-500/30 hover:border-amber-400',
                        ];
                        $ot_active = [
                            'walk_in'   => 'ot-active-slate',
                            'delivery'  => 'ot-active-blue',
                            'wholesale' => 'ot-active-violet',
                            'dine_in'   => 'ot-active-emerald',
                            'takeaway'  => 'ot-active-amber',
                        ];
                        $ot_index = 0;
                    ?>
                    <?php foreach ($order_types as $key => $ot): ?>
                    <?php
                        $color_class = $ot_colors[$key] ?? 'text-slate-300 border-slate-600 hover:border-slate-400';
                        $active_class = $ot_active[$key] ?? 'ot-active-slate';
                        $is_first = $ot_index === 0;
                        $ot_index++;
                    ?>
                    <button class="order-type-btn px-2.5 py-1 bg-slate-900/60 border rounded-lg text-xs transition-all whitespace-nowrap shrink-0 <?php echo $color_class; ?> <?php echo $active_class; ?> <?php echo $is_first ? 'ring-1 ring-current opacity-100' : 'opacity-60 hover:opacity-100'; ?>" data-order-type="<?php echo $key; ?>" onclick="setOrderType('<?php echo $key; ?>', this)">
                        <i class="fas <?php echo htmlspecialchars($ot['icon']); ?> mr-1"></i><?php echo htmlspecialchars($ot['label']); ?>
                    </button>
                    <?php endforeach; ?>
                    <!-- Divider -->
                    <?php $has_bt_features = !empty(array_filter($bt_features)); ?>
                    <?php if ($has_bt_features): ?>
                    <div class="w-px h-4 bg-slate-700 mx-0.5 shrink-0"></div>
                    <!-- Business Features -->
                    <?php if (!empty($bt_features['prescription'])): ?>
                    <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                        <i class="fas fa-file-medical text-amber-400 text-xs"></i>
                        <span class="text-slate-300 text-[11px]">Rx</span>
                        <label class="relative inline-flex items-center cursor-pointer ml-1">
                            <input type="checkbox" id="prescriptionToggle" class="sr-only peer" onchange="togglePrescription(this.checked)">
                            <div class="w-8 h-4 bg-slate-600 rounded-full peer-checked:bg-amber-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                        </label>
                    </div>
                    <div id="prescriptionFields" class="hidden items-center gap-1">
                        <input type="text" id="prescriptionRef" name="prescription_ref" placeholder="#" autocomplete="off" class="w-20 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                        <input type="text" id="prescriptionDoctor" name="prescription_doctor" placeholder="Dr" autocomplete="off" class="w-24 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                        <input type="text" id="prescriptionNotes" name="prescription_notes" placeholder="Notes" autocomplete="off" class="w-28 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bt_features['batch_number'])): ?>
                    <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                        <i class="fas fa-barcode text-amber-400 text-xs"></i>
                        <input type="text" id="batchNumber" name="batch_number" placeholder="Batch #" autocomplete="off" class="w-24 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bt_features['table'])): ?>
                    <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                        <i class="fas fa-chair text-amber-400 text-xs"></i>
                        <input type="number" id="tableNumber" name="table_number" placeholder="Table #" autocomplete="off" class="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                        <button onclick="applyTableNumber()" class="px-2 py-1 bg-amber-500/20 rounded text-amber-400 text-xs hover:bg-amber-500/30">Set</button>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bt_features['kitchen_notes'])): ?>
                    <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                        <i class="fas fa-utensils text-amber-400 text-xs"></i>
                        <input type="text" id="kitchenNotes" name="kitchen_notes" placeholder="Kitchen notes" autocomplete="off" class="w-32 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bt_features['room_charge'])): ?>
                    <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                        <i class="fas fa-bed text-amber-400 text-xs"></i>
                        <input type="text" id="roomNumber" name="room_number" list="recentRooms" placeholder="Room" autocomplete="off" class="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                        <input type="text" id="guestName" name="guest_name" list="recentGuests" placeholder="Guest" autocomplete="off" class="w-24 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bt_features['weight'])): ?>
                    <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                        <i class="fas fa-weight text-amber-400 text-xs"></i>
                        <input type="number" id="weightInput" name="weight_input" step="0.01" placeholder="0.00" autocomplete="off" class="w-20 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                        <select id="weightUnit" class="w-12 px-1 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                            <option value="kg">kg</option><option value="g">g</option><option value="lb">lb</option>
                        </select>
                        <button onclick="applyWeight()" class="px-2 py-1 bg-amber-500/20 rounded text-amber-400 text-xs hover:bg-amber-500/30">Apply</button>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bt_features['age_verify'])): ?>
                    <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                        <i class="fas fa-id-card text-amber-400 text-xs"></i>
                        <span class="text-slate-300 text-[11px]">Age</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="ageVerifyToggle" class="sr-only peer" onchange="toggleAgeVerify(this.checked)">
                            <div class="w-8 h-4 bg-slate-600 rounded-full peer-checked:bg-amber-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                        </label>
                        <input type="text" id="ageVerifyId" placeholder="ID #" class="hidden w-24 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bt_features['bulk'])): ?>
                    <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                        <i class="fas fa-layer-group text-amber-400 text-xs"></i>
                        <select id="wholesaleTier" class="px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs" onchange="setWholesaleTier(this.value)">
                            <?php foreach ($wholesale_tiers as $tier): ?>
                            <option value="<?php echo htmlspecialchars($tier['value']); ?>"><?php echo htmlspecialchars($tier['label']); ?><?php echo $tier['discount'] > 0 ? ' (' . $tier['discount'] . '%)' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bt_features['staff_select'])): ?>
                    <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                        <i class="fas fa-user-tie text-amber-400 text-xs"></i>
                        <select id="staffSelect" class="px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                            <option value="">Staff</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bt_features['appointment'])): ?>
                    <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                        <i class="fas fa-calendar-check text-amber-400 text-xs"></i>
                        <select id="appointmentSelect" class="px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs" onchange="setAppointment(this.value)">
                            <option value="">Appt</option>
                            <?php foreach ($today_appointments as $ap): ?>
                            <option value="<?php echo (int) $ap['id']; ?>"><?php echo date('H:i', strtotime($ap['appointment_time'])); ?> <?php echo htmlspecialchars(substr($ap['customer_name'] ?? '', 0, 15)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bt_features['serial']) || !empty($bt_features['warranty'])): ?>
                    <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                        <i class="fas fa-microchip text-amber-400 text-xs"></i>
                        <input type="text" id="serialNumber" name="serial_number" placeholder="Serial" autocomplete="off" class="w-28 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                        <select id="warrantyPeriod" class="w-20 px-1 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                            <?php foreach ($warranty_periods as $wp): ?>
                            <option value="<?php echo htmlspecialchars($wp['value']); ?>"><?php echo htmlspecialchars($wp['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <!-- Divider before search -->
                    <div class="w-px h-4 bg-slate-700 mx-0.5 shrink-0"></div>
                    <!-- Search -->
                    <div class="relative shrink-0">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="text" id="searchBox" name="search" placeholder="Search products… (F1)" autocomplete="off" class="w-48 pl-8 pr-3 py-1 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500 placeholder-slate-500">
                    </div>
                    <!-- Refresh Products Button -->
                    <button onclick="refreshProducts()" id="refreshBtn" class="px-2 py-1 bg-blue-500/10 border border-blue-500/30 rounded-lg text-blue-400 text-xs hover:bg-blue-500/20 transition-colors whitespace-nowrap shrink-0" title="Refresh products (clears cache)">
                        <i class="fas fa-sync-alt mr-1" id="refreshIcon"></i>Refresh
                    </button>
                    <!-- Loading Spinner (hidden by default) -->
                    <div id="productsLoading" class="hidden shrink-0">
                        <i class="fas fa-spinner fa-spin text-amber-400 text-xs"></i>
                    </div>
                    <!-- Reports -->
                    <button onclick="showModal('reportsModal'); loadReports();" class="px-2.5 py-1 bg-amber-500/10 border border-amber-500/30 rounded-lg text-amber-400 text-xs font-medium hover:bg-amber-500/20 transition-colors whitespace-nowrap shrink-0 ml-auto">
                        <i class="fas fa-chart-bar mr-1"></i>Reports
                    </button>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="flex-1 overflow-y-auto scrollbar-thin min-h-0">
                <!-- Error Message Container -->
                <div id="productsError" class="hidden p-4 text-center">
                    <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-2"></i>
                    <p class="text-red-400 text-sm">Failed to load products. <button onclick="refreshProducts()" class="text-blue-400 underline">Try again</button></p>
                </div>
                <!-- Empty State -->
                <div id="productsEmpty" class="hidden p-8 text-center">
                    <i class="fas fa-box-open text-slate-600 text-3xl mb-3"></i>
                    <p class="text-slate-500 text-sm">No products found</p>
                    <?php if (is_super_admin() || $user_role === 'Admin'): ?>
                    <p class="text-slate-600 text-xs mt-2"><a href="../products/products.php" class="text-blue-400 hover:underline">Add products</a> or <button onclick="refreshProducts()" class="text-blue-400 hover:underline">refresh</button></p>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 2xl:grid-cols-9 gap-1" id="productsGrid">
                    <?php foreach ($products as $p):
                        $stock   = (int)($p['stock'] ?? 999);
                        $isOOS   = ($stock <= 0);
                        $isLow   = !$isOOS && $stock <= 5;
                        $imgPath = !empty($p['image']) ? base_url(ltrim($p['image'], '/')) : '';

                        if ($isOOS) {
                            $pcClass   = 'pc-oos';
                            $dotClass  = 's-dot s-dot-red';
                            $dotTitle  = 'Out of stock';
                            $priceClass = 's-price-red';
                            $nameClass  = 's-name-muted';
                            $barClass   = 's-bar s-bar-red';
                            $priceText  = 'Out of Stock';
                        } elseif ($isLow) {
                            $pcClass   = 'pc-low';
                            $dotClass  = 's-dot s-dot-amber';
                            $dotTitle  = "Only {$stock} left";
                            $priceClass = 's-price-amber';
                            $nameClass  = '';
                            $barClass   = 's-bar s-bar-amber';
                            $priceText  = $settings['currency'] . ' ' . number_format($p['price'], 0);
                        } else {
                            $pcClass   = 'pc-in';
                            $dotClass  = 's-dot s-dot-green';
                            $dotTitle  = "In stock ({$stock})";
                            $priceClass = 's-price-green';
                            $nameClass  = '';
                            $barClass   = 's-bar s-bar-green';
                            $priceText  = $settings['currency'] . ' ' . number_format($p['price'], 0);
                        }
                    ?>
                    <div class="product-card aspect-square bg-slate-800 rounded-lg relative select-none overflow-hidden flex flex-col <?php echo $pcClass; ?>"
                         data-product-id="<?php echo $p['id']; ?>"
                         data-product-name="<?php echo htmlspecialchars($p['name']); ?>"
                         data-product-price="<?php echo $p['price']; ?>"
                         data-product-stock="<?php echo $stock; ?>"
                         data-product-image="<?php echo htmlspecialchars($imgPath); ?>"
                         data-category-id="<?php echo $p['category_id'] ?? ''; ?>"
                         <?php if (!$isOOS): ?>onclick="addToCart(this)" role="button" tabindex="0"<?php else: ?>role="img" tabindex="-1"<?php endif; ?>>

                        <!-- Stock status dot -->
                        <span class="<?php echo $dotClass; ?>" title="<?php echo $dotTitle; ?>"></span>

                        <!-- Image / icon area -->
                        <div class="s-img flex-1 relative flex items-center justify-center overflow-hidden">
                            <?php if (!empty($imgPath)): ?>
                            <img src="<?php echo htmlspecialchars($imgPath); ?>" class="absolute inset-0 w-full h-full object-cover opacity-80" alt="">
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/10 to-transparent"></div>
                            <?php else: ?>
                            <div class="w-6 h-6 rounded-full bg-slate-700 flex items-center justify-center">
                                <i class="fas fa-box text-slate-500 text-xs"></i>
                            </div>
                            <?php endif; ?>
                            <?php if ($isOOS): ?>
                            <div class="s-stripe"></div>
                            <div class="s-pill"><span>Out of Stock</span></div>
                            <?php elseif ($isLow): ?>
                            <span class="s-chip">Only <?php echo $stock; ?> left</span>
                            <?php endif; ?>
                        </div>

                        <!-- Info bar -->
                        <div class="px-1 pt-0.5 pb-0.5 flex-shrink-0 bg-slate-900 <?php echo $barClass; ?> <?php echo $nameClass; ?>">
                            <div class="text-[9px] font-semibold truncate leading-tight <?php echo $nameClass; ?>" style="color:<?php echo $isOOS ? '#64748b' : '#f1f5f9'; ?>"><?php echo htmlspecialchars($p['name']); ?></div>
                            <div class="font-bold text-[9px] leading-tight <?php echo $priceClass; ?>"><?php echo $priceText; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- RIGHT COLUMN - Cart Panel (Receipt Style) -->
        <div class="cart-panel w-64 lg:w-72 xl:w-80 flex-shrink-0 bg-slate-950 border border-slate-800 rounded-xl flex flex-col overflow-hidden shadow-2xl h-full">
            <!-- Receipt Header -->
            <div class="px-4 pt-3 pb-2 border-b border-dashed border-slate-700/60">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-[10px] text-slate-500 uppercase tracking-widest font-medium">Order</p>
                        <h3 class="text-white font-bold text-base leading-tight font-mono" id="orderNumberDisplay">#—</h3>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] text-slate-500 font-mono" id="cartItemCount">0 items</span>
                        <button onclick="clearCart()" class="w-7 h-7 rounded-lg bg-slate-800 text-slate-500 hover:bg-red-500/20 hover:text-red-400 transition-colors" title="Clear order">
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </div>
                </div>
                <!-- Customer badge if set -->
                <div id="cartCustomerBadge" class="hidden mt-1.5 flex items-center gap-1 text-[10px] text-amber-400">
                    <i class="fas fa-user-circle"></i>
                    <span id="cartCustomerName">Walk-in</span>
                </div>
            </div>

            <!-- Cart Items -->
            <div class="flex-1 overflow-y-auto px-3 py-2 space-y-px scrollbar-thin min-h-0" id="cartItems">
                <div class="flex flex-col items-center justify-center h-full py-8 gap-2">
                    <div class="w-14 h-14 rounded-full bg-slate-800 flex items-center justify-center">
                        <i class="fas fa-receipt text-slate-600 text-xl"></i>
                    </div>
                    <p class="text-slate-500 text-xs text-center">No items yet<br><span class="text-slate-600">Tap a product to add</span></p>
                </div>
            </div>

            <!-- AI Strip -->
            <div id="aiSuggestionsStrip" class="hidden px-3 pb-1">
                <div class="flex items-center gap-1 mb-1"><i class="fas fa-robot text-amber-400 text-[9px]"></i><span class="text-[9px] text-slate-500 uppercase tracking-wider" id="aiStripReason">Suggestions</span></div>
                <div class="flex gap-1.5 overflow-x-auto pb-1" id="aiStripProducts"></div>
            </div>
            <!-- Dashed separator -->
            <div class="receipt-dashes mx-3"></div>

            <!-- Voucher -->
            <div class="px-3 pt-2 pb-1">
                <div class="flex gap-1.5">
                    <input type="text" id="voucherInput" name="voucher" placeholder="Voucher / promo code" autocomplete="off" class="flex-1 px-2 py-1.5 bg-slate-900 border border-slate-700/60 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500 placeholder-slate-600">
                    <button onclick="applyVoucher()" class="px-2.5 py-1.5 bg-slate-800 rounded-lg text-amber-400 text-xs font-medium hover:bg-slate-700 transition-colors border border-slate-700/60">Apply</button>
                </div>
            </div>

            <!-- Totals (receipt style) -->
            <div class="px-3 pb-2 space-y-0.5">
                <div class="p-2 bg-slate-800/40 rounded-lg space-y-1">
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-500 font-mono">Subtotal</span>
                        <span class="text-slate-300 font-mono" id="cartSubtotalDisplay"><?php echo $settings['currency']; ?> 0.00</span>
                    </div>
                    <div id="discountDisplayRow" class="hidden flex justify-between text-xs">
                        <span class="text-slate-500 font-mono">Discount</span>
                        <span class="text-emerald-400 font-mono" id="cartDiscountDisplay">-<?php echo $settings['currency']; ?> 0.00</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-500 font-mono">Tax <?php echo $tax_rate; ?>%</span>
                        <span class="text-slate-300 font-mono" id="cartTaxDisplay"><?php echo $settings['currency']; ?> 0.00</span>
                    </div>
                    <div class="receipt-dashes my-1"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-white font-bold text-xs font-mono uppercase tracking-wider">Total</span>
                        <span class="text-amber-400 text-lg font-bold font-mono" id="paymentTotal"><?php echo $settings['currency']; ?> 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="px-3 pb-3 flex gap-2">
                <button onclick="holdSale()" class="flex-1 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-xs font-medium hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas fa-pause mr-1"></i>Hold
                </button>
                <button onclick="showPaymentModal()" id="payBtn" disabled class="flex-2 w-full flex-1 py-2.5 bg-amber-500 rounded-lg text-slate-900 text-sm font-bold hover:bg-amber-400 transition-colors disabled:opacity-30 disabled:cursor-not-allowed shadow-lg shadow-amber-500/20">
                    <i class="fas fa-arrow-right mr-1"></i>Charge
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Cart drawer backdrop (mobile) -->
<div class="cart-backdrop mobile-only" id="cartBackdrop" onclick="togglePosCart()"></div>

<!-- Mobile Bottom Nav -->
<nav class="mobile-bottom-nav mobile-only" aria-label="Mobile navigation">
    <button onclick="togglePosCart()" class="flex-1 flex flex-col items-center justify-center gap-0.5 text-slate-400 hover:text-amber-400 transition-colors relative">
        <i class="fas fa-shopping-cart text-base"></i>
        <span class="text-[9px] font-medium">Cart</span>
        <span id="posCartFabBadge" class="absolute top-1.5 right-1/4 translate-x-1/2 bg-amber-500 text-slate-900 text-[8px] font-bold px-1 py-px rounded-full leading-none hidden">0</span>
    </button>
    <button onclick="showModal('customerModal')" class="flex-1 flex flex-col items-center justify-center gap-0.5 text-slate-400 hover:text-amber-400 transition-colors">
        <i class="fas fa-user text-base"></i>
        <span class="text-[9px] font-medium">Customer</span>
    </button>
    <button onclick="showModal('heldModal'); loadHeldSales();" class="flex-1 flex flex-col items-center justify-center gap-0.5 text-slate-400 hover:text-amber-400 transition-colors relative">
        <i class="fas fa-layer-group text-base"></i>
        <span class="text-[9px] font-medium">Held</span>
        <span id="mobileHeldBadge" class="absolute top-1.5 right-1/4 translate-x-1/2 bg-amber-500 text-slate-900 text-[8px] font-bold px-1 py-px rounded-full leading-none <?php echo $held_count > 0 ? '' : 'hidden'; ?>"><?php echo $held_count; ?></span>
    </button>
    <button onclick="showPaymentModal()" class="flex-1 flex flex-col items-center justify-center gap-0.5 text-amber-400 hover:text-amber-300 transition-colors">
        <i class="fas fa-credit-card text-base"></i>
        <span class="text-[9px] font-medium">Charge</span>
    </button>
    <button onclick="showModal('reportsModal'); loadReports();" class="flex-1 flex flex-col items-center justify-center gap-0.5 text-slate-400 hover:text-amber-400 transition-colors">
        <i class="fas fa-chart-bar text-base"></i>
        <span class="text-[9px] font-medium">Reports</span>
    </button>
</nav>

<!-- Toast Container -->
<div class="fixed bottom-5 right-5 z-[2000] flex flex-col gap-2" id="toastContainer"></div>

<!-- ============================================ -->
<!-- MODALS - All modals with proper Tailwind classes -->
<!-- ============================================ -->

<!-- SHIFT MODAL -->
<div id="shiftModal" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-[1000] p-2 pb-[72px] sm:p-0 sm:pb-0">
    <div class="modal-inner bg-slate-800 rounded-xl max-w-md w-full mx-2 sm:mx-4 shadow-2xl max-h-[92vh] overflow-y-auto scrollbar-thin">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-700">
            <h3 class="text-white font-semibold"><i class="fas fa-clock mr-2 text-amber-400"></i>Register Session</h3>
            <button onclick="closeModal('shiftModal')" class="text-slate-400 hover:text-white text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="p-4" id="shiftContent">
            <?php if (!$current_shift): ?>
            <div class="space-y-4">
                <div>
                    <label for="openingCash" class="text-slate-300 text-sm block mb-1.5">Opening Cash Amount</label>
                    <input type="number" id="openingCash" name="opening_cash" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white" value="0" step="0.01" aria-label="Opening Cash Amount">
                </div>
                <button onclick="openShift()" class="w-full py-2 bg-amber-500 text-slate-900 rounded-lg font-semibold hover:bg-amber-400 transition-colors" aria-label="Open Shift">Open Shift</button>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <!-- Shift Summary -->
                <div class="bg-slate-700/50 rounded-lg p-3 space-y-1.5">
                    <div class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Shift Summary</div>
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Opened:</span><span class="text-white"><?php echo date('d M Y H:i', strtotime($current_shift['opened_at'])); ?></span></div>
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Opening cash:</span><span class="text-white"><?php echo $settings['currency']; ?> <?php echo number_format($current_shift['opening_cash'], 2); ?></span></div>
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Total sales:</span><span class="text-amber-400 font-semibold"><?php echo $settings['currency']; ?> <span id="shiftTotalSales"><?php echo number_format($current_shift['total_sales'] ?? 0, 2); ?></span></span></div>
                    <div class="flex justify-between text-sm" id="shiftSaleCountRow"><span class="text-slate-400">Transactions:</span><span class="text-white" id="shiftSaleCount"><?php echo number_format($current_shift['sale_count'] ?? $today_stats['sale_count'] ?? 0); ?></span></div>
                    <!-- Live breakdown loaded on open -->
                    <div id="shiftPaymentBreakdown" class="pt-1 space-y-0.5 border-t border-slate-600/60 hidden">
                        <div class="text-slate-500 text-[10px] uppercase tracking-wider">By Payment Method</div>
                    </div>
                </div>
                <!-- Expected vs Actual -->
                <div class="bg-slate-800/60 rounded-lg p-3 space-y-1.5">
                    <div class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Cash Reconciliation</div>
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Opening cash:</span><span class="text-white"><?php echo $settings['currency']; ?> <?php echo number_format($current_shift['opening_cash'], 2); ?></span></div>
                    <div class="flex justify-between text-sm"><span class="text-slate-400">+ Cash sales:</span><span class="text-white"><?php echo $settings['currency']; ?> <span id="shiftCashSales"><?php echo number_format($today_stats['cash'] ?? 0, 2); ?></span></span></div>
                    <div class="flex justify-between text-sm font-semibold border-t border-slate-600/60 pt-1"><span class="text-slate-300">Expected in drawer:</span><span class="text-amber-400"><?php echo $settings['currency']; ?> <span id="shiftExpectedCash"><?php echo number_format(($current_shift['opening_cash'] ?? 0) + ($today_stats['cash'] ?? 0), 2); ?></span></span></div>
                </div>
                <div>
                    <label for="closingCash" class="text-slate-300 text-sm block mb-1.5">Actual Closing Cash</label>
                    <input type="number" id="closingCash" name="closing_cash" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white" step="0.01" placeholder="Count your drawer..." aria-label="Closing Cash Amount" oninput="calcCashVariance()">
                </div>
                <div id="cashVarianceRow" class="hidden flex justify-between text-sm p-2 rounded-lg">
                    <span class="text-slate-400">Variance:</span><span id="cashVarianceAmt" class="font-semibold"></span>
                </div>
                <div>
                    <label for="closingNotes" class="text-slate-300 text-sm block mb-1.5">Notes (Optional)</label>
                    <textarea id="closingNotes" name="closing_notes" rows="2" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white" aria-label="Closing Notes"></textarea>
                </div>
                <button onclick="closeShift()" class="w-full py-2 bg-red-500 text-white rounded-lg font-semibold hover:bg-red-400 transition-all" aria-label="Close Shift"><i class="fas fa-door-open mr-1"></i>Close Shift</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- CUSTOMER MODAL -->
<div id="customerModal" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-[1000] p-2 pb-[72px] sm:p-0 sm:pb-0">
    <div class="modal-inner bg-slate-800 rounded-xl max-w-md w-full mx-2 sm:mx-4 shadow-2xl max-h-[92vh] overflow-y-auto scrollbar-thin">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-700">
            <h3 class="text-white font-semibold"><i class="fas fa-user mr-2 text-amber-400"></i>Customer</h3>
            <button onclick="closeModal('customerModal')" class="text-slate-400 hover:text-white text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="p-4 space-y-3">
            <div>
                <label for="customerPhone" class="text-slate-300 text-sm block mb-1.5">Phone Number</label>
                <input type="tel" id="customerPhone" name="customer_phone" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white" placeholder="0712345678" aria-label="Customer Phone">
            </div>
            <button onclick="searchCustomer()" class="w-full py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-600 transition-colors" aria-label="Search Customer">Search</button>
            <div id="customerInfo" class="hidden bg-slate-700/50 rounded-lg p-3 space-y-1.5">
                <div class="flex justify-between text-sm"><span class="text-slate-400">Name:</span><span class="text-white" id="customerName">-</span></div>
                <div class="flex justify-between text-sm"><span class="text-slate-400">Loyalty Points:</span><span class="text-amber-400" id="customerPoints">0</span></div>
                <div class="flex justify-between text-sm"><span class="text-slate-400">Credit Limit:</span><span class="text-white" id="customerBalance">0</span></div>
                <div class="flex justify-between text-sm"><span class="text-slate-400">Total Spent:</span><span class="text-white" id="customerTotalSpent">0</span></div>
            </div>
            <button onclick="saveCustomer()" class="w-full py-2 bg-amber-500 text-slate-900 rounded-lg font-semibold hover:bg-amber-400 transition-colors" aria-label="Save Customer">Use This Customer</button>
        </div>
    </div>
</div>

<!-- PAYMENT MODAL -->
<div id="paymentModal" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-[1000] p-2 sm:p-4 pb-[72px] sm:pb-4">
    <div class="modal-inner bg-slate-800 rounded-xl max-w-md w-full mx-0 sm:mx-4 shadow-2xl flex flex-col" style="max-height:min(calc(100vh - 80px),640px)">
        <div class="flex-shrink-0 bg-slate-800 z-10 flex justify-between items-center px-4 py-2.5 border-b border-slate-700 rounded-t-xl">
            <h3 class="text-white font-semibold"><i class="fas fa-credit-card mr-2 text-amber-400"></i>Payment</h3>
            <button onclick="closeModal('paymentModal')" class="text-slate-400 hover:text-white text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="flex-1 overflow-y-auto scrollbar-thin p-3 sm:p-4 space-y-2.5">
            <div class="flex justify-between items-baseline">
                <span class="text-slate-400 font-medium text-sm">Total Due</span>
                <span class="text-amber-400 text-xl font-bold" id="paymentTotal"><?php echo $settings['currency']; ?> 0</span>
            </div>
            <!-- Cart Summary -->
            <div class="p-3 bg-slate-700/30 rounded-lg space-y-1.5">
                <div class="flex justify-between text-xs text-slate-400"><span>Items</span><span id="modalItemCount" class="text-white font-medium">0</span></div>
                <div class="flex justify-between text-xs text-slate-400"><span>Subtotal</span><span id="modalSubtotalDisplay" class="text-white font-medium"><?php echo $settings['currency']; ?> 0</span></div>
                <div id="modalDiscountRow" class="hidden flex justify-between text-xs text-slate-400"><span>Discount</span><span id="modalDiscountDisplay" class="text-emerald-400 font-medium">-<?php echo $settings['currency']; ?> 0</span></div>
                <div class="flex justify-between text-xs text-slate-400"><span>Tax</span><span id="modalTaxDisplay" class="text-white font-medium"><?php echo $settings['currency']; ?> 0</span></div>
            </div>
            
            <!-- Payment Methods Grid -->
            <div class="grid grid-cols-3 gap-2">
                <?php foreach ($enabled_payment_methods as $idx => $pm): ?>
                <button class="payment-method-btn py-2 px-3 bg-slate-700 rounded-lg text-slate-300 text-sm hover:bg-slate-600 transition-colors <?php echo $idx === 0 ? 'ring-2 ring-amber-500 text-white' : ''; ?>" data-method="<?php echo $pm['id']; ?>" onclick="selectPaymentMethod('<?php echo $pm['id']; ?>')" aria-label="<?php echo $pm['label']; ?>">
                    <i class="fas <?php echo $pm['icon']; ?> mr-1"></i><?php echo $pm['label']; ?>
                </button>
                <?php endforeach; ?>
                <button class="payment-method-btn py-2 px-3 bg-slate-700 rounded-lg text-slate-300 text-sm hover:bg-slate-600 transition-colors" data-method="split" onclick="selectPaymentMethod('split')" aria-label="Split Payment">
                    <i class="fas fa-divide mr-1"></i>Split
                </button>
            </div>
            
            <!-- Split Payment Panel -->
            <div id="splitPaymentPanel" class="hidden p-3 bg-slate-700/50 rounded-lg space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-white text-sm font-medium"><i class="fas fa-divide mr-1"></i> Split Payments</span>
                    <button onclick="addSplitPayment()" class="text-amber-400 text-sm hover:text-amber-300">+ Add Payment</button>
                </div>
                <div id="splitPaymentsList" class="space-y-2">
                    <div class="space-y-2 p-2 bg-slate-800 rounded">
                        <select name="split_method_0" class="split-method w-full px-2 py-1 bg-slate-900 border border-slate-600 rounded text-sm text-white">
                            <?php foreach ($enabled_payment_methods as $pm): ?>
                            <option value="<?php echo htmlspecialchars($pm['id']); ?>"><?php echo htmlspecialchars($pm['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="split_amount_0" class="split-amount w-full px-2 py-1 bg-slate-900 border border-slate-600 rounded text-sm text-white" placeholder="Amount" autocomplete="off" onchange="calculateSplitTotal()">
                    </div>
                </div>
                <div class="flex justify-between text-sm pt-2 border-t border-slate-600">
                    <span class="text-slate-400">Split Total:</span>
                    <span class="text-amber-400 font-semibold" id="splitTotal"><?php echo $settings['currency']; ?> 0</span>
                </div>
            </div>
            
            <!-- Coupon Code -->
            <div class="p-2.5 bg-slate-700/40 border border-slate-600/60 rounded-lg">
                <div class="flex items-center gap-2">
                    <i class="fas fa-ticket text-amber-400 text-xs shrink-0"></i>
                    <input type="text" id="couponCodeInput" placeholder="Coupon code (optional)"
                           class="flex-1 px-2 py-1 bg-slate-800 border border-slate-600 rounded text-white text-xs placeholder-slate-500 uppercase tracking-widest focus:outline-none focus:ring-1 focus:ring-amber-500"
                           oninput="this.value = this.value.toUpperCase()"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();applyCouponCode();}">
                    <button onclick="applyCouponCode()" id="couponApplyBtn"
                            class="px-2 py-1 bg-amber-500/20 border border-amber-500/30 rounded text-amber-400 text-xs font-medium hover:bg-amber-500/30 transition-colors">
                        Apply
                    </button>
                    <button onclick="clearCoupon()" id="couponClearBtn" class="hidden px-2 py-1 bg-slate-600 rounded text-slate-300 text-xs hover:bg-slate-500 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="couponMsg" class="mt-1 text-[10px] hidden"></div>
            </div>

            <!-- Loyalty Points Redeem -->
            <div id="loyaltyRedeemRow" class="hidden p-2.5 bg-amber-500/10 border border-amber-500/20 rounded-lg">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-amber-400 text-xs font-medium"><i class="fas fa-star mr-1"></i>Loyalty Points: <span id="loyaltyPointsDisplay">0</span></span>
                    <span class="text-slate-400 text-xs">= <span id="loyaltyValueDisplay">0.00</span></span>
                </div>
                <div class="flex items-center gap-2">
                    <input type="number" id="loyaltyRedeemInput" min="0" step="1" placeholder="Points to redeem" class="flex-1 px-2 py-1 bg-slate-700 border border-slate-600 rounded text-white text-xs" oninput="applyLoyaltyRedeem()">
                    <button onclick="applyLoyaltyRedeem()" class="px-2 py-1 bg-amber-500 text-slate-900 rounded text-xs font-semibold hover:bg-amber-400">Apply</button>
                </div>
                <div id="loyaltyDiscountMsg" class="text-emerald-400 text-[10px] mt-1 hidden"></div>
            </div>

            <!-- Amount Received -->
            <div>
                <label for="amountReceived" class="text-slate-300 text-sm block mb-1.5">Amount Received</label>
                <input type="number" id="amountReceived" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white text-lg font-semibold" step="0.01" aria-label="Amount Received" oninput="calculateChange()">
            </div>
            
            <!-- Quick Cash Buttons -->
            <div class="grid grid-cols-4 gap-1.5">
                <button class="quick-cash-btn py-1.5 bg-slate-700 rounded text-white text-xs hover:bg-slate-600 transition-colors" data-amount="0">Exact</button>
                <button class="quick-cash-btn py-1.5 bg-slate-700 rounded text-white text-xs hover:bg-slate-600 transition-colors" data-amount="100">100</button>
                <button class="quick-cash-btn py-1.5 bg-slate-700 rounded text-white text-xs hover:bg-slate-600 transition-colors" data-amount="200">200</button>
                <button class="quick-cash-btn py-1.5 bg-slate-700 rounded text-white text-xs hover:bg-slate-600 transition-colors" data-amount="500">500</button>
                <button class="quick-cash-btn py-1.5 bg-slate-700 rounded text-white text-xs hover:bg-slate-600 transition-colors" data-amount="1000">1000</button>
                <button class="quick-cash-btn py-1.5 bg-slate-700 rounded text-white text-xs hover:bg-slate-600 transition-colors" data-amount="2000">2000</button>
                <button class="quick-cash-btn py-1.5 bg-slate-700 rounded text-white text-xs hover:bg-slate-600 transition-colors" data-amount="5000">5000</button>
                <button class="quick-cash-btn py-1.5 bg-slate-700 rounded text-white text-xs hover:bg-slate-600 transition-colors" data-amount="10000">10000</button>
            </div>
            
            <!-- Numeric Keypad -->
            <div class="grid grid-cols-3 gap-1">
                <button class="keypad-btn py-2 sm:py-3 bg-slate-700 rounded-lg text-white text-base sm:text-xl font-semibold hover:bg-slate-600 transition-colors" data-value="1">1</button>
                <button class="keypad-btn py-2 sm:py-3 bg-slate-700 rounded-lg text-white text-base sm:text-xl font-semibold hover:bg-slate-600 transition-colors" data-value="2">2</button>
                <button class="keypad-btn py-2 sm:py-3 bg-slate-700 rounded-lg text-white text-base sm:text-xl font-semibold hover:bg-slate-600 transition-colors" data-value="3">3</button>
                <button class="keypad-btn py-2 sm:py-3 bg-slate-700 rounded-lg text-white text-base sm:text-xl font-semibold hover:bg-slate-600 transition-colors" data-value="4">4</button>
                <button class="keypad-btn py-2 sm:py-3 bg-slate-700 rounded-lg text-white text-base sm:text-xl font-semibold hover:bg-slate-600 transition-colors" data-value="5">5</button>
                <button class="keypad-btn py-2 sm:py-3 bg-slate-700 rounded-lg text-white text-base sm:text-xl font-semibold hover:bg-slate-600 transition-colors" data-value="6">6</button>
                <button class="keypad-btn py-2 sm:py-3 bg-slate-700 rounded-lg text-white text-base sm:text-xl font-semibold hover:bg-slate-600 transition-colors" data-value="7">7</button>
                <button class="keypad-btn py-2 sm:py-3 bg-slate-700 rounded-lg text-white text-base sm:text-xl font-semibold hover:bg-slate-600 transition-colors" data-value="8">8</button>
                <button class="keypad-btn py-2 sm:py-3 bg-slate-700 rounded-lg text-white text-base sm:text-xl font-semibold hover:bg-slate-600 transition-colors" data-value="9">9</button>
                <button class="keypad-btn py-2 sm:py-3 bg-red-500/20 text-red-400 rounded-lg text-base sm:text-xl font-semibold hover:bg-red-500/30 transition-colors" data-value="C">C</button>
                <button class="keypad-btn py-2 sm:py-3 bg-slate-700 rounded-lg text-white text-base sm:text-xl font-semibold hover:bg-slate-600 transition-colors" data-value="0">0</button>
                <button class="keypad-btn py-2 sm:py-3 bg-slate-700 rounded-lg text-white text-base sm:text-xl font-semibold hover:bg-slate-600 transition-colors" data-value=".">.</button>
            </div>
            
            <!-- Change Display -->
            <div class="flex justify-between items-center pt-2 border-t border-slate-700">
                <span class="text-slate-400 font-medium text-sm">Change</span>
                <div class="flex items-center gap-1">
                    <span class="text-emerald-400 font-bold text-base" id="changeAmount"><?php echo $settings['currency']; ?> 0</span>
                    <span id="changeIndicator"></span>
                </div>
            </div>
        </div>

        <!-- Action Buttons — sticky at bottom, always visible -->
        <div class="flex-shrink-0 flex gap-2 px-3 sm:px-4 py-3 border-t border-slate-700 bg-slate-800 rounded-b-xl">
            <button onclick="closeModal('paymentModal')" class="flex-1 py-2.5 bg-slate-700 rounded-lg text-slate-300 hover:bg-slate-600 transition-colors text-sm" aria-label="Cancel">Cancel</button>
            <button onclick="processPayment()" id="processPaymentBtn" class="flex-2 flex-1 py-2.5 bg-amber-500 text-slate-900 rounded-lg font-bold hover:bg-amber-400 transition-colors text-sm" aria-label="Complete Payment"><i class="fas fa-check mr-1"></i>Complete Sale</button>
        </div>
    </div>
</div>

<!-- HELD SALES MODAL -->
<div id="heldModal" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-[1000] p-2 pb-[72px] sm:p-0 sm:pb-0">
    <div class="modal-inner bg-slate-800 rounded-xl max-w-md w-full mx-2 sm:mx-4 shadow-2xl flex flex-col" style="max-height:min(calc(100vh - 80px),560px)">
        <div class="flex-shrink-0 flex justify-between items-center px-4 py-3 border-b border-slate-700 rounded-t-xl">
            <h3 class="text-white font-semibold"><i class="fas fa-pause mr-2 text-amber-400"></i>Held Orders</h3>
            <button onclick="closeModal('heldModal')" class="text-slate-400 hover:text-white text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="flex-1 overflow-y-auto scrollbar-thin p-3" id="heldList">
            <div class="text-center py-8 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>
        </div>
    </div>
</div>

<!-- REPORTS MODAL -->
<div id="reportsModal" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-[1000] p-2 pb-[72px] sm:p-0 sm:pb-0">
    <div class="modal-inner bg-slate-800 rounded-xl max-w-md w-full mx-2 sm:mx-4 shadow-2xl max-h-[92vh] overflow-y-auto scrollbar-thin">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-700">
            <h3 class="text-white font-semibold"><i class="fas fa-chart-bar mr-2 text-amber-400"></i>Sales Reports</h3>
            <button onclick="closeModal('reportsModal')" class="text-slate-400 hover:text-white text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="p-4 max-h-96 overflow-y-auto scrollbar-thin" id="reportsContent">
            <div class="text-center py-8"><i class="fas fa-spinner fa-spin text-amber-400 text-2xl"></i><p class="text-slate-400 mt-2">Loading reports...</p></div>
        </div>
    </div>
</div>

<!-- CALCULATOR MODAL -->
<div id="calculatorModal" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-[1000] p-2 pb-[72px] sm:p-0 sm:pb-0">
    <div class="modal-inner bg-slate-800 rounded-xl max-w-sm w-full mx-2 sm:mx-4 shadow-2xl max-h-[92vh] overflow-y-auto scrollbar-thin">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-700">
            <h3 class="text-white font-semibold"><i class="fas fa-calculator mr-2 text-amber-400"></i>Calculator</h3>
            <button onclick="closeModal('calculatorModal')" class="text-slate-400 hover:text-white text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="p-4 space-y-3">
            <div class="bg-slate-900 border border-slate-700 rounded-lg p-3 text-right">
                <span class="text-amber-400 text-3xl font-mono font-bold" id="calcDisplay">0</span>
            </div>
            <div class="grid grid-cols-4 gap-1.5">
                <button class="calc-btn py-3 bg-red-500/20 text-red-400 rounded-lg font-semibold hover:bg-red-500/30 transition-colors" data-action="clear">C</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors" data-action="operator" data-op="%">%</button>
                <button class="calc-btn py-3 bg-slate-600 rounded-lg text-amber-400 font-semibold hover:bg-slate-500 transition-colors" data-action="operator" data-op="/">÷</button>
                <button class="calc-btn py-3 bg-slate-600 rounded-lg text-amber-400 font-semibold hover:bg-slate-500 transition-colors" data-action="operator" data-op="*">×</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors" data-action="number" data-num="7">7</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors" data-action="number" data-num="8">8</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors" data-action="number" data-num="9">9</button>
                <button class="calc-btn py-3 bg-slate-600 rounded-lg text-amber-400 font-semibold hover:bg-slate-500 transition-colors" data-action="operator" data-op="-">−</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors" data-action="number" data-num="4">4</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors" data-action="number" data-num="5">5</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors" data-action="number" data-num="6">6</button>
                <button class="calc-btn py-3 bg-slate-600 rounded-lg text-amber-400 font-semibold hover:bg-slate-500 transition-colors" data-action="operator" data-op="+">+</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors" data-action="number" data-num="1">1</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors" data-action="number" data-num="2">2</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors" data-action="number" data-num="3">3</button>
                <button class="calc-btn py-3 bg-amber-500 text-slate-900 rounded-lg font-bold hover:bg-amber-400 transition-colors row-span-2" data-action="equals">=</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors col-span-2" data-action="number" data-num="0">0</button>
                <button class="calc-btn py-3 bg-slate-700 rounded-lg text-white font-semibold hover:bg-slate-600 transition-colors" data-action="number" data-num=".">.</button>
            </div>
        </div>
        <div class="px-4 py-3 border-t border-slate-700 flex gap-2">
            <button onclick="copyCalcResult()" class="flex-1 py-2 bg-slate-700 rounded-lg text-white text-sm hover:bg-slate-600 transition-colors" aria-label="Copy Result"><i class="fas fa-copy mr-1"></i>Copy</button>
            <button onclick="closeModal('calculatorModal')" class="flex-1 py-2 bg-slate-700 rounded-lg text-white text-sm hover:bg-slate-600 transition-colors" aria-label="Close">Close</button>
        </div>
    </div>
</div>

<!-- PRICE OVERRIDE MODAL -->
<div id="priceOverrideModal" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-[1000] p-2 pb-[72px] sm:p-0 sm:pb-0">
    <div class="modal-inner bg-slate-800 rounded-xl max-w-sm w-full mx-2 sm:mx-4 shadow-2xl max-h-[92vh] overflow-y-auto scrollbar-thin">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-700">
            <h3 class="text-white font-semibold"><i class="fas fa-edit mr-2 text-amber-400"></i>Manual Price</h3>
            <button onclick="closeModal('priceOverrideModal')" class="text-slate-400 hover:text-white text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="p-4 space-y-3">
            <div class="bg-slate-700/50 rounded-lg p-3 space-y-1">
                <div class="flex justify-between text-sm"><span class="text-slate-400">Product:</span><span class="text-white font-medium" id="overrideProductName">-</span></div>
                <div class="flex justify-between text-sm"><span class="text-slate-400">Current Price:</span><span class="text-amber-400" id="overrideCurrentPrice">-</span></div>
            </div>
            <div>
                <label for="overridePriceInput" class="text-slate-300 text-sm block mb-1.5">New Price</label>
                <div class="grid grid-cols-3 gap-1.5 mb-2">
                    <button class="override-keypad-btn py-2 bg-slate-700 rounded text-white text-lg font-semibold hover:bg-slate-600 transition-colors" data-value="1">1</button>
                    <button class="override-keypad-btn py-2 bg-slate-700 rounded text-white text-lg font-semibold hover:bg-slate-600 transition-colors" data-value="2">2</button>
                    <button class="override-keypad-btn py-2 bg-slate-700 rounded text-white text-lg font-semibold hover:bg-slate-600 transition-colors" data-value="3">3</button>
                    <button class="override-keypad-btn py-2 bg-slate-700 rounded text-white text-lg font-semibold hover:bg-slate-600 transition-colors" data-value="4">4</button>
                    <button class="override-keypad-btn py-2 bg-slate-700 rounded text-white text-lg font-semibold hover:bg-slate-600 transition-colors" data-value="5">5</button>
                    <button class="override-keypad-btn py-2 bg-slate-700 rounded text-white text-lg font-semibold hover:bg-slate-600 transition-colors" data-value="6">6</button>
                    <button class="override-keypad-btn py-2 bg-slate-700 rounded text-white text-lg font-semibold hover:bg-slate-600 transition-colors" data-value="7">7</button>
                    <button class="override-keypad-btn py-2 bg-slate-700 rounded text-white text-lg font-semibold hover:bg-slate-600 transition-colors" data-value="8">8</button>
                    <button class="override-keypad-btn py-2 bg-slate-700 rounded text-white text-lg font-semibold hover:bg-slate-600 transition-colors" data-value="9">9</button>
                    <button class="override-keypad-btn py-2 bg-red-500/20 text-red-400 rounded text-lg font-semibold hover:bg-red-500/30 transition-colors" data-value="C">C</button>
                    <button class="override-keypad-btn py-2 bg-slate-700 rounded text-white text-lg font-semibold hover:bg-slate-600 transition-colors" data-value="0">0</button>
                    <button class="override-keypad-btn py-2 bg-slate-700 rounded text-white text-lg font-semibold hover:bg-slate-600 transition-colors" data-value=".">.</button>
                </div>
                <input type="text" id="overridePriceInput" class="w-full px-3 py-2 bg-slate-900 border-2 border-amber-500 rounded-lg text-white text-xl font-bold text-center" readonly placeholder="0.00" aria-label="New Price">
            </div>
            <div>
                <label for="adminPinInput" class="text-slate-300 text-sm block mb-1.5">Admin PIN / Authorization</label>
                <input type="password" id="adminPinInput" name="admin_pin" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white" maxlength="6" aria-label="Admin PIN">
            </div>
            <div class="flex items-center gap-2 p-2 bg-amber-500/10 border border-amber-500/30 rounded-lg text-amber-400 text-xs">
                <i class="fas fa-exclamation-triangle"></i> All price overrides are logged for audit purposes.
            </div>
        </div>
        <div class="px-4 py-3 border-t border-slate-700 flex gap-2">
            <button onclick="closeModal('priceOverrideModal')" class="flex-1 py-2 bg-slate-700 rounded-lg text-white hover:bg-slate-600 transition-colors" aria-label="Cancel">Cancel</button>
            <button onclick="confirmPriceOverride()" class="flex-1 py-2 bg-amber-500 text-slate-900 rounded-lg font-semibold hover:bg-amber-400 transition-colors" aria-label="Confirm Override">Confirm Override</button>
        </div>
    </div>
</div>

<!-- AI RECOMMENDATIONS MODAL -->
<div id="aiRecommendationsModal" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-[1000] p-2 pb-[72px] sm:p-0 sm:pb-0">
    <div class="modal-inner bg-slate-800 rounded-xl max-w-sm w-full mx-2 sm:mx-4 shadow-2xl max-h-[92vh] overflow-y-auto scrollbar-thin">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-700">
            <div class="flex items-center gap-2">
                <i class="fas fa-robot text-amber-400"></i>
                <h3 class="text-white font-semibold">AI Recommendations</h3>
            </div>
            <button onclick="closeModal('aiRecommendationsModal')" class="text-slate-400 hover:text-white text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="p-4">
            <p class="text-slate-400 text-sm mb-3" id="aiReason"></p>
            <div id="aiProducts" class="space-y-2 max-h-64 overflow-y-auto scrollbar-thin"></div>
        </div>
    </div>
</div>

<!-- RECEIPT MODAL -->
<div id="receiptModal" class="fixed inset-0 bg-black/80  hidden items-center justify-center z-[1000] p-2 pb-[72px] sm:p-0 sm:pb-0">
    <div class="modal-inner bg-slate-800 rounded-xl max-w-sm w-full mx-2 sm:mx-4 shadow-2xl overflow-hidden max-h-[92vh]">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-700">
            <h3 class="text-white font-semibold"><i class="fas fa-receipt mr-2 text-amber-400"></i>Receipt</h3>
            <button onclick="closeModal('receiptModal')" class="text-slate-400 hover:text-white text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="bg-white">
            <iframe id="receiptFrame" src="about:blank" class="w-full h-96 border-0" title="Receipt"></iframe>
        </div>
        <div class="px-4 py-3 border-t border-slate-700 flex gap-2">
            <button onclick="printReceipt()" class="flex-1 py-2 bg-slate-700 text-white rounded-lg font-semibold hover:bg-slate-600 transition-colors" aria-label="Print"><i class="fas fa-print mr-1"></i>Print</button>
            <button onclick="closeModal('receiptModal')" class="flex-1 py-2 bg-amber-500 text-slate-900 rounded-lg font-semibold hover:bg-amber-400 transition-colors" aria-label="Done">Done</button>
        </div>
    </div>
</div>

<!-- KEYBOARD SHORTCUTS HELP OVERLAY -->
<div id="posHelpOverlay" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-[2000] p-2 sm:p-0">
    <div class="modal-inner bg-slate-800 rounded-xl max-w-md w-full mx-2 sm:mx-4 shadow-2xl max-h-[92vh] overflow-y-auto scrollbar-thin">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-700">
            <h3 class="text-white font-semibold"><i class="fas fa-keyboard mr-2 text-amber-400"></i>Keyboard Shortcuts</h3>
            <button onclick="closePosHelp()" class="text-slate-400 hover:text-white text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="px-4 py-2">
            <div class="flex justify-between items-center py-2 border-b border-slate-700/60"><span class="text-slate-300 text-sm">Search products</span><kbd class="px-2 py-0.5 bg-slate-700 rounded text-amber-400 text-xs font-mono">F1</kbd></div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/60"><span class="text-slate-300 text-sm">Calculator</span><kbd class="px-2 py-0.5 bg-slate-700 rounded text-amber-400 text-xs font-mono">Alt+C</kbd></div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/60"><span class="text-slate-300 text-sm">Pay / Checkout</span><kbd class="px-2 py-0.5 bg-slate-700 rounded text-amber-400 text-xs font-mono">F2</kbd></div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/60"><span class="text-slate-300 text-sm">Hold sale</span><kbd class="px-2 py-0.5 bg-slate-700 rounded text-amber-400 text-xs font-mono">F4</kbd></div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/60"><span class="text-slate-300 text-sm">Clear cart</span><kbd class="px-2 py-0.5 bg-slate-700 rounded text-amber-400 text-xs font-mono">F8</kbd></div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/60"><span class="text-slate-300 text-sm">Open customer</span><kbd class="px-2 py-0.5 bg-slate-700 rounded text-amber-400 text-xs font-mono">F6</kbd></div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/60"><span class="text-slate-300 text-sm">Toggle cart (mobile)</span><kbd class="px-2 py-0.5 bg-slate-700 rounded text-amber-400 text-xs font-mono">C</kbd></div>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/60"><span class="text-slate-300 text-sm">Show this help</span><kbd class="px-2 py-0.5 bg-slate-700 rounded text-amber-400 text-xs font-mono">?</kbd></div>
            <div class="flex justify-between items-center py-2"><span class="text-slate-300 text-sm">Close any modal</span><kbd class="px-2 py-0.5 bg-slate-700 rounded text-amber-400 text-xs font-mono">Esc</kbd></div>
        </div>
        <div class="px-4 py-3 border-t border-slate-700">
            <button onclick="closePosHelp()" class="w-full py-2 bg-amber-500 text-slate-900 rounded-lg font-semibold hover:bg-amber-400 transition-colors" aria-label="Close">Close</button>
        </div>
    </div>
</div>
<script>
// ============================================
// CONFIGURATION
// ============================================
const CONFIG = {
    currency: '<?php echo addslashes($settings['currency'] ?? 'KES'); ?>',
    taxRate: <?php echo (int) ($settings['tax_rate'] ?? 16); ?>,
    companyId: <?php echo (int) $tenant_id; ?>,
    companyName: '<?php echo addslashes($settings['company_name'] ?? 'POS'); ?>',
    branchId: <?php echo (int) ($current_branch['id'] ?? 1); ?>,
    branchName: '<?php echo addslashes($current_branch['name'] ?? 'Main Branch'); ?>',
    branchAddress: '<?php echo addslashes($settings['address'] ?? $settings['company_address'] ?? ''); ?>',
    branchPhone: '<?php echo addslashes($settings['phone'] ?? $settings['company_phone'] ?? ''); ?>',
    vatNumber: '<?php echo addslashes($settings['vat_number'] ?? $settings['tin_number'] ?? $settings['kra_pin'] ?? ''); ?>',
    logoUrl: '<?php echo addslashes(!empty($settings['logo']) ? (function_exists('base_url') ? base_url($settings['logo']) : '/JDH_POS/public/' . $settings['logo']) : ''); ?>',
    receiptFooter: '<?php echo addslashes($settings['receipt_footer'] ?? 'Thank you for your business!'); ?>',
    shiftId: <?php echo isset($current_shift['id']) ? (int) $current_shift['id'] : 'null'; ?>,
    businessType: '<?php echo addslashes($current_business_type ?? 'retail'); ?>',
    businessTypeId: <?php echo (int)($current_bt_id ?? 0); ?>,
    orderType: '<?php echo addslashes(array_key_first($order_types) ?? 'walkin'); ?>',
    csrfToken: '<?php echo addslashes($_SESSION['csrf_token'] ?? ''); ?>',
    userId: <?php echo (int) $user_id; ?>,
    baseUrl: '<?php echo function_exists('base_url') ? rtrim(base_url(''), '/') : '/JDH_POS/public'; ?>',
    paymentMethods: <?php echo json_encode(array_values(array_map(fn($pm) => ['id' => $pm['id'], 'label' => $pm['label']], $enabled_payment_methods))); ?>,
    wholesaleTiers: <?php echo json_encode(array_values($wholesale_tiers)); ?>,
    warrantyPeriods: <?php echo json_encode(array_values($warranty_periods)); ?>
};

// ============================================
// GLOBAL STATE - Exposed to window for advanced modules
// ============================================
window.cart = [];
let cart = window.cart;
let appliedDiscount = null;
let appliedVoucher = null;
window.selectedCustomer = null;
let selectedCustomer = window.selectedCustomer;
let currentCategory = 'all';
let currentTag = null;
let currentSearchQuery = '';
let isProcessingPayment = false;
let selectedPaymentMethod = 'cash';
let splitPayments = [];

// Products data - loaded via AJAX for faster initial page load
let allProducts = [];
const categories = <?php echo json_encode($categories ?? [], JSON_INVALID_UTF8_IGNORE); ?>;
const discounts = <?php echo json_encode($discounts ?? [], JSON_INVALID_UTF8_IGNORE); ?>

// Load products asynchronously for better performance
async function loadProductsLazy() {
    try {
        const response = await fetch('../ajax/get_products_lazy.php?page=1&limit=100');
        const data = await response.json();
        if (data.success) {
            allProducts = data.products;
            renderProducts(allProducts);
            // Load remaining pages in background
            if (data.pages > 1) {
                loadRemainingProducts(2, data.pages);
            }
        }
    } catch (e) {
        console.error('Failed to load products:', e);
        showToast('Error loading products', 'error');
    }
}

// Load remaining product pages in background
async function loadRemainingProducts(currentPage, totalPages) {
    for (let page = currentPage; page <= totalPages && page <= 5; page++) {
        try {
            const response = await fetch(`../ajax/get_products_lazy.php?page=${page}&limit=100`);
            const data = await response.json();
            if (data.success) {
                allProducts = allProducts.concat(data.products);
            }
        } catch (e) {
            console.error(`Failed to load page ${page}:`, e);
        }
    }
}

// Start loading products after page loads
document.addEventListener('DOMContentLoaded', loadProductsLazy);

// Local storage prefix for shared terminals
const LS_PREFIX = 'pos_' + CONFIG.userId + '_';

function _lsKey(name) { return LS_PREFIX + name; }

function _saveState() {
    try {
        localStorage.setItem(_lsKey('cart'), JSON.stringify(window.cart));
        localStorage.setItem(_lsKey('discount'), JSON.stringify(appliedDiscount));
        localStorage.setItem(_lsKey('voucher'), JSON.stringify(appliedVoucher));
        localStorage.setItem(_lsKey('customer'), JSON.stringify(window.selectedCustomer));
    } catch (e) { }
}

function _loadState() {
    try {
        const savedCart = localStorage.getItem(_lsKey('cart'));
        if (savedCart) { window.cart = JSON.parse(savedCart) || []; cart = window.cart; }
        const savedDisc = localStorage.getItem(_lsKey('discount'));
        if (savedDisc) appliedDiscount = JSON.parse(savedDisc);
        const savedVoucher = localStorage.getItem(_lsKey('voucher'));
        if (savedVoucher) appliedVoucher = JSON.parse(savedVoucher);
        const savedCust = localStorage.getItem(_lsKey('customer'));
        if (savedCust) { window.selectedCustomer = JSON.parse(savedCust); selectedCustomer = window.selectedCustomer; }
    } catch (e) { }
}

function _clearState() {
    try {
        ['cart', 'discount', 'voucher', 'customer'].forEach(k => localStorage.removeItem(_lsKey(k)));
    } catch (e) { }
}

// ============================================
// MODAL MANAGEMENT
// ============================================
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function closeAllModals() {
    document.querySelectorAll('.fixed.inset-0.bg-black\\/70').forEach(modal => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function formatCurrency(amount) {
    return `${CONFIG.currency} ${Number(amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
}

function escapeHtml(str) {
    return String(str || '').replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function escapeJsString(value) {
    return String(value || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const bgColor = type === 'success' ? 'bg-emerald-500' : type === 'error' ? 'bg-red-500' : type === 'warning' ? 'bg-amber-500' : 'bg-blue-500';
    const toast = document.createElement('div');
    toast.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg animate-slide-in text-sm max-w-xs`;
    toast.textContent = message;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function getPricingBreakdown() {
    const subtotal = window.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    let discount = 0;
    
    if (appliedDiscount) {
        if (appliedDiscount.type === 'percent') {
            discount = subtotal * (appliedDiscount.value / 100);
        } else {
            discount = appliedDiscount.value;
        }
        discount = Math.min(discount, subtotal);
    }
    
    const taxableAmount = Math.max(0, subtotal - discount);
    const tax = taxableAmount * (CONFIG.taxRate / 100);
    const total = taxableAmount + tax;
    
    return { subtotal, discount, tax, total, itemCount: window.cart.reduce((sum, item) => sum + item.qty, 0) };
}

// ============================================
// CART FUNCTIONS
// ============================================
function updateCartDisplay() {
    _saveState();
    const container = document.getElementById('cartItems');
    if (!container) return;

    if (window.cart.length === 0) {
        container.innerHTML = '<div class="flex flex-col items-center justify-center h-full py-8 gap-2"><div class="w-14 h-14 rounded-full bg-slate-800 flex items-center justify-center"><i class="fas fa-receipt text-slate-600 text-xl"></i></div><p class="text-slate-500 text-xs text-center">No items yet<br><span class="text-slate-600">Tap a product to add</span></p></div>';
        document.getElementById('payBtn').disabled = true;
        updateTotals();
        updatePosCartFabBadge();
        
        // Dispatch event for advanced modules
        document.dispatchEvent(new CustomEvent('cart:updated', { detail: { cart: window.cart } }));
        return;
    }
    
    container.innerHTML = window.cart.map((item, index) => `
        <div class="cart-item group flex flex-col px-1 py-1 rounded-lg hover:bg-slate-800/60 transition-colors">
            <div class="flex items-center gap-2">
                <div class="flex flex-col items-center gap-0.5 shrink-0">
                    <button onclick="updateQty(${index}, 1)" class="w-5 h-5 rounded bg-slate-800 text-slate-400 text-[10px] hover:bg-amber-500/20 hover:text-amber-400 transition-colors leading-none">+</button>
                    <button onclick="showQtyPopup(${index})" class="text-white text-xs font-bold font-mono min-w-[20px] text-center hover:text-amber-400 transition-colors" title="Tap to set qty">${item.qty}</button>
                    <button onclick="updateQty(${index}, -1)" class="w-5 h-5 rounded bg-slate-800 text-slate-400 text-[10px] hover:bg-red-500/20 hover:text-red-400 transition-colors leading-none">&minus;</button>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-slate-200 text-xs font-medium truncate leading-tight">${escapeHtml(item.name)}</div>
                    <div class="text-slate-500 text-[10px] font-mono">${formatCurrency(item.price)} each${item.note ? ' <span class="text-amber-400/70 italic">' + escapeHtml(item.note) + '</span>' : ''}</div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-white text-xs font-bold font-mono">${formatCurrency(item.price * item.qty)}</div>
                    <div class="flex gap-1 justify-end mt-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button onclick="toggleItemNote(${index})" class="text-slate-600 hover:text-blue-400 text-[9px] transition-colors" title="Add note"><i class="fas fa-comment-dots"></i></button>
                        <button onclick="showPriceOverrideModal(${index})" class="text-slate-600 hover:text-amber-400 text-[9px] transition-colors" title="Override Price"><i class="fas fa-tag"></i></button>
                        <button onclick="removeFromCart(${index})" class="text-slate-600 hover:text-red-400 text-[9px] transition-colors" title="Remove"><i class="fas fa-times"></i></button>
                    </div>
                </div>
            </div>
            <div id="noteRow_${index}" class="${item.note ? '' : 'hidden'} mt-0.5 pl-7">
                <input type="text" value="${escapeHtml(item.note || '')}" placeholder="Item note..." maxlength="80"
                    class="w-full px-1.5 py-0.5 bg-slate-900 border border-slate-700/60 rounded text-[10px] text-slate-300 placeholder-slate-600 focus:outline-none focus:border-amber-500/50"
                    oninput="saveItemNote(${index}, this.value)" onblur="saveItemNote(${index}, this.value)">
            </div>
        </div>
    `).join('<div class="receipt-dashes my-0.5 opacity-40"></div>');
    
    document.getElementById('payBtn').disabled = false;
    updateTotals();
    updatePosCartFabBadge();
    loadAIRecommendations();
    
    // Dispatch event for advanced modules
    document.dispatchEvent(new CustomEvent('cart:updated', { detail: { cart: window.cart } }));
}

function updateTotals() {
    const { subtotal, discount, tax, total, itemCount } = getPricingBreakdown();
    
    const subtotalEl = document.getElementById('cartSubtotalDisplay');
    const discountDisplayRow = document.getElementById('discountDisplayRow');
    const discountEl = document.getElementById('cartDiscountDisplay');
    const taxEl = document.getElementById('cartTaxDisplay');
    const totalEl = document.getElementById('paymentTotal');
    const itemCountEl = document.getElementById('cartItemCount');
    const orderNumEl = document.getElementById('orderNumberDisplay');
    
    if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
    
    if (discount > 0 && discountDisplayRow && discountEl) {
        discountDisplayRow.classList.remove('hidden');
        discountDisplayRow.classList.add('flex');
        discountEl.textContent = '-' + formatCurrency(discount);
    } else if (discountDisplayRow) {
        discountDisplayRow.classList.add('hidden');
        discountDisplayRow.classList.remove('flex');
    }
    
    if (taxEl) taxEl.textContent = formatCurrency(tax);
    if (totalEl) totalEl.textContent = formatCurrency(total);
    if (itemCountEl) itemCountEl.textContent = itemCount + (itemCount === 1 ? ' item' : ' items');
    if (orderNumEl) orderNumEl.textContent = window.cart.length > 0 ? '#' + String(Date.now()).slice(-4) : '#—';
    
    // Update payment modal if it's visible
    updatePaymentModalTotals();
    
    // Recalculate change after updating totals
    const changeAmountEl = document.getElementById('changeAmount');
    if (changeAmountEl && !document.getElementById('paymentModal')?.classList.contains('hidden')) {
        calculateChange();
    }
    
    return total;
}

function updateQty(index, delta) {
    const item = window.cart[index];
    if (!item) return;
    
    const newQty = item.qty + delta;
    if (newQty < 1) {
        window.cart.splice(index, 1);
    } else if (newQty <= item.stock) {
        item.qty = newQty;
    } else {
        showToast('No more stock available', 'error');
        return;
    }
    cart = window.cart;
    updateCartDisplay();
}

function removeFromCart(index) {
    window.cart.splice(index, 1);
    cart = window.cart;
    updateCartDisplay();
}

function clearCart() {
    if (window.cart.length > 0 && !confirm('Clear entire cart?')) return;
    window.cart = [];
    cart = window.cart;
    appliedDiscount = null;
    appliedVoucher = null;
    window.selectedCustomer = null;
    selectedCustomer = null;
    _clearState();
    // Reset coupon UI
    const _ci = document.getElementById('couponCodeInput');
    const _cm = document.getElementById('couponMsg');
    const _ca = document.getElementById('couponApplyBtn');
    const _cc = document.getElementById('couponClearBtn');
    if (_ci) { _ci.value = ''; _ci.readOnly = false; }
    if (_cm) { _cm.textContent = ''; _cm.classList.add('hidden'); }
    if (_ca) { _ca.disabled = false; _ca.textContent = 'Apply'; _ca.classList.remove('hidden'); }
    if (_cc) { _cc.classList.add('hidden'); }
    updateCartDisplay();
    showToast('Cart cleared', 'info');
}

// Helper function for advanced modules
window.getCartTotal = function() {
    return window.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
};

function addToCart(element) {
    const id = parseInt(element.dataset.productId);
    const name = element.dataset.productName;
    const price = parseFloat(element.dataset.productPrice);
    const stock = (element.dataset.productStock != null && element.dataset.productStock !== '') ? parseInt(element.dataset.productStock, 10) : 999;
    const image = element.dataset.productImage || '';
    
    if (stock <= 0) {
        showToast('This product is out of stock', 'error');
        return;
    }
    
    // Visual feedback (only if element is a real DOM node)
    if (element.classList) {
        element.classList.add('processing');
        setTimeout(() => element.classList.remove('processing'), 400);
    }
    
    const existing = window.cart.find(item => item.id === id);
    if (existing) {
        if (existing.qty >= stock) {
            showToast('No more stock available', 'error');
            return;
        }
        existing.qty++;
    } else {
        window.cart.push({ id, name, price, qty: 1, stock, image });
    }
    
    // Keep local reference in sync
    cart = window.cart;
    
    updateCartDisplay();
    showToast(`${name} added to cart`, 'success');
    
    // Dispatch event for advanced modules
    document.dispatchEvent(new CustomEvent('cart:updated', { detail: { cart: window.cart } }));
    // Live stock-dot update
    if (element && element.classList && stock < 999) {
        const inCart = window.cart.find(i => i.id === id)?.qty || 0;
        const rem = stock - inCart;
        const dot = element.querySelector('.stock-dot');
        const chip = element.querySelector('.low-qty');
        if (rem <= 0) {
            element.classList.replace('card-in','card-oos') || element.classList.replace('card-low','card-oos');
            if (dot) { dot.className='stock-dot stock-dot-oos'; dot.title='Out of stock'; }
            if (chip) chip.remove();
        } else if (rem <= 5) {
            element.classList.replace('card-in','card-low');
            if (dot) { dot.className='stock-dot stock-dot-low'; dot.title=`Only ${rem} left`; }
            if (chip) chip.textContent=`Only ${rem} left`;
            else { const c=document.createElement('span'); c.className='low-qty'; c.textContent=`Only ${rem} left`; element.querySelector('.card-img-area')?.appendChild(c); }
        }
    }
}

// ============================================
// PAYMENT MODAL FUNCTIONS (AUTO-CALCULATION FIXED)
// ============================================
function showPaymentModal() {
    closePosCart();
    if (window.cart.length === 0) {
        showToast('Cart is empty', 'warning');
        return;
    }
    
    // Get fresh totals from current cart
    const { subtotal, discount, tax, total, itemCount } = getPricingBreakdown();
    
    // Update payment modal displays
    const paymentTotalEl = document.getElementById('paymentTotal');
    const amountReceivedEl = document.getElementById('amountReceived');
    const changeAmountEl = document.getElementById('changeAmount');
    const cartItemCountEl = document.getElementById('modalItemCount');
    const cartSubtotalDisplay = document.getElementById('modalSubtotalDisplay');
    const cartTaxDisplay = document.getElementById('modalTaxDisplay');
    const cartDiscountDisplay = document.getElementById('modalDiscountDisplay');
    const discountDisplayRow = document.getElementById('modalDiscountRow');
    
    if (cartItemCountEl) cartItemCountEl.textContent = itemCount;
    if (cartSubtotalDisplay) cartSubtotalDisplay.textContent = formatCurrency(subtotal);
    if (cartTaxDisplay) cartTaxDisplay.textContent = formatCurrency(tax);
    if (paymentTotalEl) paymentTotalEl.textContent = formatCurrency(total);
    
    if (discount > 0 && cartDiscountDisplay && discountDisplayRow) {
        cartDiscountDisplay.textContent = '-' + formatCurrency(discount);
        discountDisplayRow.classList.remove('hidden');
        discountDisplayRow.classList.add('flex');
    } else if (discountDisplayRow) {
        discountDisplayRow.classList.add('hidden');
        discountDisplayRow.classList.remove('flex');
    }
    
    if (amountReceivedEl) amountReceivedEl.value = total.toFixed(2);
    if (changeAmountEl) changeAmountEl.textContent = formatCurrency(0);
    
    // Reset payment method selection
    selectedPaymentMethod = 'cash';
    document.querySelectorAll('.payment-method-btn').forEach((btn, idx) => {
        const isCash = btn.dataset.method === 'cash';
        btn.classList.toggle('ring-2', isCash);
        btn.classList.toggle('ring-amber-500', isCash);
    });
    
    // Hide split payment panel
    const splitPanel = document.getElementById('splitPaymentPanel');
    if (splitPanel) splitPanel.style.display = 'none';
    
    // Clear split payments list (keep first empty one)
    const splitList = document.getElementById('splitPaymentsList');
    if (splitList) {
        splitList.innerHTML = `
            <div class="space-y-2 p-2 bg-slate-800 rounded">
                <select name="split_method_0" class="split-method w-full px-2 py-1 bg-slate-900 border border-slate-600 rounded text-sm text-white">
                    ${buildPaymentOptions()}
                </select>
                <input type="number" name="split_amount_0" class="split-amount w-full px-2 py-1 bg-slate-900 border border-slate-600 rounded text-sm text-white" placeholder="Amount" autocomplete="off" onchange="calculateSplitTotal()">
            </div>
        `;
    }
    const splitTotalEl = document.getElementById('splitTotal');
    if (splitTotalEl) splitTotalEl.textContent = formatCurrency(0);
    
    // Enable process button
    const processBtn = document.getElementById('processPaymentBtn');
    if (processBtn) {
        processBtn.disabled = false;
        processBtn.innerHTML = '<i class="fas fa-credit-card mr-1"></i> Complete Sale';
    }
    
    // Reset loyalty redeem
    _loyaltyRedeemDiscount = 0;
    _loyaltyPointsToRedeem = 0;
    const loyaltyInput = document.getElementById('loyaltyRedeemInput');
    if (loyaltyInput) loyaltyInput.value = '';
    const loyaltyMsg = document.getElementById('loyaltyDiscountMsg');
    if (loyaltyMsg) loyaltyMsg.classList.add('hidden');
    showLoyaltyRowIfCustomer();

    // Show the modal
    showModal('paymentModal');
    
    // Focus on amount input
    setTimeout(() => {
        if (amountReceivedEl) {
            amountReceivedEl.focus();
            amountReceivedEl.select();
        }
    }, 100);
}

function updatePaymentModalTotals() {
    // Only update if payment modal is visible
    const paymentModal = document.getElementById('paymentModal');
    if (!paymentModal || paymentModal.classList.contains('hidden')) return;
    
    const { subtotal, discount, tax, total, itemCount } = getPricingBreakdown();
    
    const paymentTotalEl = document.getElementById('paymentTotal');
    const cartItemCountEl = document.getElementById('modalItemCount');
    const cartSubtotalDisplay = document.getElementById('modalSubtotalDisplay');
    const cartTaxDisplay = document.getElementById('modalTaxDisplay');
    const cartDiscountDisplay = document.getElementById('modalDiscountDisplay');
    const discountDisplayRow = document.getElementById('modalDiscountRow');
    const amountReceivedEl = document.getElementById('amountReceived');
    
    if (cartItemCountEl) cartItemCountEl.textContent = itemCount;
    if (cartSubtotalDisplay) cartSubtotalDisplay.textContent = formatCurrency(subtotal);
    if (cartTaxDisplay) cartTaxDisplay.textContent = formatCurrency(tax);
    if (paymentTotalEl) paymentTotalEl.textContent = formatCurrency(total);
    
    if (discount > 0 && cartDiscountDisplay && discountDisplayRow) {
        cartDiscountDisplay.textContent = '-' + formatCurrency(discount);
        discountDisplayRow.classList.remove('hidden');
        discountDisplayRow.classList.add('flex');
    } else if (discountDisplayRow) {
        discountDisplayRow.classList.add('hidden');
        discountDisplayRow.classList.remove('flex');
    }
    
    // Update amount received to match new total if exact is selected
    if (amountReceivedEl && selectedPaymentMethod !== 'split') {
        const currentAmount = parseFloat(amountReceivedEl.value) || 0;
        const previousTotal = parseFloat(paymentTotalEl?.dataset.previousTotal || currentAmount);
        
        // If user hasn't manually changed the amount, auto-update it
        if (Math.abs(currentAmount - previousTotal) < 0.01) {
            amountReceivedEl.value = total.toFixed(2);
        }
        if (paymentTotalEl) paymentTotalEl.dataset.previousTotal = total;
    }
    
    calculateChange();
}

function selectPaymentMethod(method) {
    selectedPaymentMethod = method;
    document.querySelectorAll('.payment-method-btn').forEach(btn => {
        const isSelected = btn.dataset.method === method;
        btn.classList.toggle('ring-2', isSelected);
        btn.classList.toggle('ring-amber-500', isSelected);
    });
    
    const splitPanel = document.getElementById('splitPaymentPanel');
    if (splitPanel) splitPanel.style.display = method === 'split' ? 'block' : 'none';
    
    if (method !== 'split') {
        splitPayments = [];
        const total = updateTotals();
        const amountReceived = document.getElementById('amountReceived');
        if (amountReceived) amountReceived.value = total.toFixed(2);
    }
    calculateChange();
}

function buildPaymentOptions() {
    return (CONFIG.paymentMethods || []).map(pm =>
        `<option value="${escapeHtml(pm.id)}">${escapeHtml(pm.label)}</option>`
    ).join('');
}

function addSplitPayment() {
    const list = document.getElementById('splitPaymentsList');
    const idx = list.children.length;
    const div = document.createElement('div');
    div.className = 'space-y-2 p-2 bg-slate-800 rounded';
    div.innerHTML = `
        <select name="split_method_${idx}" class="split-method w-full px-2 py-1 bg-slate-900 border border-slate-600 rounded text-sm text-white">
            ${buildPaymentOptions()}
        </select>
        <input type="number" name="split_amount_${idx}" class="split-amount w-full px-2 py-1 bg-slate-900 border border-slate-600 rounded text-sm text-white" placeholder="Amount" autocomplete="off" onchange="calculateSplitTotal()">
    `;
    list.appendChild(div);
}

function getSplitPayments() {
    const payments = [];
    const methods = document.querySelectorAll('#splitPaymentsList .split-method');
    const amounts = document.querySelectorAll('#splitPaymentsList .split-amount');
    
    for (let i = 0; i < amounts.length; i++) {
        const amount = parseFloat(amounts[i].value) || 0;
        if (amount > 0) {
            const method = methods[i] ? methods[i].value : 'cash';
            payments.push({ method, amount });
        }
    }
    return payments;
}

function calculateSplitTotal() {
    const payments = getSplitPayments();
    const total = payments.reduce((sum, p) => sum + p.amount, 0);
    const splitTotalEl = document.getElementById('splitTotal');
    if (splitTotalEl) splitTotalEl.textContent = formatCurrency(total);
    calculateChange();
}

function calculateChange() {
    const { total } = getPricingBreakdown();
    const amount = parseFloat(document.getElementById('amountReceived')?.value || 0);
    let change = 0;
    let isSufficient = false;
    
    if (selectedPaymentMethod === 'split') {
        const splitTotal = getSplitPayments().reduce((sum, p) => sum + p.amount, 0);
        change = Math.max(0, splitTotal - total);
        isSufficient = splitTotal >= total;
    } else {
        change = Math.max(0, amount - total);
        isSufficient = amount >= total;
    }
    
    const changeEl = document.getElementById('changeAmount');
    const changeIndicator = document.getElementById('changeIndicator');
    
    if (changeEl) changeEl.textContent = formatCurrency(change);
    
    if (changeIndicator) {
        if (isSufficient) {
            changeIndicator.innerHTML = '<i class="fas fa-check-circle text-emerald-400 ml-2"></i>';
        } else {
            changeIndicator.innerHTML = '<i class="fas fa-exclamation-triangle text-amber-400 ml-2"></i>';
        }
    }
}

function processPayment() {
    if (!CONFIG.shiftId) {
        showToast('Please open a shift first', 'error');
        showModal('shiftModal');
        return;
    }
    
    if (isProcessingPayment) {
        showToast('Payment already in progress', 'warning');
        return;
    }
    
    const { subtotal, discount, tax, total } = getPricingBreakdown();
    
    if (selectedPaymentMethod === 'split') {
        splitPayments = getSplitPayments();
        if (splitPayments.length === 0) {
            showToast('Add at least one payment method', 'error');
            return;
        }
        const splitTotal = splitPayments.reduce((sum, p) => sum + p.amount, 0);
        if (splitTotal < total) {
            showToast('Split payments total is less than total due', 'error');
            return;
        }
    }
    
    const amount = parseFloat(document.getElementById('amountReceived')?.value || 0);
    if (selectedPaymentMethod !== 'split' && amount < total) {
        showToast('Insufficient amount received', 'error');
        return;
    }
    
    isProcessingPayment = true;
    const processBtn = document.getElementById('processPaymentBtn');
    const originalHtml = processBtn.innerHTML;
    processBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    processBtn.disabled = true;
    
    const soldItems = [...window.cart];
    const normalizedPaymentMethod = selectedPaymentMethod === 'bank' ? 'bank_transfer' : selectedPaymentMethod;
    const saleItems = window.cart.map(item => ({ product_id: item.id, quantity: item.qty, price: item.price }));
    
    // Business type specific fields
    const kitchenNotes = document.getElementById('kitchenNotes')?.value || '';
    const tableNumber = document.getElementById('tableNumber')?.value || '';
    const prescriptionEnabled = document.getElementById('prescriptionToggle')?.checked || false;
    const prescriptionRef = document.getElementById('prescriptionRef')?.value || '';
    const prescriptionDoctor = document.getElementById('prescriptionDoctor')?.value || '';
    const prescriptionNotes = document.getElementById('prescriptionNotes')?.value || '';
    const roomNumber = document.getElementById('roomNumber')?.value || '';
    const guestName = document.getElementById('guestName')?.value || '';
    const serialNumber = document.getElementById('serialNumber')?.value || '';
    const warrantyMonths = document.getElementById('warrantyPeriod')?.value || '';
    const ageVerified = document.getElementById('ageVerifyToggle')?.checked ? 1 : 0;
    const ageVerifyId = document.getElementById('ageVerifyId')?.value || '';
    const staffId = document.getElementById('staffSelect')?.value || '';
    const batchNumber = document.getElementById('batchNumber')?.value || '';
    const weight = parseFloat(document.getElementById('weightInput')?.value || 0);
    const weightUnit = document.getElementById('weightUnit')?.value || 'kg';
    
    const _amtReceived = selectedPaymentMethod === 'split'
        ? splitPayments.reduce((s, p) => s + p.amount, 0)
        : parseFloat(document.getElementById('amountReceived')?.value || total);

    const saleData = {
        branch_id: CONFIG.branchId,
        customer_id: window.selectedCustomer?.id || '',
        customer_name: window.selectedCustomer?.name || 'Walk-in Customer',
        payment_method: normalizedPaymentMethod,
        voucher_code: document.getElementById('voucherInput')?.value.trim() || '',
        discount_id: appliedDiscount?.id || '',
        discount_value: Number(appliedDiscount?.value || 0),
        discount_type: appliedDiscount?.type || 'fixed',
        subtotal,
        discount,
        tax,
        total,
        amount_received: _amtReceived,
        items: saleItems,
        items_json: JSON.stringify(saleItems),
        order_type: CONFIG.orderType,
        csrf_token: CONFIG.csrfToken,
        loyalty_points_redeemed: _loyaltyPointsToRedeem,
        loyalty_discount: _loyaltyRedeemDiscount,
        kitchen_notes: kitchenNotes,
        table_number: tableNumber,
        prescription_enabled: prescriptionEnabled ? 1 : 0,
        prescription_ref: prescriptionRef,
        prescription_doctor: prescriptionDoctor,
        prescription_notes: prescriptionNotes,
        room_number: roomNumber,
        guest_name: guestName,
        serial_number: serialNumber,
        warranty_months: warrantyMonths,
        age_verified: ageVerified,
        age_verify_id: ageVerifyId,
        staff_id: staffId,
        batch_number: batchNumber,
        weight: weight,
        weight_unit: weightUnit,
        bt_id: CONFIG.businessTypeId,
        business_type: CONFIG.businessType
    };
    
    if (selectedPaymentMethod === 'split') {
        saleData.split_payments = JSON.stringify(splitPayments);
    }
    
    fetch('../ajax/process_sale.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': CONFIG.csrfToken
        },
        body: JSON.stringify(saleData)
    })
    .then(async r => {
        const text = await r.text();
        let data = null;
        try {
            data = text ? JSON.parse(text) : null;
        } catch (e) {
            throw new Error('Server returned non-JSON response: ' + text.slice(0, 300));
        }
        if (!data && !r.ok) {
            throw new Error('Request failed with HTTP ' + r.status);
        }
        return data;
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Sale failed');
        }
        
        showToast(`Sale completed! Invoice: ${data.invoice_number || '#' + data.sale_id}`, 'success');
        if (data.csrf_token) CONFIG.csrfToken = data.csrf_token;

        trackBehavior([...window.cart]); // snapshot before clear
        updateGridStockAfterSale([...window.cart]); // decrement stock on product grid
        awardLoyaltyPoints([...window.cart], total);
        _loyaltyRedeemDiscount = 0;
        _loyaltyPointsToRedeem = 0;
        refreshTodayStats();
        clearCart();
        closeModal('paymentModal');
        showConfetti();
        
        const amountReceived = _amtReceived;
        const change = Math.max(0, amountReceived - total);

        if (data.sale_id) {
            _lastSaleId = data.sale_id;
            showReceipt(data.sale_id);
        } else if (data.receipt) {
            data.receipt.amount_received = amountReceived;
            data.receipt.change = change;
            showReceiptWithData(data.receipt);
        } else {
            showReceiptWithItems(soldItems, normalizedPaymentMethod, data.invoice_number, amountReceived, change);
        }
    })
    .catch(err => {
        const message = err.message || 'Network error';
        const isNetworkError = err.name === 'TypeError' || message.includes('fetch') || message.includes('NetworkError') || message.includes('Failed to fetch');
        const details = Array.isArray(err.details) ? '\n' + err.details.join('\n') : '';
        showToast((isNetworkError ? 'Network error: ' : '') + message + details, 'error');
        if (isNetworkError && window.POSOffline && !window.POSOffline.isOnline()) {
            window.POSOffline.queueSale(saleData).then(function(id) {
                showToast(`Sale queued offline (${id}). Will sync when online.`, 'warning');
                clearCart();
                closeModal('paymentModal');
                showConfetti();
            }).catch(function(e) {
                showToast('Failed to queue sale: ' + e.message, 'error');
            });
        }
    })
    .finally(() => {
        isProcessingPayment = false;
        processBtn.innerHTML = originalHtml;
        processBtn.disabled = false;
    });
}

// ============================================
// SEARCH & FILTER FUNCTIONS
// ============================================
function searchProducts(query) {
    currentSearchQuery = String(query || '');
    applyProductFilters();
}

function filterCategory(categoryId, btn) {
    currentCategory = categoryId;
    currentTag = null;
    
    document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tag-filter-btn').forEach(b => {
        b.style.background = b.dataset.originalBg || '';
        b.style.color = b.dataset.originalColor || '';
        b.style.borderColor = (b.dataset.originalColor || '') + '40';
    });
    if (btn) btn.classList.add('active');
    applyProductFilters();
}

function filterTag(tagId, btn) {
    currentTag = tagId;
    
    document.querySelectorAll('.tag-filter-btn').forEach(b => {
        if (!b.dataset.originalBg) {
            b.dataset.originalBg = b.style.background;
            b.dataset.originalColor = b.style.color;
        }
    });
    
    document.querySelectorAll('.tag-filter-btn').forEach(b => {
        const isActive = b === btn;
        if (isActive) {
            b.style.background = b.dataset.originalColor;
            b.style.color = '#111827';
            b.style.borderColor = b.dataset.originalColor;
        } else {
            b.style.background = b.dataset.originalBg;
            b.style.color = b.dataset.originalColor;
            b.style.borderColor = (b.dataset.originalColor || '') + '40';
        }
    });
    applyProductFilters();
}

function setOrderType(type, btn) {
    CONFIG.orderType = type;
    document.querySelectorAll('.order-type-btn').forEach(b => {
        b.classList.remove('ring-1', 'ring-current', 'opacity-100');
        b.classList.add('opacity-60');
    });
    if (btn) {
        btn.classList.add('ring-1', 'ring-current', 'opacity-100');
        btn.classList.remove('opacity-60');
    }
    showToast(`Order type: ${type}`, 'info');
}

function applyProductFilters() {
    const query = currentSearchQuery.trim().toLowerCase();
    const filtered = allProducts.filter(p => {
        const matchesCategory = currentCategory === 'all' || p.category_id == currentCategory;
        const matchesTag = !currentTag || (p.tag_ids || []).includes(currentTag);
        const matchesQuery = !query || p.name.toLowerCase().includes(query) || (p.sku && p.sku.toLowerCase().includes(query));
        return matchesCategory && matchesTag && matchesQuery;
    });
    renderProducts(filtered);
}

function renderProducts(products) {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;

    if (products.length === 0) {
        grid.innerHTML = '<div class="col-span-full text-center py-12 text-slate-500">No products found</div>';
        return;
    }
    
    grid.innerHTML = products.map(p => {
        const stockQty = (p.stock != null && p.stock !== '') ? parseInt(p.stock, 10) : 999;
        const isLowStock = stockQty > 0 && stockQty <= 5;
        const hasImage = p.image && String(p.image).trim() !== '';
        let imgRel = String(p.image || '').replace(/^\/+/, '');
        if (imgRel.indexOf('public/') === 0) imgRel = imgRel.substring(7);
        const imgUrl = hasImage ? CONFIG.baseUrl + '/' + imgRel : '';
        const productName = escapeHtml(p.name) || '<span class="text-slate-500">Unnamed</span>';
        const stockBadgeClass = isLowStock ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500';
        
        const tagDots = (p.tags && p.tags.length) ? 
            `<div class="flex items-center gap-0.5">${p.tags.slice(0, 3).map(t => `<span class="w-1.5 h-1.5 rounded-full" style="background: ${t.color};" title="${escapeHtml(t.name)}"></span>`).join('')}${p.tags.length > 3 ? '<span class="text-[8px] text-slate-500">+' + (p.tags.length - 3) + '</span>' : ''}</div>` : '';
        
        const isOOS = stockQty <= 0;
        const pcClass  = isOOS ? 'pc-oos' : (isLowStock ? 'pc-low' : 'pc-in');
        const dotClass = isOOS ? 's-dot s-dot-red' : (isLowStock ? 's-dot s-dot-amber' : 's-dot s-dot-green');
        const dotTitle = isOOS ? 'Out of stock' : (isLowStock ? `Only ${stockQty} left` : `In stock (${stockQty})`);
        const priceClass = isOOS ? 's-price-red' : (isLowStock ? 's-price-amber' : 's-price-green');
        const barClass   = isOOS ? 's-bar s-bar-red' : (isLowStock ? 's-bar s-bar-amber' : 's-bar s-bar-green');
        const nameColor  = isOOS ? '#64748b' : '#f1f5f9';
        const priceText  = isOOS ? 'Out of Stock' : (CONFIG.currency + ' ' + Number(p.price).toLocaleString());
        const iconHtml   = hasImage
            ? `<img src="${imgUrl}" class="absolute inset-0 w-full h-full object-cover opacity-80" alt=""><div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/10 to-transparent"></div>`
            : `<div class="w-6 h-6 rounded-full bg-slate-700 flex items-center justify-center"><i class="fas fa-box text-slate-500 text-xs"></i></div>`;
        const overlayHtml = isOOS
            ? `<div class="s-stripe"></div><div class="s-pill"><span>Out of Stock</span></div>`
            : isLowStock ? `<span class="s-chip">Only ${stockQty} left</span>` : '';
        return `
            <div class="product-card aspect-square bg-slate-800 rounded-lg relative select-none overflow-hidden flex flex-col ${pcClass}"
                 data-product-id="${p.id}" data-product-name="${escapeJsString(p.name)}" data-product-price="${p.price}" data-product-stock="${stockQty}" data-product-image="${imgUrl}"
                 ${isOOS ? '' : 'onclick="addToCart(this)"'} role="${isOOS ? 'img' : 'button'}" tabindex="${isOOS ? -1 : 0}">
                <span class="${dotClass}" title="${dotTitle}"></span>
                <div class="s-img flex-1 relative flex items-center justify-center overflow-hidden">
                    ${iconHtml}${overlayHtml}
                </div>
                <div class="px-1 pt-0.5 pb-0.5 flex-shrink-0 ${barClass}">
                    <div class="text-[9px] font-semibold truncate leading-tight" style="color:${nameColor}">${productName}</div>
                    <div class="${priceClass} font-bold text-[9px] leading-tight">${priceText}</div>
                </div>
            </div>
        `;
    }).join('');
}

// ============================================
// SHIFT FUNCTIONS
// ============================================
function openShift() {
    const openingCash = parseFloat(document.getElementById('openingCash')?.value || 0);
    fetch('../ajax/shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=open&opening_cash=${openingCash}&csrf_token=${CONFIG.csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Shift opened successfully', 'success');
            location.reload();
        } else {
            showToast(data.error || 'Failed to open shift', 'error');
        }
    })
    .catch(() => showToast('Network error', 'error'));
}

function closeShift() {
    const closingCash = parseFloat(document.getElementById('closingCash')?.value || 0);
    const notes = document.getElementById('closingNotes')?.value || '';
    fetch('../ajax/shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=close&closing_cash=${closingCash}&notes=${encodeURIComponent(notes)}&csrf_token=${CONFIG.csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Shift closed successfully', 'success');
            location.reload();
        } else {
            showToast(data.error || 'Failed to close shift', 'error');
        }
    })
    .catch(() => showToast('Network error', 'error'));
}

function calcCashVariance() {
    const closingEl = document.getElementById('closingCash');
    const expectedEl = document.getElementById('shiftExpectedCash');
    const varRow = document.getElementById('cashVarianceRow');
    const varAmt = document.getElementById('cashVarianceAmt');
    if (!closingEl || !expectedEl || !varRow || !varAmt) return;
    const closing = parseFloat(closingEl.value) || 0;
    const expected = parseFloat(expectedEl.textContent.replace(/,/g, '')) || 0;
    const diff = closing - expected;
    varRow.classList.remove('hidden');
    varRow.classList.add('flex');
    if (Math.abs(diff) < 0.01) {
        varRow.className = varRow.className.replace(/bg-\S+/g, '') + ' bg-emerald-500/10';
        varAmt.className = 'font-semibold text-emerald-400';
        varAmt.textContent = 'Balanced';
    } else if (diff > 0) {
        varRow.className = varRow.className.replace(/bg-\S+/g, '') + ' bg-blue-500/10';
        varAmt.className = 'font-semibold text-blue-400';
        varAmt.textContent = '+' + CONFIG.currency + ' ' + diff.toFixed(2) + ' (over)';
    } else {
        varRow.className = varRow.className.replace(/bg-\S+/g, '') + ' bg-red-500/10';
        varAmt.className = 'font-semibold text-red-400';
        varAmt.textContent = CONFIG.currency + ' ' + diff.toFixed(2) + ' (short)';
    }
}

function refreshShiftSummary() {
    fetch(`../ajax/get_register_summary.php?csrf_token=${CONFIG.csrfToken}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const s = data.summary;
            const setEl = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
            setEl('shiftTotalSales', parseFloat(s.total_sales || 0).toFixed(2));
            setEl('shiftSaleCount', s.sale_count || 0);
            setEl('shiftCashSales', parseFloat(s.cash || 0).toFixed(2));
            const opening = parseFloat(s.opening_cash || 0);
            const cash = parseFloat(s.cash || 0);
            setEl('shiftExpectedCash', (opening + cash).toFixed(2));
            const breakdown = document.getElementById('shiftPaymentBreakdown');
            if (breakdown && s.by_method) {
                const rows = Object.entries(s.by_method).map(([m, v]) =>
                    `<div class="flex justify-between text-xs"><span class="text-slate-400 capitalize">${m}</span><span class="text-white">${CONFIG.currency} ${parseFloat(v).toFixed(2)}</span></div>`
                ).join('');
                breakdown.innerHTML = '<div class="text-slate-500 text-[10px] uppercase tracking-wider">By Payment Method</div>' + rows;
                breakdown.classList.remove('hidden');
            }
        })
        .catch(() => {});
}

// ============================================
// CUSTOMER FUNCTIONS
// ============================================
function searchCustomer() {
    const phone = document.getElementById('customerPhone')?.value.trim();
    if (!phone) {
        showToast('Enter a phone number', 'warning');
        return;
    }
    
    fetch(`../ajax/get_customer.php?phone=${encodeURIComponent(phone)}`)
        .then(r => r.json())
        .then(data => {
            const info = document.getElementById('customerInfo');
            if (data.success && data.customer) {
                window.selectedCustomer = data.customer;
                selectedCustomer = window.selectedCustomer;
                _saveState();
                document.getElementById('customerName').textContent = data.customer.name || '';
                document.getElementById('customerPoints').textContent = data.customer.loyalty_points || 0;
                document.getElementById('customerBalance').textContent = data.customer.credit_limit ? formatCurrency(data.customer.credit_limit) : 'N/A';
                document.getElementById('customerTotalSpent').textContent = data.customer.total_spent ? formatCurrency(data.customer.total_spent) : 'N/A';
                info.style.display = 'block';
                showToast(`Customer found: ${data.customer.name}`, 'success');
                
                // Dispatch event for advanced modules
                document.dispatchEvent(new CustomEvent('customer:selected', { detail: { customer: data.customer } }));
            } else {
                window.selectedCustomer = null;
                selectedCustomer = null;
                info.style.display = 'none';
                showToast(data.message || 'Customer not found', 'warning');
            }
        })
        .catch(() => showToast('Error searching customer', 'error'));
}

function saveCustomer() {
    const phone = document.getElementById('customerPhone')?.value.trim();
    if (!phone) {
        showToast('Enter a phone number first', 'error');
        return;
    }
    if (!window.selectedCustomer) {
        window.selectedCustomer = { id: null, name: 'Walk-in Customer', phone: phone, loyalty_points: 0 };
        selectedCustomer = window.selectedCustomer;
    }
    _saveState();
    showToast(`Customer set: ${window.selectedCustomer.name || phone}`, 'success');
    closeModal('customerModal');
}

// ============================================
// RECEIPT FUNCTIONS
// ============================================
function showReceipt(saleId) {
    const frame = document.getElementById('receiptFrame');
    if (frame) frame.src = `receipts/receipt.php?id=${encodeURIComponent(saleId)}`;
    showModal('receiptModal');
}

function showReceiptWithData(receipt) {
    const frame = document.getElementById('receiptFrame');
    if (frame && receipt) {
        const html = buildReceiptHtml(receipt);
        frame.srcdoc = html;
    }
    showModal('receiptModal');
}

function showReceiptWithItems(items, paymentMethod, invoiceNumber, amountReceived, change) {
    const subtotal = items.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const tax = subtotal * (CONFIG.taxRate / 100);
    const total = subtotal + tax;
    const receipt = {
        invoice_number: invoiceNumber || 'Receipt',
        date: new Date().toLocaleString(),
        items: items.map(item => ({ name: item.name, quantity: item.qty, price: item.price, subtotal: item.price * item.qty, note: item.note || '' })),
        subtotal: subtotal,
        discount: 0,
        tax: tax,
        total: total,
        payment_method: paymentMethod,
        split_payments: [],
        amount_received: amountReceived != null ? amountReceived : total,
        change: change != null ? change : 0
    };
    showReceiptWithData(receipt);
}

function buildReceiptHtml(receipt) {
    const itemsHtml = (receipt.items || []).map(item => `
        <div style="display:flex;justify-content:space-between;margin-bottom:2px;font-size:10pt;">
            <span style="width:60%;word-break:break-word;">${escapeHtml(item.name)} <span style="color:#555;">x${item.quantity}</span></span>
            <span style="width:38%;text-align:right;">${formatCurrency(item.subtotal)}</span>
        </div>
        ${item.note ? `<div style="font-size:8.5pt;color:#777;padding-left:4mm;margin-bottom:2px;">↳ ${escapeHtml(item.note)}</div>` : ''}
    `).join('');

    const logoHtml = CONFIG.logoUrl
        ? `<img src="${CONFIG.logoUrl}" style="max-height:18mm;max-width:60mm;object-fit:contain;display:block;margin:0 auto 2mm;" alt="Logo">`
        : '';
    const splitHtml = (receipt.split_payments || []).length > 1
        ? (receipt.split_payments.map(sp => `<div style="display:flex;justify-content:space-between;font-size:9pt;"><span>${escapeHtml(sp.method||'').toUpperCase()}</span><span>${formatCurrency(sp.amount)}</span></div>`).join(''))
        : `<div style="font-weight:bold;text-align:center;letter-spacing:1px;">PAID: ${escapeHtml((receipt.payment_method||'cash').toUpperCase())}</div>`;

    return `<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Receipt</title>
<style>
    @page { size: 80mm auto; margin: 0; }
    * { box-sizing: border-box; }
    body { font-family: 'Courier New', monospace; background: white; margin: 0; padding: 0; width: 80mm; }
    .receipt { width: 80mm; padding: 4mm 5mm 8mm; font-size: 10pt; line-height: 1.4; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .dash { border-top: 1px dashed #000; margin: 2mm 0; }
    .row { display: flex; justify-content: space-between; margin-bottom: 1px; font-size: 9.5pt; }
    .total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 12pt; margin-top: 1mm; }
    .store-name { font-size: 14pt; font-weight: bold; letter-spacing: 0.5px; }
    .sub-info { font-size: 8.5pt; color: #444; }
    @media print {
        body { width: 80mm; }
        .no-print { display: none !important; }
    }
</style>
</head>
<body onload="window.print()">
<div class="receipt">
    <div class="center" style="margin-bottom:2mm;">
        ${logoHtml}
        <div class="store-name">${escapeHtml(CONFIG.companyName)}</div>
        <div class="sub-info">${escapeHtml(CONFIG.branchName)}</div>
        ${CONFIG.branchAddress ? `<div class="sub-info">${escapeHtml(CONFIG.branchAddress)}</div>` : ''}
        ${CONFIG.branchPhone ? `<div class="sub-info">Tel: ${escapeHtml(CONFIG.branchPhone)}</div>` : ''}
        ${CONFIG.vatNumber ? `<div class="sub-info">VAT/TIN: ${escapeHtml(CONFIG.vatNumber)}</div>` : ''}
    </div>
    <div class="dash"></div>
    <div class="row"><span>Invoice:</span><span>${escapeHtml(receipt.invoice_number || '-')}</span></div>
    <div class="row"><span>Date:</span><span>${escapeHtml(receipt.date || new Date().toLocaleString())}</span></div>
    ${receipt.customer_name && receipt.customer_name !== 'Walk-in Customer' ? `<div class="row"><span>Customer:</span><span>${escapeHtml(receipt.customer_name)}</span></div>` : ''}
    <div class="dash"></div>
    ${itemsHtml || '<div class="center sub-info">No items</div>'}
    <div class="dash"></div>
    <div class="row"><span>Subtotal</span><span>${formatCurrency(receipt.subtotal)}</span></div>
    ${receipt.discount > 0 ? `<div class="row"><span>Discount</span><span style="color:#059669;">-${formatCurrency(receipt.discount)}</span></div>` : ''}
    <div class="row"><span>Tax (${CONFIG.taxRate}%)</span><span>${formatCurrency(receipt.tax)}</span></div>
    <div class="dash"></div>
    <div class="total-row"><span>TOTAL</span><span>${formatCurrency(receipt.total)}</span></div>
    <div class="dash"></div>
    ${splitHtml}
    ${(receipt.amount_received != null && receipt.amount_received > 0 && (receipt.split_payments||[]).length <= 1) ? `
    <div class="row"><span>Cash Received</span><span>${formatCurrency(receipt.amount_received)}</span></div>
    <div class="row bold" style="font-size:11pt;"><span>Change</span><span style="color:${(receipt.change||0) > 0 ? '#059669' : '#111'};">${formatCurrency(receipt.change || 0)}</span></div>` : ''}
    <div class="dash"></div>
    <div class="center" style="margin-top:2mm;font-size:9pt;">${escapeHtml(CONFIG.receiptFooter)}</div>
    <div class="center sub-info" style="margin-top:1mm;">Served by: ${escapeHtml(receipt.cashier || '')}</div>
</div>
</body>
    </html>`;
}

function printReceipt() {
    const frame = document.getElementById('receiptFrame');
    if (frame && frame.contentWindow) {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    }
}

// ============================================
// DISCOUNTS & VOUCHERS
// ============================================
function applyVoucher() {
    const code = document.getElementById('voucherInput')?.value.trim();
    if (!code) {
        showToast('Enter a voucher code', 'warning');
        return;
    }
    
    fetch(`../ajax/validate_voucher.php?code=${encodeURIComponent(code)}&branch_id=${CONFIG.branchId}`)
        .then(r => r.json())
        .then(data => {
            if (data.valid) {
                appliedVoucher = data.voucher;
                appliedDiscount = { type: data.voucher.type, value: data.voucher.value, id: data.voucher.id };
                _saveState();
                updateCartDisplay();
                showToast(`Voucher applied: ${data.voucher.description || code}`, 'success');
            } else {
                showToast(data.message || 'Invalid voucher', 'error');
            }
        })
        .catch(() => showToast('Error validating voucher', 'error'));
}

// ============================================
// HELD SALES FUNCTIONS
// ============================================
function holdSale() {
    if (window.cart.length === 0) {
        showToast('Cart is empty', 'warning');
        return;
    }
    
    const saleData = {
        items: window.cart,
        total: updateTotals(),
        customer_name: window.selectedCustomer?.name || 'Walk-in',
        table: document.getElementById('tableNumber')?.value || ''
    };
    
    fetch('../ajax/hold_sale.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=save&data=${encodeURIComponent(JSON.stringify(saleData))}&branch_id=${CONFIG.branchId}&bt_id=${CONFIG.businessTypeId}&csrf_token=${CONFIG.csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            clearCart();
            updateHeldBadge(+1);
            showToast('Order held successfully', 'success');
            closeModal('heldModal');
        } else {
            showToast(data.error || 'Failed to hold order', 'error');
        }
    })
    .catch(() => showToast('Error holding order', 'error'));
}

function loadHeldSales() {
    const listEl = document.getElementById('heldList');
    if (!listEl) return;
    listEl.innerHTML = '<div class="text-center py-8 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>';
    
    fetch(`../ajax/hold_sale.php?action=list&bt_id=${CONFIG.businessTypeId}&csrf_token=${CONFIG.csrfToken}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.sales && data.sales.length) {
                listEl.innerHTML = data.sales.map(sale => {
                    const preview = (sale.items_preview || []).map(i => escapeHtml(i.name)).join(', ');
                    return `
                    <div class="bg-slate-700/60 border border-slate-600/50 rounded-xl p-3 mb-2">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="text-white font-semibold text-sm">${escapeHtml(sale.customer_name || 'Walk-in')}</div>
                                <div class="text-slate-400 text-xs mt-0.5"><i class="fas fa-clock mr-1"></i>${escapeHtml(sale.formatted_date || '')} ${escapeHtml(sale.formatted_time || '')}</div>
                            </div>
                            <div class="text-amber-400 font-bold text-sm">${formatCurrency(sale.total || 0)}</div>
                        </div>
                        <div class="text-slate-400 text-xs mb-3 truncate"><i class="fas fa-box mr-1"></i>${sale.item_count || 0} item${sale.item_count != 1 ? 's' : ''}${preview ? ' &mdash; ' + preview : ''}</div>
                        <div class="flex gap-2">
                            <button onclick="restoreHeldSale(${sale.id})"
                                class="flex-1 flex items-center justify-center gap-1.5 py-1.5 bg-amber-500 hover:bg-amber-400 active:scale-95 text-slate-900 font-semibold text-xs rounded-lg transition-all">
                                <i class="fas fa-play"></i> Restore to Cart
                            </button>
                            <button onclick="deleteHeldSale(${sale.id}, this)"
                                class="w-8 flex items-center justify-center py-1.5 bg-slate-600 hover:bg-red-500/80 active:scale-95 text-slate-300 hover:text-white text-xs rounded-lg transition-all" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>`;
                }).join('');
            } else {
                listEl.innerHTML = '<div class="text-center py-10 text-slate-500"><i class="fas fa-inbox text-3xl mb-2 block"></i>No held orders</div>';
            }
        })
        .catch(() => {
            listEl.innerHTML = '<div class="text-center py-8 text-red-400"><i class="fas fa-exclamation-triangle"></i><p class="mt-2">Error loading held orders</p></div>';
        });
}

function restoreHeldSale(id) {
    fetch(`../ajax/hold_sale.php?action=restore&id=${id}&csrf_token=${CONFIG.csrfToken}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.sale_data) {
                const saleData = typeof data.sale_data === 'string' ? JSON.parse(data.sale_data) : data.sale_data;
                const sale = typeof saleData === 'string' ? JSON.parse(saleData) : saleData;
                if (sale && sale.items && sale.items.length) {
                    cart = sale.items;
                    updateCartDisplay();
                    closeModal('heldModal');
                    updateHeldBadge(-1);
                    showToast('Order restored to cart', 'success');
                } else {
                    showToast('Held order had no items', 'error');
                }
            } else {
                showToast(data.message || 'Failed to restore order', 'error');
            }
        })
        .catch(() => showToast('Error restoring order', 'error'));
}

function deleteHeldSale(id, btn) {
    if (!confirm('Delete this held order?')) return;
    if (btn) btn.disabled = true;
    fetch(`../ajax/hold_sale.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&id=${id}&csrf_token=${CONFIG.csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (btn) btn.closest('.bg-slate-700\\/60').remove();
            updateHeldBadge(-1);
            const listEl = document.getElementById('heldList');
            if (listEl && !listEl.querySelector('.bg-slate-700\\/60')) {
                listEl.innerHTML = '<div class="text-center py-10 text-slate-500"><i class="fas fa-inbox text-3xl mb-2 block"></i>No held orders</div>';
            }
            showToast('Held order deleted', 'success');
        } else {
            if (btn) btn.disabled = false;
            showToast(data.message || 'Failed to delete', 'error');
        }
    })
    .catch(() => { if (btn) btn.disabled = false; showToast('Error deleting order', 'error'); });
}

function updateHeldBadge(delta) {
    ['heldCountBadge', 'mobileHeldBadge'].forEach(id => {
        const badge = document.getElementById(id);
        if (!badge) return;
        const current = parseInt(badge.textContent) || 0;
        const next = Math.max(0, current + delta);
        badge.textContent = next;
        badge.classList.toggle('hidden', next <= 0);
    });
}

// ============================================
// REPORTS FUNCTIONS
// ============================================
function loadReports() {
    const btParam = CONFIG.businessTypeId > 0 ? `&bt_id=${CONFIG.businessTypeId}` : '';
    fetch(`../ajax/get_today_sales.php?branch_id=${CONFIG.branchId}${btParam}`)
        .then(r => r.json())
        .then(data => {
            const total = data.total || 0;
            const count = data.count || 0;
            const cash = data.cash_total || 0;
            const card = data.card_total || 0;
            const mpesa = data.mpesa_total || 0;
            
            document.getElementById('reportsContent').innerHTML = `
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-slate-700 rounded-lg p-3 text-center">
                        <div class="text-amber-400 text-xl font-bold">${formatCurrency(total)}</div>
                        <div class="text-slate-400 text-xs">Total Sales</div>
                    </div>
                    <div class="bg-slate-700 rounded-lg p-3 text-center">
                        <div class="text-emerald-400 text-xl font-bold">${count}</div>
                        <div class="text-slate-400 text-xs">Transactions</div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Cash</span><span class="text-white">${formatCurrency(cash)}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Card</span><span class="text-white">${formatCurrency(card)}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-slate-400">M-Pesa</span><span class="text-white">${formatCurrency(mpesa)}</span></div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-700">
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Today's Profit</span><span class="text-white">${formatCurrency(data.profit || 0)}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Margin</span><span class="text-emerald-400">${data.margin || 0}%</span></div>
                </div>
            `;
        })
        .catch(() => {
            document.getElementById('reportsContent').innerHTML = '<div class="text-center py-8 text-red-400"><i class="fas fa-exclamation-triangle"></i><p class="mt-2">Error loading reports</p></div>';
        });
}

function refreshTodayStats() {
    const btParam = CONFIG.businessTypeId > 0 ? `&bt_id=${CONFIG.businessTypeId}` : '';
    fetch(`../ajax/get_today_sales.php?branch_id=${CONFIG.branchId}${btParam}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const totalEl = document.getElementById('todaySalesValue');
                const countEl = document.getElementById('todaySalesCount');
                if (totalEl) totalEl.textContent = formatCurrency(data.total || 0);
                if (countEl) countEl.innerHTML = `<i class="fas fa-receipt"></i> ${data.sale_count || 0}`;
            }
        })
        .catch(() => {});
}

// ============================================
// CALCULATOR FUNCTIONS
// ============================================
let calcCurrent = '0';
let calcPrev = null;
let calcOperator = null;

function updateCalcDisplay() {
    const display = document.getElementById('calcDisplay');
    if (display) display.textContent = calcCurrent;
}

function calcInput(num) {
    if (calcCurrent === '0' && num !== '.') calcCurrent = num;
    else calcCurrent += num;
    updateCalcDisplay();
}

function calcSetOperator(op) {
    if (calcPrev !== null) calcCalculate();
    calcPrev = parseFloat(calcCurrent);
    calcOperator = op;
    calcCurrent = '0';
}

function calcCalculate() {
    if (calcPrev === null || calcOperator === null) return;
    const current = parseFloat(calcCurrent);
    let result;
    switch (calcOperator) {
        case '+': result = calcPrev + current; break;
        case '-': result = calcPrev - current; break;
        case '*': result = calcPrev * current; break;
        case '/': result = calcPrev / current; break;
        case '%': result = calcPrev % current; break;
        default: return;
    }
    calcCurrent = result.toString();
    calcPrev = null;
    calcOperator = null;
    updateCalcDisplay();
}

function calcClear() {
    calcCurrent = '0';
    calcPrev = null;
    calcOperator = null;
    updateCalcDisplay();
}

function copyCalcResult() {
    const result = document.getElementById('calcDisplay')?.textContent || '0';
    navigator.clipboard.writeText(result).then(() => showToast('Copied: ' + result, 'success'));
}

// Calculator event delegation
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.calc-btn');
    if (!btn) return;
    
    if (btn.dataset.action === 'clear') calcClear();
    else if (btn.dataset.action === 'number') calcInput(btn.dataset.num);
    else if (btn.dataset.action === 'operator') calcSetOperator(btn.dataset.op);
    else if (btn.dataset.action === 'equals') calcCalculate();
});

// Keypad for payment modal
document.querySelectorAll('.keypad-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const value = btn.dataset.value;
        const input = document.getElementById('amountReceived');
        if (!input) return;
        
        if (value === 'C') {
            input.value = '';
        } else if (value === '.') {
            if (!input.value.includes('.')) input.value += '.';
        } else {
            input.value += value;
        }
        calculateChange();
    });
});

document.querySelectorAll('.quick-cash-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const amount = btn.dataset.amount;
        const input = document.getElementById('amountReceived');
        if (!input) return;
        
        if (amount === '0') {
            input.value = updateTotals().toFixed(2);
        } else {
            input.value = amount;
        }
        calculateChange();
    });
});

// ============================================
// AI RECOMMENDATIONS
// ============================================
let _aiDebounceTimer = null;

function loadAIRecommendations() {
    clearTimeout(_aiDebounceTimer);
    _aiDebounceTimer = setTimeout(_fetchAIRecommendations, 800);
}

function _fetchAIRecommendations() {
    const strip = document.getElementById('aiSuggestionsStrip');
    if (window.cart.length === 0) {
        if (strip) strip.classList.add('hidden');
        return;
    }
    const cartItems = window.cart.map(i => ({ id: i.id }));
    const cartProductIds = window.cart.map(i => i.id);
    fetch('../ajax/ai_recommendations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cart_items: cartItems, limit: 5 })
    })
    .then(r => r.json())
    .then(data => {
        if (!strip) return;
        if (data.success && data.recommendations && data.recommendations.length > 0) {
            const filtered = data.recommendations.filter(p => !cartProductIds.includes(p.id));
            if (!filtered.length) { strip.classList.add('hidden'); return; }
            const reasonEl = document.getElementById('aiStripReason');
            const productsEl = document.getElementById('aiStripProducts');
            if (reasonEl) reasonEl.textContent = data.reason || 'Suggestions';
            if (productsEl) {
                productsEl.innerHTML = filtered.slice(0, 4).map(p => `
                    <button onclick="addRecommendedProduct(${p.id},'${escapeJsString(p.name)}',${p.price},${p.stock||999})"
                        class="flex-shrink-0 flex flex-col items-center gap-0.5 p-1.5 bg-slate-800 hover:bg-amber-500/20 active:scale-95 border border-slate-700/60 hover:border-amber-500/40 rounded-lg transition-all w-14 text-center">
                        <i class="fas fa-plus-circle text-amber-400 text-xs"></i>
                        <span class="text-white text-[9px] leading-tight line-clamp-2">${escapeHtml(p.name)}</span>
                        <span class="text-amber-400 text-[9px] font-bold">${formatCurrency(p.price)}</span>
                    </button>
                `).join('');
            }
            strip.classList.remove('hidden');
        } else {
            strip.classList.add('hidden');
        }
    })
    .catch(() => { if (strip) strip.classList.add('hidden'); });
}

function addRecommendedProduct(id, name, price, stock) {
    addToCart({ dataset: { productId: id, productName: name, productPrice: price, productStock: stock } });
}

// ============================================
// LOYALTY POINTS
// ============================================
const POINTS_PER_CURRENCY = 1;      // 1 point per 1 currency unit spent
const CURRENCY_PER_POINT  = 0.01;   // 1 point = 0.01 in discount (adjust as needed)
let _loyaltyRedeemDiscount = 0;
let _loyaltyPointsToRedeem = 0;

function showLoyaltyRowIfCustomer() {
    const row = document.getElementById('loyaltyRedeemRow');
    if (!row) return;
    if (window.selectedCustomer && window.selectedCustomer.loyalty_points > 0) {
        const pts = parseInt(window.selectedCustomer.loyalty_points) || 0;
        const val = (pts * CURRENCY_PER_POINT).toFixed(2);
        document.getElementById('loyaltyPointsDisplay').textContent = pts;
        document.getElementById('loyaltyValueDisplay').textContent = val;
        document.getElementById('loyaltyRedeemInput').max = pts;
        row.classList.remove('hidden');
    } else {
        row.classList.add('hidden');
        _loyaltyRedeemDiscount = 0;
        _loyaltyPointsToRedeem = 0;
    }
}

// ============================================
// COUPON CODE FUNCTIONS
// ============================================
function applyCouponCode() {
    const input  = document.getElementById('couponCodeInput');
    const msgEl  = document.getElementById('couponMsg');
    const applyBtn = document.getElementById('couponApplyBtn');
    const clearBtn = document.getElementById('couponClearBtn');
    const code   = input?.value?.trim();

    if (!code) return;

    const { subtotal } = getPricingBreakdown();

    applyBtn.disabled = true;
    applyBtn.textContent = '...';
    msgEl.className = 'mt-1 text-[10px]';
    msgEl.textContent = 'Checking…';
    msgEl.classList.remove('hidden');

    fetch('../ajax/apply_coupon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CONFIG.csrfToken },
        body: JSON.stringify({
            code,
            subtotal,
            customer_id: window.selectedCustomer?.id || '',
            csrf_token: CONFIG.csrfToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.valid) {
            appliedDiscount = {
                id:     data.discount.id,
                name:   data.discount.name,
                code:   data.discount.code,
                type:   data.discount.type,
                value:  data.discount.value,
                amount: data.discount.amount
            };
            _saveState();
            updateCartDisplay();
            updateTotals();
            msgEl.className = 'mt-1 text-[10px] text-emerald-400';
            msgEl.textContent = `✓ ${data.discount.name} applied (–${formatCurrency(data.discount.amount)})`;
            clearBtn.classList.remove('hidden');
            applyBtn.classList.add('hidden');
            input.readOnly = true;
        } else {
            msgEl.className = 'mt-1 text-[10px] text-red-400';
            msgEl.textContent = data.error || 'Invalid coupon';
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
        }
    })
    .catch(() => {
        msgEl.className = 'mt-1 text-[10px] text-red-400';
        msgEl.textContent = 'Error checking coupon. Try again.';
        applyBtn.disabled = false;
        applyBtn.textContent = 'Apply';
    });
}

function clearCoupon() {
    appliedDiscount = null;
    _saveState();
    const input    = document.getElementById('couponCodeInput');
    const msgEl    = document.getElementById('couponMsg');
    const applyBtn = document.getElementById('couponApplyBtn');
    const clearBtn = document.getElementById('couponClearBtn');
    if (input)    { input.value = ''; input.readOnly = false; }
    if (msgEl)    { msgEl.textContent = ''; msgEl.classList.add('hidden'); }
    if (applyBtn) { applyBtn.disabled = false; applyBtn.textContent = 'Apply'; applyBtn.classList.remove('hidden'); }
    if (clearBtn) { clearBtn.classList.add('hidden'); }
    updateCartDisplay();
    updateTotals();
}

function applyLoyaltyRedeem() {
    const maxPts = parseInt(window.selectedCustomer?.loyalty_points || 0);
    const inputEl = document.getElementById('loyaltyRedeemInput');
    const msgEl = document.getElementById('loyaltyDiscountMsg');
    let pts = parseInt(inputEl?.value || 0);
    if (pts < 0) pts = 0;
    if (pts > maxPts) { pts = maxPts; if (inputEl) inputEl.value = maxPts; }
    _loyaltyPointsToRedeem = pts;
    _loyaltyRedeemDiscount = pts * CURRENCY_PER_POINT;
    if (msgEl) {
        if (pts > 0) {
            msgEl.textContent = `${pts} pts = ${CONFIG.currency} ${_loyaltyRedeemDiscount.toFixed(2)} discount`;
            msgEl.classList.remove('hidden');
        } else {
            msgEl.classList.add('hidden');
        }
    }
    updatePaymentModalTotals();
}

function getPricingBreakdownWithLoyalty() {
    const base = getPricingBreakdown();
    const loyaltyDisc = Math.min(_loyaltyRedeemDiscount, base.total);
    return { ...base, loyaltyDiscount: loyaltyDisc, finalTotal: Math.max(0, base.total - loyaltyDisc) };
}

function awardLoyaltyPoints(soldCart, totalPaid) {
    if (!window.selectedCustomer || !window.selectedCustomer.id) return;
    const points = Math.floor(totalPaid * POINTS_PER_CURRENCY);
    if (points <= 0) return;
    fetch('../ajax/get_customer_loyalty.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=award&customer_id=${window.selectedCustomer.id}&points=${points}&csrf_token=${CONFIG.csrfToken}`
    }).catch(() => {});
}

// ============================================
// STOCK + GRID HELPERS
// ============================================
function updateGridStockAfterSale(soldItems) {
    soldItems.forEach(item => {
        const p = allProducts.find(x => x.id === item.id);
        if (p) p.stock = Math.max(0, p.stock - item.qty);
        const card = document.querySelector(`.product-card[data-product-id="${item.id}"]`);
        if (card) {
            const newStock = Math.max(0, parseInt(card.dataset.productStock ?? 999, 10) - item.qty);
            card.dataset.productStock = newStock;
            if (newStock <= 0) {
                card.classList.remove('pc-in', 'pc-low');
                card.classList.add('pc-oos');
                const dot = card.querySelector('.s-dot');
                if (dot) { dot.className = 's-dot s-dot-red'; dot.title = 'Out of stock'; }
            }
        }
    });
}

// ============================================
// QTY POPUP (tap qty number to set directly)
// ============================================
function showQtyPopup(index) {
    const item = window.cart[index];
    if (!item) return;
    const current = item.qty;
    const val = prompt(`Qty for "${item.name}":`, current);
    if (val === null) return;
    const n = parseInt(val);
    if (!n || n < 1) { showToast('Invalid quantity', 'error'); return; }
    if (n > item.stock) { showToast('Exceeds stock (' + item.stock + ')', 'error'); return; }
    item.qty = n;
    updateCartDisplay();
}

// ============================================
// ITEM NOTES
// ============================================
function toggleItemNote(index) {
    const row = document.getElementById('noteRow_' + index);
    if (!row) return;
    row.classList.toggle('hidden');
    if (!row.classList.contains('hidden')) row.querySelector('input')?.focus();
}

function saveItemNote(index, val) {
    if (window.cart[index]) { window.cart[index].note = val; _saveState(); }
}

// ============================================
// LAST SALE
// ============================================
let _lastSaleId = null;

function viewLastSale() {
    if (!_lastSaleId) { showToast('No sale yet this session', 'info'); return; }
    showReceipt(_lastSaleId);
}

function trackBehavior(completedCart) {
    if (!completedCart || completedCart.length === 0) return;
    const productIds = completedCart.map(i => i.id);
    const total = completedCart.reduce((s, i) => s + (i.price * i.qty), 0);
    fetch('../ajax/ai_track_behavior.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_ids: productIds, total_amount: total })
    }).catch(() => {});
}

// ============================================
// PRICE OVERRIDE
// ============================================
let currentOverrideIndex = null;

function showPriceOverrideModal(index) {
    const item = window.cart[index];
    if (!item) return;
    currentOverrideIndex = index;
    document.getElementById('overrideProductName').textContent = item.name;
    document.getElementById('overrideCurrentPrice').textContent = formatCurrency(item.price);
    document.getElementById('overridePriceInput').value = '';
    document.getElementById('adminPinInput').value = '';
    showModal('priceOverrideModal');
}

function confirmPriceOverride() {
    const newPrice = parseFloat(document.getElementById('overridePriceInput')?.value);
    const pin = document.getElementById('adminPinInput')?.value;
    
    if (isNaN(newPrice) || newPrice <= 0) {
        showToast('Enter a valid price', 'error');
        return;
    }
    if (!pin) {
        showToast('Admin PIN required', 'error');
        return;
    }
    
    fetch('../ajax/override_price.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=override&price=${newPrice}&pin=${encodeURIComponent(pin)}&csrf_token=${CONFIG.csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (currentOverrideIndex !== null && window.cart[currentOverrideIndex]) {
                window.cart[currentOverrideIndex].price = newPrice;
                updateCartDisplay();
                showToast('Price updated successfully', 'success');
                closeModal('priceOverrideModal');
            }
        } else {
            showToast(data.error || 'Override failed', 'error');
        }
    })
    .catch(() => showToast('Error processing override', 'error'));
}

// Override keypad
document.querySelectorAll('.override-keypad-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const value = btn.dataset.value;
        const input = document.getElementById('overridePriceInput');
        if (!input) return;
        
        if (value === 'C') {
            input.value = '';
        } else if (value === '.') {
            if (!input.value.includes('.')) input.value += '.';
        } else {
            input.value += value;
        }
    });
});

// ============================================
// BUSINESS TYPE FEATURES
// ============================================
function togglePrescription(enabled) {
    const fields = document.getElementById('prescriptionFields');
    if (fields) fields.style.display = enabled ? 'block' : 'none';
}

function toggleAgeVerify(enabled) {
    const fields = document.getElementById('ageVerifyFields');
    if (fields) fields.style.display = enabled ? 'block' : 'none';
    const knob = document.getElementById('ageVerifyKnob');
    if (knob && knob.parentElement) {
        knob.style.transform = enabled ? 'translateX(20px)' : 'translateX(0)';
        knob.parentElement.style.background = enabled ? '#10B981' : '#374151';
    }
}

function applyTableNumber() {
    const tableNum = document.getElementById('tableNumber')?.value;
    if (tableNum) showToast(`Table ${tableNum} selected`, 'success');
}

function applyWeight() {
    const weight = parseFloat(document.getElementById('weightInput')?.value || 0);
    if (weight <= 0) {
        showToast('Enter a valid weight', 'warning');
        return;
    }
    const unit = document.getElementById('weightUnit')?.value || 'kg';
    if (window.cart.length > 0) {
        const lastItem = window.cart[window.cart.length - 1];
        showToast(`Weight ${weight}${unit} applied to ${lastItem.name}`, 'success');
    } else {
        showToast(`Weight ${weight}${unit} set`, 'success');
    }
}

function setWholesaleTier(tier) {
    const tierDef = (CONFIG.wholesaleTiers || []).find(t => t.value === tier);
    const tierLabel = tierDef
        ? (tierDef.discount > 0 ? `${tierDef.label} (${tierDef.discount}%)` : tierDef.label)
        : tier;
    const discountFraction = tierDef ? (tierDef.discount / 100) : 0;
    const badge = document.getElementById('tierBadge');
    if (badge) {
        badge.textContent = tierLabel;
        badge.style.display = tier && tier !== 'retail' ? 'inline-block' : 'none';
    }
    cart.forEach(item => {
        item.wholesaleTier = tier;
        item.wholesaleDiscount = discountFraction;
    });
    updateCartDisplay();
    showToast(`Pricing tier: ${tierLabel}`, 'info');
}

function setAppointment(appointmentId) {
    if (appointmentId === 'new') {
        showToast('Opening appointment scheduler...', 'info');
        return;
    }
    if (appointmentId) {
        const select = document.getElementById('appointmentSelect');
        const option = select?.options[select.selectedIndex];
        if (option) {
            showToast(`Appointment: ${option.dataset.customer || 'Customer'}`, 'success');
        }
    }
}

function setStaffMember(staffId) {
    if (!staffId) {
        document.getElementById('staffDisplay').style.display = 'none';
        return;
    }
    const select = document.getElementById('staffSelect');
    const option = select?.options[select.selectedIndex];
    const name = option ? option.text : '';
    document.getElementById('staffName').textContent = name;
    document.getElementById('staffDisplay').style.display = 'inline-flex';
    showToast(`Staff: ${name}`, 'success');
}

function setSerialWarranty() {
    const serial = document.getElementById('serialNumber')?.value;
    const warranty = document.getElementById('warrantyPeriod')?.value;
    if (serial) showToast(`Serial: ${serial}`, 'success');
    if (warranty) showToast(`Warranty: ${warranty}`, 'success');
}

// ============================================
// UI HELPERS
// ============================================
function goHome() {
    if (window.cart.length > 0 && !confirm('You have items in cart. Leave anyway?')) return;
    window.location.href = '../dashboard/home.php';
}

function togglePosCart() {
    const cartPanel = document.querySelector('.cart-panel');
    const backdrop  = document.getElementById('cartBackdrop');
    if (!cartPanel) return;
    const isOpen = cartPanel.classList.toggle('open');
    if (backdrop) backdrop.classList.toggle('open', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}

function closePosCart() {
    const cartPanel = document.querySelector('.cart-panel');
    const backdrop  = document.getElementById('cartBackdrop');
    if (cartPanel) cartPanel.classList.remove('open');
    if (backdrop)  backdrop.classList.remove('open');
    document.body.style.overflow = '';
}

function updatePosCartFabBadge() {
    const count = window.cart.reduce((sum, item) => sum + item.qty, 0);
    const badge = document.getElementById('posCartFabBadge');
    if (badge) {
        badge.textContent = count;
        badge.classList.toggle('hidden', count <= 0);
    }
}

function showConfetti() {
    const colors = ['#fbbf24', '#10b981', '#3b82f6', '#8b5cf6', '#ef4444'];
    for (let i = 0; i < 80; i++) {
        const conf = document.createElement('div');
        conf.className = 'confetti-piece';
        conf.style.cssText = `left: ${Math.random() * 100}%; background: ${colors[Math.floor(Math.random() * colors.length)]}; width: ${6 + Math.random() * 6}px; height: ${6 + Math.random() * 6}px; top: -10px; animation-delay: ${Math.random() * 0.5}s;`;
        document.body.appendChild(conf);
        setTimeout(() => conf.remove(), 2000);
    }
}

function openCalculator() {
    calcClear();
    showModal('calculatorModal');
}

function closeCalculator() {
    closeModal('calculatorModal');
}

// ============================================
// HELP OVERLAY
// ============================================
function openPosHelp() {
    showModal('posHelpOverlay');
}

function closePosHelp() {
    closeModal('posHelpOverlay');
}

// ============================================
// EVENT LISTENERS & INITIALIZATION
// ============================================
// Persist business type selection across page refreshes
(function() {
    const LS_BT_KEY = 'pos_bt_id_' + CONFIG.companyId;
    const urlParams = new URLSearchParams(window.location.search);
    const btInUrl = urlParams.get('bt_id');
    if (btInUrl && parseInt(btInUrl) > 0) {
        // URL has a valid bt_id — save it and continue
        try { localStorage.setItem(LS_BT_KEY, btInUrl); } catch(e) {}
    } else if (CONFIG.businessTypeId > 0) {
        // PHP resolved a type (from branch) — store it
        try { localStorage.setItem(LS_BT_KEY, CONFIG.businessTypeId); } catch(e) {}
    } else {
        // No bt_id in URL and PHP resolved nothing — check localStorage
        const stored = localStorage.getItem(LS_BT_KEY);
        if (stored && parseInt(stored) > 0) {
            urlParams.set('bt_id', stored);
            window.location.replace('?' + urlParams.toString());
        }
    }
})();

document.addEventListener('DOMContentLoaded', () => {
    _loadState();
    if (window.cart.length > 0) updateCartDisplay();
    applyProductFilters();
    
    // Search input handler
    const searchBox = document.getElementById('searchBox');
    if (searchBox) searchBox.addEventListener('input', (e) => searchProducts(e.target.value));
    
    // Amount received change handler with real-time update
    const amountReceived = document.getElementById('amountReceived');
    if (amountReceived) {
        amountReceived.addEventListener('input', function() {
            calculateChange();
        });
    }
    
    // Branch switching
    const branchSelect = document.getElementById('branchSelect');
    if (branchSelect) {
        branchSelect.addEventListener('change', (e) => {
            const branchId = e.target.value;
            if (branchId != CONFIG.branchId && confirm('Switch branch? Cart will be cleared.')) {
                const btParam = CONFIG.businessTypeId > 0 ? `&bt_id=${CONFIG.businessTypeId}` : '';
                window.location.href = `?branch=${branchId}&branch_token=${CONFIG.csrfToken}${btParam}`;
            } else if (branchId != CONFIG.branchId) {
                e.target.value = CONFIG.branchId;
            }
        });
    }
    
    updatePosCartFabBadge();
});

function goHome() {
    window.location.href = '<?php echo function_exists('base_url') ? base_url('dashboard/home.php') : '../dashboard/home.php'; ?>';
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.target.matches('input, textarea, select')) return;
    
    if (e.key === 'F1') {
        e.preventDefault();
        document.getElementById('searchBox')?.focus();
    } else if (e.key === 'F2') {
        e.preventDefault();
        if (window.cart.length > 0) showPaymentModal();
    } else if (e.key === 'F4') {
        e.preventDefault();
        holdSale();
    } else if (e.key === 'F6') {
        e.preventDefault();
        showModal('customerModal');
    } else if (e.key === 'F8') {
        e.preventDefault();
        clearCart();
    } else if (e.altKey && e.key === 'c') {
        e.preventDefault();
        openCalculator();
    } else if (e.altKey && e.key === 'h') {
        e.preventDefault();
        goHome();
    } else if (e.key === 'Escape') {
        closeAllModals();
    } else if (e.key === '?' || (e.shiftKey && e.key === '/')) {
        e.preventDefault();
        openPosHelp();
    } else if ((e.key === 'c' || e.key === 'C') && window.innerWidth <= 768) {
        e.preventDefault();
        togglePosCart();
    }
});

// Real-time stock sync (SSE)
if (window.EventSource) {
    (function startStockSync() {
        let sse = null;
        let lastEventId = 0;
        let reconnectDelay = 5000;
        let retryCount = 0;
        const maxRetries = 3;
        
        function connect() {
            if (retryCount >= maxRetries) return;
            const url = `../api/stock-events.php?last_id=${lastEventId}`;
            sse = new EventSource(url);
            
            sse.addEventListener('stock_change', (e) => {
                const ev = JSON.parse(e.data);
                lastEventId = parseInt(e.lastEventId, 10) || lastEventId;
                const p = allProducts.find(x => x.id === ev.product_id);
                if (p) p.stock = ev.new_qty;
                window.cart.forEach(item => { if (item.id === ev.product_id) item.stock = ev.new_qty; });
                const card = document.querySelector(`.product-card[data-product-id="${ev.product_id}"]`);
                if (card) {
                    card.setAttribute('data-product-stock', ev.new_qty);
                    const badge = card.querySelector('.absolute.top-2.right-2');
                    if (badge) {
                        badge.className = `absolute top-2 right-2 w-2 h-2 rounded-full ${ev.new_qty <= 0 ? 'bg-red-500' : ev.new_qty <= 5 ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'}`;
                    }
                }
                const cartItem = window.cart.find(c => c.id === ev.product_id);
                if (cartItem && ev.new_qty <= 0) showToast(`⚠️ ${p ? p.name : 'Item'} is out of stock!`, 'warning');
            });
            
            sse.onerror = () => {
                retryCount++;
                if (retryCount >= maxRetries) return;
                sse.close();
                setTimeout(connect, reconnectDelay);
                reconnectDelay = Math.min(reconnectDelay * 2, 60000);
            };
        }
        connect();
    })();
}

// Live clock
(function startClock() {
    const clockEl = document.getElementById('liveClock');
    const dateEl  = document.getElementById('liveDate');
    const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    function tick() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2,'0');
        const m = String(now.getMinutes()).padStart(2,'0');
        const s = String(now.getSeconds()).padStart(2,'0');
        if (clockEl) clockEl.textContent = h + ':' + m + ':' + s;
        if (dateEl)  dateEl.textContent  = days[now.getDay()] + ' ' + now.getDate() + ' ' + months[now.getMonth()];
    }
    tick();
    setInterval(tick, 1000);
})();

// ============================================
// QUICK USER SWITCH (SHARED TERMINAL)
// ============================================
function openQuickSwitch() {
    document.getElementById('quickSwitchUser').value = '';
    document.getElementById('quickSwitchPin').value = '';
    showModal('quickSwitchModal');
}

async function performQuickSwitch() {
    const userId = document.getElementById('quickSwitchUser').value;
    const pin = document.getElementById('quickSwitchPin').value;
    
    if (!userId || !pin) {
        showToast('Enter user ID and PIN', 'error');
        return;
    }
    
    try {
        const res = await fetch('../ajax/quick_switch_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `user_id=${encodeURIComponent(userId)}&pin=${encodeURIComponent(pin)}&csrf_token=${CONFIG.csrfToken}`
        });
        const data = await res.json();
        
        if (data.success) {
            // Clear cart before switching
            cart = [];
            localStorage.removeItem(_lsKey('cart'));
            updateCartDisplay();
            
            // Reload to refresh session with new user
            window.location.reload();
        } else {
            showToast(data.error || 'Switch failed', 'error');
        }
    } catch (e) {
        showToast('Error switching user', 'error');
    }
}

// Virtual keypad for PIN entry (handled in separate script block with modal)

// ============================================
// PRODUCT REFRESH & CACHE MANAGEMENT
// ============================================
async function refreshProducts() {
    const refreshBtn = document.getElementById('refreshBtn');
    const refreshIcon = document.getElementById('refreshIcon');
    const loading = document.getElementById('productsLoading');
    const errorDiv = document.getElementById('productsError');
    const emptyDiv = document.getElementById('productsEmpty');
    const grid = document.getElementById('productsGrid');
    
    // Show loading state
    refreshBtn.disabled = true;
    refreshIcon.classList.add('fa-spin');
    loading.classList.remove('hidden');
    errorDiv.classList.add('hidden');
    
    try {
        // Call cache clear endpoint
        const res = await fetch('../ajax/clear_product_cache.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `csrf_token=${CONFIG.csrfToken}`
        });
        
        const data = await res.json();
        
        if (data.success) {
            showToast('Products refreshed', 'success');
            // Reload page to get fresh products
            setTimeout(() => window.location.reload(), 500);
        } else {
            throw new Error(data.error || 'Failed to refresh');
        }
    } catch (e) {
        console.error('Refresh error:', e);
        errorDiv.classList.remove('hidden');
        showToast('Failed to refresh products', 'error');
    } finally {
        refreshBtn.disabled = false;
        refreshIcon.classList.remove('fa-spin');
        loading.classList.add('hidden');
    }
}

// Check if products loaded and show empty state if needed
(function checkProductsLoaded() {
    const grid = document.getElementById('productsGrid');
    const emptyDiv = document.getElementById('productsEmpty');
    
    if (grid && grid.children.length === 0 && emptyDiv) {
        emptyDiv.classList.remove('hidden');
    }
})();

</script>

<!-- Advanced POS Modules -->
<script src="<?php echo base_url('assets/js/pos-smart-checkout.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/pos-offline-advanced.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/pos-advanced-loyalty.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/pos-advanced-payments.js'); ?>"></script>

</body>
</html>