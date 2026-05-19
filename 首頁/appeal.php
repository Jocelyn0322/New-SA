<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['user'];
$videoId  = (int)($_GET['video_id'] ?? 0);
$msg      = '';
$msgType  = '';

// 查影片資訊
$stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ? AND uploaded_by = ?");
$stmt->execute([$videoId, $username]);
$video = $stmt->fetch();

if (!$video) {
    die('<p style="text-align:center;padding:40px;color:#e83e5a;">找不到此影片，或您沒有權限申訴。</p>');
}

if ($video['is_active']) {
    die('<p style="text-align:center;padding:40px;color:#555;">此影片目前未被下架，無需申訴。</p>');
}

// 檢查是否已申訴過
$existing = $pdo->prepare("SELECT * FROM video_appeals WHERE video_id = ? AND username = ?");
$existing->execute([$videoId, $username]);
$appeal = $existing->fetch();

// 處理申訴提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$appeal) {
    $reason = trim($_POST['reason'] ?? '');
    if (mb_strlen($reason) < 10) {
        $msg = '申訴原因至少需要 10 個字';
        $msgType = 'error';
    } else {
        $pdo->prepare("INSERT INTO video_appeals (video_id, username, reason) VALUES (?, ?, ?)")
            ->execute([$videoId, $username, $reason]);
        $msg = '申訴已提交，管理員將在 3-5 個工作天內審核';
        $msgType = 'success';
        // 重新抓申訴資料
        $existing->execute([$videoId, $username]);
        $appeal = $existing->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>影片申訴</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: '標楷體', 'BiauKai', 'DFKai-SB', 'KaiTi', serif; background: #f5f5f5; color: #333; }
.container { max-width: 600px; margin: 60px auto; padding: 0 20px; }
.card { background: #fff; border-radius: 16px; padding: 36px; box-shadow: 0 2px 16px rgba(0,0,0,0.08); }
h1 { font-size: 22px; margin-bottom: 6px; }
.subtitle { color: #888; font-size: 14px; margin-bottom: 24px; }
.video-info { background: #fff5f7; border: 1px solid #ffc0cb; border-radius: 10px; padding: 16px; margin-bottom: 24px; }
.video-info .label { font-size: 12px; color: #888; margin-bottom: 4px; }
.video-info .title { font-weight: 700; font-size: 16px; margin-bottom: 10px; }
.video-info .reason { font-size: 13px; color: #555; }
label { display: block; font-weight: 600; margin-bottom: 8px; }
textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; resize: vertical; font-family: inherit; }
textarea:focus { outline: none; border-color: #e83e5a; }
.btn { display: block; width: 100%; padding: 14px; background: #e83e5a; color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 16px; }
.btn:hover { background: #c8304a; }
.alert { padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
.alert.success { background: #d4edda; color: #155724; }
.alert.error   { background: #f8d7da; color: #721c24; }
.status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; }
.status-pending  { background: #fff3cd; color: #856404; }
.status-approved { background: #d4edda; color: #155724; }
.status-rejected { background: #f8d7da; color: #721c24; }
.back-link { display: inline-block; margin-top: 20px; color: #888; font-size: 13px; text-decoration: none; }
.back-link:hover { color: #e83e5a; }
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>📋 影片申訴</h1>
        <p class="subtitle">若您認為影片下架決定有誤，請提出申訴說明。</p>

        <?php if ($msg): ?>
            <div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <div class="video-info">
            <div class="label">影片標題</div>
            <div class="title"><?php echo htmlspecialchars($video['title']); ?></div>
            <div class="label">下架原因</div>
            <div class="reason"><?php echo nl2br(htmlspecialchars($video['removed_reason'] ?? '未說明')); ?></div>
            <?php if ($video['removed_at']): ?>
                <div style="margin-top:8px;font-size:12px;color:#aaa;">下架時間：<?php echo date('Y/m/d H:i', strtotime($video['removed_at'])); ?></div>
            <?php endif; ?>
        </div>

        <?php if ($appeal): ?>
            <!-- 已申訴，顯示狀態 -->
            <div style="text-align:center;padding:20px 0;">
                <p style="font-size:15px;margin-bottom:12px;">您的申訴狀態：</p>
                <span class="status-badge status-<?php echo $appeal['status']; ?>">
                    <?php echo ['pending'=>'⏳ 審核中','approved'=>'✅ 已核准','rejected'=>'❌ 已拒絕'][$appeal['status']]; ?>
                </span>
                <?php if ($appeal['admin_note']): ?>
                    <p style="margin-top:16px;color:#555;font-size:14px;">管理員備註：<?php echo htmlspecialchars($appeal['admin_note']); ?></p>
                <?php endif; ?>
                <?php if ($appeal['status'] === 'approved'): ?>
                    <p style="margin-top:12px;color:#155724;font-size:14px;">您的影片已恢復上架。</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- 申訴表單 -->
            <form method="post">
                <label>申訴原因 <span style="color:#888;font-weight:400;font-size:12px;">（請詳細說明，至少 10 字）</span></label>
                <textarea name="reason" rows="5" placeholder="例如：此影片內容符合社群規範，下架決定有誤，因為..." required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                <button type="submit" class="btn">提交申訴</button>
            </form>
        <?php endif; ?>

        <a href="javascript:history.back()" class="back-link">← 返回</a>
    </div>
</div>
</body>
</html>
