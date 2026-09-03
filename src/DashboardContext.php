<?php
declare(strict_types=1);

/**
 * Dashboard Context — Immutable value object for tenant/role scoping
 *
 * Every dashboard query is filtered through this context.
 * Superadmin  → tenant_id = null  (aggregate across all tenants)
 * Admin/Owner → tenant_id = X      (single tenant)
 * Branch mgr  → tenant_id = X, branch_id = Y (branch scope)
 *
 * @package Jakababa
 * @subpackage Dashboard
 */

final class DashboardContext
{
    private function __construct(
        private readonly ?int   $companyId,
        private readonly ?int   $branchId,
        private readonly string $businessType,
        private readonly string $role,
        private readonly int    $userId,
        private readonly bool   $isSuperAdmin,
        private readonly array  $permissions,
        private readonly string $currency,
        private readonly ?array $btConfigCache = null,
    ) {
        // Basic validation
        if (!is_string($businessType) || empty($businessType)) {
            $businessType = 'retail';
        }
        if (!is_string($role) || empty($role)) {
            $role = 'user';
        }
        if (!is_string($currency) || empty($currency)) {
            $currency = 'KES';
        }
        if (!is_array($permissions)) {
            $permissions = [];
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Factory — builds context from the current session                 */
    /* ------------------------------------------------------------------ */

    public static function fromSession(): self
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if we have basic session data
        if (empty($_SESSION)) {
            error_log("DashboardContext: Session is empty, creating guest context");
            return self::create(null, null, 'retail', 'guest', 0);
        }

        $companyId    = isset($_SESSION['tenant_id']) ? (int) $_SESSION['tenant_id'] : null;
        $branchId     = isset($_SESSION['branch_id'])  ? (int) $_SESSION['branch_id']  : null;
        $userId       = (int) ($_SESSION['user_id'] ?? 0);
        $role         = strtolower($_SESSION['role'] ?? 'user');
        // Only the canonical super-admin role/flag may bypass tenant filters.
        // "administrator" is a tenant-local role and must remain tenant-scoped.
        $isSuperAdmin = in_array($role, ['superadmin', 'super_admin', 'super-admin'], true) ||
                        !empty($_SESSION['is_super_admin']);
        $permissions  = $_SESSION['permissions'] ?? [];

        // Handle case where user is logged in but tenant_id is in user array
        if ($companyId === null && isset($_SESSION['user']['tenant_id'])) {
            $companyId = (int) $_SESSION['user']['tenant_id'];
        }

        // Handle case where branch_id is in user array
        if ($branchId === null && isset($_SESSION['user']['branch_id'])) {
            $branchId = (int) $_SESSION['user']['branch_id'];
        }

        // The session tenant is only a hint; verify it against the
        // database-backed user identity before constructing a tenant context.
        if (!$isSuperAdmin && $userId > 0 && $companyId !== null && function_exists('get_db_connection')) {
            try {
                $stmt = get_db_connection()->prepare(
                    'SELECT tenant_id FROM users WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1'
                );
                $stmt->execute([$userId, $companyId]);
                if (!$stmt->fetchColumn()) {
                    error_log("DashboardContext: User {$userId} is not a member of tenant {$companyId}");
                    $companyId = null;
                }
            } catch (\Throwable $e) {
                error_log('DashboardContext: Tenant membership validation failed - ' . $e->getMessage());
                $companyId = null;
            }
        }

        // Validate required values
        if ($userId <= 0) {
            error_log("DashboardContext: Invalid user_id in session: " . $userId);
            $userId = 0; // Allow 0 for guest contexts, but log the issue
        }

        // Business type from session or fallback
        $businessType = $_SESSION['business_type'] ?? 'retail';
        if ($companyId !== null && $companyId > 0) {
            try {
                if (function_exists('get_current_business_type')) {
                    $bt = get_current_business_type($companyId);
                    if ($bt && is_string($bt)) {
                        $businessType = $bt;
                    }
                }
            } catch (Exception $e) {
                error_log("DashboardContext: Failed to get business type for tenant {$companyId} - " . $e->getMessage());
            }
        }

        // Currency from settings or session fallback
        $currency = $_SESSION['tenant_currency'] ?? 'KES';
        if ($companyId !== null && $companyId > 0) {
            try {
                if (function_exists('get_settings')) {
                    $curr = get_settings('currency', $currency, $companyId);
                    if ($curr && is_string($curr)) {
                        $currency = $curr;
                    }
                }
            } catch (Exception $e) {
                error_log("DashboardContext: Failed to get currency for tenant {$companyId} - " . $e->getMessage());
            }
        }

        return new self(
            companyId:    $companyId,
            branchId:     $branchId,
            businessType: $businessType,
            role:         $role,
            userId:       $userId,
            isSuperAdmin: $isSuperAdmin,
            permissions:  $permissions,
            currency:     $currency,
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Factory — for testing or explicit overrides                       */
    /* ------------------------------------------------------------------ */

    public static function create(
        ?int   $companyId,
        ?int   $branchId,
        string $businessType,
        string $role,
        int    $userId,
        bool   $isSuperAdmin = false,
        array  $permissions = [],
        string $currency = 'KES',
    ): self {
        return new self(
            companyId:    $companyId,
            branchId:     $branchId,
            businessType: $businessType,
            role:         $role,
            userId:       $userId,
            isSuperAdmin: $isSuperAdmin,
            permissions:  $permissions,
            currency:     $currency,
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Getters                                                           */
    /* ------------------------------------------------------------------ */

    public function companyId(): ?int   { return $this->companyId; }
    public function branchId(): ?int    { return $this->branchId; }
    public function businessType(): string { return $this->businessType; }
    public function role(): string      { return $this->role; }
    public function userId(): int       { return $this->userId; }
    public function currency(): string  { return $this->currency; }
    public function isSuperAdmin(): bool { return $this->isSuperAdmin; }

    /**
     * Check if user is company admin/owner
     */
    public function isCompanyAdmin(): bool
    {
        return $this->isSuperAdmin || 
               in_array($this->role, ['admin', 'owner', 'administrator'], true);
    }

    /**
     * Check if user is branch manager
     */
    public function isBranchManager(): bool
    {
        return $this->role === 'branch_manager' || 
               $this->role === 'manager';
    }

    /**
     * Check if the context is properly initialized
     */
    public function isValid(): bool
    {
        return $this->userId > 0 && ($this->isSuperAdmin || $this->companyId !== null);
    }

    /**
     * Check if the user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin) {
            return true;
        }

        // Check if permission exists in session permissions
        if (in_array($permission, $this->permissions, true)) {
            return true;
        }

        // Check role-based permissions as fallback
        $rolePermissions = [
            'admin' => ['view_all', 'edit_all', 'delete_all', 'manage_users', 'manage_branches'],
            'owner' => ['view_all', 'edit_all', 'delete_all', 'manage_users', 'manage_branches', 'manage_settings'],
            'manager' => ['view_reports', 'manage_inventory', 'process_returns'],
            'cashier' => ['process_sales', 'view_products'],
        ];

        $rolePerms = $rolePermissions[$this->role] ?? [];
        return in_array($permission, $rolePerms, true);
    }

    /**
     * Check if this context is scoped to a specific company
     */
    public function isCompanyScoped(): bool
    {
        return $this->companyId !== null && $this->companyId > 0;
    }

    /**
     * Check if this context is scoped to a specific branch
     */
    public function isBranchScoped(): bool
    {
        return $this->branchId !== null && $this->branchId > 0;
    }

    /* ------------------------------------------------------------------ */
    /*  Query helpers — build WHERE clauses and parameter arrays          */
    /* ------------------------------------------------------------------ */

    /**
     * Build company WHERE clause for raw SQL queries.
     * Returns [string, params] tuple.
     *
     * Usage:
     *   [$where, $params] = $ctx->companyFilter('s');
     *   $sql = "SELECT ... FROM sales s WHERE 1=1 {$where}";
     *
     * @param string $alias  Table alias (e.g. 's', 'p', empty for bare table)
     * @return array [string $whereClause, array $params]
     */
    public function companyFilter(string $alias = ''): array
    {
        if ($this->isSuperAdmin) {
            return ['', []]; // no filter — full aggregate
        }

        if (!$this->isCompanyScoped()) {
            // A regular user without a tenant must never receive unscoped data.
            return [' AND 1 = 0', []];
        }

        $col = $alias !== '' ? "{$alias}.tenant_id" : "tenant_id";
        return [" AND {$col} = :_ctx_company_id", ['_ctx_company_id' => $this->companyId]];
    }

    /**
     * Build branch WHERE clause for raw SQL queries.
     * Only applies if the table has a `branch_id` column.
     * Returns [string, params] tuple.
     *
     * @param string $alias
     * @param \PDO|null $pdo Optional PDO connection to check column existence
     * @param string $table Table name to check (default: 'sales')
     * @return array [string, array]
     */
    public function branchFilter(string $alias = '', ?\PDO $pdo = null, string $table = 'sales'): array
    {
        if (!$this->isBranchScoped()) {
            return ['', []]; // no branch filter
        }

        // If PDO provided, check if table has branch_id column
        if ($pdo !== null) {
            static $branchColCache = [];
            $cacheKey = $table;
            if (!isset($branchColCache[$cacheKey])) {
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'branch_id'");
                    $branchColCache[$cacheKey] = $stmt && $stmt->rowCount() > 0;
                } catch (\Exception $e) {
                    $branchColCache[$cacheKey] = false;
                }
            }
            if (!$branchColCache[$cacheKey]) {
                return ['', []]; // table doesn't have branch_id
            }
        }

        $col = $alias !== '' ? "{$alias}.branch_id" : "branch_id";
        return [" AND {$col} = :_ctx_branch_id", ['_ctx_branch_id' => $this->branchId]];
    }

    /**
     * Build business-type WHERE clause for sales queries.
     * Only applies if the sales table has a `business_type` column.
     *
     * @param \PDO $pdo
     * @param string $alias
     * @return array [string, array]
     */
    public function businessTypeFilter(\PDO $pdo, string $alias = ''): array
    {
        static $hasBizType = null;
        if ($hasBizType === null) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM sales LIKE 'business_type'");
                $hasBizType = $stmt && $stmt->rowCount() > 0;
            } catch (\Exception $e) {
                $hasBizType = false;
            }
        }
        
        if (!$hasBizType) {
            return ['', []];
        }

        $col = $alias !== '' ? "{$alias}.business_type" : "business_type";
        return [
            " AND ({$col} = :_ctx_biz_type OR {$col} IS NULL OR {$col} = '')",
            ['_ctx_biz_type' => $this->businessType],
        ];
    }

    /**
     * Combined filter: company + branch + business-type.
     * Returns [string $whereClause, array $params].
     *
     * @param \PDO $pdo
     * @param string $alias
     * @return array [string, array]
     */
    public function fullFilter(\PDO $pdo, string $alias = ''): array
    {
        [$cWhere, $cParams] = $this->companyFilter($alias);
        [$bWhere, $bParams] = $this->branchFilter($alias, $pdo);
        [$tWhere, $tParams] = $this->businessTypeFilter($pdo, $alias);

        $where = '';
        $params = [];
        
        // Only add WHERE clauses that have content
        if (!empty($cWhere)) {
            $where .= $cWhere;
            $params = array_merge($params, $cParams);
        }
        if (!empty($bWhere)) {
            $where .= $bWhere;
            $params = array_merge($params, $bParams);
        }
        if (!empty($tWhere)) {
            $where .= $tWhere;
            $params = array_merge($params, $tParams);
        }

        return [$where, $params];
    }

    /* ------------------------------------------------------------------ */
    /*  Business type config helpers with caching                         */
    /* ------------------------------------------------------------------ */

    private function getBtConfig(): array
    {
        if ($this->btConfigCache !== null) {
            return $this->btConfigCache;
        }
        
        if (function_exists('get_business_type_config')) {
            try {
                $config = get_business_type_config($this->businessType);
                if (is_array($config)) {
                    return $config;
                }
            } catch (Exception $e) {
                error_log("DashboardContext: Failed to get business type config - " . $e->getMessage());
            }
        }
        
        // Default configs based on business type
        $defaultConfigs = [
            'restaurant' => [
                'name' => 'Restaurant',
                'icon' => 'fa-utensils',
                'sale_label' => 'Order',
                'sales_label' => 'Orders',
                'customer_label' => 'Guest',
                'order_labels' => [
                    'dine-in' => ['label' => 'Dine In', 'icon' => 'fa-chair'],
                    'takeaway' => ['label' => 'Takeaway', 'icon' => 'fa-bag-shopping'],
                    'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck'],
                ],
                'features' => ['table_management', 'kitchen_display'],
            ],
            'supermarket' => [
                'name' => 'Supermarket',
                'icon' => 'fa-store',
                'sale_label' => 'Sale',
                'sales_label' => 'Sales',
                'customer_label' => 'Customer',
                'order_labels' => [
                    'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                    'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck'],
                ],
                'features' => ['barcode_scanning', 'bulk_pricing', 'loyalty'],
            ],
            'pharmacy' => [
                'name' => 'Pharmacy',
                'icon' => 'fa-pills',
                'sale_label' => 'Sale',
                'sales_label' => 'Sales',
                'customer_label' => 'Patient',
                'order_labels' => [
                    'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                    'prescription' => ['label' => 'Prescription', 'icon' => 'fa-file-prescription'],
                ],
                'features' => ['prescription_tracking', 'expiry_alerts'],
            ],
            'retail' => [
                'name' => 'Retail',
                'icon' => 'fa-shirt',
                'sale_label' => 'Sale',
                'sales_label' => 'Sales',
                'customer_label' => 'Customer',
                'order_labels' => [
                    'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                    'online' => ['label' => 'Online', 'icon' => 'fa-globe'],
                ],
                'features' => ['size_variants', 'color_variants'],
            ],
            'hardware' => [
                'name' => 'Hardware',
                'icon' => 'fa-hammer',
                'sale_label' => 'Sale',
                'sales_label' => 'Sales',
                'customer_label' => 'Customer',
                'order_labels' => [
                    'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                    'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-truck-fast'],
                ],
                'features' => ['bulk_pricing', 'quote_required'],
            ],
            'electronics' => [
                'name' => 'Electronics',
                'icon' => 'fa-microchip',
                'sale_label' => 'Sale',
                'sales_label' => 'Sales',
                'customer_label' => 'Customer',
                'order_labels' => [
                    'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                    'online' => ['label' => 'Online', 'icon' => 'fa-globe'],
                ],
                'features' => ['warranty', 'serial_numbers'],
            ],
            'hotel' => [
                'name' => 'Hotel',
                'icon' => 'fa-hotel',
                'sale_label' => 'Charge',
                'sales_label' => 'Charges',
                'customer_label' => 'Guest',
                'order_labels' => [
                    'room_service' => ['label' => 'Room Service', 'icon' => 'fa-concierge-bell'],
                    'restaurant' => ['label' => 'Restaurant', 'icon' => 'fa-utensils'],
                    'minibar' => ['label' => 'Minibar', 'icon' => 'fa-wine-bottle'],
                ],
                'features' => ['room_charge', 'folio_printing'],
            ],
            'salon' => [
                'name' => 'Salon & Spa',
                'icon' => 'fa-spa',
                'sale_label' => 'Service',
                'sales_label' => 'Services',
                'customer_label' => 'Client',
                'order_labels' => [
                    'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                    'appointment' => ['label' => 'Appointment', 'icon' => 'fa-calendar-check'],
                ],
                'features' => ['appointments', 'staff_commission'],
            ],
            'bakery' => [
                'name' => 'Bakery',
                'icon' => 'fa-bread-slice',
                'sale_label' => 'Sale',
                'sales_label' => 'Sales',
                'customer_label' => 'Customer',
                'order_labels' => [
                    'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking'],
                    'preorder' => ['label' => 'Pre-order', 'icon' => 'fa-calendar'],
                ],
                'features' => ['freshness_tracking', 'custom_orders'],
            ],
        ];
        
        return $defaultConfigs[$this->businessType] ?? $defaultConfigs['retail'];
    }

