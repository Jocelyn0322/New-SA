<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 檢查是否為管理員
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '沒有權限']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只支援 POST 請求']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '缺少 id 參數']);
    exit;
}

require __DIR__ . '/../db.php';

try {
    // 取得圖片路徑
    $stmt = $pdo->prepare("SELECT image_path FROM carousel_images WHERE id = ?");
    $stmt->execute([$data['id']]);
    $image = $stmt->fetch();

    if (!$image) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '圖片不存在']);
        exit;
    }

    // 刪除圖片文件
    $imagePath = '../' . $image['image_path'];
    if (file_exists($imagePath)) {
        unlink($imagePath);
    }

    // 刪除資料庫記錄
    $stmt = $pdo->prepare("DELETE FROM carousel_images WHERE id = ?");
    $stmt->execute([$data['id']]);

    echo json_encode(['success' => true, 'message' => '刪除成功']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '刪除失敗：' . $e->getMessage()]);
}
?>
