<?php
/**
 * Authentication Tests
 * Test critical auth paths
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../src/auth_minimal.php';

$runner = new TestRunner();

// Test 1: Session starts correctly
$runner->test('Session starts correctly', function($t) {
    // Clear any existing session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    // Start fresh
    start_session_minimal();
    
    $t->assert(session_status() === PHP_SESSION_ACTIVE, 'Session should be active');
});

// Test 2: Session variables can be set and retrieved
$runner->test('Session variables persist', function($t) {
    start_session_minimal();
    
    $_SESSION['test_value'] = 'hello';
    $t->assertEquals('hello', $_SESSION['test_value'], 'Session variable should persist');
    
    unset($_SESSION['test_value']);
});

// Test 3: is_logged_in works correctly
$runner->test('is_logged_in returns false when not logged in', function($t) {
    start_session_minimal();
    
    // Clear login state
    unset($_SESSION['is_logged_in']);
    unset($_SESSION['user_id']);
    
    $t->assert(is_logged_in() === false, 'Should not be logged in');
});

// Test 4: is_logged_in returns true when logged in
$runner->test('is_logged_in returns true when logged in', function($t) {
    start_session_minimal();
    
    $_SESSION['is_logged_in'] = true;
    $_SESSION['user_id'] = 123;
    
    $t->assert(is_logged_in() === true, 'Should be logged in');
    
    // Cleanup
    unset($_SESSION['is_logged_in']);
    unset($_SESSION['user_id']);
});

// Test 5: get_current_user_id returns correct value
$runner->test('get_current_user_id returns user ID', function($t) {
    start_session_minimal();
    
    $_SESSION['user_id'] = 456;
    $t->assertEquals(456, get_current_user_id(), 'Should return correct user ID');
    
    unset($_SESSION['user_id']);
});

// Test 6: get_current_tenant_id returns correct value
$runner->test('get_current_tenant_id returns tenant ID', function($t) {
    start_session_minimal();
    
    $_SESSION['tenant_id'] = 789;
    $t->assertEquals(789, get_current_tenant_id(), 'Should return correct tenant ID');
    
    unset($_SESSION['tenant_id']);
});

// Run tests
$success = $runner->run();
exit($success ? 0 : 1);
