<?php
/**
 * Background Job Queue System
 * For handling heavy tasks asynchronously
 */

namespace JDH\POS\Jobs;

class JobQueue
{
    private \PDO $pdo;
    
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTableExists();
    }
    
    /**
     * Add a job to the queue
     */
    public function push(string $type, array $payload, int $delaySeconds = 0, ?int $tenantId = null): int
    {
        $availableAt = date('Y-m-d H:i:s', time() + $delaySeconds);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO job_queue (type, payload, tenant_id, status, available_at, created_at)
            VALUES (?, ?, ?, 'pending', ?, NOW())
        ");
        
        $stmt->execute([
            $type,
            json_encode($payload),
            $tenantId,
            $availableAt
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }
    
    /**
     * Get next available job
     */
    public function pop(): ?array
    {
        $this->pdo->beginTransaction();
        
        try {
            // Find and lock next job
            $stmt = $this->pdo->query("
                SELECT * FROM job_queue 
                WHERE status = 'pending' 
                AND available_at <= NOW()
                ORDER BY priority DESC, created_at ASC
                LIMIT 1
                FOR UPDATE SKIP LOCKED
            ");
            
            $job = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$job) {
                $this->pdo->rollBack();
                return null;
            }
            
            // Mark as processing
            $update = $this->pdo->prepare("
                UPDATE job_queue 
                SET status = 'processing', started_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$job['id']]);
            
            $this->pdo->commit();
            
            $job['payload'] = json_decode($job['payload'], true);
            return $job;
            
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Mark job as completed
     */
    public function complete(int $jobId, ?array $result = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE job_queue 
            SET status = 'completed', completed_at = NOW(), result = ?
            WHERE id = ?
        ");
        $stmt->execute([$result ? json_encode($result) : null, $jobId]);
    }
    
    /**
     * Mark job as failed
     */
    public function fail(int $jobId, string $error, bool $shouldRetry = true): void
    {
        $stmt = $this->pdo->prepare("
            SELECT attempts FROM job_queue WHERE id = ?
        ");
        $stmt->execute([$jobId]);
        $attempts = (int) $stmt->fetchColumn() + 1;
        $maxRetries = 3;
        
        if ($shouldRetry && $attempts < $maxRetries) {
            // Retry with exponential backoff
            $delay = pow(2, $attempts) * 60; // 2, 4, 8 minutes
            $availableAt = date('Y-m-d H:i:s', time() + $delay);
            
            $update = $this->pdo->prepare("
                UPDATE job_queue 
                SET status = 'pending', attempts = ?, available_at = ?, error = ?
                WHERE id = ?
            ");
            $update->execute([$attempts, $availableAt, $error, $jobId]);
        } else {
            // Mark as failed
            $update = $this->pdo->prepare("
                UPDATE job_queue 
                SET status = 'failed', attempts = ?, failed_at = NOW(), error = ?
                WHERE id = ?
            ");
            $update->execute([$attempts, $error, $jobId]);
        }
    }
    
    /**
     * Get job statistics
     */
    public function stats(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM job_queue
            GROUP BY status
        ");
        
        $stats = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = (int) $row['count'];
        }
        
        return $stats;
    }
    
    /**
     * Clean up old completed jobs
     */
    public function cleanup(int $daysToKeep = 7): int
    {
        $cutoff = date('Y-m-d', strtotime("-{$daysToKeep} days"));
        
        $stmt = $this->pdo->prepare("
            DELETE FROM job_queue 
            WHERE status IN ('completed', 'failed')
            AND completed_at < ?
        ");
        $stmt->execute([$cutoff]);
        
        return $stmt->rowCount();
    }
    
    private function ensureTableExists(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS job_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(100) NOT NULL,
                payload JSON NOT NULL,
                tenant_id INT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                priority INT DEFAULT 0,
                attempts INT DEFAULT 0,
                available_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                failed_at TIMESTAMP NULL,
                error TEXT NULL,
                result JSON NULL,
                INDEX idx_status (status, available_at),
                INDEX idx_type (type),
                INDEX idx_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

/**
 * Job worker CLI script
 * Run: php scripts/worker.php
 */
if (!function_exists('run_job_worker')) {
    function run_job_worker(\PDO $pdo, int $maxJobs = 10): int
    {
        $queue = new JobQueue($pdo);
        $processed = 0;
        
        while ($processed < $maxJobs) {
            $job = $queue->pop();
            
            if (!$job) {
                break;
            }
            
            try {
                $result = processJob($job);
                $queue->complete($job['id'], $result);
            } catch (\Exception $e) {
                $queue->fail($job['id'], $e->getMessage());
            }
            
            $processed++;
        }
        
        return $processed;
    }
    
    function processJob(array $job): array
    {
        $type = $job['type'];
        $payload = $job['payload'];
        
        switch ($type) {
            case 'send_email':
                // Process email sending
                return ['sent' => true];
                
            case 'generate_report':
                // Process report generation
                return ['generated' => true];
                
            case 'backup_database':
                // Process database backup
                return ['backed_up' => true];
                
            default:
                throw new \Exception("Unknown job type: {$type}");
        }
    }
}
