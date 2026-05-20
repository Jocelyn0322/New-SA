<?php
session_start();
require 'db.php';
require_once 'send_mail.php';

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminUser = $_SESSION['user'];
$tab       = $_GET['tab'] ?? 'stats';
$msg       = '';
$msgType   = '';

// ── POST 處理 ──────────────────────────────────────────────

// 強制刪除影片
if (isset($_POST['force_delete_video'])) {
    $vid = (int)$_POST['video_id'];
    $row = $pdo->prepare("SELECT file_path FROM videos WHERE id = ?");
    $row->execute([$vid]);
    $vrow = $row->fetch();
    if ($vrow) {
        $fp = __DIR__ . '/' . $vrow['file_path'];
        if (file_exists($fp) && is_file($fp)) unlink($fp);
        $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$vid]);
        $msg = '已刪除影片'; $msgType = 'success';
    }
    $tab = isset($_POST['from_reports']) ? 'reports' : 'videos';
}

// 切換使用者角色
if (isset($_POST['toggle_role'])) {
    $targetUser  = trim($_POST['target_user']  ?? '');
    $currentRole = trim($_POST['current_role'] ?? '');
    if ($targetUser && $targetUser !== $adminUser) {
        $newRole = ($currentRole === 'admin') ? 'user' : 'admin';
        $pdo->prepare("UPDATE users SET role = ? WHERE username = ?")
            ->execute([$newRole, $targetUser]);
        $msg = "已將「{$targetUser}」改為 {$newRole}"; $msgType = 'success';
    }
    $tab = 'users';
}

// 停用使用者
if (isset($_POST['suspend_user'])) {
    $targetUser = trim($_POST['target_user'] ?? '');
    if ($targetUser && $targetUser !== $adminUser) {
        $pdo->prepare("UPDATE users SET status = 'suspended', suspended_at = NOW() WHERE username = ?")
            ->execute([$targetUser]);
        $msg = "已停用使用者「{$targetUser}」"; $msgType = 'success';
    }
    $tab = 'users';
}

// 恢復使用者
if (isset($_POST['restore_user'])) {
    $targetUser = trim($_POST['target_user'] ?? '');
    if ($targetUser && $targetUser !== $adminUser) {
        $pdo->prepare("UPDATE users SET status = 'active', suspended_at = NULL WHERE username = ?")
            ->execute([$targetUser]);
        $msg = "已恢復使用者「{$targetUser}」帳號"; $msgType = 'success';
    }
    $tab = 'users';
}

// 刪除留言
if (isset($_POST['delete_comment'])) {
    $cid = (int)$_POST['comment_id'];
    $pdo->prepare("DELETE FROM video_comments WHERE id = ?")->execute([$cid]);
    $msg = '已刪除留言'; $msgType = 'success';
    $tab = 'comments';
}

// 標記留言檢舉已處理
if (isset($_POST['dismiss_comment_report'])) {
    $cid = (int)$_POST['comment_id'];
    $pdo->prepare("UPDATE comment_reports SET status = 'resolved' WHERE comment_id = ?")->execute([$cid]);
    $msg = '已標記為已處理'; $msgType = 'success';
    $tab = 'comments';
}

