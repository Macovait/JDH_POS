<?php
/**
 * OrderService — Online Order Management & POS Sync
 * Creates orders, manages status, syncs to POS sales table
 */

namespace Services\Shop;

class OrderService {
    private PDO $pdo;
    private int $tenantId;

    public function __construct(PDO $pdo, int $tenantId) {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
    }

    /**
     * Create online order with items
     */
    public function createOrder(array $orderData, array $items): int {
        $this->pdo->beginTransaction();
        try {
            // Insert order
            $validColumns = [
                'tenant_id', 'customer_name', 'customer_phone', 'customer_email',
                'customer_id', 'delivery_address', 'payment_method', 'total', 'status',
                'items_json', 'paid_amount', 'created_at', 'updated_at'
            ];
            $orderData = array_intersect_key($orderData, array_flip($validColumns));
            $orderData['created_at'] = date('Y-m-d H:i:s');
            $orderData['updated_at'] = date('Y-m-d H:i:s');
            $cols = implode(', ', array_keys($orderData));
            $vals = implode(', ', array_fill(0, count($orderData), '?'));
            $stmt = $this->pdo->prepare("INSERT INTO online_orders ($cols) VALUES ($vals)");
            $stmt->execute(array_values($orderData));
            $orderId = (int) $this->pdo->lastInsertId();

            // Insert items
            foreach ($items as $item) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO online_order_items
                        (order_id, product_id, product_name, qty, price, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $orderId, $item['product_id'], $item['product_name'] ?? '',
                    $item['quantity'], $item['unit_price'] ?? $item['price'] ?? 0
                ]);
            }

            // Log status change
            $this->logStatusChange($orderId, null, 'pending', 'system', 'Order placed online');

