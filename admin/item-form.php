<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

admin_require_setup_complete();

$pdo = db();
$id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['item_id'] ?? 0);
}
if ($id <= 0 && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
}
$item = null;
$subImages = [];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch() ?: null;
    if ($item) {
        $imgStmt = $pdo->prepare('SELECT * FROM item_images WHERE item_id = :id ORDER BY sort_order ASC, id ASC');
        $imgStmt->execute(['id' => $id]);
        $subImages = $imgStmt->fetchAll();
    } else {
        $id = 0;
    }
}

$categories = $pdo->query(
    "SELECT id, COALESCE(NULLIF(description, ''), title) AS title FROM categories WHERE is_active = TRUE ORDER BY title ASC"
)->fetchAll();
$brands = $pdo->query('SELECT id, name FROM phone_brands WHERE is_active = TRUE ORDER BY sort_order ASC, name ASC')->fetchAll();
$allModels = $pdo->query(
    'SELECT id, brand_id, name FROM product_models WHERE is_active = TRUE ORDER BY name ASC'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        uploads_assert_post_accepted();
        admin_csrf_verify();

        if (($_POST['action'] ?? '') === 'delete_sub_image') {
            if ($id <= 0) {
                throw new RuntimeException('Item not found.');
            }
            $imgId = (int) ($_POST['image_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT image_path FROM item_images WHERE id = :id AND item_id = :item');
            $stmt->execute(['id' => $imgId, 'item' => $id]);
            $row = $stmt->fetch();
            if ($row) {
                uploads_delete_file($row['image_path']);
                $pdo->prepare('DELETE FROM item_images WHERE id = :id')->execute(['id' => $imgId]);
            }
            admin_flash('success', 'Sub image removed.');
            header('Location: ' . admin_url('item-form.php?id=' . $id));
            exit;
        }

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
        $reorderLevel = max(0, (int) ($_POST['reorder_level'] ?? 5));
        $price = (float) ($_POST['price'] ?? 0);
        $salePriceRaw = trim($_POST['sale_price'] ?? '');
        $salePriceDb = null;
        if ($salePriceRaw !== '') {
            $salePriceDb = max(0, (float) $salePriceRaw);
            if ($salePriceDb <= 0) {
                $salePriceDb = null;
            }
        }
        if ($tag !== '' && is_numeric($tag) && $salePriceDb === null) {
            $legacySale = (float) $tag;
            if ($legacySale > 0 && $legacySale < $price) {
                $salePriceDb = $legacySale;
                $tag = 'Sale';
            }
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

        $isPhone = store_category_is_phone($categoryId);
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
        } else {
            $stockQuantity = max(0, (int) ($_POST['stock_quantity'] ?? 0));
        }
        if (!$isPhone && $price < 0) {
            throw new RuntimeException('Price must be zero or greater.');
        }
        if ($salePriceDb !== null && $salePriceDb >= $price) {
            throw new RuntimeException('Sale price must be lower than the list price.');
        }

        $modelCheck = $pdo->prepare(
            'SELECT id FROM product_models WHERE id = :mid AND brand_id = :bid AND is_active = TRUE'
        );
        $modelCheck->execute(['mid' => $modelId, 'bid' => $brandId]);
        if (!$modelCheck->fetch()) {
            throw new RuntimeException('Selected model does not belong to the chosen brand.');
        }

        $mainImage = $item['main_image'] ?? '';
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
                 reorder_level = :reorder
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
                'ip' => $isPhone,
                'if' => $isFeatured,
                'img' => $mainImage,
                'a' => $isActive,
                's' => $sortOrder,
                'st' => $stockStatus,
                'qty' => $stockQuantity,
                'cost' => $costPrice,
                'reorder' => $reorderLevel,
                'id' => $id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO items (category_id, brand_id, model_id, name, price, sale_price, tag, color, is_phone,
                 is_featured, main_image, is_active, sort_order, stock_status, stock_quantity, cost_price, reorder_level)
                 VALUES (:cid, :bid, :mid, :n, :p, :sp, :t, :col, :ip, :if, :img, :a, :s, :st, :qty, :cost, :reorder)
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
                'ip' => $isPhone,
                'if' => $isFeatured,
                'img' => $mainImage,
                'a' => $isActive,
                's' => $sortOrder,
                'st' => $stockStatus,
                'qty' => $stockQuantity,
                'cost' => $costPrice,
                'reorder' => $reorderLevel,
            ]);
            $id = (int) $stmt->fetchColumn();
        }

        $subFiles = uploads_collect_files($_FILES['sub_images'] ?? []);
        $uploadBytes = 0;
        if (!empty($_FILES['main_image']['name']) && (int) ($_FILES['main_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadBytes += (int) ($_FILES['main_image']['size'] ?? 0);
        }
        foreach ($subFiles as $file) {
            $uploadBytes += (int) ($file['size'] ?? 0);
        }
        if ($uploadBytes > UPLOAD_MAX_TOTAL_BYTES) {
            throw new RuntimeException(
                'Total upload size is too large (max '
                . (int) (UPLOAD_MAX_TOTAL_BYTES / 1024 / 1024)
                . ' MB per save). Upload fewer images or use smaller files.'
            );
        }

        if ($subFiles) {
            if ($id <= 0) {
                throw new RuntimeException('Save the item first, then add sub images.');
            }

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

        $flashMsg = $isUpdate ? 'Item updated.' : 'Item created.';
        if (!empty($subFiles)) {
            $flashMsg = $isUpdate
                ? 'Item updated. Sub images were added to this product.'
                : 'Item created. Sub images were saved.';
        }
        admin_flash('success', $flashMsg);
        header('Location: ' . admin_url('item-form.php?id=' . $id));
        exit;
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
        header('Location: ' . admin_url('item-form.php' . ($id ? '?id=' . $id : '')));
        exit;
    }
}

$stockStatuses = store_stock_statuses();
$currentStock = store_normalize_stock_status($item['stock_status'] ?? 'in_stock');
$maxSubImages = store_max_sub_images();
$subImageCount = count($subImages);
$subSlotsLeft = max(0, $maxSubImages - $subImageCount);
$storageVariants = $id > 0 ? store_get_item_storage_variants($id) : [];
$storageVariant = $storageVariants[0] ?? null;
$systemSpecs = $id > 0 ? store_get_item_system_specs($id) : [];
$initialCategoryId = (int) ($item['category_id'] ?? 0);
$initialIsPhone = $initialCategoryId > 0 && store_category_is_phone($initialCategoryId);

admin_render_header($item ? 'Edit item' : 'Add item', 'items');
?>
<section class="admin-panel admin-panel--wide">
    <h2><?php echo $item ? 'Edit item' : 'Add item'; ?></h2>

    <?php if ($subImages): ?>
    <div class="admin-sub-images admin-sub-images--manage">
        <?php foreach ($subImages as $img): ?>
        <div class="admin-sub-images__item">
            <img src="<?php echo htmlspecialchars(upload_url($img['image_path'])); ?>" alt="">
            <form method="post" class="admin-inline-form"
                  action="<?php echo htmlspecialchars(admin_url('item-form.php?id=' . $id)); ?>">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="item_id" value="<?php echo (int) $id; ?>">
                <input type="hidden" name="action" value="delete_sub_image">
                <input type="hidden" name="image_id" value="<?php echo (int) $img['id']; ?>">
                <button type="submit" class="admin-link-btn admin-link-btn--danger">Remove</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="admin-form" id="item-form"
          action="<?php echo htmlspecialchars(admin_url('item-form.php' . ($id > 0 ? '?id=' . $id : ''))); ?>">
        <?php admin_csrf_field(); ?>
        <?php if ($id > 0): ?>
        <input type="hidden" name="item_id" value="<?php echo (int) $id; ?>">
        <?php endif; ?>

        <div class="admin-field">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $cat): ?>
                <?php $catIsPhone = store_category_is_phone((int) $cat['id']); ?>
                <option value="<?php echo (int) $cat['id']; ?>"
                    data-is-phone="<?php echo $catIsPhone ? '1' : '0'; ?>"
                    <?php echo (int) ($item['category_id'] ?? 0) === (int) $cat['id'] ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['title']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="admin-field">
            <label for="brand_id">Brand</label>
            <select id="brand_id" name="brand_id" required>
                <option value="">Select brand</option>
                <?php foreach ($brands as $brand): ?>
                <option value="<?php echo (int) $brand['id']; ?>"<?php echo (int) ($item['brand_id'] ?? 0) === (int) $brand['id'] ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($brand['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="admin-field">
            <label for="model_id">Model</label>
            <select id="model_id" name="model_id" required>
                <option value="">Select model</option>
            </select>
            <p class="admin-field-note">Add models under <a href="<?php echo admin_url('models.php'); ?>">Model Management</a>.</p>
        </div>

        <div class="admin-field">
            <label for="name">Listing name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" required>
        </div>

        <div class="admin-phone-extras" id="phone-variants-section"<?php echo $initialIsPhone ? '' : ' hidden'; ?>>
            <h3 class="admin-phone-extras__title">Phone / tablet — this unit</h3>
            <p class="admin-field-note">Add one listing per physical phone. Same brand and model with different RAM/ROM appear as storage options on the shop. Create another item for each extra unit.</p>

            <div class="admin-field-row admin-phone-variant-fields">
                <div class="admin-field">
                    <label for="phone_variant_ram">RAM</label>
                    <input type="text" id="phone_variant_ram" name="phone_variant_ram"
                           value="<?php echo htmlspecialchars($storageVariant['ram'] ?? ''); ?>" placeholder="e.g. 8GB">
                </div>
                <div class="admin-field">
                    <label for="phone_variant_rom">ROM</label>
                    <input type="text" id="phone_variant_rom" name="phone_variant_rom"
                           value="<?php echo htmlspecialchars($storageVariant['rom'] ?? ''); ?>" placeholder="e.g. 128GB">
                </div>
                <div class="admin-field">
                    <label for="phone_variant_price">List price (Rs.)</label>
                    <input type="number" id="phone_variant_price" name="phone_variant_price" min="0" step="0.01" required
                           value="<?php echo $storageVariant && $storageVariant['price'] !== null ? htmlspecialchars((string) $storageVariant['price']) : ''; ?>"
                           placeholder="e.g. 200000">
                    <p class="admin-field-note">Normal price on the website.</p>
                </div>
                <div class="admin-field">
                    <label for="sale_price_phone">Sale price (Rs.) <small>optional</small></label>
                    <input type="number" id="sale_price_phone" name="sale_price" min="0" step="0.01"
                           value="<?php
                           $saleVal = $item['sale_price'] ?? null;
                           echo $saleVal !== null && $saleVal !== '' ? htmlspecialchars((string) $saleVal) : '';
                           ?>"
                           placeholder="e.g. 180000">
                    <p class="admin-field-note">Lower than list price — old price shown crossed out.</p>
                </div>
                <div class="admin-field">
                    <label for="phone_variant_cost">Your cost (Rs.) <small>not shown on website</small></label>
                    <input type="number" id="phone_variant_cost" name="phone_variant_cost" min="0" step="0.01" required
                           value="<?php echo htmlspecialchars((string) ($storageVariant['cost_price'] ?? 0)); ?>"
                           placeholder="What you paid">
                    <p class="admin-field-note">For POS profit only. Customers never see this.</p>
                </div>
                <div class="admin-field">
                    <label for="phone_variant_stock">Stock</label>
                    <select id="phone_variant_stock" name="phone_variant_stock">
                        <?php
                        $variantStock = $storageVariant['stock_status'] ?? $currentStock;
                        foreach ($stockStatuses as $value => $label):
                        ?>
                        <option value="<?php echo htmlspecialchars($value); ?>"<?php echo $variantStock === $value ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="admin-field-row" id="item-stock-standard">
            <div class="admin-field" id="item-stock-status-field">
                <label for="stock_status">Stock status</label>
                <select id="stock_status" name="stock_status" required>
                    <?php foreach ($stockStatuses as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>"<?php echo $currentStock === $value ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-field" id="item-stock-qty-field">
                <label for="stock_quantity">Quantity in stock</label>
                <input type="number" id="stock_quantity" name="stock_quantity" min="0" step="1"
                       value="<?php echo (int) ($item['stock_quantity'] ?? 0); ?>" required>
                <p class="admin-field-note">For accessories and other items. Phones are always one unit per listing.</p>
            </div>
            <div class="admin-field" id="item-cost-field">
                <label for="cost_price">Cost price (Rs.) <small>for POS profit</small></label>
                <input type="number" id="cost_price" name="cost_price" min="0" step="0.01"
                       value="<?php echo htmlspecialchars((string) ($item['cost_price'] ?? 0)); ?>">
            </div>
            <div class="admin-field">
                <label for="reorder_level">Reorder level</label>
                <input type="number" id="reorder_level" name="reorder_level" min="0" step="1"
                       value="<?php echo (int) ($item['reorder_level'] ?? 5); ?>">
                <p class="admin-field-note">Minimum stock before low-stock alerts (e.g. reorder when qty ≤ this).</p>
            </div>
        </div>

        <div class="admin-field-row" id="item-sale-price-row">
            <div class="admin-field">
                <label for="sale_price">Sale price (Rs.) <small>optional</small></label>
                <input type="number" id="sale_price" name="sale_price" min="0" step="0.01"
                       value="<?php
                       $saleVal = $item['sale_price'] ?? null;
                       echo $saleVal !== null && $saleVal !== '' ? htmlspecialchars((string) $saleVal) : '';
                       ?>"
                       placeholder="e.g. 180000">
                <p class="admin-field-note">Must be lower than list price. Shown with the old price crossed out.</p>
            </div>
        </div>

        <div class="admin-field-row" id="item-pricing-standard">
            <div class="admin-field">
                <label for="price">List price (Rs.)</label>
                <input type="number" id="price" name="price" min="0" step="0.01" value="<?php echo htmlspecialchars((string) ($item['price'] ?? '0')); ?>" required>
                <p class="admin-field-note">Normal price on the website.</p>
            </div>
        </div>

        <div class="admin-field-row" id="item-display-extras">
            <div class="admin-field">
                <label for="tag">Badge label <small>(e.g. New, Hot)</small></label>
                <input type="text" id="tag" name="tag" value="<?php
                    $tagVal = trim($item['tag'] ?? '');
                    echo htmlspecialchars(is_numeric($tagVal) ? 'Sale' : $tagVal);
                ?>">
            </div>
            <div class="admin-field">
                <label for="color">Accent color</label>
                <input type="color" id="color" name="color" value="<?php echo htmlspecialchars($item['color'] ?? '#333333'); ?>">
            </div>
        </div>

        <div class="admin-field">
            <label for="main_image">Main image</label>
            <?php if (!empty($item['main_image'])): ?>
            <img class="admin-preview" src="<?php echo htmlspecialchars(upload_url($item['main_image'])); ?>" alt="">
            <?php endif; ?>
            <input type="file" id="main_image" name="main_image" accept="image/*" data-upload-max-mb="15">
            <p class="admin-field-note">Max 15 MB per image. Server allows up to <?php echo htmlspecialchars(ini_get('upload_max_filesize') ?: '?'); ?> per file, <?php echo htmlspecialchars(ini_get('post_max_size') ?: '?'); ?> per form.</p>
        </div>

        <div class="admin-phone-extras">
            <h3 class="admin-phone-extras__title">Product detail lines</h3>
            <p class="admin-field-note">Optional bullet list on the product page (features, specs, warranty notes, etc.). Available for every category.</p>

            <div class="admin-phone-block">
                <div class="admin-phone-block__head">
                    <h4>Detail lines</h4>
                    <button type="button" class="btn btn-ghost btn-sm" id="add-spec-row">Add detail line</button>
                </div>
                <div class="admin-repeat-list" id="spec-rows">
                    <?php if ($systemSpecs): ?>
                    <?php foreach ($systemSpecs as $spec): ?>
                    <div class="admin-repeat-row admin-spec-row">
                        <div class="admin-field admin-field--grow">
                            <label>Detail</label>
                            <input type="text" name="phone_spec_text[]" value="<?php echo htmlspecialchars($spec['text']); ?>"
                                   placeholder="e.g. One-year warranty · Free delivery">
                        </div>
                        <button type="button" class="admin-repeat-remove" aria-label="Remove detail">&times;</button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="admin-field" id="sub-images-field">
            <label for="sub_images">
                Sub images
                <small>(<?php echo $subImageCount; ?>/<?php echo $maxSubImages; ?> max)</small>
            </label>
            <?php if ($subSlotsLeft > 0): ?>
            <input type="file" id="sub_images" name="sub_images[]" accept="image/*" multiple data-max="<?php echo $subSlotsLeft; ?>" data-upload-max-mb="15">
            <p class="admin-field-note">You can add up to <?php echo $subSlotsLeft; ?> more image<?php echo $subSlotsLeft === 1 ? '' : 's'; ?>. Keep each file under 15 MB.</p>
            <?php else: ?>
            <p class="admin-field-note">Maximum of <?php echo $maxSubImages; ?> sub images reached. Remove one to upload another.</p>
            <?php endif; ?>
        </div>

        <div class="admin-field">
            <label for="sort_order">Sort order</label>
            <input type="number" id="sort_order" name="sort_order" value="<?php echo (int) ($item['sort_order'] ?? 0); ?>">
        </div>

        <label class="admin-check">
            <input type="checkbox" name="is_featured"<?php echo (!empty($item['is_featured'])) ? ' checked' : ''; ?>>
            Featured on home page
        </label>
        <label class="admin-check">
            <input type="checkbox" name="is_active"<?php echo ($item === null || !empty($item['is_active'])) ? ' checked' : ''; ?>>
            Active (visible on website)
        </label>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $item ? 'Update item' : 'Create item'; ?></button>
            <a href="<?php echo admin_url('items.php'); ?>" class="btn btn-ghost">Back to list</a>
        </div>
    </form>
</section>
<script>
(function () {
    var models = <?php echo json_encode($allModels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var brandSelect = document.getElementById('brand_id');
    var modelSelect = document.getElementById('model_id');
    var nameInput = document.getElementById('name');
    var selectedModel = <?php echo (int) ($item['model_id'] ?? 0); ?>;

    function modelsForBrand(brandId) {
        return models.filter(function (m) { return String(m.brand_id) === String(brandId); });
    }

    function fillModels() {
        var brandId = brandSelect.value;
        var list = brandId ? modelsForBrand(brandId) : [];
        var keep = modelSelect.value;
        modelSelect.innerHTML = '<option value="">Select model</option>';
        modelSelect.disabled = !brandId;
        if (!brandId) {
            modelSelect.value = '';
            return;
        }
        list.forEach(function (m) {
            var opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.name;
            modelSelect.appendChild(opt);
        });
        if (keep && list.some(function (m) { return String(m.id) === keep; })) {
            modelSelect.value = keep;
        } else if (selectedModel && list.some(function (m) { return String(m.id) === String(selectedModel); })) {
            modelSelect.value = String(selectedModel);
            selectedModel = 0;
        }
    }

    function suggestName() {
        if (nameInput.value.trim() !== '') return;
        var brandOpt = brandSelect.options[brandSelect.selectedIndex];
        var modelOpt = modelSelect.options[modelSelect.selectedIndex];
        if (!brandOpt || !modelOpt || !brandSelect.value || !modelSelect.value) return;
        nameInput.value = brandOpt.textContent.trim() + ' ' + modelOpt.textContent.trim();
    }

    brandSelect.addEventListener('change', function () {
        modelSelect.value = '';
        fillModels();
    });
    modelSelect.addEventListener('change', suggestName);
    fillModels();

    var MAX_FILE_MB = 15;
    var MAX_TOTAL_MB = 48;

    function uploadSizeMessage(files, label) {
        if (!files || !files.length) return '';
        var total = 0;
        for (var i = 0; i < files.length; i++) {
            var mb = files[i].size / (1024 * 1024);
            total += files[i].size;
            if (mb > MAX_FILE_MB) {
                return label + ': "' + files[i].name + '" is ' + mb.toFixed(1) + ' MB (max ' + MAX_FILE_MB + ' MB per file).';
            }
        }
        if (total / (1024 * 1024) > MAX_TOTAL_MB) {
            return label + ': total size is too large (max ' + MAX_TOTAL_MB + ' MB per save).';
        }
        return '';
    }

    var itemForm = document.getElementById('item-form');
    if (itemForm) {
        itemForm.addEventListener('submit', function (e) {
            var mainInput = document.getElementById('main_image');
            var subInput = document.getElementById('sub_images');
            var msg = '';
            if (mainInput && mainInput.files) {
                msg = uploadSizeMessage(mainInput.files, 'Main image');
            }
            if (!msg && subInput && subInput.files) {
                msg = uploadSizeMessage(subInput.files, 'Sub images');
            }
            if (msg) {
                e.preventDefault();
                alert(msg);
            }
        });
    }

    var subInput = document.getElementById('sub_images');
    if (subInput) {
        var maxAdd = parseInt(subInput.getAttribute('data-max') || '0', 10);
        subInput.addEventListener('change', function () {
            if (maxAdd <= 0 || !this.files) return;
            if (this.files.length > maxAdd) {
                alert('You can only add ' + maxAdd + ' more sub image(s). Maximum ' + <?php echo $maxSubImages; ?> + ' per product.');
                this.value = '';
                return;
            }
            var msg = uploadSizeMessage(this.files, 'Sub images');
            if (msg) {
                alert(msg);
                this.value = '';
            }
        });
    }

    var mainImageInput = document.getElementById('main_image');
    if (mainImageInput) {
        mainImageInput.addEventListener('change', function () {
            var msg = uploadSizeMessage(this.files, 'Main image');
            if (msg) {
                alert(msg);
                this.value = '';
            }
        });
    }

    var stockStatuses = <?php echo json_encode($stockStatuses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var categorySelect = document.getElementById('category_id');
    var phoneVariantsSection = document.getElementById('phone-variants-section');
    var itemPricingStandard = document.getElementById('item-pricing-standard');
    var itemSalePriceRow = document.getElementById('item-sale-price-row');
    var itemCostField = document.getElementById('item-cost-field');
    var itemStockStatusField = document.getElementById('item-stock-status-field');
    var itemStockQtyField = document.getElementById('item-stock-qty-field');
    var priceInput = document.getElementById('price');
    var specRows = document.getElementById('spec-rows');
    var addSpecBtn = document.getElementById('add-spec-row');

    function stockOptionsHtml(selected) {
        var html = '';
        Object.keys(stockStatuses).forEach(function (key) {
            var sel = selected === key ? ' selected' : '';
            html += '<option value="' + key + '"' + sel + '>' + stockStatuses[key] + '</option>';
        });
        return html;
    }

    function syncPhoneVariantsVisibility() {
        if (!categorySelect) return;
        var opt = categorySelect.options[categorySelect.selectedIndex];
        var isPhone = !!(opt && opt.getAttribute('data-is-phone') === '1');
        if (phoneVariantsSection) phoneVariantsSection.hidden = !isPhone;
        if (itemPricingStandard) itemPricingStandard.hidden = isPhone;
        if (itemSalePriceRow) itemSalePriceRow.hidden = isPhone;
        if (itemCostField) itemCostField.hidden = isPhone;
        if (itemStockStatusField) itemStockStatusField.hidden = isPhone;
        if (itemStockQtyField) itemStockQtyField.hidden = isPhone;
        if (priceInput) {
            priceInput.required = !isPhone;
            priceInput.disabled = isPhone;
        }
        var phoneListPrice = document.getElementById('phone_variant_price');
        if (phoneListPrice) {
            phoneListPrice.required = isPhone;
            phoneListPrice.disabled = !isPhone;
        }
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', syncPhoneVariantsVisibility);
        syncPhoneVariantsVisibility();
    }

    function bindRemoveButtons(root) {
        if (!root) return;
        root.querySelectorAll('.admin-repeat-remove').forEach(function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                var row = btn.closest('.admin-repeat-row');
                if (row) row.remove();
            });
        });
    }

    function addSpecRow() {
        if (!specRows) return;
        var row = document.createElement('div');
        row.className = 'admin-repeat-row admin-spec-row';
        row.innerHTML =
            '<div class="admin-field admin-field--grow"><label>Detail</label>' +
            '<input type="text" name="phone_spec_text[]" placeholder="e.g. Immersive 6.88″ display"></div>' +
            '<button type="button" class="admin-repeat-remove" aria-label="Remove detail">&times;</button>';
        specRows.appendChild(row);
        bindRemoveButtons(row);
    }

    if (addSpecBtn) addSpecBtn.addEventListener('click', addSpecRow);
    bindRemoveButtons(specRows);
})();
</script>
<?php
admin_render_footer();
