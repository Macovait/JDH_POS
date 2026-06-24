<?php
/**
 * Rate Limit Middleware - Per-tenant/API-key rate limiting
 * 
 * Implements token bucket algorithm for API rate limiting
 * Supports: per-user, per-API-key, per-IP limits
 * 
 * @package JakababaPOS
 * @version 2.0.0
 */

namespace JakababaPOS\Middleware;

class RateLimitMiddleware
{
    private \PDO $db;
    private array $config;
    
    const TABLE_PREFIX = 'pos_';
    const DEFAULT_RATE = 60;       // requests per window
    const DEFAULT_WINDOW = 3600;    // 1 hour in seconds
    const BURST_ALLOWANCE = 10;     // burst allowance
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'rate' => self::DEFAULT_RATE,
            'window' => self::DEFAULT_WINDOW,
            'burst' => self::BURST_ALLOWANCE,
            'storage' => 'database', // 'database' | 'redis' | 'memory'
            'ip_whitelist' => [],
            'fail_action' => '429', // '429' | 'redirect' | 'json'
        ], $config);
    }
    
    /**
     * Check rate limit for request
     * Returns true if allowed, false if exceeded
     */
    public function check(?string $identifier = null): bool
    {
        $identifier = $identifier ?? $this->getIdentifier();
        
        // Check whitelist
        if ($this->isWhitelisted()) {
            return true;
        }
        
        // Get rate limit for this identifier
        $limit = $this->getRateLimit($identifier);
        
        // Check current usage
        $usage = $this->getCurrentUsage($identifier);
        
        if ($usage >= $limit) {
            $this->handleLimitExceeded($identifier, $limit);
            return false;
        }
        
        // Increment counter
        $this->incrementUsage($identifier);
        
        return true;
    }
    
    /**
     * Get rate limit for identifier
     */
    private function getRateLimit(string $identifier): int
    {
        // Try to get custom limit from API key
        if ($this->isApiRequest()) {
            $apiKeyId = $_SESSION['api_key_id'] ?? null;
            
            if ($apiKeyId) {
                $stmt = $this->db->prepare("
                    SELECT rate_limit FROM " . self::TABLE_PREFIX . "api_keys
                    WHERE id = :id AND is_active = 1
                ");
                $stmt->execute([':id' => $apiKeyId]);
                $rate = $stmt->fetchColumn();
                
                if ($rate) {
                    return (int) $rate;
                }
            }
        }
        
        // Check user's plan limit
        $plan = $_SESSION['plan_id'] ?? null;
        
        if ($plan) {
            $stmt = $this->db->prepare("
                SELECT max_api_calls FROM " . self::TABLE_PREFIX . "plans
                WHERE id = :id
            ");
            $stmt->execute([':id' => $plan]);
            $maxCalls = $stmt->fetchColumn();
            
            if ($maxCalls) {
                return (int) $maxCalls;
            }
        }
        
        return $this->config['rate'];
    }
    
    /**
     * Get identifier for rate limiting
     */
    private function getIdentifier(): string
    {
        // API key takes priority
        if ($apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null) {
            return 'api:' . hash('sha256', $apiKey);
        }
        
        // User ID
        if (!empty($_SESSION['user_id'])) {
            return 'user:' . $_SESSION['user_id'];
        }
        
        // IP address
        $ip = $this->getClientIp();
        return 'ip:' . $ip;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        // Check for forwarded headers (reverse proxy)
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? null;
        
        if ($ip) {
            return explode(',', $ip)[0];
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if IP is whitelisted
     */
    private function isWhitelisted(): bool
    {
        $ip = $this->getClientIp();
        
        foreach ($this->config['ip_whitelist'] as $allowed) {
            if ($this->ipInRange($ip, $allowed)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is in CIDR range
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        [$subnet, $bits] = explode('/', $range);
        
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        
        if ($ip === false || $subnet === false) {
            return false;
        }
        
        $mask = -1 << (32 - (int) $bits);
        
        return ($ip & $mask) === ($subnet & $mask);
    }
    
    /**
     * Get current usage for identifier
     */
    private function getCurrentUsage(string $identifier): int
    {
        $windowStart = date('Y-m-d H:i:s', time() - $this->config['window']);
        
        $stmt = $this->db->prepare("
            SELECT request_count FROM " . self::TABLE_PREFIX . "api_rate_limits
            WHERE identifier = :identifier AND window_start > :window_start
            ORDER BY window_start DESC
            LIMIT 1
        ");
        
        $stmt->execute([
            ':identifier' => $identifier,
            ':window_start' => $windowStart
        ]);
        
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Increment usage counter
     */
    private function incrementUsage(string $identifier): void
    {
        $windowStart = date('Y-m-d H:i:s', time() - ($this->config['window'] - (time() % $this->config['window'])));
        
        // Try to update existing record
        $stmt = $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "api_rate_limits
            SET request_count = request_count + 1
            WHERE identifier = :identifier AND window_start = :window_start
        ");
        
        $stmt->execute([
            ':identifier' => $identifier,
            ':window_start' => $windowStart
        ]);
        
        // If no rows updated, insert new record
        if ($stmt->rowCount() === 0) {
            $this->db->prepare("
                INSERT INTO " . self::TABLE_PREFIX . "api_rate_limits
                (identifier, window_start, request_count)
                VALUES (:identifier, :window_start, 1)
            ")->execute([
                ':identifier' => $identifier,
                ':window_start' => $windowStart
            ]);
        }
    }
    
    /**
     * Check if this is an API request
     */
    private function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_starts_with($uri, '/api/');
    }
    
    /**
     * Handle rate limit exceeded
     */
    private function handleLimitExceeded(string $identifier, int $limit): void
    {
        // Log the event
        error_log("Rate limit exceeded for: $identifier (limit: $limit)");
        
        switch ($this->config['fail_action']) {
            case '429':
                http_response_code(429);
                header('Content-Type: application/json');
                header('Retry-After: ' . $this->config['window']);
                echo json_encode([
                    'error' => 'Rate limit exceeded',
                    'limit' => $limit,
                    'window' => $this->config['window'],
                    'message' => "You have exceeded the rate limit of {$limit} requests per hour"
                ]);
                exit;
                
            case 'json':
                http_response_code(429);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Rate limit exceeded']);
                exit;
                
            case 'redirect':
                header('Location: /rate-limit-exceeded.php');
                exit;
        }
    }
    
    /**
     * Get rate limit headers for response
     */
    public function getHeaders(): array
    {
        $identifier = $this->getIdentifier();
        $limit = $this->getRateLimit($identifier);
        $usage = $this->getCurrentUsage($identifier);
        $remaining = max(0, $limit - $usage);
        $reset = time() + $this->config['window'];
        
        return [
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $reset,
        ];
    }
    
    /**
     * Add rate limit headers to response
     */
    public function addHeaders(): void
    {
        foreach ($this->getHeaders() as $name => $value) {
            header("$name: $value");
        }
    }
}

/**
 * Factory function
 */
if (!function_exists('createRateLimitMiddleware')) {
    function createRateLimitMiddleware(\PDO $db, array $config = []): RateLimitMiddleware
    {
        return new RateLimitMiddleware($db, $config);
    }
}

/**
 * Check rate limit - helper function
 */
if (!function_exists('checkRateLimit')) {
    function checkRateLimit(\PDO $db, ?string $identifier = null): bool
    {
        $middleware = createRateLimitMiddleware($db);
        return $middleware->check($identifier);
    }
}