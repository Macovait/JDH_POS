<?php
/**
 * Paystack Payment Gateway (Africa)
 * 
 * @package JDH_POS
 * @version 2.0.0
 */

namespace JDH_POS\Billing;

class PaystackGateway extends AbstractPaymentGateway {
    
    private ?string $secretKey;
    private ?string $publicKey;
    private string $baseUrl = 'https://api.paystack.co';
    
    public function __construct(array $config = []) {
        parent::__construct($config);
        $this->secretKey = $this->config('paystack_secret_key', 'PAYSTACK_SECRET_KEY');
        $this->publicKey = $this->config('paystack_public_key', 'PAYSTACK_PUBLIC_KEY');
    }
    
    public function initializePayment(float $amount, string $currency, array $metadata = []): array {
        $amountFormatted = (int) ($amount * 100); // Paystack uses kobo/kesh
        
        $data = [
            'amount' => $amountFormatted,
            'currency' => strtoupper($currency),
            'email' => $metadata['customer_email'] ?? 'customer@example.com',
            'metadata' => array_merge($metadata, [
                'tenant_id' => $this->tenantId,
                'website' => $_SERVER['HTTP_HOST'] ?? '',
            ]),
            'callback_url' => $metadata['callback_url'] ?? '',
        ];
        
        $response = $this->makeRequest('POST', '/transaction/initialize', $data);
        
        $this->log('payment_initialized', [
            'reference' => $response['data']['reference'] ?? null,
            'amount' => $amount,
            'status' => $response['status'] ? 'pending' : 'failed',
        ]);
        
        return [
            'success' => $response['status'] ?? false,
            'reference' => $response['data']['reference'] ?? null,
            'checkout_url' => $response['data']['authorization_url'] ?? null,
            'raw' => $response,
        ];
    }
    
    public function processCallback(array $data): array {
        $reference = $data['reference'] ?? '';
        return $this->checkStatus($reference);
    }
    
    public function checkStatus(string $paymentReference): array {
        $response = $this->makeRequest('GET', '/transaction/verify/' . $paymentReference);
        $status = $response['data']['status'] ?? 'unknown';
        return [
            'success' => $status === 'success',
            'status' => $status,
            'amount' => ($response['data']['amount'] ?? 0) / 100,
            'raw' => $response,
        ];
    }
    
    public function refund(string $paymentReference, ?float $amount = null, string $reason = ''): array {
        $data = [];
        if ($amount !== null) {
            $data['amount'] = (int) ($amount * 100);
        }
        $response = $this->makeRequest('POST', '/refund', array_merge($data, ['transaction' => $paymentReference]));
        return [
            'success' => $response['status'] ?? false,
            'reference' => $response['data']['reference'] ?? null,
            'raw' => $response,
        ];
    }
    
    public function createCustomer(string $email, string $name, array $data = []): array {
        $payload = ['email' => $email, 'first_name' => $name];
        $response = $this->makeRequest('POST', '/customer', $payload);
        return ['success' => $response['status'] ?? false, 'id' => $response['data']['customer_code'] ?? null, 'raw' => $response];
    }
    
    public function getPaymentMethods(string $customerReference): array {
        return ['success' => true, 'methods' => ['card', 'bank', 'ussd', 'qr', 'mobile_money']];
    }
    
    public function verifyWebhookSignature(string $payload, string $signature): bool {
        $hash = hash_hmac('sha512', $payload, $this->secretKey);
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
