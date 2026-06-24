<?php
/**
 * Base API Controller
 * Provides common functionality for API endpoints
 */

declare(strict_types=1);

namespace JDH_POS\Controllers;

use PDO;
use JDH_POS\Services\AuthenticationService;
use JDH_POS\Services\TenantContextService;
use JDH_POS\Services\PermissionService;

abstract class BaseApiController
{
    protected PDO $db;
    protected AuthenticationService $auth;
    protected TenantContextService $tenant;
    protected PermissionService $permissions;
    protected ?array $currentUser = null;
    protected array $response = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->tenant = new TenantContextService($db);
        $this->auth = new AuthenticationService($db, $this->tenant);
        $this->permissions = new PermissionService($db);

        $this->initializeRequest();
    }

    /**
     * Initialize request context
     */
    protected function initializeRequest(): void
    {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Set tenant context from session
        if (!empty($_SESSION['tenant_id'])) {
            $this->tenant->setTenant($_SESSION['tenant_id']);
        }

        // Authenticate user from session
        if (!empty($_SESSION['user_id'])) {
            $this->currentUser = $this->getCurrentUser($_SESSION['user_id']);
        }
    }

    /**
     * Get current authenticated user
     */
    protected function getCurrentUser(int $userId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, r.name as role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND u.status = 1
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $user['permissions'] = $this->permissions->getUserPermissions($userId);
                return $user;
            }

            return null;

        } catch (PDOException $e) {
            error_log("BaseApiController::getCurrentUser error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user is authenticated
     */
    protected function requireAuth(): void
    {
        if (!$this->currentUser) {
            $this->error('Authentication required', 401);
        }
    }

    /**
     * Check if user has permission
     */
    protected function requirePermission(string $module, string $action): void
    {
        $this->requireAuth();

        if (!$this->permissions->userHasPermission($this->currentUser['id'], $module, $action)) {
            $this->error('Insufficient permissions', 403);
        }
    }

    /**
     * Check if tenant context is set
     */
    protected function requireTenant(): void
    {
        if (!$this->tenant->hasTenant()) {
            $this->error('Tenant context required', 400);
        }
    }

    /**
     * Validate required fields in request data
     */
    protected function validateRequired(array $data, array $required): void
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $this->error('Missing required fields: ' . implode(', ', $missing), 400);
        }
    }

    /**
     * Sanitize input data
     */
    protected function sanitize(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim(strip_tags($value));
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Get JSON input from request body
     */
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON input', 400);
        }

        return $this->sanitize($data);
    }

    /**
     * Send success response
     */
    protected function success(array $data = [], string $message = 'Success', int $code = 200): void
    {
        $this->response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ];

        $this->sendResponse($code);
    }

    /**
     * Send error response
     */
    protected function error(string $message, int $code = 400, array $errors = []): void
    {
        $this->response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('c')
        ];

        $this->sendResponse($code);
    }

    /**
     * Send paginated response
     */
    protected function paginated(array $items, int $total, int $page, int $perPage, string $message = 'Success'): void
    {
        $this->response = [
            'success' => true,
            'message' => $message,
            'data' => [
                'items' => $items,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => (int) ceil($total / $perPage),
                    'has_more' => ($page * $perPage) < $total
                ]
            ],
            'timestamp' => date('c')
        ];

        $this->sendResponse(200);
    }

    /**
     * Send HTTP response
     */
    private function sendResponse(int $code): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        echo json_encode($this->response, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Log API request
     */
    protected function logRequest(string $action, array $data = []): void
    {
        if ($this->currentUser) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO activity_logs
                    (user_id, tenant_id, action, meta, ip_address, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $this->currentUser['id'],
                    $this->tenant->getTenantId(),
                    $action,
                    json_encode($data),
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);
            } catch (PDOException $e) {
                error_log("BaseApiController::logRequest error: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle OPTIONS request for CORS
     */
    public function handleOptions(): void
    {
        $this->sendResponse(200);
    }

    /**
     * Abstract method to be implemented by child controllers
     */
    abstract public function handleRequest(string $method, array $pathParts = []): void;
}