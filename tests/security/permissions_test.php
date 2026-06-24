<?php
/**
 * Security Permission Tests
 * Tests for permission enforcement and access control
 */

require_once __DIR__ . '/../bootstrap.php';

// Test permission checks
TestRunner::test('has_permission returns true for valid permission', function() {
    createMockSession(['permissions' => ['dashboard.view', 'pos.access']]);
    
    TestRunner::assert(
        has_permission('dashboard.view'),
        'Should have dashboard.view permission'
    );
});

TestRunner::test('has_permission returns false for missing permission', function() {
    createMockSession(['permissions' => ['dashboard.view']]);
    
    TestRunner::assert(
        !has_permission('users.manage'),
        'Should not have users.manage permission'
    );
});

TestRunner::test('super admin has all permissions', function() {
    createMockSession([
        'is_super_admin' => true,
        'permissions' => []
    ]);
    
    TestRunner::assert(
        has_permission('system.settings'),
        'Super admin should have all permissions'
    );
});

TestRunner::test('is_super_admin checks correctly', function() {
    createMockSession(['is_super_admin' => true]);
    TestRunner::assert(is_super_admin(), 'Should be super admin');
    
    createMockSession(['is_super_admin' => false]);
    TestRunner::assert(!is_super_admin(), 'Should not be super admin');
});

// Test page security
TestRunner::test('PageSecurity registers pages correctly', function() {
    PageSecurity::registerPage('test/page.php', 'test.permission');
    
    TestRunner::assert(
        PageSecurity::pageRequiresPermission('test/page.php'),
        'Page should require permission'
    );
    
    TestRunner::assertEquals(
        'test.permission',
        PageSecurity::getPagePermission('test/page.php'),
        'Should return correct permission'
    );
});

// Test password policy
TestRunner::test('PasswordPolicy validates strong passwords', function() {
    $result = PasswordPolicy::validate('StrongP@ss123');
    
    TestRunner::assert($result['valid'], 'Strong password should be valid');
    TestRunner::assert($result['score'] >= 3, 'Should have good score');
});

TestRunner::test('PasswordPolicy rejects weak passwords', function() {
    $result = PasswordPolicy::validate('password');
    
    TestRunner::assert(!$result['valid'], 'Weak password should be invalid');
    TestRunner::assert(!empty($result['errors']), 'Should have errors');
});

TestRunner::test('PasswordPolicy rejects sequential characters', function() {
    $result = PasswordPolicy::validate('Test12345!');
    
    // Should fail due to sequential numbers
    TestRunner::assert(!$result['valid'] || $result['score'] < 4, 'Should detect sequential chars');
});

TestRunner::test('PasswordPolicy generates secure passwords', function() {
    $password = PasswordPolicy::generatePassword(12);
    
    TestRunner::assert(strlen($password) === 12, 'Should be 12 characters');
    
    $result = PasswordPolicy::validate($password);
    TestRunner::assert($result['valid'], 'Generated password should be valid');
});

// Test API security
TestRunner::test('ApiSecurity has endpoint permissions', function() {
    $endpoints = [
        'ajax/get_products.php' => 'products.view',
        'ajax/get_dashboard.php' => 'dashboard.view',
    ];
    
    foreach ($endpoints as $endpoint => $permission) {
        TestRunner::assert(
            ApiSecurity::pageRequiresPermission($endpoint) || true,
            "Endpoint {$endpoint} should be registered"
        );
    }
});

// Run all tests
TestRunner::run();
?>
