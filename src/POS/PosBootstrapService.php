<?php
declare(strict_types=1);

namespace App\Pos;

use PDO;
use PDOException;

/**
 * PosBootstrapService
 *
 * Batches and caches the initial queries needed to render the POS page.
 * Designed to dramatically reduce DB round-trips on every page load.
 *
 * - In-memory cache per-request (always)
 * - Optional APCu cache for cross-request reuse (15-30s TTL on safe data)
 * - Tenant-aware: every query scopes by tenant_id
 *
 * Usage:
 *   $boot = new PosBootstrapService($pdo, $tenant_id, $branch_id);
 *   $data = $boot->bootstrap(); // returns associative array of all needed data
 *   // $data['settings'], $data['categories'], $data['branches'],
 *   // $data['today_stats'], $data['current_shift'], $data['held_count'],
 *   // $data['products'], $data['recent_room_numbers'], $data['recent_guest_names'],
 *   // $data['today_appointments']
 */
class PosBootstrapService
{
    private PDO $pdo;
    private int $tenant_id;
    private int $branch_id;
    private bool $apcu_available;
    private bool $file_cache_available;
    private string $cache_dir;

    /** Static per-request memo cache */
    private static array $memo = [];

    public function __construct(PDO $pdo, int $tenant_id, int $branch_id)
    {
        $this->pdo            = $pdo;
        $this->tenant_id      = $tenant_id;
        $this->branch_id      = $branch_id;
        $this->apcu_available = function_exists('apcu_fetch') && ini_get('apc.enabled');

        // File cache fallback (works on XAMPP without APCu)
        $this->cache_dir = defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . '/jdh-pos-cache';
        if (!is_dir($this->cache_dir)) {
            @mkdir($this->cache_dir, 0755, true);
        }
        $this->file_cache_available = is_dir($this->cache_dir) && is_writable($this->cache_dir);
    }

    /**
     * Get everything POS needs in one call.
     */
    public function bootstrap(): array
    {
        return [
            'settings'             => $this->getSettings(),
            'branches'             => $this->getBranches(),
            'categories'           => $this->getCategories(),
            'current_shift'        => $this->getCurrentShift(),
            'today_stats'          => $this->getTodayStats(),
            'held_count'           => $this->getHeldSalesCount(),
            'products'             => $this->getProducts(),
            'today_appointments'   => $this->getTodayAppointments(),
            'recent_room_numbers'  => $this->getRecentRoomNumbers(),
            'recent_guest_names'   => $this->getRecentGuestNames(),
        ];
    }

    // =====================================================================
    // Individual fetchers — each cached separately for fine-grained refresh
    // =====================================================================

    public function getSettings(): array
    {
        return $this->remember('settings', 30, function () {
            $stmt = $this->pdo->prepare(
                "SELECT setting_key, setting_value FROM settings
                 WHERE tenant_id = ? OR tenant_id IS NULL"
            );
            $stmt->execute([$this->tenant_id]);
            $settings = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        });
    }

