<?php
/**
 * Cache Functions for JDH POS
 * Simple file-based caching for frequently accessed data
 */

/**
 * Get cached products list for a tenant/branch
 * Falls back to direct DB query if cache fails
 */
function db_get_products_cached($tenantId, $branchId, $ttl = 60) {
    $cacheKey = "products_{$tenantId}_{$branchId}";
    $cacheFile = __DIR__ . '/../storage/cache/' . md5($cacheKey) . '.cache';
    
    // Try to read from cache
    if (file_exists($cacheFile)) {
        try {
            $data = unserialize(file_get_contents($cacheFile));
            if ($data && isset($data['expires']) && $data['expires'] > time()) {
                return $data['value'];
            }
        } catch (Exception $e) {
            error_log("Cache read error (products): " . $e->getMessage());
            // Continue to fetch from database
        }
    }
    
    // Fetch from database
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT p.*, i.stock, i.reorder_level 
            FROM products p 
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? 
            WHERE p.tenant_id = ? AND p.active = 1 AND p.deleted_at IS NULL
            ORDER BY p.name
            LIMIT 500
        ");
        $stmt->execute([$branchId, $tenantId]);
        $results = $stmt->fetchAll();
        
        // Try to save to cache (don't fail if cache write fails)
        try {
            @mkdir(dirname($cacheFile), 0755, true);
            file_put_contents($cacheFile, serialize(['expires' => time() + $ttl, 'value' => $results]), LOCK_EX);
        } catch (Exception $cacheError) {
            error_log("Cache write error (products): " . $cacheError->getMessage());
            // Continue - we have the data even if cache fails
        }
        
        return $results;
    } catch (Exception $dbError) {
        error_log("Database error (products): " . $dbError->getMessage());
        // If database fails but cache exists (even if expired), use it as emergency fallback
        if (file_exists($cacheFile)) {
            try {
                $data = unserialize(file_get_contents($cacheFile));
                if ($data && isset($data['value'])) {
                    error_log("Using stale cache for products due to DB error");
                    return $data['value'];
                }
            } catch (Exception $e) {
                // Cache also failed
            }
        }
        throw $dbError; // Re-throw if we can't get data from anywhere
    }
}

/**
 * Get cached categories for a tenant
 * Falls back to direct DB query if cache fails
 */
function db_get_categories_cached($tenantId, $ttl = 300) {
    $cacheKey = "categories_{$tenantId}";
    $cacheFile = __DIR__ . '/../storage/cache/' . md5($cacheKey) . '.cache';
    
    // Try to read from cache
    if (file_exists($cacheFile)) {
        try {
            $data = unserialize(file_get_contents($cacheFile));
            if ($data && isset($data['expires']) && $data['expires'] > time()) {
                return $data['value'];
            }
        } catch (Exception $e) {
            error_log("Cache read error (categories): " . $e->getMessage());
            // Continue to fetch from database
        }
    }
    
    // Fetch from database
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT * FROM categories 
            WHERE tenant_id = ? AND status = 'active' 
            ORDER BY name
        ");
        $stmt->execute([$tenantId]);
        $results = $stmt->fetchAll();
        
        // Try to save to cache (don't fail if cache write fails)
        try {
            @mkdir(dirname($cacheFile), 0755, true);
            file_put_contents($cacheFile, serialize(['expires' => time() + $ttl, 'value' => $results]), LOCK_EX);
        } catch (Exception $cacheError) {
            error_log("Cache write error (categories): " . $cacheError->getMessage());
            // Continue - we have the data even if cache fails
        }
        
        return $results;
    } catch (Exception $dbError) {
        error_log("Database error (categories): " . $dbError->getMessage());
        // If database fails but cache exists (even if expired), use it as emergency fallback
        if (file_exists($cacheFile)) {
            try {
                $data = unserialize(file_get_contents($cacheFile));
                if ($data && isset($data['value'])) {
                    error_log("Using stale cache for categories due to DB error");
                    return $data['value'];
                }
            } catch (Exception $e) {
                // Cache also failed
            }
        }
        throw $dbError; // Re-throw if we can't get data from anywhere
    }
}
