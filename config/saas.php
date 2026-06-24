<?php
/**
 * SaaS Configuration for Jakababa POS
 * 
 * Multi-tenant subscription and feature management settings.
 *
 * @package Jakababa
 * @subpackage Config
 * @version 2.0
 */

// Prevent direct access
if (!defined('ROOT_PATH') && !defined('PATHS_LOADED')) {
    http_response_code(403);
    die('Direct access not permitted');
}

// -----------------------------------------------------------------------------
// SaaS Mode Settings
// -----------------------------------------------------------------------------
return [
    // Multi-tenant mode
    'enabled' => true,
    'allow_self_register' => true,
    'require_email_verification' => true,
    'auto_activate_trial' => true,

    // Trial settings
    'default_trial_days' => 14,
    'trial_reminder_days' => [7, 3, 1], // Days before trial ends to send reminders

    // Default plan for new companies
    'default_plan_id' => 1, // Free plan

    // Company limits (can be overridden by plan)
    'defaults' => [
        'max_users' => 5,
        'max_branches' => 1,
        'max_products' => 100,
        'max_storage_mb' => 500,
    ],

    // Feature flags
    'features' => [
        'pos' => [
            'label' => 'Point of Sale',
            'description' => 'Process sales and transactions',
            'icon' => 'fa-cash-register',
        ],
        'inventory' => [
            'label' => 'Inventory Management',
            'description' => 'Track products and stock levels',
            'icon' => 'fa-boxes',
        ],
        'reports' => [
            'label' => 'Advanced Reports',
            'description' => 'Detailed analytics and insights',
            'icon' => 'fa-chart-line',
        ],
        'multi_branch' => [
            'label' => 'Multi-Branch',
            'description' => 'Manage multiple locations',
            'icon' => 'fa-store',
        ],
        'api_access' => [
            'label' => 'API Access',
            'description' => 'Integrate with external systems',
            'icon' => 'fa-code',
        ],
        'priority_support' => [
            'label' => 'Priority Support',
            'description' => '24/7 dedicated support',
            'icon' => 'fa-headset',
        ],
        'loyalty' => [
            'label' => 'Loyalty Program',
            'description' => 'Customer rewards and points',
            'icon' => 'fa-star',
        ],
        'purchases' => [
            'label' => 'Purchase Orders',
            'description' => 'Manage supplier orders',
            'icon' => 'fa-truck',
        ],
        'quotations' => [
            'label' => 'Quotations',
            'description' => 'Create and manage quotes',
            'icon' => 'fa-file-invoice',
        ],
        'shipments' => [
            'label' => 'Shipments',
            'description' => 'Track deliveries',
            'icon' => 'fa-shipping-fast',
        ],
    ],

    // Billing settings
    'billing' => [
        'currency' => 'KES',
        'currency_symbol' => 'KSh',
        'tax_rate' => 16.0,
        'invoice_prefix' => 'INV-',
        'invoice_due_days' => 30,
        'grace_period_days' => 7,
    ],

    // Payment methods
    'payment_methods' => [
        'mpesa' => [
            'label' => 'M-Pesa',
            'icon' => 'fa-mobile-alt',
            'enabled' => true,
        ],
        'card' => [
            'label' => 'Credit/Debit Card',
            'icon' => 'fa-credit-card',
            'enabled' => true,
        ],
        'bank' => [
            'label' => 'Bank Transfer',
            'icon' => 'fa-university',
            'enabled' => true,
        ],
        'cash' => [
            'label' => 'Cash',
            'icon' => 'fa-money-bill',
            'enabled' => false,
        ],
    ],

    // Notification settings
    'notifications' => [
        'trial_ending' => true,
        'payment_due' => true,
        'payment_received' => true,
        'subscription_cancelled' => true,
        'limit_reached' => true,
    ],

    // Email templates
    'email_templates' => [
        'welcome' => 'emails/welcome.php',
        'trial_ending' => 'emails/trial_ending.php',
        'payment_reminder' => 'emails/payment_reminder.php',
        'payment_receipt' => 'emails/payment_receipt.php',
        'subscription_cancelled' => 'emails/subscription_cancelled.php',
    ],

    // Admin settings
    'admin' => [
        'allow_plan_management' => true,
        'allow_company_management' => true,
        'allow_subscription_management' => true,
        'require_approval' => false,
    ],

    // Storage settings
    'storage' => [
        'max_upload_size_mb' => 10,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'],
        'backup_retention_days' => 30,
    ],

    // Rate limiting
    'rate_limits' => [
        'api_requests_per_minute' => 60,
        'login_attempts_per_hour' => 10,
        'password_reset_per_hour' => 3,
    ],
];
