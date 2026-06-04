<?php

require_once __DIR__ . '/includes/init.php';

$user = pos_require_setup_pending();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pos_csrf_verify();
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if ($username === '' || strlen($username) < 3) {
            throw new RuntimeException('Username must be at least 3 characters.');
        }
        if (strlen($password) < 6) {
            throw new RuntimeException('Password must be at least 6 characters.');
        }
        if ($password !== $confirm) {
            throw new RuntimeException('Passwords do not match.');
        }
        if ($username === 'admin' && $password === 'admin') {
            throw new RuntimeException('Choose a new username and password — defaults are not allowed.');
        }

        pos_update_staff_credentials((int) $user['id'], $username, $password, $name);
        pos_flash('success', 'Credentials updated. Welcome to POS.');
        header('Location: ' . pos_panel_url('dashboard.php'));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php pos_theme_head_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Setup | <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url('admin/assets/admin-theme.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('admin/assets/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo pos_panel_url('assets/pos.css'); ?>">
</head>
<body class="admin-auth-body pos-auth-body">
    <div class="admin-auth-theme-wrap">
        <?php pos_theme_toggle(); ?>
    </div>
    <div class="admin-auth-card admin-auth-card--wide">
        <h1>First-time POS setup</h1>
        <p class="admin-auth-sub">Change the default username and password before continuing.</p>
        <?php if ($error): ?>
        <div class="admin-alert admin-alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
            <div class="admin-field">
                <label for="name">Display name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?: $user['username']); ?>">
            </div>
            <div class="admin-field">
                <label for="username">New username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            <div class="admin-field">
                <label for="password">New password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required>
            </div>
            <div class="admin-field">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Save and continue</button>
        </form>
    </div>
<script src="<?php echo base_url('admin/assets/admin-theme.js'); ?>"></script>
</body>
</html>
