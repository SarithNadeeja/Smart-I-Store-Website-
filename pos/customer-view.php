<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

pos_require_setup_complete();

$id = (int) ($_GET['id'] ?? 0);
$customer = $id > 0 ? pos_get_customer($id) : null;

if (!$customer) {
    pos_flash('error', 'Customer not found.');
    header('Location: ' . pos_panel_url('customers.php'));
    exit;
}

$invoiceStmt = db()->prepare(
    'SELECT id, invoice_no, total, paid_amount, balance, payment_status, status, created_at
     FROM pos_invoices WHERE customer_id = :id ORDER BY created_at DESC LIMIT 50'
);
$invoiceStmt->execute(['id' => $id]);
$sales = $invoiceStmt->fetchAll();

$repairStmt = db()->prepare(
    'SELECT id, job_no, device_brand, device_model, status, final_cost, estimated_cost,
            payment_status, created_at
     FROM pos_repair_jobs WHERE customer_id = :id ORDER BY created_at DESC LIMIT 50'
);
$repairStmt->execute(['id' => $id]);
$repairs = $repairStmt->fetchAll();

pos_render_header($customer['name'], 'customers');
?>
<section class="admin-panel">
    <p><a href="<?php echo pos_panel_url('customers.php'); ?>" class="admin-link">← All customers</a></p>
    <h2><?php echo htmlspecialchars($customer['name']); ?></h2>
    <div class="admin-grid-2">
        <div>
            <?php if ($customer['phone'] !== ''): ?><p><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone']); ?></p><?php endif; ?>
            <?php if ($customer['email'] !== ''): ?><p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p><?php endif; ?>
            <?php if ($customer['address'] !== ''): ?><p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($customer['address'])); ?></p><?php endif; ?>
            <?php if ($customer['notes'] !== ''): ?><p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($customer['notes'])); ?></p><?php endif; ?>
        </div>
        <div>
            <a href="<?php echo pos_panel_url('sale-new.php'); ?>" class="btn btn-primary btn-sm">New sale</a>
            <a href="<?php echo pos_panel_url('repair-new.php?customer_id=' . $id); ?>" class="btn btn-ghost btn-sm">New repair</a>
        </div>
    </div>
</section>

<section class="admin-panel">
    <h2>Sales history</h2>
    <?php if (!$sales): ?>
    <p class="admin-empty">No invoices for this customer.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Invoice</th><th>Total</th><th>Balance</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($sales as $inv): ?>
                <tr>
                    <td><?php echo htmlspecialchars($inv['invoice_no']); ?></td>
                    <td><?php echo pos_format_money((float) $inv['total']); ?></td>
                    <td><?php echo pos_format_money((float) $inv['balance']); ?></td>
                    <td><?php echo htmlspecialchars($inv['payment_status']); ?></td>
                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($inv['created_at']))); ?></td>
                    <td><a href="<?php echo pos_panel_url('invoice-view.php?id=' . (int) $inv['id']); ?>">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="admin-panel">
    <h2>Repair history</h2>
    <?php if (!$repairs): ?>
    <p class="admin-empty">No repair jobs for this customer.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Job</th><th>Device</th><th>Status</th><th>Cost</th><th>Date</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($repairs as $job): ?>
                <tr>
                    <td><?php echo htmlspecialchars($job['job_no']); ?></td>
                    <td><?php echo htmlspecialchars(trim($job['device_brand'] . ' ' . $job['device_model'])); ?></td>
                    <td><?php echo htmlspecialchars($job['status']); ?></td>
                    <td><?php echo pos_format_money((float) ($job['final_cost'] > 0 ? $job['final_cost'] : $job['estimated_cost'])); ?></td>
                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($job['created_at']))); ?></td>
                    <td><a href="<?php echo pos_panel_url('repair-view.php?id=' . (int) $job['id']); ?>">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php
pos_render_footer();
