<?php
/**
 * Update Admin Password Script
 * Generates a new password hash and updates the admin password in production
 */

// Load environment
require_once __DIR__ . '/../src/db.php';

// Generate new password hash
$newPassword = 'Admin@2026!Secure'; // Change this to your desired password
$passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

echo "=== Admin Password Update ===\n";
echo "New Password: $newPassword\n";
echo "Password Hash: $passwordHash\n\n";

try {
    $db = get_db_connection();

    // Update admin password
    $stmt = $db->prepare("UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE username = 'admin'");
    $stmt->execute([$passwordHash]);

    if ($stmt->rowCount() > 0) {
        echo "✓ Admin password updated successfully!\n";
        echo "Username: admin\n";
        echo "New Password: $newPassword\n";
        echo "\n⚠️  IMPORTANT: Change this password after first login!\n";
    } else {
        echo "✗ No admin user found to update.\n";
    }

} catch (Exception $e) {
    echo "✗ Error updating password: " . $e->getMessage() . "\n";
}
