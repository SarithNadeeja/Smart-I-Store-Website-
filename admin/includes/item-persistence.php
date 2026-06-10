<?php

declare(strict_types=1);

/**
 * @param array<string, mixed>|null $existingItem
 * @param array{manage_offer?: bool, require_offer?: bool, preowned?: bool} $options
 */
function admin_save_item_request(PDO $pdo, int $id, ?array $existingItem, array $options = []): int
{
    $manageOffer = !empty($options['manage_offer']);
    $requireOffer = !empty($options['require_offer']);
    $isPreownedSave = !empty($options['preowned']);
    $isUpdate = $id > 0;

    $name = trim($_POST['name'] ?? '');
    $tag = trim($_POST['tag'] ?? '');
    $color = trim($_POST['color'] ?? '#333333');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $brandId = (int) ($_POST['brand_id'] ?? 0);
    $modelId = (int) ($_POST['model_id'] ?? 0);
    $isFeatured = isset($_POST['is_featured']);
    $isActive = isset($_POST['is_active']);
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $stockStatus = store_normalize_stock_status($_POST['stock_status'] ?? 'in_stock');
    $costPrice = max(0, (float) ($_POST['cost_price'] ?? 0));
    $price = (float) ($_POST['price'] ?? 0);
    $stockQuantity = max(0, (int) ($_POST['stock_quantity'] ?? 0));

    // Trade fields (client's Excel sheet). Forms that don't post them keep existing values.
    $productCode = array_key_exists('product_code', $_POST)
        ? trim((string) $_POST['product_code'])
        : trim((string) ($existingItem['product_code'] ?? ''));
    $unit = array_key_exists('unit', $_POST)
        ? trim((string) $_POST['unit'])
        : trim((string) ($existingItem['unit'] ?? ''));
    $note = array_key_exists('note', $_POST)
        ? trim((string) $_POST['note'])
        : trim((string) ($existingItem['note'] ?? ''));

    $parseOptionalPrice = static function (string $key) use ($existingItem): ?float {
        if (array_key_exists($key, $_POST)) {
            $raw = trim((string) $_POST[$key]);
            return $raw === '' ? null : max(0, (float) $raw);
        }
        $existing = $existingItem[$key] ?? null;
        return ($existing !== null && $existing !== '') ? (float) $existing : null;
    };
    $wholesalePrice = $parseOptionalPrice('wholesale_price');
    $minPrice = $parseOptionalPrice('min_price');

    $reorderLevel = array_key_exists('reorder_level', $_POST)
        ? max(0, (int) $_POST['reorder_level'])
        : (int) ($existingItem['reorder_level'] ?? 5);

    if (mb_strlen($productCode) > 64) {
        throw new RuntimeException('Product code is too long (max 64 characters).');
    }

    $preownedCondition = '';
    $batteryHealth = null;
    $isPreowned = false;

    if ($isPreownedSave) {
        if ($isUpdate && $existingItem && empty($existingItem['is_preowned'])) {
            throw new RuntimeException('This listing is not a pre-owned phone.');
        }
        $categoryId = store_get_default_phone_category_id();
        if ($categoryId <= 0) {
            throw new RuntimeException('Add a phone category before listing pre-owned phones.');
        }
        $isPreowned = true;
        $isFeatured = false;
        $preownedCondition = store_normalize_preowned_condition($_POST['preowned_condition'] ?? '');
        if ($preownedCondition === '') {
            throw new RuntimeException('Select a device condition.');
        }
        if (store_brand_is_apple($brandId)) {
            $batteryRaw = trim($_POST['battery_health'] ?? '');
            if ($batteryRaw === '' || !is_numeric($batteryRaw)) {
                throw new RuntimeException('Battery health is required for Apple devices (1–100%).');
            }
            $batteryHealth = (int) $batteryRaw;
            if ($batteryHealth < 1 || $batteryHealth > 100) {
                throw new RuntimeException('Battery health must be between 1 and 100%.');
            }
        }
        if ($tag === '') {
            $tag = 'Pre-Owned';
        }
    } elseif ($isUpdate && $existingItem) {
        $isPreowned = !empty($existingItem['is_preowned']);
        $preownedCondition = trim((string) ($existingItem['preowned_condition'] ?? ''));
        $batteryHealth = isset($existingItem['battery_health']) && $existingItem['battery_health'] !== null && $existingItem['battery_health'] !== ''
            ? (int) $existingItem['battery_health'] : null;
    }

    if ($name === '') {
        throw new RuntimeException('Item name is required.');
    }
    if ($brandId <= 0) {
        throw new RuntimeException('Select a brand.');
    }
    if ($modelId <= 0) {
        throw new RuntimeException('Select a model.');
    }
    if ($categoryId <= 0) {
        throw new RuntimeException('Select a category.');
    }

    $isPhone = $isPreownedSave || store_category_is_phone($categoryId);
    $phoneVariant = store_parse_phone_variant_from_post($_POST);
    $phoneSpecs = store_parse_phone_specs_from_post($_POST);
    $phoneVariants = [];

    if ($isPhone) {
        store_validate_phone_variant($phoneVariant);
        $synced = store_sync_item_pricing_from_variant($phoneVariant);
        $price = $synced['price'];
        $costPrice = $synced['cost_price'];
        $stockStatus = $phoneVariant['stock_status'];
        $stockQuantity = 1;
        $phoneVariants = [$phoneVariant];
    } elseif ($price < 0) {
        throw new RuntimeException('Price must be zero or greater.');
    }

    $salePriceDb = null;
    if ($manageOffer) {
        $salePriceRaw = trim($_POST['sale_price'] ?? $_POST['offer_price'] ?? '');
        if ($salePriceRaw === '') {
            if ($requireOffer) {
                throw new RuntimeException('Offer price is required.');
            }
        } else {
            $salePriceDb = max(0, (float) $salePriceRaw);
            if ($salePriceDb <= 0) {
                if ($requireOffer) {
                    throw new RuntimeException('Offer price is required.');
                }
                $salePriceDb = null;
            }
        }
        if ($salePriceDb !== null && $salePriceDb >= $price) {
            throw new RuntimeException('Offer price must be lower than the retail price.');
        }
        if ($requireOffer && $salePriceDb === null) {
            throw new RuntimeException('Offer price is required.');
        }
        if ($salePriceDb !== null && $tag === '') {
            $tag = 'Offer';
        }
    } elseif ($isUpdate && $existingItem) {
        $salePriceDb = $existingItem['sale_price'] ?? null;
        if ($salePriceDb !== null && $salePriceDb !== '') {
            $salePriceDb = (float) $salePriceDb;
        } else {
            $salePriceDb = null;
        }
    }

    $modelCheck = $pdo->prepare(
        'SELECT category_id FROM product_models WHERE id = :mid AND brand_id = :bid AND is_active = TRUE'
    );
    $modelCheck->execute(['mid' => $modelId, 'bid' => $brandId]);
    $modelRow = $modelCheck->fetch();
    if (!$modelRow) {
        throw new RuntimeException('Selected model does not belong to the chosen brand.');
    }
    $modelCategoryId = (int) ($modelRow['category_id'] ?? 0);
    if ($modelCategoryId > 0 && $modelCategoryId !== $categoryId
        && !($isPreownedSave && store_category_is_phone($modelCategoryId))) {
        throw new RuntimeException('Selected model does not belong to the chosen category.');
    }

    $subFiles = uploads_collect_files($_FILES['sub_images'] ?? []);
    uploads_assert_total_size($_FILES['main_image'] ?? [], $subFiles);

    $mainImage = $existingItem['main_image'] ?? '';
    if (!empty($_FILES['main_image']['name'])) {
        $newMain = uploads_save_image($_FILES['main_image']);
        if ($newMain) {
            uploads_delete_file($mainImage);
            $mainImage = $newMain;
        }
    }

    if ($isUpdate) {
        $stmt = $pdo->prepare(
            'UPDATE items SET category_id = :cid, brand_id = :bid, model_id = :mid, name = :n, price = :p,
             sale_price = :sp, tag = :t, color = :col, is_phone = :ip, is_featured = :if, main_image = :img,
             is_active = :a, sort_order = :s, stock_status = :st, stock_quantity = :qty, cost_price = :cost,
             is_preowned = :ipo, preowned_condition = :pcond, battery_health = :bat,
             product_code = :pcode, unit = :unit, wholesale_price = :wp, min_price = :mp,
             reorder_level = :rl, note = :note
             WHERE id = :id'
        );
        $stmt->execute([
            'cid' => $categoryId,
            'bid' => $brandId,
            'mid' => $modelId,
            'n' => $name,
            'p' => $price,
            'sp' => $salePriceDb,
            't' => $tag,
            'col' => $color,
            'ip' => db_bool($isPhone),
            'if' => db_bool($isFeatured),
            'img' => $mainImage,
            'a' => db_bool($isActive),
            's' => $sortOrder,
            'st' => $stockStatus,
            'qty' => $stockQuantity,
            'cost' => $costPrice,
            'ipo' => db_bool($isPreowned),
            'pcond' => $preownedCondition,
            'bat' => $batteryHealth,
            'pcode' => $productCode,
            'unit' => $unit,
            'wp' => $wholesalePrice,
            'mp' => $minPrice,
            'rl' => $reorderLevel,
            'note' => $note,
            'id' => $id,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO items (category_id, brand_id, model_id, name, price, sale_price, tag, color, is_phone,
             is_featured, main_image, is_active, sort_order, stock_status, stock_quantity, cost_price,
             is_preowned, preowned_condition, battery_health,
             product_code, unit, wholesale_price, min_price, reorder_level, note)
             VALUES (:cid, :bid, :mid, :n, :p, :sp, :t, :col, :ip, :if, :img, :a, :s, :st, :qty, :cost,
             :ipo, :pcond, :bat, :pcode, :unit, :wp, :mp, :rl, :note)
             RETURNING id'
        );
        $stmt->execute([
            'cid' => $categoryId,
            'bid' => $brandId,
            'mid' => $modelId,
            'n' => $name,
            'p' => $price,
            'sp' => $salePriceDb,
            't' => $tag,
            'col' => $color,
            'ip' => db_bool($isPhone),
            'if' => db_bool($isFeatured),
            'img' => $mainImage,
            'a' => db_bool($isActive),
            's' => $sortOrder,
            'st' => $stockStatus,
            'qty' => $stockQuantity,
            'cost' => $costPrice,
            'ipo' => db_bool($isPreowned),
            'pcond' => $preownedCondition,
            'bat' => $batteryHealth,
            'pcode' => $productCode,
            'unit' => $unit,
            'wp' => $wholesalePrice,
            'mp' => $minPrice,
            'rl' => $reorderLevel,
            'note' => $note,
        ]);
        $id = (int) $stmt->fetchColumn();
    }

    if ($subFiles) {
        $maxSub = store_max_sub_images();
        $existingSub = store_count_item_sub_images($id);
        $slotsLeft = $maxSub - $existingSub;

        if ($slotsLeft <= 0) {
            throw new RuntimeException('Maximum of ' . $maxSub . ' sub images allowed per product.');
        }
        if (count($subFiles) > $slotsLeft) {
            throw new RuntimeException(
                'You can add ' . $slotsLeft . ' more sub image(s) (' . $existingSub . '/' . $maxSub . ' already saved).'
            );
        }

        $orderStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM item_images WHERE item_id = :id'
        );
        $orderStmt->execute(['id' => $id]);
        $order = (int) $orderStmt->fetchColumn();

        $ins = $pdo->prepare(
            'INSERT INTO item_images (item_id, image_path, sort_order) VALUES (:item, :path, :ord)'
        );
        $uploaded = 0;
        foreach ($subFiles as $file) {
            if ($uploaded >= $slotsLeft) {
                break;
            }
            $path = uploads_save_image($file);
            if ($path) {
                $ins->execute(['item' => $id, 'path' => $path, 'ord' => $order++]);
                $uploaded++;
            }
        }
    }

    store_replace_item_phone_details($pdo, $id, $isPhone, $phoneVariants, $phoneSpecs);

    return $id;
}

