<?php
/**
 * Support Mode Exit
 * 
 * Cleanly exit support mode and return to owner panel.
 * Logs the exit action and clears session.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Services/OwnerPanelService.php';

use JDH_POS\Services\OwnerPanelService;

admin_require_super_admin();

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    header('Location: ' . admin_url('login.php'));
    exit;
}

$pdo = admin_require_db(['admins', 'pos_tenants']);
$ownerService = new OwnerPanelService($pdo, $adminId);

// Log exit if in support mode
if (!empty($_SESSION['support_access_id'])) {
    try {
        // Log the exit action
        $stmt = $pdo->prepare("
            INSERT INTO support_access_actions 
            (access_id, admin_id, action, details, ip_address, created_at)
            VALUES (?, ?, 'session_exited', 'Support session manually exited by admin', ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['support_access_id'],
            $adminId,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        // Optionally mark session as completed
        $stmt = $pdo->prepare("
            UPDATE support_access_logs 
            SET status = 'completed', ended_at = NOW()
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$_SESSION['support_access_id']]);
        
    } catch (Exception $e) {
        error_log("Error logging support exit: " . $e->getMessage());
    }
}

// Clear all support session data
$wasInSupportMode = !empty($_SESSION['support_mode']);
unset($_SESSION['support_mode']);
unset($_SESSION['support_access_id']);
unset($_SESSION['support_company_id']);
unset($_SESSION['support_company_name']);
unset($_SESSION['support_company_slug']);
unset($_SESSION['support_access_type']);
unset($_SESSION['support_started_at']);
unset($_SESSION['support_expires_at']);

// Redirect back to owner panel or support entry
if ($wasInSupportMode) {
    header('Location: ' . admin_url('support-access.php?message=session_ended'));
} else {
    header('Location: ' . admin_url('dashboard.php'));
}
exit;
