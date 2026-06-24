<?php
/**
 * Payment Service - Orchestrates all payment operations
 */

namespace JDH\Modules\Payments;

use JDH\Modules\Payments\Gateways\MpesaGateway;
use PDO;
use Exception;

class PaymentService 
{
    private $db;
    private $companyId;
    private $branchId;
    
    public function __construct(PDO $db, int $companyId, int $branchId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->branchId = $branchId;
    }
    
    /**
     * Get gateway configuration for a company
     */
    public function getGatewayConfig(string $gatewayCode): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM payment_gateways 
            WHERE tenant_id = ? AND gateway_code = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$this->companyId, $gatewayCode]);
        $gateway = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$gateway) {
            return null;
        }
        
        return [
            'id' => $gateway['id'],
            'gateway_code' => $gateway['gateway_code'],
            'gateway_name' => $gateway['gateway_name'],
            'is_sandbox' => (bool) $gateway['test_mode'],
            'config' => json_decode($gateway['config'], true) ?? []
        ];
    }
    
    /**
     * Initialize M-Pesa gateway
     */
    public function getMpesaGateway(): ?MpesaGateway
    {
        $gatewayData = $this->getGatewayConfig('mpesa');
        
        if (!$gatewayData) {
            return null;
        }
        
        $config = array_merge($gatewayData['config'], [
            'test_mode' => $gatewayData['is_sandbox']
        ]);
        
        return new MpesaGateway($config, $this->db);
    }
    
    /**
     * Process M-Pesa STK Push payment
     */
    public function processMpesaStkPush(int $saleId, string $phone, float $amount, ?string $accountRef = null): array
    {
        $gateway = $this->getMpesaGateway();
        
        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'M-Pesa not configured for this tenant'
            ];
        }
        
        // Get sale details for account reference
        $stmt = $this->db->prepare("
            SELECT sale_number, total, customer_id 
            FROM sales 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$saleId, $this->companyId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            return [
                'success' => false,
                'error' => 'Sale not found'
            ];
        }
        
        $accountReference = $accountRef ?? $sale['sale_number'];
        
        // Initiate STK Push
        $result = $gateway->stkPush([
            'amount' => $amount,
            'phone' => $phone,
            'account_reference' => $accountReference,
            'description' => 'Payment for ' . $sale['sale_number']
        ]);
        
        if ($result['success']) {
            // Create pending transaction record
            $this->createTransaction([
                'sale_id' => $saleId,
                'gateway_id' => $this->getGatewayConfig('mpesa')['id'],
                'transaction_type' => 'sale',
                'amount' => $amount,
                'currency' => 'KES',
                'status' => 'pending',
                'gateway_reference' => $result['checkout_request_id'],
                'request_payload' => json_encode([
                    'phone' => $phone,
                    'checkout_request_id' => $result['checkout_request_id'],
                    'merchant_request_id' => $result['merchant_request_id']
                ])
            ]);
            
            // Store pending payment record
            $this->storePendingPayment($saleId, $result['checkout_request_id'], $phone, $amount);
        }
        
        return $result;
    }
    
    /**
     * Check and update STK Push status
     */
    public function checkStkStatus(string $checkoutRequestId): array
    {
        $gateway = $this->getMpesaGateway();
        
        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'M-Pesa not configured'
            ];
        }
        
        $result = $gateway->queryStkStatus($checkoutRequestId);
        
        if ($result['success'] && $result['mpesa_receipt']) {
            // Update transaction to completed
            $this->updateTransactionByGatewayRef($checkoutRequestId, [
                'status' => 'completed',
                'gateway_reference' => $result['mpesa_receipt'],
                'response_payload' => json_encode($result)
            ]);
            
            // Update sale payment status
            $this->updateSalePaymentStatus($checkoutRequestId, $result['mpesa_receipt']);
        } elseif (isset($result['result_code']) && $result['result_code'] !== '0') {
            // Payment failed or cancelled
            $this->updateTransactionByGatewayRef($checkoutRequestId, [
                'status' => 'failed',
                'error_message' => $result['result_desc'],
                'response_payload' => json_encode($result)
            ]);
        }
        
        return $result;
    }
    
    /**
     * Process C2B payment confirmation
     */
    public function processC2BConfirmation(array $mpesaData): array
    {
        $gateway = $this->getMpesaGateway();
        
        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'M-Pesa not configured'
            ];
        }
        
        $result = $gateway->processC2BCallback($mpesaData);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Find sale by account reference (BillRefNumber)
        $stmt = $this->db->prepare("
            SELECT id, total, payment_status 
            FROM sales 
            WHERE tenant_id = ? 
            AND (sale_number = ? OR invoice_number = ?)
            LIMIT 1
        ");
        $stmt->execute([
            $this->companyId, 
            $result['account_reference'],
            $result['account_reference']
        ]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            // No matching sale - store as unallocated payment
            $this->createUnallocatedPayment($result);
            return [
                'success' => true,
                'message' => 'Payment received but not allocated to any sale',
                'requires_allocation' => true
            ];
        }
        
        // Create completed transaction
        $gatewayConfig = $this->getGatewayConfig('mpesa');
        $this->createTransaction([
            'sale_id' => $sale['id'],
            'gateway_id' => $gatewayConfig['id'],
            'transaction_type' => 'sale',
            'amount' => $result['amount'],
            'currency' => 'KES',
            'status' => 'completed',
            'gateway_reference' => $result['transaction_id'],
            'request_payload' => json_encode($mpesaData),
            'response_payload' => json_encode(['ResultCode' => '0'])
        ]);
        
        // Update sale payment
        $this->recordSalePayment($sale['id'], 'mpesa', $result['amount'], $result['transaction_id']);
        
        return [
            'success' => true,
            'sale_id' => $sale['id'],
            'amount' => $result['amount'],
            'transaction_id' => $result['transaction_id']
        ];
    }
    
    /**
     * Record a payment for a sale
     */
    public function recordSalePayment(int $saleId, string $method, float $amount, ?string $reference = null): void
    {
        // Insert payment record
        $stmt = $this->db->prepare("
            INSERT INTO payments (tenant_id, branch_id, sale_id, method, amount, 
                                 status, transaction_reference, paid_at, created_at)
            VALUES (?, ?, ?, ?, ?, 'paid', ?, NOW(), NOW())
        ");
        $stmt->execute([
            $this->companyId,
            $this->branchId,
            $saleId,
            $method,
            $amount,
            $reference
        ]);
        
        // Update sale payment status
        $this->updateSalePaymentStatus($saleId);
    }
    
    /**
     * Update sale payment status based on total payments
     */
    private function updateSalePaymentStatus(int $saleId): void
    {
        $stmt = $this->db->prepare("
            SELECT 
                s.total,
                COALESCE(SUM(p.amount), 0) as paid_amount
            FROM sales s
            LEFT JOIN payments p ON s.id = p.sale_id AND p.status = 'paid'
            WHERE s.id = ? AND s.tenant_id = ?
            GROUP BY s.id
        ");
        $stmt->execute([$saleId, $this->companyId]);
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
        
        $stmt = $this->db->prepare("
            UPDATE sales 
            SET payment_status = ?, status = ?
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$paymentStatus, $saleStatus, $saleId, $this->companyId]);
    }
    
    /**
     * Create transaction record
     */
    private function createTransaction(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO payment_transactions 
            (tenant_id, branch_id, sale_id, gateway_id, transaction_type, amount, 
             currency, gateway_reference, status, request_payload, response_payload, 
             error_message, created_at)
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $this->companyId,
            $this->branchId,
            $data['sale_id'],
            $data['gateway_id'],
            $data['transaction_type'],
            $data['amount'],
            $data['currency'] ?? 'KES',
            $data['gateway_reference'] ?? null,
            $data['status'],
            $data['request_payload'] ?? null,
            $data['response_payload'] ?? null,
            $data['error_message'] ?? null
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Update transaction by gateway reference
     */
    private function updateTransactionByGatewayRef(string $gatewayRef, array $updates): void
    {
        $fields = [];
        $values = [];
        
        foreach ($updates as $field => $value) {
            $fields[] = "{$field} = ?";
            $values[] = $value;
        }
        
        $values[] = $gatewayRef;
        $values[] = $this->companyId;
        
        $sql = "UPDATE payment_transactions SET " . implode(', ', $fields) . 
               ", updated_at = NOW() WHERE gateway_reference = ? AND tenant_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }
    
    /**
     * Store pending payment for polling
     */
    private function storePendingPayment(int $saleId, string $checkoutRequestId, string $phone, float $amount): void
    {
        // Store in session or cache for polling
        // This is a simple implementation - consider Redis for production
        $_SESSION['pending_mpesa_payments'][$checkoutRequestId] = [
            'sale_id' => $saleId,
            'phone' => $phone,
            'amount' => $amount,
            'created_at' => time()
        ];
    }
    
    /**
     * Create unallocated payment record
     */
    private function createUnallocatedPayment(array $result): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO unallocated_payments 
            (tenant_id, branch_id, transaction_id, amount, phone, account_reference, 
             payer_name, payment_date, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', NOW())
        ");
        
        $stmt->execute([
            $this->companyId,
            $this->branchId,
            $result['transaction_id'],
            $result['amount'],
            $result['phone'],
            $result['account_reference'],
            $result['name']
        ]);
    }
    
    /**
     * Get payment methods available for company
     */
    public function getAvailablePaymentMethods(): array
    {
        $methods = [
            ['code' => 'cash', 'name' => 'Cash', 'icon' => 'fa-money-bill'],
            ['code' => 'card', 'name' => 'Card', 'icon' => 'fa-credit-card']
        ];
        
        // Check for M-Pesa
        if ($this->getGatewayConfig('mpesa')) {
            $methods[] = ['code' => 'mpesa', 'name' => 'M-Pesa', 'icon' => 'fa-mobile-alt'];
        }
        
        return $methods;
    }
}
