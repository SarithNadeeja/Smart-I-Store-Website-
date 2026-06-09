<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$filterBrand = isset($_GET['brand']) ? (int) $_GET['brand'] : 0;
$editRow = null;

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM product_models WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $editRow = $stmt->fetch() ?: null;
    if ($editRow) {
        $filterBrand = (int) $editRow['brand_id'];
    }
}

$brands = $pdo->query(
    'SELECT id, name FROM phone_brands WHERE is_active = TRUE ORDER BY name ASC'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_csrf_verify();
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM product_models WHERE id = :id')->execute(['id' => $id]);
            admin_flash('success', 'Model deleted.');
        } elseif ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $brandId = (int) ($_POST['brand_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $isActive = isset($_POST['is_active']);

            if ($brandId <= 0) {
                throw new RuntimeException('Select a brand.');
            }
            if ($name === '') {
                throw new RuntimeException('Model name is required.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE product_models SET brand_id = :b, name = :n, is_active = :a WHERE id = :id'
                );
                $stmt->execute(['b' => $brandId, 'n' => $name, 'a' => db_bool($isActive), 'id' => $id]);
                admin_flash('success', 'Model updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO product_models (brand_id, name, is_active) VALUES (:b, :n, :a)'
                );
                $stmt->execute(['b' => $brandId, 'n' => $name, 'a' => db_bool($isActive)]);
                admin_flash('success', 'Model created.');
            }
        }

        header('Location: ' . admin_url('models.php' . ($filterBrand ? '?brand=' . $filterBrand : '')));
        exit;
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
        header('Location: ' . admin_url('models.php' . ($editId ? '?edit=' . $editId : '')));
        exit;
    }
}

$sql = "
    SELECT m.*, b.name AS brand_name
    FROM product_models m
    JOIN phone_brands b ON b.id = m.brand_id
";
$params = [];
if ($filterBrand > 0) {
    $sql .= ' WHERE m.brand_id = :brand';
    $params['brand'] = $filterBrand;
}
$sql .= ' ORDER BY b.name ASC, m.name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$models = $stmt->fetchAll();

admin_render_header('Model Management', 'models');
?>
<div class="admin-grid-2">
    <section class="admin-panel">
        <h2><?php echo $editRow ? 'Edit model' : 'Add model'; ?></h2>
        <p class="admin-hint">Example: Category Phone → Brand Apple → Model 17 Pro Max</p>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo (int) ($editRow['id'] ?? 0); ?>">
            <div class="admin-field">
                <label for="brand_id">Brand</label>
                <select id="brand_id" name="brand_id" required>
                    <option value="">Select brand</option>
                    <?php foreach ($brands as $brand): ?>
                    <option value="<?php echo (int) $brand['id']; ?>"<?php echo (int) ($editRow['brand_id'] ?? $filterBrand) === (int) $brand['id'] ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($brand['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-field">
                <label for="name">Model name</label>
                <input type="text" id="name" name="name" placeholder="e.g. 17 Pro Max" value="<?php echo htmlspecialchars($editRow['name'] ?? ''); ?>" required>
            </div>
            <label class="admin-check">
                <input type="checkbox" name="is_active"<?php echo ($editRow === null || !empty($editRow['is_active'])) ? ' checked' : ''; ?>>
                Active
            </label>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $editRow ? 'Update' : 'Create'; ?></button>
                <?php if ($editRow): ?>
                <a href="<?php echo admin_url('models.php'); ?>" class="btn btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head">
            <h2>All models</h2>
            <form method="get" class="admin-filter-form">
                <select name="brand" onchange="this.form.submit()">
                    <option value="">All brands</option>
                    <?php foreach ($brands as $brand): ?>
                    <option value="<?php echo (int) $brand['id']; ?>"<?php echo $filterBrand === (int) $brand['id'] ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($brand['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if (!$models): ?>
        <p class="admin-empty">No models yet. Add models for each brand (e.g. Apple → 17 Pro Max).</p>
        <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($models as $model): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($model['brand_name']); ?></td>
                        <td><?php echo htmlspecialchars($model['name']); ?></td>
                        <td><?php echo !empty($model['is_active']) ? 'Active' : 'Hidden'; ?></td>
                        <td class="admin-table-actions">
                            <a href="<?php echo admin_url('models.php?edit=' . (int) $model['id']); ?>">Edit</a>
                            <form method="post" class="admin-inline-form" onsubmit="return confirm('Delete this model?');">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $model['id']; ?>">
                                <button type="submit" class="admin-link-btn admin-link-btn--danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php
admin_render_footer();
