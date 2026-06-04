<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
pos_require_setup_complete();

$type = $_GET['type'] ?? '';
$q = trim($_GET['q'] ?? '');

try {
    if ($type === 'products') {
        echo json_encode(['ok' => true, 'items' => pos_search_products($q)], JSON_UNESCAPED_UNICODE);
    } elseif ($type === 'customers') {
        echo json_encode(['ok' => true, 'items' => pos_search_customers($q)], JSON_UNESCAPED_UNICODE);
    } else {
        throw new RuntimeException('Invalid type.');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
