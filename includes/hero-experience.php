<?php
/**
 * Home hero banner (no intro overlay)
 */
?>
<?php
$hero_mobile_banners = hero_mobile_banner_urls();
$hero_scroll_over = hero_video_exists() || $hero_mobile_banners !== [];
$hero_banner_classes = ['hero-banner'];
if ($hero_mobile_banners) {
    $hero_banner_classes[] = 'hero-banner--has-mobile-slides';
}
if ($hero_scroll_over) {
    $hero_banner_classes[] = 'hero-banner--scroll-over';
}
?>
<section class="<?php echo htmlspecialchars(implode(' ', $hero_banner_classes)); ?>" id="hero" aria-label="Hero">
    <?php
    $hero_autoplay = true;
    require __DIR__ . '/hero-banner-inner.php';
    ?>
</section>
