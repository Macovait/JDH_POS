<?php
/**
 * Edge POS - Offline Capability Layer
 * 
 * Provides local SQLite storage and sync capabilities for offline resiliency.
 * Critical for African markets with intermittent connectivity.
 * 
 * @package JDH_POS\POS
 * @version 1.0.0
 */

namespace JDH_POS\POS;

use PDO;
use Exception;

class EdgePOSService
{
    private ?PDO $localDb = null;
    private string $dbPath;
    private array $syncQueue = [];
    private bool $isOnline = true;
    
    const MAX_OFFLINE_DAYS = 30;
    const SYNC_BATCH_SIZE = 100;
    
    public function __construct()
    {
        $this->dbPath = STORAGE_PATH . '/edge/pos local.sqlite';
        $this->initializeLocalDb();
        $this->checkConnectivity();
    }
    
    private function initializeLocalDb(): void
    {
        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $this->localDb = new PDO('sqlite:' . $this->dbPath);
        $this->localDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->createTables();
    }
    
    private function createTables(): void
    {
        $tables = [
            'offline_transactions' => "
                CREATE TABLE IF NOT EXISTS offline_transactions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant_id INTEGER NOT NULL,
                    branch_id INTEGER,
                    transaction_type TEXT NOT NULL,
                    data TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    synced_at DATETIME,
                    status TEXT DEFAULT 'pending'
                )
            ",
            'offline_products' => "
                CREATE TABLE IF NOT EXISTS offline_products (
                    id INTEGER PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    price REAL NOT NULL,
                    category_id INTEGER,
                    sku TEXT,
                    barcode TEXT,
                    stock INTEGER DEFAULT 0,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            'offline_customers' => "
                CREATE TABLE IF NOT EXISTS offline_customers (
                    id INTEGER PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    phone TEXT,
                    email TEXT,
                    loyalty_points INTEGER DEFAULT 0,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            'offline_settings' => "
                CREATE TABLE IF NOT EXISTS offline_settings (
                    key TEXT PRIMARY KEY,
                    value TEXT,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            'sync_queue' => "
                CREATE TABLE IF NOT EXISTS sync_queue (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    table_name TEXT NOT NULL,
                    record_id INTEGER NOT NULL,
                    operation TEXT NOT NULL,
                    data TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    attempts INTEGER DEFAULT 0,
                    last_error TEXT
                )
            ",
            'conflict_log' => "
                CREATE TABLE IF NOT EXISTS conflict_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    table_name TEXT NOT NULL,
                    record_id INTEGER NOT NULL,
                    local_data TEXT NOT NULL,
                    server_data TEXT NOT NULL,
                    resolution TEXT,
                    resolved_at DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            "
        ];
        
        foreach ($tables as $sql) {
            $this->localDb->exec($sql);
        }
        
        $this->createIndexes();
    }
    
    private function createIndexes(): void
    {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_transactions_tenant ON offline_transactions(tenant_id)",
            "CREATE INDEX IF NOT EXISTS idx_transactions_status ON offline_transactions(status)",
            "CREATE INDEX IF NOT EXISTS idx_products_tenant ON offline_products(tenant_id)",
            "CREATE INDEX IF NOT EXISTS idx_customers_tenant ON offline_customers(tenant_id)",
            "CREATE INDEX IF NOT EXISTS idx_sync_queue_table ON sync_queue(table_name)",
            "CREATE INDEX IF NOT EXISTS idx_sync_queue_status ON sync_queue(attempts)"
        ];
        
        foreach ($indexes as $sql) {
            try {
                $this->localDb->exec($sql);
            } catch (Exception $e) {
                // Index may already exist
            }
        }
    }
    
    private function checkConnectivity(): void
    {
        $host = getenv('APP_URL') ?: 'http://localhost';
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'ignore_errors' => true
                ]
            ]);
            
            $response = @file_get_contents($host, false, $context);
            $this->isOnline = ($response !== false);
            
        } catch (Exception $e) {
            $this->isOnline = false;
        }
    }
    
    public function isOnline(): bool
    {
        return $this->isOnline;
    }
    
    public function getConnectionStatus(): array
    {
        return [
            'is_online' => $this->isOnline,
            'last_check' => date('Y-m-d H:i:s'),
            'pending_sync' => $this->getPendingSyncCount(),
            'last_sync' => $this->getLastSyncTime()
        ];
    }
    
    private function getPendingSyncCount(): int
    {
        $stmt = $this->localDb->prepare("
            SELECT COUNT(*) FROM sync_queue 
            WHERE attempts < 3
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
    
    private function getLastSyncTime(): ?string
    {
        $stmt = $this->localDb->query("
            SELECT MAX(created_at) FROM sync_queue 
            WHERE status = 'synced'
        ");
        return $stmt->fetchColumn();
    }
    
    // ============================================
    // OFFLINE TRANSACTION STORAGE
    // ============================================
    
    public function storeOfflineTransaction(int $tenantId, int $branchId, string $type, array $data): int
    {
        $stmt = $this->localDb->prepare("
            INSERT INTO offline_transactions (tenant_id, branch_id, transaction_type, data, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $tenantId,
            $branchId,
            $type,
            json_encode($data)
        ]);
        
        $transactionId = (int) $this->localDb->lastInsertId();
        
        $this->addToSyncQueue('offline_transactions', $transactionId, 'insert', $data);
        
        return $transactionId;
    }
    
    public function getOfflineTransactions(int $tenantId, int $limit = 50): array
    {
        $stmt = $this->localDb->prepare("
            SELECT * FROM offline_transactions 
            WHERE tenant_id = ? AND status = 'pending'
            ORDER BY created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$tenantId, $limit]);
        
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($transactions as &$t) {
            $t['data'] = json_decode($t['data'], true);
        }
        
        return $transactions;
    }
    
    // ============================================
    // OFFLINE PRODUCT CACHE
    // ============================================
    
    public function cacheProducts(array $products): void
    {
        $this->localDb->beginTransaction();
        
        $stmt = $this->localDb->prepare("
            INSERT OR REPLACE INTO offline_products 
            (id, tenant_id, name, price, category_id, sku, barcode, stock, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        foreach ($products as $product) {
            $stmt->execute([
                $product['id'],
                $product['tenant_id'],
                $product['name'],
                $product['price'],
                $product['category_id'] ?? null,
                $product['sku'] ?? null,
                $product['barcode'] ?? null,
                $product['stock'] ?? 0
            ]);
        }
        
        $this->localDb->commit();
    }
    
    public function getOfflineProducts(int $tenantId, string $search = ''): array
    {
        $sql = "
            SELECT * FROM offline_products 
            WHERE tenant_id = ?
        ";
        
        $params = [$tenantId];
        
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR sku LIKE ? OR barcode LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY name LIMIT 100";
        
        $stmt = $this->localDb->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findOfflineProduct(int $tenantId, string $barcodeOrSku): ?array
    {
        $stmt = $this->localDb->prepare("
            SELECT * FROM offline_products 
            WHERE tenant_id = ? AND (barcode = ? OR sku = ?)
            LIMIT 1
        ");
        
        $stmt->execute([$tenantId, $barcodeOrSku, $barcodeOrSku]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    // ============================================
    // OFFLINE CUSTOMER CACHE
    // ============================================
    
    public function cacheCustomers(array $customers): void
    {
        $this->localDb->beginTransaction();
        
        $stmt = $this->localDb->prepare("
            INSERT OR REPLACE INTO offline_customers 
            (id, tenant_id, name, phone, email, loyalty_points, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        foreach ($customers as $customer) {
            $stmt->execute([
                $customer['id'],
                $customer['tenant_id'],
                $customer['name'],
                $customer['phone'] ?? null,
                $customer['email'] ?? null,
                $customer['loyalty_points'] ?? 0
            ]);
        }
        
        $this->localDb->commit();
    }
    
    public function getOfflineCustomer(int $tenantId, string $phoneOrName): ?array
    {
        $stmt = $this->localDb->prepare("
            SELECT * FROM offline_customers 
            WHERE tenant_id = ? AND (phone = ? OR name LIKE ?)
            LIMIT 1
        ");
        
        $search = "%{$phoneOrName}%";
        $stmt->execute([$tenantId, $phoneOrName, $search]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    // ============================================
    // SYNC QUEUE MANAGEMENT
    // ============================================
    
    private function addToSyncQueue(string $table, int $recordId, string $operation, array $data): void
    {
        $stmt = $this->localDb->prepare("
            INSERT INTO sync_queue (table_name, record_id, operation, data)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $table,
            $recordId,
            $operation,
            json_encode($data)
        ]);
    }
    
    public function processSyncQueue(PDO $serverDb): array
    {
        $results = [
            'synced' => 0,
            'failed' => 0,
            'conflicts' => 0
        ];
        
        if (!$this->isOnline()) {
            return $results;
        }
        
        $stmt = $this->localDb->prepare("
            SELECT * FROM sync_queue 
            WHERE attempts < 3
            ORDER BY created_at ASC
            LIMIT ?
        ");
        
        $stmt->execute([self::SYNC_BATCH_SIZE]);
        $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($queue as $item) {
            try {
                $this->syncRecord($serverDb, $item);
                $results['synced']++;
                
                // Remove from queue
                $this->localDb->exec("DELETE FROM sync_queue WHERE id = " . $item['id']);
                
            } catch (Exception $e) {
                $results['failed']++;
                
                // Increment attempts
                $update = $this->localDb->prepare("
                    UPDATE sync_queue 
                    SET attempts = attempts + 1, last_error = ?
                    WHERE id = ?
                ");
                $update->execute([$e->getMessage(), $item['id']]);
                
                // Check for conflict
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $results['conflicts']++;
                    $this->logConflict($item, $e->getMessage());
                }
            }
        }
        
        return $results;
    }
    
    private function syncRecord(PDO $serverDb, array $item): void
    {
        $data = json_decode($item['data'], true);
        $table = $item['table_name'];
        $operation = $item['operation'];
        
        switch ($operation) {
            case 'insert':
                $this->syncInsert($serverDb, $table, $data);
                break;
            case 'update':
                $this->syncUpdate($serverDb, $table, $data);
                break;
            case 'delete':
                $this->syncDelete($serverDb, $table, $item['record_id']);
                break;
        }
    }
    
    private function syncInsert(PDO $serverDb, string $table, array $data): void
    {
        $keys = array_keys($data);
        $cols = implode(', ', $keys);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        
        $sql = "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})";
        
        $stmt = $serverDb->prepare($sql);
        $stmt->execute(array_values($data));
    }
    
    private function syncUpdate(PDO $serverDb, string $table, array $data): void
    {
        if (!isset($data['id'])) return;
        
        $id = $data['id'];
        unset($data['id']);
        
        $sets = [];
        foreach (array_keys($data) as $key) {
            $sets[] = "{$key} = ?";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = ?";
        
        $stmt = $serverDb->prepare($sql);
        $stmt->execute(array_values($data));
    }
    
    private function syncDelete(PDO $serverDb, string $table, int $id): void
    {
        $sql = "DELETE FROM {$table} WHERE id = ?";
        $stmt = $serverDb->prepare($sql);
        $stmt->execute([$id]);
    }
    
    private function logConflict(array $item, string $error): void
    {
        $stmt = $this->localDb->prepare("
            INSERT INTO conflict_log (table_name, record_id, local_data, server_data)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $item['table_name'],
            $item['record_id'],
            $item['data'],
            $error
        ]);
    }
    
    public function resolveConflict(int $conflictId, string $resolution, array $data): void
    {
        $stmt = $this->localDb->prepare("
            UPDATE conflict_log 
            SET resolution = ?, resolved_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([$resolution, $conflictId]);
    }
    
    // ============================================
    // SETTINGS CACHE
    // ============================================
    
    public function cacheSettings(array $settings): void
    {
        $stmt = $this->localDb->prepare("
            INSERT OR REPLACE INTO offline_settings (key, value, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, is_array($value) ? json_encode($value) : $value]);
        }
    }
    
    public function getOfflineSetting(string $key, $default = null)
    {
        $stmt = $this->localDb->prepare("
            SELECT value FROM offline_settings WHERE key = ?
        ");
        
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        
        if ($result === false) {
            return $default;
        }
        
        // Try JSON decode
        $decoded = json_decode($result, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $result;
    }
    
    // ============================================
    // STORAGE MANAGEMENT
    // ============================================
    
    public function getStorageStats(): array
    {
        $tables = ['offline_transactions', 'offline_products', 'offline_customers', 'sync_queue'];
        
        $stats = [];
        foreach ($tables as $table) {
            $stmt = $this->localDb->query("SELECT COUNT(*) FROM {$table}");
            $stats[$table] = (int) $stmt->fetchColumn();
        }
        
        return $stats;
    }
    
    public function clearOldData(int $olderThanDays = 30): int
    {
        $cutoff = date('Y-m-d', strtotime("-{$olderThanDays} days"));
        
        $deleted = 0;
        
        // Clean old synced transactions
        $stmt = $this->localDb->prepare("
            DELETE FROM offline_transactions 
            WHERE synced_at < ? AND status = 'synced'
        ");
        $stmt->execute([$cutoff]);
        $deleted += $stmt->rowCount();
        
        // Clean old failed sync attempts
        $stmt = $this->localDb->prepare("
            DELETE FROM sync_queue 
            WHERE attempts >= 3 AND created_at < ?
        ");
        $stmt->execute([$cutoff]);
        $deleted += $stmt->rowCount();
        
        // Clean old resolved conflicts
        $stmt = $this->localDb->prepare("
            DELETE FROM conflict_log 
            WHERE resolved_at < ?
        ");
        $stmt->execute([$cutoff]);
        $deleted += $stmt->rowCount();
        
        return $deleted;
    }
    
    public function getDbSize(): int
    {
        return file_exists($this->dbPath) ? filesize($this->dbPath) : 0;
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function get_edge_pos(): EdgePOSService
{
    static $instance = null;
    
    if ($instance === null) {
        $instance = new EdgePOSService();
    }
    
    return $instance;
}

function is_pos_online(): bool
{
    return get_edge_pos()->isOnline();
}

function get_pos_connection_status(): array
{
    return get_edge_pos()->getConnectionStatus();
}