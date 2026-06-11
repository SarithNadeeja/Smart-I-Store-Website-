<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

$user = pos_require_setup_complete();
$id = (int) ($_GET['id'] ?? 0);
$isManager = pos_is_manager($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pos_csrf_verify();
        $action = $_POST['action'] ?? '';

        if ($action === 'payment') {
            pos_add_invoice_payment(
                (int) ($_POST['invoice_id'] ?? 0),
                (float) ($_POST['amount'] ?? 0),
                (string) ($_POST['payment_method'] ?? 'cash'),
                trim($_POST['note'] ?? ''),
                (int) $user['id']
            );
            pos_flash('success', 'Payment recorded.');
        } elseif ($action === 'return') {
            pos_create_return(
                (int) ($_POST['invoice_item_id'] ?? 0),
                max(1, (int) ($_POST['quantity'] ?? 1)),
                trim($_POST['reason'] ?? ''),
                (int) $user['id']
            );
            pos_flash('success', 'Return recorded.');
        } elseif ($action === 'cancel') {
            pos_cancel_invoice((int) ($_POST['invoice_id'] ?? 0), (int) $user['id'], $isManager);
            pos_flash('success', 'Invoice cancelled.');
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        pos_flash('error', $e->getMessage());
    }
    header('Location: ' . pos_panel_url('invoice-view.php?id=' . $id));
    exit;
}

$invoice = $id > 0 ? pos_get_invoice($id) : null;
if (!$invoice) {
    pos_flash('error', 'Invoice not found.');
    header('Location: ' . pos_panel_url('invoices.php'));
    exit;
}

$paymentMethods = pos_payment_methods();
$cancelled = $invoice['status'] === 'cancelled';
$hasBalance = (float) $invoice['balance'] > 0 && !$cancelled;

// Spread the invoice-level discount across the lines (proportionally to their
// totals) so the Discount column shows the full discount the customer received
// and the line totals add up to the invoice total.
$discountShares = pos_allocate_invoice_discount($invoice);

// Gross subtotal (before any discount) and combined discount, so the summary
// matches the discounted line totals: subtotal - discount = total.
$grossSubtotal = 0.0;
$combinedDiscount = (float) $invoice['discount'];
foreach ($invoice['items'] as $item) {
    $grossSubtotal += (float) $item['unit_price'] * (int) $item['quantity'];
    $combinedDiscount += (float) $item['discount'];
}

pos_render_header('Invoice ' . $invoice['invoice_no'], 'invoices');
?>
<div class="admin-panel">
    <div class="admin-toolbar">
        <a href="<?php echo pos_panel_url('invoice-print.php?id=' . (int) $invoice['id']); ?>" class="btn btn-primary" target="_blank">Print</a>
        <a href="<?php echo pos_panel_url('invoices.php'); ?>" class="btn btn-ghost">Back to list</a>
        <?php if (!$cancelled && $isManager): ?>
        <form method="post" class="admin-inline-form" onsubmit="return confirm('Cancel this invoice and restock items?');">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="invoice_id" value="<?php echo (int) $invoice['id']; ?>">
            <button type="submit" class="btn btn-ghost admin-link-btn--danger">Cancel invoice</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="admin-grid-2">
        <div>
            <p><strong>Invoice:</strong> <?php echo htmlspecialchars($invoice['invoice_no']); ?></p>
            <p><strong>Date:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($invoice['created_at']))); ?></p>
            <p><strong>Customer:</strong> <?php echo htmlspecialchars($invoice['customer_name'] ?: 'Walk-in'); ?>
                <?php if (!empty($invoice['customer_phone'])): ?>
                · <?php echo htmlspecialchars($invoice['customer_phone']); ?>
                <?php endif; ?>
            </p>
            <p><strong>Cashier:</strong> <?php echo htmlspecialchars($invoice['cashier_name'] ?? '—'); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($invoice['status']); ?> · <?php echo htmlspecialchars($invoice['payment_status']); ?></p>
            <?php if ((int) $invoice['warranty_period_days'] > 0): ?>
            <p><strong>Warranty:</strong> <?php echo (int) $invoice['warranty_period_days']; ?> days
                (until <?php echo htmlspecialchars($invoice['warranty_end_date'] ?? '—'); ?>)</p>
            <?php endif; ?>
            <?php if ($invoice['notes'] !== ''): ?>
            <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
            <?php endif; ?>
        </div>
        <div class="pos-sale-totals admin-panel">
            <div><span>Subtotal</span><strong><?php echo pos_format_money($grossSubtotal); ?></strong></div>
            <div><span>Discount</span><strong>&minus; <?php echo pos_format_money($combinedDiscount); ?></strong></div>
            <div class="pos-sale-totals__grand"><span>Total</span><strong><?php echo pos_format_money((float) $invoice['total']); ?></strong></div>
            <div><span>Paid</span><strong><?php echo pos_format_money((float) $invoice['paid_amount']); ?></strong></div>
            <div><span>Balance</span><strong><?php echo pos_format_money((float) $invoice['balance']); ?></strong></div>
        </div>
    </div>
