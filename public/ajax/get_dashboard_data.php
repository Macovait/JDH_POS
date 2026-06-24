<?php
declare(strict_types=1);

/**
 * Dashboard Data API — single AJAX endpoint for the full dashboard
 *
 * GET /ajax/get_dashboard_data.php
 * Optional query params: ?tenant_id=X&branch_id=Y
 *
 * Returns: { success: true, kpis: {...}, tables: {...}, charts: {...}, alerts: [...], meta: {...} }
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
safe_require('cache.php', 'src');

require_once __DIR__ . '/../../src/DashboardContext.php';
require_once __DIR__ . '/../../src/DashboardRepository.php';

require_login();

header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $ctx = DashboardContext::fromSession();

    // Optional overrides from query string (admin users only)
    if ($ctx->isSuperAdmin() && isset($_GET['tenant_id'])) {
        $ctx = DashboardContext::create(
            companyId:    (int) $_GET['tenant_id'],
            branchId:     isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : $ctx->branchId(),
            businessType: $ctx->businessType(),
            role:         $ctx->role(),
            userId:       $ctx->userId(),
            isSuperAdmin: true,
            permissions:  [],
            currency:     $ctx->currency(),
        );
    }

    // Cache key scoped to tenant + branch
    $cacheKey = 'dashboard:'
        . ($ctx->companyId() ?? 'all') . ':'
        . ($ctx->branchId() ?? 'all') . ':'
        . $ctx->businessType();

    // Try cache (300s = 5 min TTL for hot metrics)
    $cache = cache();
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        echo $cached;
        exit;
    }

    // Build from repository
    $repo    = new DashboardRepository($pdo, $ctx);
    $payload = $repo->build();

    $json = json_encode(array_merge(['success' => true], $payload), JSON_THROW_ON_ERROR);

    // Store in cache
    $cache->set($cacheKey, $json, 300);

    echo $json;

} catch (\PDOException $e) {
    error_log("Dashboard API PDO error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error loading dashboard']);
} catch (\Throwable $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
