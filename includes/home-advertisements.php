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
    <?php
    $label = $ad['item_name'] ?: $ad['title'];
    $hasLink = !empty($ad['url']);
    $tag = $hasLink ? 'a' : 'div';
    ?>
    <<?php echo $tag; ?> class="home-ad glass-card reveal-up<?php echo $hasLink ? '' : ' home-ad--static'; ?>"
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
</aside>
