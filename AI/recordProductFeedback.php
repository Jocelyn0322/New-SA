<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function clampWeight(float $value): float
{
    if ($value < 0.6) return 0.6;
    if ($value > 2.2) return 2.2;
    return $value;
}

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
$feedbackType = trim((string)($payload['feedbackType'] ?? ''));
$detectedLab = is_array($payload['detectedLab'] ?? null) ? $payload['detectedLab'] : [];

$allowedFeedback = ['just_right', 'too_yellow', 'too_dark', 'too_dry', 'too_oily'];
if ($productId === '' || !in_array($feedbackType, $allowedFeedback, true)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'feedback 參數不正確'], JSON_UNESCAPED_UNICODE);
    exit;
}

$labL = isset($detectedLab['L']) ? (float)$detectedLab['L'] : null;
$laba = isset($detectedLab['a']) ? (float)$detectedLab['a'] : null;
$labb = isset($detectedLab['b']) ? (float)$detectedLab['b'] : null;

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

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `UserProductFeedback` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(100) NOT NULL,
        `product_id` VARCHAR(64) NOT NULL,
        `feedback_type` VARCHAR(30) NOT NULL,
        `detected_L` DECIMAL(7,3) NULL,
        `detected_a` DECIMAL(7,3) NULL,
        `detected_b` DECIMAL(7,3) NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_user_product` (`username`, `product_id`),
        INDEX `idx_feedback_user_time` (`username`, `updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

// Migration: older deployments used ENUM for feedback_type, which blocks new feedback values.
$feedbackTypeColumnStmt = $pdo->query("SHOW COLUMNS FROM `UserProductFeedback` LIKE 'feedback_type'");
$feedbackTypeColumn = $feedbackTypeColumnStmt ? $feedbackTypeColumnStmt->fetch(PDO::FETCH_ASSOC) : null;
if (is_array($feedbackTypeColumn) && isset($feedbackTypeColumn['Type'])) {
    $columnType = strtolower((string)$feedbackTypeColumn['Type']);
    if (str_starts_with($columnType, 'enum(')) {
        $pdo->exec('ALTER TABLE `UserProductFeedback` MODIFY `feedback_type` VARCHAR(30) NOT NULL');
    }
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `UserLabWeights` (
        `username` VARCHAR(100) PRIMARY KEY,
        `weight_L` DECIMAL(5,3) NOT NULL DEFAULT 1.000,
        `weight_a` DECIMAL(5,3) NOT NULL DEFAULT 1.000,
        `weight_b` DECIMAL(5,3) NOT NULL DEFAULT 1.000,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$clickCheck = $pdo->prepare(
    'SELECT COUNT(*) AS cnt FROM `UserProductClicks` WHERE username = :username AND product_id = :product_id'
);
$clickCheck->execute([
    ':username' => $username,
    ':product_id' => $productId,
]);
$clickCount = (int)($clickCheck->fetchColumn() ?: 0);

if ($clickCount <= 0) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => '請先有該產品的點擊紀錄，再提交妝效回饋'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$upsertFeedback = $pdo->prepare(
    'INSERT INTO `UserProductFeedback` (`username`, `product_id`, `feedback_type`, `detected_L`, `detected_a`, `detected_b`)
     VALUES (:username, :product_id, :feedback_type, :detected_L, :detected_a, :detected_b)
     ON DUPLICATE KEY UPDATE
       `feedback_type` = VALUES(`feedback_type`),
       `detected_L` = VALUES(`detected_L`),
       `detected_a` = VALUES(`detected_a`),
       `detected_b` = VALUES(`detected_b`),
       `updated_at` = CURRENT_TIMESTAMP'
);
$upsertFeedback->execute([
    ':username' => $username,
    ':product_id' => $productId,
    ':feedback_type' => $feedbackType,
    ':detected_L' => $labL,
    ':detected_a' => $laba,
    ':detected_b' => $labb,
]);

$getWeights = $pdo->prepare('SELECT `weight_L`, `weight_a`, `weight_b` FROM `UserLabWeights` WHERE `username` = :username LIMIT 1');
$getWeights->execute([':username' => $username]);
$currentWeights = $getWeights->fetch(PDO::FETCH_ASSOC);

$weightL = isset($currentWeights['weight_L']) ? (float)$currentWeights['weight_L'] : 1.0;
$weightA = isset($currentWeights['weight_a']) ? (float)$currentWeights['weight_a'] : 1.0;
$weightB = isset($currentWeights['weight_b']) ? (float)$currentWeights['weight_b'] : 1.0;

if ($feedbackType === 'too_dark') {
    $weightL += 0.08;
} elseif ($feedbackType === 'too_yellow') {
    $weightB += 0.08;
    $weightA += 0.02;
} elseif ($feedbackType === 'just_right') {
    // "剛好" 時將權重緩慢回歸 1.0，避免權重長期漂移
    $weightL = ($weightL * 0.95) + 0.05;
    $weightA = ($weightA * 0.95) + 0.05;
    $weightB = ($weightB * 0.95) + 0.05;
}

$weightL = clampWeight($weightL);
$weightA = clampWeight($weightA);
$weightB = clampWeight($weightB);

$upsertWeights = $pdo->prepare(
    'INSERT INTO `UserLabWeights` (`username`, `weight_L`, `weight_a`, `weight_b`)
     VALUES (:username, :weight_L, :weight_a, :weight_b)
     ON DUPLICATE KEY UPDATE
       `weight_L` = VALUES(`weight_L`),
       `weight_a` = VALUES(`weight_a`),
       `weight_b` = VALUES(`weight_b`),
       `updated_at` = CURRENT_TIMESTAMP'
);
$upsertWeights->execute([
    ':username' => $username,
    ':weight_L' => $weightL,
    ':weight_a' => $weightA,
    ':weight_b' => $weightB,
]);

$feedbackText = [
    'just_right' => '色號剛好',
    'too_yellow' => '偏黃',
    'too_dark' => '偏暗',
    'too_dry' => '太乾',
    'too_oily' => '太油',
];

$message = in_array($feedbackType, ['too_dry', 'too_oily'], true)
    ? '回饋已記錄，將優化妝感與質地推薦'
    : '回饋已記錄，已更新 LAB 匹配權重';

echo json_encode([
    'error' => false,
    'message' => $message,
    'feedbackLabel' => $feedbackText[$feedbackType] ?? $feedbackType,
    'weights' => [
        'L' => round($weightL, 3),
        'a' => round($weightA, 3),
        'b' => round($weightB, 3),
    ]
], JSON_UNESCAPED_UNICODE);
