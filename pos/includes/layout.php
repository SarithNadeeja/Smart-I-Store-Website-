<?php

function pos_render_header(string $title, string $active = ''): void
{
    $user = pos_require_setup_complete();
    $flash = pos_consume_flash();
    $nav = [
        'dashboard' => ['Dashboard', 'dashboard.php'],
        'sale' => ['New Sale', 'sale-new.php'],
        'invoices' => ['Invoices', 'invoices.php'],
        'customers' => ['Customers', 'customers.php'],
        'repairs' => ['Repairs', 'repairs.php'],
        'due' => ['Due Payments', 'due-payments.php'],
        'expenses' => ['Expenses', 'expenses.php'],
        'reports' => ['Reports', 'reports.php'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php pos_theme_head_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> | POS · <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('admin/assets/admin-theme.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('admin/assets/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo pos_panel_url('assets/pos.css'); ?>">
</head>
<body class="admin-body pos-body">
<div class="admin-shell" id="admin-shell">
    <div class="admin-nav-backdrop" id="admin-nav-backdrop" hidden aria-hidden="true"></div>
    <aside class="admin-sidebar pos-sidebar" id="admin-sidebar" aria-label="POS menu">
        <div class="admin-brand">
            <span class="admin-brand__title"><?php echo htmlspecialchars(SITE_NAME); ?></span>
            <span class="admin-brand__sub">Cloud POS</span>
        </div>
        <nav class="admin-nav pos-nav" aria-label="POS navigation">
            <?php foreach ($nav as $key => $item): ?>
            <a href="<?php echo pos_panel_url($item[1]); ?>" class="admin-nav__link pos-nav__link<?php echo $active === $key ? ' is-active' : ''; ?>">
                <?php echo htmlspecialchars($item[0]); ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar__foot">
            <span class="admin-user"><?php echo htmlspecialchars($user['name'] ?: $user['username']); ?></span>
            <small class="pos-role-badge"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></small>
            <?php if (($user['role'] ?? '') === 'manager'): ?>
            <a href="<?php echo pos_panel_url('staff.php'); ?>" class="admin-link">Manage staff</a>
            <?php endif; ?>
            <a href="<?php echo pos_panel_url('logout.php'); ?>" class="admin-link admin-link--muted">Log out</a>
        </div>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar__start">
                <button type="button" class="admin-menu-btn" id="admin-menu-btn"
                        aria-expanded="false" aria-controls="admin-sidebar" aria-label="Open menu">
                    <span class="admin-menu-btn__bar" aria-hidden="true"></span>
                    <span class="admin-menu-btn__bar" aria-hidden="true"></span>
                    <span class="admin-menu-btn__bar" aria-hidden="true"></span>
                </button>
                <h1 class="admin-page-title"><?php echo htmlspecialchars($title); ?></h1>
            </div>
            <div class="admin-topbar__actions">
                <a href="<?php echo pos_panel_url('sale-new.php'); ?>" class="btn btn-primary btn-sm pos-btn-new-sale">+ Sale</a>
                <?php pos_theme_toggle(); ?>
            </div>
        </header>
        <?php if ($flash): ?>
        <div class="admin-alert admin-alert--<?php echo htmlspecialchars($flash['type']); ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
        <?php endif; ?>
        <div class="admin-content">
    <?php
}

function pos_render_footer(): void
{
    ?>
        </div>
    </main>
</div>
<script src="<?php echo base_url('admin/assets/admin-theme.js'); ?>"></script>
<script src="<?php echo base_url('admin/assets/admin-mobile.js'); ?>"></script>
<script src="<?php echo pos_panel_url('assets/pos.js'); ?>"></script>
</body>
</html>
    <?php
}
