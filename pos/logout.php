<?php

require_once __DIR__ . '/includes/init.php';

pos_logout();
header('Location: ' . pos_panel_url('login.php'));
exit;
