<?php
/**
 * AJAX endpoint to save company settings
 * POST only - returns JSON
 * Multi-tenant: enforces tenant_id isolation
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/src/paths.php';

if (function_exists('safe_require')) {
    safe_require('auth.php', 'src');
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

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Require login
require_login();

// Verify CSRF
$csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

// Check permission
if (!check_permission('settings.manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id() ?: get_current_tenant_id();
$tenant_id = $tenant_id;

if (!$tenant_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Tenant context missing']);
    exit;
}

$pdo = get_db_connection();
$action = $_POST['action'] ?? '';

try {
    // Detect settings table columns
    $has_group_name = db_has_column('settings', 'group_name');
    $has_category = db_has_column('settings', 'category');
    $has_description = db_has_column('settings', 'description');
    $has_created_by = db_has_column('settings', 'created_by');
    $has_updated_by = db_has_column('settings', 'updated_by');
    $has_setting_type = db_has_column('settings', 'setting_type');

    $settings_scope_column = db_get_scope_column('settings') ?? 'tenant_id';

    // Build dynamic INSERT query based on available columns
    $cols = [$settings_scope_column, 'setting_key', 'setting_value'];
    $vals = ['?', '?', '?'];
    $updates = ['setting_value = VALUES(setting_value)'];

    if ($has_group_name) {
        $cols[] = 'group_name';
        $vals[] = '?';
        $updates[] = 'group_name = VALUES(group_name)';
    }
    if ($has_category) {
        $cols[] = 'category';
        $vals[] = '?';
        $updates[] = 'category = VALUES(category)';
    }
    if ($has_description) {
        $cols[] = 'description';
        $vals[] = '?';
        $updates[] = 'description = VALUES(description)';
    }
    if ($has_setting_type) {
        $cols[] = 'setting_type';
        $vals[] = '?';
        $updates[] = 'setting_type = VALUES(setting_type)';
    }
    if ($has_created_by) {
        $cols[] = 'created_by';
        $vals[] = '?';
    }
    if ($has_updated_by) {
        $cols[] = 'updated_by';
        $vals[] = '?';
        $updates[] = 'updated_by = VALUES(updated_by)';
    }

    $cols[] = 'created_at';
    $vals[] = 'NOW()';
    $cols[] = 'updated_at';
    $vals[] = 'NOW()';
    $updates[] = 'updated_at = NOW()';

    $upsert_sql = "INSERT INTO settings (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ") ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
    $stmt_upsert = $pdo->prepare($upsert_sql);

    /**
     * Save a single setting
     */
    function save_setting_row(PDOStatement $stmt, int $scope_id, int $user_id, string $key, string $value, string $group = 'general', string $type = 'string', string $desc = '', array $col_flags = []): void {
        $params = [$scope_id, $key, $value];
        if ($col_flags['has_group_name']) $params[] = $group;
        if ($col_flags['has_category']) $params[] = $group;
        if ($col_flags['has_description']) $params[] = $desc;
        if ($col_flags['has_setting_type']) $params[] = $type;
        if ($col_flags['has_created_by']) $params[] = $user_id;
        if ($col_flags['has_updated_by']) $params[] = $user_id;
        $stmt->execute($params);
    }

    $col_flags = [
        'has_group_name' => $has_group_name,
        'has_category' => $has_category,
        'has_description' => $has_description,
        'has_setting_type' => $has_setting_type,
        'has_created_by' => $has_created_by,
        'has_updated_by' => $has_updated_by,
    ];

    // =========================================================================
    // SAVE GENERAL SETTINGS
    // =========================================================================
    if ($action === 'save_general') {
        $general_settings = [
            'company_name'      => ['value' => trim($_POST['company_name'] ?? ''), 'type' => 'string', 'desc' => 'Company display name'],
            'company_email'     => ['value' => trim($_POST['tenant_email'] ?? ''), 'type' => 'string', 'desc' => 'Company contact email'],
            'company_phone'     => ['value' => trim($_POST['tenant_phone'] ?? ''), 'type' => 'string', 'desc' => 'Company phone number'],
            'company_address'   => ['value' => trim($_POST['tenant_address'] ?? ''), 'type' => 'string', 'desc' => 'Company physical address'],
            'vat_number'        => ['value' => trim($_POST['vat_number'] ?? ''), 'type' => 'string', 'desc' => 'VAT registration number'],
            'pin_number'        => ['value' => trim($_POST['pin_number'] ?? ''), 'type' => 'string', 'desc' => 'Tax PIN number'],
            'fiscal_stand'      => ['value' => trim($_POST['fiscal_stand'] ?? ''), 'type' => 'string', 'desc' => 'Fiscal receipt stand label'],
            'receipt_description' => ['value' => trim($_POST['receipt_description'] ?? ''), 'type' => 'string', 'desc' => 'Receipt tagline printed below company info'],
            'etims_enabled'     => ['value' => trim($_POST['etims_enabled'] ?? '0'), 'type' => 'string', 'desc' => 'KRA eTIMS integration enabled'],
            'etims_environment' => ['value' => trim($_POST['etims_environment'] ?? 'sandbox'), 'type' => 'string', 'desc' => 'KRA eTIMS environment (sandbox/production)'],
            'etims_tin'         => ['value' => trim($_POST['etims_tin'] ?? ''), 'type' => 'string', 'desc' => 'KRA Tax Identification Number'],
            'etims_branch_id'   => ['value' => trim($_POST['etims_branch_id'] ?? '00'), 'type' => 'string', 'desc' => 'KRA eTIMS branch identifier'],
            'etims_device_serial' => ['value' => trim($_POST['etims_device_serial'] ?? ''), 'type' => 'string', 'desc' => 'KRA eTIMS device serial number'],
            'etims_cmc_key'     => ['value' => trim($_POST['etims_cmc_key'] ?? ''), 'type' => 'string', 'desc' => 'KRA eTIMS communication key'],
            'business_type'     => ['value' => trim($_POST['business_type'] ?? 'retail'), 'type' => 'string', 'desc' => 'Business type for UI customization'],
            'currency'          => ['value' => trim($_POST['currency'] ?? 'KES'), 'type' => 'string', 'desc' => 'Default currency code'],
            'timezone'          => ['value' => trim($_POST['timezone'] ?? 'Africa/Nairobi'), 'type' => 'string', 'desc' => 'System timezone'],
            'date_format'       => ['value' => trim($_POST['date_format'] ?? 'd M Y'), 'type' => 'string', 'desc' => 'Date display format'],
            'time_format'       => ['value' => trim($_POST['time_format'] ?? 'H:i'), 'type' => 'string', 'desc' => 'Time display format'],
            'tax_rate'          => ['value' => (string) floatval($_POST['tax_rate'] ?? 16), 'type' => 'float', 'desc' => 'Default tax rate percentage'],
            'default_branch_id' => ['value' => (string) max(0, intval($_POST['default_branch_id'] ?? 0)), 'type' => 'integer', 'desc' => 'Default branch ID'],
            'default_payment_method' => ['value' => trim($_POST['default_payment_method'] ?? 'cash'), 'type' => 'string', 'desc' => 'Default payment method'],
            'locale'            => ['value' => trim($_POST['locale'] ?? 'en'), 'type' => 'string', 'desc' => 'UI language locale'],
            'site_title'        => ['value' => trim($_POST['site_title'] ?? ''), 'type' => 'string', 'desc' => 'Browser tab title and login heading'],
            'site_tagline'      => ['value' => trim($_POST['site_tagline'] ?? ''), 'type' => 'string', 'desc' => 'Short subtitle below site title'],
        ];

        $pdo->beginTransaction();

        foreach ($general_settings as $key => $meta) {
            save_setting_row($stmt_upsert, $tenant_id, $user_id, $key, $meta['value'], 'general', $meta['type'], $meta['desc'], $col_flags);
        }

        // Handle logo removal
        if (!empty($_POST['remove_company_logo']) && $_POST['remove_company_logo'] === '1') {
            $scope_col = function_exists('db_get_scope_column') ? db_get_scope_column('settings') : 'tenant_id';
            $stmt_del = $pdo->prepare("DELETE FROM settings WHERE {$scope_col} = ? AND setting_key = 'company_logo' LIMIT 1");
            $stmt_del->execute([$tenant_id]);
            unset($_SESSION['company_logo']);
            $company_logo = null;
        }

        // Handle icon removal
        if (!empty($_POST['remove_site_icon']) && $_POST['remove_site_icon'] === '1') {
            $scope_col = function_exists('db_get_scope_column') ? db_get_scope_column('settings') : 'tenant_id';
            $stmt_del = $pdo->prepare("DELETE FROM settings WHERE {$scope_col} = ? AND setting_key = 'site_icon' LIMIT 1");
            $stmt_del->execute([$tenant_id]);
            unset($_SESSION['site_icon']);
            $icon_result = ['path' => null];
        }

        // Handle logo upload
        $logo_result = ['path' => null];
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $logo_result = handle_logo_upload($_FILES['company_logo'], $tenant_id);
            if ($logo_result['success']) {
                save_setting_row($stmt_upsert, $tenant_id, $user_id, 'company_logo', $logo_result['path'], 'general', 'string', 'Company logo path', $col_flags);
            } else {
                throw new Exception($logo_result['error']);
            }
        }

        // Handle site icon upload
        if (!isset($icon_result)) $icon_result = ['path' => null];
        if (isset($_FILES['site_icon']) && $_FILES['site_icon']['error'] === UPLOAD_ERR_OK) {
            $icon_result = handle_icon_upload($_FILES['site_icon'], $tenant_id);
            if ($icon_result['success']) {
                save_setting_row($stmt_upsert, $tenant_id, $user_id, 'site_icon', $icon_result['path'], 'general', 'string', 'Site favicon path', $col_flags);
            } else {
                throw new Exception($icon_result['error']);
            }
        }

        // Keep tenant profile data in sync with settings values.
        $company_name = trim($_POST['company_name'] ?? '');
        $company_email = trim($_POST['company_email'] ?? '');
        $company_phone = trim($_POST['company_phone'] ?? '');
        $company_address = trim($_POST['company_address'] ?? '');
        $company_logo = $logo_result['path'] ?? null;

        if ($company_name) {
            $tenantUpdateSql = "
                UPDATE tenants
                SET name = ?, email = ?, phone = ?, address = ?, currency = ?, timezone = ?, tax_rate = ?, updated_at = NOW()
            ";
            $tenantUpdateParams = [
                $company_name,
                $company_email,
                $company_phone,
                $company_address,
                trim($_POST['currency'] ?? 'KES'),
                trim($_POST['timezone'] ?? 'Africa/Nairobi'),
                (float) ($_POST['tax_rate'] ?? 16),
            ];

            if ($company_logo !== null && db_has_column('tenants', 'logo_url')) {
                $tenantUpdateSql .= ", logo_url = ?";
                $tenantUpdateParams[] = $company_logo;
            }

            $tenantUpdateSql .= " WHERE id = ?";
            $tenantUpdateParams[] = $tenant_id;

            $stmt = $pdo->prepare($tenantUpdateSql);
            $stmt->execute($tenantUpdateParams);
        }

        // Apply business type defaults if type changed
        $new_type = trim($_POST['business_type'] ?? 'retail');

        // Get current business type for change detection
        $current_type = 'retail';
        try {
            $stmt_current = $pdo->prepare("
                SELECT business_type
                FROM tenants
                WHERE id = ?
                LIMIT 1
            ");
            $stmt_current->execute([$tenant_id]);
            $current_row = $stmt_current->fetch(PDO::FETCH_ASSOC);
            if ($current_row && $current_row['business_type']) {
                $current_type = $current_row['business_type'];
            }
        } catch (Exception $e) {
            // Ignore error, use default
        }

        $business_type_changed = ($current_type !== $new_type);

        apply_business_type_defaults($pdo, $tenant_id, $user_id, $new_type, $stmt_upsert, $col_flags);

        try {
            $stmt_bt = $pdo->prepare("SELECT id FROM business_types WHERE code = ? AND active = 1 LIMIT 1");
            $stmt_bt->execute([$new_type]);
            $bt_row = $stmt_bt->fetch(PDO::FETCH_ASSOC);
            $stmt_upd = $pdo->prepare("UPDATE tenants SET business_type = ?, updated_at = NOW() WHERE id = ?");
            $stmt_upd->execute([$new_type, $tenant_id]);

            if ($bt_row) {
                $_SESSION['business_type_id'] = $bt_row['id'];
                $_SESSION['business_type_id_' . $tenant_id] = $bt_row['id'];
            }
        } catch (Exception $e) {
            error_log("Could not update tenant business_type: " . $e->getMessage());
        }

        $pdo->commit();

        // Update session so get_current_business_type() returns the new value immediately
        // Also update both session keys for proper caching
        $session_key = 'business_type_' . $tenant_id;
        $session_key_id = 'business_type_id_' . $tenant_id;
        
        $_SESSION[$session_key] = $new_type;
        $_SESSION['business_type'] = $new_type;  // Global fallback key
        $_SESSION['tenant_name'] = $company_name ?: ($_SESSION['tenant_name'] ?? '');
        $_SESSION['tenant_name'] = $_SESSION['tenant_name'];

        // Update branding session keys so they appear immediately in the browser
        $saved_site_title = trim($_POST['site_title'] ?? '');
        $saved_site_icon  = $icon_result['path'] ?? (trim($_POST['site_icon'] ?? ''));
        if ($saved_site_title) {
            $_SESSION['site_title'] = $saved_site_title;
        }
        if ($saved_site_icon) {
            $_SESSION['site_icon'] = $saved_site_icon;
        }

        // Clear both ID keys
        unset($_SESSION['business_type_id']);
        unset($_SESSION[$session_key_id]);
        
        // Force clear any static caches in functions
        // by updating the session with the ID from earlier lookup
        if (!empty($bt_row['id'])) {
            $_SESSION[$session_key_id] = $bt_row['id'];
            $_SESSION['business_type_id'] = $bt_row['id'];
        }
        
        // Clear settings cache
        if (function_exists('get_settings')) {
            get_settings(null, null, -1); // Force cache clear
        }

        // Clear PosBootstrapService file + APCu cache
        try {
            require_once __DIR__ . '/../../src/Pos/PosBootstrapService.php';
            $boot = new \App\Pos\PosBootstrapService($pdo, (int)$tenant_id, (int)($_SESSION['branch_id'] ?? 0));
            $boot->invalidate('settings');
            $boot->invalidate('branches');
        } catch (Exception $e) {
            error_log('Cache invalidation error: ' . $e->getMessage());
        }

        // Return the new business type in response so UI can update
        $message = 'General settings saved successfully';
        if ($business_type_changed) {
            $message .= '. Business type changed to ' . ucfirst($new_type) . ' - reloading data...';
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'new_business_type' => $new_type,
            'new_business_type_id' => $bt_row['id'] ?? null,
            'settings' => get_settings(null, null, $tenant_id)
        ]);
        exit;
    }

    // =========================================================================
    // SAVE NOTIFICATION SETTINGS
    // =========================================================================
    if ($action === 'save_notifications') {
        $notif_settings = [
            'notify_low_stock'     => ['value' => isset($_POST['notify_low_stock']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Low stock alert toggle'],
            'low_stock_threshold'  => ['value' => (string) max(1, intval($_POST['low_stock_threshold'] ?? 10)), 'type' => 'integer', 'desc' => 'Low stock alert threshold'],
            'notify_new_order'     => ['value' => isset($_POST['notify_new_order']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'New order alert toggle'],
            'notify_daily_report'  => ['value' => isset($_POST['notify_daily_report']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Daily sales summary toggle'],
            'daily_report_time'    => ['value' => trim($_POST['daily_report_time'] ?? '08:00'), 'type' => 'string', 'desc' => 'Daily report send time'],
            'email_notifications'  => ['value' => isset($_POST['email_notifications']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Email notifications toggle'],
            'sms_notifications'    => ['value' => isset($_POST['sms_notifications']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'SMS notifications toggle (future)'],
            'notify_low_balance'   => ['value' => isset($_POST['notify_low_balance']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Low cash balance alert'],
        ];

        $pdo->beginTransaction();
        foreach ($notif_settings as $key => $meta) {
            save_setting_row($stmt_upsert, $tenant_id, $user_id, $key, $meta['value'], 'notifications', $meta['type'], $meta['desc'], $col_flags);
        }
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Notification settings saved']);
        exit;
    }

    // =========================================================================
    // SAVE INVOICE SETTINGS
    // =========================================================================
    if ($action === 'save_invoice') {
        $invoice_settings = [
            'invoice_prefix'         => ['value' => trim($_POST['invoice_prefix'] ?? 'INV-'), 'type' => 'string', 'desc' => 'Invoice number prefix'],
            'invoice_next_number'    => ['value' => (string) max(1, intval($_POST['invoice_next_number'] ?? 1001)), 'type' => 'integer', 'desc' => 'Next invoice number'],
            'invoice_footer'         => ['value' => trim($_POST['invoice_footer'] ?? 'Thank you for your business!'), 'type' => 'string', 'desc' => 'Invoice footer message'],
            'receipt_width'          => ['value' => trim($_POST['receipt_width'] ?? '80'), 'type' => 'string', 'desc' => 'Receipt paper width'],
            'show_logo_on_receipt'   => ['value' => isset($_POST['show_logo_on_receipt']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Show company logo on receipt'],
            'show_tax_on_receipt'    => ['value' => isset($_POST['show_tax_on_receipt']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Show tax breakdown on receipt'],
            'show_discount_on_receipt' => ['value' => isset($_POST['show_discount_on_receipt']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Show discount on receipt'],
            'auto_print_receipt'     => ['value' => isset($_POST['auto_print_receipt']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Auto-print receipt after sale'],
            'tax_display_mode'       => ['value' => trim($_POST['tax_display_mode'] ?? 'exclusive'), 'type' => 'string', 'desc' => 'Tax display: inclusive or exclusive'],
        ];

        $pdo->beginTransaction();
        foreach ($invoice_settings as $key => $meta) {
            save_setting_row($stmt_upsert, $tenant_id, $user_id, $key, $meta['value'], 'invoice', $meta['type'], $meta['desc'], $col_flags);
        }
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Invoice settings saved']);
        exit;
    }

    // =========================================================================
    // SAVE PAYMENT GATEWAY CREDENTIALS
    // =========================================================================
    if ($action === 'save_payment_gateways') {
        $gateway_settings = [
            'stripe_secret_key'         => ['value' => trim($_POST['stripe_secret_key'] ?? ''), 'type' => 'string', 'desc' => 'Stripe secret key'],
            'stripe_publishable_key'    => ['value' => trim($_POST['stripe_publishable_key'] ?? ''), 'type' => 'string', 'desc' => 'Stripe publishable key'],
            'stripe_webhook_secret'     => ['value' => trim($_POST['stripe_webhook_secret'] ?? ''), 'type' => 'string', 'desc' => 'Stripe webhook secret'],
            'paypal_client_id'          => ['value' => trim($_POST['paypal_client_id'] ?? ''), 'type' => 'string', 'desc' => 'PayPal client ID'],
            'paypal_client_secret'      => ['value' => trim($_POST['paypal_client_secret'] ?? ''), 'type' => 'string', 'desc' => 'PayPal client secret'],
            'paypal_mode'               => ['value' => trim($_POST['paypal_mode'] ?? 'sandbox'), 'type' => 'string', 'desc' => 'PayPal environment mode'],
            'mpesa_consumer_key'        => ['value' => trim($_POST['mpesa_consumer_key'] ?? ''), 'type' => 'string', 'desc' => 'M-Pesa consumer key'],
            'mpesa_consumer_secret'     => ['value' => trim($_POST['mpesa_consumer_secret'] ?? ''), 'type' => 'string', 'desc' => 'M-Pesa consumer secret'],
            'mpesa_passkey'             => ['value' => trim($_POST['mpesa_passkey'] ?? ''), 'type' => 'string', 'desc' => 'M-Pesa STK passkey'],
            'mpesa_shortcode'           => ['value' => trim($_POST['mpesa_shortcode'] ?? ''), 'type' => 'string', 'desc' => 'M-Pesa shortcode'],
            'mpesa_env'                 => ['value' => trim($_POST['mpesa_env'] ?? 'sandbox'), 'type' => 'string', 'desc' => 'M-Pesa environment'],
            'razorpay_key_id'           => ['value' => trim($_POST['razorpay_key_id'] ?? ''), 'type' => 'string', 'desc' => 'Razorpay key ID'],
            'razorpay_key_secret'       => ['value' => trim($_POST['razorpay_key_secret'] ?? ''), 'type' => 'string', 'desc' => 'Razorpay key secret'],
            'paystack_secret_key'       => ['value' => trim($_POST['paystack_secret_key'] ?? ''), 'type' => 'string', 'desc' => 'Paystack secret key'],
            'paystack_public_key'       => ['value' => trim($_POST['paystack_public_key'] ?? ''), 'type' => 'string', 'desc' => 'Paystack public key'],
            'flutterwave_secret_key'    => ['value' => trim($_POST['flutterwave_secret_key'] ?? ''), 'type' => 'string', 'desc' => 'Flutterwave secret key'],
            'flutterwave_public_key'    => ['value' => trim($_POST['flutterwave_public_key'] ?? ''), 'type' => 'string', 'desc' => 'Flutterwave public key'],
            'flutterwave_encryption_key' => ['value' => trim($_POST['flutterwave_encryption_key'] ?? ''), 'type' => 'string', 'desc' => 'Flutterwave encryption key'],
            'square_app_id'             => ['value' => trim($_POST['square_app_id'] ?? ''), 'type' => 'string', 'desc' => 'Square application ID'],
            'square_access_token'       => ['value' => trim($_POST['square_access_token'] ?? ''), 'type' => 'string', 'desc' => 'Square access token'],
            'square_env'                => ['value' => trim($_POST['square_env'] ?? 'sandbox'), 'type' => 'string', 'desc' => 'Square environment'],
        ];

        $pdo->beginTransaction();
        foreach ($gateway_settings as $key => $meta) {
            save_setting_row($stmt_upsert, $tenant_id, $user_id, $key, $meta['value'], 'payment_gateways', $meta['type'], $meta['desc'], $col_flags);
        }
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Payment gateway credentials saved']);
        exit;
    }

    // =========================================================================
    // SAVE SYSTEM / POS SETTINGS
    // =========================================================================
    if ($action === 'save_system') {
        $system_settings = [
            'enable_barcode_scanner'  => ['value' => isset($_POST['enable_barcode_scanner']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable barcode scanner'],
            'enable_discounts'        => ['value' => isset($_POST['enable_discounts']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable discounts'],
            'enable_returns'          => ['value' => isset($_POST['enable_returns']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable sales returns'],
            'enable_draft_sales'      => ['value' => isset($_POST['enable_draft_sales']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable draft/hold sales'],
            'allow_negative_stock'    => ['value' => isset($_POST['allow_negative_stock']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Allow negative stock'],
            'enable_expiry_tracking'  => ['value' => isset($_POST['enable_expiry_tracking']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable expiry date tracking'],
            'default_reorder_level'   => ['value' => (string) max(0, intval($_POST['default_reorder_level'] ?? 10)), 'type' => 'integer', 'desc' => 'Default reorder level'],
            'session_timeout'         => ['value' => (string) max(300, intval($_POST['session_timeout'] ?? 7200)), 'type' => 'integer', 'desc' => 'Session timeout in seconds'],
            'require_pin_for_refund'  => ['value' => isset($_POST['require_pin_for_refund']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Require PIN for refunds'],
            'require_pin_for_discount' => ['value' => isset($_POST['require_pin_for_discount']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Require PIN for discounts'],
            'enable_loyalty'          => ['value' => isset($_POST['enable_loyalty']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable loyalty points'],
            'enable_vouchers'         => ['value' => isset($_POST['enable_vouchers']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable vouchers'],
            'round_prices'            => ['value' => isset($_POST['round_prices']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Round prices to nearest whole'],
            'enable_cash'             => ['value' => isset($_POST['enable_cash']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Accept cash payments'],
            'enable_mpesa'            => ['value' => isset($_POST['enable_mpesa']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Accept M-Pesa payments'],
            'enable_card'             => ['value' => isset($_POST['enable_card']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Accept card payments'],
            'enable_credit'           => ['value' => isset($_POST['enable_credit']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Allow customer credit'],
            'enable_stripe'           => ['value' => isset($_POST['enable_stripe']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Stripe card payments'],
            'enable_paypal'           => ['value' => isset($_POST['enable_paypal']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'PayPal payments'],
            'enable_razorpay'         => ['value' => isset($_POST['enable_razorpay']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Razorpay India payments'],
            'enable_paystack'         => ['value' => isset($_POST['enable_paystack']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Paystack Africa payments'],
            'enable_flutterwave'      => ['value' => isset($_POST['enable_flutterwave']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Flutterwave payments'],
            'enable_square'           => ['value' => isset($_POST['enable_square']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Square card payments'],
            'enable_braintree'        => ['value' => isset($_POST['enable_braintree']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Braintree payments'],
            'enable_authorize_net'    => ['value' => isset($_POST['enable_authorize_net']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Authorize.Net payments'],
            'enable_paytm'            => ['value' => isset($_POST['enable_paytm']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Paytm India payments'],
            'enable_wechat_pay'       => ['value' => isset($_POST['enable_wechat_pay']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'WeChat Pay'],
            'enable_alipay'           => ['value' => isset($_POST['enable_alipay']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Alipay'],
            'enable_google_pay'       => ['value' => isset($_POST['enable_google_pay']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Google Pay'],
            'enable_apple_pay'        => ['value' => isset($_POST['enable_apple_pay']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Apple Pay'],
            'enable_klarna'           => ['value' => isset($_POST['enable_klarna']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Klarna BNPL'],
            'enable_afterpay'         => ['value' => isset($_POST['enable_afterpay']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Afterpay BNPL'],
            'enable_gocardless'       => ['value' => isset($_POST['enable_gocardless']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'GoCardless direct debit'],
            'enable_crypto'           => ['value' => isset($_POST['enable_crypto']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Cryptocurrency payments'],
            'enable_upi'              => ['value' => isset($_POST['enable_upi']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'UPI India payments'],
            'enable_ideal'            => ['value' => isset($_POST['enable_ideal']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'iDEAL Netherlands'],
            'enable_bancontact'       => ['value' => isset($_POST['enable_bancontact']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Bancontact Belgium'],
            'enable_giropay'          => ['value' => isset($_POST['enable_giropay']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Giropay Germany'],
            'enable_sofort'           => ['value' => isset($_POST['enable_sofort']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'SOFORT payments'],
            'enable_eps'              => ['value' => isset($_POST['enable_eps']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'EPS Austria'],
            'enable_przelewy24'       => ['value' => isset($_POST['enable_przelewy24']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Przelewy24 Poland'],
            'enable_trustly'          => ['value' => isset($_POST['enable_trustly']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Trustly Europe'],
            'enable_revolut'          => ['value' => isset($_POST['enable_revolut']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Revolut payments'],
            'enable_wise'             => ['value' => isset($_POST['enable_wise']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Wise transfers'],
            'enable_worldpay'         => ['value' => isset($_POST['enable_worldpay']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Worldpay payments'],
            'enable_sagepay'          => ['value' => isset($_POST['enable_sagepay']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Sage Pay payments'],
            'enable_2checkout'        => ['value' => isset($_POST['enable_2checkout']) ? '1' : '0', 'type' => 'boolean', 'desc' => '2Checkout payments'],
            'enable_payfast'          => ['value' => isset($_POST['enable_payfast']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Payfast South Africa'],
            'enable_sezzle'           => ['value' => isset($_POST['enable_sezzle']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Sezzle BNPL'],
            'enable_affirm'           => ['value' => isset($_POST['enable_affirm']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Affirm BNPL'],
            'enable_venmo'            => ['value' => isset($_POST['enable_venmo']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Venmo payments'],
            'enable_cash_app'         => ['value' => isset($_POST['enable_cash_app']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Cash App Pay'],
            'auto_delete_sales'       => ['value' => isset($_POST['auto_delete_sales']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Auto-delete old sales records'],
            'data_retention_days'     => ['value' => (string) max(30, intval($_POST['data_retention_days'] ?? 365)), 'type' => 'integer', 'desc' => 'Data retention period in days'],
            'enable_audit_log'        => ['value' => isset($_POST['enable_audit_log']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable audit logging'],
        ];

        $pdo->beginTransaction();
        foreach ($system_settings as $key => $meta) {
            save_setting_row($stmt_upsert, $tenant_id, $user_id, $key, $meta['value'], 'system', $meta['type'], $meta['desc'], $col_flags);
        }
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'System settings saved']);
        exit;
    }

    // =========================================================================
    // SAVE SAAS SETTINGS
    // =========================================================================
    if ($action === 'save_saas') {
        $saas_settings = [
            'subscription_plan'        => ['value' => trim($_POST['subscription_plan'] ?? 'starter'), 'type' => 'string', 'desc' => 'Current subscription plan'],
            'max_users'                  => ['value' => (string) max(1, intval($_POST['max_users'] ?? 10)), 'type' => 'integer', 'desc' => 'Maximum allowed users'],
            'max_branches'               => ['value' => (string) max(1, intval($_POST['max_branches'] ?? 5)), 'type' => 'integer', 'desc' => 'Maximum allowed branches'],
            'enable_multi_currency'      => ['value' => isset($_POST['enable_multi_currency']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable multi-currency support'],
            'enable_api_access'          => ['value' => isset($_POST['enable_api_access']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable API access'],
            'enable_white_label'         => ['value' => isset($_POST['enable_white_label']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable white labeling'],
            'enable_advanced_reports'    => ['value' => isset($_POST['enable_advanced_reports']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable advanced reporting'],
            'enable_custom_domain'       => ['value' => isset($_POST['enable_custom_domain']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable custom domain'],
            'enable_priority_support'    => ['value' => isset($_POST['enable_priority_support']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable priority support'],
        ];

        $pdo->beginTransaction();
        foreach ($saas_settings as $key => $meta) {
            save_setting_row($stmt_upsert, $tenant_id, $user_id, $key, $meta['value'], 'saas', $meta['type'], $meta['desc'], $col_flags);
        }
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'SaaS settings saved successfully']);
        exit;
    }

    // =========================================================================
    // SAVE BACKUP SETTINGS
    // =========================================================================
    if ($action === 'save_backup') {
        $backup_settings = [
            'auto_backup'           => ['value' => isset($_POST['auto_backup']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable automatic backup'],
            'backup_frequency'      => ['value' => trim($_POST['backup_frequency'] ?? 'daily'), 'type' => 'string', 'desc' => 'Backup frequency'],
            'backup_time'           => ['value' => trim($_POST['backup_time'] ?? '02:00'), 'type' => 'string', 'desc' => 'Backup scheduled time'],
            'backup_retention_days' => ['value' => (string) max(1, intval($_POST['backup_retention_days'] ?? 30)), 'type' => 'integer', 'desc' => 'Backup retention days'],
        ];

        $pdo->beginTransaction();
        foreach ($backup_settings as $key => $meta) {
            save_setting_row($stmt_upsert, $tenant_id, $user_id, $key, $meta['value'], 'backup', $meta['type'], $meta['desc'], $col_flags);
        }
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Backup settings saved']);
        exit;
    }

    // =========================================================================
    // RUN MANUAL BACKUP
    // =========================================================================
    if ($action === 'run_backup') {
        $backup_dir = STORAGE_PATH . '/backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
        $db_config = require $root_path . '/config/app.php';
        $db_name = $db_config['database']['database'] ?? 'jakababa_pos';
        $db_user = $db_config['database']['username'] ?? 'root';
        $db_pass = $db_config['database']['password'] ?? '';
        $db_host = $db_config['database']['host'] ?? 'localhost';

        $cmd = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s 2>&1',
            escapeshellarg($db_host),
            escapeshellarg($db_user),
            escapeshellarg($db_pass),
            escapeshellarg($db_name),
            escapeshellarg($backup_file)
        );

        exec($cmd, $output, $exit_code);

        if ($exit_code === 0 && file_exists($backup_file)) {
            // Update last backup time
            save_setting_row($stmt_upsert, $tenant_id, $user_id, 'last_backup_at', date('Y-m-d H:i:s'), 'backup', 'string', 'Last backup timestamp', $col_flags);

            echo json_encode([
                'success' => true,
                'message' => 'Backup created successfully',
                'file' => basename($backup_file),
                'size' => filesize($backup_file),
                'download_url' => base_url('ajax/download_backup.php?file=' . urlencode(basename($backup_file)))
            ]);
        } else {
            throw new Exception('Backup failed. Exit code: ' . $exit_code . '. ' . implode(' ', $output));
        }
        exit;
    }

    // =========================================================================
    // SAVE ONLINE STORE SETTINGS
    // =========================================================================
    if ($action === 'save_online') {
        $online_settings = [
            'online_store_enabled' => ['value' => isset($_POST['online_store_enabled']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable online store'],
            'online_store_url'      => ['value' => trim($_POST['online_store_url'] ?? ''), 'type' => 'string', 'desc' => 'Public store URL'],
            'whatsapp_number'      => ['value' => trim($_POST['whatsapp_number'] ?? ''), 'type' => 'string', 'desc' => 'WhatsApp ordering number'],
            'whatsapp_message'     => ['value' => trim($_POST['whatsapp_message'] ?? ''), 'type' => 'string', 'desc' => 'WhatsApp pre-filled message'],
            'meta_title'         => ['value' => trim($_POST['meta_title'] ?? ''), 'type' => 'string', 'desc' => 'SEO meta title'],
            'meta_description'    => ['value' => trim($_POST['meta_description'] ?? ''), 'type' => 'string', 'desc' => 'SEO meta description'],
            'og_image_url'        => ['value' => trim($_POST['og_image_url'] ?? ''), 'type' => 'string', 'desc' => 'Open Graph image URL'],
            'social_facebook'     => ['value' => trim($_POST['social_facebook'] ?? ''), 'type' => 'string', 'desc' => 'Facebook page URL'],
            'social_twitter'      => ['value' => trim($_POST['social_twitter'] ?? ''), 'type' => 'string', 'desc' => 'Twitter/X page URL'],
            'social_instagram'    => ['value' => trim($_POST['social_instagram'] ?? ''), 'type' => 'string', 'desc' => 'Instagram page URL'],
            'social_tiktok'       => ['value' => trim($_POST['social_tiktok'] ?? ''), 'type' => 'string', 'desc' => 'TikTok page URL'],
        ];

        $pdo->beginTransaction();
        foreach ($online_settings as $key => $meta) {
            save_setting_row($stmt_upsert, $tenant_id, $user_id, $key, $meta['value'], 'online', $meta['type'], $meta['desc'], $col_flags);
        }
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Online store settings saved']);
        exit;
    }

    // =========================================================================
    // SAVE LOYALTY & VOUCHER SETTINGS
    // =========================================================================
    if ($action === 'save_loyalty') {
        $loyalty_settings = [
            'enable_loyalty'        => ['value' => isset($_POST['enable_loyalty']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable loyalty points'],
            'loyalty_points_rate'   => ['value' => (string) max(1, intval($_POST['loyalty_points_rate'] ?? 1)), 'type' => 'integer', 'desc' => 'Points earned per KES'],
            'loyalty_redeem_points'  => ['value' => (string) max(1, intval($_POST['loyalty_redeem_points'] ?? 1)), 'type' => 'integer', 'desc' => 'Points required to redeem'],
            'loyalty_points_value'   => ['value' => (string) floatval($_POST['loyalty_points_value'] ?? 0.01), 'type' => 'float', 'desc' => 'KES value per point'],
            'loyalty_expire_days'   => ['value' => (string) max(1, intval($_POST['loyalty_expire_days'] ?? 365)), 'type' => 'integer', 'desc' => 'Days before points expire'],
            'enable_vouchers'       => ['value' => isset($_POST['enable_vouchers']) ? '1' : '0', 'type' => 'boolean', 'desc' => 'Enable voucher acceptance'],
            'voucher_prefix'       => ['value' => trim($_POST['voucher_prefix'] ?? 'VO'), 'type' => 'string', 'desc' => 'Voucher code prefix'],
            'voucher_length'      => ['value' => (string) max(4, intval($_POST['voucher_length'] ?? 8)), 'type' => 'integer', 'desc' => 'Voucher code length'],
            'voucher_expire_days'   => ['value' => (string) max(1, intval($_POST['voucher_expire_days'] ?? 180)), 'type' => 'integer', 'desc' => 'Days before voucher expires'],
            'voucher_min_spend'     => ['value' => (string) max(0, intval($_POST['voucher_min_spend'] ?? 0)), 'type' => 'integer', 'desc' => 'Minimum spend to use voucher'],
        ];

        $pdo->beginTransaction();
        foreach ($loyalty_settings as $key => $meta) {
            save_setting_row($stmt_upsert, $tenant_id, $user_id, $key, $meta['value'], 'loyalty', $meta['type'], $meta['desc'], $col_flags);
        }
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Loyalty & voucher settings saved']);
        exit;
    }

    // =========================================================================
    // SAVE BRANCH (Create or Update)
    // =========================================================================
    if ($action === 'save_branch') {
        $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int) $_POST['branch_id'] : 0;
        $name = trim($_POST['branch_name'] ?? '');
        $code = trim($_POST['branch_code'] ?? '');
        $address = trim($_POST['branch_address'] ?? '');
        $phone = trim($_POST['branch_phone'] ?? '');
        $email = trim($_POST['branch_email'] ?? '');
        $location = trim($_POST['branch_location'] ?? '');
        $tax_rate = (float) ($_POST['branch_tax_rate'] ?? 0);
        $opening_time = trim($_POST['branch_opening'] ?? '');
        $closing_time = trim($_POST['branch_closing'] ?? '');
        $is_active = isset($_POST['branch_active']) ? 1 : 0;

        if (empty($name) || empty($code)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Branch name and code are required']);
            exit;
        }

        // Ensure code is uppercase and unique per tenant
        $code = strtoupper($code);

        $pdo->beginTransaction();

        if ($branch_id > 0) {
            // Update existing branch
            // Verify branch belongs to this tenant
            $stmt_check = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt_check->execute([$branch_id, $tenant_id]);
            if (!$stmt_check->fetch(PDO::FETCH_ASSOC)) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Branch not found']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE branches SET name = ?, code = ?, address = ?, phone = ?, email = ?, location = ?, tax_rate = ?, opening_time = ?, closing_time = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$name, $code, $address, $phone, $email, $location, $tax_rate, $opening_time ?: null, $closing_time ?: null, $is_active, $branch_id, $tenant_id]);
            $message = 'Branch updated: ' . $name;
            $new_id = $branch_id;
        } else {
            // Check unique code
            $stmt_check = $pdo->prepare("SELECT id FROM branches WHERE tenant_id = ? AND code = ? AND deleted_at IS NULL LIMIT 1");
            $stmt_check->execute([$tenant_id, $code]);
            if ($stmt_check->fetch(PDO::FETCH_ASSOC)) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Branch code "' . $code . '" already exists']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO branches (tenant_id, name, code, address, phone, email, location, tax_rate, opening_time, closing_time, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$tenant_id, $name, $code, $address, $phone, $email, $location, $tax_rate, $opening_time ?: null, $closing_time ?: null, $is_active]);
            $new_id = (int) $pdo->lastInsertId();
            $message = 'Branch created: ' . $name;

            // If this is the first branch, set it as default
            $stmt_first = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE tenant_id = ? AND deleted_at IS NULL");
            $stmt_first->execute([$tenant_id]);
            $branch_count = (int) $stmt_first->fetchColumn();
            if ($branch_count === 1) {
                save_setting_row($stmt_upsert, $tenant_id, $user_id, 'default_branch_id', (string) $new_id, 'general', 'integer', 'Default branch ID', $col_flags);
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => $message,
            'branch_id' => $new_id,
            'branch_name' => $name,
            'branch_code' => $code,
        ]);
        exit;
    }

    // =========================================================================
    // DELETE BRANCH (Soft delete)
    // =========================================================================
    if ($action === 'delete_branch') {
        $branch_id = (int) ($_POST['branch_id'] ?? 0);
        if ($branch_id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Invalid branch ID']);
            exit;
        }

        $pdo->beginTransaction();

        // Verify branch belongs to this tenant
        $stmt_check = $pdo->prepare("SELECT id, name FROM branches WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt_check->execute([$branch_id, $tenant_id]);
        $branch = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if (!$branch) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Branch not found']);
            exit;
        }

        // Soft delete
        $stmt = $pdo->prepare("UPDATE branches SET deleted_at = NOW(), updated_at = NOW(), is_active = 0 WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$branch_id, $tenant_id]);

        // If this was the default branch, clear the setting
        $stmt_default = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'default_branch_id' LIMIT 1");
        $stmt_default->execute([$tenant_id]);
        $default_setting = $stmt_default->fetch(PDO::FETCH_ASSOC);
        if ($default_setting && (int) $default_setting['setting_value'] === $branch_id) {
            // Find another active branch to set as default
            $stmt_next = $pdo->prepare("SELECT id FROM branches WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1");
            $stmt_next->execute([$tenant_id]);
            $next_branch = $stmt_next->fetch(PDO::FETCH_ASSOC);
            if ($next_branch) {
                save_setting_row($stmt_upsert, $tenant_id, $user_id, 'default_branch_id', (string) $next_branch['id'], 'general', 'integer', 'Default branch ID', $col_flags);
            } else {
                save_setting_row($stmt_upsert, $tenant_id, $user_id, 'default_branch_id', '0', 'general', 'integer', 'Default branch ID', $col_flags);
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Branch "' . $branch['name'] . '" deleted',
            'branch_id' => $branch_id,
        ]);
        exit;
    }

    // =========================================================================
    // SAVE TAX RATE (Create or Update)
    // =========================================================================
    if ($action === 'save_tax') {
        $tax_id = isset($_POST['tax_id']) && $_POST['tax_id'] !== '' ? (int) $_POST['tax_id'] : 0;
        $name = trim($_POST['tax_name'] ?? '');
        $rate = (float) ($_POST['tax_rate'] ?? 0);
        $type = in_array($_POST['tax_type'] ?? '', ['inclusive', 'exclusive']) ? $_POST['tax_type'] : 'exclusive';
        $description = trim($_POST['tax_description'] ?? '');
        $is_active = isset($_POST['tax_active']) ? 1 : 0;
        $is_default = isset($_POST['tax_is_default']) ? 1 : 0;

        if (empty($name) || $rate < 0 || $rate > 100) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Valid tax name and rate (0-100) are required']);
            exit;
        }

        $tax_scope = db_get_scope_column('tax_rates') ?? 'tenant_id';

        $pdo->beginTransaction();

        if ($tax_id > 0) {
            $stmt_check = $pdo->prepare("SELECT id FROM tax_rates WHERE id = ? AND {$tax_scope} = ? LIMIT 1");
            $stmt_check->execute([$tax_id, $tenant_id]);
            if (!$stmt_check->fetch(PDO::FETCH_ASSOC)) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Tax rate not found']);
                exit;
            }

            // If setting as default, unset other defaults
            if ($is_default) {
                $stmt_unset = $pdo->prepare("UPDATE tax_rates SET is_default = 0 WHERE {$tax_scope} = ?");
                $stmt_unset->execute([$tenant_id]);
            }

            $stmt = $pdo->prepare("UPDATE tax_rates SET name = ?, rate = ?, type = ?, description = ?, active = ?, is_default = ? WHERE id = ? AND {$tax_scope} = ?");
            $stmt->execute([$name, $rate, $type, $description, $is_active, $is_default, $tax_id, $tenant_id]);
            $message = 'Tax rate updated: ' . $name;
            $new_id = $tax_id;
        } else {
            if ($is_default) {
                $stmt_unset = $pdo->prepare("UPDATE tax_rates SET is_default = 0 WHERE {$tax_scope} = ?");
                $stmt_unset->execute([$tenant_id]);
            }

            $stmt = $pdo->prepare("INSERT INTO tax_rates ({$tax_scope}, name, rate, type, description, active, is_default, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$tenant_id, $name, $rate, $type, $description, $is_active, $is_default]);
            $new_id = (int) $pdo->lastInsertId();
            $message = 'Tax rate created: ' . $name;
        }

        // Also sync to settings table for easy lookup
        if ($is_default) {
            save_setting_row($stmt_upsert, $tenant_id, $user_id, 'tax_rate', (string) $rate, 'general', 'float', 'Default tax rate percentage', $col_flags);
            save_setting_row($stmt_upsert, $tenant_id, $user_id, 'tax_display_mode', $type, 'invoice', 'string', 'Tax display: inclusive or exclusive', $col_flags);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => $message,
            'tax_id' => $new_id,
            'tax_name' => $name,
            'tax_rate' => $rate,
        ]);
        exit;
    }

    // =========================================================================
    // DELETE TAX RATE
    // =========================================================================
    if ($action === 'delete_tax') {
        $tax_id = (int) ($_POST['tax_id'] ?? 0);
        if ($tax_id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Invalid tax ID']);
            exit;
        }

        $tax_scope = db_get_scope_column('tax_rates') ?? 'tenant_id';

        $pdo->beginTransaction();

        $stmt_check = $pdo->prepare("SELECT id, name, is_default FROM tax_rates WHERE id = ? AND {$tax_scope} = ? LIMIT 1");
        $stmt_check->execute([$tax_id, $tenant_id]);
        $tax = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if (!$tax) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Tax rate not found']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM tax_rates WHERE id = ? AND {$tax_scope} = ?");
        $stmt->execute([$tax_id, $tenant_id]);

        // If this was the default, find another active tax to set as default
        if (!empty($tax['is_default'])) {
            $stmt_next = $pdo->prepare("SELECT id, rate FROM tax_rates WHERE {$tax_scope} = ? AND active = 1 ORDER BY id LIMIT 1");
            $stmt_next->execute([$tenant_id]);
            $next_tax = $stmt_next->fetch(PDO::FETCH_ASSOC);
            if ($next_tax) {
                $stmt_set = $pdo->prepare("UPDATE tax_rates SET is_default = 1 WHERE id = ?");
                $stmt_set->execute([$next_tax['id']]);
                save_setting_row($stmt_upsert, $tenant_id, $user_id, 'tax_rate', (string) $next_tax['rate'], 'general', 'float', 'Default tax rate percentage', $col_flags);
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Tax rate "' . $tax['name'] . '" deleted',
            'tax_id' => $tax_id,
        ]);
        exit;
    }

    // Unknown action
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Settings save error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Settings save error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// =========================================================================
// HELPER: Handle logo upload
// =========================================================================
function handle_logo_upload(array $file, int $tenant_id): array {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowed_types, true)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'];
    }

    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large. Maximum size: 2MB'];
    }

    $upload_dir = PUBLIC_PATH . '/uploads/logos/company_' . $tenant_id;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'logo_' . $tenant_id . '_' . time() . '.' . $ext;
    $filepath = $upload_dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => 'uploads/logos/company_' . $tenant_id . '/' . $filename];
    }

    return ['success' => false, 'error' => 'Failed to upload file'];
}

// =========================================================================
// HELPER: Handle site icon (favicon) upload
// =========================================================================
function handle_icon_upload(array $file, int $tenant_id): array {
    $allowed_types = ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml', 'image/jpeg'];
    $max_size = 1024 * 1024; // 1MB

    if (!in_array($file['type'], $allowed_types, true)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: PNG, ICO, SVG, JPG'];
    }

    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large. Maximum size: 1MB'];
    }

    $upload_dir = PUBLIC_PATH . '/uploads/icons/company_' . $tenant_id;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === 'jpeg') $ext = 'jpg';
    $filename = 'icon_' . $tenant_id . '_' . time() . '.' . $ext;
    $filepath = $upload_dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => 'uploads/icons/company_' . $tenant_id . '/' . $filename];
    }

    return ['success' => false, 'error' => 'Failed to upload icon'];
}

// =========================================================================
// HELPER: Apply business type defaults
// =========================================================================
function apply_business_type_defaults(PDO $pdo, int $tenant_id, int $user_id, string $business_type, PDOStatement $stmt, array $col_flags): void {
    $defaults = [
        'supermarket' => [
            'enable_barcode_scanner' => '1',
            'allow_negative_stock' => '0',
            'enable_expiry_tracking' => '1',
            'default_reorder_level' => '20',
        ],
        'restaurant' => [
            'enable_barcode_scanner' => '0',
            'allow_negative_stock' => '1',
            'enable_expiry_tracking' => '0',
            'default_reorder_level' => '5',
            'enable_tables' => '1',
            'enable_kitchen' => '1',
        ],
        'pharmacy' => [
            'enable_barcode_scanner' => '1',
            'allow_negative_stock' => '0',
            'enable_expiry_tracking' => '1',
            'default_reorder_level' => '10',
            'require_prescription' => '1',
            'batch_tracking' => '1',
        ],
        'retail' => [
            'enable_barcode_scanner' => '1',
            'allow_negative_stock' => '0',
            'enable_expiry_tracking' => '0',
            'default_reorder_level' => '10',
        ],
        'salon' => [
            'enable_barcode_scanner' => '0',
            'allow_negative_stock' => '1',
            'enable_expiry_tracking' => '0',
            'default_reorder_level' => '0',
        ],
        'hardware' => [
            'enable_barcode_scanner' => '1',
            'allow_negative_stock' => '0',
            'enable_expiry_tracking' => '0',
            'default_reorder_level' => '5',
        ],
    ];

    $type_defaults = $defaults[$business_type] ?? $defaults['retail'];

    foreach ($type_defaults as $key => $value) {
        $params = [$tenant_id, $key, $value];
        if ($col_flags['has_group_name']) $params[] = 'system';
        if ($col_flags['has_category']) $params[] = 'system';
        if ($col_flags['has_description']) $params[] = 'Business type default: ' . $business_type;
        if ($col_flags['has_setting_type']) $params[] = 'boolean';
        if ($col_flags['has_created_by']) $params[] = $user_id;
        if ($col_flags['has_updated_by']) $params[] = $user_id;
        $stmt->execute($params);
    }
}
