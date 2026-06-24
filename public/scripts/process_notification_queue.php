<?php
/**
 * Notification Queue Processor
 * Background script to process queued notifications (email, SMS, etc.)
 * Designed to be run via cron or task scheduler
 */

// Bootstrapping - load core system files
$root_path = null;
$possible_paths = [
    dirname(dirname(__DIR__)),
    dirname(__DIR__),
    $_SERVER['DOCUMENT_ROOT'] . '/JDH_POS',
    __DIR__ . '/../..'
];

foreach ($possible_paths as $path) {
    if (file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php')) {
        $root_path = $path;
        break;
    }
}

if (!$root_path) {
    fwrite(STDERR, "System configuration not found\n");
    exit(1);
}

require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Start output buffering
if (ob_get_level() === 0) {
    ob_start();
}

// Only accept CLI execution for security
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Access denied');
}

try {
    $pdo = get_db_connection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Process notifications in batches
    $batchSize = 10; // Process up to 10 notifications per run
    $processedCount = 0;
    
    // Begin transaction for batch processing
    $pdo->beginTransaction();
    
    try {
        // Fetch pending notifications (lock them for processing to prevent race conditions)
        $stmt = $pdo->prepare("
            SELECT id, tenant_id, type, payload, attempts 
            FROM notification_queue 
            WHERE status = 'pending' 
            ORDER BY created_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$batchSize]);
        
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        fwrite(STDOUT, "Found " . count($notifications) . " pending notifications\n");
        
        if (empty($notifications)) {
            // No pending notifications
            $pdo->commit();
            fwrite(STDOUT, "No pending notifications to process\n");
            exit(0);
        }
        
        // Mark notifications as processing to prevent other workers from picking them up
        $ids = array_column($notifications, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $updateStmt = $pdo->prepare("
            UPDATE notification_queue 
            SET status = 'processing', last_attempt_at = NOW() 
            WHERE id IN ($placeholders)
        ");
        $updateStmt->execute($ids);
        
        // Commit the locking transaction
        $pdo->commit();
        
        // Now process each notification
        foreach ($notifications as $notification) {
            try {
                fwrite(STDOUT, "Processing notification ID: " . $notification['id'] . ", Type: " . $notification['type'] . "\n");
                $result = processNotification($pdo, $notification);
                
                if ($result['success']) {
                    // Mark as sent
                    $stmt = $pdo->prepare("
                        UPDATE notification_queue 
                        SET status = 'sent', processed_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$notification['id']]);
                    $processedCount++;
                    fwrite(STDOUT, "Notification ID: " . $notification['id'] . " processed successfully\n");
                } else {
                    // Mark as failed or increment attempts
                    $newAttempts = (int)$notification['attempts'] + 1;
                    $maxAttempts = 3; // Default max attempts
                    
                    if ($newAttempts >= $maxAttempts) {
                        // Max attempts reached, mark as failed
                        $stmt = $pdo->prepare("
                            UPDATE notification_queue 
                            SET status = 'failed', error_message = ?, processed_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$result['error'] ?? 'Unknown error', $notification['id']]);
                    } else {
                        // Reset to pending for retry
                        $stmt = $pdo->prepare("
                            UPDATE notification_queue 
                            SET status = 'pending', attempts = ?, error_message = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$newAttempts, $result['error'] ?? 'Unknown error', $notification['id']]);
                    }
                    fwrite(STDOUT, "Notification ID: " . $notification['id'] . " failed: " . ($result['error'] ?? 'Unknown error') . "\n");
                }
            } catch (Exception $e) {
                // Handle processing errors
                $newAttempts = (int)$notification['attempts'] + 1;
                $maxAttempts = 3;
                
                if ($newAttempts >= $maxAttempts) {
                    // Max attempts reached, mark as failed
                    $stmt = $pdo->prepare("
                        UPDATE notification_queue 
                        SET status = 'failed', error_message = ?, processed_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$e->getMessage(), $notification['id']]);
                } else {
                    // Reset to pending for retry
                    $stmt = $pdo->prepare("
                        UPDATE notification_queue 
                        SET status = 'pending', attempts = ?, error_message = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$newAttempts, $e->getMessage(), $notification['id']]);
                }
                
                error_log("Notification processing failed for ID {$notification['id']}: " . $e->getMessage());
                fwrite(STDOUT, "Notification ID: " . $notification['id'] . " exception: " . $e->getMessage() . "\n");
            }
        }
        
        fwrite(STDOUT, "Processed {$processedCount} notifications\n");
        
    } catch (Exception $e) {
        // Rollback the locking transaction if something went wrong
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    
} catch (Exception $e) {
    fwrite(STDERR, "Error processing notification queue: " . $e->getMessage() . "\n");
    error_log("Notification queue processor error: " . $e->getMessage());
    exit(1);
}

