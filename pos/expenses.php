<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$user = pos_require_setup_complete();
$today = date('Y-m-d');
$categories = pos_expense_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pos_csrf_verify();
        if (($_POST['action'] ?? '') !== 'save') {
            throw new RuntimeException('Unknown action.');
        }
        pos_add_expense(
            trim($_POST['expense_date'] ?? $today),
            trim($_POST['category'] ?? ''),
            trim($_POST['description'] ?? ''),
            (float) ($_POST['amount'] ?? 0),
            (int) $user['id']
        );
        pos_flash('success', 'Expense recorded.');
    } catch (Throwable $e) {
        pos_flash('error', $e->getMessage());
    }
    header('Location: ' . pos_panel_url('expenses.php'));
    exit;
}

$stmt = db()->prepare(
    'SELECT e.*, s.name AS staff_name
     FROM pos_expenses e
     LEFT JOIN pos_staff s ON s.id = e.created_by
     WHERE e.expense_date = :d
     ORDER BY e.created_at DESC'
);
$stmt->execute(['d' => $today]);
$expenses = $stmt->fetchAll();

$totalStmt = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM pos_expenses WHERE expense_date = :d');
$totalStmt->execute(['d' => $today]);
$todayTotal = (float) $totalStmt->fetchColumn();

pos_render_header('Expenses', 'expenses');
?>
<div class="admin-grid-2">
    <section class="admin-panel">
        <h2>Add expense</h2>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
            <input type="hidden" name="action" value="save">
            <div class="admin-field">
                <label for="expense_date">Date</label>
                <input type="date" id="expense_date" name="expense_date" value="<?php echo htmlspecialchars($today); ?>" required>
            </div>
            <div class="admin-field">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-field">
                <label for="description">Description</label>
                <input type="text" id="description" name="description" required>
            </div>
            <div class="admin-field">
                <label for="amount">Amount (Rs.)</label>
                <input type="number" id="amount" name="amount" min="0.01" step="0.01" required>
            </div>
            <button type="submit" class="btn btn-primary">Save expense</button>
        </form>
    </section>

    <section class="admin-panel">
        <h2>Today (<?php echo htmlspecialchars($today); ?>)</h2>
        <p class="admin-stat-inline">Total: <strong><?php echo pos_format_money($todayTotal); ?></strong></p>
        <?php if (!$expenses): ?>
        <p class="admin-empty">No expenses recorded today.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Time</th><th>Category</th><th>Description</th><th>Amount</th><th>By</th></tr></thead>
                <tbody>
                    <?php foreach ($expenses as $exp): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('H:i', strtotime($exp['created_at']))); ?></td>
                        <td><?php echo htmlspecialchars($exp['category']); ?></td>
                        <td><?php echo htmlspecialchars($exp['description']); ?></td>
                        <td><?php echo pos_format_money((float) $exp['amount']); ?></td>
                        <td><?php echo htmlspecialchars($exp['staff_name'] ?? '—'); ?></td>
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
