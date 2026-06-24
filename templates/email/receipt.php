<p>Thank you for your purchase!</p>

<table style="width:100%;margin:20px 0;border-collapse:collapse;">
    <tr>
        <td style="padding:8px 0;border-bottom:1px solid #e5e7eb;">
            <strong>Receipt #:</strong>
        </td>
        <td style="padding:8px 0;border-bottom:1px solid #e5e7eb;text-align:right;">
            <?php echo htmlspecialchars($receipt_number ?? 'N/A'); ?>
        </td>
    </tr>
    <tr>
        <td style="padding:8px 0;border-bottom:1px solid #e5e7eb;">
            <strong>Date:</strong>
        </td>
        <td style="padding:8px 0;border-bottom:1px solid #e5e7eb;text-align:right;">
            <?php echo htmlspecialchars($date ?? date('d M Y, g:i A')); ?>
        </td>
    </tr>
    <tr>
        <td style="padding:8px 0;border-bottom:1px solid #e5e7eb;">
            <strong>Branch:</strong>
        </td>
        <td style="padding:8px 0;border-bottom:1px solid #e5e7eb;text-align:right;">
            <?php echo htmlspecialchars($branch ?? ''); ?>
        </td>
    </tr>
</table>

<h3 style="margin:20px 0 10px;">Items</h3>
<table style="width:100%;border-collapse:collapse;">
    <tr style="background:#f9fafb;">
        <th style="padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;">Item</th>
        <th style="padding:8px;text-align:center;border-bottom:1px solid #e5e7eb;">Qty</th>
        <th style="padding:8px;text-align:right;border-bottom:1px solid #e5e7eb;">Price</th>
    </tr>
    <?php foreach ($items ?? [] as $item): ?>
    <tr>
        <td style="padding:8px;border-bottom:1px solid #e5e7eb;"><?php echo htmlspecialchars($item['name']); ?></td>
        <td style="padding:8px;text-align:center;border-bottom:1px solid #e5e7eb;"><?php echo $item['qty']; ?></td>
        <td style="padding:8px;text-align:right;border-bottom:1px solid #e5e7eb;"><?php echo format_currency($item['total']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<table style="width:100%;margin:20px 0;">
    <tr>
        <td style="padding:5px 0;"><strong>Subtotal:</strong></td>
        <td style="padding:5px 0;text-align:right;"><?php echo format_currency($subtotal ?? 0); ?></td>
    </tr>
    <tr>
        <td style="padding:5px 0;"><strong>Tax:</strong></td>
        <td style="padding:5px 0;text-align:right;"><?php echo format_currency($tax ?? 0); ?></td>
    </tr>
    <?php if (($discount ?? 0) > 0): ?>
    <tr>
        <td style="padding:5px 0;"><strong>Discount:</strong></td>
        <td style="padding:5px 0;text-align:right;">-<?php echo format_currency($discount); ?></td>
    </tr>
    <?php endif; ?>
    <tr style="font-size:18px;">
        <td style="padding:10px 0;"><strong>Total:</strong></td>
        <td style="padding:10px 0;text-align:right;font-weight:700;color:#10B981;"><?php echo format_currency($total ?? 0); ?></td>
    </tr>
</table>

<?php if (!empty($payment_method)): ?>
<p style="margin-top:20px;"><strong>Payment Method:</strong> <?php echo htmlspecialchars($payment_method); ?></p>
<?php endif; ?>

<p style="margin-top:30px;font-size:14px;color:#6b7280;">Please keep this receipt for your records.</p>