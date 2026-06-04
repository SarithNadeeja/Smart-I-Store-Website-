<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$stats = pos_dashboard_stats();

pos_render_header('Dashboard', 'dashboard');
?>
<div class="pos-stats admin-stats">
    <div class="admin-stat-card pos-stat-card--income">
        <span class="admin-stat-card__value"><?php echo pos_format_money($stats['today_sales']); ?></span>
        <span class="admin-stat-card__label">Today sales</span>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-card__value"><?php echo pos_format_money($stats['today_repair_revenue']); ?></span>
        <span class="admin-stat-card__label">Today repair revenue</span>
    </div>
    <div class="admin-stat-card pos-stat-card--expense">
        <span class="admin-stat-card__value"><?php echo pos_format_money($stats['today_repair_expense']); ?></span>
        <span class="admin-stat-card__label">Today repair expenses</span>
    </div>
    <div class="admin-stat-card pos-stat-card--profit">
        <span class="admin-stat-card__value"><?php echo pos_format_money($stats['today_repair_profit']); ?></span>
        <span class="admin-stat-card__label">Today repair profit</span>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-card__value"><?php echo (int) $stats['pending_repairs']; ?></span>
        <span class="admin-stat-card__label">Pending repairs</span>
    </div>
</div>

<section class="admin-panel">
    <h2>Repair profit · this month</h2>
    <div class="pos-stats admin-stats">
        <div class="admin-stat-card">
            <span class="admin-stat-card__value"><?php echo pos_format_money($stats['month_repair_revenue']); ?></span>
            <span class="admin-stat-card__label">Monthly repair revenue</span>
        </div>
        <div class="admin-stat-card pos-stat-card--expense">
            <span class="admin-stat-card__value"><?php echo pos_format_money($stats['month_repair_expense']); ?></span>
            <span class="admin-stat-card__label">Monthly repair expenses</span>
        </div>
        <div class="admin-stat-card pos-stat-card--profit">
            <span class="admin-stat-card__value"><?php echo pos_format_money($stats['month_repair_profit']); ?></span>
            <span class="admin-stat-card__label">Monthly repair profit</span>
        </div>
    </div>
</section>

<div class="pos-quick-actions admin-panel">
    <h2>Quick actions</h2>
    <div class="pos-action-grid">
        <a href="<?php echo pos_panel_url('sale-new.php'); ?>" class="pos-action-tile pos-action-tile--primary">New sale</a>
        <a href="<?php echo pos_panel_url('repair-new.php'); ?>" class="pos-action-tile">New repair</a>
        <a href="<?php echo pos_panel_url('customers.php'); ?>" class="pos-action-tile">Customers</a>
        <a href="<?php echo pos_panel_url('due-payments.php'); ?>" class="pos-action-tile">Due payments</a>
    </div>
</div>

<div class="admin-grid-2">
    <section class="admin-panel">
        <h2>Low stock</h2>
        <?php if (!$stats['low_stock']): ?>
        <p class="admin-empty">No low stock items.</p>
        <?php else: ?>
        <ul class="pos-list">
            <?php foreach ($stats['low_stock'] as $item): ?>
            <li>
                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                <span><?php echo (int) $item['stock_quantity']; ?> / <?php echo (int) $item['reorder_level']; ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
    <section class="admin-panel">
        <h2>Recent repairs</h2>
        <?php if (!$stats['recent_repairs']): ?>
        <p class="admin-empty">No repair jobs yet.</p>
        <?php else: ?>
        <ul class="pos-list">
            <?php foreach ($stats['recent_repairs'] as $job): ?>
            <li>
                <a href="<?php echo pos_panel_url('repair-view.php?id=' . (int) $job['id']); ?>">
                    <strong><?php echo htmlspecialchars($job['job_no']); ?></strong>
                </a>
                <span><?php echo htmlspecialchars($job['status']); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="admin-hint"><a href="<?php echo pos_panel_url('repairs.php'); ?>">All repairs →</a></p>
        <?php endif; ?>
    </section>
</div>
<?php
pos_render_footer();
