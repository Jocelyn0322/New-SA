<?php
/**
 * 上傳色號照片到 MySQL（color_images BLOB）
 * POST: color_id（數字）、image（檔案）
 * 回傳 JSON: { success, url, message }
 */
session_start();
require __DIR__ . '/../db.php';
require __DIR__ . '/../image_store.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => '權限不足']);
    exit;
}

$colorId = intval($_POST['color_id'] ?? 0);
if ($colorId <= 0) {
    echo json_encode(['success' => false, 'message' => '缺少色號編號']);
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

// 存進 MySQL（color_images），不再上傳 Supabase
if (!storeImageBytes($pdo, 'color', $colorId, $bytes, $mimeType)) {
    echo json_encode(['success' => false, 'message' => '圖片寫入資料庫失敗']);
    exit;
}

$publicUrl = imageUrl('color', $colorId);

$pdo->prepare("UPDATE product_colors SET color_img = ? WHERE color_id = ?")
    ->execute([$publicUrl, $colorId]);

echo json_encode(['success' => true, 'url' => $publicUrl]);
