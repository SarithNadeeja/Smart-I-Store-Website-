<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$user = pos_require_setup_complete();
$prefillCustomerId = (int) ($_GET['customer_id'] ?? 0);
$prefillCustomer = $prefillCustomerId > 0 ? pos_get_customer($prefillCustomerId) : null;
$collectMethods = pos_repair_collect_methods();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pos_csrf_verify();
        if (($_POST['action'] ?? '') !== 'save') {
            throw new RuntimeException('Unknown action.');
        }
        $jobId = pos_create_repair_job([
            'customer_id' => (int) ($_POST['customer_id'] ?? 0),
            'customer_phone' => trim($_POST['customer_phone'] ?? ''),
            'device' => trim($_POST['device'] ?? ''),
            'imei_serial' => trim($_POST['imei_serial'] ?? ''),
            'issue_description' => trim($_POST['issue_description'] ?? ''),
            'estimated_cost' => (float) ($_POST['estimated_cost'] ?? 0),
            'advance_payment' => (float) ($_POST['advance_payment'] ?? 0),
            'payment_method' => (string) ($_POST['payment_method'] ?? 'cash'),
        ], (int) $user['id']);
        pos_flash('success', 'Repair job created — status: Received.');
        header('Location: ' . pos_panel_url('repair-view.php?id=' . $jobId));
        exit;
    } catch (Throwable $e) {
        pos_flash('error', $e->getMessage());
        header('Location: ' . pos_panel_url('repair-new.php'));
        exit;
    }
}

$searchApi = pos_panel_url('api/search.php');

pos_render_header('New Repair', 'repairs');
?>
<section class="admin-panel admin-panel--narrow">
    <h2>New repair job</h2>
    <p class="admin-hint">Device is received. Add repair expenses and final cost on the job page later.</p>
    <form method="post" class="admin-form" id="pos-repair-form">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="customer_id" id="customer_id" value="<?php echo (int) ($prefillCustomer['id'] ?? 0); ?>">

        <div class="admin-field">
            <label for="pos-customer-search">Customer *</label>
            <input type="search" id="pos-customer-search" placeholder="Search by name or phone…" autocomplete="off"
                   value="<?php echo $prefillCustomer ? htmlspecialchars($prefillCustomer['name']) : ''; ?>">
        </div>
        <div id="pos-customer-results" class="pos-search-results"></div>

        <div class="admin-field">
            <label for="customer_phone">Phone</label>
            <input type="text" id="customer_phone" name="customer_phone"
                   value="<?php echo htmlspecialchars($prefillCustomer['phone'] ?? ''); ?>" placeholder="07XXXXXXXX">
        </div>

        <div class="admin-field">
            <label for="device">Device *</label>
            <input type="text" id="device" name="device" required placeholder="e.g. iPhone 13 Pro Max">
        </div>
        <div class="admin-field">
            <label for="imei_serial">IMEI / Serial</label>
            <input type="text" id="imei_serial" name="imei_serial">
        </div>
        <div class="admin-field">
            <label for="issue_description">Issue *</label>
            <textarea id="issue_description" name="issue_description" rows="3" required placeholder="Screen broken, not charging…"></textarea>
        </div>
        <div class="admin-field-row">
            <div class="admin-field">
                <label for="estimated_cost">Estimated cost (Rs.)</label>
                <input type="number" id="estimated_cost" name="estimated_cost" min="0" step="0.01" value="0">
            </div>
            <div class="admin-field">
                <label for="advance_payment">Advance payment (Rs.)</label>
                <input type="number" id="advance_payment" name="advance_payment" min="0" step="0.01" value="0">
            </div>
        </div>
        <div class="admin-field">
            <label for="payment_method">Advance method</label>
            <select id="payment_method" name="payment_method">
                <?php foreach ($collectMethods as $key => $label): ?>
                <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">Create repair job</button>
        <a href="<?php echo pos_panel_url('repairs.php'); ?>" class="btn btn-ghost">Cancel</a>
    </form>
</section>
<script>
window.POS_REPAIR = <?php echo json_encode(['searchApi' => $searchApi], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo pos_panel_url('assets/repair-new.js'); ?>"></script>
<?php
pos_render_footer();
