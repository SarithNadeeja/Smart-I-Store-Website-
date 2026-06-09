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
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id AND is_preowned = TRUE');
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

$brands = $pdo->query('SELECT id, name FROM phone_brands WHERE is_active = TRUE ORDER BY sort_order ASC, name ASC')->fetchAll();
$allModels = array_values(array_filter(
    $pdo->query(
        'SELECT id, brand_id, category_id, name FROM product_models WHERE is_active = TRUE ORDER BY name ASC'
    )->fetchAll(),
    static function (array $m): bool {
        $catId = (int) ($m['category_id'] ?? 0);
        // Pre-owned market is phones only: keep phone/tablet models and legacy uncategorized ones.
        return $catId <= 0 || store_category_is_phone($catId);
    }
));
$conditions = store_preowned_conditions();
$stockStatuses = store_stock_statuses();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        uploads_assert_post_accepted();
        admin_csrf_verify();

        if (($_POST['action'] ?? '') === 'delete_sub_image') {
            if ($id <= 0) {
                throw new RuntimeException('Listing not found.');
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
            header('Location: ' . admin_url('preowned-form.php?id=' . $id));
            exit;
        }

        $isUpdate = $id > 0;
        $id = admin_save_preowned_request($pdo, $id, $item);
        $subFiles = uploads_collect_files($_FILES['sub_images'] ?? []);
        $flashMsg = $isUpdate ? 'Pre-owned listing updated.' : 'Pre-owned phone listed.';
        if (!empty($subFiles)) {
            $flashMsg .= ' Sub images were saved.';
        }
        admin_flash('success', $flashMsg);
        header('Location: ' . admin_url('preowned-form.php?id=' . $id));
        exit;
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
        header('Location: ' . admin_url('preowned-form.php' . ($id ? '?id=' . $id : '')));
        exit;
    }
}

$currentStock = store_normalize_stock_status($item['stock_status'] ?? 'in_stock');
$maxSubImages = store_max_sub_images();
$subImageCount = count($subImages);
$subSlotsLeft = max(0, $maxSubImages - $subImageCount);
$storageVariants = $id > 0 ? store_get_item_storage_variants($id) : [];
$storageVariant = $storageVariants[0] ?? null;
$systemSpecs = $id > 0 ? store_get_item_system_specs($id) : [];
$currentCondition = store_normalize_preowned_condition($item['preowned_condition'] ?? '');
$currentBrandId = (int) ($item['brand_id'] ?? 0);
$showBattery = $currentBrandId > 0 && store_brand_is_apple($currentBrandId);

