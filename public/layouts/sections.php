<?php
/**
 * Laravel-style @section / @yield helpers for plain PHP layouts
 */

if (!function_exists('start_section')) {
    $__sections = [];
    $__current_section = null;

    function start_section(string $name): void {
        global $__current_section;
        $__current_section = $name;
        ob_start();
    }

    function end_section(): void {
        global $__sections, $__current_section;
        $__sections[$__current_section] = ob_get_clean();
        $__current_section = null;
    }

    function yield_section(string $name, string $default = ''): void {
        global $__sections;
        echo $__sections[$name] ?? $default;
    }

    function has_section(string $name): bool {
        global $__sections;
        return isset($__sections[$name]) && trim($__sections[$name]) !== '';
    }
}