function admin_apply_offer_to_item(PDO $pdo, int $itemId, float $listPrice, float $offerPrice, string $tag = 'Offer'): void
{
    if ($itemId <= 0) {
        throw new RuntimeException('Select an item.');
    }
    if ($listPrice <= 0) {
        throw new RuntimeException('Retail price must be greater than zero.');
    }
    if ($offerPrice <= 0) {
        throw new RuntimeException('Offer price is required.');
    }
    if ($offerPrice >= $listPrice) {
        throw new RuntimeException('Offer price must be lower than the retail price.');
    }

    $tag = trim($tag);
    if ($tag === '') {
        $tag = 'Offer';
    }

    $exists = $pdo->prepare('SELECT id FROM items WHERE id = :id');
    $exists->execute(['id' => $itemId]);
    if (!$exists->fetch()) {
        throw new RuntimeException('Item not found.');
    }

    $stmt = $pdo->prepare(
        'UPDATE items SET price = :p, sale_price = :sp, tag = :t WHERE id = :id'
    );
    $stmt->execute([
        'p' => $listPrice,
        'sp' => $offerPrice,
        't' => $tag,
        'id' => $itemId,
    ]);

    $phoneCheck = $pdo->prepare('SELECT is_phone FROM items WHERE id = :id');
    $phoneCheck->execute(['id' => $itemId]);
    if ((bool) $phoneCheck->fetchColumn()) {
        $pdo->prepare(
            'UPDATE item_storage_variants SET price = :p WHERE item_id = :id'
        )->execute(['p' => $listPrice, 'id' => $itemId]);
    }
}

