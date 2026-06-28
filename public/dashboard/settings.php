<?php
/**
 * Company Settings Page - SaaS Multi-Tenant
 * Tabs: General | Notifications | Invoice | Backup | System
 * Uses AJAX for saving (no page reload)
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('settings.manage')) {
    header('Location: home.php?error=unauthorized');
    exit;
}

$page_title = 'Company Settings | Jakababa POS';
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();

if (!$tenant_id) {
    http_response_code(403);
    exit('Tenant context missing.');
}

$pdo = get_db_connection();
$active_tab = $_GET['tab'] ?? 'general';
$settings_scope_column = 'tenant_id';
$branches_scope_column = 'tenant_id';

// Load current settings
$settings = [];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE {$settings_scope_column} = ?");
    $stmt->execute([$tenant_id]);
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Error loading settings: " . $e->getMessage());
}

// Load actual tenant name from tenants table (authoritative)
$tenant_name_db = '';
try {
    $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ? LIMIT 1");
    $stmt->execute([$tenant_id]);
    $tenant_name_db = $stmt->fetchColumn() ?: '';
} catch (Exception $e) {
    // Silent fail
}
if ($tenant_name_db) {
    $settings['company_name'] = $tenant_name_db;
}

// Load branches
$branches = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, code, address, phone, email, location, tax_rate, opening_time, closing_time, is_active, active, created_at FROM branches WHERE {$branches_scope_column} = ? AND (deleted_at IS NULL) ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* silent */ }

// Load tax rates
$tax_rates = [];
try {
    $tax_scope = 'tenant_id';
    $stmt = $pdo->prepare("SELECT id, name, rate, description, active, is_default, type FROM tax_rates WHERE {$tax_scope} = ? ORDER BY rate");
    $stmt->execute([$tenant_id]);
    $tax_rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* silent */ }

// Load business types from canonical config/app.php (always up-to-date, no hardcoding)
$app_config = require dirname(__DIR__, 2) . '/config/app.php';
$business_types = [];
foreach ($app_config['business_types'] ?? [] as $code => $cfg) {
    $business_types[] = ['code' => $code, 'name' => $cfg['name'] ?? $code, 'icon' => $cfg['icon'] ?? 'fa-store'];
}
// Sort by name for consistent UI ordering
usort($business_types, fn($a, $b) => strcasecmp($a['name'], $b['name']));

// Advanced mode toggle (stored in settings)
$advanced_mode = !empty($settings['advanced_settings']) && $settings['advanced_settings'] !== '0';

// Defaults
$defaults = [
    'company_name' => 'Jakababa POS', 'company_email' => '', 'company_phone' => '', 'company_address' => '', 'company_logo' => '',
    'vat_number' => '', 'pin_number' => '', 'fiscal_stand' => '', 'receipt_description' => '',
    'etims_enabled' => '0', 'etims_environment' => 'sandbox', 'etims_tin' => '', 'etims_branch_id' => '00', 'etims_device_serial' => '', 'etims_cmc_key' => '',
    'site_title' => '', 'site_tagline' => '', 'site_icon' => '',
    'business_type' => 'retail', 'currency' => 'KES', 'timezone' => 'Africa/Nairobi',
    'date_format' => 'd M Y', 'time_format' => 'H:i', 'tax_rate' => '11',
    'default_branch_id' => '0', 'default_payment_method' => 'cash',
    // Online Store
    'online_store_enabled' => '0', 'online_store_url' => '', 'whatsapp_number' => '', 'whatsapp_message' => 'Hi, I would like to order:',
    'meta_title' => '', 'meta_description' => '', 'og_image_url' => '',
    'social_facebook' => '', 'social_twitter' => '', 'social_instagram' => '', 'social_tiktok' => '',
    // Loyalty & Vouchers
    'loyalty_points_rate' => '1', 'loyalty_redeem_points' => '1', 'loyalty_points_value' => '0.01', 'loyalty_expire_days' => '365',
    'voucher_prefix' => 'VO', 'voucher_length' => '8', 'voucher_expire_days' => '180', 'voucher_min_spend' => '0',
    // Notifications
    'notify_low_stock' => '1', 'low_stock_threshold' => '5',
    'notify_new_order' => '1', 'notify_daily_report' => '1', 'daily_report_time' => '08:00',
    'email_notifications' => '1', 'sms_notifications' => '0', 'notify_low_balance' => '0',
    // Invoice
    'invoice_prefix' => 'INV-', 'invoice_next_number' => '1001', 'invoice_footer' => 'Thank you for your business!',
    'receipt_width' => '80', 'show_logo_on_receipt' => '1', 'show_tax_on_receipt' => '1',
    'show_discount_on_receipt' => '1', 'auto_print_receipt' => '1', 'tax_display_mode' => 'exclusive',
    // System
    'enable_barcode_scanner' => '1', 'enable_discounts' => '1', 'enable_returns' => '1',
    'enable_draft_sales' => '1', 'allow_negative_stock' => '0', 'enable_expiry_tracking' => '0',
    'default_reorder_level' => '10', 'session_timeout' => '7200', 'require_pin_for_refund' => '0',
    'enable_loyalty' => '1', 'enable_vouchers' => '1', 'round_prices' => '0',
    // Backup
    'auto_backup' => '1', 'backup_frequency' => 'daily', 'backup_time' => '02:00',
    'backup_retention_days' => '30', 'last_backup_at' => '',
    // Advanced
    'advanced_settings' => '0',
];
$settings = array_merge($defaults, $settings);

$csrf_token = generate_csrf_token();

$timezones = DateTimeZone::listIdentifiers();
$currencies = [
    'KES' => 'KES - Kenyan Shilling', 'USD' => 'USD - US Dollar', 'UGX' => 'UGX - Ugandan Shilling',
    'TZS' => 'TZS - Tanzanian Shilling', 'NGN' => 'NGN - Nigerian Naira', 'GHS' => 'GHS - Ghanaian Cedi',
    'ZAR' => 'ZAR - South African Rand', 'EUR' => 'EUR - Euro', 'GBP' => 'GBP - British Pound',
    'INR' => 'INR - Indian Rupee', 'RWF' => 'RWF - Rwandan Franc', 'ETB' => 'ETB - Ethiopian Birr',
];

// Load backup files
$backups = [];
$backup_dir = STORAGE_PATH . '/backups';
if (is_dir($backup_dir)) {
    foreach (scandir($backup_dir) as $file) {
        if (preg_match('/\.(sql|zip|gz)$/', $file)) {
            $filepath = $backup_dir . '/' . $file;
            $backups[] = ['name' => $file, 'size' => filesize($filepath), 'date' => filemtime($filepath)];
        }
    }
    usort($backups, fn($a, $b) => $b['date'] - $a['date']);
    $backups = array_slice($backups, 0, 10);
}

ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            font-size: 14px;
        }
        body {
            font-size: 14px;
            background: #0f172a;
        }
        /* Custom toggle switch */
        .toggle-switch {
            position: relative;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-switch .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #334155;
            border-radius: 24px;
            transition: 0.25s;
        }
        .toggle-switch .toggle-slider:before {
            content: "";
            position: absolute;
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: #f1f5f9;
            border-radius: 50%;
            transition: 0.25s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .toggle-switch input:checked + .toggle-slider {
            background: #f59e0b;
        }
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(20px);
            background: #0f172a;
        }
        /* Toast notifications */
        .toast-container {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .toast {
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
            min-width: 280px;
            border: 1px solid;
            backdrop-filter: blur(8px);
        }
        .toast-success {
            background: rgba(16,185,129,0.12);
            border-color: rgba(16,185,129,0.3);
            color: #34d399;
        }
        .toast-error {
            background: rgba(239,68,68,0.12);
            border-color: rgba(239,68,68,0.3);
            color: #f87171;
        }
        .toast-info {
            background: rgba(59,130,246,0.12);
            border-color: rgba(59,130,246,0.3);
            color: #60a5fa;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        /* Loading overlay */
        .saving-overlay {
            position: absolute;
            inset: 0;
            background: rgba(11,18,32,0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            z-index: 10;
            backdrop-filter: blur(4px);
        }
        .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid #334155;
            border-top-color: #f59e0b;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* Logo preview */
        .logo-preview {
            width: 80px;
            height: 80px;
            border-radius: 0.5rem;
            border: 2px dashed #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #1e293b;
        }
        /* Admin Menu */
        .admin-menu {
            background: #0f172a;
            width: 165px;
            min-height: calc(100vh - 120px);
            flex-shrink: 0;
            border-right: 1px solid #1e293b;
        }
        .menu-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.15s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            border-left: 3px solid transparent;
        }
        .menu-item:hover {
            background: rgba(245,158,11,0.08);
            color: #f8fafc;
            border-left-color: #f59e0b;
        }
        .menu-item.active {
            background: rgba(245,158,11,0.12);
            color: #f59e0b;
            font-weight: 600;
            border-left-color: #f59e0b;
        }
        .menu-item i {
            width: 18px;
            text-align: center;
            font-size: 0.875rem;
        }
        /* Cards */
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            transition: border-color 0.15s ease;
        }
        .card:hover {
            border-color: #475569;
        }
        .card-header {
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid #334155;
            background: #0f172a;
            font-weight: 700;
            color: #f8fafc;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: 0.01em;
            border-radius: 12px 12px 0 0;
        }
        .card-body {
            padding: 1.25rem;
        }
        /* Form elements */
        input, select, textarea {
            background: #0f172a;
            border: 1px solid #334155;
            color: #f8fafc;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            width: 100%;
            transition: all 0.15s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245,158,11,0.2);
        }
        label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            margin-bottom: 0.35rem;
        }
        .btn-primary {
            background: #f59e0b;
            color: #0f172a;
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.15s;
        }
        .btn-primary:hover {
            background: #d97706;
        }
        .btn-secondary {
            background: #334155;
            color: #f8fafc;
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.15s;
        }
        .btn-secondary:hover {
            background: #475569;
        }
        /* Scrollbar */
        .admin-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .admin-scroll::-webkit-scrollbar-track {
            background: #0f172a;
        }
        .admin-scroll::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 3px;
        }
        @media (max-width: 1024px) {
            .admin-menu {
                display: none;
                position: fixed;
                left: 0;
                top: 110px;
                z-index: 50;
                height: calc(100vh - 110px);
                width: 165px;
            }
            .admin-menu.open {
                display: block;
            }
        }
    </style>
