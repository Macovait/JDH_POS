<?php
/**
 * Fix admin access checks in all admin files
 */

$files = [
    'users.php',
    'support-entry.php',
    'support-access.php',
    'subscriptions.php',
    'settings.php',
    'roles.php',
    'revenue.php',
    'reports.php',
    'plans.php',
    'notifications.php',
    'export.php',
    'companies.php',
    'audit-logs.php'
];

$oldPattern = "if (empty(\$_SESSION['admin_id']) || !is_super_admin())";
$newPattern = "if ((!isset(\$_SESSION['admin_id']) && !isset(\$_SESSION['user_id'])) || !is_super_admin())";

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        echo "Skip: $file (not found)\n";
        continue;
    }
    
    $content = file_get_contents($path);
    if (strpos($content, $oldPattern) === false) {
        echo "Skip: $file (already fixed or different pattern)\n";
        continue;
    }
    
    $newContent = str_replace($oldPattern, $newPattern, $content);
    
    // Also fix the OwnerPanelService initialization if needed
    $oldService = "\$ownerService = new OwnerPanelService(\$pdo, \$_SESSION['admin_id']);";
    $newService = "\$adminId = \$_SESSION['admin_id'] ?? (\$_SESSION['user_id'] ?? 0);\n\$ownerService = new OwnerPanelService(\$pdo, \$adminId);";
    
    if (strpos($newContent, $oldService) !== false) {
        $newContent = str_replace($oldService, $newService, $newContent);
    }
    
    file_put_contents($path, $newContent);
    echo "Fixed: $file\n";
}

echo "\nDone!\n";
