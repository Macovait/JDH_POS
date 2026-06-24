<?php
declare(strict_types=1);

namespace JDH\POS\Plugin;

use PDO;
use PDOException;

/**
 * Core plugin manager: discovers, loads, activates, and deactivates plugins.
 */
class PluginManager
{
    private PDO $pdo;
    private ?int $tenantId;
    private string $pluginsPath;
    private bool $initialized = false;

    public function __construct(PDO $pdo, ?int $tenantId = null, ?string $pluginsPath = null)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->pluginsPath = $pluginsPath ?? (defined('PLUGINS_PATH') ? PLUGINS_PATH : ROOT_PATH . '/plugins');
    }

    /**
     * Ensure the plugins table exists.
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS plugins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(128) NOT NULL,
            version VARCHAR(32) NOT NULL,
            description TEXT,
            author VARCHAR(128),
            status ENUM('active','inactive','error') DEFAULT 'inactive',
            tenant_id INT NULL,
            settings JSON NULL,
            installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_tenant (tenant_id),
            UNIQUE KEY unique_plugin_tenant (slug, tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("PluginManager initialization failed: " . $e->getMessage());
        }

        $this->initialized = true;
    }

    /**
     * Scan the plugins directory and return all discovered plugin classes.
     *
     * @return array<int, array{slug: string, class: class-string<PluginInterface>, path: string, manifest: array}>
     */
    public function discover(): array
    {
        $found = [];
        if (!is_dir($this->pluginsPath)) {
            return $found;
        }

        $dirs = glob($this->pluginsPath . '/*', GLOB_ONLYDIR);
        if ($dirs === false) {
            return $found;
        }

        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $autoload = $dir . '/plugin.php';
            $manifestFile = $dir . '/manifest.json';

            if (!file_exists($autoload)) {
                continue;
            }

            // Temporarily suppress output while requiring
            ob_start();
            require_once $autoload;
            ob_end_clean();

            $class = $this->findPluginClass($slug, $dir);
            if ($class === null) {
                continue;
            }

            $manifest = [];
            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true) ?: [];
            }

            // Let the class override manifest if getManifest() is richer
            try {
                $tmp = new $class($this->pdo, $this->tenantId);
                $manifest = array_merge($manifest, $tmp->getManifest());
            } catch (\Throwable $e) {
                error_log("Plugin {$slug} manifest error: " . $e->getMessage());
            }

            $found[] = [
                'slug'     => $slug,
                'class'    => $class,
                'path'     => $dir,
                'manifest' => $manifest,
            ];
        }

        return $found;
    }

    /**
     * Load all active plugins and boot them.
     */
    public function loadActive(): void
    {
        $this->initialize();

        try {
            $stmt = $this->pdo->prepare(
                "SELECT slug FROM plugins WHERE status = 'active' AND (tenant_id = ? OR tenant_id IS NULL)"
            );
            $stmt->execute([$this->tenantId]);
            $activeSlugs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("PluginManager loadActive error: " . $e->getMessage());
            return;
        }

        if (empty($activeSlugs)) {
            return;
        }

        $discovered = $this->discover();
        foreach ($discovered as $info) {
            if (!in_array($info['slug'], $activeSlugs, true)) {
                continue;
            }
            $this->loadPlugin($info['class'], $info['slug']);
        }
    }

    /**
     * Activate a plugin by slug.
     */
    public function activate(string $slug): array
    {
        $this->initialize();
        $discovered = $this->discover();

        $target = null;
        foreach ($discovered as $info) {
            if ($info['slug'] === $slug) {
                $target = $info;
                break;
            }
        }

        if ($target === null) {
            return ['success' => false, 'message' => "Plugin {$slug} not found."];
        }

        // Check dependencies
        $missing = $this->checkDependencies($target['manifest']['requires'] ?? []);
        if (!empty($missing)) {
            return ['success' => false, 'message' => 'Missing dependencies: ' . implode(', ', $missing)];
        }

        $class = $target['class'];
        $plugin = new $class($this->pdo, $this->tenantId);

        try {
            $plugin->activate();
            $this->upsertRecord($plugin, 'active');
            return ['success' => true, 'message' => "Plugin {$plugin->getName()} activated."];
        } catch (\Throwable $e) {
            $this->upsertRecord($plugin, 'error');
            error_log("[PluginManager] Activation error for {$slug}: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Deactivate a plugin by slug.
     */
    public function deactivate(string $slug): array
    {
        $this->initialize();

        $plugin = PluginRegistry::get($slug);
        if ($plugin) {
            try {
                $plugin->deactivate();
            } catch (\Throwable $e) {
                error_log("[PluginManager] Deactivation error for {$slug}: " . $e->getMessage());
            }
            PluginRegistry::unregister($slug);
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE plugins SET status = 'inactive' WHERE slug = ? AND (tenant_id = ? OR tenant_id IS NULL)"
            );
            $stmt->execute([$slug, $this->tenantId]);
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => "Plugin {$slug} deactivated."];
    }

    /**
     * Get the status of all known plugins.
     */
    public function getPluginStatuses(): array
    {
        $this->initialize();
        $discovered = $this->discover();
        $result = [];

        // Fetch persisted statuses
        $statuses = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT slug, status, settings, installed_at FROM plugins WHERE tenant_id = ? OR tenant_id IS NULL"
            );
            $stmt->execute([$this->tenantId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $statuses[$row['slug']] = $row;
            }
        } catch (PDOException $e) {
            // ignore
        }

        foreach ($discovered as $info) {
            $slug = $info['slug'];
            $record = $statuses[$slug] ?? null;
            $result[] = [
                'slug'        => $slug,
                'name'        => $info['manifest']['name'] ?? $slug,
                'version'     => $info['manifest']['version'] ?? '0.0.0',
                'description' => $info['manifest']['description'] ?? '',
                'author'      => $info['manifest']['author'] ?? '',
                'status'      => $record['status'] ?? 'inactive',
                'installed'   => $record !== null,
                'installed_at'=> $record['installed_at'] ?? null,
                'features'    => $info['manifest']['features'] ?? [],
                'hooks'       => $info['manifest']['hooks'] ?? [],
            ];
        }

        return $result;
    }

    /**
     * Get all POS assets from active plugins.
     */
    public function getPosAssets(): array
    {
        $assets = [];
        foreach (PluginRegistry::all() as $plugin) {
            foreach ($plugin->getPosAssets() as $asset) {
                $asset['plugin_slug'] = $plugin->getSlug();
                $asset['plugin_url'] = $plugin->getUrl();
                $assets[] = $asset;
            }
        }
        return $assets;
    }

    /**
     * Get all POS panels from active plugins.
     */
    public function getPosPanels(): array
    {
        $panels = [];
        foreach (PluginRegistry::all() as $plugin) {
            foreach ($plugin->getPosPanels() as $panel) {
                $panel['plugin_slug'] = $plugin->getSlug();
                $panels[] = $panel;
            }
        }
        return $panels;
    }

    /**
     * Get all admin menu items from active plugins.
     */
    public function getAdminMenus(): array
    {
        $menus = [];
        foreach (PluginRegistry::all() as $plugin) {
            foreach ($plugin->getAdminMenu() as $item) {
                $item['plugin_slug'] = $plugin->getSlug();
                $menus[] = $item;
            }
        }
        return $menus;
    }

    /**
     * Get all routes from active plugins.
     */
    public function getPluginRoutes(): array
    {
        $routes = [];
        foreach (PluginRegistry::all() as $plugin) {
            foreach ($plugin->getRoutes() as $route) {
                $route['plugin_slug'] = $plugin->getSlug();
                $routes[] = $route;
            }
        }
        return $routes;
    }

    /**
     * Execute a callback scoped to a specific plugin's settings.
     */
    public function withPlugin(string $slug, callable $callback): mixed
    {
        $plugin = PluginRegistry::get($slug);
        if (!$plugin) {
            return null;
        }
        return $callback($plugin);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function loadPlugin(string $class, string $slug): void
    {
        try {
            $plugin = new $class($this->pdo, $this->tenantId);
            $plugin->boot();
            PluginRegistry::register($plugin);
        } catch (\Throwable $e) {
            error_log("[PluginManager] Failed to load plugin {$slug}: " . $e->getMessage());
        }
    }

    private function findPluginClass(string $slug, string $dir): ?string
    {
        $candidates = [];

        // 1. Check manifest.json for explicit class name
        $manifestFile = $dir . '/manifest.json';
        if (file_exists($manifestFile)) {
            $manifest = json_decode(file_get_contents($manifestFile), true);
            if (!empty($manifest['class'])) {
                $candidates[] = $manifest['class'];
            }
        }

        // 2. Auto-detect from namespace conventions
        $nsBase = 'JDH\\POS\\Plugins\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
        $candidates[] = $nsBase . '\\Plugin';
        $candidates[] = $nsBase . '\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', $slug))) . 'Plugin';
        $candidates[] = $nsBase;

        foreach ($candidates as $class) {
            if (class_exists($class) && in_array(PluginInterface::class, class_implements($class), true)) {
                return $class;
            }
        }

        return null;
    }

    private function checkDependencies(array $requires): array
    {
        $missing = [];
        $active = [];

        try {
            $stmt = $this->pdo->prepare(
                "SELECT slug FROM plugins WHERE status = 'active' AND (tenant_id = ? OR tenant_id IS NULL)"
            );
            $stmt->execute([$this->tenantId]);
            $active = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            // ignore
        }

        foreach ($requires as $dep) {
            if (!in_array($dep, $active, true)) {
                $missing[] = $dep;
            }
        }

        return $missing;
    }

    private function upsertRecord(PluginInterface $plugin, string $status): void
    {
        $manifest = $plugin->getManifest();
        $slug = $plugin->getSlug();

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO plugins (slug, name, version, description, author, status, tenant_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 name = VALUES(name), version = VALUES(version), description = VALUES(description),
                 author = VALUES(author), status = VALUES(status), updated_at = NOW()"
            );
            $stmt->execute([
                $slug,
                $manifest['name'] ?? $slug,
                $manifest['version'] ?? '0.0.0',
                $manifest['description'] ?? '',
                $manifest['author'] ?? '',
                $status,
                $this->tenantId
            ]);
        } catch (PDOException $e) {
            error_log("[PluginManager] upsertRecord error: " . $e->getMessage());
        }
    }
}
