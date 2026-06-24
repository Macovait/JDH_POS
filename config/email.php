<?php
/**
 * Email Configuration
 */

return [
    // SMTP Settings
    'smtp_host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
    'smtp_port' => (int)($_ENV['SMTP_PORT'] ?? 587),
    'smtp_username' => $_ENV['SMTP_USERNAME'] ?? '',
    'smtp_password' => $_ENV['SMTP_PASSWORD'] ?? '',
    'smtp_secure' => $_ENV['SMTP_SECURE'] ?? 'tls', // tls or ssl
    
    // From settings
    'from_email' => $_ENV['FROM_EMAIL'] ?? 'noreply@jakababa.com',
    'from_name' => $_ENV['FROM_NAME'] ?? 'Jakababa POS',
    
    // Reply-to
    'reply_to_email' => $_ENV['REPLY_TO_EMAIL'] ?? 'support@jakababa.com',
    'reply_to_name' => $_ENV['REPLY_TO_NAME'] ?? 'Jakababa Support',
    
    // App URL for links
    'app_url' => $_ENV['APP_URL'] ?? 'http://localhost/JDH_POS',
    
    // Email sending settings
    'enabled' => ($_ENV['EMAIL_ENABLED'] ?? 'true') === 'true',
    'queue_enabled' => ($_ENV['EMAIL_QUEUE_ENABLED'] ?? 'false') === 'true',
    
    // Notification settings
    'notifications' => [
        'send_welcome_email' => true,
        'send_invoice_emails' => true,
        'send_payment_confirmations' => true,
        'send_payment_failed_emails' => true,
        'send_trial_reminders' => true,
        'send_subscription_cancelled' => true,
    ],
];
