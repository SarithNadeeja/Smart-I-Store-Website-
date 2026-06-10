<?php

require_once __DIR__ . '/database.php';

function upload_url(string $path): string
{
    return asset_url('uploads/' . ltrim($path, '/'));
}

function store_stock_statuses(): array
{
    return [
        'in_stock' => 'In stock',
        'out_of_stock' => 'Out of stock',
        'pre_order' => 'Pre order',
    ];
}

function store_stock_label(string $status): string
{
    $statuses = store_stock_statuses();
    return $statuses[$status] ?? 'In stock';
}

function store_normalize_stock_status(string $status): string
{
    $statuses = store_stock_statuses();
    return array_key_exists($status, $statuses) ? $status : 'in_stock';
}

function store_icon_options(): array
{
    return [
        'smartphone' => 'Smartphone',
        'headphones' => 'Headphones',
        'accessories' => 'Accessories',
        'watch' => 'Smart Watch',
        'tablet' => 'Tablet',
    ];
}

function store_parse_sale_price_value(array $row): ?float
{
    if (array_key_exists('sale_price', $row) && $row['sale_price'] !== null && $row['sale_price'] !== '') {
        $sale = (float) $row['sale_price'];
        if ($sale > 0) {
            return $sale;
        }
    }

    $tag = trim((string) ($row['tag'] ?? ''));
    if ($tag !== '' && is_numeric($tag)) {
        $legacy = (float) $tag;
        $list = (float) ($row['price'] ?? 0);
        if ($legacy > 0 && $legacy < $list) {
            return $legacy;
        }
    }

    return null;
}

function store_apply_item_pricing(array $item, array $row): array
{
    $listPrice = (float) ($row['price'] ?? $item['price'] ?? 0);
    $salePrice = store_parse_sale_price_value($row);
    $onSale = $salePrice !== null && $salePrice < $listPrice;

    $item['price'] = $listPrice;
    $item['list_price'] = $listPrice;
    $item['sale_price'] = $onSale ? $salePrice : null;
    $item['current_price'] = $onSale ? (float) $salePrice : $listPrice;
    $item['on_sale'] = $onSale;

    if ($onSale && $salePrice !== null) {
        $item['offer_discount_percent'] = store_offer_discount_percent($listPrice, $salePrice);
    } else {
        $item['offer_discount_percent'] = 0;
    }

    $tag = trim((string) ($row['tag'] ?? ''));
    if ($tag !== '' && !is_numeric($tag)) {
        $item['tag'] = $tag;
    } elseif ($onSale) {
        $item['tag'] = 'Offer';
    } else {
        $item['tag'] = $tag !== '' ? $tag : 'New';
    }

    return $item;
}

function store_map_item_row(array $row): array
{
    $brand = $row['brand_name'] ?? '';
    $model = $row['model_name'] ?? '';
    $meta = $brand;
    if ($model !== '') {
        $meta = $meta !== '' ? $brand . ' · ' . $model : $model;
    }

    $item = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'brand' => $brand,
        'brand_id' => isset($row['brand_id']) ? (int) $row['brand_id'] : 0,
        'model' => $model,
        'model_id' => isset($row['model_id']) ? (int) $row['model_id'] : 0,
        'meta' => $meta,
        'price' => (float) $row['price'],
        'image' => $row['main_image'] ?? '',
        'color' => $row['color'] ?? '#333333',
        'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
        'is_phone' => !empty($row['is_phone']),
        'stock_status' => store_normalize_stock_status($row['stock_status'] ?? 'in_stock'),
        'stock_label' => store_stock_label(store_normalize_stock_status($row['stock_status'] ?? 'in_stock')),
        'stock_quantity' => max(0, (int) ($row['stock_quantity'] ?? 0)),
        'tag' => $row['tag'] ?? '',
        'is_preowned' => !empty($row['is_preowned']),
        'preowned_condition' => trim((string) ($row['preowned_condition'] ?? '')),
        'battery_health' => isset($row['battery_health']) && $row['battery_health'] !== null && $row['battery_health'] !== ''
            ? (int) $row['battery_health'] : null,
    ];

    return store_apply_item_pricing($item, $row);
}