    public function btConfig(): array
    {
        return $this->getBtConfig();
    }

    public function btName(): string
    {
        $config = $this->getBtConfig();
        return $config['name'] ?? 'Retail';
    }

    public function btIcon(): string
    {
        $config = $this->getBtConfig();
        return $config['icon'] ?? 'fa-store';
    }

    public function btSaleLabel(): string
    {
        $config = $this->getBtConfig();
        return $config['sale_label'] ?? 'Sale';
    }

    public function btSalesLabel(): string
    {
        $config = $this->getBtConfig();
        return $config['sales_label'] ?? 'Sales';
    }

    public function btCustomerLabel(): string
    {
        $config = $this->getBtConfig();
        return $config['customer_label'] ?? 'Customer';
    }

    public function btOrderTypes(): array
    {
        $config = $this->getBtConfig();
        return $config['order_labels'] ?? ['walkin' => ['label' => 'Walk-in', 'icon' => 'fa-walking']];
    }

    public function btFeatures(): array
    {
        $config = $this->getBtConfig();
        return $config['features'] ?? [];
    }

    /**
     * Check if business type has a specific feature
     */
    public function hasBusinessFeature(string $feature): bool
    {
        $features = $this->btFeatures();
        return in_array($feature, $features, true);
    }

    /**
     * Export labels for JSON / frontend consumption
     */
    public function labels(): array
    {
        $cfg = $this->getBtConfig();
        return [
            'businessType'   => $this->businessType,
            'name'           => $cfg['name'] ?? 'Retail',
            'icon'           => $cfg['icon'] ?? 'fa-store',
            'saleLabel'      => $cfg['sale_label'] ?? 'Sale',
            'salesLabel'     => $cfg['sales_label'] ?? 'Sales',
            'customerLabel'  => $cfg['customer_label'] ?? 'Customer',
            'orderLabels'    => $cfg['order_labels'] ?? [],
            'features'       => $cfg['features'] ?? [],
        ];
    }

    /**
     * Export context as array for logging / debugging
     */
    public function toArray(): array
    {
        return [
            'companyId'       => $this->companyId,
            'branchId'        => $this->branchId,
            'businessType'    => $this->businessType,
            'role'            => $this->role,
            'userId'          => $this->userId,
            'isSuperAdmin'    => $this->isSuperAdmin,
            'currency'        => $this->currency,
            'permissions'     => $this->permissions,
            'isValid'         => $this->isValid(),
            'isCompanyScoped' => $this->isCompanyScoped(),
            'isBranchScoped'  => $this->isBranchScoped(),
        ];
    }
}