<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

require 'db.php';

$data      = json_decode(file_get_contents('php://input'), true) ?? [];
$username  = $_SESSION['user'];
$productId = intval($data['product_id'] ?? 0);
$attribute = trim($data['attribute']   ?? '');
$score     = intval($data['score']     ?? 0);

if (!$productId || !$attribute || $score < 1 || $score > 5) {
    echo json_encode(['success' => false, 'message' => '資料不完整']);
    exit;
}

try {
    // 插入或更新（同一用戶對同一產品同一屬性只有一筆）
    $pdo->prepare("
        INSERT INTO product_ratings (product_id, username, attribute, score)
        VALUES (:product_id, :username, :attribute, :score)
        ON CONFLICT (product_id, username, attribute) DO UPDATE SET
            score      = EXCLUDED.score,
            created_at = NOW()
    ")->execute([
        ':product_id' => $productId,
        ':username'   => $username,
        ':attribute'  => $attribute,
        ':score'      => $score,
    ]);

    // 回傳最新平均分
    $stmt = $pdo->prepare("
        SELECT ROUND(AVG(score)::numeric, 1) AS avg_score, COUNT(*) AS total
        FROM product_ratings
        WHERE product_id = :product_id AND attribute = :attribute
    ");
    $stmt->execute([':product_id' => $productId, ':attribute' => $attribute]);
    $avg = $stmt->fetch();

    echo json_encode([
        'success'   => true,
        'message'   => '評分已儲存',
        'avg_score' => (float)($avg['avg_score'] ?? 0),
        'total'     => (int)($avg['total'] ?? 0),
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '儲存失敗：' . $e->getMessage()]);
}
