<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * SMS Webhook Handler
 * Receives delivery confirmations from SMS providers (Africa's Talking, Twilio, etc.)
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

header('Content-Type: application/json');

try {
    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?? $_POST;
    
    // Validate required fields
    $phone = $data['phone'] ?? $data['to'] ?? $data['phoneNumber'] ?? '';
    $status = strtolower($data['status'] ?? $data['delivery_status'] ?? 'unknown');
    $message_id = $data['id'] ?? $data['message_id'] ?? $data['messageId'] ?? '';
    $timestamp = $data['timestamp'] ?? $data['sentTime'] ?? date('Y-m-d H:i:s');
    
    if (empty($phone) || empty($status) || empty($message_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: phone, status, message_id']);
        exit;
    }
    
    $pdo = get_db_connection();
    
    // Normalize status to match our database enums
    $status_map = [
        'success' => 'delivered',
        'delivered' => 'delivered',
        'sent' => 'sent',
        'pending' => 'queued',
        'failed' => 'failed',
        'rejected' => 'failed',
        'undelivered' => 'failed',
        'bounced' => 'failed',
    ];
    
    $mapped_status = $status_map[$status] ?? $status;
    if (!in_array($mapped_status, ['sent', 'delivered', 'pending', 'queued', 'failed', 'cancelled'])) {
        $mapped_status = 'sent';
    }
    
    // Update SMS log
    $stmt = $pdo->prepare("
        UPDATE sms_logs 
        SET status = ?, 
            delivered_at = IF(? = 'delivered', NOW(), delivered_at)
        WHERE message_id = ? OR phone = ?
        LIMIT 1
    ");
    
    if (!$stmt->execute([$mapped_status, $mapped_status, $message_id, $phone])) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update SMS log']);
        exit;
    }
    
    // Log webhook activity
    error_log("SMS Webhook: Phone=$phone, Status=$mapped_status, MessageID=$message_id");
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Webhook processed',
        'status' => $mapped_status,
        'phone' => $phone
    ]);
    
} catch (Exception $e) {
    error_log("SMS Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
