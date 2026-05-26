<?php
session_start();
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

$username = $_SESSION['user'];
$file = $_FILES['avatar'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '請選擇圖片']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => '圖片大小不能超過 5MB']);
    exit;
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$extMap   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

if (!isset($extMap[$mimeType])) {
    echo json_encode(['success' => false, 'message' => '只支援 JPG、PNG、GIF、WebP 格式']);
    exit;
}

$ext      = $extMap[$mimeType];
$filename = 'avatars/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username) . '.' . $ext;
$tmpPath  = $file['tmp_name'];
$fileSize = $file['size'];

$fp = fopen($tmpPath, 'rb');
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => SUPABASE_URL . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $filename,
    CURLOPT_PUT            => true,
    CURLOPT_INFILE         => $fp,
    CURLOPT_INFILESIZE     => $fileSize,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: ' . $mimeType,
        'x-upsert: true',
    ],
]);
$resp   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
fclose($fp);

if ($status !== 200 && $status !== 201) {
    echo json_encode(['success' => false, 'message' => '上傳失敗，請稍後再試']);
    exit;
}

$publicUrl = SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/' . $filename . '?v=' . time();

$pdo->exec("ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS avatar_url TEXT");
$pdo->prepare("UPDATE user_profiles SET avatar_url = ? WHERE username = ?")
    ->execute([$publicUrl, $username]);

echo json_encode(['success' => true, 'url' => $publicUrl]);
