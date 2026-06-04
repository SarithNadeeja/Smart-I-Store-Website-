<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

pos_require_setup_complete();

$preset = $_GET['preset'] ?? 'today';
$tab = $_GET['tab'] ?? 'sales';
$validTabs = ['sales', 'repairs', 'expenses', 'profit', 'due', 'returns', 'warranty'];
$warrantyQ = trim($_GET['warranty_q'] ?? '');
$warrantyResults = $warrantyQ !== '' ? pos_search_warranty($warrantyQ) : ['invoices' => [], 'repairs' => []];
if (!in_array($tab, $validTabs, true)) {
    $tab = 'sales';
}

[$dateFrom, $dateTo] = pos_date_range($preset);
if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
    $dateFrom = trim($_GET['date_from']);
    $dateTo = trim($_GET['date_to']);
}

$params = ['from' => $dateFrom, 'to' => $dateTo];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $report = $_GET['report'] ?? 'sales';
    if ($report === 'sales') {
        $rows = db()->prepare(
            "SELECT i.invoice_no, i.created_at, c.name AS customer, i.subtotal, i.discount, i.total,
                    i.paid_amount, i.balance, i.payment_method, i.payment_status, i.status
             FROM pos_invoices i
             LEFT JOIN pos_customers c ON c.id = i.customer_id
             WHERE DATE(i.created_at) BETWEEN :from AND :to
             ORDER BY i.created_at ASC"
        );
        $rows->execute($params);
        $data = [];
        foreach ($rows->fetchAll() as $r) {
            $data[] = [
                $r['invoice_no'],
                $r['created_at'],
                $r['customer'] ?: 'Walk-in',
                $r['subtotal'],
                $r['discount'],
                $r['total'],
                $r['paid_amount'],
                $r['balance'],
                $r['payment_method'],
                $r['payment_status'],
                $r['status'],
            ];
        }
        pos_export_csv('sales-' . $dateFrom . '-to-' . $dateTo . '.csv', [
            'Invoice', 'Date', 'Customer', 'Subtotal', 'Discount', 'Total', 'Paid', 'Balance', 'Method', 'Pay status', 'Status',
        ], $data);
    } elseif ($report === 'expenses') {
        $rows = db()->prepare(
            'SELECT e.expense_date, e.category, e.description, e.amount, s.name AS staff
             FROM pos_expenses e LEFT JOIN pos_staff s ON s.id = e.created_by
             WHERE e.expense_date BETWEEN :from AND :to ORDER BY e.expense_date ASC'
        );
        $rows->execute($params);
        $data = [];
        foreach ($rows->fetchAll() as $r) {
            $data[] = [$r['expense_date'], $r['category'], $r['description'], $r['amount'], $r['staff'] ?: ''];
        }
        pos_export_csv('expenses-' . $dateFrom . '-to-' . $dateTo . '.csv', ['Date', 'Category', 'Description', 'Amount', 'Staff'], $data);
    } elseif ($report === 'returns') {
        $rows = db()->prepare(
            "SELECT r.return_no, r.created_at, i.invoice_no, r.quantity, r.reason, r.refund_amount, s.name AS staff
             FROM pos_returns r
             JOIN pos_invoices i ON i.id = r.invoice_id
             LEFT JOIN pos_staff s ON s.id = r.created_by
             WHERE DATE(r.created_at) BETWEEN :from AND :to ORDER BY r.created_at ASC"
        );
        $rows->execute($params);
        $data = [];
        foreach ($rows->fetchAll() as $r) {
            $data[] = [$r['return_no'], $r['created_at'], $r['invoice_no'], $r['quantity'], $r['reason'], $r['refund_amount'], $r['staff'] ?: ''];
        }
        pos_export_csv('returns-' . $dateFrom . '-to-' . $dateTo . '.csv', ['Return', 'Date', 'Invoice', 'Qty', 'Reason', 'Refund', 'Staff'], $data);
    } elseif ($report === 'repairs') {
        $rows = db()->prepare(
            "SELECT r.job_no, c.name AS customer, TRIM(CONCAT(r.device_brand, ' ', r.device_model)) AS device,
                    r.final_cost, r.parts_cost, r.repair_profit, r.status,
                    DATE(COALESCE(r.delivered_at, r.completed_at, r.created_at)) AS job_date
             FROM pos_repair_jobs r
             JOIN pos_customers c ON c.id = r.customer_id
             WHERE r.status IN ('Completed', 'Delivered') AND r.final_cost > 0
               AND DATE(COALESCE(r.delivered_at, r.completed_at, r.created_at)) BETWEEN :from AND :to
             ORDER BY r.created_at ASC"
        );
        $rows->execute($params);
        $data = [];
        foreach ($rows->fetchAll() as $r) {
            $data[] = [
                $r['job_no'],
                $r['customer'],
                $r['device'],
                $r['final_cost'],
                $r['parts_cost'],
                $r['repair_profit'],
                $r['status'],
                $r['job_date'],
            ];
        }
        pos_export_csv('repairs-' . $dateFrom . '-to-' . $dateTo . '.csv', [
            'Job', 'Customer', 'Device', 'Final cost', 'Repair expense', 'Profit', 'Status', 'Date',
        ], $data);
    }
}

