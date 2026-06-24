<?php
/**
 * Customers API Controller
 * REST API endpoints for customer management
 */

declare(strict_types=1);

namespace JDH_POS\Controllers;

class CustomersApiController extends BaseApiController
{
    public function handleRequest(string $method, array $pathParts = []): void
    {
        $this->requireAuth();
        $this->requireTenant();

        switch ($method) {
            case 'GET':
                if (!empty($pathParts[0])) {
                    $this->getCustomer((int)$pathParts[0]);
                } else {
                    $this->getCustomers();
                }
                break;

            case 'POST':
                $this->createCustomer();
                break;

            default:
                $this->error('Method not allowed', 405);
        }
    }

    private function getCustomers(): void
    {
        $this->requirePermission('customers', 'view');
        $this->success([], 'Customers endpoint - implementation pending');
    }

    private function getCustomer(int $customerId): void
    {
        $this->requirePermission('customers', 'view');
        $this->success(['id' => $customerId], 'Customer endpoint - implementation pending');
    }

    private function createCustomer(): void
    {
        $this->requirePermission('customers', 'create');
        $this->success([], 'Create customer endpoint - implementation pending');
    }
}