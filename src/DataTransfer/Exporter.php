<?php
/**
 * Data Export/Import System
 * Export/import products, sales, customers in various formats
 */

namespace JDH\POS\DataTransfer;

class Exporter
{
    private \PDO $pdo;
    private int $tenantId;
    
    public function __construct(\PDO $pdo, int $tenantId)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
    }
    
    /**
     * Export products to CSV
     */
    public function exportProducts(string $format = 'csv'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.sku,
                p.name,
                p.description,
                p.price,
                p.cost,
                c.name as category,
                p.barcode,
                p.tax_rate,
                p.status
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.tenant_id = ?
            AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute([$this->tenantId]);
        $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        switch ($format) {
            case 'csv':
                return $this->toCsv($products, 'products_export_' . date('Y-m-d') . '.csv');
            case 'json':
                return $this->toJson($products, 'products_export_' . date('Y-m-d') . '.json');
            case 'excel':
                return $this->toExcel($products, 'products_export_' . date('Y-m-d') . '.xlsx');
            default:
                throw new \Exception("Unsupported format: {$format}");
        }
    }
    
    /**
     * Export sales to CSV
     */
    public function exportSales(string $dateFrom = null, string $dateTo = null, string $format = 'csv'): array
    {
        $where = 's.tenant_id = ?';
        $params = [$this->tenantId];
        
        if ($dateFrom) {
            $where .= ' AND s.created_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where .= ' AND s.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        
        $stmt = $this->pdo->prepare("
            SELECT 
                s.id,
                s.receipt_number,
                s.total_amount,
                s.tax_amount,
                s.discount_amount,
                s.final_amount,
                s.payment_method,
                s.status,
                s.created_at,
                u.name as cashier,
                c.name as customer
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE {$where}
            ORDER BY s.created_at DESC
        ");
        $stmt->execute($params);
        $sales = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->toCsv($sales, 'sales_export_' . date('Y-m-d') . '.csv');
    }
    
    /**
     * Export customers to CSV
     */
    public function exportCustomers(string $format = 'csv'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                name,
                email,
                phone,
                address,
                city,
                loyalty_points,
                total_spent,
                created_at
            FROM customers
            WHERE tenant_id = ?
            ORDER BY name
        ");
        $stmt->execute([$this->tenantId]);
        $customers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->toCsv($customers, 'customers_export_' . date('Y-m-d') . '.csv');
    }
    
    /**
     * Import products from CSV
     */
    public function importProducts(string $filePath, bool $skipValidation = false): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Cannot open file: {$filePath}");
        }
        
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new \Exception("Invalid CSV file - no headers found");
        }
        
        $imported = 0;
        $errors = [];
        $rowNum = 1;
        
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO products 
                (tenant_id, sku, name, description, price, cost_price, category_id, barcode, tax_rate, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                price = VALUES(price),
                cost_price = VALUES(cost_price),
                category_id = VALUES(category_id),
                barcode = VALUES(barcode),
                tax_rate = VALUES(tax_rate),
                status = VALUES(status),
                updated_at = NOW()
            ");
            
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                $data = array_combine($headers, $row);
                
                try {
                    // Validate required fields
                    if (empty($data['sku']) || empty($data['name'])) {
                        throw new \Exception("Missing required fields (sku, name)");
                    }
                    
                    // Get or create category
                    $categoryId = $this->getOrCreateCategory($data['category'] ?? 'Uncategorized');
                    
                    $stmt->execute([
                        $this->tenantId,
                        $data['sku'],
                        $data['name'],
                        $data['description'] ?? null,
                        $data['price'] ?? 0,
                        $data['cost_price'] ?? 0,
                        $categoryId,
                        $data['barcode'] ?? null,
                        $data['tax_rate'] ?? 0,
                        $data['status'] ?? 'active'
                    ]);
                    
                    $imported++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }
            
            fclose($handle);
            $this->pdo->commit();
            
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            fclose($handle);
            throw $e;
        }
        
        return [
            'success' => true,
            'imported' => $imported,
            'errors' => $errors,
            'total' => $rowNum - 1
        ];
    }
    
    /**
     * Import customers from CSV
     */
    public function importCustomers(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Cannot open file: {$filePath}");
        }
        
        $headers = fgetcsv($handle);
        $imported = 0;
        $errors = [];
        $rowNum = 1;
        
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO customers 
                (tenant_id, name, email, phone, address, city, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                phone = VALUES(phone),
                address = VALUES(address),
                city = VALUES(city),
                updated_at = NOW()
            ");
            
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                $data = array_combine($headers, $row);
                
                try {
                    if (empty($data['name'])) {
                        throw new \Exception("Customer name is required");
                    }
                    
                    $stmt->execute([
                        $this->tenantId,
                        $data['name'],
                        $data['email'] ?? null,
                        $data['phone'] ?? null,
                        $data['address'] ?? null,
                        $data['city'] ?? null
                    ]);
                    
                    $imported++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }
            
            fclose($handle);
            $this->pdo->commit();
            
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            fclose($handle);
            throw $e;
        }
        
        return [
            'success' => true,
            'imported' => $imported,
            'errors' => $errors,
            'total' => $rowNum - 1
        ];
    }
    
    /**
     * Download export file
     */
    public function download(string $filepath, string $filename): void
    {
        if (!file_exists($filepath)) {
            throw new \Exception("File not found");
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        
        readfile($filepath);
    }
    
    private function toCsv(array $data, string $filename): array
    {
        if (empty($data)) {
            return ['success' => false, 'error' => 'No data to export'];
        }
        
        $filepath = sys_get_temp_dir() . '/' . $filename;
        $handle = fopen($filepath, 'w');
        
        // Write headers
        fputcsv($handle, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        
        return [
            'success' => true,
            'file' => $filepath,
            'filename' => $filename,
            'count' => count($data)
        ];
    }
    
    private function toJson(array $data, string $filename): array
    {
        $filepath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
        
        return [
            'success' => true,
            'file' => $filepath,
            'filename' => $filename,
            'count' => count($data)
        ];
    }
    
    private function toExcel(array $data, string $filename): array
    {
        // Would require PhpSpreadsheet library
        // For now, fall back to CSV
        return $this->toCsv($data, str_replace('.xlsx', '.csv', $filename));
    }
    
    private function getOrCreateCategory(string $categoryName): int
    {
        // Check if category exists
        $stmt = $this->pdo->prepare("
            SELECT id FROM categories 
            WHERE name = ? AND tenant_id = ?
        ");
        $stmt->execute([$categoryName, $this->tenantId]);
        $id = $stmt->fetchColumn();
        
        if ($id) {
            return (int) $id;
        }
        
        // Create new category
        $stmt = $this->pdo->prepare("
            INSERT INTO categories (tenant_id, name, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$this->tenantId, $categoryName]);
        
        return (int) $this->pdo->lastInsertId();
    }
}

/**
 * Global helper functions
 */

if (!function_exists('export_data')) {
    /**
     * Export data helper
     */
    function export_data(\PDO $pdo, int $tenantId, string $type, string $format = 'csv'): array
    {
        $exporter = new \JDH\POS\DataTransfer\Exporter($pdo, $tenantId);
        
        switch ($type) {
            case 'products':
                return $exporter->exportProducts($format);
            case 'sales':
                return $exporter->exportSales(null, null, $format);
            case 'customers':
                return $exporter->exportCustomers($format);
            default:
                throw new \Exception("Unknown export type: {$type}");
        }
    }
}
