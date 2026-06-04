<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

$user = pos_require_setup_complete();
if (!pos_is_manager($user)) {
    pos_flash('error', 'Manager access required.');
    header('Location: ' . pos_panel_url('dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pos_csrf_verify();
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = ($_POST['role'] ?? '') === 'manager' ? 'manager' : 'cashier';
            if ($name === '' || $username === '' || strlen($password) < 6) {
                throw new RuntimeException('Name, username, and password (6+ chars) are required.');
            }
            db()->prepare(
                'INSERT INTO pos_staff (name, username, password_hash, role, status)
                 VALUES (:n,:u,:h,:r,:s)'
            )->execute([
                'n' => $name,
                'u' => $username,
                'h' => password_hash($password, PASSWORD_DEFAULT),
                'r' => $role,
                's' => 'active',
            ]);
            pos_flash('success', 'Staff user created.');
        }
        header('Location: ' . pos_panel_url('staff.php'));
        exit;
    } catch (Throwable $e) {
        pos_flash('error', $e->getMessage());
    }
}

$staffList = db()->query('SELECT id, name, username, role, status, created_at FROM pos_staff ORDER BY id ASC')->fetchAll();

pos_render_header('POS Staff', 'reports');
?>
<div class="admin-grid-2">
    <section class="admin-panel">
        <h2>Add staff user</h2>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
            <input type="hidden" name="action" value="add">
            <div class="admin-field">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="admin-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="admin-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="admin-field">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="cashier">Cashier</option>
                    <option value="manager">Manager</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Create user</button>
        </form>
    </section>
    <section class="admin-panel">
        <h2>All staff</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($staffList as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['name']); ?></td>
                        <td><?php echo htmlspecialchars($s['username']); ?></td>
                        <td><?php echo htmlspecialchars($s['role']); ?></td>
                        <td><?php echo htmlspecialchars($s['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php
pos_render_footer();
