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
        'headphones' => 'Headphones / Accessories',
        'watch' => 'Smart Watch',
        'tablet' => 'Tablet',
    ];
}

function store_map_item_row(array $row): array
{
    $brand = $row['brand_name'] ?? '';
    $model = $row['model_name'] ?? '';
    $meta = $brand;
    if ($model !== '') {
        $meta = $meta !== '' ? $brand . ' · ' . $model : $model;
    }

    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'brand' => $brand,
        'brand_id' => isset($row['brand_id']) ? (int) $row['brand_id'] : 0,
        'model' => $model,
        'model_id' => isset($row['model_id']) ? (int) $row['model_id'] : 0,
        'meta' => $meta,
        'price' => (float) $row['price'],
        'tag' => $row['tag'] !== '' ? $row['tag'] : 'New',
        'image' => $row['main_image'] ?? '',
        'color' => $row['color'] ?? '#333333',
        'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
        'is_phone' => !empty($row['is_phone']),
        'stock_status' => store_normalize_stock_status($row['stock_status'] ?? 'in_stock'),
        'stock_label' => store_stock_label(store_normalize_stock_status($row['stock_status'] ?? 'in_stock')),
        'stock_quantity' => max(0, (int) ($row['stock_quantity'] ?? 0)),
    ];
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

function store_get_featured_phones(int $limit = 4): array
{
    if (!db_available()) {
        return [];
    }

    $sql = store_item_select_sql() . "
        WHERE i.is_active = TRUE AND i.is_phone = TRUE
        ORDER BY i.is_featured DESC, i.sort_order ASC, i.id DESC
        LIMIT :lim
    ";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return array_map('store_map_item_row', $stmt->fetchAll());
}

function store_get_all_products(): array
{
    if (!db_available()) {
        return [];
    }

    $sql = store_item_select_sql() . "
        WHERE i.is_active = TRUE
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
        $product['storage_variants'] = store_get_item_storage_variants($id);
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
        'SELECT id, ram, rom, price, stock_status, sort_order
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
    if (isset($variant['price']) && $variant['price'] !== null && $variant['price'] !== '') {
        return (float) $variant['price'];
    }
    return $basePrice;
}

function store_parse_phone_variants_from_post(array $source): array
{
    $rams = $source['phone_variant_ram'] ?? [];
    $roms = $source['phone_variant_rom'] ?? [];
    $prices = $source['phone_variant_price'] ?? [];
    $stocks = $source['phone_variant_stock'] ?? [];

    if (!is_array($rams)) {
        return [];
    }

    $variants = [];
    $count = count($rams);

    for ($i = 0; $i < $count; $i++) {
        $ram = trim((string) ($rams[$i] ?? ''));
        $rom = trim((string) ($roms[$i] ?? ''));
        if ($ram === '' && $rom === '') {
            continue;
        }

        $priceRaw = trim((string) ($prices[$i] ?? ''));
        $price = $priceRaw === '' ? null : max(0, (float) $priceRaw);
        $stock = store_normalize_stock_status((string) ($stocks[$i] ?? 'in_stock'));

        $variants[] = [
            'ram' => $ram,
            'rom' => $rom,
            'price' => $price,
            'stock_status' => $stock,
            'sort_order' => count($variants),
        ];
    }

    return $variants;
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

    if ($isPhone) {
        $variantStmt = $pdo->prepare(
            'INSERT INTO item_storage_variants (item_id, ram, rom, price, stock_status, sort_order)
             VALUES (:item, :ram, :rom, :price, :stock, :ord)'
        );
        foreach ($variants as $variant) {
            $variantStmt->execute([
                'item' => $itemId,
                'ram' => $variant['ram'],
                'rom' => $variant['rom'],
                'price' => $variant['price'],
                'stock' => $variant['stock_status'],
                'ord' => $variant['sort_order'],
            ]);
        }
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
    $basePrice = (float) ($product['price'] ?? 0);
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
        WHERE i.is_active = TRUE AND i.category_id = :cid
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
        $products = store_get_products_by_category((int) $cat['id'], $limitPerCategory);
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
    if (($row['icon'] ?? '') === 'smartphone') {
        return true;
    }
    $label = strtolower(trim(($row['description'] ?? '') . ' ' . ($row['title'] ?? '')));
    return str_contains($label, 'phone') || str_contains($label, 'smartphone');
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
