<?php
declare(strict_types=1);

/**
 * SaleRepository - Sales Data Access
 * 
 * Handles sales CRUD with multi-tenant isolation
 * Strict company and branch filtering
 * 
 * @package Jakababa
 * @subpackage Repository
 */

class SaleRepository extends BaseRepository
{
    protected string $tableName = 'sales';
    protected string $primaryKey = 'id';

    /**
     * Create a new sale with items
     */
    public function createSale(array $saleData, array $items): int
    {
        $this->beginTransaction();
        
        try {
            // Add company and branch to sale
            $saleData = $this->addCompanyToData($saleData);
            $saleData = $this->addBranchToData($saleData);
            
            // Generate sale number
            $saleData['sale_number'] = $this->generateSaleNumber();
            
            // Insert sale
            $columns = array_keys($saleData);
            $placeholders = array_map(fn($c) => ":{$c}", $columns);
            
            $sql = "INSERT INTO sales (" . implode(', ', $columns) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($saleData);
            
            $saleId = (int) $this->pdo->lastInsertId();
            
            // Insert sale items
            foreach ($items as $item) {
                $item['sale_id'] = $saleId;
                $item['tenant_id'] = $this->companyId;
                
                $itemColumns = array_keys($item);
                $itemPlaceholders = array_map(fn($c) => ":{$c}", $itemColumns);
                
                $itemSql = "INSERT INTO sale_items (" . implode(', ', $itemColumns) . ") 
                            VALUES (" . implode(', ', $itemPlaceholders) . ")";
                
                $itemStmt = $this->pdo->prepare($itemSql);
                $itemStmt->execute($item);
                
                // Update inventory stock
                $this->updateProductStock($item['product_id'], -$item['quantity']);
            }
            
            $this->commit();
            return $saleId;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Update product stock after sale
     */
    protected function updateProductStock(int $productId, int $quantityChange): void
    {
        // Check if inventory record exists
        $checkSql = "SELECT id, stock FROM inventory 
                     WHERE product_id = :product_id AND branch_id = :branch_id AND tenant_id = :tenant_id";
        
        $stmt = $this->pdo->prepare($checkSql);
        $stmt->execute([
            ':product_id' => $productId,
            ':branch_id' => $this->branchId,
            ':tenant_id' => $this->companyId
        ]);
        
        if ($stmt->rowCount() > 0) {
            // Update existing
            $updateSql = "UPDATE inventory 
                          SET stock = stock + :change, updated_at = NOW() 
                          WHERE product_id = :product_id AND branch_id = :branch_id AND tenant_id = :tenant_id";
            
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([
                ':change' => $quantityChange,
                ':product_id' => $productId,
                ':branch_id' => $this->branchId,
                ':tenant_id' => $this->companyId
            ]);
        } else {
            // Create new inventory record
            $insertSql = "INSERT INTO inventory (product_id, branch_id, tenant_id, stock, created_at, updated_at)
                          VALUES (:product_id, :branch_id, :tenant_id, :stock, NOW(), NOW())";
            
            $insertStmt = $this->pdo->prepare($insertSql);
            $insertStmt->execute([
                ':product_id' => $productId,
                ':branch_id' => $this->branchId,
                ':tenant_id' => $this->companyId,
                ':stock' => $quantityChange < 0 ? 0 : $quantityChange
            ]);
        }
        
        // Log stock movement if table exists
        $this->logStockMovement($productId, $quantityChange);
    }

    /**
     * Log stock movement
     */
    protected function logStockMovement(int $productId, int $quantityChange): void
    {
        try {
            $sql = "INSERT INTO stock_movements 
                    (tenant_id, branch_id, product_id, movement_type, quantity_change, 
                     quantity_before, quantity_after, notes, user_id, created_at)
                    SELECT :tenant_id, :branch_id, :product_id, 'sale', :change,
                           COALESCE(i.stock, 0), COALESCE(i.stock, 0) + :change,
                           :notes, :user_id, NOW()
                    FROM inventory i
                    WHERE i.product_id = :product_id AND i.branch_id = :branch_id AND i.tenant_id = :tenant_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':tenant_id' => $this->companyId,
                ':branch_id' => $this->branchId,
                ':product_id' => $productId,
                ':change' => $quantityChange,
                ':notes' => 'Sale transaction',
                ':user_id' => $this->userId
            ]);
        } catch (Exception $e) {
            // Non-critical - log but don't fail
            error_log("Stock movement log failed: " . $e->getMessage());
        }
    }