// 審核使用者商品申請
if (isset($_POST['review_submission'])) {
    $subId      = (int)$_POST['submission_id'];
    $decision   = $_POST['decision']   ?? '';   // approved / rejected
    $adminNote  = trim($_POST['admin_note'] ?? '');

    if ($subId && in_array($decision, ['approved', 'rejected'])) {
        // 更新申請狀態
        $pdo->prepare("UPDATE product_submissions SET status = ?, admin_note = ? WHERE id = ?")
            ->execute([$decision, $adminNote, $subId]);

        // 取得申請者資訊以寄送通知
        $sub = $pdo->prepare("SELECT ps.product_name, ps.username, u.email
                               FROM product_submissions ps
                               LEFT JOIN users u ON u.username = ps.username
                               WHERE ps.id = ?");
        $sub->execute([$subId]);
        $subRow = $sub->fetch();

        $emailSent = false;
        if ($subRow && !empty($subRow['email'])) {
            try {
                $emailSent = sendProductReviewEmail(
                    $subRow['email'],
                    $subRow['username'],
                    $subRow['product_name'],
                    $decision,
                    $adminNote
                );
            } catch (Throwable $e) {
                error_log('商品審核 Email 失敗：' . $e->getMessage());
            }
        }

        $label    = $decision === 'approved' ? '✅ 已核准' : '❌ 已拒絕';
        $mailNote = $emailSent ? '（通知信已寄出）' : ($subRow && !empty($subRow['email']) ? '（Email 設定未完成，未寄信）' : '（使用者無 Email，未寄信）');
        $msg      = "{$label}「" . ($subRow['product_name'] ?? '該商品') . "」{$mailNote}";
        $msgType = 'success';
    }
    $tab = 'products';
}

// 存入本月排名快照
if (isset($_POST['save_monthly_ranking'])) {
    $month = date('Y-m');
    try {
        // 刪除同月舊快照
        $pdo->prepare("DELETE FROM monthly_rankings WHERE month = ?")->execute([$month]);

        $types = [
            'product_views' => "SELECT p_id AS item_id, name AS item_name, view_count AS score FROM products ORDER BY view_count DESC LIMIT 10",
            'product_favs'  => "SELECT p.p_id AS item_id, p.name AS item_name, COUNT(f.id) AS score FROM products p LEFT JOIN product_favorites f ON f.product_id = p.p_id GROUP BY p.p_id, p.name ORDER BY score DESC LIMIT 10",
            'video_views'   => "SELECT id AS item_id, title AS item_name, view_count AS score FROM videos WHERE is_active = 1 ORDER BY view_count DESC LIMIT 10",
            'video_likes'   => "SELECT v.id AS item_id, v.title AS item_name, COUNT(l.id) AS score FROM videos v LEFT JOIN likes l ON l.video_id = v.id WHERE v.is_active = 1 GROUP BY v.id, v.title ORDER BY score DESC LIMIT 10",
        ];

        $ins = $pdo->prepare("INSERT INTO monthly_rankings (month, rank_type, rank_no, item_id, item_name, score) VALUES (?,?,?,?,?,?)");
        foreach ($types as $type => $sql) {
            $rows = $pdo->query($sql)->fetchAll();
            foreach ($rows as $i => $r) {
                $ins->execute([$month, $type, $i + 1, $r['item_id'], $r['item_name'], $r['score']]);
            }
        }
        $msg = "✅ 已儲存 {$month} 的排名快照"; $msgType = 'success';
    } catch (Throwable $e) {
        $msg = '❌ 儲存失敗：' . $e->getMessage(); $msgType = 'error';
    }
    $tab = 'stats';
}

// 標記檢舉為已處理
if (isset($_POST['dismiss_report'])) {
    $vid = (int)$_POST['video_id'];
    $pdo->prepare("UPDATE video_reports SET status = 'resolved' WHERE video_id = ?")
        ->execute([$vid]);
    $msg = '已標記為已處理'; $msgType = 'success';
    $tab = 'reports';
}

// ── 資料查詢 ───────────────────────────────────────────────

if ($tab === 'products') {
    $filterStatus = $_GET['status'] ?? 'pending';
    $allowedStatus = ['pending', 'approved', 'rejected', 'all'];
    if (!in_array($filterStatus, $allowedStatus)) $filterStatus = 'pending';

    if ($filterStatus === 'all') {
        $submissions = $pdo->query("
            SELECT ps.*, u.email
            FROM product_submissions ps
            LEFT JOIN users u ON u.username = ps.username
            ORDER BY ps.created_at DESC
        ")->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT ps.*, u.email
            FROM product_submissions ps
            LEFT JOIN users u ON u.username = ps.username
            WHERE ps.status = ?
            ORDER BY ps.created_at DESC
        ");
        $stmt->execute([$filterStatus]);
        $submissions = $stmt->fetchAll();
    }

    $pendingCount = $pdo->query("SELECT COUNT(*) FROM product_submissions WHERE status = 'pending'")->fetchColumn();
}

if ($tab === 'stats') {
    $stats['users']    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['videos']   = $pdo->query("SELECT COUNT(*) FROM videos WHERE is_active = 1")->fetchColumn();
    $stats['comments'] = $pdo->query("SELECT COUNT(*) FROM video_comments")->fetchColumn();
    $stats['reports']  = $pdo->query("SELECT COUNT(*) FROM video_reports WHERE status = 'pending'")->fetchColumn();
    $stats['new_users_week']   = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL '7 days'")->fetchColumn();
    $stats['pending_products'] = $pdo->query("SELECT COUNT(*) FROM product_submissions WHERE status = 'pending'")->fetchColumn();

    // 排名資料
    try {
        $rankData['product_views'] = $pdo->query("SELECT p_id AS item_id, name AS item_name, view_count AS score FROM products ORDER BY view_count DESC LIMIT 10")->fetchAll();
        $rankData['product_favs']  = $pdo->query("SELECT p.p_id AS item_id, p.name AS item_name, COUNT(f.id) AS score FROM products p LEFT JOIN product_favorites f ON f.product_id = p.p_id GROUP BY p.p_id, p.name ORDER BY score DESC LIMIT 10")->fetchAll();
        $rankData['video_views']   = $pdo->query("SELECT id AS item_id, title AS item_name, view_count AS score FROM videos WHERE is_active = 1 ORDER BY view_count DESC LIMIT 10")->fetchAll();
        $rankData['video_likes']   = $pdo->query("SELECT v.id AS item_id, v.title AS item_name, COUNT(l.id) AS score FROM videos v LEFT JOIN likes l ON l.video_id = v.id WHERE v.is_active = 1 GROUP BY v.id, v.title ORDER BY score DESC LIMIT 10")->fetchAll();
        $lastMonth = date('Y-m', strtotime('first day of last month'));
        $lastMonthRows = $pdo->prepare("SELECT * FROM monthly_rankings WHERE month = ? ORDER BY rank_type, rank_no");
        $lastMonthRows->execute([$lastMonth]);
        $lastMonthData = [];
        foreach ($lastMonthRows->fetchAll() as $r) {
            $lastMonthData[$r['rank_type']][] = $r;
        }
    } catch (Throwable $e) {
        $rankData = []; $lastMonthData = [];
    }
}

if ($tab === 'videos') {
    $search = trim($_GET['q'] ?? '');
    $sql = "
        SELECT v.id, v.title, v.uploaded_by, v.upload_time,
               COUNT(DISTINCT r.id) AS report_count,
               COUNT(DISTINCT c.id) AS comment_count
        FROM videos v
        LEFT JOIN video_reports r ON v.id = r.video_id AND r.status = 'pending'
        LEFT JOIN video_comments c ON v.id = c.video_id
        WHERE v.is_active = 1
    ";
    if ($search !== '') {
        $s = $pdo->prepare($sql . " AND (v.title LIKE ? OR v.uploaded_by LIKE ?) GROUP BY v.id ORDER BY v.upload_time DESC");
        $s->execute(["%{$search}%", "%{$search}%"]);
    } else {
        $s = $pdo->query($sql . " GROUP BY v.id ORDER BY v.upload_time DESC");
    }
    $allVideos = $s->fetchAll();
}

if ($tab === 'users') {
    // 自動停用：過去30天內被管理員標記的違規留言 >= 5 次
    try {
        $pdo->query("
            UPDATE users SET status = 'suspended', suspended_at = NOW()
            WHERE username IN (
                SELECT vc.username
                FROM video_comments vc
                JOIN comment_reports cr ON cr.comment_id = vc.id
                WHERE cr.status = 'resolved'
                  AND cr.created_at >= NOW() - INTERVAL '30 days'
                GROUP BY vc.username
                HAVING COUNT(cr.id) >= 5
            ) AND status = 'active' AND role != 'admin'
        ");
    } catch (Throwable $e) { /* ignore */ }

    // 查詢會員清單，含本月違規次數
    $allUsers = $pdo->query("
        SELECT u.username, u.email, u.role, u.email_verified, u.created_at,
               COALESCE(u.status, 'active') AS status,
               u.suspended_at,
               COUNT(cr.id) AS monthly_violations
        FROM users u
        LEFT JOIN video_comments vc ON vc.username = u.username
        LEFT JOIN comment_reports cr
               ON cr.comment_id = vc.id
              AND cr.status = 'resolved'
              AND cr.created_at >= NOW() - INTERVAL '30 days'
        GROUP BY u.username, u.email, u.role, u.email_verified,
                 u.created_at, u.status, u.suspended_at
        ORDER BY u.role DESC, monthly_violations DESC, u.created_at DESC
    ")->fetchAll();
}

if ($tab === 'reports') {
    $reportedVideos = $pdo->query("
        SELECT v.id, v.title, v.uploaded_by, v.file_path,
               COUNT(r.id)     AS report_count,
               MAX(r.created_at) AS last_reported_at
        FROM videos v
        JOIN video_reports r ON v.id = r.video_id
        WHERE r.status = 'pending'
        GROUP BY v.id
        ORDER BY report_count DESC, last_reported_at DESC
    ")->fetchAll();

    $reportDetails = $pdo->query("
        SELECT r.video_id, r.reported_by, r.reason, r.description, r.created_at
        FROM video_reports r
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC
    ")->fetchAll();
    $detailsByVideo = [];
    foreach ($reportDetails as $d) {
        $detailsByVideo[$d['video_id']][] = $d;
    }
}

if ($tab === 'comments') {
    $search = trim($_GET['q'] ?? '');
    $baseSql = "
        SELECT c.id, c.video_id, c.username, c.content, c.created_at, v.title AS video_title,
               COUNT(cr.id) AS report_count
        FROM video_comments c
        LEFT JOIN videos v ON c.video_id = v.id
        LEFT JOIN comment_reports cr ON c.id = cr.comment_id AND cr.status = 'pending'
    ";
    if ($search !== '') {
        $s = $pdo->prepare($baseSql . " WHERE c.username LIKE ? OR c.content LIKE ? GROUP BY c.id, c.video_id, c.username, c.content, c.created_at, v.title ORDER BY report_count DESC, c.created_at DESC");
        $s->execute(["%{$search}%", "%{$search}%"]);
    } else {
        $s = $pdo->query($baseSql . " GROUP BY c.id, c.video_id, c.username, c.content, c.created_at, v.title ORDER BY report_count DESC, c.created_at DESC");
    }
    $allComments = $s->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理後台</title>
@import url('https://cdn.jsdelivr.net/npm/lxgw-wenkai-tc-webfont@latest/style.css');
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'LXGW WenKai TC', '標楷體', 'BiauKai', 'DFKai-SB', serif; background: #f5f0f0; color: #3a2a2a; font-size: 15px; }

/* ── Topbar ── */
.adm-topbar {
    background: #fff;
    border-bottom: 1px solid #f0e8e8;
    padding: 0 32px; height: 60px;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 1px 6px rgba(180,100,110,0.07);
}
.adm-topbar-logo { font-size: 17px; font-weight: 700; color: #c47a8a; letter-spacing: .5px; }
.adm-topbar-right { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #9a8080; }
.adm-topbar-right a { color: #7a6060; text-decoration: none; padding: 7px 14px; border-radius: 8px; transition: background 0.2s; font-weight: 500; }
.adm-topbar-right a:hover { background: #f5eeee; color: #c47a8a; }
.logout-btn { color: #c47a8a !important; border: 1px solid #ecd8da !important; }
.logout-btn:hover { background: #fdf0f1 !important; }

/* ── Tabs ── */
.adm-tabs { background: #fff; border-bottom: 1px solid #f0e8e8; padding: 0 32px; display: flex; gap: 2px; }
.adm-tab { display: inline-block; padding: 15px 18px; text-decoration: none; color: #9a8080; font-size: 13px; font-weight: 600; border-bottom: 2.5px solid transparent; transition: all 0.2s; letter-spacing: .2px; }
.adm-tab:hover { color: #c47a8a; }
.adm-tab.active { color: #c47a8a; border-bottom-color: #c47a8a; }

/* ── Content ── */
.adm-content { max-width: 1160px; margin: 28px auto; padding: 0 24px; }
.adm-msg { padding: 13px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; font-weight: 500; }
.adm-msg.success { background: #eef7f1; color: #4a8a62; border-left: 3px solid #7aba96; }
.adm-msg.error   { background: #fdf0f0; color: #a05050; border-left: 3px solid #d08888; }

/* ── Stat cards ── */
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
@media(max-width:800px){ .stats-grid { grid-template-columns: repeat(2,1fr); } }
.stat-card { background: #fff; border-radius: 14px; padding: 18px 20px; box-shadow: 0 2px 10px rgba(180,100,110,0.07); display: flex; align-items: center; gap: 16px; border: 1px solid #f5eeee; }
.stat-icon { width: 46px; height: 46px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.stat-body { min-width: 0; }
.stat-num { font-size: 28px; font-weight: 800; line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 12px; color: #b09090; font-weight: 500; letter-spacing: .2px; white-space: nowrap; }

/* ── Section title ── */
.section-title { font-size: 16px; font-weight: 700; margin-bottom: 16px; color: #3a2a2a; letter-spacing: .3px; }

/* ── Table ── */
.adm-table-wrap { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(180,100,110,0.06); overflow: hidden; border: 1px solid #f5eeee; }
.adm-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.adm-table th { background: #fdf8f8; padding: 12px 16px; text-align: left; font-weight: 600; color: #9a7878; border-bottom: 1px solid #f0e8e8; font-size: 12px; letter-spacing: .3px; }
.adm-table td { padding: 12px 16px; border-bottom: 1px solid #f8f0f0; vertical-align: middle; color: #4a3535; }
.adm-table tbody tr:last-child td { border-bottom: none; }
.adm-table tbody tr:hover { background: #fdf8f8; }

/* ── Buttons ── */
.btn-del  { background: #f0e0e0; color: #a05050; border: none; border-radius: 7px; padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all .15s; }
.btn-del:hover { background: #c47878; color: #fff; }
.btn-role { background: #ece8f0; color: #6a5a80; border: none; border-radius: 7px; padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all .15s; }
.btn-role:hover { background: #8a78a8; color: #fff; }
.btn-role.is-admin { background: #dceaf8; color: #3a6a9a; }
.btn-role.is-admin:hover { background: #5a8ab8; color: #fff; }
.btn-ok { background: #e0f0e8; color: #4a8060; border: none; border-radius: 7px; padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all .15s; }
.btn-ok:hover { background: #6aaa84; color: #fff; }

/* ── Search ── */
.search-bar { display: flex; gap: 10px; margin-bottom: 18px; }
.search-bar input { flex: 1; padding: 10px 14px; border: 1.5px solid #f0e4e4; border-radius: 10px; font-size: 13px; font-family: inherit; color: #4a3535; outline: none; transition: border .2s; background: #fff; }
.search-bar input:focus { border-color: #d4a0a8; }
.search-bar button { padding: 10px 22px; background: #c47a8a; color: #fff; border: none; border-radius: 10px; cursor: pointer; font-size: 13px; font-weight: 600; font-family: inherit; transition: background .2s; }
.search-bar button:hover { background: #a85e70; }

/* ── Badges ── */
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; letter-spacing: .2px; }
.badge-admin { background: #dceaf8; color: #3a6090; }
.badge-user  { background: #f0ecec; color: #7a6060; }
.badge-ok    { background: #e0f0e8; color: #4a8060; }
.badge-no    { background: #f8e8e8; color: #a05050; }
.badge-warn  { background: #fdf3e0; color: #9a7030; }

/* ── Empty state ── */
.adm-empty { text-align: center; padding: 50px 20px; color: #c8b0b0; }
.adm-empty-icon { font-size: 38px; margin-bottom: 12px; }

/* ── Report detail ── */
.report-detail { background: #fdf8f8; padding: 14px 16px; margin-top: 8px; border-radius: 10px; border: 1px solid #f0e4e4; font-size: 13px; }
.report-detail-item { padding: 8px 0; border-bottom: 1px solid #f5eaea; }
.report-detail-item:last-child { border-bottom: none; }

/* ── Comment ── */
.comment-content { max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.btn-group { display: flex; gap: 6px; flex-wrap: wrap; }
</style>
</head>
<body>

<div class="adm-topbar">
    <div class="adm-topbar-logo">Cosmetic後台</div>
    <div class="adm-topbar-right">
        <span>管理員：<?php echo htmlspecialchars($adminUser); ?></span>
        <a href="/SA/New-SA/產品/index.php">← 返回網站</a>
        <a href="logout.php" class="logout-btn">登出</a>
    </div>
</div>

<div class="adm-tabs">
    <a href="?tab=stats"    class="adm-tab <?php echo $tab==='stats'    ? 'active':''; ?>"> 數據總覽</a>
    <a href="?tab=videos"   class="adm-tab <?php echo $tab==='videos'   ? 'active':''; ?>"> 影片管理</a>
    <a href="?tab=comments" class="adm-tab <?php echo $tab==='comments' ? 'active':''; ?>"> 留言管理</a>
    <a href="?tab=reports"  class="adm-tab <?php echo $tab==='reports'  ? 'active':''; ?>"> 檢舉管理</a>
    <a href="?tab=users"    class="adm-tab <?php echo $tab==='users'    ? 'active':''; ?>"> 會員管理</a>
    <a href="?tab=products" class="adm-tab <?php echo $tab==='products' ? 'active':''; ?>" style="position:relative;">
        商品審核
        <?php
        $badgeCount = $pdo->query("SELECT COUNT(*) FROM product_submissions WHERE status='pending'")->fetchColumn();
        if ($badgeCount > 0): ?>
            <span style="position:absolute;top:8px;right:4px;background:#e83e5a;color:#fff;font-size:10px;font-weight:700;border-radius:10px;padding:1px 6px;"><?php echo (int)$badgeCount; ?></span>
        <?php endif; ?>
    </a>
</div>

<div class="adm-content">

<?php if ($msg): ?>
    <div class="adm-msg <?php echo htmlspecialchars($msgType); ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<?php if ($tab === 'stats'): ?>
<!-- ══════════ 數據總覽 ══════════ -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#fdeaed;">👥</div>
        <div class="stat-body">
            <div class="stat-num" style="color:#c47a8a;"><?php echo $stats['users']; ?></div>
            <div class="stat-label">總使用者</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#e8f0fa;">✨</div>
        <div class="stat-body">
            <div class="stat-num" style="color:#6a90c4;"><?php echo $stats['new_users_week']; ?></div>
            <div class="stat-label">本週新增會員</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f0ebfa;">🎬</div>
        <div class="stat-body">
            <div class="stat-num" style="color:#9a78c4;"><?php echo $stats['videos']; ?></div>
            <div class="stat-label">影片總數</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#eaf5ee;">💬</div>
        <div class="stat-body">
            <div class="stat-num" style="color:#6aaa84;"><?php echo $stats['comments']; ?></div>
            <div class="stat-label">總留言數</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:<?php echo $stats['reports']>0?'#fde8e8':'#eaf5ee'; ?>;">🚨</div>
        <div class="stat-body">
            <div class="stat-num" style="color:<?php echo $stats['reports']>0?'#c47878':'#6aaa84'; ?>;"><?php echo $stats['reports']; ?></div>
            <div class="stat-label">待處理檢舉
                <?php if($stats['reports']>0): ?><a href="?tab=reports" style="color:#c47878;font-size:11px;margin-left:4px;">→ 處理</a><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:<?php echo $stats['pending_products']>0?'#fdf0e8':'#eaf5ee'; ?>;">🛍️</div>
        <div class="stat-body">
            <div class="stat-num" style="color:<?php echo $stats['pending_products']>0?'#c4946a':'#6aaa84'; ?>;"><?php echo $stats['pending_products']; ?></div>
            <div class="stat-label">待審核商品
                <?php if($stats['pending_products']>0): ?><a href="?tab=products" style="color:#c4946a;font-size:11px;margin-left:4px;">→ 審核</a><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══════════ 排名統計 ══════════ -->
<style>
.rank-section-header { display:flex; align-items:center; justify-content:space-between; margin:36px 0 16px; flex-wrap:wrap; gap:12px; }
.rank-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:14px; margin-bottom:8px; }
@media(max-width:700px){ .rank-grid { grid-template-columns:1fr; } }
.rank-block { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(180,100,110,0.07); overflow:hidden; border:1px solid #f5eeee; }
.rank-block-title { padding:13px 18px; font-weight:700; font-size:11px; display:flex; align-items:center; gap:8px; border-bottom:1px solid #faf0f0; letter-spacing:.5px; text-transform:uppercase; }
.rank-block-title .title-icon { font-size:14px; }
.rank-block-title .title-text { flex:1; }
.rank-block-title .title-count { font-size:10px; font-weight:500; opacity:.45; background:rgba(0,0,0,0.04); border-radius:4px; padding:1px 6px; }
.rank-row { display:flex; align-items:center; gap:12px; padding:10px 18px; border-bottom:1px solid #faf4f4; font-size:13px; text-decoration:none; color:inherit; transition:background .12s; }
.rank-row:last-child { border-bottom:none; }
.rank-row:hover { background:#fdf8f8; }
.rank-medal { min-width:20px; text-align:center; font-size:15px; line-height:1; }
.rank-no { min-width:20px; text-align:center; font-size:12px; color:#d0b8b8; font-weight:700; }
.rank-name { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#5a3a3a; font-size:13px; }
.rank-score { font-size:11px; font-weight:700; white-space:nowrap; padding:3px 10px; border-radius:20px; letter-spacing:.2px; }
.rank-empty { padding:22px 18px; color:#d0b8b8; font-size:12px; text-align:center; }
.rank-more { display:none; }
.rank-toggle { width:100%; background:none; border:none; border-top:1px solid #faf0f0; padding:9px; font-size:11px; color:#c8aaaa; cursor:pointer; text-align:center; transition:color .15s; font-family:inherit; font-weight:600; letter-spacing:.3px; }
.rank-toggle:hover { color:#c47a8a; background:#fdf8f8; }
.snap-btn { background:#fff; color:#c47a8a; border:1.5px solid #ecd8da; border-radius:10px; padding:9px 18px; cursor:pointer; font-size:12px; font-weight:700; font-family:inherit; transition:all .2s; letter-spacing:.3px; }
.snap-btn:hover { background:#fdf0f1; border-color:#d4a0a8; }
.rank-last-header { display:flex; align-items:center; gap:10px; margin:32px 0 14px; }
.rank-last-header h3 { font-size:15px; font-weight:700; color:#3a2a2a; margin:0; }
.rank-last-header span { font-size:11px; color:#b09090; background:#f5eeee; border-radius:6px; padding:2px 10px; font-weight:500; }
</style>

<?php
$rankLabels = [
    'product_views' => ['label'=>'產品・觀看數', 'icon'=>'👁️', 'hdr_bg'=>'#fff5f5', 'hdr_color'=>'#c47a7a', 'score_bg'=>'#fdeaea', 'score_color'=>'#c47a7a', 'unit'=>'次', 'link'=>'../產品/product.php?id='],
    'product_favs'  => ['label'=>'產品・收藏數', 'icon'=>'🤍', 'hdr_bg'=>'#fff8f0', 'hdr_color'=>'#c4986a', 'score_bg'=>'#fdeedd', 'score_color'=>'#c4986a', 'unit'=>'人', 'link'=>'../產品/product.php?id='],
    'video_views'   => ['label'=>'影片・觀看數', 'icon'=>'▶️', 'hdr_bg'=>'#f0f6fc', 'hdr_color'=>'#5a90b8', 'score_bg'=>'#deedf8', 'score_color'=>'#5a90b8', 'unit'=>'次', 'link'=>'video.php?video='],
    'video_likes'   => ['label'=>'影片・按讚數', 'icon'=>'💜', 'hdr_bg'=>'#f6f3fc', 'hdr_color'=>'#8a72b8', 'score_bg'=>'#ebe5f8', 'score_color'=>'#8a72b8', 'unit'=>'讚', 'link'=>'video.php?video='],
];
$medals = ['🥇','🥈','🥉'];
?>

<div class="rank-section-header">
    <div class="section-title" style="margin:0;">🏆 排名統計</div>
    <form method="post" onsubmit="return confirm('存入 <?php echo date('Y-m'); ?> 的排名快照？');">
        <button type="submit" name="save_monthly_ranking" value="1" class="snap-btn">📸 存入本月快照</button>
    </form>
</div>

<div class="rank-grid">
<?php foreach ($rankLabels as $type => $info): ?>
<div class="rank-block">
    <div class="rank-block-title" style="background:<?php echo $info['hdr_bg']; ?>; color:<?php echo $info['hdr_color']; ?>;">
        <span class="title-icon"><?php echo $info['icon']; ?></span>
        <span class="title-text"><?php echo $info['label']; ?></span>
        <span class="title-count">TOP 10</span>
    </div>
    <?php if (empty($rankData[$type])): ?>
        <div class="rank-empty">尚無資料</div>
    <?php else: ?>
        <?php $hasMore = count($rankData[$type]) > 3; $uid = $type . '_now'; ?>
        <?php foreach ($rankData[$type] as $i => $r): ?>
        <a class="rank-row <?php echo $i >= 3 ? 'rank-more' : ''; ?>" data-group="<?php echo $uid; ?>"
           href="<?php echo $info['link'] . (int)$r['item_id']; ?>" target="_blank">
            <?php if ($i < 3): ?>
                <span class="rank-medal"><?php echo $medals[$i]; ?></span>
            <?php else: ?>
                <span class="rank-no"><?php echo $i + 1; ?></span>
            <?php endif; ?>
            <span class="rank-name" title="<?php echo htmlspecialchars($r['item_name']); ?>"><?php echo htmlspecialchars($r['item_name']); ?></span>
            <span class="rank-score" style="background:<?php echo $info['score_bg']; ?>;color:<?php echo $info['score_color']; ?>;"><?php echo (int)$r['score']; ?> <?php echo $info['unit']; ?></span>
        </a>
        <?php endforeach; ?>
        <?php if ($hasMore): ?>
        <button class="rank-toggle" onclick="toggleRank('<?php echo $uid; ?>', this)">▾ 查看更多</button>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<!-- 上個月快照 -->
<?php $lastMonth = date('Y-m', strtotime('first day of last month')); ?>
<div class="rank-last-header">
    <h3>上個月排名</h3>
    <span><?php echo $lastMonth; ?></span>
</div>
<?php if (empty($lastMonthData)): ?>
    <div style="background:#fff;border-radius:14px;padding:28px;text-align:center;color:#ccc;font-size:13px;box-shadow:0 1px 6px rgba(0,0,0,0.05);">
        尚無快照紀錄。點「存入本月快照」，下個月即可在此查閱歷史排名。
    </div>
<?php else: ?>
    <div class="rank-grid">
    <?php foreach ($rankLabels as $type => $info): ?>
    <div class="rank-block">
        <div class="rank-block-title" style="background:<?php echo $info['hdr_bg']; ?>; color:<?php echo $info['hdr_color']; ?>;">
            <span class="title-icon"><?php echo $info['icon']; ?></span>
            <span class="title-text"><?php echo $info['label']; ?></span>
        </div>
        <?php if (empty($lastMonthData[$type])): ?>
            <div class="rank-empty">無紀錄</div>
        <?php else: ?>
            <?php $hasMoreL = count($lastMonthData[$type]) > 3; $uidL = $type . '_last'; ?>
            <?php foreach ($lastMonthData[$type] as $idx => $r): ?>
            <a class="rank-row <?php echo $idx >= 3 ? 'rank-more' : ''; ?>" data-group="<?php echo $uidL; ?>"
               href="<?php echo $info['link'] . (int)$r['item_id']; ?>" target="_blank">
                <?php $n = (int)$r['rank_no']; ?>
                <?php if ($n <= 3): ?>
                    <span class="rank-medal"><?php echo $medals[$n-1]; ?></span>
                <?php else: ?>
                    <span class="rank-no"><?php echo $n; ?></span>
                <?php endif; ?>
                <span class="rank-name" title="<?php echo htmlspecialchars($r['item_name']); ?>"><?php echo htmlspecialchars($r['item_name']); ?></span>
                <span class="rank-score" style="background:<?php echo $info['score_bg']; ?>;color:<?php echo $info['score_color']; ?>;"><?php echo (int)$r['score']; ?> <?php echo $info['unit']; ?></span>
            </a>
            <?php endforeach; ?>
            <?php if ($hasMoreL): ?>
            <button class="rank-toggle" onclick="toggleRank('<?php echo $uidL; ?>', this)">▾ 查看更多</button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>


<?php elseif ($tab === 'videos'): ?>
<!-- ══════════ 影片管理 ══════════ -->
<form class="search-bar" method="get">
    <input type="hidden" name="tab" value="videos">
    <input type="text" name="q" placeholder="搜尋影片標題或上傳者..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
    <button type="submit">搜尋</button>
</form>

<?php if (empty($allVideos)): ?>
    <div class="adm-empty"><div class="adm-empty-icon">🎬</div>找不到影片</div>
<?php else: ?>
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>標題</th>
                    <th>上傳者</th>
                    <th>上傳時間</th>
                    <th style="text-align:center;">留言</th>
                    <th style="text-align:center;">檢舉</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allVideos as $v): ?>
                    <tr>
                        <td style="color:#aaa;">#<?php echo (int)$v['id']; ?></td>
                        <td><?php echo htmlspecialchars($v['title']); ?></td>
                        <td><?php echo htmlspecialchars($v['uploaded_by']); ?></td>
                        <td><?php echo date('Y/m/d H:i', strtotime($v['upload_time'])); ?></td>
                        <td style="text-align:center;">💬 <?php echo (int)$v['comment_count']; ?></td>
                        <td style="text-align:center;">
                            <?php if ($v['report_count'] > 0): ?>
                                <span class="badge badge-warn">⚠️ <?php echo (int)$v['report_count']; ?></span>
                            <?php else: ?>
                                <span style="color:#ccc;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" onsubmit="return confirm('確定刪除「<?php echo htmlspecialchars(addslashes($v['title'])); ?>」？')">
                                <input type="hidden" name="video_id" value="<?php echo (int)$v['id']; ?>">
                                <button type="submit" name="force_delete_video" value="1" class="btn-del">🗑️ 刪除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="color:#aaa;font-size:13px;margin-top:10px;">共 <?php echo count($allVideos); ?> 筆</p>
<?php endif; ?>


<?php elseif ($tab === 'comments'): ?>
<!-- ══════════ 留言管理 ══════════ -->
<form class="search-bar" method="get">
    <input type="hidden" name="tab" value="comments">
    <input type="text" name="q" placeholder="搜尋留言者或留言內容..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
    <button type="submit">搜尋</button>
</form>

<?php if (empty($allComments)): ?>
    <div class="adm-empty"><div class="adm-empty-icon">💬</div>目前沒有留言</div>
<?php else: ?>
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>留言者</th>
                    <th>所屬影片</th>
                    <th>留言內容</th>
                    <th style="text-align:center;">檢舉</th>
                    <th>時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allComments as $c): ?>
                    <tr <?php echo $c['report_count'] > 0 ? 'style="background:#fffbf0;"' : ''; ?>>
                        <td><strong><?php echo htmlspecialchars($c['username']); ?></strong></td>
                        <td style="color:#888;font-size:12px;"><?php echo htmlspecialchars($c['video_title'] ?? '（已刪除）'); ?></td>
                        <td>
                            <div class="comment-content" title="<?php echo htmlspecialchars($c['content']); ?>">
                                <?php echo htmlspecialchars($c['content']); ?>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($c['report_count'] > 0): ?>
                                <span class="badge badge-warn">⚠️ <?php echo (int)$c['report_count']; ?></span>
                            <?php else: ?>
                                <span style="color:#ccc;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#aaa;font-size:12px;"><?php echo date('m/d H:i', strtotime($c['created_at'])); ?></td>
                        <td>
                            <div class="btn-group">
                                <?php if ($c['report_count'] > 0): ?>
                                    <form method="post">
                                        <input type="hidden" name="comment_id" value="<?php echo (int)$c['id']; ?>">
                                        <button type="submit" name="dismiss_comment_report" value="1" class="btn-ok">✓ 已處理</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" onsubmit="return confirm('確定刪除這則留言？')">
                                    <input type="hidden" name="comment_id" value="<?php echo (int)$c['id']; ?>">
                                    <button type="submit" name="delete_comment" value="1" class="btn-del">🗑️ 刪除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="color:#aaa;font-size:13px;margin-top:10px;">共 <?php echo count($allComments); ?> 則留言</p>
<?php endif; ?>


<?php elseif ($tab === 'reports'): ?>
<!-- ══════════ 檢舉管理 ══════════ -->
<?php if (empty($reportedVideos)): ?>
    <div class="adm-empty"><div class="adm-empty-icon">✅</div>目前沒有待處理的檢舉</div>
<?php else: ?>
    <?php foreach ($reportedVideos as $rv): ?>
        <div class="adm-table-wrap" style="margin-bottom:20px;">
            <div style="padding:16px 20px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <div>
                    <div style="font-weight:700;font-size:15px;">
                        <?php echo htmlspecialchars($rv['title']); ?>
                        <span class="badge badge-warn" style="margin-left:8px;">⚠️ <?php echo (int)$rv['report_count']; ?> 件檢舉</span>
                    </div>
                    <div style="color:#888;font-size:12px;margin-top:4px;">
                        上傳者：<?php echo htmlspecialchars($rv['uploaded_by']); ?> &nbsp;·&nbsp;
                        最後檢舉：<?php echo date('m/d H:i', strtotime($rv['last_reported_at'])); ?>
                    </div>
                </div>
                <div class="btn-group">
                    <a href="video.php?video=<?php echo (int)$rv['id']; ?>" target="_blank" style="background:#6c757d;color:#fff;border-radius:6px;padding:7px 12px;font-size:12px;text-decoration:none;">▶ 查看影片</a>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="video_id" value="<?php echo (int)$rv['id']; ?>">
                        <button type="submit" name="dismiss_report" value="1" class="btn-ok">✓ 標記已處理</button>
                    </form>
                    <form method="post" style="display:inline;" onsubmit="return confirm('確定強制刪除這部影片？')">
                        <input type="hidden" name="video_id" value="<?php echo (int)$rv['id']; ?>">
                        <input type="hidden" name="from_reports" value="1">
                        <button type="submit" name="force_delete_video" value="1" class="btn-del">🗑️ 強制刪除</button>
                    </form>
                </div>
            </div>
            <?php if (!empty($detailsByVideo[$rv['id']])): ?>
                <div style="padding:12px 20px;">
                    <div style="font-size:13px;font-weight:600;color:#555;margin-bottom:10px;">檢舉明細</div>
                    <?php foreach ($detailsByVideo[$rv['id']] as $d): ?>
                        <div class="report-detail-item">
                            <span style="font-weight:600;color:#333;"><?php echo htmlspecialchars($d['reported_by']); ?></span>
                            <span class="badge badge-warn" style="margin:0 8px;"><?php echo htmlspecialchars($d['reason']); ?></span>
                            <span style="color:#555;"><?php echo htmlspecialchars($d['description']); ?></span>
                            <span style="color:#bbb;font-size:11px;margin-left:8px;"><?php echo date('m/d H:i', strtotime($d['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>


<?php elseif ($tab === 'products'): ?>
<!-- ══════════ 商品審核 ══════════ -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap;">
    <div class="section-title" style="margin:0;">🛍️ 商品申請審核</div>
    <div style="display:flex;gap:6px;margin-left:auto;">
        <?php foreach(['pending'=>'待審核','approved'=>'已通過','rejected'=>'已拒絕','all'=>'全部'] as $s=>$label): ?>
            <a href="?tab=products&status=<?php echo $s; ?>"
               style="padding:5px 14px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;
                      <?php echo ($filterStatus===$s) ? 'background:#e83e5a;color:#fff;' : 'background:#f0f0f0;color:#555;'; ?>">
                <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($submissions)): ?>
    <div class="adm-empty"><div class="adm-empty-icon">🛍️</div>目前沒有<?php echo $filterStatus==='pending'?'待審核的':($filterStatus==='all'?'':($filterStatus==='approved'?'已通過的':'已拒絕的')); ?>商品申請</div>
<?php else: ?>
    <?php foreach ($submissions as $sub): ?>
        <?php
            $statusBadge = match($sub['status']) {
                'pending'  => '<span class="badge badge-warn">⏳ 待審核</span>',
                'approved' => '<span class="badge badge-ok">✅ 已通過</span>',
                'rejected' => '<span class="badge badge-no">❌ 已拒絕</span>',
                default    => ''
            };
        ?>
        <div class="adm-table-wrap" style="margin-bottom:16px;">
            <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div style="flex:1;min-width:240px;">
                    <div style="font-size:16px;font-weight:700;color:#222;margin-bottom:4px;">
                        <?php echo htmlspecialchars($sub['product_name']); ?>
                        <?php echo $statusBadge; ?>
                    </div>
                    <div style="font-size:13px;color:#888;line-height:1.8;">
                        <?php if ($sub['brand']): ?>品牌：<?php echo htmlspecialchars($sub['brand']); ?> &nbsp;·&nbsp; <?php endif; ?>
                        <?php if ($sub['category']): ?>分類：<?php echo htmlspecialchars($sub['category']); ?> &nbsp;·&nbsp; <?php endif; ?>
                        <?php if ($sub['price']): ?>售價：<?php echo htmlspecialchars($sub['price']); ?> &nbsp;·&nbsp; <?php endif; ?>
                        申請者：<strong><?php echo htmlspecialchars($sub['username']); ?></strong>
                        （<?php echo $sub['email'] ? htmlspecialchars($sub['email']) : '無 Email'; ?>）<br>
                        申請時間：<?php echo date('Y/m/d H:i', strtotime($sub['created_at'])); ?>
                    </div>
                    <?php if ($sub['description']): ?>
                        <div style="margin-top:8px;font-size:13px;color:#555;background:#f8f8f8;border-radius:6px;padding:10px 12px;">
                            <?php echo nl2br(htmlspecialchars($sub['description'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($sub['purchase_link']): ?>
                        <div style="margin-top:6px;font-size:12px;">
                            <a href="<?php echo htmlspecialchars($sub['purchase_link']); ?>" target="_blank" rel="noopener"
                               style="color:#0069d9;">🔗 查看購買連結</a>
                        </div>
                    <?php endif; ?>
                    <?php if ($sub['admin_note'] && $sub['status'] !== 'pending'): ?>
                        <div style="margin-top:8px;font-size:12px;color:#777;border-left:3px solid #ddd;padding-left:10px;">
                            管理員備註：<?php echo htmlspecialchars($sub['admin_note']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($sub['status'] === 'pending'): ?>
                <div style="min-width:260px;">
                    <form method="post">
                        <input type="hidden" name="submission_id" value="<?php echo (int)$sub['id']; ?>">
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">管理員備註（寄信時顯示）</label>
                        <textarea name="admin_note" rows="3" placeholder="可填入核准原因、建議修改內容等..."
                            style="width:100%;padding:8px 10px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;font-family:inherit;box-sizing:border-box;margin-bottom:8px;"></textarea>
                        <div class="btn-group">
                            <button type="submit" name="review_submission" value="1"
                                onclick="this.form.querySelector('[name=decision]').value='approved';return confirm('確定核准「<?php echo htmlspecialchars(addslashes($sub['product_name'])); ?>」？');"
                                class="btn-ok" style="flex:1;padding:8px 0;">✅ 核准</button>
                            <input type="hidden" name="decision" value="">
                            <button type="submit" name="review_submission" value="1"
                                onclick="this.form.querySelector('[name=decision]').value='rejected';return confirm('確定拒絕「<?php echo htmlspecialchars(addslashes($sub['product_name'])); ?>」？');"
                                class="btn-del" style="flex:1;padding:8px 0;">❌ 拒絕</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <p style="color:#aaa;font-size:13px;margin-top:4px;">共 <?php echo count($submissions); ?> 筆</p>
<?php endif; ?>


<?php elseif ($tab === 'users'): ?>
<!-- ══════════ 會員管理 ══════════ -->
<style>
.badge-suspended { background:#f0e0e0; color:#a05050; }
.vio-bar { display:inline-flex; gap:3px; vertical-align:middle; }
.vio-dot { width:10px; height:10px; border-radius:50%; }
.vio-dot.filled { background:#e05050; }
.vio-dot.empty  { background:#f0e0e0; }
.tr-suspended td { background:#fff8f8 !important; opacity:.85; }
</style>

<?php
$suspendedCount = count(array_filter($allUsers, fn($u) => ($u['status'] ?? 'active') === 'suspended'));
$warningCount   = count(array_filter($allUsers, fn($u) => ($u['monthly_violations'] ?? 0) >= 3 && ($u['status'] ?? 'active') !== 'suspended'));
?>

<!-- 狀態摘要 -->
<div style="display:flex;gap:12px;margin-bottom:18px;flex-wrap:wrap;">
    <div style="background:#fff;border-radius:10px;padding:12px 20px;border:1px solid #f0e8e8;font-size:13px;">
        👥 總會員 <strong><?php echo count($allUsers); ?></strong>
    </div>
    <?php if ($warningCount > 0): ?>
    <div style="background:#fff8e8;border-radius:10px;padding:12px 20px;border:1px solid #f0d890;font-size:13px;color:#8a6020;">
        ⚠️ 違規警告中 <strong><?php echo $warningCount; ?></strong> 人（3次以上）
    </div>
    <?php endif; ?>
    <?php if ($suspendedCount > 0): ?>
    <div style="background:#fff0f0;border-radius:10px;padding:12px 20px;border:1px solid #f0c0c0;font-size:13px;color:#a05050;">
        🚫 已停用 <strong><?php echo $suspendedCount; ?></strong> 人
    </div>
    <?php endif; ?>
    <div style="background:#f0fff8;border-radius:10px;padding:12px 20px;border:1px solid #a0d8b0;font-size:13px;color:#406050;">
        ℹ️ 違規定義：30天內留言被管理員標記「已處理」達 <strong>5</strong> 次即自動停用
    </div>
</div>

<?php if (empty($allUsers)): ?>
    <div class="adm-empty"><div class="adm-empty-icon">👥</div>沒有使用者資料</div>
<?php else: ?>
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>帳號</th>
                    <th>Email</th>
                    <th>身份</th>
                    <th>信箱驗證</th>
                    <th style="text-align:center;">本月違規</th>
                    <th>狀態</th>
                    <th>加入時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allUsers as $u):
                    $isSuspended = ($u['status'] ?? 'active') === 'suspended';
                    $violations  = (int)($u['monthly_violations'] ?? 0);
                    $isWarning   = $violations >= 3 && !$isSuspended;
                ?>
                    <tr class="<?php echo $isSuspended ? 'tr-suspended' : ''; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                            <?php if ($isSuspended): ?>
                                <div style="font-size:11px;color:#c05050;margin-top:2px;">
                                    停用於 <?php echo $u['suspended_at'] ? date('m/d H:i', strtotime($u['suspended_at'])) : '—'; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="color:#888;font-size:12px;"><?php echo htmlspecialchars($u['email'] ?: '—'); ?></td>
                        <td>
                            <span class="badge <?php echo $u['role']==='admin' ? 'badge-admin' : 'badge-user'; ?>">
                                <?php echo $u['role']==='admin' ? '管理員' : '一般'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $u['email_verified'] ? 'badge-ok' : 'badge-no'; ?>">
                                <?php echo $u['email_verified'] ? '已驗證' : '未驗證'; ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($u['role'] === 'admin'): ?>
                                <span style="color:#ccc;font-size:12px;">—</span>
                            <?php else: ?>
                                <!-- 5格圓點視覺化 -->
                                <div class="vio-bar" title="本月違規 <?php echo $violations; ?>/5 次">
                                    <?php for ($vi = 1; $vi <= 5; $vi++): ?>
                                        <div class="vio-dot <?php echo $vi <= $violations ? 'filled' : 'empty'; ?>"></div>
                                    <?php endfor; ?>
                                </div>
                                <span style="font-size:11px;color:<?php echo $violations>=5?'#c05050':($isWarning?'#c08020':'#aaa'); ?>;margin-left:4px;">
                                    <?php echo $violations; ?>/5
                                    <?php if ($violations >= 5): ?> 已達上限<?php elseif ($isWarning): ?> ⚠️<?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isSuspended): ?>
                                <span class="badge badge-suspended">🚫 已停用</span>
                            <?php elseif ($isWarning): ?>
                                <span class="badge badge-warn">⚠️ 警告</span>
                            <?php else: ?>
                                <span class="badge badge-ok">正常</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#aaa;font-size:12px;"><?php echo $u['created_at'] ? date('Y/m/d', strtotime($u['created_at'])) : '—'; ?></td>
                        <td>
                            <?php if ($u['username'] !== $adminUser): ?>
                                <div class="btn-group">
                                    <?php if ($u['role'] !== 'admin'): ?>
                                        <!-- 身份切換 -->
                                        <form method="post" onsubmit="return confirm('確定變更「<?php echo htmlspecialchars(addslashes($u['username'])); ?>」的身份？')">
                                            <input type="hidden" name="target_user"  value="<?php echo htmlspecialchars($u['username']); ?>">
                                            <input type="hidden" name="current_role" value="<?php echo htmlspecialchars($u['role']); ?>">
                                            <button type="submit" name="toggle_role" value="1" class="btn-role">升為管理員</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" onsubmit="return confirm('確定降級「<?php echo htmlspecialchars(addslashes($u['username'])); ?>」？')">
                                            <input type="hidden" name="target_user"  value="<?php echo htmlspecialchars($u['username']); ?>">
                                            <input type="hidden" name="current_role" value="admin">
                                            <button type="submit" name="toggle_role" value="1" class="btn-role is-admin">降為一般</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($isSuspended): ?>
                                        <!-- 恢復帳號 -->
                                        <form method="post" onsubmit="return confirm('確定恢復「<?php echo htmlspecialchars(addslashes($u['username'])); ?>」的帳號？')">
                                            <input type="hidden" name="target_user" value="<?php echo htmlspecialchars($u['username']); ?>">
                                            <button type="submit" name="restore_user" value="1" class="btn-ok">✓ 恢復</button>
                                        </form>
                                    <?php elseif ($u['role'] !== 'admin'): ?>
                                        <!-- 停用帳號 -->
                                        <form method="post" onsubmit="return confirm('確定停用「<?php echo htmlspecialchars(addslashes($u['username'])); ?>」帳號？')">
                                            <input type="hidden" name="target_user" value="<?php echo htmlspecialchars($u['username']); ?>">
                                            <button type="submit" name="suspend_user" value="1" class="btn-del">🚫 停用</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#aaa;font-size:12px;">（目前帳號）</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="color:#aaa;font-size:13px;margin-top:10px;">共 <?php echo count($allUsers); ?> 位使用者</p>
<?php endif; ?>

<?php endif; ?>
</div>
<script>
function toggleRank(group, btn) {
    var rows = document.querySelectorAll('[data-group="' + group + '"].rank-more');
    var expanded = btn.dataset.expanded === '1';
    rows.forEach(function(r) { r.style.display = expanded ? 'none' : 'flex'; });
    btn.dataset.expanded = expanded ? '0' : '1';
    btn.textContent = expanded ? '▾ 查看更多' : '▴ 收起';
}
</script>
</body>
</html>
