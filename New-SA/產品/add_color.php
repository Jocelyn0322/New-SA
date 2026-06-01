<?php
session_start();
require __DIR__ . '/../db.php';
require __DIR__ . '/../image_store.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => '權限不足']);
    exit;
}

$pId       = intval($_POST['p_id'] ?? 0);
$colorName = trim($_POST['color_name'] ?? '');
$colorHex  = trim($_POST['color_hex'] ?? '');

if ($pId <= 0 || $colorName === '') {
    echo json_encode(['success' => false, 'message' => '缺少必填欄位']);
    exit;
}

if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colorHex)) {
    echo json_encode(['success' => false, 'message' => '顏色格式不正確']);
    exit;
}

$stmt = $pdo->prepare(
    "INSERT INTO product_colors (p_id, color_name, color_hex, color_img) VALUES (?, ?, ?, '')"
);
$stmt->execute([$pId, $colorName, $colorHex]);
$newId = $pdo->lastInsertId();

// 若有附上照片，一併存圖並寫回 color_img
$imgUrl = '';
if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file     = $_FILES['image'];
    $mimeType = mime_content_type($file['tmp_name']);
    $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
    if (in_array($mimeType, $allowed)) {
        $bytes = file_get_contents($file['tmp_name']);
        if ($bytes !== false && storeImageBytes($pdo, 'color', $newId, $bytes, $mimeType)) {
            $imgUrl = imageUrl('color', $newId);
            $pdo->prepare("UPDATE product_colors SET color_img = ? WHERE color_id = ?")
                ->execute([$imgUrl, $newId]);
        }
    }
}

echo json_encode(['success' => true, 'color_id' => $newId, 'url' => $imgUrl]);
