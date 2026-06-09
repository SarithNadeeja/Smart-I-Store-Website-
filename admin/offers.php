<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_offer') {
    try {
        uploads_assert_post_accepted();
        admin_csrf_verify();
        $id = (int) ($_POST['item_id'] ?? 0);
        admin_remove_item_offer($pdo, $id);
        admin_flash('success', 'Offer removed.');
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
    }
    header('Location: ' . admin_url('offers.php'));
    exit;
}

$sql = store_item_select_sql() . '
    WHERE i.sale_price IS NOT NULL
      AND i.sale_price > 0
      AND i.sale_price < i.price
    ORDER BY i.sort_order ASC, i.id DESC';
$offers = $pdo->query($sql)->fetchAll();

admin_render_header('Offers', 'offers');
?>
<div class="admin-panel">
    <div class="admin-panel-head">
        <h2>Active offers</h2>
        <a href="<?php echo admin_url('offer-form.php'); ?>" class="btn btn-primary">Add offer</a>
    </div>

    <?php if (!$offers): ?>
    <p class="admin-empty">No active offers. Add items or pre-owned phones, then use <strong>Add offer</strong> to set promotional pricing.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Retail price</th>
                    <th>Offer price</th>
                    <th>Discount</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($offers as $row): ?>
                <?php
                $list = (float) $row['price'];
                $sale = (float) $row['sale_price'];
                $discount = store_offer_discount_percent($list, $sale);
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                        <?php if (!empty($row['is_preowned'])): ?>
                        <br><small class="admin-muted">Pre-Owned</small>
                        <?php endif; ?>
                        <?php if (!empty($row['brand_name']) || !empty($row['model_name'])): ?>
                        <br><small class="admin-muted"><?php echo htmlspecialchars(trim(($row['brand_name'] ?? '') . ' ' . ($row['model_name'] ?? ''))); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['category_title'] ?? '—'); ?></td>
                    <td>Rs. <?php echo number_format($list, 0); ?></td>
                    <td>Rs. <?php echo number_format($sale, 0); ?></td>
                    <td><?php echo (int) $discount; ?>% OFF</td>
                    <td class="admin-table__actions">
                        <a href="<?php echo admin_url('offer-form.php?item_id=' . (int) $row['id']); ?>" class="admin-link">Edit</a>
                        <form method="post" class="admin-inline-form" action="<?php echo admin_url('offers.php'); ?>"
                              onsubmit="return confirm('Remove this offer? The item stays; only the offer price is cleared.');">
                            <?php admin_csrf_field(); ?>
                            <input type="hidden" name="action" value="remove_offer">
                            <input type="hidden" name="item_id" value="<?php echo (int) $row['id']; ?>">
                            <button type="submit" class="admin-link-btn admin-link-btn--danger">Remove</button>
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
