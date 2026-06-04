<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/store.php';

$id = (int) ($_GET['id'] ?? 0);
$product = store_get_product($id);

$page_title = $product ? $product['name'] : 'Product not found';
$body_class = 'page-product-detail';
$extra_js = $product ? [asset_url('js/product-detail.js')] : [];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/navbar.php';

$whatsappOrderUrl = '';
$variantOptions = [];
if ($product) {
    $basePrice = (float) $product['price'];
    foreach ($product['storage_variants'] ?? [] as $variant) {
        $effective = store_variant_effective_price($variant, $basePrice);
        $listPrice = (float) ($variant['list_price'] ?? $variant['price'] ?? $basePrice);
        $onSale = !empty($variant['on_sale']);
        $variantOptions[] = array_merge($variant, [
            'effective_price' => $effective,
            'list_price' => $listPrice,
            'current_price' => $effective,
            'on_sale' => $onSale,
            'price_formatted' => $onSale
                ? 'Rs. ' . number_format($listPrice, 0) . ' → Rs. ' . number_format($effective, 0)
                : 'Rs. ' . number_format($effective, 0),
            'product_url' => page_url('product.php?id=' . (int) ($variant['item_id'] ?? $product['id'])),
        ]);
    }
    $currentVariant = null;
    foreach ($variantOptions as $variant) {
        if (!empty($variant['is_current']) || (int) ($variant['item_id'] ?? 0) === (int) $product['id']) {
            $currentVariant = $variant;
            break;
        }
    }
    $defaultVariant = $currentVariant ?? ($variantOptions[0] ?? null);
    $whatsappOrderUrl = whatsapp_order_url(
        SITE_WHATSAPP_1,
        store_whatsapp_order_message($product, $defaultVariant)
    );
}
?>

