<?php
/**
 * Payment Service - Stripe/PayPal Integration
 * Handles subscriptions, one-time payments, and webhooks
 */

namespace Jakababa\Services;

use PDO;
use Exception;

class PaymentService
{
    private PDO $pdo;
    private array $config;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->config = require __DIR__ . '/../../config/payment.php';
        
        // Initialize Stripe
        if ($this->config['stripe']['enabled'] && !empty($this->config['stripe']['secret_key'])) {
            \Stripe\Stripe::setApiKey($this->config['stripe']['secret_key']);
        }
    }
    
    /**
     * Create a Stripe Checkout Session for subscription
     */
    public function createStripeCheckout(int $tenantId, string $planId, string $billingCycle = 'monthly'): array
    {
        if (!$this->config['stripe']['enabled']) {
            throw new Exception('Stripe payments not enabled');
        }
        
        $plan = $this->getPlan($planId);
        if (!$plan) {
            throw new Exception('Invalid plan selected');
        }
        
        // Get or create customer
        $customerId = $this->getOrCreateStripeCustomer($tenantId);
        
        // Calculate price
        $unitAmount = $billingCycle === 'yearly' 
            ? $plan['price_yearly'] * 100 
            : $plan['price_monthly'] * 100;
        
        $session = \Stripe\Checkout\Session::create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($this->config['currency']),
                    'product_data' => [
                        'name' => $plan['name'] . ' Plan',
                        'description' => $plan['description'] ?? 'Jakababa POS Subscription',
                    ],
                    'unit_amount' => (int) $unitAmount,
                    'recurring' => [
                        'interval' => $billingCycle === 'yearly' ? 'year' : 'month',
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $this->config['app_url'] . '/dashboard/billing?success=true&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->config['app_url'] . '/dashboard/billing?canceled=true',
            'subscription_data' => [
                'trial_period_days' => $this->config['trial_days'],
                'metadata' => [
                    'tenant_id' => $tenantId,
                    'plan_id' => $planId,
                ],
            ],
        ]);
        
        // Store checkout session
        $this->storeCheckoutSession($tenantId, 'stripe', $session->id, $planId, $billingCycle);
        
        return [
            'success' => true,
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ];
    }
    
    /**
     * Create PayPal subscription
     */
    public function createPayPalSubscription(int $tenantId, string $planId, string $billingCycle = 'monthly'): array
    {
        if (!$this->config['paypal']['enabled']) {
            throw new Exception('PayPal payments not enabled');
        }
        
        $plan = $this->getPlan($planId);
        if (!$plan) {
            throw new Exception('Invalid plan selected');
        }
        
        $accessToken = $this->getPayPalAccessToken();
        
        // Create subscription
        $response = $this->paypalRequest('/v1/billing/subscriptions', [
            'plan_id' => $this->getPayPalPlanId($planId, $billingCycle),
            'start_time' => date('Y-m-d\TH:i:s\Z', strtotime('+' . $this->config['trial_days'] . ' days')),
            'quantity' => '1',
            'shipping_amount' => [
                'currency_code' => $this->config['currency'],
                'value' => '0.00',
            ],
            'subscriber' => [
                'name' => [
                    'given_name' => $this->getTenantName($tenantId),
                ],
                'email_address' => $this->getTenantEmail($tenantId),
            ],
            'application_context' => [
                'brand_name' => 'Jakababa POS',
                'locale' => 'en-US',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'payment_method' => [
                    'payer_selected' => 'PAYPAL',
                    'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                ],
                'return_url' => $this->config['app_url'] . '/dashboard/billing?paypal=success',
                'cancel_url' => $this->config['app_url'] . '/dashboard/billing?paypal=cancel',
            ],
        ], $accessToken);
        
        if (!isset($response['id'])) {
            throw new Exception('Failed to create PayPal subscription: ' . json_encode($response));
        }
        
        // Store subscription
        $this->storeCheckoutSession($tenantId, 'paypal', $response['id'], $planId, $billingCycle);
        
        // Find approval URL
        $approvalUrl = '';
        foreach ($response['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }
        
        return [
            'success' => true,
            'checkout_url' => $approvalUrl,
            'subscription_id' => $response['id'],
        ];
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handleStripeWebhook(string $payload, string $sigHeader): array
    {
        if (!$this->config['stripe']['enabled']) {
            return ['success' => false, 'error' => 'Stripe not enabled'];
        }
        
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->config['stripe']['webhook_secret']
            );
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return ['success' => false, 'error' => 'Invalid signature'];
        }
        
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($event->data->object);
                break;
                
            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event->data->object);
                break;
                
            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event->data->object);
                break;
                
            case 'customer.subscription.deleted':
                $this->handleSubscriptionCancelled($event->data->object);
                break;
        }
        
        return ['success' => true, 'type' => $event->type];
    }
    
    /**
     * Handle PayPal webhook
     */
    public function handlePayPalWebhook(array $data): array
    {
        if (!$this->config['paypal']['enabled']) {
            return ['success' => false, 'error' => 'PayPal not enabled'];
        }
        
        $eventType = $data['event_type'] ?? '';
        $resource = $data['resource'] ?? [];
        
        switch ($eventType) {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
            case 'BILLING.SUBSCRIPTION.CREATED':
                $this->activatePayPalSubscription($resource);
                break;
                
            case 'BILLING.SUBSCRIPTION.CANCELLED':
                $this->cancelPayPalSubscription($resource);
                break;
                
            case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                $this->handlePayPalPaymentFailed($resource);
                break;
        }
        
        return ['success' => true, 'type' => $eventType];
    }
    
    /**
     * Get tenant's current subscription
     */
    public function getTenantSubscription(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, p.name as plan_name, p.slug as plan_slug
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.tenant_id = ? AND s.status != 'cancelled'
            ORDER BY s.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Cancel subscription
     */
    public function cancelSubscription(int $tenantId, bool $immediate = false): array
    {
        $sub = $this->getTenantSubscription($tenantId);
        if (!$sub) {
            throw new Exception('No active subscription found');
        }
        
        // Cancel in payment gateway
        if ($sub['gateway'] === 'stripe' && $sub['gateway_subscription_id']) {
            try {
                $subscription = \Stripe\Subscription::retrieve($sub['gateway_subscription_id']);
                $subscription->cancel(['prorate' => !$immediate]);
            } catch (\Exception $e) {
                // Log but continue
            }
        } elseif ($sub['gateway'] === 'paypal' && $sub['gateway_subscription_id']) {
            $this->cancelPayPalSubscriptionRemote($sub['gateway_subscription_id']);
        }
        
        // Update local record
        $stmt = $this->pdo->prepare("
            UPDATE subscriptions 
            SET status = 'cancelled', 
                cancelled_at = NOW(),
                current_period_ends_at = ?
            WHERE id = ?
        ");
        
        $endsAt = $immediate ? date('Y-m-d H:i:s') : $sub['current_period_ends_at'];
        $stmt->execute([$endsAt, $sub['id']]);
        
        return [
            'success' => true,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'access_until' => $endsAt,
        ];
    }
    
    /**
     * Get billing history
     */
    public function getBillingHistory(int $tenantId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payments 
            WHERE tenant_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Private helper methods
    
    private function getPlan(string $planId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM plans WHERE slug = ? AND status = 'active'");
        $stmt->execute([$planId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    private function getOrCreateStripeCustomer(int $tenantId): string
    {
        // Check existing customer
        $stmt = $this->pdo->prepare("SELECT stripe_customer_id FROM companies WHERE id = ?");
        $stmt->execute([$tenantId]);
        $customerId = $stmt->fetchColumn();
        
        if ($customerId) return $customerId;
        
        // Get tenant info
        $stmt = $this->pdo->prepare("SELECT name, email FROM companies WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create Stripe customer
        $customer = \Stripe\Customer::create([
            'name' => $tenant['name'],
            'email' => $tenant['email'],
            'metadata' => [
                'tenant_id' => $tenantId,
            ],
        ]);
        
        // Save customer ID
        $stmt = $this->pdo->prepare("UPDATE companies SET stripe_customer_id = ? WHERE id = ?");
        $stmt->execute([$customer->id, $tenantId]);
        
        return $customer->id;
    }
    
    private function getPayPalAccessToken(): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['paypal']['api_url'] . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_USERPWD, $this->config['paypal']['client_id'] . ':' . $this->config['paypal']['secret']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Failed to get PayPal access token: ' . $response);
        }
        
        $data = json_decode($response, true);
        return $data['access_token'] ?? '';
    }
    
    private function paypalRequest(string $endpoint, array $data, string $accessToken): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['paypal']['api_url'] . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'PayPal-Request-Id: ' . uniqid(),
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?: [];
    }
    
    private function getPayPalPlanId(string $planId, string $billingCycle): string
    {
        // In production, you'd map local plan IDs to PayPal plan IDs
        // For now, return a placeholder or create dynamically
        $stmt = $this->pdo->prepare("SELECT paypal_plan_id FROM plans WHERE slug = ?");
        $stmt->execute([$planId]);
        $paypalPlanId = $stmt->fetchColumn();
        
        if (!$paypalPlanId) {
            throw new Exception('PayPal plan not configured for: ' . $planId);
        }
        
        return $paypalPlanId;
    }
    
    private function getTenantName(int $tenantId): string
    {
        $stmt = $this->pdo->prepare("SELECT name FROM companies WHERE id = ?");
        $stmt->execute([$tenantId]);
        return $stmt->fetchColumn() ?? 'Unknown';
    }
    
    private function getTenantEmail(int $tenantId): string
    {
        $stmt = $this->pdo->prepare("SELECT email FROM companies WHERE id = ?");
        $stmt->execute([$tenantId]);
        return $stmt->fetchColumn() ?? '';
    }
    
    private function storeCheckoutSession(int $tenantId, string $gateway, string $sessionId, string $planId, string $billingCycle): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_sessions 
            (tenant_id, gateway, session_id, plan_id, billing_cycle, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ON DUPLICATE KEY UPDATE
            session_id = VALUES(session_id),
            status = VALUES(status),
            created_at = VALUES(created_at)
        ");
        $stmt->execute([$tenantId, $gateway, $sessionId, $planId, $billingCycle]);
    }
    
    // Webhook handlers
    
    private function handleCheckoutCompleted($session): void
    {
        $tenantId = $session->metadata->tenant_id ?? null;
        $planId = $session->metadata->plan_id ?? null;
        
        if (!$tenantId || !$planId) return;
        
        // Create or update subscription
        $this->createOrUpdateSubscription($tenantId, 'stripe', $session->subscription, $planId);
    }
    
    private function handleInvoicePaymentSucceeded($invoice): void
    {
        $subscriptionId = $invoice->subscription ?? null;
        if (!$subscriptionId) return;
        
        // Log payment
        $stmt = $this->pdo->prepare("
            INSERT INTO payments (tenant_id, gateway, transaction_id, amount, currency, status, created_at)
            SELECT tenant_id, 'stripe', ?, ?, ?, 'completed', NOW()
            FROM subscriptions WHERE gateway_subscription_id = ?
        ");
        $stmt->execute([
            $invoice->id,
            $invoice->amount_paid / 100,
            strtoupper($invoice->currency),
            $subscriptionId,
        ]);
    }
    
    private function handleInvoicePaymentFailed($invoice): void
    {
        // Update subscription status
        $stmt = $this->pdo->prepare("
            UPDATE subscriptions 
            SET status = 'past_due', 
                updated_at = NOW()
            WHERE gateway_subscription_id = ?
        ");
        $stmt->execute([$invoice->subscription]);
    }
    
    private function handleSubscriptionCancelled($subscription): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE subscriptions 
            SET status = 'cancelled', 
                cancelled_at = NOW(),
                current_period_ends_at = FROM_UNIXTIME(?)
            WHERE gateway_subscription_id = ?
        ");
        $stmt->execute([
            $subscription->current_period_end,
            $subscription->id,
        ]);
    }
    
    private function activatePayPalSubscription(array $resource): void
    {
        $subscriptionId = $resource['id'] ?? '';
        $customId = $resource['custom_id'] ?? '';
        
        // Extract tenant and plan from custom_id or lookup
        $parts = explode('|', $customId);
        $tenantId = $parts[0] ?? null;
        $planId = $parts[1] ?? null;
        
        if ($tenantId && $planId) {
            $this->createOrUpdateSubscription((int)$tenantId, 'paypal', $subscriptionId, $planId);
        }
    }
    
    private function cancelPayPalSubscription(array $resource): void
    {
        $subscriptionId = $resource['id'] ?? '';
        
        $stmt = $this->pdo->prepare("
            UPDATE subscriptions 
            SET status = 'cancelled', 
                cancelled_at = NOW()
            WHERE gateway_subscription_id = ? AND gateway = 'paypal'
        ");
        $stmt->execute([$subscriptionId]);
    }
    
    private function handlePayPalPaymentFailed(array $resource): void
    {
        $subscriptionId = $resource['id'] ?? '';
        
        $stmt = $this->pdo->prepare("
            UPDATE subscriptions 
            SET status = 'past_due', 
                updated_at = NOW()
            WHERE gateway_subscription_id = ? AND gateway = 'paypal'
        ");
        $stmt->execute([$subscriptionId]);
    }
    
    private function cancelPayPalSubscriptionRemote(string $subscriptionId): void
    {
        $accessToken = $this->getPayPalAccessToken();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['paypal']['api_url'] . '/v1/billing/subscriptions/' . $subscriptionId . '/cancel');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['reason' => 'Customer requested cancellation']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ]);
        
        curl_exec($ch);
        curl_close($ch);
    }
    
    private function createOrUpdateSubscription(int $tenantId, string $gateway, string $gatewaySubscriptionId, string $planId): void
    {
        // Check if subscription exists
        $stmt = $this->pdo->prepare("SELECT id FROM subscriptions WHERE gateway_subscription_id = ?");
        $stmt->execute([$gatewaySubscriptionId]);
        $existing = $stmt->fetchColumn();
        
        if ($existing) {
            // Update
            $stmt = $this->pdo->prepare("
                UPDATE subscriptions 
                SET status = 'active',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$existing]);
        } else {
            // Create new
            $stmt = $this->pdo->prepare("
                INSERT INTO subscriptions 
                (tenant_id, plan_id, gateway, gateway_subscription_id, status, billing_cycle, current_period_ends_at, created_at)
                VALUES (?, ?, ?, ?, 'active', 'monthly', DATE_ADD(NOW(), INTERVAL 1 MONTH), NOW())
            ");
            $stmt->execute([$tenantId, $planId, $gateway, $gatewaySubscriptionId]);
        }
    }
}
