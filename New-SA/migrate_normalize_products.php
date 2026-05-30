<?php
/**
 * 正規化 data 表的 origin 和 ingredients 欄位
 * 執行一次後可刪除此檔案
 */
require __DIR__ . '/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<pre>";

try {
    $pdo->beginTransaction();

    // ── 1. 建立 product_origins 產地 reference table ──────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_origins (
            id   INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(30) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ 建立 product_origins 表\n";

    // ── 2. 將現有 origin 匯入 product_origins ────────────────────
    $pdo->exec("
        INSERT IGNORE INTO product_origins (name)
        SELECT DISTINCT origin FROM data
        WHERE origin IS NOT NULL AND origin != ''
    ");
    echo "✓ 匯入產地資料\n";

    // ── 3. data 表加 origin_id 欄位 ──────────────────────────────
    $cols = $pdo->query("SHOW COLUMNS FROM data LIKE 'origin_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE data ADD COLUMN origin_id INT NULL AFTER origin");
        echo "✓ 新增 data.origin_id 欄位\n";
    } else {
        echo "- data.origin_id 已存在，跳過\n";
    }

    // ── 4. 填入 origin_id ─────────────────────────────────────────
    $pdo->exec("
        UPDATE data d
        JOIN product_origins po ON po.name = d.origin
        SET d.origin_id = po.id
    ");
    echo "✓ 更新 data.origin_id\n";

    // ── 5. 加 FK ──────────────────────────────────────────────────
    try {
        $pdo->exec("
            ALTER TABLE data
            ADD CONSTRAINT fk_data_origin
            FOREIGN KEY (origin_id) REFERENCES product_origins(id)
        ");
        echo "✓ 加入 origin_id 外鍵\n";
    } catch (Exception $e) {
        echo "- 外鍵已存在或略過：{$e->getMessage()}\n";
    }

    // ── 6. 建立 ingredients 成分 reference table ──────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ingredients (
            id   INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ 建立 ingredients 表\n";

    // ── 7. 建立 product_ingredients junction table ────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_ingredients (
            product_id    INT NOT NULL,
            ingredient_id INT NOT NULL,
            PRIMARY KEY (product_id, ingredient_id),
            FOREIGN KEY (product_id)    REFERENCES data(id)        ON DELETE CASCADE,
            FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ 建立 product_ingredients 表\n";

    // ── 8. 解析現有 ingredients 文字並匯入 ───────────────────────
    $rows = $pdo->query("SELECT id, ingredients FROM data WHERE ingredients IS NOT NULL AND ingredients != ''")->fetchAll(PDO::FETCH_ASSOC);

    $insertIngredient = $pdo->prepare("INSERT IGNORE INTO ingredients (name) VALUES (?)");
    $getIngredientId  = $pdo->prepare("SELECT id FROM ingredients WHERE name = ?");
    $insertJunction   = $pdo->prepare("INSERT IGNORE INTO product_ingredients (product_id, ingredient_id) VALUES (?, ?)");

    $productCount = 0;
    foreach ($rows as $row) {
        // 支援「、」和「,」兩種分隔符
        $parts = preg_split('/[、,，]+/u', $row['ingredients']);
        foreach ($parts as $part) {
            $name = trim($part);
            if ($name === '') continue;
            $insertIngredient->execute([$name]);
            $getIngredientId->execute([$name]);
            $ingId = $getIngredientId->fetchColumn();
            if ($ingId) {
                $insertJunction->execute([$row['id'], $ingId]);
            }
        }
        $productCount++;
    }
    echo "✓ 匯入成分資料（{$productCount} 個產品）\n";

    $pdo->commit();
    echo "\n✅ 正規化完成！\n";
    echo "\n接下來可手動執行（確認資料無誤後）：\n";
    echo "  ALTER TABLE data DROP COLUMN origin;\n";
    echo "  ALTER TABLE data DROP COLUMN ingredients;\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ 失敗：" . $e->getMessage() . "\n";
}

echo "</pre>";
