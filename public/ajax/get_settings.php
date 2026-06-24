<?php
/**
 * AJAX endpoint to load company settings
 * GET only - returns JSON
 * Multi-tenant: enforces tenant_id isolation
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/src/paths.php';

if (function_exists('safe_require')) {
    safe_require('auth.php', 'src');

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
    safe_require('db.php', 'src');
    safe_require('functions.php', 'src');
} else {
    require_once $root_path . '/src/auth.php';
    require_once $root_path . '/src/db.php';
    require_once $root_path . '/src/functions.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_login();

$tenant_id = get_current_tenant_id() ?: get_current_tenant_id();
$tenant_id = $tenant_id;

if (!$tenant_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Tenant context missing']);
    exit;
}

try {
    $pdo = get_db_connection();

    $settings_scope_column = db_get_scope_column('settings') ?? 'tenant_id';
    $branches_scope_column = db_get_scope_column('branches') ?? 'tenant_id';

    // Load all settings for this tenant
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE {$settings_scope_column} = ?");
    $stmt->execute([$tenant_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Load branches for default branch dropdown
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE {$branches_scope_column} = ? AND (deleted_at IS NULL OR active = 1 OR is_active = 1) ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load business types
    $business_types = [];
    try {
        $stmt = $pdo->query("SELECT code, name, icon FROM business_types WHERE active = 1 ORDER BY name");
        $business_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback to config
        $config_types = require $root_path . '/config/app.php';
        foreach ($config_types['business_types'] ?? [] as $code => $info) {
            $business_types[] = ['code' => $code, 'name' => $info['name'], 'icon' => $info['icon']];
        }
    }

    // Load backup files
    $backups = [];
    $backup_dir = STORAGE_PATH . '/backups';
    if (is_dir($backup_dir)) {
        foreach (scandir($backup_dir) as $file) {
            if (preg_match('/\.(sql|zip|gz)$/', $file)) {
                $filepath = $backup_dir . '/' . $file;
                $backups[] = [
                    'name' => $file,
                    'size' => filesize($filepath),
                    'date' => filemtime($filepath),
                    'size_display' => format_bytes(filesize($filepath)),
                ];
            }
        }
        usort($backups, fn($a, $b) => $b['date'] - $a['date']);
        $backups = array_slice($backups, 0, 10);
    }

    // Merge with defaults
    $defaults = get_settings_defaults();
    $settings = array_merge($defaults, $rows);

    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'branches' => $branches,
        'business_types' => $business_types,
        'backups' => $backups,
        'tenant_id' => $tenant_id,
        'tenant_id' => $tenant_id,
    ]);

} catch (Exception $e) {
    error_log("Get settings error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load settings']);
}

/**
 * Default settings values
 */
function get_settings_defaults(): array {
    return [
        'company_name' => 'Jakababa POS',
        'company_email' => '',
        'company_phone' => '',
        'company_address' => '',
        'company_logo' => '',
        'business_type' => 'retail',
        'currency' => 'KES',
        'timezone' => 'Africa/Nairobi',
        'date_format' => 'd M Y',
        'time_format' => 'H:i',
        'tax_rate' => '16',
        'default_branch_id' => '0',
        'default_payment_method' => 'cash',
        'notify_low_stock' => '1',
        'low_stock_threshold' => '10',
        'notify_new_order' => '1',
        'notify_daily_report' => '1',
        'daily_report_time' => '08:00',
        'email_notifications' => '1',
        'sms_notifications' => '0',
        'notify_low_balance' => '0',
        'invoice_prefix' => 'INV-',
        'invoice_next_number' => '1001',
        'invoice_footer' => 'Thank you for your business!',
        'receipt_width' => '80',
        'show_logo_on_receipt' => '1',
        'show_tax_on_receipt' => '1',
        'show_discount_on_receipt' => '1',
        'auto_print_receipt' => '1',
        'tax_display_mode' => 'exclusive',
        'enable_barcode_scanner' => '1',
        'enable_discounts' => '1',
        'enable_returns' => '1',
        'enable_draft_sales' => '1',
        'allow_negative_stock' => '0',
        'enable_expiry_tracking' => '0',
        'default_reorder_level' => '10',
        'session_timeout' => '7200',
        'require_pin_for_refund' => '0',
        'enable_loyalty' => '1',
        'enable_vouchers' => '1',
        'round_prices' => '0',
        'auto_backup' => '1',
        'backup_frequency' => 'daily',
        'backup_time' => '02:00',
        'backup_retention_days' => '30',
        'last_backup_at' => '',
    ];
}

/**
 * Format bytes to human readable
 */
function format_bytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
