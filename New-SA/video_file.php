<?php
/**
 * 影片串流：從資料庫讀出 BLOB 輸出，支援 HTTP Range（拖曳/縮圖 seek 需要）
 * 用法：video_file.php?id=123
 */
require __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('bad id'); }

$stmt = $pdo->prepare("SELECT mime, data FROM video_files WHERE video_id = ?");
$stmt->execute([$id]);
$stmt->bindColumn('mime', $mime, PDO::PARAM_STR);
$stmt->bindColumn('data', $data, PDO::PARAM_LOB);
if (!$stmt->fetch(PDO::FETCH_BOUND)) {
    http_response_code(404); exit('not found');
}

// PDO LOB 在 MySQL 通常回傳字串
$binary = is_resource($data) ? stream_get_contents($data) : (string)$data;
$mime   = $mime ?: 'video/mp4';
$size   = strlen($binary);

// 清掉任何緩衝，避免污染二進位輸出
while (ob_get_level()) { ob_end_clean(); }

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=86400');

// 處理 Range 請求（影片拖曳、seek）
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    $start = $m[1] === '' ? 0 : (int)$m[1];
    $end   = $m[2] === '' ? $size - 1 : (int)$m[2];
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    $end    = min($end, $size - 1);
    $length = $end - $start + 1;
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
    header('Content-Length: ' . $length);
    echo substr($binary, $start, $length);
} else {
    header('Content-Length: ' . $size);
    echo $binary;
}
exit;
