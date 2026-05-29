<?php
/**
 * 圖片串流：從 MySQL 讀出 BLOB 輸出（取代 Supabase 公開網址）
 * 用法：
 *   image_file.php?type=product&id=17
 *   image_file.php?type=color&id=5
 *   image_file.php?type=avatar&user=uchieh
 */
require __DIR__ . '/db.php';
require __DIR__ . '/image_store.php';

ensureImageTables($pdo);

$type = $_GET['type'] ?? '';
$row  = null;

if ($type === 'product' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT mime, data FROM product_images WHERE product_id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $row = $stmt->fetch();
} elseif ($type === 'color' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT mime, data FROM color_images WHERE color_id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $row = $stmt->fetch();
} elseif ($type === 'avatar' && isset($_GET['user'])) {
    $stmt = $pdo->prepare("SELECT mime, data FROM avatar_images WHERE username = ?");
    $stmt->execute([(string)$_GET['user']]);
    $row = $stmt->fetch();
} else {
    http_response_code(400); exit('bad request');
}

if (!$row) { http_response_code(404); exit('not found'); }

$data = is_resource($row['data']) ? stream_get_contents($row['data']) : (string)$row['data'];
$mime = $row['mime'] ?: 'image/jpeg';

while (ob_get_level()) { ob_end_clean(); }  // 清緩衝，避免污染二進位

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($data));
header('Cache-Control: public, max-age=86400');
echo $data;
exit;
