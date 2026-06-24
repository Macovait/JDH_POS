<?php
/**
 * Rate Limiter - Barcode generation rate limiting
 * Stub implementation for Jakababa POS
 */

namespace JDH\POS\Barcode;

class RateLimiter
{
    private $pdo;
    private $tenantId;
    private $userId;

    public function __construct($pdo, int $tenantId, int $userId)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
    }
}
