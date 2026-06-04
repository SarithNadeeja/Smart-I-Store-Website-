<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

pos_require_setup_complete();

$q = trim($_GET['q'] ?? '');
$params = [];
$sql = "
    SELECT i.id, i.invoice_no, i.total, i.paid_amount, i.balance, i.payment_status,
           i.status, i.created_at, c.name AS customer_name
    FROM pos_invoices i
    LEFT JOIN pos_customers c ON c.id = i.customer_id
    WHERE 1=1
";
if ($q !== '') {
    $sql .= ' AND (i.invoice_no ILIKE :q OR c.name ILIKE :q OR c.phone ILIKE :q)';
    $params['q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY i.created_at DESC LIMIT 200';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$hasFilters = $q !== '';
$invoiceCount = count($invoices);

pos_render_header('Invoices', 'invoices');
?>
<section class="admin-panel">
    <div class="admin-panel-head">
        <h2>Invoices</h2>
        <a href="<?php echo pos_panel_url('sale-new.php'); ?>" class="btn btn-primary">+ New sale</a>
    </div>

    <form method="get" class="admin-filters pos-list-filters">
        <div class="admin-filters__row admin-filters__row--single">
            <div class="admin-field admin-field--search">
                <label for="q">Search</label>
                <input type="search" id="q" name="q" value="<?php echo htmlspecialchars($q); ?>"
                       placeholder="Invoice no., customer name or phone" autocomplete="off">
            </div>
        </div>
        <div class="admin-filters__row admin-filters__row--actions">
            <div class="admin-filters__buttons">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($hasFilters): ?>
                <a href="<?php echo pos_panel_url('invoices.php'); ?>" class="btn btn-ghost">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <p class="admin-results-meta">
        <?php if ($hasFilters): ?>
        <?php echo (int) $invoiceCount; ?> result<?php echo $invoiceCount === 1 ? '' : 's'; ?> for <strong><?php echo htmlspecialchars($q); ?></strong>
        <?php else: ?>
        <?php echo (int) $invoiceCount; ?> invoice<?php echo $invoiceCount === 1 ? '' : 's'; ?> (newest first)
        <?php endif; ?>
    </p>
    <?php if (!$invoices): ?>
    <p class="admin-empty">No invoices found.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr<?php echo $inv['status'] === 'cancelled' ? ' class="is-inactive"' : ''; ?>>
                    <td><strong><?php echo htmlspecialchars($inv['invoice_no']); ?></strong></td>
                    <td><?php echo htmlspecialchars($inv['customer_name'] ?: 'Walk-in'); ?></td>
                    <td><?php echo pos_format_money((float) $inv['total']); ?></td>
                    <td><?php echo pos_format_money((float) $inv['paid_amount']); ?></td>
                    <td><?php echo pos_format_money((float) $inv['balance']); ?></td>
                    <td><?php echo htmlspecialchars($inv['payment_status']); ?><?php echo $inv['status'] === 'cancelled' ? ' · cancelled' : ''; ?></td>
                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($inv['created_at']))); ?></td>
                    <td><a href="<?php echo pos_panel_url('invoice-view.php?id=' . (int) $inv['id']); ?>" class="admin-link-btn">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php
pos_render_footer();