    /**
     * Generate unique sale number
     */
    protected function generateSaleNumber(): string
    {
        $prefix = date('Ymd');
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(sale_number, '-', -1) AS UNSIGNED)) as max_num
                FROM sales 
                WHERE sale_number LIKE :prefix";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':prefix' => $prefix . '-%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nextNum = ($result['max_num'] ?? 0) + 1;
        
        return $prefix . '-' . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Find sales with filters
     */
    public function findWithFilters(
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $orderBy = 'created_at',
        string $orderDir = 'DESC'
    ): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT SQL_CALC_FOUND_ROWS s.*, 
                       u.name as user_name, 
                       c.name as customer_name,
                       b.name as branch_name
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN branches b ON s.branch_id = b.id
                WHERE s.status != 'voided'";
        
        $params = [];
        
        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND s.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['customer_id'])) {
            $sql .= " AND s.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }
        
        if (!empty($filters['order_type'])) {
            $sql .= " AND s.order_type = :order_type";
            $params[':order_type'] = $filters['order_type'];
        }
        
        if (!empty($filters['payment_method'])) {
            $sql .= " AND s.payment_method = :payment_method";
            $params[':payment_method'] = $filters['payment_method'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND s.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND s.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $sql = $this->addCompanyFilter($sql, 's');
        $sql = $this->addBranchFilter($sql, 's');
        
        // Validate orderBy
        $allowedOrderBy = ['id', 'sale_number', 'total', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'created_at';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql .= " ORDER BY s.{$orderBy} {$orderDir} LIMIT :limit OFFSET :offset";
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;
        
        $stmt = $this->executeWithFilters($sql, $params);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $countStmt = $this->pdo->query("SELECT FOUND_ROWS() as total");
        $total = $countStmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'data' => $sales,
            'total' => (int) $total['total'],
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total['total'] / $perPage)
        ];
    }

    /**
     * Get sale with items
     */
    public function findByIdWithItems(int $saleId): ?array
    {
        // Get sale
        $sql = "SELECT s.*, u.name as user_name, c.name as customer_name
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE s.id = :id";
        
        $sql = $this->addCompanyFilter($sql, 's');
        
        $stmt = $this->executeWithFilters($sql, [':id' => $saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            return null;
        }
        
        // Get items
        $itemsSql = "SELECT si.*, p.name as product_name, p.sku, p.barcode
                     FROM sale_items si
                     LEFT JOIN products p ON si.product_id = p.id
                     WHERE si.sale_id = :sale_id";
        
        $itemsStmt = $this->pdo->prepare($itemsSql);
        $itemsStmt->execute([':sale_id' => $saleId]);
        $sale['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $sale;
    }

    /**
     * Get today's sales summary
     */
    public function getTodaySummary(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    COALESCE(SUM(subtotal), 0) as subtotal,
                    COALESCE(SUM(tax), 0) as tax,
                    COALESCE(SUM(discount), 0) as discount,
                    COALESCE(SUM(total), 0) as total
                FROM sales 
                WHERE DATE(created_at) = CURDATE() 
                AND status = 'completed'
                AND status != 'voided'";
        
        $sql = $this->addCompanyFilter($sql);
        $sql = $this->addBranchFilter($sql);
        
        $stmt = $this->executeWithFilters($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get sales by date range
     */
    public function getByDateRange(string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT s.*, u.name as user_name, b.name as branch_name
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN branches b ON s.branch_id = b.id
                WHERE s.created_at BETWEEN :date_from AND :date_to
                AND s.status != 'voided'";
        
        $params = [
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo
        ];
        
        $sql = $this->addCompanyFilter($sql, 's');
        $sql = $this->addBranchFilter($sql, 's');
        $sql .= " ORDER BY s.created_at DESC";
        
        $stmt = $this->executeWithFilters($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get sales by payment method
     */
    public function getByPaymentMethod(string $method, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT s.*, u.name as user_name
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.payment_method = :method
                AND s.status = 'completed'
                AND s.status != 'voided'";
        
        $params = [':method' => $method];
        
        if ($dateFrom) {
            $sql .= " AND s.created_at >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND s.created_at <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        
        $sql = $this->addCompanyFilter($sql, 's');
        $sql = $this->addBranchFilter($sql, 's');
        
        $stmt = $this->executeWithFilters($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Void a sale
     */
    public function voidSale(int $saleId, string $reason): bool
    {
        $this->beginTransaction();
        
        try {
            // Get sale items first
            $itemsSql = "SELECT product_id, quantity FROM sale_items WHERE sale_id = :sale_id";
            $itemsStmt = $this->pdo->prepare($itemsSql);
            $itemsStmt->execute([':sale_id' => $saleId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Restore inventory
            foreach ($items as $item) {
                $this->updateProductStock($item['product_id'], $item['quantity']);
            }
            
            // Update sale status
            $sql = "UPDATE sales 
                    SET status = 'voided', 
                        voided = 1,
                        voided_by = :user_id,
                        voided_at = NOW(),
                        void_reason = :reason
                    WHERE id = :sale_id";
            
            $sql = $this->addCompanyFilter($sql);
            
            $params = [
                ':sale_id' => $saleId,
                ':user_id' => $this->userId,
                ':reason' => $reason
            ];
            
            if (!$this->isSuperAdmin && $this->companyId) {
                $params[':tenant_id'] = $this->companyId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $this->commit();

            // Submit credit note to KRA eTIMS (after commit, non-blocking)
            try {
                $etimsPath = __DIR__ . '/../Services/Integration/KRA/EtimsService.php';
                if (file_exists($etimsPath) && $this->companyId) {
                    require_once $etimsPath;
                    $etimsService = new \Jakababa\Services\Integration\KRA\EtimsService($this->pdo, $this->companyId);
                    if ($etimsService->isEnabled()) {
                        $returnItems = [];
                        foreach ($items as $item) {
                            $itemDetail = $this->pdo->prepare("
                                SELECT si.*, p.name, p.sku, p.barcode, p.tax_rate, p.etims_item_code, p.etims_class_code
                                FROM sale_items si JOIN products p ON si.product_id = p.id
                                WHERE si.sale_id = ? AND si.product_id = ?
                            ");
                            $itemDetail->execute([$saleId, $item['product_id']]);
                            $detail = $itemDetail->fetch(\PDO::FETCH_ASSOC);
                            if ($detail) {
                                $detail['quantity'] = $item['quantity'];
                                $returnItems[] = $detail;
                            }
                        }
                        if (!empty($returnItems)) {
                            $etimsService->submitCreditNote($saleId, $returnItems);
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log("eTIMS credit note failed for voided sale {$saleId}: " . $e->getMessage());
            }

            return $stmt->rowCount() > 0;
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Get sales count by status
     */
    public function getCountByStatus(): array
    {
        $sql = "SELECT status, COUNT(*) as count, SUM(total) as total
                FROM sales 
                WHERE status != 'voided'";
        
        $sql = $this->addCompanyFilter($sql);
        $sql = $this->addBranchFilter($sql);
        $sql .= " GROUP BY status";
        
        $stmt = $this->executeWithFilters($sql);
        
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['status']] = [
                'count' => (int) $row['count'],
                'total' => (float) $row['total']
            ];
        }
        
        return $result;
    }
}
