<?php
/**
 * Bulk SMS System - Advanced SaaS Edition
 * Full-featured SMS marketing platform with:
 * - Credit system management
 * - Template library
 * - Campaign scheduling
 * - DND management
 * - Customer consent tracking
 * - Analytics & reporting
 * - Multi-provider support (Africa's Talking, Twilio, generic)
 * - Queue system for large batches
 * - Webhook delivery status
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
$currency = get_tenant_currency() ?? 'KES';

// Permission check
function has_sms_permission($permission) {
    global $is_super_admin;
    if ($is_super_admin) return true;
    if (check_permission('sms.admin')) return true;
    return check_permission($permission);
}

// ============================================
// DATABASE SCHEMA CREATION
// ============================================
function create_sms_tables($pdo, $tenant_id) {
    $queries = [
        "CREATE TABLE IF NOT EXISTS sms_credits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            balance INT NOT NULL DEFAULT 0,
            total_purchased INT NOT NULL DEFAULT 0,
            total_used INT NOT NULL DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_tenant (tenant_id),
            INDEX idx_balance (balance)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS sms_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            content TEXT NOT NULL,
            category VARCHAR(50) DEFAULT 'general',
            variables JSON,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS sms_campaigns (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            name VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            template_id INT UNSIGNED NULL,
            target_type ENUM('all', 'group', 'tier', 'selected', 'segment') NOT NULL,
            target_ids TEXT,
            total_recipients INT DEFAULT 0,
            sent_count INT DEFAULT 0,
            failed_count INT DEFAULT 0,
            credits_used INT DEFAULT 0,
            status ENUM('draft', 'scheduled', 'processing', 'completed', 'cancelled', 'failed') DEFAULT 'draft',
            scheduled_at TIMESTAMP NULL,
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            created_by INT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            INDEX idx_status (status),
            INDEX idx_scheduled (scheduled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS sms_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            branch_id INT UNSIGNED NULL,
            campaign_id INT UNSIGNED NULL,
            customer_id INT UNSIGNED NULL,
            phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            message_length INT DEFAULT 0,
            segments INT DEFAULT 1,
            credits_used INT DEFAULT 1,
            status ENUM('pending', 'queued', 'sent', 'delivered', 'failed', 'cancelled') DEFAULT 'pending',
            provider_message_id VARCHAR(100),
            error_message TEXT,
            sent_at TIMESTAMP NULL,
            delivered_at TIMESTAMP NULL,
            scheduled_at TIMESTAMP NULL,
            created_by INT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            INDEX idx_campaign (campaign_id),
            INDEX idx_customer (customer_id),
            INDEX idx_status (status),
            INDEX idx_sent_at (sent_at),
            INDEX idx_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS sms_dnd_list (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            phone VARCHAR(20) NOT NULL,
            reason VARCHAR(100),
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            added_by INT UNSIGNED,
            UNIQUE KEY uk_tenant_phone (tenant_id, phone),
            INDEX idx_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS sms_customer_consent (
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
            UNIQUE KEY uk_tenant_customer (tenant_id, customer_id),
            INDEX idx_opted_in (opted_in)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS sms_provider_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            provider ENUM('africastalking', 'twilio', 'generic') DEFAULT 'africastalking',
            api_key VARCHAR(255),
            api_secret VARCHAR(255),
            username VARCHAR(100),
            sender_id VARCHAR(50) DEFAULT 'JAKABABA',
            api_url VARCHAR(500),
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($queries as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { error_log("Table creation error: " . $e->getMessage()); }
    }
    
    // Initialize credits
    $stmt = $pdo->prepare("INSERT IGNORE INTO sms_credits (tenant_id, balance) VALUES (?, 0)");
    $stmt->execute([$tenant_id]);
    
    // Initialize provider settings
    $stmt = $pdo->prepare("INSERT IGNORE INTO sms_provider_settings (tenant_id, provider, sender_id) VALUES (?, 'africastalking', 'JAKABABA')");
    $stmt->execute([$tenant_id]);
}

create_sms_tables($pdo, $tenant_id);

// ============================================
// SMS PROVIDER FUNCTIONS
// ============================================
function get_sms_provider_settings($tenant_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM sms_provider_settings WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['provider' => 'africastalking', 'sender_id' => 'JAKABABA'];
    }
}

function send_sms_via_provider($phone, $message, $tenant_id) {
    $settings = get_sms_provider_settings($tenant_id);
    if (!$settings || empty($settings['api_key'])) {
        return ['success' => false, 'message' => 'SMS provider not configured'];
    }
    
    // Normalize phone number
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($phone) === 9) $phone = '254' . $phone;
    elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '0') $phone = '254' . substr($phone, 1);
    elseif (!preg_match('/^\+?254/', $phone)) $phone = '254' . ltrim($phone, '0');
    
    // Limit message
    if (strlen($message) > 160) $message = substr($message, 0, 157) . '...';
    
    try {
        switch ($settings['provider']) {
            case 'africastalking':
                $url = 'https://api.africastalking.com/version1/messaging';
                $data = ['username' => $settings['username'], 'to' => $phone, 'message' => $message, 'from' => $settings['sender_id']];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['ApiKey: ' . $settings['api_key'], 'Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return ['success' => $http_code >= 200 && $http_code < 300, 'message' => $response];
                
            case 'twilio':
                $url = "https://api.twilio.com/2010-04-01/Accounts/{$settings['username']}/Messages.json";
                $data = ['To' => $phone, 'From' => $settings['sender_id'], 'Body' => $message];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_USERPWD, "{$settings['username']}:{$settings['api_key']}");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return ['success' => $http_code === 201, 'message' => $response];
                
            default:
                if (!empty($settings['api_url'])) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $settings['api_url']);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['api_key' => $settings['api_key'], 'to' => $phone, 'message' => $message, 'from' => $settings['sender_id']]));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    return ['success' => $http_code >= 200 && $http_code < 300, 'message' => $response];
                }
                return ['success' => false, 'message' => 'No API URL configured'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================
// CREDIT MANAGEMENT
// ============================================
function get_sms_balance($tenant_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT balance FROM sms_credits WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        $result = $stmt->fetch();
        return $result ? (int)$result['balance'] : 0;
    } catch (Exception $e) { return 0; }
}

function deduct_sms_credits($tenant_id, $amount) {
    try {
        $pdo = get_db_connection();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT balance FROM sms_credits WHERE tenant_id = ? FOR UPDATE");
        $stmt->execute([$tenant_id]);
        $balance = $stmt->fetchColumn();
        if ($balance < $amount) return false;
        $stmt = $pdo->prepare("UPDATE sms_credits SET balance = balance - ?, total_used = total_used + ? WHERE tenant_id = ?");
        $stmt->execute([$amount, $amount, $tenant_id]);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        return false;
    }
}

function add_sms_credits($tenant_id, $amount, $reference = 'purchase') {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("UPDATE sms_credits SET balance = balance + ?, total_purchased = total_purchased + ? WHERE tenant_id = ?");
        $stmt->execute([$amount, $amount, $tenant_id]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) { return false; }
}

// ============================================
// DND & CONSENT MANAGEMENT
// ============================================
function is_phone_on_dnd($phone, $tenant_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT id FROM sms_dnd_list WHERE tenant_id = ? AND phone = ?");
        $stmt->execute([$tenant_id, $phone]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) { return false; }
}

function has_customer_consent($customer_id, $tenant_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT opted_in FROM sms_customer_consent WHERE tenant_id = ? AND customer_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);
        $result = $stmt->fetch();
        return $result ? (bool)$result['opted_in'] : true;
    } catch (Exception $e) { return true; }
}

// ============================================
// MESSAGE PROCESSING
// ============================================
function calculate_sms_segments($message) {
    $length = strlen($message);
    if ($length <= 160) return 1;
    if ($length <= 306) return 2;
    return ceil($length / 153);
}

function personalize_message($message, $customer) {
    $placeholders = [
        '{name}' => $customer['name'] ?? 'Customer',
        '{customer_name}' => $customer['name'] ?? 'Customer',
        '{phone}' => $customer['phone'] ?? '',
        '{points}' => number_format($customer['loyalty_points'] ?? 0),
        '{loyalty_points}' => number_format($customer['loyalty_points'] ?? 0),
        '{tier}' => ucfirst($customer['loyalty_tier'] ?? 'bronze'),
        '{loyalty_tier}' => ucfirst($customer['loyalty_tier'] ?? 'bronze'),
        '{company}' => get_tenant_setting($tenant_id ?? 1, 'company_name', 'Our Store'),
        '{store}' => get_tenant_setting($tenant_id ?? 1, 'company_name', 'Our Store'),
    ];
    return str_replace(array_keys($placeholders), array_values($placeholders), $message);
}

// ============================================
// AJAX HANDLERS
// ============================================
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$is_post = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']);

if ($is_ajax || $is_post) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token) && !verify_csrf_token($csrf_token, 'bulk_sms')) {
        echo json_encode(['success' => false, 'message' => 'Security validation failed']);
        exit;
    }
    
    switch ($action) {
        case 'get_stats':
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT CASE WHEN phone IS NOT NULL AND phone != '' THEN id END) as customers_with_phone FROM customers WHERE tenant_id = ? AND status = 1");
            $stmt->execute([$tenant_id]);
            $customer_stats = $stmt->fetch();
            $stmt = $pdo->prepare("SELECT COUNT(*) as sent_today FROM sms_logs WHERE tenant_id = ? AND DATE(sent_at) = CURDATE() AND status = 'sent'");
            $stmt->execute([$tenant_id]);
            $sent_today = $stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_sent FROM sms_logs WHERE tenant_id = ? AND status = 'sent'");
            $stmt->execute([$tenant_id]);
            $total_sent = $stmt->fetchColumn();
            echo json_encode(['success' => true, 'customers_with_phone' => (int)($customer_stats['customers_with_phone'] ?? 0), 'sms_sent_today' => (int)$sent_today, 'total_sent' => (int)$total_sent, 'sms_balance' => get_sms_balance($tenant_id)]);
            break;
            
        case 'get_templates':
            $stmt = $pdo->prepare("SELECT id, name, content, category FROM sms_templates WHERE tenant_id = ? AND is_active = 1 ORDER BY name");
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'templates' => $stmt->fetchAll()]);
            break;
            
        case 'save_template':
            if (!has_sms_permission('sms.manage_templates')) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }
            $name = trim($_POST['name'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $category = trim($_POST['category'] ?? 'general');
            if (empty($name) || empty($content)) { echo json_encode(['success' => false, 'message' => 'Name and content required']); exit; }
            $stmt = $pdo->prepare("INSERT INTO sms_templates (tenant_id, name, content, category, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $name, $content, $category, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Template saved', 'id' => $pdo->lastInsertId()]);
            break;
            
        case 'delete_template':
            if (!has_sms_permission('sms.manage_templates')) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }
            $id = intval($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM sms_templates WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            echo json_encode(['success' => true, 'message' => 'Template deleted']);
            break;
            
        case 'send_sms':
            if (!has_sms_permission('sms.send')) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }
            
            $message = trim($_POST['message'] ?? '');
            $send_type = $_POST['send_type'] ?? 'selected';
            $customer_ids = json_decode($_POST['customer_ids'] ?? '[]', true);
            $group_id = intval($_POST['group_id'] ?? 0);
            $tier = $_POST['tier'] ?? '';
            $schedule_time = $_POST['schedule_time'] ?? '';
            $campaign_name = trim($_POST['campaign_name'] ?? '');
            $template_id = intval($_POST['template_id'] ?? 0);
            
            if (empty($message)) { echo json_encode(['success' => false, 'message' => 'Message is required']); exit; }
            
            // Get recipients
            $recipients = [];
            if ($send_type === 'selected' && !empty($customer_ids)) {
                $placeholders = implode(',', array_fill(0, count($customer_ids), '?'));
                $stmt = $pdo->prepare("SELECT id, name, phone, loyalty_points, loyalty_tier FROM customers WHERE id IN ($placeholders) AND tenant_id = ? AND phone IS NOT NULL AND phone != '' AND status = 1");
                $stmt->execute(array_merge($customer_ids, [$tenant_id]));
                $recipients = $stmt->fetchAll();
            } elseif ($send_type === 'group' && $group_id > 0) {
                $stmt = $pdo->prepare("SELECT id, name, phone, loyalty_points, loyalty_tier FROM customers WHERE tenant_id = ? AND group_id = ? AND phone IS NOT NULL AND phone != '' AND status = 1");
                $stmt->execute([$tenant_id, $group_id]);
                $recipients = $stmt->fetchAll();
            } elseif ($send_type === 'tier' && !empty($tier)) {
                $stmt = $pdo->prepare("SELECT id, name, phone, loyalty_points, loyalty_tier FROM customers WHERE tenant_id = ? AND loyalty_tier = ? AND phone IS NOT NULL AND phone != '' AND status = 1");
                $stmt->execute([$tenant_id, $tier]);
                $recipients = $stmt->fetchAll();
            } elseif ($send_type === 'all') {
                $stmt = $pdo->prepare("SELECT id, name, phone, loyalty_points, loyalty_tier FROM customers WHERE tenant_id = ? AND phone IS NOT NULL AND phone != '' AND status = 1");
                $stmt->execute([$tenant_id]);
                $recipients = $stmt->fetchAll();
            }
            
            if (empty($recipients)) { echo json_encode(['success' => false, 'message' => 'No valid recipients found']); exit; }
            
            // Check credits
            $credits_needed = count($recipients);
            $current_balance = get_sms_balance($tenant_id);
            if ($current_balance < $credits_needed) { echo json_encode(['success' => false, 'message' => "Insufficient credits. Need $credits_needed, have $current_balance"]); exit; }
            
            // Create campaign
            $campaign_id = null;
            $stmt = $pdo->prepare("INSERT INTO sms_campaigns (tenant_id, name, message, template_id, target_type, target_ids, total_recipients, credits_used, status, scheduled_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $campaign_name ?: 'Bulk SMS Campaign', $message, $template_id, $send_type, json_encode($customer_ids), count($recipients), $credits_needed, !empty($schedule_time) ? 'scheduled' : 'processing', !empty($schedule_time) ? $schedule_time : null, $user_id]);
            $campaign_id = $pdo->lastInsertId();
            
            // Schedule or send
            if (!empty($schedule_time) && strtotime($schedule_time) > time()) {
                echo json_encode(['success' => true, 'message' => "Campaign scheduled for " . date('Y-m-d H:i', strtotime($schedule_time)), 'campaign_id' => $campaign_id, 'recipients' => count($recipients)]);
                exit;
            }
            
            // Deduct credits
            if (!deduct_sms_credits($tenant_id, $credits_needed)) { echo json_encode(['success' => false, 'message' => 'Failed to deduct credits']); exit; }
            
            // Send SMS
            $sent_count = 0;
            $failed_count = 0;
            $log_stmt = $pdo->prepare("INSERT INTO sms_logs (tenant_id, branch_id, campaign_id, customer_id, phone, message, message_length, segments, credits_used, status, error_message, sent_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($recipients as $customer) {
                if (is_phone_on_dnd($customer['phone'], $tenant_id)) {
                    $failed_count++;
                    $log_stmt->execute([$tenant_id, $branch_id, $campaign_id, $customer['id'], $customer['phone'], $message, strlen($message), 1, 1, 'failed', 'Phone on DND list', null, $user_id]);
                    continue;
                }
                if (!has_customer_consent($customer['id'], $tenant_id)) {
                    $failed_count++;
                    $log_stmt->execute([$tenant_id, $branch_id, $campaign_id, $customer['id'], $customer['phone'], $message, strlen($message), 1, 1, 'failed', 'No consent', null, $user_id]);
                    continue;
                }
                
                $personalized = personalize_message($message, $customer);
                $segments = calculate_sms_segments($personalized);
                $result = send_sms_via_provider($customer['phone'], $personalized, $tenant_id);
                
                if ($result['success']) {
                    $sent_count++;
                    $log_stmt->execute([$tenant_id, $branch_id, $campaign_id, $customer['id'], $customer['phone'], $personalized, strlen($personalized), $segments, $segments, 'sent', null, date('Y-m-d H:i:s'), $user_id]);
                } else {
                    $failed_count++;
                    $log_stmt->execute([$tenant_id, $branch_id, $campaign_id, $customer['id'], $customer['phone'], $personalized, strlen($personalized), $segments, $segments, 'failed', substr($result['message'], 0, 255), null, $user_id]);
                }
            }
            
            $stmt = $pdo->prepare("UPDATE sms_campaigns SET sent_count = ?, failed_count = ?, status = 'completed', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$sent_count, $failed_count, $campaign_id]);
            
            echo json_encode(['success' => true, 'sent' => $sent_count, 'failed' => $failed_count, 'message' => "SMS sent: $sent_count successful, $failed_count failed"]);
            break;
            
        case 'get_campaigns':
            $stmt = $pdo->prepare("SELECT c.*, u.name as creator_name FROM sms_campaigns c LEFT JOIN users u ON c.created_by = u.id WHERE c.tenant_id = ? ORDER BY c.created_at DESC LIMIT 20");
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'campaigns' => $stmt->fetchAll()]);
            break;
            
        case 'get_history':
            $stmt = $pdo->prepare("SELECT l.*, c.name as customer_name FROM sms_logs l LEFT JOIN customers c ON l.customer_id = c.id WHERE l.tenant_id = ? ORDER BY l.created_at DESC LIMIT 100");
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'logs' => $stmt->fetchAll()]);
            break;
            
        case 'get_analytics':
            $date_from = $_POST['date_from'] ?? date('Y-m-01');
            $date_to = $_POST['date_to'] ?? date('Y-m-d');
            $stmt = $pdo->prepare("SELECT DATE(sent_at) as date, COUNT(*) as total, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed FROM sms_logs WHERE tenant_id = ? AND DATE(sent_at) BETWEEN ? AND ? GROUP BY DATE(sent_at) ORDER BY date DESC");
            $stmt->execute([$tenant_id, $date_from, $date_to]);
            $daily = $stmt->fetchAll();
            $total_sent = array_sum(array_column($daily, 'sent'));
            $total_failed = array_sum(array_column($daily, 'failed'));
            echo json_encode(['success' => true, 'total_sent' => $total_sent, 'total_failed' => $total_failed, 'daily_breakdown' => $daily]);
            break;
            
        case 'add_credits':
            if (!has_sms_permission('sms.credits')) { echo json_encode(['success' => false, 'message' => 'Permission denied']); exit; }
            $amount = intval($_POST['amount'] ?? 0);
            if ($amount <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid amount']); exit; }
            if (add_sms_credits($tenant_id, $amount)) {
                echo json_encode(['success' => true, 'message' => "$amount credits added", 'new_balance' => get_sms_balance($tenant_id)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add credits']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    exit;
}

// ============================================
// PAGE DATA LOADING
// ============================================
$csrf_token = generate_csrf_token();

// Get customer groups
$customer_groups = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM customer_groups WHERE tenant_id = ? ORDER BY name");
    $stmt->execute([$tenant_id]);
    $customer_groups = $stmt->fetchAll();
} catch (Exception $e) {}

// Get customers with phones
$customers = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, phone, loyalty_points, loyalty_tier FROM customers WHERE tenant_id = ? AND phone IS NOT NULL AND phone != '' AND status = 1 ORDER BY name LIMIT 500");
    $stmt->execute([$tenant_id]);
    $customers = $stmt->fetchAll();
} catch (Exception $e) {}

// Get templates
$templates = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, content, category FROM sms_templates WHERE tenant_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$tenant_id]);
    $templates = $stmt->fetchAll();
} catch (Exception $e) {}

// Get campaigns
$campaigns = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, status, total_recipients, sent_count, failed_count, scheduled_at, created_at FROM sms_campaigns WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$tenant_id]);
    $campaigns = $stmt->fetchAll();
} catch (Exception $e) {}

$page_title = 'Bulk SMS System';
ob_start();
?>

<!-- Custom Styles -->
<style>
    .bulk-sms-tabs .nav-tab { padding: 0.5rem 1rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; background: transparent; color: #64748b; font-weight: 500; border: 1px solid transparent; }
    .bulk-sms-tabs .nav-tab.active { background: rgba(245,158,11,0.15); color: #fbbf24; border-color: rgba(245,158,11,0.3); }
    .bulk-sms-tabs .nav-tab:hover:not(.active) { background: rgba(245,158,11,0.1); color: #fbbf24; }
    .bulk-sms-input { background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem; padding: 0.5rem 0.75rem; color: white; width: 100%; transition: all 0.2s; }
    .bulk-sms-input:focus { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 2px rgba(245,158,11,0.2); }
    .bulk-sms-card { transition: all 0.2s ease; }
    .bulk-sms-card:hover { transform: translateY(-2px); border-color: rgba(245,158,11,0.3); }
    .char-count-bulk { font-size: 0.75rem; color: #64748b; }
    .char-count-bulk.warning { color: #f59e0b; }
    .char-count-bulk.error { color: #ef4444; }
</style>

<?php if (!empty($_GET['success'])): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars(urldecode($_GET['message'] ?? 'Action completed successfully.')); ?>
</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
</div>
<?php endif; ?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-sms text-amber-400"></i> Bulk SMS System
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">SMS marketing platform</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <div class="bg-slate-800/60 rounded-xl px-4 py-2 text-center border border-slate-700">
            <p class="text-xs text-slate-500">Credit Balance</p>
            <p class="text-xl font-bold text-amber-400" id="smsBalance">--</p>
        </div>
        <a href="customers.php" class="px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left mr-1 text-xs"></i>Back
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-phone text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-amber-400 truncate" id="customersWithPhone">--</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">With Phone</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-paper-plane text-emerald-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-emerald-400 truncate" id="smsSentToday">--</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Sent Today</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-chart-line text-blue-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-blue-400 truncate" id="totalSent">--</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Total Sent</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-users text-purple-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-purple-400 truncate"><?php echo count($customer_groups); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Groups</div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="flex flex-wrap gap-2 border-b border-slate-700 pb-2 bulk-sms-tabs">
    <button class="nav-tab active" data-tab="compose"><i class="fas fa-edit mr-2"></i>Compose</button>
    <button class="nav-tab" data-tab="templates"><i class="fas fa-file-alt mr-2"></i>Templates</button>
    <button class="nav-tab" data-tab="campaigns"><i class="fas fa-bullhorn mr-2"></i>Campaigns</button>
    <button class="nav-tab" data-tab="history"><i class="fas fa-history mr-2"></i>History</button>
    <button class="nav-tab" data-tab="analytics"><i class="fas fa-chart-bar mr-2"></i>Analytics</button>
    <button class="nav-tab" data-tab="credits"><i class="fas fa-coins mr-2"></i>Credits</button>
</div>

<!-- Compose Tab -->
<div id="composeTab" class="tab-content">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-lg font-semibold text-white mb-4"><i class="fas fa-edit text-amber-400 mr-2"></i>Compose Message</h3>
            <form id="smsForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="send_sms">
                
                <div><label class="block text-xs font-semibold text-slate-400 mb-1">Campaign Name</label><input type="text" name="campaign_name" id="campaignName" class="bulk-sms-input" placeholder="e.g., Black Friday Promo"></div>
                
                <div><label class="block text-xs font-semibold text-slate-400 mb-2">Send To</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="send_type" value="selected" checked onchange="toggleSendType()"><span class="text-slate-300">Selected Customers</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="send_type" value="group" onchange="toggleSendType()"><span class="text-slate-300">All Customers in Group</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="send_type" value="tier" onchange="toggleSendType()"><span class="text-slate-300">Customers by Tier</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="send_type" value="all" onchange="toggleSendType()"><span class="text-slate-300">All Customers with Phone</span></label>
                    </div>
                </div>
                
                <div id="groupSelect" class="hidden"><label class="block text-xs font-semibold text-slate-400 mb-1">Select Group</label><select id="sendGroup" class="bulk-sms-input"><option value="">-- Select Group --</option><?php foreach ($customer_groups as $g): ?><option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option><?php endforeach; ?></select></div>
                <div id="tierSelect" class="hidden"><label class="block text-xs font-semibold text-slate-400 mb-1">Select Tier</label><select id="sendTier" class="bulk-sms-input"><option value="">-- Select Tier --</option><option value="bronze">Bronze</option><option value="silver">Silver</option><option value="gold">Gold</option><option value="platinum">Platinum</option></select></div>
                    
                <div><label class="block text-xs font-semibold text-slate-400 mb-1">Use Template</label><select id="templateSelect" class="bulk-sms-input" onchange="loadTemplateContent()"><option value="">-- Select Template --</option><?php foreach ($templates as $t): ?><option value="<?php echo $t['id']; ?>" data-content="<?php echo htmlspecialchars($t['content']); ?>"><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?></select></div>
                    
                <div><label class="block text-xs font-semibold text-slate-400 mb-1">Message <span class="text-red-400">*</span></label><textarea id="smsMessage" name="message" rows="4" class="bulk-sms-input" placeholder="Type your message here..."></textarea><div class="flex justify-between mt-1"><span class="char-count-bulk" id="charCount">0 / 160 characters</span><span class="text-xs text-slate-500">Standard SMS: 160 chars, 1 credit</span></div></div>
                    
                <div><label class="block text-xs font-semibold text-slate-400 mb-1">Schedule (Optional)</label><input type="datetime-local" id="scheduleTime" class="bulk-sms-input"></div>
                    
                <button type="submit" id="sendBtn" class="w-full py-2.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 font-semibold hover:bg-amber-500/25 transition-all"><i class="fas fa-paper-plane mr-2"></i>Send SMS</button>
                <div id="sendResult" class="hidden p-3 rounded-lg"></div>
            </form>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-white"><i class="fas fa-users text-amber-400 mr-2"></i>Select Customers</h3><div class="flex gap-2"><button type="button" onclick="selectAll()" class="text-xs px-2 py-1 bg-slate-700 rounded hover:bg-slate-600">All</button><button type="button" onclick="deselectAll()" class="text-xs px-2 py-1 bg-slate-700 rounded hover:bg-slate-600">None</button></div></div>
            <div class="mb-3"><input type="text" id="customerSearch" placeholder="Search customers..." class="bulk-sms-input" onkeyup="filterCustomers()"></div>
            <?php if (empty($customers)): ?>
                <div class="text-center py-8 text-slate-500"><i class="fas fa-users text-3xl mb-2 opacity-50"></i><p>No customers with phone numbers</p></div>
            <?php else: ?>
                <div class="customer-list border border-slate-700 rounded-lg bg-slate-900/50 overflow-x-auto">
                    <table class="w-full text-sm"><thead class="bg-slate-800/60 sticky top-0"><tr><th class="px-3 py-2 w-8"><input type="checkbox" id="selectAllCheck" onchange="toggleSelectAll(this)"></th><th class="px-3 py-2 text-left">Customer</th><th class="px-3 py-2 text-left">Phone</th><th class="px-3 py-2 text-right">Points</th></tr></thead><tbody class="divide-y divide-slate-700"><?php foreach ($customers as $c): ?><tr class="customer-row hover:bg-slate-700/30" data-name="<?php echo strtolower(htmlspecialchars($c['name'])); ?>"><td class="px-3 py-2"><input type="checkbox" class="customer-checkbox" value="<?php echo $c['id']; ?>"></td><td class="px-3 py-2 text-white"><?php echo htmlspecialchars($c['name']); ?></td><td class="px-3 py-2 text-slate-400"><?php echo htmlspecialchars($c['phone']); ?></td><td class="px-3 py-2 text-right text-amber-400"><?php echo number_format($c['loyalty_points']); ?></td></tr><?php endforeach; ?></tbody></table>
                </div>
                <p class="text-xs text-slate-500 mt-2">Showing <span id="visibleCount"><?php echo count($customers); ?></span> customers</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Templates Tab -->
<div id="templatesTab" class="tab-content hidden">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-4">
        <div class="flex justify-between items-center mb-4"><h3 class="text-lg font-semibold text-white"><i class="fas fa-file-alt text-amber-400 mr-2"></i>SMS Templates</h3><button onclick="openTemplateModal()" class="px-3 py-1.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm hover:bg-amber-500/25"><i class="fas fa-plus mr-1"></i>New Template</button></div>
        <?php if (empty($templates)): ?><div class="text-center py-8 text-slate-500"><i class="fas fa-file-alt text-3xl mb-2 opacity-50"></i><p>No templates yet</p></div>
        <?php else: ?><div class="grid grid-cols-1 md:grid-cols-2 gap-3"><?php foreach ($templates as $t): ?><div class="border border-slate-700 rounded-lg p-3 hover:border-amber-500/30"><div class="flex justify-between"><h4 class="font-medium text-white"><?php echo htmlspecialchars($t['name']); ?></h4><span class="text-xs px-2 py-0.5 rounded bg-amber-500/10 text-amber-400"><?php echo htmlspecialchars($t['category']); ?></span></div><p class="text-xs text-slate-400 mt-1"><?php echo htmlspecialchars(substr($t['content'], 0, 80)) . (strlen($t['content']) > 80 ? '...' : ''); ?></p><div class="flex gap-2 mt-2"><button onclick="useTemplate(<?php echo $t['id']; ?>, '<?php echo addslashes($t['content']); ?>')" class="text-xs text-amber-400 hover:text-amber-300"><i class="fas fa-paste mr-1"></i>Use</button><button onclick="deleteTemplate(<?php echo $t['id']; ?>)" class="text-xs text-red-400 hover:text-red-300"><i class="fas fa-trash mr-1"></i>Delete</button></div></div><?php endforeach; ?></div><?php endif; ?>
    </div>
</div>

<!-- Campaigns Tab -->
<div id="campaignsTab" class="tab-content hidden">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-4">
        <h3 class="text-lg font-semibold text-white mb-4"><i class="fas fa-bullhorn text-amber-400 mr-2"></i>SMS Campaigns</h3>
        <div id="campaignsList"><div class="text-center py-8"><i class="fas fa-spinner fa-spin text-xl"></i><p class="mt-2 text-slate-500">Loading campaigns...</p></div></div>
    </div>
</div>

<!-- History Tab -->
<div id="historyTab" class="tab-content hidden">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-4">
        <h3 class="text-lg font-semibold text-white mb-4"><i class="fas fa-history text-amber-400 mr-2"></i>SMS History</h3>
        <div id="historyList"><div class="text-center py-8"><i class="fas fa-spinner fa-spin text-xl"></i><p class="mt-2 text-slate-500">Loading history...</p></div></div>
    </div>
</div>

<!-- Analytics Tab -->
<div id="analyticsTab" class="tab-content hidden">
    <div class="space-y-4">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1">From Date</label><input type="date" id="analyticsFrom" class="bulk-sms-input" value="<?php echo date('Y-m-01'); ?>"></div>
                <div><label class="block text-xs text-slate-400 mb-1">To Date</label><input type="date" id="analyticsTo" class="bulk-sms-input" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="flex items-end"><button onclick="loadAnalytics()" class="px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400"><i class="fas fa-chart-bar mr-2"></i>Load</button></div>
                <div class="flex items-end justify-end"><button onclick="exportAnalytics()" class="px-4 py-2 bg-emerald-500/15 border border-emerald-500/30 rounded-lg text-emerald-400"><i class="fas fa-download mr-2"></i>Export CSV</button></div>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
                <p class="text-xs text-slate-500">Total Sent</p>
                <p class="text-xl font-bold text-white" id="totalSentAnalytics">--</p>
            </div>
            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
                <p class="text-xs text-slate-500">Failed</p>
                <p class="text-xl font-bold text-red-400" id="totalFailedAnalytics">--</p>
            </div>
            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
                <p class="text-xs text-slate-500">Success Rate</p>
                <p class="text-xl font-bold text-amber-400" id="successRate">--</p>
            </div>
        </div>
        <div id="analyticsTable" class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-4"><div class="text-center py-8 text-slate-500">Select date range and click Load</div></div>
    </div>
</div>

<!-- Credits Tab -->
<div id="creditsTab" class="tab-content hidden">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-4 text-center">
            <i class="fas fa-coins text-5xl text-amber-400 mb-3"></i>
            <h3 class="text-xl font-bold text-white">Current Balance</h3>
            <p class="text-4xl font-bold text-amber-400 mt-2" id="creditBalance">--</p>
            <p class="text-xs text-slate-500 mt-2">1 credit = 1 SMS (160 characters)</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-lg font-semibold text-white mb-4"><i class="fas fa-plus-circle text-amber-400 mr-2"></i>Add Credits</h3>
            <div class="space-y-3">
                <input type="number" id="creditAmount" placeholder="Amount (1 credit = 1 SMS)" class="bulk-sms-input" min="1">
                <button onclick="addCredits()" class="w-full py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 font-semibold hover:bg-amber-500/25"><i class="fas fa-shopping-cart mr-2"></i>Purchase Credits</button>
            </div>
        </div>
    </div>
</div>

<!-- Template Modal -->
<div id="templateModal" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-50">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 max-w-md w-full mx-4">
        <div class="flex justify-between mb-4"><h3 class="text-lg font-semibold text-white">New Template</h3><button onclick="closeTemplateModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button></div>
        <form id="templateForm" class="space-y-4">
            <input type="hidden" name="action" value="save_template">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div><input type="text" name="name" id="templateName" placeholder="Template Name" class="bulk-sms-input" required></div>
            <div><select name="category" id="templateCategory" class="bulk-sms-input"><option value="general">General</option><option value="promotional">Promotional</option><option value="reminder">Reminder</option><option value="notification">Notification</option></select></div>
            <div><textarea name="content" id="templateContent" rows="3" placeholder="Message content..." class="bulk-sms-input" required></textarea></div>
            <button type="submit" class="w-full py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 font-semibold hover:bg-amber-500/25">Save Template</button>
        </form>
    </div>
</div>

<script>
const csrf = '<?php echo $csrf_token; ?>';
let currentTab = 'compose';

document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    updateCharCount();
    document.getElementById('smsMessage').addEventListener('input', updateCharCount);
    document.querySelectorAll('.nav-tab').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });
    loadCampaigns();
    loadHistory();
});

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.getElementById(tab + 'Tab').classList.remove('hidden');
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.nav-tab[data-tab="${tab}"]`).classList.add('active');
    if (tab === 'campaigns') loadCampaigns();
    if (tab === 'history') loadHistory();
    if (tab === 'analytics') loadAnalytics();
    if (tab === 'credits') loadCredits();
}

async function loadStats() {
    try {
        const fd = new FormData(); fd.append('action', 'get_stats'); fd.append('csrf_token', csrf);
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('customersWithPhone').textContent = data.customers_with_phone?.toLocaleString() || '0';
            document.getElementById('smsSentToday').textContent = data.sms_sent_today?.toLocaleString() || '0';
            document.getElementById('totalSent').textContent = data.total_sent?.toLocaleString() || '0';
            document.getElementById('smsBalance').textContent = data.sms_balance?.toLocaleString() || '0';
        }
    } catch(e) { console.error(e); }
}

async function loadCampaigns() {
    try {
        const fd = new FormData(); fd.append('action', 'get_campaigns'); fd.append('csrf_token', csrf);
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        const container = document.getElementById('campaignsList');
        if (data.success && data.campaigns?.length) {
            let html = '<div class="overflow-x-auto"><table class="w-full"><thead class="bg-slate-800/60"><tr><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-right">Recipients</th><th class="px-3 py-2 text-right">Sent</th><th class="px-3 py-2 text-right">Failed</th><th class="px-3 py-2 text-left">Schedule</th></tr></thead><tbody>';
            data.campaigns.forEach(c => {
                const statusClass = c.status === 'completed' ? 'text-emerald-400' : c.status === 'scheduled' ? 'text-amber-400' : 'text-slate-400';
                html += `<tr class="border-t border-slate-700"><td class="px-3 py-2 text-white">${c.name}</td><td class="px-3 py-2"><span class="${statusClass}">${c.status}</span></td><td class="px-3 py-2 text-right">${c.total_recipients}</td><td class="px-3 py-2 text-right text-emerald-400">${c.sent_count}</td><td class="px-3 py-2 text-right text-red-400">${c.failed_count}</td><td class="px-3 py-2">${c.scheduled_at ? new Date(c.scheduled_at).toLocaleString() : 'Immediate'}</td></tr>`;
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        } else { container.innerHTML = '<div class="text-center py-8 text-slate-500">No campaigns found</div>'; }
    } catch(e) { document.getElementById('campaignsList').innerHTML = '<div class="text-center py-8 text-red-500">Error loading</div>'; }
}

async function loadHistory() {
    try {
        const fd = new FormData(); fd.append('action', 'get_history'); fd.append('csrf_token', csrf);
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        const container = document.getElementById('historyList');
        if (data.success && data.logs?.length) {
            let html = '<div class="overflow-x-auto"><table class="w-full"><thead class="bg-slate-800/60"><tr><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Phone</th><th class="px-3 py-2 text-left">Message</th><th class="px-3 py-2 text-left">Status</th></tr></thead><tbody>';
            data.logs.forEach(l => {
                html += `<tr class="border-t border-slate-700"><td class="px-3 py-2 text-slate-400 text-xs">${l.sent_at ? new Date(l.sent_at).toLocaleString() : '-'}</td><td class="px-3 py-2">${l.phone}</td><td class="px-3 py-2 text-slate-400 text-xs">${l.message.substring(0, 50)}${l.message.length > 50 ? '...' : ''}</td><td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs ${l.status === 'sent' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'}">${l.status}</span></td></tr>`;
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        } else { container.innerHTML = '<div class="text-center py-8 text-slate-500">No history found</div>'; }
    } catch(e) { document.getElementById('historyList').innerHTML = '<div class="text-center py-8 text-red-500">Error loading</div>'; }
}

async function loadAnalytics() {
    const from = document.getElementById('analyticsFrom').value;
    const to = document.getElementById('analyticsTo').value;
    try {
        const fd = new FormData(); fd.append('action', 'get_analytics'); fd.append('date_from', from); fd.append('date_to', to); fd.append('csrf_token', csrf);
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('totalSentAnalytics').textContent = data.total_sent?.toLocaleString() || '0';
            document.getElementById('totalFailedAnalytics').textContent = data.total_failed?.toLocaleString() || '0';
            const rate = data.total_sent + data.total_failed > 0 ? ((data.total_sent / (data.total_sent + data.total_failed)) * 100).toFixed(1) : 0;
            document.getElementById('successRate').textContent = rate + '%';
            if (data.daily_breakdown?.length) {
                let html = '<div class="overflow-x-auto"><table class="w-full"><thead class="bg-slate-800/60"><tr><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-right">Sent</th><th class="px-3 py-2 text-right">Failed</th><th class="px-3 py-2 text-right">Rate</th></tr></thead><tbody>';
                data.daily_breakdown.forEach(d => {
                    const dayRate = d.total > 0 ? ((d.sent / d.total) * 100).toFixed(1) : 0;
                    html += `<tr class="border-t border-slate-700"><td class="px-3 py-2">${d.date}</td><td class="px-3 py-2 text-right">${d.total}</td><td class="px-3 py-2 text-right text-emerald-400">${d.sent}</td><td class="px-3 py-2 text-right text-red-400">${d.failed}</td><td class="px-3 py-2 text-right text-amber-400">${dayRate}%</td></tr>`;
                });
                html += '</tbody></table></div>';
                document.getElementById('analyticsTable').innerHTML = html;
            } else { document.getElementById('analyticsTable').innerHTML = '<div class="text-center py-8 text-slate-500">No data for selected period</div>'; }
        }
    } catch(e) { document.getElementById('analyticsTable').innerHTML = '<div class="text-center py-8 text-red-500">Error loading</div>'; }
}

async function loadCredits() {
    const balance = document.getElementById('smsBalance').textContent;
    document.getElementById('creditBalance').textContent = balance;
}

async function addCredits() {
    const amount = document.getElementById('creditAmount').value;
    if (!amount || amount < 1) { alert('Enter valid amount'); return; }
    try {
        const fd = new FormData(); fd.append('action', 'add_credits'); fd.append('amount', amount); fd.append('csrf_token', csrf);
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) { alert(data.message); loadStats(); loadCredits(); }
        else { alert(data.message); }
    } catch(e) { alert('Error adding credits'); }
}

function updateCharCount() {
    const msg = document.getElementById('smsMessage').value;
    const len = msg.length;
    const span = document.getElementById('charCount');
    span.textContent = `${len} / 160 characters`;
    span.classList.remove('warning', 'error');
    if (len > 160) span.classList.add('error');
    else if (len > 144) span.classList.add('warning');
}

function toggleSendType() {
    const type = document.querySelector('input[name="send_type"]:checked').value;
    document.getElementById('groupSelect')?.classList.add('hidden');
    document.getElementById('tierSelect')?.classList.add('hidden');
    if (type === 'group') document.getElementById('groupSelect')?.classList.remove('hidden');
    else if (type === 'tier') document.getElementById('tierSelect')?.classList.remove('hidden');
}

function loadTemplateContent() {
    const sel = document.getElementById('templateSelect');
    const opt = sel.options[sel.selectedIndex];
    if (opt.value && opt.dataset.content) {
        document.getElementById('smsMessage').value = opt.dataset.content;
        updateCharCount();
    }
}

function useTemplate(id, content) {
    document.getElementById('smsMessage').value = content;
    updateCharCount();
    switchTab('compose');
}

function filterCustomers() {
    const term = document.getElementById('customerSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.customer-row');
    let visible = 0;
    rows.forEach(row => {
        const name = row.dataset.name || '';
        if (name.includes(term)) { row.style.display = ''; visible++; }
        else row.style.display = 'none';
    });
    document.getElementById('visibleCount').textContent = visible;
}

function selectAll() { document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = true); document.getElementById('selectAllCheck').checked = true; }
function deselectAll() { document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = false); document.getElementById('selectAllCheck').checked = false; }
function toggleSelectAll(cb) { document.querySelectorAll('.customer-checkbox').forEach(c => c.checked = cb.checked); }

async function deleteTemplate(id) {
    if (!confirm('Delete this template?')) return;
    const fd = new FormData(); fd.append('action', 'delete_template'); fd.append('id', id); fd.append('csrf_token', csrf);
    const res = await fetch(window.location.href, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.message);
}

function openTemplateModal() { document.getElementById('templateModal').style.display = 'flex'; }
function closeTemplateModal() { document.getElementById('templateModal').style.display = 'none'; }

document.getElementById('smsForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('smsMessage').value.trim();
    if (!msg) { alert('Enter message'); return; }
    if (msg.length > 160) { alert('Message exceeds 160 characters'); return; }
    
    const type = document.querySelector('input[name="send_type"]:checked').value;
    let customerIds = [];
    if (type === 'selected') {
        customerIds = Array.from(document.querySelectorAll('.customer-checkbox:checked')).map(cb => cb.value);
        if (customerIds.length === 0) { alert('Select at least one customer'); return; }
    }
    
    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
    
    const fd = new FormData();
    fd.append('action', 'send_sms');
    fd.append('csrf_token', csrf);
    fd.append('message', msg);
    fd.append('send_type', type);
    fd.append('customer_ids', JSON.stringify(customerIds));
    fd.append('group_id', document.getElementById('sendGroup')?.value || '');
    fd.append('tier', document.getElementById('sendTier')?.value || '');
    fd.append('schedule_time', document.getElementById('scheduleTime')?.value || '');
    fd.append('campaign_name', document.getElementById('campaignName')?.value || '');
    fd.append('template_id', document.getElementById('templateSelect')?.value || '');
    
    try {
        const res = await fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        const data = await res.json();
        const resultDiv = document.getElementById('sendResult');
        resultDiv.classList.remove('hidden');
        if (data.success) {
            resultDiv.className = 'p-3 rounded-lg bg-emerald-500/10 text-emerald-400';
            resultDiv.innerHTML = data.message;
            if (data.sent) { setTimeout(() => { document.getElementById('smsMessage').value = ''; deselectAll(); updateCharCount(); loadStats(); }, 2000); }
        } else {
            resultDiv.className = 'p-3 rounded-lg bg-red-500/10 text-red-400';
            resultDiv.innerHTML = data.message;
        }
        setTimeout(() => resultDiv.classList.add('hidden'), 5000);
    } catch(e) { alert('Error: ' + e.message); }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send SMS';
});

document.getElementById('templateForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(document.getElementById('templateForm'));
    fd.append('csrf_token', csrf);
    const res = await fetch(window.location.href, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) { alert('Template saved'); location.reload(); }
    else alert(data.message);
});

function exportAnalytics() {
    const from = document.getElementById('analyticsFrom').value;
    const to = document.getElementById('analyticsTo').value;
    window.location.href = `../ajax/export_sms_csv.php?tenant_id=<?php echo $tenant_id; ?>&date_from=${from}&date_to=${to}`;
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>