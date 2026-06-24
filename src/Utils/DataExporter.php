<?php
/**
 * Data Exporter Utility
 * Export data to CSV, Excel, or PDF formats
 * 
 * @package Jakababa\Utils
 * @version 1.0.0
 */

class DataExporter {
    
    /**
     * Export data to CSV format
     */
    public static function exportToCSV(array $data, array $headers, string $filename = 'export'): void {
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($headers as $key => $label) {
                $csvRow[] = $row[$key] ?? '';
            }
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export data to Excel format (HTML table with headers)
     */
    public static function exportToExcel(array $data, array $headers, string $filename = 'export'): void {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.xls"');
        
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head><meta charset="UTF-8"></head><body>';
        echo '<table border="1">';
        
        // Headers
        echo '<tr style="background-color: #fbbf24; font-weight: bold;">';
        foreach ($headers as $label) {
            echo '<th style="padding: 8px;">' . htmlspecialchars($label) . '</th>';
        }
        echo '</tr>';
        
        // Data rows
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($headers as $key => $label) {
                $value = $row[$key] ?? '';
                echo '<td style="padding: 6px;">' . htmlspecialchars((string)$value) . '</td>';
            }
            echo '</tr>';
        }
        
        echo '</table></body></html>';
        exit;
    }
    
    /**
     * Format data for export
     */
    public static function formatExportData(array $data, array $columnMap): array {
        $formatted = [];
        
        foreach ($data as $row) {
            $formattedRow = [];
            foreach ($columnMap as $key => $config) {
                $value = $row[$key] ?? '';
                
                // Apply formatter if provided
                if (isset($config['formatter']) && is_callable($config['formatter'])) {
                    $value = $config['formatter']($value, $row);
                }
                
                // Apply default value if empty
                if (empty($value) && isset($config['default'])) {
                    $value = $config['default'];
                }
                
                $formattedRow[$key] = $value;
            }
            $formatted[] = $formattedRow;
        }
        
        return $formatted;
    }
    
    /**
     * Export sales data
     */
    public static function exportSales(PDO $pdo, int $tenantId, array $filters = []): array {
        $sql = "
            SELECT 
                s.receipt_number,
                s.created_at,
                c.name as customer_name,
                s.final_amount,
                s.discount_amount,
                s.tax_amount,
                s.payment_method,
                s.status,
                u.name as cashier_name
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.tenant_id = ?
        ";
        
        $params = [$tenantId];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(s.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(s.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND s.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Export products data
     */
    public static function exportProducts(PDO $pdo, int $tenantId, array $filters = []): array {
        $sql = "
            SELECT 
                p.name,
                p.sku,
                p.barcode,
                p.price,
                p.cost_price,
                p.tax_rate,
                p.active,
                c.name as category,
                b.name as brand,
                i.stock as current_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.tenant_id = ? AND p.deleted_at IS NULL
        ";
        
        $params = [$tenantId];
        
        if (!empty($filters['active_only'])) {
            $sql .= " AND p.active = 1";
        }
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        $sql .= " ORDER BY p.name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Export customers data
     */
    public static function exportCustomers(PDO $pdo, int $tenantId): array {
        $stmt = $pdo->prepare("
            SELECT 
                name,
                email,
                phone,
                address,
                city,
                loyalty_points,
                active,
                created_at
            FROM customers
            WHERE tenant_id = ? AND deleted_at IS NULL
            ORDER BY name
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
