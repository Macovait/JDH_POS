<?php
/**
 * Rate Limiter - Enterprise-grade request throttling
 * 
 * @package JDH_POS\Security
 * @version 1.0
 */

namespace JDH\Security;

use PDO;
use Exception;

class RateLimiter
{
    private string $driver;
    private array $limits;
    private int $blockDuration;
    private ?PDO $db = null;
    private ?\Redis $redis = null;
    private array $cache = [];
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = [])
    {
        $this->driver = $config['driver'] ?? 'database';
        $this->limits = $config['limits'] ?? [
            'login_attempts' => ['max' => 5, 'decay' => 900],
            'password_reset' => ['max' => 3, 'decay' => 3600],
            'mfa_attempts' => ['max' => 3, 'decay' => 300],
            'api_requests' => ['max' => 100, 'decay' => 60]
        ];
        $this->blockDuration = $config['block_duration'] ?? 3600;
        
        // Initialize connection based on driver
        if ($this->driver === 'redis' && extension_loaded('redis')) {
            $this->initRedis();
        } else {
            $this->initDatabase();
        }
    }
    
    /**
     * Initialize Redis connection
     */
    private function initRedis(): void
    {
        try {
            $this->redis = new \Redis();
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = getenv('REDIS_PORT') ?: 6379;
            $password = getenv('REDIS_PASSWORD') ?: null;
            
            $this->redis->connect($host, $port);
            if ($password) {
                $this->redis->auth($password);
            }
        } catch (Exception $e) {
            error_log("Redis connection failed, falling back to database: " . $e->getMessage());
            $this->driver = 'database';
            $this->initDatabase();
        }
    }
    
    /**
     * Initialize database connection
     */
    private function initDatabase(): void
    {
        if (function_exists('get_db_connection')) {
            $this->db = get_db_connection();
        }
    }
    
    /**
     * Get rate limit key for identifier and type
     */
    private function getKey(string $identifier, string $type): string
    {
        return "rate_limit:{$type}:{$identifier}";
    }
    
    /**
     * Check if identifier is blocked
     * 
     * @param string $identifier IP address or user ID
     * @param string $type Rate limit type
     * @return bool
     */
    public function isBlocked(string $identifier, string $type): bool
    {
        $blockKey = $this->getKey($identifier, $type) . ':blocked';
        
        if ($this->driver === 'redis' && $this->redis) {
            return (bool) $this->redis->exists($blockKey);
        }
        
        // Database check
        if ($this->db) {
            $stmt = $this->db->prepare("
                SELECT expires_at FROM rate_limit_blocks 
                WHERE identifier = ? AND type = ? AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$identifier, $type]);
            return (bool) $stmt->fetch();
        }
        
        return false;
    }
    
    /**
     * Get remaining block time in seconds
     * 
     * @param string $identifier IP address or user ID
     * @param string $type Rate limit type
     * @return int Seconds remaining (0 if not blocked)
     */
    public function getBlockRemaining(string $identifier, string $type): int
    {
        $blockKey = $this->getKey($identifier, $type) . ':blocked';
        
        if ($this->driver === 'redis' && $this->redis) {
            $ttl = $this->redis->ttl($blockKey);
            return $ttl > 0 ? $ttl : 0;
        }
        
        if ($this->db) {
            $stmt = $this->db->prepare("
                SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) as remaining
                FROM rate_limit_blocks 
                WHERE identifier = ? AND type = ? AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$identifier, $type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? max(0, (int) $result['remaining']) : 0;
        }
        
        return 0;
    }
    
    /**
     * Increment attempt counter
     * 
     * @param string $identifier IP address or user ID
     * @param string $type Rate limit type
     * @return array Current attempts and max allowed
     */
    public function increment(string $identifier, string $type): array
    {
        $limit = $this->limits[$type] ?? ['max' => 5, 'decay' => 900];
        $maxAttempts = $limit['max'];
        $decaySeconds = $limit['decay'];
        $key = $this->getKey($identifier, $type);
        
        $currentAttempts = $this->getAttempts($identifier, $type);
        $newAttempts = $currentAttempts + 1;
        
        if ($this->driver === 'redis' && $this->redis) {
            if ($currentAttempts === 0) {
                $this->redis->setex($key, $decaySeconds, $newAttempts);
            } else {
                $this->redis->incr($key);
            }
            
            // Check if exceeded limit
            if ($newAttempts >= $maxAttempts) {
                $this->block($identifier, $type);
            }
        } else {
            // Database storage
            $this->storeAttemptInDatabase($identifier, $type, $newAttempts, $decaySeconds);
            
            if ($newAttempts >= $maxAttempts) {
                $this->block($identifier, $type);
            }
        }
        
        return [
            'attempts' => $newAttempts,
            'max_attempts' => $maxAttempts,
            'remaining' => max(0, $maxAttempts - $newAttempts),
            'reset_in' => $this->getResetTime($identifier, $type)
        ];
    }
    
    /**
     * Get current attempt count
     * 
     * @param string $identifier IP address or user ID
     * @param string $type Rate limit type
     * @return int
     */
    public function getAttempts(string $identifier, string $type): int
    {
        $key = $this->getKey($identifier, $type);
        
        if ($this->driver === 'redis' && $this->redis) {
            return (int) $this->redis->get($key);
        }
        
        if ($this->db) {
            $stmt = $this->db->prepare("
                SELECT attempts FROM rate_limit_attempts 
                WHERE identifier = ? AND type = ? AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$identifier, $type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int) $result['attempts'] : 0;
        }
        
        return 0;
    }
    
    /**
     * Reset attempts for identifier
     * 
     * @param string $identifier IP address or user ID
     * @param string $type Rate limit type
     * @return bool
     */
    public function reset(string $identifier, string $type): bool
    {
        $key = $this->getKey($identifier, $type);
        
        if ($this->driver === 'redis' && $this->redis) {
            $this->redis->del($key);
            $this->redis->del($key . ':blocked');
            return true;
        }
        
        if ($this->db) {
            $stmt = $this->db->prepare("
                DELETE FROM rate_limit_attempts 
                WHERE identifier = ? AND type = ?
            ");
            $stmt->execute([$identifier, $type]);
            
            $stmt = $this->db->prepare("
                DELETE FROM rate_limit_blocks 
                WHERE identifier = ? AND type = ?
            ");
            $stmt->execute([$identifier, $type]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Block an identifier
     * 
     * @param string $identifier IP address or user ID
     * @param string $type Rate limit type
     */
    private function block(string $identifier, string $type): void
    {
        $blockKey = $this->getKey($identifier, $type) . ':blocked';
        
        if ($this->driver === 'redis' && $this->redis) {
            $this->redis->setex($blockKey, $this->blockDuration, 1);
        } elseif ($this->db) {
            $stmt = $this->db->prepare("
                INSERT INTO rate_limit_blocks (identifier, type, expires_at, created_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
                ON DUPLICATE KEY UPDATE 
                    expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                    created_at = NOW()
            ");
            $stmt->execute([$identifier, $type, $this->blockDuration, $this->blockDuration]);
        }
        
        error_log("Rate limit exceeded: {$identifier} blocked for {$type} for {$this->blockDuration} seconds");
    }
    
    /**
     * Store attempt in database
     * 
     * @param string $identifier IP address or user ID
     * @param string $type Rate limit type
     * @param int $attempts Current attempts
     * @param int $decaySeconds Decay time in seconds
     */
    private function storeAttemptInDatabase(string $identifier, string $type, int $attempts, int $decaySeconds): void
    {
        if (!$this->db) return;
        
        $stmt = $this->db->prepare("
            INSERT INTO rate_limit_attempts (identifier, type, attempts, expires_at, created_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
            ON DUPLICATE KEY UPDATE 
                attempts = ?,
                expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                updated_at = NOW()
        ");
        $stmt->execute([$identifier, $type, $attempts, $decaySeconds, $attempts, $decaySeconds]);
    }
    
    /**
     * Get reset time in seconds
     * 
     * @param string $identifier IP address or user ID
     * @param string $type Rate limit type
     * @return int
     */
    private function getResetTime(string $identifier, string $type): int
    {
        if ($this->driver === 'redis' && $this->redis) {
            $key = $this->getKey($identifier, $type);
            $ttl = $this->redis->ttl($key);
            return $ttl > 0 ? $ttl : 0;
        }
        
        if ($this->db) {
            $stmt = $this->db->prepare("
                SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) as remaining
                FROM rate_limit_attempts 
                WHERE identifier = ? AND type = ? AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$identifier, $type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? max(0, (int) $result['remaining']) : 0;
        }
        
        return 0;
    }
    
    /**
     * Get remaining attempts before block
     * 
     * @param string $identifier IP address or user ID
     * @param string $type Rate limit type
     * @return int
     */
    public function remainingAttempts(string $identifier, string $type): int
    {
        if ($this->isBlocked($identifier, $type)) {
            return 0;
        }
        
        $limit = $this->limits[$type] ?? ['max' => 5];
        $currentAttempts = $this->getAttempts($identifier, $type);
        
        return max(0, $limit['max'] - $currentAttempts);
    }
    
    /**
     * Create rate limit tables if they don't exist
     */
    public static function createTables(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS rate_limit_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 1,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY uk_identifier_type (identifier, type),
                INDEX idx_expires (expires_at),
                INDEX idx_identifier (identifier)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS rate_limit_blocks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uk_identifier_type (identifier, type),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}