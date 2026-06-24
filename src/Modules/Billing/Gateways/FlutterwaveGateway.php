<?php
/**
 * Flutterwave Payment Gateway (Africa / Global)
 * 
 * @package JDH_POS
 * @version 2.0.0
 */

namespace JDH_POS\Billing;

class FlutterwaveGateway extends AbstractPaymentGateway {
    
    private ?string $secretKey;
    private ?string $publicKey;
    private string $baseUrl = 'https://api.flutterwave.com/v3';
    
    public function __construct(array $config = []) {
        parent::__construct($config);
        $this->secretKey = $this->config('flutterwave_secret_key', 'FLUTTERWAVE_SECRET_KEY');
        $this->publicKey = $this->config('flutterwave_public_key', 'FLUTTERWAVE_PUBLIC_KEY');
    }
    
    public function initializePayment(float $amount, string $currency, array $metadata = []): array {
        $data = [
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'tx_ref' => $metadata['reference'] ?? uniqid('flw_', true),
            'redirect_url' => $metadata['callback_url'] ?? '',
            'customer' => [
                'email' => $metadata['customer_email'] ?? 'customer@example.com',
                'name' => $metadata['customer_name'] ?? 'Customer',
            ],
            'meta' => array_merge($metadata, [
                'tenant_id' => $this->tenantId,
            ]),
        ];
        
        $response = $this->makeRequest('POST', '/payments', $data);
        
        $this->log('payment_initialized', [
            'reference' => $data['tx_ref'],
            'amount' => $amount,
            'status' => 'pending',
        ]);
        
        return [
            'success' => ($response['status'] ?? '') === 'success',
            'reference' => $data['tx_ref'],
            'checkout_url' => $response['data']['link'] ?? null,
            'raw' => $response,
        ];
    }
    
    public function processCallback(array $data): array {
        $status = $data['status'] ?? '';
        $txRef = $data['tx_ref'] ?? '';
        return [
            'success' => $status === 'successful',
            'reference' => $txRef,
            'status' => $status,
        ];
    }
    
    public function checkStatus(string $paymentReference): array {
        $response = $this->makeRequest('GET', '/transactions/verify_by_reference?tx_ref=' . urlencode($paymentReference));
        $status = $response['data']['status'] ?? 'unknown';
        return [
            'success' => $status === 'successful',
            'status' => $status,
            'amount' => $response['data']['amount'] ?? 0,
            'raw' => $response,
        ];
    }
    
    public function refund(string $paymentReference, ?float $amount = null, string $reason = ''): array {
        $data = ['flutterwave_ref' => $paymentReference];
        if ($amount !== null) {
            $data['amount'] = $amount;
        }
        $response = $this->makeRequest('POST', '/refunds', $data);
        return ['success' => ($response['status'] ?? '') === 'success', 'raw' => $response];
    }
    
    public function createCustomer(string $email, string $name, array $data = []): array {
        $payload = ['email' => $email, 'full_name' => $name, 'phone' => $data['phone'] ?? ''];
        $response = $this->makeRequest('POST', '/customers', $payload);
        return ['success' => ($response['status'] ?? '') === 'success', 'id' => $response['data']['id'] ?? null, 'raw' => $response];
    }
    
    public function getPaymentMethods(string $customerReference): array {
        return ['success' => true, 'methods' => ['card', 'bank_transfer', 'mobile_money', 'ussd', 'qr']];
    }
    
    public function verifyWebhookSignature(string $payload, string $signature): bool {
        $hash = hash_hmac('sha256', $payload, $this->secretKey);
        return hash_equals($hash, $signature);
    }
    
    private function makeRequest(string $method, string $endpoint, array $data = []): array {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json',
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }
}
