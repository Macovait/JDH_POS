<?php
/**
 * JDH POS - Simple Test Framework
 * Run tests for critical paths
 */

class TestRunner {
    private $tests = [];
    private $passed = 0;
    private $failed = 0;
    
    public function test($name, $callback) {
        $this->tests[] = ['name' => $name, 'callback' => $callback];
    }
    
    public function assert($condition, $message = '') {
        if (!$condition) {
            throw new Exception("Assertion failed: " . $message);
        }
        return true;
    }
    
    public function assertEquals($expected, $actual, $message = '') {
        if ($expected !== $actual) {
            throw new Exception("Expected {$expected}, got {$actual}. " . $message);
        }
        return true;
    }
    
    public function run() {
        echo "=== JDH POS Test Suite ===\n\n";
        
        foreach ($this->tests as $test) {
            try {
                $test['callback']($this);
                echo "✓ PASS: {$test['name']}\n";
                $this->passed++;
            } catch (Exception $e) {
                echo "✗ FAIL: {$test['name']}\n";
                echo "  Error: {$e->getMessage()}\n";
                $this->failed++;
            }
        }
        
        echo "\n=== Results ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total: " . count($this->tests) . "\n";
        
        return $this->failed === 0;
    }
}

// Helper function to run all tests
function run_all_tests() {
    require_once __DIR__ . '/auth_test.php';
    require_once __DIR__ . '/database_test.php';
}
