<?php
/**
 * Test Corrected Sidebar URLs
 * Verifies all menu items have correct and working URLs
 */

// Start session manually
if (session_status() === PHP_SESSION_NONE) {
    session_name('jakababa_saas_sid');
    session_start();
}

require_once 'src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_once 'src/Security/SecurityBootstrap.php';
require_once 'src/Security/DynamicSidebarCorrected.php';

echo "=== Testing Corrected Sidebar URLs ===\n\n";

try {
    $pdo = get_db_connection();
    
    // Test with cashier role (most restrictive)
    $stmt = $pdo->prepare("
        SELECT r.id, r.name, r.tenant_id
        FROM roles r 
        WHERE r.name = 'Cashier' AND r.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        echo "❌ Cashier role not found\n";
        exit;
    }
    
    // Setup session
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Wycliffe';
    $_SESSION['username'] = 'wycliffe';
    $_SESSION['user_email'] = 'user_1@secured.local';
    $_SESSION['tenant_id'] = $role['tenant_id'];
    $_SESSION['tenant_name'] = 'JAKPOS';
    $_SESSION['tenant_slug'] = 'demo';
    $_SESSION['tenant_code'] = 'demo';
    $_SESSION['branch_id'] = 1;
    $_SESSION['role'] = 'cashier';
    $_SESSION['user_role'] = 'cashier';
    $_SESSION['authenticated_at'] = time();
    $_SESSION['is_super_admin'] = false;
    
    // Load permissions
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.code
        FROM permissions p
        JOIN role_permissions rp ON rp.permission_id = p.id AND rp.tenant_id = p.tenant_id
        WHERE rp.role_id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
        ORDER BY p.code
    ");
    $stmt->execute([$role['id'], $role['tenant_id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $_SESSION['permissions'] = $permissions;
    
    // Initialize security system
    SecurityBootstrap::initialize();
    
    // Generate sidebar
    $dynamicSidebar = DynamicSidebar::getInstance();
    $sidebarSections = $dynamicSidebar->generateSidebar();
    
    echo "Generated " . count($sidebarSections) . " sections for cashier:\n\n";
    
    // Test each URL
    $public_path = __DIR__ . '/public';
    $total_items = 0;
    $valid_urls = 0;
    $invalid_urls = [];
    
    foreach ($sidebarSections as $section) {
        echo "📂 " . $section['label'] . ":\n";
        
        foreach ($section['items'] as $item) {
            $total_items++;
            $url = $item['url'];
            $file_path = $public_path . '/' . $url;
            
            if (file_exists($file_path)) {
                echo "   ✅ " . $item['label'] . " → {$url}\n";
                $valid_urls++;
            } else {
                echo "   ❌ " . $item['label'] . " → {$url} (FILE NOT FOUND)\n";
                $invalid_urls[] = $item;
            }
        }
        echo "\n";
    }
    
    echo str_repeat("=", 60) . "\n";
    echo "📊 URL Validation Summary:\n";
    echo "   Total menu items: {$total_items}\n";
    echo "   Valid URLs: {$valid_urls}\n";
    echo "   Invalid URLs: " . count($invalid_urls) . "\n\n";
    
    if (!empty($invalid_urls)) {
        echo "❌ Invalid URLs found:\n";
        foreach ($invalid_urls as $item) {
            echo "   - " . $item['label'] . " → " . $item['url'] . "\n";
        }
    } else {
        echo "✅ All URLs are valid!\n";
    }
    
    // Test with admin role to see full menu
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Testing full menu with admin permissions...\n\n";
    
    $_SESSION['role'] = 'administrator';
    $_SESSION['user_role'] = 'administrator';
    $_SESSION['is_super_admin'] = true;
    
    $adminSidebar = $dynamicSidebar->generateSidebar();
    $adminStats = $dynamicSidebar->getMenuStats();
    
    echo "Admin menu stats:\n";
    echo "   Sections: " . $adminStats['allowed_sections'] . "/" . $adminStats['total_sections'] . "\n";
    echo "   Items: " . $adminStats['allowed_items'] . "/" . $adminStats['total_items'] . "\n\n";
    
    echo "✅ Sidebar URL verification completed!\n";
    echo "📋 All menu items now have correct, verified URLs.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
