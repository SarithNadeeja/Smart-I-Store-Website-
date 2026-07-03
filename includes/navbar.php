<?php
$is_home = is_current_page('index.php');
$header_class = 'site-header';
if ($is_home) {
    if (hero_desktop_banner_urls() !== [] || hero_mobile_banner_urls() !== []) {
        $header_class .= ' site-header--home-banner';
    } else {
        $header_class .= ' site-header--hero';
    }
}

$nav_links = [
    ['label' => 'Home', 'href' => page_url('index.php'), 'page' => 'index.php'],
    ['label' => 'Products', 'href' => page_url('products.php'), 'page' => 'products.php'],
    ['label' => 'Pre-Owned Market', 'href' => page_url('pre-owned.php'), 'page' => 'pre-owned.php'],
    ['label' => 'About Us', 'href' => page_url('about.php'), 'page' => 'about.php'],
    ['label' => 'Contact', 'href' => page_url('contact.php'), 'page' => 'contact.php'],
];
?>
<header class="<?php echo $header_class; ?>" id="site-header">
    <div class="container header-inner">
        <a href="<?php echo page_url('index.php'); ?>" class="logo logo--text" aria-label="<?php echo htmlspecialchars(SITE_NAME); ?> home">
            <span class="logo-text"><?php echo htmlspecialchars(SITE_NAME); ?></span>
        </a>

        <nav class="main-nav" id="main-nav" aria-label="Main navigation">
            <ul class="nav-list">
                <?php foreach ($nav_links as $link): ?>
                <li>
                    <a href="<?php echo htmlspecialchars($link['href']); ?>"
                       class="nav-link<?php echo ($link['page'] && is_current_page($link['page'])) ? ' is-active' : ''; ?>">
                        <?php echo htmlspecialchars($link['label']); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="header-search-mobile">
                <?php
                $site_search_id = 'header-search-mobile';
                $site_search_scope = 'all';
                $site_search_variant = 'compact';
                $site_search_action = page_url('products.php');
                $site_search_q = trim($_GET['q'] ?? '');
                $site_search_autocomplete = true;
                require __DIR__ . '/site-search.php';
                ?>
            </div>
        </nav>

        <div class="header-search-desktop">
            <?php
            $site_search_id = 'header-search';
            $site_search_scope = 'all';
            $site_search_variant = 'compact';
            $site_search_action = page_url('products.php');
            $site_search_q = trim($_GET['q'] ?? '');
            $site_search_autocomplete = true;
            require __DIR__ . '/site-search.php';
            ?>
        </div>

        <div class="header-actions">
            <?php $social_class = 'social-links social-links--header'; require __DIR__ . '/social-links.php'; ?>
            <button type="button" class="nav-toggle" id="nav-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="main-nav">
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>
        </div>
    </div>
    <div class="nav-overlay" id="nav-overlay" aria-hidden="true"></div>
</header>
