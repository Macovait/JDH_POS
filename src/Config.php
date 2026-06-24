<?php
/**
 * Configuration Loader
 * Loads environment variables from .env file
 */

namespace JDH\POS;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;
    
    /**
     * Load configuration from .env file
     */
    public static function load(string $envFile = __DIR__ . '/../.env'): void
    {
        if (self::$loaded) {
            return;
        }
        
        if (!file_exists($envFile)) {
            throw new \Exception("Environment file not found: {$envFile}");
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove inline comments
                if (strpos($value, '#') !== false) {
                    $value = trim(explode('#', $value)[0]);
                }
                
                // Remove quotes
                $value = trim($value, '"\'');
                
                self::$config[$key] = $value;
                
                // Also set as environment variable for compatibility
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get a configuration value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$config[$key] ?? $default;
    }
    
    /**
     * Check if a configuration key exists
     */
    public static function has(string $key): bool
    {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset(self::$config[$key]);
    }
    
    /**
     * Get all configuration values
     */
    public static function all(): array
    {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$config;
    }
    
    /**
     * Get database configuration as array
     */
    public static function getDatabaseConfig(): array
    {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'port' => self::get('DB_PORT', '3306'),
            'name' => self::get('DB_NAME', 'jdh_pos'),
            'user' => self::get('DB_USER', 'root'),
            'pass' => self::get('DB_PASS', ''),
            'charset' => self::get('DB_CHARSET', 'utf8mb4'),
        ];
    }
    
    /**
     * Create PDO connection using config
     */
    public static function getPDO(): \PDO
    {
        $db = self::getDatabaseConfig();
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";
        
        $pdo = new \PDO($dsn, $db['user'], $db['pass']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        
        return $pdo;
    }
}

// Auto-load on include
Config::load();
