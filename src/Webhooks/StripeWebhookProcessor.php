<?php
/**
 * Stripe Webhook Processor
 * Production-ready handler for Stripe events with idempotency and signature verification.
 */

namespace JDH\POS\Webhooks;

use PDO;
use Exception;

class StripeWebhookProcessor
{
    private PDO $pdo;
    private string $webhookSecret;

    public function __construct(PDO $pdo, ?string $webhookSecret = null)
    {
        $this->pdo = $pdo;
        $this->webhookSecret = $webhookSecret ?: (getenv('STRIPE_WEBHOOK_SECRET') ?: '');
    }

    /**
     * Verify Stripe signature and process the event.
     *
     * @param string $payload Raw request body
     * @param string $sigHeader Stripe-Signature header value
     * @return array ['success' => bool, 'event_id' => string, 'message' => string]
     */
    public function handleWebhook(string $payload, string $sigHeader): array
    {
        try {
            if (empty($this->webhookSecret)) {
                throw new Exception('STRIPE_WEBHOOK_SECRET not configured');
            }

            // Verify signature manually (no stripe-php dependency required)
            $event = $this->verifySignature($payload, $sigHeader);

            // Idempotency: check if already processed
            if ($this->isAlreadyProcessed($event['id'])) {
                return ['success' => true, 'event_id' => $event['id'], 'message' => 'Already processed'];
            }

            // Store raw event
            $this->storeRawEvent($event, $payload);

            // Process the event
            $result = $this->processEvent($event);

            // Mark as processed
            $this->markProcessed($event['id'], $event['type'], $result);

            return [
                'success' => true,
                'event_id' => $event['id'],
                'message' => 'Processed: ' . $event['type'],
                'result' => $result,
            ];

        } catch (Exception $e) {
            error_log("[StripeWebhook] " . $e->getMessage());
            return ['success' => false, 'event_id' => $event['id'] ?? 'unknown', 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify Stripe webhook signature.
     */
    private function verifySignature(string $payload, string $sigHeader): array
    {
        $elements = explode(',', $sigHeader);
        $signatureData = [];
        foreach ($elements as $element) {
            $parts = explode('=', trim($element), 2);
            if (count($parts) === 2) {
                $signatureData[$parts[0]] = $parts[1];
            }
        }

        $timestamp = $signatureData['t'] ?? '';
        $signature = $signatureData['v1'] ?? '';

        if (empty($timestamp) || empty($signature)) {
            throw new Exception('Invalid signature header');
        }

        // Check timestamp tolerance (5 minutes)
        if (abs(time() - (int) $timestamp) > 300) {
            throw new Exception('Webhook timestamp too old');
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception('Invalid signature');
        }

        return json_decode($payload, true);
    }

    private function isAlreadyProcessed(string $eventId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM processed_stripe_events WHERE stripe_event_id = ?");
        $stmt->execute([$eventId]);
        return (bool) $stmt->fetchColumn();
    }

    private function storeRawEvent(array $event, string $rawPayload): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO processed_stripe_events (stripe_event_id, event_type, payload, processed_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$event['id'], $event['type'], $rawPayload]);
    }

    private function markProcessed(string $eventId, string $type, array $result): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE processed_stripe_events
            SET event_type = ?, result = ?, processed_at = NOW()
            WHERE stripe_event_id = ?
        ");
        $stmt->execute([$type, json_encode($result), $eventId]);
    }

    /**
     * Process a Stripe event. Can be called directly for retry logic.
     */
    public function processEvent(array $event): array
    {
        $type = $event['type'] ?? '';
        $data = $event['data']['object'] ?? [];

        switch ($type) {
            case 'checkout.session.completed':
                return $this->handleCheckoutSessionCompleted($data);

            case 'invoice.payment_succeeded':
                return $this->handleInvoicePaymentSucceeded($data);

            case 'invoice.payment_failed':
                return $this->handleInvoicePaymentFailed($data);

            case 'customer.subscription.created':
                return $this->handleSubscriptionCreated($data);

            case 'customer.subscription.updated':
                return $this->handleSubscriptionUpdated($data);

            case 'customer.subscription.deleted':
                return $this->handleSubscriptionDeleted($data);

            case 'charge.refunded':
                return $this->handleChargeRefunded($data);

            case 'customer.created':
                return $this->handleCustomerCreated($data);

            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($data);

            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentFailed($data);

            default:
                return ['action' => 'ignored', 'reason' => 'Unhandled event type'];
        }
    }

