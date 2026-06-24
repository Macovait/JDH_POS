<?php
/**
 * Tenant Service - Core multi-tenancy management
 * 
 * Handles tenant resolution, creation, and context management
 * for Shopify-style multi-tenant SaaS platform.
 * 
 * @package JakababaPOS
 * @version 2.0.0
 */

namespace JakababaPOS\Services\Tenant;

class TenantService
{
    private \PDO $db;
    private ?Tenant $currentTenant = null;
    private static ?TenantService $instance = null;
    
    const DEFAULT_TABLE_PREFIX = '';  // Uses existing tables: plans, features, subscriptions, etc.
    
    /**
     * Get singleton instance
     */
    public static function getInstance(\PDO $db): self
    {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Resolve tenant from current request context
     * Checks: subdomain -> header -> session -> default
     */
    public function resolve(): ?Tenant
    {
        if ($this->currentTenant !== null) {
            return $this->currentTenant;
        }
        
        // 1. Try subdomain
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $subdomain = $this->extractSubdomain($host);
        
        if ($subdomain) {
            $tenant = $this->findBySubdomain($subdomain);
            if ($tenant) {
                return $this->setCurrentTenant($tenant);
            }
        }
        
        // 2. Try X-Tenant header (API requests)
        $tenantUuid = $_SERVER['X-TENANT-UUID'] ?? null;
        if (!$tenantUuid) {
            $headers = getallheaders();
            $tenantUuid = $headers['X-Tenant-UUID'] ?? $headers['X-Tenant-Id'] ?? null;
        }
        
        if ($tenantUuid) {
            $tenant = $this->findByUuid($tenantUuid);
            if ($tenant) {
                return $this->setCurrentTenant($tenant);
            }
        }
        
        // 3. Try session (web requests)
        if (isset($_SESSION['tenant_id'])) {
            $tenant = $this->findById((int) $_SESSION['tenant_id']);
            if ($tenant) {
                return $this->setCurrentTenant($tenant);
            }
        }
        
        // 4. Fallback to company-based session (legacy support)
        if (isset($_SESSION['tenant_id'])) {
            $tenant = $this->findByCompanyId((int) $_SESSION['tenant_id']);
            if ($tenant) {
                return $this->setCurrentTenant($tenant);
            }
        }
        
        // 5. Default tenant for single-tenant mode
        return $this->getDefaultTenant();
    }
    
    /**
     * Extract subdomain from hostname
     */
    public function extractSubdomain(string $host): ?string
    {
        $parts = explode('.', $host);
        
        // Handle .co.ke, .com.au, etc. TLDs
        if (count($parts) >= 3) {
            $tld = implode('.', array_slice($parts, -2));
            $tlds = ['com', 'net', 'io', 'org', 'co.ke', 'co.tz', 'co.za', 'com.au', 'com.tw'];
            
            foreach ($tlds as $customTld) {
                if (str_ends_with($host, $customTld)) {
                    return $parts[0];
                }
            }
            
            // Standard TLDs (com, net, org, io)
            if (in_array($parts[count($parts) - 2], ['com', 'net', 'org', 'io'])) {
                return $parts[0];
            }
        }
        
        return null;
    }
    
    /**
     * Find tenant by ID
     */
    public function findById(int $id): ?Tenant
    {
        $stmt = $this->db->prepare("
            SELECT * FROM " . self::DEFAULT_TABLE_PREFIX . "tenants 
            WHERE id = :id AND status != 'cancelled'
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? new Tenant($row) : null;
    }
    
    /**
     * Find tenant by UUID
     */
    public function findByUuid(string $uuid): ?Tenant
    {
        $stmt = $this->db->prepare("
            SELECT * FROM " . self::DEFAULT_TABLE_PREFIX . "tenants 
            WHERE uuid = :uuid AND status != 'cancelled'
            LIMIT 1
        ");
        $stmt->execute([':uuid' => $uuid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? new Tenant($row) : null;
    }
    
    /**
     * Find tenant by subdomain
     */
    public function findBySubdomain(string $subdomain): ?Tenant
    {
        $stmt = $this->db->prepare("
            SELECT * FROM " . self::DEFAULT_TABLE_PREFIX . "tenants 
            WHERE subdomain = :subdomain AND status != 'cancelled'
            LIMIT 1
        ");
        $stmt->execute([':subdomain' => $subdomain]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? new Tenant($row) : null;
    }
    
    /**
     * Find tenant by company ID (legacy support)
     */
    public function findByCompanyId(int $companyId): ?Tenant
    {
        // Check if tenant record exists for this company
        $stmt = $this->db->prepare("
            SELECT t.* FROM " . self::DEFAULT_TABLE_PREFIX . "tenants t
            INNER JOIN " . self::DEFAULT_TABLE_PREFIX . "companies c ON c.tenant_id = t.id
            WHERE c.id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($row) {
            return new Tenant($row);
        }
        
        // Create tenant from existing company
        return $this->createFromCompany($companyId);
    }
    
    /**
     * Get default tenant (fallback)
     */
    public function getDefaultTenant(): ?Tenant
    {
        // Get first active tenant or create system tenant
        $stmt = $this->db->query("
            SELECT * FROM " . self::DEFAULT_TABLE_PREFIX . "tenants 
            WHERE status IN ('active', 'trial') 
            ORDER BY id ASC 
            LIMIT 1
        ");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($row) {
            return new Tenant($row);
        }
        
        return null;
    }
    
    /**
     * Create tenant from existing company
     */
    private function createFromCompany(int $companyId): ?Tenant
    {
        $stmt = $this->db->prepare("
            SELECT * FROM " . self::DEFAULT_TABLE_PREFIX . "companies 
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $companyId]);
        $company = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$company) {
            return null;
        }
        
        // Create tenant record
        $uuid = $this->generateUuid();
        $slug = $this->generateSlug($company['name']);
        
        $sql = "INSERT INTO " . self::DEFAULT_TABLE_PREFIX . "tenants 
                (uuid, name, slug, subdomain, status, plan_id, parent_tenant_id) 
                VALUES (:uuid, :name, :slug, :subdomain, 'trial', 1, NULL)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uuid' => $uuid,
            ':name' => $company['name'],
            ':slug' => $slug,
            ':subdomain' => $slug
        ]);
        
        $tenantId = (int) $this->db->lastInsertId();
        
        // Link company to tenant
        $this->db->prepare("UPDATE " . self::DEFAULT_TABLE_PREFIX . "companies SET tenant_id = ? WHERE id = ?")
            ->execute([$tenantId, $companyId]);
        
        return $this->findById($tenantId);
    }
    
    /**
     * Create a new tenant
     */
    public function create(array $data): Tenant
    {
        $uuid = $this->generateUuid();
        $slug = $this->generateSlug($data['name'] ?? 'tenant');
        
        // Ensure unique subdomain
        $subdomain = $slug;
        $counter = 0;
        while ($this->findBySubdomain($subdomain)) {
            $counter++;
            $subdomain = $slug . $counter;
            $slug = substr($slug, 0, 50) . $counter;
        }
        
        $sql = "INSERT INTO " . self::DEFAULT_TABLE_PREFIX . "tenants 
                (uuid, name, slug, subdomain, status, plan_id, parent_tenant_id, settings, branding) 
                VALUES (:uuid, :name, :slug, :subdomain, :status, :plan_id, :parent_id, :settings, :branding)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uuid' => $uuid,
            ':name' => $data['name'],
            ':slug' => $slug,
            ':subdomain' => $subdomain,
            ':status' => $data['status'] ?? 'trial',
            ':plan_id' => $data['plan_id'] ?? 1,
            ':parent_id' => $data['parent_tenant_id'] ?? null,
            ':settings' => json_encode($data['settings'] ?? []),
            ':branding' => json_encode($data['branding'] ?? [])
        ]);
        
        $tenantId = (int) $this->db->lastInsertId();
        
        // Create default subscription
        $this->createSubscription($tenantId, $data['plan_id'] ?? 1);
        
        return $this->findById($tenantId);
    }
    
    /**
     * Create subscription for tenant
     */
    private function createSubscription(int $tenantId, int $planId): void
    {
        $stmt = $this->db->prepare("SELECT * FROM " . self::DEFAULT_TABLE_PREFIX . "plans WHERE id = :id");
        $stmt->execute([':id' => $planId]);
        $plan = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$plan) {
            return;
        }
        
        $trialDays = (int) $plan['trial_days'];
        $periodStart = date('Y-m-d H:i:s');
        $periodEnd = date('Y-m-d H:i:s', strtotime("+1 month"));
        $trialEnd = $trialDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$trialDays} days")) : null;
        
        $sql = "INSERT INTO " . self::DEFAULT_TABLE_PREFIX . "subscriptions 
                (tenant_id, plan_id, status, billing_cycle, amount, currency, 
                 trial_ends_at, current_period_start, current_period_end) 
                VALUES (:tenant_id, :plan_id, :status, :cycle, :amount, :currency, 
                        :trial_end, :period_start, :period_end)";
        
        $this->db->prepare($sql)->execute([
            ':tenant_id' => $tenantId,
            ':plan_id' => $planId,
            ':status' => $trialDays > 0 ? 'trialing' : 'active',
            ':cycle' => $plan['billing_cycle'],
            ':amount' => $plan['price'],
            ':currency' => $plan['currency'],
            ':trial_end' => $trialEnd,
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd
        ]);
    }
    
