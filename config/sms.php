<?php
/**
 * SMS Configuration
 */

return [
    // Enable/disable SMS
    'enabled' => ($_ENV['SMS_ENABLED'] ?? 'false') === 'true',
    
    // Provider: africastalking, twilio, custom
    'provider' => $_ENV['SMS_PROVIDER'] ?? 'africastalking',
    
    // Default currency
    'currency' => $_ENV['CURRENCY'] ?? 'KES',
    
    // App URL for links in SMS
    'app_url' => $_ENV['APP_URL'] ?? 'http://localhost/JDH_POS',
    
    // Africa's Talking Settings
    'africastalking_username' => $_ENV['AFRICASTALKING_USERNAME'] ?? '',
    'africastalking_api_key' => $_ENV['AFRICASTALKING_API_KEY'] ?? '',
    'africastalking_sender_id' => $_ENV['AFRICASTALKING_SENDER_ID'] ?? '',
    
    // Twilio Settings
    'twilio_sid' => $_ENV['TWILIO_SID'] ?? '',
    'twilio_token' => $_ENV['TWILIO_TOKEN'] ?? '',
    'twilio_phone_number' => $_ENV['TWILIO_PHONE_NUMBER'] ?? '',
    
    // Custom SMS Gateway
    'custom_gateway_url' => $_ENV['CUSTOM_SMS_URL'] ?? '',
    'custom_api_key' => $_ENV['CUSTOM_SMS_API_KEY'] ?? '',
    'custom_sender_id' => $_ENV['CUSTOM_SMS_SENDER_ID'] ?? 'Jakababa',
    
    // Notification Settings
    'notifications' => [
        'trial_reminders' => ($_ENV['SMS_TRIAL_REMINDERS'] ?? 'true') === 'true',
        'payment_failed_alerts' => ($_ENV['SMS_PAYMENT_FAILED'] ?? 'true') === 'true',
        'low_stock_alerts' => ($_ENV['SMS_LOW_STOCK'] ?? 'false') === 'true',
        'daily_sales_summary' => ($_ENV['SMS_DAILY_SUMMARY'] ?? 'false') === 'true',
        'large_sale_alerts' => ($_ENV['SMS_LARGE_SALES'] ?? 'false') === 'true',
    ],
    
    // Thresholds
    'large_sale_threshold' => (float)($_ENV['SMS_LARGE_SALE_THRESHOLD'] ?? 10000),
];