function store_sql_exclude_preowned(string $alias = 'i'): string
{
    return ' COALESCE(' . $alias . '.is_preowned, FALSE) = FALSE';
}

function store_preowned_conditions(): array
{
    return [
        'mint' => 'Mint condition',
        'excellent' => 'Excellent (top condition)',
        'good' => 'Good condition',
        'fair' => 'Fair condition',
    ];
}

function store_normalize_preowned_condition(string $value): string
{
    $value = strtolower(trim($value));
    $allowed = store_preowned_conditions();

    return array_key_exists($value, $allowed) ? $value : '';
}

function store_preowned_condition_label(string $code): string
{
    $allowed = store_preowned_conditions();

    return $allowed[$code] ?? $code;
}

function store_brand_is_apple(int $brandId): bool
{
    if ($brandId <= 0 || !db_available()) {
        return false;
    }
    $stmt = db()->prepare('SELECT name FROM phone_brands WHERE id = :id');
    $stmt->execute(['id' => $brandId]);
    $name = strtolower(trim((string) $stmt->fetchColumn()));

    return $name === 'apple' || str_starts_with($name, 'apple ');
}

function store_get_default_phone_category_id(): int
{
    if (!db_available()) {
        return 0;
    }
    $stmt = db()->query(
        'SELECT id FROM categories WHERE is_active = TRUE ORDER BY sort_order ASC, id ASC'
    );
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['id'];
        if (store_category_is_phone($id)) {
            return $id;
        }
    }

    return 0;
}

function store_get_preowned_phones(): array
{
    if (!db_available()) {
        return [];
    }

    $sql = store_item_select_sql() . '
        WHERE i.is_active = TRUE AND i.is_preowned = TRUE
        ORDER BY i.sort_order ASC, i.id DESC';

    return array_map('store_map_item_row', db()->query($sql)->fetchAll());
}

function store_format_price_display(float $currentPrice, ?float $listPrice = null, string $prefix = ''): string
{
    $showSale = $listPrice !== null && $listPrice > $currentPrice;
    $html = '<span class="product-price-row">';
    if ($prefix !== '') {
        $html .= '<span class="product-price-prefix">' . htmlspecialchars($prefix) . '</span>';
    }
    if ($showSale) {
        $html .= '<span class="product-price--was" aria-label="Original price">Rs. '
            . number_format($listPrice, 0) . '</span>';
        $html .= '<span class="product-price--now" aria-label="Sale price">Rs. '
            . number_format($currentPrice, 0) . '</span>';
    } else {
        $html .= '<span class="product-price--now">Rs. ' . number_format($currentPrice, 0) . '</span>';
    }
    $html .= '</span>';

    return $html;
}

function store_item_select_sql(): string
{
    return "
        SELECT i.*,
               b.name AS brand_name,
               m.name AS model_name,
               COALESCE(NULLIF(c.description, ''), c.title) AS category_title
        FROM items i
        LEFT JOIN phone_brands b ON b.id = i.brand_id
        LEFT JOIN product_models m ON m.id = i.model_id
        LEFT JOIN categories c ON c.id = i.category_id
    ";
}

function store_offer_discount_percent(float $listPrice, float $offerPrice): int
{
    if ($listPrice <= 0 || $offerPrice <= 0 || $offerPrice >= $listPrice) {
        return 0;
    }

    return (int) round((1 - $offerPrice / $listPrice) * 100);
}

function store_get_flagship_offers(int $limit = 12): array
{
    if (!db_available()) {
        return [];
    }

    $limit = max(1, min(24, $limit));
    $sql = store_item_select_sql() . '
        WHERE i.is_active = TRUE
          AND i.sale_price IS NOT NULL
          AND i.sale_price > 0
          AND i.sale_price < i.price
        ORDER BY i.sort_order ASC, i.id DESC
        LIMIT :lim';
    $stmt = db()->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return array_map('store_map_item_row', $stmt->fetchAll());
}