<main class="product-detail-page">
    <?php if (!$product): ?>
    <section class="section section-white">
        <div class="container product-detail-empty reveal-up">
            <h1 class="section-title">Product not found</h1>
            <p class="section-desc">This item may have been removed or is no longer available.</p>
            <a href="<?php echo page_url('products.php'); ?>" class="btn btn-primary">Back to products</a>
        </div>
    </section>
    <?php else: ?>
    <?php
    $images = $product['images'];
    $mainImage = $images[0] ?? '';
    $stockStatus = $product['stock_status'] ?? 'in_stock';
    $hasVariants = count($variantOptions) > 1;
    if ($hasVariants && count($variantOptions) > 1) {
        $displayCurrent = min(array_map(static fn(array $v): float => (float) $v['effective_price'], $variantOptions));
        $displayList = null;
        $pricePrefix = 'From ';
    } else {
        $priceSource = $defaultVariant ?? $product;
        $displayCurrent = (float) ($priceSource['current_price'] ?? $priceSource['effective_price'] ?? $product['current_price'] ?? $product['price']);
        $displayList = !empty($priceSource['on_sale']) ? (float) ($priceSource['list_price'] ?? $product['list_price'] ?? $product['price']) : null;
        $pricePrefix = '';
    }
    $systemSpecs = $product['system_specs'] ?? [];
    ?>
    <section class="section section-white product-detail-section">
        <div class="container">
            <nav class="product-breadcrumb reveal-up" aria-label="Breadcrumb">
                <a href="<?php echo page_url('index.php'); ?>">Home</a>
                <span aria-hidden="true">/</span>
                <a href="<?php echo page_url('products.php'); ?>">Products</a>
                <span aria-hidden="true">/</span>
                <span><?php echo htmlspecialchars($product['name']); ?></span>
            </nav>

            <div class="product-detail reveal-up">
                <div class="product-detail__gallery" id="product-gallery">
                    <?php if ($mainImage): ?>
                    <div class="product-detail__zoom" id="product-zoom">
                        <div class="product-detail__main-wrap product-detail__zoom-stage" id="product-zoom-stage">
                            <img
                                id="product-detail-main"
                                class="product-detail__main-img"
                                src="<?php echo htmlspecialchars(upload_url($mainImage)); ?>"
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                            >
                            <div class="product-detail__zoom-lens" id="product-zoom-lens" hidden aria-hidden="true"></div>
                        </div>
                        <div
                            class="product-detail__zoom-pane"
                            id="product-zoom-pane"
                            role="img"
                            aria-label="Zoomed product view"
                        ></div>
                    </div>
                    <?php else: ?>
                    <div class="product-detail__main-wrap">
                        <div class="product-detail__main-placeholder" style="--product-accent: <?php echo htmlspecialchars($product['color'] ?? '#333'); ?>">
                            <span><?php echo htmlspecialchars($product['brand'] ?: $product['name']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (count($images) > 1): ?>
                    <div class="product-detail__thumbs" role="tablist" aria-label="Product images">
                        <?php foreach ($images as $index => $imagePath): ?>
                        <button
                            type="button"
                            class="product-detail__thumb<?php echo $index === 0 ? ' is-active' : ''; ?>"
                            role="tab"
                            aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                            aria-label="View image <?php echo $index + 1; ?>"
                            data-src="<?php echo htmlspecialchars(upload_url($imagePath)); ?>"
                        >
                            <img src="<?php echo htmlspecialchars(upload_url($imagePath)); ?>" alt="" loading="lazy">
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="product-detail__info">
                    <?php if (!empty($product['tag'])): ?>
                    <span class="product-detail__tag"><?php echo htmlspecialchars($product['tag']); ?></span>
                    <?php endif; ?>

                    <h1 class="product-detail__title"><?php echo htmlspecialchars($product['name']); ?></h1>

                    <?php if (!empty($product['meta'])): ?>
                    <p class="product-detail__meta"><?php echo htmlspecialchars($product['meta']); ?></p>
                    <?php endif; ?>

                    <p class="product-detail__price" id="product-detail-price"><?php
                        if ($hasVariants && count($variantOptions) > 1) {
                            echo store_format_price_display($displayCurrent, null, $pricePrefix);
                        } else {
                            echo store_format_price_display($displayCurrent, $displayList, $pricePrefix);
                        }
                    ?></p>

                    <?php if ($hasVariants): ?>
                    <div class="product-detail__variant-field">
                        <label class="product-detail__variant-label" for="product-variant">Storage option</label>
                        <select class="product-filters__select product-detail__variant-select" id="product-variant">
                            <?php foreach ($variantOptions as $variant): ?>
                            <?php $isSelected = (int) ($variant['item_id'] ?? 0) === (int) $product['id']; ?>
                            <option value="<?php echo (int) ($variant['item_id'] ?? 0); ?>"<?php echo $isSelected ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($variant['label']); ?> — Rs. <?php echo $variant['price_formatted']; ?>
                                (<?php echo htmlspecialchars($variant['stock_label']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="product-detail__badges">
                        <span class="product-stock product-stock--<?php echo htmlspecialchars($stockStatus); ?>" id="product-detail-stock">
                            <?php echo htmlspecialchars($product['stock_label']); ?>
                        </span>
                        <?php if (!empty($product['category_title'])): ?>
                        <span class="product-detail__category"><?php echo htmlspecialchars($product['category_title']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="product-detail__actions">
                        <a
                            href="<?php echo htmlspecialchars($whatsappOrderUrl); ?>"
                            class="btn btn-whatsapp btn-lg"
                            id="product-whatsapp-order"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <span class="btn-whatsapp__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </span>
                            Order on WhatsApp
                        </a>
                        <a href="<?php echo page_url('products.php'); ?>" class="btn btn-ghost btn-lg">Continue shopping</a>
                    </div>

                    <?php if ($systemSpecs): ?>
                    <div class="product-detail__highlights glass-card">
                        <h2 class="product-detail__specs-title">Key features</h2>
                        <ul class="product-detail__feature-list">
                            <?php foreach ($systemSpecs as $spec): ?>
                            <li><?php echo htmlspecialchars($spec['text']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="product-detail__specs glass-card">
                        <h2 class="product-detail__specs-title">Product details</h2>
                        <dl class="product-detail__spec-list">
                            <?php if (!empty($product['brand'])): ?>
                            <div>
                                <dt>Brand</dt>
                                <dd><?php echo htmlspecialchars($product['brand']); ?></dd>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($product['model'])): ?>
                            <div>
                                <dt>Model</dt>
                                <dd><?php echo htmlspecialchars($product['model']); ?></dd>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($product['category_title'])): ?>
                            <div>
                                <dt>Category</dt>
                                <dd><?php echo htmlspecialchars($product['category_title']); ?></dd>
                            </div>
                            <?php endif; ?>
                            <div>
                                <dt>Availability</dt>
                                <dd><?php echo htmlspecialchars($product['stock_label']); ?></dd>
                            </div>
                            <div>
                                <dt>Price</dt>
                                <dd><?php
                                    $specCurrent = (float) ($product['current_price'] ?? $product['price']);
                                    $specList = !empty($product['on_sale'])
                                        ? (float) ($product['list_price'] ?? $product['price'])
                                        : null;
                                    echo store_format_price_display($specCurrent, $specList);
                                ?></dd>
                            </div>
                        </dl>
                    </div>

                    <p class="product-detail__note">
                        Tap <strong>Order on WhatsApp</strong> to message us with this product. We will confirm stock and next steps in chat.
                    </p>
                </div>
            </div>
        </div>
    </section>
    <script type="application/json" id="product-order-data"><?php
        if ($product) {
            echo json_encode([
                'whatsappNumber' => SITE_WHATSAPP_1,
                'siteName' => SITE_NAME,
                'product' => [
                    'id' => (int) $product['id'],
                    'name' => $product['name'],
                    'price' => (float) ($product['list_price'] ?? $product['price']),
                    'current_price' => (float) ($product['current_price'] ?? $product['price']),
                    'list_price' => (float) ($product['list_price'] ?? $product['price']),
                    'on_sale' => !empty($product['on_sale']),
                    'meta' => $product['meta'] ?? '',
                    'category_title' => $product['category_title'] ?? '',
                    'stock_label' => $product['stock_label'] ?? '',
                ],
                'variants' => $variantOptions,
            ], JSON_UNESCAPED_UNICODE);
        }
    ?></script>
    <?php endif; ?>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
