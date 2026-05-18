<?php
session_start();
require 'db.php';

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

// 刪除使用者
if (isset($_POST['delete_user'])) {
    $targetUser = trim($_POST['target_user'] ?? '');
    if ($targetUser && $targetUser !== $adminUser) {
        $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$targetUser]);
        $msg = "已刪除使用者「{$targetUser}」"; $msgType = 'success';
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

// 標記檢舉為已處理
if (isset($_POST['dismiss_report'])) {
    $vid = (int)$_POST['video_id'];
    $pdo->prepare("UPDATE video_reports SET status = 'resolved' WHERE video_id = ?")
        ->execute([$vid]);
    $msg = '已標記為已處理'; $msgType = 'success';
    $tab = 'reports';
}

// ── 資料查詢 ───────────────────────────────────────────────

if ($tab === 'stats') {
    $stats['users']    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['videos']   = $pdo->query("SELECT COUNT(*) FROM videos WHERE is_active = 1")->fetchColumn();
    $stats['comments'] = $pdo->query("SELECT COUNT(*) FROM video_comments")->fetchColumn();
    $stats['reports']  = $pdo->query("SELECT COUNT(*) FROM video_reports WHERE status = 'pending'")->fetchColumn();
    $stats['new_users_week'] = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL '7 days'")->fetchColumn();
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
    $allUsers = $pdo->query("
        SELECT username, email, role, email_verified, created_at
        FROM users ORDER BY role DESC, created_at DESC
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
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f2f5; color: #333; }

.adm-topbar {
    background: #1a1a2e; color: #fff;
    padding: 0 28px; height: 56px;
    display: flex; align-items: center; justify-content: space-between;
}
.adm-topbar-logo { font-size: 18px; font-weight: 700; letter-spacing: 1px; }
.adm-topbar-right { display: flex; align-items: center; gap: 16px; font-size: 13px; }
.adm-topbar-right a { color: #ccc; text-decoration: none; padding: 6px 12px; border-radius: 6px; transition: background 0.2s; }
.adm-topbar-right a:hover { background: rgba(255,255,255,0.12); color: #fff; }
.logout-btn { background: rgba(220,53,69,0.2) !important; color: #ff8a96 !important; }
.logout-btn:hover { background: rgba(220,53,69,0.4) !important; color: #fff !important; }

.adm-tabs { background: #fff; border-bottom: 1px solid #e0e0e0; padding: 0 28px; display: flex; gap: 4px; }
.adm-tab { display: inline-block; padding: 14px 20px; text-decoration: none; color: #666; font-size: 14px; font-weight: 600; border-bottom: 3px solid transparent; transition: all 0.2s; }
.adm-tab:hover { color: #e83e5a; }
.adm-tab.active { color: #e83e5a; border-bottom-color: #e83e5a; }

.adm-content { max-width: 1200px; margin: 28px auto; padding: 0 20px; }
.adm-msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
.adm-msg.success { background: #d4edda; color: #155724; }
.adm-msg.error   { background: #f8d7da; color: #721c24; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card { background: #fff; border-radius: 12px; padding: 22px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: center; }
.stat-num { font-size: 36px; font-weight: 800; color: #e83e5a; line-height: 1; margin-bottom: 6px; }
.stat-label { font-size: 13px; color: #888; }

.section-title { font-size: 18px; font-weight: 700; margin-bottom: 16px; color: #222; }

.adm-table-wrap { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
.adm-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.adm-table th { background: #f8f8f8; padding: 12px 16px; text-align: left; font-weight: 600; color: #555; border-bottom: 1px solid #eee; }
.adm-table td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.adm-table tbody tr:last-child td { border-bottom: none; }
.adm-table tbody tr:hover { background: #fafafa; }

.btn-del  { background: #dc3545; color: #fff; border: none; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 12px; }
.btn-del:hover { background: #c82333; }
.btn-role { background: #6c757d; color: #fff; border: none; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 12px; }
.btn-role:hover { background: #545b62; }
.btn-role.is-admin { background: #0069d9; }
.btn-role.is-admin:hover { background: #0056b3; }
.btn-ok { background: #28a745; color: #fff; border: none; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 12px; }
.btn-ok:hover { background: #218838; }

.search-bar { display: flex; gap: 10px; margin-bottom: 18px; }
.search-bar input { flex: 1; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
.search-bar button { padding: 10px 20px; background: #e83e5a; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; }

.badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.badge-admin { background: #cce5ff; color: #004085; }
.badge-user  { background: #e2e3e5; color: #383d41; }
.badge-ok    { background: #d4edda; color: #155724; }
.badge-no    { background: #f8d7da; color: #721c24; }
.badge-warn  { background: #fff3cd; color: #856404; }

.adm-empty { text-align: center; padding: 50px 20px; color: #bbb; }
.adm-empty-icon { font-size: 40px; margin-bottom: 12px; }

/* 檢舉展開區塊 */
.report-detail { background: #fafafa; padding: 14px 16px; margin-top: 8px; border-radius: 8px; border: 1px solid #eee; font-size: 13px; }
.report-detail-item { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.report-detail-item:last-child { border-bottom: none; }

/* 留言內容截斷 */
.comment-content { max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.btn-group { display: flex; gap: 6px; flex-wrap: wrap; }
</style>
</head>
<body>

<div class="adm-topbar">
    <div class="adm-topbar-logo">🎀 彩妝管理後台</div>
    <div class="adm-topbar-right">
        <span>管理員：<?php echo htmlspecialchars($adminUser); ?></span>
        <a href="/SA/New-SA/產品/index.php">← 返回網站</a>
        <a href="logout.php" class="logout-btn">登出</a>
    </div>
</div>

<div class="adm-tabs">
    <a href="?tab=stats"    class="adm-tab <?php echo $tab==='stats'    ? 'active':''; ?>">📊 數據總覽</a>
    <a href="?tab=videos"   class="adm-tab <?php echo $tab==='videos'   ? 'active':''; ?>">🎬 影片管理</a>
    <a href="?tab=comments" class="adm-tab <?php echo $tab==='comments' ? 'active':''; ?>">💬 留言管理</a>
    <a href="?tab=reports"  class="adm-tab <?php echo $tab==='reports'  ? 'active':''; ?>">🚨 檢舉管理</a>
    <a href="?tab=users"    class="adm-tab <?php echo $tab==='users'    ? 'active':''; ?>">👥 會員管理</a>
</div>

<div class="adm-content">

<?php if ($msg): ?>
    <div class="adm-msg <?php echo htmlspecialchars($msgType); ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<?php if ($tab === 'stats'): ?>
<!-- ══════════ 數據總覽 ══════════ -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?php echo $stats['users']; ?></div>
        <div class="stat-label">總使用者</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#0069d9;"><?php echo $stats['new_users_week']; ?></div>
        <div class="stat-label">本週新增會員</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?php echo $stats['videos']; ?></div>
        <div class="stat-label">影片總數</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#6f42c1;"><?php echo $stats['comments']; ?></div>
        <div class="stat-label">總留言數</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:<?php echo $stats['reports'] > 0 ? '#c82333' : '#28a745'; ?>">
            <?php echo $stats['reports']; ?>
        </div>
        <div class="stat-label">待處理檢舉</div>
    </div>
</div>

<?php if ($stats['reports'] > 0): ?>
<div style="background:#fff3cd;color:#856404;border-radius:10px;padding:14px 20px;margin-bottom:24px;font-size:14px;">
    ⚠️ 目前有 <strong><?php echo $stats['reports']; ?></strong> 件待處理的影片檢舉，
    <a href="?tab=reports" style="color:#c82333;font-weight:600;">前往處理 →</a>
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


<?php elseif ($tab === 'users'): ?>
<!-- ══════════ 會員管理 ══════════ -->
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
                    <th>加入時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allUsers as $u): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                        <td style="color:#888;"><?php echo htmlspecialchars($u['email'] ?: '—'); ?></td>
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
                        <td style="color:#aaa;font-size:12px;"><?php echo $u['created_at'] ? date('Y/m/d', strtotime($u['created_at'])) : '—'; ?></td>
                        <td>
                            <?php if ($u['username'] !== $adminUser): ?>
                                <div class="btn-group">
                                    <form method="post" onsubmit="return confirm('確定變更「<?php echo htmlspecialchars(addslashes($u['username'])); ?>」的身份？')">
                                        <input type="hidden" name="target_user"  value="<?php echo htmlspecialchars($u['username']); ?>">
                                        <input type="hidden" name="current_role" value="<?php echo htmlspecialchars($u['role']); ?>">
                                        <button type="submit" name="toggle_role" value="1"
                                            class="btn-role <?php echo $u['role']==='admin' ? 'is-admin' : ''; ?>">
                                            <?php echo $u['role']==='admin' ? '降為一般' : '升為管理員'; ?>
                                        </button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('確定刪除「<?php echo htmlspecialchars(addslashes($u['username'])); ?>」帳號？此操作不可回復！')">
                                        <input type="hidden" name="target_user" value="<?php echo htmlspecialchars($u['username']); ?>">
                                        <button type="submit" name="delete_user" value="1" class="btn-del">🗑️ 刪除</button>
                                    </form>
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
</body>
</html>
