<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

pos_require_setup_complete();

$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$params = [];
$sql = "
    SELECT r.*, c.name AS customer_name, c.phone AS customer_phone,
           COALESCE((SELECT SUM(p.amount) FROM pos_repair_payments p WHERE p.repair_job_id = r.id), 0) AS total_paid
    FROM pos_repair_jobs r
    JOIN pos_customers c ON c.id = r.customer_id
    WHERE 1=1
";
if ($status !== '' && in_array($status, pos_repair_statuses(), true)) {
    $sql .= ' AND r.status = :st';
    $params['st'] = $status;
}
if ($q !== '') {
    $sql .= ' AND (r.job_no ILIKE :q OR c.name ILIKE :q OR c.phone ILIKE :q OR r.imei_serial ILIKE :q)';
    $params['q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY r.created_at DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();
$statuses = pos_repair_statuses();
$hasFilters = $status !== '' || $q !== '';
$jobCount = count($jobs);

pos_render_header('Repairs', 'repairs');
?>
<section class="admin-panel">
    <div class="admin-panel-head">
        <h2>Repair jobs</h2>
        <a href="<?php echo pos_panel_url('repair-new.php'); ?>" class="btn btn-primary">+ New repair</a>
    </div>

    <form method="get" class="admin-filters pos-list-filters">
        <div class="admin-filters__row admin-filters__row--compact">
            <div class="admin-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statuses as $st): ?>
                    <option value="<?php echo htmlspecialchars($st); ?>"<?php echo $status === $st ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($st); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-field admin-field--search">
                <label for="q">Search</label>
                <input type="search" id="q" name="q" value="<?php echo htmlspecialchars($q); ?>"
                       placeholder="Job no., customer, phone, IMEI" autocomplete="off">
            </div>
        </div>
        <div class="admin-filters__row admin-filters__row--actions">
            <div class="admin-filters__buttons">
                <button type="submit" class="btn btn-primary">Apply filters</button>
                <?php if ($hasFilters): ?>
                <a href="<?php echo pos_panel_url('repairs.php'); ?>" class="btn btn-ghost">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <p class="admin-results-meta"><?php echo (int) $jobCount; ?> job<?php echo $jobCount === 1 ? '' : 's'; ?></p>

    <?php if (!$jobs): ?>
    <p class="admin-empty">No repair jobs found.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Job no.</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Device</th>
                    <th>Status</th>
                    <th>Final cost</th>
                    <th>Balance</th>
                    <th>Profit</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job): ?>
                <?php
                $final = (float) $job['final_cost'];
                $paid = (float) $job['total_paid'];
                $balance = $final > 0 ? max(0, round($final - $paid, 2)) : 0;
                $badgeKey = strtolower(preg_replace('/[^a-z]/', '', $job['status']));
                if (!in_array($badgeKey, ['received', 'completed', 'delivered', 'cancelled'], true)) {
                    $badgeKey = 'received';
                }
                $profit = (float) ($job['repair_profit'] ?? 0);
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($job['job_no']); ?></strong></td>
                    <td><?php echo htmlspecialchars($job['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($job['customer_phone'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars(trim($job['device_brand'] . ' ' . $job['device_model']) ?: '—'); ?></td>
                    <td>
                        <span class="pos-repair-list-badge pos-repair-list-badge--<?php echo htmlspecialchars($badgeKey); ?>">
                            <?php echo htmlspecialchars($job['status']); ?>
                        </span>
                    </td>
                    <td><?php echo $final > 0 ? pos_format_money($final) : '—'; ?></td>
                    <td><?php echo $final > 0 ? pos_format_money($balance) : '—'; ?></td>
                    <td><?php echo $final > 0 ? pos_format_money($profit) : '—'; ?></td>
                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($job['created_at']))); ?></td>
                    <td><a href="<?php echo pos_panel_url('repair-view.php?id=' . (int) $job['id']); ?>" class="admin-link-btn">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php
pos_render_footer();
