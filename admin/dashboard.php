<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$stats = store_dashboard_stats();

admin_render_header('Dashboard', 'dashboard');
?>
<div class="admin-stats">
    <div class="admin-stat-card">
        <span class="admin-stat-card__value"><?php echo $stats['categories']; ?></span>
        <span class="admin-stat-card__label">Categories</span>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-card__value"><?php echo $stats['brands']; ?></span>
        <span class="admin-stat-card__label">Brands</span>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-card__value"><?php echo $stats['models']; ?></span>
        <span class="admin-stat-card__label">Models</span>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-card__value"><?php echo $stats['items']; ?></span>
        <span class="admin-stat-card__label">Items</span>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-card__value"><?php echo $stats['users']; ?></span>
        <span class="admin-stat-card__label">Admin users</span>
    </div>
</div>

<div class="admin-panel">
    <h2>Quick actions</h2>
    <div class="admin-actions-row">
        <a class="btn btn-primary" href="<?php echo admin_url('categories.php'); ?>">Manage categories</a>
        <a class="btn btn-primary" href="<?php echo admin_url('brands.php'); ?>">Manage brands</a>
        <a class="btn btn-primary" href="<?php echo admin_url('models.php'); ?>">Manage models</a>
        <a class="btn btn-primary" href="<?php echo admin_url('item-form.php'); ?>">Add new item</a>
        <a class="btn btn-ghost" href="<?php echo admin_url('stock.php'); ?>">Stock status</a>
        <a class="btn btn-ghost" href="<?php echo page_url('index.php'); ?>" target="_blank" rel="noopener">View website</a>
    </div>
</div>
<?php
admin_render_footer();
