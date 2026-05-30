<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../db.php';
require_once __DIR__ . '/../notify_helper.php';

// 確保欄位存在
try { $pdo->exec("ALTER TABLE videos ADD COLUMN tags VARCHAR(500) NOT NULL DEFAULT ''"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE videos ADD COLUMN removed_reason TEXT"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE videos ADD COLUMN removed_at DATETIME"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE video_appeals ADD COLUMN reviewed_at DATETIME"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE videos ADD COLUMN view_count INT DEFAULT 0"); } catch (Exception $e) {}

// 撈現有標籤（給 autocomplete 用）
$existingTags = [];
try {
    $tagRows = $pdo->query("SELECT tags FROM videos WHERE tags != '' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tagRows as $row) {
        foreach (explode(',', $row) as $t) { $t = trim($t); if ($t) $existingTags[] = $t; }
    }
    $existingTags = array_values(array_unique($existingTags));
} catch (Exception $e) {}

// 檢查是否已登入
$isLoggedIn = isset($_SESSION['user']);
$isAdmin    = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// 確保影片 BLOB 資料表存在（影片二進位存在資料庫）
function ensureVideoFilesTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS video_files (
        video_id INT NOT NULL PRIMARY KEY,
        mime     VARCHAR(100) NOT NULL DEFAULT 'video/mp4',
        data     LONGBLOB     NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// 把影片二進位存進資料庫
function storeVideoBlob(PDO $pdo, int $videoId, string $tmpPath, string $mimeType): bool {
    ensureVideoFilesTable($pdo);
    $bin = file_get_contents($tmpPath);
    if ($bin === false) return false;
    $stmt = $pdo->prepare("INSERT INTO video_files (video_id, mime, data) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE mime = VALUES(mime), data = VALUES(data)");
    $stmt->bindValue(1, $videoId, PDO::PARAM_INT);
    $stmt->bindValue(2, $mimeType, PDO::PARAM_STR);
    $stmt->bindValue(3, $bin, PDO::PARAM_LOB);
    return $stmt->execute();
}

// 從資料庫刪除影片 BLOB
function deleteVideoFromSupabase(string $filename): void {
    // 保留簽名相容性，實際刪除在刪除流程用 video_id 處理
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
    $isAjaxUpload = isset($_POST['ajax_upload']);
    $currentView = $_GET['view'] ?? 'home';
    if ($isAdmin) {
        $message = '管理員無法上傳影片';
        $messageType = 'error';
    } elseif ($currentView !== 'personal') {
        $message = '請先切換到個人頁面才能上傳影片';
        $messageType = 'error';
    } else {
        $file = $_FILES['video'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        // 清理標籤：去空白、去重、最多 10 個
        $rawTags = trim($_POST['tags'] ?? '');
        $tagsArr = array_unique(array_filter(array_map(fn($t) => mb_substr(ltrim(trim($t), '#'), 0, 20), explode(',', $rawTags))));
        $tags = implode(',', $tagsArr);

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

                // 依 MIME 判斷 Content-Type（瀏覽器送來的有時不準，以伺服器偵測為準）
                $mimeType = mime_content_type($file['tmp_name']) ?: 'video/mp4';

                try {
                    // 先建立影片紀錄（file_path 待會用 id 組出串流網址）
                    $stmt = $pdo->prepare("
                        INSERT INTO videos (title, description, filename, file_path, uploaded_by, tags)
                        VALUES (?, ?, ?, '', ?, ?)
                    ");
                    $stmt->execute([$title, $description, $filename, $_SESSION['user'], $tags]);
                    $newVideoId = (int)$pdo->lastInsertId();

                    // 影片二進位存進資料庫
                    if (!storeVideoBlob($pdo, $newVideoId, $file['tmp_name'], $mimeType)) {
                        throw new RuntimeException('影片寫入資料庫失敗');
                    }

                    // file_path 指向串流腳本
                    $publicUrl = BASE_URL . '/video_file.php?id=' . $newVideoId;
                    $pdo->prepare("UPDATE videos SET file_path = ? WHERE id = ?")
                        ->execute([$publicUrl, $newVideoId]);

                    $message = '影片上傳成功！';
                    $messageType = 'success';

                    // 通知所有追蹤者
                    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                        id INT AUTO_INCREMENT PRIMARY KEY, recipient VARCHAR(100) NOT NULL,
                        actor VARCHAR(100) NOT NULL, type VARCHAR(50) DEFAULT 'new_video',
                        video_id INT, video_title VARCHAR(255),
                        is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    $fols = $pdo->prepare("SELECT follower FROM follows WHERE following = ?");
                    $fols->execute([$_SESSION['user']]);
                    $nStmt = $pdo->prepare("INSERT INTO notifications (recipient, actor, video_id, video_title) VALUES (?, ?, ?, ?)");
                    foreach ($fols->fetchAll() as $f) {
                        $nStmt->execute([$f['follower'], $_SESSION['user'], $newVideoId, $title]);
                    }
                } catch (Exception $e) {
                    if (!empty($newVideoId)) {
                        try { $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$newVideoId]); } catch (Exception $e2) {}
                        try { $pdo->prepare("DELETE FROM video_files WHERE video_id = ?")->execute([$newVideoId]); } catch (Exception $e2) {}
                    }
                    $message = '資料庫儲存失敗：' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
    }

    // AJAX 上傳：回傳 JSON（不重整頁面，失敗時前端保留欄位）
    if ($isAjaxUpload) {
        while (ob_get_level()) { ob_end_clean(); }  // 清掉任何提前輸出，確保 JSON 乾淨
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $messageType === 'success',
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 處理影片刪除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['video_id'])) {
    $isAjaxDelete = isset($_POST['ajax_delete']);
    $videoId = (int)$_POST['video_id'];

    // 檢查權限
    $stmt = $pdo->prepare("SELECT uploaded_by FROM videos WHERE id = ?");
    $stmt->execute([$videoId]);
    $video = $stmt->fetch();

    if ($video) {
        $canDelete = ($_SESSION['role'] ?? '') === 'admin' || $video['uploaded_by'] === $_SESSION['user'];

        if ($canDelete) {
            // 刪除影片 BLOB 與紀錄
            try { $pdo->prepare("DELETE FROM video_files WHERE video_id = ?")->execute([$videoId]); } catch (Exception $e) {}
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

    // AJAX 刪除：回傳 JSON
    if ($isAjaxDelete) {
        while (ob_get_level()) { ob_end_clean(); }  // 清掉任何提前輸出，確保 JSON 乾淨
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $messageType === 'success',
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 處理影片編輯
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_video']) && isset($_POST['video_id'])) {
    header('Content-Type: application/json');
    $videoId    = (int)$_POST['video_id'];
    $title      = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $rawTags    = trim($_POST['tags'] ?? '');
    $tagsArr    = array_unique(array_filter(array_map(fn($t) => mb_substr(ltrim(trim($t), '#'), 0, 20), explode(',', $rawTags))));
    $tags       = implode(',', $tagsArr);

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => '標題不能為空']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT uploaded_by FROM videos WHERE id = ?");
    $stmt->execute([$videoId]);
    $row = $stmt->fetch();
    if ($row && $row['uploaded_by'] === $_SESSION['user']) {
        $pdo->prepare("UPDATE videos SET title = ?, description = ?, tags = ? WHERE id = ?")
            ->execute([$title, $description, $tags, $videoId]);
        echo json_encode(['success' => true, 'title' => $title, 'description' => $description, 'tags' => $tags]);
    } else {
        echo json_encode(['success' => false, 'message' => '沒有權限']);
    }
    exit;
}

// 處理按讚/取消按讚
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_like']) && isset($_POST['video_id'])) {
    if ($isAdmin) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => '管理員無法按讚']); exit; }
    $videoId = (int)$_POST['video_id'];
    $userId  = $_SESSION['user'];

    $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND video_id = ?");
    $stmt->execute([$userId, $videoId]);
    $existingLike = $stmt->fetch();

    if ($existingLike) {
        $pdo->prepare("DELETE FROM likes WHERE id = ?")->execute([$existingLike['id']]);
        $pdo->prepare("UPDATE videos SET likes = GREATEST(likes - 1, 0) WHERE id = ?")->execute([$videoId]);
        $liked = false;
    } else {
        $pdo->prepare("INSERT INTO likes (user_id, video_id) VALUES (?, ?)")->execute([$userId, $videoId]);
        $pdo->prepare("UPDATE videos SET likes = likes + 1 WHERE id = ?")->execute([$videoId]);
        $liked = true;
    }

    $cnt = $pdo->prepare("SELECT likes FROM videos WHERE id = ?");
    $cnt->execute([$videoId]);
    $newCount = (int)$cnt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'liked' => $liked, 'likes' => $newCount]);
    exit;
}

// 觀看數 +1（每次打開 overlay 時由前端呼叫，不限登入）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_view']) && isset($_POST['video_id'])) {
    $vid = (int)$_POST['video_id'];
    if ($vid > 0) {
        try {
            $pdo->prepare("UPDATE videos SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ? AND is_active = 1")
                ->execute([$vid]);
        } catch (Exception $e) {}
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// 追蹤 / 取消追蹤
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_follow']) && $isLoggedIn) {
    $targetUser = trim($_POST['target_user'] ?? '');
    if ($targetUser && $targetUser !== $_SESSION['user']) {
        $check = $pdo->prepare("SELECT id FROM follows WHERE follower = ? AND following = ?");
        $check->execute([$_SESSION['user'], $targetUser]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM follows WHERE follower = ? AND following = ?")
                ->execute([$_SESSION['user'], $targetUser]);
            $followResult = 'unfollowed';
        } else {
            $pdo->prepare("INSERT IGNORE INTO follows (follower, following) VALUES (?, ?)")
                ->execute([$_SESSION['user'], $targetUser]);
            $followResult = 'followed';
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'action' => $followResult]);
        exit;
    }
}


// 獲取影片列表
$view = $_GET['view'] ?? 'home';
if ($view === 'admin') $view = 'home';
if ($view === 'following' && !$isLoggedIn) $view = 'home';
$activeTag = ltrim(trim($_GET['tag'] ?? ''), '#');

// 目前使用者追蹤的人（陣列，供 JS 判斷按鈕狀態）
$myFollowings = [];
if ($isLoggedIn) {
    $fStmt = $pdo->prepare("SELECT following FROM follows WHERE follower = ?");
    $fStmt->execute([$_SESSION['user']]);
    $myFollowings = $fStmt->fetchAll(PDO::FETCH_COLUMN);
}