admin_render_header($item ? 'Edit pre-owned phone' : 'Add pre-owned phone', 'preowned');
?>
<section class="admin-panel admin-panel--wide">
    <h2><?php echo $item ? 'Edit pre-owned phone' : 'Add pre-owned phone'; ?></h2>
    <p class="admin-field-note">One listing per physical phone. These appear in the website <strong>Pre-Owned Market</strong> section only.</p>

    <?php if ($subImages): ?>
    <div class="admin-sub-images admin-sub-images--manage">
        <?php foreach ($subImages as $img): ?>
        <div class="admin-sub-images__item">
            <img src="<?php echo htmlspecialchars(upload_url($img['image_path'])); ?>" alt="">
            <form method="post" class="admin-inline-form" action="<?php echo htmlspecialchars(admin_url('preowned-form.php?id=' . $id)); ?>">
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

    <form method="post" enctype="multipart/form-data" class="admin-form" id="preowned-form"
          action="<?php echo htmlspecialchars(admin_url('preowned-form.php' . ($id > 0 ? '?id=' . $id : ''))); ?>">
        <?php admin_csrf_field(); ?>
        <?php if ($id > 0): ?>
        <input type="hidden" name="item_id" value="<?php echo (int) $id; ?>">
        <?php endif; ?>

        <div class="admin-field-row">
            <div class="admin-field">
                <label for="brand_id">Brand</label>
                <select id="brand_id" name="brand_id" required>
                    <option value="">Select brand</option>
                    <?php foreach ($brands as $brand): ?>
                    <option value="<?php echo (int) $brand['id']; ?>"
                        data-is-apple="<?php echo store_brand_is_apple((int) $brand['id']) ? '1' : '0'; ?>"
                        <?php echo $currentBrandId === (int) $brand['id'] ? ' selected' : ''; ?>>
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
            </div>
        </div>

        <div class="admin-field">
            <label for="name">Listing name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" required
                   placeholder="e.g. iPhone 13 Pro 256GB — Mint">
        </div>

        <div class="admin-field-row">
            <div class="admin-field">
                <label for="preowned_condition">Condition</label>
                <select id="preowned_condition" name="preowned_condition" required>
                    <option value="">Select condition</option>
                    <?php foreach ($conditions as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>"<?php echo $currentCondition === $value ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-field" id="battery-health-field"<?php echo $showBattery ? '' : ' hidden'; ?>>
                <label for="battery_health">Battery health <small>Apple only</small></label>
                <input type="number" id="battery_health" name="battery_health" min="1" max="100" step="1"
                       value="<?php echo isset($item['battery_health']) && $item['battery_health'] !== null && $item['battery_health'] !== ''
                           ? (int) $item['battery_health'] : ''; ?>"
                       placeholder="e.g. 87">
                <p class="admin-field-note">Shown on the product page for iPhones (1–100%).</p>
            </div>
        </div>

        <div class="admin-phone-extras">
            <h3 class="admin-phone-extras__title">This unit</h3>
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
                           value="<?php echo $storageVariant && $storageVariant['price'] !== null ? htmlspecialchars((string) $storageVariant['price']) : ''; ?>">
                </div>
                <div class="admin-field">
                    <label for="phone_variant_cost">Purchase cost (Rs.) <small>internal only</small></label>
                    <input type="number" id="phone_variant_cost" name="phone_variant_cost" min="0" step="0.01" required
                           value="<?php echo htmlspecialchars((string) ($storageVariant['cost_price'] ?? 0)); ?>">
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

        <div class="admin-field-row">
            <div class="admin-field">
                <label for="tag">Badge label <small>optional</small></label>
                <input type="text" id="tag" name="tag" value="<?php echo htmlspecialchars($item['tag'] ?? 'Pre-Owned'); ?>"
                       placeholder="Pre-Owned">
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
            <div class="admin-phone-block">
                <div class="admin-phone-block__head">
                    <h4>Detail lines</h4>
                    <button type="button" class="btn btn-ghost btn-sm" id="add-spec-row">Add detail line</button>
                </div>
                <div class="admin-repeat-list" id="spec-rows">
                    <?php foreach ($systemSpecs as $spec): ?>
                    <div class="admin-repeat-row admin-spec-row">
                        <div class="admin-field admin-field--grow">
                            <label>Detail</label>
                            <input type="text" name="phone_spec_text[]" value="<?php echo htmlspecialchars($spec['text']); ?>">
                        </div>
                        <button type="button" class="admin-repeat-remove" aria-label="Remove detail">&times;</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="admin-field">
            <label for="sub_images">Sub images <small>(<?php echo $subImageCount; ?>/<?php echo $maxSubImages; ?> max)</small></label>
            <?php if ($subSlotsLeft > 0): ?>
            <input type="file" id="sub_images" name="sub_images[]" accept="image/*" multiple data-max="<?php echo $subSlotsLeft; ?>">
            <?php else: ?>
            <p class="admin-field-note">Maximum sub images reached.</p>
            <?php endif; ?>
        </div>

        <div class="admin-field">
            <label for="sort_order">Sort order</label>
            <input type="number" id="sort_order" name="sort_order" value="<?php echo (int) ($item['sort_order'] ?? 0); ?>">
        </div>

        <label class="admin-check">
            <input type="checkbox" name="is_active"<?php echo ($item === null || !empty($item['is_active'])) ? ' checked' : ''; ?>>
            Active (visible on Pre-Owned Market)
        </label>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $item ? 'Update listing' : 'Create listing'; ?></button>
            <a href="<?php echo admin_url('preowned.php'); ?>" class="btn btn-ghost">Back to list</a>
        </div>
    </form>
</section>
<script>
(function () {
    var models = <?php echo json_encode($allModels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var brandSelect = document.getElementById('brand_id');
    var modelSelect = document.getElementById('model_id');
    var nameInput = document.getElementById('name');
    var batteryField = document.getElementById('battery-health-field');
    var batteryInput = document.getElementById('battery_health');
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

    function syncAppleBattery() {
        if (!brandSelect || !batteryField) return;
        var opt = brandSelect.options[brandSelect.selectedIndex];
        var isApple = !!(opt && opt.getAttribute('data-is-apple') === '1');
        batteryField.hidden = !isApple;
        if (batteryInput) {
            batteryInput.required = isApple;
            if (!isApple) batteryInput.value = '';
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
        syncAppleBattery();
    });
    modelSelect.addEventListener('change', suggestName);
    fillModels();
    syncAppleBattery();

    var specRows = document.getElementById('spec-rows');
    var addSpecBtn = document.getElementById('add-spec-row');

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
            '<input type="text" name="phone_spec_text[]" placeholder="e.g. Minor wear on corners"></div>' +
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
