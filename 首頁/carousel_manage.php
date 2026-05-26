<?php
session_start();
require __DIR__ . '/../db.php';

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

$imagesDir = __DIR__ . '/images';
if (!is_dir($imagesDir)) mkdir($imagesDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_FILES['image']) && !isset($_POST['delete_id'])) {
        $file = $_FILES['image'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $_SESSION['carousel_msg'] = ['type' => 'error', 'text' => '格式不支援，請上傳 JPG/PNG/GIF/WEBP'];
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $_SESSION['carousel_msg'] = ['type' => 'error', 'text' => '圖片不能超過 5MB'];
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['carousel_msg'] = ['type' => 'error', 'text' => '上傳失敗，請重試'];
        } else {
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
            $relPath  = 'images/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $imagesDir . '/' . $filename)) {
                $stmt = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM carousel_images");
                $next = $stmt->fetch()['n'];
                $pdo->prepare("INSERT INTO carousel_images (filename, image_path, sort_order, is_active) VALUES (?, ?, ?, 1)")
                    ->execute([$filename, $relPath, $next]);
                $_SESSION['carousel_msg'] = ['type' => 'success', 'text' => '上傳成功！'];
            } else {
                $_SESSION['carousel_msg'] = ['type' => 'error', 'text' => '檔案移動失敗，請確認資料夾權限'];
            }
        }
    }

    if (isset($_POST['delete_id'])) {
        $id   = (int)$_POST['delete_id'];
        $stmt = $pdo->prepare("SELECT image_path FROM carousel_images WHERE id = ?");
        $stmt->execute([$id]);
        $img  = $stmt->fetch();
        if ($img) {
            $fullPath = __DIR__ . '/' . $img['image_path'];
            if (file_exists($fullPath) && is_file($fullPath)) unlink($fullPath);
            $pdo->prepare("DELETE FROM carousel_images WHERE id = ?")->execute([$id]);
            $_SESSION['carousel_msg'] = ['type' => 'success', 'text' => '已刪除圖片'];
        } else {
            $_SESSION['carousel_msg'] = ['type' => 'error', 'text' => '找不到該圖片'];
        }
    }
}

header("Location: index.php");
exit();
