<?php
/**
 * Reports API Controller
 * REST API endpoints for reporting
 */

declare(strict_types=1);

namespace JDH_POS\Controllers;

class ReportsApiController extends BaseApiController
{
    public function handleRequest(string $method, array $pathParts = []): void
    {
        $this->requireAuth();
        $this->requireTenant();

        if ($method !== 'GET') {
            $this->error('Method not allowed', 405);
        }

        $reportType = $pathParts[0] ?? 'dashboard';

        switch ($reportType) {
            case 'sales':
                $this->getSalesReport();
                break;

            case 'inventory':
                $this->getInventoryReport();
                break;

            case 'dashboard':
            default:
                $this->getDashboardReport();
                break;
        }
    }

    private function getSalesReport(): void
    {
        $this->requirePermission('reports', 'view');
        $this->success([], 'Sales report endpoint - implementation pending');
    }

    private function getInventoryReport(): void
    {
        $this->requirePermission('reports', 'view');
        $this->success([], 'Inventory report endpoint - implementation pending');
    }

    private function getDashboardReport(): void
    {
        $this->requirePermission('reports', 'view');
        $this->success([], 'Dashboard report endpoint - implementation pending');
    }
}