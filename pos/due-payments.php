<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$user = pos_require_setup_complete();
$paymentMethods = pos_payment_methods();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pos_csrf_verify();
        $action = $_POST['action'] ?? '';

        if ($action === 'invoice_payment') {
            pos_add_invoice_payment(
                (int) ($_POST['invoice_id'] ?? 0),
                (float) ($_POST['amount'] ?? 0),
                (string) ($_POST['payment_method'] ?? 'cash'),
                trim($_POST['note'] ?? ''),
                (int) $user['id']
            );
            pos_flash('success', 'Invoice payment recorded.');
        } elseif ($action === 'repair_payment') {
            pos_add_repair_payment(
                (int) ($_POST['repair_id'] ?? 0),
                (float) ($_POST['amount'] ?? 0),
                (string) ($_POST['payment_method'] ?? 'cash'),
                trim($_POST['note'] ?? ''),
                (int) $user['id']
            );
            pos_flash('success', 'Repair payment recorded.');
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        pos_flash('error', $e->getMessage());
    }
    header('Location: ' . pos_panel_url('due-payments.php'));
    exit;
}

$dueInvoices = db()->query(
    "SELECT i.id, i.invoice_no, i.total, i.balance, i.payment_status, c.name AS customer_name, c.phone AS customer_phone
     FROM pos_invoices i
     LEFT JOIN pos_customers c ON c.id = i.customer_id
     WHERE i.status = 'completed' AND i.balance > 0
     ORDER BY i.created_at ASC"
)->fetchAll();

$repairRows = db()->query(
    "SELECT r.id, r.job_no, r.estimated_cost, r.final_cost, r.status, r.payment_status,
            c.name AS customer_name, c.phone AS customer_phone
     FROM pos_repair_jobs r
     JOIN pos_customers c ON c.id = r.customer_id
     WHERE r.status NOT IN ('Cancelled', 'Delivered')
     ORDER BY r.created_at ASC"
)->fetchAll();

$dueRepairs = [];
foreach ($repairRows as $row) {
    $job = pos_get_repair_job((int) $row['id']);
    if (!$job) {
        continue;
    }
    $balance = pos_repair_balance_due($job);
    if ($balance > 0) {
        $row['balance'] = $balance;
        $dueRepairs[] = $row;
    }
}

pos_render_header('Due Payments', 'due');
?>
<section class="admin-panel">
    <h2>Invoices with balance due (<?php echo count($dueInvoices); ?>)</h2>
    <?php if (!$dueInvoices): ?>
    <p class="admin-empty">No invoice balances due.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Balance</th><th>Payment</th></tr></thead>
            <tbody>
                <?php foreach ($dueInvoices as $inv): ?>
                <tr>
                    <td><a href="<?php echo pos_panel_url('invoice-view.php?id=' . (int) $inv['id']); ?>"><?php echo htmlspecialchars($inv['invoice_no']); ?></a></td>
                    <td><?php echo htmlspecialchars($inv['customer_name'] ?: 'Walk-in'); ?></td>
                    <td><?php echo pos_format_money((float) $inv['total']); ?></td>
                    <td><?php echo pos_format_money((float) $inv['balance']); ?></td>
                    <td>
                        <form method="post" class="admin-form admin-form--compact">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
                            <input type="hidden" name="action" value="invoice_payment">
                            <input type="hidden" name="invoice_id" value="<?php echo (int) $inv['id']; ?>">
                            <input type="number" name="amount" min="0.01" max="<?php echo (float) $inv['balance']; ?>" step="0.01" value="<?php echo (float) $inv['balance']; ?>" class="pos-input-sm" required>
                            <select name="payment_method" class="pos-input-md">
                                <?php foreach ($paymentMethods as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Pay</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="admin-panel">
    <h2>Repairs with balance due (<?php echo count($dueRepairs); ?>)</h2>
    <?php if (!$dueRepairs): ?>
    <p class="admin-empty">No repair balances due.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Job</th><th>Customer</th><th>Status</th><th>Balance</th><th>Payment</th></tr></thead>
            <tbody>
                <?php foreach ($dueRepairs as $job): ?>
                <tr>
                    <td><a href="<?php echo pos_panel_url('repair-view.php?id=' . (int) $job['id']); ?>"><?php echo htmlspecialchars($job['job_no']); ?></a></td>
                    <td><?php echo htmlspecialchars($job['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($job['status']); ?></td>
                    <td><?php echo pos_format_money((float) $job['balance']); ?></td>
                    <td>
                        <form method="post" class="admin-form admin-form--compact">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
                            <input type="hidden" name="action" value="repair_payment">
                            <input type="hidden" name="repair_id" value="<?php echo (int) $job['id']; ?>">
                            <input type="number" name="amount" min="0.01" max="<?php echo (float) $job['balance']; ?>" step="0.01" value="<?php echo (float) $job['balance']; ?>" class="pos-input-sm" required>
                            <select name="payment_method" class="pos-input-md">
                                <?php foreach ($paymentMethods as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Pay</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php
pos_render_footer();
