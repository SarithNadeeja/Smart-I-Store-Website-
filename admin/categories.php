<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editRow = null;

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $editRow = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_csrf_verify();
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
            $stmt->execute(['id' => $id]);
            admin_flash('success', 'Category deleted.');
        } elseif ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $icon = $_POST['icon'] ?? 'smartphone';
            $isActive = isset($_POST['is_active']);

            if ($description === '') {
                throw new RuntimeException('Category name is required.');
            }
            if (!array_key_exists($icon, store_icon_options())) {
                $icon = 'smartphone';
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE categories SET title = :t, description = :d, icon = :i, is_active = :a WHERE id = :id'
                );
                $stmt->execute([
                    't' => $description,
                    'd' => $description,
                    'i' => $icon,
                    'a' => $isActive,
                    'id' => $id,
                ]);
                admin_flash('success', 'Category updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO categories (title, description, icon, is_active)
                     VALUES (:t, :d, :i, :a)'
                );
                $stmt->execute([
                    't' => $description,
                    'd' => $description,
                    'i' => $icon,
                    'a' => $isActive,
                ]);
                admin_flash('success', 'Category created.');
            }
        }

        header('Location: ' . admin_url('categories.php'));
        exit;
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
        header('Location: ' . admin_url('categories.php' . ($editId ? '?edit=' . $editId : '')));
        exit;
    }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY id ASC')->fetchAll();
$iconOptions = store_icon_options();

admin_render_header('Category Management', 'categories');
?>
<div class="admin-grid-2">
    <section class="admin-panel">
        <h2><?php echo $editRow ? 'Edit category' : 'Add category'; ?></h2>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo (int) ($editRow['id'] ?? 0); ?>">
            <div class="admin-field">
                <label for="description">Category name</label>
                <input type="text" id="description" name="description" value="<?php echo htmlspecialchars($editRow['description'] ?? $editRow['title'] ?? ''); ?>" required>
            </div>
            <div class="admin-field">
                <label for="icon">Icon</label>
                <select id="icon" name="icon">
                    <?php foreach ($iconOptions as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>"<?php echo ($editRow['icon'] ?? 'smartphone') === $key ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="admin-check">
                <input type="checkbox" name="is_active"<?php echo ($editRow === null || !empty($editRow['is_active'])) ? ' checked' : ''; ?>>
                Active (visible on website)
            </label>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $editRow ? 'Update' : 'Create'; ?></button>
                <?php if ($editRow): ?>
                <a href="<?php echo admin_url('categories.php'); ?>" class="btn btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="admin-panel">
        <h2>All categories</h2>
        <?php if (!$categories): ?>
        <p class="admin-empty">No categories yet. Add one to show on the home page.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Icon</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cat['description'] !== '' ? $cat['description'] : $cat['title']); ?></td>
                        <td><?php echo htmlspecialchars($cat['icon']); ?></td>
                        <td><?php echo !empty($cat['is_active']) ? 'Active' : 'Hidden'; ?></td>
                        <td class="admin-table-actions">
                            <a href="<?php echo admin_url('categories.php?edit=' . (int) $cat['id']); ?>">Edit</a>
                            <form method="post" class="admin-inline-form" onsubmit="return confirm('Delete this category?');">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $cat['id']; ?>">
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
