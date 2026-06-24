<?php
/**
 * Test Bootstrap
 * Initializes the testing environment
 */

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test constants
define('TESTING', true);
define('BASE_PATH', dirname(__DIR__));

// Load core files
require_once BASE_PATH . '/src/paths.php';

// Simple test framework
class TestRunner {
    private static array $tests = [];
    private static array $results = [];
    private static int $passed = 0;
    private static int $failed = 0;
    
    /**
     * Register a test
     */
    public static function test(string $name, callable $callback): void {
        self::$tests[] = ['name' => $name, 'callback' => $callback];
    }
    
    /**
     * Assert that condition is true
     */
    public static function assert(bool $condition, string $message = ''): void {
        if (!$condition) {
            throw new AssertionError($message ?: 'Assertion failed');
        }
    }
    
    /**
     * Assert equals
     */
    public static function assertEquals($expected, $actual, string $message = ''): void {
        if ($expected !== $actual) {
            throw new AssertionError($message ?: "Expected " . var_export($expected, true) . " but got " . var_export($actual, true));
        }
    }
    
    /**
     * Assert not null
     */
    public static function assertNotNull($value, string $message = ''): void {
        if ($value === null) {
            throw new AssertionError($message ?: 'Expected value to not be null');
        }
    }
    
    /**
     * Assert throws exception
     */
    public static function assertThrows(string $exceptionClass, callable $callback): void {
        try {
            $callback();
            throw new AssertionError("Expected exception {$exceptionClass} was not thrown");
        } catch (Exception $e) {
            if (!($e instanceof $exceptionClass)) {
                throw new AssertionError("Expected {$exceptionClass} but got " . get_class($e));
            }
        }
    }
    
    /**
     * Run all registered tests
     */
    public static function run(): void {
        echo "=== Running Tests ===\n\n";
        
        foreach (self::$tests as $test) {
            try {
                $test['callback']();
                self::$passed++;
                echo "✅ PASS: {$test['name']}\n";
            } catch (Exception $e) {
                self::$failed++;
                echo "❌ FAIL: {$test['name']}\n";
                echo "   Error: {$e->getMessage()}\n";
            }
        }
        
        echo "\n=== Test Results ===\n";
        echo "Total: " . (self::$passed + self::$failed) . "\n";
        echo "✅ Passed: " . self::$passed . "\n";
        echo "❌ Failed: " . self::$failed . "\n";
        
        if (self::$failed > 0) {
            exit(1);
        }
    }
}

// AssertionError class for PHP < 8.0
if (!class_exists('AssertionError')) {
    class AssertionError extends Error {}
}

// Mock functions for testing
function mockFunction(string $name, callable $implementation): void {
    if (!function_exists($name)) {
        throw new Exception("Function {$name} does not exist");
    }
    
    // Store original
    $original = $name;
    
    // Override (requires runkit or similar extension in production)
    // For testing, we'll use a mock wrapper
}

// Database mock for testing
class MockDatabase {
    private static array $data = [];
    
    public static function set(string $table, array $records): void {
        self::$data[$table] = $records;
    }
    
    public static function get(string $table): array {
        return self::$data[$table] ?? [];
    }
    
    public static function reset(): void {
        self::$data = [];
    }
}

// Helper functions
function createMockUser(array $overrides = []): array {
    return array_merge([
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'role' => 'cashier',
        'tenant_id' => 1,
        'branch_id' => 1,
        'permissions' => ['dashboard.view', 'pos.access']
    ], $overrides);
}

function createMockSession(array $overrides = []): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION = array_merge([
        'user_id' => 1,
        'user_name' => 'Test User',
        'tenant_id' => 1,
        'role' => 'cashier',
        'permissions' => ['dashboard.view', 'pos.access'],
        'is_super_admin' => false
    ], $overrides);
}
?>
