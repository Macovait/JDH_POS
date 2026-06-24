<?php
// Test script to check what logo is being served
$settings = [
    'company_logo' => $_GET['logo'] ?? '',
    'company_name' => 'Test Company'
];
$has_logo = !empty($settings['company_logo']);
?>
<!DOCTYPE html>
<html>
<head><title>Logo Test</title></head>
<body style="margin:0;background:#0F172A;">
<div style="padding:20px;">
    <h2 style="color:#fff;">Logo Test: <?php echo $has_logo ? 'HAS LOGO' : 'NO LOGO (using initials)'; ?></h2>
    <?php if ($has_logo): ?>
    <div style="height:40px;max-width:160px;overflow:hidden;">
        <img src="<?php echo htmlspecialchars($settings['company_logo']); ?>" 
             style="height:100%;max-height:40px;width:auto;object-fit:contain;">
    </div>
    <?php else: ?>
    <div style="width:40px;height:40px;background:#FBBF24;border-radius:8px;display:flex;align-items:center;justify-content:center;">
        <span style="color:#000;font-weight:bold;">TC</span>
    </div>
    <?php endif; ?>
    <p style="color:#9CA3AF;">If this shows correctly, the issue is in the main POS layout, not the logo itself.</p>
</div>
</body>
</html>
