<?php
/**
 * Razorpay Payment Gateway (India)
 * 
 * @package JDH_POS
 * @version 2.0.0
 */

namespace JDH_POS\Billing;

class RazorpayGateway extends AbstractPaymentGateway {
    
    private ?string $keyId;
    private ?string $keySecret;
    private string $baseUrl = 'https://api.razorpay.com/v1';
    
    public function __construct(array $config = []) {
        parent::__construct($config);
        $this->keyId = $this->config('razorpay_key_id', 'RAZORPAY_KEY_ID');
        $this->keySecret = $this->config('razorpay_key_secret', 'RAZORPAY_KEY_SECRET');
    }
    
    public function initializePayment(float $amount, string $currency, array $metadata = []): array {
        $amountFormatted = (int) ($amount * 100); // Razorpay uses paise
        
        $data = [
            'amount' => $amountFormatted,
            'currency' => strtoupper($currency),
            'receipt' => $metadata['receipt'] ?? uniqid('rcp_', true),
            'notes' => array_merge($metadata, [
                'tenant_id' => $this->tenantId,
                'website' => $_SERVER['HTTP_HOST'] ?? '',
            ]),
        ];
        
        $response = $this->makeRequest('POST', '/orders', $data);
        
        $this->log('payment_initialized', [
            'reference' => $response['id'] ?? null,
            'amount' => $amount,
            'status' => $response['status'] ?? 'pending',
        ]);
        
        return [
            'success' => isset($response['id']),
            'reference' => $response['id'] ?? null,
            'amount' => $amountFormatted,
            'currency' => strtoupper($currency),
            'status' => $response['status'] ?? 'pending',
            'checkout_url' => null,
            'raw' => $response,
        ];
    }
    
    public function processCallback(array $data): array {
        $signature = $data['razorpay_signature'] ?? '';
        $orderId = $data['razorpay_order_id'] ?? '';
        $paymentId = $data['razorpay_payment_id'] ?? '';
        
        $generated = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->keySecret);
        
        if (!hash_equals($generated, $signature)) {
            return ['success' => false, 'error' => 'Invalid signature'];
        }
        
        return ['success' => true, 'reference' => $paymentId, 'status' => 'completed'];
    }
    
    public function checkStatus(string $paymentReference): array {
        $response = $this->makeRequest('GET', '/payments/' . $paymentReference);
        return [
            'success' => true,
            'status' => $response['status'] ?? 'unknown',
            'amount' => ($response['amount'] ?? 0) / 100,
            'raw' => $response,
        ];
    }
    
    public function refund(string $paymentReference, ?float $amount = null, string $reason = ''): array {
        $data = ['speed' => 'normal'];
        if ($amount !== null) {
            $data['amount'] = (int) ($amount * 100);
        }
        $response = $this->makeRequest('POST', '/payments/' . $paymentReference . '/refund', $data);
        return [
            'success' => isset($response['id']),
            'reference' => $response['id'] ?? null,
            'raw' => $response,
        ];
    }
    
    public function createCustomer(string $email, string $name, array $data = []): array {
        $payload = [
            'email' => $email,
            'name' => $name,
            'contact' => $data['phone'] ?? '',
        ];
        $response = $this->makeRequest('POST', '/customers', $payload);
        return ['success' => isset($response['id']), 'id' => $response['id'] ?? null, 'raw' => $response];
    }
    
    public function getPaymentMethods(string $customerReference): array {
        return ['success' => true, 'methods' => ['card', 'upi', 'netbanking', 'wallet']];
    }
    
    public function verifyWebhookSignature(string $payload, string $signature): bool {
        $expected = hash_hmac('sha256', $payload, $this->keySecret);
        return hash_equals($expected, $signature);
    }
    
    private function makeRequest(string $method, string $endpoint, array $data = []): array {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->keyId . ':' . $this->keySecret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }
}
