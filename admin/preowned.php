<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        uploads_assert_post_accepted();
        admin_csrf_verify();
        $id = (int) ($_POST['id'] ?? 0);
        $check = $pdo->prepare('SELECT is_preowned FROM items WHERE id = :id');
        $check->execute(['id' => $id]);
        $row = $check->fetch();
        if (!$row || empty($row['is_preowned'])) {
            throw new RuntimeException('Pre-owned listing not found.');
        }
        $imageCount = store_delete_item($id);
        $msg = 'Pre-owned listing deleted.';
        if ($imageCount > 0) {
            $msg .= ' ' . $imageCount . ' image' . ($imageCount === 1 ? '' : 's') . ' removed.';
        }
        admin_flash('success', $msg);
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
    }
    header('Location: ' . admin_url('preowned.php'));
    exit;
}

$sql = store_item_select_sql() . '
    WHERE i.is_preowned = TRUE
    ORDER BY i.sort_order ASC, i.id DESC';
$items = $pdo->query($sql)->fetchAll();
$conditions = store_preowned_conditions();

admin_render_header('Pre-Owned Phones', 'preowned');
?>
<div class="admin-panel">
    <div class="admin-panel-head">
        <h2>Pre-owned phones</h2>
        <a href="<?php echo admin_url('preowned-form.php'); ?>" class="btn btn-primary">Add pre-owned phone</a>
    </div>

    <?php if (!$items): ?>
    <p class="admin-empty">No pre-owned listings yet. Each phone is one listing — add RAM, ROM, condition, and photos for every unit.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Listing</th>
                    <th>Brand / model</th>
                    <th>Condition</th>
                    <th>Battery</th>
                    <th>Retail price</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars(trim(($row['brand_name'] ?? '') . ' ' . ($row['model_name'] ?? ''))); ?></td>
                    <td><?php echo htmlspecialchars(store_preowned_condition_label($row['preowned_condition'] ?? '')); ?></td>
                    <td><?php
                        if ($row['battery_health'] !== null && $row['battery_health'] !== '') {
                            echo (int) $row['battery_health'] . '%';
                        } else {
                            echo '—';
                        }
                    ?></td>
                    <td>Rs. <?php echo number_format((float) $row['price'], 0); ?></td>
                    <td><?php echo !empty($row['is_active']) ? 'Active' : 'Hidden'; ?></td>
                    <td class="admin-table__actions">
                        <a href="<?php echo admin_url('preowned-form.php?id=' . (int) $row['id']); ?>" class="admin-link">Edit</a>
                        <form method="post" class="admin-inline-form" action="<?php echo admin_url('preowned.php'); ?>"
                              onsubmit="return confirm('Delete this pre-owned listing?');">
                            <?php admin_csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
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
<?php
admin_render_footer();
