<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();

function admin_items_filter_params(): array
{
    return [
        'q' => trim($_GET['q'] ?? ''),
        'category_id' => (int) ($_GET['category_id'] ?? 0),
        'brand_id' => (int) ($_GET['brand_id'] ?? 0),
        'model_id' => (int) ($_GET['model_id'] ?? 0),
        'stock_status' => trim($_GET['stock_status'] ?? ''),
        'visibility' => trim($_GET['visibility'] ?? ''),
    ];
}

function admin_items_filter_query(array $filters = null): string
{
    $filters = $filters ?? admin_items_filter_params();
    $parts = [];
    foreach ($filters as $key => $value) {
        if ($value === '' || $value === 0) {
            continue;
        }
        $parts[] = urlencode($key) . '=' . urlencode((string) $value);
    }
    return $parts ? '?' . implode('&', $parts) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        admin_csrf_verify();
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('SELECT main_image FROM items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();

        $imgStmt = $pdo->prepare('SELECT image_path FROM item_images WHERE item_id = :id');
        $imgStmt->execute(['id' => $id]);
        $subImages = $imgStmt->fetchAll();

        $pdo->prepare('DELETE FROM items WHERE id = :id')->execute(['id' => $id]);

        if ($item) {
            uploads_delete_file($item['main_image'] ?? '');
        }
        foreach ($subImages as $img) {
            uploads_delete_file($img['image_path']);
        }

        admin_flash('success', 'Item deleted.');
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
    }
    header('Location: ' . admin_url('items.php' . admin_items_filter_query()));
    exit;
}

$filters = admin_items_filter_params();
$where = [];
$params = [];

if ($filters['q'] !== '') {
    $where[] = '(i.name ILIKE :q OR b.name ILIKE :q OR m.name ILIKE :q
        OR c.title ILIKE :q OR c.description ILIKE :q)';
    $params['q'] = '%' . $filters['q'] . '%';
}
if ($filters['category_id'] > 0) {
    $where[] = 'i.category_id = :category_id';
    $params['category_id'] = $filters['category_id'];
}
if ($filters['brand_id'] > 0) {
    $where[] = 'i.brand_id = :brand_id';
    $params['brand_id'] = $filters['brand_id'];
}
if ($filters['model_id'] > 0) {
    $where[] = 'i.model_id = :model_id';
    $params['model_id'] = $filters['model_id'];
}
if ($filters['stock_status'] !== '') {
    $where[] = 'i.stock_status = :stock_status';
    $params['stock_status'] = store_normalize_stock_status($filters['stock_status']);
}
if ($filters['visibility'] === 'active') {
    $where[] = 'i.is_active = TRUE';
} elseif ($filters['visibility'] === 'hidden') {
    $where[] = 'i.is_active = FALSE';
}

$sql = store_item_select_sql();
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY i.sort_order ASC, i.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$categories = $pdo->query(
    "SELECT id, COALESCE(NULLIF(description, ''), title) AS title
     FROM categories ORDER BY title ASC"
)->fetchAll();
$brands = $pdo->query('SELECT id, name FROM phone_brands ORDER BY name ASC')->fetchAll();
$models = $pdo->query('SELECT id, brand_id, name FROM product_models ORDER BY name ASC')->fetchAll();
$stockStatuses = store_stock_statuses();

$hasFilters = admin_items_filter_query() !== '';
$itemCount = count($items);

