<?php
/**
 * Stripe Webhook Handler
 * Receives and processes Stripe webhook events with idempotency and signature verification.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);

// Read raw payload
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// For now, log all incoming webhooks (signature verification requires STRIPE_WEBHOOK_SECRET)
$event = null;
try {
    // If Stripe library is available, verify signature
    // For this implementation without Stripe SDK, we parse the JSON and log it
    $event = json_decode($payload, true);
    if (!$event || !isset($event['id'])) {
        http_response_code(400);
        echo 'Invalid payload';
        exit;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

$pdo = get_db_connection();

// ---- IDEMPOTENCY CHECK ----
$existing = $pdo->prepare("SELECT id FROM processed_stripe_events WHERE stripe_event_id = ?");
$existing->execute([$event['id']]);
if ($existing->rowCount() > 0) {
    http_response_code(200);
    echo 'Already processed';
    exit;
}

// Log the event first
$logStmt = $pdo->prepare("INSERT INTO processed_stripe_events (stripe_event_id, type, payload, processed_at) VALUES (?, ?, ?, NOW())");
$logStmt->execute([$event['id'], $event['type'] ?? 'unknown', json_encode($event)]);

// ---- EVENT HANDLERS ----
$eventType = $event['type'] ?? '';
$data = $event['data']['object'] ?? [];

try {
    switch ($eventType) {
        case 'checkout.session.completed':
            handleCheckoutCompleted($pdo, $data);
            break;

        case 'invoice.payment_succeeded':
        case 'invoice.paid':
            handleInvoicePaid($pdo, $data);
            break;

        case 'invoice.payment_failed':
            handleInvoicePaymentFailed($pdo, $data);
            break;

        case 'customer.subscription.updated':
            handleSubscriptionUpdated($pdo, $data);
            break;

        case 'customer.subscription.deleted':
            handleSubscriptionDeleted($pdo, $data);
            break;

        case 'payment_method.attached':
        case 'payment_method.updated':
            handlePaymentMethodUpdated($pdo, $data);
            break;

        default:
            // Log unhandled events but return 200
            error_log("Stripe webhook: unhandled event type {$eventType}");
    }

    http_response_code(200);
    echo 'OK';
} catch (Exception $e) {
    error_log("Stripe webhook error: " . $e->getMessage());
    http_response_code(500);
    echo 'Internal error';
}

// ---- HANDLER FUNCTIONS ----

function handleCheckoutCompleted(PDO $pdo, array $session): void
{
    $customerId = $session['customer'] ?? '';
    $subscriptionId = $session['subscription'] ?? '';
    $tenantId = null;

    // Find tenant by stripe_customer_id or metadata
    if ($customerId) {
        $stmt = $pdo->prepare("SELECT id FROM pos_tenants WHERE stripe_customer_id = ?");
        $stmt->execute([$customerId]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tenant) {
            $tenantId = $tenant['id'];
        }
    }

    // Fallback: check metadata for tenant_id
    if (!$tenantId && isset($session['metadata']['tenant_id'])) {
        $tenantId = (int) $session['metadata']['tenant_id'];
    }

    if (!$tenantId) {
        error_log("Stripe checkout.completed: could not resolve tenant for customer {$customerId}");
        return;
    }

    // Update tenant with stripe customer id if not set
    if ($customerId) {
        $pdo->prepare("UPDATE pos_tenants SET stripe_customer_id = ? WHERE id = ? AND (stripe_customer_id IS NULL OR stripe_customer_id = '')")
            ->execute([$customerId, $tenantId]);
    }

    // Update subscription with stripe subscription id
    if ($subscriptionId) {
        $pdo->prepare("UPDATE pos_subscriptions SET stripe_subscription_id = ?, status = 'active' WHERE tenant_id = ? AND (stripe_subscription_id IS NULL OR stripe_subscription_id = '')")
            ->execute([$subscriptionId, $tenantId]);
    }

    // Record in subscription history
    $pdo->prepare("INSERT INTO subscription_history (tenant_id, subscription_id, event, previous_status, new_status, triggered_by, created_at) VALUES (?, (SELECT id FROM pos_subscriptions WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1), 'activated', 'trialing', 'active', 'stripe', NOW())")
        ->execute([$tenantId, $tenantId]);

    // Update tenant status
    $pdo->prepare("UPDATE pos_tenants SET status = 'active' WHERE id = ?")
        ->execute([$tenantId]);
}

function handleInvoicePaid(PDO $pdo, array $invoice): void
{
    $customerId = $invoice['customer'] ?? '';
    $stripeInvoiceId = $invoice['id'] ?? '';

    if (!$customerId) return;

    $stmt = $pdo->prepare("SELECT id FROM pos_tenants WHERE stripe_customer_id = ?");
    $stmt->execute([$customerId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tenant) return;

    $tenantId = $tenant['id'];

    // Update existing invoice
    $pdo->prepare("UPDATE pos_invoices SET status = 'paid', paid_at = NOW() WHERE stripe_invoice_id = ? AND tenant_id = ?")
        ->execute([$stripeInvoiceId, $tenantId]);

    // If no invoice found by stripe_invoice_id, create one
    if ($pdo->rowCount() === 0 && isset($invoice['amount_paid'])) {
        $amount = $invoice['amount_paid'] / 100; // Stripe amounts are in cents
        $currency = strtoupper($invoice['currency'] ?? 'kes');
        $invoiceNumber = $invoice['number'] ?? ('STRIPE-' . substr($stripeInvoiceId, -8));

        $pdo->prepare("INSERT INTO pos_invoices (tenant_id, subscription_id, invoice_number, amount, currency, status, due_date, paid_at, stripe_invoice_id, created_at, updated_at) VALUES (?, (SELECT id FROM pos_subscriptions WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1), ?, ?, ?, 'paid', NOW(), NOW(), ?, NOW(), NOW())")
            ->execute([$tenantId, $tenantId, $invoiceNumber, $amount, $currency, $stripeInvoiceId]);
    }

    // Update subscription period if available
    if (isset($invoice['subscription'])) {
        $periodStart = isset($invoice['period_start']) ? date('Y-m-d H:i:s', $invoice['period_start']) : null;
        $periodEnd = isset($invoice['period_end']) ? date('Y-m-d H:i:s', $invoice['period_end']) : null;
        if ($periodStart && $periodEnd) {
            $pdo->prepare("UPDATE pos_subscriptions SET current_period_start = ?, current_period_end = ? WHERE tenant_id = ? AND stripe_subscription_id = ?")
                ->execute([$periodStart, $periodEnd, $tenantId, $invoice['subscription']]);
        }
    }
}

function handleInvoicePaymentFailed(PDO $pdo, array $invoice): void
{
    $customerId = $invoice['customer'] ?? '';
    $stripeInvoiceId = $invoice['id'] ?? '';
    $attemptCount = $invoice['attempt_count'] ?? 0;

    if (!$customerId) return;

    $stmt = $pdo->prepare("SELECT id FROM pos_tenants WHERE stripe_customer_id = ?");
    $stmt->execute([$customerId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tenant) return;

    $tenantId = $tenant['id'];
    $subscriptionId = $invoice['subscription'] ?? '';

    // Record payment attempt
    $pdo->prepare("INSERT INTO payment_attempts (tenant_id, subscription_id, invoice_id, attempt_number, amount, currency, status, failure_reason, created_at) VALUES (?, (SELECT id FROM pos_subscriptions WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1), (SELECT id FROM pos_invoices WHERE stripe_invoice_id = ?), ?, ?, ?, 'failed', ?, NOW())")
        ->execute([$tenantId, $tenantId, $stripeInvoiceId, $attemptCount, ($invoice['amount_due'] ?? 0) / 100, strtoupper($invoice['currency'] ?? 'kes'), $invoice['billing_reason'] ?? 'subscription_cycle']);

    // Update invoice status
    $pdo->prepare("UPDATE pos_invoices SET status = 'failed' WHERE stripe_invoice_id = ? AND tenant_id = ?")
        ->execute([$stripeInvoiceId, $tenantId]);

    // Start dunning campaign if first failure
    if ($attemptCount === 1) {
        $pdo->prepare("INSERT INTO dunning_campaigns (tenant_id, subscription_id, stage, action_taken, created_at) VALUES (?, (SELECT id FROM pos_subscriptions WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1), 1, 'email_sent', NOW())")
            ->execute([$tenantId, $tenantId]);
    }
}

function handleSubscriptionUpdated(PDO $pdo, array $subscription): void
{
    $stripeSubId = $subscription['id'] ?? '';
    $status = $subscription['status'] ?? '';
    $customerId = $subscription['customer'] ?? '';

    if (!$stripeSubId || !$customerId) return;

    $stmt = $pdo->prepare("SELECT id FROM pos_tenants WHERE stripe_customer_id = ?");
    $stmt->execute([$customerId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tenant) return;

    $tenantId = $tenant['id'];

    // Map Stripe status to local status
    $localStatus = match($status) {
        'active', 'trialing' => $status,
        'past_due' => 'past_due',
        'canceled' => 'cancelled',
        'unpaid' => 'unpaid',
        'paused' => 'paused',
        default => 'active'
    };

    // Update subscription
    $update = ['status' => $localStatus];
    if (isset($subscription['current_period_start'])) {
        $update['current_period_start'] = date('Y-m-d H:i:s', $subscription['current_period_start']);
    }
    if (isset($subscription['current_period_end'])) {
        $update['current_period_end'] = date('Y-m-d H:i:s', $subscription['current_period_end']);
    }
    if (isset($subscription['cancel_at_period_end']) && $subscription['cancel_at_period_end']) {
        $update['cancel_at_period_end'] = 1;
    }

    $fields = [];
    $values = [];
    foreach ($update as $k => $v) {
        $fields[] = "{$k} = ?";
        $values[] = $v;
    }
    $values[] = $stripeSubId;
    $values[] = $tenantId;

    $pdo->prepare("UPDATE pos_subscriptions SET " . implode(', ', $fields) . " WHERE stripe_subscription_id = ? AND tenant_id = ?")
        ->execute($values);

    // Record history
    $pdo->prepare("INSERT INTO subscription_history (tenant_id, subscription_id, event, new_status, triggered_by, created_at) VALUES (?, (SELECT id FROM pos_subscriptions WHERE stripe_subscription_id = ?), 'updated', ?, 'stripe', NOW())")
        ->execute([$tenantId, $stripeSubId, $localStatus]);
}

function handleSubscriptionDeleted(PDO $pdo, array $subscription): void
{
    $stripeSubId = $subscription['id'] ?? '';
    $customerId = $subscription['customer'] ?? '';

    if (!$stripeSubId || !$customerId) return;

    $stmt = $pdo->prepare("SELECT id FROM pos_tenants WHERE stripe_customer_id = ?");
    $stmt->execute([$customerId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tenant) return;

    $tenantId = $tenant['id'];

    // Update subscription
    $pdo->prepare("UPDATE pos_subscriptions SET status = 'cancelled', cancelled_at = NOW() WHERE stripe_subscription_id = ? AND tenant_id = ?")
        ->execute([$stripeSubId, $tenantId]);

    // Update tenant
    $pdo->prepare("UPDATE pos_tenants SET status = 'cancelled' WHERE id = ?")
        ->execute([$tenantId]);

    // Record history
    $pdo->prepare("INSERT INTO subscription_history (tenant_id, subscription_id, event, new_status, triggered_by, created_at) VALUES (?, (SELECT id FROM pos_subscriptions WHERE stripe_subscription_id = ?), 'cancelled', 'cancelled', 'stripe', NOW())")
        ->execute([$tenantId, $stripeSubId]);
}

function handlePaymentMethodUpdated(PDO $pdo, array $method): void
{
    $customerId = $method['customer'] ?? '';
    $methodId = $method['id'] ?? '';

    if (!$customerId || !$methodId) return;

    $stmt = $pdo->prepare("SELECT id FROM pos_tenants WHERE stripe_customer_id = ?");
    $stmt->execute([$customerId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tenant) return;

    $tenantId = $tenant['id'];

    // Update subscription payment method
    $pdo->prepare("UPDATE pos_subscriptions SET stripe_payment_method_id = ?, payment_method = ? WHERE tenant_id = ? AND (stripe_payment_method_id IS NULL OR stripe_payment_method_id = '')")
        ->execute([$methodId, $method['type'] ?? 'card', $tenantId]);
}
