<?php
session_start();
require 'db.php';

// 檢查是否已登入且為管理員
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

// 建立 images 目錄
$imagesDir = __DIR__ . '/images';
if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0755, true);
}

$message = '';
$messageType = '';

// 上傳圖片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image']) && !isset($_POST['delete'])) {
    $file = $_FILES['image'];

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowed)) {
        $message = '只支援 JPG、JPEG、PNG、GIF、WEBP';
        $messageType = 'error';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $message = '檔案大小不能超過 5MB';
        $messageType = 'error';
    } elseif ($file['error'] === UPLOAD_ERR_OK) {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
        $filename = time() . '_' . $safeName;
        $relativePath = 'images/' . $filename;
        $fullPath = $imagesDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM carousel_images");
            $stmt->execute();
            $nextOrder = $stmt->fetch()['next_order'];

            $insert = $pdo->prepare("
                INSERT INTO carousel_images (filename, image_path, sort_order, is_active)
                VALUES (?, ?, ?, 1)
            ");
            $insert->execute([$filename, $relativePath, $nextOrder]);

            $message = '上傳成功！';
            $messageType = 'success';
        } else {
            $message = '圖片上傳失敗';
            $messageType = 'error';
        }
    } else {
        $message = '請重新選擇檔案';
        $messageType = 'error';
    }
}

// 刪除圖片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $id = (int)$_POST['delete'];

    $stmt = $pdo->prepare("SELECT * FROM carousel_images WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetch();

    if ($image) {
        $fullPath = __DIR__ . '/' . $image['image_path'];

        $deleteStmt = $pdo->prepare("DELETE FROM carousel_images WHERE id = ?");
        $deleteStmt->execute([$id]);

        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }

        $message = '刪除成功！';
        $messageType = 'success';
    } else {
        $message = '找不到該圖片資料';
        $messageType = 'error';
    }
}

// 強制刪除影片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['force_delete_video']) && isset($_POST['video_id'])) {
    $videoId = (int)$_POST['video_id'];
    $stmt = $pdo->prepare("SELECT file_path FROM videos WHERE id = ?");
    $stmt->execute([$videoId]);
    $video = $stmt->fetch();

    if ($video) {
        $fullPath = __DIR__ . '/' . $video['file_path'];
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }

        $deleteStmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
        $deleteStmt->execute([$videoId]);

        $message = '已強制刪除該影片';
        $messageType = 'success';
    } else {
        $message = '找不到該影片';
        $messageType = 'error';
    }
}

// 確保影片檢舉表存在
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS video_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        reported_by VARCHAR(100) NOT NULL,
        reason VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) DEFAULT 'pending',
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {
    // 這裡不影響管理頁面主要功能
}