$repairTotals = pos_repair_profit_totals($dateFrom, $dateTo);
$repairRows = db()->prepare(
    "SELECT r.id, r.job_no, c.name AS customer_name,
            TRIM(CONCAT(r.device_brand, ' ', r.device_model)) AS device,
            r.final_cost, r.parts_cost, r.repair_profit, r.status,
            COALESCE(r.delivered_at, r.completed_at, r.created_at) AS job_date
     FROM pos_repair_jobs r
     JOIN pos_customers c ON c.id = r.customer_id
     WHERE r.status IN ('Completed', 'Delivered') AND r.final_cost > 0
       AND DATE(COALESCE(r.delivered_at, r.completed_at, r.created_at)) BETWEEN :from AND :to
     ORDER BY r.created_at DESC LIMIT 200"
);
$repairRows->execute($params);
$repairList = $repairRows->fetchAll();

$salesStmt = db()->prepare(
    "SELECT COALESCE(SUM(total),0) FROM pos_invoices
     WHERE status = 'completed' AND DATE(created_at) BETWEEN :from AND :to"
);
$salesStmt->execute($params);
$salesTotal = (float) $salesStmt->fetchColumn();

$expenseStmt = db()->prepare(
    'SELECT COALESCE(SUM(amount),0) FROM pos_expenses WHERE expense_date BETWEEN :from AND :to'
);
$expenseStmt->execute($params);
$expenseTotal = (float) $expenseStmt->fetchColumn();

$costStmt = db()->prepare(
    "SELECT COALESCE(SUM((ii.cost_price_snapshot * ii.quantity) - ii.discount),0)
     FROM pos_invoice_items ii JOIN pos_invoices i ON i.id = ii.invoice_id
     WHERE i.status = 'completed' AND DATE(i.created_at) BETWEEN :from AND :to"
);
$costStmt->execute($params);
$productCost = (float) $costStmt->fetchColumn();

$repairStmt = db()->prepare(
    'SELECT COALESCE(SUM(amount),0) FROM pos_repair_payments WHERE DATE(created_at) BETWEEN :from AND :to'
);
$repairStmt->execute($params);
$repairIncome = (float) $repairStmt->fetchColumn();
$repairPeriodTotals = pos_repair_profit_totals($dateFrom, $dateTo);

$profitEstimate = $salesTotal - $productCost - $expenseTotal + $repairPeriodTotals['profit'];

$salesRows = db()->prepare(
    "SELECT i.id, i.invoice_no, i.created_at, c.name AS customer_name, i.total, i.payment_status, i.status
     FROM pos_invoices i LEFT JOIN pos_customers c ON c.id = i.customer_id
     WHERE DATE(i.created_at) BETWEEN :from AND :to ORDER BY i.created_at DESC LIMIT 100"
);
$salesRows->execute($params);
$salesList = $salesRows->fetchAll();

$expenseRows = db()->prepare(
    'SELECT e.*, s.name AS staff_name FROM pos_expenses e
     LEFT JOIN pos_staff s ON s.id = e.created_by
     WHERE e.expense_date BETWEEN :from AND :to ORDER BY e.expense_date DESC LIMIT 100'
);
$expenseRows->execute($params);
$expenseList = $expenseRows->fetchAll();

