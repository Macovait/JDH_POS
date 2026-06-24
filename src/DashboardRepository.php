<?php
declare(strict_types=1);

/**
 * DashboardRepository — Single source of truth for all dashboard data
 *
 * This class provides comprehensive dashboard analytics including KPIs, charts,
 * tables, and alerts. Every public method accepts a DashboardContext and returns
 * arrays ready for JSON serialization. Multi-tenant isolation is enforced at the
 * query level via the context's filter helpers.
 *
 * Features:
 * - Automatic schema compatibility checking
 * - Graceful error handling with fallback responses
 * - Multi-tenant data isolation
 * - Performance optimized queries with caching
 * - Comprehensive error logging and debugging
 *
 * @package Jakababa
 * @subpackage Dashboard
 * @version 2.1.0
 */

final class DashboardRepository
{
    private \PDO $pdo;
    private DashboardContext $ctx;

    // Schema caches (per-request)
    private static array $tableExists = [];
    private static array $columnExists = [];
    private static ?array $dbInfo = null;

    // Dashboard data cache TTL in seconds
    private const CACHE_TTL = 120;

    /* ================================================================== */
    /*  Construction                                                      */
    /* ================================================================== */

    public function __construct(\PDO $pdo, DashboardContext $ctx)
    {
        $this->pdo = $pdo;
        $this->ctx = $ctx;

        if (!$this->pdo) {
            throw new \InvalidArgumentException("Invalid PDO connection provided");
        }

        try {
            $this->pdo->query("SELECT 1");
        } catch (\PDOException $e) {
            throw new \RuntimeException("Database connection test failed: " . $e->getMessage());
        }

        $this->seedTableCache();
    }

