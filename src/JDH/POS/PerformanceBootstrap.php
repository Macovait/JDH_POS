<?php
/**
 * Performance Bootstrap - Request-level optimizations for JDH POS
 * 
 * This file provides:
 * - Query result memoization across the request lifecycle
 * - Fast in-memory caches for hot-path data
 * - Optimized helper wrappers
 */

namespace JDH\POS;

class PerformanceBootstrap
{
    private static array $memo = [];
    private static array $queryCache = [];
    private static bool $initialized = false;

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // Optimize session read/write by keeping active data in memory
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) {
            // Already active, nothing to do
        }
    }

    /**
     * Memoize a callable result for the current request
     */
    public static function memoize(string $key, callable $fn): mixed
    {
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }
        self::$memo[$key] = $fn();
        return self::$memo[$key];
    }

    /**
     * Clear a specific memoized value
     */
    public static function clearMemo(string $key): void
    {
        unset(self::$memo[$key]);
    }

    /**
     * Clear all memoized values
     */
    public static function clearAllMemo(): void
    {
        self::$memo = [];
        self::$queryCache = [];
    }

    /**
     * Fast tenant-aware cache key builder
     */
    public static function buildKey(string $prefix, array $parts): string
    {
        $tenantId = $_SESSION['tenant_id'] ?? 0;
        $branchId = $_SESSION['branch_id'] ?? 0;
        return $prefix . ':' . $tenantId . ':' . $branchId . ':' . md5(serialize($parts));
    }

    /**
     * Cache a database query result for the current request
     */
    public static function cacheQuery(string $key, callable $queryFn, int $ttlSeconds = 0): mixed
    {
        $now = time();
        if (isset(self::$queryCache[$key]) && ($ttlSeconds <= 0 || $now < self::$queryCache[$key]['expires'])) {
            return self::$queryCache[$key]['data'];
        }

        $data = $queryFn();
        self::$queryCache[$key] = [
            'data' => $data,
            'expires' => $ttlSeconds > 0 ? $now + $ttlSeconds : PHP_INT_MAX,
        ];
        return $data;
    }

    /**
     * Invalidate a cached query
     */
    public static function invalidateQuery(string $pattern): void
    {
        foreach (self::$queryCache as $key => $value) {
            if (strpos($key, $pattern) !== false) {
                unset(self::$queryCache[$key]);
            }
        }
    }
}

// Global helper for quick memoization
if (!function_exists('memoize')) {
    function memoize(string $key, callable $fn): mixed
    {
        return PerformanceBootstrap::memoize($key, $fn);
    }
}
