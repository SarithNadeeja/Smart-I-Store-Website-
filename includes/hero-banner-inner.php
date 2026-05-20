<?php
if (!isset($hero_video_file)) {
    $hero_video_file = __DIR__ . '/../assets/videos/website.webm';
}
if (!isset($hero_video_url)) {
    $hero_video_url = asset_url(HERO_VIDEO);
}
$hero_video_exists = file_exists($hero_video_file);
$hero_autoplay = $hero_autoplay ?? false;
?>
<div class="hero-banner__bg" aria-hidden="true">
    <div class="hero-banner__bg-base"></div>
    <div class="hero-banner__bg-glow"></div>
    <div class="hero-banner__bg-sparkle"></div>
</div>

<div class="hero-banner__stage" id="hero-stage">
    <?php if ($hero_video_exists): ?>
    <video
        id="hero-source-video"
        class="hero-banner__video-src"
        src="<?php echo htmlspecialchars($hero_video_url); ?>"
        muted
        playsinline
        webkit-playsinline
        <?php echo $hero_autoplay ? 'autoplay' : ''; ?>
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

<h1 class="hero-banner__showcase">
    <span class="visually-hidden">Smart I Store — Future Mobile</span>
    <span class="hero-banner__brand hero-banner__brand--split hero-banner__brand--left" aria-hidden="true">
        <span class="hero-banner__brand-line">
            <span class="hero-banner__brand-text">Smart</span>
        </span>
        <span class="hero-banner__brand-sub">Future</span>
    </span>
    <span class="hero-banner__brand hero-banner__brand--split hero-banner__brand--right" aria-hidden="true">
        <span class="hero-banner__brand-line">
            <span class="hero-banner__brand-accent">I</span><span class="hero-banner__brand-text"> Store</span>
        </span>
        <span class="hero-banner__brand-sub">Mobile</span>
    </span>
    <span class="hero-banner__brand-mobile" aria-hidden="true">
        <span class="hero-banner__brand-mobile-text">
            Smart <span class="hero-banner__brand-accent">I</span> Store
        </span>
    </span>
</h1>
