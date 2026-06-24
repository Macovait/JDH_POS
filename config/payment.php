<?php
/**
 * Payment Configuration - SaaS Billing Settings
 * 
 * Add these to your .env file:
 * STRIPE_PUBLISHABLE_KEY=pk_test_...
 * STRIPE_SECRET_KEY=sk_test_...
 * STRIPE_WEBHOOK_SECRET=whsec_...
 * STRIPE_TEST_MODE=true
 * 
 * PAYPAL_CLIENT_ID=...
 * PAYPAL_SECRET=...
 * PAYPAL_SANDBOX=true
 */

return [
    // Application
    'app_url' => $_ENV['APP_URL'] ?? 'http://localhost/JDH_POS',
    
    // Currency settings
    'currency' => $_ENV['CURRENCY'] ?? 'USD',
    'currency_symbol' => $_ENV['CURRENCY_SYMBOL'] ?? '$',
    
    // Trial settings
    'trial_days' => 14,
    'trial_requires_card' => false,
    
    // Grace period (days after payment fails before suspending)
    'grace_period_days' => 3,
    
    // Stripe Configuration
    'stripe' => [
        'enabled' => !empty($_ENV['STRIPE_SECRET_KEY']),
        'publishable_key' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '',
        'secret_key' => $_ENV['STRIPE_SECRET_KEY'] ?? '',
        'webhook_secret' => $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '',
        'test_mode' => ($_ENV['STRIPE_TEST_MODE'] ?? 'true') === 'true',
    ],
    
    // PayPal Configuration
    'paypal' => [
        'enabled' => !empty($_ENV['PAYPAL_CLIENT_ID']) && !empty($_ENV['PAYPAL_SECRET']),
        'client_id' => $_ENV['PAYPAL_CLIENT_ID'] ?? '',
        'secret' => $_ENV['PAYPAL_SECRET'] ?? '',
        'api_url' => ($_ENV['PAYPAL_SANDBOX'] ?? 'true') === 'true' 
            ? 'https://api-m.sandbox.paypal.com' 
            : 'https://api-m.paypal.com',
        'sandbox' => ($_ENV['PAYPAL_SANDBOX'] ?? 'true') === 'true',
    ],
    
    // M-Pesa (for African markets)
    'mpesa' => [
        'enabled' => !empty($_ENV['MPESA_CONSUMER_KEY']),
        'consumer_key' => $_ENV['MPESA_CONSUMER_KEY'] ?? '',
        'consumer_secret' => $_ENV['MPESA_CONSUMER_SECRET'] ?? '',
        'shortcode' => $_ENV['MPESA_SHORTCODE'] ?? '',
        'passkey' => $_ENV['MPESA_PASSKEY'] ?? '',
        'environment' => $_ENV['MPESA_ENVIRONMENT'] ?? 'sandbox',
    ],
    
    // Webhook URLs (relative to app_url)
    'webhooks' => [
        'stripe' => '/webhooks/stripe.php',
        'paypal' => '/webhooks/paypal.php',
        'mpesa' => '/webhooks/mpesa.php',
    ],
    
    // Billing notifications
    'notifications' => [
        'send_invoice_emails' => true,
        'send_payment_failed_emails' => true,
        'send_trial_ending_emails' => true,
        'trial_warning_days' => [7, 3, 1],
    ],
];
