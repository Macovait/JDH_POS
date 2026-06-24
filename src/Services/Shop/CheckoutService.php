<?php
/**
 * CheckoutService — Multi-step checkout flow
 * Handles shipping, payment initiation (M-Pesa), order creation
 */

namespace Services\Shop;

use JDH\Modules\Payments\Gateways\MpesaGateway;

class CheckoutService {
    private PDO $pdo;
    private int $tenantId;
    private CartService $cart;
    private InventoryService $inventory;
    private OrderService $order;

    public function __construct(PDO $pdo, int $tenantId, string $sessionId) {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->cart = new CartService($pdo, $tenantId, $sessionId);
        $this->inventory = new InventoryService($pdo, $tenantId);
        $this->order = new OrderService($pdo, $tenantId);
    }

    /**
     * Step 1: Validate cart and get checkout summary
     */
    public function getCheckoutSummary(): array {
        $cart = $this->cart->getCart();
        $items = $cart['items'] ?? [];

        if (empty($items)) {
            return ['success' => false, 'error' => 'Your cart is empty'];
        }

        // Validate all items still have stock
        $productIds = array_column($items, 'product_id');
        $stockMap = $this->inventory->getAvailableStockBatch($productIds);

        $invalidItems = [];
        foreach ($items as $item) {
            $available = $stockMap[(int) $item['product_id']] ?? 0;
            if ($item['quantity'] > $available) {
                $invalidItems[] = [
                    'product_id' => $item['product_id'],
                    'name' => $item['product_name'],
                    'requested' => $item['quantity'],
                    'available' => $available
                ];
            }
        }

        if (!empty($invalidItems)) {
            return ['success' => false, 'error' => 'Some items are no longer available', 'invalid_items' => $invalidItems];
        }

        // Get shipping zones
        $stmt = $this->pdo->prepare("
            SELECT * FROM shipping_zones
            WHERE tenant_id = ? AND is_active = 1
            ORDER BY sort_order ASC
        ");
        $stmt->execute([$this->tenantId]);
        $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get customer addresses if logged in
        $addresses = [];
        if (!empty($cart['customer_id'])) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM customer_addresses
                WHERE tenant_id = ? AND customer_id = ?
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->execute([$this->tenantId, $cart['customer_id']]);
            $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'success' => true,
            'cart' => $cart,
            'shipping_zones' => $zones,
            'addresses' => $addresses
        ];
    }

