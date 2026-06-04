<?php

require_once __DIR__ . '/includes/init.php';

if (admin_logged_in()) {
    $user = admin_current_user();
    header('Location: ' . admin_url(!empty($user['must_change_credentials']) ? 'setup.php' : 'dashboard.php'));
    exit;
}

$error = '';

$dbError = '';
try {
    db();
} catch (Throwable $e) {
    $dbError = 'Database connection failed. Check PostgreSQL and database "' . DB_NAME . '" for user "' . DB_USER . '".';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($dbError) {
            throw new RuntimeException($dbError);
        }
        admin_csrf_verify();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            throw new RuntimeException('Username and password are required.');
        }

        if (!admin_attempt_login($username, $password)) {
            throw new RuntimeException('Invalid username or password.');
        }

        $user = admin_current_user();
        header('Location: ' . admin_url(!empty($user['must_change_credentials']) ? 'setup.php' : 'dashboard.php'));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php admin_theme_head_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo admin_url('assets/admin-theme.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_url('assets/admin.css'); ?>">
</head>
<body class="admin-auth-body">
    <div class="admin-auth-theme-wrap">
        <?php admin_theme_toggle(); ?>
    </div>
    <div class="admin-auth-card">
        <h1>Admin Login</h1>
        <p class="admin-auth-sub">Sign in to manage <?php echo htmlspecialchars(SITE_NAME); ?></p>
        <?php if ($dbError): ?>
        <div class="admin-alert admin-alert--error"><?php echo htmlspecialchars($dbError); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="admin-alert admin-alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
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
<script src="<?php echo admin_url('assets/admin-theme.js'); ?>"></script>
</body>
</html>
