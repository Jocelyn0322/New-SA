<?php
/**
 * 上傳產品照片到 MySQL（product_images BLOB）
 * POST 參數：product_id（數字）、image（檔案）
 * 回傳 JSON：{ success, url, message }
 */
session_start();
require __DIR__ . '/../db.php';
require __DIR__ . '/../image_store.php';

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

$bytes = file_get_contents($file['tmp_name']);
if ($bytes === false) {
    echo json_encode(['success' => false, 'message' => '讀取檔案失敗']);
    exit;
}

// 存進 MySQL（product_images），不再上傳 Supabase
if (!storeImageBytes($pdo, 'product', $productId, $bytes, $mimeType)) {
    echo json_encode(['success' => false, 'message' => '圖片寫入資料庫失敗']);
    exit;
}

$publicUrl = imageUrl('product', $productId);

$pdo->prepare("UPDATE data SET image_url = ? WHERE id = ?")
    ->execute([$publicUrl, $productId]);

echo json_encode(['success' => true, 'url' => $publicUrl, 'message' => '照片已上傳']);
