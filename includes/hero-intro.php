<?php
/** Intro overlay — play on button click, then enter site */
$intro_webm = asset_url(INTRO_VIDEO);
$intro_mp4 = asset_url('videos/intro.mp4');
?>
<div class="hero-intro" id="hero-intro" aria-hidden="false">
    <div class="hero-intro__bg" aria-hidden="true">
        <div class="hero-intro__bg-base"></div>
        <div class="hero-intro__bg-vignette"></div>
    </div>

    <div class="hero-intro__stage" id="hero-intro-stage">
        <video
            id="hero-intro-video"
            class="hero-intro__video-src"
            muted
            playsinline
            webkit-playsinline
            preload="auto"
            disablePictureInPicture
            aria-hidden="true"
        >
            <source src="<?php echo htmlspecialchars($intro_webm); ?>" type="video/webm">
            <source src="<?php echo htmlspecialchars($intro_mp4); ?>" type="video/mp4">
        </video>
        <canvas
            id="hero-intro-canvas"
            class="hero-intro__canvas"
            aria-label="Intro animation"
        ></canvas>
        <p class="hero-intro__status" id="hero-intro-status" hidden>Loading intro…</p>
    </div>

    <div class="hero-intro__panel" id="hero-intro-panel">
        <h1 class="hero-intro__name"><?php
            $brand_parts = explode(' I ', SITE_NAME, 2);
            if (count($brand_parts) === 2) {
                echo htmlspecialchars($brand_parts[0]) . ' <span class="accent">I</span> ' . htmlspecialchars($brand_parts[1]);
            } else {
                echo htmlspecialchars(SITE_NAME);
            }
        ?></h1>

        <p class="hero-intro__about"><?php echo htmlspecialchars(SITE_ABOUT); ?></p>

        <p class="hero-intro__address"><a href="<?php echo htmlspecialchars(SITE_MAP_URL); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(SITE_ADDRESS); ?></a></p>

        <p class="hero-intro__phone">
            <a href="<?php echo htmlspecialchars(whatsapp_url(SITE_WHATSAPP_1)); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(SITE_WHATSAPP_1); ?></a>
            <span class="hero-intro__phone-sep"> / </span>
            <a href="<?php echo htmlspecialchars(whatsapp_url(SITE_WHATSAPP_2)); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(SITE_WHATSAPP_2); ?></a>
        </p>

        <button type="button" class="hero-intro__enter btn btn-primary" id="hero-intro-enter" disabled>
            Enter Store
        </button>
    </div>
</div>
