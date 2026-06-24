<?php

/**
 * Jakababa POS System - Core Utility Functions
 * SaaS-ready with multi-tenant support - SECURITY HARDENED
 *
 * @package Jakababa
 * @subpackage Functions
 * @version 3.1 - SECURITY HARDENED
 */

// Prevent multiple inclusions
if (defined('FUNCTIONS_LOADED')) {
    return;
}
define('FUNCTIONS_LOADED', true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/paths.php';

// -----------------------------------------------------------------------------
// Security: Input Validation Functions
// -----------------------------------------------------------------------------

if (!function_exists('sanitize_input')) {
    /**
     * Sanitize user input for security
     *
     * @param mixed $input
     * @param string $type 'string', 'int', 'float', 'email', 'url', 'alphanumeric'
     * @return mixed
     */
    function sanitize_input($input, string $type = 'string')
    {
        if ($input === null) {
            return null;
        }
        
        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : 0;
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float)$input : 0.0;
            case 'email':
                $cleaned = filter_var($input, FILTER_SANITIZE_EMAIL);
                return filter_var($cleaned, FILTER_VALIDATE_EMAIL) ? $cleaned : '';
            case 'url':
                $cleaned = filter_var($input, FILTER_SANITIZE_URL);
                return filter_var($cleaned, FILTER_VALIDATE_URL) ? $cleaned : '';
            case 'alphanumeric':
                return preg_replace('/[^a-zA-Z0-9]/', '', (string)$input);
            case 'alphanumeric_space':
                return preg_replace('/[^a-zA-Z0-9\s\-_]/', '', (string)$input);
            case 'string':
            default:
                return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
        }
    }
}

if (!function_exists('generate_csrf_token')) {
    /**
     * Generate a secure CSRF token
     *
     * @param string $form_name
     * @return string
     */
    function generate_csrf_token(string $form_name = 'default'): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$form_name] = $token;
        return $token;
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Verify a CSRF token
     *
     * @param string|null $token
     * @param string $form_name
     * @return bool
     */
    function verify_csrf_token(?string $token = null, string $form_name = 'default'): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }
        if (!$token || empty($_SESSION['csrf_tokens'][$form_name])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_tokens'][$form_name], $token);
    }
}

if (!function_exists('validate_csrf_token')) {
    /**
     * Validate CSRF token from request
     *
     * @param string|null $token
     * @param string $form_name
     * @return bool
     */
    function validate_csrf_token(?string $token = null, string $form_name = 'default'): bool
    {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        return verify_csrf_token($token, $form_name);
    }
}

// -----------------------------------------------------------------------------
// Settings Functions (must be defined early)
// -----------------------------------------------------------------------------

if (!function_exists('get_settings')) {
    /**
     * Get company settings (cached with security)
     *
     * @param string|null $key
     * @param mixed $default
     * @param int|null $tenant_id
     * @return mixed
     */
    function get_settings($key = null, $default = null, $tenant_id = null)
    {
        static $cache = [];
        static $cache_tenant = null;
        
        if ($tenant_id === null && function_exists('get_current_tenant_id')) {
            $tenant_id = get_current_tenant_id();
        }
        
        // Validate tenant_id is positive integer
        $tenant_id = $tenant_id ? (int)$tenant_id : 0;
        if ($tenant_id <= 0) {
            return $key !== null ? $default : [];
        }
        
        // Reset cache if tenant changed
        if ($cache_tenant !== $tenant_id) {
            $cache = [];
            $cache_tenant = $tenant_id;
        }
        
        // Use session cache for cross-request persistence (30-second TTL)
        $sessionKey = '_settings_' . $tenant_id;
        if (empty($cache) && isset($_SESSION[$sessionKey]) && (time() - ($_SESSION[$sessionKey . '_time'] ?? 0) < 30)) {
            $cache = $_SESSION[$sessionKey];
        }

        if (empty($cache) && function_exists('db_fetch_all')) {
            try {
                $rows = db_fetch_all(
                    "SELECT setting_key, setting_value FROM settings WHERE tenant_id = ?",
                    [$tenant_id]
                );
                foreach ($rows as $row) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
                $_SESSION[$sessionKey] = $cache;
                $_SESSION[$sessionKey . '_time'] = time();
            } catch (Exception $e) {
                error_log("Failed to fetch settings: " . $e->getMessage());
            }
        }

        if ($key !== null) {
            return $cache[$key] ?? $default;
        }
        return $cache;
    }
}

