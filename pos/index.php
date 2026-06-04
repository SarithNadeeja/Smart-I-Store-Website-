<?php

require_once __DIR__ . '/includes/init.php';

if (pos_logged_in()) {
    $user = pos_current_user();
    header('Location: ' . pos_panel_url(!empty($user['must_change_credentials']) ? 'setup.php' : 'dashboard.php'));
} else {
    header('Location: ' . pos_panel_url('login.php'));
}
exit;
