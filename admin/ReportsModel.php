<?php
/**
 * Reports Model - Zero-Trust Implementation
 * Handles reporting operations with controlled tenant access
 */

require_once dirname(__DIR__) . '/src/TenantContext.php';
require_once dirname(__DIR__) . '/src/BaseModel.php';

class ReportsModel extends BaseModel {
    protected $table = 'sales';

    public function getSalesSummary(string $dateFrom, string $dateTo): array {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total_sales, COALESCE(SUM(s.total), 0) AS total_revenue
            FROM sales s
            WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status = 'completed'
        ");
        $params = [$dateFrom, $dateTo];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_sales' => 0, 'total_revenue' => 0];
    }

    public function getRevenueByCompany(string $dateFrom, string $dateTo, int $limit = 10): array {
        // For SaaS admin, allow cross-tenant access but log it
        if ($this->context->allowCrossTenant()) {
            error_log("SaaS Admin accessing cross-tenant revenue data");
            $stmt = $this->pdo->prepare("
                SELECT c.name AS company_name, c.slug AS company_slug, COALESCE(SUM(s.total), 0) AS total_revenue, COUNT(s.id) AS sale_count
                FROM sales s
                JOIN companies c ON s.tenant_id = c.id
                WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status = 'completed'
                GROUP BY s.tenant_id
                ORDER BY total_revenue DESC
                LIMIT ?
            ");
            $stmt->execute([$dateFrom, $dateTo, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Regular tenant gets only their own data
        return [];
    }

    public function getRevenueByPayment(string $dateFrom, string $dateTo): array {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(s.payment_method, 'unknown') AS method, COUNT(*) AS cnt, COALESCE(SUM(s.total), 0) AS total_amount
            FROM sales s
            WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status = 'completed'
            GROUP BY s.payment_method
            ORDER BY total_amount DESC
        ");
        $params = [$dateFrom, $dateTo];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMonthlyTrend(): array {
        $stmt = $this->pdo->prepare("
            SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS month_label, COALESCE(SUM(s.total), 0) AS revenue, COUNT(*) AS sale_count
            FROM sales s
            WHERE s.status = 'completed' AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(s.created_at, '%Y-%m')
            ORDER BY month_label ASC
        ");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopProducts(string $dateFrom, string $dateTo, int $limit = 10): array {
        $stmt = $this->pdo->prepare("
            SELECT p.name AS product_name, p.sku, SUM(si.quantity) AS qty_sold, SUM(si.quantity * si.price) AS revenue
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            JOIN sales s ON si.sale_id = s.id
            WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status = 'completed'
            GROUP BY p.id
            ORDER BY qty_sold DESC
            LIMIT ?
        ");
        $params = [$dateFrom, $dateTo, $limit];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNewCompaniesCount(string $dateFrom, string $dateTo): int {
        // SaaS admin only - cross-tenant access
        if ($this->context->allowCrossTenant()) {
            error_log("SaaS Admin accessing new tenants count");
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS cnt FROM pos_tenants
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            return (int) $stmt->fetchColumn();
        }
        return 0;
    }

    public function getNewCompanies(string $dateFrom, string $dateTo, int $limit = 10): array {
        // SaaS admin only - cross-tenant access
        if ($this->context->allowCrossTenant()) {
            error_log("SaaS Admin accessing new tenants list");
            $stmt = $this->pdo->prepare("
                SELECT
                    name,
                    slug,
                    JSON_UNQUOTE(JSON_EXTRACT(settings, '$.email')) as email,
                    status,
                    DATE(created_at) AS created
                FROM pos_tenants
                WHERE DATE(created_at) BETWEEN ? AND ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$dateFrom, $dateTo, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }

    // CSV Export methods with proper scoping
    public function exportSalesCSV(string $dateFrom, string $dateTo): array {
        $stmt = $this->pdo->prepare("
            SELECT DATE(s.created_at) AS sale_date, COUNT(*) AS total_sales, COALESCE(SUM(s.total), 0) AS total_revenue
            FROM sales s
            WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status = 'completed'
            GROUP BY DATE(s.created_at)
            ORDER BY sale_date ASC
        ");
        $params = [$dateFrom, $dateTo];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function exportTopProductsCSV(string $dateFrom, string $dateTo): array {
        $stmt = $this->pdo->prepare("
            SELECT p.name AS product_name, SUM(si.quantity) AS qty_sold, SUM(si.quantity * si.price) AS revenue
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            JOIN sales s ON si.sale_id = s.id
            WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status = 'completed'
            GROUP BY p.id
            ORDER BY qty_sold DESC
            LIMIT 10
        ");
        $params = [$dateFrom, $dateTo];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function exportPaymentMethodsCSV(string $dateFrom, string $dateTo): array {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(s.payment_method, 'unknown') AS method, COUNT(*) AS cnt, COALESCE(SUM(s.total), 0) AS total_amount
            FROM sales s
            WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status = 'completed'
            GROUP BY s.payment_method
            ORDER BY total_amount DESC
        ");
        $params = [$dateFrom, $dateTo];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}