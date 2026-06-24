<?php
declare(strict_types=1);

namespace JDH\POS\Plugin;

/**
 * Interface that all plugins must implement.
 */
interface PluginInterface
{
    /**
     * Return the plugin manifest/metadata.
     *
     * @return array{
     *   name: string,
     *   version: string,
     *   description: string,
     *   author: string,
     *   requires: array<string>,
     *   features: array<string>,
     *   hooks: array<string>,
     *   assets: array{css?: string[], js?: string[]},
     *   settings_schema: array<string, mixed>
     * }
     */
    public function getManifest(): array;

    /**
     * Called when the plugin is activated.
     */
    public function activate(): void;

    /**
     * Called when the plugin is deactivated.
     */
    public function deactivate(): void;

    /**
     * Called when the plugin is loaded (on every request if active).
     */
    public function boot(): void;

    /**
     * Register hooks, filters, routes, etc.
     */
    public function register(): void;

    /**
     * Optional database migrations to run on activation.
     *
     * @return array<int, string> SQL statements
     */
    public function getMigrations(): array;

    /**
     * Return any custom admin menu items.
     *
     * @return array<int, array{label: string, url: string, icon?: string, permission?: string}>
     */
    public function getAdminMenu(): array;

    /**
     * Return POS-specific JS modules to inject.
     *
     * @return array<int, array{file: string, handle: string, deps?: string[], inline?: bool}>
     */
    public function getPosAssets(): array;

    /**
     * Return POS panel components (HTML or JS mount points).
     *
     * @return array<int, array{id: string, label: string, position: string, content?: string}>
     */
    public function getPosPanels(): array;

    /**
     * Return API endpoints registered by this plugin.
     *
     * @return array<int, array{method: string, route: string, callback: string, auth: bool}>
     */
    public function getRoutes(): array;
}
