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

$sr = $pdo->prepare("SELECT snapshot FROM deleted_colors WHERE color_id = ?");
$sr->execute([$colorId]);
$snap = $sr->fetchColumn();
$row  = $snap ? json_decode($snap, true) : null;

if ($row && is_array($row)) {
    try {
        $cols   = array_keys($row);
        $colSql = implode(',', array_map(fn($c) => "`$c`", $cols));
        $place  = implode(',', array_fill(0, count($cols), '?'));
        $pdo->prepare("INSERT INTO product_colors ($colSql) VALUES ($place)")->execute(array_values($row));
        $pdo->prepare("DELETE FROM deleted_colors WHERE color_id = ?")->execute([$colorId]);
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '找不到可復原的紀錄']);
}
