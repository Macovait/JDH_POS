<p>Inventory Alert</p>

<p>The following items are running low on stock:</p>

<table style="width:100%;margin:20px 0;border-collapse:collapse;">
    <tr style="background:#f9fafb;">
        <th style="padding:10px;text-align:left;border-bottom:1px solid #e5e7eb;">Product</th>
        <th style="padding:10px;text-align:center;border-bottom:1px solid #e5e7eb;">Current Stock</th>
        <th style="padding:10px;text-align:right;border-bottom:1px solid #e5e7eb;">Minimum</th>
    </tr>
    <?php foreach ($items ?? [] as $item): ?>
    <tr style="<?php echo $item['quantity'] <= $item['minimum'] * 0.5 ? 'background:#FEF2F2;' : ''; ?>">
        <td style="padding:10px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars($item['name']); ?></td>
        <td style="padding:10px;text-align:center;border-bottom:1px solid #e5e7eb;">
            <span style="<?php echo $item['quantity'] <= $item['minimum'] ? 'color:#DC2626;font-weight:600;' : ''; ?>">
                <?php echo $item['quantity']; ?>
            </span>
        </td>
        <td style="padding:10px;text-align:right;border-bottom:1px solid #e5e7eb;"><?php echo $item['minimum']; ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<p style="font-size:14px;color:#6b7280;margin-top:20px;">Please restock these items soon to avoid unavailable sales.</p>