function store_get_featured_phones(int $limit = 4): array
{
    if (!db_available()) {
        return [];
    }

    $sql = store_item_select_sql() . "
        WHERE i.is_active = TRUE AND i.is_phone = TRUE AND" . store_sql_exclude_preowned() . "
        ORDER BY i.is_featured DESC, i.sort_order ASC, i.id DESC
        LIMIT :lim
    ";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(':lim', max($limit * 6, $limit), PDO::PARAM_INT);
    $stmt->execute();

    $rows = store_group_products_for_listing(array_map('store_map_item_row', $stmt->fetchAll()));

    return array_slice($rows, 0, $limit);
}

function store_get_all_products(): array
{
    if (!db_available()) {
        return [];
    }

    $sql = store_item_select_sql() . "
        WHERE i.is_active = TRUE AND" . store_sql_exclude_preowned() . "
        ORDER BY i.sort_order ASC, i.id DESC
    ";

    return array_map('store_map_item_row', db()->query($sql)->fetchAll());
}

function store_get_product(int $id): ?array
{
    if (!db_available() || $id <= 0) {
        return null;
    }

    $sql = store_item_select_sql() . ' WHERE i.id = :id AND i.is_active = TRUE';
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $product = store_map_item_row($row);
    $product['category_title'] = trim($row['category_title'] ?? '');

    $images = [];
    if (!empty($product['image'])) {
        $images[] = $product['image'];
    }
    foreach (store_get_item_sub_images($id) as $path) {
        if ($path !== '' && !in_array($path, $images, true)) {
            $images[] = $path;
        }
    }
    $product['images'] = $images;

    if (!empty($product['is_phone'])) {
        $product['storage_variants'] = store_get_phone_model_variants($id);
    } else {
        $product['storage_variants'] = [];
    }

    $product['system_specs'] = store_get_item_system_specs($id);

    return $product;
}

function store_get_item_storage_variants(int $itemId): array
{
    if (!db_available() || $itemId <= 0) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT id, ram, rom, price, cost_price, stock_status, sort_order
         FROM item_storage_variants
         WHERE item_id = :id
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['id' => $itemId]);

    return array_map(static function (array $row): array {
        $price = $row['price'];
        return [
            'id' => (int) $row['id'],
            'ram' => trim($row['ram'] ?? ''),
            'rom' => trim($row['rom'] ?? ''),
            'price' => $price !== null && $price !== '' ? (float) $price : null,
            'cost_price' => (float) ($row['cost_price'] ?? 0),
            'stock_status' => store_normalize_stock_status($row['stock_status'] ?? 'in_stock'),
            'stock_label' => store_stock_label(store_normalize_stock_status($row['stock_status'] ?? 'in_stock')),
            'sort_order' => (int) $row['sort_order'],
            'label' => store_format_storage_variant_label(
                trim($row['ram'] ?? ''),
                trim($row['rom'] ?? '')
            ),
        ];
    }, $stmt->fetchAll());
}

function store_get_item_system_specs(int $itemId): array
{
    if (!db_available() || $itemId <= 0) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT id, spec_text, sort_order
         FROM item_system_specs
         WHERE item_id = :id
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['id' => $itemId]);

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'text' => trim($row['spec_text'] ?? ''),
            'sort_order' => (int) $row['sort_order'],
        ];
    }, $stmt->fetchAll());
}

function store_format_storage_variant_label(string $ram, string $rom): string
{
    $parts = array_filter([$ram, $rom], static fn(string $v): bool => $v !== '');
    if (!$parts) {
        return 'Standard';
    }
    if ($ram !== '' && $rom !== '') {
        return $ram . ' RAM / ' . $rom . ' ROM';
    }
    return implode(' / ', $parts);
}

function store_variant_effective_price(array $variant, float $basePrice): float
{
    if (!empty($variant['current_price'])) {
        return (float) $variant['current_price'];
    }
    if (isset($variant['price']) && $variant['price'] !== null && $variant['price'] !== '') {
        $list = (float) $variant['price'];
        $sale = store_parse_sale_price_value($variant);
        if ($sale !== null && $sale < $list) {
            return $sale;
        }
        return $list;
    }
    $sale = store_parse_sale_price_value(['price' => $basePrice, 'sale_price' => $variant['sale_price'] ?? null, 'tag' => $variant['tag'] ?? '']);
    if ($sale !== null && $sale < $basePrice) {
        return $sale;
    }
    return $basePrice;
}

