<?php
/**
 * OTP Configuration
 * Configure SMS and WhatsApp API credentials
 */

return [
    // SMS Provider: 'africastalking', 'twilio', or 'null' for testing
    'sms_provider' => 'africastalking',
    
    // WhatsApp Provider: 'meta' (Facebook Business), 'twilio', or 'null'
    'whatsapp_provider' => 'meta',
    
    // Default sender preference: 'sms' or 'whatsapp'
    'default_sender' => 'sms',
    
    // OTP Settings
    'otp' => [
        'length' => 6,
        'expiry_minutes' => 10,
        'max_attempts' => 3,
        'resend_delay_seconds' => 60,
    ],
    
    // Africa's Talking (Recommended for Kenya +254)
    // 1. Sign up at: https://account.africastalking.com
    // 2. Go to Settings > API Key
    // 3. Copy your API key and paste below
    // 4. For production, change username from 'sandbox' to your username
    'africastalking' => [
        'username' => 'sandbox', // 'sandbox' for testing, your username for production
        'api_key' => 'YOUR_AT_API_KEY_HERE', // Paste your API key here
        'sender_id' => 'JDH_POS', // Optional: Your sender ID (max 11 chars)
    ],
    
    // Twilio (Alternative SMS/WhatsApp)
    // Get credentials from: https://console.twilio.com
    'twilio' => [
        'account_sid' => 'YOUR_TWILIO_ACCOUNT_SID',
        'auth_token' => 'YOUR_TWILIO_AUTH_TOKEN',
        'phone_number' => '+1234567890', // Your Twilio phone number
        'whatsapp_number' => '+1234567890', // Your Twilio WhatsApp number
    ],
    
    // Meta (Facebook) WhatsApp Business API
    // Get credentials from: https://developers.facebook.com/apps
    'meta' => [
        'api_token' => 'YOUR_META_API_TOKEN',
        'phone_number_id' => 'YOUR_WHATSAPP_PHONE_NUMBER_ID',
        'business_account_id' => 'YOUR_WHATSAPP_BUSINESS_ACCOUNT_ID',
    ],
    
    // Test Mode (for development)
    // When enabled, OTP is logged instead of sent
    'test_mode' => false,
    'test_mode_log_file' => __DIR__ . '/../storage/logs/otp_test.log',
];
