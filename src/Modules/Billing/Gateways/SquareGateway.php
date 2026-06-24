<?php
/**
 * Square Payment Gateway
 * 
 * @package JDH_POS
 * @version 2.0.0
 */

namespace JDH_POS\Billing;

class SquareGateway extends AbstractPaymentGateway {
    
    private ?string $accessToken;
    private ?string $applicationId;
    private string $baseUrl = 'https://connect.squareup.com/v2';
    private string $environment = 'production';
    
    public function __construct(array $config = []) {
        parent::__construct($config);
        $this->accessToken = $this->config('square_access_token', 'SQUARE_ACCESS_TOKEN');
        $this->applicationId = $this->config('square_app_id', 'SQUARE_APPLICATION_ID');
        $this->environment = $this->config('square_env', '', 'production');
        if ($this->environment === 'sandbox') {
            $this->baseUrl = 'https://connect.squareupsandbox.com/v2';
        }
    }
    
    public function initializePayment(float $amount, string $currency, array $metadata = []): array {
        $amountFormatted = (int) ($amount * 100);
        $idempotencyKey = uniqid('sq_', true);
        
        $data = [
            'idempotency_key' => $idempotencyKey,
            'amount_money' => [
                'amount' => $amountFormatted,
                'currency' => strtoupper($currency),
            ],
            'source_id' => $metadata['source_id'] ?? 'cnon:card-nonce-ok',
            'autocomplete' => true,
            'note' => $metadata['note'] ?? ('Tenant ' . $this->tenantId),
        ];
        
        $response = $this->makeRequest('POST', '/payments', $data);
        
        $this->log('payment_initialized', [
            'reference' => $response['payment']['id'] ?? null,
            'amount' => $amount,
            'status' => $response['payment']['status'] ?? 'pending',
        ]);
        
        return [
            'success' => isset($response['payment']['id']) && $response['payment']['status'] !== 'FAILED',
            'reference' => $response['payment']['id'] ?? null,
            'status' => $response['payment']['status'] ?? 'pending',
            'raw' => $response,
        ];
    }
    
    public function processCallback(array $data): array {
        return ['success' => true, 'reference' => $data['payment_id'] ?? '', 'status' => 'completed'];
    }
    
    public function checkStatus(string $paymentReference): array {
        $response = $this->makeRequest('GET', '/payments/' . $paymentReference);
        return [
            'success' => true,
            'status' => $response['payment']['status'] ?? 'unknown',
            'amount' => ($response['payment']['amount_money']['amount'] ?? 0) / 100,
            'raw' => $response,
        ];
    }
    
    public function refund(string $paymentReference, ?float $amount = null, string $reason = ''): array {
        $data = [
            'idempotency_key' => uniqid('sq_refund_', true),
            'payment_id' => $paymentReference,
        ];
        if ($amount !== null) {
            $data['amount_money'] = [
                'amount' => (int) ($amount * 100),
                'currency' => 'USD',
            ];
        }
        $response = $this->makeRequest('POST', '/refunds', $data);
        return ['success' => isset($response['refund']['id']), 'reference' => $response['refund']['id'] ?? null, 'raw' => $response];
    }
    
    public function createCustomer(string $email, string $name, array $data = []): array {
        $payload = [
            'idempotency_key' => uniqid('sq_cust_', true),
            'given_name' => $name,
            'email_address' => $email,
        ];
        $response = $this->makeRequest('POST', '/customers', $payload);
        return ['success' => isset($response['customer']['id']), 'id' => $response['customer']['id'] ?? null, 'raw' => $response];
    }
    
    public function getPaymentMethods(string $customerReference): array {
        return ['success' => true, 'methods' => ['card', 'square_gift_card', 'cash_app_pay']];
    }
    
    public function verifyWebhookSignature(string $payload, string $signature): bool {
        $hash = hash_hmac('sha256', $payload, $this->accessToken);
        return hash_equals($hash, $signature);
    }
    
    private function makeRequest(string $method, string $endpoint, array $data = []): array {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Square-Version: 2024-01-25',
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
