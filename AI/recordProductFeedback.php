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

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => '請提供有效 JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

$username     = trim((string)$_SESSION['user']);
$productId    = trim((string)($payload['productId']    ?? ''));
$feedbackType = trim((string)($payload['feedbackType'] ?? ''));
$detectedLab  = is_array($payload['detectedLab'] ?? null) ? $payload['detectedLab'] : [];

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

$clickCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM user_product_clicks WHERE username = :username AND product_id = :product_id'
);
$clickCheck->execute([':username' => $username, ':product_id' => $productId]);
if ((int)$clickCheck->fetchColumn() <= 0) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => '請先有該產品的點擊紀錄，再提交妝效回饋'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo->prepare('
    INSERT INTO user_product_feedback (username, product_id, feedback_type, detected_l, detected_a, detected_b)
    VALUES (:username, :product_id, :feedback_type, :detected_l, :detected_a, :detected_b)
    ON CONFLICT (username, product_id) DO UPDATE SET
        feedback_type = EXCLUDED.feedback_type,
        detected_l    = EXCLUDED.detected_l,
        detected_a    = EXCLUDED.detected_a,
        detected_b    = EXCLUDED.detected_b,
        updated_at    = CURRENT_TIMESTAMP
')->execute([
    ':username'      => $username,
    ':product_id'    => $productId,
    ':feedback_type' => $feedbackType,
    ':detected_l'    => $labL,
    ':detected_a'    => $laba,
    ':detected_b'    => $labb,
]);

$getWeights = $pdo->prepare('SELECT weight_l, weight_a, weight_b FROM user_lab_weights WHERE username = :username');
$getWeights->execute([':username' => $username]);
$w = $getWeights->fetch(PDO::FETCH_ASSOC);

$weightL = isset($w['weight_l']) ? (float)$w['weight_l'] : 1.0;
$weightA = isset($w['weight_a']) ? (float)$w['weight_a'] : 1.0;
$weightB = isset($w['weight_b']) ? (float)$w['weight_b'] : 1.0;

if ($feedbackType === 'too_dark') {
    $weightL += 0.08;
} elseif ($feedbackType === 'too_yellow') {
    $weightB += 0.08;
    $weightA += 0.02;
} elseif ($feedbackType === 'just_right') {
    $weightL = ($weightL * 0.95) + 0.05;
    $weightA = ($weightA * 0.95) + 0.05;
    $weightB = ($weightB * 0.95) + 0.05;
}

$weightL = clampWeight($weightL);
$weightA = clampWeight($weightA);
$weightB = clampWeight($weightB);

$pdo->prepare('
    INSERT INTO user_lab_weights (username, weight_l, weight_a, weight_b)
    VALUES (:username, :weight_l, :weight_a, :weight_b)
    ON CONFLICT (username) DO UPDATE SET
        weight_l   = EXCLUDED.weight_l,
        weight_a   = EXCLUDED.weight_a,
        weight_b   = EXCLUDED.weight_b,
        updated_at = CURRENT_TIMESTAMP
')->execute([
    ':username' => $username,
    ':weight_l' => $weightL,
    ':weight_a' => $weightA,
    ':weight_b' => $weightB,
]);

$feedbackText = [
    'just_right' => '色號剛好', 'too_yellow' => '偏黃',
    'too_dark'   => '偏暗',    'too_dry'    => '太乾', 'too_oily' => '太油',
];

echo json_encode([
    'error'         => false,
    'message'       => in_array($feedbackType, ['too_dry', 'too_oily'], true) ? '回饋已記錄，將優化妝感與質地推薦' : '回饋已記錄，已更新 LAB 匹配權重',
    'feedbackLabel' => $feedbackText[$feedbackType] ?? $feedbackType,
    'weights'       => ['L' => round($weightL, 3), 'a' => round($weightA, 3), 'b' => round($weightB, 3)],
], JSON_UNESCAPED_UNICODE);
