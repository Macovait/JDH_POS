<?php
declare(strict_types=1);

namespace JDH\POS\Plugin;

use PDO;

/**
 * Base class that all plugins should extend.
 * Provides sensible defaults so plugins only override what they need.
 */
abstract class AbstractPlugin implements PluginInterface
{
    protected PDO $pdo;
    protected ?int $tenantId;
    protected array $settings = [];

    public function __construct(PDO $pdo, ?int $tenantId = null)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
    }

    public function activate(): void
    {
        foreach ($this->getMigrations() as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (\PDOException $e) {
                error_log("[Plugin:{$this->getName()}] Migration failed: " . $e->getMessage());
            }
        }
    }

    public function deactivate(): void
    {
        // Override if cleanup is needed
    }

    public function boot(): void
    {
        // Load persisted settings
        $this->settings = $this->loadSettings();
        $this->register();
    }

    public function register(): void
    {
        // Override to register hooks, filters, routes
    }

    public function getMigrations(): array
    {
        return [];
    }

    public function getAdminMenu(): array
    {
        return [];
    }

    public function getPosAssets(): array
    {
        return [];
    }

    public function getPosPanels(): array
    {
        return [];
    }

    public function getRoutes(): array
    {
        return [];
    }

    /**
     * Get a setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a setting value.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $this->settings[$key] = $value;
        $this->saveSettings();
    }

    /**
     * Unique plugin identifier (slug). Should be overridden.
     */
    public function getSlug(): string
    {
        $class = static::class;
        $base = basename(str_replace('\\', '/', $class));
        return $this->kebabCase(str_replace('Plugin', '', $base));
    }

    /**
     * Human-readable name from manifest.
     */
    public function getName(): string
    {
        return $this->getManifest()['name'] ?? $this->getSlug();
    }

    /**
     * Path to the plugin directory.
     */
    public function getPath(): string
    {
        $ref = new \ReflectionClass(static::class);
        return dirname($ref->getFileName());
    }

    /**
     * URL to the plugin directory (relative to public).
     */
    public function getUrl(): string
    {
        $path = $this->getPath();
        $root = realpath(ROOT_PATH);
        $relative = str_replace('\\', '/', substr($path, strlen($root)));
        return $relative;
    }

    protected function loadSettings(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT settings FROM plugins WHERE slug = ? AND (tenant_id = ? OR tenant_id IS NULL) LIMIT 1"
            );
            $stmt->execute([$this->getSlug(), $this->tenantId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['settings'])) {
                $decoded = json_decode($row['settings'], true);
                return is_array($decoded) ? $decoded : [];
            }
        } catch (\PDOException $e) {
            // Table may not exist yet
        }
        return [];
    }

    protected function saveSettings(): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE plugins SET settings = ? WHERE slug = ? AND (tenant_id = ? OR tenant_id IS NULL)"
            );
            $stmt->execute([json_encode($this->settings), $this->getSlug(), $this->tenantId]);
        } catch (\PDOException $e) {
            error_log("[Plugin:{$this->getName()}] Failed to save settings: " . $e->getMessage());
        }
    }

    protected function kebabCase(string $str): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $str));
    }
}