$dueInvoices = db()->query(
    "SELECT i.id, i.invoice_no, i.balance, c.name AS customer_name
     FROM pos_invoices i LEFT JOIN pos_customers c ON c.id = i.customer_id
     WHERE i.status = 'completed' AND i.balance > 0 ORDER BY i.balance DESC LIMIT 50"
)->fetchAll();

$returnRows = db()->prepare(
    "SELECT r.*, i.invoice_no, s.name AS staff_name
     FROM pos_returns r
     JOIN pos_invoices i ON i.id = r.invoice_id
     LEFT JOIN pos_staff s ON s.id = r.created_by
     WHERE DATE(r.created_at) BETWEEN :from AND :to ORDER BY r.created_at DESC LIMIT 100"
);
$returnRows->execute($params);
$returnsList = $returnRows->fetchAll();

$returnTotalStmt = db()->prepare(
    'SELECT COALESCE(SUM(refund_amount),0) FROM pos_returns WHERE DATE(created_at) BETWEEN :from AND :to'
);
$returnTotalStmt->execute($params);
$returnTotal = (float) $returnTotalStmt->fetchColumn();

$filterQuery = http_build_query(['date_from' => $dateFrom, 'date_to' => $dateTo]);

pos_render_header('Reports', 'reports');
?>
<section class="admin-panel">
    <form method="get" class="admin-form">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <div class="admin-sales-report__quick pos-action-grid" style="margin-bottom:16px;">
            <?php foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This week', 'month' => 'This month'] as $key => $label): ?>
            <a href="<?php echo pos_panel_url('reports.php?' . http_build_query(['preset' => $key, 'tab' => $tab])); ?>"
               class="btn btn-ghost<?php echo $preset === $key && empty($_GET['date_from']) ? ' btn-primary' : ''; ?>"><?php echo htmlspecialchars($label); ?></a>
            <?php endforeach; ?>
        </div>
        <div class="admin-field-row">
            <div class="admin-field">
                <label for="date_from">From</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            <div class="admin-field">
                <label for="date_to">To</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            <div class="admin-field admin-field--actions">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </div>
    </form>

    <div class="admin-tabs">
        <?php foreach ($validTabs as $t): ?>
        <a href="<?php echo pos_panel_url('reports.php?' . http_build_query(['preset' => $preset, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'tab' => $t])); ?>"
           class="admin-tabs__link<?php echo $tab === $t ? ' is-active' : ''; ?>"><?php echo htmlspecialchars(ucfirst($t)); ?></a>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($tab === 'sales'): ?>
<section class="admin-panel">
    <div class="admin-toolbar">
        <h2>Sales · <?php echo pos_format_money($salesTotal); ?></h2>
        <a href="<?php echo pos_panel_url('reports.php?' . $filterQuery . '&export=csv&report=sales'); ?>" class="btn btn-ghost btn-sm">Export CSV</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($salesList as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['invoice_no']); ?></td>
                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($row['created_at']))); ?></td>
                    <td><?php echo htmlspecialchars($row['customer_name'] ?: 'Walk-in'); ?></td>
                    <td><?php echo pos_format_money((float) $row['total']); ?></td>
                    <td><?php echo htmlspecialchars($row['payment_status']); ?><?php echo $row['status'] === 'cancelled' ? ' · cancelled' : ''; ?></td>
                    <td><a href="<?php echo pos_panel_url('invoice-view.php?id=' . (int) $row['id']); ?>">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php elseif ($tab === 'repairs'): ?>