    // ---- EVENT HANDLERS ----

    private function handleCheckoutSessionCompleted(array $session): array
    {
        $customerId = $session['customer'] ?? '';
        $subscriptionId = $session['subscription'] ?? '';
        $tenantId = $this->resolveTenantFromCustomer($customerId);

        if (!$tenantId) {
            return ['action' => 'ignored', 'reason' => 'Tenant not found for customer'];
        }

        // Activate subscription
        $stmt = $this->pdo->prepare("
            UPDATE pos_subscriptions
            SET status = 'active', stripe_subscription_id = ?, updated_at = NOW()
            WHERE tenant_id = ? AND status IN ('trialing', 'pending', 'past_due', 'expired')
        ");
        $stmt->execute([$subscriptionId, $tenantId]);

        // Reactivate tenant if it was suspended
        $this->reactivateTenant($tenantId);

        // Insert subscription history
        $this->pdo->prepare("
            INSERT INTO subscription_history (tenant_id, event_type, new_plan_id, metadata, created_at)
            VALUES (?, 'subscription_activated', NULL, ?, NOW())
        ")->execute([$tenantId, json_encode(['stripe_session' => $session['id']])]);

        return ['action' => 'activated', 'tenant_id' => $tenantId];
    }

    private function handleInvoicePaymentSucceeded(array $invoice): array
    {
        $subscriptionId = $invoice['subscription'] ?? '';
        $tenantId = $this->resolveTenantFromSubscription($subscriptionId);

        if (!$tenantId) {
            return ['action' => 'ignored', 'reason' => 'Tenant not found'];
        }

        $amount = ($invoice['amount_paid'] ?? 0) / 100;
        $currency = $invoice['currency'] ?? 'usd';

        // Record payment
        $this->pdo->prepare("
            INSERT INTO pos_invoices (tenant_id, amount, currency, status, description, paid_at, created_at)
            VALUES (?, ?, ?, 'paid', ?, NOW(), NOW())
        ")->execute([$tenantId, $amount, strtoupper($currency), 'Stripe invoice ' . ($invoice['number'] ?? $invoice['id'])]);

        // Update subscription status
        $this->pdo->prepare("
            UPDATE pos_subscriptions SET status = 'active', updated_at = NOW()
            WHERE tenant_id = ? AND stripe_subscription_id = ?
        ")->execute([$tenantId, $subscriptionId]);

        // Reactivate tenant if it was suspended
        $this->reactivateTenant($tenantId);

        return ['action' => 'payment_recorded', 'amount' => $amount, 'tenant_id' => $tenantId];
    }

    private function handleInvoicePaymentFailed(array $invoice): array
    {
        $subscriptionId = $invoice['subscription'] ?? '';
        $tenantId = $this->resolveTenantFromSubscription($subscriptionId);

        if (!$tenantId) {
            return ['action' => 'ignored', 'reason' => 'Tenant not found'];
        }

        // Mark subscription past_due, trigger dunning
        $this->pdo->prepare("
            UPDATE pos_subscriptions SET status = 'past_due', updated_at = NOW()
            WHERE tenant_id = ? AND stripe_subscription_id = ?
        ")->execute([$tenantId, $subscriptionId]);

        // Queue dunning email
        $this->queueDunningEmail($tenantId, 1);

        return ['action' => 'dunning_triggered', 'tenant_id' => $tenantId];
    }

    private function handleSubscriptionCreated(array $sub): array
    {
        $customerId = $sub['customer'] ?? '';
        $tenantId = $this->resolveTenantFromCustomer($customerId);
        if (!$tenantId) return ['action' => 'ignored'];

        $this->pdo->prepare("
            INSERT INTO subscription_history (tenant_id, event_type, metadata, created_at)
            VALUES (?, 'subscription_created', ?, NOW())
        ")->execute([$tenantId, json_encode(['stripe_subscription_id' => $sub['id'], 'status' => $sub['status']])]);

        return ['action' => 'logged'];
    }

    private function handleSubscriptionUpdated(array $sub): array
    {
        $customerId = $sub['customer'] ?? '';
        $tenantId = $this->resolveTenantFromCustomer($customerId);
        if (!$tenantId) return ['action' => 'ignored'];

        $newStatus = $sub['status'] ?? '';
        $this->pdo->prepare("
            UPDATE pos_subscriptions SET status = ?, updated_at = NOW()
            WHERE tenant_id = ? AND stripe_subscription_id = ?
        ")->execute([$newStatus, $tenantId, $sub['id']]);

        $this->pdo->prepare("
            INSERT INTO subscription_history (tenant_id, event_type, metadata, created_at)
            VALUES (?, 'subscription_updated', ?, NOW())
        ")->execute([$tenantId, json_encode(['new_status' => $newStatus, 'stripe_subscription_id' => $sub['id']])]);

        return ['action' => 'updated', 'status' => $newStatus];
    }

    private function handleSubscriptionDeleted(array $sub): array
    {
        $customerId = $sub['customer'] ?? '';
        $tenantId = $this->resolveTenantFromCustomer($customerId);
        if (!$tenantId) return ['action' => 'ignored'];

        $this->pdo->prepare("
            UPDATE pos_subscriptions SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW()
            WHERE tenant_id = ? AND stripe_subscription_id = ?
        ")->execute([$tenantId, $sub['id']]);

        $this->pdo->prepare("
            INSERT INTO subscription_history (tenant_id, event_type, metadata, created_at)
            VALUES (?, 'subscription_cancelled', ?, NOW())
        ")->execute([$tenantId, json_encode(['stripe_subscription_id' => $sub['id']])]);

        return ['action' => 'cancelled'];
    }

    private function handleChargeRefunded(array $charge): array
    {
        $paymentIntent = $charge['payment_intent'] ?? '';
        $amountRefunded = ($charge['amount_refunded'] ?? 0) / 100;

        // Find invoice by payment intent
        $stmt = $this->pdo->prepare("
            SELECT id, tenant_id FROM pos_invoices
            WHERE description LIKE ? AND status = 'paid'
            LIMIT 1
        ");
        $stmt->execute(["%{$paymentIntent}%"]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($invoice) {
            $this->pdo->prepare("
                UPDATE pos_invoices SET status = 'refunded', updated_at = NOW()
                WHERE id = ?
            ")->execute([$invoice['id']]);

            $this->pdo->prepare("
                INSERT INTO credits (tenant_id, amount, remaining_amount, currency, type, reason, created_at)
                VALUES (?, ?, ?, 'USD', 'refund', 'Stripe refund', NOW())
            ")->execute([$invoice['tenant_id'], $amountRefunded, $amountRefunded]);
        }

        return ['action' => 'refunded', 'amount' => $amountRefunded];
    }

    private function handleCustomerCreated(array $customer): array
    {
        $email = $customer['email'] ?? '';
        $stripeCustomerId = $customer['id'] ?? '';

        // Link to tenant by email
        $stmt = $this->pdo->prepare("SELECT id FROM pos_tenants WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $tenantId = $stmt->fetchColumn();

        if ($tenantId) {
            $this->pdo->prepare("
                UPDATE pos_tenants SET stripe_customer_id = ?, updated_at = NOW() WHERE id = ?
            ")->execute([$stripeCustomerId, $tenantId]);
        }

        return ['action' => 'linked', 'tenant_id' => $tenantId];
    }

    private function handlePaymentIntentSucceeded(array $pi): array
    {
        return ['action' => 'logged', 'id' => $pi['id'] ?? ''];
    }

    private function handlePaymentIntentFailed(array $pi): array
    {
        return ['action' => 'logged_failure', 'id' => $pi['id'] ?? ''];
    }

    // ---- HELPERS ----

    /**
     * Reactivate a suspended tenant after successful payment.
     */
    private function reactivateTenant(int $tenantId): void
    {
        try {
            $this->pdo->prepare("
                UPDATE pos_tenants
                SET status = 'active', is_suspended = 0, updated_at = NOW()
                WHERE id = ? AND (status = 'suspended' OR is_suspended = 1)
            ")->execute([$tenantId]);
        } catch (Exception $e) {
            error_log("[StripeWebhook] Failed to reactivate tenant {$tenantId}: " . $e->getMessage());
        }
    }

    private function resolveTenantFromCustomer(string $customerId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM pos_tenants WHERE stripe_customer_id = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function resolveTenantFromSubscription(string $subscriptionId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT tenant_id FROM pos_subscriptions WHERE stripe_subscription_id = ? LIMIT 1");
        $stmt->execute([$subscriptionId]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function queueDunningEmail(int $tenantId, int $day): void
    {
        $queue = new \JDH\POS\Jobs\JobQueue($this->pdo);
        $queue->push('dunning_email', [
            'tenant_id' => $tenantId,
            'day' => $day,
        ], 0, $tenantId);
    }
}
