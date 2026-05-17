
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'db.php';

// 檢查是否已登入
$isLoggedIn = isset($_SESSION['user']);
$isAdmin    = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// 建立 videos 目錄
$videosDir = __DIR__ . '/videos';
if (!is_dir($videosDir)) {
    if (!mkdir($videosDir, 0755, true)) {
        die('無法創建 videos 目錄，請檢查權限設定');
    }
}

$message = '';
$messageType = '';

// 偵測 POST 超過 post_max_size 的情況（$_FILES 會是空的）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES) && empty($_POST)
    && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $limitMB = (int)ini_get('post_max_size');
    $message = "檔案太大，超過伺服器限制（最大 {$limitMB}MB）";
    $messageType = 'error';
}

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

// 管理員：強制刪除影片
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_force_delete'])) {
    $vid = (int)$_POST['video_id'];
    $row = $pdo->prepare("SELECT file_path FROM videos WHERE id = ?");
    $row->execute([$vid]);
    $vrow = $row->fetch();
    if ($vrow) {
        $fp = __DIR__ . '/' . $vrow['file_path'];
        if (file_exists($fp) && is_file($fp)) unlink($fp);
        $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$vid]);
        $message     = '已強制刪除影片';
        $messageType = 'success';
    }
}

// 管理員：標記檢舉為已處理
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_dismiss_report'])) {
    $vid = (int)$_POST['video_id'];
    $pdo->prepare("UPDATE video_reports SET status = 'resolved' WHERE video_id = ?")
        ->execute([$vid]);
    $message     = '已標記為已處理';
    $messageType = 'success';
}

