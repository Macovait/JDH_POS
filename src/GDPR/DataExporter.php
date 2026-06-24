<?php
namespace JDH\POS\GDPR;
use PDO, Exception, ZipArchive;
class DataExporter {
    private PDO $pdo; private string $exportPath;
    public function __construct(PDO $pdo, string $exportPath = __DIR__ . '/../../storage/exports') {
        $this->pdo = $pdo; $this->exportPath = $exportPath;
        if (!is_dir($exportPath)) mkdir($exportPath, 0755, true);
    }
    public function export(string $type, int $tenantId, ?int $recordId = null): string {
        $ts = date('Ymd_His');
        $zipName = "gdpr_{$type}_{$tenantId}" . ($recordId ? "_c{$recordId}" : "") . "_{$ts}.zip";
        $zipPath = $this->exportPath . '/' . $zipName;
        $zip = new ZipArchive();
        if (!$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) throw new Exception('Cannot create ZIP');
        try {
            if ($type === 'tenant') $this->exportTenant($zip, $tenantId);
            elseif ($type === 'customer') $this->exportCustomer($zip, $tenantId, $recordId);
            else throw new Exception("Unknown type: {$type}");
            $zip->addFromString('manifest.json', json_encode(['exported_at' => date('c'), 'type' => $type, 'tenant_id' => $tenantId, 'record_id' => $recordId], JSON_PRETTY_PRINT));
            $zip->close();
            return $zipPath;
        } catch (Exception $e) { $zip->close(); if (file_exists($zipPath)) unlink($zipPath); throw $e; }
    }
    private function exportTenant(ZipArchive $zip, int $tenantId): void {
        $tables = ['pos_tenants'=>'SELECT * FROM pos_tenants WHERE id = ?','pos_subscriptions'=>'SELECT * FROM pos_subscriptions WHERE tenant_id = ?','pos_invoices'=>'SELECT * FROM pos_invoices WHERE tenant_id = ?','support_tickets'=>'SELECT * FROM support_tickets WHERE tenant_id = ?','credits'=>'SELECT * FROM credits WHERE tenant_id = ?','subscription_history'=>'SELECT * FROM subscription_history WHERE tenant_id = ?','products'=>'SELECT p.id,p.name,p.sku,p.price,COALESCE(SUM(i.stock),0) AS stock_quantity,p.created_at,p.updated_at FROM products p LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id WHERE p.tenant_id = ? AND p.deleted_at IS NULL GROUP BY p.id','customers'=>'SELECT id,name,email,phone,loyalty_points,created_at,updated_at FROM customers WHERE tenant_id = ?','sales'=>'SELECT id,customer_id,total,payment_method,created_at FROM sales WHERE tenant_id = ?','audit_logs'=>'SELECT action,entity_type,entity_id,details,created_at FROM audit_logs WHERE tenant_id = ?'];
        foreach ($tables as $name => $sql) { $stmt = $this->pdo->prepare($sql); $stmt->execute([$tenantId]); $zip->addFromString("tenant/{$name}.json", json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT)); }
    }
    private function exportCustomer(ZipArchive $zip, int $tenantId, int $customerId): void {
        foreach (['customers'=>'SELECT * FROM customers WHERE tenant_id = ? AND id = ?','sales'=>'SELECT * FROM sales WHERE tenant_id = ? AND customer_id = ?','loyalty_transactions'=>'SELECT * FROM loyalty_transactions WHERE tenant_id = ? AND customer_id = ?'] as $name => $sql) { $stmt = $this->pdo->prepare($sql); $stmt->execute([$tenantId, $customerId]); $zip->addFromString("customer/{$name}.json", json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT)); }
    }
    public function anonymizeCustomer(int $tenantId, int $customerId): void {
        $this->pdo->prepare("UPDATE customers SET name='Deleted User', email=CONCAT('deleted_',id,'@anonymized.local'), phone=NULL, address=NULL, updated_at=NOW() WHERE tenant_id = ? AND id = ?")->execute([$tenantId, $customerId]);
    }
    public function deleteTenantData(int $tenantId): void {
        // Only soft-delete or anonymize; keep invoices for legal retention
        $this->pdo->prepare("UPDATE pos_tenants SET status='deleted', name='Deleted Tenant', email=NULL, phone=NULL, updated_at=NOW() WHERE id = ?")->execute([$tenantId]);
    }
}
