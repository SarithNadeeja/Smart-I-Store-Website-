<?php
/**
 * Animated horizontal brand line. Pass $marquee_brands as list of strings, or leave empty to load from DB + fallback.
 */
if (!isset($marquee_brands) || !is_array($marquee_brands)) {
    $marquee_brands = [];
}
if (!$marquee_brands && function_exists('store_get_brand_names')) {
    require_once __DIR__ . '/store.php';
    $marquee_brands = store_get_brand_names();
}
if (!$marquee_brands) {
    $marquee_brands = [
        'Samsung', 'Apple', 'Xiaomi', 'OPPO', 'vivo', 'Huawei', 'Nokia', 'realme',
        'Nothing', 'Google Pixel', 'OnePlus', 'Sony',
    ];
}
$marquee_brands = array_values(array_filter(array_map('trim', $marquee_brands)));
if (!$marquee_brands) {
    return;
}
?>
<div class="brands-marquee reveal-up" aria-label="Brands we work with">
    <p class="brands-marquee__label">Brands we carry</p>
    <div class="brands-marquee__viewport">
        <div class="brands-marquee__track">
            <div class="brands-marquee__group">
                <?php foreach ($marquee_brands as $brand): ?>
                <span class="brands-marquee__chip"><?php echo htmlspecialchars($brand); ?></span>
                <?php endforeach; ?>
            </div>
            <div class="brands-marquee__group" aria-hidden="true">
                <?php foreach ($marquee_brands as $brand): ?>
                <span class="brands-marquee__chip"><?php echo htmlspecialchars($brand); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
