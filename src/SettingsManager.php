<?php
declare(strict_types=1);

/**
 * Enterprise-grade Settings Manager for multi-tenant POS
 *
 * Handles all tenant-specific settings stored in the `tenant_settings` table.
 * Provides a clean API for getting/setting values, with caching and type safety.
 *
 * Table schema expected:
 *   CREATE TABLE tenant_settings (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     tenant_id INT NOT NULL,
 *     setting_key VARCHAR(100) NOT NULL,
 *     setting_value TEXT,
 *     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *     UNIQUE KEY unique_tenant_setting (tenant_id, setting_key),
 *     INDEX idx_tenant (tenant_id)
 *   );
 */
final class SettingsManager
{
    private \PDO $pdo;
    private int $tenantId;
    private array $cache = [];

    public function __construct(\PDO $pdo, int $tenantId)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->ensureTableExists();
    }

    /**
     * Auto-create the tenant_settings table if it doesn't exist (self-healing for dev).
     */
    private function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        try {
            $this->pdo->query("SELECT 1 FROM tenant_settings LIMIT 1");
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, "doesn't exist") || $e->getCode() === '42S02') {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS tenant_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        tenant_id INT NOT NULL,
                        setting_key VARCHAR(100) NOT NULL,
                        setting_value TEXT,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_tenant_setting (tenant_id, setting_key),
                        INDEX idx_tenant (tenant_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            }
        }
    }

    /**
     * Get a setting value (returns default if not set)
     */
    public function get(string $key, $default = null)
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $stmt = $this->pdo->prepare(
            "SELECT setting_value FROM tenant_settings 
             WHERE tenant_id = ? AND setting_key = ? LIMIT 1"
        );
        $stmt->execute([$this->tenantId, $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $value = $row ? $row['setting_value'] : $default;
        $this->cache[$key] = $value;

        return $value;
    }

    /**
     * Set a setting value (insert or update)
     */
    public function set(string $key, $value): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );

        $success = $stmt->execute([$this->tenantId, $key, (string)$value]);

        if ($success) {
            $this->cache[$key] = $value;
        }

        return $success;
    }

    /**
     * Get all settings for the tenant as associative array
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ?"
        );
        $stmt->execute([$this->tenantId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $this->cache = $settings + $this->cache;

        return $settings;
    }

    /**
     * Delete a setting
     */
    public function delete(string $key): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM tenant_settings WHERE tenant_id = ? AND setting_key = ?"
        );
        $success = $stmt->execute([$this->tenantId, $key]);

        if ($success) {
            unset($this->cache[$key]);
        }

        return $success;
    }

    // =====================================================================
    // Label Printing Specific Helpers (used by LabelPrinter / dashboard)
    // =====================================================================

    public function getLabelSettings(): array
    {
        $defaults = [
            'auto_print_labels_on_sale' => '0',
            'auto_print_label_size'     => 'k22',
            'auto_print_method'         => 'pdf',
            'printer_ip'                => '',
            'printer_port'              => '9100',
            'labels_per_row'            => '3',
            'show_price'                => '1',
            'show_sku'                  => '1',
            'show_short_code'           => '1',
        ];

        $settings = [];
        foreach ($defaults as $key => $default) {
            $val = $this->get($key, $default);
            $settings[$key] = $val;
        }

        return $settings;
    }

    public function setLabelSettings(array $settings): bool
    {
        $success = true;
        foreach ($settings as $key => $value) {
            if (!$this->set('label_' . $key, $value)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Convenience: get label size for current tenant
     */
    public function getLabelSize(): string
    {
        return $this->get('auto_print_label_size', 'k22');
    }

    /**
     * Convenience: get preferred print method
     */
    public function getLabelMethod(): string
    {
        return $this->get('auto_print_method', 'pdf');
    }

    /**
     * Convenience: whether auto-print on sale is enabled
     */
    public function isAutoPrintEnabled(): bool
    {
        return (bool)$this->get('auto_print_labels_on_sale', '0');
    }
}
