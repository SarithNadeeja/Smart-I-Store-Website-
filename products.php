<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Products';
$body_class = 'page-products';
$extra_js = [asset_url('js/products-filters.js')];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/products-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/navbar.php';

$all_products = store_get_all_products();
$active_category = (int) ($_GET['category'] ?? 0);
$active_stock = trim($_GET['stock'] ?? '');
if ($active_stock !== '') {
    $active_stock = store_normalize_stock_status($active_stock);
}
$active_brand = trim($_GET['brand'] ?? '');
$active_model = trim($_GET['model'] ?? '');
$active_sort = trim($_GET['sort'] ?? '');
$stock_labels = store_stock_statuses();

function product_passes_filters(array $phone, int $category, string $brand, string $stock, string $model): bool
{
    if ($category > 0 && (int) ($phone['category_id'] ?? 0) !== $category) {
        return false;
    }
    if ($brand !== '' && trim($phone['brand'] ?? '') !== $brand) {
        return false;
    }
    if ($stock !== '' && store_normalize_stock_status($phone['stock_status'] ?? 'in_stock') !== $stock) {
        return false;
    }
    if ($model !== '' && trim($phone['model'] ?? '') !== $model) {
        return false;
    }
    return true;
}
?>

<main>
    <section class="page-hero page-hero-compact">
        <div class="container">
            <span class="section-label reveal-up">Catalog</span>
            <h1 class="page-hero-title reveal-up">Our <em>Collection</em></h1>
            <p class="page-hero-desc reveal-up">Explore smartphones and accessories from our catalog.</p>
        </div>
    </section>

    <section class="section section-white products-catalog">
        <div class="container">
            <div class="products-filter-stack reveal-up">
                <?php if ($categories): ?>
                <nav class="category-subnav" id="category-subnav" aria-label="Product categories">
                    <a href="<?php echo page_url('products.php'); ?>"
                       class="category-subnav__btn<?php echo $active_category === 0 ? ' is-active' : ''; ?>"
                       data-filter="all">
                        All products
                    </a>
                    <?php foreach ($categories as $cat): ?>
                    <a href="<?php echo page_url('products.php?category=' . (int) $cat['id']); ?>"
                       class="category-subnav__btn<?php echo $active_category === (int) $cat['id'] ? ' is-active' : ''; ?>"
                       data-filter="<?php echo (int) $cat['id']; ?>">
                        <span class="category-subnav__icon" aria-hidden="true"><?php echo icon($cat['icon']); ?></span>
                        <span class="category-subnav__label"><?php echo htmlspecialchars($cat['title']); ?></span>
                    </a>
                    <?php endforeach; ?>
                </nav>
                <?php endif; ?>

                <div class="product-filters" id="product-filters">
                    <div class="product-filters__row">
                        <div class="product-filters__group product-filters__group--select" id="filter-group-brand" hidden>
                            <label class="product-filters__label" for="filter-brand">Brand</label>
                            <select class="product-filters__select" id="filter-brand" name="brand" aria-label="Filter by brand">
                                <option value="">All brands</option>
                            </select>
                        </div>
                        <div class="product-filters__group product-filters__group--select" id="filter-group-model" hidden>
                            <label class="product-filters__label" for="filter-model">Model</label>
                            <select class="product-filters__select" id="filter-model" name="model" aria-label="Filter by model">
                                <option value="">All models</option>
                            </select>
                        </div>
                        <div class="product-filters__group product-filters__group--select" id="filter-group-stock" hidden>
                            <label class="product-filters__label" for="filter-stock">Stock</label>
                            <select class="product-filters__select" id="filter-stock" name="stock" aria-label="Filter by stock">
                                <option value="">All stock</option>
                            </select>
                        </div>
                        <div class="product-filters__group product-filters__group--select">
                            <label class="product-filters__label" for="filter-sort">Sort</label>
                            <select class="product-filters__select" id="filter-sort" name="sort">
                                <option value="">Default</option>
                                <option value="price-asc"<?php echo $active_sort === 'price-asc' ? ' selected' : ''; ?>>Price: low to high</option>
                                <option value="price-desc"<?php echo $active_sort === 'price-desc' ? ' selected' : ''; ?>>Price: high to low</option>
                                <option value="name-asc"<?php echo $active_sort === 'name-asc' ? ' selected' : ''; ?>>Name: A-Z</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="product-filters__clear" id="filter-clear" hidden>Clear filters</button>
                </div>
            </div>

            <script type="application/json" id="product-stock-labels"><?php
                echo json_encode($stock_labels, JSON_UNESCAPED_UNICODE);
            ?></script>

            <p class="products-empty-filter section-desc" id="products-empty-filter" hidden>
                No products match your filters. Try another category or clear filters.
            </p>

            <div class="product-grid product-grid-page" id="product-grid">
                <?php if ($all_products): ?>
                <?php foreach ($all_products as $i => $phone): ?>
                <?php
                $product_card_hidden = !product_passes_filters(
                    $phone,
                    $active_category,
                    $active_brand,
                    $active_stock,
                    $active_model
                );
                include __DIR__ . '/includes/product-card.php';
                ?>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="section-desc">No products yet. Check back soon.</p>
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
