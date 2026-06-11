<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');
admin_require_setup_complete();

$categoryId = (int) ($_GET['category_id'] ?? 0);
$brandId = (int) ($_GET['brand_id'] ?? 0);
$modelId = (int) ($_GET['model_id'] ?? 0);
$ram = trim((string) ($_GET['ram'] ?? ''));
$rom = trim((string) ($_GET['rom'] ?? ''));

try {
    if ($categoryId <= 0 || $brandId <= 0 || $modelId <= 0) {
        echo json_encode(['ok' => true, 'found' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $isPhone = store_category_is_phone($categoryId);
    $itemId = admin_find_item_by_catalog(db(), $categoryId, $brandId, $modelId, $isPhone, $ram, $rom);
    if ($itemId <= 0) {
        echo json_encode(['ok' => true, 'found' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = admin_item_lookup_payload(db(), $itemId);
    if (!$payload) {
        echo json_encode(['ok' => true, 'found' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'found' => true,
        'item' => $payload,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
