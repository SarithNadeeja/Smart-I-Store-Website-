<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$user = admin_require_setup_complete();
$users = $pdo->query('SELECT id, username, created_at, must_change_credentials FROM admin_users ORDER BY id ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_csrf_verify();
        $action = $_POST['action'] ?? '';

        if ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['new_password_confirm'] ?? '';
            if (strlen($new) < 6) {
                throw new RuntimeException('New password must be at least 6 characters.');
            }
            if ($new !== $confirm) {
                throw new RuntimeException('New passwords do not match.');
            }
            admin_change_password((int) $user['id'], $current, $new);
            admin_flash('success', 'Password changed.');
        } elseif ($action === 'add_user') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            if ($username === '' || strlen($username) < 3) {
                throw new RuntimeException('Username must be at least 3 characters.');
            }
            if (strlen($password) < 6) {
                throw new RuntimeException('Password must be at least 6 characters.');
            }
            admin_create_user($username, $password);
            admin_flash('success', 'User added.');
        } elseif ($action === 'delete_user') {
            admin_delete_user((int) ($_POST['user_id'] ?? 0));
            admin_flash('success', 'User deleted.');
        }

        header('Location: ' . admin_url('security.php'));
        exit;
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
        header('Location: ' . admin_url('security.php'));
        exit;
    }
}

admin_render_header('Security', 'security');
?>
<div class="admin-grid-2 admin-grid-2--stack">
    <section class="admin-panel">
        <h2>Change password</h2>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="admin-field">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="admin-field">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
            </div>
            <div class="admin-field">
                <label for="new_password_confirm">Confirm new password</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn-primary">Update password</button>
        </form>
    </section>

    <section class="admin-panel">
        <h2>Add user</h2>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="add_user">
            <div class="admin-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="admin-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn-primary">Create user</button>
        </form>
    </section>

    <section class="admin-panel admin-panel--full">
        <h2>Admin users</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Username</th><th>Created</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['username']); ?><?php echo (int) $u['id'] === (int) $user['id'] ? ' (you)' : ''; ?></td>
                        <td><?php echo htmlspecialchars(date('M j, Y', strtotime($u['created_at']))); ?></td>
                        <td><?php echo !empty($u['must_change_credentials']) ? 'Setup required' : 'Active'; ?></td>
                        <td>
                            <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                            <form method="post" class="admin-inline-form" onsubmit="return confirm('Delete this user?');">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                <button type="submit" class="admin-link-btn admin-link-btn--danger">Delete</button>
                            </form>
                            <?php else: ?>
                            —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php
admin_render_footer();