function admin_remove_item_offer(PDO $pdo, int $itemId): void
{
    if ($itemId <= 0) {
        throw new RuntimeException('Item not found.');
    }
    $pdo->prepare(
        "UPDATE items SET sale_price = NULL, tag = CASE
            WHEN COALESCE(is_preowned, FALSE) = TRUE AND tag IN ('Sale', 'Offer') THEN 'Pre-Owned'
            WHEN tag IN ('Sale', 'Offer') THEN ''
            ELSE tag END
         WHERE id = :id"
    )->execute(['id' => $itemId]);
}

function admin_item_has_active_offer(array $row): bool
{
    $list = (float) ($row['price'] ?? 0);
    $sale = store_parse_sale_price_value($row);

    return $sale !== null && $sale > 0 && $sale < $list;
}

function admin_search_offer_items(PDO $pdo, string $q, int $limit = 12): array
{
    $limit = max(1, min(20, $limit));
    $sql = store_item_select_sql() . ' WHERE i.is_active = TRUE';
    $params = [];

    if ($q !== '') {
        $sql .= ' AND (i.name ILIKE :q OR b.name ILIKE :q OR m.name ILIKE :q
            OR c.title ILIKE :q OR c.description ILIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    $sql .= ' ORDER BY i.name ASC, i.id DESC LIMIT :lim';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $label = $row['name'];
        if (!empty($row['brand_name'])) {
            $label .= ' · ' . $row['brand_name'];
        }
        if (!empty($row['model_name'])) {
            $label .= ' ' . $row['model_name'];
        }
        if (!empty($row['is_preowned'])) {
            $label .= ' · Pre-Owned';
        }

        $list = (float) $row['price'];
        $saleRaw = $row['sale_price'] ?? null;
        $sale = ($saleRaw !== null && $saleRaw !== '') ? (float) $saleRaw : null;
        $tag = trim((string) ($row['tag'] ?? ''));
        if ($tag === '' || is_numeric($tag)) {
            $tag = 'Offer';
        }

        $items[] = [
            'id' => (int) $row['id'],
            'label' => $label,
            'name' => $row['name'],
            'brand_name' => $row['brand_name'] ?? '',
            'model_name' => $row['model_name'] ?? '',
            'category_title' => $row['category_title'] ?? '',
            'is_preowned' => !empty($row['is_preowned']),
            'list_price' => $list,
            'offer_price' => $sale,
            'tag' => $tag,
            'image' => !empty($row['main_image']) ? upload_url($row['main_image']) : '',
        ];
    }

    return $items;
}

function admin_save_preowned_request(PDO $pdo, int $id, ?array $existingItem): int
{
    return admin_save_item_request($pdo, $id, $existingItem, [
        'preowned' => true,
        'manage_offer' => false,
    ]);
}
