<?php
session_start();
require 'db.php';
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

$pdo->prepare("DELETE FROM product_colors WHERE color_id = ?")->execute([$colorId]);

echo json_encode(['success' => true]);
