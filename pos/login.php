<?php

require_once __DIR__ . '/includes/init.php';

if (pos_logged_in()) {
    $user = pos_current_user();
    header('Location: ' . pos_panel_url(!empty($user['must_change_credentials']) ? 'setup.php' : 'dashboard.php'));
    exit;
}

$error = '';
$dbError = '';
try {
    db();
} catch (Throwable $e) {
    $dbError = 'Database connection failed. Ensure PostgreSQL is running.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($dbError) {
            throw new RuntimeException($dbError);
        }
        pos_csrf_verify();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            throw new RuntimeException('Username and password are required.');
        }

        if (!pos_attempt_login($username, $password)) {
            throw new RuntimeException('Invalid username or password.');
        }

        $user = pos_current_user();
        header('Location: ' . pos_panel_url(!empty($user['must_change_credentials']) ? 'setup.php' : 'dashboard.php'));
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
    <title>POS Login | <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url('admin/assets/admin-theme.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('admin/assets/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo pos_panel_url('assets/pos.css'); ?>">
</head>
<body class="admin-auth-body pos-auth-body">
    <div class="admin-auth-theme-wrap">
        <?php pos_theme_toggle(); ?>
    </div>
    <div class="admin-auth-card">
        <h1>POS Login</h1>
        <p class="admin-auth-sub">Sign in to manage store sales, income, and expenses</p>
        <?php if ($dbError): ?>
        <div class="admin-alert admin-alert--error"><?php echo htmlspecialchars($dbError); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="admin-alert admin-alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
            <div class="admin-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="username" required>
            </div>
            <div class="admin-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>
    </div>
<script src="<?php echo base_url('admin/assets/admin-theme.js'); ?>"></script>
</body>
</html>