</head>
<body class="text-slate-200" style="background:#0f172a; font-size:14px;">

<div class="min-h-screen" id="settingsApp">
    <!-- Toolbar -->
    <div class="border-b border-slate-800 bg-slate-900/50 px-6 py-4">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <div class="text-xs text-slate-500 uppercase tracking-wider">Configuration</div>
                <h1 class="text-xl font-bold text-white mt-1">Settings</h1>
                <p class="text-sm text-slate-400 mt-1">Company Configuration  Tenant: <?php echo $tenant_id; ?></p>
            </div>
            <div>
                <button type="button" class="lg:hidden btn-secondary" onclick="document.getElementById('adminMenu').classList.toggle('open')">
                    <i class="fas fa-bars"></i> Menu
                </button>
            </div>
        </div>
    </div>

    <div class="flex max-w-[1400px] mx-auto">
        <!-- Sidebar Menu -->
        <nav class="admin-menu admin-scroll overflow-y-auto" id="adminMenu">
            <div class="py-2">
                <?php
                $menuItems = [
                    ['general', 'cog', 'General'],
                    ['branches', 'code-branch', 'Branches'],
                    ['taxes', 'calculator', 'Taxes'],
                    ['online', 'globe', 'Online Store'],
                    ['loyalty', 'star', 'Loyalty'],
                    ['notifications', 'bell', 'Notifications'],
                    ['invoice', 'file-invoice', 'Invoice'],
                    ['payment_gateways', 'credit-card', 'Payment Gateways'],
                    ['saas', 'cloud', 'SaaS & Limits'],
                    ['backup', 'cloud-arrow-up', 'Backup'],
                    ['system', 'server', 'System'],
                ];
                foreach ($menuItems as $item):
                    $isActive = $active_tab === $item[0];
                ?>
                <button onclick="switchSettingsTab('<?php echo $item[0]; ?>', this)" class="menu-item <?php echo $isActive ? 'active' : ''; ?>">
                    <i class="fas fa-<?php echo $item[1]; ?>"></i>
                    <span><?php echo $item[2]; ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 p-6 min-w-0">
            <div class="relative" id="settingsContent">

                <!-- GENERAL TAB -->
                <div id="tab-general" class="tab-content <?php echo $active_tab === 'general' ? 'block' : 'hidden'; ?>">
                    <form id="formGeneral" onsubmit="return saveGeneralSettings(event)" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_general">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="card mb-5">
                            <div class="card-header">
                                <i class="fas fa-building text-amber-500"></i> Company Information
                            </div>
                            <div class="card-body">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label>Company Name <span class="text-red-400">*</span></label>
                                        <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                                    </div>
                                    <div>
                                        <label>Business Type</label>
                                        <select name="business_type" onchange="onBusinessTypeChange(this.value)">
                                            <?php foreach ($business_types as $bt): ?>
                                            <option value="<?php echo htmlspecialchars($bt['code']); ?>" <?php echo $settings['business_type'] === $bt['code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($bt['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Email</label>
                                        <input type="email" name="tenant_email" value="<?php echo htmlspecialchars($settings['company_email']); ?>">
                                    </div>
                                    <div>
                                        <label>Phone</label>
                                        <input type="text" name="tenant_phone" value="<?php echo htmlspecialchars($settings['company_phone']); ?>">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label>Address</label>
                                        <textarea name="tenant_address" rows="2"><?php echo htmlspecialchars($settings['company_address']); ?></textarea>
                                    </div>
                                    <div>
                                        <label>VAT Number</label>
                                        <input type="text" name="vat_number" value="<?php echo htmlspecialchars($settings['vat_number']); ?>" placeholder="e.g. P0123456789">
                                    </div>
                                    <div>
                                        <label>PIN / Tax ID</label>
                                        <input type="text" name="pin_number" value="<?php echo htmlspecialchars($settings['pin_number']); ?>" placeholder="e.g. A123456789B">
                                    </div>
                                    <div>
                                        <label>Fiscal Stand</label>
                                        <input type="text" name="fiscal_stand" value="<?php echo htmlspecialchars($settings['fiscal_stand']); ?>" placeholder="e.g. Main Counter / POS 1">
                                        <p class="text-xs text-slate-500 mt-1">Stand label printed on fiscal receipts</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label>Receipt Description / Tagline</label>
                                        <input type="text" name="receipt_description" value="<?php echo htmlspecialchars($settings['receipt_description']); ?>" placeholder="e.g. Your trusted retail partner">
                                        <p class="text-xs text-slate-500 mt-1">Short tagline shown on printed receipts below company info</p>
                                    </div>

                                    <!-- KRA eTIMS Configuration -->
                                    <div class="md:col-span-2 border-t border-slate-700 pt-4 mt-2">
                                        <h4 class="text-sm font-semibold text-white mb-3"><i class="fas fa-landmark text-amber-400 mr-2"></i>KRA eTIMS Integration</h4>
                                        <p class="text-xs text-slate-400 mb-3">Configure Kenya Revenue Authority Electronic Tax Invoice Management System for tax compliance.</p>
                                    </div>
                                    <div>
                                        <label>eTIMS Status</label>
                                        <select name="etims_enabled" class="w-full">
                                            <option value="0" <?php echo ($settings['etims_enabled'] ?? '') !== '1' ? 'selected' : ''; ?>>Disabled</option>
                                            <option value="1" <?php echo ($settings['etims_enabled'] ?? '') === '1' ? 'selected' : ''; ?>>Enabled</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Environment</label>
                                        <select name="etims_environment" class="w-full">
                                            <option value="sandbox" <?php echo ($settings['etims_environment'] ?? '') !== 'production' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                                            <option value="production" <?php echo ($settings['etims_environment'] ?? '') === 'production' ? 'selected' : ''; ?>>Production</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>KRA TIN</label>
                                        <input type="text" name="etims_tin" value="<?php echo htmlspecialchars($settings['etims_tin'] ?? ''); ?>" placeholder="e.g. P000000000A">
                                        <p class="text-xs text-slate-500 mt-1">Tax Identification Number from KRA</p>
                                    </div>
                                    <div>
                                        <label>Branch ID</label>
                                        <input type="text" name="etims_branch_id" value="<?php echo htmlspecialchars($settings['etims_branch_id'] ?? '00'); ?>" placeholder="00">
                                        <p class="text-xs text-slate-500 mt-1">00 for headquarters, 01+ for branches</p>
                                    </div>
                                    <div>
                                        <label>Device Serial No</label>
                                        <input type="text" name="etims_device_serial" value="<?php echo htmlspecialchars($settings['etims_device_serial'] ?? ''); ?>" placeholder="Device serial from KRA">
                                    </div>
                                    <div>
                                        <label>CMC Key</label>
                                        <input type="password" name="etims_cmc_key" value="<?php echo htmlspecialchars($settings['etims_cmc_key'] ?? ''); ?>" placeholder="Communication key from KRA">
                                        <p class="text-xs text-slate-500 mt-1">Issued during device registration</p>
                                    </div>
                                    <div>
                                        <label>Company Logo</label>
                                        <div class="flex items-center gap-4">
                                            <div class="logo-preview" id="logoPreview">
                                                <?php if (!empty($settings['company_logo'])): ?>
                                                    <img src="<?php echo base_url($settings['company_logo']); ?>" alt="Logo" id="logoPreviewImg">
                                                <?php else: ?>
                                                    <i class="fas fa-image text-slate-600 text-xl" id="logoPreviewIcon"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <label class="btn-secondary cursor-pointer">
                                                        <i class="fas fa-upload text-amber-400"></i> Upload Logo
                                                        <input type="file" name="company_logo" accept="image/*" class="hidden" onchange="previewLogo(this)">
                                                    </label>
                                                    <?php if (!empty($settings['company_logo'])): ?>
                                                    <button type="button" onclick="removeLogo()" class="btn-secondary bg-red-900/30 border border-red-800 text-red-400" title="Remove logo">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-xs text-slate-500 mt-1">JPG, PNG, max 2MB</p>
                                                <input type="hidden" name="remove_company_logo" id="removeCompanyLogo" value="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="siteTitle">Site Title</label>
                                        <input type="text" name="site_title" id="siteTitle" value="<?php echo htmlspecialchars($settings['site_title']); ?>" placeholder="e.g. JAKPOS">
                                        <p class="text-xs text-slate-500 mt-1">Browser tab title and login page heading</p>
                                    </div>
                                    <div>
                                        <label for="siteTagline">Tagline</label>
                                        <input type="text" name="site_tagline" id="siteTagline" value="<?php echo htmlspecialchars($settings['site_tagline']); ?>" placeholder="e.g. Smart Retail Solutions">
                                        <p class="text-xs text-slate-500 mt-1">Short subtitle shown below the site title</p>
                                    </div>
                                    <div>
                                        <label>Site Icon (Favicon)</label>
                                        <div class="flex items-center gap-4">
                                            <div class="logo-preview w-10 h-10 rounded-lg" id="iconPreview">
                                                <?php if (!empty($settings['site_icon'])): ?>
                                                    <img src="<?php echo base_url($settings['site_icon']); ?>" alt="Icon" class="w-full h-full object-contain" id="iconPreviewImg">
                                                <?php else: ?>
                                                    <i class="fas fa-image text-slate-600 text-base" id="iconPreviewIcon"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <label class="btn-secondary cursor-pointer">
                                                        <i class="fas fa-upload text-amber-400"></i> Upload Icon
                                                        <input type="file" name="site_icon" accept="image/png,image/x-icon,image/svg+xml" class="hidden" onchange="previewIcon(this)">
                                                    </label>
                                                    <?php if (!empty($settings['site_icon'])): ?>
                                                    <button type="button" onclick="removeIcon()" class="btn-secondary bg-red-900/30 border border-red-800 text-red-400" title="Remove icon">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-xs text-slate-500 mt-1">PNG / ICO / SVG, max 1MB</p>
                                                <p class="text-xs text-slate-400 mt-0.5"><i class="fas fa-circle-info mr-1"></i>Tip: Use a <strong>512 x 512</strong> square image with the logo filling the entire frame for a crisp browser tab icon.</p>
                                                <input type="hidden" name="remove_site_icon" id="removeSiteIcon" value="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header">
                                <i class="fas fa-sliders-h text-amber-500"></i> Regional Settings
                            </div>
                            <div class="card-body">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label for="currency">Currency</label>
                                        <select name="currency" id="currency">
                                            <?php foreach ($currencies as $code => $label): ?>
                                            <option value="<?php echo $code; ?>" <?php echo $settings['currency'] === $code ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="locale">Language</label>
                                        <select name="locale" id="locale">
                                            <?php
                                            $locales = ['en' => 'English', 'es' => 'Espanol', 'fr' => 'Francais', 'de' => 'Deutsch', 'pt' => 'Portugues', 'sw' => 'Kiswahili', 'hi' => 'Hindi', 'ar' => 'Arabic'];
                                            foreach ($locales as $code => $label):
                                            ?>
                                            <option value="<?php echo $code; ?>" <?php echo ($settings['locale'] ?? 'en') === $code ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="timezone">Timezone</label>
                                        <select name="timezone" id="timezone">
                                            <?php foreach ($timezones as $tz): ?>
                                            <option value="<?php echo $tz; ?>" <?php echo $settings['timezone'] === $tz ? 'selected' : ''; ?>><?php echo str_replace('_', ' ', $tz); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="taxRate">Tax Rate (%)</label>
                                        <input type="number" name="tax_rate" id="taxRate" value="<?php echo $settings['tax_rate']; ?>" step="0.01" min="0" max="100">
                                    </div>
                                    <div>
                                        <label for="dateFormat">Date Format</label>
                                        <select name="date_format" id="dateFormat">
                                            <option value="d M Y" <?php echo $settings['date_format'] === 'd M Y' ? 'selected' : ''; ?>>01 Jan 2026</option>
                                            <option value="Y-m-d" <?php echo $settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>2026-01-01</option>
                                            <option value="d/m/Y" <?php echo $settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>01/01/2026</option>
                                            <option value="m/d/Y" <?php echo $settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>01/01/2026 (US)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="timeFormat">Time Format</label>
                                        <select name="time_format" id="timeFormat">
                                            <option value="H:i" <?php echo $settings['time_format'] === 'H:i' ? 'selected' : ''; ?>>14:30 (24hr)</option>
                                            <option value="h:i A" <?php echo $settings['time_format'] === 'h:i A' ? 'selected' : ''; ?>>02:30 PM (12hr)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header">
                                <i class="fas fa-cash-register text-amber-500"></i> POS Defaults
                            </div>
                            <div class="card-body">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label>Default Branch</label>
                                        <select name="default_branch_id">
                                            <option value="0">-- Current Branch --</option>
                                            <?php foreach ($branches as $branch): ?>
                                            <option value="<?php echo $branch['id']; ?>" <?php echo (int)$settings['default_branch_id'] === (int)$branch['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Default Payment Method</label>
                                        <select name="default_payment_method">
                                            <option value="cash" <?php echo $settings['default_payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                            <option value="mpesa" <?php echo $settings['default_payment_method'] === 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
                                            <option value="card" <?php echo $settings['default_payment_method'] === 'card' ? 'selected' : ''; ?>>Card</option>
                                            <option value="bank" <?php echo $settings['default_payment_method'] === 'bank' ? 'selected' : ''; ?>>Bank Transfer</option>
                                            <option value="credit" <?php echo $settings['default_payment_method'] === 'credit' ? 'selected' : ''; ?>>Credit</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-check"></i> Save General Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- BRANCHES TAB -->
                <div id="tab-branches" class="tab-content <?php echo $active_tab === 'branches' ? 'block' : 'hidden'; ?>">
                    <div class="card mb-5">
                        <div class="card-header flex justify-between items-center">
                            <span><i class="fas fa-code-branch text-amber-500"></i> Branch Management</span>
                            <button onclick="openBranchModal()" class="btn-primary text-xs py-1.5">
                                <i class="fas fa-plus"></i> Add Branch
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-800">
                                            <th class="text-left py-2 px-3 text-slate-400 font-semibold text-xs">Name</th>
                                            <th class="text-left py-2 px-3 text-slate-400 font-semibold text-xs">Code</th>
                                            <th class="text-left py-2 px-3 text-slate-400 font-semibold text-xs">Location</th>
                                            <th class="text-left py-2 px-3 text-slate-400 font-semibold text-xs">Tax Rate</th>
                                            <th class="text-left py-2 px-3 text-slate-400 font-semibold text-xs">Status</th>
                                            <th class="text-right py-2 px-3 text-slate-400 font-semibold text-xs">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="branchesTableBody">
                                        <?php foreach ($branches as $branch): ?>
                                        <tr data-branch-id="<?php echo (int) $branch['id']; ?>" class="border-b border-slate-800">
                                            <td class="py-3 px-3 text-slate-100">
                                                <div class="font-medium"><?php echo htmlspecialchars($branch['name']); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($branch['address'] ?? ''); ?></div>
                                            </td>
                                            <td class="py-3 px-3 text-slate-100"><code><?php echo htmlspecialchars($branch['code']); ?></code></td>
                                            <td class="py-3 px-3 text-slate-100"><?php echo htmlspecialchars($branch['location'] ?? '-'); ?></td>
                                            <td class="py-3 px-3 text-slate-100"><?php echo number_format($branch['tax_rate'] ?? 0, 2); ?>%</td>
                                            <td class="py-3 px-3">
                                                <?php if (!empty($branch['is_active']) || !empty($branch['active'])): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-900/30 text-emerald-400">Active</span>
                                                <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-900/30 text-red-400">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-3 text-right">
                                                <?php if (empty($branch['is_active']) && empty($branch['active'])): ?>
                                                <button onclick="switchToBranch(<?php echo (int) $branch['id']; ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 text-emerald-500 border border-slate-700 mr-1" title="Switch to this branch">
                                                    <i class="fas fa-check text-xs"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button onclick="editBranch(<?php echo (int) $branch['id']; ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 text-amber-500 border border-slate-700 mr-1" title="Edit">
                                                    <i class="fas fa-pen text-xs"></i>
                                                </button>
                                                <button onclick="deleteBranch(<?php echo (int) $branch['id']; ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 text-red-400 border border-slate-700" title="Delete">
                                                    <i class="fas fa-trash text-xs"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($branches)): ?>
                                        <tr>
                                            <td colspan="6" class="py-8 text-center text-slate-500">
                                                <i class="fas fa-code-branch text-2xl mb-2 block"></i>
                                                No branches found. Click "Add Branch" to create one.
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Branch Modal -->
                <div id="branchModal" class="fixed inset-0 z-50 hidden bg-black/70 ">
                    <div class="flex items-center justify-center min-h-screen p-4">
                        <div class="w-full max-w-lg rounded-xl overflow-hidden bg-slate-800 border border-slate-700">
                            <div class="px-5 py-4 flex justify-between items-center bg-slate-900 border-b border-slate-700">
                                <h3 class="font-bold text-white" id="branchModalTitle">Add Branch</h3>
                                <button onclick="closeBranchModal()" class="w-8 h-8 rounded-lg flex items-center justify-center bg-slate-800 text-slate-400"><i class="fas fa-xmark"></i></button>
                            </div>
                            <form id="branchForm" onsubmit="return saveBranch(event)">
                                <input type="hidden" name="action" value="save_branch">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="branch_id" id="branchId" value="">
                                <div class="p-5 space-y-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="branchName">Branch Name <span class="text-red-400">*</span></label>
                                            <input type="text" name="branch_name" id="branchName" required>
                                        </div>
                                        <div>
                                            <label for="branchCode">Code <span class="text-red-400">*</span></label>
                                            <input type="text" name="branch_code" id="branchCode" required placeholder="e.g. KIS">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="branchAddress">Address</label>
                                        <textarea name="branch_address" id="branchAddress" rows="2"></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="branchPhone">Phone</label>
                                            <input type="text" name="branch_phone" id="branchPhone">
                                        </div>
                                        <div>
                                            <label for="branchEmail">Email</label>
                                            <input type="email" name="branch_email" id="branchEmail">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="branchLocation">Location / City</label>
                                            <input type="text" name="branch_location" id="branchLocation">
                                        </div>
                                        <div>
                                            <label for="branchTaxRate">Tax Rate (%)</label>
                                            <input type="number" name="branch_tax_rate" id="branchTaxRate" step="0.01" min="0" max="100" value="0">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="branchOpening">Opening Time</label>
                                            <input type="time" name="branch_opening" id="branchOpening">
                                        </div>
                                        <div>
                                            <label for="branchClosing">Closing Time</label>
                                            <input type="time" name="branch_closing" id="branchClosing">
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-3 rounded-lg bg-slate-900 border border-slate-700">
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="branch_active" id="branchActive" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="text-slate-100">Branch is active</span>
                                    </div>
                                </div>
                                <div class="px-5 py-4 flex justify-end gap-2 bg-slate-900 border-t border-slate-700">
                                    <button type="button" onclick="closeBranchModal()" class="btn-secondary">Cancel</button>
                                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Branch</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- TAXES TAB -->
                <div id="tab-taxes" class="tab-content <?php echo $active_tab === 'taxes' ? 'block' : 'hidden'; ?>">
                    <div class="card mb-5">
                        <div class="card-header flex justify-between items-center">
                            <span><i class="fas fa-calculator text-amber-500"></i> Tax Rates</span>
                            <button onclick="openTaxModal()" class="btn-primary text-xs py-1.5">
                                <i class="fas fa-plus"></i> Add Tax Rate
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-800">
                                            <th class="text-left py-2 px-3 text-slate-400 font-semibold text-xs">Name</th>
                                            <th class="text-left py-2 px-3 text-slate-400 font-semibold text-xs">Rate</th>
                                            <th class="text-left py-2 px-3 text-slate-400 font-semibold text-xs">Type</th>
                                            <th class="text-left py-2 px-3 text-slate-400 font-semibold text-xs">Default</th>
                                            <th class="text-left py-2 px-3 text-slate-400 font-semibold text-xs">Status</th>
                                            <th class="text-right py-2 px-3 text-slate-400 font-semibold text-xs">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="taxRatesTableBody">
                                        <?php foreach ($tax_rates as $tax): ?>
                                        <tr data-tax-id="<?php echo (int) $tax['id']; ?>" class="border-b border-slate-800">
                                            <td class="py-3 px-3 text-slate-100">
                                                <div class="font-medium"><?php echo htmlspecialchars($tax['name']); ?></div>
                                                <?php if (!empty($tax['description'])): ?>
                                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($tax['description']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-3 text-slate-100"><code><?php echo number_format($tax['rate'], 2); ?>%</code></td>
                                            <td class="py-3 px-3 text-slate-100"><?php echo ucfirst(htmlspecialchars($tax['type'] ?? 'exclusive')); ?></td>
                                            <td class="py-3 px-3">
                                                <?php if (!empty($tax['is_default'])): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-900/30 text-amber-500">Default</span>
                                                <?php else: ?>
                                                <span class="text-xs text-slate-500">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-3">
                                                <?php if (!empty($tax['active'])): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-900/30 text-emerald-400">Active</span>
                                                <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-900/30 text-red-400">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-3 text-right">
                                                <button onclick="editTax(<?php echo (int) $tax['id']; ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 text-amber-500 border border-slate-700 mr-1" title="Edit">
                                                    <i class="fas fa-pen text-xs"></i>
                                                </button>
                                                <button onclick="deleteTax(<?php echo (int) $tax['id']; ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 text-red-400 border border-slate-700" title="Delete">
                                                    <i class="fas fa-trash text-xs"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($tax_rates)): ?>
                                        <tr>
                                            <td colspan="6" class="py-8 text-center text-slate-500">
                                                <i class="fas fa-calculator text-2xl mb-2 block"></i>
                                                No tax rates found. Click "Add Tax Rate" to create one.
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tax Modal -->
                <div id="taxModal" class="fixed inset-0 z-50 hidden bg-black/70 ">
                    <div class="flex items-center justify-center min-h-screen p-4">
                        <div class="w-full max-w-lg rounded-xl overflow-hidden bg-slate-800 border border-slate-700">
                            <div class="px-5 py-4 flex justify-between items-center bg-slate-900 border-b border-slate-700">
                                <h3 class="font-bold text-white" id="taxModalTitle">Add Tax Rate</h3>
                                <button onclick="closeTaxModal()" class="w-8 h-8 rounded-lg flex items-center justify-center bg-slate-800 text-slate-400"><i class="fas fa-xmark"></i></button>
                            </div>
                            <form id="taxForm" onsubmit="return saveTax(event)">
                                <input type="hidden" name="action" value="save_tax">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="tax_id" id="taxId" value="">
                                <div class="p-5 space-y-4">
                                    <div>
                                        <label for="taxName">Tax Name <span class="text-red-400">*</span></label>
                                        <input type="text" name="tax_name" id="taxName" required placeholder="e.g. VAT">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="taxRate">Rate (%) <span class="text-red-400">*</span></label>
                                            <input type="number" name="tax_rate" id="taxRate" step="0.01" min="0" max="100" required value="16">
                                        </div>
                                        <div>
                                            <label for="taxType">Type</label>
                                            <select name="tax_type" id="taxType">
                                                <option value="exclusive">Exclusive (added on top)</option>
                                                <option value="inclusive">Inclusive (in price)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="taxDescription">Description</label>
                                        <textarea name="tax_description" id="taxDescription" rows="2"></textarea>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="flex items-center gap-3 p-3 rounded-lg flex-1 bg-slate-900 border border-slate-700">
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="tax_active" id="taxActive" checked>
                                                <span class="toggle-slider"></span>
                                            </label>
                                            <span class="text-slate-100">Active</span>
                                        </div>
                                        <div class="flex items-center gap-3 p-3 rounded-lg flex-1 bg-slate-900 border border-slate-700">
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="tax_is_default" id="taxIsDefault">
                                                <span class="toggle-slider"></span>
                                            </label>
                                            <span class="text-slate-100">Set as default</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-5 py-4 flex justify-end gap-2 bg-slate-900 border-t border-slate-700">
                                    <button type="button" onclick="closeTaxModal()" class="btn-secondary">Cancel</button>
                                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Tax Rate</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ONLINE STORE TAB -->
                <div id="tab-online" class="tab-content <?php echo $active_tab === 'online' ? 'block' : 'hidden'; ?>">
                    <form id="formOnline" onsubmit="return saveOnline(event)">
                        <input type="hidden" name="action" value="save_online">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-globe text-amber-500"></i> Online Store Settings</div>
                            <div class="card-body space-y-5">
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-store text-emerald-400"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Enable Online Store</p>
                                            <p class="text-xs text-slate-500">Allow customers to order online</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="online_store_enabled" <?php echo $settings['online_store_enabled'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div>
                                    <label for="onlineStoreUrl">Online Store URL</label>
                                    <input type="url" name="online_store_url" id="onlineStoreUrl" value="<?php echo htmlspecialchars($settings['online_store_url']); ?>" placeholder="https://yourstore.com">
                                    <p class="text-xs text-slate-500 mt-1.5">
                                        <a href="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/../store/?tenant=' . get_current_tenant_id(); ?>" target="_blank" class="text-amber-500 underline"><i class="fas fa-external-link-alt"></i> Preview your store</a>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fab fa-whatsapp text-amber-500"></i> WhatsApp Ordering</div>
                            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label for="whatsappNumber">WhatsApp Number</label>
                                    <input type="text" name="whatsapp_number" id="whatsappNumber" value="<?php echo htmlspecialchars($settings['whatsapp_number']); ?>" placeholder="+254700000000">
                                </div>
                                <div>
                                    <label for="whatsappMessage">Pre-filled Message</label>
                                    <input type="text" name="whatsapp_message" id="whatsappMessage" value="<?php echo htmlspecialchars($settings['whatsapp_message']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-share-nodes text-amber-500"></i> Social Media Links</div>
                            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div><label for="socialFacebook">Facebook</label><input type="url" name="social_facebook" id="socialFacebook" value="<?php echo htmlspecialchars($settings['social_facebook']); ?>"></div>
                                <div><label for="socialTwitter">Twitter / X</label><input type="url" name="social_twitter" id="socialTwitter" value="<?php echo htmlspecialchars($settings['social_twitter']); ?>"></div>
                                <div><label for="socialInstagram">Instagram</label><input type="url" name="social_instagram" id="socialInstagram" value="<?php echo htmlspecialchars($settings['social_instagram']); ?>"></div>
                                <div><label for="socialTiktok">TikTok</label><input type="url" name="social_tiktok" id="socialTiktok" value="<?php echo htmlspecialchars($settings['social_tiktok']); ?>"></div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-magnifying-glass text-amber-500"></i> SEO Settings</div>
                            <div class="card-body space-y-5">
                                <div>
                                    <label for="metaTitle">Meta Title</label>
                                    <input type="text" name="meta_title" id="metaTitle" value="<?php echo htmlspecialchars($settings['meta_title']); ?>" maxlength="60">
                                    <p class="text-xs text-slate-500 mt-1"><span id="metaTitleCount">0</span>/60 characters</p>
                                </div>
                                <div>
                                    <label for="metaDescription">Meta Description</label>
                                    <textarea name="meta_description" id="metaDescription" rows="2" maxlength="160"><?php echo htmlspecialchars($settings['meta_description']); ?></textarea>
                                    <p class="text-xs text-slate-500 mt-1"><span id="metaDescCount">0</span>/160 characters</p>
                                </div>
                                <div>
                                    <label for="ogImageUrl">Open Graph Image</label>
                                    <input type="url" name="og_image_url" id="ogImageUrl" value="<?php echo htmlspecialchars($settings['og_image_url']); ?>" placeholder="https://example.com/image.jpg">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Online Settings</button>
                        </div>
                    </form>
                </div>

                <!-- LOYALTY TAB -->
                <div id="tab-loyalty" class="tab-content <?php echo $active_tab === 'loyalty' ? 'block' : 'hidden'; ?>">
                    <form id="formLoyalty" onsubmit="return saveLoyalty(event)">
                        <input type="hidden" name="action" value="save_loyalty">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-star text-amber-500"></i> Loyalty Points Program</div>
                            <div class="card-body space-y-5">
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-star text-amber-500"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Enable Loyalty Points</p>
                                            <p class="text-xs text-slate-500">Customers earn points on purchases</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="enable_loyalty" <?php echo $settings['enable_loyalty'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label for="loyaltyPointsRate">Points Earn Rate</label>
                                        <div class="flex items-center gap-2">
                                            <span class="text-slate-500">1 point per</span>
                                            <input type="number" name="loyalty_points_rate" id="loyaltyPointsRate" value="<?php echo $settings['loyalty_points_rate']; ?>" min="1" class="w-20">
                                            <span class="text-slate-500">KES spent</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="loyaltyRedeemPoints">Points to Redeem</label>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <input type="number" name="loyalty_redeem_points" id="loyaltyRedeemPoints" value="<?php echo $settings['loyalty_redeem_points']; ?>" min="1" class="w-20">
                                            <span class="text-slate-500">points =</span>
                                            <input type="number" name="loyalty_points_value" id="loyaltyPointsValue" value="<?php echo $settings['loyalty_points_value']; ?>" step="0.01" min="0" class="w-24">
                                            <span class="text-slate-500">KES</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="loyaltyExpireDays">Points Expire After</label>
                                        <div class="flex items-center gap-2">
                                            <input type="number" name="loyalty_expire_days" id="loyaltyExpireDays" value="<?php echo $settings['loyalty_expire_days']; ?>" min="1" class="w-20">
                                            <span class="text-slate-500">days</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-ticket text-amber-500"></i> Voucher Settings</div>
                            <div class="card-body space-y-5">
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-ticket text-purple-400"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Enable Vouchers</p>
                                            <p class="text-xs text-slate-500">Accept voucher codes for payment</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="enable_vouchers" <?php echo $settings['enable_vouchers'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label for="voucherPrefix">Voucher Prefix</label>
                                        <input type="text" name="voucher_prefix" id="voucherPrefix" value="<?php echo htmlspecialchars($settings['voucher_prefix']); ?>" maxlength="5">
                                    </div>
                                    <div>
                                        <label for="voucherLength">Code Length</label>
                                        <select name="voucher_length" id="voucherLength">
                                            <option value="6" <?php echo $settings['voucher_length'] === '6' ? 'selected' : ''; ?>>6 characters</option>
                                            <option value="8" <?php echo $settings['voucher_length'] === '8' ? 'selected' : ''; ?>>8 characters</option>
                                            <option value="10" <?php echo $settings['voucher_length'] === '10' ? 'selected' : ''; ?>>10 characters</option>
                                            <option value="12" <?php echo $settings['voucher_length'] === '12' ? 'selected' : ''; ?>>12 characters</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="voucherExpireDays">Expires After</label>
                                        <div class="flex items-center gap-2">
                                            <input type="number" name="voucher_expire_days" id="voucherExpireDays" value="<?php echo $settings['voucher_expire_days']; ?>" min="1" class="w-20">
                                            <span class="text-slate-500">days</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="voucherMinSpend">Minimum Spend to Use</label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-500">KES</span>
                                        <input type="number" name="voucher_min_spend" id="voucherMinSpend" value="<?php echo $settings['voucher_min_spend']; ?>" min="0" class="w-32">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Loyalty Settings</button>
                        </div>
                    </form>
                </div>

                <!-- NOTIFICATIONS TAB -->
                <div id="tab-notifications" class="tab-content <?php echo $active_tab === 'notifications' ? 'block' : 'hidden'; ?>">
                    <form id="formNotifications" onsubmit="return saveNotifications(event)">
                        <input type="hidden" name="action" value="save_notifications">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-bell text-amber-500"></i> Notification Preferences</div>
                            <div class="card-body space-y-3">
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-cubes text-red-400"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Low Stock Alerts</p>
                                            <p class="text-xs text-slate-500">Get notified when products run low</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="notify_low_stock" <?php echo $settings['notify_low_stock'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="ml-12">
                                    <label for="lowStockThreshold" class="text-slate-400">Alert when stock falls below:</label>
                                    <input type="number" name="low_stock_threshold" id="lowStockThreshold" value="<?php echo $settings['low_stock_threshold']; ?>" min="1" class="w-24 ml-2">
                                    <span class="text-xs text-slate-500 ml-1">units</span>
                                </div>

                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-shopping-cart text-blue-400"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">New Order Alerts</p>
                                            <p class="text-xs text-slate-500">Notify on new sales</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="notify_new_order" <?php echo $settings['notify_new_order'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-chart-bar text-emerald-400"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Daily Sales Summary</p>
                                            <p class="text-xs text-slate-500">Receive end-of-day sales report</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="notify_daily_report" <?php echo $settings['notify_daily_report'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="ml-12">
                                    <label for="dailyReportTime" class="text-slate-400">Send report at:</label>
                                    <input type="time" name="daily_report_time" id="dailyReportTime" value="<?php echo $settings['daily_report_time']; ?>" class="w-32 ml-2">
                                </div>

                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-envelope text-purple-400"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Email Notifications</p>
                                            <p class="text-xs text-slate-500">Send alerts via email</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="email_notifications" <?php echo $settings['email_notifications'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700 opacity-50">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-mobile-alt text-amber-500"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">SMS Notifications <span class="text-xs px-1.5 py-0.5 rounded ml-1 bg-slate-700 text-slate-400">Coming Soon</span></p>
                                            <p class="text-xs text-slate-500">Send alerts via SMS</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="sms_notifications" disabled>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-wallet text-amber-500"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Low Cash Balance Alert</p>
                                            <p class="text-xs text-slate-500">Alert when register balance is low</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="notify_low_balance" <?php echo $settings['notify_low_balance'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Notifications</button>
                        </div>
                    </form>
                </div>

                <!-- INVOICE TAB -->
                <div id="tab-invoice" class="tab-content <?php echo $active_tab === 'invoice' ? 'block' : 'hidden'; ?>">
                    <form id="formInvoice" onsubmit="return saveInvoice(event)">
                        <input type="hidden" name="action" value="save_invoice">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-file-invoice text-amber-500"></i> Invoice Configuration</div>
                            <div class="card-body">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-4">
                                    <div>
                                        <label for="invoicePrefix">Invoice Prefix</label>
                                        <input type="text" name="invoice_prefix" id="invoicePrefix" value="<?php echo htmlspecialchars($settings['invoice_prefix']); ?>" placeholder="INV-">
                                    </div>
                                    <div>
                                        <label for="invoiceNextNumber">Next Invoice Number</label>
                                        <input type="number" name="invoice_next_number" id="invoiceNextNumber" value="<?php echo $settings['invoice_next_number']; ?>" min="1">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="invoiceFooter">Receipt Footer Message</label>
                                        <textarea name="invoice_footer" id="invoiceFooter" rows="2"><?php echo htmlspecialchars($settings['invoice_footer']); ?></textarea>
                                    </div>
                                    <div>
                                        <label for="receiptWidth">Receipt Paper Size</label>
                                        <select name="receipt_width" id="receiptWidth">
                                            <option value="58" <?php echo $settings['receipt_width'] === '58' ? 'selected' : ''; ?>>58mm (Small Thermal)</option>
                                            <option value="80" <?php echo $settings['receipt_width'] === '80' ? 'selected' : ''; ?>>80mm (Standard Thermal)</option>
                                            <option value="A4" <?php echo $settings['receipt_width'] === 'A4' ? 'selected' : ''; ?>>A4 (PDF Invoice)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="taxDisplayMode">Tax Display Mode</label>
                                        <select name="tax_display_mode" id="taxDisplayMode">
                                            <option value="exclusive" <?php echo $settings['tax_display_mode'] === 'exclusive' ? 'selected' : ''; ?>>Exclusive (Tax added on top)</option>
                                            <option value="inclusive" <?php echo $settings['tax_display_mode'] === 'inclusive' ? 'selected' : ''; ?>>Inclusive (Tax included in price)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-eye text-amber-500"></i> Display Options</div>
                            <div class="card-body space-y-2">
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <span class="text-slate-100"><i class="fas fa-image mr-2 text-amber-500"></i>Show company logo on receipt</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="show_logo_on_receipt" <?php echo $settings['show_logo_on_receipt'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <span class="text-slate-100"><i class="fas fa-calculator mr-2 text-amber-500"></i>Show tax breakdown on receipt</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="show_tax_on_receipt" <?php echo $settings['show_tax_on_receipt'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <span class="text-slate-100"><i class="fas fa-tag mr-2 text-amber-500"></i>Show discount on receipt</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="show_discount_on_receipt" <?php echo $settings['show_discount_on_receipt'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <span class="text-slate-100"><i class="fas fa-print mr-2 text-amber-500"></i>Auto-print receipt after sale</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="auto_print_receipt" <?php echo $settings['auto_print_receipt'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Invoice Settings</button>
                        </div>
                    </form>
                </div>

                <!-- PAYMENT GATEWAYS TAB -->
                <div id="tab-payment_gateways" class="tab-content <?php echo $active_tab === 'payment_gateways' ? 'block' : 'hidden'; ?>">
                    <form id="formPaymentGateways" onsubmit="return savePaymentGateways(event)">
                        <input type="hidden" name="action" value="save_payment_gateways">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="card mb-5">
                            <div class="card-header"><i class="fab fa-stripe" style="color:#635bff"></i> Stripe</div>
                            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label for="stripeSecretKey">Secret Key</label>
                                    <input type="password" name="stripe_secret_key" id="stripeSecretKey" value="<?php echo htmlspecialchars($settings['stripe_secret_key'] ?? ''); ?>" placeholder="sk_live_... or sk_test_...">
                                </div>
                                <div>
                                    <label for="stripePublishableKey">Publishable Key</label>
                                    <input type="password" name="stripe_publishable_key" id="stripePublishableKey" value="<?php echo htmlspecialchars($settings['stripe_publishable_key'] ?? ''); ?>" placeholder="pk_live_... or pk_test_...">
                                </div>
                                <div class="md:col-span-2">
                                    <label for="stripeWebhookSecret">Webhook Secret</label>
                                    <input type="password" name="stripe_webhook_secret" id="stripeWebhookSecret" value="<?php echo htmlspecialchars($settings['stripe_webhook_secret'] ?? ''); ?>" placeholder="whsec_...">
                                    <p class="text-xs text-slate-500 mt-1">Used to verify Stripe webhook signatures</p>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fab fa-paypal" style="color:#0070ba"></i> PayPal</div>
                            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label for="paypalClientId">Client ID</label>
                                    <input type="password" name="paypal_client_id" id="paypalClientId" value="<?php echo htmlspecialchars($settings['paypal_client_id'] ?? ''); ?>" placeholder="PayPal Client ID">
                                </div>
                                <div>
                                    <label for="paypalClientSecret">Client Secret</label>
                                    <input type="password" name="paypal_client_secret" id="paypalClientSecret" value="<?php echo htmlspecialchars($settings['paypal_client_secret'] ?? ''); ?>" placeholder="PayPal Client Secret">
                                </div>
                                <div class="md:col-span-2">
                                    <label for="paypalMode">Mode</label>
                                    <select name="paypal_mode" id="paypalMode">
                                        <option value="sandbox" <?php echo ($settings['paypal_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                                        <option value="live" <?php echo ($settings['paypal_mode'] ?? '') === 'live' ? 'selected' : ''; ?>>Live (Production)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-mobile-alt" style="color:#00a650"></i> M-Pesa (Daraja API)</div>
                            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label for="mpesaConsumerKey">Consumer Key</label>
                                    <input type="password" name="mpesa_consumer_key" id="mpesaConsumerKey" value="<?php echo htmlspecialchars($settings['mpesa_consumer_key'] ?? ''); ?>" placeholder="Your Safaricom Consumer Key">
                                </div>
                                <div>
                                    <label for="mpesaConsumerSecret">Consumer Secret</label>
                                    <input type="password" name="mpesa_consumer_secret" id="mpesaConsumerSecret" value="<?php echo htmlspecialchars($settings['mpesa_consumer_secret'] ?? ''); ?>" placeholder="Your Safaricom Consumer Secret">
                                </div>
                                <div>
                                    <label>Passkey (STK Push)</label>
                                    <input type="password" name="mpesa_passkey" value="<?php echo htmlspecialchars($settings['mpesa_passkey'] ?? ''); ?>" placeholder="STK Push Passkey">
                                </div>
                                <div>
                                    <label>Shortcode</label>
                                    <input type="text" name="mpesa_shortcode" value="<?php echo htmlspecialchars($settings['mpesa_shortcode'] ?? ''); ?>" placeholder="e.g. 174379">
                                </div>
                                <div class="md:col-span-2">
                                    <label>Environment</label>
                                    <select name="mpesa_env">
                                        <option value="sandbox" <?php echo ($settings['mpesa_env'] ?? 'sandbox') === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                                        <option value="production" <?php echo ($settings['mpesa_env'] ?? '') === 'production' ? 'selected' : ''; ?>>Production (Live)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-bolt" style="color:#3395ff"></i> Razorpay</div>
                            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label>Key ID</label>
                                    <input type="password" name="razorpay_key_id" value="<?php echo htmlspecialchars($settings['razorpay_key_id'] ?? ''); ?>" placeholder="rzp_live_... or rzp_test_...">
                                </div>
                                <div>
                                    <label>Key Secret</label>
                                    <input type="password" name="razorpay_key_secret" value="<?php echo htmlspecialchars($settings['razorpay_key_secret'] ?? ''); ?>" placeholder="Your Razorpay Key Secret">
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-layer-group" style="color:#00c3f7"></i> Paystack</div>
                            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label>Secret Key</label>
                                    <input type="password" name="paystack_secret_key" value="<?php echo htmlspecialchars($settings['paystack_secret_key'] ?? ''); ?>" placeholder="sk_live_... or sk_test_...">
                                </div>
                                <div>
                                    <label>Public Key</label>
                                    <input type="password" name="paystack_public_key" value="<?php echo htmlspecialchars($settings['paystack_public_key'] ?? ''); ?>" placeholder="pk_live_... or pk_test_...">
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-wave-square" style="color:#f5a623"></i> Flutterwave</div>
                            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label>Secret Key</label>
                                    <input type="password" name="flutterwave_secret_key" value="<?php echo htmlspecialchars($settings['flutterwave_secret_key'] ?? ''); ?>" placeholder="FLWSECK_...">
                                </div>
                                <div>
                                    <label>Public Key</label>
                                    <input type="password" name="flutterwave_public_key" value="<?php echo htmlspecialchars($settings['flutterwave_public_key'] ?? ''); ?>" placeholder="FLWPUBK_...">
                                </div>
                                <div class="md:col-span-2">
                                    <label>Encryption Key</label>
                                    <input type="password" name="flutterwave_encryption_key" value="<?php echo htmlspecialchars($settings['flutterwave_encryption_key'] ?? ''); ?>" placeholder="FLWSECK_..._X">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Gateway Credentials</button>
                        </div>
                    </form>
                </div>

                <!-- BACKUP TAB -->
                <div id="tab-backup" class="tab-content <?php echo $active_tab === 'backup' ? 'block' : 'hidden'; ?>">
                    <form id="formBackup" onsubmit="return saveBackup(event)">
                        <input type="hidden" name="action" value="save_backup">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-cloud-arrow-up text-amber-500"></i> Backup Configuration</div>
                            <div class="card-body space-y-5">
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-arrow-path text-blue-400"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Automatic Backups</p>
                                            <p class="text-xs text-slate-500">Schedule regular database backups</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="auto_backup" <?php echo $settings['auto_backup'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="ml-12 grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label>Frequency</label>
                                        <select name="backup_frequency">
                                            <option value="hourly" <?php echo $settings['backup_frequency'] === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                            <option value="daily" <?php echo $settings['backup_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                            <option value="weekly" <?php echo $settings['backup_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Time</label>
                                        <input type="time" name="backup_time" value="<?php echo $settings['backup_time']; ?>">
                                    </div>
                                    <div>
                                        <label>Retention (days)</label>
                                        <input type="number" name="backup_retention_days" value="<?php echo $settings['backup_retention_days']; ?>" min="1" max="365">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 mb-6">
                            <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Backup Settings</button>
                            <button type="button" class="btn-secondary" onclick="runBackup()">
                                <i class="fas fa-cloud-arrow-up"></i> Backup Now
                            </button>
                        </div>

                        <?php if (!empty($settings['last_backup_at'])): ?>
                        <p class="text-sm mb-4 text-slate-500"><i class="fas fa-clock mr-1 text-amber-500"></i> Last backup: <?php echo date('d M Y H:i', strtotime($settings['last_backup_at'])); ?></p>
                        <?php endif; ?>
                    </form>

                    <?php if (!empty($backups)): ?>
                    <div class="card">
                        <div class="card-header"><i class="fas fa-clock-rotate-left text-amber-500"></i> Recent Backups</div>
                        <div class="card-body">
                            <div class="space-y-2">
                                <?php foreach ($backups as $bk): ?>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-file-zipper text-slate-500"></i>
                                        <div>
                                            <p class="text-sm font-medium text-slate-100"><?php echo htmlspecialchars($bk['name']); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo date('d M Y H:i', $bk['date']); ?>  <?php echo round($bk['size'] / 1024); ?> KB</p>
                                        </div>
                                    </div>
                                    <a href="<?php echo base_url('ajax/download_backup.php?file=' . urlencode($bk['name'])); ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 text-amber-500 border border-slate-700" title="Download">
                                        <i class="fas fa-download text-xs"></i>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- SAAS TAB -->
                <div id="tab-saas" class="tab-content <?php echo $active_tab === 'saas' ? 'block' : 'hidden'; ?>">
                    <form id="formSaas" onsubmit="return saveSaas(event)">
                        <input type="hidden" name="action" value="save_saas">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-crown text-amber-500"></i> Subscription Plan</div>
                            <div class="card-body">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label>Current Plan</label>
                                        <select name="subscription_plan">
                                            <option value="free">Free</option>
                                            <option value="starter" selected>Starter</option>
                                            <option value="business">Business</option>
                                            <option value="enterprise">Enterprise</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Max Users</label>
                                        <input type="number" name="max_users" value="10" min="1" max="1000">
                                    </div>
                                    <div>
                                        <label>Max Branches</label>
                                        <input type="number" name="max_branches" value="5" min="1" max="100">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-toggle-on text-amber-500"></i> Feature Flags</div>
                            <div class="card-body">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <?php
                                    $features = [
                                        'enable_multi_currency' => 'Multi-Currency',
                                        'enable_api_access' => 'API Access',
                                        'enable_white_label' => 'White Label',
                                        'enable_advanced_reports' => 'Advanced Reports',
                                        'enable_custom_domain' => 'Custom Domain',
                                        'enable_priority_support' => 'Priority Support',
                                    ];
                                    foreach ($features as $key => $label):
                                    ?>
                                    <div class="flex items-center gap-3">
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="<?php echo $key; ?>" value="1">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="text-slate-300 text-sm"><?php echo $label; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save SaaS Settings</button>
                        </div>
                    </form>
                </div>

                <!-- SYSTEM TAB -->
                <div id="tab-system" class="tab-content <?php echo $active_tab === 'system' ? 'block' : 'hidden'; ?>">
                    <form id="formSystem" onsubmit="return saveSystem(event)">
                        <input type="hidden" name="action" value="save_system">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-cash-register text-amber-500"></i> POS Features</div>
                            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-2">
                                <?php
                                $pos_toggles = [
                                    'enable_barcode_scanner' => ['fa-barcode', 'Barcode Scanner', 'Scan products using barcode reader'],
                                    'enable_discounts' => ['fa-percent', 'Discounts', 'Allow applying discounts to sales'],
                                    'enable_returns' => ['fa-undo', 'Returns', 'Process sales returns and refunds'],
                                    'enable_draft_sales' => ['fa-pause', 'Draft Sales', 'Hold and recall sales'],
                                    'enable_loyalty' => ['fa-star', 'Loyalty Points', 'Earn and redeem loyalty points'],
                                    'enable_vouchers' => ['fa-ticket', 'Vouchers', 'Accept voucher codes'],
                                    'round_prices' => ['fa-coins', 'Round Prices', 'Round to nearest whole amount'],
                                ];
                                foreach ($pos_toggles as $key => $info):
                                ?>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas <?php echo $info[0]; ?> text-sm text-amber-500 w-[18px]"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100"><?php echo $info[1]; ?></p>
                                            <p class="text-xs text-slate-500"><?php echo $info[2]; ?></p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="<?php echo $key; ?>" <?php echo $settings[$key] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-boxes-stacked text-amber-500"></i> Inventory Controls</div>
                            <div class="card-body space-y-2">
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-exclamation-triangle text-sm text-red-400 w-[18px]"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Allow Negative Stock</p>
                                            <p class="text-xs text-slate-500">Sell products even when out of stock</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="allow_negative_stock" <?php echo $settings['allow_negative_stock'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-calendar-times text-sm text-amber-500 w-[18px]"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Expiry Tracking</p>
                                            <p class="text-xs text-slate-500">Track product expiry dates</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="enable_expiry_tracking" <?php echo $settings['enable_expiry_tracking'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div>
                                    <label for="defaultReorderLevel">Default Reorder Level</label>
                                    <input type="number" name="default_reorder_level" id="defaultReorderLevel" value="<?php echo $settings['default_reorder_level']; ?>" min="0" max="9999" class="w-32">
                                    <span class="text-xs text-slate-500 ml-1">units</span>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-credit-card text-amber-500"></i> Payment Methods</div>
                            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-2 max-h-96 overflow-y-auto">
                                <?php
                                $payment_toggles = [
                                    'enable_cash' => ['fa-money-bill-wave', 'Cash', 'Accept cash payments'],
                                    'enable_mpesa' => ['fa-mobile-alt', 'M-Pesa', 'Accept M-Pesa mobile money'],
                                    'enable_card' => ['fa-credit-card', 'Card / Bank', 'Accept card and bank payments'],
                                    'enable_credit' => ['fa-hand-holding-dollar', 'Credit / Account', 'Allow customer credit / on-account'],
                                    'enable_stripe' => ['fa-cc-stripe', 'Stripe', 'Card payments via Stripe'],
                                    'enable_paypal' => ['fa-cc-paypal', 'PayPal', 'PayPal payments'],
                                    'enable_razorpay' => ['fa-bolt', 'Razorpay', 'India card/UPI payments'],
                                    'enable_paystack' => ['fa-layer-group', 'Paystack', 'Africa payments'],
                                    'enable_flutterwave' => ['fa-wave-square', 'Flutterwave', 'Africa / global payments'],
                                ];
                                foreach ($payment_toggles as $key => $info):
                                ?>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas <?php echo $info[0]; ?> text-sm text-amber-500 w-[18px]"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100"><?php echo $info[1]; ?></p>
                                            <p class="text-xs text-slate-500"><?php echo $info[2]; ?></p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="<?php echo $key; ?>" <?php echo ($settings[$key] ?? '0') === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-shield-halved text-amber-500"></i> Security</div>
                            <div class="card-body space-y-2">
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-lock text-sm text-emerald-400 w-[18px]"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Require PIN for Refunds</p>
                                            <p class="text-xs text-slate-500">Manager PIN required to process returns</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="require_pin_for_refund" <?php echo $settings['require_pin_for_refund'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-user-shield text-sm text-blue-400 w-[18px]"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Require PIN for Discounts</p>
                                            <p class="text-xs text-slate-500">Manager PIN required to apply discounts</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="require_pin_for_discount" <?php echo $settings['require_pin_for_discount'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div>
                                    <label for="sessionTimeout">Session Timeout</label>
                                    <select name="session_timeout" id="sessionTimeout">
                                        <option value="1800" <?php echo $settings['session_timeout'] === '1800' ? 'selected' : ''; ?>>30 minutes</option>
                                        <option value="3600" <?php echo $settings['session_timeout'] === '3600' ? 'selected' : ''; ?>>1 hour</option>
                                        <option value="7200" <?php echo $settings['session_timeout'] === '7200' ? 'selected' : ''; ?>>2 hours</option>
                                        <option value="14400" <?php echo $settings['session_timeout'] === '14400' ? 'selected' : ''; ?>>4 hours</option>
                                        <option value="28800" <?php echo $settings['session_timeout'] === '28800' ? 'selected' : ''; ?>>8 hours</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-database text-amber-500"></i> Data & Retention</div>
                            <div class="card-body space-y-2">
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-eraser text-sm text-red-400 w-[18px]"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Auto-delete Old Sales</p>
                                            <p class="text-xs text-slate-500">Permanently remove sales older than retention period</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="auto_delete_sales" <?php echo $settings['auto_delete_sales'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div>
                                    <label for="dataRetentionDays">Retention Period (days)</label>
                                    <input type="number" name="data_retention_days" id="dataRetentionDays" value="<?php echo (int) ($settings['data_retention_days'] ?? 365); ?>" min="30" max="2555" class="w-32">
                                    <span class="text-xs text-slate-500 ml-1">days (min 30, max 7 years)</span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-list-check text-sm text-blue-400 w-[18px]"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Enable Audit Logging</p>
                                            <p class="text-xs text-slate-500">Log all data changes for compliance</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="enable_audit_log" <?php echo $settings['enable_audit_log'] === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-5">
                            <div class="card-header"><i class="fas fa-flask text-purple-400"></i> Advanced Settings</div>
                            <div class="card-body space-y-2">
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-flask text-sm text-purple-400 w-[18px]"></i>
                                        <div>
                                            <p class="font-semibold text-slate-100">Enable Advanced Mode</p>
                                            <p class="text-xs text-slate-500">Show advanced business type options, custom features and developer tools</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="advanced_settings" <?php echo $settings['advanced_settings'] === '1' ? 'checked' : ''; ?> onchange="toggleAdvancedPreview(this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div id="advancedPreview" class="p-3 rounded-lg bg-slate-900 border border-slate-600 <?php echo $advanced_mode ? '' : 'hidden'; ?>">
                                    <p class="text-xs font-semibold mb-2 text-purple-400">Available Business Types (<?php echo count($business_types); ?>)</p>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($business_types as $bt): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-slate-800 border border-slate-700 text-slate-300">
                                            <i class="fas <?php echo htmlspecialchars($bt['icon']); ?>" style="color:#f59e0b; font-size:0.65rem;"></i>
                                            <?php echo htmlspecialchars($bt['name']); ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="text-xs mt-2 text-slate-500"><i class="fas fa-info-circle mr-1"></i>All business types are loaded dynamically from <code class="bg-slate-950 px-1 py-0.5 rounded">config/app.php</code>. No hardcoded lists.</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save System Settings</button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script>
function switchSettingsTab(tab, btn) {
    document.querySelectorAll('.tab-content').forEach(function(el) { el.classList.add('hidden'); });
    var target = document.getElementById('tab-' + tab);
    if (target) target.classList.remove('hidden');
    document.querySelectorAll('.menu-item').forEach(function(b) { b.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    var url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);
    document.getElementById('adminMenu').classList.remove('open');
}

function showToast(message, type) {
    type = type || 'info';
    var container = document.getElementById('toastContainer');
    var toast = document.createElement('div');
    var icons = { success: 'fa-check-circle', error: 'fa-circle-xmark', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    var colors = { success: '#34d399', error: '#f87171', warning: '#fbbf24', info: '#60a5fa' };
    toast.className = 'toast toast-' + type;
    toast.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '" style="color:' + (colors[type] || colors.info) + '"></i><span class="flex-1">' + message + '</span><button onclick="this.parentElement.remove()" class="opacity-50 hover:opacity-100"><i class="fas fa-xmark"></i></button>';
    container.appendChild(toast);
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(function() { toast.remove(); }, 300);
    }, 4000);
}

function showLoading() {
    var overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'saving-overlay';
    overlay.innerHTML = '<div class="spinner"></div>';
    document.getElementById('settingsContent').appendChild(overlay);
}

function hideLoading() {
    var el = document.getElementById('loadingOverlay');
    if (el) el.remove();
}

var csrfToken = '<?php echo htmlspecialchars($csrf_token); ?>';

function submitFormAjax(formId, callback) {
    var form = document.getElementById(formId);
    if (!form) return;
    var formData = new FormData(form);
    showLoading();
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo base_url("ajax/save_settings.php"); ?>', true);
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
    xhr.onload = function() {
        hideLoading();
        try {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                showToast(response.message || 'Settings saved successfully', 'success');
                if (callback) callback(response);
            } else {
                showToast(response.error || 'Failed to save settings', 'error');
            }
        } catch (e) {
            showToast('Invalid server response', 'error');
        }
    };
    xhr.onerror = function() {
        hideLoading();
        showToast('Network error. Please try again.', 'error');
    };
    xhr.send(formData);
    return false;
}

function saveGeneralSettings(e) { e.preventDefault(); submitFormAjax('formGeneral'); return false; }
function saveOnline(e) { e.preventDefault(); submitFormAjax('formOnline'); return false; }
function saveLoyalty(e) { e.preventDefault(); submitFormAjax('formLoyalty'); return false; }
function saveNotifications(e) { e.preventDefault(); submitFormAjax('formNotifications'); return false; }
function saveInvoice(e) { e.preventDefault(); submitFormAjax('formInvoice'); return false; }
function savePaymentGateways(e) { e.preventDefault(); submitFormAjax('formPaymentGateways'); return false; }
function saveBackup(e) { e.preventDefault(); submitFormAjax('formBackup'); return false; }
function saveSaas(e) { e.preventDefault(); submitFormAjax('formSaas'); return false; }
function saveSystem(e) { e.preventDefault(); submitFormAjax('formSystem'); return false; }

// Branch CRUD functions
var branchData = <?php echo json_encode(array_map(function($b) { return [
    'id' => (int)$b['id'],
    'name' => $b['name'],
    'code' => $b['code'],
    'address' => $b['address'] ?? '',
    'phone' => $b['phone'] ?? '',
    'email' => $b['email'] ?? '',
    'location' => $b['location'] ?? '',
    'tax_rate' => (float)($b['tax_rate'] ?? 0),
    'opening_time' => $b['opening_time'] ?? '',
    'closing_time' => $b['closing_time'] ?? '',
    'is_active' => (int)(!empty($b['is_active']) || !empty($b['active']))
]; }, $branches)); ?>;

function openBranchModal(mode, branchId) {
    var modal = document.getElementById('branchModal');
    var title = document.getElementById('branchModalTitle');
    var form = document.getElementById('branchForm');
    form.reset();
    document.getElementById('branchId').value = '';
    if (mode === 'edit' && branchId) {
        var branch = branchData.find(function(b) { return b.id === branchId; });
        if (branch) {
            title.textContent = 'Edit Branch: ' + branch.name;
            document.getElementById('branchId').value = branch.id;
            document.getElementById('branchName').value = branch.name;
            document.getElementById('branchCode').value = branch.code;
            document.getElementById('branchAddress').value = branch.address || '';
            document.getElementById('branchPhone').value = branch.phone || '';
            document.getElementById('branchEmail').value = branch.email || '';
            document.getElementById('branchLocation').value = branch.location || '';
            document.getElementById('branchTaxRate').value = branch.tax_rate || 0;
            document.getElementById('branchOpening').value = branch.opening_time ? branch.opening_time.substring(0, 5) : '';
            document.getElementById('branchClosing').value = branch.closing_time ? branch.closing_time.substring(0, 5) : '';
            document.getElementById('branchActive').checked = branch.is_active;
        }
    } else {
        title.textContent = 'Add Branch';
        document.getElementById('branchActive').checked = true;
    }
    modal.classList.remove('hidden');
}
function closeBranchModal() {
    document.getElementById('branchModal').classList.add('hidden');
    document.getElementById('branchForm').reset();
    document.getElementById('branchId').value = '';
}
function editBranch(branchId) { openBranchModal('edit', branchId); }
function deleteBranch(branchId) {
    var branch = branchData.find(function(b) { return b.id === branchId; });
    var name = branch ? branch.name : 'this branch';
    if (!confirm('Delete branch "' + name + '"? This will soft-delete it. Associated data will remain.')) return;
    var formData = new FormData();
    formData.append('action', 'delete_branch');
    formData.append('csrf_token', csrfToken);
    formData.append('branch_id', branchId);
    showLoading();
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo base_url("ajax/save_settings.php"); ?>', true);
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
    xhr.onload = function() {
        hideLoading();
        try {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                showToast(response.message || 'Branch deleted', 'success');
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                showToast(response.error || 'Failed to delete branch', 'error');
            }
        } catch (e) { showToast('Invalid server response', 'error'); }
    };
    xhr.onerror = function() { hideLoading(); showToast('Network error', 'error'); };
    xhr.send(formData);
}
function saveBranch(e) {
    e.preventDefault();
    var form = document.getElementById('branchForm');
    var formData = new FormData(form);
    showLoading();
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo base_url("ajax/save_settings.php"); ?>', true);
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
    xhr.onload = function() {
        hideLoading();
        try {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                showToast(response.message || 'Branch saved', 'success');
                closeBranchModal();
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                showToast(response.error || 'Failed to save branch', 'error');
            }
        } catch (e) { showToast('Invalid server response', 'error'); }
    };
    xhr.onerror = function() { hideLoading(); showToast('Network error', 'error'); };
    xhr.send(formData);
    return false;
}
function switchToBranch(branchId) {
    var branch = branchData.find(function(b) { return b.id === branchId; });
    var name = branch ? branch.name : 'this branch';
    if (!confirm('Switch to branch "' + name + '"?')) return;
    var formData = new FormData();
    formData.append('branch_id', branchId);
    formData.append('csrf_token', csrfToken);
    showLoading();
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo base_url("ajax/switch_branch.php"); ?>', true);
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
    xhr.onload = function() {
        hideLoading();
        try {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                showToast(response.message || 'Switched to branch successfully', 'success');
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                showToast(response.message || 'Failed to switch branch', 'error');
            }
        } catch (e) { showToast('Invalid server response', 'error'); }
    };
    xhr.onerror = function() { hideLoading(); showToast('Network error', 'error'); };
    xhr.send(formData);
}

// Tax CRUD functions
var taxData = <?php echo json_encode(array_map(function($t) { return [
    'id' => (int)$t['id'],
    'name' => $t['name'],
    'rate' => (float)$t['rate'],
    'description' => $t['description'] ?? '',
    'type' => $t['type'] ?? 'exclusive',
    'active' => (int)(!empty($t['active'])),
    'is_default' => (int)(!empty($t['is_default']))
]; }, $tax_rates)); ?>;

function openTaxModal(mode, taxId) {
    var modal = document.getElementById('taxModal');
    var title = document.getElementById('taxModalTitle');
    var form = document.getElementById('taxForm');
    form.reset();
    document.getElementById('taxId').value = '';
    if (mode === 'edit' && taxId) {
        var tax = taxData.find(function(t) { return t.id === taxId; });
        if (tax) {
            title.textContent = 'Edit Tax: ' + tax.name;
            document.getElementById('taxId').value = tax.id;
            document.getElementById('taxName').value = tax.name;
            document.getElementById('taxRate').value = tax.rate;
            document.getElementById('taxType').value = tax.type;
            document.getElementById('taxDescription').value = tax.description || '';
            document.getElementById('taxActive').checked = tax.active;
            document.getElementById('taxIsDefault').checked = tax.is_default;
        }
    } else {
        title.textContent = 'Add Tax Rate';
        document.getElementById('taxActive').checked = true;
        document.getElementById('taxIsDefault').checked = false;
    }
    modal.classList.remove('hidden');
}
function closeTaxModal() {
    document.getElementById('taxModal').classList.add('hidden');
    document.getElementById('taxForm').reset();
    document.getElementById('taxId').value = '';
}
function editTax(taxId) { openTaxModal('edit', taxId); }
function deleteTax(taxId) {
    var tax = taxData.find(function(t) { return t.id === taxId; });
    var name = tax ? tax.name : 'this tax rate';
    if (!confirm('Delete tax rate "' + name + '"? This cannot be undone.')) return;
    var formData = new FormData();
    formData.append('action', 'delete_tax');
    formData.append('csrf_token', csrfToken);
    formData.append('tax_id', taxId);
    showLoading();
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo base_url("ajax/save_settings.php"); ?>', true);
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
    xhr.onload = function() {
        hideLoading();
        try {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                showToast(response.message || 'Tax rate deleted', 'success');
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                showToast(response.error || 'Failed to delete tax rate', 'error');
            }
        } catch (e) { showToast('Invalid server response', 'error'); }
    };
    xhr.onerror = function() { hideLoading(); showToast('Network error', 'error'); };
    xhr.send(formData);
}
function saveTax(e) {
    e.preventDefault();
    var form = document.getElementById('taxForm');
    var formData = new FormData(form);
    showLoading();
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo base_url("ajax/save_settings.php"); ?>', true);
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
    xhr.onload = function() {
        hideLoading();
        try {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                showToast(response.message || 'Tax rate saved', 'success');
                closeTaxModal();
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                showToast(response.error || 'Failed to save tax rate', 'error');
            }
        } catch (e) { showToast('Invalid server response', 'error'); }
    };
    xhr.onerror = function() { hideLoading(); showToast('Network error', 'error'); };
    xhr.send(formData);
    return false;
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('logoPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Logo">';
        };
        reader.readAsDataURL(input.files[0]);
    }
    document.getElementById('removeCompanyLogo').value = '0';
}

function previewIcon(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('iconPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Icon" class="w-full h-full object-contain">';
        };
        reader.readAsDataURL(input.files[0]);
    }
    document.getElementById('removeSiteIcon').value = '0';
}

function removeLogo() {
    if (!confirm('Remove the company logo?')) return;
    document.getElementById('logoPreview').innerHTML = '<i class="fas fa-image text-slate-600 text-xl"></i>';
    document.getElementById('removeCompanyLogo').value = '1';
    var fi = document.querySelector('input[name="company_logo"]');
    if (fi) fi.value = '';
}

function removeIcon() {
    if (!confirm('Remove the site icon?')) return;
    document.getElementById('iconPreview').innerHTML = '<i class="fas fa-image text-slate-600 text-base"></i>';
    document.getElementById('removeSiteIcon').value = '1';
    var fi = document.querySelector('input[name="site_icon"]');
    if (fi) fi.value = '';
}

function onBusinessTypeChange(type) {
    if (!confirm('Changing business type will apply recommended defaults for POS features. Your manual overrides will be preserved. Continue?')) return;
    showToast('Business type will be updated when you save General Settings', 'info');
}

function toggleAdvancedPreview(enabled) {
    var preview = document.getElementById('advancedPreview');
    if (preview) {
        if (enabled) {
            preview.classList.remove('hidden');
        } else {
            preview.classList.add('hidden');
        }
    }
}

function runBackup() {
    if (!confirm('Create a backup now? This may take a few moments.')) return;
    var formData = new FormData();
    formData.append('action', 'run_backup');
    formData.append('csrf_token', csrfToken);
    showLoading();
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo base_url("ajax/save_settings.php"); ?>', true);
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
    xhr.onload = function() {
        hideLoading();
        try {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                showToast('Backup created: ' + response.file, 'success');
                setTimeout(function() { window.location.reload(); }, 1500);
            } else {
                showToast(response.error || 'Backup failed', 'error');
            }
        } catch (e) {
            showToast('Invalid server response', 'error');
        }
    };
    xhr.onerror = function() {
        hideLoading();
        showToast('Network error during backup', 'error');
    };
    xhr.send(formData);
}

document.addEventListener('DOMContentLoaded', function() {
    var metaTitle = document.getElementById('metaTitleCount');
    var metaDesc = document.getElementById('metaDescCount');
    var titleInput = document.querySelector('input[name="meta_title"]');
    var descInput = document.querySelector('textarea[name="meta_description"]');
    if (titleInput && metaTitle) {
        titleInput.addEventListener('input', function() { metaTitle.textContent = this.value.length; });
        metaTitle.textContent = titleInput.value.length;
    }
    if (descInput && metaDesc) {
        descInput.addEventListener('input', function() { metaDesc.textContent = this.value.length; });
        metaDesc.textContent = descInput.value.length;
    }
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>