<section class="admin-panel">
    <div class="admin-toolbar">
        <h2>Repairs · <?php echo pos_format_money($repairTotals['revenue']); ?> revenue</h2>
        <a href="<?php echo pos_panel_url('reports.php?' . $filterQuery . '&export=csv&report=repairs&tab=repairs'); ?>" class="btn btn-ghost btn-sm">Export CSV</a>
    </div>
    <div class="pos-stats admin-stats" style="margin-bottom:16px;">
        <div class="admin-stat-card pos-stat-card--income">
            <span class="admin-stat-card__value"><?php echo pos_format_money($repairTotals['revenue']); ?></span>
            <span class="admin-stat-card__label">Total revenue</span>
        </div>
        <div class="admin-stat-card pos-stat-card--expense">
            <span class="admin-stat-card__value"><?php echo pos_format_money($repairTotals['expense']); ?></span>
            <span class="admin-stat-card__label">Total expense</span>
        </div>
        <div class="admin-stat-card pos-stat-card--profit">
            <span class="admin-stat-card__value"><?php echo pos_format_money($repairTotals['profit']); ?></span>
            <span class="admin-stat-card__label">Total profit</span>
        </div>
    </div>
    <?php if (!$repairList): ?>
    <p class="admin-empty">No completed repair jobs in this period.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Job no.</th>
                    <th>Customer</th>
                    <th>Device</th>
                    <th>Final cost</th>
                    <th>Repair expense</th>
                    <th>Profit</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($repairList as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['job_no']); ?></td>
                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['device'] ?: '—'); ?></td>
                    <td><?php echo pos_format_money((float) $row['final_cost']); ?></td>
                    <td><?php echo pos_format_money((float) $row['parts_cost']); ?></td>
                    <td><?php echo pos_format_money((float) $row['repair_profit']); ?></td>
                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($row['job_date']))); ?></td>
                    <td><a href="<?php echo pos_panel_url('repair-view.php?id=' . (int) $row['id']); ?>">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>Totals</strong></td>
                    <td><strong><?php echo pos_format_money($repairTotals['revenue']); ?></strong></td>
                    <td><strong><?php echo pos_format_money($repairTotals['expense']); ?></strong></td>
                    <td><strong><?php echo pos_format_money($repairTotals['profit']); ?></strong></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php elseif ($tab === 'expenses'): ?>
