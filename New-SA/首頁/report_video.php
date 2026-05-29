<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../db.php';

// 檢查是否已登入
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

// 管理員不能檢舉（應使用管理後台直接處理）
if (($_SESSION['role'] ?? '') === 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '管理員請使用檢舉管理頁面處理影片']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只支援 POST 請求']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['video_id']) || !isset($data['reason']) || !isset($data['description'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '缺少必要參數']);
    exit;
}

$videoId = (int)$data['video_id'];
$reason = trim($data['reason']);
$description = trim($data['description']);

if ($videoId <= 0 || $reason === '' || mb_strlen($description) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '請提供有效的檢舉資訊，說明至少 10 字']);
    exit;
}

try {
    $checkVideo = $pdo->prepare("SELECT id FROM videos WHERE id = ?");
    $checkVideo->execute([$videoId]);
    if (!$checkVideo->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '找不到該影片']);
        exit;
    }
    // 檢查是否重複檢舉
    $stmt = $pdo->prepare("
        SELECT id FROM video_reports
        WHERE video_id = ? AND reported_by = ? AND created_at > NOW() - INTERVAL 24 HOUR
    ");
    $stmt->execute([$videoId, $_SESSION['user']]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '您已在24小時內檢舉過此影片']);
        exit;
    }

    // 插入檢舉記錄
    $stmt = $pdo->prepare("
        INSERT INTO video_reports (video_id, reported_by, reason, description)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $videoId,
        $_SESSION['user'],
        $reason,
        $description
    ]);

    echo json_encode([
        'success' => true,
        'message' => '感謝您的檢舉，我們會盡快查看'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '檢舉失敗：' . $e->getMessage()
    ]);
}
?>
