<?php
/**
 * @var array $phone Product row from store
 * @var int $i Optional index for animation delay
 */
$i = $i ?? 0;
$brandName = trim($phone['brand'] ?? '');
$brandAttr = htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8');
$brandId = (int) ($phone['brand_id'] ?? 0);
$modelName = trim($phone['model'] ?? '');
$modelId = (int) ($phone['model_id'] ?? 0);
$modelAttr = htmlspecialchars($modelName, ENT_QUOTES, 'UTF-8');
$categoryId = (int) ($phone['category_id'] ?? 0);
$stockStatus = store_normalize_stock_status($phone['stock_status'] ?? 'in_stock');
$delay = ($i % 4) * 0.08;
$cardHidden = !empty($product_card_hidden ?? false);
$showOfferAd = !empty($product_card_offer ?? false);
$showPreowned = !empty($product_card_preowned ?? false) || !empty($phone['is_preowned']);
$preownedConditionLabel = $showPreowned
    ? store_preowned_condition_label($phone['preowned_condition'] ?? '')
    : '';
$offerDiscount = (int) ($phone['offer_discount_percent'] ?? 0);
if ($showOfferAd && $offerDiscount <= 0 && !empty($phone['on_sale'])) {
    $listForPct = (float) ($phone['list_price'] ?? $phone['price'] ?? 0);
    $currentForPct = (float) ($phone['current_price'] ?? $phone['price'] ?? 0);
    $offerDiscount = store_offer_discount_percent($listForPct, $currentForPct);
}
$hasImage = !empty($phone['image']);
$imgUrl = $hasImage ? upload_url($phone['image']) : '';
$detailUrl = page_url('product.php?id=' . (int) ($phone['id'] ?? 0));
$searchBlob = htmlspecialchars(store_item_search_blob($phone), ENT_QUOTES, 'UTF-8');
?>
<article class="product-card glass-card reveal-up<?php echo $cardHidden ? ' is-hidden' : ''; ?>"
         data-brand="<?php echo $brandAttr; ?>"
         data-brand-id="<?php echo $brandId; ?>"
         data-model="<?php echo $modelAttr; ?>"
         data-model-id="<?php echo $modelId; ?>"
         data-stock="<?php echo htmlspecialchars($stockStatus, ENT_QUOTES, 'UTF-8'); ?>"
         data-category-id="<?php echo $categoryId; ?>"
         data-price="<?php echo (float) ($phone['price'] ?? 0); ?>"
         data-search="<?php echo $searchBlob; ?>"
         <?php if ($showPreowned): ?>data-preowned-condition="<?php echo htmlspecialchars(store_normalize_preowned_condition($phone['preowned_condition'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
         data-delay="<?php echo $delay; ?>">
    <?php if ($showPreowned && $preownedConditionLabel !== ''): ?>
    <span class="product-preowned-label">Pre-Owned</span>
    <span class="product-preowned-condition"><?php echo htmlspecialchars($preownedConditionLabel); ?></span>
    <?php endif; ?>
    <?php if ($showOfferAd && $offerDiscount > 0): ?>
    <span class="product-offer-label">Offer</span>
    <span class="product-offer-discount"><?php echo $offerDiscount; ?>% OFF</span>
    <?php elseif (!$showPreowned && !empty($phone['tag']) && empty($phone['on_sale'])): ?>
    <span class="product-tag"><?php echo htmlspecialchars($phone['tag']); ?></span>
    <?php endif; ?>
    <?php if ($stockStatus !== 'in_stock'): ?>
    <span class="product-stock product-stock--<?php echo htmlspecialchars($stockStatus); ?>">
        <?php echo htmlspecialchars($phone['stock_label'] ?? ''); ?>
    </span>
    <?php endif; ?>
    <div class="product-image" style="--product-accent: <?php echo htmlspecialchars($phone['color'] ?? '#333'); ?>">
        <?php if ($hasImage): ?>
        <img class="product-image-photo" src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($phone['name']); ?>" loading="lazy">
        <?php else: ?>
        <div class="product-image-placeholder" role="img" aria-label="<?php echo htmlspecialchars($phone['name']); ?>">
            <?php if (!empty($phone['brand'])): ?>
            <span class="product-brand"><?php echo htmlspecialchars($phone['brand']); ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="product-info">
        <h3 class="product-name"><?php echo htmlspecialchars($phone['name']); ?></h3>
        <?php if (!empty($phone['meta'])): ?>
        <p class="product-meta"><?php echo htmlspecialchars($phone['meta']); ?></p>
        <?php endif; ?>
        <?php if ($showPreowned && !empty($phone['battery_health'])): ?>
        <p class="product-meta product-meta--battery">Battery: <?php echo (int) $phone['battery_health']; ?>%</p>
        <?php endif; ?>
        <p class="product-price"><?php
            $cardPrefix = !empty($phone['price_from']) ? 'From ' : '';
            $cardCurrent = (float) ($phone['current_price'] ?? $phone['price']);
            $cardList = !empty($phone['on_sale']) ? (float) ($phone['list_price'] ?? $phone['price']) : null;
            echo store_format_price_display($cardCurrent, $cardList, $cardPrefix);
        ?></p>
        <div class="product-actions">
            <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="btn btn-primary btn-sm">Buy Now</a>
            <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="btn btn-ghost btn-sm">View</a>
        </div>
    </div>
</article>
