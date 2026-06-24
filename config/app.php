<?php
/**
 * Application Configuration
 * 
 * This file contains application-specific settings and constants.
 */

return [
    // Application Information
    'name' => 'Jakababa POS',
    'version' => '2.0.0',
    'description' => 'Point of Sale System for Retail Businesses',
    'author' => 'Jakababa Team',

    // Environment
    'env' => getenv('APP_ENV') ?: 'development',
    'debug' => filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN) ?: false,
    'timezone' => getenv('APP_TIMEZONE') ?: 'Africa/Nairobi',

    // URLs
    'url' => getenv('APP_URL') ?: 'http://localhost/JDH_POS',
    'asset_url' => (getenv('APP_URL') ?: 'http://localhost/JDH_POS') . '/public',
    'storefront_url' => rtrim(getenv('STOREFRONT_URL') ?: 'http://localhost:3000', '/'),

    // Paths
    'root_path' => dirname(__DIR__),
    'public_path' => dirname(__DIR__) . '/public',
    'storage_path' => dirname(__DIR__) . '/storage',
    'cache_path' => dirname(__DIR__) . '/cache',
    'log_path' => dirname(__DIR__) . '/logs',
    'upload_path' => dirname(__DIR__) . '/public/uploads',

    // Session Configuration
    'session' => [
        'driver' => 'file',
        'lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 7200),
        'expire_on_close' => false,
        'encrypt' => false,
        'files' => dirname(__DIR__) . '/storage/sessions',
        'cookie' => 'jakababa_pos_session',
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
    ],

    // Security
    'security' => [
        'encryption_key' => getenv('ENCRYPTION_KEY') ?: '',
        'password_algo' => PASSWORD_DEFAULT,
        'password_options' => ['cost' => 12],
        'csrf_token_name' => '_token',
        'csrf_header' => 'X-CSRF-TOKEN',
    ],

    // Database
    'database' => [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'jakababa_pos',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => 'InnoDB',
    ],

    // Mail Configuration
    'mail' => [
        'driver' => 'smtp',
        'host' => getenv('SMTP_HOST') ?: '',
        'port' => (int) (getenv('SMTP_PORT') ?: 587),
        'username' => getenv('SMTP_USER') ?: '',
        'password' => getenv('SMTP_PASS') ?: '',
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'from' => [
            'address' => getenv('EMAIL_FROM') ?: 'noreply@jakababa.com',
            'name' => getenv('APP_NAME') ?: 'Jakababa POS',
        ],
    ],

    // File Uploads
    'uploads' => [
        'max_size' => (int) (getenv('MAX_FILE_SIZE') ?: 10485760), // 10MB
        'allowed_extensions' => explode(',', getenv('ALLOWED_UPLOAD_EXTENSIONS') ?: 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx'),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'disk' => 'local',
    ],

    // Cache Configuration
    'cache' => [
        'enabled' => filter_var(getenv('CACHE_ENABLED'), FILTER_VALIDATE_BOOLEAN) ?: true,
        'driver' => 'file',
        'lifetime' => (int) (getenv('CACHE_LIFETIME') ?: 3600),
        'path' => dirname(__DIR__) . '/cache',
        'prefix' => 'jakababa_pos',
    ],

    // Logging
    'logging' => [
        'enabled' => filter_var(getenv('LOG_ERRORS'), FILTER_VALIDATE_BOOLEAN) ?: true,
        'level' => getenv('LOG_LEVEL') ?: 'error',
        'driver' => 'daily',
        'path' => dirname(__DIR__) . '/logs',
        'max_files' => 30,
    ],

    // Business Settings
    'business' => [
        'default_currency' => getenv('DEFAULT_CURRENCY') ?: 'KES',
        'default_tax_rate' => (float) (getenv('DEFAULT_TAX_RATE') ?: 16),
        'default_plan_id' => (int) (getenv('DEFAULT_PLAN_ID') ?: 1),
        'items_per_page' => 20,
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i:s',
        'datetime_format' => 'Y-m-d H:i:s',
        'default_business_type' => getenv('DEFAULT_BUSINESS_TYPE') ?: 'retail',
    ],

    // Business Types Configuration
    'business_types' => [
        'supermarket' => [
            'name' => 'Supermarket / Grocery',
            'icon' => 'fa-shopping-cart',
            'sale_label' => 'Sale',
            'sales_label' => 'Sales',
            'customer_label' => 'Customer',
            'order_types' => ['walkin', 'wholesale', 'delivery'],
            'order_labels' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck'],
            ],
            'features' => ['track_stock' => true, 'barcode_scanning' => true, 'weight_based' => true, 'bulk_pricing' => true, 'expiry_tracking' => true],
            'product_fields' => ['barcode', 'weight', 'unit', 'expiry_date'],
            'default_category_icon' => 'fa-shopping-basket',
        ],
        'restaurant' => [
            'name' => 'Restaurant / Cafe',
            'icon' => 'fa-utensils',
            'sale_label' => 'Order',
            'sales_label' => 'Orders',
            'customer_label' => 'Guest',
            'order_types' => ['dine-in', 'takeaway', 'delivery', 'walkin'],
            'order_labels' => [
                'dine-in' => ['label' => 'Dine In', 'icon' => 'fa-utensils'],
                'takeaway' => ['label' => 'Takeaway', 'icon' => 'fa-shopping-bag'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck'],
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
            ],
            'features' => ['track_stock' => true, 'kitchen_display' => true, 'table_management' => true, 'recipe_management' => true],
            'product_fields' => ['ingredients', 'allergens', 'prep_time', 'spice_level'],
            'default_category_icon' => 'fa-utensils',
        ],
        'pharmacy' => [
            'name' => 'Pharmacy / Drugstore',
            'icon' => 'fa-pills',
            'sale_label' => 'Sale',
            'sales_label' => 'Sales',
            'customer_label' => 'Patient',
            'order_types' => ['walkin', 'prescription', 'delivery'],
            'order_labels' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                'prescription' => ['label' => 'Prescription', 'icon' => 'fa-file-medical'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck'],
            ],
            'features' => ['track_stock' => true, 'batch_tracking' => true, 'expiry_alerts' => true, 'prescription_required' => true],
            'product_fields' => ['batch_number', 'expiry_date', 'dosage', 'manufacturer', 'requires_prescription'],
            'default_category_icon' => 'fa-pills',
        ],
        'retail' => [
            'name' => 'General Retail',
            'icon' => 'fa-store',
            'sale_label' => 'Sale',
            'sales_label' => 'Sales',
            'customer_label' => 'Customer',
            'order_types' => ['walkin', 'online', 'wholesale', 'delivery'],
            'order_labels' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                'online' => ['label' => 'Online', 'icon' => 'fa-globe'],
                'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck'],
            ],
            'features' => ['track_stock' => true, 'barcode_scanning' => true, 'variants' => true],
            'product_fields' => ['barcode', 'size', 'color', 'variant'],
            'default_category_icon' => 'fa-store',
        ],
        'hotel' => [
            'name' => 'Hotel / Hospitality',
            'icon' => 'fa-bed',
            'sale_label' => 'Bill',
            'sales_label' => 'Bills',
            'customer_label' => 'Guest',
            'order_types' => ['dine-in', 'room-service', 'takeaway', 'walkin'],
            'order_labels' => [
                'dine-in' => ['label' => 'Dine In', 'icon' => 'fa-utensils'],
                'room-service' => ['label' => 'Room Service', 'icon' => 'fa-concierge-bell'],
                'takeaway' => ['label' => 'Takeaway', 'icon' => 'fa-shopping-bag'],
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
            ],
            'features' => ['track_stock' => true, 'room_service' => true, 'mini_bar' => true],
            'product_fields' => ['room_number', 'duration', 'amenities'],
            'default_category_icon' => 'fa-bed',
        ],
        'salon' => [
            'name' => 'Salon / Spa',
            'icon' => 'fa-scissors',
            'sale_label' => 'Service',
            'sales_label' => 'Services',
            'customer_label' => 'Client',
            'order_types' => ['walkin', 'appointment', 'home-service'],
            'order_labels' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                'appointment' => ['label' => 'Appointment', 'icon' => 'fa-calendar-check'],
                'home-service' => ['label' => 'Home Service', 'icon' => 'fa-home'],
            ],
            'features' => ['track_stock' => true, 'appointments' => true, 'staff_commission' => true],
            'product_fields' => ['service_duration', 'staff_member'],
            'default_category_icon' => 'fa-scissors',
        ],
        'hardware' => [
            'name' => 'Hardware Store',
            'icon' => 'fa-tools',
            'sale_label' => 'Sale',
            'sales_label' => 'Sales',
            'customer_label' => 'Customer',
            'order_types' => ['walkin', 'wholesale', 'delivery'],
            'order_labels' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck'],
            ],
            'features' => ['track_stock' => true, 'barcode_scanning' => true, 'weight_based' => true, 'length_based' => true],
            'product_fields' => ['barcode', 'weight', 'unit', 'length', 'diameter'],
            'default_category_icon' => 'fa-tools',
        ],
        'electronics' => [
            'name' => 'Electronics Store',
            'icon' => 'fa-laptop',
            'sale_label' => 'Sale',
            'sales_label' => 'Sales',
            'customer_label' => 'Customer',
            'order_types' => ['walkin', 'online', 'wholesale', 'delivery'],
            'order_labels' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                'online' => ['label' => 'Online', 'icon' => 'fa-globe'],
                'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck'],
            ],
            'features' => ['track_stock' => true, 'barcode_scanning' => true, 'warranty_tracking' => true, 'serial_tracking' => true],
            'product_fields' => ['barcode', 'model', 'warranty', 'serial_number'],
            'default_category_icon' => 'fa-laptop',
        ],
        'liquor_store' => [
            'name' => 'Liquor Store',
            'icon' => 'fa-wine-bottle',
            'sale_label' => 'Sale',
            'sales_label' => 'Sales',
            'customer_label' => 'Customer',
            'order_types' => ['walkin', 'wholesale', 'delivery'],
            'order_labels' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck'],
            ],
            'features' => ['track_stock' => true, 'barcode_scanning' => true, 'age_verification' => true, 'bulk_pricing' => true],
            'product_fields' => ['barcode', 'volume', 'alcohol_content', 'age_restriction'],
            'default_category_icon' => 'fa-wine-bottle',
        ],
        'butchery' => [
            'name' => 'Butchery',
            'icon' => 'fa-drumstick-bite',
            'sale_label' => 'Sale',
            'sales_label' => 'Sales',
            'customer_label' => 'Customer',
            'order_types' => ['walkin', 'wholesale'],
            'order_labels' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes'],
            ],
            'features' => ['track_stock' => true, 'weight_based' => true, 'freshness_tracking' => true],
            'product_fields' => ['weight', 'unit', 'cut_type', 'freshness_date'],
            'default_category_icon' => 'fa-drumstick-bite',
        ],
        'bakery' => [
            'name' => 'Bakery',
            'icon' => 'fa-bread-slice',
            'sale_label' => 'Sale',
            'sales_label' => 'Sales',
            'customer_label' => 'Customer',
            'order_types' => ['walkin', 'takeaway', 'delivery'],
            'order_labels' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                'takeaway' => ['label' => 'Takeaway', 'icon' => 'fa-shopping-bag'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck'],
            ],
            'features' => ['track_stock' => true, 'freshness_tracking' => true, 'recipe_management' => true],
            'product_fields' => ['freshness_date', 'ingredients', 'allergens'],
            'default_category_icon' => 'fa-bread-slice',
        ],
        'stationery' => [
            'name' => 'Stationery / Office',
            'icon' => 'fa-pencil-alt',
            'sale_label' => 'Sale',
            'sales_label' => 'Sales',
            'customer_label' => 'Customer',
            'order_types' => ['walkin', 'online', 'wholesale', 'delivery'],
            'order_labels' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                'online' => ['label' => 'Online', 'icon' => 'fa-globe'],
                'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-boxes'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck'],
            ],
            'features' => ['track_stock' => true, 'barcode_scanning' => true, 'bulk_pricing' => true],
            'product_fields' => ['barcode', 'brand', 'color', 'size'],
            'default_category_icon' => 'fa-pencil-alt',
        ],
    ],

    // POS Settings
    'pos' => [
        'auto_print_receipt' => true,
        'show_stock_warning' => true,
        'stock_warning_threshold' => 10,
        'allow_negative_stock' => false,
        'round_prices' => true,
        'rounding_precision' => 2,
        'enable_barcode_scanning' => true,
        'enable_sound' => true,
        'enable_hold_recall' => true,
        'enable_split_payment' => true,
        'enable_vouchers' => true,
        'enable_discounts' => true,
        'enable_loyalty' => true,
    ],

    // Inventory Settings
    'inventory' => [
        'track_stock' => true,
        'allow_backorders' => false,
        'low_stock_threshold' => 10,
        'critical_stock_threshold' => 5,
        'auto_reorder' => false,
    ],

    // Customer Settings
    'customers' => [
        'enable_loyalty' => true,
        'points_per_currency' => 1,
        'points_redemption_value' => 0.01,
        'enable_credit' => false,
        'default_credit_limit' => 0,
    ],

    // Notification Settings
    'notifications' => [
        'enable_email' => true,
        'enable_sms' => false,
        'enable_push' => false,
        'low_stock_alert' => true,
        'daily_sales_report' => true,
    ],

    // Backup Settings
    'backup' => [
        'enabled' => true,
        'schedule' => 'daily',
        'retention_days' => (int) (getenv('BACKUP_RETENTION_DAYS') ?: 30),
        'email' => getenv('BACKUP_EMAIL') ?: 'backup@jakababa.com',
        'compress' => true,
    ],

    // Maintenance
    'maintenance' => [
        'enabled' => filter_var(getenv('MAINTENANCE_MODE'), FILTER_VALIDATE_BOOLEAN) ?: false,
        'message' => getenv('MAINTENANCE_MESSAGE') ?: 'System under maintenance. Please check back later.',
        'allowed_ips' => ['127.0.0.1', '::1'],
    ],

    // API Configuration
    'api' => [
        'enabled' => false,
        'rate_limit' => 60,
        'rate_limit_period' => 60,
        'throttle' => true,
    ],

    // Localization
    'localization' => [
        'locale' => 'en',
        'fallback_locale' => 'en',
        'timezone' => 'Africa/Nairobi',
        'currency' => 'KES',
        'currency_symbol' => 'KSh',
        'currency_position' => 'before',
        'thousand_separator' => ',',
        'decimal_separator' => '.',
        'decimal_places' => 2,
    ],
];
