<?php

namespace JDH\POS {

    class RateLimiter
    {
        private \PDO $pdo;
        private int $tenantId;
        private int $userId;
        private int $maxAttempts;
        private int $windowSeconds;

        public function __construct(\PDO $pdo, int $tenantId, int $userId, int $maxAttempts = 10, int $windowSeconds = 60)
        {
            $this->pdo = $pdo;
            $this->tenantId = $tenantId;
            $this->userId = $userId;
            $this->maxAttempts = $maxAttempts;
            $this->windowSeconds = $windowSeconds;
        }

        public function check(string $action): bool
        {
            return !$this->isLimited($action);
        }

        public function recordAttempt(string $action): void
        {
        }

        public function isLimited(string $action): bool
        {
            return false;
        }

        public function resetAttempts(string $action): void
        {
        }

        public function getRemainingAttempts(string $action): int
        {
            return $this->maxAttempts;
        }

        public function getResetTime(string $action): int
        {
            return 0;
        }
    }
}

namespace {
    use JDH\POS\RateLimiter;

    function rate_limit_enforce(string $action, int $maxAttempts = 10, int $windowSeconds = 60): void
    {
        $limiter = new RateLimiter(getDB(), 0, 0, $maxAttempts, $windowSeconds);
        if ($limiter->isLimited($action)) {
            throw new \RuntimeException('Rate limit exceeded. Please try again later.');
        }
    }

    function rate_limit_reset(string $action): void
    {
        $limiter = new RateLimiter(getDB(), 0, 0);
        $limiter->resetAttempts($action);
    }
}