<?php
/**
 * 圖片 BLOB 儲存工具：把產品圖 / 色號圖 / 使用者頭像存進 MySQL，
 * 取代原本的 Supabase Storage。搭配 image_file.php 串流輸出。
 *
 * 三種類型（type）：
 *   product → product_images(product_id) ← data.image_url
 *   color   → color_images(color_id)     ← product_colors.color_img
 *   avatar  → avatar_images(username)     ← users.avatar_url
 */

// 確保三張圖片 BLOB 資料表存在
function ensureImageTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
        product_id INT NOT NULL PRIMARY KEY,
        mime VARCHAR(100) NOT NULL DEFAULT 'image/jpeg',
        data LONGBLOB NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS color_images (
        color_id INT NOT NULL PRIMARY KEY,
        mime VARCHAR(100) NOT NULL DEFAULT 'image/jpeg',
        data LONGBLOB NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS avatar_images (
        username VARCHAR(100) NOT NULL PRIMARY KEY,
        mime VARCHAR(100) NOT NULL DEFAULT 'image/jpeg',
        data LONGBLOB NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// 把圖片二進位存進對應資料表（同 key 會覆蓋）
function storeImageBytes(PDO $pdo, string $type, $key, string $bytes, string $mime): bool {
    ensureImageTables($pdo);
    switch ($type) {
        case 'product':
            $sql = "INSERT INTO product_images (product_id, mime, data) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE mime = VALUES(mime), data = VALUES(data)";
            $isStrKey = false; break;
        case 'color':
            $sql = "INSERT INTO color_images (color_id, mime, data) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE mime = VALUES(mime), data = VALUES(data)";
            $isStrKey = false; break;
        case 'avatar':
            $sql = "INSERT INTO avatar_images (username, mime, data) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE mime = VALUES(mime), data = VALUES(data)";
            $isStrKey = true; break;
        default:
            return false;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $isStrKey ? (string)$key : (int)$key, $isStrKey ? PDO::PARAM_STR : PDO::PARAM_INT);
    $stmt->bindValue(2, $mime, PDO::PARAM_STR);
    $stmt->bindValue(3, $bytes, PDO::PARAM_LOB);
    return $stmt->execute();
}

// 組出指向 image_file.php 的串流網址（存進 image_url / color_img / avatar_url 欄位）
function imageUrl(string $type, $key): string {
    $base = defined('BASE_URL') ? BASE_URL : '';
    $v    = '&v=' . substr(md5((string)$key . microtime()), 0, 8); // 破快取
    if ($type === 'avatar') {
        return $base . '/image_file.php?type=avatar&user=' . rawurlencode((string)$key) . $v;
    }
    return $base . '/image_file.php?type=' . $type . '&id=' . (int)$key . $v;
}
