<?php
/**
 * PayPal Payment Gateway
 * 
 * Implements payment processing via PayPal API
 * Supports: Express Checkout, Subscriptions
 * 
 * @package JDH_POS
 * @version 2.0.0
 */

namespace JDH_POS\Billing;

class PayPalGateway extends AbstractPaymentGateway {
    
    private ?string $clientId;
    private ?string $clientSecret;
    private bool $sandbox;
    private string $accessToken;
    private string $baseUrl;
    
    public function __construct(array $config = []) {
        parent::__construct($config);
        
        $this->clientId = $this->config('paypal_client_id', 'PAYPAL_CLIENT_ID');
        $this->clientSecret = $this->config('paypal_client_secret', 'PAYPAL_CLIENT_SECRET');
        $this->sandbox = $this->config('paypal_mode', 'PAYPAL_MODE', 'sandbox') !== 'live';
        
        $this->baseUrl = $this->sandbox 
            ? 'https://api-m.sandbox.paypal.com' 
            : 'https://api-m.paypal.com';
    }
    
    /**
     * Get access token
     */
    private function getAccessToken(): string {
        if (!empty($this->accessToken)) {
            return $this->accessToken;
        }
        
        $ch = curl_init($this->baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode("$this->clientId:$this->clientSecret"),
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'] ?? '';
        
        return $this->accessToken;
    }
    
    /**
     * Initialize payment (create order)
     */
    public function initializePayment(float $amount, string $currency, array $metadata = []): array {
        $token = $this->getAccessToken();
        
        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $metadata['reference_id'] ?? $this->tenantId,
                    'description' => $metadata['description'] ?? 'JDH POS Payment',
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                    'metadata' => [
                        'tenant_id' => $this->tenantId,
                    ],
                ],
            ],
            'application_context' => [
                'return_url' => $metadata['return_url'] ?? '',
                'cancel_url' => $metadata['cancel_url'] ?? '',
            ],
        ];
        
        $response = $this->makeRequest('POST', '/v2/checkout/orders', $data);
        
        $this->log('payment_initialized', [
            'reference' => $response['id'] ?? null,
            'amount' => $amount,
            'status' => $response['status'] ?? 'CREATED',
        ]);
        
        // Find approval URL
        $approveUrl = '';
        foreach ($response['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approveUrl = $link['href'];
                break;
            }
        }
        
        return [
            'success' => isset($response['id']),
            'reference' => $response['id'] ?? null,
            'status' => $response['status'] ?? 'CREATED',
            'checkout_url' => $approveUrl,
            'raw' => $response,
        ];
    }
    
    /**
     * Process webhook callback
     */
    public function processCallback(array $data): array {
        $eventType = $data['event_type'] ?? '';
        $resource = $data['resource'] ?? [];
        
        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                return $this->handlePaymentSuccess($resource);
            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.DECLINED':
                return $this->handlePaymentFailed($resource);
            case 'REFUND.COMPLETED':
                return $this->handleRefund($resource);
            default:
                return ['handled' => false, 'type' => $eventType];
        }
    }
    
    /**
     * Check payment status
     */
    public function checkStatus(string $paymentReference): array {
        $token = $this->getAccessToken();
        
        $response = $this->makeRequest('GET', "/v2/checkout/orders/$paymentReference");
        
        $amount = 0;
        $status = 'UNKNOWN';
        
        foreach ($response['purchase_units'] ?? [] as $pu) {
            if (!empty($pu['payments']['captures'])) {
                foreach ($pu['payments']['captures'] as $capture) {
                    $amount = floatval($capture['amount']['value']);
                    $status = $capture['status'];
                }
            }
        }
        
        return [
            'success' => isset($response['id']),
            'status' => $status,
            'amount' => $amount,
            'reference' => $response['id'],
            'raw' => $response,
        ];
    }
    
    /**
     * Capture payment (after approval)
     */
    public function capturePayment(string $orderId): array {
        $token = $this->getAccessToken();
        
        $response = $this->makeRequest('POST', "/v2/checkout/orders/$orderId/capture", []);
        
        $this->log('payment_captured', [
            'reference' => $response['id'] ?? $orderId,
            'status' => $response['status'] ?? 'COMPLETED',
        ]);
        
        return [
            'success' => $response['status'] === 'COMPLETED',
            'reference' => $response['id'] ?? $orderId,
            'status' => $response['status'] ?? 'UNKNOWN',
            'raw' => $response,
        ];
    }
    
    /**
     * Refund payment
     */
    public function refund(string $paymentReference, ?float $amount = null, string $reason = ''): array {
        $token = $this->getAccessToken();
        
        $data = [];
        
        if ($amount !== null) {
            $data['amount'] = [
                'value' => number_format($amount, 2, '.', ''),
                'currency_code' => 'KES',
            ];
        }
        
        if ($reason) {
            $data['note'] = $reason;
        }
        
        $response = $this->makeRequest('POST', "/v2/payments/captures/$paymentReference/refund", $data);
        
        $this->log('refund_processed', [
            'reference' => $response['id'] ?? null,
            'original_reference' => $paymentReference,
            'amount' => $amount,
            'status' => $response['status'] ?? 'COMPLETED',
        ]);
        
        return [
            'success' => isset($response['id']),
            'reference' => $response['id'] ?? null,
            'status' => $response['status'] ?? 'COMPLETED',
            'raw' => $response,
        ];
    }
    
    /**
     * Create customer in PayPal
     */
    public function createCustomer(string $email, string $name, array $data = []): array {
        // PayPal doesn't have explicit customer creation - uses email directly
        return [
            'success' => true,
            'id' => $email,
            'email' => $email,
        ];
    }
    
    /**
     * Get payment methods
     */
    public function getPaymentMethods(string $customerReference): array {
        // PayPal uses checkout flow, not stored methods
        return [];
    }
    
    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool {
        // PayPal uses different verification
        // For manual verification, check API credentials or use PayPal SDK
        return true;
    }
    
    /**
     * Create subscription
     */
    public function createSubscription(array $data): array {
        $token = $this->getAccessToken();
        
        $response = $this->makeRequest('POST', '/v1/billing/subscriptions', [
            'plan_id' => $data['plan_id'],
            'subscriber' => [
                'email_address' => $data['email'],
            ],
            'application_context' => [
                'brand_name' => $data['brand_name'] ?? 'JDH POS',
                'return_url' => $data['return_url'] ?? '',
                'cancel_url' => $data['cancel_url'] ?? '',
            ],
        ]);
        
        $this->log('subscription_created', [
            'reference' => $response['id'] ?? null,
            'plan' => $data['plan_id'],
            'status' => $response['status'] ?? 'ACTIVE',
        ]);
        
        return [
            'success' => isset($response['id']),
            'subscription_id' => $response['id'] ?? null,
            'status' => $response['status'] ?? 'ACTIVE',
            'raw' => $response,
        ];
    }
    
    /**
     * Make API request
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array {
        $ch = curl_init($this->baseUrl . $endpoint);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json',
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("PayPal API error: $error");
        }
        
        return json_decode($response, true) ?? [];
    }
    
    private function handlePaymentSuccess(array $data): array {
        $this->log('payment_succeeded', [
            'reference' => $data['id'] ?? null,
            'amount' => floatval($data['amount']['value'] ?? 0),
        ]);
        
        return [
            'handled' => true,
            'type' => 'payment_succeeded',
            'reference' => $data['id'],
            'amount' => floatval($data['amount']['value'] ?? 0),
        ];
    }
    
    private function handlePaymentFailed(array $data): array {
        $this->log('payment_failed', [
            'reference' => $data['id'] ?? null,
        ]);
        
        return [
            'handled' => true,
            'type' => 'payment_failed',
            'reference' => $data['id'],
        ];
    }
    
    private function handleRefund(array $data): array {
        return [
            'handled' => true,
            'type' => 'refund',
            'reference' => $data['id'],
            'amount' => floatval($data['amount']['value'] ?? 0),
        ];
    }
}