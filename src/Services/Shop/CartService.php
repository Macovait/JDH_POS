<?php
/**
 * CartService — E-commerce Shopping Cart Logic
 * Handles cart CRUD, stock reservation, coupon validation
 */

namespace Services\Shop;

class CartService {
    private PDO $pdo;
    private int $tenantId;
    private string $sessionId;
    private InventoryService $inventory;

    public function __construct(PDO $pdo, int $tenantId, string $sessionId) {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->sessionId = $sessionId;
        $this->inventory = new InventoryService($pdo, $tenantId);
    }

    /**
     * Get or create cart for current session
     */
    public function getCart(): array {
        $stmt = $this->pdo->prepare("SELECT * FROM carts WHERE tenant_id = ? AND session_id = ? LIMIT 1");
        $stmt->execute([$this->tenantId, $this->sessionId]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cart) {
            $stmt = $this->pdo->prepare("INSERT INTO carts (tenant_id, session_id, currency) VALUES (?, ?, 'KES')");
            $stmt->execute([$this->tenantId, $this->sessionId]);
            $cart = ['id' => $this->pdo->lastInsertId(), 'items' => []];
        } else {
            $cart['items'] = $this->getCartItems((int) $cart['id']);
        }

        return $cart;
    }

    /**
     * Get cart items with product snapshots
     */
    public function getCartItems(int $cartId): array {
        $stmt = $this->pdo->prepare("
            SELECT ci.*,
                   p.name as current_name, p.image as current_image,
                   p.active, p.deleted_at,
                   (SELECT COALESCE(SUM(stock), 0) FROM inventory WHERE product_id = p.id AND tenant_id = p.tenant_id) as current_stock
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id AND p.tenant_id = ci.tenant_id
            WHERE ci.cart_id = ?
            ORDER BY ci.created_at DESC
        ");
        $stmt->execute([$cartId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add item to cart with stock reservation
     */
    public function addItem(int $productId, int $quantity = 1): array {
        $cart = $this->getCart();
        $cartId = (int) $cart['id'];

        // Get product with current price and stock
        $product = $this->getProductForCart($productId);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found'];
        }
        if (!$product['active'] || !empty($product['deleted_at'])) {
            return ['success' => false, 'error' => 'Product is no longer available'];
        }

        // Check available stock (inventory minus reservations)
        $available = $this->inventory->getAvailableStock($productId);
        $currentQty = $this->getCartItemQuantity($cartId, $productId);
        $requestedTotal = $currentQty + $quantity;

        if ($requestedTotal > $available) {
            return [
                'success' => false,
                'error' => "Only {$available} units available. You have {$currentQty} in cart."
            ];
        }

        // Calculate price (use selling_price if on sale)
        $price = (!empty($product['selling_price']) && $product['selling_price'] > 0 && $product['selling_price'] < $product['price'])
            ? (float) $product['selling_price'] : (float) $product['price'];
        $subtotal = $price * $quantity;

        // Check if item already in cart
        if ($currentQty > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE cart_items
                SET quantity = quantity + ?, subtotal = unit_price * (quantity + ?), updated_at = NOW()
                WHERE cart_id = ? AND product_id = ?
            ");
            $stmt->execute([$quantity, $quantity, $cartId, $productId]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO cart_items (tenant_id, cart_id, product_id, quantity, unit_price, original_price, subtotal, product_name, product_sku, image_url, weight_kg)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->tenantId, $cartId, $productId, $quantity,
                $price, $product['price'], $subtotal,
                $product['name'], $product['sku'] ?? '',
                $product['image'] ?? '', $product['weight_kg'] ?? 0
            ]);
        }

        // Create/refresh stock reservation (30 min expiry)
        $this->inventory->reserveStock($productId, $quantity, $cartId);

        // Recalculate cart totals
        $this->recalculateCart($cartId);

        return ['success' => true, 'cart' => $this->getCart()];
    }

    /**
     * Update item quantity
     */
    public function updateItem(int $productId, int $quantity): array {
        $cart = $this->getCart();
        $cartId = (int) $cart['id'];

        if ($quantity <= 0) {
            return $this->removeItem($productId);
        }

        // Check stock
        $available = $this->inventory->getAvailableStock($productId);
        if ($quantity > $available) {
            return ['success' => false, 'error' => "Only {$available} units available"];
        }

        $stmt = $this->pdo->prepare("
            UPDATE cart_items
            SET quantity = ?, subtotal = unit_price * ?, updated_at = NOW()
            WHERE cart_id = ? AND product_id = ?
        ");
        $stmt->execute([$quantity, $quantity, $cartId, $productId]);

        // Refresh reservation
        $this->inventory->reserveStock($productId, $quantity, $cartId, true);
        $this->recalculateCart($cartId);

        return ['success' => true, 'cart' => $this->getCart()];
    }

    /**
     * Remove item from cart
     */
    public function removeItem(int $productId): array {
        $cart = $this->getCart();
        $cartId = (int) $cart['id'];

        $stmt = $this->pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cartId, $productId]);

        // Release reservation
        $this->inventory->releaseReservation($productId, $cartId);
        $this->recalculateCart($cartId);

        return ['success' => true, 'cart' => $this->getCart()];
    }

    /**
     * Apply coupon code
     */
    public function applyCoupon(string $code): array {
        $cart = $this->getCart();
        $cartId = (int) $cart['id'];

        $stmt = $this->pdo->prepare("
            SELECT * FROM coupons
            WHERE tenant_id = ? AND code = ? AND is_active = 1
              AND (start_date IS NULL OR start_date <= CURDATE())
              AND (end_date IS NULL OR end_date >= CURDATE())
            LIMIT 1
        ");
        $stmt->execute([$this->tenantId, $code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            return ['success' => false, 'error' => 'Invalid or expired coupon code'];
        }

        // Check usage limits
        if ($coupon['usage_limit'] && $coupon['usage_count'] >= $coupon['usage_limit']) {
            return ['success' => false, 'error' => 'Coupon usage limit reached'];
        }

        // Check minimum order
        if ($cart['subtotal'] < $coupon['min_order_value']) {
            return ['success' => false, 'error' => "Minimum order of KES {$coupon['min_order_value']} required"];
        }

        // Calculate discount
        $discount = 0;
        if ($coupon['discount_type'] === 'percentage') {
            $discount = $cart['subtotal'] * ($coupon['discount_value'] / 100);
            if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
                $discount = $coupon['max_discount'];
            }
        } elseif ($coupon['discount_type'] === 'fixed_amount') {
            $discount = min($coupon['discount_value'], $cart['subtotal']);
        } elseif ($coupon['discount_type'] === 'free_shipping') {
            $discount = $cart['shipping_cost'];
        }

        $stmt = $this->pdo->prepare("
            UPDATE carts SET coupon_code = ?, coupon_discount = ? WHERE id = ?
        ");
        $stmt->execute([$code, $discount, $cartId]);
        $this->recalculateCart($cartId);

        return ['success' => true, 'discount' => $discount, 'cart' => $this->getCart()];
    }

    /**
     * Remove coupon
     */
    public function removeCoupon(): array {
        $cart = $this->getCart();
        $stmt = $this->pdo->prepare("UPDATE carts SET coupon_code = NULL, coupon_discount = 0 WHERE id = ?");
        $stmt->execute([$cart['id']]);
        $this->recalculateCart((int) $cart['id']);
        return ['success' => true, 'cart' => $this->getCart()];
    }

    /**
     * Clear cart (after order placed)
     */
    public function clearCart(int $convertedToOrderId = 0): void {
        $cart = $this->getCart();
        $cartId = (int) $cart['id'];

        // Release all reservations
        $stmt = $this->pdo->prepare("SELECT product_id FROM cart_items WHERE cart_id = ?");
        $stmt->execute([$cartId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $this->inventory->releaseReservation((int) $pid, $cartId);
        }

        // Delete items and optionally mark cart as converted
        $this->pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);

        if ($convertedToOrderId > 0) {
            $this->pdo->prepare("UPDATE carts SET converted_to_order_id = ? WHERE id = ?")
                ->execute([$convertedToOrderId, $cartId]);
        } else {
            $this->pdo->prepare("DELETE FROM carts WHERE id = ?")->execute([$cartId]);
        }
    }

    // ─── Helpers ───

    private function getProductForCart(int $productId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT id, name, price, selling_price, image, sku, active, deleted_at, weight_kg
            FROM products
            WHERE id = ? AND tenant_id = ? AND active = 1 AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            LIMIT 1
        ");
        $stmt->execute([$productId, $this->tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getCartItemQuantity(int $cartId, int $productId): int {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cartId, $productId]);
        return (int) $stmt->fetchColumn();
    }

    private function recalculateCart(int $cartId): void {
        // Get all items
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(subtotal), 0) as subtotal, COALESCE(SUM(weight_kg * quantity), 0) as weight
            FROM cart_items WHERE cart_id = ?
        ");
        $stmt->execute([$cartId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $subtotal = (float) $row['subtotal'];
        $weight = (float) $row['weight'];

        // Get coupon discount
        $stmt = $this->pdo->prepare("SELECT coupon_discount FROM carts WHERE id = ?");
        $stmt->execute([$cartId]);
        $couponDiscount = (float) $stmt->fetchColumn();

        // Calculate totals
        $total = max(0, $subtotal - $couponDiscount);

        $stmt = $this->pdo->prepare("
            UPDATE carts
            SET subtotal = ?, total = ?, last_activity = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$subtotal, $total, $cartId]);
    }
}
