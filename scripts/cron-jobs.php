<?php
/**
 * Cron Jobs for SaaS Notifications
 * Run this via cron every hour or every day
 * 
 * Setup:
 * crontab -e
 * 0 * * * * /usr/bin/php /var/www/JDH_POS/scripts/cron-jobs.php >> /var/log/jakababa-cron.log 2>&1
 * 
 * Or for Windows Task Scheduler:
 * php c:\xampp\htdocs\JDH_POS\scripts\cron-jobs.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script must be run from command line');
}

echo "========================================\n";
echo "Jakababa POS - SaaS Cron Jobs\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// Track results
$results = [
    'emails_sent' => 0,
    'emails_failed' => 0,
    'sms_sent' => 0,
    'sms_failed' => 0,
    'errors' => [],
];

try {
    // Initialize services
    $emailService = new \Jakababa\Services\EmailService($pdo);
    $smsService = new \Jakababa\Services\SMSService($pdo);
    
    // ========================================
    // 1. Process Scheduled Emails
    // ========================================
    echo "Processing scheduled emails...\n";
    $emailResults = $emailService->processScheduledEmails();
    
    foreach ($emailResults as $result) {
        if ($result['sent']) {
            $results['emails_sent']++;
            echo "  ✓ Email sent: {$result['type']} to tenant {$result['tenant_id']}\n";
        } else {
            $results['emails_failed']++;
            echo "  ✗ Email failed: {$result['type']} to tenant {$result['tenant_id']}\n";
        }
    }
    
    // ========================================
    // 2. Process Scheduled SMS
    // ========================================
    echo "\nProcessing scheduled SMS...\n";
    $smsResults = $smsService->processScheduledSMS();
    
    foreach ($smsResults as $result) {
        if ($result['sent']) {
            $results['sms_sent']++;
            echo "  ✓ SMS sent: {$result['type']} to tenant {$result['tenant_id']}\n";
        } else {
            $results['sms_failed']++;
            echo "  ✗ SMS failed: {$result['type']} to tenant {$result['tenant_id']}\n";
        }
    }
    
    // ========================================
    // 3. Send Trial Reminders (7 days before)
    // ========================================
    echo "\nChecking for trial reminders...\n";
    $stmt = $pdo->prepare("
        SELECT c.id as tenant_id, c.name, c.email, c.phone, s.trial_ends_at,
               DATEDIFF(s.trial_ends_at, NOW()) as days_left
        FROM companies c
        JOIN subscriptions s ON c.id = s.tenant_id
        WHERE s.status = 'trial'
        AND s.trial_ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        AND s.reminder_sent = 0
    ");
    $stmt->execute();
    $trials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($trials as $trial) {
        echo "  Processing trial ending in {$trial['days_left']} days for {$trial['name']}...\n";
        
        // Send email reminder
        $emailSent = $emailService->sendTrialEndingReminder($trial['tenant_id'], $trial['days_left']);
        if ($emailSent) {
            $results['emails_sent']++;
            echo "    ✓ Email reminder sent\n";
        } else {
            $results['emails_failed']++;
            echo "    ✗ Email reminder failed\n";
        }
        
        // Send SMS reminder (if phone available and enabled)
        if (!empty($trial['phone'])) {
            $smsSent = $smsService->sendTrialReminder($trial['tenant_id'], $trial['days_left']);
            if ($smsSent) {
                $results['sms_sent']++;
                echo "    ✓ SMS reminder sent\n";
            } else {
                $results['sms_failed']++;
                echo "    ✗ SMS reminder failed\n";
            }
        }
        
        // Mark reminder as sent
        $pdo->prepare("
            UPDATE subscriptions SET reminder_sent = 1 
            WHERE tenant_id = ? AND status = 'trial'
        ")->execute([$trial['tenant_id']]);
    }
    
    // ========================================
    // 4. Payment Failure Notifications
    // ========================================
    echo "\nChecking for failed payments...\n";
    $stmt = $pdo->query("
        SELECT c.id as tenant_id, c.name, c.email, c.phone, 
               s.current_period_ends_at, s.last_payment_reminder_sent
        FROM companies c
        JOIN subscriptions s ON c.id = s.tenant_id
        WHERE s.status = 'past_due'
        AND (s.last_payment_reminder_sent IS NULL 
             OR s.last_payment_reminder_sent < DATE_SUB(NOW(), INTERVAL 3 DAY))
        LIMIT 10
    ");
    $failedPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($failedPayments as $failed) {
        echo "  Notifying {$failed['name']} about failed payment...\n";
        
        // Email notification
        $emailSent = $emailService->sendPaymentFailed($failed['tenant_id']);
        if ($emailSent) {
            $results['emails_sent']++;
            echo "    ✓ Payment failure email sent\n";
        }
        
        // SMS alert
        if (!empty($failed['phone'])) {
            $smsSent = $smsService->sendPaymentFailedAlert($failed['tenant_id']);
            if ($smsSent) {
                $results['sms_sent']++;
                echo "    ✓ Payment failure SMS sent\n";
            }
        }
        
        // Update reminder sent timestamp
        $pdo->prepare("
            UPDATE subscriptions 
            SET last_payment_reminder_sent = NOW()
            WHERE tenant_id = ?
        ")->execute([$failed['tenant_id']]);
    }
    
    // ========================================
    // 5. Daily Sales Summary (if enabled)
    // ========================================
    echo "\nChecking for daily sales summary...\n";
    $smsConfig = require __DIR__ . '/../config/sms.php';
    
    if ($smsConfig['notifications']['daily_sales_summary'] ?? false) {
        // Only send at 9 PM
        if (date('H') === '21') {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            $stmt = $pdo->query("
                SELECT 
                    c.id as tenant_id,
                    c.name,
                    c.phone,
                    COUNT(s.id) as transaction_count,
                    SUM(s.total_amount) as total_sales
                FROM companies c
                LEFT JOIN sales s ON c.id = s.tenant_id AND DATE(s.created_at) = '{$yesterday}'
                WHERE c.phone IS NOT NULL AND c.phone != ''
                GROUP BY c.id
                HAVING transaction_count > 0
            ");
            $summaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($summaries as $summary) {
                $smsSent = $smsService->sendDailySalesSummary(
                    $summary['tenant_id'],
                    (float)$summary['total_sales'],
                    (int)$summary['transaction_count']
                );
                
                if ($smsSent) {
                    $results['sms_sent']++;
                    echo "  ✓ Daily summary sent to {$summary['name']}\n";
                } else {
                    $results['sms_failed']++;
                    echo "  ✗ Daily summary failed for {$summary['name']}\n";
                }
            }
        } else {
            echo "  Skipped (only runs at 9 PM)\n";
        }
    }
    
    // ========================================
    // 6. Clean up old logs
    // ========================================
    echo "\nCleaning up old logs...\n";
    
    // Delete email logs older than 90 days
    $stmt = $pdo->query("DELETE FROM email_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $deletedEmails = $stmt->rowCount();
    echo "  Deleted {$deletedEmails} old email logs\n";
    
    // Delete SMS logs older than 90 days
    $stmt = $pdo->query("DELETE FROM sms_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $deletedSMS = $stmt->rowCount();
    echo "  Deleted {$deletedSMS} old SMS logs\n";
    
    // Delete webhook logs older than 30 days
    // (if you have a webhook_logs table)
    
} catch (Exception $e) {
    $results['errors'][] = $e->getMessage();
    error_log("Cron job error: " . $e->getMessage());
    echo "\nERROR: " . $e->getMessage() . "\n";
}

// ========================================
// Summary
// ========================================
echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Emails sent: {$results['emails_sent']}\n";
echo "Emails failed: {$results['emails_failed']}\n";
echo "SMS sent: {$results['sms_sent']}\n";
echo "SMS failed: {$results['sms_failed']}\n";
echo "Errors: " . count($results['errors']) . "\n";
echo "Completed: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

// Log to file
$logEntry = sprintf(
    "[%s] Emails: %d/%d | SMS: %d/%d | Errors: %d\n",
    date('Y-m-d H:i:s'),
    $results['emails_sent'],
    $results['emails_sent'] + $results['emails_failed'],
    $results['sms_sent'],
    $results['sms_sent'] + $results['sms_failed'],
    count($results['errors'])
);

file_put_contents(__DIR__ . '/../logs/cron.log', $logEntry, FILE_APPEND | LOCK_EX);

echo "\nDone!\n";
