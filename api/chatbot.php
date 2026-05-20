<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/store.php';

header('Content-Type: application/json; charset=utf-8');

if (!db_available()) {
    echo json_encode([
        'ok' => false,
        'error' => 'Service unavailable',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'bootstrap':
        echo json_encode([
            'ok' => true,
            'brands' => store_get_bot_brands(),
            'accessoryCategories' => store_get_bot_accessory_categories(),
            'whatsappUrl' => whatsapp_url(SITE_WHATSAPP_1),
            'whatsappNumber' => SITE_WHATSAPP_1,
            'telUrl' => 'tel:' . preg_replace('/\D/', '', SITE_WHATSAPP_1),
            'contactUrl' => page_url('contact.php'),
            'siteName' => SITE_NAME,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'search_phone':
        $bid = (int) ($_GET['brand_id'] ?? $_POST['brand_id'] ?? 0);
        $max = (float) ($_GET['max_price'] ?? $_POST['max_price'] ?? 0);
        if ($bid <= 0 || $max <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid parameters'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($max > 50000000) {
            $max = 50000000;
        }
        $products = store_bot_search_phones($bid, $max);
        echo json_encode(['ok' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);
        break;

    case 'search_accessory':
        $cid = (int) ($_GET['category_id'] ?? $_POST['category_id'] ?? 0);
        $bid = (int) ($_GET['brand_id'] ?? $_POST['brand_id'] ?? 0);
        $max = (float) ($_GET['max_price'] ?? $_POST['max_price'] ?? 0);
        if ($cid <= 0 || $bid <= 0 || $max <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid parameters'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($max > 50000000) {
            $max = 50000000;
        }
        $products = store_bot_search_accessories($cid, $bid, $max);
        echo json_encode(['ok' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
}
