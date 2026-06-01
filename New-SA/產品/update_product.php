<?php
session_start();
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => '權限不足']);
    exit;
}

$pid         = intval($_POST['product_id'] ?? 0);
$name        = trim($_POST['name']        ?? '');
$origin      = trim($_POST['origin']      ?? '');
$ingredients = trim($_POST['ingredients'] ?? '');

if ($pid <= 0 || $name === '') {
    echo json_encode(['success' => false, 'message' => '缺少必填欄位']);
    exit;
}

try {
    $pdo->prepare("
        UPDATE data SET name=?, brand=?, category=?, origin=?, purpose=?, ingredients=?, precautions=?
        WHERE id=?
    ")->execute([
        $name,
        trim($_POST['brand']        ?? ''),
        trim($_POST['category']     ?? ''),
        $origin,
        trim($_POST['purpose']      ?? ''),
        $ingredients,
        trim($_POST['precautions']  ?? ''),
        $pid,
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
