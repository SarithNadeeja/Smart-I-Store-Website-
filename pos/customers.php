<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

pos_require_setup_complete();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pos_csrf_verify();
        if (($_POST['action'] ?? '') !== 'save') {
            throw new RuntimeException('Unknown action.');
        }
        $newId = pos_save_customer(
            0,
            trim($_POST['name'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['address'] ?? ''),
            trim($_POST['notes'] ?? '')
        );
        pos_flash('success', 'Customer added.');
        header('Location: ' . pos_panel_url('customer-view.php?id=' . $newId));
        exit;
    } catch (Throwable $e) {
        pos_flash('error', $e->getMessage());
        header('Location: ' . pos_panel_url('customers.php'));
        exit;
    }
}

$q = trim($_GET['q'] ?? '');
$params = [];
$sql = 'SELECT * FROM pos_customers WHERE is_active = TRUE';
if ($q !== '') {
    $sql .= ' AND (name ILIKE :q OR phone ILIKE :q OR email ILIKE :q)';
    $params['q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY name ASC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

pos_render_header('Customers', 'customers');
?>
<div class="admin-grid-2">
    <section class="admin-panel">
        <h2>Add customer</h2>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
            <input type="hidden" name="action" value="save">
            <div class="admin-field">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="admin-field-row">
                <div class="admin-field">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone">
                </div>
                <div class="admin-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
            </div>
            <div class="admin-field">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="2"></textarea>
            </div>
            <div class="admin-field">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save customer</button>
        </form>
    </section>

    <section class="admin-panel">
        <h2>All customers</h2>
        <form method="get" class="admin-filters pos-list-filters">
            <div class="admin-filters__row admin-filters__row--single">
                <div class="admin-field admin-field--search">
                    <label for="q">Search</label>
                    <input type="search" id="q" name="q" value="<?php echo htmlspecialchars($q); ?>"
                           placeholder="Name, phone, or email" autocomplete="off">
                </div>
            </div>
            <div class="admin-filters__row admin-filters__row--actions">
                <div class="admin-filters__buttons">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($q !== ''): ?>
                    <a href="<?php echo pos_panel_url('customers.php'); ?>" class="btn btn-ghost">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        <?php if (!$customers): ?>
        <p class="admin-empty">No customers found.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Name</th><th>Phone</th><th>Email</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                            <?php if ($c['address'] !== ''): ?><br><small><?php echo htmlspecialchars($c['address']); ?></small><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($c['phone'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($c['email'] ?: '—'); ?></td>
                        <td><a href="<?php echo pos_panel_url('customer-view.php?id=' . (int) $c['id']); ?>" class="admin-link-btn">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php
pos_render_footer();
