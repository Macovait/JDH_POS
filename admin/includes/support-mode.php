<?php
/**
 * Support Mode Banner
 * 
 * Include this file in the POS interface or any page accessed via support mode
 * to show visual indicator and session controls.
 * 
 * Usage:
 *   if (!empty($_SESSION['support_mode'])) {
 *       include 'includes/support-mode.php';
 *   }
 */

// Validate support session is still active
$isValidSupportSession = false;
if (!empty($_SESSION['support_mode']) && !empty($_SESSION['support_access_id'])) {
    // Check if session is still valid in database
    try {
        $stmt = $pdo->prepare("
            SELECT id, expires_at, access_type 
            FROM support_access_logs 
            WHERE id = ? AND status = 'active' AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['support_access_id']]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            $isValidSupportSession = true;
            $_SESSION['support_expires_at'] = $session['expires_at'];
            
            // Calculate remaining time
            $expires = strtotime($session['expires_at']);
            $now = time();
            $remaining = $expires - $now;
            $remainingMinutes = floor($remaining / 60);
            $remainingSeconds = $remaining % 60;
        } else {
            // Session expired or revoked - clear it
            unset($_SESSION['support_mode']);
            unset($_SESSION['support_access_id']);
            unset($_SESSION['support_company_id']);
            header('Location: ' . admin_url('support-entry.php?error=session_expired'));
            exit;
        }
    } catch (Exception $e) {
        error_log("Support mode validation error: " . $e->getMessage());
    }
}

if (!$isValidSupportSession) {
    return; // Don't show banner if session is invalid
}

$accessType = $_SESSION['support_access_type'] ?? 'read_only';
$companyName = $_SESSION['support_company_name'] ?? 'Unknown Company';
$isReadOnly = $accessType === 'read_only';
?>

<!-- Support Mode Banner -->
<div id="supportModeBanner" class="fixed top-0 left-0 right-0 z-[9999] <?= $isReadOnly ? 'bg-blue-600' : 'bg-amber-600' ?> text-white shadow-lg">
    <div class="flex items-center justify-between px-4 py-2">
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <i class="fas fa-headset"></i>
                <span class="font-semibold">SUPPORT MODE</span>
                <?php if ($isReadOnly): ?>
                    <span class="px-2 py-0.5 rounded bg-blue-500 text-xs font-medium">READ ONLY</span>
                <?php else: ?>
                    <span class="px-2 py-0.5 rounded bg-red-500 text-xs font-medium animate-pulse">FULL ACCESS</span>
                <?php endif; ?>
            </div>
            
            <div class="hidden sm:flex items-center gap-2 text-sm text-white/80">
                <span>|</span>
                <span>Company: <strong><?= htmlspecialchars($companyName) ?></strong></span>
            </div>
            
            <div class="flex items-center gap-2 text-sm">
                <span>|</span>
                <span id="supportTimer" class="font-mono font-bold">--:--</span>
                <span>remaining</span>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <button onclick="extendSession()" class="px-3 py-1 rounded bg-slate-700/70 hover:bg-slate-700/80 text-sm transition">
                <i class="fas fa-clock"></i> +15 min
            </button>
            <a href="<?= admin_url('support-exit.php') ?>" class="px-3 py-1 rounded bg-red-500 hover:bg-red-600 text-sm transition flex items-center gap-1">
                <i class="fas fa-sign-out-alt"></i> Exit
            </a>
        </div>
    </div>
    
    <!-- Warning for full access -->
    <?php if (!$isReadOnly): ?>
    <div class="bg-red-700 text-center py-1 text-xs">
        <i class="fas fa-exclamation-triangle"></i>
        You have FULL ACCESS. All changes will be logged. Use with caution.
    </div>
    <?php endif; ?>
</div>

<!-- Spacer for fixed banner -->
<div class="h-<?= $isReadOnly ? '10' : '14' ?>"></div>

<script>
// Timer countdown
const expiresAt = new Date('<?= $_SESSION['support_expires_at'] ?>').getTime();

function updateTimer() {
    const now = new Date().getTime();
    const distance = expiresAt - now;
    
    if (distance <= 0) {
        document.getElementById('supportTimer').textContent = 'EXPIRED';
        document.getElementById('supportTimer').classList.add('text-red-200', 'animate-pulse');
        // Redirect after brief delay
        setTimeout(() => {
            window.location.href = '<?= admin_url('support-entry.php?error=session_expired') ?>';
        }, 3000);
        return;
    }
    
    const minutes = Math.floor(distance / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
    document.getElementById('supportTimer').textContent = 
        minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
    
    // Change color when running low
    if (minutes < 5) {
        document.getElementById('supportTimer').classList.add('text-red-200', 'animate-pulse');
    }
}

updateTimer();
setInterval(updateTimer, 1000);

// Extend session
function extendSession() {
    if (!confirm('Request 15 minute extension? This will be logged.')) return;
    
    fetch('<?= admin_url('support-extend.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({access_id: <?= $_SESSION['support_access_id'] ?>})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to extend session');
        }
    })
    .catch(err => {
        alert('Error extending session');
        console.error(err);
    });
}
</script>
