<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => '只支援 POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user']) || trim((string)$_SESSION['user']) === '') {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => '請先登入'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => '請提供有效 JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

$username  = trim((string)$_SESSION['user']);
$productId = trim((string)($payload['productId'] ?? ''));
$source    = trim((string)($payload['source'] ?? 'ai_recommendation'));

if ($productId === '') {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => '缺少 productId'], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/../db.php';

$stmt = $pdo->prepare('
    INSERT INTO user_product_interactions (username, product_id, source, click_count, clicked_at)
    VALUES (:username, :product_id, :source, 1, NOW())
    ON DUPLICATE KEY UPDATE
        click_count = click_count + 1,
        clicked_at  = NOW()
');
$stmt->execute([
    ':username'   => $username,
    ':product_id' => $productId,
    ':source'     => $source !== '' ? $source : 'ai_recommendation',
]);

echo json_encode(['error' => false, 'message' => '點擊紀錄已儲存'], JSON_UNESCAPED_UNICODE);