if ($view === 'following') {
    // 追蹤中：只顯示我追蹤的人的影片
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.description, v.file_path, v.uploaded_by, v.upload_time, v.likes, v.tags,
               CASE WHEN l.user_id IS NOT NULL THEN 1 ELSE 0 END as is_liked
        FROM videos v
        LEFT JOIN likes l ON v.id = l.video_id AND l.user_id = ?
        INNER JOIN follows f ON f.following = v.uploaded_by AND f.follower = ?
        WHERE v.is_active = 1
        ORDER BY v.upload_time DESC
    ");
    $stmt->execute([$_SESSION['user'], $_SESSION['user']]);
    $videos = $stmt->fetchAll();

    // 我追蹤的人（含追蹤時間）
    $followingList = $pdo->prepare("
        SELECT f.following, f.created_at,
               (SELECT COUNT(*) FROM videos v WHERE v.uploaded_by = f.following AND v.is_active = 1) AS video_count
        FROM follows f WHERE f.follower = ? ORDER BY f.created_at DESC
    ");
    $followingList->execute([$_SESSION['user']]);
    $followingUsers = $followingList->fetchAll();

} elseif ($view === 'personal') {
    // 個人頁面：獲取用戶上傳的影片和按讚的影片
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.description, v.file_path, v.uploaded_by, v.upload_time, v.likes, v.tags,
               CASE WHEN l.user_id IS NOT NULL THEN 1 ELSE 0 END as is_liked
        FROM videos v
        LEFT JOIN likes l ON v.id = l.video_id AND l.user_id = ?
        WHERE v.is_active = 1 AND (v.uploaded_by = ? OR l.user_id = ?)
        ORDER BY v.upload_time DESC
    ");
    $stmt->execute([$_SESSION['user'], $_SESSION['user'], $_SESSION['user']]);
    $videos = $stmt->fetchAll();
} else {
    // 主頁：顯示所有影片（支援 tag 篩選）
    if ($activeTag !== '') {
        $stmt = $pdo->prepare("
            SELECT v.id, v.title, v.description, v.file_path, v.uploaded_by, v.upload_time, v.likes,
                   COALESCE(v.view_count, 0) AS view_count, v.tags,
                   CASE WHEN l.user_id IS NOT NULL THEN 1 ELSE 0 END as is_liked
            FROM videos v
            LEFT JOIN likes l ON v.id = l.video_id AND l.user_id = ?
            WHERE v.is_active = 1 AND FIND_IN_SET(?, v.tags)
            ORDER BY v.upload_time DESC
        ");
        $stmt->execute([$_SESSION['user'] ?? null, $activeTag]);
    } else {
        $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.description, v.file_path, v.uploaded_by, v.upload_time, v.likes,
               COALESCE(v.view_count, 0) AS view_count, v.tags,
               CASE WHEN l.user_id IS NOT NULL THEN 1 ELSE 0 END as is_liked
        FROM videos v
        LEFT JOIN likes l ON v.id = l.video_id AND l.user_id = ?
        WHERE v.is_active = 1
        ORDER BY v.upload_time DESC
");
        $stmt->execute([$_SESSION['user'] ?? null]);
    }
    $videos = $stmt->fetchAll();
}

// ── 推薦演算法（僅主頁、登入狀態） ──
$recommended   = [];
$recReason     = '';   // 'personalized' | 'popular' | ''
if ($isLoggedIn && $view === 'home' && $activeTag === '') {
    try {
        // 1. 使用者已按讚的影片 ID + 標籤
        $ls = $pdo->prepare("SELECT v.id, v.tags FROM likes l JOIN videos v ON l.video_id = v.id WHERE l.user_id = ?");
        $ls->execute([$_SESSION['user']]);
        $likedRows = $ls->fetchAll();
        $likedIds  = array_column($likedRows, 'id');

        $likedTags = [];
        foreach ($likedRows as $r) {
            foreach (explode(',', $r['tags'] ?? '') as $t) {
                $t = trim($t); if ($t) $likedTags[] = $t;
            }
        }
        $likedTags = array_unique($likedTags);

        // 2. 膚質困擾標籤
        $ps = $pdo->prepare("SELECT skin_concerns FROM user_profiles WHERE username = ?");
        $ps->execute([$_SESSION['user']]);
        $pr = $ps->fetch();
        $skinTags = [];
        if ($pr && $pr['skin_concerns']) {
            foreach (explode(',', $pr['skin_concerns']) as $t) {
                $t = trim($t); if ($t) $skinTags[] = $t;
            }
        }

        // 3. 候選影片（排除自己上傳 + 已按讚）
        if (!empty($likedIds)) {
            $ph  = implode(',', array_fill(0, count($likedIds), '?'));
            $cs  = $pdo->prepare("SELECT v.id, v.title, v.description, v.file_path, v.uploaded_by, v.upload_time, v.likes, v.tags, 0 AS is_liked FROM videos v WHERE v.is_active = 1 AND v.uploaded_by != ? AND v.id NOT IN ($ph)");
            $cs->execute(array_merge([$_SESSION['user']], $likedIds));
        } else {
            $cs  = $pdo->prepare("SELECT v.id, v.title, v.description, v.file_path, v.uploaded_by, v.upload_time, v.likes, v.tags, 0 AS is_liked FROM videos v WHERE v.is_active = 1 AND v.uploaded_by != ?");
            $cs->execute([$_SESSION['user']]);
        }
        $candidates = $cs->fetchAll();

        // 4. 計分
        $hasSignals = !empty($likedTags) || !empty($myFollowings) || !empty($skinTags);

        foreach ($candidates as &$v) {
            $score = 0;
            if (in_array($v['uploaded_by'], $myFollowings)) $score += 30;
            $vTags = $v['tags'] ? array_map('trim', explode(',', $v['tags'])) : [];
            foreach ($vTags as $tag) {
                if (!$tag) continue;
                if (in_array($tag, $likedTags)) $score += 10;
                if (in_array($tag, $skinTags))  $score += 5;
            }
            $score += min((int)$v['likes'], 10);
            if (strtotime($v['upload_time']) > strtotime('-7 days')) $score += 5;
            $v['rec_score'] = $score;
        }
        unset($v);

        if ($hasSignals) {
            usort($candidates, fn($a, $b) => $b['rec_score'] <=> $a['rec_score']);
            $recommended = array_slice($candidates, 0, 6);
            $recReason   = 'personalized';
        } else {
            // 無互動紀錄 → 熱門推薦
            usort($candidates, fn($a, $b) => $b['likes'] <=> $a['likes']);
            $recommended = array_slice($candidates, 0, 6);
            $recReason   = 'popular';
        }
    } catch (Exception $e) {
        $recommended = [];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COSMETIC — 影片交流</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ─── Layout ─── */
        .video-page { min-height: 100vh; background: #f4f3f8; padding-bottom: 80px; }
        .video-wrapper { max-width: 1160px; margin: 0 auto; padding: 0 24px; }

        /* ─── Recommendation Section ─── */
        .rec-section { margin: 20px 0 0; }
        .rec-header { display: flex; align-items: baseline; gap: 10px; margin-bottom: 14px; }
        .rec-title { font-size: 16px; font-weight: 700; color: #1a1a2e; }
        .rec-sub   { font-size: 12px; color: #aaa; }
        .rec-grid  { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        @media(max-width:900px){ .rec-grid { grid-template-columns: repeat(2,1fr); } }
        @media(max-width:580px){ .rec-grid { grid-template-columns: 1fr; } }
        .rec-card  { background: #fff; border-radius: 12px; border: 1px solid #e8e6f0;
                     overflow: hidden; cursor: pointer; transition: all .18s; display: flex; flex-direction: column; }
        .rec-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.09); }
        .rec-thumb { position: relative; aspect-ratio: 16/9; background: linear-gradient(135deg,#3d1520,#c26b7c);
                     overflow: hidden; }
        .rec-thumb video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .rec-info  { padding: 10px 12px 12px; flex: 1; }
        .rec-card-title { font-size: 13px; font-weight: 600; line-height: 1.4; margin-bottom: 5px;
                          display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .rec-card-meta  { font-size: 11px; color: #aaa; display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
        .rec-tag { color: #c26b7c; font-size: 11px; }
        .rec-divider { display: flex; align-items: center; gap: 12px; margin: 24px 0 16px; }
        .rec-divider::before, .rec-divider::after { content: ''; flex: 1; height: 1px; background: #e8e6f0; }
        .rec-divider span { font-size: 12px; color: #aaa; font-weight: 600; white-space: nowrap; }

        /* ─── Page Header ─── */
        .vp-header { display: flex; align-items: center; gap: 16px; padding: 32px 0 0; }
        .vp-header-left { flex: 1; }
        .page-title { font-size: 26px; font-weight: 800; letter-spacing: -0.5px; color: #1a1a2e; }
        .page-title-sub { font-size: 13px; color: #888; margin-top: 5px; }
        .btn-upload-header {
            display: inline-flex; align-items: center; gap: 7px;
            height: 40px; padding: 0 18px;
            background: #c26b7c; color: #fff; border: none;
            border-radius: 10px; font-size: 13px; font-weight: 700;
            cursor: pointer; text-decoration: none; white-space: nowrap;
            transition: background .18s;
        }
        .btn-upload-header:hover { background: #b05c6c; }

        /* ─── Tab Bar ─── */
        .nav-tabs { display: flex; gap: 0; border-bottom: 2px solid #e8e6f0; margin: 22px 0 0; }
        .nav-tab {
            padding: 11px 22px; color: #888; font-size: 14px; font-weight: 600;
            text-decoration: none; border-bottom: 2px solid transparent;
            margin-bottom: -2px; transition: all .18s; white-space: nowrap;
        }
        .nav-tab:hover { color: #c26b7c; }
        .nav-tab.active { color: #c26b7c; border-bottom-color: #c26b7c; }

        /* ─── Filter / Sort ─── */
        .filter-area { padding: 20px 0 4px; display: flex; flex-direction: column; gap: 12px; }
        .search-row { display: flex; gap: 10px; align-items: center; }
        .search-wrap { position: relative; flex: 1; max-width: 380px; }
        .search-wrap::before { content: '🔍'; position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 13px; pointer-events: none; }
        .search-input {
            width: 100%; height: 38px; padding: 0 14px 0 36px;
            border: 1.5px solid #e8e6f0; border-radius: 10px;
            font-size: 13px; outline: none; background: #fff; color: #1a1a2e;
            transition: border .18s;
        }
        .search-input:focus { border-color: #c26b7c; }
        .filter-tags { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .filter-label { font-size: 11px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: #888; margin-right: 2px; }
        .f-chip {
            display: inline-flex; align-items: center; height: 28px; padding: 0 12px;
            border-radius: 99px; border: 1.5px solid #e8e6f0;
            background: #fff; font-size: 12px; font-weight: 600;
            color: #555; cursor: pointer; transition: all .18s; text-decoration: none; white-space: nowrap;
        }
        .f-chip:hover { border-color: #c26b7c; color: #c26b7c; }
        .f-chip.active { background: #c26b7c; color: #fff; border-color: #c26b7c; }
        .sort-row { display: flex; gap: 6px; align-items: center; margin-bottom: 20px; margin-top: 4px; }
        .sort-btn {
            height: 28px; padding: 0 12px; border-radius: 8px;
            border: 1.5px solid #e8e6f0; background: #fff;
            font-size: 12px; color: #555; cursor: pointer; font-family: inherit;
            font-weight: 500; transition: all .18s; text-decoration: none;
            display: inline-flex; align-items: center;
        }
        .sort-btn:hover { border-color: #c26b7c; color: #c26b7c; }
        .sort-btn.active { background: #fce7ec; color: #9d2942; border-color: #fce7ec; font-weight: 700; }
        .sort-label { font-size: 11px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: #888; margin-right: 2px; }

        .tag-filter-bar {
            display: flex; align-items: center; gap: 10px; margin-bottom: 16px;
            padding: 10px 14px; background: #fce7ec; border: 1px solid #f9cfd8;
            border-radius: 12px; font-size: 13px; color: #9d2942; font-weight: 600;
        }
        .tag-filter-bar a { margin-left: auto; color: #c26b7c; font-size: 12px; text-decoration: none; padding: 3px 10px; border-radius: 99px; border: 1px solid #c26b7c; }
        .tag-filter-bar a:hover { background: #c26b7c; color: white; }

        /* ─── Video Grid ─── */
        .video-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; }
        @media(max-width:900px){ .video-grid { grid-template-columns: repeat(2,1fr); } }
        @media(max-width:580px){ .video-grid { grid-template-columns: 1fr; } }

        /* ─── Video Card ─── */
        .video-card {
            background: #fff; border-radius: 14px; overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,.06); border: 1px solid #e8e6f0;
            transition: all .18s; cursor: pointer; display: flex; flex-direction: column;
        }
        .video-card:hover { transform: translateY(-4px); box-shadow: 0 10px 32px rgba(0,0,0,.13); }

        /* 16:9 thumbnail */
        .video-player {
            position: relative; padding-top: 56.25%;
            overflow: hidden; background: #1c1c1e;
        }
        .video-player video {
            position: absolute; top: 0; left: 0;
            width: 100%; height: 100%; object-fit: cover; pointer-events: none;
        }
        .play-icon {
            position: absolute; inset: 0; background: rgba(0,0,0,0);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity .18s, background .18s;
        }
        .video-card:hover .play-icon { opacity: 1; background: rgba(0,0,0,.28); }
        .play-icon::after {
            content: '▶'; color: white; font-size: 20px;
            width: 50px; height: 50px; background: rgba(194,107,124,.9);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            padding-left: 4px; box-shadow: 0 4px 18px rgba(0,0,0,.3);
        }
        .video-like-btn {
            position: absolute; bottom: 9px; left: 10px;
            background: rgba(0,0,0,.55); border: none; border-radius: 20px;
            padding: 4px 10px; font-size: 12px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; gap: 4px;
            transition: background .18s; z-index: 10; color: #fff;
        }
        .video-like-btn:hover, .video-like-btn.liked { background: rgba(194,107,124,.88); }

        /* Card body */
        .vc-info { padding: 13px 15px 15px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .vc-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 2px; }
        .vc-tag {
            font-size: 11px; font-weight: 600; color: #c26b7c;
            background: #fce7ec; border-radius: 99px; padding: 2px 9px;
            text-decoration: none; cursor: pointer; transition: all .15s;
            border: 1px solid #f9cfd8;
        }
        .vc-tag:hover, .vc-tag.active { background: #c26b7c; color: #fff; border-color: #c26b7c; }
        .vc-title {
            font-size: 14px; font-weight: 700; color: #1a1a2e; line-height: 1.45;
            display: -webkit-box; -webkit-line-clamp: 2;
            -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 5px;
        }
        .vc-footer { display: flex; align-items: center; gap: 8px; margin-top: auto; }
        .vc-avatar {
            width: 26px; height: 26px; border-radius: 50%;
            background: #fce7ec; color: #9d2942;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 800; flex-shrink: 0;
        }
        .vc-author { font-size: 12px; color: #555; font-weight: 600; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .vc-likes { font-size: 11px; color: #888; flex-shrink: 0; }

        /* Messages */
        .message { padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Empty State */
        .empty-state { grid-column: 1/-1; text-align: center; padding: 80px 20px; color: #888; }
        .empty-icon { font-size: 48px; margin-bottom: 14px; }
        .empty-text { font-size: 16px; font-weight: 700; color: #555; margin-bottom: 6px; }

        /* ─── Personal Tab ─── */
        .upload-section {
            background: #fff; padding: 24px 28px; border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06); border: 1px solid #e8e6f0;
            margin-bottom: 28px;
        }
        .upload-section h2 { font-size: 16px; font-weight: 700; margin-bottom: 16px; color: #1a1a2e; }
        .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px; }
        .content-grid .upload-section { grid-column: 1/-1; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 12px; font-weight: 700; color: #888; letter-spacing: .3px; }
        .form-group input[type="text"],
        .form-group textarea {
            padding: 9px 12px; border: 1.5px solid #e8e6f0; border-radius: 10px;
            font-size: 13px; outline: none; transition: border .18s; background: #f4f3f8;
            width: 100%;
        }
        .form-group input[type="text"]:focus,
        .form-group textarea:focus { border-color: #c26b7c; background: #fff; }
        .form-group textarea { height: 80px; resize: vertical; }
        .form-group input[type="file"] { padding: 10px; border: 1.5px solid #e8e6f0; border-radius: 10px; background: #f4f3f8; }
        .upload-btn {
            background: #c26b7c; color: white; padding: 11px 30px; border: none;
            border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 700;
            width: 100%; transition: background .18s;
        }
        .upload-btn:hover { background: #b05c6c; }
        .video-info { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
        .video-title { font-size: 14px; font-weight: bold; margin-bottom: 8px; color: #333; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .video-description { color: #999; margin-bottom: 10px; font-size: 13px; line-height: 1.4; }
        .video-meta { display: flex; align-items: center; margin-bottom: 12px; font-size: 12px; }
        .author-info { display: flex; align-items: center; flex: 1; }
        .author-avatar { width: 24px; height: 24px; border-radius: 50%; background: rgba(194,107,124,.7); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 11px; margin-right: 8px; }
        .author-details { display: flex; flex-direction: column; }
        .author-name { color: #333; font-weight: 600; font-size: 12px; }
        .upload-time { color: #ccc; font-size: 11px; }
        .video-actions { display: flex; gap: 8px; margin-top: auto; }
        .delete-btn { flex: 1; background: #dc3545; color: white; border: none; padding: 8px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; transition: background .2s; }
        .delete-btn:hover { background: #c82333; }
        .edit-btn { flex: 1; background: #c26b7c; color: white; border: none; padding: 8px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; transition: background .2s; }
        .edit-btn:hover { background: #9d2942; }
        /* Edit Modal */
        .edit-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 2000; align-items: center; justify-content: center; }
        .edit-modal-overlay.active { display: flex; }
        .edit-modal { background: #fff; border-radius: 18px; padding: 32px; width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
        .edit-modal h3 { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 20px; }
        .edit-modal .form-group { margin-bottom: 16px; }
        .edit-modal label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .edit-modal input[type=text], .edit-modal textarea { width: 100%; padding: 10px 13px; border: 1.5px solid #e8e6f0; border-radius: 10px; font-size: 14px; font-family: inherit; color: #333; outline: none; transition: border-color .2s; box-sizing: border-box; }
        .edit-modal input[type=text]:focus, .edit-modal textarea:focus { border-color: #c26b7c; }
        .edit-modal textarea { resize: vertical; min-height: 90px; }
        .edit-modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .edit-save-btn { flex: 1; background: #c26b7c; color: #fff; border: none; padding: 11px; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; transition: background .2s; }
        .edit-save-btn:hover { background: #9d2942; }
        .edit-cancel-btn { flex: 1; background: #f4f3f8; color: #666; border: none; padding: 11px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background .2s; }
        .edit-cancel-btn:hover { background: #e8e6f0; }
        .edit-modal-close { position: absolute; top: 14px; right: 16px; background: none; border: none; font-size: 22px; color: #aaa; cursor: pointer; line-height: 1; }
        .edit-modal-close:hover { color: #c26b7c; }
        /* Hashtag chip input */
        .hashtag-input-box { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; min-height: 42px; padding: 7px 12px; border: 1.5px solid #e8e6f0; border-radius: 10px; background: white; cursor: text; transition: border-color .2s; }
        .hashtag-input-box:focus-within { border-color: #c26b7c; }
        .hashtag-chip { display: inline-flex; align-items: center; gap: 4px; background: #fce7ec; color: #c26b7c; border: 1px solid #f9cfd8; border-radius: 99px; padding: 2px 10px 2px 8px; font-size: 13px; font-weight: 600; }
        .hashtag-chip-remove { cursor: pointer; font-size: 14px; line-height: 1; color: #c26b7c; opacity: 0.6; margin-left: 2px; }
        .hashtag-chip-remove:hover { opacity: 1; }
        .hashtag-typing { border: none; outline: none; font-size: 13px; min-width: 120px; flex: 1; color: #333; background: transparent; }
        .preset-tag-btn { background: white; border: 1.5px solid #f9cfd8; color: #c26b7c; border-radius: 99px; padding: 4px 12px; font-size: 12px; font-weight: 600; cursor: pointer; transition: background .15s, color .15s; }
        .preset-tag-btn:hover { background: #fce7ec; }
        .preset-tag-btn.selected { background: #c26b7c; color: white; border-color: #c26b7c; }
        .suggestion-chip { display: inline-block; padding: 3px 11px; border-radius: 99px; background: #fce7ec; color: #c26b7c; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #f9cfd8; }
        .suggestion-chip:hover { background: #c26b7c; color: white; }

        /* ─── Video Detail Overlay ─── */
        .video-detail-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,.95); z-index: 1000; overflow-y: auto; }
        .video-detail-overlay.active { display: block; }
        .video-detail-content { max-width: 1400px; margin: 0 auto; padding: 20px; display: flex; gap: 30px; align-items: flex-start; }
        .video-detail-close { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,.2); border: none; color: white; font-size: 30px; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; z-index: 1001; }
        .video-detail-player { width: 65%; max-height: 70vh; background: #000; display: flex; justify-content: center; align-items: center; flex-shrink: 0; }
        .video-detail-player video { width: 100%; max-height: 70vh; display: block; }
        .video-detail-info {
            padding: 0;
            color: white;
            flex: 1;
            overflow-y: auto;
            max-height: 70vh;
        }
        .video-detail-title { font-size: 20px; font-weight: bold; margin-bottom: 12px; }
        .video-detail-meta { display: flex; align-items: center; gap: 15px; margin-bottom: 12px; font-size: 13px; color: #ccc; }
        .video-detail-author { display: flex; align-items: center; gap: 10px; }
        .video-detail-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg,#ff5a7e 0%,#ff3a6f 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 18px; }
        .video-detail-description { color: #aaa; line-height: 1.5; margin-top: 0; font-size: 13px; }
        .comments-section { margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,.1); }
        .comments-header { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 10px; }
        .comment-form { display: flex; gap: 8px; margin-bottom: 12px; }
        .comment-input { flex: 1; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); border-radius: 8px; padding: 8px 10px; color: #fff; font-size: 12px; outline: none; }
        .comment-input::placeholder { color: rgba(255,255,255,.5); }
        .comment-input:focus { border-color: rgba(255,90,126,.5); background: rgba(255,255,255,.12); }
        .comment-submit-btn { background: #ff5a7e; color: white; border: none; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .comment-submit-btn:hover { background: #ff3a6f; }
        .comments-list { max-height: 300px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
        .comment-item { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); border-radius: 8px; padding: 10px; }
        .comment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .comment-author { font-size: 12px; font-weight: 600; color: #ff5a7e; }
        .comment-time { font-size: 11px; color: rgba(255,255,255,.4); }
        .comment-content { font-size: 12px; color: #ddd; margin-bottom: 8px; line-height: 1.4; word-break: break-word; }
        .comment-actions { display: flex; gap: 8px; align-items: center; }
        .comment-like-btn { background: none; border: none; color: rgba(255,255,255,.6); cursor: pointer; font-size: 11px; display: flex; align-items: center; gap: 3px; transition: all .2s; }
        .comment-like-btn:hover, .comment-like-btn.liked { color: #ff5a7e; }
        .comment-reply-btn { background: none; border: none; color: rgba(255,255,255,.6); cursor: pointer; font-size: 11px; transition: all .2s; }
        .comment-reply-btn:hover { color: #ff5a7e; }
        .comment-delete-btn { background: none; border: none; color: rgba(220,53,69,.8); cursor: pointer; font-size: 11px; transition: all .2s; }
        .comment-delete-btn:hover { color: #dc3545; }
        .comment-report-btn { background: none; border: none; color: rgba(150,100,0,.7); cursor: pointer; font-size: 11px; transition: all .2s; }
        .comment-report-btn:hover { color: #856404; }
        .comment-report-form { margin-top: 8px; background: #fff8e8; border: 1px solid #ffc; border-radius: 8px; padding: 10px 12px; display: none; flex-direction: column; gap: 7px; }
        .comment-report-form select, .comment-report-form textarea { width: 100%; border: 1px solid #ddd; border-radius: 6px; padding: 6px 10px; font-size: 12px; background: #fff; }
        .comment-report-form textarea { resize: none; height: 54px; }
        .comment-report-actions { display: flex; gap: 6px; }
        .comment-report-actions button { padding: 5px 12px; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; }
        .comment-report-submit { background: #e83e5a; color: #fff; }
        .comment-report-cancel { background: #e0e0e0; color: #555; }
        .replies { margin-top: 8px; padding-left: 12px; border-left: 2px solid rgba(255,90,126,.3); display: flex; flex-direction: column; gap: 8px; }
        .reply-item { background: rgba(255,255,255,.03); border-radius: 6px; padding: 8px; font-size: 11px; }
        .reply-form { display: flex; gap: 6px; margin-top: 8px; }
        .reply-input { flex: 1; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15); border-radius: 6px; padding: 6px 8px; color: #fff; font-size: 11px; outline: none; }
        .reply-input:focus { border-color: rgba(255,90,126,.4); }
        .reply-submit-btn { background: #ff5a7e; color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer; font-size: 11px; white-space: nowrap; }
        .reply-submit-btn:hover { background: #ff3a6f; }
        .empty-comments { text-align: center; color: rgba(255,255,255,.4); font-size: 12px; padding: 20px 0; }

        .video-detail-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            margin-bottom: 12px;
        }
        .video-detail-like-btn, .report-trigger-btn { border: none; border-radius: 18px; padding: 8px 16px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all .2s; }
        .video-detail-like-btn { background: #ff5a7e; color: white; }
        .video-detail-like-btn:hover { opacity: .9; transform: translateY(-1px); }
        .video-detail-like-btn.liked { background: #d6336c; }
        .report-trigger-btn { background: rgba(255,255,255,.15); color: #ff5a7e; }
        .report-trigger-btn:hover { background: rgba(255,255,255,.3); }
        .share-btn { background: rgba(255,255,255,.15); color: white; border: none; border-radius: 18px; padding: 8px 16px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all .2s; }
        .share-btn:hover { background: rgba(255,255,255,.28); transform: translateY(-1px); }
        .share-toast { display: none; position: fixed; bottom: 36px; left: 50%; transform: translateX(-50%); background: rgba(30,30,30,.92); color: #fff; padding: 10px 22px; border-radius: 24px; font-size: 14px; z-index: 2000; pointer-events: none; white-space: nowrap; }
        .share-toast.show { display: block; animation: fadeInOut 2s ease forwards; }
        @keyframes fadeInOut { 0%{opacity:0;transform:translateX(-50%) translateY(8px)} 15%{opacity:1;transform:translateX(-50%) translateY(0)} 75%{opacity:1} 100%{opacity:0} }
        .report-form { display: none; flex-direction: column; gap: 10px; padding: 12px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18); border-radius: 12px; margin-bottom: 12px; }
        .report-form.active { display: flex; }
        .report-form label { display: flex; align-items: center; gap: 8px; cursor: pointer; color: #fff; font-size: 13px; }
        .report-form textarea { width: 100%; min-height: 60px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); border-radius: 8px; padding: 8px; color: #fff; resize: vertical; font-size: 12px; }
        .report-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .report-submit-btn, .report-cancel-btn { border: none; border-radius: 20px; padding: 10px 18px; font-size: 14px; cursor: pointer; }
        .report-submit-btn { background: #ff5a7e; color: #fff; }
        .report-cancel-btn { background: rgba(255,255,255,.2); color: #fff; }
        .report-message { padding: 10px 12px; border-radius: 8px; font-size: 12px; display: none; }
        .report-message.success { display: block; background: rgba(72,187,120,.15); color: #d4f8dc; border: 1px solid rgba(72,187,120,.35); }
        .report-message.error { display: block; background: rgba(220,53,69,.15); color: #ffd5dc; border: 1px solid rgba(220,53,69,.35); }

        /* Following tab */
        .following-user-card { display: flex; align-items: center; gap: 14px; background: #fff; border-radius: 14px; padding: 14px 18px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 10px; border: 1px solid #e8e6f0; }
        .following-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg,#c26b7c,#9d2942); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 18px; flex-shrink: 0; }
        .follow-btn { margin-left: auto; padding: 6px 16px; border-radius: 20px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s; }
        .follow-btn.following { background: #fce7ec; color: #9d2942; }
        .follow-btn.following:hover { background: #c26b7c; color: #fff; }
        .follow-btn.not-following { background: #c26b7c; color: #fff; }
        .follow-btn.not-following:hover { background: #9d2942; }

        /* Admin no-action tooltip */
        .admin-no-action {
            cursor: not-allowed !important;
            opacity: 0.65;
        }
        /* JS-driven tooltip, appended to body to avoid overflow clipping */
        #adminTooltip {
            display: none;
            position: fixed;
            background: rgba(30,10,15,.88);
            color: #fff;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            pointer-events: none;
            z-index: 99999;
            transition: opacity .15s;
        }

        /* Admin report table */
        .report-mgr-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .report-mgr-table th, .report-mgr-table td { padding: 12px 14px; border: 1px solid #f1d1dc; text-align: left; vertical-align: top; }
        .report-mgr-table th { background: #ffe3eb; color: #9c2132; }
        .report-mgr-table tbody tr:nth-child(odd) { background: #fff7f9; }
        .rpt-tag { display: inline-block; background: #ffd6de; color: #9c2132; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin: 2px; }
        .btn-force-del { background: #c82333; color: #fff; border: none; border-radius: 6px; padding: 7px 12px; cursor: pointer; font-size: 12px; }
        .btn-dismiss { background: #6c757d; color: #fff; border: none; border-radius: 6px; padding: 7px 12px; cursor: pointer; font-size: 12px; margin-left: 6px; }
        .btn-preview { background: #0069d9; color: #fff; border: none; border-radius: 6px; padding: 7px 12px; cursor: pointer; font-size: 12px; margin-left: 6px; }
        .rpt-preview-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.8); z-index: 3000; justify-content: center; align-items: center; }
        .rpt-preview-overlay.open { display: flex; }
        .rpt-preview-box { background: #111; border-radius: 12px; padding: 20px; max-width: 720px; width: 90%; position: relative; }
        .rpt-preview-box video { width: 100%; border-radius: 8px; max-height: 70vh; }
        .rpt-preview-title { color: #fff; font-size: 15px; font-weight: 600; margin-bottom: 12px; }
        .rpt-preview-close { position: absolute; top: 12px; right: 14px; background: none; border: none; color: #aaa; font-size: 22px; cursor: pointer; }
        .rpt-preview-close:hover { color: #fff; }

        @media(max-width:560px){ .content-grid { grid-template-columns: 1fr; } }

        /* ── Mobile tweaks ── */
        @media(max-width:640px){
          .vp-header { flex-direction: column; align-items: flex-start; gap: 10px; padding: 20px 0 0; }
          .page-title { font-size: 20px; }
          .btn-upload-header { width: 100%; justify-content: center; }
          .nav-tabs { overflow-x: auto; -webkit-overflow-scrolling: touch; }
          .nav-tab { padding: 10px 14px; font-size: 13px; white-space: nowrap; }
          .form-grid { grid-template-columns: 1fr; }
          .video-wrapper { padding: 0 16px; }
          .search-row { flex-direction: column; align-items: stretch; }
          .search-wrap { max-width: 100%; }
          .filter-tags { overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch; padding-bottom: 4px; }
          .filter-label { display: none; }
          .video-detail-content { flex-direction: column; padding: 60px 12px 20px; }
          .video-detail-player { width: 100%; flex-shrink: 0; }
          .video-detail-info { max-height: none; }
        }
        @media(max-width:480px){
          .rec-grid { grid-template-columns: 1fr; }
          .video-grid { grid-template-columns: 1fr; }
          .upload-section { padding: 18px 16px; }
          .sort-row { flex-wrap: wrap; gap: 6px; }
        }

        /* ─── 社群規範彈窗 ─── */
        .guideline-overlay {
            display: none; position: fixed; inset: 0; z-index: 2500;
            background: rgba(30,10,15,.55); -webkit-backdrop-filter: blur(2px); backdrop-filter: blur(2px);
            align-items: center; justify-content: center; padding: 20px;
        }
        .guideline-overlay.active { display: flex; }
        .guideline-modal {
            background: #fff; border-radius: 18px; width: 100%; max-width: 540px;
            max-height: 88vh; display: flex; flex-direction: column; overflow: hidden;
            box-shadow: 0 24px 70px rgba(0,0,0,.32); animation: glPop .25s ease;
        }
        @keyframes glPop { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: none; } }
        .guideline-modal-head {
            display: flex; align-items: center; gap: 8px;
            padding: 18px 22px; font-size: 16px; font-weight: 800; color: #9d2942;
            background: linear-gradient(135deg, #fdf2f4, #fce7ec); border-bottom: 1px solid #f5c6d0;
        }
        .guideline-modal-body { padding: 18px 22px; overflow-y: auto; }
        .guideline-modal-foot { padding: 14px 22px 20px; border-top: 1px solid #f1e3e7; }
        .guideline-confirm-btn {
            width: 100%; background: #c26b7c; color: #fff; border: none;
            border-radius: 10px; padding: 13px; font-size: 14px; font-weight: 700;
            cursor: pointer; transition: background .18s;
        }
        .guideline-confirm-btn:hover { background: #9d2942; }
        /* 重看規範的小按鈕 */
        .guideline-reopen-btn {
            display: inline-flex; align-items: center; gap: 5px;
            height: 32px; padding: 0 12px; border-radius: 99px;
            background: #fff; border: 1.5px solid #f5c6d0; color: #9d2942;
            font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap;
            transition: all .18s;
        }
        .guideline-reopen-btn:hover { background: #fce7ec; border-color: #c26b7c; }
        .guideline-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
        .guideline-list li {
            display: flex; gap: 10px; font-size: 13px; color: #555; line-height: 1.6;
            background: rgba(255,255,255,.65); border-radius: 10px; padding: 10px 12px;
        }
        .guideline-list .gl-num {
            flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%;
            background: #c26b7c; color: #fff; font-size: 12px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .guideline-list b { color: #9d2942; }
        .guideline-tag-hint {
            display: inline-block; background: #c26b7c; color: #fff;
            font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 10px; margin: 0 2px;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../header.php'; ?>

<main class="video-page">
    <?php if (!$isLoggedIn): ?>
    <div style="max-width: 1400px; margin: 100px auto; text-align: center; padding: 40px;">
        <h1 style="font-size: 32px; margin-bottom: 20px; color: #333;">✨ 影片交流</h1>
        <p style="font-size: 18px; color: #666; margin-bottom: 30px;">需要登入才能查看和上傳影片</p>
        <a href="login.php" style="display: inline-block; background: linear-gradient(135deg, #ff5a7e 0%, #ff3a6f 100%); color: white; padding: 12px 40px; border-radius: 8px; text-decoration: none; font-size: 16px; font-weight: 600; cursor: pointer;">📱 前往登入</a>
    </div>
    <?php else: ?>
    <div class="video-wrapper">
        <div class="vp-header">
            <div class="vp-header-left">
                <h1 class="page-title">影片交流</h1>
                <div class="page-title-sub">探索彩妝技巧・分享你的精彩</div>
            </div>
            <button type="button" class="guideline-reopen-btn" onclick="openGuideline()">📢 社群規範</button>
            <?php if (!$isAdmin): ?>
            <a href="?view=personal" class="btn-upload-header">＋ 上傳影片</a>
            <?php endif; ?>
        </div>

        <!-- 社群規範彈窗 -->
        <div class="guideline-overlay" id="guidelineOverlay">
            <div class="guideline-modal">
                <div class="guideline-modal-head">
                    <span>📢</span>
                    <span>影片交流社群規範</span>
                </div>
                <div class="guideline-modal-body">
                    <ul class="guideline-list">
                        <li><span class="gl-num">1</span><span><b>留言禮儀：</b>留言不得包含辱罵、歧視、人身攻擊等不當言詞。</span></li>
                        <li><span class="gl-num">2</span><span><b>業配標示：</b>發布業配內容請務必加上 <span class="guideline-tag-hint">#業配</span> 標籤。若經其他使用者檢舉且查證屬實，管理者將刪除該影片；<b>一個月內違規三次，帳號將永久停權</b>。</span></li>
                        <li><span class="gl-num">3</span><span><b>內容相關性：</b>發布內容須與美妝相關，否則管理者有權直接刪除。</span></li>
                        <li><span class="gl-num">4</span><span><b>尊重原創：</b>請勿盜用、未經授權轉載他人影片，違者將下架處理。</span></li>
                        <li><span class="gl-num">5</span><span><b>隱私保護：</b>禁止張貼廣告連結、垃圾訊息，或洩漏他人個人資料。</span></li>
                    </ul>
                </div>
                <div class="guideline-modal-foot">
                    <button type="button" class="guideline-confirm-btn" onclick="acceptGuideline()">我已閱讀並同意</button>
                </div>
            </div>
        </div>

        <div class="nav-tabs">
            <a href="?view=home" class="nav-tab <?php echo ($view === 'home') ? 'active' : ''; ?>">🏠 主頁</a>
            <?php if ($isLoggedIn): ?>
            <a href="?view=following" class="nav-tab <?php echo ($view === 'following') ? 'active' : ''; ?>" style="position:relative;">
                📡 追蹤中
                <?php
                $followingCount = count($myFollowings);
                if ($followingCount > 0): ?>
                    <span style="position:absolute;top:8px;right:2px;background:#c26b7c;color:#fff;font-size:10px;font-weight:700;border-radius:10px;padding:1px 5px;"><?php echo $followingCount; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <a href="?view=personal" class="nav-tab <?php echo ($view === 'personal') ? 'active' : ''; ?>">👤 個人</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($view === 'personal'): ?>
        <?php if (!$isAdmin): ?>
        <div class="content-grid">
            <div class="upload-section">
                <h2>📹 分享你的精彩時刻</h2>
                <form method="post" enctype="multipart/form-data" id="uploadForm" onsubmit="return submitUpload(event)">
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
                            <label>標籤</label>

                            <!-- 建議標籤 -->
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                                <?php
                                $presetTags = ['唇妝','眼妝','底妝','腮紅','修容','眉毛','日系','教學','彩妝','彩妝品','穿搭','日常通勤','歐美立體','韓系清透','約會精緻','霧面','奶油肌','水光感','自然裸妝','業配'];
                                foreach ($presetTags as $pt): ?>
                                <button type="button" class="preset-tag-btn" data-tag="<?= htmlspecialchars($pt) ?>">#<?= htmlspecialchars($pt) ?></button>
                                <?php endforeach; ?>
                            </div>

                            <!-- 已選 + 自訂輸入 -->
                            <div class="hashtag-input-box" id="hashtagInputBox" onclick="document.getElementById('hashtagTyping').focus()">
                                <input type="text" id="hashtagTyping" class="hashtag-typing" placeholder="輸入標籤，按 Enter 確認" autocomplete="off">
                            </div>

                            <!-- 即時建議下拉 -->
                            <div style="position:relative;">
                                <div id="tagSuggestionList" style="display:none;position:absolute;top:4px;left:0;right:0;background:white;border:1.5px solid #ffd6de;border-radius:12px;padding:8px 10px;flex-wrap:wrap;gap:6px;box-shadow:0 4px 16px rgba(232,62,90,.12);z-index:200;"></div>
                            </div>

                            <input type="hidden" name="tags" id="tagsHidden">
                            <small style="color: #999; margin-top: 5px; display: block;">點選建議標籤或輸入自訂標籤（按 Enter）</small>
                        </div>

                        <div class="form-group">
                            <label for="video">選擇影片檔案 *</label>
                            <input type="file" id="video" name="video" accept="video/*" required>
                            <small style="color: #999; margin-top: 5px; display: block;">支援 MP4、AVI、MOV 等格式，最大 40MB</small>
                        </div>

                        <button type="submit" class="upload-btn" id="uploadBtn">🚀 上傳影片</button>
                        <div id="uploadStatus" style="display:none;margin-top:12px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;text-align:center;"></div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; /* end !$isAdmin upload section */ ?>
        <?php endif; /* end $view === 'personal' upload block */ ?>

        <?php if ($view === 'personal'): ?>
            <?php if ($isAdmin): ?>
            <div style="text-align:center;padding:80px 20px;">
                <div style="font-size:3rem;margin-bottom:16px;">🚫</div>
                <div style="font-size:20px;font-weight:700;color:#9d2942;margin-bottom:10px;">管理員無法使用此功能</div>
                <p style="color:#888;font-size:14px;">個人頁面僅供一般使用者使用</p>
            </div>
            <?php else: ?>
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
                        <div class="video-card"
                             data-video-id="<?php echo (int)$video['id']; ?>"
                             data-title="<?php echo htmlspecialchars($video['title']); ?>"
                             data-author="<?php echo htmlspecialchars($video['uploaded_by']); ?>"
                             data-time="<?php echo date('Y年m月d日', strtotime($video['upload_time'])); ?>"
                             data-likes="<?php echo (int)$video['likes']; ?>"
                             data-is-liked="<?php echo $video['is_liked'] ? 1 : 0; ?>"
                             data-view-count="<?php echo (int)($video['view_count'] ?? 0); ?>"
                             data-description="<?php echo htmlspecialchars($video['description'] ?? ''); ?>"
                             data-file-path="<?php echo htmlspecialchars($video['file_path']); ?>">
                            <div class="video-player">
                                <video controls>
                                    <source src="<?php echo htmlspecialchars($video['file_path']); ?>" type="video/mp4">
                                    您的瀏覽器不支援影片播放。
                                </video>
                                <div class="play-icon"></div>
                                <button class="video-like-btn <?php echo $video['is_liked'] ? 'liked' : ''; ?><?php echo $isAdmin ? ' admin-no-action' : ''; ?>"
                                        onclick="<?php echo $isAdmin ? 'return false;' : 'toggleLikeCard(' . (int)$video['id'] . ', this)'; ?>">
                                    <span class="like-icon"><?php echo $video['is_liked'] ? '❤️' : '🤍'; ?></span>
                                    <span class="like-count"><?php echo (int)$video['likes']; ?></span>
                                </button>
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
                                    <button class="edit-btn" onclick="openEditModal(<?php echo (int)$video['id']; ?>, <?php echo htmlspecialchars(json_encode($video['title']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($video['description'] ?? ''), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($video['tags'] ?? ''), ENT_QUOTES); ?>)">✏️ 編輯</button>
                                    <button class="delete-btn" onclick="deleteVideoAjax(event, <?php echo (int)$video['id']; ?>, this)">🗑️ 刪除</button>
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
                        <div class="video-card"
                             data-video-id="<?php echo (int)$video['id']; ?>"
                             data-title="<?php echo htmlspecialchars($video['title']); ?>"
                             data-author="<?php echo htmlspecialchars($video['uploaded_by']); ?>"
                             data-time="<?php echo date('Y年m月d日', strtotime($video['upload_time'])); ?>"
                             data-likes="<?php echo (int)$video['likes']; ?>"
                             data-is-liked="<?php echo $video['is_liked'] ? 1 : 0; ?>"
                             data-view-count="<?php echo (int)($video['view_count'] ?? 0); ?>"
                             data-description="<?php echo htmlspecialchars($video['description'] ?? ''); ?>"
                             data-file-path="<?php echo htmlspecialchars($video['file_path']); ?>">
                            <div class="video-player">
                                <video controls>
                                    <source src="<?php echo htmlspecialchars($video['file_path']); ?>" type="video/mp4">
                                    您的瀏覽器不支援影片播放。
                                </video>
                                <div class="play-icon"></div>
                                <button class="video-like-btn liked" onclick="toggleLikeCard(<?php echo (int)$video['id']; ?>, this)">
                                    <span class="like-icon">❤️</span>
                                    <span class="like-count"><?php echo (int)$video['likes']; ?></span>
                                </button>
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
            <?php endif; /* end !$isAdmin personal content */ ?>
        <?php elseif ($view === 'following'): ?>
        <!-- ── 追蹤中 ── -->
        <?php if ($isAdmin): ?>
        <div style="text-align:center;padding:80px 20px;">
            <div style="font-size:3rem;margin-bottom:16px;">🚫</div>
            <div style="font-size:20px;font-weight:700;color:#9d2942;margin-bottom:10px;">管理員無法使用此功能</div>
            <p style="color:#888;font-size:14px;">追蹤功能僅供一般使用者使用</p>
        </div>
        <?php else: ?>

        <!-- 追蹤的人列表 -->
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
            <h2 style="font-size:18px;font-weight:700;color:#1c1c1e;margin:0;">👥 我追蹤的人</h2>
            <span style="font-size:13px;color:#8e8e93;"><?php echo count($followingUsers); ?> 人</span>
        </div>

        <?php if (empty($followingUsers)): ?>
            <div class="empty-state" style="margin-bottom:36px;">
                <div class="empty-icon">👤</div>
                <div class="empty-text">還沒有追蹤任何人</div>
                <p style="color:#aaa;">去主頁點擊影片作者的「追蹤」按鈕吧！</p>
            </div>
        <?php else: ?>
            <div style="margin-bottom:32px;">
                <?php foreach ($followingUsers as $fu): ?>
                <div class="following-user-card">
                    <div class="following-avatar"><?php echo mb_strtoupper(mb_substr($fu['following'],0,1)); ?></div>
                    <div>
                        <div style="font-weight:700;font-size:14px;color:#1c1c1e;"><?php echo htmlspecialchars($fu['following']); ?></div>
                        <div style="font-size:12px;color:#8e8e93;">共 <?php echo (int)$fu['video_count']; ?> 支影片 · 追蹤於 <?php echo date('m/d', strtotime($fu['created_at'])); ?></div>
                    </div>
                    <button class="follow-btn following"
                            onclick="toggleFollow('<?php echo htmlspecialchars(addslashes($fu['following'])); ?>', this)">
                        ✓ 追蹤中
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 追蹤的人的影片 -->
        <h2 style="font-size:18px;font-weight:700;color:#1c1c1e;margin-bottom:16px;">🎬 追蹤的人的影片</h2>
        <?php if (empty($videos)): ?>
            <div class="empty-state">
                <div class="empty-icon">📹</div>
                <div class="empty-text">追蹤的人還沒有上傳影片</div>
            </div>
        <?php else: ?>
            <div class="video-grid">
                <?php foreach ($videos as $video): ?>
                    <div class="video-card"
                         data-video-id="<?php echo $video['id']; ?>"
                         data-title="<?php echo htmlspecialchars($video['title']); ?>"
                         data-author="<?php echo htmlspecialchars($video['uploaded_by']); ?>"
                         data-time="<?php echo date('Y年m月d日', strtotime($video['upload_time'])); ?>"
                         data-likes="<?php echo $video['likes']; ?>"
                         data-is-liked="<?php echo $video['is_liked']; ?>"
                         data-view-count="<?php echo (int)$video['view_count']; ?>"
                         data-description="<?php echo htmlspecialchars($video['description'] ?? ''); ?>"
                         data-file-path="<?php echo htmlspecialchars($video['file_path']); ?>"
                         onclick="openVideoDetail(<?php echo (int)$video['id']; ?>)">
                        <div class="video-player">
                            <video playsinline muted preload="metadata"
                                   src="<?php echo htmlspecialchars($video['file_path']); ?>"
                                   onloadedmetadata="this.currentTime=0.1"></video>
                            <div class="play-icon"></div>
                        </div>
                        <div class="vc-info">
                            <div class="vc-title"><?php echo htmlspecialchars($video['title']); ?></div>
                            <?php if (!empty($video['tags'])): ?>
                            <div class="vc-tags">
                                <?php foreach(array_slice(explode(',', $video['tags']), 0, 3) as $tag): $tag=trim($tag); if(!$tag) continue; ?>
                                <a href="?view=home&tag=<?php echo urlencode($tag);?>" onclick="event.stopPropagation();"
                                   class="vc-tag">#<?php echo htmlspecialchars($tag);?></a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="vc-footer">
                                <div class="vc-avatar"><?php echo mb_strtoupper(mb_substr($video['uploaded_by'], 0, 1)); ?></div>
                                <span class="vc-author"><?php echo htmlspecialchars($video['uploaded_by']); ?></span>
                                <span class="vc-likes">❤️ <?php echo (int)$video['likes']; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php endif; /* end !$isAdmin following content */ ?>

        <?php else: ?>
            <form method="get" action="" id="tagSearchForm">
                <input type="hidden" name="view" value="home">
                <input type="hidden" name="tag" id="tagSearchHidden" value="<?php echo htmlspecialchars($activeTag); ?>">
                <div class="filter-area">
                    <div class="search-row">
                        <div class="search-wrap">
                            <input type="text" id="tagSearchDisplay" class="search-input"
                                   placeholder="搜尋標籤或影片關鍵字…"
                                   value="<?php echo $activeTag ? '#'.htmlspecialchars($activeTag) : ''; ?>">
                        </div>
                        <button type="submit" style="display:none">搜尋</button>
                    </div>
                    <?php
                    $filterTags = ['唇妝','眼妝','底妝','腮紅','修容','眉毛','日系','教學','彩妝','彩妝品','穿搭','日常通勤','歐美立體','韓系清透','約會精緻','霧面','奶油肌','水光感','自然裸妝','業配'];
                    ?>
                    <div class="filter-tags">
                        <span class="filter-label">標籤</span>
                        <a href="?view=home" class="f-chip <?php echo $activeTag === '' ? 'active' : ''; ?>"># 全部</a>
                        <?php foreach ($filterTags as $tag): ?>
                        <a href="?view=home&tag=<?php echo urlencode($tag); ?>"
                           class="f-chip <?php echo $activeTag === $tag ? 'active' : ''; ?>">#<?php echo htmlspecialchars($tag); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>


            <?php if ($activeTag !== ''): ?>
            <div class="tag-filter-bar">
                篩選標籤：#<?php echo htmlspecialchars($activeTag); ?>
                &nbsp;·&nbsp; 共 <?php echo count($videos); ?> 部影片
                <a href="?view=home">× 清除篩選</a>
            </div>
            <?php endif; ?>

            <?php if (!empty($recommended)): ?>
            <div class="rec-section">
                <div class="rec-header">
                    <span class="rec-title">
                        <?= $recReason === 'personalized' ? '✨ 為你推薦' : '🔥 熱門影片' ?>
                    </span>
                    <span class="rec-sub">
                        <?= $recReason === 'personalized' ? '根據你的追蹤與按讚紀錄' : '探索平台熱門內容' ?>
                    </span>
                </div>
                <div class="rec-grid">
                    <?php foreach ($recommended as $rv):
                        $rvTags = array_filter(array_map('trim', explode(',', $rv['tags'] ?? '')));
                    ?>
                    <div class="rec-card"
                         data-video-id="<?= $rv['id'] ?>"
                         data-title="<?= htmlspecialchars($rv['title']) ?>"
                         data-author="<?= htmlspecialchars($rv['uploaded_by']) ?>"
                         data-time="<?= date('Y年m月d日', strtotime($rv['upload_time'])) ?>"
                         data-likes="<?= $rv['likes'] ?>"
                         data-is-liked="0"
                         data-description="<?= htmlspecialchars($rv['description'] ?? '') ?>"
                         data-file-path="<?= htmlspecialchars($rv['file_path']) ?>"
                         onclick="openVideoDetail(<?= (int)$rv['id'] ?>)">
                        <div class="rec-thumb">
                            <video playsinline muted preload="metadata"
                                   src="<?= htmlspecialchars($rv['file_path']) ?>"
                                   onloadedmetadata="this.currentTime=0.1"></video>
                            <div class="play-icon"></div>
                        </div>
                        <div class="rec-info">
                            <div class="rec-card-title"><?= htmlspecialchars($rv['title']) ?></div>
                            <div class="rec-card-meta">
                                <span class="vc-avatar" style="width:20px;height:20px;font-size:10px;"><?= mb_strtoupper(mb_substr($rv['uploaded_by'], 0, 1)) ?></span>
                                <?= htmlspecialchars($rv['uploaded_by']) ?>
                                <?php if (!empty($rvTags)): ?>
                                    · <?php foreach (array_slice($rvTags, 0, 2) as $t): ?>
                                        <span class="rec-tag">#<?= htmlspecialchars($t) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="rec-divider">
                <span>所有影片</span>
            </div>
            <?php endif; ?>

            <div class="video-grid">
                <?php foreach ($videos as $index => $video): ?>
                    <?php $videoTags = array_filter(array_map('trim', explode(',', $video['tags'] ?? ''))); ?>
                    <div class="video-card"
                         data-video-id="<?php echo $video['id']; ?>"
                         data-title="<?php echo htmlspecialchars($video['title']); ?>"
                         data-author="<?php echo htmlspecialchars($video['uploaded_by']); ?>"
                         data-time="<?php echo date('Y年m月d日', strtotime($video['upload_time'])); ?>"
                         data-likes="<?php echo $video['likes']; ?>"
                         data-is-liked="<?php echo $video['is_liked']; ?>"
                         data-view-count="<?php echo (int)$video['view_count']; ?>"
                         data-description="<?php echo htmlspecialchars($video['description'] ?? ''); ?>"
                         data-file-path="<?php echo htmlspecialchars($video['file_path']); ?>"
                         onclick="openVideoDetail(<?php echo (int)$video['id']; ?>)">
                        <div class="video-player">
                            <video playsinline muted preload="metadata"
                                   src="<?php echo htmlspecialchars($video['file_path']); ?>"
                                   onloadedmetadata="this.currentTime=0.1"></video>
                            <div class="play-icon"></div>
                        </div>
                        <div class="vc-info">
                            <div class="vc-title"><?php echo htmlspecialchars($video['title']); ?></div>
                            <?php if (!empty($videoTags)): ?>
                            <div class="vc-tags">
                                <?php foreach ($videoTags as $tag): ?>
                                <a href="?view=home&tag=<?php echo urlencode($tag); ?>"
                                   class="vc-tag<?php echo ($activeTag === $tag) ? ' active' : ''; ?>"
                                   onclick="event.stopPropagation()">#<?php echo htmlspecialchars($tag); ?></a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="vc-footer">
                                <div class="vc-avatar"><?php echo mb_strtoupper(mb_substr($video['uploaded_by'], 0, 1)); ?></div>
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
                    <div class="video-detail-author" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <div style="display:flex;align-items:center;gap:8px;"></div>
                        <?php if ($isLoggedIn): ?>
                        <button id="detailFollowBtn" class="follow-btn<?php echo $isAdmin ? ' admin-no-action' : ''; ?>"
                                style="display:none;"
                                onclick="<?php echo $isAdmin ? 'return false;' : "toggleFollow('', this)"; ?>">+ 追蹤</button>
                        <?php endif; ?>
                    </div>
                    <div class="video-detail-time"></div>
                    <div class="video-detail-likes"></div>
                    <div class="video-detail-views" style="font-size:12px;color:#aaa;"></div>
                </div>
                <div class="video-detail-actions">
                    <button class="video-detail-like-btn<?php echo $isAdmin ? ' admin-no-action' : ''; ?>"
                            onclick="<?php echo $isAdmin ? 'return false;' : 'toggleLike()'; ?>">🤍 讚</button>
                    <button class="share-btn<?php echo $isAdmin ? ' admin-no-action' : ''; ?>"
                            onclick="<?php echo $isAdmin ? 'return false;' : 'shareVideo()'; ?>"><span style="font-size:13px;">🔗</span> 分享</button>
                    <button class="report-trigger-btn<?php echo $isAdmin ? ' admin-no-action' : ''; ?>"
                            onclick="<?php echo $isAdmin ? 'return false;' : 'toggleReportForm()'; ?>"><span style="font-size:13px;">🚩</span> 檢舉</button>
                </div>
                <?php if (!$isAdmin): ?>
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
                <?php else: ?>
                <div id="reportForm"></div>
                <?php endif; ?>

                <!-- 評論區 -->
                <div class="comments-section">
                    <div class="comments-header">💬 評論</div>

                    <div class="comment-form">
                        <input
                            type="text"
                            class="comment-input<?php echo $isAdmin ? ' admin-no-action' : ''; ?>"
                            id="commentInput"
                            placeholder="<?php echo $isAdmin ? '管理員無法留言' : '分享你的想法...'; ?>"
                            maxlength="200"
                            <?php if ($isAdmin): ?>readonly onclick="return false;"<?php endif; ?>>
                        <button class="comment-submit-btn<?php echo $isAdmin ? ' admin-no-action' : ''; ?>"
                                onclick="<?php echo $isAdmin ? 'return false;' : 'submitComment()'; ?>">發表</button>
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

<!-- Edit Video Modal -->
<div class="edit-modal-overlay" id="editModalOverlay" onclick="if(event.target===this)closeEditModal()">
    <div class="edit-modal">
        <button class="edit-modal-close" onclick="closeEditModal()">×</button>
        <h3>✏️ 編輯影片資訊</h3>
        <div class="form-group">
            <label for="editTitle">影片標題 *</label>
            <input type="text" id="editTitle" maxlength="255" placeholder="輸入影片標題…">
        </div>
        <div class="form-group">
            <label for="editDescription">影片內容描述</label>
            <textarea id="editDescription" rows="4" placeholder="分享這個影片的背景故事…"></textarea>
        </div>
        <div class="form-group">
            <label>標籤</label>

            <!-- 建議標籤 -->
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                <?php foreach ($presetTags as $pt): ?>
                <button type="button" class="preset-tag-btn edit-preset-tag-btn" data-tag="<?= htmlspecialchars($pt) ?>">#<?= htmlspecialchars($pt) ?></button>
                <?php endforeach; ?>
            </div>

            <div class="hashtag-input-box" id="editHashtagBox" onclick="document.getElementById('editHashtagTyping').focus()">
                <input type="text" id="editHashtagTyping" class="hashtag-typing" placeholder="輸入標籤，按 Enter 確認" autocomplete="off">
            </div>
            <input type="hidden" id="editTagsHidden">
            <small style="color:#999;margin-top:5px;display:block;">點選建議標籤或輸入自訂標籤（按 Enter）</small>
        </div>
        <div class="edit-modal-actions">
            <button class="edit-cancel-btn" onclick="closeEditModal()">取消</button>
            <button class="edit-save-btn" id="editSaveBtn" onclick="saveEditAjax()">儲存</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>

<div class="share-toast" id="shareToast">🔗 已複製連結！</div>

<script>
    let currentVideoIndex = 0;
    let videoIds = [];
    let currentDetailVideoId = null;

    // 儲存當前用戶資訊
    const currentUser = '<?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : ''; ?>';
    const currentRole = '<?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : ''; ?>';

    // ── 社群規範彈窗 ──
    const GUIDELINE_KEY = 'videoGuidelineAccepted';
    function openGuideline() {
        const o = document.getElementById('guidelineOverlay');
        if (o) o.classList.add('active');
    }
    function acceptGuideline() {
        try { localStorage.setItem(GUIDELINE_KEY, '1'); } catch (e) {}
        const o = document.getElementById('guidelineOverlay');
        if (o) o.classList.remove('active');
    }

    // 初始化影片ID列表
    function initVideoIds() {
        const videoCards = document.querySelectorAll('.video-card');
        videoIds = Array.from(videoCards).map(card => card.dataset.videoId);
    }

    // 頁面載入後初始化
    document.addEventListener('DOMContentLoaded', function() {
        initVideoIds();

        // 首次進入自動彈出社群規範（已同意過則不再自動跳，可用標題列按鈕重看）
        let guidelineAccepted = false;
        try { guidelineAccepted = !!localStorage.getItem(GUIDELINE_KEY); } catch (e) {}
        if (!guidelineAccepted) openGuideline();

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
            let wheelAccum    = 0;       // 累加滾動量
            let switchLock     = false;  // 切換中鎖定，避免慣性連跳
            let wheelResetTimer = null;
            const THRESHOLD   = 60;      // 觸發切換所需的累積量
            const LOCK_MS     = 650;     // 切換後鎖定時間

            function doSwitch(dir) {
                switchLock = true;
                wheelAccum = 0;
                dir > 0 ? nextVideo() : prevVideo();
                setTimeout(() => { switchLock = false; }, LOCK_MS);
            }

            // 滾輪／觸控板切換影片
            overlay.addEventListener('wheel', function(e) {
                // 滑鼠在右側評論／資訊區 → 允許正常捲動，不切換影片
                if (e.target.closest('.video-detail-info')) return;

                e.preventDefault();
                if (switchLock) return;

                wheelAccum += e.deltaY;
                clearTimeout(wheelResetTimer);
                wheelResetTimer = setTimeout(() => { wheelAccum = 0; }, 180); // 停止滾動後歸零

                if (wheelAccum > THRESHOLD)      doSwitch(1);
                else if (wheelAccum < -THRESHOLD) doSwitch(-1);
            }, { passive: false });

            // 手機觸控滑動支援
            let touchStartY = 0;
            overlay.addEventListener('touchstart', function(e) {
                touchStartY = e.touches[0].clientY;
            }, { passive: true });
            overlay.addEventListener('touchend', function(e) {
                if (switchLock) return;
                if (e.target.closest('.video-detail-info')) return;
                const diff = touchStartY - e.changedTouches[0].clientY;
                if (Math.abs(diff) > 50) doSwitch(diff > 0 ? 1 : -1);
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

            // 觀看數 +1（fire-and-forget）
            const vfd = new FormData();
            vfd.append('record_view', '1');
            vfd.append('video_id', videoId);
            fetch('', { method: 'POST', body: vfd }).catch(() => {});
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
                const viewCount = parseInt(card.dataset.viewCount || '0') + 1; // +1 for this view
                card.dataset.viewCount = viewCount; // update card data
                const description = card.dataset.description;
                const filePath = card.dataset.filePath;

                currentDetailVideoId = videoId;

                const overlay = document.getElementById('videoDetailOverlay');
                overlay.querySelector('.video-detail-title').textContent = title;
                const authorMeta = overlay.querySelector('.video-detail-author');
                const authorInner = authorMeta.querySelector('div');
                authorInner.innerHTML = `<div class="video-detail-avatar">${author.charAt(0).toUpperCase()}</div><span>${author}</span>`;
                const followBtn = document.getElementById('detailFollowBtn');
                if (followBtn) {
                    const currentUser = <?php echo json_encode($_SESSION['user'] ?? null); ?>;
                    if (author && author !== currentUser) {
                        followBtn.style.display = '';
                        if (currentRole === 'admin') {
                            followBtn.textContent = '+ 追蹤';
                            followBtn.className = 'follow-btn admin-no-action';
                            followBtn.onclick = function() { return false; };
                        } else {
                            const myFollowings = <?php echo json_encode(array_values($myFollowings)); ?>;
                            const isFollowing = myFollowings.includes(author);
                            followBtn.textContent = isFollowing ? '✓ 追蹤中' : '+ 追蹤';
                            followBtn.className = 'follow-btn ' + (isFollowing ? 'following' : 'not-following');
                            followBtn.onclick = function() { toggleFollow(author, this); };
                        }
                    } else {
                        followBtn.style.display = 'none';
                    }
                }
                // ── 每次開 overlay 重新讀卡片最新狀態（可能被 toggleLike 更新過）──
                const freshCard = document.querySelector(`.video-card[data-video-id="${videoId}"], .rec-card[data-video-id="${videoId}"]`);
                const freshIsLiked = freshCard ? freshCard.dataset.isLiked === '1' : isLiked;
                const freshLikes   = freshCard ? freshCard.dataset.likes : likes;

                overlay.querySelector('.video-detail-time').textContent = time;
                overlay.querySelector('.video-detail-likes').textContent = freshLikes + ' 讚';
                const viewEl = overlay.querySelector('.video-detail-views');
                if (viewEl) viewEl.textContent = '👁 ' + viewCount.toLocaleString() + ' 次觀看';
                overlay.querySelector('.video-detail-description').textContent = description || '無描述';

                const likeBtn = overlay.querySelector('.video-detail-like-btn');
                if (currentRole === 'admin') {
                    likeBtn.className = 'video-detail-like-btn admin-no-action';
                    likeBtn.innerHTML = '<span style="font-size:13px;">🤍</span> 讚';
                    likeBtn.onclick = function() { return false; };
                } else {
                    likeBtn.className = 'video-detail-like-btn' + (freshIsLiked ? ' liked' : '');
                    likeBtn.innerHTML = freshIsLiked ? '<span style="font-size:13px;">❤️</span> 已讚' : '<span style="font-size:13px;">🤍</span> 讚';
                    likeBtn.onclick = function() { toggleLike(videoId); };
                }

                const video = overlay.querySelector('.video-detail-player video');
                if (video) {
                    video.src = filePath;
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
                descriptionInput.value = '';
                // 關閉檢舉表單，彈出確認框
                const form = document.getElementById('reportForm');
                if (form) form.classList.remove('active');
                messageBox.style.display = 'none';
                const overlay = document.getElementById('reportSuccessOverlay');
                if (overlay) overlay.style.display = 'flex';
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

    function closeReportSuccess() {
        const overlay = document.getElementById('reportSuccessOverlay');
        if (overlay) overlay.style.display = 'none';
    }
    // 點背景也可關閉
    document.getElementById('reportSuccessOverlay')?.addEventListener('click', function(e) {
        if (e.target === this) closeReportSuccess();
    });

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

    async function _sendLikeAjax(videoId) {
        const fd = new FormData();
        fd.append('video_id', videoId);
        fd.append('toggle_like', '1');
        const res = await fetch('', { method: 'POST', body: fd });
        return res.json();
    }

    // detail overlay 按讚
    async function toggleLike(videoId) {
        try {
            const data = await _sendLikeAjax(videoId);
            if (!data.success) return;
            const overlay = document.getElementById('videoDetailOverlay');
            const likeBtn = overlay.querySelector('.video-detail-like-btn');
            likeBtn.className = 'video-detail-like-btn' + (data.liked ? ' liked' : '');
            likeBtn.innerHTML = data.liked
                ? '<span style="font-size:13px;">❤️</span> 已讚'
                : '<span style="font-size:13px;">🤍</span> 讚';
            overlay.querySelector('.video-detail-likes').textContent = data.likes + ' 讚';

            // ── 同步更新卡片的 data 屬性，讓下次重開 overlay 狀態一致 ──
            const card = document.querySelector(`.video-card[data-video-id="${videoId}"], .rec-card[data-video-id="${videoId}"]`);
            if (card) {
                card.dataset.isLiked = data.liked ? '1' : '0';
                card.dataset.likes   = data.likes;
            }
        } catch(e) { console.error('按讚失敗', e); }
    }

    // 卡片按讚
    async function toggleLikeCard(videoId, btn) {
        btn.disabled = true;
        try {
            const data = await _sendLikeAjax(videoId);
            if (!data.success) return;
            btn.classList.toggle('liked', data.liked);
            btn.querySelector('.like-icon').textContent = data.liked ? '❤️' : '🤍';
            btn.querySelector('.like-count').textContent = data.likes;
        } catch(e) { console.error('按讚失敗', e); }
        btn.disabled = false;
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

    function displayComments(comments, replies) {
        const commentsList = document.getElementById('commentsList');
        
        if (comments.length === 0) {
            commentsList.innerHTML = '<div class="empty-comments">尚無評論</div>';
            return;
        }

        let html = '';
        comments.forEach(comment => {
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
                        ${comment.username === currentUser || currentRole === 'admin' ? `<button class="comment-delete-btn" onclick="deleteComment(${comment.id})">刪除</button>` : ''}
                    </div>
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
                showCommentError('');
                loadComments();
            } else {
                showCommentError(data.message || '發表評論失敗', data.blocked);
            }
        })
        .catch(error => {
            console.error('提交評論失敗:', error);
            showCommentError('網路連線失敗');
        });
    }

    function showCommentError(msg, isBlocked) {
        var el = document.getElementById('commentErrorMsg');
        if (!el) {
            el = document.createElement('div');
            el.id = 'commentErrorMsg';
            el.style.cssText = 'font-size:12px;padding:7px 12px;border-radius:8px;margin-top:6px;display:none;';
            var form = document.querySelector('.comment-form');
            if (form) form.insertAdjacentElement('afterend', el);
        }
        if (!msg) { el.style.display = 'none'; return; }
        el.style.display = 'block';
        el.style.background = isBlocked ? 'rgba(220,53,69,0.15)' : 'rgba(255,200,0,0.15)';
        el.style.color      = isBlocked ? '#ff6b7a' : '#ffc107';
        el.style.border     = isBlocked ? '1px solid rgba(220,53,69,0.35)' : '1px solid rgba(255,200,0,0.35)';
        el.textContent = isBlocked ? '🚫 ' + msg : '⚠️ ' + msg;
        setTimeout(function(){ el.style.display = 'none'; }, 4000);
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
                // 顯示在回覆輸入框下方
                var replyForm = document.getElementById(`replyForm-${parentId}`);
                var errEl = replyForm ? replyForm.querySelector('.reply-err') : null;
                if (replyForm && !errEl) {
                    errEl = document.createElement('div');
                    errEl.className = 'reply-err';
                    errEl.style.cssText = 'font-size:11px;padding:5px 8px;border-radius:6px;margin-top:4px;';
                    replyForm.appendChild(errEl);
                }
                if (errEl) {
                    var isBlocked = data.blocked;
                    errEl.style.background = isBlocked ? 'rgba(220,53,69,0.15)' : 'rgba(255,200,0,0.15)';
                    errEl.style.color      = isBlocked ? '#ff6b7a' : '#ffc107';
                    errEl.textContent      = (isBlocked ? '🚫 ' : '⚠️ ') + (data.message || '發表回覆失敗');
                    setTimeout(function(){ errEl.textContent = ''; }, 4000);
                }
            }
        })
        .catch(error => {
            console.error('提交回覆失敗:', error);
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

    function promptTakedownReason(form) {
        const r = prompt('請輸入下架原因（可留空，預設為「違反社群規範」）：');
        if (r === null) return false;
        form.querySelector('[name="removed_reason"]').value = r;
        return true;
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
        const tag = document.activeElement ? document.activeElement.tagName : '';
        const isEditing = (tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement.isContentEditable);
        if (e.key === 'Escape') {
            closeVideoDetail();
        } else if (!isEditing && e.key === 'ArrowUp') {
            prevVideo();
        } else if (!isEditing && e.key === 'ArrowDown') {
            nextVideo();
        }
    });

    async function deleteVideoAjax(event, videoId, btn) {
        // 阻止冒泡，避免點刪除時誤觸卡片開啟影片
        if (event) { event.stopPropagation(); event.preventDefault(); }
        if (!confirm('確定要刪除此影片嗎？')) return;
        btn.disabled = true;
        btn.textContent = '刪除中…';
        try {
            const form = new FormData();
            form.append('video_id', videoId);
            form.append('delete', '1');
            form.append('ajax_delete', '1');
            const res  = await fetch('?view=personal', { method: 'POST', body: form });
            const data = await res.json();
            if (data.success) {
                btn.textContent = '✓ 已刪除';
                btn.style.background = '#16a34a';
                const card = btn.closest('.video-card');
                if (card) {
                    setTimeout(() => {
                        card.style.transition = 'opacity .3s';
                        card.style.opacity = '0';
                        setTimeout(() => card.remove(), 300);
                    }, 600);
                }
            } else {
                alert(data.message || '刪除失敗，請重試');
                btn.disabled = false;
                btn.textContent = '🗑️ 刪除';
            }
        } catch (e) {
            alert('網路錯誤，請重試');
            btn.disabled = false;
            btn.textContent = '🗑️ 刪除';
        }
    }

    // ── 影片上傳（AJAX：上傳中/成功/失敗，失敗保留欄位）─────────────
    async function submitUpload(event) {
        event.preventDefault();
        const form   = document.getElementById('uploadForm');
        const btn    = document.getElementById('uploadBtn');
        const status = document.getElementById('uploadStatus');

        const fileInput = document.getElementById('video');
        if (!fileInput.files.length) {
            showUploadStatus('error', '請選擇影片檔案');
            return false;
        }

        btn.disabled = true;
        btn.textContent = '⏳ 上傳中…';
        showUploadStatus('loading', '⏳ 影片上傳中，請稍候…');

        try {
            const formData = new FormData(form);
            formData.append('ajax_upload', '1');
            const res  = await fetch('?view=personal', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                showUploadStatus('success', '✓ 上傳成功！即將重新整理…');
                btn.textContent = '✓ 上傳成功';
                setTimeout(() => { window.location.href = '?view=personal'; }, 1200);
            } else {
                // 失敗：保留標題/描述/標籤，只需重新選檔案
                showUploadStatus('error', '✗ ' + (data.message || '上傳失敗，請重試'));
                btn.disabled = false;
                btn.textContent = '🚀 上傳影片';
            }
        } catch (e) {
            showUploadStatus('error', '✗ 網路錯誤，請重試');
            btn.disabled = false;
            btn.textContent = '🚀 上傳影片';
        }
        return false;
    }

    function showUploadStatus(type, msg) {
        const el = document.getElementById('uploadStatus');
        if (!el) return;
        el.style.display = 'block';
        el.textContent = msg;
        if (type === 'success') {
            el.style.background = '#f0fdf4'; el.style.color = '#16a34a'; el.style.border = '1px solid #bbf7d0';
        } else if (type === 'error') {
            el.style.background = '#fef2f2'; el.style.color = '#dc2626'; el.style.border = '1px solid #fecaca';
        } else {
            el.style.background = '#eff6ff'; el.style.color = '#2563eb'; el.style.border = '1px solid #bfdbfe';
        }
    }

    // ── Edit Modal ──────────────────────────────────────────────────
    let _editVideoId = null;

    function openEditModal(videoId, title, description, tags) {
        _editVideoId = videoId;
        document.getElementById('editTitle').value = title;
        document.getElementById('editDescription').value = description;
        // init hashtag chips
        editTags = tags ? tags.split(',').map(t => t.trim()).filter(Boolean) : [];
        renderEditChips();
        document.getElementById('editModalOverlay').classList.add('active');
        document.getElementById('editTitle').focus();
    }

    function closeEditModal() {
        document.getElementById('editModalOverlay').classList.remove('active');
        _editVideoId = null;
    }

    async function saveEditAjax() {
        const title = document.getElementById('editTitle').value.trim();
        if (!title) { alert('標題不能為空'); return; }
        const btn = document.getElementById('editSaveBtn');
        btn.disabled = true; btn.textContent = '儲存中…';
        try {
            const fd = new FormData();
            fd.append('edit_video', '1');
            fd.append('video_id', _editVideoId);
            fd.append('title', title);
            fd.append('description', document.getElementById('editDescription').value.trim());
            fd.append('tags', document.getElementById('editTagsHidden').value);
            const res = await fetch('?view=personal', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                // update the card in DOM
                const cards = document.querySelectorAll('.video-card');
                cards.forEach(card => {
                    const editBtnEl = card.querySelector('.edit-btn');
                    if (editBtnEl && editBtnEl.getAttribute('onclick').includes('openEditModal(' + _editVideoId + ',')) {
                        const titleEl = card.querySelector('.video-title');
                        const descEl  = card.querySelector('.video-description');
                        if (titleEl) titleEl.textContent = data.title;
                        if (descEl)  descEl.textContent = data.description ? data.description.substring(0, 100) : '';
                    }
                });
                closeEditModal();
            } else {
                alert(data.message || '儲存失敗');
            }
        } catch(e) {
            alert('網路錯誤，請重試');
        }
        btn.disabled = false; btn.textContent = '儲存';
    }

    async function toggleFollow(username, btn) {
        var form = new FormData();
        form.append('toggle_follow', '1');
        form.append('target_user', username);
        try {
            var res = await fetch('video.php', { method: 'POST', body: form });
            var data = await res.json();
            if (data.success) {
                if (data.action === 'unfollowed') {
                    btn.textContent = '+ 追蹤';
                    btn.className = 'follow-btn not-following';
                    btn.onclick = function() { toggleFollow(username, this); };
                } else {
                    btn.textContent = '✓ 追蹤中';
                    btn.className = 'follow-btn following';
                    btn.onclick = function() { toggleFollow(username, this); };
                }
            }
        } catch(e) {
            console.error('追蹤操作失敗', e);
        }
    }
</script>

<script>
// ── Hashtag chip input ───────────────────────────────────────────
(function () {
    const box        = document.getElementById('hashtagInputBox');
    const typing     = document.getElementById('hashtagTyping');
    const hidden     = document.getElementById('tagsHidden');
    const sugList    = document.getElementById('tagSuggestionList');
    const presetBtns = document.querySelectorAll('.preset-tag-btn');
    if (!box || !typing || !hidden) return;

    // 推薦池 = 預設標籤 + DB 現有標籤（合併去重）
    const presetPool = Array.from(presetBtns).map(b => b.dataset.tag);
    const dbTags     = <?= json_encode($existingTags) ?>;
    const allPool    = [...new Set([...presetPool, ...dbTags])];

    let tags = [];
    let isComposing = false;

    typing.addEventListener('compositionstart', () => { isComposing = true; });
    typing.addEventListener('compositionend', (e) => {
        isComposing = false;
        // compositionend 後瀏覽器還沒把 value 更新完，用 setTimeout 等一個 tick
        setTimeout(() => updateSuggestions(), 0);
    });

    function syncHidden() { hidden.value = tags.join(','); }

    function addTag(raw) {
        const tag = raw.replace(/^#+/, '').trim();
        if (!tag || tags.includes(tag)) return;
        tags.push(tag);

        const chip = document.createElement('span');
        chip.className = 'hashtag-chip';
        chip.dataset.tag = tag;
        chip.innerHTML = `#${tag} <span class="hashtag-chip-remove">×</span>`;
        chip.querySelector('.hashtag-chip-remove').addEventListener('click', () => removeTag(tag));
        box.insertBefore(chip, typing);

        // 同步 preset 按鈕狀態
        presetBtns.forEach(btn => { if (btn.dataset.tag === tag) btn.classList.add('selected'); });
        syncHidden();
        hideSuggestions();
    }

    function removeTag(tag) {
        tags = tags.filter(t => t !== tag);
        box.querySelector(`.hashtag-chip[data-tag="${tag}"]`)?.remove();
        presetBtns.forEach(btn => { if (btn.dataset.tag === tag) btn.classList.remove('selected'); });
        syncHidden();
    }

    // Preset 按鈕
    presetBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tag = btn.dataset.tag;
            if (tags.includes(tag)) removeTag(tag);
            else addTag(tag);
        });
    });

    // 鍵盤
    typing.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = typing.value.trim();
            if (val) { addTag(val); typing.value = ''; }
        } else if (e.key === 'Backspace' && typing.value === '' && tags.length) {
            removeTag(tags[tags.length - 1]);
        } else if (e.key === 'Escape') {
            hideSuggestions();
        }
    });

    // 自動補 # + 即時建議
    function updateSuggestions() {
        const val = typing.value;
        const query = val.replace(/^#+/, '').trim().toLowerCase();
        if (query.length < 1) { hideSuggestions(); return; }

        const matched = allPool.filter(t => t.toLowerCase().includes(query) && !tags.includes(t));
        if (matched.length === 0) { hideSuggestions(); return; }

        sugList.innerHTML = '';
        matched.forEach(t => {
            const chip = document.createElement('span');
            chip.className = 'suggestion-chip';
            const idx = t.toLowerCase().indexOf(query);
            chip.innerHTML = '#' + t.slice(0, idx) + `<strong>${t.slice(idx, idx + query.length)}</strong>` + t.slice(idx + query.length);
            chip.addEventListener('mousedown', (e) => { e.preventDefault(); addTag(t); typing.value = ''; });
            sugList.appendChild(chip);
        });
        sugList.style.display = 'flex';
    }

    typing.addEventListener('input', () => {
        updateSuggestions();
    });

    function hideSuggestions() { if (sugList) sugList.style.display = 'none'; }
    document.addEventListener('click', (e) => { if (!box.contains(e.target) && !sugList.contains(e.target)) hideSuggestions(); });
})();

// ── 搜尋欄：去掉 # 才送出 ────────────────────────────────────────
(function () {
    const form    = document.getElementById('tagSearchForm');
    const display = document.getElementById('tagSearchDisplay');
    const hidden  = document.getElementById('tagSearchHidden');
    if (!form || !display || !hidden) return;

    form.addEventListener('submit', (e) => {
        hidden.value = display.value.replace(/^#+/, '').trim();
    });
})();
</script>

<script>
// ── Edit Modal Hashtag Chip ─────────────────────────────────────
var editTags = [];

function renderEditChips() {
    const box    = document.getElementById('editHashtagBox');
    const typing = document.getElementById('editHashtagTyping');
    // remove existing chips
    box.querySelectorAll('.hashtag-chip').forEach(c => c.remove());
    editTags.forEach(tag => {
        const chip = document.createElement('span');
        chip.className = 'hashtag-chip';
        chip.innerHTML = '#' + tag + ' <span class="hashtag-chip-remove" onclick="removeEditTag(\'' + tag.replace(/'/g,"&#39;") + '\')">×</span>';
        box.insertBefore(chip, typing);
    });
    document.getElementById('editTagsHidden').value = editTags.join(',');
    // 同步建議標籤按鈕的選取狀態
    document.querySelectorAll('.edit-preset-tag-btn').forEach(btn => {
        btn.classList.toggle('selected', editTags.includes(btn.dataset.tag));
    });
}

function addEditTag(tag) {
    if (tag && !editTags.includes(tag) && editTags.length < 10) {
        editTags.push(tag);
        renderEditChips();
    }
}

function removeEditTag(tag) {
    editTags = editTags.filter(t => t !== tag);
    renderEditChips();
}

// 建議標籤按鈕：點一下加入/移除
document.querySelectorAll('.edit-preset-tag-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tag = this.dataset.tag;
        if (editTags.includes(tag)) {
            removeEditTag(tag);
        } else {
            addEditTag(tag);
        }
    });
});

document.getElementById('editHashtagTyping').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = this.value.replace(/^#/, '').trim();
        if (val && !editTags.includes(val) && editTags.length < 10) {
            editTags.push(val);
            renderEditChips();
        }
        this.value = '';
    } else if (e.key === 'Backspace' && this.value === '' && editTags.length > 0) {
        editTags.pop();
        renderEditChips();
    }
});
</script>

<!-- 檢舉成功確認框 -->
<div id="reportSuccessOverlay" style="
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.45); z-index:9999;
  justify-content:center; align-items:center;">
  <div style="
    background:#fff; border-radius:20px;
    padding:36px 32px 28px; max-width:340px; width:90%;
    box-shadow:0 20px 60px rgba(0,0,0,.25);
    text-align:center; animation:rptFadeIn .2s ease;">
    <div style="font-size:2.4rem; margin-bottom:12px;">✅</div>
    <div style="font-size:17px; font-weight:700; color:#1a1a2e; margin-bottom:8px;">檢舉已送出</div>
    <div style="font-size:13px; color:#888; line-height:1.7; margin-bottom:24px;">
      感謝您的回報，<br>我們會盡快審查此影片。
    </div>
    <button onclick="closeReportSuccess()" style="
      width:100%; padding:12px;
      background:linear-gradient(135deg,#6b1e2e,#c26b7c);
      color:#fff; border:none; border-radius:99px;
      font-size:15px; font-weight:700; cursor:pointer;">
      確認
    </button>
  </div>
</div>
<style>
@keyframes rptFadeIn {
  from { opacity:0; transform:scale(.92); }
  to   { opacity:1; transform:scale(1); }
}
</style>

<!-- Admin tooltip (fixed, never clipped) -->
<div id="adminTooltip">管理員無法使用此功能</div>
<script>
(function() {
    const tip = document.getElementById('adminTooltip');
    if (!tip) return;

    document.addEventListener('mouseover', function(e) {
        const el = e.target.closest('.admin-no-action');
        if (!el) return;
        const r = el.getBoundingClientRect();
        tip.style.display = 'block';
        // Position below the element
        let top = r.bottom + 6;
        let left = r.left + r.width / 2 - tip.offsetWidth / 2;
        // Clamp so tooltip stays inside viewport
        left = Math.max(8, Math.min(left, window.innerWidth - tip.offsetWidth - 8));
        if (top + tip.offsetHeight > window.innerHeight - 8) {
            top = r.top - tip.offsetHeight - 6; // flip to above if no room below
        }
        tip.style.top  = top + 'px';
        tip.style.left = left + 'px';
    });

    document.addEventListener('mouseout', function(e) {
        if (!e.target.closest('.admin-no-action')) return;
        if (e.relatedTarget && e.relatedTarget.closest('.admin-no-action')) return;
        tip.style.display = 'none';
    });

    // Hide on scroll / click
    document.addEventListener('scroll', function() { tip.style.display = 'none'; }, true);
    document.addEventListener('click',  function() { tip.style.display = 'none'; }, true);
})();
</script>

</body>
</html>
