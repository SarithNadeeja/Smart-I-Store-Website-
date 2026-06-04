<?php
/**
 * Homepage category product carousels (mouse-drag horizontal scroll).
 *
 * @var array $category_slides From store_get_home_category_slides()
 */
if (empty($category_slides)) {
    return;
}
?>
<section class="section section-gray category-slides" id="categories" aria-label="Shop by category">
    <div class="container">
        <div class="section-header reveal-up">
            <span class="section-label">Shop by Category</span>
            <h2 class="section-title">Browse Our Collection</h2>
            <p class="section-desc">Drag sideways with your mouse to explore each category.</p>
        </div>

        <div class="category-slides__list">
            <?php foreach ($category_slides as $slideIndex => $slide): ?>
            <?php
            $cat = $slide['category'];
            $products = $slide['products'];
            $catId = (int) $cat['id'];
            $viewAllUrl = page_url('products.php?category=' . $catId);
            ?>
            <section class="category-carousel reveal-up" data-delay="<?php echo min($slideIndex * 0.06, 0.36); ?>" aria-labelledby="category-carousel-<?php echo $catId; ?>">
                <div class="category-carousel__header">
                    <div class="category-carousel__title-wrap">
                        <span class="category-carousel__icon" aria-hidden="true"><?php echo icon($cat['icon'] ?? 'smartphone'); ?></span>
                        <h3 class="category-carousel__title" id="category-carousel-<?php echo $catId; ?>">
                            <?php echo htmlspecialchars($cat['title']); ?>
                        </h3>
                    </div>
                    <a href="<?php echo htmlspecialchars($viewAllUrl); ?>" class="category-carousel__link">
                        View all
                        <span class="category-carousel__link-arrow" aria-hidden="true"><?php echo icon('arrow-right'); ?></span>
                    </a>
                </div>

                <div class="category-carousel__viewport" data-category-carousel tabindex="0" role="region" aria-label="<?php echo htmlspecialchars($cat['title']); ?> products">
                    <div class="category-carousel__track">
                        <?php foreach ($products as $i => $phone): ?>
                        <div class="category-carousel__slide">
                            <?php include __DIR__ . '/product-card.php'; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
    </div>
</section>
