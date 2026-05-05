
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'db.php';

// 檢查是否已登入
$isLoggedIn = isset($_SESSION['user']);

// 建立 videos 目錄
$videosDir = __DIR__ . '/videos';
if (!is_dir($videosDir)) {
    if (!mkdir($videosDir, 0755, true)) {
        die('無法創建 videos 目錄，請檢查權限設定');
    }
}

// 檢查並建立 videos 表
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            uploaded_by VARCHAR(100) NOT NULL,
            upload_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            likes INT DEFAULT 0,
            thumbnail VARCHAR(500)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL,
            video_id INT NOT NULL,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
        )
    ");
} catch (PDOException $e) {
    die('資料庫錯誤：' . $e->getMessage());
}

$message = '';
$messageType = '';

// 處理影片上傳
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video']) && !isset($_POST['delete'])) {
    $currentView = $_GET['view'] ?? 'home';
    if ($currentView !== 'personal') {
        $message = '請先切換到個人頁面才能上傳影片';
        $messageType = 'error';
    } else {
        $file = $_FILES['video'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($title)) {
            $message = '請輸入影片標題';
            $messageType = 'error';
        } else {
            $allowed = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowed)) {
                $message = '只支援 MP4、AVI、MOV、WMV、FLV、WEBM、MKV 格式';
                $messageType = 'error';
            } elseif ($file['size'] > 40 * 1024 * 1024) {
                $message = '檔案大小不能超過 40MB';
                $messageType = 'error';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                // 詳細的檔案上傳錯誤訊息
                switch ($file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $message = '檔案大小超過伺服器限制 (php.ini upload_max_filesize)';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $message = '檔案大小超過表單限制 (MAX_FILE_SIZE)';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $message = '檔案只有部分被上傳，請重新上傳';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $message = '沒有選擇要上傳的檔案';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $message = '伺服器臨時文件夾不存在';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $message = '檔案寫入失敗，可能是權限問題';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $message = '檔案上傳被伺服器擴展程序阻止';
                        break;
                    default:
                        $message = '檔案上傳錯誤 (錯誤代碼: ' . $file['error'] . ')';
                        break;
                }
                $messageType = 'error';
            } else {
                $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
                $filename = time() . '_' . $safeName;
                $relativePath = 'videos/' . $filename;
                $fullPath = $videosDir . '/' . $filename;

                if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO videos (title, description, filename, file_path, uploaded_by)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$title, $description, $filename, $relativePath, $_SESSION['user']]);

                        $message = '影片上傳成功！';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        // 如果資料庫插入失敗，刪除已上傳的檔案
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                        $message = '資料庫儲存失敗：' . $e->getMessage();
                        $messageType = 'error';
                    }
                } else {
                    // 檢查可能的 move_uploaded_file 失敗原因
                    $errorDetails = [];
                    if (!is_writable($videosDir)) {
                        $errorDetails[] = 'videos 目錄沒有寫入權限';
                    }
                    if (disk_free_space($videosDir) < $file['size']) {
                        $errorDetails[] = '磁盤空間不足';
                    }
                    if (!is_uploaded_file($file['tmp_name'])) {
                        $errorDetails[] = '臨時檔案不存在或不是有效的上傳檔案';
                    }

                    $message = '檔案移動失敗';
                    if (!empty($errorDetails)) {
                        $message .= '：' . implode('、', $errorDetails);
                    }
                    $messageType = 'error';
                }
            }
        }
    }
}

// 處理影片刪除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['video_id'])) {
    $videoId = (int)$_POST['video_id'];

    // 檢查權限
    $stmt = $pdo->prepare("SELECT uploaded_by FROM videos WHERE id = ?");
    $stmt->execute([$videoId]);
    $video = $stmt->fetch();

    if ($video) {
        $canDelete = ($_SESSION['role'] ?? '') === 'admin' || $video['uploaded_by'] === $_SESSION['user'];

        if ($canDelete) {
            // 刪除檔案
            $stmt = $pdo->prepare("SELECT file_path FROM videos WHERE id = ?");
            $stmt->execute([$videoId]);
            $filePath = $stmt->fetch()['file_path'];
            $fullPath = __DIR__ . '/' . $filePath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            // 刪除資料庫記錄
            $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
            $stmt->execute([$videoId]);

            $message = '影片刪除成功！';
            $messageType = 'success';
        } else {
            $message = '您沒有權限刪除此影片';
            $messageType = 'error';
        }
    } else {
        $message = '影片不存在';
        $messageType = 'error';
    }
}

