<?php
/**
 * 上傳產品照片到 Supabase Storage
 * POST 參數：product_id（數字）、image（檔案）
 * 回傳 JSON：{ success, url, message }
 */
session_start();
require __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

$productId = intval($_POST['product_id'] ?? 0);
if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => '缺少產品編號']);
    exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '請選擇照片']);
    exit;
}

$file     = $_FILES['image'];
$mimeType = mime_content_type($file['tmp_name']);
$allowed  = ['image/jpeg', 'image/png', 'image/webp'];

if (!in_array($mimeType, $allowed)) {
    echo json_encode(['success' => false, 'message' => '只接受 JPG / PNG / WebP']);
    exit;
}

// 副檔名
$ext      = match($mimeType) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
$filename = $productId . '.' . $ext;

// 上傳到 Supabase Storage
$uploadUrl = SUPABASE_URL . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $filename;

$ch = curl_init($uploadUrl);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'apikey: '               . SUPABASE_SERVICE_KEY,
        'Content-Type: '         . $mimeType,
        'x-upsert: true',        // 覆蓋同名檔案
    ],
    CURLOPT_POSTFIELDS     => file_get_contents($file['tmp_name']),
]);
$response   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpStatus !== 200) {
    echo json_encode(['success' => false, 'message' => '上傳失敗：' . $response]);
    exit;
}

// 組成公開 URL
$publicUrl = SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/' . $filename;

// 更新資料庫
$pdo->prepare("UPDATE data SET image_url = ? WHERE id = ?")
    ->execute([$publicUrl, $productId]);

echo json_encode(['success' => true, 'url' => $publicUrl, 'message' => '照片已上傳']);
