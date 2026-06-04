<?php

function admin_render_header(string $title, string $active = ''): void
{
    $user = admin_require_setup_complete();
    $flash = admin_consume_flash();
    $nav = [
        'dashboard' => ['Dashboard', 'dashboard.php', 'dashboard'],
        'categories' => ['Category Management', 'categories.php', 'categories'],
        'brands' => ['Phone Brands', 'brands.php', 'brands'],
        'models' => ['Model Management', 'models.php', 'models'],
        'items' => ['Add / Manage Items', 'items.php', 'items'],
        'stock' => ['Stock Status', 'stock.php', 'stock'],
        'security' => ['Security', 'security.php', 'security'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php admin_theme_head_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> | Admin · <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_url('assets/admin-theme.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_url('assets/admin.css'); ?>">
</head>
<body class="admin-body">
<div class="admin-shell" id="admin-shell">
    <div class="admin-nav-backdrop" id="admin-nav-backdrop" hidden aria-hidden="true"></div>
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin menu">
        <div class="admin-brand">
            <span class="admin-brand__title">Smart I Store</span>
            <span class="admin-brand__sub">Admin Panel</span>
        </div>
        <nav class="admin-nav" aria-label="Admin navigation">
            <?php foreach ($nav as $key => $item): ?>
            <a href="<?php echo admin_url($item[1]); ?>" class="admin-nav__link<?php echo $active === $key ? ' is-active' : ''; ?>">
                <?php echo htmlspecialchars($item[0]); ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar__foot">
            <span class="admin-user"><?php echo htmlspecialchars($user['username']); ?></span>
            <a href="<?php echo page_url('index.php'); ?>" class="admin-link" target="_blank" rel="noopener">View site</a>
            <a href="<?php echo admin_url('logout.php'); ?>" class="admin-link admin-link--muted">Log out</a>
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
                <?php admin_theme_toggle(); ?>
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

function admin_render_footer(): void
{
    ?>
        </div>
    </main>
</div>
<script src="<?php echo admin_url('assets/admin-theme.js'); ?>"></script>
<script src="<?php echo admin_url('assets/admin-mobile.js'); ?>"></script>
</body>
</html>
    <?php
}
