<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$statuses = store_stock_statuses();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_csrf_verify();
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $stockStatus = store_normalize_stock_status($_POST['stock_status'] ?? '');

        if ($itemId <= 0) {
            throw new RuntimeException('Invalid item.');
        }

        $stmt = $pdo->prepare('UPDATE items SET stock_status = :s WHERE id = :id');
        $stmt->execute(['s' => $stockStatus, 'id' => $itemId]);

        admin_flash('success', 'Stock status updated.');
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
    }
    header('Location: ' . admin_url('stock.php'));
    exit;
}

$sql = store_item_select_sql() . ' ORDER BY i.name ASC';
$items = $pdo->query($sql)->fetchAll();

admin_render_header('Stock Status', 'stock');
?>
<div class="admin-panel">
    <div class="admin-panel-head">
        <h2>Stock status</h2>
        <a href="<?php echo admin_url('item-form.php'); ?>" class="btn btn-primary">Add item</a>
    </div>

    <p class="admin-hint">Set each product to <strong>In stock</strong>, <strong>Out of stock</strong>, or <strong>Pre order</strong>. Shown on the website product cards.</p>

    <?php if (!$items): ?>
    <p class="admin-empty">No items yet. <a href="<?php echo admin_url('item-form.php'); ?>">Add a product</a> first.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <?php $itemStock = store_item_effective_stock($item); ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars($item['brand_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($item['model_name'] ?? '—'); ?></td>
                    <td>
                        <span class="admin-stock-badge admin-stock-badge--<?php echo htmlspecialchars($itemStock['stock_status']); ?>">
                            <?php echo htmlspecialchars($itemStock['stock_label']); ?>
                        </span>
                        <form method="post" class="admin-stock-form">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
                            <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                            <select name="stock_status" class="admin-stock-select">
                                <?php foreach ($statuses as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>"<?php echo $itemStock['stock_status'] === $value ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php
admin_render_footer();