function store_variant_effective_cost(array $variant, float $baseCost): float
{
    if (isset($variant['cost_price']) && $variant['cost_price'] !== null && $variant['cost_price'] !== '') {
        return (float) $variant['cost_price'];
    }
    return $baseCost;
}

function store_get_phone_model_variants(int $itemId): array
{
    if (!db_available() || $itemId <= 0) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT brand_id, model_id, is_phone, is_preowned FROM items WHERE id = :id'
    );
    $stmt->execute(['id' => $itemId]);
    $anchor = $stmt->fetch();
    if (!$anchor || empty($anchor['is_phone'])) {
        return [];
    }

    if (!empty($anchor['is_preowned'])) {
        return store_map_storage_variant_rows(store_get_item_storage_variants($itemId), $itemId);
    }

    $brandId = (int) ($anchor['brand_id'] ?? 0);
    $modelId = (int) ($anchor['model_id'] ?? 0);
    if ($brandId <= 0 || $modelId <= 0) {
        return store_map_storage_variant_rows(store_get_item_storage_variants($itemId), $itemId);
    }

    $stmt = db()->prepare(
        'SELECT i.id AS item_id, i.price, i.sale_price, i.cost_price, i.stock_status, i.sort_order, i.tag,
                sv.ram, sv.rom, sv.price AS variant_price, sv.cost_price AS variant_cost
         FROM items i
         LEFT JOIN item_storage_variants sv ON sv.item_id = i.id
         WHERE i.is_active = TRUE AND i.is_phone = TRUE
           AND COALESCE(i.is_preowned, FALSE) = FALSE
           AND i.brand_id = :bid AND i.model_id = :mid
         ORDER BY i.sort_order ASC, i.id ASC, sv.sort_order ASC, sv.id ASC'
    );
    $stmt->execute(['bid' => $brandId, 'mid' => $modelId]);

    $byItem = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['item_id'];
        if (isset($byItem[$id])) {
            continue;
        }
        $ram = trim($row['ram'] ?? '');
        $rom = trim($row['rom'] ?? '');
        $itemPrice = (float) ($row['price'] ?? 0);
        $variantPrice = $row['variant_price'];
        $price = $variantPrice !== null && $variantPrice !== ''
            ? (float) $variantPrice
            : $itemPrice;
        $cost = $row['variant_cost'] !== null && $row['variant_cost'] !== ''
            ? (float) $row['variant_cost']
            : (float) ($row['cost_price'] ?? 0);
        $stockStatus = store_normalize_stock_status($row['stock_status'] ?? 'in_stock');

        $variantRow = [
            'item_id' => $id,
            'id' => $id,
            'ram' => $ram,
            'rom' => $rom,
            'price' => $price,
            'cost_price' => $cost,
            'stock_status' => $stockStatus,
            'stock_label' => store_stock_label($stockStatus),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'label' => store_format_storage_variant_label($ram, $rom),
            'is_current' => $id === $itemId,
            'tag' => $row['tag'] ?? '',
            'sale_price' => $row['sale_price'] ?? null,
        ];
        $byItem[$id] = store_apply_item_pricing($variantRow, [
            'price' => $price,
            'sale_price' => $row['sale_price'] ?? null,
            'tag' => $row['tag'] ?? '',
        ]);
        $byItem[$id]['price'] = $price;
    }

    return array_values($byItem);
}

function store_map_storage_variant_rows(array $variants, int $currentItemId): array
{
    return array_map(static function (array $variant) use ($currentItemId): array {
        $itemId = (int) ($variant['item_id'] ?? $currentItemId);
        return array_merge($variant, [
            'item_id' => $itemId,
            'id' => $itemId,
            'is_current' => $itemId === $currentItemId,
        ]);
    }, $variants);
}

