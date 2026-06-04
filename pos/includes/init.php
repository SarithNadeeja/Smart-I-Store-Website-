<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function pos_panel_url(string $path = ''): string
{
    return base_url('pos/' . ltrim($path, '/'));
}

function pos_theme_storage_key(): string
{
    return 'admin-theme';
}

function pos_theme_head_script(): void
{
    $key = pos_theme_storage_key();
    ?>
<script>
(function () {
    var t = localStorage.getItem(<?php echo json_encode($key); ?>);
    document.documentElement.setAttribute('data-admin-theme', t === 'light' ? 'light' : 'dark');
})();
</script>
    <?php
}

function pos_theme_toggle(): void
{
    ?>
<div class="admin-theme-toggle" role="group" aria-label="Color theme">
    <button type="button" class="admin-theme-toggle__btn" data-theme="light" aria-pressed="false">Light</button>
    <button type="button" class="admin-theme-toggle__btn" data-theme="dark" aria-pressed="false">Dark</button>
</div>
    <?php
}

function pos_csrf_token(): string
{
    if (empty($_SESSION['pos_csrf'])) {
        $_SESSION['pos_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pos_csrf'];
}

function pos_csrf_verify(): void
{
    $token = $_POST['csrf'] ?? '';
    if ($token === '' || !hash_equals(pos_csrf_token(), $token)) {
        throw new RuntimeException('Invalid security token. Please try again.');
    }
}

function pos_flash(string $type, string $message): void
{
    $_SESSION['pos_flash'] = ['type' => $type, 'message' => $message];
}

function pos_consume_flash(): ?array
{
    if (empty($_SESSION['pos_flash'])) {
        return null;
    }
    $flash = $_SESSION['pos_flash'];
    unset($_SESSION['pos_flash']);
    return $flash;
}

function pos_logged_in(): bool
{
    return !empty($_SESSION['pos_staff_id']);
}

function pos_current_user(): ?array
{
    if (!pos_logged_in()) {
        return null;
    }

    static $user = null;
    if ($user !== null) {
        return $user;
    }

    $stmt = db()->prepare('SELECT * FROM pos_staff WHERE id = :id AND status = :st');
    $stmt->execute(['id' => (int) $_SESSION['pos_staff_id'], 'st' => 'active']);
    $user = $stmt->fetch() ?: null;

    if ($user === null) {
        unset($_SESSION['pos_staff_id'], $_SESSION['pos_username'], $_SESSION['pos_staff_name']);
    }

    return $user;
}

function pos_require_login(): array
{
    if (!pos_logged_in()) {
        header('Location: ' . pos_panel_url('login.php'));
        exit;
    }

    $user = pos_current_user();
    if ($user === null) {
        header('Location: ' . pos_panel_url('login.php'));
        exit;
    }

    return $user;
}

function pos_require_setup_complete(): array
{
    $user = pos_require_login();
    if (!empty($user['must_change_credentials'])) {
        header('Location: ' . pos_panel_url('setup.php'));
        exit;
    }
    return $user;
}

function pos_require_setup_pending(): array
{
    $user = pos_require_login();
    if (empty($user['must_change_credentials'])) {
        header('Location: ' . pos_panel_url('dashboard.php'));
        exit;
    }
    return $user;
}

function pos_attempt_login(string $username, string $password): bool
{
    $stmt = db()->prepare(
        "SELECT * FROM pos_staff WHERE username = :u AND status = 'active'"
    );
    $stmt->execute(['u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['pos_staff_id'] = (int) $user['id'];
    $_SESSION['pos_username'] = $user['username'];
    $_SESSION['pos_staff_name'] = $user['name'];
    pos_audit((int) $user['id'], 'login', 'staff', (int) $user['id'], 'POS login');

    return true;
}

function pos_logout(): void
{
    $id = (int) ($_SESSION['pos_staff_id'] ?? 0);
    if ($id > 0) {
        pos_audit($id, 'logout', 'staff', $id, '');
    }
    unset(
        $_SESSION['pos_staff_id'],
        $_SESSION['pos_username'],
        $_SESSION['pos_staff_name'],
        $_SESSION['pos_csrf'],
        $_SESSION['pos_flash']
    );
}

function pos_update_staff_credentials(int $staffId, string $username, string $password, string $name = ''): void
{
    $sql = 'UPDATE pos_staff SET username = :u, password_hash = :h, must_change_credentials = FALSE';
    $params = [
        'u' => $username,
        'h' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $staffId,
    ];
    if ($name !== '') {
        $sql .= ', name = :n';
        $params['n'] = $name;
    }
    $sql .= ' WHERE id = :id';
    db()->prepare($sql)->execute($params);
    if ($staffId === (int) ($_SESSION['pos_staff_id'] ?? 0)) {
        $_SESSION['pos_username'] = $username;
        if ($name !== '') {
            $_SESSION['pos_staff_name'] = $name;
        }
    }
}
