<?php
/**
 * Stripe Payment Gateway
 * 
 * Implements payment processing via Stripe API
 * Supports: Card payments, checkout, subscriptions
 * 
 * @package JDH_POS
 * @version 2.0.0
 */

namespace JDH_POS\Billing;

class StripeGateway extends AbstractPaymentGateway {
    
    private ?string $apiKey;
    private ?string $webhookSecret;
    private string $baseUrl = 'https://api.stripe.com/v1';
    
    public function __construct(array $config = []) {
        parent::__construct($config);
        
        $this->apiKey = $this->config('stripe_secret_key', 'STRIPE_SECRET_KEY');
        $this->webhookSecret = $this->config('stripe_webhook_secret', 'STRIPE_WEBHOOK_SECRET');
    }
    
    /**
     * Initialize payment (create payment intent)
     */
    public function initializePayment(float $amount, string $currency, array $metadata = []): array {
        $amountFormatted = $this->formatAmount($amount, $currency);
        
        $data = [
            'amount' => $amountFormatted,
            'currency' => strtolower($currency),
            'metadata' => array_merge($metadata, [
                'tenant_id' => $this->tenantId,
                'website' => $_SERVER['HTTP_HOST'] ?? '',
            ]),
        ];
        
        // Optional: attach customer
        if (!empty($metadata['customer_email'])) {
            $customer = $this->createCustomer($metadata['customer_email'], $metadata['customer_name'] ?? '');
            if ($customer && !empty($customer['id'])) {
                $data['customer'] = $customer['id'];
            }
        }
        
        // Optional: payment method type
        if (!empty($metadata['payment_method_types'])) {
            $data['payment_method_types'] = $metadata['payment_method_types'];
        }
        
        $response = $this->makeRequest('POST', '/payment_intents', $data);
        
        $this->log('payment_initialized', [
            'reference' => $response['id'] ?? null,
            'amount' => $amount,
            'status' => $response['status'] ?? 'pending',
        ]);
        
        return [
            'success' => isset($response['id']),
            'reference' => $response['id'] ?? null,
            'client_secret' => $response['client_secret'] ?? null,
            'status' => $response['status'] ?? 'pending',
            'checkout_url' => $this->getCheckoutUrl($response['id'] ?? ''),
            'raw' => $response,
        ];
    }
    
    /**
     * Process Stripe webhook callback
     */
    public function processCallback(array $data): array {
        $eventType = $data['type'] ?? '';
        $eventData = $data['data']['object'] ?? [];
        
        switch ($eventType) {
            case 'payment_intent.succeeded':
                return $this->handlePaymentSuccess($eventData);
            case 'payment_intent.payment_failed':
                return $this->handlePaymentFailed($eventData);
            case 'charge.refunded':
                return $this->handleRefund($eventData);
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                return $this->handleSubscriptionEvent($eventData, $eventType);
            default:
                return ['handled' => false, 'type' => $eventType];
        }
    }
    
    /**
     * Check payment status
     */
    public function checkStatus(string $paymentReference): array {
        $response = $this->makeRequest('GET', "/payment_intents/$paymentReference");
        
        return [
            'success' => isset($response['id']),
            'status' => $response['status'] ?? 'unknown',
            'amount' => $this->unformatAmount($response['amount'] ?? 0, $response['currency'] ?? 'kes'),
            'reference' => $response['id'],
            'raw' => $response,
        ];
    }
    
    /**
     * Refund payment
     */
    public function refund(string $paymentReference, ?float $amount = null, string $reason = ''): array {
        $data = ['payment_intent' => $paymentReference];
        
        if ($amount !== null) {
            $currency = 'KES'; // Get from original payment
            $data['amount'] = $this->formatAmount($amount, $currency);
        }
        
        if ($reason) {
            $data['reason'] = $reason;
        }
        
        $response = $this->makeRequest('POST', '/refunds', $data);
        
        $this->log('refund_processed', [
            'reference' => $response['id'] ?? null,
            'original_reference' => $paymentReference,
            'amount' => $amount,
            'status' => $response['status'] ?? 'pending',
        ]);
        
        return [
            'success' => isset($response['id']),
            'reference' => $response['id'] ?? null,
            'status' => $response['status'] ?? 'pending',
            'raw' => $response,
        ];
    }
    