            $this->pdo->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('Order creation failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Sync online order to POS sales table
     * This is the critical bridge between online and POS
     */
    public function syncOrderToPOS(int $orderId): ?int {
        $order = $this->getOrder($orderId);
        if (!$order) return null;
        if (array_key_exists('pos_sale_id', $order)) {
            if (!empty($order['pos_sale_id'])) return (int) $order['pos_sale_id'];
        } else {
            return null;
        }

        // Get default branch
        $stmt = $this->pdo->prepare("
            SELECT setting_value FROM settings
            WHERE tenant_id = ? AND setting_key = 'default_branch_id' LIMIT 1
        ");
        $stmt->execute([$this->tenantId]);
        $branchId = (int) $stmt->fetchColumn();
        if (!$branchId) {
            $stmt = $this->pdo->prepare("
                SELECT id FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1
            ");
            $stmt->execute([$this->tenantId]);
            $branchId = (int) $stmt->fetchColumn();
        }

        // Create POS sale record
        $stmt = $this->pdo->prepare("
            INSERT INTO sales
                (tenant_id, branch_id, customer_id, subtotal, total, discount_amount, tax_amount, payment_method, status, pos_transaction, order_type, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', 0, 'delivery', NOW(), NOW())
        ");
        $stmt->execute([
            $this->tenantId, $branchId, $order['customer_id'],
            $order['subtotal'] ?? $order['total'], $order['total'], $order['discount_amount'] ?? 0,
            $order['tax_amount'] ?? 0, $order['payment_method']
        ]);
        $saleId = (int) $this->pdo->lastInsertId();

        // Create sale items
        $items = $this->getOrderItems($orderId);
        foreach ($items as $item) {
            $stmt = $this->pdo->prepare("
                INSERT INTO sale_items
                    (tenant_id, sale_id, product_id, quantity, unit, price, original_price, subtotal)
                VALUES (?, ?, ?, ?, 'pcs', ?, ?, ?)
            ");
            $stmt->execute([
                $this->tenantId, $saleId, $item['product_id'], $item['qty'],
                $item['price'], $item['price'], ($item['qty'] ?? 1) * ($item['price'] ?? 0)
            ]);
        }

        // Link order to POS sale
        $this->pdo->prepare("
            UPDATE online_orders SET pos_sale_id = ? WHERE id = ? AND tenant_id = ?
        ")->execute([$saleId, $orderId, $this->tenantId]);

        return $saleId;
    }

    /**
     * Update order status with audit trail
     */
    public function updateStatus(int $orderId, string $newStatus, ?string $notes = null, int $changedBy = 0, string $changedByType = 'system'): array {
        $order = $this->getOrder($orderId);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        $oldStatus = $order['status'];
        if ($oldStatus === $newStatus) {
            return ['success' => false, 'error' => 'New status must be different'];
        }

        // Validate status transitions
        $validTransitions = [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered', 'returned'],
            'delivered' => ['returned'],
            'cancelled' => [],
            'refunded' => [],
            'returned' => ['refunded']
        ];

        if (!in_array($newStatus, $validTransitions[$oldStatus] ?? [])) {
            return ['success' => false, 'error' => "Cannot transition from {$oldStatus} to {$newStatus}"];
        }

        $updateFields = ['status' => $newStatus];
        if ($newStatus === 'shipped') $updateFields['shipped_at'] = date('Y-m-d H:i:s');
        if ($newStatus === 'delivered') $updateFields['delivered_at'] = date('Y-m-d H:i:s');

        $setParts = [];
        $values = [];
        foreach ($updateFields as $col => $val) {
            $setParts[] = "$col = ?";
            $values[] = $val;
        }
        $values[] = $orderId;
        $values[] = $this->tenantId;

        $stmt = $this->pdo->prepare("UPDATE online_orders SET " . implode(', ', $setParts) . " WHERE id = ? AND tenant_id = ?");
        $stmt->execute($values);

        // Log status change
        $this->logStatusChange($orderId, $oldStatus, $newStatus, $changedByType, $notes, $changedBy);

        // Send notification to customer
        $this->notifyCustomerOfStatusChange($orderId, $newStatus);

        return ['success' => true, 'order' => $this->getOrder($orderId)];
    }

    /**
     * Get order by ID
     */
    public function getOrder(int $orderId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM online_orders
            WHERE id = ? AND tenant_id = ? LIMIT 1
        ");
        $stmt->execute([$orderId, $this->tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get order items
     */
    public function getOrderItems(int $orderId): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM online_order_items
            WHERE order_id = ? AND tenant_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$orderId, $this->tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get customer orders
     */
    public function getCustomerOrders(int $customerId, int $page = 1, int $perPage = 20): array {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare("
            SELECT * FROM online_orders
            WHERE tenant_id = ? AND customer_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$this->tenantId, $customerId, $perPage, $offset]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM online_orders
            WHERE tenant_id = ? AND customer_id = ?
        ");
        $stmt->execute([$this->tenantId, $customerId]);
        $total = (int) $stmt->fetchColumn();

        return ['orders' => $orders, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * Get order tracking info (guest accessible)
     */
    public function trackOrder(string $orderNumber, ?string $phone = null, ?string $email = null): ?array {
        $sql = "SELECT * FROM online_orders WHERE tenant_id = ? AND order_number = ?";
        $params = [$this->tenantId, $orderNumber];

        if ($phone) {
            $sql .= " AND customer_phone = ?";
            $params[] = $phone;
        }
        if ($email) {
            $sql .= " AND customer_email = ?";
            $params[] = $email;
        }

        $stmt = $this->pdo->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return null;

        $order['items'] = $this->getOrderItems((int) $order['id']);
        $order['status_history'] = $this->getStatusHistory((int) $order['id']);

        return $order;
    }

    /**
     * Get admin order list with filters
     */
    public function getAdminOrders(array $filters = [], int $page = 1, int $perPage = 20): array {
        $where = ['tenant_id = ?'];
        $params = [$this->tenantId];

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['payment_status'])) {
            $where[] = "payment_status = ?";
            $params[] = $filters['payment_status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        $whereSql = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare("
            SELECT * FROM online_orders
            WHERE $whereSql
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM online_orders WHERE $whereSql");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        return ['orders' => $orders, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * Get order analytics for dashboard
     */
    public function getAnalytics(string $period = '30d'): array {
        $dateFilter = match($period) {
            '7d' => 'DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
            '30d' => 'DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
            '90d' => 'DATE_SUB(CURDATE(), INTERVAL 90 DAY)',
            '1y' => 'DATE_SUB(CURDATE(), INTERVAL 1 YEAR)',
            default => 'DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
        };

        // Total revenue
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as orders
            FROM online_orders
            WHERE tenant_id = ? AND status NOT IN ('cancelled', 'refunded') AND created_at >= $dateFilter
        ");
        $stmt->execute([$this->tenantId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        // By status
        $stmt = $this->pdo->prepare("
            SELECT status, COUNT(*) as count, COALESCE(SUM(total), 0) as value
            FROM online_orders
            WHERE tenant_id = ? AND created_at >= $dateFilter
            GROUP BY status
        ");
        $stmt->execute([$this->tenantId]);
        $byStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Daily revenue
        $stmt = $this->pdo->prepare("
            SELECT DATE(created_at) as date, COALESCE(SUM(total), 0) as revenue, COUNT(*) as orders
            FROM online_orders
            WHERE tenant_id = ? AND status NOT IN ('cancelled', 'refunded') AND created_at >= $dateFilter
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$this->tenantId]);
        $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top products
        $stmt = $this->pdo->prepare("
            SELECT oi.product_id, oi.product_name, SUM(oi.quantity) as sold, SUM(oi.subtotal) as revenue
            FROM online_order_items oi
            JOIN online_orders o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
            WHERE oi.tenant_id = ? AND o.status NOT IN ('cancelled', 'refunded') AND o.created_at >= $dateFilter
            GROUP BY oi.product_id
            ORDER BY revenue DESC
            LIMIT 10
        ");
        $stmt->execute([$this->tenantId]);
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'summary' => $summary,
            'by_status' => $byStatus,
            'daily' => $daily,
            'top_products' => $topProducts
        ];
    }

    // ─── Private ───

    private function logStatusChange(int $orderId, ?string $from, string $to, string $byType, ?string $notes, int $changedBy = 0): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO order_status_history (tenant_id, order_id, from_status, to_status, changed_by, changed_by_type, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$this->tenantId, $orderId, $from, $to, $changedBy, $byType, $notes]);
    }

    private function getStatusHistory(int $orderId): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM order_status_history
            WHERE order_id = ? AND tenant_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$orderId, $this->tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function notifyCustomerOfStatusChange(int $orderId, string $status): void {
        $messages = [
            'processing' => 'Your order is being processed and prepared for shipment.',
            'shipped' => 'Your order has been shipped! Track your delivery for updates.',
            'delivered' => 'Your order has been delivered. Thank you for shopping with us!',
            'cancelled' => 'Your order has been cancelled.',
            'refunded' => 'A refund has been processed for your order.',
            'returned' => 'Your return has been received and is being processed.'
        ];

        if (!isset($messages[$status])) return;

        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (tenant_id, customer_id, type, channel, title, message)
            SELECT tenant_id, customer_id, 'order', 'in_app', ?, ?
            FROM online_orders WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            'Order Update: ' . ucfirst($status),
            $messages[$status],
            $orderId, $this->tenantId
        ]);
    }
}
