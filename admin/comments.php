<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_csrf_verify();
        $action = $_POST['action'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Comment not found.');
        }

        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM customer_reviews WHERE id = :id')->execute(['id' => $id]);
            admin_flash('success', 'Comment deleted.');
        } elseif ($action === 'toggle') {
            $pdo->prepare('UPDATE customer_reviews SET is_active = NOT is_active WHERE id = :id')->execute(['id' => $id]);
            admin_flash('success', 'Comment visibility updated.');
        }
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
    }
    header('Location: ' . admin_url('comments.php'));
    exit;
}

$comments = $pdo->query(
    'SELECT id, name, comment, is_active, created_at
     FROM customer_reviews
     ORDER BY created_at DESC, id DESC'
)->fetchAll();

admin_render_header('Customer Comments', 'comments');
?>
<div class="admin-panel">
    <div class="admin-panel-head">
        <h2>Customer comments</h2>
        <span class="admin-muted"><?php echo count($comments); ?> total</span>
    </div>

    <?php if (!$comments): ?>
    <p class="admin-empty">No comments yet. Visitors can post comments from the bottom of the homepage.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Comment</th>
                    <th>Posted</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comments as $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                    <td class="admin-comment-cell"><?php echo nl2br(htmlspecialchars($row['comment'])); ?></td>
                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string) $row['created_at']))); ?></td>
                    <td><?php echo !empty($row['is_active']) ? 'Visible' : 'Hidden'; ?></td>
                    <td class="admin-table__actions">
                        <form method="post" class="admin-inline-form">
                            <?php admin_csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                            <button type="submit" class="admin-link-btn">
                                <?php echo !empty($row['is_active']) ? 'Hide' : 'Show'; ?>
                            </button>
                        </form>
                        <form method="post" class="admin-inline-form"
                              onsubmit="return confirm('Delete this comment permanently?');">
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
