<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/store.php';
require_once dirname(__DIR__, 2) . '/includes/uploads.php';

function admin_url(string $path = ''): string
{
    return base_url('admin/' . ltrim($path, '/'));
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function admin_csrf_verify(): void
{
    $token = $_POST['csrf'] ?? '';
    if ($token === '' || !hash_equals(admin_csrf_token(), $token)) {
        throw new RuntimeException('Invalid security token. Please try again.');
    }
}

function admin_flash(string $type, string $message): void
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

function admin_consume_flash(): ?array
{
    if (empty($_SESSION['admin_flash'])) {
        return null;
    }
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    return $flash;
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_user_id']);
}

function admin_current_user(): ?array
{
    if (!admin_logged_in()) {
        return null;
    }

    static $user = null;
    if ($user !== null) {
        return $user;
    }

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => (int) $_SESSION['admin_user_id']]);
    $user = $stmt->fetch() ?: null;

    if ($user === null) {
        unset($_SESSION['admin_user_id'], $_SESSION['admin_username']);
    }

    return $user;
}

function admin_require_login(): array
{
    if (!admin_logged_in()) {
        header('Location: ' . admin_url('login.php'));
        exit;
    }

    $user = admin_current_user();
    if ($user === null) {
        header('Location: ' . admin_url('login.php'));
        exit;
    }

    return $user;
}

function admin_require_setup_complete(): array
{
    $user = admin_require_login();
    if (!empty($user['must_change_credentials'])) {
        header('Location: ' . admin_url('setup.php'));
        exit;
    }
    return $user;
}

function admin_require_setup_pending(): array
{
    $user = admin_require_login();
    if (empty($user['must_change_credentials'])) {
        header('Location: ' . admin_url('dashboard.php'));
        exit;
    }
    return $user;
}

function admin_attempt_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = :u');
    $stmt->execute(['u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['admin_user_id'] = (int) $user['id'];
    $_SESSION['admin_username'] = $user['username'];

    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function admin_update_user_credentials(int $userId, string $username, string $password, bool $clearMustChange = true): void
{
    $sql = 'UPDATE admin_users SET username = :u, password_hash = :h';
    if ($clearMustChange) {
        $sql .= ', must_change_credentials = FALSE';
    }
    $sql .= ' WHERE id = :id';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        'u' => $username,
        'h' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);

    if ($userId === (int) ($_SESSION['admin_user_id'] ?? 0)) {
        $_SESSION['admin_username'] = $username;
    }
}

function admin_change_password(int $userId, string $currentPassword, string $newPassword): void
{
    $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($currentPassword, $hash)) {
        throw new RuntimeException('Current password is incorrect.');
    }

    $stmt = db()->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id');
    $stmt->execute([
        'h' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);
}

function admin_create_user(string $username, string $password): void
{
    $stmt = db()->prepare(
        'INSERT INTO admin_users (username, password_hash, must_change_credentials)
         VALUES (:u, :h, FALSE)'
    );
    $stmt->execute([
        'u' => $username,
        'h' => password_hash($password, PASSWORD_DEFAULT),
    ]);
}

function admin_delete_user(int $userId): void
{
    if ($userId === (int) ($_SESSION['admin_user_id'] ?? 0)) {
        throw new RuntimeException('You cannot delete your own account while logged in.');
    }

    $count = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($count <= 1) {
        throw new RuntimeException('At least one admin user must remain.');
    }

    $stmt = db()->prepare('DELETE FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
}
