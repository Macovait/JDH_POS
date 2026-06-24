<?php
/**
 * AJAX endpoint to send bulk SMS
 * Accepts POST: message, type (all/selected), customer_ids (array)
 * Returns JSON: { status: true/false, sent: n, failed: n, message: "" }
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src');
safe_require('functions.php', 'src');

require_login();

if (!check_permission('sms.send') && !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Permission denied']);
    exit;
}

header('Content-Type: application/json');

$tenant_id = get_current_tenant_id();
$user_id = get_current_user_id();
$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid request method']);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    echo json_encode(['status' => false, 'message' => 'Invalid security token']);
    exit;
}

$message = trim($_POST['message'] ?? '');
$type = $_POST['type'] ?? 'selected';
$customer_ids = $_POST['customer_ids'] ?? [];

if (empty($message)) {
    echo json_encode(['status' => false, 'message' => 'Message is required']);
    exit;
}

if (strlen($message) > 160) {
    echo json_encode(['status' => false, 'message' => 'Message exceeds 160 characters']);
    exit;
}

$recipients = [];

if ($type === 'all') {
    try {
        $stmt = $pdo->prepare('
            SELECT id, name, phone FROM customers 
            WHERE tenant_id = ? AND phone IS NOT NULL AND phone != "" AND status = 1
        ');
        $stmt->execute([$tenant_id]);
        $customers = $stmt->fetchAll();
        
        foreach ($customers as $c) {
            $recipients[$c['id']] = ['name' => $c['name'], 'phone' => $c['phone']];
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
} elseif ($type === 'selected') {
    if (empty($customer_ids)) {
        echo json_encode(['status' => false, 'message' => 'No customers selected']);
        exit;
    }
    
    $placeholders = implode(',', array_fill(0, count($customer_ids), '?'));
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, phone FROM customers 
            WHERE id IN ($placeholders) AND tenant_id = ? AND phone IS NOT NULL AND phone != ''
        ");
        $stmt->execute(array_merge($customer_ids, [$tenant_id]));
        $customers = $stmt->fetchAll();
        
        foreach ($customers as $c) {
            $recipients[$c['id']] = ['name' => $c['name'], 'phone' => $c['phone']];
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid recipient type']);
    exit;
}

if (empty($recipients)) {
    echo json_encode(['status' => false, 'message' => 'No valid recipients with phone numbers found']);
    exit;
}

require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'sms.php';

$sms = new SMSService($tenant_id);

if (!$sms->isEnabled()) {
    echo json_encode(['status' => false, 'message' => 'SMS is not enabled. Please configure SMS settings.']);
    exit;
}

$sent_count = 0;
$failed_count = 0;
$errors = [];

foreach ($recipients as $customer_id => $customer) {
    $phone = $customer['phone'];
    $name = $customer['name'];
    
    $result = $sms->send($phone, $message);
    
    $status = $result['success'] ? 'sent' : 'failed';
    $error_message = $result['success'] ? null : ($result['message'] ?? 'Unknown error');
    
    $sms->logSMS($phone, $message, $status, $error_message, $customer_id);
    
    if ($result['success']) {
        $sent_count++;
    } else {
        $failed_count++;
        $errors[] = ['phone' => $phone, 'name' => $name, 'error' => $error_message];
    }
    
    usleep(100000);
}

try {
    log_activity($user_id, 'sms.bulk_sent', [
        'sent_count' => $sent_count, 'failed_count' => $failed_count,
        'tenant_id' => $tenant_id
    ], get_current_tenant_id());
} catch (Exception $e) {
    error_log("Failed to log activity: " . $e->getMessage());
}

if ($sent_count > 0) {
    $response = [
        'status' => true,
        'sent' => $sent_count,
        'failed' => $failed_count,
        'message' => "SMS sent successfully to $sent_count recipient(s)"
    ];
    
    if ($failed_count > 0) {
        $response['errors'] = array_slice($errors, 0, 5);
    }
    
    echo json_encode($response);
} else {
    echo json_encode([
        'status' => false,
        'sent' => 0,
        'failed' => $failed_count,
        'message' => 'Failed to send SMS to all recipients'
    ]);
}