// 獲取影片列表
$view = $_GET['view'] ?? 'home';
if ($view === 'admin' && !$isAdmin) $view = 'home';
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
} elseif ($view === 'admin' && $isAdmin) {
    $videos = [];
    // 檢舉清單（pending 狀態）
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
    } catch (Exception $e) {}

    $reportStmt = $pdo->query("
        SELECT v.id, v.title, v.uploaded_by, v.file_path,
               COUNT(r.id) AS report_count,
               MAX(r.created_at) AS last_reported_at
        FROM videos v
        JOIN video_reports r ON v.id = r.video_id
        WHERE r.status = 'pending'
        GROUP BY v.id
        ORDER BY report_count DESC, last_reported_at DESC
    ");
    $reportedVideos = $reportStmt->fetchAll();

    $reasonStmt = $pdo->query("
        SELECT video_id, reason, COUNT(*) AS cnt
        FROM video_reports
        WHERE status = 'pending'
        GROUP BY video_id, reason
        ORDER BY cnt DESC
    ");
    $reportedReasons = [];
    foreach ($reasonStmt->fetchAll() as $r) {
        $reportedReasons[$r['video_id']][] = $r;
    }

    $detailStmt = $pdo->query("
        SELECT video_id, reported_by, reason, description, created_at
        FROM video_reports
        WHERE status = 'pending' AND description IS NOT NULL AND description != ''
        ORDER BY created_at DESC
    ");
    $reportedDetails = [];
    foreach ($detailStmt->fetchAll() as $d) {
        $reportedDetails[$d['video_id']][] = $d;
    }
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
    <link rel="stylesheet" href="/sa/New-SA/產品/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .video-page {
            min-height: 100vh;
            background: #f2f2f7;
            padding: 32px 20px 60px;
        }

        .video-wrapper {
            max-width: 1160px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 24px;
            font-weight: 800;
            color: #1c1c1e;
            margin-bottom: 4px;
            text-align: center;
            letter-spacing: -0.3px;
        }

        .nav-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 28px;
            border-bottom: 2px solid #e5e5ea;
            gap: 0;
        }

        .nav-tab {
            padding: 11px 26px;
            background: transparent;
            color: #8e8e93;
            text-decoration: none;
            border-radius: 0;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: color 0.2s, border-color 0.2s;
        }

        .nav-tab.active {
            color: #e83e5a;
            border-bottom-color: #e83e5a;
            background: transparent;
        }

        .nav-tab:hover {
            color: #e83e5a;
            background: transparent;
        }

        .nav-tab.active:hover {
            background: transparent;
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
            padding: 24px 28px;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        .upload-section h2 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #1c1c1e;
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
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .video-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            transition: transform 0.22s ease, box-shadow 0.22s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }

        .video-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 28px rgba(0,0,0,0.13);
        }

        .video-player {
            width: 100%;
            height: 200px;
            background: #1c1c1e;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .video-player video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            pointer-events: none;
        }

        .play-icon {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.22);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.22s;
        }

        .play-icon::after {
            content: '▶';
            color: white;
            font-size: 20px;
            width: 52px;
            height: 52px;
            background: rgba(232,62,90,0.92);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-left: 4px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.3);
        }

        .video-card:hover .play-icon {
            opacity: 1;
        }

        /* 卡片底部資訊（首頁） */
        .vc-info {
            padding: 12px 14px 14px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .vc-title {
            font-size: 14px;
            font-weight: 700;
            color: #1c1c1e;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .vc-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }

        .vc-author {
            font-size: 12px;
            color: #8e8e93;
        }

        .vc-likes {
            font-size: 12px;
            color: #e83e5a;
            font-weight: 600;
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            gap: 30px;
            align-items: flex-start;
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
            width: 65%;
            max-height: 70vh;
            background: #000;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
        }

        .video-detail-player video {
            max-width: 100%;
            max-height: 70vh;
        }

        .video-detail-info {
            padding: 0;
            color: white;
            flex: 1;
            overflow-y: auto;
            max-height: 70vh;
        }

        .video-detail-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .video-detail-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 12px;
            font-size: 13px;
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
            line-height: 1.5;
            margin-top: 0;
            font-size: 13px;
        }

        /* 評論區樣式 */
        .comments-section {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .comments-header {
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 10px;
        }

        .comment-form {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }

        .comment-input {
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 8px 10px;
            color: #fff;
            font-size: 12px;
            outline: none;
        }

        .comment-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .comment-input:focus {
            border-color: rgba(255, 90, 126, 0.5);
            background: rgba(255, 255, 255, 0.12);
        }

        .comment-submit-btn {
            background: #ff5a7e;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .comment-submit-btn:hover {
            background: #ff3a6f;
        }

        .comments-list {
            max-height: 300px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .comment-item {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .comment-author {
            font-size: 12px;
            font-weight: 600;
            color: #ff5a7e;
        }

        .comment-time {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
        }

        .comment-content {
            font-size: 12px;
            color: #ddd;
            margin-bottom: 8px;
            line-height: 1.4;
            word-break: break-word;
        }

        .comment-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .comment-like-btn {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 3px;
            transition: all 0.2s;
        }

        .comment-like-btn:hover {
            color: #ff5a7e;
        }

        .comment-like-btn.liked {
            color: #ff5a7e;
        }

        .comment-reply-btn {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s;
        }

        .comment-reply-btn:hover {
            color: #ff5a7e;
        }

        .comment-delete-btn {
            background: none;
            border: none;
            color: rgba(220, 53, 69, 0.8);
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s;
        }

        .comment-delete-btn:hover {
            color: #dc3545;
        }

        .comment-report-btn {
            background: none;
            border: none;
            color: rgba(150, 100, 0, 0.7);
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s;
        }
        .comment-report-btn:hover { color: #856404; }

        .comment-report-form {
            margin-top: 8px;
            background: #fff8e8;
            border: 1px solid #ffc;
            border-radius: 8px;
            padding: 10px 12px;
            display: none;
            flex-direction: column;
            gap: 7px;
        }
        .comment-report-form select,
        .comment-report-form textarea {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 12px;
            background: #fff;
        }
        .comment-report-form textarea { resize: none; height: 54px; }
        .comment-report-actions { display: flex; gap: 6px; }
        .comment-report-actions button {
            padding: 5px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
        }
        .comment-report-submit { background: #e83e5a; color: #fff; }
        .comment-report-cancel { background: #e0e0e0; color: #555; }

        .replies {
            margin-top: 8px;
            padding-left: 12px;
            border-left: 2px solid rgba(255, 90, 126, 0.3);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .reply-item {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 6px;
            padding: 8px;
            font-size: 11px;
        }

        .reply-form {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }

        .reply-input {
            flex: 1;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 6px;
            padding: 6px 8px;
            color: #fff;
            font-size: 11px;
            outline: none;
        }

        .reply-input:focus {
            border-color: rgba(255, 90, 126, 0.4);
        }

        .reply-submit-btn {
            background: #ff5a7e;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 11px;
            white-space: nowrap;
        }

        .reply-submit-btn:hover {
            background: #ff3a6f;
        }

        .empty-comments {
            text-align: center;
            color: rgba(255, 255, 255, 0.4);
            font-size: 12px;
            padding: 20px 0;
        }

        .video-detail-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            margin-bottom: 12px;
        }

        .video-detail-like-btn,
        .report-trigger-btn {
            border: none;
            border-radius: 18px;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .video-detail-like-btn {
            background: #ff5a7e;
            color: white;
        }

        .video-detail-like-btn:hover,
        .report-trigger-btn:hover {
            transform: translateY(-1px);
            opacity: 0.95;
        }

        .video-detail-like-btn.liked {
            background: #d6336c;
            color: white;
        }

        .report-trigger-btn {
            background: #dc3545;
            color: white;
        }

        .video-detail-like-btn:hover {
            background: #ff5a7e;
            color: white;
        }

        .video-detail-like-btn.liked {
            background: #ff5a7e;
            color: white;
        }

        .share-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: none;
            border-radius: 18px;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .share-btn:hover {
            background: rgba(255, 255, 255, 0.28);
            transform: translateY(-1px);
        }

        .share-toast {
            display: none;
            position: fixed;
            bottom: 36px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(30, 30, 30, 0.92);
            color: #fff;
            padding: 10px 22px;
            border-radius: 24px;
            font-size: 14px;
            z-index: 2000;
            pointer-events: none;
            white-space: nowrap;
        }

        .share-toast.show {
            display: block;
            animation: fadeInOut 2s ease forwards;
        }

        @keyframes fadeInOut {
            0%   { opacity: 0; transform: translateX(-50%) translateY(8px); }
            15%  { opacity: 1; transform: translateX(-50%) translateY(0); }
            75%  { opacity: 1; }
            100% { opacity: 0; }
        }

        .video-detail-report {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .report-trigger-btn,
        .report-submit-btn,
        .report-cancel-btn {
            border: none;
            border-radius: 20px;
            padding: 10px 18px;
            font-size: 14px;
            cursor: pointer;
        }

        .report-trigger-btn {
            background: rgba(255, 255, 255, 0.15);
            color: #ff5a7e;
        }

        .report-trigger-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .report-form {
            display: none;
            flex-direction: column;
            gap: 10px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 12px;
            margin-bottom: 12px;
        }

        .report-form.active {
            display: flex;
        }

        .report-form label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #fff;
            font-size: 13px;
        }

        .report-form input[type="radio"],
        .report-form textarea {
            margin-right: 6px;
        }

        .report-form textarea {
            width: 100%;
            min-height: 60px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 8px;
            color: #fff;
            resize: vertical;
            font-size: 12px;
        }

        .report-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .report-message {
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 12px;
            display: none;
        }

        .report-message.success {
            display: block;
            background: rgba(72, 187, 120, 0.15);
            color: #d4f8dc;
            border: 1px solid rgba(72, 187, 120, 0.35);
        }

        .report-message.error {
            display: block;
            background: rgba(220, 53, 69, 0.15);
            color: #ffd5dc;
            border: 1px solid rgba(220, 53, 69, 0.35);
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

        @media (max-width: 900px) {
            .video-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 560px) {
            .video-grid {
                grid-template-columns: 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .video-player {
                height: 220px;
            }
        }
    </style>
</head>
<body>

<?php include '../產品/header.php'; ?>

<main class="video-page">
    <?php if (!$isLoggedIn): ?>
    <div style="max-width: 1400px; margin: 100px auto; text-align: center; padding: 40px;">
        <h1 style="font-size: 32px; margin-bottom: 20px; color: #333;">✨ 影片交流</h1>
        <p style="font-size: 18px; color: #666; margin-bottom: 30px;">需要登入才能查看和上傳影片</p>
        <a href="login.php" style="display: inline-block; background: linear-gradient(135deg, #ff5a7e 0%, #ff3a6f 100%); color: white; padding: 12px 40px; border-radius: 8px; text-decoration: none; font-size: 16px; font-weight: 600; cursor: pointer;">📱 前往登入</a>
    </div>
    <?php else: ?>
    <div class="video-wrapper">
        <h1 class="page-title">影片交流</h1>
        <p style="text-align:center;color:#8e8e93;font-size:13px;margin-bottom:20px;">探索彩妝技巧，分享你的精彩</p>

        <div class="nav-tabs">
            <a href="?view=home" class="nav-tab <?php echo ($view === 'home') ? 'active' : ''; ?>">🏠 主頁</a>
            <a href="?view=personal" class="nav-tab <?php echo ($view === 'personal') ? 'active' : ''; ?>">👤 個人</a>
            <?php if ($isAdmin): ?>
                <a href="?view=admin" class="nav-tab <?php echo ($view === 'admin') ? 'active' : ''; ?>" style="background:<?php echo ($view === 'admin') ? '#c82333' : '#6c757d'; ?>;color:#fff;">🛡️ 檢舉管理</a>
            <?php endif; ?>
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
        <?php elseif ($view === 'admin' && $isAdmin): ?>
            <style>
                .report-mgr-table { width:100%; border-collapse:collapse; font-size:14px; }
                .report-mgr-table th, .report-mgr-table td { padding:12px 14px; border:1px solid #f1d1dc; text-align:left; vertical-align:top; }
                .report-mgr-table th { background:#ffe3eb; color:#9c2132; }
                .report-mgr-table tbody tr:nth-child(odd) { background:#fff7f9; }
                .rpt-tag { display:inline-block; background:#ffd6de; color:#9c2132; padding:2px 8px; border-radius:10px; font-size:12px; margin:2px; }
                .btn-force-del { background:#c82333; color:#fff; border:none; border-radius:6px; padding:7px 12px; cursor:pointer; font-size:12px; }
                .btn-dismiss { background:#6c757d; color:#fff; border:none; border-radius:6px; padding:7px 12px; cursor:pointer; font-size:12px; margin-left:6px; }
                .btn-preview { background:#0069d9; color:#fff; border:none; border-radius:6px; padding:7px 12px; cursor:pointer; font-size:12px; margin-left:6px; }

                /* 影片預覽 Modal */
                .rpt-preview-overlay {
                    display:none; position:fixed; inset:0;
                    background:rgba(0,0,0,0.8); z-index:3000;
                    justify-content:center; align-items:center;
                }
                .rpt-preview-overlay.open { display:flex; }
                .rpt-preview-box {
                    background:#111; border-radius:12px; padding:20px;
                    max-width:720px; width:90%; position:relative;
                }
                .rpt-preview-box video { width:100%; border-radius:8px; max-height:70vh; }
                .rpt-preview-title { color:#fff; font-size:15px; font-weight:600; margin-bottom:12px; }
                .rpt-preview-close {
                    position:absolute; top:12px; right:14px;
                    background:none; border:none; color:#aaa;
                    font-size:22px; cursor:pointer;
                }
                .rpt-preview-close:hover { color:#fff; }
            </style>
            <h2 style="font-size:22px;margin-bottom:18px;color:#c82333;">🚩 待處理檢舉</h2>
            <?php if (empty($reportedVideos)): ?>
                <div class="empty-state">
                    <div class="empty-icon">✅</div>
                    <div class="empty-text">目前沒有待處理的檢舉</div>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="report-mgr-table">
                    <thead>
                        <tr>
                            <th>影片標題</th>
                            <th>上傳者</th>
                            <th>檢舉次數</th>
                            <th>檢舉原因</th>
                            <th>最後檢舉時間</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportedVideos as $rv): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rv['title']); ?></td>
                                <td><?php echo htmlspecialchars($rv['uploaded_by']); ?></td>
                                <td style="text-align:center;font-weight:bold;color:#c82333;"><?php echo (int)$rv['report_count']; ?></td>
                                <td>
                                    <?php foreach ($reportedReasons[$rv['id']] ?? [] as $reason): ?>
                                        <span class="rpt-tag"><?php echo htmlspecialchars($reason['reason']); ?> ×<?php echo (int)$reason['cnt']; ?></span>
                                    <?php endforeach; ?>
                                    <?php if (!empty($reportedDetails[$rv['id']])): ?>
                                        <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px;">
                                            <?php foreach ($reportedDetails[$rv['id']] as $d): ?>
                                                <div style="background:#fff0f3;border-left:3px solid #c82333;padding:6px 10px;border-radius:0 6px 6px 0;font-size:12px;">
                                                    <span style="color:#9c2132;font-weight:600;"><?php echo htmlspecialchars($d['reported_by']); ?></span>
                                                    <span style="color:#aaa;margin:0 6px;">·</span>
                                                    <span style="color:#666;"><?php echo htmlspecialchars($d['reason']); ?></span>
                                                    <div style="color:#444;margin-top:3px;line-height:1.5;"><?php echo nl2br(htmlspecialchars($d['description'])); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('m/d H:i', strtotime($rv['last_reported_at'])); ?></td>
                                <td style="white-space:nowrap;">
                                    <button class="btn-preview"
                                        onclick="openReportPreview(
                                            '<?php echo htmlspecialchars(addslashes($rv['title'])); ?>',
                                            '<?php echo htmlspecialchars(addslashes($rv['file_path'])); ?>'
                                        )">▶ 查看影片</button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('確定強制刪除？')">
                                        <input type="hidden" name="video_id" value="<?php echo (int)$rv['id']; ?>">
                                        <button type="submit" name="admin_force_delete" value="1" class="btn-force-del">🗑️ 刪除影片</button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="video_id" value="<?php echo (int)$rv['id']; ?>">
                                        <button type="submit" name="admin_dismiss_report" value="1" class="btn-dismiss">✓ 標記已處理</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>

        <!-- 影片預覽 Modal（管理員用）-->
        <div class="rpt-preview-overlay" id="rptPreviewOverlay">
            <div class="rpt-preview-box">
                <button class="rpt-preview-close" onclick="closeReportPreview()">✕</button>
                <div class="rpt-preview-title" id="rptPreviewTitle"></div>
                <video id="rptPreviewVideo" controls playsinline>
                    <source id="rptPreviewSource" src="" type="video/mp4">
                </video>
            </div>
        </div>

        <?php else: ?>
            <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                <a href="?view=personal" style="display:inline-flex;align-items:center;gap:6px;background:#e83e5a;color:#fff;padding:9px 18px;border-radius:20px;text-decoration:none;font-size:13px;font-weight:600;box-shadow:0 2px 8px rgba(232,62,90,0.3);">
                    + 上傳影片
                </a>
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
                         onclick="openVideoDetail(<?php echo (int)$video['id']; ?>)">
                        <div class="video-player">
                            <video playsinline muted preload="metadata">
                                <source src="<?php echo htmlspecialchars($video['file_path']); ?>" type="video/mp4">
                            </video>
                            <div class="play-icon"></div>
                        </div>
                        <div class="vc-info">
                            <div class="vc-title"><?php echo htmlspecialchars($video['title']); ?></div>
                            <div class="vc-footer">
                                <span class="vc-author"><?php echo htmlspecialchars($video['uploaded_by']); ?></span>
                                <span class="vc-likes">❤️ <?php echo (int)$video['likes']; ?></span>
                            </div>
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
                <div class="video-detail-actions">
                    <button class="video-detail-like-btn" onclick="toggleLike()">🤍 讚</button>
                    <button class="share-btn" onclick="shareVideo()">🔗 分享</button>
                    <button class="report-trigger-btn" onclick="toggleReportForm()">🚩 檢舉</button>
                </div>
                <div id="reportForm" class="report-form">
                    <div>
                        <strong style="color:#fff;">請選擇檢舉原因</strong>
                    </div>
                    <label><input type="radio" name="report_reason" value="不當內容" checked> 不當內容</label>
                    <label><input type="radio" name="report_reason" value="侵權影片"> 侵權影片</label>
                    <label><input type="radio" name="report_reason" value="騷擾或仇恨言論"> 騷擾或仇恨言論</label>
                    <label><input type="radio" name="report_reason" value="其他"> 其他</label>
                    <textarea id="reportDescription" placeholder="請說明檢舉原因，至少 10 個字"></textarea>
                    <div class="report-actions">
                        <button class="report-submit-btn" onclick="submitReport(event)">送出檢舉</button>
                        <button class="report-cancel-btn" onclick="hideReportForm(event)">取消</button>
                    </div>
                    <div id="reportMessage" class="report-message"></div>
                </div>
                
                <!-- 評論區 -->
                <div class="comments-section">
                    <div class="comments-header">💬 評論</div>
                    
                    <div class="comment-form">
                        <input 
                            type="text" 
                            class="comment-input" 
                            id="commentInput" 
                            placeholder="分享你的想法..." 
                            maxlength="200">
                        <button class="comment-submit-btn" onclick="submitComment()">發表</button>
                    </div>

                    <div id="commentsList" class="comments-list">
                        <div class="empty-comments">尚無評論</div>
                    </div>
                </div>

                <div class="video-detail-description"></div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<div class="share-toast" id="shareToast">🔗 已複製連結！</div>

<script>
    let currentVideoIndex = 0;
    let videoIds = [];
    let currentDetailVideoId = null;
    let scrollDebounce = null;
    
    // 儲存當前用戶資訊
    const currentUser = '<?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : ''; ?>';
    const currentRole = '<?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : ''; ?>';

    // 初始化影片ID列表
    function initVideoIds() {
        const videoCards = document.querySelectorAll('.video-card');
        videoIds = Array.from(videoCards).map(card => card.dataset.videoId);
    }

    // 頁面載入後初始化
    document.addEventListener('DOMContentLoaded', function() {
        initVideoIds();

        // 讓縮圖顯示第一幀而非黑畫面
        document.querySelectorAll('.video-card .video-player video').forEach(function(v) {
            v.addEventListener('loadedmetadata', function() {
                v.currentTime = 0.5;
            });
            if (v.readyState >= 1) v.currentTime = 0.5;
        });

        // 為影片卡片添加點擊事件監聽
        const videoCards = document.querySelectorAll('.video-card');
        videoCards.forEach(card => {
            card.addEventListener('click', function() {
                const videoId = this.dataset.videoId;
                openVideoDetail(videoId);
            });
        });
        
        const overlay = document.getElementById('videoDetailOverlay');
        if (overlay) {
            // 滾輪切換影片（加防抖避免連續觸發）
            overlay.addEventListener('wheel', function(e) {
                e.preventDefault();
                if (scrollDebounce) return;
                scrollDebounce = setTimeout(() => { scrollDebounce = null; }, 800);
                if (e.deltaY > 0) {
                    nextVideo();
                } else if (e.deltaY < 0) {
                    prevVideo();
                }
            }, { passive: false });

            // 手機觸控滑動支援
            let touchStartY = 0;
            overlay.addEventListener('touchstart', function(e) {
                touchStartY = e.touches[0].clientY;
            }, { passive: true });
            overlay.addEventListener('touchend', function(e) {
                const diff = touchStartY - e.changedTouches[0].clientY;
                if (Math.abs(diff) > 50) {
                    diff > 0 ? nextVideo() : prevVideo();
                }
            }, { passive: true });
        }
    });

    // 影片詳情浮層功能
    function openVideoDetail(videoId) {
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

                currentDetailVideoId = videoId;

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

                const video = overlay.querySelector('.video-detail-player video');
                if (video) {
                    video.querySelector('source').src = filePath;
                    video.load();
                    video.play().catch(e => console.warn('自動播放失敗:', e.message));
                    video.onended = function() {
                        nextVideo();
                    };
                }

                // 載入評論
                loadComments();
            }
        });
    }

    function toggleReportForm() {
        const form = document.getElementById('reportForm');
        const messageBox = document.getElementById('reportMessage');
        if (!form) return;
        
        form.classList.toggle('active');
        if (messageBox) {
            messageBox.style.display = 'none';
        }
    }

    function hideReportForm(event) {
        event.preventDefault();
        const form = document.getElementById('reportForm');
        const messageBox = document.getElementById('reportMessage');
        if (form) {
            form.classList.remove('active');
        }
        if (messageBox) {
            messageBox.style.display = 'none';
        }
    }

    function submitReport(event) {
        event.preventDefault();

        const reasonInput = document.querySelector('input[name="report_reason"]:checked');
        const descriptionInput = document.getElementById('reportDescription');
        const messageBox = document.getElementById('reportMessage');

        if (!reasonInput || !descriptionInput || !messageBox) {
            return;
        }

        const reason = reasonInput.value.trim();
        const description = descriptionInput.value.trim();

        if (reason === '' || description.length < 10) {
            messageBox.textContent = '請提供有效的檢舉資訊，說明至少 10 個字';
            messageBox.className = 'report-message error';
            messageBox.style.display = 'block';
            return;
        }

        if (!currentDetailVideoId) {
            messageBox.textContent = '無法取得影片資料，請重新整理頁面後重試';
            messageBox.className = 'report-message error';
            messageBox.style.display = 'block';
            return;
        }

        fetch('report_video.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                video_id: currentDetailVideoId,
                reason: reason,
                description: description
            })
        })
        .then(async response => {
            const data = await response.json();
            if (response.ok && data.success) {
                messageBox.textContent = data.message || '檢舉已送出，我們會盡快處理';
                messageBox.className = 'report-message success';
                messageBox.style.display = 'block';
                descriptionInput.value = '';
                setTimeout(() => {
                    const form = document.getElementById('reportForm');
                    if (form) {
                        form.classList.remove('active');
                    }
                }, 1800);
            } else {
                messageBox.textContent = data.message || '檢舉失敗，請稍後再試';
                messageBox.className = 'report-message error';
                messageBox.style.display = 'block';
            }
        })
        .catch(() => {
            messageBox.textContent = '網路連線失敗，請稍後再試';
            messageBox.className = 'report-message error';
            messageBox.style.display = 'block';
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

    // 評論功能
    function loadComments() {
        if (!currentDetailVideoId) return;

        fetch(`comment_video.php?action=get&video_id=${currentDetailVideoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayComments(data.comments, data.replies);
                }
            })
            .catch(error => console.error('載入評論失敗:', error));
    }

    // 隱藏留言相關（存 localStorage，僅對自己生效）
    const _commentData = {};
    function _getHiddenComments() {
        try { return new Set(JSON.parse(localStorage.getItem('vc_hidden_comments') || '[]').map(String)); }
        catch(e) { return new Set(); }
    }
    function _getHiddenUsers() {
        try { return new Set(JSON.parse(localStorage.getItem('vc_hidden_users') || '[]')); }
        catch(e) { return new Set(); }
    }
    function hideComment(commentId) {
        const s = _getHiddenComments(); s.add(String(commentId));
        localStorage.setItem('vc_hidden_comments', JSON.stringify([...s]));
        loadComments();
    }
    function hideUserFromComment(commentId) {
        const u = _commentData[commentId]?.username;
        if (!u) return;
        const s = _getHiddenUsers(); s.add(u);
        localStorage.setItem('vc_hidden_users', JSON.stringify([...s]));
        loadComments();
    }
    function unhideComment(commentId) {
        const s = _getHiddenComments(); s.delete(String(commentId));
        localStorage.setItem('vc_hidden_comments', JSON.stringify([...s]));
        loadComments();
    }
    function unhideUser(username) {
        const s = _getHiddenUsers(); s.delete(username);
        localStorage.setItem('vc_hidden_users', JSON.stringify([...s]));
        loadComments();
    }
    function toggleHideMenu(commentId) {
        const m = document.getElementById(`commentHideMenu-${commentId}`);
        if (m) m.style.display = m.style.display === 'none' ? 'block' : 'none';
    }

    function displayComments(comments, replies) {
        const commentsList = document.getElementById('commentsList');
        const hiddenComments = _getHiddenComments();
        const hiddenUsers    = _getHiddenUsers();

        if (comments.length === 0) {
            commentsList.innerHTML = '<div class="empty-comments">尚無評論</div>';
            return;
        }

        let html = '';
        comments.forEach(comment => {
            _commentData[comment.id] = { username: comment.username };
            const isHiddenC = hiddenComments.has(String(comment.id));
            const isHiddenU = hiddenUsers.has(comment.username);
            const isHidden  = isHiddenC || isHiddenU;

            // 被自己隱藏：白色卡片 + 明顯復原按鈕
            if (isHidden) {
                const restoreFn = isHiddenU
                    ? `unhideUser(${JSON.stringify(comment.username)})`
                    : `unhideComment(${comment.id})`;
                const hintLabel = isHiddenU
                    ? `已隱藏 ${escapeHtml(comment.username)} 的所有留言`
                    : `已隱藏此留言`;
                html += `
                <div class="comment-item" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:10px 12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                        <div style="flex:1;min-width:0;">
                            <span style="color:#999;font-size:12px;font-weight:600;">${escapeHtml(comment.username)}</span>
                            <span style="color:#bbb;font-size:11px;margin-left:6px;">⊘ ${hintLabel}</span>
                            <div style="color:#ccc;font-size:12px;margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(comment.content)}</div>
                        </div>
                        <button onclick="${restoreFn}" style="flex-shrink:0;background:#e83e5a;color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">恢復顯示</button>
                    </div>
                </div>`;
                return;
            }

            // 正常顯示
            const replyList = replies[comment.id] || [];
            const time = new Date(comment.created_at).toLocaleString('zh-Hant', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            html += `
                <div class="comment-item">
                    <div class="comment-header">
                        <span class="comment-author">${escapeHtml(comment.username)}</span>
                        <span class="comment-time">${time}</span>
                    </div>
                    <div class="comment-content">${escapeHtml(comment.content)}</div>
                    <div class="comment-actions">
                        <button class="comment-like-btn ${comment.is_liked ? 'liked' : ''}" onclick="toggleCommentLike(${comment.id})">
                            ${comment.is_liked ? '❤️' : '🤍'} ${comment.likes}
                        </button>
                        <button class="comment-reply-btn" onclick="toggleReplyForm(${comment.id})">回覆</button>
                        ${comment.username !== currentUser && currentUser ? `<button class="comment-report-btn" onclick="toggleCommentReportForm(${comment.id})">🚩 檢舉</button>` : ''}
                        ${comment.username !== currentUser && currentUser ? `<button onclick="toggleHideMenu(${comment.id})" style="background:none;border:none;color:rgba(255,255,255,0.35);cursor:pointer;font-size:11px;padding:2px 4px;transition:color 0.2s;" onmouseover="this.style.color='rgba(255,255,255,0.65)'" onmouseout="this.style.color='rgba(255,255,255,0.35)'">⊘ 隱藏</button>` : ''}
                        ${comment.username === currentUser || currentRole === 'admin' ? `<button class="comment-delete-btn" onclick="deleteComment(${comment.id})">刪除</button>` : ''}
                    </div>
                    ${comment.username !== currentUser && currentUser ? `
                    <div id="commentHideMenu-${comment.id}" style="display:none;margin-top:6px;padding:8px 10px;background:rgba(30,30,30,0.95);border:1px solid rgba(255,255,255,0.12);border-radius:8px;">
                        <div style="font-size:10px;color:rgba(255,255,255,0.4);margin-bottom:6px;">選擇隱藏方式（僅自己可見變化）：</div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button onclick="hideComment(${comment.id})" style="background:rgba(108,117,125,0.7);color:#fff;border:none;border-radius:4px;padding:4px 10px;font-size:11px;cursor:pointer;">隱藏此留言</button>
                            <button onclick="hideUserFromComment(${comment.id})" style="background:rgba(220,53,69,0.7);color:#fff;border:none;border-radius:4px;padding:4px 10px;font-size:11px;cursor:pointer;">隱藏 ${escapeHtml(comment.username)} 的全部留言</button>
                            <button onclick="toggleHideMenu(${comment.id})" style="background:rgba(255,255,255,0.08);color:#aaa;border:none;border-radius:4px;padding:4px 10px;font-size:11px;cursor:pointer;">取消</button>
                        </div>
                    </div>` : ''}
                    <div class="comment-report-form" id="commentReportForm-${comment.id}">
                        <select id="commentReportReason-${comment.id}">
                            <option value="">選擇檢舉原因</option>
                            <option value="不當言論">不當言論</option>
                            <option value="騷擾或霸凌">騷擾或霸凌</option>
                            <option value="垃圾訊息">垃圾訊息</option>
                            <option value="其他">其他</option>
                        </select>
                        <textarea id="commentReportDesc-${comment.id}" placeholder="請說明檢舉原因（至少 5 字）"></textarea>
                        <div class="comment-report-actions">
                            <button class="comment-report-submit" onclick="submitCommentReport(${comment.id})">送出</button>
                            <button class="comment-report-cancel" onclick="toggleCommentReportForm(${comment.id})">取消</button>
                        </div>
                    </div>

                    ${replyList.length > 0 ? `
                        <div class="replies">
                            ${replyList.map(reply => {
                                const replyTime = new Date(reply.created_at).toLocaleString('zh-Hant', {
                                    month: 'short',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                                return `
                                    <div class="reply-item">
                                        <div><strong class="comment-author">${escapeHtml(reply.username)}</strong> <span class="comment-time">${replyTime}</span></div>
                                        <div>${escapeHtml(reply.content)}</div>
                                        <div class="comment-actions" style="margin-top: 4px;">
                                            <button class="comment-like-btn ${reply.is_liked ? 'liked' : ''}" onclick="toggleCommentLike(${reply.id})">
                                                ${reply.is_liked ? '❤️' : '🤍'} ${reply.likes}
                                            </button>
                                            ${reply.username !== currentUser && currentUser ? `<button class="comment-report-btn" onclick="toggleCommentReportForm(${reply.id})">🚩 檢舉</button>` : ''}
                                            ${reply.username === currentUser || currentRole === 'admin' ? `<button class="comment-delete-btn" onclick="deleteComment(${reply.id})">刪除</button>` : ''}
                                        </div>
                                        <div class="comment-report-form" id="commentReportForm-${reply.id}">
                                            <select id="commentReportReason-${reply.id}">
                                                <option value="">選擇檢舉原因</option>
                                                <option value="不當言論">不當言論</option>
                                                <option value="騷擾或霸凌">騷擾或霸凌</option>
                                                <option value="垃圾訊息">垃圾訊息</option>
                                                <option value="其他">其他</option>
                                            </select>
                                            <textarea id="commentReportDesc-${reply.id}" placeholder="請說明檢舉原因（至少 5 字）"></textarea>
                                            <div class="comment-report-actions">
                                                <button class="comment-report-submit" onclick="submitCommentReport(${reply.id})">送出</button>
                                                <button class="comment-report-cancel" onclick="toggleCommentReportForm(${reply.id})">取消</button>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    ` : ''}

                    <div id="replyForm-${comment.id}" class="reply-form" style="display: none;">
                        <input type="text" class="reply-input" placeholder="輸入回覆..." maxlength="200" id="replyInput-${comment.id}">
                        <button class="reply-submit-btn" onclick="submitReply(${comment.id})">回覆</button>
                        <button class="reply-submit-btn" style="background: #666;" onclick="toggleReplyForm(${comment.id})">取消</button>
                    </div>
                </div>
            `;
        });

        commentsList.innerHTML = html;
    }

    function submitComment() {
        const input = document.getElementById('commentInput');
        const content = input.value.trim();

        if (!currentDetailVideoId || !content) {
            alert('請輸入評論內容');
            return;
        }

        if (content.length < 2) {
            alert('評論至少需要2個字');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('video_id', currentDetailVideoId);
        formData.append('content', content);

        fetch('comment_video.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                loadComments();
            } else {
                alert(data.message || '發表評論失敗');
            }
        })
        .catch(error => {
            console.error('提交評論失敗:', error);
            alert('網路連線失敗');
        });
    }

    function submitReply(parentId) {
        const input = document.getElementById(`replyInput-${parentId}`);
        const content = input.value.trim();

        if (!currentDetailVideoId || !content) {
            alert('請輸入回覆內容');
            return;
        }

        if (content.length < 2) {
            alert('回覆至少需要2個字');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('video_id', currentDetailVideoId);
        formData.append('parent_id', parentId);
        formData.append('content', content);

        fetch('comment_video.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadComments();
                toggleReplyForm(parentId);
            } else {
                alert(data.message || '發表回覆失敗');
            }
        })
        .catch(error => {
            console.error('提交回覆失敗:', error);
            alert('網路連線失敗');
        });
    }

    function toggleCommentLike(commentId) {
        const formData = new FormData();
        formData.append('action', 'toggle_like');
        formData.append('comment_id', commentId);

        fetch('comment_video.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadComments();
            }
        })
        .catch(error => console.error('按讚失敗:', error));
    }

    function deleteComment(commentId) {
        if (!confirm('確定要刪除此評論嗎？')) return;

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('comment_id', commentId);

        fetch('comment_video.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadComments();
            } else {
                alert(data.message || '刪除失敗');
            }
        })
        .catch(error => console.error('刪除失敗:', error));
    }

    function toggleReplyForm(commentId) {
        const form = document.getElementById(`replyForm-${commentId}`);
        if (form) {
            form.style.display = form.style.display === 'none' ? 'flex' : 'none';
            if (form.style.display === 'flex') {
                document.getElementById(`replyInput-${commentId}`).focus();
            }
        }
    }

    function toggleCommentReportForm(commentId) {
        const form = document.getElementById(`commentReportForm-${commentId}`);
        if (form) form.style.display = form.style.display === 'flex' ? 'none' : 'flex';
    }

    function submitCommentReport(commentId) {
        const reason = document.getElementById(`commentReportReason-${commentId}`).value;
        const desc   = document.getElementById(`commentReportDesc-${commentId}`).value.trim();
        if (!reason) { alert('請選擇檢舉原因'); return; }
        if (desc.length < 5) { alert('請說明檢舉原因（至少 5 字）'); return; }

        fetch('comment_video.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'report_comment', comment_id: commentId, reason, description: desc })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toggleCommentReportForm(commentId);
                alert('感謝你的檢舉，我們會盡快查看');
            } else {
                alert(data.message || '檢舉失敗，請重試');
            }
        })
        .catch(() => alert('網路錯誤，請重試'));
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function openReportPreview(title, filePath) {
        document.getElementById('rptPreviewTitle').textContent = title;
        const src = document.getElementById('rptPreviewSource');
        const video = document.getElementById('rptPreviewVideo');
        src.src = filePath;
        video.load();
        video.play().catch(() => {});
        document.getElementById('rptPreviewOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeReportPreview() {
        const video = document.getElementById('rptPreviewVideo');
        video.pause();
        document.getElementById('rptPreviewOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    function shareVideo() {
        const shareUrl = window.location.origin + window.location.pathname + '?view=home';
        if (navigator.clipboard) {
            navigator.clipboard.writeText(shareUrl).then(function() {
                showShareToast();
            }).catch(function() {
                prompt('複製此連結以分享：', shareUrl);
            });
        } else {
            prompt('複製此連結以分享：', shareUrl);
        }
    }

    function showShareToast() {
        const toast = document.getElementById('shareToast');
        toast.classList.remove('show');
        void toast.offsetWidth;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2200);
    }

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
