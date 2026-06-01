<?php
session_start();
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => '權限不足']);
    exit;
}

$colorId   = intval($_POST['color_id'] ?? 0);
$colorHex  = trim($_POST['color_hex'] ?? '');
$colorName = trim($_POST['color_name'] ?? '');

if ($colorId <= 0) {
    echo json_encode(['success' => false, 'message' => '缺少色號']);
    exit;
}
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colorHex)) {
    echo json_encode(['success' => false, 'message' => '顏色格式不正確']);
    exit;
}
if ($colorName === '') {
    echo json_encode(['success' => false, 'message' => '請輸入色號名稱']);
    exit;
}

$stmt = $pdo->prepare("UPDATE product_colors SET color_hex = ?, color_name = ? WHERE color_id = ?");
$stmt->execute([$colorHex, $colorName, $colorId]);

echo json_encode(['success' => true, 'color_name' => $colorName]);
