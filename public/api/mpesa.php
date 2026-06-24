<?php
/**
 * M-Pesa API Endpoints
 * - POST /api/mpesa/stk-push - Initiate STK Push
 * - POST /api/mpesa/check-status - Check payment status
 * - POST /api/webhooks/mpesa/stk-callback - STK callback handler
 * - POST /api/webhooks/mpesa/c2b/confirm - C2B confirmation
 * - POST /api/webhooks/mpesa/c2b/validate - C2B validation
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 0);

session_name('jakababa_saas_sid');

$cookieParams = [
    'lifetime' => 0,
    'path' => '/JDH_POS/',
    'httponly' => true,
    'samesite' => 'Lax'
];
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $cookieParams['secure'] = true;
}
session_set_cookie_params($cookieParams);

session_start();

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
$csrf_token = generate_csrf_token();


header('Content-Type: application/json');

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/Modules/Payments/PaymentService.php';
require_once __DIR__ . '/../../src/Modules/Payments/Gateways/MpesaGateway.php';

use JDH\Modules\Payments\PaymentService;

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';

// Check authentication for non-webhook endpoints
if (!str_starts_with($path, 'webhook')) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

try {
    $companyId = $_SESSION['tenant_id'] ?? null;
    $branchId = $_SESSION['branch_id'] ?? ($_GET['branch_id'] ?? null);
    $userId = $_SESSION['user_id'] ?? null;
    
    $paymentService = new PaymentService($db, (int) $companyId, (int) $branchId);
    
    switch ($path) {
        case 'stk-push':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);

// CSRF verification
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $csrf_token) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}
            
            // Validate required fields
            if (empty($input['sale_id']) || empty($input['phone']) || empty($input['amount'])) {
                throw new Exception('Missing required fields: sale_id, phone, amount', 400);
            }
            
            // Validate phone number format
            $phone = preg_replace('/[^0-9]/', '', $input['phone']);
            if (strlen($phone) < 9 || strlen($phone) > 12) {
                throw new Exception('Invalid phone number', 400);
            }
            
            // Validate amount
            $amount = (float) $input['amount'];
            if ($amount <= 0 || $amount > 150000) {
                throw new Exception('Invalid amount. Must be between 1 and 150,000 KES', 400);
            }
            
            $result = $paymentService->processMpesaStkPush(
                (int) $input['sale_id'],
                $phone,
                $amount,
                $input['account_reference'] ?? null
            );
            
            if ($result['success']) {
                http_response_code(200);
            } else {
                http_response_code(400);
            }
            
            echo json_encode($result);
            break;
            
        case 'check-status':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['checkout_request_id'])) {
                throw new Exception('Missing checkout_request_id', 400);
            }
            
            $result = $paymentService->checkStkStatus($input['checkout_request_id']);
            
            echo json_encode($result);
            break;
            
        case 'webhook/stk-callback':
            if ($method !== 'POST') {
                http_response_code(405);
                exit;
            }
            
            // Log raw input for debugging
            $rawInput = file_get_contents('php://input');
            error_log('M-Pesa STK Callback: ' . $rawInput);
            
            $data = json_decode($rawInput, true);
            
            if (!$data || !isset($data['Body']['stkCallback'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid callback data']);
                exit;
            }
            
            $callback = $data['Body']['stkCallback'];
            $checkoutRequestId = $callback['CheckoutRequestID'] ?? '';
            $resultCode = $callback['ResultCode'] ?? null;
            $resultDesc = $callback['ResultDesc'] ?? '';
            
            // Find company from checkout request ID
            $stmt = $db->prepare("
                SELECT tenant_id FROM payment_transactions 
                WHERE gateway_reference = ? AND status = 'pending'
                LIMIT 1
            ");
            $stmt->execute([$checkoutRequestId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                error_log("No pending transaction found for checkout: {$checkoutRequestId}");
                echo json_encode(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
                exit;
            }
            
            $companyId = $transaction['tenant_id'];
            
            // Re-initialize service with found company
            $paymentService = new PaymentService($db, (int) $companyId, 0);
            
            if ($resultCode === '0' && isset($callback['CallbackMetadata']['Item'])) {
                // Payment successful
                $metadata = [];
                foreach ($callback['CallbackMetadata']['Item'] as $item) {
                    $metadata[$item['Name']] = $item['Value'] ?? null;
                }
                
                $mpesaReceipt = $metadata['MpesaReceiptNumber'] ?? '';
                $transactionDate = $metadata['TransactionDate'] ?? '';
                $phone = $metadata['PhoneNumber'] ?? '';
                $amount = $metadata['Amount'] ?? 0;
                
                // Update transaction
                $stmt = $db->prepare("
                    UPDATE payment_transactions 
                    SET status = 'completed', 
                        gateway_reference = ?,
                        response_payload = ?,
                        updated_at = NOW()
                    WHERE gateway_reference = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $mpesaReceipt,
                    json_encode($callback),
                    $checkoutRequestId,
                    $companyId
                ]);
                
                // Get sale_id and update sale
                $stmt = $db->prepare("
                    SELECT sale_id, amount FROM payment_transactions 
                    WHERE gateway_reference = ? AND tenant_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$checkoutRequestId, $companyId]);
                $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($transaction) {
                    // Record payment
                    $stmt = $db->prepare("
                        INSERT INTO payments (tenant_id, branch_id, sale_id, method, amount, 
                                            status, transaction_reference, paid_at, created_at)
                        VALUES (?, ?, ?, 'mpesa', ?, 'paid', ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $companyId,
                        0, // Branch not known from callback
                        $transaction['sale_id'],
                        $transaction['amount'],
                        $mpesaReceipt
                    ]);
                    
                    // Update sale payment status
                    updateSalePaymentStatus($db, (int) $transaction['sale_id'], $companyId);
                }
                
                error_log("M-Pesa payment completed: {$mpesaReceipt} for checkout: {$checkoutRequestId}");
                
            } else {
                // Payment failed or cancelled
                $stmt = $db->prepare("
                    UPDATE payment_transactions 
                    SET status = 'failed', 
                        error_message = ?,
                        response_payload = ?,
                        updated_at = NOW()
                    WHERE gateway_reference = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $resultDesc,
                    json_encode($callback),
                    $checkoutRequestId,
                    $companyId
                ]);
                
                error_log("M-Pesa payment failed: {$resultDesc} for checkout: {$checkoutRequestId}");
            }
            
            // Always return success to M-Pesa
            echo json_encode(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
            break;
            
        case 'webhook/c2b/validate':
            // C2B validation - accept all for now
            header('Content-Type: application/json');
            echo json_encode([
                'ResultCode' => '0',
                'ResultDesc' => 'Accepted'
            ]);
            break;
            
        case 'webhook/c2b/confirm':
            if ($method !== 'POST') {
                http_response_code(405);
                exit;
            }
            
            $rawInput = file_get_contents('php://input');
            error_log('M-Pesa C2B Confirm: ' . $rawInput);
            
            $data = json_decode($rawInput, true);
            
            if (!$data) {
                http_response_code(400);
                exit;
            }
            
            // Find company by shortcode
            $shortcode = $data['BusinessShortCode'] ?? '';
            $stmt = $db->prepare("
                SELECT pg.tenant_id, pg.config 
                FROM payment_gateways pg
                WHERE pg.gateway_code = 'mpesa' 
                AND JSON_EXTRACT(pg.config, '$.shortcode') = ?
                LIMIT 1
            ");
            $stmt->execute([$shortcode]);
            $gateway = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$gateway) {
                error_log("No company found for shortcode: {$shortcode}");
                http_response_code(400);
                exit;
            }
            
            $companyId = $gateway['tenant_id'];
            $paymentService = new PaymentService($db, (int) $companyId, 0);
            
            $result = $paymentService->processC2BConfirmation($data);
            
            // Return success to M-Pesa
            header('Content-Type: application/json');
            echo json_encode([
                'ResultCode' => '0',
                'ResultDesc' => 'Accepted'
            ]);
            break;
            
        case 'config':
            if ($method === 'GET') {
                // Get current config
                $config = $paymentService->getGatewayConfig('mpesa');
                
                if (!$config) {
                    http_response_code(404);
                    echo json_encode(['error' => 'M-Pesa not configured']);
                    exit;
                }
                
                // Don't return sensitive data
                $safeConfig = [
                    'gateway_name' => $config['gateway_name'],
                    'is_sandbox' => $config['is_sandbox'],
                    'shortcode' => $config['config']['shortcode'] ?? null
                ];
                
                echo json_encode(['config' => $safeConfig]);
                
            } elseif ($method === 'POST') {
                // Save/update config - only owner can do this
                if (empty($_SESSION['is_owner']) && empty($_SESSION['is_admin'])) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Permission denied']);
                    exit;
                }
                
                $input = json_decode(file_get_contents('php://input'), true);
                
                $required = ['consumer_key', 'consumer_secret', 'shortcode', 'passkey'];
                foreach ($required as $field) {
                    if (empty($input[$field])) {
                        throw new Exception("Missing required field: {$field}", 400);
                    }
                }
                
                $config = [
                    'consumer_key' => $input['consumer_key'],
                    'consumer_secret' => $input['consumer_secret'],
                    'shortcode' => $input['shortcode'],
                    'passkey' => $input['passkey'],
                    'initiator_name' => $input['initiator_name'] ?? 'testapi',
                    'security_credential' => $input['security_credential'] ?? '',
                    'callback_url' => $input['callback_url'] ?? null
                ];
                
                // Check if gateway already exists
                $stmt = $db->prepare("
                    SELECT id FROM payment_gateways 
                    WHERE tenant_id = ? AND gateway_code = 'mpesa'
                ");
                $stmt->execute([$companyId]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $stmt = $db->prepare("
                        UPDATE payment_gateways 
                        SET config = ?, test_mode = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        json_encode($config),
                        $input['test_mode'] ?? true ? 1 : 0,
                        $existing['id']
                    ]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO payment_gateways 
                        (tenant_id, gateway_code, gateway_name, config, test_mode, is_active, created_at)
                        VALUES (?, 'mpesa', 'M-Pesa', ?, ?, 1, NOW())
                    ");
                    $stmt->execute([
                        $companyId,
                        json_encode($config),
                        $input['test_mode'] ?? true ? 1 : 0
                    ]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Configuration saved']);
            }
            break;
            
        case 'register-c2b':
            // Register C2B URLs
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            if (empty($_SESSION['is_owner']) && empty($_SESSION['is_admin'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied']);
                exit;
            }
            
            $gateway = $paymentService->getMpesaGateway();
            if (!$gateway) {
                http_response_code(400);
                echo json_encode(['error' => 'M-Pesa not configured']);
                exit;
            }
            
            $result = $gateway->registerC2BUrls();
            echo json_encode($result);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
    
} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Update sale payment status
 */
function updateSalePaymentStatus(PDO $db, int $saleId, int $companyId): void
{
    $stmt = $db->prepare("
        SELECT 
            s.total,
            COALESCE(SUM(p.amount), 0) as paid_amount
        FROM sales s
        LEFT JOIN payments p ON s.id = p.sale_id AND p.status = 'paid'
        WHERE s.id = ? AND s.tenant_id = ?
        GROUP BY s.id
    ");
    $stmt->execute([$saleId, $companyId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return;
    }
    
    $total = (float) $result['total'];
    $paid = (float) $result['paid_amount'];
    
    if ($paid >= $total) {
        $paymentStatus = 'paid';
        $saleStatus = 'completed';
    } elseif ($paid > 0) {
        $paymentStatus = 'partial';
        $saleStatus = 'pending';
    } else {
        $paymentStatus = 'unpaid';
        $saleStatus = 'pending';
    }
    
    $stmt = $db->prepare("
        UPDATE sales 
        SET payment_status = ?, status = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$paymentStatus, $saleStatus, $saleId, $companyId]);
}
