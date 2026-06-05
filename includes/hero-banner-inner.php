<?php
if (!isset($hero_video_file)) {
    $hero_video_file = __DIR__ . '/../assets/videos/website.webm';
}
if (!isset($hero_video_url)) {
    $hero_video_url = asset_url(HERO_VIDEO);
}
$hero_video_exists = file_exists($hero_video_file);
$hero_autoplay = $hero_autoplay ?? true;
$hero_mobile_banners = $hero_mobile_banners ?? hero_mobile_banner_urls();
$hero_scroll_over = $hero_scroll_over ?? ($hero_video_exists || $hero_mobile_banners !== []);
?>
<?php if ($hero_mobile_banners): ?>
<div class="hero-banner__mobile-slides" id="hero-mobile-slides" aria-hidden="true">
    <?php foreach ($hero_mobile_banners as $index => $bannerUrl): ?>
    <img
        class="hero-banner__mobile-slide<?php echo $index === 0 ? ' is-active' : ''; ?>"
        src="<?php echo htmlspecialchars($bannerUrl); ?>"
        alt=""
        width="1080"
        height="1920"
        loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
        decoding="async"
    >
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="hero-banner__bg" aria-hidden="true">
    <div class="hero-banner__bg-base"></div>
    <div class="hero-banner__bg-glow"></div>
    <div class="hero-banner__bg-sparkle"></div>
</div>

<div class="hero-banner__stage hero-banner__stage--video" id="hero-stage">
    <?php if ($hero_video_exists): ?>
    <video
        id="hero-source-video"
        class="hero-banner__video-src"
        src="<?php echo htmlspecialchars($hero_video_url); ?>"
        muted
        playsinline
        webkit-playsinline
        <?php echo $hero_autoplay ? 'autoplay' : ''; ?>
        loop
        preload="auto"
        disablePictureInPicture
        aria-hidden="true"
    ></video>
    <canvas
        id="hero-chroma-canvas"
        class="hero-banner__canvas"
        aria-label="Smartphone showcase animation"
    ></canvas>
    <?php else: ?>
    <p class="hero-banner__error">
        Video not found. Add <strong>assets/videos/website.webm</strong> to your project.
    </p>
    <?php endif; ?>
</div>

<h1 class="visually-hidden">Smart I Store</h1>