    /**
     * Set current tenant in context
     */
    public function setCurrentTenant(Tenant $tenant): Tenant
    {
        $this->currentTenant = $tenant;
        
        // Set session context
        $_SESSION['tenant_id'] = $tenant->id;
        $_SESSION['tenant_uuid'] = $tenant->uuid;
        $_SESSION['tenant_name'] = $tenant->name;
        
        // Set database context for queries
        $this->db->exec('SET @current_tenant_id = ' . $tenant->id);
        
        return $tenant;
    }
    
    /**
     * Get current tenant
     */
    public function getCurrentTenant(): ?Tenant
    {
        return $this->currentTenant ?? $this->resolve();
    }
    
    /**
     * Check if tenant has access to feature
     */
    public function hasFeature(string $featureKey): bool
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT pf.is_enabled 
            FROM " . self::DEFAULT_TABLE_PREFIX . "plan_features pf
            INNER JOIN " . self::DEFAULT_TABLE_PREFIX . "features f ON pf.feature_id = f.id
            INNER JOIN " . self::DEFAULT_TABLE_PREFIX . "subscriptions s ON s.plan_id = pf.plan_id
            WHERE s.tenant_id = :tenant_id AND s.status IN ('active', 'trialing')
            AND f.feature_key = :feature_key
            AND pf.is_enabled = 1
            LIMIT 1
        ");
        
