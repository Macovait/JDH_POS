<?php
/**
 * Cache Layer for Jakababa POS - Supermarket Scale
 * Supports Redis (preferred) with file-based fallback
 *
 * @package Jakababa
 * @subpackage Cache
 * @version 3.0
 */

if (defined('CACHE_LOADED')) {
    return;
}
define('CACHE_LOADED', true);

/**
 * Cache backend interface
 */
interface CacheBackendInterface
{
    public function get(string $key, $default = null);
    public function set(string $key, $value, int $ttl = 3600): bool;
    public function delete(string $key): bool;
    public function flush(): bool;
    public function has(string $key): bool;
    public function getMultiple(array $keys, $default = null): array;
    public function setMultiple(array $values, int $ttl = 3600): bool;
    public function deleteMultiple(array $keys): bool;
    public function increment(string $key, int $value = 1): int;
    public function decrement(string $key, int $value = 1): int;
}

/**
 * Redis Cache Backend
 */
class RedisCacheBackend implements CacheBackendInterface
{
    private $redis;
    private $prefix;
    private $connected = false;

    public function __construct(string $prefix = 'jakababa:')
    {
        $this->prefix = $prefix;
        $this->connect();
    }

    private function connect(): void
    {
        if (!extension_loaded('redis')) {
            throw new RuntimeException('Redis extension not loaded');
        }

        try {
            $this->redis = new Redis();
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = (int) (getenv('REDIS_PORT') ?: 6379);
            $timeout = (float) (getenv('REDIS_TIMEOUT') ?: 2.5);
            $password = getenv('REDIS_PASSWORD') ?: null;
            $db = (int) (getenv('REDIS_DB') ?: 0);

            $this->redis->connect($host, $port, $timeout);

            if ($password) {
                $this->redis->auth($password);
            }

            if ($db > 0) {
                $this->redis->select($db);
            }

            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            $this->redis->setOption(Redis::OPT_PREFIX, $this->prefix);
            $this->connected = true;
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function get(string $key, $default = null)
    {
        try {
            $value = $this->redis->get($key);
            return $value !== false ? $value : $default;
        } catch (Exception $e) {
            error_log("Redis get error: " . $e->getMessage());
            return $default;
        }
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        try {
            if ($ttl > 0) {
                return $this->redis->setex($key, $ttl, $value);
            }
            return $this->redis->set($key, $value);
        } catch (Exception $e) {
            error_log("Redis set error: " . $e->getMessage());
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return $this->redis->del($key) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public function flush(): bool
    {
        try {
            $keys = $this->redis->keys('*');
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function has(string $key): bool
    {
        try {
            return $this->redis->exists($key) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getMultiple(array $keys, $default = null): array
    {
        try {
            $values = $this->redis->mGet($keys);
            $result = [];
            foreach ($keys as $i => $key) {
                $result[$key] = $values[$i] !== false ? $values[$i] : $default;
            }
            return $result;
        } catch (Exception $e) {
            return array_fill_keys($keys, $default);
        }
    }

    public function setMultiple(array $values, int $ttl = 3600): bool
    {
        try {
            $pipe = $this->redis->pipeline();
            foreach ($values as $key => $value) {
                if ($ttl > 0) {
                    $pipe->setex($key, $ttl, $value);
                } else {
                    $pipe->set($key, $value);
                }
            }
            $pipe->exec();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteMultiple(array $keys): bool
    {
        try {
            $this->redis->del($keys);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function increment(string $key, int $value = 1): int
    {
        try {
            return $this->redis->incrBy($key, $value);
        } catch (Exception $e) {
            return 0;
        }
    }

    public function decrement(string $key, int $value = 1): int
    {
        try {
            return $this->redis->decrBy($key, $value);
        } catch (Exception $e) {
            return 0;
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }
}

/**
 * File-based Cache Backend (fallback)
 */
class FileCacheBackend implements CacheBackendInterface
{
    private $cacheDir;
    private $prefix;

    public function __construct(string $cacheDir = null, string $prefix = 'jakababa_')
    {
        $this->cacheDir = $cacheDir ?? (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . '/jakababa_cache');
        $this->prefix = $prefix;

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        $subdir = substr($hash, 0, 2);
        $dir = $this->cacheDir . DIRECTORY_SEPARATOR . $subdir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . DIRECTORY_SEPARATOR . $this->prefix . $hash . '.cache';
    }

    public function get(string $key, $default = null)
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return $default;
        }

        $data = @file_get_contents($file);
        if ($data === false) {
            return $default;
        }

        $entry = @unserialize($data);
        if (!is_array($entry) || !isset($entry['expires']) || !isset($entry['value'])) {
            @unlink($file);
            return $default;
        }

        if ($entry['expires'] > 0 && $entry['expires'] < time()) {
            @unlink($file);
            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        $entry = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time()
        ];

        return @file_put_contents($file, serialize($entry), LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        return file_exists($file) ? @unlink($file) : true;
    }

    public function flush(): bool
    {
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '**' . DIRECTORY_SEPARATOR . $this->prefix . '*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    public function has(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return false;
        }

        $data = @file_get_contents($file);
        if ($data === false) {
            return false;
        }

        $entry = @unserialize($data);
        if (!is_array($entry) || !isset($entry['expires'])) {
            return false;
        }

        return $entry['expires'] == 0 || $entry['expires'] > time();
    }

    public function getMultiple(array $keys, $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(array $values, int $ttl = 3600): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function deleteMultiple(array $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    public function increment(string $key, int $value = 1): int
    {
        $current = (int) $this->get($key, 0);
        $new = $current + $value;
        $this->set($key, $new, 0);
        return $new;
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }
}

/**
 * Cache Manager - Singleton with automatic backend selection
 */
class CacheManager
{
    private static $instance = null;
    private $backend;
    private $enabled;

    private function __construct()
    {
        $this->enabled = filter_var(getenv('CACHE_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN);

        if (!$this->enabled) {
            $this->backend = new NullCacheBackend();
            return;
        }

        // Try Redis first, fall back to file cache
        try {
            $this->backend = new RedisCacheBackend();
            if (defined('LOGS_PATH')) {
                error_log("Cache: Using Redis backend");
            }
        } catch (Exception $e) {
            $this->backend = new FileCacheBackend();
            if (defined('LOGS_PATH')) {
                error_log("Cache: Using file backend (Redis unavailable)");
            }
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getBackend(): CacheBackendInterface
    {
        return $this->backend;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    // Convenience methods
    public function get(string $key, $default = null)
    {
        return $this->backend->get($key, $default);
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        return $this->backend->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->backend->delete($key);
    }

    public function remember(string $key, callable $callback, int $ttl = 3600)
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    public function flush(): bool
    {
        return $this->backend->flush();
    }

    // Cache key builders for common entities
    public function productKey(int $productId, ?int $companyId = null): string
    {
        $cid = $companyId ?? (function_exists('get_current_tenant_id') ? get_current_tenant_id() : 0);
        return "product:{$cid}:{$productId}";
    }

    public function stockKey(int $productId, int $branchId, ?int $companyId = null): string
    {
        $cid = $companyId ?? (function_exists('get_current_tenant_id') ? get_current_tenant_id() : 0);
        return "stock:{$cid}:{$branchId}:{$productId}";
    }

    public function categoryKey(?int $companyId = null): string
    {
        $cid = $companyId ?? (function_exists('get_current_tenant_id') ? get_current_tenant_id() : 0);
        return "categories:{$cid}";
    }

    public function settingsKey(?int $companyId = null): string
    {
        $cid = $companyId ?? (function_exists('get_current_tenant_id') ? get_current_tenant_id() : 0);
        return "settings:{$cid}";
    }

    public function branchKey(?int $companyId = null): string
    {
        $cid = $companyId ?? (function_exists('get_current_tenant_id') ? get_current_tenant_id() : 0);
        return "branches:{$cid}";
    }

    public function promotionKey(?int $companyId = null): string
    {
        $cid = $companyId ?? (function_exists('get_current_tenant_id') ? get_current_tenant_id() : 0);
        return "promotions:{$cid}";
    }

    public function salesReportKey(string $dateRange, ?int $companyId = null): string
    {
        $cid = $companyId ?? (function_exists('get_current_tenant_id') ? get_current_tenant_id() : 0);
        return "report:sales:{$cid}:" . md5($dateRange);
    }
}

/**
 * Null Cache Backend (when caching is disabled)
 */
class NullCacheBackend implements CacheBackendInterface
{
    public function get(string $key, $default = null) { return $default; }
    public function set(string $key, $value, int $ttl = 3600): bool { return true; }
    public function delete(string $key): bool { return true; }
    public function flush(): bool { return true; }
    public function has(string $key): bool { return false; }
    public function getMultiple(array $keys, $default = null): array { return array_fill_keys($keys, $default); }
    public function setMultiple(array $values, int $ttl = 3600): bool { return true; }
    public function deleteMultiple(array $keys): bool { return true; }
    public function increment(string $key, int $value = 1): int { return $value; }
    public function decrement(string $key, int $value = 1): int { return 0; }
}

// -----------------------------------------------------------------------------
// Global Helper Functions
// -----------------------------------------------------------------------------

if (!function_exists('cache')) {
    /**
     * Get the cache manager instance
     */
    function cache(): CacheManager
    {
        return CacheManager::getInstance();
    }
}

if (!function_exists('cache_get')) {
    function cache_get(string $key, $default = null)
    {
        return cache()->get($key, $default);
    }
}

if (!function_exists('cache_set')) {
    function cache_set(string $key, $value, int $ttl = 3600): bool
    {
        return cache()->set($key, $value, $ttl);
    }
}

if (!function_exists('cache_forget')) {
    function cache_forget(string $key): bool
    {
        return cache()->forget($key);
    }
}

if (!function_exists('cache_remember')) {
    function cache_remember(string $key, callable $callback, int $ttl = 3600)
    {
        return cache()->remember($key, $callback, $ttl);
    }
}

if (!function_exists('cache_flush')) {
    function cache_flush(): bool
    {
        return cache()->flush();
    }
}
