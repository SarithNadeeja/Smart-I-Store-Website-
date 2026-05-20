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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> | Admin · <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_url('assets/admin.css'); ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
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
            <h1 class="admin-page-title"><?php echo htmlspecialchars($title); ?></h1>
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
</body>
</html>
    <?php
}
