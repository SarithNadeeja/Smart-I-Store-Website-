<?php
/**
 * Home hero + intro overlay (once per browser tab session)
 */
$show_intro = intro_video_exists();
?>
<?php if ($show_intro): ?>
<div
    class="intro-experience intro-experience--overlay"
    id="intro-experience"
    data-trim="<?php echo (int) INTRO_TRIM_START; ?>"
    role="dialog"
    aria-label="Site introduction"
    aria-modal="true"
>
    <?php require __DIR__ . '/hero-intro.php'; ?>
</div>
<?php endif; ?>
<section
    class="hero-banner<?php echo $show_intro ? ' hero-banner--after-intro' : ''; ?>"
    id="hero"
    aria-label="Hero"
>
    <?php
    $hero_autoplay = !$show_intro;
    require __DIR__ . '/hero-banner-inner.php';
    ?>
</section>
