<?php
session_start();
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => '權限不足']);
    exit;
}

$pId       = intval($_POST['p_id'] ?? 0);
$colorName = trim($_POST['color_name'] ?? '');
$colorHex  = trim($_POST['color_hex'] ?? '');

if ($pId <= 0 || $colorName === '') {
    echo json_encode(['success' => false, 'message' => '缺少必填欄位']);
    exit;
}

if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colorHex)) {
    echo json_encode(['success' => false, 'message' => '顏色格式不正確']);
    exit;
}

$stmt = $pdo->prepare(
    "INSERT INTO product_colors (p_id, color_name, color_hex, color_img) VALUES (?, ?, ?, '') RETURNING color_id"
);
$stmt->execute([$pId, $colorName, $colorHex]);
$newId = $stmt->fetchColumn();

echo json_encode(['success' => true, 'color_id' => $newId]);
