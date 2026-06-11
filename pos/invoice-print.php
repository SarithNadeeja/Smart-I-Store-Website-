<?php

require_once __DIR__ . '/includes/init.php';

$user = pos_require_setup_complete();
$id = (int) ($_GET['id'] ?? 0);
$invoice = $id > 0 ? pos_get_invoice($id) : null;

if (!$invoice) {
    http_response_code(404);
    echo 'Invoice not found.';
    exit;
}

$paymentMethods = pos_payment_methods();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($invoice['invoice_no']); ?> | Print</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 24px; color: #111; font-size: 14px; }
        h1 { margin: 0 0 4px; font-size: 1.4rem; }
        .meta { margin-bottom: 20px; color: #444; }
        .meta p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { border: 1px solid #ccc; padding: 8px 10px; text-align: left; }
        th { background: #f5f5f5; }
        .totals { margin-left: auto; width: 280px; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .grand { font-weight: 700; font-size: 1.1rem; border-top: 2px solid #111; margin-top: 8px; padding-top: 8px; }
        .foot { margin-top: 32px; font-size: 12px; color: #666; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <p class="no-print"><button onclick="window.print()">Print</button></p>
    <h1><?php echo htmlspecialchars(SITE_NAME); ?></h1>
    <p><strong>Tax Invoice</strong> · <?php echo htmlspecialchars($invoice['invoice_no']); ?></p>
    <div class="meta">
        <p><strong>Date:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($invoice['created_at']))); ?></p>
        <p><strong>Customer:</strong> <?php echo htmlspecialchars($invoice['customer_name'] ?: 'Walk-in'); ?>
            <?php if (!empty($invoice['customer_phone'])): ?> · <?php echo htmlspecialchars($invoice['customer_phone']); ?><?php endif; ?>
        </p>
        <p><strong>Cashier:</strong> <?php echo htmlspecialchars($invoice['cashier_name'] ?? '—'); ?></p>
        <?php if ((int) $invoice['warranty_period_days'] > 0): ?>
        <p><strong>Warranty:</strong> <?php echo (int) $invoice['warranty_period_days']; ?> days until <?php echo htmlspecialchars($invoice['warranty_end_date'] ?? ''); ?></p>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Unit</th>
                <th>Qty</th>
                <th>Disc.</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoice['items'] as $i => $item): ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo htmlspecialchars($item['product_name_snapshot']); ?></td>
                <td><?php echo pos_format_money((float) $item['unit_price']); ?></td>
                <td><?php echo (int) $item['quantity']; ?></td>
                <td><?php echo pos_format_money((float) $item['discount']); ?></td>
                <td><?php echo pos_format_money((float) $item['line_total']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php if ((float) $invoice['discount'] > 0): ?>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align: right; font-weight: 600;">Invoice discount</td>
                <td style="font-weight: 600;">&minus; <?php echo pos_format_money((float) $invoice['discount']); ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <div class="totals">
        <div><span>Subtotal</span><span><?php echo pos_format_money((float) $invoice['subtotal']); ?></span></div>
        <div><span>Discount</span><span>&minus; <?php echo pos_format_money((float) $invoice['discount']); ?></span></div>
        <div class="grand"><span>Total</span><span><?php echo pos_format_money((float) $invoice['total']); ?></span></div>
        <div><span>Paid</span><span><?php echo pos_format_money((float) $invoice['paid_amount']); ?></span></div>
        <div><span>Balance</span><span><?php echo pos_format_money((float) $invoice['balance']); ?></span></div>
        <div><span>Payment</span><span><?php echo htmlspecialchars($paymentMethods[$invoice['payment_method']] ?? $invoice['payment_method']); ?></span></div>
    </div>

    <?php if ($invoice['notes'] !== ''): ?>
    <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
    <?php endif; ?>

    <div class="foot">Thank you for your business · <?php echo htmlspecialchars(SITE_NAME); ?></div>
</body>
</html>
