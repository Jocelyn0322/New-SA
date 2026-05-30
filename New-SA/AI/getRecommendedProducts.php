<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/recommend_helper.php';

try {
    $skinType     = trim((string)($_GET['skinType']     ?? ''));
    $makeupFinish = trim((string)($_GET['makeupFinish'] ?? ''));
    $sensitive    = filter_var($_GET['sensitive'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $limit        = (int)($_GET['limit'] ?? 6);

    $ranked = getRecommendedProductRanking($pdo, $skinType, $makeupFinish, $sensitive, $limit);

    echo json_encode([
        'status' => 'success',
        'skinType' => $skinType,
        'sensitive' => $sensitive,
        'products' => $ranked,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