// 讀取檢舉影片資料
$reportedVideos = [];
$reportedReasons = [];
try {
    $reportStmt = $pdo->query("SELECT v.id, v.title, v.uploaded_by, COUNT(r.id) AS report_count, MAX(r.created_at) AS last_reported_at
        FROM videos v
        JOIN video_reports r ON v.id = r.video_id
        WHERE v.is_active = 1
        GROUP BY v.id
        ORDER BY report_count DESC, last_reported_at DESC");
    $reportedVideos = $reportStmt->fetchAll();

    $reasonStmt = $pdo->query("SELECT video_id, reason, COUNT(*) AS count
        FROM video_reports
        GROUP BY video_id, reason
        ORDER BY video_id, count DESC");
    foreach ($reasonStmt->fetchAll() as $row) {
        $reportedReasons[$row['video_id']][] = $row;
    }
} catch (Exception $e) {
    // 無需處理，若沒有檢舉資料則保持空陣列
}

// 讀取所有圖片
$stmt = $pdo->query("SELECT * FROM carousel_images ORDER BY sort_order ASC, id ASC");
$images = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>輪播照片管理</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #efc6cd;
            padding-bottom: 20px;
        }
        .back-link {
            display: inline-block;
            padding: 8px 16px;
            background: #f0f0f0;
            text-decoration: none;
            border-radius: 5px;
            color: #111;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
        }
        .upload-section {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 2px dashed #ddd;
        }
        .upload-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group input[type="file"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group button {
            padding: 10px 20px;
            background: #efc6cd;
            color: #111;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
        .image-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .image-preview {
            width: 100%;
            height: 160px;
            object-fit: cover;
            display: block;
        }
        .image-info {
            padding: 12px;
        }
        .image-name {
            font-size: 12px;
            word-break: break-all;
            margin-bottom: 8px;
            color: #666;
        }
        .image-actions form {
            margin: 0;
        }
        .btn-delete {
            width: 100%;
            padding: 8px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .report-section {
            margin-top: 50px;
            background: #fff5f7;
            border: 1px solid #f5c6d0;
            border-radius: 16px;
            padding: 24px;
        }
        .report-section h2 {
            margin-bottom: 18px;
            color: #c82333;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .report-table th,
        .report-table td {
            padding: 12px 14px;
            border: 1px solid #f1d1dc;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }
        .report-table th {
            background: #ffe3eb;
            color: #9c2132;
        }
        .report-table tbody tr:nth-child(odd) {
            background: #fff7f9;
        }
        .report-reason-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .force-delete-btn {
            background: #c82333;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 14px;
            cursor: pointer;
        }
        .force-delete-btn:hover {
            background: #a71d2a;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <h1>輪播照片管理</h1>
            <a href="index.php" class="back-link">← 返回首頁</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($messageType); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="upload-section">
            <h2>上傳新照片</h2>
            <form class="upload-form" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="image">選擇圖片（最大 5MB）</label>
                    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.gif,.webp" required>
                </div>
                <div class="form-group" style="flex: 0 0 auto;">
                    <button type="submit">上傳</button>
                </div>
            </form>
        </div>

        <div class="images-section">
            <h2>現有照片（<?php echo count($images); ?> 張）</h2>

            <?php if (empty($images)): ?>
                <div class="empty-state">
                    還沒有輪播照片
                </div>
            <?php else: ?>
                <div class="images-grid">
                    <?php foreach ($images as $image): ?>
                        <div class="image-card">
                            <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="輪播照片" class="image-preview">
                            <div class="image-info">
                                <div class="image-name">ID: <?php echo $image['id']; ?></div>
                                <div class="image-name"><?php echo htmlspecialchars($image['filename']); ?></div>
                                <div class="image-name">排序：<?php echo $image['sort_order']; ?></div>
                                <div class="image-actions">
                                    <form method="post">
                                        <button type="submit"
                                                name="delete"
                                                value="<?php echo $image['id']; ?>"
                                                class="btn-delete"
                                                onclick="return confirm('確定要刪除此照片嗎？')">
                                            刪除
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="report-section">
            <h2>🎯 影片檢舉管理</h2>

            <?php if (empty($reportedVideos)): ?>
                <div class="empty-state">
                    目前沒有影片被檢舉。
                </div>
            <?php else: ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>影片 ID</th>
                            <th>標題</th>
                            <th>上傳者</th>
                            <th>檢舉次數</th>
                            <th>檢舉原因統計</th>
                            <th>最後檢舉時間</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportedVideos as $video): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($video['id']); ?></td>
                                <td><?php echo htmlspecialchars($video['title']); ?></td>
                                <td><?php echo htmlspecialchars($video['uploaded_by']); ?></td>
                                <td><?php echo htmlspecialchars($video['report_count']); ?></td>
                                <td>
                                    <div class="report-reason-list">
                                        <?php if (!empty($reportedReasons[$video['id']])): ?>
                                            <?php foreach ($reportedReasons[$video['id']] as $reason): ?>
                                                <div><?php echo htmlspecialchars($reason['reason']); ?>：<?php echo htmlspecialchars($reason['count']); ?> 次</div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div>尚無詳細原因</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($video['last_reported_at']))); ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('確定要強制刪除此影片嗎？此操作會移除影片與所有檢舉記錄。');">
                                        <input type="hidden" name="video_id" value="<?php echo htmlspecialchars($video['id']); ?>">
                                        <button type="submit" name="force_delete_video" value="1" class="force-delete-btn">強制刪除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>