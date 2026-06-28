<?php
/**
 * ENTERPRISE POS SYSTEM - PURE TAILWIND EDITION
 * Complete POS with business types, branch management, shift handling
 * ZERO-TRUST TENANT ISOLATION - All data access is scoped to company/branch
 * 
 * @version 6.1 - FIXED: Missing CSS, JS endpoints, and class issues
 * @total_lines ~3900
 */

// ============================================
// SESSION START (MUST BE FIRST - before any output)
// ============================================
if (!defined('SESSION_COOKIE_PATH')) {
    define('SESSION_COOKIE_PATH', '/');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('jakababa_saas_sid');
    session_start();
}

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

// Security Headers
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
require_once __DIR__ . '/../../src/Events/HookManager.php';

// ---------------------------------------------------------------------------
// Plugin System (load panels & assets for POS)
// ---------------------------------------------------------------------------
$pluginManager = null;
$pluginPanels = [];
$pluginAssets = [];
if (class_exists('JDH\\POS\\Plugin\\PluginManager')) {
    global $pdo;
    if (!$pdo) { $pdo = function_exists('get_db_connection') ? get_db_connection() : null; }
    $pluginManager = new \JDH\POS\Plugin\PluginManager($pdo, $tenant_id);
    $pluginManager->loadActive();
    $pluginPanels = $pluginManager->getPosPanels();
    $pluginAssets = $pluginManager->getPosAssets();
}

function render_plugin_panels(string $position, array $panels): void {
    foreach ($panels as $panel) {
        if (($panel['position'] ?? '') !== $position) continue;
        $id = htmlspecialchars($panel['id'] ?? uniqid('plugin-'));
        $label = htmlspecialchars($panel['label'] ?? '');
        echo '<div class="plugin-panel plugin-panel--' . $position . '" data-plugin="' . htmlspecialchars($panel['plugin_slug'] ?? '') . '" data-panel="' . $id . '">';
        if ($label) echo '<div class="text-[10px] uppercase tracking-wider text-slate-500 mb-1">' . $label . '</div>';
        echo $panel['content'] ?? '';
        echo '</div>';
    }
}

// Auto-print labels toast
if (!empty($_SESSION['auto_labels_printed'])) {
    $count = (int)$_SESSION['auto_labels_printed'];
    $method = $_SESSION['auto_labels_method'] ?? 'pdf';
    $toastMsg = "Auto-printed {$count} labels via " . strtoupper($method);
    echo "<script>document.addEventListener('DOMContentLoaded', () => { if (typeof showToast === 'function') showToast('" . addslashes($toastMsg) . "', 'success'); });</script>";
    unset($_SESSION['auto_labels_printed'], $_SESSION['auto_labels_method']);
}

// CSRF Token
$_pos_csrf_token = generate_csrf_token();
$_SESSION['csrf_token_time'] = time();

// ============================================
// ZERO-TRUST TENANT CONTEXT
// ============================================
$tenant_id = $user_id = $branch_id = null;
$is_super_admin = false;
$user_role = '';

