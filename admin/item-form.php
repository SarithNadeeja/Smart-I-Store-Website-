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
    'SELECT id, brand_id, category_id, name FROM product_models WHERE is_active = TRUE ORDER BY name ASC'
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

        if ($id <= 0) {
            $lookupCategoryId = (int) ($_POST['category_id'] ?? 0);
            $lookupBrandId = (int) ($_POST['brand_id'] ?? 0);
            $lookupModelId = (int) ($_POST['model_id'] ?? 0);
            $lookupIsPhone = store_category_is_phone($lookupCategoryId);
            $foundId = admin_find_item_by_catalog(
                $pdo,
                $lookupCategoryId,
                $lookupBrandId,
                $lookupModelId,
                $lookupIsPhone,
                trim((string) ($_POST['phone_variant_ram'] ?? '')),
                trim((string) ($_POST['phone_variant_rom'] ?? ''))
            );
            if ($foundId > 0) {
                $id = $foundId;
                $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $item = $stmt->fetch() ?: null;
                $isUpdate = $item !== null;
            }
        }

        $id = admin_save_item_request($pdo, $id, $item, ['manage_offer' => false]);

        $subFiles = uploads_collect_files($_FILES['sub_images'] ?? []);
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
$uploadStatus = uploads_status();

admin_render_header($item ? 'Edit item' : 'Add item', 'items');
?>
<section class="admin-panel admin-panel--wide">
    <h2><?php echo $item ? 'Edit item' : 'Add item'; ?></h2>
    <p class="admin-field-note">Set the retail price here. Discounts and promotional pricing are managed under <a href="<?php echo admin_url('offers.php'); ?>">Offers</a>.</p>
    <?php if (!$uploadStatus['ok']): ?>
    <div class="admin-alert admin-alert--error"><?php echo htmlspecialchars($uploadStatus['message']); ?></div>
    <?php endif; ?>

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
        <input type="hidden" name="item_id" id="item_id" value="<?php echo (int) $id; ?>">

        <div class="admin-alert admin-alert--success" id="existing-item-notice" hidden>
            This product already exists — fields below were filled from the saved listing. Saving will <strong>update</strong> it, not create a duplicate.
        </div>
        <div class="admin-existing-media" id="existing-item-media" hidden></div>

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

        <div class="admin-phone-extras">
            <h3 class="admin-phone-extras__title">Trade details <small>(internal — never shown to customers)</small></h3>
            <div class="admin-field-row">
                <div class="admin-field">
                    <label for="product_code">Product code</label>
                    <input type="text" id="product_code" name="product_code" maxlength="64"
                           value="<?php echo htmlspecialchars($item['product_code'] ?? ''); ?>" placeholder="e.g. CHL 40w">
                </div>
                <div class="admin-field">
                    <label for="unit">Unit</label>
                    <input type="text" id="unit" name="unit" maxlength="32"
                           value="<?php echo htmlspecialchars($item['unit'] ?? ''); ?>" placeholder="e.g. 2 PCS">
                </div>
                <div class="admin-field">
                    <label for="reorder_level">Stock alert</label>
                    <input type="number" id="reorder_level" name="reorder_level" min="0" step="1"
                           value="<?php echo (int) ($item['reorder_level'] ?? 5); ?>">
                    <p class="admin-field-note">POS warns when stock falls to this level.</p>
                </div>
            </div>
            <div class="admin-field-row">
                <div class="admin-field">
                    <label for="wholesale_price">Wholesale price (Rs.)</label>
                    <input type="number" id="wholesale_price" name="wholesale_price" min="0" step="0.01"
                           value="<?php echo isset($item['wholesale_price']) && $item['wholesale_price'] !== null && $item['wholesale_price'] !== '' ? htmlspecialchars((string) $item['wholesale_price']) : ''; ?>">
                </div>
                <div class="admin-field">
                    <label for="min_price">Min price (Rs.)</label>
                    <input type="number" id="min_price" name="min_price" min="0" step="0.01"
                           value="<?php echo isset($item['min_price']) && $item['min_price'] !== null && $item['min_price'] !== '' ? htmlspecialchars((string) $item['min_price']) : ''; ?>">
                    <p class="admin-field-note">Lowest allowed selling price.</p>
                </div>
            </div>
            <div class="admin-field">
                <label for="note">Note</label>
                <textarea id="note" name="note" rows="2" placeholder="Internal note about this item"><?php echo htmlspecialchars($item['note'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="admin-phone-extras" id="phone-variants-section"<?php echo $initialIsPhone ? '' : ' hidden'; ?>>
            <h3 class="admin-phone-extras__title">Phone / tablet — this variant</h3>
            <p class="admin-field-note">Same brand and model with different RAM/ROM appear as storage options on the shop. Use Quantity when you have several identical phones of this variant.</p>

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
                    <label for="phone_variant_price">Retail price (Rs.)</label>
                    <input type="number" id="phone_variant_price" name="phone_variant_price" min="0" step="0.01" required
                           value="<?php echo $storageVariant && $storageVariant['price'] !== null ? htmlspecialchars((string) $storageVariant['price']) : ''; ?>"
                           placeholder="e.g. 200000">
                    <p class="admin-field-note">Price shown to customers on the website.</p>
                </div>
                <div class="admin-field">
                    <label for="phone_variant_cost">Purchase cost (Rs.) <small>internal only</small></label>
                    <input type="number" id="phone_variant_cost" name="phone_variant_cost" min="0" step="0.01" required
                           value="<?php echo htmlspecialchars((string) ($storageVariant['cost_price'] ?? 0)); ?>"
                           placeholder="What you paid">
                    <p class="admin-field-note">Used for POS profit tracking. Never shown to customers.</p>
                </div>
                <div class="admin-field">
                    <label for="phone_variant_quantity">Quantity</label>
                    <input type="number" id="phone_variant_quantity" name="phone_variant_quantity" min="0" step="1"
                           value="<?php echo $item ? max(0, (int) ($item['stock_quantity'] ?? 1)) : 1; ?>">
                    <p class="admin-field-note">How many identical phones of this variant are in stock.</p>
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
                <p class="admin-field-note">For accessories and other items. Phone quantity is set inside the variant section above.</p>
            </div>
            <div class="admin-field" id="item-cost-field">
                <label for="cost_price">Purchase cost (Rs.) <small>internal only</small></label>
                <input type="number" id="cost_price" name="cost_price" min="0" step="0.01"
                       value="<?php echo htmlspecialchars((string) ($item['cost_price'] ?? 0)); ?>">
            </div>
        </div>

        <div class="admin-field-row" id="item-pricing-standard"<?php echo $initialIsPhone ? ' hidden' : ''; ?>>
            <div class="admin-field">
                <label for="price">Retail price (Rs.)</label>
                <input type="number" id="price" name="price" min="0" step="0.01" value="<?php echo htmlspecialchars((string) ($item['price'] ?? '0')); ?>" required>
                <p class="admin-field-note">Price shown to customers on the website.</p>
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
            <p class="admin-field-note">JPG, PNG, or HEIC uploads are saved as WebP. Max 15 MB per image. Server allows up to <?php echo htmlspecialchars(ini_get('upload_max_filesize') ?: '?'); ?> per file, <?php echo htmlspecialchars(ini_get('post_max_size') ?: '?'); ?> per form.</p>
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
            <button type="submit" class="btn btn-primary" id="item-submit-btn"><?php echo $item ? 'Update item' : 'Create item'; ?></button>
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
        var catSelect = document.getElementById('category_id');
        var catId = catSelect ? catSelect.value : '';
        return models.filter(function (m) {
            if (String(m.brand_id) !== String(brandId)) return false;
            // Models without a category (legacy) show everywhere.
            if (!m.category_id) return true;
            return !catId || String(m.category_id) === String(catId);
        });
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
        if (phoneVariantsSection) {
            phoneVariantsSection.hidden = !isPhone;
        }
        if (itemPricingStandard) {
            itemPricingStandard.hidden = isPhone;
            itemPricingStandard.style.display = isPhone ? 'none' : '';
        }
        if (phoneVariantsSection) {
            phoneVariantsSection.style.display = isPhone ? '' : 'none';
        }
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
        categorySelect.addEventListener('change', function () {
            syncPhoneVariantsVisibility();
            fillModels();
        });
        syncPhoneVariantsVisibility();
        fillModels();
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

    var isEditPage = <?php echo $id > 0 ? 'true' : 'false'; ?>;
    var catalogLookupUrl = <?php echo json_encode(admin_url('api/item-catalog-lookup.php'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var itemIdInput = document.getElementById('item_id');
    var existingNotice = document.getElementById('existing-item-notice');
    var existingMedia = document.getElementById('existing-item-media');
    var submitBtn = document.getElementById('item-submit-btn');
    var linkedItemId = isEditPage ? <?php echo (int) $id; ?> : 0;
    var lookupTimer = null;

    function setFieldValue(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value != null ? String(value) : '';
    }

    function setCheckbox(id, checked) {
        var el = document.getElementById(id);
        if (el) el.checked = !!checked;
    }

    function clearSpecRows() {
        if (!specRows) return;
        specRows.innerHTML = '';
    }

    function fillSpecRows(lines) {
        clearSpecRows();
        if (!lines || !lines.length) return;
        lines.forEach(function (text) {
            addSpecRow();
            var inputs = specRows.querySelectorAll('input[name="phone_spec_text[]"]');
            if (inputs.length) {
                inputs[inputs.length - 1].value = text;
            }
        });
    }

    function renderExistingMedia(item) {
        if (!existingMedia) return;
        existingMedia.innerHTML = '';
        if (!item.main_image_url && (!item.sub_images || !item.sub_images.length)) {
            existingMedia.hidden = true;
            return;
        }
        var html = '<p class="admin-field-note"><strong>Saved images</strong> — kept unless you upload new ones.</p><div class="admin-sub-images admin-sub-images--manage">';
        if (item.main_image_url) {
            html += '<div class="admin-sub-images__item"><img src="' + item.main_image_url + '" alt="Main image"><span>Main</span></div>';
        }
        (item.sub_images || []).forEach(function (img) {
            html += '<div class="admin-sub-images__item"><img src="' + img.url + '" alt=""></div>';
        });
        html += '</div>';
        existingMedia.innerHTML = html;
        existingMedia.hidden = false;
    }

    function applyExistingItem(item) {
        linkedItemId = item.id;
        if (itemIdInput) itemIdInput.value = String(item.id);
        if (existingNotice) existingNotice.hidden = false;
        if (submitBtn) submitBtn.textContent = 'Update item';

        setFieldValue('name', item.name);
        setFieldValue('product_code', item.product_code);
        setFieldValue('unit', item.unit);
        setFieldValue('note', item.note);
        setFieldValue('wholesale_price', item.wholesale_price !== '' && item.wholesale_price != null ? item.wholesale_price : '');
        setFieldValue('min_price', item.min_price !== '' && item.min_price != null ? item.min_price : '');
        setFieldValue('reorder_level', item.reorder_level);
        setFieldValue('price', item.price);
        setFieldValue('cost_price', item.cost_price);
        setFieldValue('stock_quantity', item.stock_quantity);
        setFieldValue('tag', item.tag);
        setFieldValue('color', item.color || '#333333');
        setFieldValue('sort_order', item.sort_order);
        setFieldValue('stock_status', item.stock_status);
        setCheckbox('is_featured', item.is_featured);
        setCheckbox('is_active', item.is_active);

        if (item.variant) {
            setFieldValue('phone_variant_ram', item.variant.ram);
            setFieldValue('phone_variant_rom', item.variant.rom);
            setFieldValue('phone_variant_price', item.variant.price != null ? item.variant.price : '');
            setFieldValue('phone_variant_cost', item.variant.cost_price);
            setFieldValue('phone_variant_quantity', item.stock_quantity);
            setFieldValue('phone_variant_stock', item.variant.stock_status);
        }

        fillSpecRows(item.specs || []);
        renderExistingMedia(item);
        syncPhoneVariantsVisibility();
    }

    function clearExistingItemLink() {
        if (isEditPage) return;
        linkedItemId = 0;
        if (itemIdInput) itemIdInput.value = '0';
        if (existingNotice) existingNotice.hidden = true;
        if (existingMedia) {
            existingMedia.innerHTML = '';
            existingMedia.hidden = true;
        }
        if (submitBtn) submitBtn.textContent = 'Create item';
    }

    function categoryIsPhone() {
        if (!categorySelect) return false;
        var opt = categorySelect.options[categorySelect.selectedIndex];
        return !!(opt && opt.getAttribute('data-is-phone') === '1');
    }

    function lookupExistingItem() {
        if (isEditPage) return;
        var catId = categorySelect ? categorySelect.value : '';
        var brandId = brandSelect ? brandSelect.value : '';
        var modelId = modelSelect ? modelSelect.value : '';
        if (!catId || !brandId || !modelId) {
            clearExistingItemLink();
            return;
        }

        var params = new URLSearchParams({
            category_id: catId,
            brand_id: brandId,
            model_id: modelId
        });
        if (categoryIsPhone()) {
            var ramEl = document.getElementById('phone_variant_ram');
            var romEl = document.getElementById('phone_variant_rom');
            var ram = ramEl ? ramEl.value.trim() : '';
            var rom = romEl ? romEl.value.trim() : '';
            if (!ram && !rom) {
                clearExistingItemLink();
                return;
            }
            params.set('ram', ram);
            params.set('rom', rom);
        }

        fetch(catalogLookupUrl + '?' + params.toString(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok || !data.found || !data.item) {
                    clearExistingItemLink();
                    return;
                }
                applyExistingItem(data.item);
            })
            .catch(function () {
                clearExistingItemLink();
            });
    }

    function scheduleLookup() {
        if (isEditPage) return;
        clearTimeout(lookupTimer);
        lookupTimer = setTimeout(lookupExistingItem, 250);
    }

    if (!isEditPage) {
        if (categorySelect) categorySelect.addEventListener('change', scheduleLookup);
        if (brandSelect) brandSelect.addEventListener('change', scheduleLookup);
        if (modelSelect) modelSelect.addEventListener('change', scheduleLookup);
        ['phone_variant_ram', 'phone_variant_rom'].forEach(function (fieldId) {
            var el = document.getElementById(fieldId);
            if (el) el.addEventListener('input', scheduleLookup);
        });
    }
})();
</script>
<?php
admin_render_footer();
