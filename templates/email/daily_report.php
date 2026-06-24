<p>Daily Sales Report - <?php echo date('d M Y', strtotime($date ?? 'today')); ?></p>

<table style="width:100%;margin:20px 0;border-collapse:collapse;">
    <tr style="background:#f9fafb;">
        <th style="padding:10px;text-align:left;border-bottom:1px solid #e5e7eb;">Metric</th>
        <th style="padding:10px;text-align:right;border-bottom:1px solid #e5e7eb;">Value</th>
    </tr>
    <tr>
        <td style="padding:10px;border-bottom:1px solid #e5e7eb;">Total Sales</td>
        <td style="padding:10px;text-align:right;border-bottom:1px solid #e5e7eb;font-weight:600;color:#10B981;">
            <?php echo format_currency($total_sales ?? 0); ?>
        </td>
    </tr>
    <tr>
        <td style="padding:10px;border-bottom:1px solid #e5e7eb;">Transactions</td>
        <td style="padding:10px;text-align:right;border-bottom:1px solid #e5e7eb;">
            <?php echo number_format($transactions ?? 0); ?>
        </td>
    </tr>
    <tr>
        <td style="padding:10px;border-bottom:1px solid #e5e7eb;">Items Sold</td>
        <td style="padding:10px;text-align:right;border-bottom:1px solid #e5e7eb;">
            <?php echo number_format($items_sold ?? 0); ?>
        </td>
    </tr>
    <tr>
        <td style="padding:10px;border-bottom:1px solid #e5e7eb;">Average Transaction</td>
        <td style="padding:10px;text-align:right;border-bottom:1px solid #e5e7eb;">
            <?php echo format_currency($average_transaction ?? 0); ?>
        </td>
    </tr>
    <tr>
        <td style="padding:10px;border-bottom:1px solid #e5e7eb;">New Customers</td>
        <td style="padding:10px;text-align:right;border-bottom:1px solid #e5e7eb;">
            <?php echo number_format($new_customers ?? 0); ?>
        </td>
    </tr>
</table>

<?php if (!empty($top_items)): ?>
<h3 style="margin:20px 0 10px;">Top Sellers</h3>
<ol style="padding-left:20px;">
    <?php foreach ($top_items as $item): ?>
    <li style="padding:5px 0;"><?php echo htmlspecialchars($item['name']); ?> - <?php echo format_currency($item['total']); ?></li>
    <?php endforeach; ?>
</ol>
<?php endif; ?>

<p style="font-size:14px;color:#6b7280;margin-top:20px;">
    Branch: <?php echo htmlspecialchars($branch ?? 'All'); ?>
</p>