<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$user = pos_require_setup_complete();
$isManager = pos_is_manager($user);
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pos_csrf_verify();
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            pos_save_repair_job_simple(
                $id,
                (float) ($_POST['final_cost'] ?? 0),
                trim($_POST['notes'] ?? ''),
                (int) $user['id']
            );
            pos_flash('success', 'Saved.');
        } elseif ($action === 'add_expense') {
            pos_add_repair_expense(
                $id,
                trim($_POST['expense_description'] ?? ''),
                (float) ($_POST['expense_cost'] ?? 0),
                (int) $user['id']
            );
            pos_flash('success', 'Repair expense added.');
        } elseif ($action === 'remove_expense') {
            pos_remove_repair_expense((int) ($_POST['expense_id'] ?? 0), $id, (int) $user['id']);
            pos_flash('success', 'Expense removed.');
        } elseif ($action === 'complete') {
            pos_mark_repair_completed($id, (int) $user['id']);
            pos_flash('success', 'Marked as completed — ready for pickup.');
        } elseif ($action === 'deliver') {
            pos_mark_repair_delivered(
                $id,
                (int) $user['id'],
                (float) ($_POST['received_amount'] ?? 0),
                (string) ($_POST['payment_method'] ?? 'cash'),
                isset($_POST['allow_due_balance']),
                $isManager
            );
            pos_flash('success', 'Marked as delivered.');
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        pos_flash('error', $e->getMessage());
    }
    header('Location: ' . pos_panel_url('repair-view.php?id=' . $id));
    exit;
}

$job = $id > 0 ? pos_get_repair_job($id) : null;
if (!$job) {
    pos_flash('error', 'Repair job not found.');
    header('Location: ' . pos_panel_url('repairs.php'));
    exit;
}

$totalPaid = pos_repair_total_paid($job);
$balanceDue = pos_repair_balance_due($job);
$expenseTotal = pos_repair_expense_total($job);
$repairProfit = pos_repair_profit($job);
$collectMethods = pos_repair_collect_methods();
$statusClass = strtolower(preg_replace('/[^a-z]/', '', $job['status']));
if (!in_array($statusClass, ['received', 'completed', 'delivered', 'cancelled'], true)) {
    $statusClass = 'received';
}
$isClosed = in_array($job['status'], ['Delivered', 'Cancelled'], true);
$canEdit = !$isClosed;
$canComplete = $canEdit && $job['status'] !== 'Completed';
$canDeliver = $canEdit;
$needsPaymentOnDeliver = $balanceDue > 0.009;
$deviceLabel = trim($job['device_brand'] . ' ' . $job['device_model']) ?: '—';

