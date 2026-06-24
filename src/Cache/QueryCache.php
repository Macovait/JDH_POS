<?php
/**
 * Query Cache Manager
 * Provides file-based caching for frequently accessed database queries
 */

class QueryCache
{
    private string $cacheDir;
    private int $defaultTtl;
    private static ?QueryCache $instance = null;
    
    public function __construct(string $cacheDir = __DIR__ . '/../../storage/cache', int $defaultTtl = 300)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->defaultTtl = $defaultTtl;
        
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate cache key from query and parameters
     */
    private function generateKey(string $query, array $params = []): string
    {
        return md5($query . serialize($params));
    }
    
    /**
     * Get cached data
     */
    public function get(string $query, array $params = [], ?int $ttl = null): ?array
    {
        $key = $this->generateKey($query, $params);
        $file = $this->cacheDir . '/' . $key . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Store data in cache
     */
    public function set(string $query, array $params, $value, ?int $ttl = null): void
    {
        $key = $this->generateKey($query, $params);
        $file = $this->cacheDir . '/' . $key . '.cache';
        
        $data = [
            'expires' => time() + ($ttl ?? $this->defaultTtl),
            'value' => $value
        ];
        
        file_put_contents($file, serialize($data), LOCK_EX);
    }
    
    /**
     * Delete specific cache entry
     */
    public function delete(string $query, array $params = []): void
    {
        $key = $this->generateKey($query, $params);
        $file = $this->cacheDir . '/' . $key . '.cache';
        
        if (file_exists($file)) {
            @unlink($file);
        }
    }
    
    /**
     * Clear all cache or by pattern
     */
    public function clear(?string $pattern = null): int
    {
        $files = glob($this->cacheDir . '/*.cache');
        $count = 0;
        
        foreach ($files as $file) {
            if ($pattern === null || strpos(basename($file), $pattern) !== false) {
                @unlink($file);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Cache frequently accessed products list
     */
    public function getProducts(int $tenantId, int $branchId): ?array
    {
        return $this->get("products_list_{$tenantId}_{$branchId}");
    }
    
    public function setProducts(int $tenantId, int $branchId, array $products): void
    {
        $this->set("products_list_{$tenantId}_{$branchId}", [], $products, 60);
    }
    
    /**
     * Cache customer data
     */
    public function getCustomer(int $customerId): ?array
    {
        return $this->get("customer_{$customerId}");
    }
    
    public function setCustomer(int $customerId, array $data): void
    {
        $this->set("customer_{$customerId}", [], $data, 300);
    }
    
    /**
     * Cache inventory for a branch
     */
    public function getInventory(int $tenantId, int $branchId): ?array
    {
        return $this->get("inventory_{$tenantId}_{$branchId}");
    }
    
    public function setInventory(int $tenantId, int $branchId, array $data): void
    {
        $this->set("inventory_{$tenantId}_{$branchId}", [], $data, 30);
    }
    
    /**
     * Cache categories
     */
    public function getCategories(int $tenantId): ?array
    {
        return $this->get("categories_{$tenantId}");
    }
    
    public function setCategories(int $tenantId, array $data): void
    {
        $this->set("categories_{$tenantId}", [], $data, 600);
    }
    
    /**
     * Clear cache by table name (call after INSERT/UPDATE/DELETE)
     */
    public function invalidate(string $table): void
    {
        $files = glob($this->cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = @unserialize($content);
            
            if ($data && is_array($data['value'])) {
                // Check if cache entry relates to this table
                if (strpos($content, $table) !== false) {
                    @unlink($file);
                }
            }
        }
    }
}
