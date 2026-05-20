<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editRow = null;

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM phone_brands WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $editRow = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_csrf_verify();
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM phone_brands WHERE id = :id');
            $stmt->execute(['id' => $id]);
            admin_flash('success', 'Brand deleted.');
        } elseif ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']);

            if ($name === '') {
                throw new RuntimeException('Brand name is required.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE phone_brands SET name = :n, sort_order = :s, is_active = :a WHERE id = :id'
                );
                $stmt->execute(['n' => $name, 's' => $sortOrder, 'a' => $isActive, 'id' => $id]);
                admin_flash('success', 'Brand updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO phone_brands (name, sort_order, is_active) VALUES (:n, :s, :a)'
                );
                $stmt->execute(['n' => $name, 's' => $sortOrder, 'a' => $isActive]);
                admin_flash('success', 'Brand created.');
            }
        }

        header('Location: ' . admin_url('brands.php'));
        exit;
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
        header('Location: ' . admin_url('brands.php' . ($editId ? '?edit=' . $editId : '')));
        exit;
    }
}

$brands = $pdo->query('SELECT * FROM phone_brands ORDER BY sort_order ASC, name ASC')->fetchAll();

admin_render_header('Phone Brands Management', 'brands');
?>
<div class="admin-grid-2">
    <section class="admin-panel">
        <h2><?php echo $editRow ? 'Edit brand' : 'Add brand'; ?></h2>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo (int) ($editRow['id'] ?? 0); ?>">
            <div class="admin-field">
                <label for="name">Brand name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editRow['name'] ?? ''); ?>" required>
            </div>
            <div class="admin-field">
                <label for="sort_order">Sort order</label>
                <input type="number" id="sort_order" name="sort_order" value="<?php echo (int) ($editRow['sort_order'] ?? 0); ?>">
            </div>
            <label class="admin-check">
                <input type="checkbox" name="is_active"<?php echo ($editRow === null || !empty($editRow['is_active'])) ? ' checked' : ''; ?>>
                Active (visible on website)
            </label>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $editRow ? 'Update' : 'Create'; ?></button>
                <?php if ($editRow): ?>
                <a href="<?php echo admin_url('brands.php'); ?>" class="btn btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="admin-panel">
        <h2>All brands</h2>
        <?php if (!$brands): ?>
        <p class="admin-empty">No brands yet. Add phone brands for the catalog filters.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Name</th><th>Order</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($brands as $brand): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($brand['name']); ?></td>
                        <td><?php echo (int) $brand['sort_order']; ?></td>
                        <td><?php echo !empty($brand['is_active']) ? 'Active' : 'Hidden'; ?></td>
                        <td class="admin-table-actions">
                            <a href="<?php echo admin_url('brands.php?edit=' . (int) $brand['id']); ?>">Edit</a>
                            <form method="post" class="admin-inline-form" onsubmit="return confirm('Delete this brand?');">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $brand['id']; ?>">
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
