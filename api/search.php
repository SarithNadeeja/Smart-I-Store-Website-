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

$action = $_GET['action'] ?? $_POST['action'] ?? 'suggest';

if ($action !== 'suggest') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
    exit;
}

$q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
$scope = strtolower(trim((string) ($_GET['scope'] ?? $_POST['scope'] ?? 'all')));
if (!in_array($scope, ['products', 'preowned', 'all'], true)) {
    $scope = 'all';
}

$limit = (int) ($_GET['limit'] ?? $_POST['limit'] ?? 8);
$limit = max(1, min(12, $limit));

if ($q === '') {
    echo json_encode(['ok' => true, 'results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'results' => [], 'message' => 'Type at least 2 characters'], JSON_UNESCAPED_UNICODE);
    exit;
}

$results = store_search_suggestions($q, $scope, $limit);

echo json_encode([
    'ok' => true,
    'q' => $q,
    'scope' => $scope,
    'results' => $results,
], JSON_UNESCAPED_UNICODE);
