<?php
/**
 * Home hero banner — desktop image slideshow + mobile image slideshow
 */
$hero_mobile_banners = hero_mobile_banner_urls();
$hero_desktop_banners = hero_desktop_banner_urls();
$hero_scroll_over = $hero_mobile_banners !== [] || $hero_desktop_banners !== [];
$hero_show_video = false;

$hero_banner_classes = ['hero-banner'];
if ($hero_mobile_banners) {
    $hero_banner_classes[] = 'hero-banner--has-mobile-slides';
}
if ($hero_desktop_banners) {
    $hero_banner_classes[] = 'hero-banner--has-desktop-slides';
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