admin_render_header('Items', 'items');
?>
<div class="admin-panel">
    <div class="admin-panel-head">
        <h2>All items</h2>
        <a href="<?php echo admin_url('item-form.php'); ?>" class="btn btn-primary">Add item</a>
    </div>

    <form method="get" class="admin-filters" id="items-filter-form">
        <div class="admin-filters__row">
            <div class="admin-field admin-field--search">
                <label for="q">Search</label>
                <input type="search" id="q" name="q" value="<?php echo htmlspecialchars($filters['q']); ?>"
                       placeholder="Name, brand, model, category…" autocomplete="off">
            </div>
            <div class="admin-field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo (int) $cat['id']; ?>"<?php echo $filters['category_id'] === (int) $cat['id'] ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-field">
                <label for="brand_id">Brand</label>
                <select id="brand_id" name="brand_id">
                    <option value="">All brands</option>
                    <?php foreach ($brands as $brand): ?>
                    <option value="<?php echo (int) $brand['id']; ?>"<?php echo $filters['brand_id'] === (int) $brand['id'] ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($brand['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-field">
                <label for="model_id">Model</label>
                <select id="model_id" name="model_id">
                    <option value="">All models</option>
                    <?php foreach ($models as $model): ?>
                    <option value="<?php echo (int) $model['id']; ?>" data-brand="<?php echo (int) $model['brand_id']; ?>"
                        <?php echo $filters['model_id'] === (int) $model['id'] ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($model['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="admin-filters__row admin-filters__row--actions">
            <div class="admin-field">
                <label for="stock_status">Stock</label>
                <select id="stock_status" name="stock_status">
                    <option value="">All stock</option>
                    <?php foreach ($stockStatuses as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>"<?php echo $filters['stock_status'] === $value ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-field">
                <label for="visibility">Visibility</label>
                <select id="visibility" name="visibility">
                    <option value="">All</option>
                    <option value="active"<?php echo $filters['visibility'] === 'active' ? ' selected' : ''; ?>>Active</option>
                    <option value="hidden"<?php echo $filters['visibility'] === 'hidden' ? ' selected' : ''; ?>>Hidden</option>
                </select>
            </div>
            <div class="admin-filters__buttons">
                <button type="submit" class="btn btn-primary">Apply filters</button>
                <?php if ($hasFilters): ?>
                <a href="<?php echo admin_url('items.php'); ?>" class="btn btn-ghost">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <p class="admin-results-meta">
        <?php echo $itemCount === 1 ? '1 item' : $itemCount . ' items'; ?>
        <?php if ($hasFilters): ?> matching your filters<?php endif; ?>
    </p>

    <?php if (!$items): ?>
    <p class="admin-empty">
        <?php if ($hasFilters): ?>
        No items match your search or filters. <a href="<?php echo admin_url('items.php'); ?>">Clear filters</a>
        <?php else: ?>
        No items yet. <a href="<?php echo admin_url('item-form.php'); ?>">Add your first product</a>.
        <?php endif; ?>
    </p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Stock</th>
                    <th>Visible</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?php if (!empty($item['main_image'])): ?>
                        <img class="admin-thumb" src="<?php echo htmlspecialchars(upload_url($item['main_image'])); ?>" alt="">
                        <?php else: ?>
                        <span class="admin-thumb admin-thumb--empty">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo store_category_is_phone((int) ($item['category_id'] ?? 0)) ? 'Phone' : 'Other'; ?></td>
                    <td><?php echo htmlspecialchars($item['brand_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($item['model_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($item['category_title'] ?? '—'); ?></td>
                    <td>Rs. <?php echo number_format((float) $item['price'], 0); ?></td>
                    <td><?php echo (int) ($item['stock_quantity'] ?? 0); ?></td>
                    <td>
                        <span class="admin-stock-badge admin-stock-badge--<?php echo htmlspecialchars(store_normalize_stock_status($item['stock_status'] ?? 'in_stock')); ?>">
                            <?php echo htmlspecialchars(store_stock_label(store_normalize_stock_status($item['stock_status'] ?? 'in_stock'))); ?>
                        </span>
                    </td>
                    <td><?php echo !empty($item['is_active']) ? 'Active' : 'Hidden'; ?></td>
                    <td class="admin-table-actions">
                        <a href="<?php echo admin_url('item-form.php?id=' . (int) $item['id']); ?>">Edit</a>
                        <form method="post" class="admin-inline-form" onsubmit="return confirm('Delete this item?');">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="admin-link-btn admin-link-btn--danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<script>
(function () {
    var brandSelect = document.getElementById('brand_id');
    var modelSelect = document.getElementById('model_id');
    if (!brandSelect || !modelSelect) return;

    var allOptions = Array.prototype.slice.call(modelSelect.querySelectorAll('option[data-brand]'));

    function syncModels() {
        var brandId = brandSelect.value;
        var selected = modelSelect.value;
        modelSelect.innerHTML = '<option value="">All models</option>';
        allOptions.forEach(function (opt) {
            if (!brandId || opt.getAttribute('data-brand') === brandId) {
                modelSelect.appendChild(opt.cloneNode(true));
            }
        });
        if (selected && Array.prototype.some.call(modelSelect.options, function (o) { return o.value === selected; })) {
            modelSelect.value = selected;
        }
    }

    brandSelect.addEventListener('change', function () {
        modelSelect.value = '';
        syncModels();
    });
    syncModels();
})();
</script>
<?php
admin_render_footer();
