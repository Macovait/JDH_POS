<?php

namespace JDH\POS\Src\Barcode;

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

    public function checkChartOfAccountsOperation(string $operation): bool
    {
        return $this->check('chart_of_accounts_' . $operation);
    }

    public function checkInventoryOperation(string $operation, int $quantity = 1): bool
    {
        return $this->check('inventory_' . $operation);
    }
}