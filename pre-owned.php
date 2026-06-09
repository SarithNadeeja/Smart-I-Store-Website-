<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Pre-Owned Market';
$body_class = 'page-preowned';
$extra_js = [asset_url('js/preowned-filters.js')];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/store.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/navbar.php';

$all_phones = store_get_preowned_phones();
$conditions = store_preowned_conditions();
$active_brand = trim($_GET['brand'] ?? '');
$active_condition = store_normalize_preowned_condition($_GET['condition'] ?? '');
$active_sort = trim($_GET['sort'] ?? '');

function preowned_passes_filters(array $phone, string $brand, string $condition): bool
{
    if ($brand !== '' && trim($phone['brand'] ?? '') !== $brand) {
        return false;
    }
    if ($condition !== '' && store_normalize_preowned_condition($phone['preowned_condition'] ?? '') !== $condition) {
        return false;
    }
    return true;
}

$brands = [];
foreach ($all_phones as $phone) {
    $b = trim($phone['brand'] ?? '');
    if ($b !== '' && !in_array($b, $brands, true)) {
        $brands[] = $b;
    }
}
sort($brands);

$phones = array_values(array_filter($all_phones, static function (array $phone) use ($active_brand, $active_condition): bool {
    return preowned_passes_filters($phone, $active_brand, $active_condition);
}));

if ($active_sort === 'price_asc') {
    usort($phones, static fn($a, $b) => ($a['current_price'] ?? $a['price']) <=> ($b['current_price'] ?? $b['price']));
} elseif ($active_sort === 'price_desc') {
    usort($phones, static fn($a, $b) => ($b['current_price'] ?? $b['price']) <=> ($a['current_price'] ?? $a['price']));
}
?>

<main>
    <section class="page-hero page-hero-compact page-hero--preowned">
        <div class="container">
            <span class="section-label reveal-up">Certified Pre-Owned</span>
            <h1 class="page-hero-title reveal-up">Pre-Owned <em>Market</em></h1>
            <p class="page-hero-desc reveal-up">Quality-checked used smartphones — honestly graded, fairly priced, ready to go.</p>
        </div>
    </section>

    <section class="section section-white products-catalog">
        <div class="container">
            <?php if ($all_phones): ?>
            <div class="products-filter-stack reveal-up">
                <form method="get" class="product-filters" id="preowned-filters">
                    <div class="product-filters__row">
                        <div class="product-filters__group product-filters__group--select">
                            <label class="product-filters__label" for="filter-brand">Brand</label>
                            <select class="product-filters__select" id="filter-brand" name="brand">
                                <option value="">All brands</option>
                                <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo htmlspecialchars($brand); ?>"<?php echo $active_brand === $brand ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brand); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="product-filters__group product-filters__group--select">
                            <label class="product-filters__label" for="filter-condition">Condition</label>
                            <select class="product-filters__select" id="filter-condition" name="condition">
                                <option value="">All conditions</option>
                                <?php foreach ($conditions as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>"<?php echo $active_condition === $value ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="product-filters__group product-filters__group--select">
                            <label class="product-filters__label" for="filter-sort">Sort</label>
                            <select class="product-filters__select" id="filter-sort" name="sort">
                                <option value="">Newest first</option>
                                <option value="price_asc"<?php echo $active_sort === 'price_asc' ? ' selected' : ''; ?>>Price: low to high</option>
                                <option value="price_desc"<?php echo $active_sort === 'price_desc' ? ' selected' : ''; ?>>Price: high to low</option>
                            </select>
                        </div>
                        <div class="product-filters__group product-filters__group--actions">
                            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            <?php if ($active_brand !== '' || $active_condition !== '' || $active_sort !== ''): ?>
                            <a href="<?php echo page_url('pre-owned.php'); ?>" class="btn btn-ghost btn-sm">Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="product-grid product-grid-page" id="preowned-grid">
                <?php if ($phones): ?>
                <?php foreach ($phones as $i => $phone): ?>
                <?php
                $product_card_preowned = true;
                include __DIR__ . '/includes/product-card.php';
                unset($product_card_preowned);
                ?>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="section-desc">No pre-owned phones listed right now. Check back soon or visit our store in Bandarawela.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
