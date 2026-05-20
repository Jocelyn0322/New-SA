<?php
/**
 * 上傳色號照片到 Supabase Storage
 * POST: color_id（數字）、image（檔案）
 * 回傳 JSON: { success, url, message }
 */
session_start();
require 'db.php';

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

$ext      = match($mimeType) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
$filename = 'colors/' . $colorId . '.' . $ext;

// 上傳到 Supabase Storage
$ch = curl_init(SUPABASE_URL . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $filename);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'apikey: '               . SUPABASE_SERVICE_KEY,
        'Content-Type: '         . $mimeType,
        'x-upsert: true',
    ],
    CURLOPT_POSTFIELDS => file_get_contents($file['tmp_name']),
]);
$resp   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200) {
    echo json_encode(['success' => false, 'message' => '上傳失敗：' . $resp]);
    exit;
}

$publicUrl = SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/' . $filename;

$pdo->prepare("UPDATE product_colors SET color_img = ? WHERE color_id = ?")
    ->execute([$publicUrl, $colorId]);

echo json_encode(['success' => true, 'url' => $publicUrl]);
