<?php
$preload_videos = site_preload_videos();
$loader_logo = site_logo_exists() ? site_logo_url() : '';
?>
<div
    id="site-loader"
    class="site-loader"
    role="status"
    aria-live="polite"
    aria-busy="true"
    aria-label="Loading Smart I Store"
    data-videos="<?php echo htmlspecialchars(json_encode($preload_videos, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
>
    <div class="site-loader__inner">
        <?php if ($loader_logo): ?>
        <img class="site-loader__logo" src="<?php echo htmlspecialchars($loader_logo); ?>" alt="" width="72" height="72" decoding="async">
        <?php else: ?>
        <span class="site-loader__mark" aria-hidden="true"></span>
        <?php endif; ?>
        <p class="site-loader__brand"><?php echo htmlspecialchars(SITE_NAME); ?></p>
        <div class="site-loader__spinner" aria-hidden="true">
            <span class="site-loader__ring"></span>
        </div>
        <p class="site-loader__label" id="site-loader-label">Loading…</p>
    </div>
</div>