function store_group_products_for_listing(array $products): array
{
    $grouped = [];
    $indexByKey = [];

    foreach ($products as $product) {
        if (!empty($product['is_preowned'])) {
            $grouped[] = $product;
            continue;
        }

        if (empty($product['is_phone'])) {
            $grouped[] = $product;
            continue;
        }

        $brandId = (int) ($product['brand_id'] ?? 0);
        $modelId = (int) ($product['model_id'] ?? 0);
        if ($brandId <= 0 || $modelId <= 0) {
            $grouped[] = $product;
            continue;
        }

        $key = $brandId . ':' . $modelId;
        if (!isset($indexByKey[$key])) {
            $product['price_from'] = false;
            $indexByKey[$key] = count($grouped);
            $grouped[] = $product;
            continue;
        }

        $idx = $indexByKey[$key];
        $grouped[$idx]['price_from'] = true;
        if ((float) ($product['current_price'] ?? $product['price'] ?? 0)
            < (float) ($grouped[$idx]['current_price'] ?? $grouped[$idx]['price'] ?? 0)) {
            $grouped[$idx] = array_merge($grouped[$idx], [
                'price' => $product['price'],
                'list_price' => $product['list_price'] ?? $product['price'],
                'sale_price' => $product['sale_price'] ?? null,
                'current_price' => $product['current_price'] ?? $product['price'],
                'on_sale' => !empty($product['on_sale']),
                'id' => $product['id'],
                'image' => $product['image'] ?? $grouped[$idx]['image'],
                'tag' => $product['tag'] ?? $grouped[$idx]['tag'],
            ]);
        }
    }

    return $grouped;
}

function store_validate_phone_variant(?array $variant): void
{
    if (!$variant) {
        throw new RuntimeException('Enter RAM or ROM, sell price, and cost price for this unit.');
    }

    if (($variant['ram'] ?? '') === '' && ($variant['rom'] ?? '') === '') {
        throw new RuntimeException('Enter RAM or ROM for this unit.');
    }
    $price = $variant['price'] ?? null;
    if ($price === null || (float) $price <= 0) {
        throw new RuntimeException('Sell price is required.');
    }
    if (!array_key_exists('cost_price', $variant) || $variant['cost_price'] === null) {
        throw new RuntimeException('Cost price is required.');
    }
    if ((float) $variant['cost_price'] < 0) {
        throw new RuntimeException('Cost price cannot be negative.');
    }
}

function store_sync_item_pricing_from_variant(array $variant): array
{
    return [
        'price' => (float) ($variant['price'] ?? 0),
        'cost_price' => (float) ($variant['cost_price'] ?? 0),
    ];
}

function store_parse_phone_variant_from_post(array $source): ?array
{
    $ram = trim((string) ($source['phone_variant_ram'] ?? ''));
    $rom = trim((string) ($source['phone_variant_rom'] ?? ''));
    if ($ram === '' && $rom === '') {
        return null;
    }

    $priceRaw = trim((string) ($source['phone_variant_price'] ?? ''));
    $price = $priceRaw === '' ? null : max(0, (float) $priceRaw);
    $costRaw = trim((string) ($source['phone_variant_cost'] ?? ''));
    $costPrice = $costRaw === '' ? null : max(0, (float) $costRaw);
    $stock = store_normalize_stock_status((string) ($source['phone_variant_stock'] ?? 'in_stock'));
    $quantityRaw = trim((string) ($source['phone_variant_quantity'] ?? ''));
    $quantity = $quantityRaw === '' ? 1 : max(0, (int) $quantityRaw);

    return [
        'ram' => $ram,
        'rom' => $rom,
        'price' => $price,
        'cost_price' => $costPrice,
        'stock_status' => $stock,
        'quantity' => $quantity,
        'sort_order' => 0,
    ];
}