</div>

<section class="admin-panel">
    <h2>Line items</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Unit price</th>
                    <th>Qty</th>
                    <th>Returned</th>
                    <th>Discount</th>
                    <th>Line total</th>
                    <?php if (!$cancelled): ?><th>Return</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoice['items'] as $item): ?>
                <?php
                $maxReturn = (int) $item['quantity'] - (int) $item['returned_quantity'];
                $discountShare = $discountShares[(int) $item['id']] ?? 0.0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name_snapshot']); ?></td>
                    <td><?php echo pos_format_money((float) $item['unit_price']); ?></td>
                    <td><?php echo (int) $item['quantity']; ?></td>
                    <td><?php echo (int) $item['returned_quantity']; ?></td>
                    <td><?php echo pos_format_money((float) $item['discount'] + $discountShare); ?></td>
                    <td><?php echo pos_format_money((float) $item['line_total'] - $discountShare); ?></td>
                    <?php if (!$cancelled): ?>
                    <td>
                        <?php if ($maxReturn > 0): ?>
                        <form method="post" class="admin-form admin-form--compact">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
                            <input type="hidden" name="action" value="return">
                            <input type="hidden" name="invoice_item_id" value="<?php echo (int) $item['id']; ?>">
                            <input type="number" name="quantity" min="1" max="<?php echo $maxReturn; ?>" value="1" class="pos-input-sm">
                            <input type="text" name="reason" placeholder="Reason" required class="pos-input-md">
                            <button type="submit" class="btn btn-ghost btn-sm">Return</button>
                        </form>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($invoice['payments']): ?>
<section class="admin-panel">
    <h2>Payments</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Note</th><th>By</th></tr></thead>
            <tbody>
                <?php foreach ($invoice['payments'] as $pay): ?>
                <tr>
                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($pay['created_at']))); ?></td>
                    <td><?php echo pos_format_money((float) $pay['amount']); ?></td>
                    <td><?php echo htmlspecialchars($paymentMethods[$pay['payment_method']] ?? $pay['payment_method']); ?></td>
                    <td><?php echo htmlspecialchars($pay['note'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($pay['staff_name'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($hasBalance): ?>
<section class="admin-panel">
    <h2>Add payment</h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
        <input type="hidden" name="action" value="payment">
        <input type="hidden" name="invoice_id" value="<?php echo (int) $invoice['id']; ?>">
        <div class="admin-field-row">
            <div class="admin-field">
                <label for="amount">Amount (max <?php echo pos_format_money((float) $invoice['balance']); ?>)</label>
                <input type="number" id="amount" name="amount" min="0.01" max="<?php echo (float) $invoice['balance']; ?>" step="0.01" value="<?php echo (float) $invoice['balance']; ?>" required>
            </div>
            <div class="admin-field">
                <label for="payment_method">Method</label>
                <select id="payment_method" name="payment_method">
                    <?php foreach ($paymentMethods as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="admin-field">
            <label for="note">Note</label>
            <input type="text" id="note" name="note">
        </div>
        <button type="submit" class="btn btn-primary">Record payment</button>
    </form>
</section>
<?php endif; ?>
<?php
pos_render_footer();
