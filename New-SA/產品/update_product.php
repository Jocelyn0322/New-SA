<?php
session_start();
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => '權限不足']);
    exit;
}

$pid  = intval($_POST['product_id'] ?? 0);
$name = trim($_POST['name'] ?? '');

if ($pid <= 0 || $name === '') {
    echo json_encode(['success' => false, 'message' => '缺少必填欄位']);
    exit;
}

$pdo->prepare("
    UPDATE data SET name=?, brand=?, category=?, origin=?, purpose=?, ingredients=?, precautions=?
    WHERE id=?
")->execute([
    $name,
    trim($_POST['brand']        ?? ''),
    trim($_POST['category']     ?? ''),
    trim($_POST['origin']       ?? ''),
    trim($_POST['purpose']      ?? ''),
    trim($_POST['ingredients']  ?? ''),
    trim($_POST['precautions']  ?? ''),
    $pid,
]);

echo json_encode(['success' => true]);
