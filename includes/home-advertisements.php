<?php
/**
 * Home page advertisement slideshow below the catalog search (4 per row).
 *
 * @var list<array<string, mixed>> $home_advertisements
 */
$home_advertisements = $home_advertisements ?? store_get_site_advertisements();
if (!$home_advertisements) {
    return;
}

$adsPerSlide = 4;
$adPages = array_chunk($home_advertisements, $adsPerSlide);
$multiPage = count($adPages) > 1;
?>
<div class="home-ads-slideshow" data-home-ads-slideshow aria-label="Promotions">
    <div class="home-ads-slideshow__viewport">
        <div class="home-ads-slideshow__track" id="home-ads-slideshow-track">
            <?php foreach ($adPages as $pageIndex => $pageAds): ?>
            <div class="home-ads-slideshow__page" data-page="<?php echo (int) $pageIndex; ?>">
                <?php foreach ($pageAds as $ad): ?>
                <?php
                $label = $ad['item_name'] ?: $ad['title'];
                $hasLink = !empty($ad['url']);
                $tag = $hasLink ? 'a' : 'div';
                ?>
                <<?php echo $tag; ?> class="home-ad glass-card<?php echo $hasLink ? '' : ' home-ad--static'; ?>"
                   <?php if ($hasLink): ?>
                   href="<?php echo htmlspecialchars($ad['url'], ENT_QUOTES, 'UTF-8'); ?>"
                   <?php endif; ?>
                   title="<?php echo htmlspecialchars($ad['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!empty($ad['image_url'])): ?>
                    <img class="home-ad__image"
                         src="<?php echo htmlspecialchars($ad['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($ad['title'], ENT_QUOTES, 'UTF-8'); ?>"
                         loading="lazy"
                         decoding="async">
                    <?php endif; ?>
                    <?php if ($label !== ''): ?>
                    <span class="home-ad__label"><?php echo htmlspecialchars($label); ?></span>
                    <?php endif; ?>
                </<?php echo $tag; ?>>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($multiPage): ?>
    <div class="home-ads-slideshow__controls">
        <button type="button" class="home-ads-slideshow__arrow home-ads-slideshow__arrow--prev" aria-label="Previous advertisements">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="home-ads-slideshow__dots" role="tablist" aria-label="Advertisement slides">
            <?php foreach ($adPages as $pageIndex => $_page): ?>
            <button type="button"
                    class="home-ads-slideshow__dot<?php echo $pageIndex === 0 ? ' is-active' : ''; ?>"
                    data-slide-to="<?php echo (int) $pageIndex; ?>"
                    role="tab"
                    aria-label="Slide <?php echo (int) $pageIndex + 1; ?>"
                    aria-selected="<?php echo $pageIndex === 0 ? 'true' : 'false'; ?>"></button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="home-ads-slideshow__arrow home-ads-slideshow__arrow--next" aria-label="Next advertisements">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        </button>
    </div>
    <?php endif; ?>
</div>
