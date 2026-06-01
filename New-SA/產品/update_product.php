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

    // 1) data 表本來就有的欄位
    $pdo->prepare("UPDATE data SET name=?, brand=?, category=?, purpose=?, precautions=? WHERE id=?")
        ->execute([
            $name,
            trim($_POST['brand']       ?? ''),
            trim($_POST['category']    ?? ''),
            trim($_POST['purpose']     ?? ''),
            trim($_POST['precautions'] ?? ''),
            $pid,
        ]);

    // 2) 產地 → product_origins（找不到就新增），寫回 data.origin_id
    if ($origin !== '') {
        $pdo->prepare("INSERT IGNORE INTO product_origins (name) VALUES (?)")->execute([$origin]);
        $os = $pdo->prepare("SELECT id FROM product_origins WHERE name = ?");
        $os->execute([$origin]);
        $originId = $os->fetchColumn();
        $pdo->prepare("UPDATE data SET origin_id = ? WHERE id = ?")->execute([$originId, $pid]);
    } else {
        $pdo->prepare("UPDATE data SET origin_id = NULL WHERE id = ?")->execute([$pid]);
    }

    // 3) 成分 → ingredients + product_ingredients（先清空該產品的，再重建）
    $pdo->prepare("DELETE FROM product_ingredients WHERE product_id = ?")->execute([$pid]);
    if ($ingredients !== '') {
        // 以「、 , ; ，」分隔（不切 / 與空白，避免拆壞成分名稱）
        $parts  = preg_split('/[、,;，]+/u', $ingredients, -1, PREG_SPLIT_NO_EMPTY);
        $seen   = [];
        $insIng = $pdo->prepare("INSERT IGNORE INTO ingredients (name) VALUES (?)");
        $selIng = $pdo->prepare("SELECT id FROM ingredients WHERE name = ?");
        $insPi  = $pdo->prepare("INSERT IGNORE INTO product_ingredients (product_id, ingredient_id) VALUES (?, ?)");
        foreach ($parts as $ing) {
            $ing = trim($ing);
            if ($ing === '' || isset($seen[$ing])) continue;
            $seen[$ing] = true;
            $insIng->execute([$ing]);
            $selIng->execute([$ing]);
            $iid = $selIng->fetchColumn();
            if ($iid) $insPi->execute([$pid, $iid]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
