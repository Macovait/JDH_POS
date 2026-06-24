<?php
/**
 * Event/Hook System
 * Allows plugins and extensions to hook into system events
 */

namespace JDH\POS\Events;

class HookManager
{
    private static array $hooks = [];
    private static array $filters = [];
    
    /**
     * Register an action hook
     * 
     * @param string $hookName Name of the hook
     * @param callable $callback Function to execute
     * @param int $priority Execution priority (lower = earlier)
     * @param int $acceptedArgs Number of arguments the callback accepts
     */
    public static function addAction(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        if (!isset(self::$hooks[$hookName])) {
            self::$hooks[$hookName] = [];
        }
        
        self::$hooks[$hookName][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs
        ];
        
        // Sort by priority
        usort(self::$hooks[$hookName], function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }
    
    /**
     * Execute all callbacks for an action hook
     * 
     * @param string $hookName Hook to execute
     * @param mixed ...$args Arguments to pass to callbacks
     */
    public static function doAction(string $hookName, ...$args): void
    {
        if (!isset(self::$hooks[$hookName])) {
            return;
        }
        
        foreach (self::$hooks[$hookName] as $hook) {
            $callback = $hook['callback'];
            $argCount = min($hook['accepted_args'], count($args));
            
            try {
                call_user_func_array($callback, array_slice($args, 0, $argCount));
            } catch (\Exception $e) {
                error_log("Hook error in {$hookName}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Register a filter hook
     * 
     * @param string $hookName Name of the filter
     * @param callable $callback Function to execute (must return value)
     * @param int $priority Execution priority
     * @param int $acceptedArgs Number of arguments
     */
    public static function addFilter(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        if (!isset(self::$filters[$hookName])) {
            self::$filters[$hookName] = [];
        }
        
        self::$filters[$hookName][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs
        ];
        
        // Sort by priority
        usort(self::$filters[$hookName], function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }
    
    /**
     * Execute all filters and return filtered value
     * 
     * @param string $hookName Filter to execute
     * @param mixed $value Value to filter
     * @param mixed ...$args Additional arguments
     * @return mixed Filtered value
     */
    public static function applyFilter(string $hookName, $value, ...$args)
    {
        if (!isset(self::$filters[$hookName])) {
            return $value;
        }
        
        foreach (self::$filters[$hookName] as $filter) {
            $callback = $filter['callback'];
            $argCount = $filter['accepted_args'];
            
            // Build argument array: value first, then additional args
            $callbackArgs = array_merge([$value], array_slice($args, 0, $argCount - 1));
            
            try {
                $value = call_user_func_array($callback, $callbackArgs);
            } catch (\Exception $e) {
                error_log("Filter error in {$hookName}: " . $e->getMessage());
            }
        }
        
        return $value;
    }
    
    /**
     * Remove an action hook
     */
    public static function removeAction(string $hookName, callable $callback): bool
    {
        if (!isset(self::$hooks[$hookName])) {
            return false;
        }
        
        foreach (self::$hooks[$hookName] as $key => $hook) {
            if ($hook['callback'] === $callback) {
                unset(self::$hooks[$hookName][$key]);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Remove a filter hook
     */
    public static function removeFilter(string $hookName, callable $callback): bool
    {
        if (!isset(self::$filters[$hookName])) {
            return false;
        }
        
        foreach (self::$filters[$hookName] as $key => $filter) {
            if ($filter['callback'] === $callback) {
                unset(self::$filters[$hookName][$key]);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a hook has any callbacks
     */
    public static function hasAction(string $hookName): bool
    {
        return !empty(self::$hooks[$hookName]);
    }
    
    /**
     * Check if a filter has any callbacks
     */
    public static function hasFilter(string $hookName): bool
    {
        return !empty(self::$filters[$hookName]);
    }
    
    /**
     * Get all registered hooks (for debugging)
     */
    public static function getAllHooks(): array
    {
        return [
            'actions' => array_keys(self::$hooks),
            'filters' => array_keys(self::$filters)
        ];
    }
}

/**
 * Global helper functions for hooks
 */

if (!function_exists('add_action')) {
    function add_action(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        HookManager::addAction($hookName, $callback, $priority, $acceptedArgs);
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hookName, ...$args): void
    {
        HookManager::doAction($hookName, ...$args);
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        HookManager::addFilter($hookName, $callback, $priority, $acceptedArgs);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hookName, $value, ...$args)
    {
        return HookManager::applyFilter($hookName, $value, ...$args);
    }
}
