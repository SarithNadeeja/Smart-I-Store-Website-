<?php
/**
 * Home page advertisements beside the catalog search.
 *
 * @var list<array<string, mixed>> $home_advertisements
 */
$home_advertisements = $home_advertisements ?? store_get_site_advertisements();
if (!$home_advertisements) {
    return;
}
?>
<aside class="home-search__ads" aria-label="Promotions">
    <?php foreach ($home_advertisements as $ad): ?>
    <a class="home-ad glass-card reveal-up"
       href="<?php echo htmlspecialchars($ad['url'], ENT_QUOTES, 'UTF-8'); ?>"
       title="<?php echo htmlspecialchars($ad['title'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (!empty($ad['image_url'])): ?>
        <img class="home-ad__image"
             src="<?php echo htmlspecialchars($ad['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
             alt="<?php echo htmlspecialchars($ad['title'], ENT_QUOTES, 'UTF-8'); ?>"
             loading="lazy"
             decoding="async">
        <?php endif; ?>
        <span class="home-ad__label"><?php echo htmlspecialchars($ad['item_name'] ?: $ad['title']); ?></span>
    </a>
    <?php endforeach; ?>
</aside>
