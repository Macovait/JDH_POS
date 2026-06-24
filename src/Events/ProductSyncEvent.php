<?php
declare(strict_types=1);

/**
 * Event Dispatcher - Event-Driven Architecture Core
 * 
 * Implements observer pattern for decoupled event handling
 * Enables real-time sync between Products module and POS
 * 
 * @package JDH_POS
 * @subpackage Events
 */

class EventDispatcher
{
    private static ?self $instance = null;
    private array $listeners = [];
    private array $eventQueue = [];
    private bool $dispatching = false;
    
    public const PRIORITY_HIGH = 100;
    public const PRIORITY_NORMAL = 50;
    public const PRIORITY_LOW = 10;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Register an event listener
     * 
     * @param string $event Event name
     * @param callable $callback Handler function
     * @param int $priority Handler priority (higher = earlier execution)
     */
    public function on(string $event, callable $callback, int $priority = self::PRIORITY_NORMAL): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        
        $this->listeners[$event][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];
        
        // Sort by priority (descending)
        usort($this->listeners[$event], fn($a, $b) => $b['priority'] - $a['priority']);
    }

    /**
     * Remove an event listener
     */
    public function off(string $event, callable $callback = null): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }
        
        if ($callback === null) {
            unset($this->listeners[$event]);
            return;
        }
        
        $this->listeners[$event] = array_filter(
            $this->listeners[$event],
            fn($listener) => $listener['callback'] !== $callback
        );
    }

    /**
     * Dispatch an event immediately
     * 
     * @param string $event Event name
     * @param array $data Event data
     * @return array Results from all handlers
     */
    public function dispatch(string $event, array $data = []): array
    {
        $event = new ProductSyncEvent($event, $data);
        return $this->dispatchEvent($event);
    }
    
    /**
     * Dispatch event object
     */
    public function dispatchEvent(ProductSyncEvent $event): array
    {
        $eventName = $event->getName();
        $results = [];
        
        if (!isset($this->listeners[$eventName])) {
            return $results;
        }
        
        foreach ($this->listeners[$eventName] as $listener) {
            try {
                $result = call_user_func($listener['callback'], $event);
                $results[] = [
                    'success' => true,
                    'result' => $result,
                ];
                
                if ($event->isPropagationStopped()) {
                    break;
                }
            } catch (Throwable $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                error_log("Event handler error in {$eventName}: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Queue an event for deferred dispatch
     * Useful for batching multiple events
     */
    public function queue(string $event, array $data = []): void
    {
        $this->eventQueue[] = new ProductSyncEvent($event, $data);
    }

    /**
     * Process all queued events
     */
    public function flush(): array
    {
        $results = [];
        
        while (!empty($this->eventQueue)) {
            $event = array_shift($this->eventQueue);
            $results[$event->getName()] = $this->dispatchEvent($event);
        }
        
        return $results;
    }

    /**
     * Check if an event has listeners
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /**
     * Get listener count for an event
     */
    public function getListenerCount(string $event): int
    {
        return count($this->listeners[$event] ?? []);
    }
}

/**
 * Product Sync Event
 * 
 * Represents a product-related event for synchronization
 */
class ProductSyncEvent
{
    public const CREATED = 'product.created';
    public const UPDATED = 'product.updated';
    public const DELETED = 'product.deleted';
    public const BULK_CREATED = 'product.bulk_created';
    public const BULK_UPDATED = 'product.bulk_updated';
    public const CACHE_INVALIDATED = 'product.cache_invalidated';
    
    private string $name;
    private array $data;
    private int $timestamp;
    private bool $propagationStopped = false;
    private array $context;

    public function __construct(string $name, array $data = [])
    {
        $this->name = $name;
        $this->data = $data;
        $this->timestamp = time();
        $this->context = $this->extractContext();
    }

    private function extractContext(): array
    {
        // Extract tenant context from session
        return [
            'tenant_id' => $_SESSION['tenant_id'] ?? null,
            'branch_id' => $_SESSION['branch_id'] ?? null,
            'user_id' => $_SESSION['user_id'] ?? null,
            'business_type_id' => $_SESSION['business_type_id'] ?? null,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getContext(): array
    {
        return $this->context;
    }
    
    public function getProductId(): ?int
    {
        return $this->data['product_id'] ?? $this->data['id'] ?? null;
    }
    
    public function getCompanyId(): ?int
    {
        return $this->context['tenant_id'] ?? $this->data['tenant_id'] ?? null;
    }
    
    public function getBranchId(): ?int
    {
        return $this->context['branch_id'] ?? $this->data['branch_id'] ?? null;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
            'context' => $this->context,
        ];
    }
}

/**
 * Product Sync Service
 * 
 * Handles real-time synchronization between Products and POS
 * Manages sync log and cache invalidation
 */
class ProductSyncService
{
    private PDO $pdo;
    private EventDispatcher $dispatcher;
    private array $syncCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->dispatcher = EventDispatcher::getInstance();
        $this->registerListeners();
    }

    /**
     * Register sync event listeners
     */
    private function registerListeners(): void
    {
        $this->dispatcher->on(ProductSyncEvent::CREATED, [$this, 'onProductCreated'], EventDispatcher::PRIORITY_HIGH);
        $this->dispatcher->on(ProductSyncEvent::UPDATED, [$this, 'onProductUpdated'], EventDispatcher::PRIORITY_HIGH);
        $this->dispatcher->on(ProductSyncEvent::DELETED, [$this, 'onProductDeleted'], EventDispatcher::PRIORITY_HIGH);
        $this->dispatcher->on(ProductSyncEvent::BULK_CREATED, [$this, 'onBulkCreated'], EventDispatcher::PRIORITY_NORMAL);
        $this->dispatcher->on(ProductSyncEvent::BULK_UPDATED, [$this, 'onBulkUpdated'], EventDispatcher::PRIORITY_NORMAL);
    }

    /**
     * Dispatch product created event
     */
    public function dispatchCreated(array $product, int $userId): int
    {
        $eventData = [
            'product_id' => $product['id'] ?? 0,
            'product' => $product,
            'user_id' => $userId,
            'action' => 'create',
            'timestamp' => time(),
        ];
        
        $results = $this->dispatcher->dispatch(ProductSyncEvent::CREATED, $eventData);
        
        // Log sync
        $this->logSync($product['id'] ?? 0, 'created', $eventData, $results);
        
        return $this->extractSyncId($results);
    }

    /**
     * Dispatch product updated event
     */
    public function dispatchUpdated(array $product, int $userId, array $changes = []): int
    {
        $eventData = [
            'product_id' => $product['id'] ?? 0,
            'product' => $product,
            'changes' => $changes,
            'user_id' => $userId,
            'action' => 'update',
            'timestamp' => time(),
        ];
        
        $results = $this->dispatcher->dispatch(ProductSyncEvent::UPDATED, $eventData);
        
        // Log sync
        $this->logSync($product['id'] ?? 0, 'updated', $eventData, $results);
        
        // Invalidate product cache
        $this->invalidateCache($product['id'] ?? 0);
        
        return $this->extractSyncId($results);
    }

    /**
     * Dispatch product deleted event
     */
    public function dispatchDeleted(int $productId, int $userId): int
    {
        $eventData = [
            'product_id' => $productId,
            'user_id' => $userId,
            'action' => 'delete',
            'timestamp' => time(),
        ];
        
        $results = $this->dispatcher->dispatch(ProductSyncEvent::DELETED, $eventData);
        
        // Log sync
        $this->logSync($productId, 'deleted', $eventData, $results);
        
        // Invalidate product cache
        $this->invalidateCache($productId);
        
        return $this->extractSyncId($results);
    }

    /**
     * Handle product created
     */
    public function onProductCreated(ProductSyncEvent $event): array
    {
        $data = $event->getData();
        
        // Record sync in database for POS to pick up
        $this->recordSync(
            $data['product_id'],
            $event->getCompanyId(),
            $event->getBranchId(),
            'created',
            $data
        );
        
        return [
            'synced' => true,
            'product_id' => $data['product_id'],
        ];
    }

    /**
     * Handle product updated
     */
    public function onProductUpdated(ProductSyncEvent $event): array
    {
        $data = $event->getData();
        
        $this->recordSync(
            $data['product_id'],
            $event->getCompanyId(),
            $event->getBranchId(),
            'updated',
            $data
        );
        
        return [
            'synced' => true,
            'product_id' => $data['product_id'],
        ];
    }

    /**
     * Handle product deleted
     */
    public function onProductDeleted(ProductSyncEvent $event): array
    {
        $data = $event->getData();
        
        $this->recordSync(
            $data['product_id'],
            $event->getCompanyId(),
            $event->getBranchId(),
            'deleted',
            $data
        );
        
        return [
            'synced' => true,
            'product_id' => $data['product_id'],
        ];
    }

    /**
     * Handle bulk created
     */
    public function onBulkCreated(ProductSyncEvent $event): array
    {
        $data = $event->getData();
        $productIds = $data['product_ids'] ?? [];
        
        foreach ($productIds as $productId) {
            $this->recordSync(
                $productId,
                $event->getCompanyId(),
                $event->getBranchId(),
                'created',
                ['bulk' => true]
            );
        }
        
        return [
            'synced' => true,
            'count' => count($productIds),
        ];
    }

    /**
     * Handle bulk updated
     */
    public function onBulkUpdated(ProductSyncEvent $event): array
    {
        $data = $event->getData();
        $productIds = $data['product_ids'] ?? [];
        
        foreach ($productIds as $productId) {
            $this->invalidateCache($productId);
        }
        
        return [
            'synced' => true,
            'count' => count($productIds),
        ];
    }

    /**
     * Record sync event in database
     */
    private function recordSync(int $productId, ?int $companyId, ?int $branchId, string $action, array $data): void
    {
        try {
            // Ensure sync table exists
            $this->ensureSyncTableExists();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO product_sync_log (product_id, tenant_id, branch_id, action, data, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $productId,
                $companyId,
                $branchId,
                $action,
                json_encode($data),
            ]);
        } catch (PDOException $e) {
            error_log("ProductSyncService: Failed to record sync - " . $e->getMessage());
        }
    }

    /**
     * Ensure product_sync_log table exists
     */
    private function ensureSyncTableExists(): void
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS product_sync_log (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    product_id INT UNSIGNED NOT NULL,
                    tenant_id INT UNSIGNED NOT NULL,
                    branch_id INT UNSIGNED NULL,
                    action VARCHAR(50) NOT NULL,
                    data JSON,
                    synced TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_company_branch (tenant_id, branch_id),
                    INDEX idx_product (product_id),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $e) {
            error_log("ProductSyncService: Failed to create sync table - " . $e->getMessage());
        }
    }

    /**
     * Log sync operation
     */
    private function logSync(int $productId, string $action, array $eventData, array $results): void
    {
        error_log(sprintf(
            "PRODUCT_SYNC: id=%d, action=%s, user=%d, company=%d, results=%d handlers",
            $productId,
            $action,
            $eventData['user_id'] ?? 0,
            $eventData['tenant_id'] ?? 0,
            count($results)
        ));
    }

    /**
     * Invalidate cache for a product
     */
    public function invalidateCache(int $productId): void
    {
        $this->syncCache[$productId] = [
            'invalidated_at' => time(),
            'product_id' => $productId,
        ];
        
        // Dispatch cache invalidation event
        $this->dispatcher->dispatch(ProductSyncEvent::CACHE_INVALIDATED, [
            'product_id' => $productId,
        ]);
    }

    /**
     * Get pending sync events for POS
     */
    public function getPendingSyncEvents(int $companyId, ?int $branchId = null, int $since = 0): array
    {
        try {
            $sql = "SELECT * FROM product_sync_log WHERE tenant_id = ? AND created_at > FROM_UNIXTIME(?)";
            $params = [$companyId, $since];
            
            if ($branchId !== null) {
                $sql .= " AND (branch_id IS NULL OR branch_id = ?)";
                $params[] = $branchId;
            }
            
            $sql .= " ORDER BY created_at ASC LIMIT 100";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ProductSyncService: Failed to get pending events - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark sync events as processed
     */
    public function markProcessed(array $syncIds): void
    {
        if (empty($syncIds)) {
            return;
        }
        
        try {
            $placeholders = str_repeat('?,', count($syncIds) - 1) . '?';
            $stmt = $this->pdo->prepare("UPDATE product_sync_log SET synced = 1 WHERE id IN ({$placeholders})");
            $stmt->execute($syncIds);
        } catch (PDOException $e) {
            error_log("ProductSyncService: Failed to mark processed - " . $e->getMessage());
        }
    }

    /**
     * Extract sync ID from results
     */
    private function extractSyncId(array $results): int
    {
        foreach ($results as $result) {
            if ($result['success'] && isset($result['result']['sync_id'])) {
                return (int) $result['result']['sync_id'];
            }
        }
        return 0;
    }
}

/**
 * Product Cache Service
 * 
 * Provides caching layer for product data with sync awareness
 */
class ProductCacheService
{
    private static array $cache = [];
    private static array $meta = [];
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Get product from cache
     */
    public static function get(int $productId, int $companyId): ?array
    {
        $key = self::buildKey($productId, $companyId);
        
        if (!isset(self::$cache[$key])) {
            return null;
        }
        
        $meta = self::$meta[$key] ?? [];
        if (isset($meta['expires_at']) && $meta['expires_at'] < time()) {
            unset(self::$cache[$key], self::$meta[$key]);
            return null;
        }
        
        return self::$cache[$key];
    }

    /**
     * Set product in cache
     */
    public static function set(int $productId, int $companyId, array $data): void
    {
        $key = self::buildKey($productId, $companyId);
        self::$cache[$key] = $data;
        self::$meta[$key] = [
            'cached_at' => time(),
            'expires_at' => time() + self::CACHE_TTL,
        ];
    }

    /**
     * Invalidate product cache
     */
    public static function invalidate(int $productId, int $companyId): void
    {
        $key = self::buildKey($productId, $companyId);
        unset(self::$cache[$key], self::$meta[$key]);
    }

    /**
     * Invalidate all products for a company
     */
    public static function invalidateCompany(int $companyId): void
    {
        foreach (array_keys(self::$cache) as $key) {
            if (strpos($key, "tenant_{$companyId}_") === 0) {
                unset(self::$cache[$key], self::$meta[$key]);
            }
        }
    }

    /**
     * Clear all cache
     */
    public static function clear(): void
    {
        self::$cache = [];
        self::$meta = [];
    }

    /**
     * Build cache key
     */
    private static function buildKey(int $productId, int $companyId): string
    {
        return "product_{$companyId}_{$productId}";
    }
}