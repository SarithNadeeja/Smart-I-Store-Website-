<?php

require_once __DIR__ . '/includes/init.php';

if (!admin_logged_in()) {
    header('Location: ' . admin_url('login.php'));
    exit;
}

$user = admin_current_user();
if (!empty($user['must_change_credentials'])) {
    header('Location: ' . admin_url('setup.php'));
    exit;
}

header('Location: ' . admin_url('dashboard.php'));
exit;
