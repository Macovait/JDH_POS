<?php
declare(strict_types=1);

/**
 * Plugin Manager AJAX API
 * Actions: list, activate, deactivate, status, settings
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

header('Content-Type: application/json');

// Auth check
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$pdo = get_db_connection();
$tenantId = $_SESSION['tenant_id'] ?? null;

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

require_once SRC_PATH . '/Plugin/PluginManager.php';
require_once SRC_PATH . '/Plugin/PluginRegistry.php';
require_once SRC_PATH . '/Plugin/PluginInterface.php';
require_once SRC_PATH . '/Plugin/AbstractPlugin.php';

$manager = new \JDH\POS\Plugin\PluginManager($pdo, $tenantId);

switch ($action) {
    case 'list':
        echo json_encode([
            'success' => true,
            'plugins' => $manager->getPluginStatuses()
        ]);
        break;

    case 'activate':
        $slug = $_POST['slug'] ?? '';
        if (!$slug) {
            echo json_encode(['success' => false, 'message' => 'Plugin slug required']);
            exit;
        }
        $result = $manager->activate($slug);
        echo json_encode($result);
        break;

    case 'deactivate':
        $slug = $_POST['slug'] ?? '';
        if (!$slug) {
            echo json_encode(['success' => false, 'message' => 'Plugin slug required']);
            exit;
        }
        $result = $manager->deactivate($slug);
        echo json_encode($result);
        break;

    case 'settings':
        $slug = $_GET['slug'] ?? ($_POST['slug'] ?? '');
        if (!$slug) {
            echo json_encode(['success' => false, 'message' => 'Plugin slug required']);
            exit;
        }

        $plugin = \JDH\POS\Plugin\PluginRegistry::get($slug);
        if (!$plugin) {
            // Try to instantiate from discovered list
            $discovered = $manager->discover();
            foreach ($discovered as $info) {
                if ($info['slug'] === $slug) {
                    $plugin = new $info['class']($pdo, $tenantId);
                    break;
                }
            }
        }

        if (!$plugin) {
            echo json_encode(['success' => false, 'message' => 'Plugin not found']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settings = json_decode(file_get_contents('php://input'), true)['settings'] ?? [];
            foreach ($settings as $key => $value) {
                $plugin->setSetting($key, $value);
            }
            echo json_encode(['success' => true, 'message' => 'Settings saved']);
        } else {
            echo json_encode([
                'success'  => true,
                'settings' => $plugin->settings ?? []
            ]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
