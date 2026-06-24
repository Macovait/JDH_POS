<?php
/**
 * Shared Shop Footer
 */
?>
<footer class="shop-footer">
    <div style="max-width:1200px; margin:0 auto;">
        <p><strong><?php echo htmlspecialchars($store_name); ?></strong></p>
        <p style="margin-top:0.5rem; opacity:0.7;">Powered by JDH POS E-Commerce</p>
        <?php if (!empty($whatsapp_number)): ?>
        <p style="margin-top:0.75rem;">
            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp_number); ?>" target="_blank" style="color:var(--primary); text-decoration:none;">
                <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($whatsapp_number); ?>
            </a>
        </p>
        <?php endif; ?>
    </div>
</footer>
