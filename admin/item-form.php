<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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
        admin_csrf_verify();

        if (($_POST['action'] ?? '') === 'delete_sub_image') {
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

        $name = trim($_POST['name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $tag = trim($_POST['tag'] ?? '');
        $color = trim($_POST['color'] ?? '#333333');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $brandId = (int) ($_POST['brand_id'] ?? 0);
        $modelId = (int) ($_POST['model_id'] ?? 0);
        $isFeatured = isset($_POST['is_featured']);
        $isActive = isset($_POST['is_active']);
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $stockStatus = store_normalize_stock_status($_POST['stock_status'] ?? 'in_stock');

        if ($name === '') {
            throw new RuntimeException('Item name is required.');
        }
        if ($price < 0) {
            throw new RuntimeException('Price must be zero or greater.');
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

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE items SET category_id = :cid, brand_id = :bid, model_id = :mid, name = :n, price = :p, tag = :t,
                 color = :col, is_phone = :ip, is_featured = :if, main_image = :img, is_active = :a, sort_order = :s,
                 stock_status = :st
                 WHERE id = :id'
            );
            $stmt->execute([
                'cid' => $categoryId,
                'bid' => $brandId,
                'mid' => $modelId,
                'n' => $name,
                'p' => $price,
                't' => $tag,
                'col' => $color,
                'ip' => $isPhone,
                'if' => $isFeatured,
                'img' => $mainImage,
                'a' => $isActive,
                's' => $sortOrder,
                'st' => $stockStatus,
                'id' => $id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO items (category_id, brand_id, model_id, name, price, tag, color, is_phone, is_featured,
                 main_image, is_active, sort_order, stock_status)
                 VALUES (:cid, :bid, :mid, :n, :p, :t, :col, :ip, :if, :img, :a, :s, :st) RETURNING id'
            );
            $stmt->execute([
                'cid' => $categoryId,
                'bid' => $brandId,
                'mid' => $modelId,
                'n' => $name,
                'p' => $price,
                't' => $tag,
                'col' => $color,
                'ip' => $isPhone,
                'if' => $isFeatured,
                'img' => $mainImage,
                'a' => $isActive,
                's' => $sortOrder,
                'st' => $stockStatus,
            ]);
            $id = (int) $stmt->fetchColumn();
        }

        if (!empty($_FILES['sub_images']['name'][0])) {
            $maxSub = store_max_sub_images();
            $existingSub = $id > 0 ? store_count_item_sub_images($id) : 0;
            $slotsLeft = $maxSub - $existingSub;

            if ($slotsLeft <= 0) {
                throw new RuntimeException('Maximum of ' . $maxSub . ' sub images allowed per product.');
            }

            $uploaded = 0;
            $fileCount = count($_FILES['sub_images']['name']);
            $order = $existingSub;

            for ($i = 0; $i < $fileCount && $uploaded < $slotsLeft; $i++) {
                if (($_FILES['sub_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $file = [
                    'name' => $_FILES['sub_images']['name'][$i],
                    'type' => $_FILES['sub_images']['type'][$i],
                    'tmp_name' => $_FILES['sub_images']['tmp_name'][$i],
                    'error' => $_FILES['sub_images']['error'][$i],
                    'size' => $_FILES['sub_images']['size'][$i],
                ];
                $path = uploads_save_image($file);
                if ($path) {
                    $ins = $pdo->prepare(
                        'INSERT INTO item_images (item_id, image_path, sort_order) VALUES (:item, :path, :ord)'
                    );
                    $ins->execute(['item' => $id, 'path' => $path, 'ord' => $order++]);
                    $uploaded++;
                }
            }
        }

        $phoneVariants = store_parse_phone_variants_from_post($_POST);
        $phoneSpecs = store_parse_phone_specs_from_post($_POST);
        store_replace_item_phone_details($pdo, $id, $isPhone, $phoneVariants, $phoneSpecs);

        admin_flash('success', $item ? 'Item updated.' : 'Item created.');
        header('Location: ' . admin_url('items.php'));
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
            <form method="post" class="admin-inline-form">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
                <input type="hidden" name="action" value="delete_sub_image">
                <input type="hidden" name="image_id" value="<?php echo (int) $img['id']; ?>">
                <button type="submit" class="admin-link-btn admin-link-btn--danger">Remove</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="admin-form" id="item-form">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">

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

        <div class="admin-field">
            <label for="stock_status">Stock status</label>
            <select id="stock_status" name="stock_status" required>
                <?php foreach ($stockStatuses as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value); ?>"<?php echo $currentStock === $value ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="admin-field-row">
            <div class="admin-field">
                <label for="price">Price (Rs.)</label>
                <input type="number" id="price" name="price" min="0" step="0.01" value="<?php echo htmlspecialchars((string) ($item['price'] ?? '0')); ?>" required>
            </div>
            <div class="admin-field">
                <label for="tag">Tag <small>(e.g. New, Sale)</small></label>
                <input type="text" id="tag" name="tag" value="<?php echo htmlspecialchars($item['tag'] ?? ''); ?>">
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
            <input type="file" id="main_image" name="main_image" accept="image/*">
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

        <div class="admin-phone-extras" id="phone-variants-section"<?php echo $initialIsPhone ? '' : ' hidden'; ?>>
            <h3 class="admin-phone-extras__title">Mobile phone — storage variants</h3>
            <p class="admin-field-note">Shown only for phone categories. Add multiple RAM/ROM options for the same model.</p>

            <div class="admin-phone-block">
                <div class="admin-phone-block__head">
                    <h4>RAM / ROM variants</h4>
                    <button type="button" class="btn btn-ghost btn-sm" id="add-variant-row">Add variant</button>
                </div>
                <div class="admin-repeat-list" id="variant-rows">
                    <?php if ($storageVariants): ?>
                    <?php foreach ($storageVariants as $variant): ?>
                    <div class="admin-repeat-row admin-variant-row">
                        <div class="admin-field">
                            <label>RAM</label>
                            <input type="text" name="phone_variant_ram[]" value="<?php echo htmlspecialchars($variant['ram']); ?>" placeholder="e.g. 8GB">
                        </div>
                        <div class="admin-field">
                            <label>ROM</label>
                            <input type="text" name="phone_variant_rom[]" value="<?php echo htmlspecialchars($variant['rom']); ?>" placeholder="e.g. 128GB">
                        </div>
                        <div class="admin-field">
                            <label>Price (Rs.) <small>optional</small></label>
                            <input type="number" name="phone_variant_price[]" min="0" step="0.01"
                                   value="<?php echo $variant['price'] !== null ? htmlspecialchars((string) $variant['price']) : ''; ?>"
                                   placeholder="Listing price if empty">
                        </div>
                        <div class="admin-field">
                            <label>Stock</label>
                            <select name="phone_variant_stock[]">
                                <?php foreach ($stockStatuses as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>"<?php echo $variant['stock_status'] === $value ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="admin-repeat-remove" aria-label="Remove variant">&times;</button>
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
            <input type="file" id="sub_images" name="sub_images[]" accept="image/*" multiple data-max="<?php echo $subSlotsLeft; ?>">
            <p class="admin-field-note">You can add up to <?php echo $subSlotsLeft; ?> more image<?php echo $subSlotsLeft === 1 ? '' : 's'; ?>.</p>
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
        var list = modelsForBrand(brandId);
        var keep = modelSelect.value;
        modelSelect.innerHTML = '<option value="">Select model</option>';
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

    var subInput = document.getElementById('sub_images');
    if (subInput) {
        var maxAdd = parseInt(subInput.getAttribute('data-max') || '0', 10);
        subInput.addEventListener('change', function () {
            if (maxAdd <= 0 || !this.files) return;
            if (this.files.length > maxAdd) {
                alert('You can only add ' + maxAdd + ' more sub image(s). Maximum ' + <?php echo $maxSubImages; ?> + ' per product.');
                this.value = '';
            }
        });
    }

    var stockStatuses = <?php echo json_encode($stockStatuses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var categorySelect = document.getElementById('category_id');
    var phoneVariantsSection = document.getElementById('phone-variants-section');
    var variantRows = document.getElementById('variant-rows');
    var specRows = document.getElementById('spec-rows');
    var addVariantBtn = document.getElementById('add-variant-row');
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
        if (!categorySelect || !phoneVariantsSection) return;
        var opt = categorySelect.options[categorySelect.selectedIndex];
        phoneVariantsSection.hidden = !(opt && opt.getAttribute('data-is-phone') === '1');
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

    function addVariantRow() {
        if (!variantRows) return;
        var row = document.createElement('div');
        row.className = 'admin-repeat-row admin-variant-row';
        row.innerHTML =
            '<div class="admin-field"><label>RAM</label><input type="text" name="phone_variant_ram[]" placeholder="e.g. 8GB"></div>' +
            '<div class="admin-field"><label>ROM</label><input type="text" name="phone_variant_rom[]" placeholder="e.g. 128GB"></div>' +
            '<div class="admin-field"><label>Price (Rs.) <small>optional</small></label><input type="number" name="phone_variant_price[]" min="0" step="0.01" placeholder="Listing price if empty"></div>' +
            '<div class="admin-field"><label>Stock</label><select name="phone_variant_stock[]">' + stockOptionsHtml('in_stock') + '</select></div>' +
            '<button type="button" class="admin-repeat-remove" aria-label="Remove variant">&times;</button>';
        variantRows.appendChild(row);
        bindRemoveButtons(row);
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

    if (addVariantBtn) addVariantBtn.addEventListener('click', addVariantRow);
    if (addSpecBtn) addSpecBtn.addEventListener('click', addSpecRow);
    bindRemoveButtons(variantRows);
    bindRemoveButtons(specRows);
})();
</script>
<?php
admin_render_footer();