function store_parse_phone_specs_from_post(array $source): array
{
    $lines = $source['phone_spec_text'] ?? [];
    if (!is_array($lines)) {
        return [];
    }

    $specs = [];
    foreach ($lines as $line) {
        $text = trim((string) $line);
        if ($text === '') {
            continue;
        }
        $specs[] = [
            'text' => $text,
            'sort_order' => count($specs),
        ];
    }

    return $specs;
}

function store_replace_item_phone_details(PDO $pdo, int $itemId, bool $isPhone, array $variants, array $specs): void
{
    $pdo->prepare('DELETE FROM item_storage_variants WHERE item_id = :id')->execute(['id' => $itemId]);
    $pdo->prepare('DELETE FROM item_system_specs WHERE item_id = :id')->execute(['id' => $itemId]);

    if ($isPhone && $variants) {
        $variant = $variants[0];
        $pdo->prepare(
            'INSERT INTO item_storage_variants (item_id, ram, rom, price, cost_price, stock_status, sort_order)
             VALUES (:item, :ram, :rom, :price, :cost, :stock, 0)'
        )->execute([
            'item' => $itemId,
            'ram' => $variant['ram'],
            'rom' => $variant['rom'],
            'price' => $variant['price'],
            'cost' => $variant['cost_price'] ?? 0,
            'stock' => $variant['stock_status'],
        ]);
    }

    $specStmt = $pdo->prepare(
        'INSERT INTO item_system_specs (item_id, spec_text, sort_order)
         VALUES (:item, :text, :ord)'
    );
    foreach ($specs as $spec) {
        $specStmt->execute([
            'item' => $itemId,
            'text' => $spec['text'],
            'ord' => $spec['sort_order'],
        ]);
    }
}

function store_whatsapp_order_message(array $product, ?array $variant = null): string
{
    $basePrice = (float) ($product['current_price'] ?? $product['price'] ?? 0);
    $orderPrice = $variant ? store_variant_effective_price($variant, $basePrice) : $basePrice;

    $lines = [
        'Hello! I would like to order from ' . SITE_NAME . ':',
        '',
        'Product: ' . ($product['name'] ?? ''),
        'Price: Rs. ' . number_format($orderPrice, 0),
    ];

    if ($variant) {
        $variantLabel = $variant['label'] ?? store_format_storage_variant_label(
            $variant['ram'] ?? '',
            $variant['rom'] ?? ''
        );
        if ($variantLabel !== '') {
            $lines[] = 'Storage: ' . $variantLabel;
        }
        if (!empty($variant['stock_label'])) {
            $lines[] = 'Variant stock: ' . $variant['stock_label'];
        }
    }

    if (!empty($product['meta'])) {
        $lines[] = 'Details: ' . $product['meta'];
    }
    if (!empty($product['category_title'])) {
        $lines[] = 'Category: ' . $product['category_title'];
    }
    if (!$variant && !empty($product['stock_label'])) {
        $lines[] = 'Stock: ' . $product['stock_label'];
    }

    $lines[] = '';
    $lines[] = 'Link: ' . page_url('product.php?id=' . (int) ($product['id'] ?? 0));
    $lines[] = '';
    $lines[] = 'Please confirm availability. Thank you!';

    return implode("\n", $lines);
}

function store_get_categories(): array
{
    if (!db_available()) {
        return [];
    }

    $sql = "
        SELECT c.*, COUNT(i.id) FILTER (WHERE i.is_active = TRUE) AS item_count
        FROM categories c
        LEFT JOIN items i ON i.category_id = c.id
        WHERE c.is_active = TRUE
        GROUP BY c.id
        ORDER BY c.sort_order ASC, c.id ASC
    ";

    $rows = db()->query($sql)->fetchAll();
    $out = [];

    foreach ($rows as $row) {
        $count = (int) $row['item_count'];
        $label = $count === 1 ? '1 item' : $count . ' items';

        $name = trim($row['description']) !== '' ? $row['description'] : $row['title'];

        $out[] = [
            'id' => (int) $row['id'],
            'title' => $name,
            'desc' => '',
            'icon' => $row['icon'],
            'count' => $label,
        ];
    }

    return $out;
}

