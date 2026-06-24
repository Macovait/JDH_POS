<?php
/**
 * API Router
 * Routes API requests to appropriate controllers
 */

declare(strict_types=1);

require_once __DIR__ . '/autoloader.php';
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);

use JDH_POS\Controllers\ProductsApiController;
use JDH_POS\Controllers\SalesApiController;
use JDH_POS\Controllers\UsersApiController;
use JDH_POS\Controllers\CustomersApiController;
use JDH_POS\Controllers\ReportsApiController;

class ApiRouter
{
    private PDO $db;
    private string $method;
    private array $pathParts;

    public function __construct()
    {
        $this->db = get_db_connection();
        $this->method = $_SERVER['REQUEST_METHOD'];

        // Parse request URI
        $requestUri = $_SERVER['REQUEST_URI'];
        $apiPrefix = '/api/';

        // Remove query string and extract API path
        $path = parse_url($requestUri, PHP_URL_PATH);
        $apiPos = strpos($path, $apiPrefix);

        if ($apiPos !== false) {
            $apiPath = substr($path, $apiPos + strlen($apiPrefix));
            $this->pathParts = array_filter(explode('/', trim($apiPath, '/')));
        } else {
            $this->pathParts = [];
        }
    }

    /**
     * Route the request
     */
    public function route(): void
    {
        // Handle CORS preflight requests
        if ($this->method === 'OPTIONS') {
            $this->handleCors();
            return;
        }

        if (empty($this->pathParts)) {
            $this->error('API endpoint not specified', 400);
            return;
        }

        $resource = $this->pathParts[0];
        $id = $this->pathParts[1] ?? null;
        $action = $this->pathParts[2] ?? null;

        // Remove the resource from path parts for controller
        $controllerPathParts = array_slice($this->pathParts, 1);

        try {
            switch ($resource) {
                case 'products':
                    $controller = new ProductsApiController($this->db);
                    $controller->handleRequest($this->method, $controllerPathParts);
                    break;

                case 'sales':
                    $controller = new SalesApiController($this->db);
                    $controller->handleRequest($this->method, $controllerPathParts);
                    break;

                case 'users':
                    $controller = new UsersApiController($this->db);
                    $controller->handleRequest($this->method, $controllerPathParts);
                    break;

                case 'customers':
                    $controller = new CustomersApiController($this->db);
                    $controller->handleRequest($this->method, $controllerPathParts);
                    break;

                case 'reports':
                    $controller = new ReportsApiController($this->db);
                    $controller->handleRequest($this->method, $controllerPathParts);
                    break;

                default:
                    $this->error('Unknown API resource', 404);
            }

        } catch (Exception $e) {
            error_log("API Router error: " . $e->getMessage());
            $this->error('Internal server error', 500);
        }
    }

    /**
     * Handle CORS preflight
     */
    private function handleCors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        http_response_code(200);
        exit;
    }

    /**
     * Send error response
     */
    private function error(string $message, int $code): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('c')
        ];

        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}

// Initialize and run router
$router = new ApiRouter();
$router->route();