<?php
session_start();
require __DIR__ . '/../db.php';
require __DIR__ . '/../image_store.php';
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

$bytes = file_get_contents($file['tmp_name']);
if ($bytes === false) {
    echo json_encode(['success' => false, 'message' => '讀取檔案失敗']);
    exit;
}

// 存進 MySQL（avatar_images），不再上傳 Supabase
if (!storeImageBytes($pdo, 'avatar', $username, $bytes, $mimeType)) {
    echo json_encode(['success' => false, 'message' => '圖片寫入資料庫失敗']);
    exit;
}

$publicUrl = imageUrl('avatar', $username);

$pdo->prepare("UPDATE user_profiles SET avatar_url = ? WHERE username = ?")
    ->execute([$publicUrl, $username]);

echo json_encode(['success' => true, 'url' => $publicUrl]);