function store_get_products_by_category(int $categoryId, int $limit = 50): array
{
    if (!db_available() || $categoryId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $sql = store_item_select_sql() . "
        WHERE i.is_active = TRUE AND i.category_id = :cid AND" . store_sql_exclude_preowned() . "
        ORDER BY i.sort_order ASC, i.id DESC
        LIMIT :lim
    ";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(':cid', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return array_map('store_map_item_row', $stmt->fetchAll());
}

/**
 * Homepage: each active category that has items, with its products for a carousel.
 */
function store_get_home_category_slides(int $limitPerCategory = 50): array
{
    $slides = [];

    foreach (store_get_categories() as $cat) {
        $products = store_group_products_for_listing(
            store_get_products_by_category((int) $cat['id'], $limitPerCategory)
        );
        if ($products === []) {
            continue;
        }
        $slides[] = [
            'category' => $cat,
            'products' => $products,
        ];
    }

    return $slides;
}

/** Active customer comments, newest first. */
function store_get_site_comments(int $limit = 30): array
{
    if (!db_available()) {
        return [];
    }

    $limit = max(1, min(60, $limit));
    $stmt = db()->prepare(
        'SELECT id, name, comment, created_at
         FROM customer_reviews
         WHERE is_active = TRUE
         ORDER BY created_at DESC, id DESC
         LIMIT :lim'
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function store_add_site_comment(string $name, string $comment): void
{
    $name = trim($name);
    $comment = trim($comment);

    if ($name === '') {
        throw new RuntimeException('Please enter your name.');
    }
    if (mb_strlen($name) > 60) {
        throw new RuntimeException('Name is too long (max 60 characters).');
    }
    if ($comment === '') {
        throw new RuntimeException('Please write a comment.');
    }
    if (mb_strlen($comment) < 3) {
        throw new RuntimeException('Comment is too short.');
    }
    if (mb_strlen($comment) > 500) {
        throw new RuntimeException('Comment is too long (max 500 characters).');
    }
    if (!db_available()) {
        throw new RuntimeException('Comments are temporarily unavailable. Please try again later.');
    }

    $stmt = db()->prepare(
        'INSERT INTO customer_reviews (name, comment, rating, is_active) VALUES (:n, :c, 5, TRUE)'
    );
    $stmt->execute(['n' => $name, 'c' => $comment]);
}

function store_get_brand_names(): array
{
    if (!db_available()) {
        return [];
    }

    $stmt = db()->query(
        'SELECT name FROM phone_brands WHERE is_active = TRUE ORDER BY sort_order ASC, name ASC'
    );

    return array_column($stmt->fetchAll(), 'name');
}

/**
 * Chatbot: brands with id + name (from database only).
 */
function store_get_bot_brands(): array
{
    if (!db_available()) {
        return [];
    }

    $stmt = db()->query(
        'SELECT id, name FROM phone_brands WHERE is_active = TRUE ORDER BY sort_order ASC, name ASC'
    );

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
        ];
    }, $stmt->fetchAll());
}

/**
 * Chatbot accessories flow: active categories that are not classified as "phone".
 */
function store_get_bot_accessory_categories(): array
{
    if (!db_available()) {
        return [];
    }

    $stmt = db()->query(
        'SELECT id, title, description FROM categories WHERE is_active = TRUE ORDER BY sort_order ASC, title ASC'
    );
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['id'];
        if (store_category_is_phone($id)) {
            continue;
        }
        $title = trim((string) ($row['description'] ?? '')) !== ''
            ? trim((string) $row['description'])
            : trim((string) $row['title']);
        if ($title === '') {
            continue;
        }
        $out[] = ['id' => $id, 'title' => $title];
    }

    return $out;
}

/**
 * Bot search: phones — is_phone, brand, max price.
 */
