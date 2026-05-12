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

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => '請提供有效 JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = trim((string)$_SESSION['user']);
$productId = trim((string)($payload['productId'] ?? ''));
$source = trim((string)($payload['source'] ?? 'ai_recommendation'));

if ($productId === '') {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => '缺少 productId'], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/db.php';

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `UserProductClicks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(100) NOT NULL,
        `product_id` VARCHAR(64) NOT NULL,
        `source` VARCHAR(50) NOT NULL DEFAULT "ai_recommendation",
        `clicked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_click_user_time` (`username`, `clicked_at`),
        INDEX `idx_click_user_product` (`username`, `product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$stmt = $pdo->prepare('INSERT INTO `UserProductClicks` (`username`, `product_id`, `source`) VALUES (:username, :product_id, :source)');
$stmt->execute([
    ':username' => $username,
    ':product_id' => $productId,
    ':source' => $source !== '' ? $source : 'ai_recommendation',
]);

echo json_encode([
    'error' => false,
    'message' => '點擊紀錄已儲存',
], JSON_UNESCAPED_UNICODE);