pos_render_header($job['job_no'], 'repairs');
?>
<div class="pos-repair-view">
    <div class="pos-repair-view__toolbar">
        <a href="<?php echo pos_panel_url('repairs.php'); ?>" class="btn btn-ghost">← Repairs</a>
        <span class="pos-repair-view__job"><?php echo htmlspecialchars($job['job_no']); ?></span>
        <a href="<?php echo pos_panel_url('repair-print.php?id=' . (int) $job['id']); ?>" class="btn btn-primary" target="_blank">Print receipt</a>
    </div>

    <div class="pos-repair-cards">
        <section class="pos-repair-card">
            <h2 class="pos-repair-card__title">Repair details</h2>
            <span class="pos-repair-badge pos-repair-badge--<?php echo htmlspecialchars($statusClass); ?>">
                <?php echo htmlspecialchars($job['status']); ?>
            </span>
            <dl class="pos-repair-facts pos-repair-facts--simple">
                <div><dt>Customer</dt><dd><?php echo htmlspecialchars($job['customer_name']); ?></dd></div>
                <div><dt>Phone</dt><dd><?php echo htmlspecialchars($job['customer_phone'] ?: '—'); ?></dd></div>
                <div><dt>Device</dt><dd><?php echo htmlspecialchars($deviceLabel); ?></dd></div>
                <?php if ($job['imei_serial'] !== ''): ?>
                <div><dt>IMEI / Serial</dt><dd><?php echo htmlspecialchars($job['imei_serial']); ?></dd></div>
                <?php endif; ?>
                <div><dt>Issue</dt><dd><?php echo nl2br(htmlspecialchars($job['issue_description'])); ?></dd></div>
            </dl>
        </section>

        <section class="pos-repair-card">
            <h2 class="pos-repair-card__title">Repair cost</h2>
            <form method="post" class="pos-repair-cost-form">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
                <input type="hidden" name="action" value="save">

                <div class="pos-repair-cost-grid">
                    <div class="pos-repair-cost-row">
                        <span class="pos-repair-cost-label">Estimated cost</span>
                        <span class="pos-repair-cost-value"><?php echo pos_format_money((float) $job['estimated_cost']); ?></span>
                    </div>
                    <div class="pos-repair-cost-row pos-repair-cost-row--input">
                        <label class="pos-repair-cost-label" for="final_cost">Final cost (Rs.)</label>
                        <input type="number" id="final_cost" name="final_cost" min="0" step="0.01"
                               value="<?php echo (float) $job['final_cost'] > 0 ? htmlspecialchars((string) $job['final_cost']) : ''; ?>"
                               placeholder="Customer charge" <?php echo $canEdit ? '' : 'readonly'; ?>>
                    </div>
                    <div class="pos-repair-cost-row">
                        <span class="pos-repair-cost-label">Advance / paid</span>
                        <span class="pos-repair-cost-value"><?php echo pos_format_money($totalPaid); ?></span>
                    </div>
                    <div class="pos-repair-cost-row pos-repair-cost-row--balance">
                        <span class="pos-repair-cost-label">Balance due</span>
                        <span class="pos-repair-cost-value"><?php echo pos_format_money($balanceDue); ?></span>
                    </div>
                </div>

                <div class="admin-field">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Optional"<?php echo $canEdit ? '' : ' readonly'; ?>><?php echo htmlspecialchars($job['notes']); ?></textarea>
                </div>

                <?php if ($canEdit): ?>
                <button type="submit" class="btn btn-primary btn-lg pos-repair-btn">Save</button>
                <?php endif; ?>
            </form>

            <div class="pos-repair-actions">
                <?php if ($canComplete): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn btn-lg pos-repair-btn pos-repair-btn--complete">Mark completed</button>
                </form>
                <?php endif; ?>
                <?php if ($canDeliver): ?>
                <button type="button" class="btn btn-lg pos-repair-btn pos-repair-btn--deliver" id="btn-mark-delivered"
                        data-balance="<?php echo htmlspecialchars((string) $balanceDue); ?>"
                        data-needs-payment="<?php echo $needsPaymentOnDeliver ? '1' : '0'; ?>">
                    Mark delivered
                </button>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="pos-repair-card pos-repair-card--wide">
        <h2 class="pos-repair-card__title">Repair expenses <small>(for profit)</small></h2>
        <p class="admin-hint">Add parts/materials used on this job. Not shown to customers — used to calculate profit only.</p>

        <?php if ($job['expenses']): ?>
        <ul class="pos-repair-expense-list">
            <?php foreach ($job['expenses'] as $exp): ?>
            <li class="pos-repair-expense-list__item">
                <span class="pos-repair-expense-list__name"><?php echo htmlspecialchars($exp['part_description']); ?></span>
                <span class="pos-repair-expense-list__cost"><?php echo pos_format_money((float) $exp['cost']); ?></span>
                <?php if ($canEdit): ?>
                <form method="post" class="pos-repair-expense-list__remove" onsubmit="return confirm('Remove this expense?');">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
                    <input type="hidden" name="action" value="remove_expense">
                    <input type="hidden" name="expense_id" value="<?php echo (int) $exp['id']; ?>">
                    <button type="submit" class="admin-link-btn admin-link-btn--danger" aria-label="Remove">×</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="admin-empty">No expenses added yet.</p>
        <?php endif; ?>

        <?php if ($canEdit): ?>
        <form method="post" class="pos-repair-expense-add admin-form--inline">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
            <input type="hidden" name="action" value="add_expense">
            <div class="admin-field admin-field--grow">
                <label for="expense_description">Expense description</label>
                <input type="text" id="expense_description" name="expense_description" required
                       placeholder="e.g. Display, Battery, Adhesive" list="repair-expense-suggestions">
                <datalist id="repair-expense-suggestions">
                    <option value="Display"></option>
                    <option value="Battery"></option>
                    <option value="Charging IC"></option>
                    <option value="Tempered Glass"></option>
                    <option value="Speaker"></option>
                    <option value="Adhesive"></option>
                </datalist>
            </div>
            <div class="admin-field">
                <label for="expense_cost">Cost (Rs.)</label>
                <input type="number" id="expense_cost" name="expense_cost" min="0.01" step="0.01" required>
            </div>
            <button type="submit" class="btn btn-primary">Add expense</button>
        </form>
        <?php endif; ?>

        <div class="pos-repair-profit-summary">
            <div><span>Total repair expense</span><strong><?php echo pos_format_money($expenseTotal); ?></strong></div>
            <div><span>Repair revenue (final cost)</span><strong><?php echo (float) $job['final_cost'] > 0 ? pos_format_money((float) $job['final_cost']) : '—'; ?></strong></div>
            <div class="pos-repair-profit-summary__profit">
                <span>Repair profit</span>
                <strong><?php echo (float) $job['final_cost'] > 0 ? pos_format_money($repairProfit) : '—'; ?></strong>
            </div>
        </div>
    </section>
