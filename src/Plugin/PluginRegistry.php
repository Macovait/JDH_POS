<?php
declare(strict_types=1);

namespace JDH\POS\Plugin;

/**
 * Holds references to all loaded plugin instances.
 */
class PluginRegistry
{
    /** @var array<string, PluginInterface> */
    private static array $plugins = [];

    public static function register(PluginInterface $plugin): void
    {
        self::$plugins[$plugin->getSlug()] = $plugin;
    }

    public static function unregister(string $slug): void
    {
        unset(self::$plugins[$slug]);
    }

    public static function get(string $slug): ?PluginInterface
    {
        return self::$plugins[$slug] ?? null;
    }

    /**
     * @return array<string, PluginInterface>
     */
    public static function all(): array
    {
        return self::$plugins;
    }

    /**
     * @return array<int, PluginInterface>
     */
    public static function getByFeature(string $feature): array
    {
        $result = [];
        foreach (self::$plugins as $plugin) {
            $manifest = $plugin->getManifest();
            if (in_array($feature, $manifest['features'] ?? [], true)) {
                $result[] = $plugin;
            }
        }
        return $result;
    }

    public static function has(string $slug): bool
    {
        return isset(self::$plugins[$slug]);
    }

    public static function clear(): void
    {
        self::$plugins = [];
    }
}