/**
 * Process a single notification based on its type
 * 
 * @param PDO $pdo Database connection
 * @param array $notification Notification data from queue
 * @return array Result with 'success' boolean and optional 'error' string
 */
function processNotification(PDO $pdo, array $notification): array {
    $payload = json_decode($notification['payload'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Invalid JSON payload: ' . json_last_error_msg()
        ];
    }
    
    switch ($notification['type']) {
        case 'email':
            return processEmailNotification($pdo, $payload);
        case 'sms':
            return processSMSNotification($pdo, $payload);
        case 'low_stock_alert':
            return processLowStockAlertNotification($pdo, $payload);
        default:
            return [
                'success' => false,
                'error' => 'Unknown notification type: ' . $notification['type']
            ];
    }
}

/**
 * Process email notification
 */
function processEmailNotification(PDO $pdo, array $payload): array {
    $to = $payload['to'] ?? '';
    $subject = $payload['subject'] ?? '';
    $body = $payload['body'] ?? '';
    $headers = $payload['headers'] ?? '';
    
    fwrite(STDOUT, "Email notification: to=$to, subject=$subject\n");
    
    if (empty($to) || empty($subject)) {
        return [
            'success' => false,
            'error' => 'Missing required email fields (to, subject)'
        ];
    }
    
    // Set default headers if not provided
    if (empty($headers)) {
        $headers = "From: noreply@jakababa.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }
    
    // Send email
    $result = @mail($to, $subject, $body, $headers);
    
    fwrite(STDOUT, "Mail function returned: " . var_export($result, true) . "\n");
    
    if ($result) {
        return ['success' => true];
    } else {
        return [
            'success' => false,
            'error' => 'Mail function returned false'
        ];
    }
}

/**
 * Process SMS notification
 */
function processSMSNotification(PDO $pdo, array $payload): array {
    $to = $payload['to'] ?? '';
    $message = $payload['message'] ?? '';
    
    if (empty($to) || empty($message)) {
        return [
            'success' => false,
            'error' => 'Missing required SMS fields (to, message)'
        ];
    }
    
    // Try to use existing SMS function if available
    if (function_exists('send_sms_via_api')) {
        try {
            $tenantId = $payload['tenant_id'] ?? null;
            send_sms_via_api($to, $message, $tenantId);
            return ['success' => true];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'SMS API error: ' . $e->getMessage()
            ];
        }
    } else {
        // Fallback: try to include and use bulk_sms.php
        $smsFile = $_SERVER['DOCUMENT_ROOT'] . '/public/customers/bulk_sms.php';
        if (file_exists($smsFile)) {
            require_once $smsFile;
            if (function_exists('send_sms_via_api')) {
                try {
                    $tenantId = $payload['tenant_id'] ?? null;
                    send_sms_via_api($to, $message, $tenantId);
                    return ['success' => true];
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'error' => 'SMS API error: ' . $e->getMessage()
                    ];
                }
            }
        }
        
        return [
            'success' => false,
            'error' => 'SMS function not available'
        ];
    }
}

/**
 * Process low stock alert notification
 */
function processLowStockAlertNotification(PDO $pdo, array $payload): array {
    $to = $payload['to'] ?? '';
    $subject = $payload['subject'] ?? '';
    $body = $payload['body'] ?? '';
    $headers = $payload['headers'] ?? '';
    
    if (empty($to) || empty($subject)) {
        return [
            'success' => false,
            'error' => 'Missing required email fields (to, subject)'
        ];
    }
    
    // Set default headers if not provided
    if (empty($headers)) {
        $headers = "From: noreply@jakababa.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }
    
    // Send email (same as email notification but could be customized)
    $result = @mail($to, $subject, $body, $headers);
    
    if ($result) {
        return ['success' => true];
    } else {
        return [
            'success' => false,
            'error' => 'Mail function returned false'
        ];
    }
}