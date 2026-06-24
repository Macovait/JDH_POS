<?php
/**
 * Cache Manager
 * Flexible caching with Redis, File, or Memory backends
 */

namespace JDH\POS\Cache;

interface CacheInterface
{
    public function get(string $key, $default = null);
    public function set(string $key, $value, ?int $ttl = null): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function has(string $key): bool;
}

/**
 * File-based cache
 */
class FileCache implements CacheInterface
{
    private string $path;
    private int $defaultTtl;
    
    public function __construct(string $path = __DIR__ . '/../../storage/cache', int $defaultTtl = 3600)
    {
        $this->path = $path;
        $this->defaultTtl = $defaultTtl;
        
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }
    
    public function get(string $key, $default = null)
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($file));
        
        // Check expiration
        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }
        
        return $data['value'];
    }
    
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $file = $this->path . '/' . $this->sanitizeKey($key) . '.cache';
        $expires = $ttl ? time() + $ttl : null;
        
        $data = [
            'expires' => $expires,
            'value' => $value
        ];
        
        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }
    
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    public function clear(): bool
    {
        $files = glob($this->path . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
    
    private function getFilePath(string $key): string
    {
        return $this->path . '/' . $this->sanitizeKey($key) . '.cache';
    }
    
    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    }
}

/**
 * Redis cache (if available)
 */
class RedisCache implements CacheInterface
{
    private ?\Redis $redis = null;
    private bool $available = false;
    
    public function __construct(string $host = '127.0.0.1', int $port = 6379)
    {
        if (!extension_loaded('redis')) {
            return;
        }
        
        try {
            $this->redis = new \Redis();
            $this->available = $this->redis->connect($host, $port);
        } catch (\Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
        }
    }
    
    public function get(string $key, $default = null)
    {
        if (!$this->available) return $default;
        
        $value = $this->redis->get($key);
        return $value !== false ? unserialize($value) : $default;
    }
    
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if (!$this->available) return false;
        
        $serialized = serialize($value);
        
        if ($ttl) {
            return $this->redis->setex($key, $ttl, $serialized);
        }
        
        return $this->redis->set($key, $serialized);
    }
    
    public function delete(string $key): bool
    {
        if (!$this->available) return false;
        return $this->redis->del($key) > 0;
    }
    
    public function clear(): bool
    {
        if (!$this->available) return false;
        return $this->redis->flushDB();
    }
    
    public function has(string $key): bool
    {
        if (!$this->available) return false;
        return $this->redis->exists($key) > 0;
    }
    
    public function isAvailable(): bool
    {
        return $this->available;
    }
}

/**
 * Memory cache (for single request)
 */
class MemoryCache implements CacheInterface
{
    private array $data = [];
    private array $expires = [];
    
    public function get(string $key, $default = null)
    {
        if (!isset($this->data[$key])) {
            return $default;
        }
        
        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            $this->delete($key);
            return $default;
        }
        
        return $this->data[$key];
    }
    
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $this->data[$key] = $value;
        
        if ($ttl) {
            $this->expires[$key] = time() + $ttl;
        }
        
        return true;
    }
    
    public function delete(string $key): bool
    {
        unset($this->data[$key]);
        unset($this->expires[$key]);
        return true;
    }
    
    public function clear(): bool
    {
        $this->data = [];
        $this->expires = [];
        return true;
    }
    
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}

/**
 * Main Cache Manager
 */
class CacheManager
{
    private CacheInterface $driver;
    private int $defaultTtl;
    
    public function __construct(?CacheInterface $driver = null, int $defaultTtl = 3600)
    {
        $this->defaultTtl = $defaultTtl;
        
        if ($driver) {
            $this->driver = $driver;
        } else {
            // Auto-select best available driver
            $redis = new RedisCache();
            if ($redis->isAvailable()) {
                $this->driver = $redis;
            } else {
                $this->driver = new FileCache();
            }
        }
    }
    
    /**
     * Get cached value
     */
    public function get(string $key, $default = null)
    {
        return $this->driver->get($this->prefix($key), $default);
    }
    
    /**
     * Set cached value
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        return $this->driver->set($this->prefix($key), $value, $ttl ?? $this->defaultTtl);
    }
    
    /**
     * Remember value (get or set)
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        
        return $value;
    }
    
    /**
     * Delete cached value
     */
    public function forget(string $key): bool
    {
        return $this->driver->delete($this->prefix($key));
    }
    
    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        return $this->driver->clear();
    }
    
    /**
     * Check if key exists
     */
    public function has(string $key): bool
    {
        return $this->driver->has($this->prefix($key));
    }
    
    /**
     * Add prefix to prevent collisions
     */
    private function prefix(string $key): string
    {
        return 'jdh_pos:' . $key;
    }
}

/**
 * Global cache helper functions
 */

if (!function_exists('cache')) {
    /**
     * Cache helper - get, set, or get instance
     * 
     * @param string|null $key Cache key
     * @param mixed|null $value Value to cache
     * @param int|null $ttl Time to live in seconds
     * @return mixed|CacheManager
     */
    function cache(?string $key = null, $value = null, ?int $ttl = null)
    {
        static $manager = null;
        
        if ($manager === null) {
            $manager = new CacheManager();
        }
        
        if ($key === null) {
            return $manager;
        }
        
        if ($value === null) {
            return $manager->get($key);
        }
        
        if (is_callable($value)) {
            return $manager->remember($key, $value, $ttl);
        }
        
        return $manager->set($key, $value, $ttl);
    }
}

if (!function_exists('cache_remember')) {
    /**
     * Remember value in cache
     */
    function cache_remember(string $key, callable $callback, ?int $ttl = null)
    {
        return cache()->remember($key, $callback, $ttl);
    }
}

if (!function_exists('cache_forget')) {
    /**
     * Remove from cache
     */
    function cache_forget(string $key): bool
    {
        return cache()->forget($key);
    }
}
