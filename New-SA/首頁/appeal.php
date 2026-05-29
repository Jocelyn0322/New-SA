<?php
session_start();
require __DIR__ . '/../db.php';
require_once __DIR__ . '/../notify_helper.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// 確保 video_appeals 表存在
$pdo->exec("CREATE TABLE IF NOT EXISTS video_appeals (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    video_id    INT  NOT NULL,
    username    VARCHAR(100) NOT NULL,
    reason      TEXT NOT NULL,
    status      VARCHAR(20) DEFAULT 'pending',
    admin_note  TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$username = $_SESSION['user'];
$videoId  = (int)($_GET['video_id'] ?? 0);
$msg      = '';
$msgType  = '';

// 查影片資訊
$stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ? AND uploaded_by = ?");
$stmt->execute([$videoId, $username]);
$video = $stmt->fetch();

if (!$video) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="../shared.css"></head><body>
    ' . (include_once __DIR__ . '/../header.php' ? '' : '') . '
    <div style="text-align:center;padding:80px 20px;color:#e83e5a;font-size:16px;">找不到此影片，或您沒有權限申訴。</div></body></html>');
}

// 支援整數 0 或 PostgreSQL boolean 字串 'f'/'false'
$isActive = ($video['is_active'] && $video['is_active'] !== 'f' && $video['is_active'] !== 'false' && $video['is_active'] !== '0');
if ($isActive) {
    header("Location: video.php");
    exit();
}

// 檢查是否已申訴過
$existing = $pdo->prepare("SELECT * FROM video_appeals WHERE video_id = ? AND username = ? ORDER BY created_at DESC LIMIT 1");
$existing->execute([$videoId, $username]);
$appeal = $existing->fetch();

// 處理申訴提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$appeal) {
    $reason = trim($_POST['reason'] ?? '');
    if (mb_strlen($reason) < 10) {
        $msg = '申訴原因至少需要 10 個字';
        $msgType = 'error';
    } else {
        try {
            $pdo->prepare("INSERT INTO video_appeals (video_id, username, reason) VALUES (?, ?, ?)")
                ->execute([$videoId, $username, $reason]);
            $msg = '申訴已提交，管理員將在 3-5 個工作天內審核';
            $msgType = 'success';
            // 重新抓申訴資料
            $existing->execute([$videoId, $username]);
            $appeal = $existing->fetch();
        } catch (Throwable $e) {
            $msg = '申訴提交失敗，請稍後再試。';
            $msgType = 'error';
            error_log('申訴提交失敗：' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>COSMETIC — 影片申訴</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../shared.css">
<style>
.appeal-wrap { max-width: 600px; margin: 40px auto 80px; padding: 0 20px; }
.appeal-card { background: #fff; border-radius: 16px; padding: 36px; box-shadow: 0 2px 16px rgba(0,0,0,0.08); }
.appeal-card h1 { font-size: 22px; margin-bottom: 6px; color: #1a1a2e; }
.appeal-subtitle { color: #888; font-size: 14px; margin-bottom: 24px; }
.video-info { background: #fff5f7; border: 1px solid #ffc0cb; border-radius: 10px; padding: 16px; margin-bottom: 24px; }
.video-info .vi-label { font-size: 12px; color: #888; margin-bottom: 4px; margin-top: 10px; }
.video-info .vi-label:first-child { margin-top: 0; }
.video-info .vi-title { font-weight: 700; font-size: 16px; }
.video-info .vi-reason { font-size: 13px; color: #555; line-height: 1.5; }
.appeal-label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
.appeal-textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; resize: vertical; font-family: inherit; line-height: 1.6; }
.appeal-textarea:focus { outline: none; border-color: #c26b7c; box-shadow: 0 0 0 3px rgba(194,107,124,.12); }
.appeal-btn { display: block; width: 100%; padding: 14px; background: #c26b7c; color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 16px; transition: background .15s; }
.appeal-btn:hover { background: #9d2942; }
.alert { padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
.alert.success { background: #d4edda; color: #155724; }
.alert.error   { background: #f8d7da; color: #721c24; }
.status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; }
.status-pending  { background: #fff3cd; color: #856404; }
.status-approved { background: #d4edda; color: #155724; }
.status-rejected { background: #f8d7da; color: #721c24; }
.back-link { display: inline-block; margin-top: 20px; color: #aaa; font-size: 13px; text-decoration: none; }
.back-link:hover { color: #c26b7c; }
</style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>
<div class="appeal-wrap">
    <div class="appeal-card">
        <h1>📋 影片申訴</h1>
        <p class="appeal-subtitle">若您認為影片下架決定有誤，請提出申訴說明。</p>

        <?php if ($msg): ?>
            <div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <div class="video-info">
            <div class="vi-label">影片標題</div>
            <div class="vi-title"><?php echo htmlspecialchars($video['title']); ?></div>
            <div class="vi-label">下架原因</div>
            <div class="vi-reason"><?php echo nl2br(htmlspecialchars($video['removed_reason'] ?? '未說明')); ?></div>
            <?php if (!empty($video['removed_at'])): ?>
                <div style="margin-top:8px;font-size:12px;color:#aaa;">下架時間：<?php echo date('Y/m/d H:i', strtotime($video['removed_at'])); ?></div>
            <?php endif; ?>
        </div>

        <?php if ($appeal): ?>
            <div style="text-align:center;padding:24px 0;">
                <p style="font-size:15px;margin-bottom:14px;color:#555;">您的申訴狀態：</p>
                <span class="status-badge status-<?php echo htmlspecialchars($appeal['status']); ?>">
                    <?php echo ['pending'=>'⏳ 審核中','approved'=>'✅ 已核准','rejected'=>'❌ 已拒絕'][$appeal['status']] ?? $appeal['status']; ?>
                </span>
                <?php if (!empty($appeal['admin_note'])): ?>
                    <p style="margin-top:16px;color:#555;font-size:14px;line-height:1.6;">管理員備註：<?php echo htmlspecialchars($appeal['admin_note']); ?></p>
                <?php endif; ?>
                <?php if ($appeal['status'] === 'approved'): ?>
                    <p style="margin-top:12px;color:#155724;font-size:14px;">您的影片已恢復上架。</p>
                <?php endif; ?>
                <?php if (!empty($appeal['created_at'])): ?>
                    <p style="margin-top:10px;font-size:12px;color:#aaa;">提交時間：<?php echo date('Y/m/d H:i', strtotime($appeal['created_at'])); ?></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="post">
                <label class="appeal-label">申訴原因 <span style="color:#aaa;font-weight:400;font-size:12px;">（請詳細說明，至少 10 字）</span></label>
                <textarea class="appeal-textarea" name="reason" rows="5"
                    placeholder="例如：此影片內容符合社群規範，下架決定有誤，因為..."
                    required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                <button type="submit" class="appeal-btn">提交申訴</button>
            </form>
        <?php endif; ?>

        <a href="javascript:history.back()" class="back-link">← 返回</a>
    </div>
</div>
</body>
</html>