</div>

<?php if ($canDeliver): ?>
<div class="pos-modal" id="deliver-modal" hidden>
    <div class="pos-modal__backdrop" data-close-modal></div>
    <div class="pos-modal__dialog" role="dialog" aria-labelledby="deliver-modal-title" aria-modal="true">
        <h2 id="deliver-modal-title" class="pos-modal__title">Receive payment</h2>
        <p class="pos-modal__text" id="deliver-modal-message"></p>
        <form method="post" class="admin-form" id="deliver-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
            <input type="hidden" name="action" value="deliver">
            <div class="admin-field">
                <label for="received_amount">Amount received (Rs.)</label>
                <input type="number" id="received_amount" name="received_amount" min="0" step="0.01">
            </div>
            <div class="admin-field">
                <label for="payment_method">Payment method</label>
                <select id="payment_method" name="payment_method">
                    <?php foreach ($collectMethods as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($isManager): ?>
            <label class="admin-check pos-modal__check">
                <input type="checkbox" name="allow_due_balance" id="allow_due_balance" value="1">
                Allow delivery with due balance (manager)
            </label>
            <?php endif; ?>
            <div class="pos-modal__actions">
                <button type="button" class="btn btn-ghost" data-close-modal>Back</button>
                <button type="submit" class="btn btn-primary btn-lg">Confirm delivery</button>
            </div>
        </form>
        <form method="post" id="deliver-form-direct" hidden>
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
            <input type="hidden" name="action" value="deliver">
            <input type="hidden" name="received_amount" value="0">
            <input type="hidden" name="payment_method" value="cash">
        </form>
    </div>
</div>
<?php endif; ?>

<script>
window.POS_REPAIR_VIEW = {
    balanceDue: <?php echo json_encode($balanceDue); ?>,
    needsPayment: <?php echo $needsPaymentOnDeliver ? 'true' : 'false'; ?>
};
</script>
<script src="<?php echo pos_panel_url('assets/repair-view.js'); ?>"></script>
<?php
pos_render_footer();