    /**
     * Create Stripe customer
     */
    public function createCustomer(string $email, string $name, array $data = []): array {
        $response = $this->makeRequest('POST', '/customers', [
            'email' => $email,
            'name' => $name,
            'metadata' => array_merge($data, ['tenant_id' => $this->tenantId]),
        ]);
        
        return [
            'success' => isset($response['id']),
            'id' => $response['id'] ?? null,
            'email' => $response['email'] ?? $email,
            'raw' => $response,
        ];
    }
    
    /**
     * Get customer payment methods
     */
    public function getPaymentMethods(string $customerReference): array {
        $response = $this->makeRequest('GET', "/payment_methods?customer=$customerReference&type=card");
        
        $methods = [];
        foreach ($response['data'] ?? [] as $pm) {
            $methods[] = [
                'id' => $pm['id'],
                'type' => $pm['type'],
                'card' => [
                    'brand' => $pm['card']['brand'] ?? '',
                    'last4' => $pm['card']['last4'] ?? '',
                    'exp_month' => $pm['card']['exp_month'] ?? 0,
                    'exp_year' => $pm['card']['exp_year'] ?? 0,
                ],
            ];
        }
        
        return $methods;
    }
    
    /**
     * Verify webhook signature (manual HMAC verification)
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool {
        if (empty($this->webhookSecret)) {
            return false;
        }
        
        // Compute expected signature
        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);
        
        // Compare signatures (timing-safe)
        return hash_equals($expected, $signature);
    }
    
    /**
     * Create checkout session
     */
    public function createCheckoutSession(array $data): array {
        $response = $this->makeRequest('POST', '/checkout/sessions', [
            'payment_method_types' => $data['payment_methods'] ?? ['card'],
            'line_items' => $data['line_items'] ?? [],
            'mode' => $data['mode'] ?? 'payment',
            'success_url' => $data['success_url'] ?? '',
            'cancel_url' => $data['cancel_url'] ?? '',
            'customer_email' => $data['customer_email'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
        
        return [
            'success' => isset($response['id']),
            'session_id' => $response['id'] ?? null,
            'url' => $response['url'] ?? null,
            'raw' => $response,
        ];
    }
    
    /**
     * Create subscription
     */
    public function createSubscription(array $data): array {
        $response = $this->makeRequest('POST', '/subscriptions', [
            'customer' => $data['customer'],
            'items' => $data['items'] ?? [],
            'payment_behavior' => $data['payment_behavior'] ?? 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => $data['metadata'] ?? [],
        ]);
        
        $this->log('subscription_created', [
            'reference' => $response['id'] ?? null,
            'customer' => $data['customer'],
            'status' => $response['status'] ?? 'incomplete',
        ]);
        
        return [
            'success' => isset($response['id']),
            'subscription_id' => $response['id'] ?? null,
            'status' => $response['status'] ?? 'incomplete',
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("Stripe API error: $error");
        }
        
        return json_decode($response, true) ?? [];
    }
    
    private function getCheckoutUrl(string $sessionId): string {
        return "https://checkout.stripe.com/pay/$sessionId";
    }
    
    private function handlePaymentSuccess(array $data): array {
        $this->log('payment_succeeded', [
            'reference' => $data['id'],
            'amount' => $this->unformatAmount($data['amount'] ?? 0, $data['currency'] ?? 'kes'),
        ]);
        
        return [
            'handled' => true,
            'type' => 'payment_succeeded',
            'reference' => $data['id'],
            'amount' => $this->unformatAmount($data['amount'] ?? 0, $data['currency'] ?? 'kes'),
        ];
    }
    
    private function handlePaymentFailed(array $data): array {
        $this->log('payment_failed', [
            'reference' => $data['id'],
            'error' => $data['last_payment_error']['message'] ?? 'Unknown',
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
            'charge' => $data['charge'],
        ];
    }
    
    private function handleSubscriptionEvent(array $data, string $eventType): array {
        return [
            'handled' => true,
            'type' => str_replace('customer.subscription.', '', $eventType),
            'subscription_id' => $data['id'],
            'status' => $data['status'],
        ];
    }
}