function store_bot_search_phones(int $brandId, float $maxPrice, int $limit = 20): array
{
    if (!db_available() || $brandId <= 0 || $maxPrice <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $sql = store_item_select_sql() . '
        WHERE i.is_active = TRUE
          AND i.is_phone = TRUE
          AND i.brand_id = :bid
          AND i.price <= :pmax
        ORDER BY i.sort_order ASC, i.price ASC, i.id DESC
        LIMIT :lim
    ';
    $stmt = db()->prepare($sql);
    $stmt->bindValue(':bid', $brandId, PDO::PARAM_INT);
    $stmt->bindValue(':pmax', $maxPrice);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return store_bot_map_products($stmt->fetchAll());
}

/**
 * Bot search: by category + brand + max price (accessories and non-phone listings).
 */
function store_bot_search_accessories(int $categoryId, int $brandId, float $maxPrice, int $limit = 20): array
{
    if (!db_available() || $categoryId <= 0 || $brandId <= 0 || $maxPrice <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $sql = store_item_select_sql() . '
        WHERE i.is_active = TRUE
          AND i.category_id = :cid
          AND i.brand_id = :bid
          AND i.price <= :pmax
        ORDER BY i.sort_order ASC, i.price ASC, i.id DESC
        LIMIT :lim
    ';
    $stmt = db()->prepare($sql);
    $stmt->bindValue(':cid', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':bid', $brandId, PDO::PARAM_INT);
    $stmt->bindValue(':pmax', $maxPrice);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return store_bot_map_products($stmt->fetchAll());
}

function store_bot_map_products(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $m = store_map_item_row($row);
        $out[] = [
            'id' => $m['id'],
            'name' => $m['name'],
            'price' => $m['price'],
            'url' => page_url('product.php?id=' . $m['id']),
        ];
    }

    return $out;
}

function store_category_is_phone(int $categoryId): bool
{
    $stmt = db()->prepare('SELECT title, description, icon FROM categories WHERE id = :id');
    $stmt->execute(['id' => $categoryId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    // Icon is the explicit category type — only smartphones and tablets get variants.
    $icon = (string) ($row['icon'] ?? '');
    if ($icon === 'smartphone' || $icon === 'tablet') {
        return true;
    }
    if ($icon === 'headphones' || $icon === 'accessories' || $icon === 'watch') {
        return false;
    }

    // Legacy categories without a known icon: match whole words only,
    // so "Headphones" does not match "phone".
    $label = strtolower(trim(($row['description'] ?? '') . ' ' . ($row['title'] ?? '')));
    return (bool) preg_match('/\b(smart\s*phones?|phones?|tablets?)\b/', $label)
        && !str_contains($label, 'headphone')
        && !str_contains($label, 'earphone');
}

function store_max_sub_images(): int
{
    return 4;
}

function store_count_item_sub_images(int $itemId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM item_images WHERE item_id = :id');
    $stmt->execute(['id' => $itemId]);
    return (int) $stmt->fetchColumn();
}

function store_get_item_sub_images(int $itemId): array
{
    $stmt = db()->prepare(
        'SELECT image_path FROM item_images WHERE item_id = :id ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['id' => $itemId]);
    return array_column($stmt->fetchAll(), 'image_path');
}

/**
 * Delete a product and all uploaded images (main + sub images) from disk.
 *
 * @return int Number of image files removed
 */
function store_delete_item(int $itemId): int
{
    require_once __DIR__ . '/uploads.php';

    if ($itemId <= 0) {
        throw new RuntimeException('Item not found.');
    }
    if (!db_available()) {
        throw new RuntimeException('Database unavailable.');
    }

    $paths = uploads_item_image_paths($itemId);
    $stmt = db()->prepare('DELETE FROM items WHERE id = :id');
    $stmt->execute(['id' => $itemId]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Item not found.');
    }

    uploads_delete_files($paths);

    return count($paths);
}

function store_dashboard_stats(): array
{
    if (!db_available()) {
        return ['categories' => 0, 'brands' => 0, 'models' => 0, 'items' => 0, 'users' => 0];
    }

    $pdo = db();
    return [
        'categories' => (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
        'brands' => (int) $pdo->query('SELECT COUNT(*) FROM phone_brands')->fetchColumn(),
        'models' => (int) $pdo->query('SELECT COUNT(*) FROM product_models')->fetchColumn(),
        'items' => (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn(),
        'users' => (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn(),
    ];
}
