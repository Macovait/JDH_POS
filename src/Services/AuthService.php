<?php

namespace JDH\POS\Services;

use JDH\POS\RateLimiter;

class AuthService
{
    private array $config;
    private \PDO $pdo;
    private RateLimiter $rateLimiter;

    public function __construct(\PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config + [
            'max_login_attempts' => 5,
            'lockout_duration' => 300,
        ];
        $this->rateLimiter = new RateLimiter($pdo, 0, 0, $this->config['max_login_attempts'], 15);
    }

    public function authenticate(string $username, string $password, bool $remember = false): array
    {
        $action = 'login';

        if ($this->rateLimiter->isLimited($action)) {
            return ['success' => false, 'error' => 'Too many login attempts. Please try again later.'];
        }

        $stmt = $this->pdo->prepare('SELECT id, password_hash, role, status FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->rateLimiter->recordAttempt($action);
            return ['success' => false, 'error' => 'Invalid credentials.'];
        }

        if ((int)$user['status'] !== 1) {
            return ['success' => false, 'error' => 'Account is disabled.'];
        }

        $this->rateLimiter->resetAttempts($action);

        return ['success' => true, 'user' => $user];
    }

    public function enforceRateLimit(string $action): void
    {
        if ($this->rateLimiter->isLimited($action)) {
            throw new \RuntimeException('Rate limit exceeded. Please try again later.');
        }
    }

    public function resetRateLimit(string $action): void
    {
        $this->rateLimiter->resetAttempts($action);
    }
}
