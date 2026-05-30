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
    $pdo->beginTransaction();

    // 更新 data 基本欄位
    $pdo->prepare("
        UPDATE data SET name=?, brand=?, category=?, purpose=?, precautions=?
        WHERE id=?
    ")->execute([
        $name,
        trim($_POST['brand']       ?? ''),
        trim($_POST['category']    ?? ''),
        trim($_POST['purpose']     ?? ''),
        trim($_POST['precautions'] ?? ''),
        $pid,
    ]);

    // 更新產地
    if ($origin !== '') {
        $pdo->prepare("INSERT IGNORE INTO product_origins (name) VALUES (?)")->execute([$origin]);
        $originId = $pdo->prepare("SELECT id FROM product_origins WHERE name = ?");
        $originId->execute([$origin]);
        $pdo->prepare("UPDATE data SET origin_id = ? WHERE id = ?")->execute([$originId->fetchColumn(), $pid]);
    } else {
        $pdo->prepare("UPDATE data SET origin_id = NULL WHERE id = ?")->execute([$pid]);
    }

    // 更新成分（先刪舊關聯，再重新建立）
    $pdo->prepare("DELETE FROM product_ingredients WHERE product_id = ?")->execute([$pid]);
    if ($ingredients !== '') {
        $parts = preg_split('/[、,，]+/u', $ingredients);
        $insIng = $pdo->prepare("INSERT IGNORE INTO ingredients (name) VALUES (?)");
        $getIng = $pdo->prepare("SELECT id FROM ingredients WHERE name = ?");
        $insJun = $pdo->prepare("INSERT IGNORE INTO product_ingredients (product_id, ingredient_id) VALUES (?, ?)");
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            $insIng->execute([$part]);
            $getIng->execute([$part]);
            $ingId = $getIng->fetchColumn();
            if ($ingId) $insJun->execute([$pid, $ingId]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
