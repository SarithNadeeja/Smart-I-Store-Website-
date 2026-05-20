<?php

require_once __DIR__ . '/includes/init.php';
admin_logout();
header('Location: ' . admin_url('login.php'));
exit;