        $stmt->execute([
            ':tenant_id' => $tenant->id,
            ':feature_key' => $featureKey
        ]);
        
        return (bool) $stmt->fetchColumn();
    }
    
    /**
     * Get tenant's current plan
     */
    public function getCurrentPlan(): ?array
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT p.*, s.status as subscription_status, s.trial_ends_at, s.current_period_end
            FROM " . self::DEFAULT_TABLE_PREFIX . "subscriptions s
            INNER JOIN " . self::DEFAULT_TABLE_PREFIX . "plans p ON s.plan_id = p.id
            WHERE s.tenant_id = :tenant_id AND s.status IN ('active', 'trialing')
            ORDER BY s.id DESC
            LIMIT 1
        ");
        
        $stmt->execute([':tenant_id' => $tenant->id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Check subscription status
     */
    public function isSubscriptionActive(): bool
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT status FROM " . self::DEFAULT_TABLE_PREFIX . "subscriptions 
            WHERE tenant_id = :tenant_id AND status IN ('active', 'trialing')
            LIMIT 1
        ");
        
        $stmt->execute([':tenant_id' => $tenant->id]);
        return (bool) $stmt->fetchColumn();
    }
    
    /**
     * Get tenant configuration
     */
    public function getConfig(string $key, $default = null)
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            return $default;
        }
        
        $stmt = $this->db->prepare("
            SELECT config_value FROM " . self::DEFAULT_TABLE_PREFIX . "tenant_configs
            WHERE tenant_id = :tenant_id AND config_key = :key
            LIMIT 1
        ");
        
        $stmt->execute([
            ':tenant_id' => $tenant->id,
            ':key' => $key
        ]);
        
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    }
    
    /**
     * Set tenant configuration
     */
    public function setConfig(string $key, $value, bool $encrypt = false): void
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            return;
        }
        
        $sql = "INSERT INTO " . self::DEFAULT_TABLE_PREFIX . "tenant_configs 
                (tenant_id, config_key, config_value, is_encrypted) 
                VALUES (:tenant_id, :key, :value, :encrypt)
                ON DUPLICATE KEY UPDATE config_value = :value, is_encrypted = :encrypt";
        
        $this->db->prepare($sql)->execute([
            ':tenant_id' => $tenant->id,
            ':key' => $key,
            ':value' => $value,
            ':encrypt' => $encrypt ? 1 : 0
        ]);
    }
    
    /**
     * Get tenant's branding
     */
    public function getBranding(): array
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant || empty($tenant->branding)) {
            return $this->getDefaultBranding();
        }
        
        return array_merge($this->getDefaultBranding(), json_decode($tenant->branding, true) ?? []);
    }
    
    /**
     * Get default branding
     */
    private function getDefaultBranding(): array
    {
        return [
            'logo' => null,
            'logo_alt' => null,
            'primary_color' => '#3b82f6',
            'secondary_color' => '#1e40af',
            'accent_color' => '#10b981',
            'font_family' => 'Inter, system-ui, sans-serif',
        ];
    }
    
    /**
     * Enforce data isolation on all queries
     */
    public function enforceIsolation(): void
    {
        $tenant = $this->getCurrentTenant();
        if ($tenant) {
            $this->db->exec("SET @current_tenant_id = " . $tenant->id);
        }
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Generate URL-safe slug
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        return substr($slug, 0, 63);
    }
}

/**
 * Tenant Value Object
 */
class Tenant
{
    public int $id;
    public string $uuid;
    public ?string $subdomain;
    public ?string $domain;
    public string $name;
    public string $slug;
    public string $status;
    public int $planId;
    public ?int $parentTenantId;
    public ?array $settings;
    public ?array $branding;
    public string $createdAt;
    public string $updatedAt;
    
    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
    
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial'], true);
    }
    
    public function isOnTrial(): bool
    {
        return $this->status === 'trial';
    }
}

/**
 * Helper function to get tenant service
 */
if (!function_exists('getTenantService')) {
    function getTenantService(): ?TenantService
    {
        global $db;
        return isset($db) ? TenantService::getInstance($db) : null;
    }
}

/**
 * Helper function to get current tenant
 */
if (!function_exists('getCurrentTenant')) {
    function getCurrentTenant(): ?Tenant
    {
        $service = getTenantService();
        return $service ? $service->getCurrentTenant() : null;
    }
}

/**
 * Helper function to check feature access
 */
if (!function_exists('tenantHasFeature')) {
    function tenantHasFeature(string $featureKey): bool
    {
        $service = getTenantService();
        return $service ? $service->hasFeature($featureKey) : false;
    }
}