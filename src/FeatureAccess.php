<?php

class FeatureAccess
{
    private \PDO $pdo;
    private ?int $tenantId;

    public function __construct(\PDO $pdo, ?int $tenantId = null)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
    }

    public function hasFeature(string $featureKey): bool
    {
        return true;
    }

    public function hasAnyFeature(array $featureKeys): bool
    {
        return true;
    }

    public function hasAllFeatures(array $featureKeys): bool
    {
        return true;
    }

    public function getCompanyFeatures(): array
    {
        return [];
    }

    public function getCompanyPlan(): ?array
    {
        return null;
    }

    public function isSubscriptionActive(): bool
    {
        return true;
    }

    public function isOnTrial(): bool
    {
        return false;
    }

    public function getTrialDaysRemaining(): int
    {
        return 0;
    }

    public function isTrialExpired(): bool
    {
        return false;
    }

    public function hasReachedUserLimit(): bool
    {
        return false;
    }

    public function hasReachedBranchLimit(): bool
    {
        return false;
    }

    public function hasReachedProductLimit(): bool
    {
        return false;
    }

    public function getAllPlans(): array
    {
        return [];
    }

    public function getAllFeatures(): array
    {
        return [];
    }

    public function getPlanFeatures(int $planId): array
    {
        return [];
    }
}