<section class="admin-panel">
    <div class="admin-toolbar">
        <h2>Expenses · <?php echo pos_format_money($expenseTotal); ?></h2>
        <a href="<?php echo pos_panel_url('reports.php?' . $filterQuery . '&export=csv&report=expenses'); ?>" class="btn btn-ghost btn-sm">Export CSV</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>By</th></tr></thead>
            <tbody>
                <?php foreach ($expenseList as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['expense_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                    <td><?php echo pos_format_money((float) $row['amount']); ?></td>
                    <td><?php echo htmlspecialchars($row['staff_name'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php elseif ($tab === 'profit'): ?>
<section class="admin-panel">
    <h2>Profit estimate</h2>
    <div class="pos-stats admin-stats">
        <div class="admin-stat-card pos-stat-card--income">
            <span class="admin-stat-card__value"><?php echo pos_format_money($salesTotal); ?></span>
            <span class="admin-stat-card__label">Sales revenue</span>
        </div>
        <div class="admin-stat-card">
            <span class="admin-stat-card__value"><?php echo pos_format_money($productCost); ?></span>
            <span class="admin-stat-card__label">Product cost</span>
        </div>
        <div class="admin-stat-card pos-stat-card--expense">
            <span class="admin-stat-card__value"><?php echo pos_format_money($expenseTotal); ?></span>
            <span class="admin-stat-card__label">Expenses</span>
        </div>
        <div class="admin-stat-card">
            <span class="admin-stat-card__value"><?php echo pos_format_money($repairPeriodTotals['revenue']); ?></span>
            <span class="admin-stat-card__label">Repair revenue</span>
        </div>
        <div class="admin-stat-card pos-stat-card--expense">
            <span class="admin-stat-card__value"><?php echo pos_format_money($repairPeriodTotals['expense']); ?></span>
            <span class="admin-stat-card__label">Repair expenses</span>
        </div>
        <div class="admin-stat-card pos-stat-card--profit">
            <span class="admin-stat-card__value"><?php echo pos_format_money($repairPeriodTotals['profit']); ?></span>
            <span class="admin-stat-card__label">Repair profit</span>
        </div>
        <div class="admin-stat-card pos-stat-card--profit">
            <span class="admin-stat-card__value"><?php echo pos_format_money($profitEstimate); ?></span>
            <span class="admin-stat-card__label">Shop profit estimate</span>
        </div>
    </div>
    <p class="admin-hint">Shop estimate = sales − product cost − shop expenses + repair profit. See the Repairs tab for full repair breakdown.</p>
</section>

<?php elseif ($tab === 'due'): ?>
<section class="admin-panel">
    <h2>Outstanding balances (current)</h2>
    <?php if (!$dueInvoices): ?>
    <p class="admin-empty">No invoice balances due.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Invoice</th><th>Customer</th><th>Balance</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($dueInvoices as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['invoice_no']); ?></td>
                    <td><?php echo htmlspecialchars($row['customer_name'] ?: 'Walk-in'); ?></td>
                    <td><?php echo pos_format_money((float) $row['balance']); ?></td>
                    <td><a href="<?php echo pos_panel_url('invoice-view.php?id=' . (int) $row['id']); ?>">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <p class="admin-hint"><a href="<?php echo pos_panel_url('due-payments.php'); ?>">Manage due payments →</a></p>
</section>

<?php elseif ($tab === 'returns'): ?>
<section class="admin-panel">
    <div class="admin-toolbar">
        <h2>Returns · <?php echo pos_format_money($returnTotal); ?> refunded</h2>
        <a href="<?php echo pos_panel_url('reports.php?' . $filterQuery . '&export=csv&report=returns'); ?>" class="btn btn-ghost btn-sm">Export CSV</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Return</th><th>Date</th><th>Invoice</th><th>Qty</th><th>Refund</th><th>Reason</th></tr></thead>
            <tbody>
                <?php foreach ($returnsList as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['return_no']); ?></td>
                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($row['created_at']))); ?></td>
                    <td><?php echo htmlspecialchars($row['invoice_no']); ?></td>
                    <td><?php echo (int) $row['quantity']; ?></td>
                    <td><?php echo pos_format_money((float) $row['refund_amount']); ?></td>
                    <td><?php echo htmlspecialchars($row['reason']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php elseif ($tab === 'warranty'): ?>
<section class="admin-panel">
    <h2>Warranty lookup</h2>
    <form method="get" class="admin-form">
        <input type="hidden" name="tab" value="warranty">
        <div class="admin-field">
            <label for="warranty_q">Invoice / job / phone / IMEI</label>
            <input type="search" id="warranty_q" name="warranty_q" value="<?php echo htmlspecialchars($warrantyQ); ?>" placeholder="Search…">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
    <?php if ($warrantyQ !== ''): ?>
    <h3>Sales warranties</h3>
    <?php if (!$warrantyResults['invoices']): ?><p class="admin-empty">No matching invoices.</p><?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Invoice</th><th>Customer</th><th>Ends</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($warrantyResults['invoices'] as $w): ?>
            <?php $active = $w['warranty_end_date'] && $w['warranty_end_date'] >= date('Y-m-d'); ?>
            <tr>
                <td><?php echo htmlspecialchars($w['invoice_no']); ?></td>
                <td><?php echo htmlspecialchars($w['name'] . ($w['phone'] ? ' · ' . $w['phone'] : '')); ?></td>
                <td><?php echo htmlspecialchars($w['warranty_end_date'] ?? '—'); ?></td>
                <td><?php echo $active ? 'Active' : 'Expired'; ?></td>
                <td><a href="<?php echo pos_panel_url('invoice-view.php?id=' . (int) $w['id']); ?>">View</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <h3>Repair warranties</h3>
    <?php if (!$warrantyResults['repairs']): ?><p class="admin-empty">No matching repair jobs.</p><?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Job</th><th>Customer</th><th>Ends</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($warrantyResults['repairs'] as $w): ?>
            <?php $active = $w['warranty_end_date'] && $w['warranty_end_date'] >= date('Y-m-d'); ?>
            <tr>
                <td><?php echo htmlspecialchars($w['job_no']); ?></td>
                <td><?php echo htmlspecialchars($w['name'] . ($w['phone'] ? ' · ' . $w['phone'] : '')); ?></td>
                <td><?php echo htmlspecialchars($w['warranty_end_date'] ?? '—'); ?></td>
                <td><?php echo $active ? 'Active' : 'Expired'; ?></td>
                <td><a href="<?php echo pos_panel_url('repair-view.php?id=' . (int) $w['id']); ?>">View</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php
pos_render_footer();