if (!function_exists('update_setting')) {
    /**
     * Update a company setting with validation
     *
     * @param string $key
     * @param string $value
     * @param int|null $tenant_id
     * @return bool
     */
    function update_setting($key, $value, $tenant_id = null)
    {
        // Sanitize inputs
        $key = sanitize_input($key, 'alphanumeric');
        if (empty($key)) {
            return false;
        }
        
        if ($tenant_id === null && function_exists('get_current_tenant_id')) {
            $tenant_id = get_current_tenant_id();
        }
        
        $tenant_id = $tenant_id ? (int)$tenant_id : 0;
        if ($tenant_id <= 0) {
            return false;
        }
        
        $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        
        try {
            db_query("
                INSERT INTO settings (tenant_id, setting_key, setting_value, updated_by, updated_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
            ", [$tenant_id, $key, $value, $user_id]);
            
            // Clear cache
            $GLOBALS['__settings_cache'] = null;
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to update setting: " . $e->getMessage());
            return false;
        }
    }
}

// -----------------------------------------------------------------------------
// Permission Helper (if not already defined)
// -----------------------------------------------------------------------------

if (!function_exists('check_permission')) {
    /**
     * Check if user has specific permission
     *
     * @param string $permission_code Permission code to check
     * @return bool True if user has permission
     */
    function check_permission(string $permission_code): bool
    {
        static $cache = [];
        static $wildcardChecked = false;
        static $wildcardCache = [];

        $permission_code = sanitize_input($permission_code, 'alphanumeric');

        if (isset($cache[$permission_code])) {
            return $cache[$permission_code];
        }

        if (function_exists('is_super_admin') && is_super_admin()) {
            $cache[$permission_code] = true;
            return true;
        }

        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            if (in_array($permission_code, $_SESSION['permissions'], true)) {
                $cache[$permission_code] = true;
                return true;
            }
        }

        if (isset($_SESSION['user_permissions']) && is_array($_SESSION['user_permissions'])) {
            if (in_array($permission_code, $_SESSION['user_permissions'], true)) {
                $cache[$permission_code] = true;
                return true;
            }
        }

        if (!$wildcardChecked) {
            $allPermissions = array_merge(
                $_SESSION['permissions'] ?? [],
                $_SESSION['user_permissions'] ?? []
            );
            foreach ($allPermissions as $perm) {
                if (str_contains($perm, '*')) {
                    $wildcardCache[] = '/^' . str_replace('*', '.*', str_replace('/', '\/', $perm)) . '$/';
                }
            }
            $wildcardChecked = true;
        }

        foreach ($wildcardCache as $pattern) {
            if (preg_match($pattern, $permission_code)) {
                $cache[$permission_code] = true;
                return true;
            }
        }

        $cache[$permission_code] = false;
        return false;
    }
}

if (!function_exists('enforce_permission')) {
    /**
     * Enforce a permission; if not granted, show 403
     *
     * @param string $permission_code
     * @return void
     */
    function enforce_permission(string $permission_code): void
    {
        if (!check_permission($permission_code)) {
            http_response_code(403);
            
            if (function_exists('is_ajax') && is_ajax()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Permission denied', 'permission' => $permission_code]);
                exit;
            }
            
            die('<h1>403 Forbidden</h1><p>You do not have permission to access this resource.</p>');
        }
    }
}

// -----------------------------------------------------------------------------
// Formatting Helpers
// -----------------------------------------------------------------------------

if (!function_exists('format_currency')) {
    /**
     * Format currency amount (SaaS-aware - uses company currency)
     *
     * @param float $amount
     * @param string|null $currency
     * @param bool $with_symbol
     * @return string
     */
    function format_currency($amount, $currency = null, $with_symbol = true): string
    {
        $amount = (float)$amount;
        
        if ($currency === null) {
            $currency = get_tenant_currency();
        }
        
        $currency = sanitize_input($currency, 'alphanumeric');
        $formatted = number_format($amount, 2, '.', ',');

        if ($with_symbol) {
            return $currency . ' ' . $formatted;
        }

        return $formatted;
    }
}

if (!function_exists('get_tenant_currency')) {
    /**
     * Get current tenant's currency code
     *
     * @param int|null $tenant_id
     * @return string
     */
    function get_tenant_currency($tenant_id = null): string
    {
        // First check session
        if (!empty($_SESSION['tenant_currency'])) {
            return sanitize_input($_SESSION['tenant_currency'], 'alphanumeric');
        }
        
        // Try to get from settings
        try {
            $settings = get_settings(null, null, $tenant_id);
            if (!empty($settings['currency'])) {
                return sanitize_input($settings['currency'], 'alphanumeric');
            }
        } catch (Exception $e) {
            // Fall through to default
        }
        
        return 'KES';
    }
}

if (!function_exists('time_ago')) {
    /**
     * Convert timestamp to human readable "time ago" string
     *
     * @param string|int $datetime Unix timestamp or datetime string
     * @return string
     */
    function time_ago($datetime): string
    {
        if (is_string($datetime)) {
            $time = strtotime($datetime);
        } else {
            $time = (int) $datetime;
        }

        if (!$time || $time <= 0) {
            return 'Just now';
        }

        $now = time();
        $diff = $now - $time;

        if ($diff < 0) {
            return 'Just now';
        }

        if ($diff < 60) {
            return $diff . ' seconds ago';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) { // 30 days
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 31536000) { // 1 year
            $months = floor($diff / 2592000);
            return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        } else {
            $years = floor($diff / 31536000);
            return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
        }
    }
}

// -----------------------------------------------------------------------------
// Number Generation Helpers (SaaS-aware with validation)
// -----------------------------------------------------------------------------

if (!function_exists('generate_return_number')) {
    /**
     * Generate return number with optional company prefix
     *
     * @param int|null $tenant_id
     * @return string
     */
    function generate_return_number($tenant_id = null): string
    {
        if ($tenant_id === null && function_exists('get_current_tenant_id')) {
            $tenant_id = get_current_tenant_id();
        }
        
        $tenant_id = $tenant_id ? (int)$tenant_id : 0;
        $date = date('Ymd');
        $random = sprintf('%04d', random_int(1, 9999));
        $prefix = $tenant_id > 0 ? 'C' . $tenant_id . '-' : '';
        return $prefix . 'R' . $date . '-' . $random;
    }
}

if (!function_exists('generate_tracking_number')) {
    /**
     * Generate shipment tracking number with optional company prefix
     *
     * @param int|null $tenant_id
     * @return string
     */
    function generate_tracking_number($tenant_id = null): string
    {
        if ($tenant_id === null && function_exists('get_current_tenant_id')) {
            $tenant_id = get_current_tenant_id();
        }
        
        $tenant_id = $tenant_id ? (int)$tenant_id : 0;
        $date = date('Ymd');
        $random = sprintf('%04d', random_int(1, 9999));
        $prefix = $tenant_id > 0 ? 'C' . $tenant_id . '-' : '';
        return $prefix . 'SHIP-' . $date . '-' . $random;
    }
}

if (!function_exists('generate_sku')) {
    /**
     * Generate a unique SKU for products (SaaS-aware)
     *
     * @param string $product_name
     * @param int|null $category_id
     * @param int|null $tenant_id
     * @return string
     */
    function generate_sku(string $product_name, $category_id = null, $tenant_id = null): string
    {
        $product_name = sanitize_input($product_name, 'alphanumeric_space');
        
        if ($tenant_id === null && function_exists('get_current_tenant_id')) {
            $tenant_id = get_current_tenant_id();
        }
        
        $tenant_id = $tenant_id ? (int)$tenant_id : 0;

        // Get category prefix if available
        $category_prefix = '';
        if ($category_id) {
            $category_id = (int)$category_id;
            try {
                $category = db_fetch_one(
                    "SELECT LEFT(UPPER(REPLACE(name, ' ', '')), 3) AS prefix FROM categories WHERE id = ? AND tenant_id = ?",
                    [$category_id, $tenant_id]
                );
                if ($category && !empty($category['prefix'])) {
                    $category_prefix = $category['prefix'];
                }
            } catch (Exception $e) {
                error_log("Error getting category prefix: " . $e->getMessage());
            }
        }

        // Get product name prefix (first 3 letters)
        $clean_name = preg_replace('/[^a-zA-Z0-9]/', '', $product_name);
        $name_prefix = strtoupper(substr($clean_name, 0, 3));

        // Random numbers
        $random = sprintf('%04d', random_int(1, 9999));

        // Company prefix
        $company_prefix = $tenant_id > 0 ? 'C' . $tenant_id : '';

        $sku = trim($company_prefix . '-' . $category_prefix . $name_prefix . '-' . $random, '-');
        
        // Check uniqueness
        $existing = db_fetch_value("SELECT id FROM products WHERE sku = ? LIMIT 1", [$sku]);
        if ($existing) {
            return generate_sku($product_name, $category_id, $tenant_id);
        }
        
        return $sku;
    }
}

// -----------------------------------------------------------------------------
// Sequence Helpers (SaaS-aware)
// -----------------------------------------------------------------------------

if (!function_exists('get_next_sequence')) {
    /**
     * Get next sequence number for a type (SaaS-aware)
     *
     * @param string $type
     * @param int|null $tenant_id
     * @return int
     */
    function get_next_sequence(string $type, $tenant_id = null): int
    {
        $type = sanitize_input($type, 'alphanumeric');
        
        if ($tenant_id === null && function_exists('get_current_tenant_id')) {
            $tenant_id = get_current_tenant_id();
        }
        
        $tenant_id = $tenant_id ? (int)$tenant_id : 0;

        if ($tenant_id <= 0) {
            return random_int(1, 9999);
        }

        $table = $type . '_sequences';
        $year = date('Y');
        $month = date('m');

        try {
            // Check if table exists
            if (!function_exists('db_table_exists') || !db_table_exists($table)) {
                return random_int(1, 9999);
            }

            // Insert or update sequence
            db_query("
                INSERT INTO {$table} (tenant_id, type, year, month, last_number, updated_at)
                VALUES (?, ?, ?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE last_number = last_number + 1, updated_at = NOW()
            ", [$tenant_id, $type, $year, $month]);

            $result = db_fetch_one("
                SELECT last_number FROM {$table}
                WHERE tenant_id = ? AND type = ? AND year = ? AND month = ?
            ", [$tenant_id, $type, $year, $month]);

            return $result ? (int) $result['last_number'] : random_int(1, 9999);
        } catch (Exception $e) {
            error_log("Error getting sequence: " . $e->getMessage());
            return random_int(1, 9999);
        }
    }
}

// -----------------------------------------------------------------------------
// Inventory Helpers (SaaS-aware with transaction safety)
// -----------------------------------------------------------------------------

if (!function_exists('update_inventory')) {
    /**
     * Update inventory stock (SaaS-aware with branch isolation)
     *
     * @param int $product_id
     * @param int $branch_id
     * @param int $quantity Change in quantity (positive = increase, negative = decrease)
     * @param string $notes
     * @param int|null $user_id
     * @return bool
     */
    function update_inventory(int $product_id, int $branch_id, int $quantity, string $notes = '', $user_id = null): bool
    {
        $product_id = (int)$product_id;
        $branch_id = (int)$branch_id;
        $quantity = (int)$quantity;
        $notes = sanitize_input($notes, 'string');
        
        if ($user_id === null && function_exists('get_current_user_id')) {
            $user_id = get_current_user_id();
        }
        
        $user_id = $user_id ? (int)$user_id : 0;

        if ($product_id <= 0 || $branch_id <= 0) {
            error_log("Invalid parameters for update_inventory: product_id={$product_id}, branch_id={$branch_id}");
            return false;
        }

        try {
            $pdo = get_db_connection();
            $pdo->beginTransaction();

            // Check current stock
            $tenant_id = function_exists('get_current_tenant_id') ? get_current_tenant_id() : 0;
            $current = db_fetch_one(
                "SELECT stock FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?",
                [$product_id, $branch_id, $tenant_id]
            );

            if ($current) {
                $new_stock = $current['stock'] + $quantity;
                if ($new_stock < 0) {
                    $pdo->rollBack();
                    error_log("Inventory update failed: would go negative for product {$product_id}, branch {$branch_id}");
                    return false;
                }

                db_query(
                    "UPDATE inventory SET stock = stock + ?, updated_at = NOW() WHERE product_id = ? AND branch_id = ? AND tenant_id = ?",
                    [$quantity, $product_id, $branch_id, $tenant_id]
                );
            } else {
                if ($quantity < 0) {
                    $pdo->rollBack();
                    error_log("Inventory update failed: cannot decrement non-existent inventory for product {$product_id}");
                    return false;
                }

                db_insert('inventory', [
                    'tenant_id' => function_exists('get_current_tenant_id') ? get_current_tenant_id() : 0,
                    'product_id' => $product_id,
                    'branch_id' => $branch_id,
                    'stock' => $quantity,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            // Log the change
            db_insert('inventory_logs', [
                'tenant_id' => function_exists('get_current_tenant_id') ? get_current_tenant_id() : 0,
                'product_id' => $product_id,
                'branch_id' => $branch_id,
                'old_stock' => $current['stock'] ?? 0,
                'new_stock' => ($current['stock'] ?? 0) + $quantity,
                'change_amount' => $quantity,
                'notes' => $notes,
                'user_id' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $pdo->commit();

            // Set user ID for triggers
            $pdo->exec("SET @current_user_id = {$user_id}");

            // Log activity
            if (function_exists('log_activity')) {
                $change_type = $quantity > 0 ? 'increased' : 'decreased';
                log_activity(
                    "inventory.{$change_type}",
                    "Inventory {$change_type} for product #{$product_id} by " . abs($quantity) . " units",
                    ['product_id' => $product_id, 'branch_id' => $branch_id, 'quantity' => $quantity, 'notes' => $notes],
                    $user_id,
                    function_exists('get_current_tenant_id') ? get_current_tenant_id() : null
                );
            }

            return true;
        } catch (Exception $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            error_log("Error updating inventory: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_product_stock')) {
    /**
     * Get product stock level
     *
     * @param int $product_id
     * @param int|null $branch_id
     * @return int
     */
    function get_product_stock(int $product_id, $branch_id = null): int
    {
        $product_id = (int)$product_id;
        
        if ($branch_id === null && function_exists('get_current_branch_id')) {
            $branch_id = get_current_branch_id();
        }
        
        $branch_id = $branch_id ? (int)$branch_id : 0;

        if ($branch_id <= 0 || $product_id <= 0) {
            return 0;
        }

        $tenant_id = function_exists('get_current_tenant_id') ? (int) get_current_tenant_id() : 0;
        if ($tenant_id <= 0) {
            return 0;
        }

        $stock = db_fetch_value(
            "SELECT stock FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?",
            [$product_id, $branch_id, $tenant_id]
        );

        return (int) $stock;
    }
}

if (!function_exists('is_low_stock')) {
    /**
     * Check if product has low stock
     *
     * @param int $product_id
     * @param int|null $branch_id
     * @return bool
     */
    function is_low_stock(int $product_id, $branch_id = null): bool
    {
        $product_id = (int)$product_id;
        
        if ($branch_id === null && function_exists('get_current_branch_id')) {
            $branch_id = get_current_branch_id();
        }
        
        $branch_id = $branch_id ? (int)$branch_id : 0;

        if ($branch_id <= 0 || $product_id <= 0) {
            return false;
        }

        $tenant_id = function_exists('get_current_tenant_id') ? (int) get_current_tenant_id() : 0;
        if ($tenant_id <= 0) {
            return true;
        }

        $result = db_fetch_one(
            "SELECT stock, reorder_level FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?",
            [$product_id, $branch_id, $tenant_id]
        );

        if (!$result) {
            return true;
        }
        
        return (int)$result['stock'] <= (int)$result['reorder_level'];
    }
}

// -----------------------------------------------------------------------------
// Notification Helper
// -----------------------------------------------------------------------------

if (!function_exists('send_notification')) {
    /**
     * Send notification to user
     *
     * @param int $user_id
     * @param string $title
     * @param string $message
     * @param string $type
     * @return bool
     */
    function send_notification(int $user_id, string $title, string $message, string $type = 'info'): bool
    {
        $user_id = (int)$user_id;
        $title = sanitize_input($title, 'string');
        $message = sanitize_input($message, 'string');
        $type = sanitize_input($type, 'alphanumeric');
        
        if ($user_id <= 0) {
            return false;
        }
        
        try {
            db_insert('notifications', [
                'user_id' => $user_id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }
}

// -----------------------------------------------------------------------------
// Calculation Helpers
// -----------------------------------------------------------------------------

if (!function_exists('calculate_tax')) {
    /**
     * Calculate tax amount
     *
     * @param float $amount
     * @param float $tax_rate
     * @return float
     */
    function calculate_tax(float $amount, float $tax_rate): float
    {
        return round(($amount * $tax_rate) / 100, 2);
    }
}

if (!function_exists('calculate_discount')) {
    /**
     * Calculate discount amount
     *
     * @param float $amount
     * @param float $discount_value
     * @param string $discount_type 'percent' or 'fixed'
     * @return float
     */
    function calculate_discount(float $amount, float $discount_value, string $discount_type = 'percent'): float
    {
        $discount_type = sanitize_input($discount_type, 'alphanumeric');
        
        if ($discount_type === 'percent') {
            return round(($amount * $discount_value) / 100, 2);
        }
        return min($discount_value, $amount);
    }
}

if (!function_exists('calculate_profit_margin')) {
    /**
     * Calculate profit margin percentage
     *
     * @param float $selling_price
     * @param float $cost_price
     * @return float
     */
    function calculate_profit_margin(float $selling_price, float $cost_price): float
    {
        if ($selling_price <= 0 || $cost_price <= 0) {
            return 0;
        }
        return round((($selling_price - $cost_price) / $selling_price) * 100, 2);
    }
}

// -----------------------------------------------------------------------------
// Validation Helpers
// -----------------------------------------------------------------------------

if (!function_exists('validate_date_range')) {
    /**
     * Validate date range
     *
     * @param string $start_date
     * @param string $end_date
     * @return bool
     */
    function validate_date_range(string $start_date, string $end_date): bool
    {
        if (empty($start_date) || empty($end_date)) {
            return false;
        }

        $start = strtotime($start_date);
        $end = strtotime($end_date);

        return $start !== false && $end !== false && $start <= $end;
    }
}

if (!function_exists('validate_email')) {
    /**
     * Validate email address
     *
     * @param string $email
     * @return bool
     */
    function validate_email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('validate_phone')) {
    /**
     * Validate phone number
     *
     * @param string $phone
     * @return bool
     */
    function validate_phone(string $phone): bool
    {
        // Remove spaces and special characters
        $clean = preg_replace('/[^0-9+]/', '', $phone);
        return preg_match('/^[\+0-9]{10,15}$/', $clean);
    }
}

// -----------------------------------------------------------------------------
// Random String Generator (Cryptographically Secure)
// -----------------------------------------------------------------------------

if (!function_exists('generate_random_string')) {
    /**
     * Generate cryptographically secure random string
     *
     * @param int $length
     * @param bool $numeric
     * @return string
     */
    function generate_random_string(int $length = 10, bool $numeric = false): string
    {
        $length = (int)$length;
        if ($length < 1) {
            $length = 10;
        }
        
        if ($numeric) {
            $characters = '0123456789';
        } else {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        $random_string = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $random_string .= $characters[random_int(0, $max)];
        }

        return $random_string;
    }
}

// -----------------------------------------------------------------------------
// User & Branch Helpers (SaaS-aware with tenant isolation)
// -----------------------------------------------------------------------------

if (!function_exists('get_user_by_id')) {
    /**
     * Get user by ID with company isolation
     *
     * @param int $user_id
     * @param int|null $tenant_id
     * @return array|false
     */
    function get_user_by_id(int $user_id, $tenant_id = null)
    {
        $user_id = (int)$user_id;
        
        if ($tenant_id === null && function_exists('get_current_tenant_id')) {
            $tenant_id = get_current_tenant_id();
        }
        
        $tenant_id = $tenant_id ? (int)$tenant_id : 0;

        $sql = "SELECT u.*, t.name as tenant_name 
                FROM users u 
                LEFT JOIN tenants t ON u.tenant_id = t.id 
                WHERE u.id = ? AND u.deleted_at IS NULL";
        $params = [$user_id];

        if ($tenant_id > 0) {
            $sql .= " AND u.tenant_id = ?";
            $params[] = $tenant_id;
        }

        return db_fetch_one($sql, $params);
    }
}

if (!function_exists('get_branch_by_id')) {
    /**
     * Get branch by ID with company isolation
     *
     * @param int $branch_id
     * @param int|null $tenant_id
     * @return array|false
     */
    function get_branch_by_id(int $branch_id, $tenant_id = null)
    {
        $branch_id = (int)$branch_id;
        
        if ($tenant_id === null && function_exists('get_current_tenant_id')) {
            $tenant_id = get_current_tenant_id();
        }
        
        $tenant_id = $tenant_id ? (int)$tenant_id : 0;

        $sql = "SELECT * FROM branches WHERE id = ? AND deleted_at IS NULL";
        $params = [$branch_id];

        if ($tenant_id > 0) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenant_id;
        }

        return db_fetch_one($sql, $params);
    }
}

if (!function_exists('get_all_branches')) {
    /**
     * Get all active branches for a company
     *
     * @param int|null $tenant_id
     * @return array
     */
    function get_all_branches($tenant_id = null): array
    {
        if ($tenant_id === null && function_exists('get_current_tenant_id')) {
            $tenant_id = get_current_tenant_id();
        }
        
        $tenant_id = $tenant_id ? (int)$tenant_id : 0;

        if ($tenant_id <= 0) {
            return [];
        }

        return db_fetch_all(
            "SELECT * FROM branches WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY is_default DESC, name",
            [$tenant_id]
        );
    }
}

// -----------------------------------------------------------------------------
// Table Existence Helper
// -----------------------------------------------------------------------------

if (!function_exists('db_table_exists')) {
    /**
     * Check if a table exists in the database
     *
     * @param string $table_name
     * @return bool
     */
    function db_table_exists(string $table_name): bool
    {
        $table_name = sanitize_input($table_name, 'alphanumeric');
        
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->query("SHOW TABLES LIKE '" . $pdo->quote($table_name) . "'");
            return $stmt->fetchColumn() !== false;
        } catch (Exception $e) {
            error_log("db_table_exists error: " . $e->getMessage());
            return false;
        }
    }
}

// -----------------------------------------------------------------------------
// Option Lists
// -----------------------------------------------------------------------------

if (!function_exists('get_payment_methods')) {
    /**
     * Get payment methods as array
     *
     * @return array
     */
    function get_payment_methods(): array
    {
        return [
            'cash' => 'Cash',
            'card' => 'Card',
            'mpesa' => 'M-Pesa',
            'bank' => 'Bank Transfer',
            'split' => 'Split Payment',
            'credit' => 'Credit',
            'flutterwave' => 'Flutterwave',
            'stripe' => 'Stripe',
            'paypal' => 'PayPal'
        ];
    }
}

if (!function_exists('get_order_statuses')) {
    /**
     * Get order statuses as array
     *
     * @return array
     */
    function get_order_statuses(): array
    {
        return [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded'
        ];
    }
}

if (!function_exists('get_sale_statuses')) {
    /**
     * Get sale statuses as array
     *
     * @return array
     */
    function get_sale_statuses(): array
    {
        return [
            'draft' => 'Draft',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'pending' => 'Pending'
        ];
    }
}

// -----------------------------------------------------------------------------
// Utility Helpers
// -----------------------------------------------------------------------------

if (!function_exists('format_bytes')) {
    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    function format_bytes(int $bytes, int $precision = 2): string
    {
        $bytes = (int)$bytes;
        $precision = (int)$precision;
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('is_ajax')) {
    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    function is_ajax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

if (!function_exists('current_url')) {
    /**
     * Get current page URL
     *
     * @return string
     */
    function current_url(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return $protocol . $host . $uri;
    }
}

if (!function_exists('redirect_back')) {
    /**
     * Redirect back to previous page
     *
     * @param string $fallback
     * @return void
     */
    function redirect_back(string $fallback = '/'): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (!empty($referer) && filter_var($referer, FILTER_VALIDATE_URL)) {
            header("Location: $referer");
        } else {
            header("Location: " . base_url($fallback));
        }
        exit;
    }
}

if (!function_exists('getDbConnection')) {
    /**
     * Get database connection (fallback if db.php not loaded)
     *
     * @return PDO
     * @throws PDOException
     */
    function getDbConnection(): PDO
    {
        if (function_exists('get_db_connection')) {
            return get_db_connection();
        }
        
        // Load configuration
        $configFile = __DIR__ . '/../config/config.php';
        $config = file_exists($configFile) ? require $configFile : [];

        // Get database credentials
        $host = getenv('DB_HOST') ?: ($config['db_host'] ?? 'localhost');
        $db = getenv('DB_NAME') ?: ($config['db_name'] ?? 'jakababa_pos');
        $user = getenv('DB_USER') ?: ($config['db_user'] ?? 'root');
        $pass = getenv('DB_PASS') ?: ($config['db_pass'] ?? '');
        $charset = getenv('DB_CHARSET') ?: ($config['db_charset'] ?? 'utf8mb4');

        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        return $pdo;
    }
}

// -----------------------------------------------------------------------------
// Business Type Functions (with database fallback)
// -----------------------------------------------------------------------------

if (!function_exists('get_business_type_config')) {
    /**
     * Get business type configuration from database (with fallback)
     *
     * @param string|null $type Business type code
     * @return array|null Business type config or all types if null
     */
    function get_business_type_config($type = null)
    {
        static $config = null;
        
if ($config === null) {
             if (function_exists('get_business_types_from_db')) {
                 $config = get_business_types_from_db();
             }
             
             if (!$config) {
                 // Fallback hardcoded config - removed unsupported types (restaurant/salon/hotel)
                 $config = [
                     'retail' => ['name' => 'Retail Store', 'icon' => 'fa-store', 'color' => '#3B82F6', 'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user']], 'features' => ['barcode' => true, 'stock' => true]],
                     'supermarket' => ['name' => 'Supermarket', 'icon' => 'fa-shopping-cart', 'color' => '#10B981', 'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user'], 'online' => ['label' => 'Online', 'icon' => 'fa-globe']], 'features' => ['barcode' => true, 'stock' => true, 'weight' => true]],
                     'pharmacy' => ['name' => 'Pharmacy', 'icon' => 'fa-pills', 'color' => '#EF4444', 'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user'], 'prescription' => ['label' => 'Prescription', 'icon' => 'fa-file-medical']], 'features' => ['prescription' => true, 'stock' => true, 'batch_number' => true]],
                     'electronics' => ['name' => 'Electronics', 'icon' => 'fa-laptop', 'color' => '#8B5CF6', 'order_types' => ['retail' => ['label' => 'Retail', 'icon' => 'fa-store'], 'installment' => ['label' => 'Installment', 'icon' => 'fa-calendar-check']], 'features' => ['serial' => true, 'warranty' => true]],
                     'hardware' => ['name' => 'Hardware Store', 'icon' => 'fa-hammer', 'color' => '#F97316', 'order_types' => ['retail' => ['label' => 'Retail', 'icon' => 'fa-store'], 'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes']], 'features' => ['barcode' => true, 'stock' => true, 'weight' => true]],
                     'butchery' => ['name' => 'Butchery', 'icon' => 'fa-drumstick-bite', 'color' => '#DC2626', 'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user']], 'features' => ['weight' => true, 'stock' => true]],
                     'bakery' => ['name' => 'Bakery', 'icon' => 'fa-bread-slice', 'color' => '#D97706', 'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user'], 'preorder' => ['label' => 'Pre-order', 'icon' => 'fa-calendar']], 'features' => ['expiry' => true, 'stock' => true]],
                     'liquor_store' => ['name' => 'Liquor Store', 'icon' => 'fa-wine-bottle', 'color' => '#7C3AED', 'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user'], 'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes']], 'features' => ['barcode' => true, 'stock' => true, 'age_verify' => true]],
                     'stationery' => ['name' => 'Stationery / Office', 'icon' => 'fa-pencil-alt', 'color' => '#0EA5E9', 'order_types' => ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user'], 'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes']], 'features' => ['barcode' => true, 'stock' => true]]
                 ];
             }
         }
        
        if ($type === null) {
            return $config;
        }
        
        $type = sanitize_input($type, 'alphanumeric');
        return $config[$type] ?? null;
    }
}

// -----------------------------------------------------------------------------
// Multi-Tenant Context Helpers (CRITICAL for data isolation)
// -----------------------------------------------------------------------------

if (!function_exists('get_current_branch_id')) {
    /**
     * Get the current branch ID from session.
     * Returns 0 if not set (should never happen for logged-in users).
     */
    function get_current_branch_id(): int
    {
        $branch_id = $_SESSION['branch_id'] ?? 0;
        if (is_array($branch_id)) {
            $branch_id = $branch_id[0] ?? 0;
        }
        return (int) $branch_id;
    }
}

if (!function_exists('get_current_tenant_id')) {
    /**
     * Get the current tenant ID from session.
     * Returns 0 if not set.
     */
    function get_current_tenant_id(): int
    {
        $tenant_id = $_SESSION['tenant_id'] ?? 0;
        if (is_array($tenant_id)) {
            $tenant_id = $tenant_id[0] ?? 0;
        }
        return (int) $tenant_id;
    }
}

if (!function_exists('get_current_user_id')) {
    /**
     * Get the current user ID from session.
     * Returns 0 if not set.
     */
    function get_current_user_id(): int
    {
        $user_id = $_SESSION['user_id'] ?? 0;
        if (is_array($user_id)) {
            $user_id = $user_id[0] ?? 0;
        }
        return (int) $user_id;
    }
}

if (!function_exists('get_current_user_name')) {
    /**
     * Get current user name
     *
     * @return string
     */
    function get_current_user_name(): string
    {
        $name = $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'User';
        if (is_array($name)) {
            $name = $name[0] ?? 'User';
        }
        return sanitize_input($name, 'string');
    }
}

if (!function_exists('get_current_user_email')) {
    /**
     * Get current user email
     *
     * @return string
     */
    function get_current_user_email(): string
    {
        $email = $_SESSION['user_email'] ?? $_SESSION['email'] ?? '';
        if (is_array($email)) {
            $email = $email[0] ?? '';
        }
        return sanitize_input($email, 'email');
    }
}

if (!function_exists('get_current_username')) {
    /**
     * Get current username (login username)
     *
     * @return string
     */
    function get_current_username(): string
    {
        $username = $_SESSION['username'] ?? '';
        if (is_array($username)) {
            $username = $username[0] ?? '';
        }
        return sanitize_input($username, 'alphanumeric');
    }
}

// -----------------------------------------------------------------------------
// Business Type Helper Functions
// -----------------------------------------------------------------------------

if (!function_exists('get_current_business_type')) {
    /**
     * Get the current tenant's business type code
     *
     * @param int|null $tenant_id
     * @return string Business type code (default: 'retail')
     */
    function get_current_business_type($tenant_id = null): string
    {
        if ($tenant_id === null) {
            $tenant_id = get_current_tenant_id();
        }
        
        $tenant_id = $tenant_id ? (int)$tenant_id : 0;
        $session_key = 'business_type_' . $tenant_id;
        
        if (!empty($_SESSION[$session_key])) {
            return sanitize_input($_SESSION[$session_key], 'alphanumeric');
        }
        
        if (!empty($_SESSION['business_type'])) {
            return sanitize_input($_SESSION['business_type'], 'alphanumeric');
        }
        
        $business_type = 'retail';
        
        try {
            $pdo = getDbConnection();
            if ($pdo && $tenant_id > 0) {
                $stmt = $pdo->prepare("
                    SELECT business_type
                    FROM tenants
                    WHERE id = ? AND deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$tenant_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['business_type'])) {
                    $business_type = $row['business_type'];
                } else {
                    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'business_type' LIMIT 1");
                    $stmt->execute([$tenant_id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row && !empty($row['setting_value'])) {
                        $business_type = $row['setting_value'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error getting business type: " . $e->getMessage());
        }
        
        $business_type = sanitize_input($business_type, 'alphanumeric');
        $_SESSION[$session_key] = $business_type;
        $_SESSION['business_type'] = $business_type;
        return $business_type;
    }
}

if (!function_exists('get_current_business_type_id')) {
    /**
     * Get the current tenant/branch business type ID.
     *
     * @param int|null $tenant_id
     * @return int Business type ID (default: 0)
     */
    function get_current_business_type_id($tenant_id = null): int
    {
        if ($tenant_id === null) {
            $tenant_id = get_current_tenant_id();
        }

        $tenant_id = $tenant_id ? (int) $tenant_id : 0;
        $branch_id = get_current_branch_id();

        if (!empty($_SESSION['business_type_id']) && (int) $_SESSION['business_type_id'] > 0) {
            return (int) $_SESSION['business_type_id'];
        }

        if (!empty($_SESSION['current_branch']['business_type_id']) && (int) $_SESSION['current_branch']['business_type_id'] > 0) {
            return (int) $_SESSION['current_branch']['business_type_id'];
        }

        try {
            $pdo = getDbConnection();
            if ($branch_id > 0) {
                $stmt = $pdo->prepare("
                    SELECT business_type_id
                    FROM branches
                    WHERE id = ? AND tenant_id = ? AND business_type_id IS NOT NULL
                    LIMIT 1
                ");
                $stmt->execute([$branch_id, $tenant_id]);
                $branchBtId = (int) ($stmt->fetchColumn() ?? 0);
                if ($branchBtId > 0) {
                    $_SESSION['current_branch']['business_type_id'] = $branchBtId;
                    $_SESSION['business_type_id'] = $branchBtId;
                    return $branchBtId;
                }
            }

            $businessType = get_current_business_type($tenant_id);
            if ($businessType !== '') {
                $stmt = $pdo->prepare("
                    SELECT id FROM business_types
                    WHERE code = ? AND tenant_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$businessType, $tenant_id]);
                $btId = (int) ($stmt->fetchColumn() ?? 0);
                if ($btId > 0) {
                    $_SESSION['business_type_id'] = $btId;
                    return $btId;
                }
            }
        } catch (Exception $e) {
            error_log("Error getting business type ID: " . $e->getMessage());
        }

        return 0;
    }
}

if (!function_exists('get_order_types_for_business')) {
    /**
     * Get order types for a specific business type
     *
     * @param string|null $business_type
     * @return array
     */
    function get_order_types_for_business($business_type = null): array
    {
        if ($business_type === null) {
            $business_type = get_current_business_type();
        }
        
        $config = get_business_type_config($business_type);
        $order_types = $config['order_types'] ?? [];
        
        // Return default if empty
        if (empty($order_types)) {
            return ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user']];
        }
        
        return $order_types;
    }
}

if (!function_exists('business_has_feature')) {
    /**
     * Check if the current business type has a specific feature enabled
     *
     * @param string $feature
     * @param string|null $business_type
     * @return bool
     */
    function business_has_feature($feature, $business_type = null): bool
    {
        $feature = sanitize_input($feature, 'alphanumeric');
        
        if ($business_type === null) {
            $business_type = get_current_business_type();
        }
        
        $config = get_business_type_config($business_type);
        $features = $config['features'] ?? [];
        return isset($features[$feature]) && $features[$feature];
    }
}

if (!function_exists('get_business_type_name')) {
    /**
     * Get the display name of a business type
     *
     * @param string|null $business_type
     * @return string
     */
    function get_business_type_name($business_type = null): string
    {
        if ($business_type === null) {
            $business_type = get_current_business_type();
        }
        
        $config = get_business_type_config($business_type);
        return $config['name'] ?? 'Retail';
    }
}

if (!function_exists('get_business_type_icon')) {
    /**
     * Get the icon for a business type
     *
     * @param string|null $business_type
     * @return string
     */
    function get_business_type_icon($business_type = null): string
    {
        if ($business_type === null) {
            $business_type = get_current_business_type();
        }
        
        $config = get_business_type_config($business_type);
        return $config['icon'] ?? 'fa-store';
    }
}

if (!function_exists('validate_business_type')) {
    /**
     * Validate a business type code against known types
     *
     * @param string $code
     * @return bool
     */
    function validate_business_type(string $code): bool
    {
        $code = sanitize_input($code, 'alphanumeric');
        $config = get_business_type_config();
        return array_key_exists($code, $config);
    }
}

// -----------------------------------------------------------------------------
// Currency / Multi-Currency Helpers
// -----------------------------------------------------------------------------

if (!function_exists('convert_currency')) {
    /**
     * Convert currency amount (simplified - use API for production)
     *
     * @param float $amount
     * @param string $from
     * @param string $to
     * @return float
     */
    function convert_currency(float $amount, string $from, string $to): float
    {
        $amount = (float)$amount;
        $from = sanitize_input($from, 'alphanumeric');
        $to = sanitize_input($to, 'alphanumeric');
        
        if ($from === $to || $amount <= 0) {
            return $amount;
        }
        
        // Simple fallback conversion (implement actual API for production)
        $rates = [
            'KES' => ['USD' => 0.0075, 'EUR' => 0.0069, 'GBP' => 0.0059],
            'USD' => ['KES' => 133.0, 'EUR' => 0.92, 'GBP' => 0.79],
            'EUR' => ['KES' => 145.0, 'USD' => 1.09, 'GBP' => 0.86],
            'GBP' => ['KES' => 169.0, 'USD' => 1.27, 'EUR' => 1.16],
        ];
        
        if (isset($rates[$from][$to])) {
            return round($amount * $rates[$from][$to], 2);
        }
        
        return $amount;
    }
}

if (!function_exists('current_currency')) {
    /**
     * Get current currency information
     *
     * @return array
     */
    function current_currency(): array
    {
        $currency = get_tenant_currency();
        $currencies = [
            'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
            'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
        ];
        
        return [
            'code' => $currency,
            'symbol' => $currencies[$currency]['symbol'] ?? $currency,
            'name' => $currencies[$currency]['name'] ?? $currency
        ];
    }
}

if (!function_exists('get_currency_rate')) {
    /**
     * Get exchange rate between currencies
     *
     * @param string $from
     * @param string $to
     * @return float
     */
    function get_currency_rate(string $from, string $to): float
    {
        $from = sanitize_input($from, 'alphanumeric');
        $to = sanitize_input($to, 'alphanumeric');
        
        if ($from === $to) {
            return 1.0;
        }
        
        // Simple fallback rates
        $rates = [
            'KES' => ['USD' => 0.0075, 'EUR' => 0.0069, 'GBP' => 0.0059],
            'USD' => ['KES' => 133.0, 'EUR' => 0.92, 'GBP' => 0.79],
            'EUR' => ['KES' => 145.0, 'USD' => 1.09, 'GBP' => 0.86],
            'GBP' => ['KES' => 169.0, 'USD' => 1.27, 'EUR' => 1.16],
        ];
        
        return $rates[$from][$to] ?? 1.0;
    }
}

// -----------------------------------------------------------------------------
// Device & Location Security Helpers
// -----------------------------------------------------------------------------

if (!function_exists('is_known_device')) {
    /**
     * Check if device is known for user
     *
     * @param string $deviceId Device fingerprint
     * @param int|null $userId User ID
     * @return bool
     */
    function is_known_device(string $deviceId, ?int $userId = null): bool
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        
        if (!$userId) {
            return false;
        }
        
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                SELECT 1 FROM known_devices 
                WHERE user_id = ? AND device_id = ? AND is_trusted = 1
                LIMIT 1
            ");
            $stmt->execute([$userId, $deviceId]);
            return (bool) $stmt->fetch();
        } catch (Exception $e) {
            error_log("Known device check failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('register_known_device')) {
    /**
     * Register a device as known
     *
     * @param string $deviceId Device fingerprint
     * @param int $userId User ID
     * @param bool $trusted Whether to mark as trusted
     * @return bool
     */
    function register_known_device(string $deviceId, int $userId, bool $trusted = true): bool
    {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                INSERT INTO known_devices (user_id, device_id, is_trusted, last_used_at, created_at)
                VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    last_used_at = NOW(),
                    is_trusted = is_trusted OR ?
            ");
            return $stmt->execute([$userId, $deviceId, $trusted ? 1 : 0, $trusted ? 1 : 0]);
        } catch (Exception $e) {
            error_log("Register known device failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('is_known_location')) {
    /**
     * Check if location is known for user
     *
     * @param array $location Location data (city, country)
     * @param int|null $userId User ID
     * @return bool
     */
    function is_known_location(array $location, ?int $userId = null): bool
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        
        if (!$userId || empty($location['city']) || empty($location['country'])) {
            return false;
        }
        
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                SELECT 1 FROM known_locations 
                WHERE user_id = ? AND city = ? AND country = ? AND is_trusted = 1
                LIMIT 1
            ");
            $stmt->execute([$userId, $location['city'], $location['country']]);
            return (bool) $stmt->fetch();
        } catch (Exception $e) {
            error_log("Known location check failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('is_suspicious_hour')) {
    /**
     * Check if current hour is suspicious (2 AM - 5 AM)
     *
     * @return bool
     */
    function is_suspicious_hour(): bool
    {
        $hour = (int) date('H');
        return $hour >= 2 && $hour <= 5;
    }
}

if (!function_exists('is_vpn')) {
    /**
     * Check if IP is from VPN (simplified - use IP geolocation API for production)
     *
     * @param string $ip IP address
     * @return bool
     */
    function is_vpn(string $ip): bool
    {
        // Simplified check - use a proper API for production
        try {
            $ch = curl_init("http://ip-api.com/json/{$ip}?fields=status,proxy");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                return isset($data['proxy']) && $data['proxy'] === true;
            }
        } catch (Exception $e) {
            error_log("VPN check failed: " . $e->getMessage());
        }
        
        return false;
    }
}

if (!function_exists('is_tor_node')) {
    /**
     * Check if IP is Tor exit node
     *
     * @param string $ip IP address
     * @return bool
     */
    function is_tor_node(string $ip): bool
    {
        // Simplified check - use Tor DNSEL for production
        try {
            $reverseIp = implode('.', array_reverse(explode('.', $ip)));
            $check = dns_get_record($reverseIp . '.dnsel.torproject.org', DNS_TXT);
            return !empty($check);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('store_remember_token')) {
    /**
     * Store remember token for auto-login
     *
     * @param int $userId User ID
     * @param string $hashedToken Hashed token
     * @return bool
     */
    function store_remember_token(int $userId, string $hashedToken): bool
    {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                INSERT INTO remember_tokens (user_id, token_hash, expires_at, created_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
                ON DUPLICATE KEY UPDATE
                    token_hash = VALUES(token_hash),
                    expires_at = VALUES(expires_at)
            ");
            return $stmt->execute([$userId, $hashedToken]);
        } catch (Exception $e) {
            error_log("Store remember token failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('password_needs_reset')) {
    /**
     * Check if password needs reset
     *
     * @param array $user User data
     * @return bool
     */
    function password_needs_reset(array $user): bool
    {
        $passwordAge = isset($user['password_changed_at']) 
            ? time() - strtotime($user['password_changed_at'])
            : 0;
        
        // Require reset every 90 days
        $maxAge = 90 * 24 * 3600;
        
        return $passwordAge > $maxAge || ($user['force_password_reset'] ?? false);
    }
}

// -----------------------------------------------------------------------------
// Label Printer Facade
// -----------------------------------------------------------------------------

if (!function_exists('label_printer')) {
    /**
     * Global helper for the Enterprise Label Printing System.
     * Usage: label_printer()->printForProductIds($productIds, 'k22', 'pdf');
     *
     * @return object
     */
    function label_printer()
    {
        // Check if the class exists
        if (class_exists('\JDH\POS\Barcode\LabelPrinter')) {
            return new \JDH\POS\Barcode\LabelPrinter();
        }
        
        // Stub for when the class isn't available
        return new class {
            public function printForProductIds($productIds, $size = 'k22', $format = 'pdf') {
                error_log("LabelPrinter not fully configured");
                return false;
            }
            
            public function printForProduct($productId, $size = 'k22', $quantity = 1, $format = 'pdf') {
                return $this->printForProductIds([$productId], $size, $format);
            }
        };
    }
}

// -----------------------------------------------------------------------------
// Alias functions for backward compatibility
// -----------------------------------------------------------------------------

if (!function_exists('isKnownDevice')) {
    function isKnownDevice(string $deviceId, ?int $userId = null): bool {
        return is_known_device($deviceId, $userId);
    }
}

if (!function_exists('registerKnownDevice')) {
    function registerKnownDevice(string $deviceId, int $userId, bool $trusted = true): bool {
        return register_known_device($deviceId, $userId, $trusted);
    }
}

if (!function_exists('isKnownLocation')) {
    function isKnownLocation(array $location, ?int $userId = null): bool {
        return is_known_location($location, $userId);
    }
}

if (!function_exists('isSuspiciousHour')) {
    function isSuspiciousHour(): bool {
        return is_suspicious_hour();
    }
}

if (!function_exists('isVPN')) {
    function isVPN(string $ip): bool {
        return is_vpn($ip);
    }
}

if (!function_exists('isTorNode')) {
    function isTorNode(string $ip): bool {
        return is_tor_node($ip);
    }
}

if (!function_exists('storeRememberToken')) {
    function storeRememberToken(int $userId, string $hashedToken): bool {
        return store_remember_token($userId, $hashedToken);
    }
}

if (!function_exists('passwordNeedsReset')) {
    function passwordNeedsReset(array $user): bool {
        return password_needs_reset($user);
    }
}

// -----------------------------------------------------------------------------
// Security: Branch/Tenant Validation Helper
// -----------------------------------------------------------------------------

if (!function_exists('resolve_branch_id')) {
    /**
     * Resolve and validate branch_id from request, ensuring it belongs to the tenant.
     * Prevents cross-tenant data leakage.
     *
     * @param int $requested_branch_id Branch ID from request (GET/POST)
     * @param int $tenant_id Current tenant ID
     * @return int Validated branch ID or 0 if invalid/unauthorized
     */
    function resolve_branch_id(int $requested_branch_id, int $tenant_id): int
    {
        if ($requested_branch_id <= 0) {
            return get_current_branch_id() ?: 0;
        }

        $can_switch = is_super_admin() || check_permission('branches.view');
        if (!$can_switch) {
            return 0; // Unauthorized - ignore the requested branch
        }

        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND active = 1 LIMIT 1");
            $stmt->execute([$requested_branch_id, $tenant_id]);
            if ($stmt->fetch()) {
                return $requested_branch_id;
            }
        } catch (Exception $e) {
            error_log("resolve_branch_id error: " . $e->getMessage());
        }

        return 0; // Invalid branch
    }
}

if (!function_exists('validate_branch_access')) {
    /**
     * Validate branch access and return HTTP 403 on failure.
     * Use before database queries involving branch-scoped data.
     *
     * @param int $requested_branch_id Branch ID from request
     * @param int $tenant_id Current tenant ID
     * @return int|null Valid branch ID or null if unauthorized
     */
    function validate_branch_access(int $requested_branch_id, int $tenant_id): ?int
    {
        $branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
        return $branch_id > 0 ? $branch_id : null;
    }
}