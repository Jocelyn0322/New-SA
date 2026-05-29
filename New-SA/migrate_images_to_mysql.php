<?php
/**
 * 一次性遷移：把 Supabase Storage 上的圖片抓回來存進 MySQL BLOB，
 * 並把 image_url / color_img / avatar_url 欄位改指向 image_file.php。
 * 只處理「網址含 supabase」的列；抓取失敗的列保留原 Supabase 網址不動（可重跑）。
 * 用瀏覽器開：  /image 路徑.../migrate_images_to_mysql.php
 */
require __DIR__ . '/db.php';
require __DIR__ . '/image_store.php';

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);
while (ob_get_level()) { ob_end_clean(); }

function fetchBytes(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype  = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [$status, $body, $ctype];
}

ensureImageTables($pdo);
$ok = 0; $fail = 0;

// 一個一個 type 處理：[type, 查詢, key 欄位, 更新語句]
$jobs = [
    ['product', "SELECT id AS k, image_url AS url FROM data WHERE image_url LIKE '%supabase%'",
        "UPDATE data SET image_url = ? WHERE id = ?"],
    ['color',   "SELECT color_id AS k, color_img AS url FROM product_colors WHERE color_img LIKE '%supabase%'",
        "UPDATE product_colors SET color_img = ? WHERE color_id = ?"],
    ['avatar',  "SELECT username AS k, avatar_url AS url FROM users WHERE avatar_url LIKE '%supabase%'",
        "UPDATE users SET avatar_url = ? WHERE username = ?"],
];

foreach ($jobs as [$type, $selectSql, $updateSql]) {
    $rows = $pdo->query($selectSql)->fetchAll();
    echo "=== {$type}：共 " . count($rows) . " 筆 ===\n";
    $upd = $pdo->prepare($updateSql);
    foreach ($rows as $r) {
        $key = $r['k'];
        [$status, $body, $ctype] = fetchBytes($r['url']);
        if ($status === 200 && $body !== false && strlen($body) > 0) {
            $mime = (strpos((string)$ctype, 'image/') === 0) ? $ctype : 'image/jpeg';
            if (storeImageBytes($pdo, $type, $key, $body, $mime)) {
                $upd->execute([imageUrl($type, $key), $key]);
                $ok++;
                echo "  OK   {$type} [{$key}]  " . strlen($body) . " bytes\n";
                continue;
            }
        }
        $fail++;
        echo "  FAIL {$type} [{$key}]  HTTP={$status}（保留原網址）\n";
    }
}

echo "\n===== 完成：成功 {$ok}，失敗 {$fail} =====\n";
echo "失敗的列仍指向原 Supabase 網址，可重新整理本頁重跑。\n";
