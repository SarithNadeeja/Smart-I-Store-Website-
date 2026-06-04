<?php
/** Critical loader styles — paint before main CSS to avoid flash */
$preload_videos = site_preload_videos();
?>
<style id="site-loader-critical">
html.is-site-loading,
html.is-site-loading body {
    overflow: hidden;
}
#site-loader {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(ellipse 120% 80% at 30% 40%, #2a2618 0%, #111 55%, #0a0a0a 100%);
    transition: opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.5s;
}
#site-loader.is-hidden {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}
</style>
<?php foreach ($preload_videos as $vid): ?>
<link rel="preload" href="<?php echo htmlspecialchars($vid['url']); ?>" as="video" type="<?php echo htmlspecialchars($vid['type']); ?>">
<?php endforeach; ?>
<script>
document.documentElement.classList.add('is-site-loading');
</script>
