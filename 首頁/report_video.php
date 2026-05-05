<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require 'db.php';

// 檢查是否已登入
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '請先登入']);
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

try {
    // 建立檢舉表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS video_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            video_id INT NOT NULL,
            reported_by VARCHAR(100) NOT NULL,
            reason VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'pending',
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
        )
    ");

    // 檢查是否重複檢舉
    $stmt = $pdo->prepare("
        SELECT id FROM video_reports 
        WHERE video_id = ? AND reported_by = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$data['video_id'], $_SESSION['user']]);
    
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
        $data['video_id'],
        $_SESSION['user'],
        $data['reason'],
        $data['description']
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
