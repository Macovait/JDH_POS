<?php
/**
 * Label Print Service - Enterprise Barcode Label Printing
 * Stub implementation for Jakababa POS
 */

namespace JDH\POS\Barcode;

class LabelPrintService
{
    private $pdo;
    private $tenantId;
    private $branchId;
    private $userId;

    public function __construct($pdo, int $tenantId, ?int $branchId, int $userId)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->branchId = $branchId;
        $this->userId = $userId;
    }

    /**
     * Resolve store name from tenant settings
     */
    public static function resolveStoreName($pdo, int $tenantId): string
    {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'store_name' LIMIT 1");
            $stmt->execute([$tenantId]);
            $result = $stmt->fetchColumn();
            if ($result) {
                return $result;
            }
        } catch (\Exception $e) {
            error_log("resolveStoreName error: " . $e->getMessage());
        }

        // Fallback to tenant name
        try {
            $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ? LIMIT 1");
            $stmt->execute([$tenantId]);
            $result = $stmt->fetchColumn();
            if ($result) {
                return $result;
            }
        } catch (\Exception $e) {
            error_log("resolveStoreName fallback error: " . $e->getMessage());
        }

        return 'Store';
    }
}
