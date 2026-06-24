<?php
/**
 * TenantResolver — resolves the current tenant from:
 *   1. Subdomain:    myshop.platform.co.ke  → pos_domains table
 *   2. Custom domain: www.myshop.co.ke       → pos_domains table
 *   3. Query param:  ?tenant=3               → direct lookup
 *   4. Session:      $_SESSION['tenant_id']  → already resolved
 *
 * Usage:
 *   require_once SRC_PATH . '/TenantResolver.php';
 *   $tenant = TenantResolver::resolve($pdo);
 *   // $tenant = ['id' => 3, 'name' => 'My Shop', 'slug' => 'myshop', ...]
 */

class TenantResolver
{
    /** Platform base domain — subdomains of this are treated as tenant slugs */
    private const PLATFORM_DOMAIN = 'localhost'; // change to platform.co.ke in production

    /** How long to cache a domain → tenant_id mapping (seconds) */
    private const CACHE_TTL = 300;

    /**
     * Resolve the current tenant. Returns null if no tenant can be determined.
     */
    public static function resolve(PDO $pdo): ?array
    {
        // 1. Already resolved in session
        if (!empty($_SESSION['storefront_tenant_id'])) {
            return self::fetchTenant($pdo, (int) $_SESSION['storefront_tenant_id']);
        }

        // 2. Explicit query param (dev / API)
        if (!empty($_GET['tenant'])) {
            $tenant = self::fetchTenant($pdo, (int) $_GET['tenant']);
            if ($tenant) {
                $_SESSION['storefront_tenant_id'] = $tenant['id'];
            }
            return $tenant;
        }

        // 3. Resolve from HTTP host
        $host = strtolower(trim($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host); // strip port

        if (!$host) return null;

        // 3a. Check pos_domains table (covers both subdomain + custom domain)
        $tenantId = self::resolveFromDomainTable($pdo, $host);

        // 3b. Subdomain shortcut: {slug}.localhost or {slug}.platform.co.ke
        if (!$tenantId) {
            $tenantId = self::resolveFromSubdomain($pdo, $host);
        }

        if (!$tenantId) return null;

        $tenant = self::fetchTenant($pdo, $tenantId);
        if ($tenant) {
            $_SESSION['storefront_tenant_id'] = $tenant['id'];
        }
        return $tenant;
    }

    /** Look up domain in pos_domains table */
    private static function resolveFromDomainTable(PDO $pdo, string $host): int
    {
        // Cache key based on host
        $cacheFile = sys_get_temp_dir() . '/tenant_domain_' . md5($host) . '.cache';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < self::CACHE_TTL) {
            return (int) file_get_contents($cacheFile);
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT tenant_id FROM pos_domains WHERE domain = ? AND active = 1 LIMIT 1"
            );
            $stmt->execute([$host]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $tenantId = $row ? (int) $row['tenant_id'] : 0;
            file_put_contents($cacheFile, $tenantId);
            return $tenantId;
        } catch (PDOException $e) {
            return 0;
        }
    }

    /** Extract slug from subdomain and look up tenant */
    private static function resolveFromSubdomain(PDO $pdo, string $host): int
    {
        // Match: {slug}.localhost or {slug}.platform.co.ke
        $pattern = '/^([a-z0-9-]+)\.' . preg_quote(self::PLATFORM_DOMAIN, '/') . '$/';
        if (!preg_match($pattern, $host, $m)) {
            return 0;
        }
        $slug = $m[1];
        if (in_array($slug, ['www', 'api', 'admin', 'app', 'mail'], true)) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM pos_tenants WHERE slug = ? AND status IN ('active','trial') LIMIT 1"
            );
            $stmt->execute([$slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int) $row['id'] : 0;
        } catch (PDOException $e) {
            return 0;
        }
    }

    /** Fetch full tenant row + storefront settings */
    private static function fetchTenant(PDO $pdo, int $id): ?array
    {
        if ($id <= 0) return null;

        try {
            $stmt = $pdo->prepare(
                "SELECT id, name, slug, status, business_type
                 FROM pos_tenants WHERE id = ? AND status IN ('active','trial') LIMIT 1"
            );
            $stmt->execute([$id]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tenant) return null;

            // Load storefront settings
            $stmt2 = $pdo->prepare(
                "SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?"
            );
            $stmt2->execute([$id]);
            $settings = [];
            while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            $tenant['settings'] = $settings + [
                'store_name'           => $tenant['name'],
                'currency'             => 'KES',
                'primary_color'        => '#f68b1e',
                'online_store_enabled' => '1',
                'whatsapp_number'      => '',
            ];

            return $tenant;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Register a domain mapping for a tenant (call from dashboard settings).
     */
    public static function registerDomain(PDO $pdo, int $tenantId, string $domain): bool
    {
        $domain = strtolower(trim($domain));
        if (!$domain || !filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
            return false;
        }
        try {
            $pdo->prepare(
                "INSERT INTO pos_domains (tenant_id, domain, active)
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), active = 1"
            )->execute([$tenantId, $domain]);
            // Bust cache
            @unlink(sys_get_temp_dir() . '/tenant_domain_' . md5($domain) . '.cache');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Generate the storefront URL for a tenant.
     * Returns subdomain URL if slug available, else query-param URL.
     */
    public static function storefrontUrl(array $tenant): string
    {
        $base = getenv('APP_URL') ?: 'http://localhost/JDH_POS';

        if (!empty($tenant['slug'])) {
            // Production: https://myshop.platform.co.ke
            // Dev: http://localhost/JDH_POS/public/store/?tenant={id}
            if (str_contains($base, 'localhost')) {
                return $base . '/public/store/?tenant=' . $tenant['id'];
            }
            $protocol = str_starts_with($base, 'https') ? 'https' : 'http';
            $platformDomain = preg_replace('#^https?://#', '', $base);
            return "{$protocol}://{$tenant['slug']}.{$platformDomain}";
        }

        return $base . '/public/store/?tenant=' . $tenant['id'];
    }
}