// 處理按讚/取消按讚
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_like']) && isset($_POST['video_id'])) {
    $videoId = (int)$_POST['video_id'];
    $userId = $_SESSION['user'];

    // 檢查是否已經按讚
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND video_id = ?");
    $stmt->execute([$userId, $videoId]);
    $existingLike = $stmt->fetch();

    if ($existingLike) {
        // 取消按讚
        $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
        $stmt->execute([$existingLike['id']]);
        $stmt = $pdo->prepare("UPDATE videos SET likes = likes - 1 WHERE id = ?");
        $stmt->execute([$videoId]);
    } else {
        // 按讚
        $stmt = $pdo->prepare("INSERT INTO likes (user_id, video_id) VALUES (?, ?)");
        $stmt->execute([$userId, $videoId]);
        $stmt = $pdo->prepare("UPDATE videos SET likes = likes + 1 WHERE id = ?");
        $stmt->execute([$videoId]);
    }

    // 重新導向避免重複提交
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// 獲取影片列表
$view = $_GET['view'] ?? 'home';
if ($view === 'personal') {
    // 個人頁面：獲取用戶上傳的影片和按讚的影片
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.description, v.file_path, v.uploaded_by, v.upload_time, v.likes,
               CASE WHEN l.user_id IS NOT NULL THEN 1 ELSE 0 END as is_liked
        FROM videos v
        LEFT JOIN likes l ON v.id = l.video_id AND l.user_id = ?
        WHERE v.is_active = 1 AND (v.uploaded_by = ? OR l.user_id = ?)
        ORDER BY v.upload_time DESC
    ");
    $stmt->execute([$_SESSION['user'], $_SESSION['user'], $_SESSION['user']]);
    $videos = $stmt->fetchAll();
} else {
    // 主頁：顯示所有影片
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.description, v.file_path, v.uploaded_by, v.upload_time, v.likes,
               CASE WHEN l.user_id IS NOT NULL THEN 1 ELSE 0 END as is_liked
        FROM videos v
        LEFT JOIN likes l ON v.id = l.video_id AND l.user_id = ?
        WHERE v.is_active = 1
        ORDER BY v.upload_time DESC
    ");
    $stmt->execute([$_SESSION['user']]);
    $videos = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>影片交流</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .video-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 40px 20px;
        }

        .video-wrapper {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }

        .nav-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 20px;
        }

        .nav-tab {
            padding: 10px 20px;
            background: #f0f0f0;
            color: #666;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .nav-tab.active {
            background: linear-gradient(135deg, #ff5a7e 0%, #ff3a6f 100%);
            color: white;
        }

        .nav-tab:hover {
            background: #e0e0e0;
            color: #333;
        }

        .nav-tab.active:hover {
            background: linear-gradient(135deg, #ff3a6f 0%, #ff1a5e 100%);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .upload-section {
            grid-column: 1 / -1;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }

        .upload-section h2 {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff5a7e;
        }

        .form-group textarea {
            height: 80px;
            resize: vertical;
        }

        .form-group input[type="file"] {
            padding: 10px;
        }

        .upload-btn {
            background: linear-gradient(135deg, #ff5a7e 0%, #ff3a6f 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 90, 126, 0.3);
        }

        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .video-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .video-card {
            background: black;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .video-player {
            width: 100%;
            height: 240px;
            background: #000;
            position: relative;
            overflow: hidden;
        }

        .video-player video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .play-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            background: rgba(255, 90, 126, 0.8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .play-icon::after {
            content: '▶';
            color: white;
            font-size: 20px;
            margin-left: 3px;
        }

        .video-card:hover .play-icon {
            opacity: 1;
        }

        .video-like-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.6);
            border: none;
            border-radius: 20px;
            padding: 6px 12px;
            cursor: pointer;
            color: white;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
            z-index: 10;
        }

        .video-like-btn:hover {
            background: rgba(255, 90, 126, 0.8);
        }

        .video-like-btn.liked {
            color: #ff5a7e;
        }

        /* 自動播放第一個影片 */
        .video-card:first-child video {
            autoplay: true;
        }

        /* 影片詳情浮層 */
        .video-detail-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 1000;
            overflow-y: auto;
        }

        .video-detail-overlay.active {
            display: block;
        }

        .video-detail-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .video-detail-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1001;
        }

        .video-detail-player {
            width: 100%;
            max-height: 70vh;
            background: #000;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .video-detail-player video {
            max-width: 100%;
            max-height: 70vh;
        }

        .video-detail-info {
            padding: 20px;
            color: white;
        }

        .video-detail-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .video-detail-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #ccc;
        }

        .video-detail-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .video-detail-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff5a7e 0%, #ff3a6f 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }

        .video-detail-description {
            color: #aaa;
            line-height: 1.6;
            margin-top: 15px;
        }

        .video-detail-like {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .video-detail-like-btn {
            background: rgba(255, 90, 126, 0.2);
            border: 1px solid #ff5a7e;
            color: #ff5a7e;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .video-detail-like-btn:hover {
            background: #ff5a7e;
            color: white;
        }

        .video-detail-like-btn.liked {
            background: #ff5a7e;
            color: white;
        }

        .video-info {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .video-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.7);
            padding: 6px 8px;
            border-radius: 6px;
        }

        .video-description {
            color: #999;
            margin-bottom: 10px;
            font-size: 13px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .video-meta {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            font-size: 12px;
        }

        .author-info {
            display: flex;
            align-items: center;
            flex: 1;
        }

        .author-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(255, 90, 126, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 11px;
            margin-right: 8px;
        }

        .author-details {
            display: flex;
            flex-direction: column;
        }

        .author-name {
            color: #333;
            font-weight: 600;
            font-size: 12px;
        }

        .upload-time {
            color: #ccc;
            font-size: 11px;
        }

        .like-icon {
            font-size: 14px;
        }

        .like-form {
            display: flex;
            align-items: center;
        }

        .like-btn {
            display: flex;
            align-items: center;
            gap: 4px;
            background: none;
            border: none;
            cursor: pointer;
            color: #ccc;
            font-size: 12px;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .like-btn:hover {
            background: rgba(255, 90, 126, 0.1);
        }

        .like-btn.liked {
            color: #ff5a7e;
        }

        .like-btn.liked:hover {
            background: rgba(255, 90, 126, 0.2);
        }

        .video-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
        }

        .delete-btn {
            flex: 1;
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .delete-btn:hover {
            background: #c82333;
        }

        .edit-btn {
            flex: 1;
            background: #007bff;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .edit-btn:hover {
            background: #0056b3;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .empty-text {
            font-size: 18px;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .video-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .video-player {
                height: 200px;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="video-page">
    <?php if (!$isLoggedIn): ?>
    <div style="max-width: 1400px; margin: 100px auto; text-align: center; padding: 40px;">
        <h1 style="font-size: 32px; margin-bottom: 20px; color: #333;">✨ 影片交流</h1>
        <p style="font-size: 18px; color: #666; margin-bottom: 30px;">需要登入才能查看和上傳影片</p>
        <a href="login.php" style="display: inline-block; background: linear-gradient(135deg, #ff5a7e 0%, #ff3a6f 100%); color: white; padding: 12px 40px; border-radius: 8px; text-decoration: none; font-size: 16px; font-weight: 600; cursor: pointer;">📱 前往登入</a>
    </div>
    <?php else: ?>
    <div class="video-wrapper">
        <h1 class="page-title">✨ 影片交流</h1>

        <div class="nav-tabs">
            <a href="?view=home" class="nav-tab <?php echo ($view === 'home') ? 'active' : ''; ?>">🏠 主頁</a>
            <a href="?view=personal" class="nav-tab <?php echo ($view === 'personal') ? 'active' : ''; ?>">👤 個人</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($view === 'personal'): ?>
        <div class="content-grid">
            <div class="upload-section">
                <h2>📹 分享你的精彩時刻</h2>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title">影片標題 *</label>
                            <input type="text" id="title" name="title" placeholder="輸入吸引人的標題..." required>
                        </div>

                        <div class="form-group">
                            <label for="description">影片描述</label>
                            <textarea id="description" name="description" placeholder="分享這個影片的背景故事..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="video">選擇影片檔案 *</label>
                            <input type="file" id="video" name="video" accept="video/*" required>
                            <small style="color: #999; margin-top: 5px; display: block;">支援 MP4、AVI、MOV 等格式，最大 40MB</small>
                        </div>

                        <button type="submit" class="upload-btn">🚀 上傳影片</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($view === 'personal'): ?>
            <?php
            $myUploads = array_filter($videos, function($v) { return $v['uploaded_by'] === $_SESSION['user']; });
            $myLikes = array_filter($videos, function($v) { return $v['uploaded_by'] !== $_SESSION['user'] && $v['is_liked']; });
            ?>

            <h2 style="font-size: 24px; margin-bottom: 20px; color: #333;">📤 我的上傳</h2>
            <?php if (empty($myUploads)): ?>
                <div class="empty-state" style="grid-column: 1 / -1; margin-bottom: 40px;">
                    <div class="empty-icon">🎥</div>
                    <div class="empty-text">還沒有上傳影片</div>
                    <p style="color: #ccc;">開始分享你的精彩時刻吧！</p>
                </div>
            <?php else: ?>
                <div class="video-grid" style="margin-bottom: 40px;">
                    <?php foreach ($myUploads as $video): ?>
                        <div class="video-card">
                            <div class="video-player">
                                <video controls>
                                    <source src="<?php echo htmlspecialchars($video['file_path']); ?>" type="video/mp4">
                                    您的瀏覽器不支援影片播放。
                                </video>
                                <div class="play-icon"></div>
                                <form method="post" class="video-like-form">
                                    <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                    <button type="submit" name="toggle_like" class="video-like-btn <?php echo $video['is_liked'] ? 'liked' : ''; ?>">
                                        <span><?php echo $video['is_liked'] ? '❤️' : '🤍'; ?></span>
                                        <span><?php echo $video['likes']; ?></span>
                                    </button>
                                </form>
                            </div>

                            <div class="video-info">
                                <div class="video-title"><?php echo htmlspecialchars($video['title']); ?></div>
                                
                                <?php if (!empty($video['description'])): ?>
                                    <div class="video-description"><?php echo htmlspecialchars(substr($video['description'], 0, 100)); ?></div>
                                <?php endif; ?>

                                <div class="video-meta">
                                    <div class="author-info">
                                        <div class="author-avatar">
                                            <?php echo strtoupper($video['uploaded_by'][0]); ?>
                                        </div>
                                        <div class="author-details">
                                            <div class="author-name"><?php echo htmlspecialchars($video['uploaded_by']); ?></div>
                                            <div class="upload-time"><?php echo date('m月d日', strtotime($video['upload_time'])); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="video-actions">
                                    <form method="post" style="flex: 1;" onsubmit="return confirm('確定要刪除此影片嗎？')">
                                        <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                        <button type="submit" name="delete" class="delete-btn">🗑️ 刪除</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2 style="font-size: 24px; margin-bottom: 20px; color: #333;">❤️ 我的按讚</h2>
            <?php if (empty($myLikes)): ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <div class="empty-icon">💖</div>
                    <div class="empty-text">還沒有按讚的影片</div>
                    <p style="color: #ccc;">去主頁發現喜歡的影片吧！</p>
                </div>
            <?php else: ?>
                <div class="video-grid">
                    <?php foreach ($myLikes as $video): ?>
                        <div class="video-card">
                            <div class="video-player">
                                <video controls>
                                    <source src="<?php echo htmlspecialchars($video['file_path']); ?>" type="video/mp4">
                                    您的瀏覽器不支援影片播放。
                                </video>
                                <div class="play-icon"></div>
                                <form method="post" class="video-like-form">
                                    <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                    <button type="submit" name="toggle_like" class="video-like-btn liked">
                                        <span>❤️</span>
                                        <span><?php echo $video['likes']; ?></span>
                                    </button>
                                </form>
                            </div>

                            <div class="video-info">
                                <div class="video-title"><?php echo htmlspecialchars($video['title']); ?></div>
                                
                                <?php if (!empty($video['description'])): ?>
                                    <div class="video-description"><?php echo htmlspecialchars(substr($video['description'], 0, 100)); ?></div>
                                <?php endif; ?>

                                <div class="video-meta">
                                    <div class="author-info">
                                        <div class="author-avatar">
                                            <?php echo strtoupper($video['uploaded_by'][0]); ?>
                                        </div>
                                        <div class="author-details">
                                            <div class="author-name"><?php echo htmlspecialchars($video['uploaded_by']); ?></div>
                                            <div class="upload-time"><?php echo date('m月d日', strtotime($video['upload_time'])); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="video-actions">
                                    <!-- 對於按讚的影片，不顯示刪除按鈕 -->
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="text-align: center; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%); border-radius: 12px; border: 2px solid #ff5a7e;">
                <h3 style="color: #ff5a7e; margin-bottom: 10px;">📹 想要分享影片嗎？</h3>
                <p style="color: #666; margin-bottom: 15px;">切換到個人頁面即可上傳您的精彩影片！</p>
                <a href="?view=personal" style="display: inline-block; background: linear-gradient(135deg, #ff5a7e 0%, #ff3a6f 100%); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600;">👤 前往個人頁面上傳</a>
            </div>
            <div class="video-grid">
                <?php foreach ($videos as $index => $video): ?>
                    <div class="video-card" 
                         data-video-id="<?php echo $video['id']; ?>"
                         data-title="<?php echo htmlspecialchars($video['title']); ?>"
                         data-author="<?php echo htmlspecialchars($video['uploaded_by']); ?>"
                         data-time="<?php echo date('Y年m月d日', strtotime($video['upload_time'])); ?>"
                         data-likes="<?php echo $video['likes']; ?>"
                         data-is-liked="<?php echo $video['is_liked']; ?>"
                         data-description="<?php echo htmlspecialchars($video['description'] ?? ''); ?>"
                         data-file-path="<?php echo htmlspecialchars($video['file_path']); ?>"
                         onclick="openVideoDetail(<?php echo $video['id']; ?>)">
                        <div class="video-player">
                            <video controls playsinline <?php echo $index === 0 ? 'autoplay muted' : ''; ?>>
                                <source src="<?php echo htmlspecialchars($video['file_path']); ?>" type="video/mp4">
                                您的瀏覽器不支援影片播放。
                            </video>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($videos)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📺</div>
                    <div class="empty-text">還沒有影片呢</div>
                    <p style="color: #ccc;">成為第一個分享精彩時刻的人吧！</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 影片詳情浮層 -->
    <div id="videoDetailOverlay" class="video-detail-overlay">
        <button class="video-detail-close" onclick="closeVideoDetail()">×</button>
        <div class="video-detail-content">
            <div class="video-detail-player">
                <video controls id="detailVideo">
                    <source src="" type="video/mp4">
                    您的瀏覽器不支援影片播放。
                </video>
            </div>
            <div class="video-detail-info">
                <div class="video-detail-title"></div>
                <div class="video-detail-meta">
                    <div class="video-detail-author"></div>
                    <div class="video-detail-time"></div>
                    <div class="video-detail-likes"></div>
                </div>
                <div class="video-detail-like">
                    <button class="video-detail-like-btn" onclick="toggleLike()">🤍 讚</button>
                </div>
                <div class="video-detail-description"></div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
    let currentVideoIndex = 0;
    let videoIds = [];

    // 初始化影片ID列表
    function initVideoIds() {
        const videoCards = document.querySelectorAll('.video-card');
        videoIds = Array.from(videoCards).map(card => card.dataset.videoId);
    }

    // 頁面載入後初始化
    document.addEventListener('DOMContentLoaded', function() {
        initVideoIds();
        
        const overlay = document.getElementById('videoDetailOverlay');
        if (overlay) {
            overlay.addEventListener('wheel', function(e) {
                if (e.deltaY > 0) {
                    // 向下滾動 → 下一則
                    nextVideo();
                } else {
                    // 向上滾動 → 上一則
                    prevVideo();
                }
                e.preventDefault();
            }, { passive: false });
        }
    });

    // 影片詳情浮層功能
    function openVideoDetail(videoId) {
        // 每次打開時重新整理影片列表，確保順序正確
        initVideoIds();
        currentVideoIndex = videoIds.indexOf(String(videoId));
        
        const overlay = document.getElementById('videoDetailOverlay');
        if (overlay) {
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // 載入影片詳情
            loadVideoDetail(videoId);
        }
    }

    function closeVideoDetail() {
        const overlay = document.getElementById('videoDetailOverlay');
        if (overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            
            // 停止影片播放
            const detailVideo = overlay.querySelector('video');
            if (detailVideo) {
                detailVideo.pause();
            }
        }
    }

    function loadVideoDetail(videoId) {
        const videoCards = document.querySelectorAll('.video-card');
        videoCards.forEach(card => {
            const id = card.dataset.videoId;
            if (id == videoId) {
                const title = card.dataset.title;
                const author = card.dataset.author;
                const time = card.dataset.time;
                const likes = card.dataset.likes;
                const isLiked = card.dataset.isLiked === '1';
                const description = card.dataset.description;
                const filePath = card.dataset.filePath;

                const overlay = document.getElementById('videoDetailOverlay');
                overlay.querySelector('.video-detail-title').textContent = title;
                overlay.querySelector('.video-detail-author').innerHTML = `
                    <div class="video-detail-avatar">${author.charAt(0).toUpperCase()}</div>
                    <span>${author}</span>
                `;
                overlay.querySelector('.video-detail-time').textContent = time;
                overlay.querySelector('.video-detail-likes').textContent = likes + ' 讚';
                overlay.querySelector('.video-detail-description').textContent = description || '無描述';
                
                const likeBtn = overlay.querySelector('.video-detail-like-btn');
                likeBtn.className = 'video-detail-like-btn' + (isLiked ? ' liked' : '');
                likeBtn.innerHTML = isLiked ? '❤️ 已讚' : '🤍 讚';
                likeBtn.onclick = function() { toggleLike(videoId); };

                const videoEl = overlay.querySelector('.video-detail-player video source');
                if (videoEl) {
                    videoEl.src = filePath;
                    // 重新載入影片
                    const video = overlay.querySelector('.video-detail-player video');
                    video.load();
                }
            }
        });
    }

    // 上一則影片
    function prevVideo() {
        if (currentVideoIndex > 0) {
            currentVideoIndex--;
            openVideoDetail(videoIds[currentVideoIndex]);
        }
    }

    // 下一則影片
    function nextVideo() {
        if (currentVideoIndex < videoIds.length - 1) {
            currentVideoIndex++;
            openVideoDetail(videoIds[currentVideoIndex]);
        }
    }

    function toggleLike(videoId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const videoIdInput = document.createElement('input');
        videoIdInput.name = 'video_id';
        videoIdInput.value = videoId;
        
        const toggleLikeInput = document.createElement('input');
        toggleLikeInput.name = 'toggle_like';
        toggleLikeInput.value = '1';
        
        form.appendChild(videoIdInput);
        form.appendChild(toggleLikeInput);
        document.body.appendChild(form);
        form.submit();
    }

    // 關閉浮層
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeVideoDetail();
        } else if (e.key === 'ArrowUp') {
            prevVideo();
        } else if (e.key === 'ArrowDown') {
            nextVideo();
        }
    });
</script>

</body>
</html>