    /**
     * Pre-seed table existence cache with one SHOW TABLES query
     * instead of probing each table individually.
     */
    private function seedTableCache(): void
    {
        if (!empty(self::$tableExists)) {
            return;
        }
        try {
            $db = $this->pdo->query("SELECT DATABASE()")->fetchColumn();
            $stmt = $this->pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$db}'");
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $t) {
                self::$tableExists[$t] = true;
            }
        } catch (\PDOException $e) {
            // Fallback: nothing pre-seeded, hasTable will probe individually
        }
    }

    /**
     * Build cache key based on context identity.
     */
    private function cacheKey(): string
    {
        $parts = [
            $this->ctx->companyId() ?? 0,
            $this->ctx->branchId() ?? 0,
            $this->ctx->userId()   ?? 0,
            $this->ctx->role()     ?? 'guest',
        ];
        return 'dash_' . md5(implode('|', $parts));
    }

    private function cachePath(string $key): string
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . DIRECTORY_SEPARATOR . $key . '.json';
    }

    private function readCache(): ?array
    {
        $path = $this->cachePath($this->cacheKey());
        if (!file_exists($path)) {
            return null;
        }
        $age = time() - filemtime($path);
        if ($age > self::CACHE_TTL) {
            @unlink($path);
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) && isset($data['kpis'], $data['tables'], $data['charts'], $data['alerts'])
            ? $data
            : null;
    }

    private function writeCache(array $data): void
    {
        $path = $this->cachePath($this->cacheKey());
        @file_put_contents($path, json_encode($data), LOCK_EX);
    }

    /* ================================================================== */
    /*  Schema helpers                                                    */
    /* ================================================================== */

    private function hasTable(string $table): bool
    {
        if (!isset(self::$tableExists[$table])) {
            try {
                // Try multiple methods to check table existence
                $methods = [
                    "SHOW TABLES LIKE '{$table}'",
                    "SELECT 1 FROM `{$table}` LIMIT 0",
                    "DESCRIBE `{$table}`",
                ];

                $exists = false;
                foreach ($methods as $sql) {
                    try {
                        $stmt = $this->pdo->query($sql);
                        $exists = true;
                        break;
                    } catch (\PDOException $e) {
                        // Continue to next method
                    }
                }

                self::$tableExists[$table] = $exists;
            } catch (\Exception $e) {
                error_log("DashboardRepository hasTable check failed for '{$table}': " . $e->getMessage());
                self::$tableExists[$table] = false;
            }
        }
        return self::$tableExists[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";
        if (!isset(self::$columnExists[$key])) {
            try {
                // First check if table exists
                if (!$this->hasTable($table)) {
                    self::$columnExists[$key] = false;
                } else {
                    // Try multiple methods to check column existence
                    $methods = [
                        "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'",
                        "SELECT `{$column}` FROM `{$table}` LIMIT 0",
                        "DESCRIBE `{$table}` `{$column}`",
                    ];

                    $exists = false;
                    foreach ($methods as $sql) {
                        try {
                            $stmt = $this->pdo->query($sql);
                            $exists = true;
                            break;
                        } catch (\PDOException $e) {
                            // Continue to next method
                        }
                    }

                    self::$columnExists[$key] = $exists;
                }
            } catch (\Exception $e) {
                error_log("DashboardRepository hasColumn check failed for '{$table}.{$column}': " . $e->getMessage());
                self::$columnExists[$key] = false;
            }
        }
        return self::$columnExists[$key];
    }

    /**
     * Get database information for debugging
     */
    private function getDbInfo(): array
    {
        if (self::$dbInfo === null) {
            try {
                $stmt = $this->pdo->query("SELECT VERSION() as version, DATABASE() as database");
                $info = $stmt->fetch(\PDO::FETCH_ASSOC);
                self::$dbInfo = [
                    'version' => $info['version'] ?? 'unknown',
                    'database' => $info['database'] ?? 'unknown',
                    'driver' => $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) ?? 'unknown',
                ];
            } catch (\PDOException $e) {
                self::$dbInfo = [
                    'version' => 'unknown',
                    'database' => 'unknown',
                    'driver' => 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }
        return self::$dbInfo;
    }

    private function prepare(string $sql, array $params = []): \PDOStatement
    {
        // Try-except wrapper to handle failing queries gracefully
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            // Log detailed error information
            $sqlPreview = preg_replace('/\s+/', ' ', trim($sql));
            $sqlPreview = substr((string) $sqlPreview, 0, 220);
            $contextInfo = "Tenant: " . ($this->ctx->companyId() ?? 'null') .
                          ", User: " . $this->ctx->userId() .
                          ", Role: " . $this->ctx->role();

            error_log("DashboardRepository query failed: " . $e->getMessage() .
                     " | Context: {$contextInfo} | SQL: {$sqlPreview} | Params: " . json_encode($params));

            // Return empty result set to prevent crashes
            try {
                $stmt = $this->pdo->prepare("SELECT 1 as dummy WHERE 0=1");
                $stmt->execute();
                return $stmt;
            } catch (\PDOException $fallbackError) {
                error_log("DashboardRepository fallback query also failed: " . $fallbackError->getMessage());
                // Last resort: return a prepared statement that won't execute
                return $this->pdo->prepare("SELECT 1 as dummy LIMIT 0");
            }
        }
    }

    /**
     * Get a summary of dashboard key metrics
     *
     * @return array Summary with key KPIs and status
     */
    public function getSummary(): array
    {
        if (!$this->ctx->isValid()) {
            return ['error' => 'Invalid context', 'valid' => false];
        }

        try {
            [$fWhere, $fParams] = $this->ctx->fullFilter($this->pdo, 's');

            $today = $this->salesPeriodKPI('CURDATE()', 'CURDATE()', $fWhere, $fParams);
            $month = $this->salesPeriodKPI("DATE_FORMAT(CURDATE(), '%Y-%m-01')", 'CURDATE()', $fWhere, $fParams);

            return [
                'valid' => true,
                'today_sales' => $today['count'],
                'today_revenue' => $today['revenue'],
                'month_sales' => $month['count'],
                'month_revenue' => $month['revenue'],
                'currency' => $this->ctx->currency(),
                'generated_at' => date('Y-m-d\TH:i:sP'),
            ];
        } catch (\Exception $e) {
            error_log("DashboardRepository summary failed: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'valid' => false];
        }
    }

    /* ================================================================== */
    /*  MASTER ASSEMBLER — single call returns everything                 */
    /* ================================================================== */

    /**
     * Build the complete dashboard payload.
     *
     * @return array  Top-level keys: kpis, tables, charts, alerts, meta
     */
    public function build(): array
    {
        if (!$this->ctx->isValid()) {
            error_log("DashboardRepository: Invalid context - " . json_encode($this->ctx->toArray()));
            return $this->emptyDashboard();
        }

        $cached = $this->readCache();
        if ($cached !== null) {
            $cached['meta']['cached'] = true;
            return $cached;
        }

        try {
            $kpis = $this->buildKPIs();
            $tables = $this->buildTables();
            $charts = $this->buildCharts();
            $alerts = $this->buildAlerts();

            $currency = $this->ctx->currency();

            $result = [
                'kpis'   => $kpis,
                'tables' => $tables,
                'charts' => $charts,
                'alerts' => $alerts,
                'meta'   => [
                    'context'  => $this->ctx->toArray(),
                    'labels'   => $this->ctx->labels(),
                    'currency' => $currency,
                    'generatedAt' => date('Y-m-d\TH:i:sP'),
                    'dbInfo' => $this->getDbInfo(),
                    'cached' => false,
                ],
            ];

            $this->validateResult($result);
            $this->writeCache($result);

            return $result;
        } catch (\Exception $e) {
            error_log("DashboardRepository build failed: " . $e->getMessage());
            return $this->emptyDashboard();
        }
    }

    /**
     * Validate the dashboard result structure
     */
    private function validateResult(array &$result): void
    {
        // Ensure all required keys exist
        $requiredKeys = ['kpis', 'tables', 'charts', 'alerts', 'meta'];
        foreach ($requiredKeys as $key) {
            if (!isset($result[$key])) {
                error_log("DashboardRepository: Missing required key '{$key}' in result");
                $result[$key] = [];
            }
        }

        // Validate meta structure
        if (!isset($result['meta']['generatedAt'])) {
            $result['meta']['generatedAt'] = date('Y-m-d\TH:i:sP');
        }

        // Ensure arrays are properly structured
        foreach (['kpis', 'tables', 'charts', 'alerts'] as $section) {
            if (!is_array($result[$section])) {
                error_log("DashboardRepository: '{$section}' is not an array");
                $result[$section] = [];
            }
        }
    }

    /**
     * Return empty dashboard structure for error cases
     */
    private function emptyDashboard(): array
    {
        return [
            'kpis'   => [],
            'tables' => [],
            'charts' => [],
            'alerts' => [],
            'meta'   => [
                'context'  => $this->ctx->toArray(),
                'labels'   => [],
                'currency' => 'KES',
                'generatedAt' => date('Y-m-d\TH:i:sP'),
                'error' => 'Dashboard generation failed',
                'dbInfo' => $this->getDbInfo(),
            ],
        ];
    }

    /* ================================================================== */
    /*  1. KPIs                                                           */
    /* ================================================================== */

    private function buildKPIs(): array
    {
        [$fWhere, $fParams] = $this->ctx->fullFilter($this->pdo, 's');
        [$cWhere, $cParams] = $this->ctx->companyFilter('');

        $today     = $this->salesPeriodKPI('CURDATE()', 'CURDATE()', $fWhere, $fParams);
        $yesterday = $this->salesPeriodKPI(
            "DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
            "DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
            $fWhere, $fParams
        );
        $week      = $this->salesPeriodKPI('DATE_SUB(CURDATE(), INTERVAL 7 DAY)', 'CURDATE()', $fWhere, $fParams);
        $month     = $this->salesPeriodKPI(
            "DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            'CURDATE()',
            $fWhere, $fParams
        );
        $lifetime  = $this->salesPeriodKPI("'2000-01-01'", 'CURDATE()', $fWhere, $fParams);

        // Profit & cost (today + month)
        $todayCost   = $this->costForPeriod('CURDATE()', 'CURDATE()', $fWhere, $fParams);
        $monthCost   = $this->costForPeriod("DATE_FORMAT(CURDATE(), '%Y-%m-01')", 'CURDATE()', $fWhere, $fParams);

        // Inventory
        $invMetrics = $this->inventoryKPIs($cWhere, $cParams);

        // Customers
        $custMetrics = $this->customerKPIs($cWhere, $cParams);

        // Expenses
        $expMetrics = $this->expenseKPIs($cWhere, $cParams);

        // Returns
        $retMetrics = $this->returnsKPIs($cWhere, $cParams);

        // Suppliers & POs
        $supMetrics = $this->supplierKPIs($cWhere, $cParams);

        // Loyalty
        $loyaltyMetrics = $this->loyaltyKPIs($cWhere, $cParams);

        // Discounts impact
        $discountMonth = $this->discountImpact($fWhere, $fParams);

        // Real-time
        $openRegisters = $this->openRegisters($cWhere, $cParams);
        $openQuotations = $this->openQuotations($cWhere, $cParams);

        // Derived
        $revenueChangePct = $yesterday['revenue'] > 0
            ? round((($today['revenue'] - $yesterday['revenue']) / $yesterday['revenue']) * 100, 1)
            : ($today['revenue'] > 0 ? 100.0 : 0.0);

        $profitMargin = $month['revenue'] > 0
            ? round((($month['revenue'] - $monthCost) / $month['revenue']) * 100, 1)
            : 0.0;

        $stockTurnover = $invMetrics['cost'] > 0
            ? round($monthCost / $invMetrics['cost'], 2)
            : 0.0;

        // MoM growth
        $lastMonth = $this->salesPeriodKPI(
            "DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')",
            "LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))",
            $fWhere, $fParams
        );
        $momGrowth = $lastMonth['revenue'] > 0
            ? round((($month['revenue'] - $lastMonth['revenue']) / $lastMonth['revenue']) * 100, 1)
            : ($month['revenue'] > 0 ? 100.0 : 0.0);

        // Sales velocity
        $currentHour = max(1, (int) date('H'));
        $salesVelocity = $today['count'] > 0 ? round($today['count'] / $currentHour, 1) : 0.0;

        // Net margin
        $netMargin = $month['revenue'] > 0
            ? round((($month['revenue'] - $monthCost - $expMetrics['month']) / $month['revenue']) * 100, 1)
            : 0.0;

        return [
            // Raw period KPIs
            'today'        => $today,
            'yesterday'    => $yesterday,
            'week'         => $week,
            'month'        => $month,
            'lifetime'     => $lifetime,

            // Profitability
            'todayCost'    => $todayCost,
            'todayProfit'  => $today['revenue'] - $todayCost,
            'monthCost'    => $monthCost,
            'monthProfit'  => $month['revenue'] - $monthCost,
            'profitMargin' => $profitMargin,
            'netMargin'    => $netMargin,

            // Inventory
            'totalProducts'  => $invMetrics['total'],
            'inventoryValue' => $invMetrics['value'],
            'inventoryCost'  => $invMetrics['cost'],
            'lowStock'       => $invMetrics['lowStock'],
            'outOfStock'     => $invMetrics['outOfStock'],
            'inventoryHealth' => $invMetrics['total'] > 0
                ? round((($invMetrics['total'] - $invMetrics['lowStock'] - $invMetrics['outOfStock']) / $invMetrics['total']) * 100, 1)
                : 100.0,
            'stockTurnover'  => $stockTurnover,

            // Customers
            'totalCustomers'   => $custMetrics['total'],
            'newCustomersToday'=> $custMetrics['newToday'],
            'newCustomersMonth'=> $custMetrics['newMonth'],

            // Expenses
            'expensesToday' => $expMetrics['today'],
            'expensesMonth' => $expMetrics['month'],
            'expensesTotal' => $expMetrics['total'],

            // Returns
            'returnsToday'   => $retMetrics['today'],
            'returnsMonth'   => $retMetrics['month'],
            'pendingReturns' => $retMetrics['pending'],

            // Suppliers
            'totalSuppliers'   => $supMetrics['total'],
            'pendingPurchases' => $supMetrics['pending'],
            'purchasePipeline' => $supMetrics['pipeline'],

            // Loyalty
            'loyaltyIssued'   => $loyaltyMetrics['issued'],
            'loyaltyRedeemed' => $loyaltyMetrics['redeemed'],

            // Discounts
            'discountSavingsMonth' => $discountMonth,

            // Real-time
            'activeRegisters' => $openRegisters,
            'openQuotations'  => $openQuotations,

            // Derived
            'revenueChangePct' => $revenueChangePct,
            'momGrowth'        => $momGrowth,
            'salesVelocity'    => $salesVelocity,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Sales period aggregation                                          */
    /* ------------------------------------------------------------------ */

    private function salesPeriodKPI(string $dateFrom, string $dateTo, string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;

        $row = $this->prepare(
            "SELECT COUNT(*) AS cnt,
                    COALESCE(SUM(s.total), 0)   AS revenue,
                    COALESCE(SUM(s.tax), 0)     AS tax,
                    COALESCE(SUM(s.discount),0) AS discount
             FROM sales s
             WHERE s.status = 'completed'
               AND s.created_at >= {$dateFrom}
               AND s.created_at <  DATE_ADD({$dateTo}, INTERVAL 1 DAY)
               {$cleanWhere}",
            $fParams
        )->fetch(\PDO::FETCH_ASSOC);

        $count   = (int)   ($row['cnt']      ?? 0);
        $revenue = (float) ($row['revenue']  ?? 0);
        $aov     = $count > 0 ? round($revenue / $count, 2) : 0.0;

        return [
            'count'    => $count,
            'revenue'  => $revenue,
            'tax'      => (float) ($row['tax']      ?? 0),
            'discount' => (float) ($row['discount'] ?? 0),
            'aov'      => $aov,
        ];
    }

    private function costForPeriod(string $dateFrom, string $dateTo, string $fWhere, array $fParams): float
    {
        $cleanWhere = $fWhere;

        $row = $this->prepare(
            "SELECT COALESCE(SUM(si.quantity * p.cost_price), 0) AS cost
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             JOIN products p ON si.product_id = p.id
             WHERE s.status = 'completed'
               AND s.created_at >= {$dateFrom}
               AND s.created_at <  DATE_ADD({$dateTo}, INTERVAL 1 DAY)
               {$cleanWhere}",
            $fParams
        )->fetch(\PDO::FETCH_ASSOC);

        return (float) ($row['cost'] ?? 0);
    }

    /* ------------------------------------------------------------------ */
    /*  Inventory KPIs                                                    */
    /* ------------------------------------------------------------------ */

    private function inventoryKPIs(string $cWhere, array $cParams): array
    {
        $total = 0; $value = 0.0; $cost = 0.0; $lowStock = 0; $outOfStock = 0;

        if ($this->hasTable('inventory') && $this->hasTable('products')) {
            // Check if inventory has quantity_on_hand or stock column
            $stockCol = $this->hasColumn('inventory', 'quantity_on_hand') ? 'quantity_on_hand' : 'stock';
            
            $row = $this->prepare(
                "SELECT COUNT(DISTINCT p.id) AS total,
                        COALESCE(SUM(i.{$stockCol} * COALESCE(p.selling_price, p.price)), 0) AS value,
                        COALESCE(SUM(i.{$stockCol} * p.cost_price), 0) AS cost
                 FROM inventory i
                 JOIN products p ON i.product_id = p.id
                 WHERE p.deleted_at IS NULL {$cWhere}",
                $cParams
            )->fetch(\PDO::FETCH_ASSOC);

            $total = (int)   ($row['total'] ?? 0);
            $value = (float) ($row['value'] ?? 0);
            $cost  = (float) ($row['cost']  ?? 0);

            $reorderCol = $this->hasColumn('inventory', 'reorder_level') ? 'reorder_level' : 'reorder_level';
            
            $lowStock = (int) $this->prepare(
                "SELECT COUNT(*) FROM inventory i
                 JOIN products p ON i.product_id = p.id
                 WHERE p.deleted_at IS NULL
                   AND i.{$stockCol} <= i.{$reorderCol} AND i.{$stockCol} > 0
                   {$cWhere}",
                $cParams
            )->fetchColumn();

            $outOfStock = (int) $this->prepare(
                "SELECT COUNT(*) FROM inventory i
                 JOIN products p ON i.product_id = p.id
                 WHERE p.deleted_at IS NULL AND i.{$stockCol} = 0
                 {$cWhere}",
                $cParams
            )->fetchColumn();
        } else {
            $total = (int) $this->prepare(
                "SELECT COUNT(*) FROM products WHERE deleted_at IS NULL {$cWhere}",
                $cParams
            )->fetchColumn();
        }

        return compact('total', 'value', 'cost', 'lowStock', 'outOfStock');
    }

    /* ------------------------------------------------------------------ */
    /*  Customer KPIs                                                     */
    /* ------------------------------------------------------------------ */

    private function customerKPIs(string $cWhere, array $cParams): array
    {
        if (!$this->hasTable('customers')) {
            return ['total' => 0, 'newToday' => 0, 'newMonth' => 0];
        }

        $total = (int) $this->prepare(
            "SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL {$cWhere}",
            $cParams
        )->fetchColumn();

        $newToday = (int) $this->prepare(
            "SELECT COUNT(*) FROM customers WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) {$cWhere}",
            $cParams
        )->fetchColumn();

        $newMonth = (int) $this->prepare(
            "SELECT COUNT(*) FROM customers
             WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())
             {$cWhere}",
            $cParams
        )->fetchColumn();

        return compact('total', 'newToday', 'newMonth');
    }

    /* ------------------------------------------------------------------ */
    /*  Expense KPIs                                                      */
    /* ------------------------------------------------------------------ */

    private function expenseKPIs(string $cWhere, array $cParams): array
    {
        if (!$this->hasTable('expenses')) {
            return ['today' => 0.0, 'month' => 0.0, 'total' => 0.0];
        }

        $dateCol = $this->hasColumn('expenses', 'expense_date') ? 'expense_date' : 'created_at';
        $branchScope = '';
        $bp = $cParams;
        
        if ($this->ctx->isBranchScoped() && $this->hasColumn('expenses', 'branch_id')) {
            $branchScope = ' AND branch_id = :_exp_branch';
            $bp['_exp_branch'] = $this->ctx->branchId();
        }

        $today = (float) $this->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM expenses
             WHERE status='approved' AND {$dateCol} >= CURDATE() AND {$dateCol} < DATE_ADD(CURDATE(), INTERVAL 1 DAY) {$branchScope} {$cWhere}",
            $bp
        )->fetchColumn();

        $month = (float) $this->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM expenses
             WHERE status='approved'
               AND MONTH({$dateCol})=MONTH(CURDATE())
               AND YEAR({$dateCol})=YEAR(CURDATE())
               {$branchScope} {$cWhere}",
            $bp
        )->fetchColumn();

        $total = (float) $this->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM expenses
             WHERE status='approved' {$branchScope} {$cWhere}",
            $bp
        )->fetchColumn();

        return compact('today', 'month', 'total');
    }

    /* ------------------------------------------------------------------ */
    /*  Returns KPIs                                                      */
    /* ------------------------------------------------------------------ */

    private function returnsKPIs(string $cWhere, array $cParams): array
    {
        if (!$this->hasTable('returns')) {
            return ['today' => 0, 'month' => 0, 'pending' => 0];
        }

        $today = (int) $this->prepare(
            "SELECT COUNT(*) FROM returns WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) {$cWhere}",
            $cParams
        )->fetchColumn();

        $month = (int) $this->prepare(
            "SELECT COUNT(*) FROM returns
             WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())
             {$cWhere}",
            $cParams
        )->fetchColumn();

        $pending = (int) $this->prepare(
            "SELECT COUNT(*) FROM returns WHERE status='pending' {$cWhere}",
            $cParams
        )->fetchColumn();

        return compact('today', 'month', 'pending');
    }

    /* ------------------------------------------------------------------ */
    /*  Supplier / Purchase KPIs                                          */
    /* ------------------------------------------------------------------ */

    private function supplierKPIs(string $cWhere, array $cParams): array
    {
        $total = 0; $pending = 0; $pipeline = 0.0;

        if ($this->hasTable('suppliers')) {
            $total = (int) $this->prepare(
                "SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL {$cWhere}",
                $cParams
            )->fetchColumn();
        }

        if ($this->hasTable('purchase_orders')) {
            $pending = (int) $this->prepare(
                "SELECT COUNT(*) FROM purchase_orders
                 WHERE status IN ('pending','approved','partial') {$cWhere}",
                $cParams
            )->fetchColumn();

            $pipeline = (float) $this->prepare(
                "SELECT COALESCE(SUM(total_amount),0) FROM purchase_orders
                 WHERE status IN ('pending','approved','partial') {$cWhere}",
                $cParams
            )->fetchColumn();
        }

        return compact('total', 'pending', 'pipeline');
    }

    /* ------------------------------------------------------------------ */
    /*  Loyalty KPIs                                                      */
    /* ------------------------------------------------------------------ */

    private function loyaltyKPIs(string $cWhere, array $cParams): array
    {
        if (!$this->hasTable('loyalty_points_log')) {
            return ['issued' => 0, 'redeemed' => 0];
        }

        $issued = (int) $this->prepare(
            "SELECT COALESCE(SUM(points),0) FROM loyalty_points_log
             WHERE points > 0
               AND MONTH(created_at)=MONTH(CURDATE())
               AND YEAR(created_at)=YEAR(CURDATE())
               {$cWhere}",
            $cParams
        )->fetchColumn();

        $redeemed = (int) $this->prepare(
            "SELECT COALESCE(SUM(ABS(points)),0) FROM loyalty_points_log
             WHERE points < 0
               AND MONTH(created_at)=MONTH(CURDATE())
               AND YEAR(created_at)=YEAR(CURDATE())
               {$cWhere}",
            $cParams
        )->fetchColumn();

        return compact('issued', 'redeemed');
    }

    /* ------------------------------------------------------------------ */
    /*  Discount impact                                                   */
    /* ------------------------------------------------------------------ */

    private function discountImpact(string $fWhere, array $fParams): float
    {
        $cleanWhere = $fWhere;
        
        $row = $this->prepare(
            "SELECT COALESCE(SUM(s.discount), 0) AS total
             FROM sales s
             WHERE s.status = 'completed'
               AND s.discount > 0
               AND MONTH(s.created_at) = MONTH(CURDATE())
               AND YEAR(s.created_at)  = YEAR(CURDATE())
               {$cleanWhere}",
            $fParams
        )->fetch(\PDO::FETCH_ASSOC);

        return (float) ($row['total'] ?? 0);
    }

    /* ------------------------------------------------------------------ */
    /*  Real-time entities                                                */
    /* ------------------------------------------------------------------ */

    private function openRegisters(string $cWhere, array $cParams): int
    {
        if (!$this->hasTable('register_sessions') && !$this->hasTable('cash_register')) {
            return 0;
        }
        
        $table = $this->hasTable('register_sessions') ? 'register_sessions' : 'cash_register';
        $statusCol = $this->hasColumn($table, 'status') ? 'status' : 'is_open';
        $statusValue = $statusCol === 'is_open' ? '1' : "'open'";

        $branchFilter = '';
        $bp = $cParams;
        if ($this->ctx->isBranchScoped() && $this->hasColumn($table, 'branch_id')) {
            $branchFilter = ' AND branch_id = :_cr_branch';
            $bp['_cr_branch'] = $this->ctx->branchId();
        }

        return (int) $this->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$statusCol} = {$statusValue} {$branchFilter}",
            $bp
        )->fetchColumn();
    }

    private function openQuotations(string $cWhere, array $cParams): int
    {
        if (!$this->hasTable('quotations')) {
            return 0;
        }

        return (int) $this->prepare(
            "SELECT COUNT(*) FROM quotations WHERE status IN ('draft','sent') {$cWhere}",
            $cParams
        )->fetchColumn();
    }

    /* ================================================================== */
    /*  2. TABLES — row-level data for lists / sections                   */
    /* ================================================================== */

    private function buildTables(): array
    {
        [$fWhere, $fParams] = $this->ctx->fullFilter($this->pdo, 's');
        [$cWhere, $cParams] = $this->ctx->companyFilter('');
        
        return [
            'recentSales'     => $this->recentSales($fWhere, $fParams),
            'topProducts'     => $this->topProducts($fWhere, $fParams),
            'worstProducts'   => $this->worstProducts($fWhere, $fParams),
            'lowStockItems'   => $this->lowStockItems($cWhere, $cParams),
            'topCustomers'    => $this->topCustomers($fWhere, $fParams),
            'customerSegments'=> $this->customerSegments(),
            'expenseBreakdown'=> $this->expenseBreakdown($cWhere, $cParams),
            'recentReturns'   => $this->recentReturns($cWhere, $cParams),
            'supplierOrders'  => $this->supplierOrders($cWhere, $cParams),
            'branches'        => $this->branchPerformance(),
            'cashierPerformance' => $this->cashierPerformance($fWhere, $fParams),
            'recentActivity'  => $this->recentActivity($cWhere, $cParams),
        ];
    }

    private function recentSales(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        return $this->prepare(
            "SELECT s.id, s.total, s.payment_method, s.created_at, s.business_type,
                    COALESCE(c.name, 'Walk-in') AS customer
             FROM sales s
             LEFT JOIN customers c ON s.customer_id = c.id
             WHERE s.status = 'completed' {$cleanWhere}
             ORDER BY s.created_at DESC LIMIT 8",
            $fParams
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function topProducts(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        return $this->prepare(
            "SELECT p.name, p.sku,
                    SUM(si.quantity)              AS qty,
                    SUM(si.quantity * si.price)   AS revenue,
                    SUM(si.quantity * p.cost_price) AS cost
             FROM sale_items si
             JOIN products p ON si.product_id = p.id
             JOIN sales s   ON si.sale_id = s.id
             WHERE s.status = 'completed' {$cleanWhere}
             GROUP BY p.id
             ORDER BY revenue DESC LIMIT 5",
            $fParams
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function worstProducts(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        return $this->prepare(
            "SELECT p.name,
                    SUM(si.quantity)            AS qty,
                    SUM(si.quantity * si.price) AS revenue
             FROM sale_items si
             JOIN products p ON si.product_id = p.id
             JOIN sales s   ON si.sale_id = s.id
             WHERE s.status = 'completed'
               AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               {$cleanWhere}
             GROUP BY p.id
             HAVING qty > 0
             ORDER BY qty ASC LIMIT 5",
            $fParams
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function lowStockItems(string $cWhere, array $cParams): array
    {
        if (!$this->hasTable('inventory') || !$this->hasTable('products')) {
            return [];
        }

        $stockCol = $this->hasColumn('inventory', 'quantity_on_hand') ? 'quantity_on_hand' : 'stock';
        $reorderCol = $this->hasColumn('inventory', 'reorder_level') ? 'reorder_level' : 'reorder_level';

        return $this->prepare(
            "SELECT p.name, p.sku, i.{$stockCol} AS stock, i.{$reorderCol} AS reorder_level
             FROM inventory i
             JOIN products p ON i.product_id = p.id
             WHERE p.deleted_at IS NULL
               AND i.{$stockCol} <= i.{$reorderCol}
               {$cWhere}
             ORDER BY i.{$stockCol} ASC LIMIT 6",
            $cParams
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function topCustomers(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        return $this->prepare(
            "SELECT c.name, c.phone,
                    COUNT(s.id)       AS orders,
                    SUM(s.total)      AS spent,
                    MAX(s.created_at) AS last_order
             FROM sales s
             JOIN customers c ON s.customer_id = c.id
             WHERE s.status = 'completed' {$cleanWhere}
             GROUP BY c.id
             ORDER BY spent DESC LIMIT 5",
            $fParams
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function customerSegments(): array
    {
        [$segWhere, $segParams] = $this->ctx->fullFilter($this->pdo, 's');
        [$cWhere, $cParams] = $this->ctx->companyFilter('c');
        
        $cleanSegWhere = $segWhere;
        $cleanCWhere = $cWhere;
        $customerParams = $cParams;

        if (isset($cParams['_ctx_company_id'])) {
            $cleanCWhere = str_replace(':_ctx_company_id', ':_ctx_customer_company_id', $cleanCWhere);
            $customerParams = ['_ctx_customer_company_id' => $cParams['_ctx_company_id']];
        }

        $rows = $this->prepare(
            "SELECT c.id, c.name, c.created_at,
                    COALESCE(SUM(s.total), 0) AS lifetime,
                    MAX(s.created_at)         AS last_purchase
             FROM customers c
             LEFT JOIN sales s ON c.id = s.customer_id AND s.status = 'completed' {$cleanSegWhere}
             WHERE c.deleted_at IS NULL {$cleanCWhere}
             GROUP BY c.id",
            array_merge($segParams, $customerParams)
        )->fetchAll(\PDO::FETCH_ASSOC);

        $segments = ['VIP' => 0, 'Regular' => 0, 'New' => 0, 'At-Risk' => 0];

        foreach ($rows as $c) {
            $daysSinceCreated  = (time() - strtotime($c['created_at'])) / 86400;
            $daysSincePurchase = $c['last_purchase']
                ? (time() - strtotime($c['last_purchase'])) / 86400
                : 999;

            if ($c['lifetime'] >= 50000) {
                $segments['VIP']++;
            } elseif ($daysSincePurchase > 60) {
                $segments['At-Risk']++;
            } elseif ($daysSinceCreated < 30 && $c['lifetime'] < 5000) {
                $segments['New']++;
            } else {
                $segments['Regular']++;
            }
        }

        return $segments;
    }

    private function expenseBreakdown(string $cWhere, array $cParams): array
    {
        if (!$this->hasTable('expenses')) {
            return [];
        }

        $dateCol = $this->hasColumn('expenses', 'expense_date') ? 'expense_date' : 'created_at';
        $cleanCWhere = str_replace('e.', '', $cWhere);

        return $this->prepare(
            "SELECT COALESCE(ec.name, 'Other') AS cat, 
                    COALESCE(ec.color, '#6B7280') AS color,
                    SUM(e.amount) AS total
             FROM expenses e
             LEFT JOIN expense_categories ec ON e.category_id = ec.id
             WHERE e.status = 'approved'
               AND MONTH(e.{$dateCol}) = MONTH(CURDATE())
               AND YEAR(e.{$dateCol})  = YEAR(CURDATE())
               {$cleanCWhere}
             GROUP BY ec.id
             ORDER BY total DESC LIMIT 8",
            $cParams
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function recentReturns(string $cWhere, array $cParams): array
    {
        if (!$this->hasTable('returns')) {
            return [];
        }

        return $this->prepare(
            "SELECT r.id, r.amount, r.reason, r.status, r.created_at,
                    COALESCE(c.name, 'Walk-in') AS customer
             FROM returns r
             LEFT JOIN customers c ON r.customer_id = c.id
             WHERE 1=1 {$cWhere}
             ORDER BY r.created_at DESC LIMIT 5",
            $cParams
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function supplierOrders(string $cWhere, array $cParams): array
    {
        if (!$this->hasTable('purchase_orders')) {
            return [];
        }

        return $this->prepare(
            "SELECT po.id, po.total_amount, po.status, po.created_at,
                    COALESCE(s.name, 'Unknown') AS supplier
             FROM purchase_orders po
             LEFT JOIN suppliers s ON po.supplier_id = s.id
             WHERE 1=1 {$cWhere}
             ORDER BY po.created_at DESC LIMIT 5",
            $cParams
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function branchPerformance(): array
    {
        [$bcWhere, $bcParams] = $this->ctx->companyFilter('b');
        // Keep the alias prefix to avoid ambiguous column references
        $cleanBcWhere = $bcWhere;

        return $this->prepare(
            "SELECT b.id, b.name, b.code,
                    COALESCE(SUM(s.total), 0) AS revenue,
                    COUNT(s.id)               AS sales
             FROM branches b
             LEFT JOIN sales s ON b.id = s.branch_id
                AND s.status = 'completed'
                AND MONTH(s.created_at) = MONTH(CURDATE())
                AND YEAR(s.created_at)  = YEAR(CURDATE())
             WHERE b.deleted_at IS NULL {$cleanBcWhere}
             GROUP BY b.id
             ORDER BY revenue DESC",
            $bcParams
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function cashierPerformance(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;

        return $this->prepare(
            "SELECT u.name,
                    COUNT(s.id)        AS sales_count,
                    COALESCE(SUM(s.total), 0) AS sales_total
             FROM sales s
             JOIN users u ON s.user_id = u.id
             WHERE s.status = 'completed'
               AND s.created_at >= CURDATE()
               AND s.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
               {$cleanWhere}
             GROUP BY u.id
             ORDER BY sales_total DESC LIMIT 5",
            $fParams
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function recentActivity(string $cWhere, array $cParams): array
    {
        if (!$this->hasTable('activity_logs')) {
            return [];
        }

        return $this->prepare(
            "SELECT action, created_at FROM activity_logs
             WHERE 1=1 {$cWhere}
             ORDER BY created_at DESC LIMIT 8",
            $cParams
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ================================================================== */
    /*  3. CHARTS — arrays formatted for Chart.js / D3                   */
    /* ================================================================== */

    private function buildCharts(): array
    {
        [$fWhere, $fParams] = $this->ctx->fullFilter($this->pdo, 's');
        
        return [
            'dailySales'      => $this->dailySalesChart($fWhere, $fParams),
            'hourlySales'     => $this->hourlySalesChart($fWhere, $fParams),
            'paymentMethods'  => $this->paymentMethodsChart($fWhere, $fParams),
            'categorySales'   => $this->categorySalesChart($fWhere, $fParams),
            'weeklyTrend'     => $this->weeklyTrendChart($fWhere, $fParams),
            'monthlyComparison'=> $this->monthlyComparisonChart($fWhere, $fParams),
            'dowHeatmap'      => $this->dowHeatmapChart($fWhere, $fParams),
            'forecast'        => $this->forecast($fWhere, $fParams),
        ];
    }

    private function dailySalesChart(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        $rows = $this->prepare(
            "SELECT DATE(s.created_at) AS day,
                    COUNT(*)              AS cnt,
                    COALESCE(SUM(s.total), 0) AS total
             FROM sales s
             WHERE s.status = 'completed'
               AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
               {$cleanWhere}
             GROUP BY DATE(s.created_at)
             ORDER BY day",
            $fParams
        )->fetchAll(\PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $r) { 
            $map[$r['day']] = $r; 
        }

        $labels = []; 
        $counts = []; 
        $revenues = [];
        
        for ($i = 59; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $labels[]  = date('M d', strtotime($d));
            $counts[]  = (int)   ($map[$d]['cnt']   ?? 0);
            $revenues[] = (float) ($map[$d]['total'] ?? 0);
        }

        return compact('labels', 'counts', 'revenues');
    }

    private function hourlySalesChart(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        $rows = $this->prepare(
            "SELECT HOUR(s.created_at) AS hour,
                    COUNT(*)              AS cnt,
                    COALESCE(SUM(s.total), 0) AS total
             FROM sales s
             WHERE s.status = 'completed'
               AND DATE(s.created_at) = CURDATE()
               {$cleanWhere}
             GROUP BY HOUR(s.created_at)
             ORDER BY hour",
            $fParams
        )->fetchAll(\PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $r) { 
            $map[(int)$r['hour']] = $r; 
        }

        $labels = []; 
        $counts = []; 
        $totals = [];
        
        for ($h = 0; $h < 24; $h++) {
            $labels[] = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
            $counts[] = (int)   ($map[$h]['cnt']   ?? 0);
            $totals[] = (float) ($map[$h]['total'] ?? 0);
        }

        return compact('labels', 'counts', 'totals');
    }

    private function paymentMethodsChart(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        $rows = $this->prepare(
            "SELECT COALESCE(s.payment_method, 'cash') AS method,
                    COUNT(*)   AS cnt,
                    SUM(s.total) AS total
             FROM sales s
             WHERE s.status = 'completed'
               AND MONTH(s.created_at) = MONTH(CURDATE())
               AND YEAR(s.created_at)  = YEAR(CURDATE())
               {$cleanWhere}
             GROUP BY s.payment_method
             ORDER BY total DESC",
            $fParams
        )->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) {
            $rows = [['method' => 'cash', 'cnt' => 0, 'total' => 0]];
        }

        $labels = []; 
        $values = [];
        
        foreach ($rows as $r) {
            $labels[] = ucfirst($r['method']);
            $values[] = (float) $r['total'];
        }

        return compact('labels', 'values');
    }

    private function categorySalesChart(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        $rows = $this->prepare(
            "SELECT COALESCE(c.name, 'Uncategorized') AS category,
                    SUM(si.quantity)             AS qty,
                    SUM(si.quantity * si.price)  AS revenue
             FROM sale_items si
             JOIN products p ON si.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             JOIN sales s ON si.sale_id = s.id
             WHERE s.status = 'completed'
               AND MONTH(s.created_at) = MONTH(CURDATE())
               AND YEAR(s.created_at)  = YEAR(CURDATE())
               {$cleanWhere}
             GROUP BY c.id
             ORDER BY revenue DESC LIMIT 8",
            $fParams
        )->fetchAll(\PDO::FETCH_ASSOC);

        $labels = []; 
        $values = [];
        
        foreach ($rows as $r) {
            $labels[] = $r['category'];
            $values[] = (float) $r['revenue'];
        }

        return compact('labels', 'values');
    }

    private function weeklyTrendChart(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        $weeks = [];
        for ($w = 3; $w >= 0; $w--) {
            $startWeeks = (int) $w;
            $endWeeks = max(0, $startWeeks - 1);
            
            $row = $this->prepare(
                "SELECT COUNT(*) AS cnt, COALESCE(SUM(s.total), 0) AS total
                 FROM sales s
                 WHERE s.status = 'completed'
                   AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL {$startWeeks} WEEK)
                   AND s.created_at <  DATE_SUB(CURDATE(), INTERVAL {$endWeeks} WEEK)
                   {$cleanWhere}",
                $fParams
            )->fetch(\PDO::FETCH_ASSOC);

            $weeks[] = [
                'week'    => 'W-' . (4 - $w),
                'sales'   => (int)   ($row['cnt']   ?? 0),
                'revenue' => (float) ($row['total'] ?? 0),
            ];
        }

        $labels  = array_column($weeks, 'week');
        $values  = array_column($weeks, 'revenue');

        return compact('labels', 'values', 'weeks');
    }

    private function monthlyComparisonChart(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        $months = [];
        for ($m = 5; $m >= 0; $m--) {
            $monthOffset = (int) $m;
            
            $row = $this->prepare(
                "SELECT COUNT(*) AS cnt, COALESCE(SUM(s.total), 0) AS total
                 FROM sales s
                 WHERE s.status = 'completed'
                   AND MONTH(s.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL {$monthOffset} MONTH))
                   AND YEAR(s.created_at)  = YEAR(DATE_SUB(CURDATE(), INTERVAL {$monthOffset} MONTH))
                   {$cleanWhere}",
                $fParams
            )->fetch(\PDO::FETCH_ASSOC);

            $months[] = [
                'month'   => date('M Y', strtotime("-{$m} months")),
                'sales'   => (int)   ($row['cnt']   ?? 0),
                'revenue' => (float) ($row['total'] ?? 0),
            ];
        }

        $labels = array_column($months, 'month');
        $values = array_column($months, 'revenue');

        return compact('labels', 'values', 'months');
    }

    private function dowHeatmapChart(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        $rows = $this->prepare(
            "SELECT DAYOFWEEK(s.created_at) AS dow,
                    HOUR(s.created_at)       AS hour,
                    COALESCE(SUM(s.total), 0) AS total
             FROM sales s
             WHERE s.status = 'completed'
               AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 28 DAY)
               {$cleanWhere}
             GROUP BY DAYOFWEEK(s.created_at), HOUR(s.created_at)",
            $fParams
        )->fetchAll(\PDO::FETCH_ASSOC);

        $map = [];
        $max = 0.0;
        
        foreach ($rows as $r) {
            $v = (float) $r['total'];
            $map[$r['dow']][$r['hour']] = $v;
            $max = max($max, $v);
        }

        $dowNames  = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $hours     = range(6, 22);
        $matrix    = [];

        for ($dow = 1; $dow <= 7; $dow++) {
            foreach ($hours as $h) {
                $val = $map[$dow][$h] ?? 0.0;
                $matrix[] = [
                    'dow'     => $dow,
                    'dowName' => $dowNames[$dow - 1],
                    'hour'    => $h,
                    'value'   => round($val, 2),
                ];
            }
        }

        return [
            'hours'    => $hours,
            'dowNames' => $dowNames,
            'matrix'   => $matrix,
            'max'      => round($max, 2),
        ];
    }

    private function forecast(string $fWhere, array $fParams): array
    {
        $cleanWhere = $fWhere;
        
        $daily = $this->prepare(
            "SELECT DATE(s.created_at) AS day, COALESCE(SUM(s.total), 0) AS total
             FROM sales s
             WHERE s.status = 'completed'
               AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               {$cleanWhere}
             GROUP BY DATE(s.created_at)
             ORDER BY day",
            $fParams
        )->fetchAll(\PDO::FETCH_ASSOC);

        $n = count($daily);
        if ($n < 7) {
            return ['trend' => 'flat', 'avg7' => 0, 'points' => []];
        }

        $sumX = $sumY = $sumXY = $sumX2 = 0.0;
        foreach ($daily as $i => $d) {
            $y = (float) $d['total'];
            $sumX  += $i;
            $sumY  += $y;
            $sumXY += $i * $y;
            $sumX2 += $i * $i;
        }

        $slope     = ($n * $sumXY - $sumX * $sumY) / max(1, ($n * $sumX2 - $sumX * $sumX));
        $intercept = ($sumY - $slope * $sumX) / $n;

        $points = [];
        for ($f = 1; $f <= 7; $f++) {
            $forecast = max(0, $intercept + $slope * ($n - 1 + $f));
            $points[] = [
                'day'     => '+' . $f . 'd',
                'revenue' => round($forecast),
            ];
        }

        $avg7 = round(array_sum(array_column($points, 'revenue')) / 7);

        return [
            'trend' => $slope > 10 ? 'up' : ($slope < -10 ? 'down' : 'stable'),
            'avg7'  => $avg7,
            'points'=> $points,
        ];
    }

    /* ================================================================== */
    /*  4. ALERTS — automated business health signals                    */
    /* ================================================================== */

    private function buildAlerts(): array
    {
        $alerts = [];
        [$cWhere, $cParams] = $this->ctx->companyFilter('');

        // Out of stock and low stock
        if ($this->hasTable('inventory') && $this->hasTable('products')) {
            $stockCol = $this->hasColumn('inventory', 'quantity_on_hand') ? 'quantity_on_hand' : 'stock';
            $reorderCol = $this->hasColumn('inventory', 'reorder_level') ? 'reorder_level' : 'reorder_level';
            
            $oos = (int) $this->prepare(
                "SELECT COUNT(*) FROM inventory i
                 JOIN products p ON i.product_id = p.id
                 WHERE p.deleted_at IS NULL AND i.{$stockCol} = 0 {$cWhere}",
                $cParams
            )->fetchColumn();
            
            if ($oos > 0) {
                $alerts[] = [
                    'type' => 'out_of_stock', 'severity' => 'critical',
                    'icon' => 'box-open', 'color' => 'red',
                    'message' => "{$oos} product(s) out of stock. Restock immediately.",
                ];
            }

            $low = (int) $this->prepare(
                "SELECT COUNT(*) FROM inventory i
                 JOIN products p ON i.product_id = p.id
                 WHERE p.deleted_at IS NULL
                   AND i.{$stockCol} <= i.{$reorderCol} AND i.{$stockCol} > 0
                   {$cWhere}",
                $cParams
            )->fetchColumn();
            
            if ($low > 3) {
                $alerts[] = [
                    'type' => 'low_stock', 'severity' => 'warning',
                    'icon' => 'boxes-stacked', 'color' => 'amber',
                    'message' => "{$low} products running low. Consider placing purchase orders.",
                ];
            }
        }

        // Zero sales today (after 10 AM)
        if ((int) date('H') > 10) {
            [$fWhere, $fParams] = $this->ctx->fullFilter($this->pdo, 's');
            $cleanWhere = $fWhere;
            
            $todaySales = (int) $this->prepare(
                "SELECT COUNT(*) FROM sales s
                 WHERE s.status='completed' AND s.created_at >= CURDATE() AND s.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) {$cleanWhere}",
                $fParams
            )->fetchColumn();

            if ($todaySales === 0) {
                $alerts[] = [
                    'type' => 'zero_sales', 'severity' => 'critical',
                    'icon' => 'cash-register', 'color' => 'red',
                    'message' => 'No sales recorded today yet. Check POS terminal.',
                ];
            }
        }

        // Thin margin (< 15%)
        [$fWhere, $fParams] = $this->ctx->fullFilter($this->pdo, 's');
        $cleanWhere = $fWhere;
        
        $monthRev = (float) $this->prepare(
            "SELECT COALESCE(SUM(s.total),0) FROM sales s
             WHERE s.status='completed'
               AND MONTH(s.created_at)=MONTH(CURDATE())
               AND YEAR(s.created_at)=YEAR(CURDATE())
               {$cleanWhere}",
            $fParams
        )->fetchColumn();

        $monthCost = $this->costForPeriod(
            "DATE_FORMAT(CURDATE(), '%Y-%m-01')", 
            'CURDATE()', 
            $fWhere, 
            $fParams
        );

        if ($monthRev > 0) {
            $margin = (($monthRev - $monthCost) / $monthRev) * 100;
            if ($margin < 15) {
                $alerts[] = [
                    'type' => 'thin_margin', 'severity' => 'warning',
                    'icon' => 'piggy-bank', 'color' => 'amber',
                    'message' => sprintf('Profit margin is %.1f%%. Review pricing.', $margin),
                ];
            }
        }

        return $alerts;
    }
}
