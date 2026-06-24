<?php
$file = 'c:\xampp\htdocs\JDH_POS\src\functions.php';
$content = file_get_contents($file);

// 1. Optimize check_permission with static caching
$old = <<<'CODE'
    function check_permission(string $permission_code): bool
    {
        $permission_code = sanitize_input($permission_code, 'alphanumeric');
        
        // Super admin has all permissions
        if (function_exists('is_super_admin') && is_super_admin()) {
            return true;
        }
        
        // Check session permissions
        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            if (in_array($permission_code, $_SESSION['permissions'], true)) {
                return true;
            }
        }
        
        if (isset($_SESSION['user_permissions']) && is_array($_SESSION['user_permissions'])) {
            if (in_array($permission_code, $_SESSION['user_permissions'], true)) {
                return true;
            }
        }
        
        // Check for wildcard permissions
        $wildcardPattern = str_replace('*', '.*', $permission_code);
        $wildcardPattern = '/^' . str_replace('/', '\/', $wildcardPattern) . '$/';
        
        $allPermissions = array_merge(
            $_SESSION['permissions'] ?? [],
            $_SESSION['user_permissions'] ?? []
        );
        
        foreach ($allPermissions as $perm) {
            if (str_contains($perm, '*')) {
                $pattern = '/^' . str_replace('*', '.*', str_replace('/', '\/', $perm)) . '$/';
                if (preg_match($pattern, $permission_code)) {
                    return true;
                }
            }
        }

        return false;
    }
CODE;

$new = <<<'CODE'
    function check_permission(string $permission_code): bool
    {
        static $cache = [];
        static $wildcardChecked = false;
        static $wildcardCache = [];

        $permission_code = sanitize_input($permission_code, 'alphanumeric');

        if (isset($cache[$permission_code])) {
            return $cache[$permission_code];
        }

        if (function_exists('is_super_admin') && is_super_admin()) {
            $cache[$permission_code] = true;
            return true;
        }

        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            if (in_array($permission_code, $_SESSION['permissions'], true)) {
                $cache[$permission_code] = true;
                return true;
            }
        }

        if (isset($_SESSION['user_permissions']) && is_array($_SESSION['user_permissions'])) {
            if (in_array($permission_code, $_SESSION['user_permissions'], true)) {
                $cache[$permission_code] = true;
                return true;
            }
        }

        if (!$wildcardChecked) {
            $allPermissions = array_merge(
                $_SESSION['permissions'] ?? [],
                $_SESSION['user_permissions'] ?? []
            );
            foreach ($allPermissions as $perm) {
                if (str_contains($perm, '*')) {
                    $wildcardCache[] = '/^' . str_replace('*', '.*', str_replace('/', '\/', $perm)) . '$/';
                }
            }
            $wildcardChecked = true;
        }

        foreach ($wildcardCache as $pattern) {
            if (preg_match($pattern, $permission_code)) {
                $cache[$permission_code] = true;
                return true;
            }
        }

        $cache[$permission_code] = false;
        return false;
    }
CODE;

if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new, $content);
    echo "check_permission optimized\n";
} else {
    echo "check_permission pattern not found\n";
}

// 2. Optimize get_settings with session caching
$old2 = <<<'CODE'
        if (empty($cache) && function_exists('db_fetch_all')) {
            try {
                $rows = db_fetch_all(
                    "SELECT setting_key, setting_value FROM settings WHERE tenant_id = ?",
                    [$tenant_id]
                );
                foreach ($rows as $row) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
            } catch (Exception $e) {
                error_log("Failed to fetch settings: " . $e->getMessage());
            }
        }
        
        if ($key !== null) {
            return $cache[$key] ?? $default;
        }
        return $cache;
    }
CODE;

$new2 = <<<'CODE'
        // Use session cache for cross-request persistence (30-second TTL)
        $sessionKey = '_settings_' . $tenant_id;
        if (empty($cache) && isset($_SESSION[$sessionKey]) && (time() - ($_SESSION[$sessionKey . '_time'] ?? 0) < 30)) {
            $cache = $_SESSION[$sessionKey];
        }

        if (empty($cache) && function_exists('db_fetch_all')) {
            try {
                $rows = db_fetch_all(
                    "SELECT setting_key, setting_value FROM settings WHERE tenant_id = ?",
                    [$tenant_id]
                );
                foreach ($rows as $row) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
                $_SESSION[$sessionKey] = $cache;
                $_SESSION[$sessionKey . '_time'] = time();
            } catch (Exception $e) {
                error_log("Failed to fetch settings: " . $e->getMessage());
            }
        }

        if ($key !== null) {
            return $cache[$key] ?? $default;
        }
        return $cache;
    }
CODE;

if (strpos($content, $old2) !== false) {
    $content = str_replace($old2, $new2, $content);
    echo "get_settings optimized\n";
} else {
    echo "get_settings pattern not found\n";
}

file_put_contents($file, $content);
echo "Done\n";
