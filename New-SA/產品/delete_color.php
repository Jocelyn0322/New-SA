<?php
session_start();
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => '權限不足']);
    exit;
}

$colorId = intval($_POST['color_id'] ?? 0);
if ($colorId <= 0) {
    echo json_encode(['success' => false, 'message' => '缺少色號編號']);
    exit;
}

// 封存可復原，不永久刪除
$pdo->exec("CREATE TABLE IF NOT EXISTS deleted_colors (
    color_id INT PRIMARY KEY, p_id INT, color_name VARCHAR(255),
    snapshot LONGTEXT, deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$cr = $pdo->prepare("SELECT * FROM product_colors WHERE color_id = ?");
$cr->execute([$colorId]);
$crow = $cr->fetch(PDO::FETCH_ASSOC);
if ($crow) {
    $pdo->prepare("INSERT INTO deleted_colors (color_id, p_id, color_name, snapshot)
                   VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE p_id=VALUES(p_id), color_name=VALUES(color_name),
                       snapshot=VALUES(snapshot), deleted_at=NOW()")
        ->execute([$colorId, $crow['p_id'] ?? 0, $crow['color_name'] ?? '', json_encode($crow, JSON_UNESCAPED_UNICODE)]);
    $pdo->prepare("DELETE FROM product_colors WHERE color_id = ?")->execute([$colorId]);
}

echo json_encode(['success' => true]);
