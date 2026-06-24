<?php
/**
 * Users API Controller
 * REST API endpoints for user management
 */

declare(strict_types=1);

namespace JDH_POS\Controllers;

class UsersApiController extends BaseApiController
{
    public function handleRequest(string $method, array $pathParts = []): void
    {
        $this->requireAuth();
        $this->requireTenant();

        switch ($method) {
            case 'GET':
                if (!empty($pathParts[0])) {
                    $this->getUser((int)$pathParts[0]);
                } else {
                    $this->getUsers();
                }
                break;

            case 'POST':
                $this->createUser();
                break;

            case 'PUT':
                if (!empty($pathParts[0])) {
                    $this->updateUser((int)$pathParts[0]);
                }
                break;

            default:
                $this->error('Method not allowed', 405);
        }
    }

    private function getUsers(): void
    {
        $this->requirePermission('users', 'view');
        $this->success([], 'Users endpoint - implementation pending');
    }

    private function getUser(int $userId): void
    {
        $this->requirePermission('users', 'view');
        $this->success(['id' => $userId], 'User endpoint - implementation pending');
    }

    private function createUser(): void
    {
        $this->requirePermission('users', 'create');
        $this->success([], 'Create user endpoint - implementation pending');
    }

    private function updateUser(int $userId): void
    {
        $this->requirePermission('users', 'edit');
        $this->success(['id' => $userId], 'Update user endpoint - implementation pending');
    }
}