<?php
declare(strict_types=1);

namespace JDH\POS\Plugins\ExamplePlugin;

require_once __DIR__ . '/../../src/Plugin/AbstractPlugin.php';
require_once __DIR__ . '/../../src/Plugin/PluginInterface.php';

use JDH\POS\Plugin\AbstractPlugin;

class ExamplePlugin extends AbstractPlugin
{
    public function getManifest(): array
    {
        return [
            'name'        => 'Example POS Plugin',
            'version'     => '1.0.0',
            'description' => 'Demonstrates the JDH POS plugin system.',
            'author'      => 'JDH POS Team',
            'requires'    => [],
            'features'    => ['pos-panel', 'checkout-hook', 'admin-menu'],
            'hooks'       => ['pos.before_cart', 'pos.after_sale', 'pos.checkout_render'],
            'assets'      => [
                'css' => ['assets/example.css'],
                'js'  => ['assets/example.js']
            ],
            'settings_schema' => [
                'display_welcome' => ['type' => 'boolean', 'default' => true, 'label' => 'Display Welcome Message'],
                'custom_message'  => ['type' => 'string',  'default' => 'Hello from Example Plugin!', 'label' => 'Custom Message']
            ]
        ];
    }

    public function register(): void
    {
        // Register a filter that modifies cart display
        if (function_exists('add_filter')) {
            add_filter('pos.cart_items', function ($items) {
                // Example: add a flag to each item
                foreach ($items as &$item) {
                    $item['plugin_tag'] = 'example';
                }
                return $items;
            }, 10, 1);
        }

        // Register an action that runs after a sale
        if (function_exists('add_action')) {
            add_action('pos.after_sale', function ($saleId, $data) {
                $message = $this->getSetting('custom_message', 'Sale completed!');
                error_log("[ExamplePlugin] After sale {$saleId}: {$message}");
            }, 10, 2);
        }
    }

    public function getMigrations(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS example_plugin_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NULL,
                event VARCHAR(64) NOT NULL,
                data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }

    public function getAdminMenu(): array
    {
        return [
            [
                'label'      => 'Example Plugin',
                'url'        => '/admin/plugins/example-plugin.php',
                'icon'       => 'puzzle',
                'permission' => 'manage_plugins'
            ]
        ];
    }

    public function getPosAssets(): array
    {
        return [
            [
                'file'   => 'assets/example.js',
                'handle' => 'example-plugin',
                'deps'   => ['pos-core'],
                'inline' => false
            ]
        ];
    }

    public function getPosPanels(): array
    {
        if (!$this->getSetting('display_welcome', true)) {
            return [];
        }

        return [
            [
                'id'       => 'example-welcome',
                'label'    => 'Welcome',
                'position' => 'sidebar-top',
                'content'  => '<div class="p-3 bg-blue-50 rounded-lg"><p class="text-sm text-blue-800">' . htmlspecialchars($this->getSetting('custom_message', 'Hello!')) . '</p></div>'
            ]
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'method'   => 'GET',
                'route'    => '/api/plugin/example/status',
                'callback' => 'handleStatus',
                'auth'     => true
            ]
        ];
    }

    public function handleStatus(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'active'  => true,
            'version' => '1.0.0',
            'message' => $this->getSetting('custom_message', 'Hello!')
        ]);
    }
}