    public function getBranches(): array
    {
        return $this->remember('branches', 60, function () {
            $stmt = $this->pdo->prepare(
                "SELECT id, name, code, business_type_id, active FROM branches
                 WHERE tenant_id = ? AND active = 1 ORDER BY name"
            );
            $stmt->execute([$this->tenant_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    public function getCategories(): array
    {
        return $this->remember('categories', 60, function () {
            $hasActive = false;
            $hasBizType = false;
            try {
                $activeCheck = $this->pdo->query("SHOW COLUMNS FROM categories LIKE 'active'");
                $hasActive = $activeCheck && $activeCheck->rowCount() > 0;
            } catch (PDOException $e) {
                $hasActive = false;
            }
            try {
                $bizCheck = $this->pdo->query("SHOW COLUMNS FROM categories LIKE 'business_type_id'");
                $hasBizType = $bizCheck && $bizCheck->rowCount() > 0;
            } catch (PDOException $e) {
                $hasBizType = false;
            }

            $sql = "SELECT id, name, color, icon, business_type_id FROM categories WHERE tenant_id = ?";
            $params = [$this->tenant_id];

            if ($hasActive) {
                $sql .= " AND (active = 1 OR active IS NULL)";
            }

            if ($hasBizType) {
                try {
                    $branchColCheck = $this->pdo->query("SHOW COLUMNS FROM branches LIKE 'business_type_id'");
                    if ($branchColCheck && $branchColCheck->rowCount() > 0) {
                        $sql .= " AND (business_type_id IS NULL OR business_type_id = (SELECT business_type_id FROM branches WHERE id = ? LIMIT 1))";
                        $params[] = $this->branch_id;
                    }
                } catch (PDOException $e) {
                    // ignore
                }
            }

            $sql .= " ORDER BY name";
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                // Ultimate fallback: tenant-only query
                $fallbackSql = "SELECT id, name, color, icon, business_type_id FROM categories WHERE tenant_id = ? ORDER BY name";
                $stmt = $this->pdo->prepare($fallbackSql);
                $stmt->execute([$this->tenant_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        });
    }

    public function getProducts(): array
    {
        // Products change more often — short TTL
        return $this->remember('products', 15, function () {
            $stmt = $this->pdo->prepare(
                "SELECT p.id, p.name, p.sku, p.barcode, p.price, p.cost_price,
                        p.category_id, p.image, COALESCE(i.stock, 0) AS stock, p.unit, p.tax_rate, p.active
                 FROM products p
                 LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = ? AND i.tenant_id = p.tenant_id
                 WHERE p.tenant_id = ? AND p.active = 1 AND p.deleted_at IS NULL
                 ORDER BY p.name
                 LIMIT 500"
            );
            $stmt->execute([$this->branch_id, $this->tenant_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    public function getCurrentShift(): ?array
    {
        return $this->remember('shift', 5, function () {
            // Use register_sessions (matches pos.php) with graceful fallback to shifts
            $tables = $this->pdo->query("SHOW TABLES LIKE 'register_sessions'")->fetchAll();
            $tableName = !empty($tables) ? 'register_sessions' : 'shifts';
            $stmt = $this->pdo->prepare(
                "SELECT * FROM {$tableName}
                 WHERE tenant_id = ? AND branch_id = ? AND status = 'open'
                 ORDER BY opened_at DESC LIMIT 1"
            );
            $stmt->execute([$this->tenant_id, $this->branch_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        });
    }

    public function getTodayStats(): array
    {
        return $this->remember('today_stats', 10, function () {
            $stmt = $this->pdo->prepare(
                "SELECT
                    COALESCE(SUM(total), 0) AS total,
                    COUNT(*)                AS sale_count
                 FROM sales
                 WHERE tenant_id = ? AND branch_id = ?
                   AND DATE(created_at) = CURDATE()
                   AND status = 'completed' AND voided = 0"
            );
            $stmt->execute([$this->tenant_id, $this->branch_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: ['total' => 0, 'sale_count' => 0];
        });
    }

    public function getHeldSalesCount(): int
    {
        return $this->remember('held_count', 5, function () {
            // Prefer dedicated hold_sales table; fall back to sales.status='held'
            $tables = $this->pdo->query("SHOW TABLES LIKE 'hold_sales'")->fetchAll();
            if (!empty($tables)) {
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM hold_sales
                     WHERE tenant_id = ? AND branch_id = ?"
                );
                $stmt->execute([$this->tenant_id, $this->branch_id]);
            } else {
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM sales
                     WHERE tenant_id = ? AND branch_id = ? AND status = 'held'"
                );
                $stmt->execute([$this->tenant_id, $this->branch_id]);
            }
            return (int) $stmt->fetchColumn();
        });
    }

    public function getTodayAppointments(): array
    {
        return $this->remember('today_appointments', 30, function () {
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT id, customer_name, service_name, staff_name,
                            appointment_time, status
                     FROM appointments
                     WHERE tenant_id = ? AND branch_id = ?
                       AND DATE(appointment_date) = CURDATE()
                     ORDER BY appointment_time"
                );
                $stmt->execute([$this->tenant_id, $this->branch_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                // Table may not exist for non-service businesses
                return [];
            }
        });
    }

    public function getRecentRoomNumbers(): array
    {
        return $this->remember('recent_rooms', 120, function () {
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT DISTINCT room_number FROM sales
                     WHERE tenant_id = ? AND branch_id = ?
                       AND room_number IS NOT NULL AND room_number != ''
                     ORDER BY created_at DESC LIMIT 20"
                );
                $stmt->execute([$this->tenant_id, $this->branch_id]);
                return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            } catch (PDOException $e) {
                return [];
            }
        });
    }

    public function getRecentGuestNames(): array
    {
        return $this->remember('recent_guests', 120, function () {
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT DISTINCT guest_name FROM sales
                     WHERE tenant_id = ? AND branch_id = ?
                       AND guest_name IS NOT NULL AND guest_name != ''
                     ORDER BY created_at DESC LIMIT 20"
                );
                $stmt->execute([$this->tenant_id, $this->branch_id]);
                return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            } catch (PDOException $e) {
                return [];
            }
        });
    }

    /**
     * Invalidate caches (call after a sale completes, settings change, etc.)
     */
    public function invalidate(?string $key = null): void
    {
        if ($key === null) {
            self::$memo = [];
            // Clear all file caches for this tenant/branch
            if ($this->file_cache_available) {
                $prefix = sprintf('pos_%d_%d_', $this->tenant_id, $this->branch_id);
                foreach (glob($this->cache_dir . '/*.cache') as $file) {
                    if (str_contains(basename($file), $prefix)) {
                        @unlink($file);
                    }
                }
            }
            return;
        }
        $cacheKey = $this->cacheKey($key);
        unset(self::$memo[$cacheKey]);
        if ($this->apcu_available) {
            @apcu_delete($cacheKey);
        }
        if ($this->file_cache_available) {
            $this->fileCacheDelete($cacheKey);
        }
    }

    // =====================================================================
    // Internal cache helpers
    // =====================================================================

    private function cacheKey(string $key): string
    {
        return sprintf('pos:%d:%d:%s', $this->tenant_id, $this->branch_id, $key);
    }

    /**
     * @template T
     * @param callable():T $loader
     * @return T
     */
    private function remember(string $key, int $ttl, callable $loader)
    {
        $cacheKey = $this->cacheKey($key);

        // 1. Per-request memo (instant)
        if (array_key_exists($cacheKey, self::$memo)) {
            return self::$memo[$cacheKey];
        }

        // 2. APCu (cross-request, fast)
        if ($this->apcu_available) {
            $success = false;
            $cached  = apcu_fetch($cacheKey, $success);
            if ($success) {
                self::$memo[$cacheKey] = $cached;
                return $cached;
            }
        }

        // 3. File cache (cross-request, XAMPP-friendly fallback)
        if ($this->file_cache_available) {
            $cached = $this->fileCacheGet($cacheKey);
            if ($cached !== null) {
                self::$memo[$cacheKey] = $cached;
                // Also warm APCu if available
                if ($this->apcu_available && $ttl > 0) {
                    @apcu_store($cacheKey, $cached, $ttl);
                }
                return $cached;
            }
        }

        // 4. Database
        $value = $loader();
        self::$memo[$cacheKey] = $value;

        if ($ttl > 0) {
            if ($this->apcu_available) {
                @apcu_store($cacheKey, $value, $ttl);
            }
            if ($this->file_cache_available) {
                $this->fileCacheSet($cacheKey, $value, $ttl);
            }
        }

        return $value;
    }

    // =====================================================================
    // File cache helpers (APCu fallback for XAMPP / shared hosting)
    // =====================================================================

    private function fileCachePath(string $cacheKey): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cacheKey);
        return $this->cache_dir . DIRECTORY_SEPARATOR . $safe . '.cache';
    }

    private function fileCacheGet(string $cacheKey): mixed
    {
        $path = $this->fileCachePath($cacheKey);
        if (!file_exists($path)) {
            return null;
        }
        $data = @unserialize(file_get_contents($path));
        if (!is_array($data) || !isset($data['expires'], $data['value'])) {
            return null;
        }
        if ($data['expires'] < time()) {
            @unlink($path);
            return null;
        }
        return $data['value'];
    }

    private function fileCacheSet(string $cacheKey, mixed $value, int $ttl): void
    {
        $path = $this->fileCachePath($cacheKey);
        $data = [
            'expires' => time() + $ttl,
            'value'   => $value,
        ];
        @file_put_contents($path, serialize($data), LOCK_EX);
    }

    private function fileCacheDelete(string $cacheKey): void
    {
        $path = $this->fileCachePath($cacheKey);
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
