<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');
admin_require_setup_complete();

$q = trim($_GET['q'] ?? '');

try {
    echo json_encode([
        'ok' => true,
        'items' => admin_search_offer_items(db(), $q),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