    /**
     * Step 2: Calculate shipping cost
     */
    public function calculateShipping(int $zoneId, float $cartWeight = 0, float $subtotal = 0): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM shipping_rates
            WHERE zone_id = ? AND tenant_id = ? AND is_active = 1
              AND min_weight <= ? AND (max_weight IS NULL OR max_weight >= ?)
              AND min_order_value <= ?
            ORDER BY rate ASC
            LIMIT 1
        ");
        $stmt->execute([$zoneId, $this->tenantId, $cartWeight, $cartWeight, $subtotal]);
        $rate = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rate) {
            return ['success' => false, 'error' => 'No shipping rate available for this location'];
        }

        // Check free shipping threshold
        $settings = $this->getStorefrontSettings();
        $freeThreshold = (float) ($settings['free_shipping_threshold'] ?? 0);
        if ($freeThreshold > 0 && $subtotal >= $freeThreshold) {
            $rate['rate'] = 0;
            $rate['free_shipping_applied'] = true;
        }

        $shippingCost = (float) $rate['rate'];
        if ($cartWeight > 0 && $rate['per_kg_rate'] > 0) {
            $shippingCost += $cartWeight * (float) $rate['per_kg_rate'];
        }

        return [
            'success' => true,
            'rate' => $rate,
            'shipping_cost' => round($shippingCost, 2),
            'estimated_days' => $rate['estimated_days'] ?? 3
        ];
    }

    /**
     * Step 3: Create order and initiate payment
     */
    public function placeOrder(array $data): array {
        $cart = $this->cart->getCart();
        $items = $cart['items'] ?? [];
        if (empty($items)) {
            return ['success' => false, 'error' => 'Cart is empty'];
        }

        // Validate stock one final time
        $productIds = array_column($items, 'product_id');
        $stockMap = $this->inventory->getAvailableStockBatch($productIds);
        foreach ($items as $item) {
            $available = $stockMap[(int) $item['product_id']] ?? 0;
            if ($item['quantity'] > $available) {
                return ['success' => false, 'error' => "'{$item['product_name']}' is now out of stock"];
            }
        }

        // Validate required fields
        $required = ['customer_name', 'customer_phone', 'customer_email', 'shipping_address'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'error' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
            }
        }

        // Get shipping cost
        $shippingCost = 0;
        if (!empty($data['shipping_zone_id'])) {
            $weight = 0;
            foreach ($items as $item) $weight += (float) ($item['weight_kg'] ?? 0) * $item['quantity'];
            $shipping = $this->calculateShipping((int) $data['shipping_zone_id'], $weight, (float) $cart['subtotal']);
            if ($shipping['success']) {
                $shippingCost = $shipping['shipping_cost'];
            }
        }

        // Calculate totals
        $subtotal = (float) $cart['subtotal'];
        $discount = (float) ($cart['coupon_discount'] ?? 0);
        $tax = $this->calculateTax($subtotal - $discount);
        $total = $subtotal - $discount + $shippingCost + $tax;

        // Generate order number
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // Create order
        $orderData = [
            'tenant_id' => $this->tenantId,
            'order_number' => $orderNumber,
            'customer_id' => $cart['customer_id'] ?? null,
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'],
            'customer_name' => $data['customer_name'],
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'coupon_code' => $cart['coupon_code'] ?? null,
            'shipping_cost' => $shippingCost,
            'tax_amount' => $tax,
            'total' => $total,
            'currency' => $cart['currency'] ?? 'KES',
            'shipping_address_snapshot' => json_encode($data['shipping_address']),
            'billing_address_snapshot' => json_encode($data['billing_address'] ?? $data['shipping_address']),
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $data['payment_method'] ?? 'cod',
            'shipping_method' => $data['shipping_method'] ?? null,
            'notes' => $data['notes'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'source' => 'web'
        ];

        $orderId = $this->order->createOrder($orderData, $items);

        if (!$orderId) {
            return ['success' => false, 'error' => 'Failed to create order'];
        }

        // Commit inventory (deduct from POS)
        $this->inventory->commitReservation((int) $cart['id'], $orderId);

        // Update coupon usage
        if (!empty($cart['coupon_code'])) {
            $this->recordCouponUsage($cart['coupon_code'], $orderId, $cart['customer_id'] ?? null, $discount);
        }

        // Clear cart
        $this->cart->clearCart($orderId);

        // Initiate payment if M-Pesa
        $paymentResult = ['status' => 'pending'];
        if ($data['payment_method'] === 'mpesa' && !empty($data['mpesa_phone'])) {
            $paymentResult = $this->initiateMpesaPayment($orderId, $total, $data['mpesa_phone']);
        }

        // Send notifications
        $this->sendOrderNotifications($orderId, $orderData);

        return [
            'success' => true,
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'total' => $total,
            'payment' => $paymentResult
        ];
    }

    /**
     * Initiate M-Pesa STK Push using MpesaGateway
     */
    private function initiateMpesaPayment(int $orderId, float $amount, string $phone): array {
        // Validate phone format
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 9) $phone = '254' . $phone;
        if (strlen($phone) === 10 && $phone[0] === '0') $phone = '254' . substr($phone, 1);
        if (!preg_match('/^2547[0-9]{8}$/', $phone)) {
            return ['success' => false, 'error' => 'Invalid M-Pesa phone number'];
        }

        // Get M-Pesa credentials from storefront settings
        $settings = $this->getStorefrontSettings();
        $consumerKey = $settings['mpesa_consumer_key'] ?? '';
        $consumerSecret = $settings['mpesa_consumer_secret'] ?? '';
        $shortcode = $settings['mpesa_shortcode'] ?? '';
        $passkey = $settings['mpesa_passkey'] ?? '';

        if (empty($consumerKey) || empty($shortcode) || empty($passkey)) {
            return ['success' => false, 'error' => 'M-Pesa not configured'];
        }

        // Build gateway config
        $gatewayConfig = [
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
            'shortcode' => $shortcode,
            'passkey' => $passkey,
            'test_mode' => ($settings['mpesa_env'] ?? 'sandbox') !== 'production',
            'callback_url' => $settings['mpesa_callback_url'] ?? '',
        ];

        $gateway = new MpesaGateway($gatewayConfig, $this->pdo);

        // Initiate STK Push
        $result = $gateway->stkPush([
            'amount' => (int) $amount,
            'phone' => $phone,
            'account_reference' => 'ORD-' . $orderId,
            'description' => 'Online order payment'
        ]);

        if ($result['success']) {
            // Store pending transaction with request IDs
            $stmt = $this->pdo->prepare("
                INSERT INTO mpesa_transactions (tenant_id, order_id, amount, phone_number, status, merchant_request_id, checkout_request_id)
                VALUES (?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([
                $this->tenantId,
                $orderId,
                $amount,
                $phone,
                $result['merchant_request_id'] ?? null,
                $result['checkout_request_id'] ?? null
            ]);
            $txId = $this->pdo->lastInsertId();

            return [
                'success' => true,
                'transaction_id' => $txId,
                'checkout_request_id' => $result['checkout_request_id'],
                'merchant_request_id' => $result['merchant_request_id'],
                'status' => 'pending',
                'message' => $result['customer_message'] ?? 'STK Push sent to ' . $phone,
                'phone' => $phone,
                'amount' => $amount
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'M-Pesa STK Push failed',
            'phone' => $phone,
            'amount' => $amount
        ];
    }

    /**
     * Handle M-Pesa callback
     */
    public function handleMpesaCallback(array $callbackData): array {
        $checkoutRequestId = $callbackData['CheckoutRequestID'] ?? '';
        $resultCode = $callbackData['ResultCode'] ?? '1';
        $resultDesc = $callbackData['ResultDesc'] ?? '';
        $receiptNumber = $callbackData['MpesaReceiptNumber'] ?? '';
        $amount = $callbackData['Amount'] ?? 0;
        $phone = $callbackData['PhoneNumber'] ?? '';

        // Find transaction
        $stmt = $this->pdo->prepare("
            SELECT id, order_id FROM mpesa_transactions
            WHERE checkout_request_id = ? AND tenant_id = ?
            LIMIT 1
        ");
        $stmt->execute([$checkoutRequestId, $this->tenantId]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tx) {
            return ['success' => false, 'error' => 'Transaction not found'];
        }

        $status = ($resultCode === '0') ? 'completed' : 'failed';
        $paymentStatus = ($resultCode === '0') ? 'paid' : 'failed';

        // Update transaction
        $stmt = $this->pdo->prepare("
            UPDATE mpesa_transactions
            SET status = ?, result_code = ?, result_description = ?, mpesa_receipt_number = ?,
                transaction_date = NOW(), callback_received_at = NOW(), raw_callback = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $resultCode, $resultDesc, $receiptNumber, json_encode($callbackData), $tx['id']]);

        // Update order
        if ($resultCode === '0' && $tx['order_id']) {
            $this->pdo->prepare("
                UPDATE online_orders
                SET payment_status = ?, payment_reference = ?, status = 'processing'
                WHERE id = ? AND tenant_id = ?
            ")->execute([$paymentStatus, $receiptNumber, $tx['order_id'], $this->tenantId]);

            // Sync to POS
            $this->order->syncOrderToPOS((int) $tx['order_id']);
        }

        return ['success' => true, 'status' => $status];
    }

    // ─── Helpers ───

    private function getStorefrontSettings(): array {
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
        $stmt->execute([$this->tenantId]);
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    /**
     * Calculate tax based on app config or storefront override
     */
    private function calculateTax(float $taxableAmount): float {
        $settings = $this->getStorefrontSettings();
        $taxRate = 0;

        if (isset($settings['tax_rate']) && is_numeric($settings['tax_rate'])) {
            $taxRate = (float) $settings['tax_rate'];
        } else {
            // Fallback to app config default tax rate
            $configFile = dirname(__DIR__, 3) . '/config/app.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
                $taxRate = $config['business']['default_tax_rate'] ?? 0;
            }
        }

        if ($taxRate <= 0) {
            return 0;
        }

        return round($taxableAmount * ($taxRate / 100), 2);
    }

    private function recordCouponUsage(string $code, int $orderId, ?int $customerId, float $discount): void {
        $stmt = $this->pdo->prepare("SELECT id FROM coupons WHERE tenant_id = ? AND code = ? LIMIT 1");
        $stmt->execute([$this->tenantId, $code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$coupon) return;

        $this->pdo->prepare("
            INSERT INTO coupon_usage (tenant_id, coupon_id, order_id, customer_id, discount_amount)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$this->tenantId, $coupon['id'], $orderId, $customerId, $discount]);

        $this->pdo->prepare("
            UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ? AND tenant_id = ?
        ")->execute([$coupon['id'], $this->tenantId]);
    }

    private function sendOrderNotifications(int $orderId, array $orderData): void {
        // Customer notification
        $this->pdo->prepare("
            INSERT INTO notifications (tenant_id, customer_id, type, channel, title, message, action_url)
            VALUES (?, ?, 'order', 'in_app', ?, ?, ?)
        ")->execute([
            $this->tenantId, $orderData['customer_id'],
            'Order Received',
            "Your order {$orderData['order_number']} has been received and is being processed.",
            "/shop/account/orders.php?id={$orderId}"
        ]);

        // Admin notification (future: push to staff dashboard)
        // Could integrate with existing notification system
    }
}