try {
    require_once __DIR__ . '/../../src/SecureTenantContext.php';
    $context = SecureTenantContext::getInstance();
    
    if ($context && $context->isValid()) {
        $tenant_id = $context->getCompanyId();
        $user_id = $context->getUserId();
        $branch_id = $context->getBranchId();
        $is_super_admin = $context->isSuperAdmin();
        $user_role = $context->getUserRole() ?? '';
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

// Permission check for POS access
if (!$is_super_admin && !check_permission('pos.access') && !check_permission('pos.sell')) {
    http_response_code(403);
    header('Location: ../dashboard/home.php?error=unauthorized');
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
// ============================================
try {
    $canValidateAnyBranch = $is_super_admin || (function_exists('check_permission') && check_permission('branches.view'));
    
    if ($canValidateAnyBranch) {
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

    if ($canValidateAnyBranch) {
        if (!$validation['branch_exists']) {
            error_log("ZERO-TRUST BLOCKED: Branch not found");
            session_destroy();
            header('Location: ../auth/login.php?error=zero_trust_blocked');
            exit;
        }
        $branch_active = $validation['branch_active'] ?? $validation['is_active'] ?? 0;
        if (!$branch_active) {
            error_log("ZERO-TRUST BLOCKED: Branch not active");
            session_destroy();
            header('Location: ../auth/login.php?error=zero_trust_blocked');
            exit;
        }
    } else {
        if (!$validation['branch_exists'] || !$validation['branch_active']) {
            error_log("ZERO-TRUST BLOCKED: Branch not found or inactive");
            session_destroy();
            header('Location: ../auth/login.php?error=zero_trust_blocked');
            exit;
        }
    }

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

// ============================================
// BOOTSTRAP SERVICE (real SaaS — tenant-scoped batch loader)
// ============================================
$bootstrap_data = [];
try {
    if (class_exists('App\Pos\PosBootstrapService')) {
        $boot = new \App\Pos\PosBootstrapService($pdo, (int)$tenant_id, (int)$branch_id);
        $bootstrap_data = $boot->bootstrap();
    }
} catch (Throwable $e) {
    error_log('PosBootstrapService failed: ' . $e->getMessage());
    // Falls back to individual queries below
}

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

if (!function_exists('is_super_admin')) {
    function is_super_admin() {
        global $is_super_admin;
        return $is_super_admin ?? false;
    }
}

if (!function_exists('base_url')) {
    function base_url($path = '') {
        static $base = null;
        if ($base === null) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
            $scriptDir = str_replace('\\', '/', $scriptDir);
            if ($scriptDir === '/') $scriptDir = '';
            $base = $protocol . '://' . $host . $scriptDir . '/';
        }
        return $base . ltrim($path, '/');
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

// Detect business type
$detected_bt = 'retail';
try {
    $override_bt_id = isset($_GET['bt_id']) ? (int)$_GET['bt_id'] : 0;
    if ($override_bt_id > 0) {
        $bt_ovr = $pdo->prepare("SELECT code FROM business_types WHERE id = ? AND is_active = 1 LIMIT 1");
        $bt_ovr->execute([$override_bt_id]);
        $bt_ovr_row = $bt_ovr->fetch(PDO::FETCH_ASSOC);
        if (!empty($bt_ovr_row['code'])) $detected_bt = $bt_ovr_row['code'];
    } else {
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
$_branch_csrf_valid = !empty($branch_token) && verify_csrf_token($branch_token);
if ($target_branch && $can_switch_branch && $_branch_csrf_valid) {
    foreach ($branches as $b) {
        if ((int)$b['id'] === $target_branch) {
            $_SESSION['branch_id']      = (int)$b['id'];
            $_SESSION['branch_name']    = $b['name'] ?? '';
            $_SESSION['current_branch'] = ['id' => (int)$b['id'], 'name' => $b['name'] ?? ''];
            session_write_close();
            $btParam = isset($_GET['bt_id']) ? '&bt_id=' . (int)$_GET['bt_id'] : '';
            header('Location: pos.php?switched=1' . $btParam);
            exit;
        }
    }
}

session_write_close();

// ============================================
// LOAD CATEGORIES (WITH CACHE)
// ============================================
$categories = [];
if (!empty($bootstrap_data['categories'])) {
    $categories = $bootstrap_data['categories'];
} else {
    try {
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
        $products = array_values(array_filter($products, fn($p) =>
            !isset($p['business_type_id']) || (int)$p['business_type_id'] === 0
            || is_null($p['business_type_id']) || (int)$p['business_type_id'] === $current_bt_id
        ));
    }
} else {
    // Products will be loaded via AJAX
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

// SALON: TODAY APPOINTMENTS
$today_appointments = [];
if (!empty($bt_features["appointment"])) {
    try {
        $stmt = $pdo->prepare("SELECT a.id, a.appointment_time, a.customer_id, a.service_name, a.status, c.name as customer_name FROM appointments a LEFT JOIN customers c ON c.id = a.customer_id WHERE a.tenant_id = ? AND a.branch_id = ? AND DATE(a.appointment_date) = CURDATE() AND a.status IN ('scheduled', 'confirmed') ORDER BY a.appointment_time LIMIT 20");
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
$branch_switch_token = $_pos_csrf_token;

// Wholesale tiers
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

// Warranty periods
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
    
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    screens: { 'xs': '400px' },
                    colors: {
                        accent: '#fbbf24',
                        'bg-dark': '#0f172a',
                        'border': '#374151',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-out',
                        'slide-in': 'slideIn 0.3s ease-out',
                        'pulse-slow': 'pulse 2s ease-in-out infinite',
                        'spin-slow': 'spin 1s linear infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(-10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        slideIn: {
                            '0%': { transform: 'translateX(100%)', opacity: '0' },
                            '100%': { transform: 'translateX(0)', opacity: '1' },
                        },
                    }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <style>
        /* ─── CSS Variables ─── */
        :root {
            --pos-header-height: 48px;
            --pos-nav-height: 56px;
            --pos-bg: #0f172a;
            --pos-accent: #fbbf24;
            --pos-border: #374151;
        }
        
        /* ─── Base Styles ─── */
        body {
            background: var(--pos-bg);
            font-family: 'Inter', sans-serif;
            overflow: hidden;
        }
        
        /* ─── Animations ─── */
        @keyframes fall {
            to { transform: translateY(100vh) rotate(720deg); }
        }
        .confetti-piece {
            position: absolute;
            animation: fall 2s linear forwards;
            pointer-events: none;
        }
        
        @keyframes slideIn {
            from { opacity:0; transform:translateX(8px); }
            to { opacity:1; transform:translateX(0); }
        }
        .cart-item {
            animation: slideIn 0.15s ease forwards;
        }
        
        /* ─── Scrollbar ─── */
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
            transition: background 0.2s;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: rgba(251,191,36,0.5);
        }
        
        /* ─── Number Input Fix ─── */
        input[type="number"]::-webkit-inner-spin-button, 
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type="number"] {
            -moz-appearance: textfield;
        }
        
        /* ─── Print Styles ─── */
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
        }
        
        /* ─── Product Cards ─── */
        .product-card {
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            cursor: pointer;
        }
        .product-card:active {
            transform: scale(0.94);
        }
        .product-card.processing {
            opacity: .65;
            transform: scale(.97);
            transition: all 0.2s;
        }
        .product-card .card-img-area {
            transition: filter 0.2s;
        }
        .oos-card {
            pointer-events: none;
            cursor: not-allowed;
        }
        .oos-card .card-img-area {
            filter: blur(2px) grayscale(1);
            opacity: 0.4;
        }
        .oos-card .card-info-bar {
            opacity: 0.5;
        }
        
        /* ─── Stock Dots ─── */
        .s-dot-amber {
            box-shadow: 0 0 6px rgba(251,191,36,0.6);
            animation: pulse 2s ease-in-out infinite;
        }
        .stock-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            position: absolute;
            top: 4px;
            right: 4px;
        }
        .stock-dot-in {
            background: #10b981;
        }
        .stock-dot-low {
            background: #fbbf24;
            animation: pulse 2s ease-in-out infinite;
        }
        .stock-dot-oos {
            background: #ef4444;
        }
        
        /* ─── Receipt Dashes ─── */
        .receipt-dashes {
            background-image: repeating-linear-gradient(
                90deg, 
                transparent, 
                transparent 4px, 
                rgba(100,116,139,0.3) 4px, 
                rgba(100,116,139,0.3) 8px
            );
            height: 1px;
        }
        
        /* ─── Category Buttons ─── */
        .category-btn.active {
            background: #fbbf24 !important;
            color: #0f172a !important;
        }
        .category-btn.active i {
            color: #0f172a !important;
        }
        
        /* ─── Order Type Buttons ─── */
        .ot-active-slate {
            background: #334155 !important;
            color: white !important;
            border-color: #475569 !important;
        }
        .ot-active-amber {
            background: rgba(251,191,36,0.2) !important;
            color: #fbbf24 !important;
            border-color: #fbbf24 !important;
        }
        .ot-active-blue {
            background: rgba(59,130,246,0.2) !important;
            color: #3b82f6 !important;
            border-color: #3b82f6 !important;
        }
        .ot-active-violet {
            background: rgba(139,92,246,0.2) !important;
            color: #8b5cf6 !important;
            border-color: #8b5cf6 !important;
        }
        .ot-active-emerald {
            background: rgba(16,185,129,0.2) !important;
            color: #10b981 !important;
            border-color: #10b981 !important;
        }
        
        /* ─── Mobile Responsive ─── */
        @media (max-width: 767px) {
            .cart-panel {
                position: fixed;
                right: -100%;
                top: 0;
                bottom: 56px;
                width: 88%;
                max-width: 340px;
                z-index: 1050;
                transition: right 0.28s cubic-bezier(.4,0,.2,1);
                border-radius: 12px 0 0 12px !important;
                box-shadow: -8px 0 32px rgba(0,0,0,.6);
            }
            .cart-panel.open { right: 0; }
            
            .cart-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,.55);
                z-index: 1049;
                backdrop-filter: blur(2px);
            }
            .cart-backdrop.open { display: block; }
            
            .mobile-bottom-nav {
                display: flex !important;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: calc(56px + env(safe-area-inset-bottom));
                background: #0f172a;
                border-top: 1px solid rgba(51,65,85,.6);
                z-index: 1040;
                padding: 0 4px env(safe-area-inset-bottom);
                justify-content: space-around;
                align-items: center;
            }
            body.has-bottom-nav .flex-1.overflow-hidden { padding-bottom: 56px; }
            .desktop-only { display: none !important; }
            #liveDate, .header-branch-pill { display: none; }
            .toolbar-row { flex-wrap: wrap; gap: 6px 4px; }
            .modal-inner { margin-left: 8px !important; margin-right: 8px !important; max-height: 92vh; overflow-y: auto; }
            #productsGrid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .mobile-only { display: flex !important; }
            .pos-cart-fab { display: none !important; }
        }
        
        @media (min-width: 480px) and (max-width: 767px) {
            #productsGrid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        
        @media (min-width: 768px) {
            .mobile-only { display: none !important; }
            .mobile-bottom-nav { display: none !important; }
            .pos-cart-fab { display: none !important; }
        }
        
        @media (min-width: 768px) and (max-width: 1023px) {
            .cart-panel { width: 240px; }
        }
        
        /* ─── POS Header ─── */
        .pos-header {
            height: var(--pos-header-height);
            min-height: var(--pos-header-height);
        }
        
        /* ─── Payment Methods ─── */
        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 0.5rem;
        }
        
        .payment-method-btn.selected {
            ring: 2px solid #fbbf24;
            color: white;
        }
        
        /* ─── Quick Switch Modal ─── */
        .pin-display {
            letter-spacing: 0.5em;
            font-family: 'Courier New', monospace;
        }
        .pin-display::placeholder {
            letter-spacing: 0;
        }
    </style>
</head>
<body class="bg-slate-950 font-['Inter'] antialiased overflow-hidden has-bottom-nav">

<div class="h-screen w-full overflow-hidden flex flex-col">
    
    <!-- ============================================ -->
    <!-- HEADER -->
    <!-- ============================================ -->
    <header class="pos-header bg-slate-900 border-b border-slate-700/40 px-2 md:px-3 flex items-center justify-between gap-1.5 md:gap-2 no-print flex-shrink-0">
        <!-- Left: brand + branch + user -->
        <div class="flex items-center gap-2 min-w-0">
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
            <!-- Today stats -->
            <div class="hidden md:flex items-center gap-2 px-2.5 py-1 bg-slate-800/60 border border-slate-700/40 rounded-lg cursor-default">
                <div class="flex flex-col items-end">
                    <span class="text-amber-400 font-bold text-xs font-mono leading-none" id="todaySalesValue"><?php echo $settings['currency']; ?> <?php echo number_format($today_stats['total'], 0); ?></span>
                    <span class="text-slate-600 text-[9px] leading-none mt-0.5"><span id="todaySalesCount"><?php echo (int)$today_stats['sale_count']; ?></span> sales today</span>
                </div>
            </div>
            
            <!-- Shift status -->
            <button onclick="showModal('shiftModal'); <?php echo $current_shift ? 'refreshShiftSummary();' : ''; ?>" class="flex items-center gap-1 sm:gap-1.5 px-1.5 sm:px-2.5 py-1 rounded-lg text-xs transition-colors border <?php echo $current_shift ? 'bg-emerald-500/10 border-emerald-500/30' : 'bg-red-500/10 border-red-500/30'; ?>" aria-label="Shift Status">
                <span class="w-1.5 h-1.5 rounded-full <?php echo $current_shift ? 'bg-emerald-400 animate-pulse' : 'bg-red-400'; ?>"></span>
                <span class="hidden xs:inline <?php echo $current_shift ? 'text-emerald-400' : 'text-red-400'; ?> font-medium"><?php echo $current_shift ? 'Open' : 'Closed'; ?></span>
            </button>
            
            <!-- Held sales -->
            <button onclick="showModal('heldModal'); loadHeldSales();" class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 bg-slate-800 border border-slate-700/60 rounded-lg text-xs text-slate-300 hover:bg-slate-700 transition-colors" aria-label="Held Sales">
                <i class="fas fa-layer-group text-slate-400 text-[10px]"></i>
                <span>Held</span>
                <span id="heldCountBadge" class="bg-amber-500 text-slate-900 text-[9px] font-bold px-1.5 py-0.5 rounded-full leading-none<?php echo $held_count > 0 ? '' : ' hidden'; ?>"><?php echo $held_count; ?></span>
            </button>
            
            <div class="hidden sm:block w-px h-5 bg-slate-700/60 shrink-0"></div>
            
            <!-- Quick Actions -->
            <button onclick="showModal('customerModal')" class="hidden sm:flex w-8 h-8 items-center justify-center rounded-lg bg-slate-800 border border-slate-700/60 text-slate-400 hover:bg-slate-700 hover:text-amber-400 transition-colors" aria-label="Customer"><i class="fas fa-user text-xs"></i></button>
            <button onclick="openCalculator()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-800 border border-slate-700/60 text-slate-400 hover:bg-slate-700 hover:text-amber-400 transition-colors" aria-label="Calculator"><i class="fas fa-calculator text-xs"></i></button>
            <button onclick="viewLastSale()" class="hidden md:flex w-8 h-8 items-center justify-center rounded-lg bg-slate-800 border border-slate-700/60 text-slate-400 hover:bg-slate-700 hover:text-amber-400 transition-colors" aria-label="Last Receipt" title="View last receipt"><i class="fas fa-receipt text-xs"></i></button>
            <button onclick="openPosHelp()" class="hidden md:flex w-8 h-8 items-center justify-center rounded-lg bg-slate-800 border border-slate-700/60 text-slate-400 hover:bg-slate-700 hover:text-amber-400 transition-colors" aria-label="Help"><i class="fas fa-question text-xs"></i></button>
            
            <!-- Quick User Switch -->
            <button onclick="openQuickSwitch()" class="flex w-10 h-10 sm:w-8 sm:h-8 items-center justify-center rounded-xl sm:rounded-lg bg-slate-800 border border-slate-700/60 text-slate-400 hover:bg-amber-500/20 hover:text-amber-400 transition-all" aria-label="Switch User" title="Switch User">
                <i class="fas fa-user-friends text-sm sm:text-xs"></i>
            </button>
            
            <!-- Quick Logout -->
            <a href="<?php echo function_exists('base_url') ? base_url('auth/logout.php') : '../auth/logout.php'; ?>" class="flex w-10 h-10 sm:w-8 sm:h-8 items-center justify-center rounded-xl sm:rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500/20 hover:text-red-300 transition-all" aria-label="Logout" title="Logout">
                <i class="fas fa-sign-out-alt text-sm sm:text-xs"></i>
            </a>
        </div>
    </header>

    <!-- ============================================ -->
    <!-- QUICK SWITCH USER MODAL -->
    <!-- ============================================ -->
    <div id="quickSwitchModal" class="fixed inset-0 z-[2000] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/90" onclick="closeModal('quickSwitchModal')"></div>
        <div class="relative bg-slate-900 rounded-xl w-full max-w-md border-t-4 border-amber-500 shadow-2xl">
            <div class="flex flex-col items-center pt-8 pb-6">
                <div class="w-20 h-20 rounded-xl bg-gradient-to-br from-amber-500 to-yellow-600 flex items-center justify-center mb-4">
                    <i class="fas fa-user-friends text-3xl text-slate-900"></i>
                </div>
                <h2 class="text-white font-bold text-2xl">Switch User</h2>
                <p class="text-slate-500 text-sm">Enter your credentials to continue</p>
            </div>
            
            <div class="px-8 mb-6">
                <input type="password" id="quickSwitchPin" readonly placeholder="Enter PIN"
                       class="w-full h-20 bg-slate-800 border-2 border-amber-500/50 rounded-xl text-white text-4xl text-center font-bold tracking-widest focus:outline-none pin-display">
            </div>
            
            <div class="px-8 mb-6">
                <input type="text" id="quickSwitchUser" placeholder="User ID" 
                       class="w-full h-16 px-4 bg-slate-800 border-2 border-slate-700 rounded-xl text-white text-2xl text-center font-medium focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                       inputmode="numeric" autocomplete="off">
            </div>
            
            <div class="px-8 pb-8">
                <div class="grid grid-cols-3 gap-4">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                    <button type="button" class="keypad-btn h-20 bg-slate-800 rounded-xl text-white text-3xl font-bold border-2 border-slate-700 hover:bg-slate-700 active:bg-amber-500 active:text-slate-900 transition-all" data-key="<?php echo $i; ?>"><?php echo $i; ?></button>
                    <?php endfor; ?>
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

    <!-- ============================================ -->
    <!-- MAIN CONTENT -->
    <!-- ============================================ -->
    <div class="flex-1 overflow-hidden p-1.5 md:p-3">
        <div class="h-full flex gap-1.5 md:gap-3">
            
            <!-- LEFT COLUMN -->
            <div class="flex-1 flex flex-col overflow-hidden min-w-0 gap-2">

                <!-- Row 1: Categories -->
                <div class="flex-shrink-0">
                    <div class="flex items-center gap-1 md:gap-1.5 overflow-x-auto pb-0.5 scrollbar-thin">
                        <button class="category-btn px-3 py-1.5 rounded-lg bg-slate-700 text-white text-xs whitespace-nowrap font-medium active" data-category="all" onclick="filterCategory('all', this)">
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
                
                <!-- Row 2: Toolbar -->
                <div class="flex-shrink-0">
                    <div class="toolbar-row flex items-center gap-1 md:gap-1.5 overflow-x-auto scrollbar-thin bg-slate-800/40 border border-slate-700/40 rounded-lg px-2 md:px-2.5 py-1.5">
                        <!-- Order Types -->
                        <?php
                            $ot_colors = [
                                'walkin'    => 'text-slate-300 border-slate-600 hover:border-slate-400',
                                'delivery'  => 'text-blue-400 border-blue-500/30 hover:border-blue-400',
                                'wholesale' => 'text-violet-400 border-violet-500/30 hover:border-violet-400',
                                'dinein'    => 'text-emerald-400 border-emerald-500/30 hover:border-emerald-400',
                                'takeaway'  => 'text-amber-400 border-amber-500/30 hover:border-amber-400',
                                'online'    => 'text-cyan-400 border-cyan-500/30 hover:border-cyan-400',
                                'retail'    => 'text-slate-300 border-slate-600 hover:border-slate-400',
                                'appointment' => 'text-pink-400 border-pink-500/30 hover:border-pink-400',
                                'prescription' => 'text-red-400 border-red-500/30 hover:border-red-400',
                                'room'      => 'text-indigo-400 border-indigo-500/30 hover:border-indigo-400',
                                'installment' => 'text-purple-400 border-purple-500/30 hover:border-purple-400',
                            ];
                            $ot_active = [
                                'walkin'    => 'ot-active-slate',
                                'delivery'  => 'ot-active-blue',
                                'wholesale' => 'ot-active-violet',
                                'dinein'    => 'ot-active-emerald',
                                'takeaway'  => 'ot-active-amber',
                                'online'    => 'ot-active-blue',
                                'retail'    => 'ot-active-slate',
                                'appointment' => 'ot-active-pink',
                                'prescription' => 'ot-active-red',
                                'room'      => 'ot-active-indigo',
                                'installment' => 'ot-active-purple',
                            ];
                            $ot_index = 0;
                        ?>
                        <?php foreach ($order_types as $key => $ot): ?>
                        <?php
                            $cleanKey = str_replace('-', '', $key);
                            $color_class = $ot_colors[$cleanKey] ?? 'text-slate-300 border-slate-600 hover:border-slate-400';
                            $active_class = $ot_active[$cleanKey] ?? 'ot-active-slate';
                            $is_first = $ot_index === 0;
                            $ot_index++;
                        ?>
                        <button class="order-type-btn px-2.5 py-1 bg-slate-900/60 border rounded-lg text-xs transition-all whitespace-nowrap shrink-0 <?php echo $color_class; ?> <?php echo $active_class; ?> <?php echo $is_first ? 'ring-1 ring-current opacity-100' : 'opacity-60 hover:opacity-100'; ?>" data-order-type="<?php echo $cleanKey; ?>" onclick="setOrderType('<?php echo $cleanKey; ?>', this)">
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
                            <input type="text" id="prescriptionRef" placeholder="#" autocomplete="off" class="w-20 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                            <input type="text" id="prescriptionDoctor" placeholder="Dr" autocomplete="off" class="w-24 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                            <input type="text" id="prescriptionNotes" placeholder="Notes" autocomplete="off" class="w-28 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($bt_features['batch_number'])): ?>
                        <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                            <i class="fas fa-barcode text-amber-400 text-xs"></i>
                            <input type="text" id="batchNumber" placeholder="Batch #" autocomplete="off" class="w-24 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($bt_features['table'])): ?>
                        <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                            <i class="fas fa-chair text-amber-400 text-xs"></i>
                            <input type="number" id="tableNumber" placeholder="Table #" autocomplete="off" class="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                            <button onclick="applyTableNumber()" class="px-2 py-1 bg-amber-500/20 rounded text-amber-400 text-xs hover:bg-amber-500/30">Set</button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($bt_features['kitchen_notes'])): ?>
                        <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                            <i class="fas fa-utensils text-amber-400 text-xs"></i>
                            <input type="text" id="kitchenNotes" placeholder="Kitchen notes" autocomplete="off" class="w-32 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($bt_features['room_charge'])): ?>
                        <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                            <i class="fas fa-bed text-amber-400 text-xs"></i>
                            <input type="text" id="roomNumber" list="recentRooms" placeholder="Room" autocomplete="off" class="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                            <input type="text" id="guestName" list="recentGuests" placeholder="Guest" autocomplete="off" class="w-24 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($bt_features['weight'])): ?>
                        <div class="flex items-center gap-1 px-2 py-1 bg-slate-700/50 rounded-lg shrink-0">
                            <i class="fas fa-weight text-amber-400 text-xs"></i>
                            <input type="number" id="weightInput" step="0.01" placeholder="0.00" autocomplete="off" class="w-20 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
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
                            <input type="text" id="serialNumber" placeholder="Serial" autocomplete="off" class="w-28 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-xs">
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
                            <input type="text" id="searchBox" placeholder="Search products… (F1)" autocomplete="off" class="w-48 pl-8 pr-3 py-1 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500 placeholder-slate-500">
                        </div>
                        
                        <!-- Refresh Products -->
                        <button onclick="refreshProducts()" id="refreshBtn" class="px-2 py-1 bg-blue-500/10 border border-blue-500/30 rounded-lg text-blue-400 text-xs hover:bg-blue-500/20 transition-colors whitespace-nowrap shrink-0" title="Refresh products">
                            <i class="fas fa-sync-alt mr-1" id="refreshIcon"></i>Refresh
                        </button>
                        
                        <!-- Loading Spinner -->
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
                    <div id="productsError" class="hidden p-4 text-center">
                        <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-2"></i>
                        <p class="text-red-400 text-sm">Failed to load products. <button onclick="refreshProducts()" class="text-blue-400 underline">Try again</button></p>
                    </div>
                    <div id="productsEmpty" class="hidden p-8 text-center">
                        <i class="fas fa-box-open text-slate-600 text-3xl mb-3"></i>
                        <p class="text-slate-500 text-sm">No products found</p>
                        <?php if ($is_super_admin || $user_role === 'Admin'): ?>
                        <p class="text-slate-600 text-xs mt-2"><a href="../products/products.php" class="text-blue-400 hover:underline">Add products</a> or <button onclick="refreshProducts()" class="text-blue-400 hover:underline">refresh</button></p>
                        <?php endif; ?>
                    </div>
                    <div class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 2xl:grid-cols-9 gap-1" id="productsGrid">
                        <?php foreach ($products as $p):
                            $stock   = (int)($p['stock'] ?? 999);
                            $isOOS   = ($stock <= 0);
                            $isLow   = !$isOOS && $stock <= 5;
                            $imgPath = !empty($p['image']) ? base_url(ltrim($p['image'], '/')) : '';
                        ?>
                        <div class="product-card aspect-square bg-slate-800 <?= $isOOS ? 'border-red-500/60' : 'border-slate-700/80' ?> active:scale-95 active:border-amber-400 hover:border-slate-600 rounded-lg relative select-none overflow-hidden flex flex-col transition-all duration-100 group <?= $isOOS ? 'oos-card' : '' ?>"
                             data-product-id="<?php echo $p['id']; ?>"
                             data-product-name="<?php echo htmlspecialchars($p['name']); ?>"
                             data-product-price="<?php echo $p['price']; ?>"
                             data-product-stock="<?php echo $stock; ?>"
                             data-product-image="<?php echo htmlspecialchars($imgPath); ?>"
                             data-category-id="<?php echo $p['category_id'] ?? ''; ?>"
                             <?php if (!$isOOS): ?>onclick="addToCart(this)" role="button" tabindex="0" aria-label="Add <?php echo htmlspecialchars($p['name']); ?> to cart"<?php else: ?>role="img" tabindex="-1" aria-label="Out of stock: <?php echo htmlspecialchars($p['name']); ?>"<?php endif; ?>>

                            <div class="card-img-area flex-1 relative flex items-center justify-center overflow-hidden">
                                <?php if (!empty($imgPath)): ?>
                                <img src="<?php echo htmlspecialchars($imgPath); ?>" class="absolute inset-0 w-full h-full object-cover opacity-80 transition-opacity duration-100" alt="">
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900 to-transparent"></div>
                                <?php else: ?>
                                <div class="w-6 h-6 rounded-lg bg-amber-500/10 flex items-center justify-center">
                                    <i class="fas fa-box text-amber-400/60 text-xs"></i>
                                </div>
                                <?php endif; ?>
                                <?php if ($isOOS): ?>
                                    <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-red-500" title="Out of stock"></span>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <span class="bg-red-500/80 text-white text-[8px] font-bold px-1.5 py-0.5 rounded-full uppercase tracking-wide shadow">Out of Stock</span>
                                    </div>
                                <?php elseif ($isLow): ?>
                                    <span class="absolute top-1 right-1 w-1.5 h-1.5 rounded-full bg-amber-400 s-dot-amber" title="Only <?php echo $stock; ?> left"></span>
                                <?php endif; ?>
                            </div>

                            <div class="card-info-bar px-1 pt-0.5 pb-0.5 bg-slate-900/95 border-t border-slate-700/40 flex-shrink-0">
                                <div class="text-[9px] font-semibold truncate leading-tight <?= $isOOS ? 'text-slate-500' : 'text-white' ?>"><?php echo htmlspecialchars($p['name']); ?></div>
                                <div class="<?= $isOOS ? 'text-red-400/70' : 'text-amber-400' ?> font-bold text-[9px] leading-tight"><?= $isOOS ? 'Out of Stock' : $settings['currency'] . ' ' . number_format($p['price'], 0) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- ============================================ -->
            <!-- RIGHT COLUMN - CART PANEL -->
            <!-- ============================================ -->
            <div class="cart-section cart-panel w-64 lg:w-72 xl:w-80 flex-shrink-0 bg-slate-950 border border-slate-800 rounded-xl flex flex-col overflow-hidden shadow-2xl h-full">
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
                    <div id="cartCustomerBadge" class="hidden mt-1.5 flex items-center gap-1 text-[10px] text-amber-400">
                        <i class="fas fa-user-circle"></i>
                        <span id="cartCustomerName">Walk-in</span>
                    </div>
                </div>

                <!-- Plugin Panels (sidebar-top) -->
                <?php render_plugin_panels('sidebar-top', $pluginPanels); ?>

                <!-- Cart Items -->
                <div class="flex-1 overflow-y-auto px-3 py-2 space-y-px scrollbar-thin min-h-0" id="cartItems">
                    <div class="flex flex-col items-center justify-center h-full py-8 gap-2">
                        <div class="w-14 h-14 rounded-full bg-slate-800 flex items-center justify-center">
                            <i class="fas fa-receipt text-slate-600 text-xl"></i>
                        </div>
                        <p class="text-slate-500 text-xs text-center">No items yet<br><span class="text-slate-600">Tap a product to add</span></p>
                    </div>
                </div>

                <!-- AI Suggestions -->
                <div id="aiSuggestionsStrip" class="hidden px-3 pb-1">
                    <div class="flex items-center gap-1 mb-1"><i class="fas fa-robot text-amber-400 text-[9px]"></i><span class="text-[9px] text-slate-500 uppercase tracking-wider" id="aiStripReason">Suggestions</span></div>
                    <div class="flex gap-1.5 overflow-x-auto pb-1" id="aiStripProducts"></div>
                </div>
                
                <div class="receipt-dashes mx-3"></div>

                <!-- Voucher -->
                <div class="px-3 pt-2 pb-1">
                    <div class="flex gap-1.5">
                        <input type="text" id="voucherInput" placeholder="Voucher / promo code" autocomplete="off" class="flex-1 px-2 py-1.5 bg-slate-900 border border-slate-700/60 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500 placeholder-slate-600">
                        <button onclick="applyVoucher()" class="px-2.5 py-1.5 bg-slate-800 rounded-lg text-amber-400 text-xs font-medium hover:bg-slate-700 transition-colors border border-slate-700/60">Apply</button>
                    </div>
                </div>

                <!-- Totals -->
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
    
    <!-- ============================================ -->
    <!-- CART DRAWER BACKDROP (MOBILE) -->
    <!-- ============================================ -->
    <div class="cart-backdrop mobile-only" id="cartBackdrop" onclick="togglePosCart()"></div>

    <!-- ============================================ -->
    <!-- MOBILE BOTTOM NAV -->
    <!-- ============================================ -->
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

    <!-- ============================================ -->
    <!-- TOAST CONTAINER -->
    <!-- ============================================ -->
    <div class="fixed bottom-5 right-5 z-[2000] flex flex-col gap-2" id="toastContainer"></div>

    <!-- ============================================ -->
    <!-- MODALS -->
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
                        <input type="number" id="openingCash" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white" value="0" step="0.01" aria-label="Opening Cash Amount">
                    </div>
                    <button onclick="openShift()" class="w-full py-2 bg-amber-500 text-slate-900 rounded-lg font-semibold hover:bg-amber-400 transition-colors" aria-label="Open Shift">Open Shift</button>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <div class="bg-slate-700/50 rounded-lg p-3 space-y-1.5">
                        <div class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Shift Summary</div>
                        <div class="flex justify-between text-sm"><span class="text-slate-400">Opened:</span><span class="text-white"><?php echo date('d M Y H:i', strtotime($current_shift['opened_at'])); ?></span></div>
                        <div class="flex justify-between text-sm"><span class="text-slate-400">Opening cash:</span><span class="text-white"><?php echo $settings['currency']; ?> <?php echo number_format($current_shift['opening_cash'], 2); ?></span></div>
                        <div class="flex justify-between text-sm"><span class="text-slate-400">Total sales:</span><span class="text-amber-400 font-semibold"><?php echo $settings['currency']; ?> <span id="shiftTotalSales"><?php echo number_format($current_shift['total_sales'] ?? 0, 2); ?></span></span></div>
                        <div class="flex justify-between text-sm" id="shiftSaleCountRow"><span class="text-slate-400">Transactions:</span><span class="text-white" id="shiftSaleCount"><?php echo number_format($current_shift['sale_count'] ?? $today_stats['sale_count'] ?? 0); ?></span></div>
                        <div id="shiftPaymentBreakdown" class="pt-1 space-y-0.5 border-t border-slate-600/60 hidden">
                            <div class="text-slate-500 text-[10px] uppercase tracking-wider">By Payment Method</div>
                        </div>
                    </div>
                    <div class="bg-slate-800/60 rounded-lg p-3 space-y-1.5">
                        <div class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Cash Reconciliation</div>
                        <div class="flex justify-between text-sm"><span class="text-slate-400">Opening cash:</span><span class="text-white"><?php echo $settings['currency']; ?> <?php echo number_format($current_shift['opening_cash'], 2); ?></span></div>
                        <div class="flex justify-between text-sm"><span class="text-slate-400">+ Cash sales:</span><span class="text-white"><?php echo $settings['currency']; ?> <span id="shiftCashSales"><?php echo number_format($today_stats['cash'] ?? 0, 2); ?></span></span></div>
                        <div class="flex justify-between text-sm font-semibold border-t border-slate-600/60 pt-1"><span class="text-slate-300">Expected in drawer:</span><span class="text-amber-400"><?php echo $settings['currency']; ?> <span id="shiftExpectedCash"><?php echo number_format(($current_shift['opening_cash'] ?? 0) + ($today_stats['cash'] ?? 0), 2); ?></span></span></div>
                    </div>
                    <div>
                        <label for="closingCash" class="text-slate-300 text-sm block mb-1.5">Actual Closing Cash</label>
                        <input type="number" id="closingCash" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white" step="0.01" placeholder="Count your drawer..." aria-label="Closing Cash Amount" oninput="calcCashVariance()">
                    </div>
                    <div id="cashVarianceRow" class="hidden flex justify-between text-sm p-2 rounded-lg">
                        <span class="text-slate-400">Variance:</span><span id="cashVarianceAmt" class="font-semibold"></span>
                    </div>
                    <div>
                        <label for="closingNotes" class="text-slate-300 text-sm block mb-1.5">Notes (Optional)</label>
                        <textarea id="closingNotes" rows="2" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white" aria-label="Closing Notes"></textarea>
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
                    <input type="tel" id="customerPhone" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white" placeholder="0712345678" aria-label="Customer Phone">
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
                
                <div class="p-3 bg-slate-700/30 rounded-lg space-y-1.5">
                    <div class="flex justify-between text-xs text-slate-400"><span>Items</span><span id="modalItemCount" class="text-white font-medium">0</span></div>
                    <div class="flex justify-between text-xs text-slate-400"><span>Subtotal</span><span id="modalSubtotalDisplay" class="text-white font-medium"><?php echo $settings['currency']; ?> 0</span></div>
                    <div id="modalDiscountRow" class="hidden flex justify-between text-xs text-slate-400"><span>Discount</span><span id="modalDiscountDisplay" class="text-emerald-400 font-medium">-<?php echo $settings['currency']; ?> 0</span></div>
                    <div class="flex justify-between text-xs text-slate-400"><span>Tax</span><span id="modalTaxDisplay" class="text-white font-medium"><?php echo $settings['currency']; ?> 0</span></div>
                </div>
                
                <div class="payment-methods-grid grid grid-cols-3 gap-2">
                    <?php foreach ($enabled_payment_methods as $idx => $pm): ?>
                    <button class="payment-method-btn py-2 px-3 bg-slate-700 rounded-lg text-slate-300 text-sm hover:bg-slate-600 transition-colors <?php echo $idx === 0 ? 'ring-2 ring-amber-500 text-white' : ''; ?>" data-method="<?php echo $pm['id']; ?>" onclick="selectPaymentMethod('<?php echo $pm['id']; ?>')" aria-label="<?php echo $pm['label']; ?>">
                        <i class="fas <?php echo $pm['icon']; ?> mr-1"></i><?php echo $pm['label']; ?>
                    </button>
                    <?php endforeach; ?>
                    <button class="payment-method-btn py-2 px-3 bg-slate-700 rounded-lg text-slate-300 text-sm hover:bg-slate-600 transition-colors" data-method="split" onclick="selectPaymentMethod('split')" aria-label="Split Payment">
                        <i class="fas fa-divide mr-1"></i>Split
                    </button>
                </div>
                
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

                <div>
                    <label for="amountReceived" class="text-slate-300 text-sm block mb-1.5">Amount Received</label>
                    <input type="number" id="amountReceived" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white text-lg font-semibold" step="0.01" aria-label="Amount Received" oninput="calculateChange()">
                </div>
                
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
                
                <div class="flex justify-between items-center pt-2 border-t border-slate-700">
                    <span class="text-slate-400 font-medium text-sm">Change</span>
                    <div class="flex items-center gap-1">
                        <span class="text-emerald-400 font-bold text-base" id="changeAmount"><?php echo $settings['currency']; ?> 0</span>
                        <span id="changeIndicator"></span>
                    </div>
                </div>
            </div>

            <div class="payment-actions flex-shrink-0 flex gap-2 px-3 sm:px-4 py-3 border-t border-slate-700 bg-slate-800 rounded-b-xl">
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
        <div class="modal-content modal-inner bg-slate-800 rounded-xl max-w-md w-full mx-2 sm:mx-4 shadow-2xl max-h-[92vh] overflow-y-auto scrollbar-thin">
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
                    <input type="password" id="adminPinInput" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white" maxlength="6" aria-label="Admin PIN">
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

</div>

<!-- ============================================ -->
<!-- JAVASCRIPT - All POS Logic -->
<!-- ============================================ -->
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
    csrfToken: '<?php echo addslashes($_pos_csrf_token); ?>',
    userId: <?php echo (int) $user_id; ?>,
    baseUrl: '<?php echo function_exists('base_url') ? rtrim(base_url(''), '/') : '/JDH_POS/public'; ?>',
    ajaxUrl: '<?php echo function_exists('base_url') ? rtrim(base_url(''), '/') . '/ajax/' : '/JDH_POS/public/ajax/'; ?>',
    paymentMethods: <?php echo json_encode(array_values(array_map(fn($pm) => ['id' => $pm['id'], 'label' => $pm['label']], $enabled_payment_methods))); ?>,
    wholesaleTiers: <?php echo json_encode(array_values($wholesale_tiers)); ?>,
    warrantyPeriods: <?php echo json_encode(array_values($warranty_periods)); ?>
};

// ============================================
// GLOBAL STATE
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
let allProducts = <?php echo json_encode($products ?? [], JSON_INVALID_UTF8_IGNORE); ?>;
const categories = <?php echo json_encode($categories ?? [], JSON_INVALID_UTF8_IGNORE); ?>;
let _lastSaleId = null;
let _loyaltyRedeemDiscount = 0;
let _loyaltyPointsToRedeem = 0;
let _aiDebounceTimer = null;
let calcCurrent = '0';
let calcPrev = null;
let calcOperator = null;

// ============================================
// LOCAL STORAGE HELPERS
// ============================================
const LS_PREFIX = 'pos_' + CONFIG.userId + '_';

function _lsKey(name) { return LS_PREFIX + name; }

function _saveState() {
    try {
        localStorage.setItem(_lsKey('cart'), JSON.stringify(window.cart));
        localStorage.setItem(_lsKey('discount'), JSON.stringify(appliedDiscount));
        localStorage.setItem(_lsKey('voucher'), JSON.stringify(appliedVoucher));
        localStorage.setItem(_lsKey('customer'), JSON.stringify(window.selectedCustomer));
    } catch (e) { /* Ignore */ }
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
    } catch (e) { /* Ignore */ }
}

function _clearState() {
    try {
        ['cart', 'discount', 'voucher', 'customer'].forEach(k => localStorage.removeItem(_lsKey(k)));
    } catch (e) { /* Ignore */ }
}

</script>

<!-- POS Core Functions (extracted from inline for maintainability) -->
<?php $posCoreVersion = @filemtime(__DIR__ . '/../assets/js/pos-core.js') ?: time(); ?>
<script src="<?= base_url("assets/js/pos-core.js") ?>?v=<?= $posCoreVersion ?>" defer></script>

<!-- Advanced POS Modules (async to not block render) -->
<script src="<?= base_url("assets/js/pos-smart-checkout.js") ?>" async></script>
<script src="<?= base_url("assets/js/pos-offline-advanced.js") ?>" async></script>
<script src="<?= base_url("assets/js/pos-advanced-loyalty.js") ?>" async></script>
<script src="<?= base_url("assets/js/pos-advanced-payments.js") ?>" async></script>

<!-- Plugin Assets -->
<?php foreach ($pluginAssets as $asset): ?>
<script src="<?= base_url(ltrim($asset['plugin_url'], '/') . '/' . ltrim($asset['file'], '/')) ?>" async></script>
<?php endforeach; ?>

<!-- Plugin Frontend Event Bus -->
<script>
window.JDH_POS = window.JDH_POS || {
    _events: {},
    on(event, callback) {
        if (!this._events[event]) this._events[event] = [];
        this._events[event].push(callback);
    },
    off(event, callback) {
        if (!this._events[event]) return;
        this._events[event] = this._events[event].filter(cb => cb !== callback);
    },
    emit(event, data) {
        if (!this._events[event]) return;
        this._events[event].forEach(cb => { try { cb(data); } catch (e) { console.error(e); } });
    }
};
</script>

</body>
</html>