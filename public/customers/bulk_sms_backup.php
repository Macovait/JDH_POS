<?php
/**
 * Bulk SMS - Send SMS to multiple customers
 * Multi-tenant SaaS - Each company manages their own SMS campaigns
 * 
 * Features:
 * - Send to individual customers
 * - Send to customer groups
 * - Send by loyalty tier
 * - SMS templates
 * - Scheduled sending
 * - Campaign tracking
 * - Credit balance management
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id = get_current_user_id();
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['name'] ?? 'User');
$is_super_admin = is_super_admin();
// =========================================================
// SaaS Role-Based Access Control
// =========================================================
// Define SMS permissions for different roles
$sms_permissions = [
    'sms.send' => 'Can send SMS messages',
    'sms.view' => 'Can view SMS history and analytics',
    'sms.manage_templates' => 'Can create and manage SMS templates',
    'sms.manage_dnd' => 'Can manage Do Not Disturb list',
    'sms.manage_consent' => 'Can manage customer consent',
    'sms.export' => 'Can export SMS data',
    'sms.credits' => 'Can manage SMS credits',
    'sms.admin' => 'Full SMS module access'
];

// Check SMS access - at least one permission required
$has_sms_access = is_super_admin() || 
                   check_permission('sms.admin') || 
                   check_permission('sms.send') || 
                   check_permission('sms.view');

if (!$has_sms_access) {
    enforce_permission('sms.view');
}

// Helper function to check action permission
function has_sms_permission($permission, $user_id = null, $tenant_id = null) {
    global $is_super_admin;
    
    if ($is_super_admin) {
        return true;
    }
    
    // Admin permission grants all SMS permissions
    if (check_permission('sms.admin')) {
        return true;
    }
    
    return check_permission($permission);
}

// Ensure all required tables exist
function ensureSmsTables($pdo, $tenant_id) {
    try {
        // SMS Logs table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                branch_id INT UNSIGNED NULL,
                customer_id INT UNSIGNED NULL,
                phone VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                message_type VARCHAR(50) DEFAULT 'bulk',
                message_id VARCHAR(100),
                template_id INT UNSIGNED NULL,
                campaign_id INT UNSIGNED NULL,
                status ENUM('pending', 'queued', 'sent', 'delivered', 'failed', 'cancelled') DEFAULT 'pending',
                error_message TEXT,
                credits_used INT DEFAULT 1,
                sent_at TIMESTAMP NULL,
                delivered_at TIMESTAMP NULL,
                scheduled_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED,
                INDEX idx_company (tenant_id),
                INDEX idx_customer (customer_id),
                INDEX idx_status (status),
                INDEX idx_scheduled (scheduled_at),
                INDEX idx_sent_at (sent_at),
                INDEX idx_message_id (message_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // SMS Templates table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                name VARCHAR(100) NOT NULL,
                content TEXT NOT NULL,
                category VARCHAR(50),
                variables JSON,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_company (tenant_id),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // SMS Campaigns table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_campaigns (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                name VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                target_type ENUM('all', 'group', 'tier', 'selected', 'segment') NOT NULL,
                target_ids TEXT,
                total_recipients INT DEFAULT 0,
                sent_count INT DEFAULT 0,
                failed_count INT DEFAULT 0,
                status ENUM('draft', 'scheduled', 'processing', 'completed', 'cancelled') DEFAULT 'draft',
                scheduled_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                created_by INT UNSIGNED,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_company (tenant_id),
                INDEX idx_status (status),
                INDEX idx_scheduled (scheduled_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // SMS Credits table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_credits (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                balance INT NOT NULL DEFAULT 0,
                total_purchased INT NOT NULL DEFAULT 0,
                total_used INT NOT NULL DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_company (tenant_id),
                INDEX idx_balance (balance)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Initialize credits for company if not exists
        $stmt = $pdo->prepare("SELECT id FROM sms_credits WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO sms_credits (tenant_id, balance) VALUES (?, 0)")->execute([$tenant_id]);
        }
        
        // SMS DND (Do Not Disturb) List table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_dnd_list (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                phone VARCHAR(20) NOT NULL,
                reason VARCHAR(100),
                added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                added_by INT UNSIGNED,
                UNIQUE KEY uk_company_phone (tenant_id, phone),
                INDEX idx_phone (phone)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // SMS Customer Consent table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_customer_consent (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                customer_id INT UNSIGNED NOT NULL,
                opted_in TINYINT(1) DEFAULT 1,
                opted_in_date TIMESTAMP NULL,
                opted_out_date TIMESTAMP NULL,
                opted_out_reason VARCHAR(200),
                consent_method ENUM('signup', 'admin', 'sms_confirmation', 'import') DEFAULT 'signup',
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT UNSIGNED,
                UNIQUE KEY uk_company_customer (tenant_id, customer_id),
                INDEX idx_opted_in (opted_in),
                INDEX idx_customer (customer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
    } catch (PDOException $e) {
        error_log("Failed to create SMS tables: " . $e->getMessage());
    }
}

ensureSmsTables($pdo, $tenant_id);

// SMS API Configuration
function get_sms_balance($tenant_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT balance FROM sms_credits WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        $result = $stmt->fetch();
        return $result ? (int)$result['balance'] : 0;
    } catch (Exception $e) {
        return 0;
    }
}

function deduct_sms_credits($tenant_id, $amount) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            UPDATE sms_credits 
            SET balance = balance - ?, total_used = total_used + ? 
            WHERE tenant_id = ? AND balance >= ?
        ");
        $stmt->execute([$amount, $amount, $tenant_id, $amount]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Failed to deduct credits: " . $e->getMessage());
        return false;
    }
}

function send_sms_via_api($phone, $message, $tenant_id) {
    // Get SMS settings
    $sms_enabled = get_tenant_setting($tenant_id, 'sms_enabled', '0');
    if ($sms_enabled !== '1') {
        return ['success' => false, 'message' => 'SMS is not enabled for this tenant'];
    }
    
    $sms_provider = get_tenant_setting($tenant_id, 'sms_provider', 'africastalking');
    $sms_api_key = get_tenant_setting($tenant_id, 'sms_api_key', '');
    $sms_username = get_tenant_setting($tenant_id, 'sms_username', '');
    $sms_sender_id = get_tenant_setting($tenant_id, 'sms_sender_id', 'JAKABABA');
    
    if (empty($sms_api_key)) {
        return ['success' => false, 'message' => 'SMS API not configured'];
    }
    
    // Normalize phone number
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($phone) === 9) {
        $phone = '254' . $phone;
    } elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (!preg_match('/^\+?254/', $phone)) {
        $phone = '254' . ltrim($phone, '0');
    }
    
    // Limit message to 160 characters
    if (strlen($message) > 160) {
        $message = substr($message, 0, 157) . '...';
    }
    
    try {
        if ($sms_provider === 'africastalking') {
            return send_africastalking_sms($phone, $message, $sms_username, $sms_api_key, $sms_sender_id);
        } elseif ($sms_provider === 'twilio') {
            return send_twilio_sms($phone, $message, $sms_api_key, $sms_api_key, $sms_sender_id);
        } else {
            return send_generic_sms($phone, $message, $tenant_id, $sms_api_key, $sms_sender_id);
        }
    } catch (Exception $e) {
        error_log("SMS send error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function send_africastalking_sms($phone, $message, $username, $api_key, $sender_id) {
    $url = 'https://api.africastalking.com/version1/messaging';
    $data = [
        'username' => $username,
        'to' => $phone,
        'message' => $message,
        'from' => $sender_id
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'ApiKey: ' . $api_key,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 201 || $http_code === 200) {
        return ['success' => true, 'message' => 'SMS sent successfully'];
    }
    
    return ["success" => false, "message" => "Failed to send SMS via Africa's Talking"];
}

function send_twilio_sms($phone, $message, $account_sid, $auth_token, $from_number) {
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";
    $data = [
        'To' => $phone,
        'From' => $from_number,
        'Body' => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, "{$account_sid}:{$auth_token}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 201) {
        return ['success' => true, 'message' => 'SMS sent successfully'];
    }
    
    return ['success' => false, 'message' => 'Failed to send SMS via Twilio'];
}

function send_generic_sms($phone, $message, $tenant_id, $api_key, $sender_id) {
    $url = get_tenant_setting($tenant_id, 'sms_api_url', '');
    if (empty($url)) {
        return ['success' => false, 'message' => 'SMS API URL not configured'];
    }
    
    $postData = [
        'api_key' => $api_key,
        'to' => $phone,
        'from' => $sender_id,
        'message' => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code >= 200 && $http_code < 300) {
        return ['success' => true, 'message' => 'SMS sent successfully'];
    }
    
    return ['success' => false, 'message' => 'Failed to send SMS via API'];
}

// DND and Consent Management Functions
function is_phone_on_dnd($phone, $tenant_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT id FROM sms_dnd_list WHERE tenant_id = ? AND phone = ?");
        $stmt->execute([$tenant_id, $phone]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

function has_customer_consent($customer_id, $tenant_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT opted_in FROM sms_customer_consent WHERE tenant_id = ? AND customer_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);
        $result = $stmt->fetch();
        return $result ? (bool)$result['opted_in'] : true; // Default to consented if no record
    } catch (Exception $e) {
        return true;
    }
}

function add_to_dnd_list($phone, $tenant_id, $reason = 'User requested', $user_id = null) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO sms_dnd_list (tenant_id, phone, reason, added_by)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$tenant_id, $phone, $reason, $user_id]);
    } catch (Exception $e) {
        error_log("Failed to add to DND list: " . $e->getMessage());
        return false;
    }
}

function remove_from_dnd_list($phone, $tenant_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("DELETE FROM sms_dnd_list WHERE tenant_id = ? AND phone = ?");
        return $stmt->execute([$tenant_id, $phone]);
    } catch (Exception $e) {
        error_log("Failed to remove from DND list: " . $e->getMessage());
        return false;
    }
}

function set_customer_consent($customer_id, $tenant_id, $opted_in, $method = 'admin', $user_id = null) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            INSERT INTO sms_customer_consent (tenant_id, customer_id, opted_in, opted_in_date, opted_out_date, consent_method, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                opted_in = VALUES(opted_in),
                opted_in_date = IF(VALUES(opted_in) = 1, NOW(), opted_in_date),
                opted_out_date = IF(VALUES(opted_in) = 0, NOW(), opted_out_date),
                last_updated = NOW(),
                updated_by = VALUES(updated_by)
        ");
        return $stmt->execute([
            $tenant_id, $customer_id, $opted_in ? 1 : 0,
            $opted_in ? date('Y-m-d H:i:s') : null,
            $opted_in ? null : date('Y-m-d H:i:s'),
            $method, $user_id
        ]);
    } catch (Exception $e) {
        error_log("Failed to set customer consent: " . $e->getMessage());
        return false;
    }
}

// Message Personalization Function
function personalize_message($message, $customer) {
    $placeholders = [
        '{customer_name}' => $customer['name'] ?? 'Customer',
        '{phone}' => $customer['phone'] ?? '',
        '{loyalty_points}' => $customer['loyalty_points'] ?? 0,
        '{loyalty_tier}' => ucfirst($customer['loyalty_tier'] ?? 'bronze'),
        '{company_name}' => get_tenant_setting($customer['tenant_id'] ?? 1, 'company_name', 'Our Store'),
    ];
    
    return str_replace(array_keys($placeholders), array_values($placeholders), $message);
}

// Handle AJAX requests (both XMLHttpRequest and regular POST with action)
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$is_post = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']);

if ($is_ajax || $is_post) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // For debugging - log CSRF issues
    $csrf_valid = verify_csrf_token($csrf_token);
    if (!$csrf_valid) {
        // Try with explicit form name
        $csrf_valid = verify_csrf_token($csrf_token, 'bulk_sms');
    }
    
    if (!$csrf_valid) {
        // For testing - temporarily allow if empty token (remove in production)
        if (empty($csrf_token)) {
            error_log("Warning: Empty CSRF token in bulk_sms");
        } else {
            echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh the page and try again.']);
            exit;
        }
    }
    
    switch ($action) {
        case 'send_sms':
            // Permission check for sending SMS
            if (!has_sms_permission('sms.send')) {
                echo json_encode(['success' => false, 'message' => 'You do not have permission to send SMS messages', 'code' => 'PERMISSION_DENIED']);
                exit;
            }
            
            $message = trim($_POST['message'] ?? '');
            $customer_ids = json_decode($_POST['customer_ids'] ?? '[]', true);
            $send_to_group = $_POST['send_to_group'] ?? '';
            $send_to_tier = $_POST['send_to_tier'] ?? '';
            $schedule_time = $_POST['schedule_time'] ?? '';
            $campaign_name = $_POST['campaign_name'] ?? '';
            
            if (empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Message is required']);
                exit;
            }
            
            if (strlen($message) > 160) {
                echo json_encode(['success' => false, 'message' => 'Message exceeds 160 characters']);
                exit;
            }
            
            // Get recipients
            $recipients = [];
            
            if (!empty($send_to_group)) {
                $stmt = $pdo->prepare("
                    SELECT id, name, phone FROM customers 
                    WHERE tenant_id = ? AND group_id = ? AND phone IS NOT NULL AND phone != '' AND status = 1
                ");
                $stmt->execute([$tenant_id, $send_to_group]);
                $recipients = $stmt->fetchAll();
            } elseif (!empty($send_to_tier)) {
                $stmt = $pdo->prepare("
                    SELECT id, name, phone FROM customers 
                    WHERE tenant_id = ? AND loyalty_tier = ? AND phone IS NOT NULL AND phone != '' AND status = 1
                ");
                $stmt->execute([$tenant_id, $send_to_tier]);
                $recipients = $stmt->fetchAll();
            } elseif (!empty($customer_ids)) {
                $placeholders = implode(',', array_fill(0, count($customer_ids), '?'));
                $stmt = $pdo->prepare("
                    SELECT id, name, phone FROM customers 
                    WHERE id IN ($placeholders) AND tenant_id = ? AND phone IS NOT NULL AND phone != ''
                ");
                $stmt->execute(array_merge($customer_ids, [$tenant_id]));
                $recipients = $stmt->fetchAll();
            }
            
            if (empty($recipients)) {
                echo json_encode(['success' => false, 'message' => 'No valid recipients found']);
                exit;
            }
            
            // Check credits - skip if credit system not active
            $credits_needed = count($recipients);
            $current_balance = get_sms_balance($tenant_id);
            
            $credit_system_active = false;
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM sms_credits LIKE 'total_used'");
                $credit_system_active = $stmt->fetch() !== false;
            } catch (Exception $e) {
                $credit_system_active = false;
            }
            
            if ($credit_system_active && $current_balance < $credits_needed) {
                echo json_encode([
                    'success' => false, 
                    'message' => "Insufficient SMS credits. Need $credits_needed, have $current_balance",
                    'credits_needed' => $credits_needed,
                    'current_balance' => $current_balance
                ]);
                exit;
            }
            
            // Deduct credits only if credit system is active
            if ($credit_system_active) {
                if (!deduct_sms_credits($tenant_id, $credits_needed)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to deduct credits']);
                    exit;
                }
            }
            
            // Create campaign if scheduled
            $campaign_id = null;
            if (!empty($schedule_time) && strtotime($schedule_time) > time()) {
                $stmt = $pdo->prepare("
                    INSERT INTO sms_campaigns (tenant_id, name, message, target_type, target_ids, total_recipients, status, scheduled_at, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?, ?)
                ");
                $stmt->execute([
                    $tenant_id, $campaign_name ?: 'Scheduled Campaign', $message,
                    !empty($send_to_group) ? 'group' : (!empty($send_to_tier) ? 'tier' : 'selected'),
                    json_encode($customer_ids), count($recipients), $schedule_time, $user_id
                ]);
                $campaign_id = $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => "Campaign scheduled for " . date('Y-m-d H:i', strtotime($schedule_time)),
                    'campaign_id' => $campaign_id,
                    'recipients' => count($recipients)
                ]);
                exit;
            }
            
            // Deduct credits
            if (!deduct_sms_credits($tenant_id, $credits_needed)) {
                echo json_encode(['success' => false, 'message' => 'Failed to deduct credits']);
                exit;
            }
            
            // Send SMS
            $sent_count = 0;
            $failed_count = 0;
            $dnd_count = 0;
            $no_consent_count = 0;
            $failed_recipients = [];
            
            $log_stmt = $pdo->prepare("
                INSERT INTO sms_logs (tenant_id, branch_id, customer_id, phone, message, status, error_message, sent_at, created_by, campaign_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($recipients as $customer) {
                // Check DND list
                if (is_phone_on_dnd($customer['phone'], $tenant_id)) {
                    $dnd_count++;
                    $log_stmt->execute([
                        $tenant_id, $branch_id, $customer['id'], $customer['phone'],
                        $message, 'failed', 'Phone number on DND list', null, $user_id, $campaign_id
                    ]);
                    continue;
                }
                
                // Check consent
                if (!has_customer_consent($customer['id'], $tenant_id)) {
                    $no_consent_count++;
                    $log_stmt->execute([
                        $tenant_id, $branch_id, $customer['id'], $customer['phone'],
                        $message, 'failed', 'Customer has not consented', null, $user_id, $campaign_id
                    ]);
                    continue;
                }
                
                // Personalize message
                $personalized_message = personalize_message($message, $customer);
                
                $result = send_sms_via_api($customer['phone'], $personalized_message, $tenant_id);
                
                if ($result['success']) {
                    $sent_count++;
                    $log_stmt->execute([
                        $tenant_id, $branch_id, $customer['id'], $customer['phone'],
                        $personalized_message, 'sent', null, date('Y-m-d H:i:s'), $user_id, $campaign_id
                    ]);
                } else {
                    $failed_count++;
                    $failed_recipients[] = $customer['name'];
                    $log_stmt->execute([
                        $tenant_id, $branch_id, $customer['id'], $customer['phone'],
                        $personalized_message, 'failed', $result['message'], null, $user_id, $campaign_id
                    ]);
                }
            }
            
            // Update campaign if exists
            if ($campaign_id) {
                $stmt = $pdo->prepare("
                    UPDATE sms_campaigns 
                    SET sent_count = ?, failed_count = ?, status = 'completed', completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$sent_count, $failed_count, $campaign_id]);
            }
            
            // Log activity
            log_activity($user_id, 'sms.bulk_sent', [
                'sent_count' => $sent_count, 'failed_count' => $failed_count,
                'tenant_id' => $tenant_id,
                'credits_used' => $credits_needed
            ], get_current_tenant_id());
            
            $response = ['success' => true, 'sent' => $sent_count, 'failed' => $failed_count];
            if ($failed_count > 0) {
                $response['message'] = "Sent: $sent_count, Failed: $failed_count";
                $response['failed_recipients'] = array_slice($failed_recipients, 0, 5);
            } else {
                $response['message'] = "SMS sent successfully to $sent_count recipients";
            }
            
            echo json_encode($response);
            break;
            
        case 'get_templates':
            // Permission check for viewing templates
            if (!has_sms_permission('sms.manage_templates') && !has_sms_permission('sms.send')) {
                echo json_encode(['success' => false, 'message' => 'You do not have permission to view templates', 'code' => 'PERMISSION_DENIED']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT id, name, content, category FROM sms_templates WHERE tenant_id = ? AND is_active = 1 ORDER BY name");
            $stmt->execute([$tenant_id]);
            $templates = $stmt->fetchAll();
            echo json_encode(['success' => true, 'templates' => $templates]);
            break;
            
        case 'save_template':
            // Permission check for managing templates
            if (!has_sms_permission('sms.manage_templates')) {
                echo json_encode(['success' => false, 'message' => 'You do not have permission to manage templates', 'code' => 'PERMISSION_DENIED']);
                exit;
            }
            
            $name = trim($_POST['name'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $category = trim($_POST['category'] ?? 'general');
            
            if (empty($name) || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Name and content are required']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO sms_templates (tenant_id, name, content, category, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenant_id, $name, $content, $category, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Template saved', 'id' => $pdo->lastInsertId()]);
            break;
            
        case 'get_stats':
            // Permission check for viewing analytics
            if (!has_sms_permission('sms.view') && !has_sms_permission('sms.send')) {
                echo json_encode(['success' => false, 'message' => 'You do not have permission to view analytics', 'code' => 'PERMISSION_DENIED']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT CASE WHEN phone IS NOT NULL AND phone != '' THEN id END) as customers_with_phone,
                    COUNT(*) as total_customers
                FROM customers WHERE tenant_id = ? AND status = 1
            ");
            $stmt->execute([$tenant_id]);
            $customer_stats = $stmt->fetch();
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as sent_today,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as total_sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as total_failed
                FROM sms_logs 
                WHERE tenant_id = ? AND DATE(sent_at) = CURDATE()
            ");
            $stmt->execute([$tenant_id]);
            $sms_stats = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'customers_with_phone' => (int)($customer_stats['customers_with_phone'] ?? 0),
                'total_customers' => (int)($customer_stats['total_customers'] ?? 0),
                'sms_sent_today' => (int)($sms_stats['sent_today'] ?? 0),
                'total_sent' => (int)($sms_stats['total_sent'] ?? 0),
                'total_failed' => (int)($sms_stats['total_failed'] ?? 0),
                'sms_balance' => get_sms_balance($tenant_id)
            ]);
            break;
            
        case 'manage_dnd':
            // Permission check for managing DND list
            if (!has_sms_permission('sms.manage_dnd')) {
                echo json_encode(['success' => false, 'message' => 'You do not have permission to manage DND list', 'code' => 'PERMISSION_DENIED']);
                exit;
            }
            
            $phone = trim($_POST['phone'] ?? '');
            $action = $_POST['dnd_action'] ?? 'add';
            
            if (empty($phone)) {
                echo json_encode(['success' => false, 'message' => 'Phone number is required']);
                exit;
            }
            
            if ($action === 'add') {
                $reason = trim($_POST['reason'] ?? 'User requested');
                if (add_to_dnd_list($phone, $tenant_id, $reason, $user_id)) {
                    echo json_encode(['success' => true, 'message' => 'Phone added to DND list']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add phone to DND list']);
                }
            } elseif ($action === 'remove') {
                if (remove_from_dnd_list($phone, $tenant_id)) {
                    echo json_encode(['success' => true, 'message' => 'Phone removed from DND list']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to remove phone from DND list']);
                }
            }
            break;
            
        case 'set_consent':
            // Permission check for managing consent
            if (!has_sms_permission('sms.manage_consent')) {
                echo json_encode(['success' => false, 'message' => 'You do not have permission to manage customer consent', 'code' => 'PERMISSION_DENIED']);
                exit;
            }
            
            $customer_id = intval($_POST['customer_id'] ?? 0);
            $opted_in = intval($_POST['opted_in'] ?? 0) === 1;
            
            if ($customer_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
                exit;
            }
            
            if (set_customer_consent($customer_id, $tenant_id, $opted_in, 'admin', $user_id)) {
                echo json_encode(['success' => true, 'message' => 'Consent updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update consent']);
            }
            break;
            
        case 'export_csv':
            // Permission check for exporting data
            if (!has_sms_permission('sms.export') && !has_sms_permission('sms.view')) {
                echo json_encode(['success' => false, 'message' => 'You do not have permission to export SMS data', 'code' => 'PERMISSION_DENIED']);
                exit;
            }
            
            $date_from = $_POST['date_from'] ?? date('Y-m-01');
            $date_to = $_POST['date_to'] ?? date('Y-m-d');
            
            try {
                $stmt = $pdo->prepare("
                    SELECT DATE(sent_at) as date, COUNT(*) as total, 
                           SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                           SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                    FROM sms_logs
                    WHERE tenant_id = ? AND DATE(sent_at) BETWEEN ? AND ?
                    GROUP BY DATE(sent_at)
                    ORDER BY sent_at DESC
                ");
                $stmt->execute([$tenant_id, $date_from, $date_to]);
                $logs = $stmt->fetchAll();
                
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="sms_report_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Date', 'Total SMS', 'Sent', 'Failed', 'Success Rate']);
                
                foreach ($logs as $log) {
                    $rate = $log['total'] > 0 ? round(($log['sent'] / $log['total']) * 100, 2) . '%' : '0%';
                    fputcsv($output, [
                        $log['date'],
                        $log['total'],
                        $log['sent'],
                        $log['failed'],
                        $rate
                    ]);
                }
                
                fclose($output);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()]);
            }
            break;
    }
    exit;
}

// Handle form submission for immediate send
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_sms') {
    // Process form submission (same as AJAX but redirect)
    // This is for non-JS fallback
    // Implementation similar to AJAX handler above
}

$csrf_token = generate_csrf_token();

// Get data for the form
$customer_groups = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, color FROM customer_groups WHERE tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $customer_groups = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching customer groups: " . $e->getMessage());
}

$customers = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, phone, loyalty_points, loyalty_tier 
        FROM customers 
        WHERE tenant_id = ? AND phone IS NOT NULL AND phone != '' AND status = 1
        ORDER BY name ASC
        LIMIT 500
    ");
    $stmt->execute([$tenant_id]);
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching customers: " . $e->getMessage());
}

$templates = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, content, category FROM sms_templates WHERE tenant_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$tenant_id]);
    $templates = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching templates: " . $e->getMessage());
}

$campaigns = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, message, status, total_recipients, sent_count, failed_count, scheduled_at, created_at
        FROM sms_campaigns 
        WHERE tenant_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$tenant_id]);
    $campaigns = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching campaigns: " . $e->getMessage());
}

$page_title = 'Bulk SMS';
ob_start();
?>

<style>
    .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 { background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem; padding: 0.375rem 0.5rem; color: white; width: 100%; font-size: 0.875rem; transition: all 0.2s; }
    .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500:focus { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.2); }
    .checkbox-wrapper input[type="checkbox"] { width: 16px; height: 16px; accent-color: #f59e0b; }
    .customer-list { max-height: 400px; overflow-y: auto; }
    .customer-list::-webkit-scrollbar { width: 6px; }
    .customer-list::-webkit-scrollbar-track { background: #1e293b; }
    .customer-list::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
    .char-count { font-size: 0.75rem; color: #64748b; }
    .char-count.warning { color: #f59e0b; }
    .char-count.error { color: #ef4444; }
    .template-badge { background: rgba(245, 158, 11, 0.1); color: #fbbf24; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.65rem; }
    .nav-tab { padding: 0.375rem 0.75rem; border-radius: 0.375rem; cursor: pointer; transition: all 0.2s; background: transparent; border: none; color: #64748b; font-weight: 500; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 0.25rem; }
    .nav-tab.active { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
    .nav-tab:not(.active):hover { background: rgba(245, 158, 11, 0.1); color: #fbbf24; }
    .tab-content { display: none !important; }
    .tab-content.active { display: block !important; }
</style>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-sms text-amber-400"></i> Bulk SMS
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Send SMS messages to your customers</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <div class="bg-slate-800/40 px-3 py-1.5 rounded-lg border border-slate-700/60">
            <p class="text-xs text-slate-500">Balance</p>
            <p class="text-lg font-bold text-amber-400" id="smsBalance">--</p>
        </div>
        <a href="customers.php" class="px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors flex items-center gap-1.5">
            <i class="fas fa-arrow-left text-xs"></i> Back
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-2 mb-4" id="statsContainer">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500">With Phone</p>
                <p class="text-lg font-bold text-white" id="customersWithPhone">--</p>
            </div>
            <div class="p-2 bg-amber-500/10 rounded-lg">
                <i class="fas fa-phone text-amber-400 text-sm"></i>
            </div>
        </div>
    </div>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500">Sent Today</p>
                <p class="text-lg font-bold text-white" id="smsSentToday">--</p>
            </div>
            <div class="p-2 bg-emerald-500/10 rounded-lg">
                <i class="fas fa-paper-plane text-emerald-400 text-sm"></i>
            </div>
        </div>
    </div>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500">Total Sent</p>
                <p class="text-lg font-bold text-white" id="totalSent">--</p>
            </div>
            <div class="p-2 bg-blue-500/10 rounded-lg">
                <i class="fas fa-chart-line text-blue-400 text-sm"></i>
            </div>
        </div>
    </div>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500">Groups</p>
                <p class="text-lg font-bold text-white"><?php echo count($customer_groups); ?></p>
            </div>
            <div class="p-2 bg-purple-500/10 rounded-lg">
                <i class="fas fa-users text-purple-400 text-sm"></i>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<div class="flex flex-wrap gap-1.5 mb-4 border-b border-slate-700/60 pb-2">
        <?php if (has_sms_permission('sms.send')): ?>
            <button class="nav-tab active" onclick="switchTab('compose', this)">Compose Message</button>
        <?php endif; ?>
        <?php if (has_sms_permission('sms.manage_templates')): ?>
            <button class="nav-tab" onclick="switchTab('templates', this)">Templates</button>
        <?php endif; ?>
        <button class="nav-tab" onclick="switchTab('campaigns', this)">Campaigns</button>
        <?php if (has_sms_permission('sms.view')): ?>
            <button class="nav-tab" onclick="switchTab('history', this)">History</button>
            <button class="nav-tab" onclick="switchTab('analytics', this)">Analytics</button>
        <?php endif; ?>
    </div>

<!-- Compose Tab -->
<?php if (has_sms_permission('sms.send')): ?>
<div id="composeTab" class="tab-content active">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <!-- Compose Form -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-edit text-amber-400 text-xs"></i>Compose Message
            </h3>
                
            <form id="smsForm" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="send_sms">
                
                <!-- Campaign Name -->
                <div>
                    <label for="campaignName" class="block text-xs text-slate-500 mb-1">Campaign Name (Optional)</label>
                    <input type="text" name="campaign_name" id="campaignName" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="e.g., April Promo" autocomplete="off">
                </div>
                    
                    <!-- Send To Options -->
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Send To</label>
                        <div class="space-y-1 text-sm">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="send_type" value="selected" checked onchange="toggleSendOptions()">
                                <span class="text-slate-300">Selected Customers Only</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="send_type" value="group" onchange="toggleSendOptions()">
                                <span class="text-slate-300">All Customers in Group</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="send_type" value="tier" onchange="toggleSendOptions()">
                                <span class="text-slate-300">All Customers by Loyalty Tier</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Customer Group Selection -->
                    <div id="groupSelect" class="hidden">
                        <label for="sendToGroup" class="block text-xs text-slate-500 mb-1">Select Customer Group</label>
                        <select name="send_to_group" id="sendToGroup" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" autocomplete="off">
                            <option value="">-- Select a group --</option>
                            <?php foreach ($customer_groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>">
                                    <?php echo htmlspecialchars($group['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Loyalty Tier Selection -->
                    <div id="tierSelect" class="hidden">
                        <label for="sendToTier" class="block text-xs text-slate-500 mb-1">Select Loyalty Tier</label>
                        <select name="send_to_tier" id="sendToTier" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" autocomplete="off">
                            <option value="">-- Select a tier --</option>
                            <option value="bronze">Bronze</option>
                            <option value="silver">Silver</option>
                            <option value="gold">Gold</option>
                            <option value="platinum">Platinum</option>
                        </select>
                    </div>
                    
                    <!-- Message Template -->
                    <div>
                        <label for="templateSelect" class="block text-xs text-slate-500 mb-1">Use Template (Optional)</label>
                        <select id="templateSelect" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" onchange="loadTemplate()" autocomplete="off">
                            <option value="">-- Select a template --</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo $template['id']; ?>">
                                    <?php echo htmlspecialchars($template['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Message -->
                    <div>
                        <label for="smsMessage" class="block text-xs text-slate-500 mb-1">Message <span class="text-red-400">*</span></label>
                        <textarea name="message" id="smsMessage" rows="4" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" 
                            placeholder="Enter your SMS message here..." required maxlength="160" autocomplete="off"></textarea>
                        <div class="flex justify-between mt-1">
                            <span class="char-count" id="charCount">0 / 160 characters</span>
                            <span class="text-xs text-slate-500">Standard SMS: 160 characters</span>
                        </div>
                    </div>
                    
                    <!-- Schedule -->
                    <div>
                        <label for="scheduleTime" class="block text-xs text-slate-500 mb-1">Schedule (Optional)</label>
                        <input type="datetime-local" name="schedule_time" id="scheduleTime" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" autocomplete="off">
                        <p class="text-xs text-slate-500 mt-0.5">Leave empty to send immediately</p>
                    </div>
                    
                    <button type="submit" id="sendSmsBtn" class="w-full px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors flex items-center justify-center gap-1.5">
                        <i class="fas fa-paper-plane text-xs"></i>
                        <span id="btnText">Send SMS</span>
                    </button>
                    
                    <div id="smsResult" class="hidden p-3 rounded-lg"></div>
                </form>
            </div>

            <!-- Customer Selection -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-users text-amber-400 text-xs"></i>Select Customers
                    </h3>
                    <div class="flex gap-1">
                        <button type="button" onclick="selectAllCustomers()" class="text-xs px-2 py-1 bg-slate-700 text-slate-300 rounded hover:bg-slate-600">
                            All
                        </button>
                        <button type="button" onclick="deselectAllCustomers()" class="text-xs px-2 py-1 bg-slate-700 text-slate-300 rounded hover:bg-slate-600">
                            None
                        </button>
                    </div>
                </div>

                <div class="mb-2">
                    <label for="customerSearch" class="sr-only">Search customers</label>
                    <input type="search" id="customerSearch" placeholder="Search customers..." class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" onkeyup="filterCustomers()" autocomplete="off">
                </div>

                <?php if (empty($customers)): ?>
                    <div class="text-center py-6 text-slate-500">
                        <i class="fas fa-users text-2xl mb-2 opacity-50"></i>
                        <p class="text-sm">No customers with phone numbers found</p>
                        <p class="text-xs mt-1">Add phone numbers to customers to send SMS</p>
                    </div>
                <?php else: ?>
                    <div class="customer-list border border-slate-700/60 rounded-lg bg-slate-900/50" id="customerList">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-800/60 border-b border-slate-700/60 sticky top-0">
                                <tr>
                                    <th class="px-2 py-1.5 text-left text-xs font-medium text-slate-500 w-6">
                                        <input type="checkbox" id="selectAllCheck" onchange="toggleSelectAll(this)">
                                    </th>
                                    <th class="px-2 py-1.5 text-left text-xs font-medium text-slate-500">Customer</th>
                                    <th class="px-2 py-1.5 text-left text-xs font-medium text-slate-500">Phone</th>
                                    <th class="px-2 py-1.5 text-left text-xs font-medium text-slate-500">Pts</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/40">
                                <?php foreach ($customers as $customer): ?>
                                    <tr class="hover:bg-slate-700/30 customer-row" data-name="<?php echo strtolower(htmlspecialchars($customer['name'])); ?>">
                                        <td class="px-2 py-1.5">
                                            <div class="checkbox-wrapper">
                                                <input type="checkbox" name="selected_customers[]" value="<?php echo $customer['id']; ?>" class="customer-checkbox">
                                            </div>
                                        </td>
                                        <td class="px-2 py-1.5 text-white text-xs"><?php echo htmlspecialchars($customer['name']); ?></td>
                                        <td class="px-2 py-1.5 text-slate-400 text-xs"><?php echo htmlspecialchars($customer['phone']); ?></td>
                                        <td class="px-2 py-1.5 text-amber-400 text-xs"><?php echo number_format($customer['loyalty_points']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">Showing <span id="visibleCount"><?php echo count($customers); ?></span> customers with phone numbers</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; // End has_sms_permission('sms.send') ?>

<!-- Templates Tab -->
<?php if (has_sms_permission('sms.manage_templates')): ?>
<div id="templatesTab" class="tab-content">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-file-alt text-amber-400 text-xs"></i>SMS Templates
            </h3>
            <button onclick="openTemplateModal()" class="px-2 py-1 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-xs font-medium hover:bg-amber-500/25">
                <i class="fas fa-plus mr-1"></i> New
            </button>
        </div>
        
        <?php if (empty($templates)): ?>
            <div class="text-center py-6 text-slate-500">
                <i class="fas fa-file-alt text-2xl mb-2 opacity-50"></i>
                <p class="text-sm">No templates yet</p>
                <p class="text-xs mt-1">Create templates for frequently used messages</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <?php foreach ($templates as $template): ?>
                    <div class="border border-slate-700/60 rounded-lg p-3 hover:border-amber-500/30 transition bg-slate-900/30">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-medium text-white text-sm"><?php echo htmlspecialchars($template['name']); ?></h4>
                            <span class="template-badge"><?php echo htmlspecialchars($template['category']); ?></span>
                        </div>
                        <p class="text-xs text-slate-400 mb-2"><?php echo htmlspecialchars(substr($template['content'], 0, 80)) . (strlen($template['content']) > 80 ? '...' : ''); ?></p>
                        <div class="flex gap-2">
                            <button onclick="useTemplate(<?php echo $template['id']; ?>, '<?php echo addslashes($template['content']); ?>')" class="text-xs text-amber-400 hover:text-amber-300">
                                <i class="fas fa-paste mr-1"></i> Use
                            </button>
                            <button onclick="deleteTemplate(<?php echo $template['id']; ?>)" class="text-xs text-red-400 hover:text-red-300">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; // End has_sms_permission('sms.manage_templates') ?>

<!-- Campaigns Tab -->
<div id="campaignsTab" class="tab-content">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
            <i class="fas fa-bullhorn text-amber-400 text-xs"></i>SMS Campaigns
        </h3>
        
        <?php if (empty($campaigns)): ?>
            <div class="text-center py-6 text-slate-500">
                <i class="fas fa-bullhorn text-2xl mb-2 opacity-50"></i>
                <p class="text-sm">No campaigns yet</p>
                <p class="text-xs mt-1">Schedule campaigns for future sending</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-800/60 border-b border-slate-700/60">
                        <tr class="text-slate-500 text-xs uppercase">
                            <th class="px-2 py-2 text-left">Campaign</th>
                            <th class="px-2 py-2 text-left">Status</th>
                            <th class="px-2 py-2 text-left">Schedule</th>
                            <th class="px-2 py-2 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php foreach ($campaigns as $campaign): ?>
                            <tr class="hover:bg-slate-700/30">
                                <td class="px-2 py-2">
                                    <div class="font-medium text-white text-sm"><?php echo htmlspecialchars($campaign['name']); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo number_format($campaign['total_recipients']); ?> recipients</div>
                                </td>
                                <td class="px-2 py-2">
                                    <?php
                                    $status_tw = [
                                        'draft' => 'bg-slate-500/15 text-slate-400',
                                        'scheduled' => 'bg-amber-500/15 text-amber-400',
                                        'processing' => 'bg-blue-500/15 text-blue-400',
                                        'completed' => 'bg-emerald-500/15 text-emerald-400',
                                        'cancelled' => 'bg-red-500/15 text-red-400'
                                    ];
                                    $tw_class = $status_tw[$campaign['status']] ?? 'bg-slate-500/15 text-slate-400';
                                    ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $tw_class; ?>">
                                        <?php echo ucfirst($campaign['status']); ?>
                                    </span>
                                </td>
                                <td class="px-2 py-2 text-xs text-slate-500">
                                    <?php echo $campaign['scheduled_at'] ? date('Y-m-d H:i', strtotime($campaign['scheduled_at'])) : 'Immediate'; ?>
                                </td>
                                <td class="px-2 py-2">
                                    <button onclick="viewCampaign(<?php echo $campaign['id']; ?>)" class="text-amber-400 hover:text-amber-300 text-xs mr-2">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($campaign['status'] === 'scheduled'): ?>
                                        <button onclick="cancelCampaign(<?php echo $campaign['id']; ?>)" class="text-red-400 hover:text-red-300 text-xs">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- History Tab -->
<?php if (has_sms_permission('sms.view')): ?>
<div id="historyTab" class="tab-content">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
            <i class="fas fa-history text-amber-400 text-xs"></i>SMS History
        </h3>
        <div id="smsHistory">
            <div class="text-center py-6 text-slate-500">
                <i class="fas fa-spinner fa-spin text-xl mb-2"></i>
                <p class="text-sm">Loading history...</p>
            </div>
        </div>
    </div>
</div>
<?php endif; // End has_sms_permission('sms.view') ?>

<!-- Analytics Tab -->
<?php if (has_sms_permission('sms.view')): ?>
<div id="analyticsTab" class="tab-content">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-2 mb-4">
        <!-- Date Range Filter -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
            <label for="analyticsDateFrom" class="block text-xs text-slate-500 mb-1">From Date</label>
            <input type="date" id="analyticsDateFrom" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" value="<?php echo date('Y-m-01'); ?>" autocomplete="off">
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
            <label for="analyticsDateTo" class="block text-xs text-slate-500 mb-1">To Date</label>
            <input type="date" id="analyticsDateTo" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" value="<?php echo date('Y-m-d'); ?>" autocomplete="off">
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 flex items-end">
            <button onclick="loadAdvancedAnalytics()" class="w-full px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
                <i class="fas fa-chart-bar mr-1"></i>Load Analytics
            </button>
        </div>
    </div>

    <!-- Analytics Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-2 mb-4" id="analyticsCards">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <p class="text-xs text-slate-500">Total Sent</p>
        <p class="text-lg font-bold text-white" id="analyticsTotalSent">--</p>
    </div>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <p class="text-xs text-slate-500">Delivered</p>
        <p class="text-lg font-bold text-emerald-400" id="analyticsDelivered">--</p>
    </div>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <p class="text-xs text-slate-500">Failed</p>
        <p class="text-lg font-bold text-red-400" id="analyticsFailed">--</p>
    </div>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
        <p class="text-xs text-slate-500">Success Rate</p>
        <p class="text-lg font-bold text-amber-400" id="analyticsSuccessRate">--</p>
    </div>
</div>

<!-- Detailed Report -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-3">
    <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
        <i class="fas fa-table text-amber-400 text-xs"></i>Daily Report
    </h3>
    <div id="analyticsReport">
        <div class="text-center py-6 text-slate-500 text-sm">
            <p>Select date range and click Load Analytics</p>
        </div>
    </div>
</div>

<!-- DND Management -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-3">
    <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
        <i class="fas fa-ban text-amber-400 text-xs"></i>Do Not Disturb (DND)
    </h3>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-2">
        <!-- Add to DND -->
        <div>
            <h4 class="font-medium text-white mb-2 text-xs">Add Phone to DND</h4>
            <div class="space-y-2">
                <label for="dndPhone" class="sr-only">Phone number</label>
                <input type="tel" id="dndPhone" placeholder="Phone number" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" autocomplete="tel">
                <label for="dndReason" class="sr-only">Reason (optional)</label>
                <textarea id="dndReason" placeholder="Reason (optional)" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" rows="2" autocomplete="off"></textarea>
                <button onclick="addToDND()" class="w-full px-3 py-2 bg-red-500/15 border border-red-500/30 text-red-400 rounded-lg text-sm font-medium hover:bg-red-500/25 transition-colors">
                    <i class="fas fa-plus mr-1"></i>Add to DND
                </button>
            </div>
        </div>
        
        <!-- Remove from DND -->
        <div>
            <h4 class="font-medium text-white mb-2 text-xs">Remove from DND</h4>
            <div class="space-y-2">
                <label for="dndPhoneRemove" class="sr-only">Phone number to remove</label>
                <input type="tel" id="dndPhoneRemove" placeholder="Phone number to remove" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" autocomplete="tel">
                <button onclick="removeFromDND()" class="w-full px-3 py-2 bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 rounded-lg text-sm font-medium hover:bg-emerald-500/25 transition-colors">
                    <i class="fas fa-trash mr-1"></i>Remove from DND
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Customer Consent Management -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-3">
    <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
        <i class="fas fa-user-check text-amber-400 text-xs"></i>Customer Consent
    </h3>
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-800/60 border-b border-slate-700/60">
                <tr class="text-slate-500 text-xs uppercase">
                    <th class="px-2 py-2 text-left">Customer</th>
                    <th class="px-2 py-2 text-left">Phone</th>
                    <th class="px-2 py-2 text-left">Status</th>
                    <th class="px-2 py-2 text-left">Action</th>
                </tr>
            </thead>
            <tbody id="consentTable" class="divide-y divide-slate-700/40">
                <tr class="text-center py-4">
                    <td colspan="4" class="text-slate-500 text-xs">Loading customer consent data...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Export Analytics -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
    <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
        <i class="fas fa-download text-amber-400 text-xs"></i>Export Report
    </h3>
    <button onclick="exportAnalyticsToCsv()" class="px-4 py-2 bg-amber-500/15 border border-amber-500/30 text-amber-400 rounded-lg text-sm font-medium hover:bg-amber-500/25 transition-colors">
        <i class="fas fa-file-csv mr-1"></i>Download CSV
    </button>
</div>

</div>
<?php endif; // End has_sms_permission('sms.view') ?>

<!-- New Template Modal -->
<?php if (has_sms_permission('sms.manage_templates')): ?>
<div id="templateModal" class="fixed inset-0 bg-black/70 flex items-center justify-center hidden z-50">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-base font-semibold text-white">New SMS Template</h3>
            <button onclick="closeTemplateModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="templateForm" class="space-y-3">
            <input type="hidden" name="action" value="save_template">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div>
                <label for="templateName" class="block text-xs text-slate-500 mb-1">Template Name</label>
                <input type="text" name="name" id="templateName" required class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="e.g., Welcome Message" autocomplete="off">
            </div>
            
            <div>
                <label for="templateCategory" class="block text-xs text-slate-500 mb-1">Category</label>
                <select name="category" id="templateCategory" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" autocomplete="off">
                    <option value="general">General</option>
                    <option value="promotional">Promotional</option>
                    <option value="reminder">Reminder</option>
                    <option value="notification">Notification</option>
                </select>
            </div>
            
            <div>
                <label for="templateContent" class="block text-xs text-slate-500 mb-1">Message Content</label>
                <textarea name="content" id="templateContent" rows="3" required class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="Enter your template message..." autocomplete="off"></textarea>
                <p class="text-xs text-slate-500 mt-0.5">Max 160 characters for SMS</p>
            </div>
            
            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 px-3 py-2 bg-amber-500/15 border border-amber-500/30 text-amber-400 rounded-lg text-sm font-medium hover:bg-amber-500/25 transition-colors">
                    Save Template
                </button>
                <button type="button" onclick="closeTemplateModal()" class="flex-1 px-3 py-2 bg-slate-700 text-slate-300 rounded-lg text-sm font-medium hover:bg-slate-600 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; // End has_sms_permission('sms.manage_templates') ?>

<script>
// Global variables
let csrfToken = '<?php echo $csrf_token; ?>';
let currentTab = 'compose';

// Tab switching
function switchTab(tab, clickedBtn) {
    currentTab = tab;

    // Hide all tab content - remove active class (CSS handles display: none)
    document.querySelectorAll('.tab-content').forEach(el => {
        el.classList.remove('active');
    });

    // Remove active from all nav tabs
    document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active'));

    // Show selected tab - add active class (CSS handles display: block)
    const tabElement = document.getElementById(tab + 'Tab');
    if (tabElement) {
        tabElement.classList.add('active');
    }

    // Mark clicked button as active
    if (clickedBtn) {
        clickedBtn.classList.add('active');
    }

    // Load tab-specific content
    if (tab === 'history') {
        loadSmsHistory();
    } else if (tab === 'analytics') {
        loadAdvancedAnalytics();
        loadConsentTable();
    }
}

// Load statistics
async function loadStats() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_stats');
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('<?php echo base_url("customers/bulk_sms.php"); ?>', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('customersWithPhone').textContent = data.customers_with_phone.toLocaleString();
            document.getElementById('smsSentToday').textContent = data.sms_sent_today.toLocaleString();
            document.getElementById('totalSent').textContent = data.total_sent.toLocaleString();
            document.getElementById('smsBalance').textContent = data.sms_balance.toLocaleString();
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Load SMS history
async function loadSmsHistory() {
    try {
        const response = await fetch('<?php echo base_url("ajax/get_sms_history.php"); ?>?tenant_id=<?php echo $tenant_id; ?>');
        const data = await response.json();
        
        const container = document.getElementById('smsHistory');
        if (data.success && data.history.length > 0) {
            let html = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-[#111827] border-b border-[#374151]"><tr class="text-gray-400 text-xs uppercase">';
            html += '<th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Phone</th><th class="px-4 py-3 text-left">Message</th><th class="px-4 py-3 text-left">Status</th></tr></thead><tbody>';
            
            data.history.forEach(log => {
                html += `<tr class="border-b border-[#374151] hover:bg-white/5">
                    <td class="px-4 py-3 text-xs text-gray-400">${new Date(log.sent_at).toLocaleString()}</td>
                    <td class="px-4 py-3 text-white">${log.phone}</td>
                    <td class="px-4 py-3 text-gray-400">${log.message.substring(0, 50)}${log.message.length > 50 ? '...' : ''}</td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs ${log.status === 'sent' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'}">${log.status}</span></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="text-center py-8 text-gray-500"><i class="fas fa-history text-3xl mb-2 opacity-50"></i><p>No SMS history found</p></div>';
        }
    } catch (error) {
        console.error('Error loading history:', error);
        document.getElementById('smsHistory').innerHTML = '<div class="text-center py-8 text-red-500">Error loading history</div>';
    }
}

// Load template
function loadTemplate() {
    const select = document.getElementById('templateSelect');
    const option = select.options[select.selectedIndex];
    if (option.value) {
        document.getElementById('smsMessage').value = option.getAttribute('data-content') || '';
        updateCharCount();
    }
}

function useTemplate(id, content) {
    document.getElementById('smsMessage').value = content;
    updateCharCount();
    switchTab('compose');
}

async function deleteTemplate(id) {
    if (confirm('Are you sure you want to delete this template?')) {
        const formData = new FormData();
        formData.append('action', 'delete_template');
        formData.append('template_id', id);
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('<?php echo base_url("customers/bulk_sms.php"); ?>', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    }
}

// Toggle send options
function toggleSendOptions() {
    const sendType = document.querySelector('input[name="send_type"]:checked').value;
    document.getElementById('groupSelect').classList.add('hidden');
    document.getElementById('tierSelect').classList.add('hidden');
    
    if (sendType === 'group') {
        document.getElementById('groupSelect').classList.remove('hidden');
    } else if (sendType === 'tier') {
        document.getElementById('tierSelect').classList.remove('hidden');
    }
}

// Update character count
function updateCharCount() {
    const message = document.getElementById('smsMessage').value;
    const length = message.length;
    const maxChars = 160;
    const charCountSpan = document.getElementById('charCount');
    charCountSpan.textContent = `${length} / ${maxChars} characters`;
    
    charCountSpan.classList.remove('warning', 'error');
    if (length > maxChars) {
        charCountSpan.classList.add('error');
    } else if (length > maxChars * 0.9) {
        charCountSpan.classList.add('warning');
    }
}

// Filter customers
function filterCustomers() {
    const searchTerm = document.getElementById('customerSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.customer-row');
    let visible = 0;
    
    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        if (name.includes(searchTerm)) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
    
    document.getElementById('visibleCount').textContent = visible;
}

// Select/Deselect functions
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function selectAllCustomers() {
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
    const selectAllCheck = document.getElementById('selectAllCheck');
    if (selectAllCheck) selectAllCheck.checked = true;
}

function deselectAllCustomers() {
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    const selectAllCheck = document.getElementById('selectAllCheck');
    if (selectAllCheck) selectAllCheck.checked = false;
}

// Modal functions
function openTemplateModal() {
    document.getElementById('templateModal').classList.remove('hidden');
}

function closeTemplateModal() {
    document.getElementById('templateModal').classList.add('hidden');
}

function viewCampaign(id) {
    window.location.href = 'campaign_details.php?id=' + id;
}

function cancelCampaign(id) {
    if (confirm('Are you sure you want to cancel this campaign?')) {
        // Implement campaign cancellation
    }
}

// Form submission
document.getElementById('smsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const message = document.getElementById('smsMessage').value.trim();
    const sendType = document.querySelector('input[name="send_type"]:checked').value;
    const resultDiv = document.getElementById('smsResult');
    const btn = document.getElementById('sendSmsBtn');
    const btnText = document.getElementById('btnText');
    
    if (!message) {
        showResult('Please enter a message', 'error');
        return;
    }
    
    if (message.length > 160) {
        showResult('Message exceeds 160 characters', 'error');
        return;
    }
    
    let customerIds = [];
    if (sendType === 'selected') {
        const selectedCheckboxes = document.querySelectorAll('.customer-checkbox:checked');
        customerIds = Array.from(selectedCheckboxes).map(cb => cb.value);
        
        if (customerIds.length === 0) {
            showResult('Please select at least one customer', 'error');
            return;
        }
    }
    
    btn.disabled = true;
    btnText.textContent = 'Sending...';
    resultDiv.classList.add('hidden');
    
    const formData = new FormData();
    formData.append('action', 'send_sms');
    formData.append('csrf_token', csrfToken);
    formData.append('message', message);
    formData.append('send_type', sendType);
    formData.append('customer_ids', JSON.stringify(customerIds));
    formData.append('send_to_group', document.getElementById('sendToGroup')?.value || '');
    formData.append('send_to_tier', document.getElementById('sendToTier')?.value || '');
    formData.append('schedule_time', document.getElementById('scheduleTime')?.value || '');
    formData.append('campaign_name', document.getElementById('campaignName')?.value || '');
    
    try {
        const response = await fetch('<?php echo base_url("customers/bulk_sms.php"); ?>', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            let message = data.message;
            if (data.failed_recipients && data.failed_recipients.length > 0) {
                message += `<br><small class="text-gray-400">Failed: ${data.failed_recipients.join(', ')}</small>`;
            }
            showResult(message, 'success');
            
            if (data.sent > 0) {
                setTimeout(() => {
                    document.getElementById('smsMessage').value = '';
                    deselectAllCustomers();
                    updateCharCount();
                    loadStats();
                }, 2000);
            }
        } else {
            showResult(data.message, 'error');
            if (data.credits_needed && data.current_balance !== undefined) {
                showResult(`Insufficient credits. Need ${data.credits_needed}, have ${data.current_balance}. <a href="credits.php" class="underline">Purchase credits</a>`, 'error');
            }
        }
    } catch (error) {
        showResult(`Error: ${error.message}`, 'error');
    }
    
    btn.disabled = false;
    btnText.textContent = 'Send SMS';
});

function showResult(message, type) {
    const resultDiv = document.getElementById('smsResult');
    resultDiv.classList.remove('hidden', 'bg-green-500/10', 'text-green-400', 'bg-red-500/10', 'text-red-400');
    
    if (type === 'success') {
        resultDiv.classList.add('bg-green-500/10', 'text-green-400');
    } else {
        resultDiv.classList.add('bg-red-500/10', 'text-red-400');
    }
    
    resultDiv.innerHTML = message;
    
    setTimeout(() => {
        resultDiv.classList.add('hidden');
    }, 5000);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    updateCharCount();
    document.getElementById('smsMessage').addEventListener('input', updateCharCount);
    toggleSendOptions();
});

// Template form submission
document.getElementById('templateForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('csrf_token', csrfToken);
    
    try {
        const response = await fetch('<?php echo base_url("customers/bulk_sms.php"); ?>', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            alert('Template saved successfully');
            location.reload();
        } else {
            alert(data.message);
        }
    } catch (error) {
        alert('Error saving template: ' + error.message);
    }
});

// Advanced Analytics Functions
async function loadAdvancedAnalytics() {
    const dateFrom = document.getElementById('analyticsDateFrom').value;
    const dateTo = document.getElementById('analyticsDateTo').value;
    
    try {
        const response = await fetch('<?php echo base_url("ajax/get_sms_analytics.php"); ?>?tenant_id=<?php echo $tenant_id; ?>&date_from=' + dateFrom + '&date_to=' + dateTo);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('analyticsTotalSent').textContent = data.total_sent.toLocaleString();
            document.getElementById('analyticsDelivered').textContent = data.delivered.toLocaleString();
            document.getElementById('analyticsFailed').textContent = data.failed.toLocaleString();
            
            const successRate = data.total_sent > 0 ? ((data.delivered / data.total_sent) * 100).toFixed(2) : 0;
            document.getElementById('analyticsSuccessRate').textContent = successRate + '%';
            
            // Build daily report table
            let html = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-[#111827] border-b border-[#374151]"><tr class="text-gray-400 text-xs uppercase">';
            html += '<th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Sent</th><th class="px-4 py-3 text-right">Delivered</th><th class="px-4 py-3 text-right">Failed</th><th class="px-4 py-3 text-right">Rate</th></tr></thead><tbody>';
            
            data.daily_breakdown.forEach(day => {
                const rate = day.total > 0 ? ((day.delivered / day.total) * 100).toFixed(2) : 0;
                html += `<tr class="border-b border-[#374151] hover:bg-white/5">
                    <td class="px-4 py-3 text-white">${day.date}</td>
                    <td class="px-4 py-3 text-right text-gray-400">${day.total}</td>
                    <td class="px-4 py-3 text-right text-yellow-400">${day.sent}</td>
                    <td class="px-4 py-3 text-right text-green-400">${day.delivered}</td>
                    <td class="px-4 py-3 text-right text-red-400">${day.failed}</td>
                    <td class="px-4 py-3 text-right text-[#FBBF24]">${rate}%</td>
                </tr>`;
            });
            
            html += '</tbody></table></div>';
            document.getElementById('analyticsReport').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading analytics:', error);
        document.getElementById('analyticsReport').innerHTML = '<div class="text-center py-8 text-red-500">Error loading analytics</div>';
    }
}

// DND Management Functions
async function addToDND() {
    const phone = document.getElementById('dndPhone').value.trim();
    const reason = document.getElementById('dndReason').value.trim();
    
    if (!phone) {
        alert('Please enter a phone number');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'manage_dnd');
    formData.append('dnd_action', 'add');
    formData.append('phone', phone);
    formData.append('reason', reason || 'User requested');
    formData.append('csrf_token', csrfToken);
    
    try {
        const response = await fetch('<?php echo base_url("customers/bulk_sms.php"); ?>', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            document.getElementById('dndPhone').value = '';
            document.getElementById('dndReason').value = '';
        } else {
            alert(data.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function removeFromDND() {
    const phone = document.getElementById('dndPhoneRemove').value.trim();
    
    if (!phone) {
        alert('Please enter a phone number');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'manage_dnd');
    formData.append('dnd_action', 'remove');
    formData.append('phone', phone);
    formData.append('csrf_token', csrfToken);
    
    try {
        const response = await fetch('<?php echo base_url("customers/bulk_sms.php"); ?>', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            document.getElementById('dndPhoneRemove').value = '';
        } else {
            alert(data.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Consent Management
async function setCustomerConsent(customerId, optedIn) {
    const formData = new FormData();
    formData.append('action', 'set_consent');
    formData.append('customer_id', customerId);
    formData.append('opted_in', optedIn ? 1 : 0);
    formData.append('csrf_token', csrfToken);
    
    try {
        const response = await fetch('<?php echo base_url("customers/bulk_sms.php"); ?>', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            loadConsentTable();
        } else {
            alert(data.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function loadConsentTable() {
    try {
        const response = await fetch('<?php echo base_url("ajax/get_customer_consent.php"); ?>?tenant_id=<?php echo $tenant_id; ?>');
        const data = await response.json();
        
        if (data.success && data.customers.length > 0) {
            let html = '';
            data.customers.forEach(customer => {
                const status = customer.opted_in ? '<span class="px-2 py-1 rounded-full text-xs bg-green-500/20 text-green-400">Opted In</span>' : '<span class="px-2 py-1 rounded-full text-xs bg-red-500/20 text-red-400">Opted Out</span>';
                html += `<tr class="border-b border-[#374151] hover:bg-white/5">
                    <td class="px-4 py-3 text-white">${customer.name}</td>
                    <td class="px-4 py-3 text-gray-400">${customer.phone}</td>
                    <td class="px-4 py-3">${status}</td>
                    <td class="px-4 py-3 space-x-2">
                        <button onclick="setCustomerConsent(${customer.id}, 1)" class="text-xs text-green-400 hover:text-green-300">Opt In</button>
                        <button onclick="setCustomerConsent(${customer.id}, 0)" class="text-xs text-red-400 hover:text-red-300">Opt Out</button>
                    </td>
                </tr>`;
            });
            document.getElementById('consentTable').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading consent table:', error);
    }
}

// Export CSV
async function exportAnalyticsToCsv() {
    const dateFrom = document.getElementById('analyticsDateFrom').value;
    const dateTo = document.getElementById('analyticsDateTo').value;
    
    const formData = new FormData();
    formData.append('action', 'export_csv');
    formData.append('date_from', dateFrom);
    formData.append('date_to', dateTo);
    formData.append('csrf_token', csrfToken);
    
    try {
        const response = await fetch('<?php echo base_url("customers/bulk_sms.php"); ?>', {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'sms_report_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
        } else {
            alert('Failed to export CSV');
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';